<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!function_exists('user_has_permission')) {
    function user_has_permission($permission, $permissions_json, $role) {
        if ($role === 'super_admin') return true;
        $permissions = json_decode($permissions_json, true) ?? [];
        return in_array($permission, $permissions);
    }
}

if (!user_has_permission('patients_history', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$patient_id = intval($_GET['patient_id'] ?? 0);
$status_message = '';
$status_type = '';

if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success_history_updated':
            $status_message = '¡Registro del historial actualizado con éxito!'; $status_type = 'success'; break;
        case 'error_history_not_found':
            $status_message = 'Error: No se encontró el registro del historial solicitado.'; $status_type = 'danger'; break;
        case 'error_invalid_request':
             $status_message = 'Error: Solicitud inválida.'; $status_type = 'danger'; break;
        case 'success_history_added':
            $status_message = '¡Nuevo registro agregado al historial con éxito!'; $status_type = 'success'; break;
        case 'success_history_deleted':
            $status_message = '¡Registro del historial eliminado con éxito!'; $status_type = 'success'; break;
        case 'error_history_delete':
            $status_message = 'Error al eliminar el registro del historial.'; $status_type = 'danger';
            if (isset($_GET['msg'])) { $status_message .= ' Detalle: ' . htmlspecialchars(urldecode($_GET['msg']));}
            break;
    }
}

if (!$patient_id) {
    header('Location: index.php?status=error&msg=' . urlencode('ID de paciente no especificado.'));
    exit;
}

$stmt_patient = $mysqli->prepare("SELECT id, fname, lname, dni, birthdate, gender, phone, email, address, medical_record_number, allergies, current_medications, medical_conditions, important_alerts, insurance_name, insurance_number, insurance_plan FROM patients WHERE id=? LIMIT 1");
$patient = null;
if ($stmt_patient) {
    $stmt_patient->bind_param("i", $patient_id);
    $stmt_patient->execute();
    $patient_result = $stmt_patient->get_result();
    $patient = $patient_result->fetch_assoc();
    $stmt_patient->close();
} else {
    error_log("Error al preparar consulta de paciente en history.php: " . $mysqli->error);
    die("Error crítico al cargar datos del paciente.");
}

if (!$patient) {
    header('Location: index.php?status=error&msg=' . urlencode('Paciente no encontrado.'));
    exit;
}

$age = '-';
if (!empty($patient['birthdate']) && $patient['birthdate'] != '0000-00-00') {
    try {
        $birthDateObj = new DateTime($patient['birthdate']);
        $todayObj = new DateTime('today');
        $age = $todayObj->diff($birthDateObj)->y;
    } catch (Exception $e) { $age = '-'; }
}

