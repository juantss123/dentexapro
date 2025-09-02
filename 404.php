<?php
// Opcional: Iniciar sesión y cargar config si necesitas datos de sesión o configuraciones globales
// session_start();
// require_once 'config.php'; // Ajusta la ruta si es necesario

// Determinar a dónde redirigir al usuario
$go_back_url = "index.php"; // Por defecto, a la página de inicio pública
$go_back_text = "Volver a la Página Principal";

if (isset($_SESSION['admin_id'])) {
    $go_back_url = "dashboard.php"; // Si es un admin, al dashboard del panel
    $go_back_text = "Volver al Dashboard";
} elseif (isset($_SESSION['patient_id'])) {
    $go_back_url = "portal/index.php"; // Si es un paciente, al dashboard del portal
    $go_back_text = "Volver al Portal del Paciente";
}

// Enviar el código de estado HTTP 404 Not Found
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Página No Encontrada (Error 404) - DentexaPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            background: linear-gradient(135deg, #dfe4ea 0%, #a5b1c2 100%);
            font-family: 'Poppins', sans-serif; 
            color: #495057;
            text-align: center;
            padding: 1rem;
        }
        .error-container { 
            max-width: 600px; 
            width: 100%; 
        }
        .error-card { 
            background-color: #fff; 
            padding: 2.5rem 3rem; 
            border-radius: 1rem; 
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1); 
            border: none; 
        }
        .error-logo {
            max-width: 220px; 
            height: auto;
            margin-bottom: 2rem; 
        }
        /* .error-icon ya no es necesario si eliminamos el icono de la muela */
        /*
        .error-icon {
            font-size: 5rem;
            color: #0d6efd; 
            margin-bottom: 1.5rem;
        }
        */
        .error-title { 
            font-weight: 700; 
            color: #343a40; 
            margin-top: 1.5rem; /* Ajustar margen superior si el icono se va */
            margin-bottom: 0.75rem; 
            font-size: 2.5rem; 
        }
        .error-subtitle { 
            font-size: 1.1rem; 
            color: #6c757d; 
            margin-bottom: 2rem; 
        }
        .btn-go-back { 
            padding: 0.85rem 1.5rem; 
            font-size: 1.05rem; 
            font-weight: 600; 
            border-radius: 0.5rem; 
            transition: all 0.3s ease; 
        }
    </style>
</head>
<body>
    <main class="error-container">
        <div class="error-card">
            <img src="https://draturano.dentexapro.com/assets/img/dentexapro-logo.png" alt="DentexaPro Logo" class="error-logo">

            <h1 class="error-title">Oops! Error 404</h1>
            <p class="error-subtitle">La página que estás buscando no se pudo encontrar.<br>Es posible que haya sido eliminada, que haya cambiado de nombre o que no esté disponible temporalmente.</p>
            
            <a href="<?php echo htmlspecialchars($go_back_url); ?>" class="btn btn-primary btn-go-back">
                <i class="fas fa-home me-2"></i><?php echo htmlspecialchars($go_back_text); ?>
            </a>
            
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>