<?php
session_start();
// Verify if the patient is logged in
if (!isset($_SESSION['patient_id'])) {
    header('Location: login.php'); 
    exit;
}
require_once '../config.php'; 

$patient_id = $_SESSION['patient_id'];
$currentPage = 'my_profile'; 

$contact_success_message = '';
$contact_error_message = '';
$password_errors = [];
$password_success_message = '';

// Cargar datos actuales del paciente para mostrar y pre-rellenar formularios
$stmt_patient_data = $mysqli->prepare("SELECT fname, lname, patient_user_email, dni, birthdate, phone, email, address, gender, patient_password_hash FROM patients WHERE id = ?");
$patient_data = null;
if ($stmt_patient_data) {
    $stmt_patient_data->bind_param("i", $patient_id);
    $stmt_patient_data->execute();
    $result_patient_data = $stmt_patient_data->get_result();
    $patient_data = $result_patient_data->fetch_assoc();
    $stmt_patient_data->close();
}

if (!$patient_data) {
    $_SESSION['error_message_portal'] = "No se pudieron cargar los datos de su perfil.";
    header('Location: index.php'); 
    exit;
}

// Variables para los formularios de contacto, inicializadas con los datos de la BD
$current_phone = $patient_data['phone'] ?? '';
$current_contact_email = $patient_data['email'] ?? ''; // Email de contacto, no el de login
$current_address = $patient_data['address'] ?? '';

// La consulta para $completed_appointments_history ya no es necesaria aquí.


// Manejo de actualización de información de contacto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact_info'])) {
    $new_phone = trim($_POST['phone'] ?? '');
    $new_contact_email = trim($_POST['email'] ?? '');
    $new_address = trim($_POST['address'] ?? '');
    $contact_errors_temp = [];

    if (!empty($new_contact_email) && !filter_var($new_contact_email, FILTER_VALIDATE_EMAIL)) {
        $contact_errors_temp[] = "El formato del email de contacto no es válido.";
    }

    if (empty($contact_errors_temp)) {
        $stmt_update_contact = $mysqli->prepare("UPDATE patients SET phone = ?, email = ?, address = ? WHERE id = ?");
        if ($stmt_update_contact) {
            $stmt_update_contact->bind_param("sssi", $new_phone, $new_contact_email, $new_address, $patient_id);
            if ($stmt_update_contact->execute()) {
                $contact_success_message = '¡Información de contacto actualizada con éxito!';
                $current_phone = $new_phone;
                $current_contact_email = $new_contact_email;
                $current_address = $new_address;
                $patient_data['phone'] = $new_phone;
                $patient_data['email'] = $new_contact_email;
                $patient_data['address'] = $new_address;
            } else {
                $contact_error_message = 'Error al actualizar la información de contacto: ' . $stmt_update_contact->error;
            }
            $stmt_update_contact->close();
        } else {
            $contact_error_message = 'Error al preparar la actualización de contacto.';
        }
    } else {
        $contact_error_message = implode("<br>", $contact_errors_temp);
    }
}

