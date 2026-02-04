<?php
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'couple';
    $admin_code = $_POST['admin_code'] ?? '';
    
   
    if ($role === 'chief_officer' && $admin_code !== 'ADMIN2023') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid security code'
        ]);
        exit;
    }
    
  
    $result = $auth->login($email, $password, $role);
    
    if ($result['success']) {
        
        $_SESSION['user_id'] = $result['user_id'];
        $_SESSION['user_email'] = $result['email'];
        $_SESSION['user_role'] = $result['role'];
        $_SESSION['login_time'] = time();
        
       
        if ($result['role'] === 'couple') {
            $redirect = 'couple-portal.html';
        } else {
            $redirect = 'admin-portal.html';
        }
        
        echo json_encode([
            'success' => true,
            'redirect' => $redirect,
            'message' => 'Login successful'
        ]);
    } else {
        echo json_encode($result);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>