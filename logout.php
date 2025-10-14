<?php
include 'config/database.php';

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redireccionar al login
header('Location: login.php');
exit();
?>