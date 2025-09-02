<?php
session_start();
// Si ya hay una sesión de admin, redirigir al dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}
require_once 'config.php';

$error_message = '';
$login_identifier_val = ''; // Para repoblar el campo identificador

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_identifier_val = trim($_POST['login_identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login_identifier_val) || empty($password)) {
        $error_message = "Por favor, ingrese su identificador y contraseña.";
    } else {
        $stmt = $mysqli->prepare("SELECT id, name, password, role, permissions, profile_image FROM admin_users WHERE email = ?");

        if ($stmt) {
            $stmt->bind_param("s", $login_identifier_val);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_name'] = $user['name'];
                    $_SESSION['admin_role'] = $user['role'];
                    $_SESSION['admin_permissions'] = $user['permissions'] ?: '[]';
                    $_SESSION['admin_profile_image'] = $user['profile_image'];

                    session_regenerate_id(true);
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error_message = "Identificador o contraseña incorrectos.";
                }
            } else {
                $error_message = "Identificador o contraseña incorrectos.";
            }
            $stmt->close();
        } else {
            $error_message = "Error en la preparación de la consulta: " . $mysqli->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Profesional - DentexaPro</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html {
            height: 100%;
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh; /* Asegura que el body ocupe al menos toda la altura de la pantalla */
            font-family: 'Poppins', sans-serif;
            color: #495057;
            padding: 1rem;
            
            /* Imagen de fondo para el body */
            background-image: url('assets/img/mi-fondo-hero.webp'); /* Cambia esto a tu imagen */
            background-position: center center;
            background-repeat: no-repeat;
            background-size: cover;
            background-attachment: fixed; /* Opcional: para efecto parallax */
            position: relative; /* Para el overlay */
        }
        /* Overlay para oscurecer un poco el fondo y mejorar legibilidad del card */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.4); /* Ajusta la opacidad según necesites */
            z-index: 0;
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            position: relative; /* Para que esté sobre el overlay del body */
            z-index: 1;
        }
        .login-card {
            background-color: rgba(255, 255, 255, 0.95); /* Fondo del card ligeramente transparente o sólido */
            padding: 2.5rem 3rem;
            border-radius: 1rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25); /* Sombra más pronunciada */
            border: none;
        }
        .login-logo {
            max-width: 280px;
            height: auto;
            margin-bottom: 1.5rem;
        }
        .form-floating > .form-control,
        .form-floating > .form-select {
            height: calc(3.8rem + 2px);
            line-height: 1.25;
            border-radius: 0.5rem;
        }
        .form-floating > label {
            padding: 1.15rem 0.75rem;
        }
        .btn-login {
            padding: 0.85rem 1.5rem;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .alert-custom {
            border-radius: 0.5rem;
            font-size: 0.9rem;
        }
        .login-title {
            font-weight: 600;
            color: #343a40;
            margin-bottom: 0.75rem;
            font-size: 1.75rem;
        }
        .login-subtitle {
            font-size: 0.95rem;
            color: #6c757d;
            margin-bottom: 2rem;
        }

        @media (max-width: 576px) {
            .login-card {
                padding: 1.5rem 1.5rem;
                background-color: rgba(255, 255, 255, 0.98); /* Un poco más opaco en móviles si es necesario */
            }
            .login-logo {
                max-width: 200px;
                margin-bottom: 1rem;
            }
            .login-title {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            .login-subtitle {
                font-size: 0.85rem;
                margin-bottom: 1.5rem;
            }
            .form-floating > .form-control,
            .form-floating > .form-select {
                height: calc(3.5rem + 2px);
                font-size: 0.9rem;
            }
            .form-floating > label {
                padding: 1rem 0.75rem;
                font-size: 0.9rem;
            }
            .btn-login {
                padding: 0.75rem 1.25rem;
                font-size: 1rem;
            }
            .alert-custom {
                font-size: 0.85rem;
            }
        }
         @media (max-width: 380px) {
            .login-card {
                padding: 1.25rem 1rem;
            }
            .login-logo {
                max-width: 180px;
            }
            .login-title {
                font-size: 1.3rem;
            }
             .login-subtitle {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <main class="login-container">
        <div class="login-card">
            <div class="text-center">
                <img src="assets/img/dentexapro-logo.png" alt="DentexaPro Logo" class="login-logo">
                <h1 class="login-title">Acceso Profesional</h1>
                <p class="login-subtitle">Inicia sesión para administrar la clínica</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-custom" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="login_identifier" name="login_identifier" placeholder="Email o Usuario" required autofocus value="<?php echo htmlspecialchars($login_identifier_val); ?>">
                    <label for="login_identifier"><i class="fas fa-user me-2"></i>Email</label>
                </div>
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Contraseña</label>
                </div>
                <button class="btn btn-primary w-100 btn-login" type="submit">
                    <i class="fas fa-sign-in-alt me-2"></i>Ingresar
                </button>
            </form>

            <p class="mt-4 mb-1 text-center text-muted" style="font-size: 0.85rem;">
                <a href="index.php" class="text-decoration-none"><i class="fas fa-clinic-medical me-1"></i>Volver al Sitio Principal</a>
            </p>
            <p class="mt-2 mb-0 text-center text-muted" style="font-size: 0.85rem;">
                &copy; <?php echo date("Y"); ?> DentexaPro. Todos los derechos reservados.
            </p>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>