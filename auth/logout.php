<?php
include '../config/database.php';
// Hancurkan semua session
session_destroy();
// Redirect ke halaman login
header('Location: login.php');
exit();
?>