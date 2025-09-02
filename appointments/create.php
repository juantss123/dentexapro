<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!user_has_permission('appointments', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$success_message = '';
$error_message = '';
$info_message = '';

// Para la búsqueda de pacientes
$patient_search_term = trim($_GET['patient_search_term'] ?? '');
$patient_search_by = trim($_GET['patient_search_by'] ?? 'lname'); // Por defecto buscar por apellido

// Fetch patients for dropdown, filtered by search if provided
$sql_patients = "SELECT id, dni, fname, lname, email FROM patients"; // Agregado email aquí
$where_clauses_patient_search = [];
$params_patient_search = [];
$types_patient_search = "";

if (!empty($patient_search_term)) {
    $search_query_like = "%" . $patient_search_term . "%";
    if ($patient_search_by === 'dni') {
        $where_clauses_patient_search[] = "dni LIKE ?";
    } elseif ($patient_search_by === 'fname') {
        $where_clauses_patient_search[] = "fname LIKE ?";
    } else { // lname por defecto
        $where_clauses_patient_search[] = "lname LIKE ?";
    }
    $params_patient_search[] = $search_query_like;
    $types_patient_search .= "s";
}

if (!empty($where_clauses_patient_search)) {
    $sql_patients .= " WHERE " . implode(" AND ", $where_clauses_patient_search);
}
$sql_patients .= " ORDER BY lname ASC, fname ASC";

$stmt_patients_dropdown = $mysqli->prepare($sql_patients);
$patients_result_for_dropdown = null; // Inicializar
if ($stmt_patients_dropdown) {
    if (!empty($params_patient_search)) {
        $stmt_patients_dropdown->bind_param($types_patient_search, ...$params_patient_search);
    }
    $stmt_patients_dropdown->execute();
    $patients_result_for_dropdown = $stmt_patients_dropdown->get_result();
} else {
    $errors[] = "Error preparando la consulta de pacientes: " . $mysqli->error;
}


$errors = [];
$patient_id_val = '';
$datetime_val = '';
$reason_val = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $patient_id_val = intval($_POST['patient_id'] ?? 0);
    $datetime_val = $_POST['datetime'] ?? '';
    $reason_val = trim($_POST['reason'] ?? '');
    $status_val = 'Programado'; 

    if(!$patient_id_val) $errors[]='Debe seleccionar un paciente.';
    if(!$datetime_val) $errors[]='La fecha y hora son obligatorias.';
    
    if(empty($errors)){
        $stmt_insert_appt=$mysqli->prepare("INSERT INTO appointments (patient_id, datetime, reason, status) VALUES (?, ?, ?, ?)");
        if ($stmt_insert_appt) {
            $stmt_insert_appt->bind_param("isss", $patient_id_val, $datetime_val, $reason_val, $status_val);
            if($stmt_insert_appt->execute()){
                // INICIO: Lógica para enviar email de confirmación
                $patient_email = '';
                $patient_fullname = 'Paciente';

                $stmt_patient_email = $mysqli->prepare("SELECT fname, lname, email FROM patients WHERE id = ?");
                if ($stmt_patient_email) {
                    $stmt_patient_email->bind_param("i", $patient_id_val);
                    $stmt_patient_email->execute();
                    $result_patient_email = $stmt_patient_email->get_result();
                    if ($patient_info = $result_patient_email->fetch_assoc()) {
                        $patient_email = $patient_info['email'];
                        $patient_fullname = htmlspecialchars($patient_info['fname'] . ' ' . $patient_info['lname']);
                    }
                    $stmt_patient_email->close();
                }

                if (!empty($patient_email)) {
                    try {
                        $appointment_datetime_obj = new DateTime($datetime_val);
                        $formatted_date = $appointment_datetime_obj->format('d/m/Y');
                        $formatted_time = $appointment_datetime_obj->format('H:i');
                    } catch (Exception $e) {
                        $formatted_date = 'Fecha Inválida';
                        $formatted_time = 'Hora Inválida';
                    }

                    $clinic_name = "Clínica Dental Dra. Fernanda Turano"; // Puedes poner esto en config.php
                    $clinic_email_from = "turnos@dentexapro.com"; // Email "De" para el sistema

                    $subject = "Confirmación de Turno en {$clinic_name}";
                    
                    $html_content = "
                    <html>
                    <head><title>{$subject}</title></head>
                    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                        <div style='max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                            <h2 style='color: #0d6efd;'>Confirmación de Turno</h2>
                            <p>Hola <strong>{$patient_fullname}</strong>,</p>
                            <p>Te confirmamos que tu turno ha sido programado con éxito en <strong>{$clinic_name}</strong> con los siguientes detalles:</p>
                            <ul style='list-style-type: none; padding-left: 0;'>
                                <li style='margin-bottom: 10px;'><strong>Fecha:</strong> {$formatted_date}</li>
                                <li style='margin-bottom: 10px;'><strong>Hora:</strong> {$formatted_time} hs</li>";
                    if (!empty($reason_val)) {
                        $html_content .= "<li style='margin-bottom: 10px;'><strong>Motivo:</strong> " . htmlspecialchars($reason_val) . "</li>";
                    }
                    $html_content .= "
                            </ul>
                            <p>Si necesitas reprogramar o cancelar tu turno, por favor contáctanos con la mayor anticipación posible.</p>
                            <p>¡Te esperamos!</p>
                            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                            <p style='font-size: 0.9em; color: #777;'>Este es un mensaje automático, por favor no respondas directamente a este correo.</p>
                        </div>
                    </body>
                    </html>";

                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: \"{$clinic_name}\" <{$clinic_email_from}>" . "\r\n";
                    // Opcional: $headers .= "Reply-To: email_de_respuesta_real@dominio.com" . "\r\n";

                    // @ para suprimir errores de mail() si la configuración del servidor no es correcta,
                    // pero es mejor loguear estos errores si es posible.
                    if (!@mail($patient_email, $subject, $html_content, $headers)) {
                        // Opcional: Registrar error si el email no se pudo enviar
                        error_log("Error al enviar email de confirmación de turno a: {$patient_email} para el turno del paciente ID: {$patient_id_val}");
                        // No se añaden errores al array $errors para no bloquear la redirección
                        // ya que el turno SÍ se creó.
                    }
                }
                // FIN: Lógica para enviar email de confirmación
                
                header('Location: index.php?status=success_create'); 
                exit;
            }else{
                $errors[]='Error al guardar el turno: '.$stmt_insert_appt->error;
            }
            $stmt_insert_appt->close();
        } else {
            $errors[] = 'Error al preparar la consulta de inserción: ' . $mysqli->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Programar Nuevo Turno - Clínica Dental</title>
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
        .form-label i { margin-right: 8px; }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
        .patient-search-form { margin-bottom: 1.5rem; padding: 1rem; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: .375rem;}
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
if (isMobileDevice()) { // Asume que isMobileDevice() est� en config.php
    require_once ($path_to_root = '../') . '_sidebarmovil.php';

    // Barra de navegaci�n superior para m�viles CON LOGO CENTRADO
    echo '
    <nav class="navbar navbar-dark d-md-none sticky-top mb-3" style="background-color: #0d6efd;">
        <div class="container-fluid" style="position: relative; display: flex; align-items: center; justify-content: flex-start; min-height: 56px;"> 
            
           
            <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile" aria-controls="sidebarMobile" style="z-index: 10;"> 
                <i class="fas fa-bars"></i>
                <span class="d-none d-sm-inline ms-1">Men�</span>
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
            <div class="card main-content-card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0 d-flex align-items-center">
                        <i class="fas fa-calendar-plus me-2"></i>Programar Nuevo Turno
                    </h3>
                </div>
                <div class="card-body p-4">
                    <?php if(!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Error al guardar</h5>
                            <ul class="mb-0 ps-3">
                                <?php foreach($errors as $e): ?>
                                    <li><?php echo htmlspecialchars($e); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                   
                    <form method="GET" action="create.php" class="patient-search-form">
                        <h5 class="mb-3"><i class="fas fa-search me-2"></i>Buscar Paciente</h5>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="patient_search_term" class="form-label">Término:</label>
                                <input type="text" class="form-control" id="patient_search_term" name="patient_search_term" value="<?php echo htmlspecialchars($patient_search_term); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="patient_search_by" class="form-label">Buscar por:</label>
                                <select name="patient_search_by" id="patient_search_by" class="form-select">
                                    <option value="lname" <?php if($patient_search_by == 'lname') echo 'selected'; ?>>Apellido</option>
                                    <option value="fname" <?php if($patient_search_by == 'fname') echo 'selected'; ?>>Nombre</option>
                                    <option value="dni" <?php if($patient_search_by == 'dni') echo 'selected'; ?>>DNI</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-info w-100"><i class="fas fa-filter me-1"></i>Buscar</button>
                            </div>
                        </div>
                         <?php if (!empty($patient_search_term)): ?>
                            <div class="mt-2">
                                <a href="create.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>Limpiar Búsqueda y Mostrar Todos</a>
                            </div>
                        <?php endif; ?>
                    </form>


                    <form method="POST" action="create.php<?php if(!empty($patient_search_term)) echo '?patient_search_term='.urlencode($patient_search_term).'&patient_search_by='.urlencode($patient_search_by); ?>">
                        <div class="mb-4">
                            <label for="patient_id" class="form-label"><i class="fas fa-user-injured"></i>Paciente <span class="text-danger">*</span></label>
                            <select name="patient_id" id="patient_id" class="form-select form-select-lg" required>
                                <option value="">-- Seleccionar Paciente --</option>
                                <?php 
                                if ($patients_result_for_dropdown && $patients_result_for_dropdown->num_rows > 0) {
                                    while($p = $patients_result_for_dropdown->fetch_assoc()): ?>
                                        <option value="<?php echo $p['id']; ?>" <?php echo ($patient_id_val == $p['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['lname'] . ', ' . $p['fname'] . ($p['dni'] ? ' (DNI: ' . $p['dni'] . ')' : '') . (!empty($p['email']) ? ' - Email: ' . $p['email'] : '')); ?>
                                        </option>
                                <?php 
                                    endwhile;
                                    // No cerrar $stmt_patients_dropdown aquí si $patients_result_for_dropdown es su resultado.
                                    // Se cierra después del bucle si es necesario, o al final del script.
                                } elseif (!empty($patient_search_term)) {
                                    echo '<option value="" disabled>No se encontraron pacientes con los criterios de búsqueda.</option>';
                                } else {
                                     echo '<option value="" disabled>No hay pacientes registrados o no se pudo cargar la lista.</option>';
                                }
                                ?>
                            </select>
                             <?php if (empty($patient_search_term) && $patients_result_for_dropdown && $patients_result_for_dropdown->num_rows === 0): ?>
                                <div class="form-text text-warning">No hay pacientes cargados en el sistema. <a href="<?php echo $path_to_root; ?>patients/create.php">Agregar nuevo paciente</a>.</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label for="datetime" class="form-label"><i class="fas fa-clock"></i>Fecha y Hora <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="datetime" id="datetime" class="form-control form-control-lg" value="<?php echo htmlspecialchars($datetime_val); ?>" required>
                        </div>

                        <div class="mb-4">
                            <label for="reason" class="form-label"><i class="fas fa-file-alt"></i>Motivo de la Consulta (Opcional)</label>
                            <textarea name="reason" id="reason" class="form-control form-control-lg" rows="3" placeholder="Ej: Control general, limpieza, dolor molar..."><?php echo htmlspecialchars($reason_val); ?></textarea>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times-circle me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Guardar Turno
                            </button>
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
<?php
if (isset($stmt_patients_dropdown)) {
    $stmt_patients_dropdown->close();
}
?>