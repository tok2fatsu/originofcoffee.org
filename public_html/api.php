<?php
// public/api.php
declare(strict_types=1);

/**
 * Simple mini-router for real.originofcoffee.org
 * Usage: /api.php?target=exhibitors&action=register
 *
 * Security notes:
 * - Public endpoints (like register) MUST validate inputs.
 * - This router returns JSON and sets CORS/headers carefully.
 */

// Allow only JSON responses for now
header('Content-Type: application/json; charset=utf-8');

// Basic CORS for same-origin only (public pages on same domain)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    // Optionally validate origin if you host multiple domains
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}

$target = (string)($_GET['target'] ?? '');
$action = (string)($_GET['action'] ?? '');

if ($target === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'target required']);
    exit;
}

// map target -> file under src/actions/<target>_*.php
$actionFile = __DIR__ . '/../src/actions/' . preg_replace('/[^a-z0-9_]/','', $target) . '_register.php';
if (!file_exists($actionFile)) {
    // fallback to generic handlers in same folder
    $actionFile = __DIR__ . '/../src/actions/' . preg_replace('/[^a-z0-9_]/','', $target) . '_api.php';
}

if (!file_exists($actionFile)) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'not found']);
    exit;
}

// include DB and helpers; action file will expose a function 'handle_...'
require_once __DIR__ . '/../src/lib/Database.php';

try {
    require_once $actionFile;

    // assume the included file defines a function `handle_{target}` we can call
    $fn = 'handle_' . preg_replace('/[^a-z0-9_]/','', $target);
    if (!function_exists($fn)) {
        // fallback: action handler in file may provide a generic function `handle_request`
        if (function_exists('handle_request')) {
            $resp = call_user_func('handle_request', $action);
        } else {
            throw new Exception('Handler not found');
        }
    } else {
        $resp = call_user_func($fn, $action);
    }

    if (!is_array($resp)) $resp = ['ok'=>true,'result'=>$resp];
    echo json_encode($resp);
} catch (Throwable $e) {
    http_response_code(500);
    // Do not leak internal stack in production. Keep message generic.
    error_log('real.api.error: ' . $e->getMessage());
    echo json_encode(['ok'=>false,'error'=>'server error']);
}
