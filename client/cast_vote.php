<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $child_id = intval($_POST['child_id'] ?? 0);
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$child_id || !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit();
    }
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if user has already voted
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_votes WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
        $vote_count = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (($vote_count['count'] ?? 0) > 0) {
            echo json_encode(['success' => false, 'message' => 'You have already voted']);
            exit();
        }
        
        // Check if child exists and is available
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM children WHERE id = ? AND status = 'available'");
        $stmt->execute([$child_id]);
        $child_count = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (($child_count['count'] ?? 0) === 0) {
            echo json_encode(['success' => false, 'message' => 'Child not available']);
            exit();
        }
        
        // Cast vote
        $stmt = $pdo->prepare("INSERT INTO user_votes (user_id, child_id, vote_date, status) VALUES (?, ?, NOW(), 'active')");
        $stmt->execute([$user_id, $child_id]);
        
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>