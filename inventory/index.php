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

$search_term = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

$items_result = null;
$sql = "SELECT * FROM inventory_items";
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search_term)) {
    $where_clauses[] = "(item_name LIKE ? OR item_description LIKE ? OR supplier LIKE ?)";
    $search_like = "%" . $search_term . "%";
    array_push($params, $search_like, $search_like, $search_like);
    $types .= "sss";
}

if (!empty($category_filter)) {
    $where_clauses[] = "category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY item_name ASC";

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $items_result = $stmt->get_result();
} else {
    // Manejar error de preparación de consulta si es necesario
     error_log("Error en la preparación de la consulta de insumos: " . $mysqli->error);
}

// Obtener categorías para el filtro
$categories = [];
$category_query_result = $mysqli->query("SELECT DISTINCT category FROM inventory_items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
if ($category_query_result) {
    while ($cat_row = $category_query_result->fetch_assoc()) {
        $categories[] = $cat_row['category'];
    }
    $category_query_result->free();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Insumos - Clínica Dental</title>
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
        .actions-cell .btn { margin-right: 0.25rem; margin-bottom: 0.25rem;}
        .stock-ok { color: var(--bs-success); }
        .stock-low { color: var(--bs-warning); font-weight: bold; }
        .stock-critical { color: var(--bs-danger); font-weight: bold; animation: blinkWarning 1.5s infinite; }
        @keyframes blinkWarning { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }
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
                        <h2 class="mb-0 d-flex align-items-center"><i class="fas fa-boxes me-3"></i>Gestión de Insumos</h2>
                        <a href="create.php" class="btn btn-primary btn-lg"><i class="fas fa-plus-circle me-2"></i> Nuevo Insumo</a>
                    </div>

                    <?php if(isset($_GET['status']) && isset($_GET['msg'])): ?>
                        <?php 
                        $status_class = 'alert-info'; 
                        $status_icon = 'fas fa-info-circle';
                        // Comprobar si el status contiene 'success'
                        if (strpos($_GET['status'], 'success') !== false) {
                            $status_class = 'alert-success';
                            $status_icon = 'fas fa-check-circle';
                        } elseif ($_GET['status'] == 'error') {
                            $status_class = 'alert-danger';
                             $status_icon = 'fas fa-exclamation-triangle';
                        }
                        ?>
                        <div class="alert <?php echo $status_class; ?> alert-dismissible fade show" role="alert">
                            <i class="<?php echo $status_icon; ?> me-2"></i><?php echo htmlspecialchars(urldecode($_GET['msg'])); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>


                    <form class="mb-4 p-3 border rounded bg-light" method="GET" action="index.php">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="search" class="form-label fw-bold">Buscar Insumo:</label>
                                <input type="text" name="search" id="search" class="form-control form-control-lg" placeholder="Nombre, descripción, proveedor..." value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="category" class="form-label fw-bold">Filtrar por Categoría:</label>
                                <select name="category" id="category" class="form-select form-select-lg">
                                    <option value="">Todas las categorías</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php if ($category_filter == $cat) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button class="btn btn-info btn-lg w-100 me-2" type="submit"><i class="fas fa-filter me-1"></i>Filtrar/Buscar</button>
                                <?php if ($search_term || $category_filter): ?>
                                    <a href="index.php" class="btn btn-outline-secondary btn-lg w-auto" title="Limpiar Filtros"><i class="fas fa-times"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-tag"></i>Nombre</th>
                                    <th><i class="fas fa-align-left"></i>Descripción</th>
                                    <th><i class="fas fa-layer-group"></i>Categoría</th>
                                    <th class="text-center"><i class="fas fa-boxes"></i>Stock Actual</th>
                                    <th class="text-center"><i class="fas fa-exclamation-triangle"></i>Alerta Stock Mín.</th>
                                    <th><i class="fas fa-ruler-combined"></i>Unidad</th>
                                    <th><i class="fas fa-truck"></i>Proveedor</th>
                                    <th style="min-width: 220px;"><i class="fas fa-toolbox"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($items_result && $items_result->num_rows > 0): ?>
                                <?php while ($item = $items_result->fetch_assoc()): 
                                    $stock_status_class = 'stock-ok';
                                    // Asegurarse que min_quantity_alert sea mayor que 0 para que la alerta de stock bajo tenga sentido
                                    if ($item['current_quantity'] <= 0) {
                                        $stock_status_class = 'stock-critical';
                                    } elseif ($item['min_quantity_alert'] > 0 && $item['current_quantity'] <= $item['min_quantity_alert']) {
                                        $stock_status_class = 'stock-low';
                                    }
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                        <td><?php echo nl2br(htmlspecialchars($item['item_description'] ?: '-')); ?></td>
                                        <td><?php echo htmlspecialchars($item['category'] ?: '-'); ?></td>
                                        <td class="text-center <?php echo $stock_status_class; ?> fs-5">
                                            <?php echo $item['current_quantity']; ?>
                                        </td>
                                        <td class="text-center"><?php echo $item['min_quantity_alert']; ?></td>
                                        <td><?php echo htmlspecialchars($item['unit_measure'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['supplier'] ?: '-'); ?></td>
                                        <td class="actions-cell">
                                            <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning" title="Editar Insumo"><i class="fas fa-edit"></i></a>
                                            <button type="button" class="btn btn-sm btn-info adjust-stock-btn" 
                                                    data-bs-toggle="modal" data-bs-target="#adjustStockModal" 
                                                    data-item-id="<?php echo $item['id']; ?>" 
                                                    data-item-name="<?php echo htmlspecialchars(addslashes($item['item_name'])); ?>" 
                                                    data-current-stock="<?php echo $item['current_quantity']; ?>" 
                                                    title="Ajustar Stock">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            <a href="stock_log.php?item_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-secondary" title="Ver Historial de Stock">
                                                <i class="fas fa-history"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" title="Eliminar Insumo" onclick="return confirm('¿Está seguro de que desea eliminar este insumo \'<?php echo htmlspecialchars(addslashes($item['item_name'])); ?>\'? Esta acción no se puede deshacer.');"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <?php if ($search_term || $category_filter): ?>
                                            No se encontraron insumos que coincidan con los filtros aplicados.
                                        <?php else: ?>
                                            No hay insumos registrados. Comience por agregar uno nuevo.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    if (isset($stmt) && $stmt) $stmt->close(); 
                    if ($items_result) $items_result->close(); 
                    ?>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="adjustStockModalLabel">Ajustar Stock de: <span id="itemNameModal"></span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="adjustStockForm" method="POST" action="<?php echo $path_to_root; ?>actions/adjust_stock_action.php">
            <div class="modal-body">
              <input type="hidden" name="item_id" id="modalItemId">
              <p>Stock Actual: <strong id="modalCurrentStock"></strong></p>
              <div class="mb-3">
                <label for="adjustment_type" class="form-label">Tipo de Ajuste:</label>
                <select class="form-select" name="adjustment_type" id="adjustment_type" required>
                    <option value="add">Añadir (Ingreso/Compra)</option>
                    <option value="subtract">Restar (Uso/Pérdida)</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="quantity_adjusted" class="form-label">Cantidad a Ajustar:</label>
                <input type="number" class="form-control" name="quantity_adjusted" id="quantity_adjusted" min="1" required>
              </div>
              <div class="mb-3">
                <label for="adjustment_reason" class="form-label">Motivo del Ajuste (Opcional):</label>
                <textarea class="form-control" name="adjustment_reason" id="adjustment_reason" rows="2"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Aplicar Ajuste</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var autoDismissAlerts = document.querySelectorAll('.alert.alert-dismissible');
            autoDismissAlerts.forEach(function(alertEl) {
                setTimeout(function() {
                    if (document.body.contains(alertEl)) {
                        var bsAlert = bootstrap.Alert.getInstance(alertEl);
                        if (bsAlert) { bsAlert.close(); }
                    }
                }, 7000); 
            });

            var adjustStockModal = document.getElementById('adjustStockModal');
            if (adjustStockModal) {
                adjustStockModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    var itemId = button.getAttribute('data-item-id');
                    var itemName = button.getAttribute('data-item-name');
                    var currentStock = button.getAttribute('data-current-stock');

                    var modalTitleSpan = adjustStockModal.querySelector('#itemNameModal');
                    var modalItemIdInput = adjustStockModal.querySelector('#modalItemId');
                    var modalCurrentStockP = adjustStockModal.querySelector('#modalCurrentStock');
                    
                    if(modalTitleSpan) modalTitleSpan.textContent = itemName;
                    if(modalItemIdInput) modalItemIdInput.value = itemId;
                    if(modalCurrentStockP) modalCurrentStockP.textContent = currentStock;
                    
                    var form = adjustStockModal.querySelector('#adjustStockForm');
                    if(form) {
                        form.reset(); 
                         // Asegurarse de que el select también se resetee si no lo hace form.reset()
                        var adjTypeSelect = form.querySelector('#adjustment_type');
                        if(adjTypeSelect) adjTypeSelect.value = 'add';
                    }
                });
            }
        });
    </script>
</body>
</html>