<?php
include '../config/database.php';

// Verificar si el usuario está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Administrador</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .header {
            background-color: #6f42c1;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .container {
            padding: 2rem;
        }
        .welcome {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .logout-btn {
            background-color: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 4px;
        }
        .user-info {
            margin-right: 1rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sistema Restaurante - Administrador</h1>
        <div>
            <span class="user-info">Bienvenido, <?php echo $_SESSION['user_name']; ?> (<?php echo $_SESSION['user_role']; ?>)</span>
            <a href="../logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h2>Panel de Administrador</h2>
            <p>Aquí irán las funciones específicas para administradores...</p>
            <p><strong>Sesión activa:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>