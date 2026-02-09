<?php
// client/download_document.php
// Secure download (only owner can download)

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    exit("Access denied.");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$doc_id  = (int)($_GET['id'] ?? 0);

if ($user_id <= 0 || $doc_id <= 0) {
    exit("Invalid request.");
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

    // Get document (ONLY owner)
    $stmt = $pdo->prepare("
        SELECT id, user_id, file_name, original_name, file_path
        FROM documents
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$doc_id, $user_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        exit("Document not found.");
    }

    // Build real file path
    $file_path = dirname(__DIR__) . $doc['file_path'];

    if (!file_exists($file_path)) {
        exit("File missing.");
    }

    // Send headers
    header("Content-Description: File Transfer");
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"" . basename($doc['original_name']) . "\"");
    header("Content-Length: " . filesize($file_path));
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: 0");

    readfile($file_path);
    exit();

}
catch (Exception $e) {

    error_log("download_document.php error: " . $e->getMessage());
    exit("Download failed.");

}
?>
