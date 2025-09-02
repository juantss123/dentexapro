<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../config.php'; 

$path_to_root = '../';
$current_page_script_path = $_SERVER['PHP_SELF']; 

// Permiso: 'billing_customize_pdf' o 'billing' o superadmin
$can_access = false;
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin') {
    $can_access = true;
} else {
    if (user_has_permission('billing_customize_pdf', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
        $can_access = true;
    } elseif (user_has_permission('billing', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
        // Si no existe el permiso específico, el de 'billing' podría ser suficiente
        $can_access = true; 
    }
}

if (!$can_access) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}


$settings_keys_defaults = [
    'clinic_pdf_logo_path' => '', 
    'clinic_pdf_name' => 'Nombre de su Clínica Dental',
    'clinic_pdf_address' => 'Calle Falsa 123, Ciudad, Provincia',
    'clinic_pdf_phone' => '+54 11 1234-5678',
    'clinic_pdf_email' => 'contacto@suclinica.com',
    'clinic_pdf_cuit' => 'XX-XXXXXXXX-X',
    'clinic_pdf_footer_notes' => 'Presupuesto válido por 30 días. Los precios pueden estar sujetos a cambios sin previo aviso.'
];
$current_settings = $settings_keys_defaults; 

$logo_upload_dir_relative = UPLOAD_DIR_NAME_CONST . '/clinic_assets/'; 
$logo_upload_dir_absolute = PROJECT_ROOT . '/' . $logo_upload_dir_relative; 

if (!file_exists($logo_upload_dir_absolute)) {
    if (!@mkdir($logo_upload_dir_absolute, 0777, true) && !is_dir($logo_upload_dir_absolute)) {
        // Log error, pero no es fatal para la carga de la página
        error_log("Error: No se pudo crear el directorio para logos: " . $logo_upload_dir_absolute);
    }
}

// Cargar configuraciones existentes
$stmt_load = $mysqli->prepare("SELECT setting_key, setting_value FROM clinic_settings WHERE setting_key LIKE 'clinic_pdf_%'");
if ($stmt_load) {
    $stmt_load->execute();
    $result = $stmt_load->get_result();
    while ($row = $result->fetch_assoc()) {
        if (array_key_exists($row['setting_key'], $current_settings)) {
            $current_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    $stmt_load->close();
}

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pdf_settings'])) {
    $posted_settings_to_save = [];

    foreach ($settings_keys_defaults as $key => $default_value) {
        if ($key !== 'clinic_pdf_logo_path') { 
            $posted_settings_to_save[$key] = trim($_POST[$key] ?? $default_value);
        }
    }

    $current_logo_db_path = $current_settings['clinic_pdf_logo_path'] ?? null;
    $final_logo_db_path = $current_logo_db_path; 

    if (isset($_POST['remove_current_logo']) && $_POST['remove_current_logo'] == '1') {
        if (!empty($current_logo_db_path) && file_exists(PROJECT_ROOT . '/' . $current_logo_db_path)) {
            @unlink(PROJECT_ROOT . '/' . $current_logo_db_path);
        }
        $final_logo_db_path = null; 
    }

    if (isset($_FILES['clinic_pdf_logo_file']) && $_FILES['clinic_pdf_logo_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['clinic_pdf_logo_file']['tmp_name'];
        $file_name_original = basename($_FILES['clinic_pdf_logo_file']['name']);
        $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 2 * 1024 * 1024; 

        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = 'Tipo de archivo de logo no permitido (solo JPG, PNG, GIF).';
        } elseif ($_FILES['clinic_pdf_logo_file']['size'] > $max_file_size) {
            $errors[] = 'El archivo de logo es demasiado grande (Máx 2MB).';
        } else {
            if (empty($_POST['remove_current_logo']) && !empty($current_logo_db_path) && file_exists(PROJECT_ROOT . '/' . $current_logo_db_path)) {
                @unlink(PROJECT_ROOT . '/' . $current_logo_db_path);
            }
            
            $unique_logo_name = 'clinic_logo_' . time() . '.' . $file_extension;
            $destination_path_server = $logo_upload_dir_absolute . $unique_logo_name;
            
            if (move_uploaded_file($file_tmp_path, $destination_path_server)) {
                $final_logo_db_path = $logo_upload_dir_relative . $unique_logo_name; 
            } else {
                $errors[] = 'Error al mover el archivo de logo subido.';
            }
        }
    }
    $posted_settings_to_save['clinic_pdf_logo_path'] = $final_logo_db_path;


    if (empty($errors)) {
        $mysqli->begin_transaction();
        try {
            $stmt_upsert = $mysqli->prepare("INSERT INTO clinic_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            if (!$stmt_upsert) throw new Exception("Error preparando consulta: " . $mysqli->error);

            foreach ($posted_settings_to_save as $key => $value) {
                $value_to_save = ($value === '' && $key !== 'clinic_pdf_logo_path') ? null : $value; 
                 if ($key === 'clinic_pdf_logo_path' && $value === null) { 
                    $value_to_save = null;
                 }
                $stmt_upsert->bind_param("ss", $key, $value_to_save);
                if (!$stmt_upsert->execute()) {
                    throw new Exception("Error guardando configuración '{$key}': " . $stmt_upsert->error);
                }
                $current_settings[$key] = $value_to_save; 
            }
            $stmt_upsert->close();
            $mysqli->commit();
            $success_message = "¡Configuración del PDF de presupuesto actualizada con éxito!";
        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = "Error en la transacción: " . $e->getMessage();
        }
    }
}

$form_values = [];
foreach ($settings_keys_defaults as $key => $default_value) {
    $form_values[$key] = $current_settings[$key] ?? $default_value;
}
$current_logo_display_path = (!empty($form_values['clinic_pdf_logo_path']) && file_exists(PROJECT_ROOT . '/' . $form_values['clinic_pdf_logo_path'])) ? ($path_to_root . $form_values['clinic_pdf_logo_path']) : null;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Personalizar PDF de Presupuestos - DentexaPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; }
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
    </style>
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
                            <i class="fas fa-tools me-3 text-primary text-info"></i>Personalizar PDF de Presupuestos
                        </h2>
                        <a href="<?php echo $path_to_root; ?>billing/index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Volver a Cobros
                        </a>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Error al guardar:</h5>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): echo '<li>' . htmlspecialchars($error) . '</li>'; endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="customize_pdf.php" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="clinic_pdf_name" class="form-label fw-bold"><i class="fas fa-clinic-medical me-2 text-secondary"></i>Nombre de la Clínica (para PDF)</label>
                                    <input type="text" class="form-control form-control-lg" id="clinic_pdf_name" name="clinic_pdf_name" value="<?php echo htmlspecialchars($form_values['clinic_pdf_name']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="clinic_pdf_address" class="form-label fw-bold"><i class="fas fa-map-marked-alt me-2 text-secondary"></i>Dirección (para PDF)</label>
                                    <textarea class="form-control form-control-lg" id="clinic_pdf_address" name="clinic_pdf_address" rows="2"><?php echo htmlspecialchars($form_values['clinic_pdf_address']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="clinic_pdf_phone" class="form-label fw-bold"><i class="fas fa-phone-alt me-2 text-secondary"></i>Teléfono (para PDF)</label>
                                    <input type="text" class="form-control form-control-lg" id="clinic_pdf_phone" name="clinic_pdf_phone" value="<?php echo htmlspecialchars($form_values['clinic_pdf_phone']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="clinic_pdf_email" class="form-label fw-bold"><i class="fas fa-envelope me-2 text-secondary"></i>Email de Contacto (para PDF)</label>
                                    <input type="email" class="form-control form-control-lg" id="clinic_pdf_email" name="clinic_pdf_email" value="<?php echo htmlspecialchars($form_values['clinic_pdf_email']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="clinic_pdf_cuit" class="form-label fw-bold"><i class="fas fa-id-card me-2 text-secondary"></i>CUIT/Identificación Fiscal (para PDF)</label>
                                    <input type="text" class="form-control form-control-lg" id="clinic_pdf_cuit" name="clinic_pdf_cuit" value="<?php echo htmlspecialchars($form_values['clinic_pdf_cuit']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="clinic_pdf_logo_file" class="form-label fw-bold"><i class="fas fa-image me-2 text-secondary"></i>Logo de la Clínica (para PDF)</label>
                                    <?php if ($current_logo_display_path): ?>
                                        <div class="mb-2 text-center p-2 border rounded bg-light">
                                            <p class="small text-muted mb-1">Logo Actual:</p>
                                            <img src="<?php echo $current_logo_display_path; ?>?t=<?php echo time(); ?>" alt="Logo Actual" style="max-height: 80px; max-width: 180px; border:1px solid #ccc; padding:3px; background-color: #fff;">
                                            <div class="form-check mt-2 text-start">
                                                <input class="form-check-input" type="checkbox" name="remove_current_logo" value="1" id="remove_current_logo">
                                                <label class="form-check-label text-danger small" for="remove_current_logo">
                                                    Eliminar logo actual al guardar
                                                </label>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p class="small text-muted alert alert-info p-2">No hay un logo cargado actualmente.</p>
                                    <?php endif; ?>
                                    <input class="form-control form-control-lg" type="file" id="clinic_pdf_logo_file" name="clinic_pdf_logo_file" accept="image/png, image/jpeg, image/gif">
                                    <div class="form-text">Suba una imagen (JPG, PNG, GIF - Máx 2MB). Si sube uno nuevo, reemplazará el actual.</div>
                                </div>
                                 <div class="mb-3">
                                    <label for="clinic_pdf_footer_notes" class="form-label fw-bold"><i class="fas fa-sticky-note me-2 text-secondary"></i>Notas al Pie del Presupuesto (Opcional)</label>
                                    <textarea class="form-control form-control-lg" id="clinic_pdf_footer_notes" name="clinic_pdf_footer_notes" rows="4" placeholder="Ej: Validez del presupuesto 30 días. Formas de pago..."><?php echo htmlspecialchars($form_values['clinic_pdf_footer_notes']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <hr class="my-4">
                        <button type="submit" name="save_pdf_settings" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Guardar Configuración del PDF
                        </button>
                    </form>
                </div>
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
                        if (bsAlert) { bsAlert.close(); } else { new bootstrap.Alert(alertEl).close(); }
                    }
                }, 7000);
            });

            const logoFileInput = document.getElementById('clinic_pdf_logo_file');
            const currentLogoContainer = document.querySelector('.mb-2.text-center.p-2.border.rounded.bg-light'); 
            let logoPreviewImg = currentLogoContainer ? currentLogoContainer.querySelector('img') : null;
            const originalLogoSrc = logoPreviewImg ? logoPreviewImg.src : null;
            const noLogoMessageExists = document.querySelector('p.small.text-muted.alert.alert-info');


            if (logoFileInput) {
                logoFileInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (!logoPreviewImg && currentLogoContainer) { 
                                if(noLogoMessageExists) noLogoMessageExists.style.display = 'none';
                                
                                logoPreviewImg = document.createElement('img');
                                logoPreviewImg.alt = "Nuevo Logo";
                                logoPreviewImg.style.maxHeight = '80px';
                                logoPreviewImg.style.maxWidth = '180px';
                                logoPreviewImg.style.border = '1px solid #ccc';
                                logoPreviewImg.style.padding = '3px';
                                logoPreviewImg.style.backgroundColor = '#fff';
                                
                                const checkboxDiv = currentLogoContainer.querySelector('.form-check');
                                const pTagTitle = currentLogoContainer.querySelector('p.small.text-muted.mb-1');

                                if (pTagTitle) { // Si existe el "Logo Actual:", lo reemplaza o añade después.
                                     currentLogoContainer.insertBefore(logoPreviewImg, pTagTitle.nextSibling);
                                } else if (checkboxDiv) {
                                    currentLogoContainer.insertBefore(logoPreviewImg, checkboxDiv);
                                } else {
                                     currentLogoContainer.appendChild(logoPreviewImg); 
                                }
                            }
                             if(logoPreviewImg) logoPreviewImg.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                        const removeCheckbox = document.getElementById('remove_current_logo');
                        if(removeCheckbox) removeCheckbox.checked = false;
                    }
                });
            }
            
            const removeLogoCheckbox = document.getElementById('remove_current_logo');
            if(removeLogoCheckbox && logoFileInput && currentLogoContainer){
                removeLogoCheckbox.addEventListener('change', function(){
                    if(this.checked && logoFileInput.files.length > 0){
                        logoFileInput.value = ""; 
                        if(logoPreviewImg){
                            if(originalLogoSrc){
                                logoPreviewImg.src = originalLogoSrc;
                            } else {
                                logoPreviewImg.remove(); 
                                logoPreviewImg = null; 
                                if(noLogoMessageExists) noLogoMessageExists.style.display = 'block';
                                else { // Crear el mensaje si no existe y debería
                                     const pTag = document.createElement('p');
                                     pTag.classList.add('small', 'text-muted', 'alert', 'alert-info', 'p-2');
                                     pTag.textContent = 'No hay un logo cargado actualmente.';
                                     const checkboxDiv = currentLogoContainer.querySelector('.form-check');
                                     if(checkboxDiv) currentLogoContainer.insertBefore(pTag, checkboxDiv);
                                     else currentLogoContainer.appendChild(pTag);
                                }
                            }
                        }
                    } else if (this.checked && logoPreviewImg) { // Si se marca eliminar y hay un logo actual (no uno nuevo)
                        logoPreviewImg.style.opacity = '0.4';
                    } else if (!this.checked && logoPreviewImg) {
                        logoPreviewImg.style.opacity = '1';
                    }
                });
            }
        });
    </script>
</body>
</html>