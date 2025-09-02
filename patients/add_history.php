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

$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
$note_value = '';
$record_date_value = date('Y-m-d\TH:i');
$total_cost_value = '';
$errors = [];

if (!$patient_id) {
    header('Location: index.php?status=error&msg=' . urlencode('ID de paciente no especificado.'));
    exit;
}

$stmt_patient_info = $mysqli->prepare("SELECT fname, lname FROM patients WHERE id = ?");
$patient_name_display = "Desconocido";
if($stmt_patient_info){
    $stmt_patient_info->bind_param('i', $patient_id);
    $stmt_patient_info->execute();
    $result_patient_info = $stmt_patient_info->get_result();
    if($patient_info = $result_patient_info->fetch_assoc()){
        $patient_name_display = htmlspecialchars($patient_info['fname'] . ' ' . $patient_info['lname']);
    }
    $stmt_patient_info->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_date = $_POST['record_date'] ?? '';
    $note = $_POST['note'] ?? '';
    $total_cost = !empty($_POST['total_cost']) ? $_POST['total_cost'] : null;

    $note_value = $note;
    $record_date_value = $record_date;
    $total_cost_value = $total_cost;

    if (empty($note)) $errors['note'] = 'La nota o descripción es obligatoria.';
    if (empty($record_date)) $errors['record_date'] = 'La fecha del registro es obligatoria.';
    if ($total_cost !== null && !is_numeric($total_cost)) $errors['total_cost'] = 'El costo total debe ser un número válido.';

    if (isset($_FILES['record_files']) && !empty($_FILES['record_files']['name'][0])) {
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $max_file_size = 5 * 1024 * 1024; // 5 MB

        foreach ($_FILES['record_files']['name'] as $key => $name) {
            if ($_FILES['record_files']['error'][$key] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['record_files']['tmp_name'][$key];
                if (!in_array(mime_content_type($file_tmp), $allowed_mime_types)) {
                    $errors['record_files'] = "El archivo '$name' tiene un formato no permitido. Solo se aceptan imágenes y PDF.";
                }
                if ($_FILES['record_files']['size'][$key] > $max_file_size) {
                    $errors['record_files'] = "El archivo '$name' es demasiado grande (max 5 MB).";
                }
            }
        }
    }

    if (empty($errors)) {
        $mysqli->begin_transaction();
        try {
            $db_date = date('Y-m-d H:i:s', strtotime($record_date));
            $stmt_history = $mysqli->prepare("INSERT INTO medical_history (patient_id, note, created_at, total_cost) VALUES (?, ?, ?, ?)");
            $stmt_history->bind_param('issd', $patient_id, $note, $db_date, $total_cost);
            $stmt_history->execute();
            $history_id = $mysqli->insert_id;
            $stmt_history->close();

            if (isset($_FILES['record_files']) && !empty($_FILES['record_files']['name'][0])) {
                $upload_dir = '../uploads/patient_history/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0775, true); }
                
                $stmt_files = $mysqli->prepare("INSERT INTO medical_history_attachments (medical_history_id, file_name) VALUES (?, ?)");
                foreach ($_FILES['record_files']['name'] as $key => $name) {
                    if ($_FILES['record_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['record_files']['tmp_name'][$key];
                        $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $new_filename = "hist_{$patient_id}_{$history_id}_" . uniqid() . ".{$file_ext}";
                        $destination = $upload_dir . $new_filename;
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $stmt_files->bind_param('is', $history_id, $new_filename);
                            $stmt_files->execute();
                        }
                    }
                }
                $stmt_files->close();
            }

            $mysqli->commit();
            header('Location: history.php?patient_id=' . $patient_id . '&status=success_history_added');
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $errors['db_error'] = "Error al guardar en la base de datos: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Añadir a Historial de <?php echo $patient_name_display; ?> - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; }
        .main-content-card { border: none; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); border-radius: 12px; }
        .file-drop-area { position: relative; display: block; width: 100%; border: 2px dashed #ced4da; border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; transition: border-color .3s, background-color .3s; background-color: #f8f9fa; }
        .file-drop-area:hover, .file-drop-area.is-dragover { border-color: #0d6efd; background-color: #e9ecef; }
        .file-drop-area .file-input { position: absolute; left: 0; top: 0; height: 100%; width: 100%; opacity: 0; cursor: pointer; }
        .file-drop-icon { font-size: 3rem; color: #adb5bd; }
        .file-list-item { font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; }
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
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
        <main class="col-12 <?php if (!isMobileDevice()) echo 'col-md-9 col-lg-10'; ?> p-md-4 p-3">
            <div class="card main-content-card">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-center mb-4">
                        <i class="fas fa-notes-medical fa-2x text-primary me-3"></i>
                        <div>
                            <h3 class="mb-0">Nuevo Registro en Historial</h3>
                            <p class="mb-0 text-muted">Para el paciente: <strong><?php echo $patient_name_display; ?></strong></p>
                        </div>
                    </div>

                    <?php if (!empty($errors['db_error'])): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $errors['db_error']; ?></div>
                    <?php endif; ?>

                    <form action="add_history.php?patient_id=<?php echo $patient_id; ?>" method="POST" enctype="multipart/form-data" novalidate>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label for="record_date" class="form-label fw-bold">Fecha y Hora del Registro</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="datetime-local" class="form-control <?php echo !empty($errors['record_date']) ? 'is-invalid' : ''; ?>" id="record_date" name="record_date" value="<?php echo htmlspecialchars($record_date_value); ?>" required>
                                    <?php if (!empty($errors['record_date'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['record_date']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="total_cost" class="form-label fw-bold">Costo Total ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                    <input type="number" class="form-control <?php echo !empty($errors['total_cost']) ? 'is-invalid' : ''; ?>" id="total_cost" name="total_cost" value="<?php echo htmlspecialchars($total_cost_value); ?>" min="0" step="0.01" placeholder="0.00">
                                    <?php if (!empty($errors['total_cost'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['total_cost']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="note" class="form-label fw-bold">Descripción / Notas</label>
                                <textarea class="form-control <?php echo !empty($errors['note']) ? 'is-invalid' : ''; ?>" id="note" name="note" rows="8" required placeholder="Escriba aquí los detalles del tratamiento, observaciones, diagnóstico, etc."><?php echo htmlspecialchars($note_value); ?></textarea>
                                <?php if (!empty($errors['note'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['note']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Adjuntar Archivos</label>
                                <label for="record_files" class="file-drop-area <?php echo !empty($errors['record_files']) ? 'border-danger' : ''; ?>">
                                    <input type="file" class="file-input" id="record_files" name="record_files[]" multiple accept="image/jpeg,image/png,image/gif,application/pdf">
                                    <i class="fas fa-cloud-upload-alt file-drop-icon"></i>
                                    <p class="mb-0 mt-2">Arrastra y suelta los archivos aquí, o <strong>haz clic para seleccionar</strong>.</p>
                                    <small class="text-muted">Se permiten imágenes y PDF (máx 5MB).</small>
                                </label>
                                <?php if (!empty($errors['record_files'])): ?>
                                    <div class="text-danger small mt-2"><?php echo $errors['record_files']; ?></div>
                                <?php endif; ?>
                                <div id="file-list-display" class="mt-3"></div>
                            </div>
                        </div>

                        <hr class="my-5">

                        <div class="d-flex justify-content-end gap-3">
                            <a href="history.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-secondary btn-lg px-4">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-save me-2"></i>Guardar Registro
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const fileDropArea = document.querySelector('.file-drop-area');
            const fileInput = document.querySelector('.file-input');
            const fileListDisplay = document.getElementById('file-list-display');

            if(fileDropArea) {
                // El <label> se encarga del click. Solo necesitamos los eventos de arrastrar.
                fileDropArea.addEventListener('dragover', (e) => { e.preventDefault(); fileDropArea.classList.add('is-dragover'); });
                fileDropArea.addEventListener('dragleave', () => { fileDropArea.classList.remove('is-dragover'); });
                fileDropArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    fileDropArea.classList.remove('is-dragover');
                    fileInput.files = e.dataTransfer.files;
                    updateFileList();
                });
                fileInput.addEventListener('change', updateFileList);
            }

            function updateFileList() {
                fileListDisplay.innerHTML = '';
                if (fileInput.files.length > 0) {
                    const list = document.createElement('ul');
                    list.className = 'list-group';
                    for (const file of fileInput.files) {
                        const listItem = document.createElement('li');
                        listItem.className = 'list-group-item list-group-item-sm file-list-item';
                        const fileName = document.createElement('span');
                        fileName.textContent = file.name;
                        const fileSize = document.createElement('small');
                        fileSize.className = 'text-muted';
                        fileSize.textContent = (file.size / 1024).toFixed(2) + ' KB';
                        listItem.appendChild(fileName);
                        listItem.appendChild(fileSize);
                        list.appendChild(listItem);
                    }
                    fileListDisplay.appendChild(list);
                }
            }
        });
    </script>
</body>
</html>