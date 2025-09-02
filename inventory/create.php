<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ../login.php'); exit; }
require_once '../config.php';

$path_to_root = '../';

$errors = [];
$item_name = ''; $item_description = ''; $category = ''; 
$current_quantity = 0; $min_quantity_alert = 0; $unit_measure = ''; 
$supplier = ''; $last_purchase_date = ''; $notes = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $item_description = trim($_POST['item_description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $current_quantity = filter_input(INPUT_POST, 'current_quantity', FILTER_VALIDATE_INT, ["options" => ["default" => 0, "min_range" => 0]]);
    $min_quantity_alert = filter_input(INPUT_POST, 'min_quantity_alert', FILTER_VALIDATE_INT, ["options" => ["default" => 0, "min_range" => 0]]);
    $unit_measure = trim($_POST['unit_measure'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');
    $last_purchase_date = trim($_POST['last_purchase_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($item_name)) {
        $errors[] = "El nombre del insumo es obligatorio.";
    }
    if ($current_quantity === false || $current_quantity < 0) {
        $errors[] = "La cantidad actual debe ser un número entero no negativo.";
        $current_quantity = 0; // Resetear a valor seguro
    }
     if ($min_quantity_alert === false || $min_quantity_alert < 0) {
        $errors[] = "La alerta de stock mínimo debe ser un número entero no negativo.";
        $min_quantity_alert = 0; // Resetear a valor seguro
    }
    if (!empty($last_purchase_date)) {
        $d = DateTime::createFromFormat('Y-m-d', $last_purchase_date);
        if (!$d || $d->format('Y-m-d') !== $last_purchase_date) {
            $errors[] = "Formato de fecha de última compra inválido.";
            $last_purchase_date = ''; // Limpiar fecha inválida
        }
    }


    if (empty($errors)) {
        $sql = "INSERT INTO inventory_items (item_name, item_description, category, current_quantity, min_quantity_alert, unit_measure, supplier, last_purchase_date, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            // Si last_purchase_date está vacío, pasar NULL a la BD
            $purchase_date_param = !empty($last_purchase_date) ? $last_purchase_date : null;

            $stmt->bind_param("sssiissss", 
                $item_name, $item_description, $category, 
                $current_quantity, $min_quantity_alert, $unit_measure, 
                $supplier, $purchase_date_param, $notes
            );
            if ($stmt->execute()) {
                header('Location: index.php?status=success_create');
                exit;
            } else {
                $errors[] = "Error al guardar el insumo: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Error al preparar la consulta: " . $mysqli->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Añadir Nuevo Insumo - Clínica Dental</title>
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
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0 d-flex align-items-center">
                        <i class="fas fa-plus-circle me-2"></i>Añadir Nuevo Insumo
                    </h3>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Error al guardar:</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): echo '<li>' . htmlspecialchars($error) . '</li>'; endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="create.php">
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
                            <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>Guardar Insumo</button>
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
