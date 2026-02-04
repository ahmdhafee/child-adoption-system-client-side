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
    $user_id = $_SESSION['user_id'] ?? 0;
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $reason = $_POST['reason'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (!$appointment_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid appointment']);
        exit();
    }
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if appointment belongs to user
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
        $stmt->execute([$appointment_id, $user_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit();
        }
        
        // Update appointment status
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', cancellation_reason = ?, cancellation_notes = ?, cancelled_date = NOW() WHERE id = ?");
        
        if ($stmt->execute([$reason, $notes, $appointment_id])) {
            echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>