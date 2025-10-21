<?php
// src/actions/exhibitors_register.php
declare(strict_types=1);

/**
 * Public exhibitor registration handler for real.originofcoffee.org
 *
 * Behavior:
 *  - Validates required inputs
 *  - Ensures contact_email is unique among exhibitors
 *  - If there is already a users.email matching contact_email, links to it and sets is_active=0
 *  - Otherwise creates a users row with role='EXHIBITOR', is_active=0 and must_change_password=1
 *  - Creates exhibitors row with status='PENDING' and user_id pointing to created/linked user
 *  - Sends a "receipt" email that application has been received (does NOT include passwords)
 *
 * Expected POST fields:
 *  - company_name (required)
 *  - contact_name  (required)
 *  - contact_email (required)
 *  - phone         (required)
 *  - country       (required)
 *  - password      (optional) -- if provided, we use it (hashed). It won't be emailed at registration.
 *  - notes         (optional)
 *
 * Returns JSON:
 *  { ok: true, data: { exhibitor_id: n, user_id: m } } or { ok: false, error: '...' }
 *
 * IMPORTANT: this file expects a config.php at real domain root (__DIR__ . '/../../config.php')
 * that returns an array with 'db' settings similar to admin's config.
 *
 */

require_once __DIR__ . '/../lib/Database.php';

function handle_exhibitors(string $action = ''): array {
    // Only accept POST for mutating action 'register'
    if (strtolower($action) !== 'register') {
        return ['ok'=>false,'error'=>'unknown action'];
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['ok'=>false,'error'=>'method not allowed'];
    }

    // Read input (multipart/form-data or application/x-www-form-urlencoded)
    $company_name = trim((string)($_POST['company_name'] ?? ''));
    $contact_name = trim((string)($_POST['contact_name'] ?? ''));
    $contact_email = trim((string)($_POST['contact_email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $country = trim((string)($_POST['country'] ?? 'Ethiopia'));
    $password_plain = (string)($_POST['password'] ?? '');
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($company_name === '' || $contact_name === '' || !filter_var($contact_email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        return ['ok'=>false,'error'=>'missing or invalid fields'];
    }

    $pdo = Database::getConnection();

    try {
        // Start transaction
        $pdo->beginTransaction();

        // 1) Ensure no existing exhibitor with that email (unique)
        $chk = $pdo->prepare("SELECT id, status FROM exhibitors WHERE contact_email = :email LIMIT 1");
        $chk->execute([':email'=>$contact_email]);
        $existingEx = $chk->fetch(PDO::FETCH_ASSOC);
        if ($existingEx) {
            $pdo->rollBack();
            return ['ok'=>false,'error'=>'An application with that email already exists'];
        }

        // 2) Look for existing user
        $uChk = $pdo->prepare("SELECT id, is_active FROM users WHERE email = :email LIMIT 1");
        $uChk->execute([':email'=>$contact_email]);
        $urow = $uChk->fetch(PDO::FETCH_ASSOC);

        if ($urow) {
            // If user exists, link to it but ensure it is not active (pending) — we set is_active=0 to enforce approval later
            $userId = (int)$urow['id'];
            $uUpd = $pdo->prepare("UPDATE users SET is_active = 0, must_change_password = 1, updated_at = NOW() WHERE id = :id");
            $uUpd->execute([':id'=>$userId]);

            // If registrant provided a password, update the hash now (we respect their provided password)
            if ($password_plain !== '') {
                $hash = password_hash($password_plain, PASSWORD_DEFAULT);
                $pUpd = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                $pUpd->execute([':hash'=>$hash, ':id'=>$userId]);
            }
        } else {
            // Create user record (inactive until admin approval)
            if ($password_plain === '') {
                // generate a hybrid password but do NOT email it now (admin will send on approval)
                $password_plain = generate_hybrid_password(12);
            }
            $hash = password_hash($password_plain, PASSWORD_DEFAULT);

            $insu = $pdo->prepare("INSERT INTO users (email,password_hash,name,role,email_verified,must_change_password,is_active,created_at,updated_at)
                                   VALUES (:email,:hash,:name,'EXHIBITOR',0,1,0,NOW(),NOW())");
            $insu->execute([':email'=>$contact_email, ':hash'=>$hash, ':name'=>$company_name]);
            $userId = (int)$pdo->lastInsertId();
        }

        // 3) Insert exhibitor application
        $ins = $pdo->prepare("INSERT INTO exhibitors (user_id, company_name, country, contact_name, contact_email, phone, status, booth_assigned, notes, created_at, updated_at)
                              VALUES (:user_id,:company_name,:country,:contact_name,:contact_email,:phone,'PENDING',NULL,:notes,NOW(),NOW())");
        $ins->execute([
            ':user_id'=> $userId,
            ':company_name'=> $company_name,
            ':country'=> $country,
            ':contact_name'=> $contact_name,
            ':contact_email'=> $contact_email,
            ':phone'=> $phone,
            ':notes'=> $notes
        ]);
        $exhibitorId = (int)$pdo->lastInsertId();

        // Commit transaction
        $pdo->commit();

        // 4) Send confirmation email (receipt) — DO NOT include passwords
        $subject = "Exhibitor application received — Origin Expo";
        $body = "Hello " . htmlspecialchars($contact_name, ENT_QUOTES) . ",\n\n";
        $body .= "Thank you for applying to exhibit at Origin Expo 2026. We have received your application and it is currently pending review by our team.\n\n";
        $body .= "We will notify you at this email address when your application is approved. If approved, you will receive an email with account access instructions.\n\n";
        $body .= "Application details:\nCompany: {$company_name}\nContact: {$contact_name}\nEmail: {$contact_email}\nPhone: {$phone}\nCountry: {$country}\n\n";
        $body .= "If you have questions, reply to this email.\n\nRegards,\nOrigin Expo Team\n";

        $headers = "From: registrar@originofcoffee.org\r\nReply-To: registrar@originofcoffee.org\r\n";
        @mail($contact_email, $subject, $body, $headers);

        return ['ok'=>true,'data'=>['exhibitor_id'=>$exhibitorId,'user_id'=>$userId]];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('exhibitor.register.error: '.$e->getMessage());
        return ['ok'=>false,'error'=>'server error'];
    }
}

/* Helpers */

function generate_hybrid_password(int $len = 12): string {
    // Hybrid: two readable words + symbol + digits to balance memorability and strength.
    $words = ['Origin','Roast','Bean','Cup','Aroma','Harvest','Altitude','Blue','Ethiopia','Sidamo','Yirgacheffe','Koke','Guji','Bunna','Sunrise'];
    $w1 = $words[random_int(0, count($words)-1)];
    $w2 = $words[random_int(0, count($words)-1)];
    $symbols = ['!','#','$','%','@','-'];
    $sym = $symbols[random_int(0, count($symbols)-1)];
    $digits = strval(random_int(10, 99));
    $pw = $w1 . $sym . $w2 . $digits;
    // if requested length greater, append random chars
    while (strlen($pw) < $len) {
        $pw .= chr(97 + random_int(0,25));
    }
    return substr($pw,0,$len);
}
