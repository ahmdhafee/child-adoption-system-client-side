<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $child_id = intval($_POST['child_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if (!$child_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid child ID']);
        exit();
    }
    
    $shortlisted = $_SESSION['shortlisted_children'] ?? [];
    
    if ($action === 'add') {
        if (!in_array($child_id, $shortlisted)) {
            $shortlisted[] = $child_id;
        }
    } elseif ($action === 'remove') {
        $shortlisted = array_filter($shortlisted, function($id) use ($child_id) {
            return $id != $child_id;
        });
    }
    
    $_SESSION['shortlisted_children'] = array_values($shortlisted);
    echo json_encode(['success' => true, 'shortlisted' => $shortlisted]);
}
?>