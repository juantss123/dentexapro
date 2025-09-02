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
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para crear presupuestos.'));
    exit;
}

$errors = [];
if (isset($_SESSION['estimate_form_errors'])) {
    $errors = $_SESSION['estimate_form_errors'];
    unset($_SESSION['estimate_form_errors']);
}
$form_data = $_SESSION['estimate_form_data'] ?? [];
if (isset($_SESSION['estimate_form_data'])) {
    unset($_SESSION['estimate_form_data']);
}

$patient_id_selected = $form_data['patient_id'] ?? '';
$professional_name_val = trim($form_data['professional_name'] ?? ($_SESSION['admin_name'] ?? 'Profesional no asignado'));
$estimate_date_val = $form_data['estimate_date'] ?? date('Y-m-d');
$insurance_details_val = trim($form_data['insurance_details'] ?? '');
$notes_val = trim($form_data['notes'] ?? '');
// $status_val ya no se necesita aquí ya que el campo se elimina

$estimate_items_val = [];
if (!empty($form_data['item_description']) && is_array($form_data['item_description'])) {
    foreach ($form_data['item_description'] as $key => $desc) {
        $estimate_items_val[] = [
            'description' => trim($desc),
            'quantity' => filter_var($form_data['item_quantity'][$key] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]),
            'unit_price' => isset($form_data['item_unit_price'][$key]) ? str_replace('.', ',', (string)filter_var(str_replace(',', '.', $form_data['item_unit_price'][$key]), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)) : ''
        ];
    }
}
if (empty($estimate_items_val)) { 
    $estimate_items_val = [['description' => '', 'quantity' => 1, 'unit_price' => '']];
}

$patients_for_select = [];
$patients_query = $mysqli->query("SELECT id, dni, fname, lname, insurance_name, insurance_plan FROM patients ORDER BY lname ASC, fname ASC");
if ($patients_query) {
    while ($patient_row = $patients_query->fetch_assoc()) {
        $patients_for_select[] = $patient_row;
    }
    $patients_query->free();
}

