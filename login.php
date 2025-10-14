<?php
// FORZAR HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
        $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect);
        exit();
    }
}
// FIN FORZAR HTTPS

session_start();
include 'config/database.php';

// Verificar si el usuario ya está logueado
if (isset($_SESSION['user_id'])) {
    // Redireccionar según el rol
    switch ($_SESSION['user_role']) {
        case 'garzon':
            header('Location: garzon/garzon.php');
            break;
        case 'cajero':
            header('Location: cajero/cajero.php');
            break;
        case 'admin':
            header('Location: admin/admin.php');
            break;
        default:
            // Si el rol no es reconocido, destruir sesión y mostrar login
            session_destroy();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    
    // Verificar credenciales
    $sql = "SELECT id, nombre, usuario, password, rol FROM usuarios WHERE usuario = ? AND activo = true";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $password === $user['password']) {
        // Login exitoso
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_role'] = $user['rol'];
        
        // Redireccionar según el rol
        switch ($user['rol']) {
            case 'garzon':
                header('Location: garzon/garzon.php');
                break;
            case 'cajero':
                header('Location: cajero/cajero.php');
                break;
            case 'admin':
                header('Location: admin/admin.php');
                break;
            default:
                header('Location: login.php');
        }
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Restaurante</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #ffffffff 0%, #79a8eeff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #79a8eeff 100%, #79a8eeff 100%);
            color: white;
            padding: 2rem 1rem;
            text-align: center;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #79a8eeff;
            box-shadow: 0 0 0 0.2rem rgba(15, 3, 83, 0.91);
        }
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
        }
        .btn-login {
            background: linear-gradient(135deg, #ffffffff, #79a8eeff 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(120, 185, 247, 0.59);
        }
        .alert-info {
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, #17a2b8, #6f42c1);
            color: white;
        }
        .role-badges {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 1rem;
        }
        .badge-role {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .floating-label .form-control {
            padding: 1rem 0.75rem 0.5rem;
        }
        .floating-label label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            color: #6c757d;
            transition: all 0.3s;
            pointer-events: none;
        }
        .floating-label .form-control:focus + label,
        .floating-label .form-control:not(:placeholder-shown) + label {
            top: 0.25rem;
            transform: translateY(0);
            font-size: 0.75rem;
            color: #a6fcfcff;
            font-weight: 600;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <!-- Header -->
                    <div class="login-header">
                        <i class="fas fa-utensils"></i>
                        <h2 class="mb-0">Sistema Restaurante</h2>
                        <p class="mb-0 opacity-75">Iniciar Sesión</p>
                    </div>

                    <!-- Body -->
                    <div class="login-body">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>¡Sesión activa!</strong><br>
                                <small>Redirigiendo automáticamente...</small>
                            </div>
                            <script>
                                setTimeout(function() {
                                    window.location.href = '<?php echo $_SESSION['user_role']; ?>/<?php echo $_SESSION['user_role']; ?>.php';
                                }, 2000);
                            </script>
                        <?php else: ?>
                        
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <!-- Usuario -->
                                <div class="floating-label">
                                    <input type="text" 
                                           class="form-control" 
                                           id="usuario" 
                                           name="usuario" 
                                           placeholder=" "
                                           required>
                                    <label for="usuario">
                                        <i class="fas fa-user me-2"></i>Usuario
                                    </label>
                                </div>

                                <!-- Contraseña -->
                                <div class="floating-label position-relative">
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder=" "
                                           required>
                                    <label for="password">
                                        <i class="fas fa-lock me-2"></i>Contraseña
                                    </label>
                                    <button type="button" class="password-toggle" onclick="togglePassword()">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>

                                <button type="submit" class="btn btn-login btn-lg w-100 text-white mb-3">
                                    <i class="fas fa-sign-in-alt me-2"></i>Ingresar al Sistema
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-3">
                    <small class="text-white opacity-75">
                        <i class="fas fa-shield-alt me-1"></i>Sistema seguro - © 2024
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mostrar/ocultar contraseña
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        // Efecto de focus en inputs
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.parentElement.classList.remove('focused');
                    }
                });
            });
        });

        // Validación en tiempo real
        document.getElementById('usuario').addEventListener('input', function() {
            this.value = this.value.toLowerCase();
        });
    </script>
</body>
</html>