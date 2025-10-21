<?php
// src/actions/tickets_api.php
declare(strict_types=1);

/**
 * Tickets API for originofcoffee.org
 *
 * Actions:
 *  - create (POST): create ticket(s) and return {ok:true, checkout_url: '...'}
 *    Required POST: ticket_type_id, full_name, email, phone
 *
 *  - verify (GET/POST): verify by reference (reference param). Calls Chapa verify and marks ticket as PAID.
 *    Required: reference
 *
 *  - webhook (POST): handle Chapa webhook (recommended: Chapa webhook to this endpoint)
 *
 *  - qr (GET): return QR image URL for a ticket (internal use)
 *
 * NOTES:
 *  - config.php must include 'chapa' => ['secret' => '...']
 *  - The tickets table is expected to have at least: id, ticket_type_id, full_name, email, phone, status, amount, reference, chapa_ref, qr_token, created_at, updated_at
 *  - We will log to logs/tickets_api.log
 */

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Chapa.php';

function handle_tickets(string $action = ''): array {
    $action = strtolower($action ?: ($_REQUEST['action'] ?? ''));
    $pdo = Database::getConnection();
    $cfg = require __DIR__ . '/../../config.php';
    $chapa = new Chapa($cfg['chapa'] ?? []);
    $logFile = __DIR__ . '/../../logs/tickets_api.log';

    try {
        if ($action === 'create') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ['ok'=>false,'error'=>'method not allowed'];

            $ticket_type_id = intval($_POST['ticket_type_id'] ?? 0);
            $full_name = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $qty = max(1, intval($_POST['quantity'] ?? 1));

            if ($ticket_type_id <= 0 || !$full_name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$phone) {
                return ['ok'=>false,'error'=>'missing or invalid fields'];
            }

            // 1) Load ticket_type
            $stmt = $pdo->prepare("SELECT id, name, price, currency FROM ticket_types WHERE id = :id LIMIT 1");
            $stmt->execute([':id'=>$ticket_type_id]);
            $tt = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tt) return ['ok'=>false,'error'=>'ticket type not found'];

            $amount = floatval($tt['price']) * $qty;
            $currency = $tt['currency'] ?? 'ETB';

            // 2) Create a pending ticket(s) record(s)
            $pdo->beginTransaction();
            $createdTickets = [];
            $reference = bin2hex(random_bytes(8)); // local reference for this purchase

            for ($i=0;$i<$qty;$i++) {
                $token = bin2hex(random_bytes(12)); // qr token placeholder
                $ins = $pdo->prepare("INSERT INTO tickets (ticket_type_id, full_name, email, phone, status, amount, reference, qr_token, created_at, updated_at)
                                      VALUES (:ttid,:name,:email,:phone,'PENDING',:amount,:reference,:qr_token,NOW(),NOW())");
                $ins->execute([
                    ':ttid'=>$ticket_type_id,
                    ':name'=>$full_name,
                    ':email'=>$email,
                    ':phone'=>$phone,
                    ':amount'=>$tt['price'],
                    ':reference'=>$reference,
                    ':qr_token'=>$token
                ]);
                $createdTickets[] = (int)$pdo->lastInsertId();
            }
            $pdo->commit();

            // 3) Initialize Chapa transaction
            $callback = $cfg['app']['url'] . '/public/api.php?target=tickets&action=webhook'; // webhook (server-to-server)
            $redirect = $cfg['app']['url'] . '/public/tickets.php?status=redirect&reference=' . urlencode($reference);

            $meta = [
                'reference' => $reference,
                'tickets' => $createdTickets
            ];
            $init = $chapa->initializeTransaction($amount, $currency, $email, $full_name, $redirect, $meta);

            // Log
            file_put_contents($logFile, date('c') . " INIT: ref={$reference} init_resp=" . json_encode($init) . PHP_EOL, FILE_APPEND);

            if (isset($init['status']) && strtolower((string)$init['status']) === 'success' && !empty($init['data']['checkout_url'])) {
                // Store chapa reference (if provided) and response raw
                $checkout = $init['data']['checkout_url'];
                // Optionally record chapa reference id if returned in metadata
                $chapaRef = $init['data']['id'] ?? null;

                // Update tickets with chapa_ref and maybe store init payload as JSON in a column if exists
                $upd = $pdo->prepare("UPDATE tickets SET chapa_ref = :chapa_ref, updated_at = NOW() WHERE reference = :reference");
                $upd->execute([':chapa_ref'=>$chapaRef, ':reference'=>$reference]);

                return ['ok'=>true, 'checkout_url'=>$checkout, 'reference'=>$reference];
            } else {
                return ['ok'=>false,'error'=>'failed to initialize payment'];
            }
        }

        if ($action === 'webhook') {
            // Chapa server-to-server webhook: POST body contains 'data' per Chapa docs
            $raw = file_get_contents('php://input');
            file_put_contents($logFile, date('c') . " WEBHOOK RAW: " . $raw . PHP_EOL, FILE_APPEND);

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                // Chapa may also POST form data; try $_POST
                $payload = $_POST;
            }

            // The Chapa webhook payload usually includes 'data' and inside data: reference, status...
            $reference = $payload['data']['reference'] ?? $payload['reference'] ?? null;
            $txnRef = $payload['data']['tx_ref'] ?? null;
            $chapaId = $payload['data']['id'] ?? null;

            if (!$reference && !$txnRef) {
                file_put_contents($logFile, date('c') . " WEBHOOK: missing reference" . PHP_EOL, FILE_APPEND);
                http_response_code(400);
                echo json_encode(['ok'=>false,'error'=>'missing reference']);
                exit;
            }

            // Use reference created earlier
            $refToCheck = $reference ?: $txnRef;

            // Verify with Chapa API to avoid fake calls
            $verify = $chapa->verifyTransaction($refToCheck);
            file_put_contents($logFile, date('c') . " WEBHOOK VERIFY: " . json_encode($verify) . PHP_EOL, FILE_APPEND);

            if (isset($verify['status']) && strtolower((string)$verify['status']) === 'success') {
                $data = $verify['data'] ?? [];
                $paid = (strtolower($data['status'] ?? '') === 'success' || strtolower($data['status'] ?? '') === 'paid');

                if ($paid) {
                    // mark all tickets with reference = refToCheck as PAID
                    $pdo->beginTransaction();
                    $upd = $pdo->prepare("UPDATE tickets SET status = 'PAID', chapa_ref = :chapa_ref, updated_at = NOW() WHERE reference = :reference");
                    $upd->execute([':chapa_ref'=>$data['id'] ?? $data['reference'] ?? $refToCheck, ':reference'=>$refToCheck]);

                    // For each ticket, ensure qr_token exists; generate if empty
                    $sel = $pdo->prepare("SELECT id, qr_token FROM tickets WHERE reference = :reference");
                    $sel->execute([':reference'=>$refToCheck]);
                    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $r) {
                        if (empty($r['qr_token'])) {
                            $token = bin2hex(random_bytes(12));
                            $u2 = $pdo->prepare("UPDATE tickets SET qr_token = :token WHERE id = :id");
                            $u2->execute([':token'=>$token, ':id'=>$r['id']]);
                        }
                    }
                    $pdo->commit();

                    // Send confirmation email with QR link(s)
                    $sel2 = $pdo->prepare("SELECT id, full_name, email, qr_token FROM tickets WHERE reference = :reference");
                    $sel2->execute([':reference'=>$refToCheck]);
                    $tickets = $sel2->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($tickets as $t) {
                        $qrUrl = generate_qr_url($t['qr_token']);
                        $sub = "Your ticket for Origin Expo";
                        $body = "Hello " . ($t['full_name'] ?: '') . ",\n\nYour ticket has been confirmed. You can print or save your ticket using the link below:\n\n" . $qrUrl . "\n\nPlease present the QR at the venue.\n\nRegards,\nOrigin Expo Team\n";
                        $headers = "From: registrar@originofcoffee.org\r\nReply-To: registrar@originofcoffee.org\r\n";
                        @mail($t['email'], $sub, $body, $headers);
                    }

                    http_response_code(200);
                    echo json_encode(['ok'=>true]);
                    exit;
                } else {
                    file_put_contents($logFile, date('c') . " WEBHOOK: payment not successful: " . json_encode($verify) . PHP_EOL, FILE_APPEND);
                    http_response_code(200);
                    echo json_encode(['ok'=>false,'error'=>'payment not successful']);
                    exit;
                }
            } else {
                file_put_contents($logFile, date('c') . " WEBHOOK verify failed: " . json_encode($verify) . PHP_EOL, FILE_APPEND);
                http_response_code(500);
                echo json_encode(['ok'=>false,'error'=>'verify failed']);
                exit;
            }
        }

        if ($action === 'verify') {
            $reference = $_GET['reference'] ?? $_POST['reference'] ?? null;
            if (!$reference) return ['ok'=>false,'error'=>'reference required'];

            $verify = $chapa->verifyTransaction($reference);
            file_put_contents($logFile, date('c') . " MANUAL VERIFY: " . json_encode($verify) . PHP_EOL, FILE_APPEND);

            if (isset($verify['status']) && strtolower((string)$verify['status']) === 'success') {
                $data = $verify['data'] ?? [];
                $paid = (strtolower($data['status'] ?? '') === 'success' || strtolower($data['status'] ?? '') === 'paid');
                if ($paid) {
                    // mark tickets as PAID if not already
                    $pdo->beginTransaction();
                    $upd = $pdo->prepare("UPDATE tickets SET status = 'PAID', chapa_ref = :chapa_ref, updated_at = NOW() WHERE reference = :reference");
                    $upd->execute([':chapa_ref'=>$data['id'] ?? $reference, ':reference'=>$reference]);
                    // ensure qr tokens exist
                    $sel = $pdo->prepare("SELECT id, qr_token FROM tickets WHERE reference = :reference");
                    $sel->execute([':reference'=>$reference]);
                    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $r) {
                        if (empty($r['qr_token'])) {
                            $token = bin2hex(random_bytes(12));
                            $u2 = $pdo->prepare("UPDATE tickets SET qr_token = :token WHERE id = :id");
                            $u2->execute([':token'=>$token, ':id'=>$r['id']]);
                        }
                    }
                    $pdo->commit();

                    return ['ok'=>true,'data'=>$data];
                } else {
                    return ['ok'=>false,'error'=>'payment not marked success'];
                }
            }
            return ['ok'=>false,'error'=>'verify failed'];
        }

        if ($action === 'qr') {
            // return a direct URL to QR image for an existing ticket token
            $token = $_GET['token'] ?? null;
            if (!$token) return ['ok'=>false,'error'=>'token required'];
            $qr = generate_qr_url($token);
            return ['ok'=>true,'qr_url'=>$qr];
        }

        return ['ok'=>false,'error'=>'unknown action'];
    } catch (Throwable $e) {
        file_put_contents($logFile, date('c') . " ERROR: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        return ['ok'=>false,'error'=>'server error'];
    }
}

/* helper: generates a QR image URL (Google chart API). Replace with local generator later if needed */
function generate_qr_url(string $token, int $size = 300): string {
    $payload = json_encode(['t'=>$token]);
    $enc = rawurlencode($payload);
    // Google Chart API (simple, no dependencies). If you prefer a local generator, swap this function.
    return "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chl={$enc}&choe=UTF-8";
}