if(isset($_GET['status']) && $_GET['status'] === 'error_validation_save' && isset($_SESSION['estimate_form_errors'])){
    // $errors ya se cargó desde la sesión
} elseif (isset($_GET['status']) && $_GET['status'] === 'error_db_save' && isset($_GET['msg'])) {
    $errors[] = htmlspecialchars(urldecode($_GET['msg']));
} elseif (isset($_GET['status']) && $_GET['status'] === 'warning_email_failed' && isset($_GET['msg'])) {
    $errors[] = "Advertencia: " . htmlspecialchars(urldecode($_GET['msg']));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Presupuesto - DentexaPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo $path_to_root; ?>assets/img/favicon_dental.svg">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; }
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .item-row { margin-bottom: 0.5rem; }
        .item-row .btn-danger { font-size: 0.8em; padding: 0.2rem 0.5rem; }
        .total-section { font-size: 1.2em; font-weight: bold; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;}
        .form-label i { margin-right: 0.3rem; }
        .select2-container .select2-selection--single { height: calc(2.875rem + 2px)!important; }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { line-height: calc(1.5em + 1rem + 2px)!important; padding-left: 0 !important;}
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow { height: calc(2.875rem)!important; right: 0.75rem !important;}
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
                            <i class="fas fa-file-medical-alt me-3 text-primary"></i>Crear Nuevo Presupuesto
                        </h2>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Listado
                        </a>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                             <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Por favor, corrija los siguientes errores:</h5>
                            <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="actions/save_estimate.php" method="POST" id="estimateForm">
                        <h5 class="text-primary mb-3"><i class="fas fa-user-circle me-2"></i>Información del Paciente y Profesional</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="patient_id" class="form-label fw-bold"><i class="fas fa-user-injured"></i>Paciente <span class="text-danger">*</span></label>
                                <select name="patient_id" id="patient_id" class="form-select form-select-lg" required>
                                    <option value="">-- Seleccionar Paciente --</option>
                                    <?php foreach($patients_for_select as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>" 
                                                data-insurance-name="<?php echo htmlspecialchars($patient['insurance_name'] ?? ''); ?>"
                                                data-insurance-plan="<?php echo htmlspecialchars($patient['insurance_plan'] ?? ''); ?>"
                                                <?php if ($patient_id_selected == $patient['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($patient['lname'] . ', ' . $patient['fname'] . ($patient['dni'] ? ' (DNI: '.$patient['dni'].')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="professional_name" class="form-label fw-bold"><i class="fas fa-user-md"></i>Profesional que Atiende <span class="text-danger">*</span></label>
                                <input type="text" name="professional_name" id="professional_name" class="form-control form-control-lg" value="<?php echo htmlspecialchars($professional_name_val); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="estimate_date" class="form-label fw-bold"><i class="fas fa-calendar-alt"></i>Fecha del Presupuesto <span class="text-danger">*</span></label>
                                <input type="date" name="estimate_date" id="estimate_date" class="form-control form-control-lg" value="<?php echo htmlspecialchars($estimate_date_val); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="insurance_details" class="form-label fw-bold"><i class="fas fa-shield-alt"></i>Cobertura / Obra Social</label>
                                <input type="text" name="insurance_details" id="insurance_details" class="form-control form-control-lg" placeholder="Ej: OSDE Plan 210" value="<?php echo htmlspecialchars($insurance_details_val); ?>">
                                <div class="form-text small">Se precarga al seleccionar paciente, puede editarlo si es necesario.</div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <h5 class="text-primary mb-3"><i class="fas fa-tasks me-2"></i>Items del Presupuesto</h5>
                        <div id="estimateItemsContainer">
                            <?php foreach ($estimate_items_val as $index => $item): ?>
                            <div class="row g-2 align-items-center item-row mb-2">
                                <div class="col-md-6">
                                    <?php if ($index === 0): ?><label class="form-label small d-md-block d-none">Descripción <span class="text-danger">*</span></label><?php endif; ?>
                                    <input type="text" name="item_description[]" class="form-control" placeholder="Descripción del tratamiento/servicio" value="<?php echo htmlspecialchars($item['description']); ?>" required>
                                </div>
                                <div class="col-md-2 col-5"> 
                                     <?php if ($index === 0): ?><label class="form-label small d-md-block d-none">Cantidad <span class="text-danger">*</span></label><?php endif; ?>
                                    <input type="number" name="item_quantity[]" class="form-control item-quantity" placeholder="Cant." value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1" required>
                                </div>
                                <div class="col-md-3 col-5"> 
                                     <?php if ($index === 0): ?><label class="form-label small d-md-block d-none">Precio Unit. <span class="text-danger">*</span></label><?php endif; ?>
                                    <input type="text" name="item_unit_price[]" class="form-control item-unit-price text-end" placeholder="Precio Unit." value="<?php echo htmlspecialchars($item['unit_price']); ?>" required>
                                </div>
                                <div class="col-md-1 col-2 d-flex align-items-end"> 
                                    <?php if ($index > 0): ?>
                                        <button type="button" class="btn btn-danger btn-sm w-100 remove-item-btn" title="Eliminar ítem"><i class="fas fa-trash"></i></button>
                                    <?php else: ?>
                                        <div style="min-height: 31px;"></div> 
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="addItemBtn" class="btn btn-outline-success btn-sm mt-2"><i class="fas fa-plus me-1"></i>Agregar Ítem</button>

                        <div class="row justify-content-end mt-3">
                            <div class="col-md-4">
                                <div class="total-section text-end">
                                    TOTAL: $ <span id="grandTotal">0,00</span>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <h5 class="text-primary mb-3"><i class="fas fa-sticky-note me-2"></i>Notas</h5>
                         <div class="mb-4"> <label for="notes" class="form-label fw-bold">Notas Adicionales (Opcional)</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Notas internas o para el paciente..."><?php echo htmlspecialchars($notes_val); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php" class="btn btn-outline-secondary btn-lg"><i class="fas fa-times-circle me-1"></i>Cancelar</a>
                            <button type="submit" name="save_estimate_action" value="save_draft" class="btn btn-info btn-lg"><i class="fas fa-save me-1"></i>Guardar como Borrador</button>
                            <button type="submit" name="save_estimate_action" value="save_send" class="btn btn-success btn-lg"><i class="fas fa-paper-plane me-1"></i>Guardar y Enviar</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        $('#patient_id').select2({
            theme: "bootstrap-5",
            placeholder: "-- Seleccionar Paciente --",
            width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
            language: {
                noResults: function () {
                    return "No se encontraron pacientes";
                }
            }
        });

        const patientSelect = document.getElementById('patient_id');
        const insuranceDetailsInput = document.getElementById('insurance_details');
        const itemsContainer = document.getElementById('estimateItemsContainer');
        const addItemBtn = document.getElementById('addItemBtn');
        const grandTotalSpan = document.getElementById('grandTotal');

        function updateInsuranceDetails() {
            if (patientSelect && patientSelect.value && patientSelect.options[patientSelect.selectedIndex]) {
                const selectedOption = patientSelect.options[patientSelect.selectedIndex];
                const insuranceName = selectedOption.dataset.insuranceName || '';
                const insurancePlan = selectedOption.dataset.insurancePlan || '';
                let details = '';
                if (insuranceName) details += insuranceName;
                if (insurancePlan) details += (details ? ' - Plan: ' : 'Plan: ') + insurancePlan;
                if (insuranceDetailsInput) insuranceDetailsInput.value = details || '';
            } else if (insuranceDetailsInput) {
                insuranceDetailsInput.value = '';
            }
        }

        if (patientSelect) {
            $(patientSelect).on('change', updateInsuranceDetails); 
            if (patientSelect.value) {
                updateInsuranceDetails();
            }
        }

        function formatCurrency(value) {
            let num = parseFloat(value);
            if (isNaN(num)) return '0,00';
            return num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function parseCurrency(value) {
            if (typeof value !== 'string') value = String(value);
            let cleanedValue = value.replace(/\./g, ''); 
            cleanedValue = cleanedValue.replace(',', '.'); 
            return parseFloat(cleanedValue) || 0;
        }

        function calculateTotal() {
            let grandTotal = 0;
            if (itemsContainer) { 
                itemsContainer.querySelectorAll('.item-row').forEach(function(row) {
                    const quantityInput = row.querySelector('.item-quantity');
                    const priceInput = row.querySelector('.item-unit-price');
                    
                    const quantity = quantityInput ? (parseInt(quantityInput.value) || 0) : 0;
                    const price = priceInput ? parseCurrency(priceInput.value) : 0;
                    
                    grandTotal += quantity * price;
                });
            }
            if (grandTotalSpan) grandTotalSpan.textContent = formatCurrency(grandTotal);
        }

        function addRowEventListeners(row) {
            const quantityInput = row.querySelector('.item-quantity');
            const priceInput = row.querySelector('.item-unit-price');

            if(quantityInput) quantityInput.addEventListener('input', calculateTotal);
            if(priceInput) {
                priceInput.addEventListener('input', calculateTotal);
                priceInput.addEventListener('blur', function() { 
                    this.value = formatCurrency(parseCurrency(this.value));
                });
                priceInput.addEventListener('focus', function() { 
                    let parsed = parseCurrency(this.value);
                    this.value = parsed === 0 && this.value !== "0" && this.value !== "0," && this.value !== "0,0" && this.value !== "0,00" ? '' : parsed.toString().replace('.', ',');
                });
            }

            const removeBtn = row.querySelector('.remove-item-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    row.remove();
                    calculateTotal();
                });
            }
        }

        if (addItemBtn && itemsContainer) {
            addItemBtn.addEventListener('click', function() {
                const newItemRowHtml = 
                    '<div class="row g-2 align-items-center item-row mb-2">' +
                        '<div class="col-md-6">' +
                            '<input type="text" name="item_description[]" class="form-control" placeholder="Descripción del tratamiento/servicio" required>' +
                        '</div>' +
                        '<div class="col-md-2 col-5">' +
                            '<input type="number" name="item_quantity[]" class="form-control item-quantity" placeholder="Cant." value="1" min="1" required>' +
                        '</div>' +
                        '<div class="col-md-3 col-5">' +
                            '<input type="text" name="item_unit_price[]" class="form-control item-unit-price text-end" placeholder="Precio Unit." required>' +
                        '</div>' +
                        '<div class="col-md-1 col-2 d-flex align-items-end">' +
                            '<button type="button" class="btn btn-danger btn-sm w-100 remove-item-btn" title="Eliminar ítem"><i class="fas fa-trash"></i></button>' +
                        '</div>' +
                    '</div>';
                itemsContainer.insertAdjacentHTML('beforeend', newItemRowHtml);
                const newRow = itemsContainer.lastElementChild;
                if (newRow) {
                   addRowEventListeners(newRow);
                   const firstInputInNewRow = newRow.querySelector('input[name="item_description[]"]');
                   if (firstInputInNewRow) {
                       firstInputInNewRow.focus();
                   }
                }
                calculateTotal(); 
            });
        }
        
        if (itemsContainer) { 
            itemsContainer.querySelectorAll('.item-row').forEach(function(row) {
                addRowEventListeners(row);
                const priceInput = row.querySelector('.item-unit-price');
                if (priceInput && priceInput.value) { 
                    priceInput.value = formatCurrency(parseCurrency(priceInput.value));
                }
            });
        }

        calculateTotal();

        var autoDismissAlerts = document.querySelectorAll('.alert.alert-dismissible');
        autoDismissAlerts.forEach(function(alertEl) {
            setTimeout(function() {
                if (document.body.contains(alertEl)) {
                    var bsAlert = bootstrap.Alert.getInstance(alertEl);
                    if (bsAlert) { bsAlert.close(); } else { new bootstrap.Alert(alertEl).close(); }
                }
            }, 7000);
        });
    }); 
    </script>
</body>
</html>