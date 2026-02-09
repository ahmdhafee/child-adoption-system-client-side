<?php
// client/vote_submit.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php?expired=true");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: children.php");
    exit();
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid request (CSRF).");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$child_id = (int)($_POST['child_id'] ?? 0);
if ($user_id <= 0 || $child_id <= 0) {
    header("Location: children.php");
    exit();
}

// DB
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // ✅ Block if already voted
    $chk = $pdo->prepare("SELECT COUNT(*) FROM user_votes WHERE user_id = ? AND status = 'active'");
    $chk->execute([$user_id]);
    if ((int)$chk->fetchColumn() > 0) {
        header("Location: children.php?voted=already");
        exit();
    }

    // ✅ Ensure child is still available
    $c = $pdo->prepare("SELECT id FROM children WHERE id = ? AND status = 'available' LIMIT 1");
    $c->execute([$child_id]);
    if (!$c->fetch()) {
        header("Location: children.php?error=child_unavailable");
        exit();
    }

    // Insert vote
    $ins = $pdo->prepare("INSERT INTO user_votes (user_id, child_id, status, vote_date) VALUES (?, ?, 'active', NOW())");
    $ins->execute([$user_id, $child_id]);

    header("Location: children.php?voted=success");
    exit();

} catch (Exception $e) {
    error_log("vote_submit.php error: " . $e->getMessage());
    header("Location: children.php?error=vote_failed");
    exit();
}
