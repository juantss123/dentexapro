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

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
if (!$item_id) {
    header('Location: index.php?status=error&msg=' . urlencode('ID de insumo no válido para ver el log.'));
    exit;
}

// Cargar datos del insumo
$stmt_item = $mysqli->prepare("SELECT item_name FROM inventory_items WHERE id = ?");
$item_name = "Desconocido";
if ($stmt_item) {
    $stmt_item->bind_param("i", $item_id);
    $stmt_item->execute();
    $result_item = $stmt_item->get_result();
    if ($item_data = $result_item->fetch_assoc()) {
        $item_name = $item_data['item_name'];
    } else {
        header('Location: index.php?status=error&msg=' . urlencode('Insumo no encontrado para ver el log.'));
        exit;
    }
    $stmt_item->close();
} else {
    die("Error al preparar la consulta del insumo: " . $mysqli->error);
}

// Cargar historial de movimientos para este insumo
$log_entries = [];
$stmt_log = $mysqli->prepare("
    SELECT sl.*, IFNULL(au.name, 'Sistema/Desconocido') as admin_name 
    FROM inventory_stock_log sl
    LEFT JOIN admin_users au ON sl.admin_id = au.id
    WHERE sl.item_id = ? 
    ORDER BY sl.adjustment_date DESC
");
if ($stmt_log) {
    $stmt_log->bind_param("i", $item_id);
    $stmt_log->execute();
    $result_log = $stmt_log->get_result();
    while ($log_row = $result_log->fetch_assoc()) {
        $log_entries[] = $log_row;
    }
    $stmt_log->close();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Stock: <?php echo htmlspecialchars($item_name); ?> - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body{overflow-x:hidden; background-color: #f8f9fa;}
        #sidebar{min-height:100vh;}
        .chevron{transition:transform .2s;}
        .nav-link[aria-expanded="true"] .chevron{transform:rotate(180deg);}
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .table th i { margin-right: 5px; }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
        .adjustment-add { color: var(--bs-success); }
        .adjustment-subtract { color: var(--bs-danger); }
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
                <div class="card-header bg-secondary text-white">
                     <h3 class="mb-0 d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-history me-2"></i>Historial de Movimientos de Stock</span>
                        <span class="fs-5">Insumo: <?php echo htmlspecialchars($item_name); ?></span>
                    </h3>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-end mb-3">
                        <a href="index.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-2"></i>Volver al Listado de Insumos</a>
                    </div>

                    <?php if (empty($log_entries)): ?>
                        <div class="alert alert-info">No hay movimientos registrados para este insumo.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th><i class="fas fa-calendar-alt"></i>Fecha y Hora</th>
                                        <th><i class="fas fa-user-cog"></i>Admin</th>
                                        <th><i class="fas fa-exchange-alt"></i>Tipo</th>
                                        <th class="text-center"><i class="fas fa-minus-circle"></i><i class="fas fa-plus-circle"></i> Cant. Ajustada</th>
                                        <th class="text-center">Stock Anterior</th>
                                        <th class="text-center">Stock Resultante</th>
                                        <th><i class="fas fa-comment-dots"></i>Motivo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($log_entries as $entry): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($entry['adjustment_date']))); ?></td>
                                            <td><?php echo htmlspecialchars($entry['admin_name']); ?></td>
                                            <td>
                                                <?php if ($entry['adjustment_type'] == 'add'): ?>
                                                    <span class="badge bg-success">Ingreso/Añadido</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Egreso/Restado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center <?php echo ($entry['adjustment_type'] == 'add' ? 'adjustment-add' : 'adjustment-subtract'); ?> fw-bold">
                                                <?php echo ($entry['adjustment_type'] == 'add' ? '+' : '-') . $entry['quantity_adjusted']; ?>
                                            </td>
                                            <td class="text-center"><?php echo $entry['stock_before_adj']; ?></td>
                                            <td class="text-center fw-bold"><?php echo $entry['stock_after_adj']; ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($entry['reason'] ?: '-')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
             <div class="text-center mt-3">
                <a href="<?php echo $path_to_root; ?>dashboard.php" class="btn btn-link"><i class="fas fa-arrow-left me-1"></i>Volver al Panel</a>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
