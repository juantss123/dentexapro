<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!user_has_permission('reports', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$success_message = '';
$error_message = '';
$info_message = '';

$page_title = "Reporte de Pacientes Nuevos por Período";
$report_data = [];
$start_date_val = date('Y-m-01');
$end_date_val = date('Y-m-t');
$total_new_patients = 0;
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
        $stmt = $mysqli->prepare("SELECT id, dni, fname, lname, phone, email, birthdate, created_at
                                  FROM patients
                                  WHERE DATE(created_at) BETWEEN ? AND ?
                                  ORDER BY created_at ASC");
        $stmt->bind_param("ss", $start_date_val, $end_date_val);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
        }
        $total_new_patients = count($report_data);
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
        .table th i { margin-right: 5px; }
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
                            <i class="fas fa-user-plus me-3"></i><?php echo $page_title; ?>
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

                    <form method="POST" action="new_patients_by_period.php" class="mb-4 p-3 border rounded bg-light">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="start_date" class="form-label fw-bold">Fecha de Inicio (Alta):</label>
                                <input type="date" class="form-control form-control-lg" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date_val); ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label for="end_date" class="form-label fw-bold">Fecha de Fin (Alta):</label>
                                <input type="date" class="form-control form-control-lg" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date_val); ?>" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="generate" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-filter me-1"></i>Generar
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if ($report_generated): ?>
                        <?php if (empty($errors) && !empty($report_data)): ?>
                            <h4 class="mt-4 mb-3">Resultados del Reporte <small class="text-muted fs-6">(Total: <?php echo $total_new_patients; ?> pacientes nuevos)</small></h4>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th><i class="fas fa-id-badge"></i>ID</th>
                                            <th><i class="fas fa-id-card"></i>DNI</th>
                                            <th><i class="fas fa-user"></i>Nombre</th>
                                            <th><i class="fas fa-user"></i>Apellido</th>
                                            <th><i class="fas fa-phone"></i>Teléfono</th>
                                            <th><i class="fas fa-envelope"></i>Email</th>
                                            <th><i class="fas fa-calendar-star"></i>Fecha Nac.</th>
                                            <th><i class="fas fa-calendar-plus"></i>Fecha de Alta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo htmlspecialchars($row['dni']); ?></td>
                                                <td><?php echo htmlspecialchars($row['fname']); ?></td>
                                                <td><?php echo htmlspecialchars($row['lname']); ?></td>
                                                <td><?php echo htmlspecialchars($row['phone'] ?: '-'); ?></td>
                                                <td><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                                                <td><?php echo $row['birthdate'] ? htmlspecialchars(date('d/m/Y', strtotime($row['birthdate']))) : '-'; ?></td>
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif (empty($errors)): ?>
                            <div class="alert alert-secondary mt-4">
                                <i class="fas fa-info-circle me-2"></i>No se encontraron pacientes nuevos para el período seleccionado.
                            </div>
                        <?php endif; ?>
                     <?php else: ?>
                         <div class="alert alert-light mt-4 text-center">
                            <i class="fas fa-search me-2"></i>Seleccione un rango de fechas y presione "Generar" para ver el reporte.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>