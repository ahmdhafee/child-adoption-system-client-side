<?php
// client/includes/auth_guard.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php?expired=true");
    exit();
}

// OPTIONAL: if you store last activity time
$timeout = 30 * 60; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?expired=true");
    exit();
}
$_SESSION['last_activity'] = time();
