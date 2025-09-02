<?php
session_start();
// Si el paciente ya inició sesión, redirigir a su dashboard del portal
if (isset($_SESSION['patient_id'])) {
    header('Location: index.php'); // Asumiremos que index.php es el dashboard del portal
    exit;
}
require_once '../config.php'; // Subir un nivel para acceder a config.php

$error_message = '';
$login_email_portal = ''; // Para repoblar el campo email en caso de error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_email_portal = trim($_POST['email_portal'] ?? '');
    $password_portal = $_POST['password_portal'] ?? '';

    if (empty($login_email_portal) || empty($password_portal)) {
        $error_message = 'Por favor, complete todos los campos.';
    } else {
        // Buscar al paciente por su email de acceso al portal
        $stmt = $mysqli->prepare("SELECT id, fname, lname, patient_password_hash, patient_portal_access FROM patients WHERE patient_user_email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $login_email_portal);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($patient = $result->fetch_assoc()) {
                // Verificar si el acceso al portal está habilitado
                if ($patient['patient_portal_access'] === 'habilitado') {
                    // Verificar la contraseña
                    if ($patient['patient_password_hash'] && password_verify($password_portal, $patient['patient_password_hash'])) {
                        // Inicio de sesión exitoso
                        $_SESSION['patient_id'] = $patient['id'];
                        $_SESSION['patient_fname'] = $patient['fname'];
                        $_SESSION['patient_lname'] = $patient['lname'];
                        // Regenerar ID de sesión por seguridad
                        session_regenerate_id(true);
                        header('Location: index.php'); // Redirigir al dashboard del portal
                        exit;
                    } else {
                        $error_message = 'Email o contraseña incorrecta.';
                    }
                } else {
                    $error_message = 'El acceso al portal no está habilitado para esta cuenta. Por favor, contacte a la clínica.';
                }
            } else {
                $error_message = 'Email o contraseña incorrecta.';
            }
            $stmt->close();
        } else {
            $error_message = "Error al preparar la consulta: " . $mysqli->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Portal del Paciente - DentexaPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: #495057;
            padding: 1rem;
            
            /* Imagen de fondo para el body */
            /* LA RUTA DEBE SER RELATIVA AL DIRECTORIO ACTUAL (portal/) O ABSOLUTA DESDE LA RAÍZ DEL SITIO */
            background-image: url('../assets/img/mi-fondo-hero.webp'); /* Ruta ajustada */
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
            background-color: rgba(10, 30, 75, 0.5); /* Un azul oscuro semitransparente, puedes ajustar */
            z-index: 0;
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            position: relative; /* Para que esté sobre el overlay del body */
            z-index: 1;
        }
        .login-card {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 2.5rem 3rem;
            border-radius: 1rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            border: none;
        }
        .login-logo {
            max-width: 280px; /* Ajustado para el logo principal */
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
                background-color: rgba(255, 255, 255, 0.98);
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
                <img src="../assets/img/dentexapro-logo.png" alt="DentexaPro Logo" class="login-logo">
                <h1 class="login-title">Portal del Paciente</h1>
                <p class="login-subtitle">Accede a tu información y gestiona tus turnos</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-custom" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php"> 
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email_portal" name="email_portal" placeholder="Email de Acceso" required autofocus value="<?php echo htmlspecialchars($login_email_portal); ?>">
                    <label for="email_portal"><i class="fas fa-at me-2"></i>Email de Acceso</label>
                </div>
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password_portal" name="password_portal" placeholder="Contraseña" required>
                    <label for="password_portal"><i class="fas fa-lock me-2"></i>Contraseña</label>
                </div>
                <button class="btn btn-primary w-100 btn-login" type="submit">
                    <i class="fas fa-sign-in-alt me-2"></i>Ingresar
                </button>
            </form>
            <p class="mt-4 mb-1 text-center text-muted" style="font-size: 0.85rem;">
                <a href="../index.php" class="text-decoration-none"><i class="fas fa-clinic-medical me-1"></i>Volver al Sitio Principal</a>
            </p>
            <p class="mt-2 mb-0 text-center text-muted" style="font-size: 0.85rem;">
                &copy; <?php echo date("Y"); ?> DentexaPro. Todos los derechos reservados.
            </p>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>