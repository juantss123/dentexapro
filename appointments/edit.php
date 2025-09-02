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

// El resto de la lógica PHP de esta página (fetch de datos del turno, etc.)
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php?status=error_no_id'); 
    exit;
}

$stmt_appt = $mysqli->prepare("SELECT a.*, p.fname AS patient_fname, p.lname AS patient_lname
                              FROM appointments a
                              JOIN patients p ON p.id = a.patient_id
                              WHERE a.id = ? LIMIT 1");
$stmt_appt->bind_param("i", $id);
$stmt_appt->execute();
$result_appt = $stmt_appt->get_result();
$appt = $result_appt->fetch_assoc();
$stmt_appt->close();

if (!$appt) {
    header('Location: index.php?status=error_not_found'); 
    exit;
}

$patients_result = $mysqli->query("SELECT id, fname, lname FROM patients ORDER BY fname ASC");

$errors = [];
$patient_id_val = $appt['patient_id'];
$datetime_val = date('Y-m-d\TH:i', strtotime($appt['datetime']));
$reason_val = $appt['reason'];
$status_val = $appt['status'];
$patient_name_display = htmlspecialchars($appt['patient_fname'] . ' ' . $appt['patient_lname']);
$appointment_time_display = htmlspecialchars(date('d/m/Y H:i', strtotime($appt['datetime'])));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id_val = intval($_POST['patient_id']);
    $datetime_val = $_POST['datetime'];
    $reason_val = trim($_POST['reason']);
    $status_val = $_POST['status'];

    if (!$patient_id_val) $errors[] = 'Debe seleccionar un paciente.';
    if (!$datetime_val) $errors[] = 'La fecha y hora son obligatorias.';
    if (!in_array($status_val, ['Programado', 'Completado', 'Cancelado'])) $errors[] = 'Estado inválido.';

    if (empty($errors)) {
        $stmt_update = $mysqli->prepare("UPDATE appointments SET patient_id=?, datetime=?, reason=?, status=? WHERE id=?");
        $stmt_update->bind_param("isssi", $patient_id_val, $datetime_val, $reason_val, $status_val, $id);

        if ($stmt_update->execute()) {
            header('Location: index.php?status=success_update'); 
            exit;
        } else {
            $errors[] = 'Error al actualizar el turno: ' . $stmt_update->error;
        }
        $stmt_update->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Turno - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; } /* Asegúrate que este estilo esté si no es global */
        .chevron { transition: transform .2s; }
        .nav-link[aria-expanded="true"] .chevron { transform: rotate(180deg); }
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .form-label i { margin-right: 8px; }
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
            <div class="card main-content-card shadow-sm"> 
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0 d-flex align-items-center">
                        <i class="fas fa-calendar-edit me-2"></i>Editar Turno
                    </h3>
                    <?php if(isset($patient_name_display) && isset($appointment_time_display)): ?>
                    <small>Paciente: <?php echo $patient_name_display; ?> - <?php echo $appointment_time_display; ?></small>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Error al actualizar</h5>
                            <?php foreach ($errors as $e): ?>
                                <p class="mb-0"><?php echo htmlspecialchars($e); ?></p>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="edit.php?id=<?php echo $id; ?>">
                        <div class="mb-4">
                            <label for="patient_id" class="form-label"><i class="fas fa-user-injured"></i>Paciente</label>
                            <select name="patient_id" id="patient_id" class="form-select form-select-lg" required>
                                <option value="">-- Seleccionar Paciente --</option>
                                <?php
                                if ($patients_result && $patients_result->num_rows > 0) {
                                    while ($p = $patients_result->fetch_assoc()) {
                                        $selected = ($p['id'] == $patient_id_val) ? 'selected' : '';
                                        echo '<option value="' . $p['id'] . '" ' . $selected . '>' . htmlspecialchars($p['fname'] . ' ' . $p['lname']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="datetime" class="form-label"><i class="fas fa-clock"></i>Fecha y Hora</label>
                            <input type="datetime-local" name="datetime" id="datetime" class="form-control form-control-lg" value="<?php echo htmlspecialchars($datetime_val); ?>" required>
                        </div>

                        <div class="mb-4">
                            <label for="reason" class="form-label"><i class="fas fa-file-alt"></i>Motivo de la Consulta</label>
                            <textarea name="reason" id="reason" class="form-control form-control-lg" rows="3" placeholder="Ej: Control general, limpieza, dolor molar..."><?php echo htmlspecialchars($reason_val); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label"><i class="fas fa-check-circle"></i>Estado del Turno</label>
                            <select name="status" id="status" class="form-select form-select-lg" required>
                                <?php
                                $statuses = ['Programado', 'Completado', 'Cancelado'];
                                foreach ($statuses as $st):
                                    $selected = ($st == $status_val) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($st) . '" ' . $selected . '>' . htmlspecialchars($st) . '</option>';
                                endforeach;
                                ?>
                            </select>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="delete.php?id=<?php echo $id; ?>&redirect_page=calendar" 
                               class="btn btn-outline-danger btn-lg" 
                               onclick="return confirm('¿Está seguro de que desea eliminar este turno permanentemente?');">
                                <i class="fas fa-trash me-2"></i>Eliminar
                            </a>
                            <div class="ms-auto d-flex gap-2">
                                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-times-circle me-2"></i>Cancelar Edición
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Actualizar Turno
                                </button>
                            </div>
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