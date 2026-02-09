<?php
// client/profile_action.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit();
}

// CSRF token (recommended)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$action  = $_POST['action'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // ---------- Update Phone ----------
    if ($action === 'update_phone') {
        $phone = trim($_POST['phone'] ?? '');

        // simple validation (Sri Lanka style 7-15 digits + optional +)
        if ($phone === '') {
            throw new Exception("Phone number is required.");
        }
        if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
            throw new Exception("Invalid phone number format.");
        }

        $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
        $stmt->execute([$phone, $user_id]);

        echo json_encode(['ok' => true, 'message' => 'Phone number updated']);
        exit();
    }

    // ---------- Change Password ----------
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            throw new Exception("All password fields are required.");
        }
        if ($new !== $confirm) {
            throw new Exception("New password and confirm password do not match.");
        }
        if (strlen($new) < 8) {
            throw new Exception("New password must be at least 8 characters.");
        }

        // get old hash
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            throw new Exception("Current password is incorrect.");
        }

        $newHash = password_hash($new, PASSWORD_DEFAULT);

        $stmt2 = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt2->execute([$newHash, $user_id]);

        // logout other sessions concept: regenerate session id
        session_regenerate_id(true);

        echo json_encode(['ok' => true, 'message' => 'Password changed successfully']);
        exit();
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Unknown action']);
    exit();

} catch (Exception $e) {
    error_log("profile_action.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    exit();
}
