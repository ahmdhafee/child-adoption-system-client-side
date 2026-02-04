<?php
session_start();
header('Content-Type: application/json');


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}


$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $appointment_type = $_POST['appointment_type'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $preferred_caseworker = $_POST['preferred_caseworker'] ?? '';
    $meeting_location = $_POST['meeting_location'] ?? '';
    $appointment_notes = $_POST['appointment_notes'] ?? '';
    
  
    if (empty($appointment_type) || empty($appointment_date) || empty($appointment_time) || empty($meeting_location)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        exit();
    }
    
    
    $selected_date = new DateTime($appointment_date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($selected_date < $today) {
        echo json_encode(['success' => false, 'message' => 'Please select a future date']);
        exit();
    }
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
       
        $title_map = [
            'home-study' => 'Home Study Visit',
            'caseworker' => 'Caseworker Meeting',
            'counseling' => 'Adoption Counseling',
            'document-review' => 'Document Review Session',
            'child-meeting' => 'Child Introduction Meeting',
            'follow-up' => 'Follow-up Meeting',
            'other' => 'Appointment'
        ];
        
        $title = $title_map[$appointment_type] ?? 'Appointment';
        

        $caseworker_map = [
            'sarah-johnson' => 'Officer Sarah Johnson',
            'david-wilson' => 'Officer David Wilson',
            'emma-chen' => 'Officer Emma Chen',
            '' => 'To be assigned'
        ];
        $caseworker = $caseworker_map[$preferred_caseworker] ?? 'To be assigned';
        

        $location_map = [
            'office' => 'Family Bridge Office',
            'home' => 'Your Home',
            'video' => 'Video Conference',
            'phone' => 'Phone Call'
        ];
        $location = $location_map[$meeting_location] ?? $meeting_location;
        

        $stmt = $pdo->prepare("INSERT INTO appointments (user_id, appointment_type, title, appointment_date, appointment_time, meeting_location, caseworker, appointment_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')");
        
        if ($stmt->execute([$user_id, $appointment_type, $title, $appointment_date, $appointment_time, $meeting_location, $caseworker, $appointment_notes])) {
            $appointment_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Appointment scheduled successfully',
                'appointment' => $appointment
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to schedule appointment']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>