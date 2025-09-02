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

$page_title = "Reporte de Turnos por Período";
$report_data = [];
$start_date_val = date('Y-m-01'); 
$end_date_val = date('Y-m-t');  
$total_appointments = 0;
$errors = [];
$report_generated = false;

$export_csv = isset($_POST['export_csv_appointments']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['generate']) || $export_csv) {
    $report_generated = true; 

    if ($export_csv) {
        $start_date_val = $_POST['start_date_export'] ?? $start_date_val;
        $end_date_val = $_POST['end_date_export'] ?? $end_date_val;
    } else {
        $start_date_val = $_REQUEST['start_date'] ?? $start_date_val; 
        $end_date_val = $_REQUEST['end_date'] ?? $end_date_val;
    }

    if (empty($start_date_val) || empty($end_date_val)) {
        if (!$export_csv) $errors[] = "Por favor, seleccione ambas fechas, inicio y fin.";
    } elseif (strtotime($end_date_val) < strtotime($start_date_val)) {
        if (!$export_csv) $errors[] = "La fecha de fin no puede ser anterior a la fecha de inicio.";
    } else {
        $stmt = $mysqli->prepare("SELECT a.id, a.datetime, p.fname, p.lname, p.dni AS patient_dni, a.reason, a.status
                                  FROM appointments a
                                  JOIN patients p ON a.patient_id = p.id
                                  WHERE DATE(a.datetime) BETWEEN ? AND ?
                                  ORDER BY a.datetime ASC");
        if ($stmt) {
            $stmt->bind_param("ss", $start_date_val, $end_date_val);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            $total_appointments = count($report_data);
            $result->close();
            $stmt->close();

            if ($export_csv && empty($errors)) {
                $filename = "reporte_turnos_" . str_replace('-', '', $start_date_val) . "_a_" . str_replace('-', '', $end_date_val) . ".csv";
                
                // Limpiar cualquier buffer de salida ANTES de enviar cabeceras
                if (ob_get_level()) {
                    ob_end_clean();
                }

                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                
                $output = fopen('php://output', 'w');
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8

                // Encabezados del CSV, usando punto y coma como delimitador
                fputcsv($output, ['ID Turno', 'Fecha', 'Hora', 'Nombre Paciente', 'Apellido Paciente', 'DNI Paciente', 'Motivo Consulta', 'Estado Turno'], ';');
                
                // Datos, usando punto y coma como delimitador
                foreach ($report_data as $row_csv) {
                    $datetime_obj = new DateTime($row_csv['datetime']);
                    fputcsv($output, [
                        $row_csv['id'],
                        $datetime_obj->format('d/m/Y'),
                        $datetime_obj->format('H:i'),
                        $row_csv['fname'],
                        $row_csv['lname'],
                        $row_csv['patient_dni'],
                        $row_csv['reason'],
                        $row_csv['status']
                    ], ';');
                }
                fclose($output);
                exit;
            }
        } else {
             if (!$export_csv) $errors[] = "Error al preparar la consulta: " . $mysqli->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title); ?> - Clínica Dental</title>
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
        .table th { font-weight: 600; } 
        .table th i { margin-right: 8px; color: #6c757d; } 
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
        .report-header {
            background-color: #e9ecef; 
            padding: 1rem 1.5rem;
            border-radius: .375rem;
            margin-bottom: 1.5rem;
        }
        .report-header h4 {
            margin-bottom: 0.25rem;
        }
        .report-header p {
            margin-bottom: 0;
            font-size: 0.95rem;
        }
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
                            <i class="fas fa-calendar-day me-3 text-primary"></i><?php echo htmlspecialchars($page_title); ?>
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

                    <form method="POST" action="appointments_by_period.php" class="mb-4 p-3 border rounded bg-light shadow-sm">
                        <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filtrar Reporte</h5>
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
                                    <i class="fas fa-cogs me-1"></i>Generar
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if ($report_generated && empty($errors)): ?>
                        <div class="report-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4>Resultados del Reporte</h4>
                                    <p class="text-muted">
                                        Período: <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($start_date_val))); ?></strong> al <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($end_date_val))); ?></strong>
                                        <span class="ms-3 badge bg-secondary align-middle">Total: <?php echo $total_appointments; ?> turnos</span>
                                    </p>
                                </div>
                                <?php if (!empty($report_data)): ?>
                                    <form method="POST" action="appointments_by_period.php" class="ms-3">
                                        <input type="hidden" name="start_date_export" value="<?php echo htmlspecialchars($start_date_val); ?>">
                                        <input type="hidden" name="end_date_export" value="<?php echo htmlspecialchars($end_date_val); ?>">
                                        <button type="submit" name="export_csv_appointments" class="btn btn-success">
                                            <i class="fas fa-file-csv me-2"></i>Exportar a CSV
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($report_data)): ?>
                            <div class="table-responsive mt-3">
                                <table class="table table-striped table-bordered table-hover align-middle">
                                    <thead class="table-light"> 
                                        <tr>
                                            <th><i class="fas fa-hashtag"></i>ID</th>
                                            <th><i class="fas fa-calendar-alt"></i>Fecha y Hora</th>
                                            <th><i class="fas fa-user"></i>Paciente</th>
                                            <th><i class="fas fa-id-card"></i>DNI Paciente</th>
                                            <th><i class="fas fa-notes-medical"></i>Motivo Consulta</th>
                                            <th><i class="fas fa-info-circle"></i>Estado Turno</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($row['datetime']))); ?></td>
                                                <td><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></td>
                                                <td><?php echo htmlspecialchars($row['patient_dni']); ?></td>
                                                <td><?php echo htmlspecialchars($row['reason'] ?: '-'); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    $status_text = htmlspecialchars($row['status']);
                                                    switch ($row['status']) {
                                                        case 'Programado': $status_class = 'badge text-bg-primary'; break;
                                                        case 'Completado': $status_class = 'badge text-bg-success'; break;
                                                        case 'Cancelado': $status_class = 'badge text-bg-danger'; break;
                                                        default: $status_class = 'badge text-bg-secondary'; break;
                                                    }
                                                    echo '<span class="' . $status_class . '">' . $status_text . '</span>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>No se encontraron turnos para el período seleccionado.
                            </div>
                        <?php endif; ?>
                    <?php elseif (!$report_generated && empty($errors)): ?>
                         <div class="alert alert-light mt-4 text-center border shadow-sm">
                            <i class="fas fa-search fa-2x text-muted mb-2 d-block"></i>
                            <p class="mb-0">Seleccione un rango de fechas y presione "Generar" para ver el reporte.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
