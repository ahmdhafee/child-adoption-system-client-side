<?php
session_start();


$_SESSION = array();


session_destroy();


if (isset($_COOKIE['family_bridge_remember'])) {
    setcookie('family_bridge_remember', '', time() - 3600, '/');
}


header("Location: login.php?logout=true");
exit();
?>