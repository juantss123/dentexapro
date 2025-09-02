<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!user_has_permission('inventory', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$success_message = '';
$error_message = '';
$info_message = '';

$page_title = "Reporte de Estado de Turnos por Período";
$status_counts = ['Programado' => 0, 'Completado' => 0, 'Cancelado' => 0];
$start_date_val = date('Y-m-01');
$end_date_val = date('Y-m-t');
$total_appointments_period = 0;
$errors = [];
$report_generated = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['generate'])) {
    $report_generated = true;
    $start_date_val = $_REQUEST['start_date'] ?? $start_date_val;
    $end_date_val = $_REQUEST['end_date'] ?? $end_date_val;

    if (empty($start_date_val) || empty($end_date_val)) {
        $errors[] = "Por favor, seleccione ambas fechas.";
    } elseif (strtotime($end_date_val) < strtotime($start_date_val)) {
        $errors[] = "La fecha de fin no puede ser anterior a la fecha de inicio.";
    } else {
        $stmt = $mysqli->prepare("SELECT status, COUNT(*) as count
                                  FROM appointments
                                  WHERE DATE(datetime) BETWEEN ? AND ?
                                  GROUP BY status");
        $stmt->bind_param("ss", $start_date_val, $end_date_val);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (isset($status_counts[$row['status']])) {
                $status_counts[$row['status']] = $row['count'];
            }
            $total_appointments_period += $row['count'];
        }
        $result->close();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; }
        .chevron { transition: transform .2s; }
        .nav-link[aria-expanded="true"] .chevron { transform: rotate(180deg); }
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .summary-card { border-left-width: 5px; border-radius: .375rem; }
        .summary-card .card-body { padding: 1rem 1.25rem; }
        .summary-card .display-6 { font-weight: 500; }
        .border-primary-custom { border-left-color: var(--bs-primary) !important; }
        .border-success-custom { border-left-color: var(--bs-success) !important; }
        .border-danger-custom { border-left-color: var(--bs-danger) !important; }
        .border-secondary-custom { border-left-color: var(--bs-secondary) !important; }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
if (isMobileDevice()) { // Asume que isMobileDevice() está en config.php
    require_once ($path_to_root = '../') . '_sidebarmovil.php';

    // Barra de navegación superior para móviles CON LOGO CENTRADO
    echo '
    <nav class="navbar navbar-dark d-md-none sticky-top mb-3" style="background-color: #0d6efd;">
        <div class="container-fluid" style="position: relative; display: flex; align-items: center; justify-content: flex-start; min-height: 56px;"> 
            
           
            <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile" aria-controls="sidebarMobile" style="z-index: 10;"> 
                <i class="fas fa-bars"></i>
                <span class="d-none d-sm-inline ms-1">Menú</span>
            </button>

           
            <a class="navbar-brand" href="' . ($path_to_root ?: './') . 'dashboard.php" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); padding: 0; line-height: 1;"> 
                <img src="' . ($path_to_root ?: './') . 'assets/img/dentexapro-logo-iso-blanco.png" alt="DentexaPro" style="height: 40px; width: auto; max-height: 100%;"> 
            </a>
            
           
        </div>
    </nav>';
} else {
    require_once ($path_to_root ?: './') . '_sidebar.php';
}
?>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <div class="card main-content-card shadow-sm rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <h2 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-tasks me-3"></i><?php echo $page_title; ?>
                        </h2>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Volver a Reportes
                        </a>
                    </div>

                     <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="appointment_status_report.php" class="mb-4 p-3 border rounded bg-light">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="start_date" class="form-label fw-bold">Fecha de Inicio:</label>
                                <input type="date" class="form-control form-control-lg" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date_val); ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label for="end_date" class="form-label fw-bold">Fecha de Fin:</label>
                                <input type="date" class="form-control form-control-lg" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date_val); ?>" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="generate" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-filter me-1"></i>Generar
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if ($report_generated && empty($errors)): ?>
                        <h4 class="mt-4 mb-3">Resumen de Estado de Turnos</h4>
                        <p class="lead">Período: <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($start_date_val))); ?></strong> - <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($end_date_val))); ?></strong></p>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6 col-lg-3">
                                <div class="card summary-card border-secondary-custom shadow-sm h-100">
                                    <div class="card-body text-center d-flex flex-column justify-content-center">
                                        <h6 class="card-subtitle mb-2 text-muted">Total de Turnos</h6>
                                        <p class="display-6 text-secondary mb-0"><?php echo $total_appointments_period; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="card summary-card border-primary-custom shadow-sm h-100">
                                    <div class="card-body text-center d-flex flex-column justify-content-center">
                                        <h6 class="card-subtitle mb-2 text-muted">Programados</h6>
                                        <p class="display-6 text-primary mb-0"><?php echo $status_counts['Programado']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="card summary-card border-success-custom shadow-sm h-100">
                                    <div class="card-body text-center d-flex flex-column justify-content-center">
                                        <h6 class="card-subtitle mb-2 text-muted">Completados</h6>
                                        <p class="display-6 text-success mb-0"><?php echo $status_counts['Completado']; ?></p>
                                    </div>
                                </div>
                            </div>
                             <div class="col-md-6 col-lg-3">
                                <div class="card summary-card border-danger-custom shadow-sm h-100">
                                    <div class="card-body text-center d-flex flex-column justify-content-center">
                                        <h6 class="card-subtitle mb-2 text-muted">Cancelados</h6>
                                        <p class="display-6 text-danger mb-0"><?php echo $status_counts['Cancelado']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if($total_appointments_period == 0 && $report_generated): ?>
                             <div class="alert alert-secondary mt-4">
                                <i class="fas fa-info-circle me-2"></i>No se encontraron turnos para el período seleccionado.
                            </div>
                        <?php endif; ?>

                    <?php elseif (!$report_generated && empty($errors)): ?>
                         <div class="alert alert-light mt-4 text-center">
                            <i class="fas fa-search me-2"></i>Seleccione un rango de fechas y presione "Generar" para ver el resumen.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>