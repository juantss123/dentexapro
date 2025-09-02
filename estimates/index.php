<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!user_has_permission('estimates', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$success_message = '';
$error_message = '';
$info_message = '';


if (isset($_GET['status'])) {
    $status_get = $_GET['status'];
    $msg_get = isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : '';
    $estimate_number_get = isset($_GET['estimate_number']) ? htmlspecialchars(urldecode($_GET['estimate_number'])) : '';

    if ($status_get === 'success_create') {
        $success_message = '¡Presupuesto ' . $estimate_number_get . ' creado y enviado con éxito!';
    } elseif ($status_get === 'success_saved_draft') {
        $success_message = '¡Borrador del presupuesto ' . $estimate_number_get . ' guardado con éxito!';
    } elseif ($status_get === 'success_update' || $status_get === 'success_update_sent') {
        $success_message = '¡Presupuesto ' . $estimate_number_get . ' actualizado con éxito!';
        if ($status_get === 'success_update_sent' && !empty($msg_get)) { // Si hay un msg, podría ser una advertencia de email
             $success_message .= ' ' . $msg_get;
        } elseif ($status_get === 'success_update_sent') {
            $success_message .= ' Y (re)enviado por email.';
        }
    } elseif ($status_get === 'success_status_update') {
        $success_message = $msg_get ?: 'Estado del presupuesto actualizado con éxito.';
    } elseif ($status_get === 'success_delete') {
        $success_message = $msg_get ?: 'Presupuesto eliminado con éxito.';
    } elseif ($status_get === 'success_resent') {
        $success_message = $msg_get ?: 'Presupuesto reenviado por email con éxito.';
    } elseif ($status_get === 'info' && !empty($msg_get)) {
        $info_message = $msg_get;
    } elseif (strpos($status_get, 'error') !== false && !empty($msg_get)) {
        $error_message = $msg_get;
    } elseif (strpos($status_get, 'warning') !== false && !empty($msg_get)) { // Para warnings de email
        $error_message = "Advertencia: " . $msg_get; 
    }
}


// Lógica para obtener y listar presupuestos
$estimates_list = []; 
$search_term = trim($_GET['search_term'] ?? '');
$search_patient_id = filter_input(INPUT_GET, 'search_patient_id', FILTER_VALIDATE_INT);
$filter_status = trim($_GET['filter_status'] ?? '');

$sql_estimates = "SELECT e.id, e.estimate_number, e.estimate_date, e.total_amount, e.status, 
                         p.id as patient_id_for_link, /* Alias para el ID del paciente */
                         p.fname as patient_fname, p.lname as patient_lname, p.dni as patient_dni,
                         e.professional_name_text, 
                         au.name as admin_creator_name 
                  FROM estimates e 
                  JOIN patients p ON e.patient_id = p.id 
                  JOIN admin_users au ON e.admin_user_id = au.id";
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search_term)) {
    $search_like = "%" . $search_term . "%";
    $where_clauses[] = "(e.estimate_number LIKE ? OR p.fname LIKE ? OR p.lname LIKE ? OR p.dni LIKE ? OR e.professional_name_text LIKE ?)";
    array_push($params, $search_like, $search_like, $search_like, $search_like, $search_like);
    $types .= "sssss";
}
if ($search_patient_id) {
    $where_clauses[] = "e.patient_id = ?";
    $params[] = $search_patient_id;
    $types .= "i";
}
if (!empty($filter_status)) {
    $where_clauses[] = "e.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($where_clauses)) {
    $sql_estimates .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql_estimates .= " ORDER BY e.estimate_date DESC, e.id DESC";

$stmt_estimates = $mysqli->prepare($sql_estimates);
if ($stmt_estimates) {
    if (!empty($params)) {
        $stmt_estimates->bind_param($types, ...$params);
    }
    $stmt_estimates->execute();
    $result_estimates = $stmt_estimates->get_result();
    while ($row = $result_estimates->fetch_assoc()) {
        $estimates_list[] = $row;
    }
    if($result_estimates) $result_estimates->close();
    $stmt_estimates->close();
} else {
    // No sobreescribir $error_message si ya tiene algo de $_GET
    if(empty($error_message)) $error_message = "Error al preparar la consulta de presupuestos: " . $mysqli->error;
}

// Cargar pacientes para el filtro de búsqueda
$patients_for_filter_select = [];
$patients_filter_query = $mysqli->query("SELECT id, dni, fname, lname FROM patients ORDER BY lname ASC, fname ASC");
if ($patients_filter_query) {
    while ($patient_row_filter = $patients_filter_query->fetch_assoc()) {
        $patients_for_filter_select[] = $patient_row_filter;
    }
    $patients_filter_query->free();
}
$all_statuses_options = ['borrador', 'enviado', 'aprobado', 'rechazado', 'pagado'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Presupuestos - DentexaPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
       <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; }
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .table th i { margin-right: 5px; color: #6c757d;}
        .table td { vertical-align: middle; }
        .status-borrador { background-color: #e9ecef !important; color: #495057 !important; border: 1px solid #ced4da !important; }
        .status-enviado { background-color: #cfe2ff !important; color: #052c65 !important; border: 1px solid #9ec5fe !important;}
        .status-aprobado { background-color: #d1e7dd !important; color: #0a3622 !important; border: 1px solid #a3cfbb !important;}
        .status-rechazado { background-color: #f8d7da !important; color: #58151c !important; border: 1px solid #f1aeb5 !important;}
        .status-pagado { background-color: #cff0da !important; color: #0f5132 !important; border: 1px solid #a1d9ab !important;}
        .status-update-form .form-select-sm { font-size: .78rem; padding-top: 0.2rem; padding-bottom: 0.2rem; padding-right: 1.75rem } /* Ajustado para el select */
        .action-buttons .btn { margin: 0 2px; }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
</head>
<body>
    <div class="row g-0">
        <?php
        if (isMobileDevice()) {
            require_once $path_to_root . '_sidebarmovil.php';
            $mobileNavBarLogoPath = $path_to_root . 'assets/img/dentexapro-logo-iso-blanco.png';
            $dashboardPath = $path_to_root . 'dashboard.php';
            $mobileNavHTML = <<<HTML
<nav class="navbar navbar-dark d-md-none sticky-top mb-3" style="background-color: #0d6efd;">
    <div class="container-fluid" style="position: relative; display: flex; align-items: center; justify-content: flex-start; min-height: 56px;">
        <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile" aria-controls="sidebarMobile" style="z-index: 10;">
            <i class="fas fa-bars"></i><span class="d-none d-sm-inline ms-1">Menú</span>
        </button>
        <a class="navbar-brand" href="{$dashboardPath}" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); padding: 0; line-height: 1;">
            <img src="{$mobileNavBarLogoPath}" alt="DentexaPro" style="height: 40px; width: auto; max-height: 100%;">
        </a>
    </div>
</nav>
HTML;
            echo $mobileNavHTML;
        } else {
            require_once $path_to_root . '_sidebar.php';
        }
        ?>
        
        <main class="col-12 <?php if (!isMobileDevice()) echo 'col-md-9 col-lg-10'; ?> p-md-4 p-2">
            <div class="card main-content-card shadow-sm rounded-3">
                <div class="card-body p-lg-4 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <h2 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-file-alt me-3 text-primary text-info"></i>Gestión de Presupuestos
                        </h2>
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Nuevo Presupuesto
                        </a>
                    </div>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                     <?php if ($info_message): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle me-2"></i><?php echo $info_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="GET" action="index.php" class="mb-4 p-3 border rounded bg-light">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="search_term" class="form-label">Buscar (N°, Paciente, DNI, Prof.):</label>
                                <input type="text" name="search_term" id="search_term" class="form-control" value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="search_patient_id" class="form-label">Paciente Específico:</label>
                                <select name="search_patient_id" id="search_patient_id" class="form-select">
                                    <option value="">-- Todos --</option>
                                    <?php foreach ($patients_for_filter_select as $p_filter): ?>
                                        <option value="<?php echo $p_filter['id']; ?>" <?php if ($search_patient_id == $p_filter['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($p_filter['lname'] . ', ' . $p_filter['fname']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_status" class="form-label">Estado:</label>
                                <select name="filter_status" id="filter_status" class="form-select">
                                    <option value="">-- Todos --</option>
                                    <?php foreach ($all_statuses_options as $stat_opt): ?>
                                        <option value="<?php echo $stat_opt; ?>" <?php if ($filter_status == $stat_opt) echo 'selected'; ?>>
                                            <?php echo ucfirst($stat_opt); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-auto">
                                <button type="submit" class="btn btn-info w-100"><i class="fas fa-filter me-1"></i>Filtrar</button>
                            </div>
                            <div class="col-md-auto">
                                <a href="index.php" class="btn btn-outline-secondary w-100" title="Limpiar Filtros"><i class="fas fa-times"></i> Limpiar</a>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($estimates_list) && (empty($search_term) && empty($search_patient_id) && empty($filter_status)) ): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>No hay presupuestos registrados todavía. 
                            <a href="create.php" class="alert-link">Crea el primero</a>.
                        </div>
                    <?php elseif (empty($estimates_list) && (!empty($search_term) || !empty($search_patient_id) || !empty($filter_status))): ?>
                         <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>No se encontraron presupuestos con los filtros aplicados.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="fas fa-hashtag"></i>N°</th>
                                        <th><i class="fas fa-calendar-alt"></i>Fecha</th>
                                        <th><i class="fas fa-user-injured"></i>Paciente</th>
                                        <th><i class="fas fa-user-md"></i>Profesional</th>
                                        <th class="text-end"><i class="fas fa-dollar-sign"></i>Monto</th>
                                        <th style="min-width: 180px;"><i class="fas fa-check-circle"></i>Estado</th>
                                        <th style="min-width: 210px;"><i class="fas fa-cogs"></i>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estimates_list as $est): ?>
                                    <tr id="estimate-row-<?php echo $est['id']; ?>">
                                        <td><?php echo htmlspecialchars($est['estimate_number'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($est['estimate_date']))); ?></td>
                                        <td>
                                            <a href="<?php echo $path_to_root . 'patients/history.php?patient_id=' . htmlspecialchars($est['patient_id_for_link'] ?? ''); ?>" title="Ver historial de <?php echo htmlspecialchars($est['patient_fname'] . ' ' . $est['patient_lname']); ?>">
                                                <?php echo htmlspecialchars($est['patient_lname'] . ', ' . $est['patient_fname']); ?>
                                            </a>
                                            <br><small class="text-muted">DNI: <?php echo htmlspecialchars($est['patient_dni']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($est['professional_name_text'] ?: $est['admin_creator_name']); ?></td>
                                        <td class="text-end fw-bold">$<?php echo number_format($est['total_amount'], 2, ',', '.'); ?></td>
                                        <td>
                                            <form action="actions/update_estimate_status.php" method="POST" class="d-inline-flex align-items-center status-update-form">
                                                <input type="hidden" name="estimate_id_status_update" value="<?php echo $est['id']; ?>">
                                                <input type="hidden" name="estimate_number_for_msg" value="<?php echo htmlspecialchars($est['estimate_number']); ?>">
                                                <select name="new_status" class="form-select form-select-sm status-<?php echo htmlspecialchars($est['status']); ?>" onchange="this.form.submit()">
                                                    <?php foreach ($all_statuses_options as $status_opt): ?>
                                                        <option value="<?php echo $status_opt; ?>" <?php if ($est['status'] == $status_opt) echo 'selected'; ?> class="status-<?php echo $status_opt; ?>">
                                                            <?php echo ucfirst($status_opt); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                </form>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="view_pdf.php?id=<?php echo $est['id']; ?>&action=view" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver PDF"><i class="fas fa-eye"></i></a>
                                            <a href="view_pdf.php?id=<?php echo $est['id']; ?>&action=download" class="btn btn-sm btn-outline-info" title="Descargar PDF"><i class="fas fa-download"></i></a>
                                            <a href="edit.php?id=<?php echo $est['id']; ?>" class="btn btn-sm btn-outline-warning" title="Editar Presupuesto"><i class="fas fa-edit"></i></a>
                                            <a href="actions/resend_estimate_email.php?estimate_id=<?php echo $est['id']; ?>" 
                                               class="btn btn-sm btn-outline-success send-email-btn" 
                                               data-estimate-id="<?php echo $est['id']; ?>" 
                                               title="Reenviar por Email"
                                               onclick="return confirm('¿Está seguro que desea reenviar este presupuesto (N° <?php echo htmlspecialchars($est['estimate_number']); ?>) por email? Se utilizará la información más actual del presupuesto.');"
                                               <?php if ($est['status'] === 'borrador') echo 'disabled style="pointer-events: none; opacity: 0.65;"'; ?>> 
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                            <form action="actions/delete_estimate.php" method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de que desea eliminar el presupuesto N° <?php echo htmlspecialchars($est['estimate_number']); ?>? Esta acción es irreversible.');">
                                                <input type="hidden" name="estimate_id" value="<?php echo $est['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar Presupuesto"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#search_patient_id').select2({
                theme: "bootstrap-5",
                placeholder: "-- Todos los Pacientes --",
                allowClear: true,
                width: '100%', // Asegurar que ocupe el ancho del contenedor
            });

            var autoDismissAlerts = $('.alert.alert-dismissible');
            autoDismissAlerts.each(function() {
                var alertEl = this;
                setTimeout(function() {
                    if ($.contains(document, alertEl)) {
                        var bootstrapAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                        if (bootstrapAlert) bootstrapAlert.close();
                    }
                }, 7000);
            });

            // Aplicar clase inicial al cargar la página para los selects de estado
            $('.status-update-form select[name="new_status"]').each(function() {
                $(this).removeClass (function (index, className) { // Limpiar clases status- previas
                    return (className.match (/(^|\s)status-\S+/g) || []).join(' ');
                });
                $(this).addClass('status-' + $(this).val()); // Añadir clase actual
            });
            
            // No es necesario el .on('change') si el onchange="this.form.submit()" está en el HTML del select.
            // Si prefieres manejarlo con jQuery:
            // $('.status-update-form select[name="new_status"]').on('change', function() {
            //     this.form.submit();
            // });


            // El botón de reenviar ya tiene un onclick en el HTML, así que no es necesario
            // volver a añadir un listener aquí a menos que quieras hacer AJAX.
            // $('.send-email-btn').on('click', function(){ ... });

        });
    </script>
</body>
</html>