// Manejo de cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_patient_password'])) {
    $current_password_input = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($current_password_input) || empty($new_password) || empty($confirm_new_password)) {
        $password_errors[] = 'Todos los campos de contraseña son obligatorios.';
    } else {
        $current_db_password_hash = $patient_data['patient_password_hash'];
        if (!$current_db_password_hash || !password_verify($current_password_input, $current_db_password_hash)) {
            $password_errors[] = 'La contraseña actual ingresada es incorrecta.';
        }
        if ($new_password !== $confirm_new_password) {
            $password_errors[] = 'La nueva contraseña y su confirmación no coinciden.';
        }
        if (strlen($new_password) < 8) {
            $password_errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        }
        if (empty($password_errors)) {
            $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update_pass = $mysqli->prepare("UPDATE patients SET patient_password_hash = ? WHERE id = ?");
            if($stmt_update_pass){
                $stmt_update_pass->bind_param("si", $new_password_hashed, $patient_id);
                if ($stmt_update_pass->execute()) {
                    $password_success_message = '¡Contraseña actualizada con éxito!';
                    $patient_data['patient_password_hash'] = $new_password_hashed; 
                } else {
                    $password_errors[] = 'Error al actualizar la contraseña: ' . $stmt_update_pass->error;
                }
                $stmt_update_pass->close();
            } else {
                 $password_errors[] = 'Error al preparar la actualización de contraseña.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil - Portal del Paciente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        html { height: 100%; }
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f7f6; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }
        .portal-navbar { background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,.1); flex-shrink: 0; }
        .portal-navbar .navbar-brand { font-family: 'Montserrat', sans-serif; font-weight: 700; color: #0d6efd; }
        .portal-navbar .navbar-brand i { margin-right: 0.5rem; }
        .portal-navbar .nav-link.active { color: #0056b3 !important; font-weight: bold; }
        .portal-navbar .nav-link { padding-top: 0.8rem; padding-bottom: 0.8rem; }
        .portal-content { padding-top: 2rem; padding-bottom: 2rem; flex-grow: 1; }
        .footer { padding: 1.5rem 0; background-color: #343a40; color: #adb5bd; font-size: 0.9rem; flex-shrink: 0; }
        .section-title { font-family: 'Montserrat', sans-serif; font-weight: 700; color: #343a40; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #0d6efd; display: inline-block; }
        .profile-details dt { font-weight: bold; color: #495057; font-size: 0.9rem; }
        .profile-details dd { margin-bottom: 0.75rem; color: #212529; font-size: 0.9rem;}
        .card-profile-section { margin-bottom: 2rem; }
        .form-label i { margin-right: 0.3rem; }
        /* Los estilos para .history-summary-item ya no son necesarios aquí */
    </style>
</head>
<body>
    <?php require_once '_portal_header.php'; ?>

    <div class="container portal-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title"><i class="fas fa-user-circle me-2"></i>Mi Perfil</h2>
        </div>

        <?php if ($contact_success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($contact_success_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        <?php endif; ?>
        <?php if ($contact_error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $contact_error_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        <?php endif; ?>

        <div class="row">
           
            <div class="col-lg-7 col-md-12 mb-4 mb-lg-0">
                <div class="card shadow-sm card-profile-section">
                    <div class="card-header bg-light"><h5 class="mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>Información Personal</h5></div>
                    <div class="card-body">
                        <?php if ($patient_data): ?>
                            <dl class="row profile-details">
                                <dt class="col-sm-5 col-md-4"><i class="fas fa-user fa-fw text-secondary"></i> Nombre:</dt><dd class="col-sm-7 col-md-8"><?php echo htmlspecialchars($patient_data['fname'] . ' ' . $patient_data['lname']); ?></dd>
                                <dt class="col-sm-5 col-md-4"><i class="fas fa-at fa-fw text-secondary"></i> Email Acceso:</dt><dd class="col-sm-7 col-md-8"><?php echo htmlspecialchars($patient_data['patient_user_email'] ?: 'No asignado'); ?></dd>
                                <dt class="col-sm-5 col-md-4"><i class="fas fa-id-card fa-fw text-secondary"></i> DNI:</dt><dd class="col-sm-7 col-md-8"><?php echo htmlspecialchars($patient_data['dni'] ?: '-'); ?></dd>
                                <dt class="col-sm-5 col-md-4"><i class="fas fa-birthday-cake fa-fw text-secondary"></i> F. Nacimiento:</dt><dd class="col-sm-7 col-md-8"><?php echo $patient_data['birthdate'] ? htmlspecialchars(date('d/m/Y', strtotime($patient_data['birthdate']))) : '-'; ?></dd>
                                <dt class="col-sm-5 col-md-4"><i class="fas fa-phone fa-fw text-secondary"></i> Teléfono:</dt><dd class="col-sm-7 col-md-8"><?php echo htmlspecialchars($patient_data['phone'] ?: '-'); ?></dd>
                                <dt class="col-sm-5 col-md-4"><i class="fas fa-envelope fa-fw text-secondary"></i> Email Contacto:</dt><dd class="col-sm-7 col-md-8"><?php echo htmlspecialchars($patient_data['email'] ?: '-'); ?></dd>
                                <?php if(!empty($patient_data['address'])): ?><dt class="col-sm-5 col-md-4"><i class="fas fa-map-marker-alt fa-fw text-secondary"></i> Dirección:</dt><dd class="col-sm-7 col-md-8"><?php echo htmlspecialchars($patient_data['address']); ?></dd><?php endif; ?>
                                <?php if(!empty($patient_data['gender'])): ?><dt class="col-sm-5 col-md-4"><i class="fas fa-venus-mars fa-fw text-secondary"></i> Género:</dt><dd class="col-sm-7 col-md-8"><?php echo htmlspecialchars($patient_data['gender']); ?></dd><?php endif; ?>
                            </dl>
                        <?php else: ?><p class="text-danger">No se pudo cargar la información de tu perfil.</p><?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm card-profile-section mt-4">
                    <div class="card-header bg-light"><h5 class="mb-0"><i class="fas fa-address-book me-2 text-info"></i>Actualizar Información de Contacto</h5></div>
                    <div class="card-body">
                        <form method="POST" action="my_profile.php">
                            <div class="mb-3"><label for="phone" class="form-label fw-bold"><i class="fas fa-phone text-secondary"></i>Teléfono:</label><input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($current_phone); ?>"></div>
                            <div class="mb-3"><label for="email" class="form-label fw-bold"><i class="fas fa-envelope text-secondary"></i>Email de Contacto:</label><input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($current_contact_email); ?>"><div class="form-text small">Este NO es el email que usa para iniciar sesión.</div></div>
                            <div class="mb-3"><label for="address" class="form-label fw-bold"><i class="fas fa-home text-secondary"></i>Dirección:</label><input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($current_address); ?>"></div>
                            <button type="submit" name="update_contact_info" class="btn btn-info w-100"><i class="fas fa-save me-2"></i>Guardar Cambios de Contacto</button>
                        </form>
                    </div>
                </div>
            </div>

          
            <div class="col-lg-5 col-md-12">
                <div class="card shadow-sm card-profile-section">
                     <div class="card-header bg-light"><h5 class="mb-0"><i class="fas fa-key me-2 text-warning"></i>Cambiar Contraseña del Portal</h5></div>
                    <div class="card-body">
                        <?php if (!empty($password_errors)): ?><div class="alert alert-danger py-2 small" role="alert"><ul class="mb-0 ps-3"><?php foreach ($password_errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                        <?php if ($password_success_message): ?><div class="alert alert-success py-2 small" role="alert"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($password_success_message); ?></div><?php endif; ?>
                        <form method="POST" action="my_profile.php">
                            <div class="mb-3"><label for="current_password" class="form-label fw-bold">Contraseña Actual:</label><input type="password" class="form-control" id="current_password" name="current_password" required></div>
                            <div class="mb-3"><label for="new_password" class="form-label fw-bold">Nueva Contraseña:</label><input type="password" class="form-control" id="new_password" name="new_password" required><div class="form-text small">Mínimo 8 caracteres.</div></div>
                            <div class="mb-3"><label for="confirm_new_password" class="form-label fw-bold">Confirmar Nueva:</label><input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required></div>
                            <button type="submit" name="change_patient_password" class="btn btn-warning w-100"><i class="fas fa-save me-2"></i>Actualizar Contraseña</button>
                        </form>
                    </div>
                </div>

              

            </div>
        </div>
    </div>

    <footer class="footer text-center">
        <div class="container">
            <span>&copy; <?php echo date("Y"); ?> Dra. Fernanda Turano - Clínica Dental. Todos los derechos reservados.</span>
        </div>
    </footer>

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
