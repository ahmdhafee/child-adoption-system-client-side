<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
    // Update user status to deactivated
    $stmt = $pdo->prepare("UPDATE users SET status = 'deactivated', deactivated_at = NOW() WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // Logout user
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Account deactivation request submitted.']);
    
} catch (PDOException $e) {
    error_log("Deactivate account error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to deactivate account.']);
}