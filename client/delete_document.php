<?php
// client/delete_document.php
// Secure delete (only owner, cannot delete approved)

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$doc_id  = (int)($_GET['id'] ?? 0);

if ($user_id <= 0 || $doc_id <= 0) {
    header("Location: documents.php");
    exit();
}

// DB
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    // Get document
    $stmt = $pdo->prepare("
        SELECT id, user_id, file_path, status
        FROM documents
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$doc_id, $user_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        header("Location: documents.php");
        exit();
    }

    // DO NOT allow delete if approved
    if ($doc['status'] === 'approved') {

        header("Location: documents.php");
        exit();

    }

    // Delete physical file
    $file_path = dirname(__DIR__) . $doc['file_path'];

    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Delete DB record
    $del = $pdo->prepare("
        DELETE FROM documents
        WHERE id = ? AND user_id = ?
    ");

    $del->execute([$doc_id, $user_id]);

    header("Location: documents.php?deleted=true");
    exit();

}
catch (Exception $e) {

    error_log("delete_document.php error: " . $e->getMessage());

    header("Location: documents.php?error=delete_failed");
    exit();

}
?>