$history_result_data = [];
$stmt_history = $mysqli->prepare("SELECT id, note, created_at, total_cost FROM medical_history WHERE patient_id=? ORDER BY created_at DESC");
if ($stmt_history) {
    $stmt_history->bind_param("i", $patient_id);
    $stmt_history->execute();
    $result_history_query = $stmt_history->get_result();
    $history_result_data = $result_history_query->fetch_all(MYSQLI_ASSOC);
    $result_history_query->close();
    $stmt_history->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de <?php echo htmlspecialchars($patient['fname'].' '.$patient['lname']); ?> - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .history-entry-card { border-left: 5px solid #0d6efd; transition: box-shadow .3s ease; background-color: #fff; }
        .history-entry-card:hover { box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15); }
        .img-thumbnail-custom { width: 120px; height: 120px; border: 1px solid #dee2e6; padding: 0.25rem; border-radius: 0.25rem; cursor: pointer; object-fit: cover; }
        .history-field-title { display: flex; align-items: center; margin-bottom: 0.25rem; font-size: 0.9rem; color: #495057; }
        .note-content { white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6; color: #212529; padding-left: 28px; margin-top: 0; }
        .cost-display { font-size: 0.95rem; font-weight: 500; color: #198754; padding-left: 28px; margin-top: 0.1rem; margin-bottom: 0; }
        .medical-info-compact-card .card-header, .patient-data-card .card-header { font-size: 0.85rem; padding: 0.4rem 0.75rem; font-weight: bold; }
        .medical-info-compact-card .card-body, .patient-data-card .card-body { font-size: 0.8rem; padding: 0.75rem; line-height: 1.4; }
        .patient-data-card .card-body dl dd, .patient-data-card .card-body dl dt { margin-bottom: 0.25rem; white-space: pre-wrap; font-size: 0.85rem; }
         .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .patient-data-card .card-body dl dt { font-weight: 600; }
        .alert-critical { border-left: 5px solid #dc3545 !important; background-color: #f8d7da !important; color: #721c24 !important; }
        .alert-critical .card-header { background-color: #dc3545 !important; color: white !important; }
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
        <main class="col-12 <?php if (!isMobileDevice()) echo 'col-md-9 col-lg-10'; ?> p-md-4 p-2">
            <div class="card main-content-card rounded-3">
                <div class="card-body p-lg-4 p-md-3 p-2">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap border-bottom pb-3 page-main-header">
                        <h2 class="card-title mb-0 d-flex align-items-center text-primary">
                            <i class="fas fa-book-medical me-3 fa-fw"></i>
                            Historial de: <span class="fw-bold ms-2"><?php echo htmlspecialchars($patient['fname'].' '.$patient['lname']); ?></span>
                        </h2>
                        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                            <a href="gallery.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-images me-1"></i>Galería</a>
                            <a href="edit.php?id=<?php echo $patient_id; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-user-edit me-1"></i>Editar Ficha</a>
                            <a href="add_history.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus-circle me-2"></i>Nuevo Registro</a>
                        </div>
                    </div>

                    <?php if ($status_message): ?>
                        <div class="alert alert-<?php echo $status_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($status_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="medical-info-section mb-4">
                        <h5 class="mb-3 text-primary"><i class="fas fa-notes-medical me-2"></i>Resumen del Paciente</h5>
                        <div class="row g-3 mb-3 d-flex align-items-stretch">
                            <div class="col-lg-7 col-md-6 mb-3 mb-md-0">
                                <?php if (!empty($patient['important_alerts'])): ?>
                                <div class="card alert-critical shadow-sm h-100">
                                    <div class="card-header"><i class="fas fa-exclamation-triangle me-2"></i>Alertas Críticas</div>
                                    <div class="card-body medical-info-compact-card"><p class="mb-0"><?php echo nl2br(htmlspecialchars($patient['important_alerts'])); ?></p></div>
                                </div>
                                <?php else: ?>
                                <div class="card shadow-sm h-100"><div class="card-body medical-info-compact-card d-flex align-items-center justify-content-center"><p class="text-muted mb-0"><i class="fas fa-check-circle text-success me-2"></i>No hay alertas críticas.</p></div></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-lg-5 col-md-6">
                                <div class="card shadow-sm h-100 patient-data-card">
                                    <div class="card-header"><i class="fas fa-user-circle me-2"></i>Datos Básicos</div>
                                    <div class="card-body">
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4"><i class="fas fa-id-card fa-fw text-secondary"></i> DNI:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($patient['dni'] ?: '-'); ?></dd>
                                            <dt class="col-sm-4"><i class="fas fa-birthday-cake fa-fw text-secondary"></i> Nac.:</dt>
                                            <dd class="col-sm-8"><?php echo $patient['birthdate'] ? htmlspecialchars(date('d/m/Y', strtotime($patient['birthdate']))) : '-'; ?></dd>
                                            <dt class="col-sm-4"><i class="fas fa-hourglass-half fa-fw text-secondary"></i> Edad:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($age); ?> años</dd>
                                            <dt class="col-sm-4"><i class="fas fa-phone fa-fw text-secondary"></i> Teléfono:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($patient['phone'] ?: '-'); ?></dd>
                                            <dt class="col-sm-4"><i class="fas fa-envelope fa-fw text-secondary"></i> Email:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($patient['email'] ?: '-'); ?></dd>
                                            <?php if (!empty($patient['gender'])): ?>
                                                <dt class="col-sm-4"><i class="fas fa-venus-mars fa-fw text-secondary"></i> Género:</dt>
                                                <dd class="col-sm-8"><?php echo htmlspecialchars($patient['gender']); ?></dd>
                                            <?php endif; ?>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3 mt-4 history-section-header">
                        <h4 class="mb-0">Registros del Historial Clínico</h4>
                    </div>

                    <?php if (count($history_result_data) === 0): ?>
                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class="fas fa-folder-open fa-2x me-3"></i>
                            <div> <h4 class="alert-heading">Historial Vacío</h4> <p class="mb-0">Aún no hay entradas en el historial. Presione "Nuevo Registro" para comenzar.</p> </div>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach($history_result_data as $history_entry_row): ?>
                                <?php $history_id_entry = $history_entry_row['id']; ?>
                                <div class="list-group-item list-group-item-action p-0 mb-3 history-entry-card rounded shadow-sm">
                                    <div class="card-header bg-light py-2 px-3 border-bottom-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h5 class="mb-0 history-date d-flex align-items-center">
                                            <i class="fas fa-calendar-alt me-2 text-primary"></i>
                                            <?php echo date('d/m/Y \a \l\a\s H:i', strtotime($history_entry_row['created_at'])); ?> hs.
                                        </h5>
                                        <div class="d-flex gap-2">
                                            <a href="edit_history.php?history_id=<?php echo $history_id_entry; ?>&patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-outline-secondary" title="Editar Registro">
                                                <i class="fas fa-edit"></i> <span class="d-none d-sm-inline">Editar</span>
                                            </a>
                                            <form method="POST" action="delete_history_entry.php" onsubmit="return confirm('¿Está seguro de que desea eliminar este registro y todos sus archivos adjuntos? Esta acción es irreversible.');" style="display:inline;">
                                                <input type="hidden" name="history_id_to_delete" value="<?php echo $history_id_entry; ?>">
                                                <input type="hidden" name="patient_id_for_redirect" value="<?php echo $patient_id; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar Registro">
                                                    <i class="fas fa-trash-alt"></i> <span class="d-none d-sm-inline">Eliminar</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="history-field-group">
                                            <div class="history-field-title">
                                                <i class="fas fa-file-alt text-secondary me-2"></i>
                                                <span class="fw-semibold">Nota / Observación:</span>
                                            </div>
                                          <div
  class="note-content"
  style="margin: 0; padding: 0; line-height: 1.4; text-indent: 0; padding-left: 0;"
>
  <?php echo nl2br(htmlspecialchars($history_entry_row['note'])); ?>
</div>

                                        <?php if (isset($history_entry_row['total_cost']) && $history_entry_row['total_cost'] > 0): ?>
                                        <hr class="my-2">
                                        <div class="history-field-group">
                                            <div class="history-field-title">
                                                <i class="fas fa-dollar-sign text-success me-2"></i>
                                                <span class="fw-semibold">Costo Total:</span>
                                            </div>
                                            <p class="cost-display mb-0">
                                                $<?php echo number_format((float)$history_entry_row['total_cost'], 2, ',', '.'); ?>
                                            </p>
                                        </div>
                                        <?php endif; ?>

                                        <?php
                                        $stmt_files = $mysqli->prepare("SELECT id, file_name FROM medical_history_attachments WHERE medical_history_id = ?");
                                        $stmt_files->bind_param('i', $history_id_entry);
                                        $stmt_files->execute();
                                        $files_result = $stmt_files->get_result();
                                        $attachments = $files_result->fetch_all(MYSQLI_ASSOC);
                                        $stmt_files->close();

                                        if (count($attachments) > 0):
                                        ?>
                                        <hr class="my-2">
                                        <div class="history-field-group">
                                            <div class="history-field-title mb-2">
                                                <i class="fas fa-paperclip text-secondary me-2"></i>
                                                <span class="fw-semibold">Archivos Adjuntos:</span>
                                            </div>
                                            <div style="padding-left: 28px;" class="d-flex flex-wrap gap-2">
                                                <?php foreach ($attachments as $file): 
                                                    $file_web_path = $path_to_root . 'uploads/patient_history/' . rawurlencode($file['file_name']);
                                                    $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                                    $is_img = in_array($ext, ['jpg','jpeg','png','gif','bmp','webp']);
                                                ?>
                                                    <?php if ($is_img): ?>
                                                        <a href="#" data-bs-toggle="modal" data-bs-target="#imageModal" data-img-src="<?php echo htmlspecialchars($file_web_path); ?>" data-img-alt="<?php echo htmlspecialchars($file['file_name']); ?>">
                                                            <img src="<?php echo htmlspecialchars($file_web_path); ?>" class="img-thumbnail-custom" alt="<?php echo htmlspecialchars($file['file_name']); ?>">
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="<?php echo htmlspecialchars($file_web_path); ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                            <i class="fas fa-download me-2"></i> <?php echo htmlspecialchars($file['file_name']); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Vista Previa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" id="modalImageDisplay" class="img-fluid" alt="Imagen adjunta">
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var imageModal = document.getElementById('imageModal');
        if (imageModal) {
            imageModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; 
                var imgSrc = button.getAttribute('data-img-src'); 
                var imgAlt = button.getAttribute('data-img-alt');
                var modalTitle = imageModal.querySelector('.modal-title'); 
                var modalImage = imageModal.querySelector('#modalImageDisplay');
                
                modalTitle.textContent = 'Vista Previa: ' + (imgAlt || 'Adjunto'); 
                modalImage.src = imgSrc; 
                modalImage.alt = imgAlt || 'Adjunto';
            });
        }
    });
    </script>
</body>
</html>