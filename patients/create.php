<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

// Asumimos que esta función existe en config.php o un archivo incluido
if (!function_exists('user_has_permission')) {
    function user_has_permission($permission, $permissions_json, $role) {
        if ($role === 'super_admin') return true;
        $permissions = json_decode($permissions_json, true) ?? [];
        return in_array($permission, $permissions);
    }
}
// Asumimos que esta función existe en config.php o un archivo incluido
if (!function_exists('isMobileDevice')) {
    function isMobileDevice() {
        return false; // Placeholder
    }
}


if (!user_has_permission('patients', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$errors = [];
// Variables para repoblar el formulario
$fname = ''; $lname = ''; $dni = ''; $birthdate = ''; $gender = '';
$phone = ''; $email = ''; $address = ''; $medical_record_number = '';
$allergies = ''; $current_medications = ''; $medical_conditions = ''; $important_alerts = '';
$patient_user_email = ''; $patient_portal_access = 'deshabilitado';
$patient_initial_password = '';
// Campos de Prepaga
$insurance_name = ''; $insurance_number = ''; $insurance_plan = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Repoblamos las variables con los datos enviados
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $medical_record_number = trim($_POST['medical_record_number'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $current_medications = trim($_POST['current_medications'] ?? '');
    $medical_conditions = trim($_POST['medical_conditions'] ?? '');
    $important_alerts = trim($_POST['important_alerts'] ?? '');
    $patient_user_email = trim($_POST['patient_user_email'] ?? '');
    $patient_initial_password = $_POST['patient_initial_password'] ?? '';
    $patient_confirm_password = $_POST['patient_confirm_password'] ?? '';
    $patient_portal_access = $_POST['patient_portal_access'] ?? 'deshabilitado';
    $insurance_name = trim($_POST['insurance_name'] ?? '');
    $insurance_number = trim($_POST['insurance_number'] ?? '');
    $insurance_plan = trim($_POST['insurance_plan'] ?? '');

    // --- VALIDACIONES (AHORA CON ERRORES ASOCIATIVOS) ---
    if (empty($fname)) $errors['fname'] = "El nombre es obligatorio.";
    if (empty($lname)) $errors['lname'] = "El apellido es obligatorio.";
    if (empty($dni)) {
        $errors['dni'] = "El DNI es obligatorio.";
    } else {
        $stmt_check_dni = $mysqli->prepare("SELECT id FROM patients WHERE dni = ?");
        $stmt_check_dni->bind_param("s", $dni);
        $stmt_check_dni->execute();
        $stmt_check_dni->store_result();
        if ($stmt_check_dni->num_rows > 0) {
            $errors['dni'] = "El DNI ingresado ya existe en la base de datos.";
        }
        $stmt_check_dni->close();
    }
    if (empty($birthdate)) $errors['birthdate'] = "La fecha de nacimiento es obligatoria.";

    if ($patient_portal_access === 'habilitado') {
        if (empty($patient_user_email)) {
            $errors['patient_user_email'] = "El email de acceso es obligatorio si el portal está habilitado.";
        } elseif (!filter_var($patient_user_email, FILTER_VALIDATE_EMAIL)) {
            $errors['patient_user_email'] = "El formato del email no es válido.";
        } else {
            $stmt_check_portal_email = $mysqli->prepare("SELECT id FROM patients WHERE patient_user_email = ?");
            $stmt_check_portal_email->bind_param("s", $patient_user_email);
            $stmt_check_portal_email->execute();
            $stmt_check_portal_email->store_result();
            if ($stmt_check_portal_email->num_rows > 0) {
                $errors['patient_user_email'] = "Este email ya está en uso por otro paciente.";
            }
            $stmt_check_portal_email->close();
        }
        if (empty($patient_initial_password)) {
            $errors['patient_initial_password'] = "La contraseña es obligatoria.";
        } elseif (strlen($patient_initial_password) < 8) {
            $errors['patient_initial_password'] = "La contraseña debe tener al menos 8 caracteres.";
        } elseif ($patient_initial_password !== $patient_confirm_password) {
            $errors['patient_confirm_password'] = "Las contraseñas no coinciden.";
        }
    }
    
    // --- LÓGICA DE INSERCIÓN (SI NO HAY ERRORES) ---
    if (empty($errors)) {
        $patient_password_hash_val = null;
        if ($patient_portal_access === 'habilitado') { 
            $patient_password_hash_val = password_hash($patient_initial_password, PASSWORD_DEFAULT);
        }
        $patient_user_email_sql = ($patient_portal_access === 'habilitado') ? $patient_user_email : null;

        $sql = "INSERT INTO patients (fname, lname, dni, birthdate, gender, phone, email, address, medical_record_number, 
                                     allergies, current_medications, medical_conditions, important_alerts,
                                     patient_user_email, patient_password_hash, patient_portal_access,
                                     insurance_name, insurance_number, insurance_plan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssssssssssssssssss", 
                $fname, $lname, $dni, $birthdate, $gender, $phone, $email, $address, 
                $medical_record_number, $allergies, $current_medications, $medical_conditions, $important_alerts,
                $patient_user_email_sql, $patient_password_hash_val, $patient_portal_access,
                $insurance_name, $insurance_number, $insurance_plan
            );

            if ($stmt->execute()) {
                header("Location: index.php?status=success_create_patient");
                exit;
            } else {
                $errors['general'] = "Error al guardar el paciente: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors['general'] = "Error al preparar la consulta: " . $mysqli->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Paciente - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .form-label i { margin-right: 5px; }
        .portal-access-section { background-color: #eef1f5; padding: 1.5rem; border-radius: .375rem; }
        /* Estilos para el Wizard */
        .nav-tabs .nav-link { color: #6c757d; font-weight: 500; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-color: #dee2e6 #dee2e6 #fff; border-bottom: 3px solid #0d6efd; font-weight: 700; }
        .tab-pane { padding: 1.5rem 0; }
        .wizard-buttons { border-top: 1px solid #dee2e6; padding-top: 1.5rem; margin-top: 1rem; }
         .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .invalid-feedback { display: block; } /* Forzar que el feedback de error sea visible */
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
                        <h2 class="mb-0 d-flex align-items-center"><i class="fas fa-user-plus me-3 text-info"></i>Registrar Nuevo Paciente</h2>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Volver</a>
                    </div>

                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($errors['general']); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="create.php" id="createPatientForm" novalidate>
                        <ul class="nav nav-tabs" id="patientWizard" role="tablist">
                            <li class="nav-item" role="presentation"><button class="nav-link active" id="step1-tab" data-bs-toggle="tab" data-bs-target="#step1" type="button" role="tab">1. Datos Personales</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" id="step2-tab" data-bs-toggle="tab" data-bs-target="#step2" type="button" role="tab">2. Info Médica y Prepaga</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" id="step3-tab" data-bs-toggle="tab" data-bs-target="#step3" type="button" role="tab">3. Acceso al Portal</button></li>
                        </ul>

                        <div class="tab-content" id="patientWizardContent">
                            <div class="tab-pane fade show active" id="step1" role="tabpanel">
                                <h5 class="mb-3 text-primary mt-3"><i class="fas fa-id-card me-2"></i>Información Personal</h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="dni" class="form-label">DNI <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?php echo isset($errors['dni']) ? 'is-invalid' : ''; ?>" id="dni" name="dni" value="<?php echo htmlspecialchars($dni); ?>" required>
                                        <?php if(isset($errors['dni'])): ?><div class="invalid-feedback"><?php echo $errors['dni']; ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="fname" class="form-label">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?php echo isset($errors['fname']) ? 'is-invalid' : ''; ?>" id="fname" name="fname" value="<?php echo htmlspecialchars($fname); ?>" required>
                                         <?php if(isset($errors['fname'])): ?><div class="invalid-feedback"><?php echo $errors['fname']; ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="lname" class="form-label">Apellido <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?php echo isset($errors['lname']) ? 'is-invalid' : ''; ?>" id="lname" name="lname" value="<?php echo htmlspecialchars($lname); ?>" required>
                                         <?php if(isset($errors['lname'])): ?><div class="invalid-feedback"><?php echo $errors['lname']; ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="birthdate" class="form-label">Fecha de Nacimiento <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control <?php echo isset($errors['birthdate']) ? 'is-invalid' : ''; ?>" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($birthdate); ?>" required>
                                         <?php if(isset($errors['birthdate'])): ?><div class="invalid-feedback"><?php echo $errors['birthdate']; ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-md-4"><label for="gender" class="form-label">Género</label><select class="form-select" id="gender" name="gender"><option value="" <?php if($gender=='') echo 'selected';?>>Seleccionar...</option><option value="Masculino" <?php if($gender=='Masculino') echo 'selected';?>>Masculino</option><option value="Femenino" <?php if($gender=='Femenino') echo 'selected';?>>Femenino</option><option value="Otro" <?php if($gender=='Otro') echo 'selected';?>>Otro</option></select></div>
                                    <div class="col-md-4"><label for="medical_record_number" class="form-label">Nº H.C. (Opc.)</label><input type="text" class="form-control" id="medical_record_number" name="medical_record_number" value="<?php echo htmlspecialchars($medical_record_number); ?>"></div>
                                </div>
                                <h5 class="mb-3 mt-4 text-primary"><i class="fas fa-map-marker-alt me-2"></i>Información de Contacto</h5>
                                <div class="row g-3">
                                    <div class="col-md-4"><label for="phone" class="form-label">Teléfono</label><input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>"></div>
                                    <div class="col-md-4"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>"></div>
                                    <div class="col-md-4"><label for="address" class="form-label">Dirección</label><input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>"></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="step2" role="tabpanel">
                                <h5 class="mb-3 text-primary mt-3"><i class="fas fa-briefcase-medical me-2"></i>Obra Social / Prepaga</h5>
                                <div class="row g-3">
                                    <div class="col-md-4"><label for="insurance_name" class="form-label">Nombre de Prepaga/OS</label><input type="text" class="form-control" id="insurance_name" name="insurance_name" value="<?php echo htmlspecialchars($insurance_name); ?>" placeholder="Ej: OSDE"></div>
                                    <div class="col-md-4"><label for="insurance_number" class="form-label">N° de Afiliado/Socio</label><input type="text" class="form-control" id="insurance_number" name="insurance_number" value="<?php echo htmlspecialchars($insurance_number); ?>"></div>
                                    <div class="col-md-4"><label for="insurance_plan" class="form-label">Plan</label><input type="text" class="form-control" id="insurance_plan" name="insurance_plan" value="<?php echo htmlspecialchars($insurance_plan); ?>" placeholder="Ej: 210, Plata"></div>
                                </div>
                                <h5 class="mb-3 mt-4 text-danger"><i class="fas fa-notes-medical me-2"></i>Información Médica Relevante</h5>
                                <div class="row g-3">
                                    <div class="col-md-6"><label for="allergies" class="form-label">Alergias</label><textarea class="form-control" id="allergies" name="allergies" rows="3" placeholder="Ej: Penicilina..."><?php echo htmlspecialchars($allergies); ?></textarea></div>
                                    <div class="col-md-6"><label for="current_medications" class="form-label">Medicación Actual</label><textarea class="form-control" id="current_medications" name="current_medications" rows="3" placeholder="Ej: Anticoagulantes..."><?php echo htmlspecialchars($current_medications); ?></textarea></div>
                                    <div class="col-md-6"><label for="medical_conditions" class="form-label">Condiciones Médicas</label><textarea class="form-control" id="medical_conditions" name="medical_conditions" rows="3" placeholder="Ej: Diabetes, Hipertensión..."><?php echo htmlspecialchars($medical_conditions); ?></textarea></div>
                                    <div class="col-md-6"><label for="important_alerts" class="form-label">Alertas Importantes</label><textarea class="form-control" id="important_alerts" name="important_alerts" rows="3" placeholder="Ej: ALERTA: Paciente con marcapasos..."><?php echo htmlspecialchars($important_alerts); ?></textarea></div>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="step3" role="tabpanel">
                                <div class="portal-access-section mt-3">
                                    <h5 class="mb-3 text-success"><i class="fas fa-user-shield me-2"></i>Acceso al Portal del Paciente</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="patient_portal_access" class="form-label">Estado del Acceso</label>
                                            <select class="form-select" id="patient_portal_access" name="patient_portal_access">
                                                <option value="deshabilitado" <?php if($patient_portal_access == 'deshabilitado') echo 'selected'; ?>>Deshabilitado</option>
                                                <option value="habilitado" <?php if($patient_portal_access == 'habilitado') echo 'selected'; ?>>Habilitado</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div id="portal_fields_container" class="row g-3 mt-2 <?php echo ($patient_portal_access === 'habilitado') ? '' : 'd-none'; ?>">
                                        <div class="col-md-6">
                                            <label for="patient_user_email" class="form-label">Email de Acceso al Portal</label>
                                            <input type="email" class="form-control <?php echo isset($errors['patient_user_email']) ? 'is-invalid' : ''; ?>" id="patient_user_email" name="patient_user_email" value="<?php echo htmlspecialchars($patient_user_email); ?>">
                                            <?php if(isset($errors['patient_user_email'])): ?><div class="invalid-feedback"><?php echo $errors['patient_user_email']; ?></div><?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="patient_initial_password" class="form-label">Contraseña Inicial</label>
                                            <input type="password" class="form-control <?php echo isset($errors['patient_initial_password']) ? 'is-invalid' : ''; ?>" id="patient_initial_password" name="patient_initial_password">
                                             <?php if(isset($errors['patient_initial_password'])): ?><div class="invalid-feedback"><?php echo $errors['patient_initial_password']; ?></div><?php endif; ?>
                                        </div>
                                        <div class="col-md-6"> 
                                            <label for="patient_confirm_password" class="form-label">Confirmar Contraseña</label>
                                            <input type="password" class="form-control <?php echo isset($errors['patient_confirm_password']) ? 'is-invalid' : ''; ?>" id="patient_confirm_password" name="patient_confirm_password">
                                             <?php if(isset($errors['patient_confirm_password'])): ?><div class="invalid-feedback"><?php echo $errors['patient_confirm_password']; ?></div><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between wizard-buttons">
                            <button type="button" class="btn btn-secondary" id="prevBtn"><i class="fas fa-arrow-left me-2"></i>Anterior</button>
                            <button type="button" class="btn btn-primary" id="nextBtn">Siguiente<i class="fas fa-arrow-right ms-2"></i></button>
                            <button type="submit" class="btn btn-success d-none" id="submitBtn"><i class="fas fa-save me-2"></i>Guardar Paciente</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabTriggers = document.querySelectorAll('#patientWizard .nav-link');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        let currentTab = 0;

        function updateButtons() {
            prevBtn.classList.toggle('d-none', currentTab === 0);
            nextBtn.classList.toggle('d-none', currentTab === tabTriggers.length - 1);
            submitBtn.classList.toggle('d-none', currentTab !== tabTriggers.length - 1);
        }

        function showTab(tabIndex) {
            const tab = new bootstrap.Tab(tabTriggers[tabIndex]);
            tab.show();
        }

        tabTriggers.forEach((tabEl, index) => {
            tabEl.addEventListener('shown.bs.tab', () => {
                currentTab = index;
                updateButtons();
            });
        });

        nextBtn.addEventListener('click', () => {
            if (currentTab < tabTriggers.length - 1) {
                currentTab++;
                showTab(currentTab);
            }
        });

        prevBtn.addEventListener('click', () => {
            if (currentTab > 0) {
                currentTab--;
                showTab(currentTab);
            }
        });

        // Lógica para campos de portal condicionales
        const portalAccessSelect = document.getElementById('patient_portal_access');
        const portalFieldsContainer = document.getElementById('portal_fields_container');
        const portalInputs = portalFieldsContainer.querySelectorAll('input');

        function togglePortalFields() {
            if (portalAccessSelect.value === 'habilitado') {
                portalFieldsContainer.classList.remove('d-none');
                portalInputs.forEach(input => input.disabled = false);
            } else {
                portalFieldsContainer.classList.add('d-none');
                portalInputs.forEach(input => {
                    input.disabled = true;
                    input.value = ''; // Limpiar campos al deshabilitar
                });
            }
        }

        portalAccessSelect.addEventListener('change', togglePortalFields);
        
        // Estado inicial
        updateButtons();
        togglePortalFields(); // Ejecutar al cargar por si hay datos de un POST fallido

        // Si hubo errores en el POST, ir a la pestaña con el primer error
        <?php if (!empty($errors)): ?>
        const errorFields = <?php echo json_encode(array_keys($errors)); ?>;
        const fieldToTabMap = {
            'dni': 0, 'fname': 0, 'lname': 0, 'birthdate': 0,
            'allergies': 1, 'current_medications': 1, 'medical_conditions': 1, 'important_alerts': 1,
            'patient_user_email': 2, 'patient_initial_password': 2, 'patient_confirm_password': 2
        };
        for(const field of errorFields) {
            if(fieldToTabMap.hasOwnProperty(field)) {
                showTab(fieldToTabMap[field]);
                break; // Ir a la primera pestaña con error y detener
            }
        }
        <?php endif; ?>
    });
    </script>
</body>
</html>