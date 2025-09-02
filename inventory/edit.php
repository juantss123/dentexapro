<?php
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

$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$item_id) {
    header('Location: index.php?status=error&msg=' . urlencode('ID de insumo no válido.'));
    exit;
}

$errors = [];
// Inicializar variables con datos del insumo
$item_name = ''; $item_description = ''; $category = ''; 
$current_quantity = 0; $min_quantity_alert = 0; $unit_measure = ''; 
$supplier = ''; $last_purchase_date = ''; $notes = '';

// Cargar datos del insumo para editar
$stmt_load = $mysqli->prepare("SELECT * FROM inventory_items WHERE id = ?");
if ($stmt_load) {
    $stmt_load->bind_param("i", $item_id);
    $stmt_load->execute();
    $result_load = $stmt_load->get_result();
    if ($item_data = $result_load->fetch_assoc()) {
        $item_name = $item_data['item_name'];
        $item_description = $item_data['item_description'];
        $category = $item_data['category'];
        $current_quantity = $item_data['current_quantity'];
        $min_quantity_alert = $item_data['min_quantity_alert'];
        $unit_measure = $item_data['unit_measure'];
        $supplier = $item_data['supplier'];
        $last_purchase_date = $item_data['last_purchase_date'];
        $notes = $item_data['notes'];
    } else {
        header('Location: index.php?status=error&msg=' . urlencode('Insumo no encontrado.'));
        exit;
    }
    $stmt_load->close();
} else {
    die("Error al preparar la consulta de carga: " . $mysqli->error);
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name_new = trim($_POST['item_name'] ?? '');
    $item_description_new = trim($_POST['item_description'] ?? '');
    $category_new = trim($_POST['category'] ?? '');
    
    $current_quantity_post = $_POST['current_quantity'] ?? 0;
    $min_quantity_alert_post = $_POST['min_quantity_alert'] ?? 0;

    if (filter_var($current_quantity_post, FILTER_VALIDATE_INT) === false || intval($current_quantity_post) < 0) {
        $errors[] = "La cantidad actual debe ser un número entero no negativo.";
        // Mantener el valor actual en caso de error para no perderlo si otros campos están bien
        // $current_quantity_new = $current_quantity; 
    } else {
        $current_quantity_new = intval($current_quantity_post);
    }

    if (filter_var($min_quantity_alert_post, FILTER_VALIDATE_INT) === false || intval($min_quantity_alert_post) < 0) {
        $errors[] = "La alerta de stock mínimo debe ser un número entero no negativo.";
        // $min_quantity_alert_new = $min_quantity_alert;
    } else {
        $min_quantity_alert_new = intval($min_quantity_alert_post);
    }

    $unit_measure_new = trim($_POST['unit_measure'] ?? '');
    $supplier_new = trim($_POST['supplier'] ?? '');
    $last_purchase_date_new = trim($_POST['last_purchase_date'] ?? '');
    $notes_new = trim($_POST['notes'] ?? '');

    // Actualizar variables para mostrar en el formulario en caso de error
    $item_name = $item_name_new;
    $item_description = $item_description_new;
    $category = $category_new;
    if(isset($current_quantity_new)) $current_quantity = $current_quantity_new;
    if(isset($min_quantity_alert_new)) $min_quantity_alert = $min_quantity_alert_new;
    $unit_measure = $unit_measure_new;
    $supplier = $supplier_new;
    $last_purchase_date = $last_purchase_date_new;
    $notes = $notes_new;


    if (empty($item_name_new)) {
        $errors[] = "El nombre del insumo es obligatorio.";
    }
    if (!empty($last_purchase_date_new)) {
        $d = DateTime::createFromFormat('Y-m-d', $last_purchase_date_new);
        if (!$d || $d->format('Y-m-d') !== $last_purchase_date_new) {
            $errors[] = "Formato de fecha de última compra inválido. Use YYYY-MM-DD.";
        }
    }

    if (empty($errors)) {
        $sql_update = "UPDATE inventory_items SET 
                        item_name = ?, item_description = ?, category = ?, 
                        current_quantity = ?, min_quantity_alert = ?, unit_measure = ?, 
                        supplier = ?, last_purchase_date = ?, notes = ?
                       WHERE id = ?";
        $stmt_update = $mysqli->prepare($sql_update);
        if ($stmt_update) {
            $purchase_date_param_new = !empty($last_purchase_date_new) ? $last_purchase_date_new : null;

            $stmt_update->bind_param("sssiissssi", 
                $item_name_new, $item_description_new, $category_new, 
                $current_quantity_new, $min_quantity_alert_new, $unit_measure_new, 
                $supplier_new, $purchase_date_param_new, $notes_new,
                $item_id
            );
            if ($stmt_update->execute()) {
                header('Location: index.php?status=success_update');
                exit;
            } else {
                $errors[] = "Error al actualizar el insumo: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors[] = "Error al preparar la consulta de actualización: " . $mysqli->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Insumo - Clínica Dental</title>
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
        .form-label i { margin-right: 5px; }
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
                <div class="card-header bg-warning text-dark"> 
                    <h3 class="mb-0 d-flex align-items-center">
                        <i class="fas fa-edit me-2"></i>Editar Insumo: <?php echo htmlspecialchars($item_name); ?>
                    </h3>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Error al actualizar:</h5>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): echo '<li>' . htmlspecialchars($error) . '</li>'; endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="edit.php?id=<?php echo $item_id; ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="item_name" class="form-label"><i class="fas fa-tag text-secondary"></i>Nombre del Insumo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="item_name" name="item_name" value="<?php echo htmlspecialchars($item_name); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="category" class="form-label"><i class="fas fa-layer-group text-secondary"></i>Categoría</label>
                                <input type="text" class="form-control form-control-lg" id="category" name="category" value="<?php echo htmlspecialchars($category); ?>" placeholder="Ej: Descartables, Anestesia">
                            </div>

                            <div class="col-md-12">
                                <label for="item_description" class="form-label"><i class="fas fa-align-left text-secondary"></i>Descripción</label>
                                <textarea class="form-control" id="item_description" name="item_description" rows="3"><?php echo htmlspecialchars($item_description); ?></textarea>
                            </div>

                            <div class="col-md-4">
                                <label for="current_quantity" class="form-label"><i class="fas fa-boxes text-secondary"></i>Cantidad Actual <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-lg" id="current_quantity" name="current_quantity" value="<?php echo htmlspecialchars($current_quantity); ?>" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label for="min_quantity_alert" class="form-label"><i class="fas fa-exclamation-triangle text-secondary"></i>Alerta Stock Mínimo <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-lg" id="min_quantity_alert" name="min_quantity_alert" value="<?php echo htmlspecialchars($min_quantity_alert); ?>" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label for="unit_measure" class="form-label"><i class="fas fa-ruler-combined text-secondary"></i>Unidad de Medida</label>
                                <input type="text" class="form-control form-control-lg" id="unit_measure" name="unit_measure" value="<?php echo htmlspecialchars($unit_measure); ?>" placeholder="Ej: unidad, caja, ml">
                            </div>

                            <div class="col-md-6">
                                <label for="supplier" class="form-label"><i class="fas fa-truck text-secondary"></i>Proveedor (Opcional)</label>
                                <input type="text" class="form-control form-control-lg" id="supplier" name="supplier" value="<?php echo htmlspecialchars($supplier); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="last_purchase_date" class="form-label"><i class="fas fa-calendar-alt text-secondary"></i>Fecha Última Compra (Opcional)</label>
                                <input type="date" class="form-control form-control-lg" id="last_purchase_date" name="last_purchase_date" value="<?php echo htmlspecialchars($last_purchase_date); ?>">
                            </div>

                            <div class="col-md-12">
                                <label for="notes" class="form-label"><i class="fas fa-sticky-note text-secondary"></i>Notas Adicionales</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>Actualizar Insumo</button>
                            <a href="index.php" class="btn btn-outline-secondary btn-lg"><i class="fas fa-times me-2"></i>Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
             <div class="text-center mt-3">
                <a href="<?php echo $path_to_root; ?>dashboard.php" class="btn btn-link"><i class="fas fa-arrow-left me-1"></i>Volver al Panel</a>
            </div>
        </main>
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
        });
    </script>
</body>
</html>
