<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); // Ajustar ruta a login si es necesario
    exit;
}
require_once '../config.php'; // Ajustar ruta a config si es necesario

$path_to_root = '../'; // Ruta desde 'billing/' hasta la raíz del proyecto
$current_page_script_path = $_SERVER['PHP_SELF']; // Para la lógica del sidebar

if (!user_has_permission('billing', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Cobros - DentexaPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo $path_to_root; ?>assets/img/favicon_dental.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $path_to_root; ?>assets/css/global_styles.css"> <style>
        /* Puedes añadir estilos específicos para esta página aquí si es necesario */
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
        if (isMobileDevice()) {
            require_once $path_to_root . '_sidebarmovil.php';
             echo '<nav class="navbar navbar-dark d-md-none sticky-top mb-3" style="background-color: #0d6efd;">
                    <div class="container-fluid" style="position: relative; display: flex; align-items: center; justify-content: flex-start; min-height: 56px;">
                        <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile" aria-controls="sidebarMobile" style="z-index: 10;">
                            <i class="fas fa-bars"></i><span class="d-none d-sm-inline ms-1">Menú</span>
                        </button>
                        <a class="navbar-brand" href="' . $path_to_root . 'dashboard.php" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); padding: 0; line-height: 1;">
                            <img src="' . $path_to_root . 'assets/img/dentexapro-logo-iso-blanco.png" alt="DentexaPro" style="height: 40px; width: auto; max-height: 100%;">
                        </a>
                    </div>
                  </nav>';
        } else {
            require_once $path_to_root . '_sidebar.php';
        }
        ?>
        
        <main class="col-12 <?php if (!isMobileDevice()) echo 'col-md-9 col-lg-10'; ?> p-md-4 p-2">
            <div class="card main-content-card shadow-sm rounded-3">
                <div class="card-body p-lg-4 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <h2 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-file-invoice-dollar me-3 text-primary"></i>Módulo de Cobros
                        </h2>
                        <a href="<?php echo $path_to_root; ?>dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Dashboard
                        </a>
                    </div>

                    <p class="lead">Bienvenido al módulo de gestión de cobros.</p>
                    <p>Desde aquí podrá acceder a las diferentes funcionalidades relacionadas con la facturación y presupuestos de la clínica.</p>
                    
                    <div class="mt-4">
                        <a href="<?php echo $path_to_root; ?>estimates/index.php" class="btn btn-primary">
                            <i class="fas fa-file-alt me-2"></i>Gestionar Presupuestos
                        </a>
                        <?php // ?>
                    </div>

                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>