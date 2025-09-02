<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 

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

$history_id = filter_input(INPUT_GET, 'history_id', FILTER_VALIDATE_INT);
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

if (!$history_id || !$patient_id) {
    header('Location: index.php?status=error&msg=' . urlencode('Falta ID de historial o de paciente.'));
    exit;
}

$stmt = $mysqli->prepare("SELECT note, created_at, total_cost FROM medical_history WHERE id = ? AND patient_id = ?");
$stmt->bind_param("ii", $history_id, $patient_id);
$stmt->execute();
$history_entry = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$history_entry) {
    header('Location: history.php?patient_id=' . $patient_id . '&status=error_history_not_found');
    exit;
}

$stmt_files = $mysqli->prepare("SELECT id, file_name FROM medical_history_attachments WHERE medical_history_id = ?");
$stmt_files->bind_param("i", $history_id);
$stmt_files->execute();
$existing_attachments = $stmt_files->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_files->close();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $note = $_POST['note'] ?? '';
    $total_cost = !empty($_POST['total_cost']) ? $_POST['total_cost'] : null;
    $files_to_delete = $_POST['delete_files'] ?? [];

    if (empty($note)) { $errors['note'] = 'La nota o descripción es obligatoria.'; }

    if (isset($_FILES['record_files']) && !empty($_FILES['record_files']['name'][0])) {
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $max_file_size = 5 * 1024 * 1024; // 5 MB
        foreach ($_FILES['record_files']['name'] as $key => $name) {
            if ($_FILES['record_files']['error'][$key] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['record_files']['tmp_name'][$key];
                if (!in_array(mime_content_type($file_tmp), $allowed_mime_types)) {
                    $errors['record_files'] = "El archivo '$name' tiene un formato no permitido.";
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
            $stmt_update = $mysqli->prepare("UPDATE medical_history SET note = ?, total_cost = ? WHERE id = ?");
            $stmt_update->bind_param("sdi", $note, $total_cost, $history_id);
            $stmt_update->execute();
            $stmt_update->close();

            if (!empty($files_to_delete)) {
                $delete_ids_placeholders = implode(',', array_fill(0, count($files_to_delete), '?'));
                $stmt_get_filenames = $mysqli->prepare("SELECT file_name FROM medical_history_attachments WHERE id IN ($delete_ids_placeholders)");
                $stmt_get_filenames->bind_param(str_repeat('i', count($files_to_delete)), ...$files_to_delete);
                $stmt_get_filenames->execute();
                $files_to_unlink_result = $stmt_get_filenames->get_result();
                
                $stmt_delete_files = $mysqli->prepare("DELETE FROM medical_history_attachments WHERE id IN ($delete_ids_placeholders)");
                $stmt_delete_files->bind_param(str_repeat('i', count($files_to_delete)), ...$files_to_delete);
                $stmt_delete_files->execute();
                $stmt_delete_files->close();

                while ($file = $files_to_unlink_result->fetch_assoc()) {
                    if(!empty($file['file_name'])) {
                        $file_path = $path_to_root . 'uploads/patient_history/' . $file['file_name'];
                        if (file_exists($file_path)) unlink($file_path);
                    }
                }
            }

            if (isset($_FILES['record_files']) && !empty($_FILES['record_files']['name'][0])) {
                $upload_dir = $path_to_root . 'uploads/patient_history/';
                $stmt_new_files = $mysqli->prepare("INSERT INTO medical_history_attachments (medical_history_id, file_name) VALUES (?, ?)");
                foreach ($_FILES['record_files']['name'] as $key => $name) {
                    if ($_FILES['record_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['record_files']['tmp_name'][$key];
                        $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $new_filename = "hist_{$patient_id}_{$history_id}_" . uniqid() . ".{$file_ext}";
                        $destination = $upload_dir . $new_filename;
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $stmt_new_files->bind_param('is', $history_id, $new_filename);
                            $stmt_new_files->execute();
                        }
                    }
                }
                $stmt_new_files->close();
            }

            $mysqli->commit();
            header('Location: history.php?patient_id=' . $patient_id . '&status=success_history_updated');
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $errors['db_error'] = "Error al actualizar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Registro del Historial - Clínica Dental</title>
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
        .file-list-item, .attachment-list-item { font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; }
        .attachment-list-item .form-check-label { cursor: pointer; display: flex; align-items: center; width: 100%; padding: 0.5rem 0; }
         .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .attachment-list-item .form-check-input { margin-top: 0; }
        .attachment-list-item i.fa-file-image { color: #0d6efd; }
        .attachment-list-item i.fa-file-pdf { color: #dc3545; }
        .attachment-list-item i.fa-file { color: #6c757d; }
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
                        <i class="fas fa-edit fa-2x text-primary me-3"></i>
                        <div>
                            <h3 class="mb-0">Editar Registro de Historial</h3>
                            <p class="mb-0 text-muted">Registro del día: <strong><?php echo date('d/m/Y H:i', strtotime($history_entry['created_at'])); ?> hs</strong></p>
                        </div>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                             <i class="fas fa-exclamation-triangle me-2"></i> Un error ocurrió. Por favor revise los campos marcados.
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data" novalidate>
                        <h5 class="mb-3 fw-bold">Datos del Registro</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label for="note" class="form-label">Descripción / Notas</label>
                                <textarea class="form-control <?php echo !empty($errors['note']) ? 'is-invalid' : ''; ?>" id="note" name="note" rows="8" required><?php echo htmlspecialchars($history_entry['note']); ?></textarea>
                                <?php if (!empty($errors['note'])): ?><div class="invalid-feedback"><?php echo $errors['note']; ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="total_cost" class="form-label">Costo Total ($)</label>
                                <input type="number" class="form-control" id="total_cost" name="total_cost" value="<?php echo htmlspecialchars($history_entry['total_cost']); ?>" min="0" step="0.01">
                            </div>
                        </div>

                        <?php if (!empty($existing_attachments)): ?>
                        <hr class="my-4">
                        <h5 class="mb-3 fw-bold">Gestionar Archivos Adjuntos</h5>
                        <div class="list-group">
                            <?php foreach ($existing_attachments as $file): 
                                $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                $icon_class = 'fas fa-file';
                                if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $icon_class = 'fas fa-file-image text-primary';
                                if($ext == 'pdf') $icon_class = 'fas fa-file-pdf text-danger';
                            ?>
                            <div class="list-group-item attachment-list-item">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="delete_files[]" value="<?php echo $file['id']; ?>" id="delete_file_<?php echo $file['id']; ?>">
                                    <label class="form-check-label" for="delete_file_<?php echo $file['id']; ?>">
                                        <i class="<?php echo $icon_class; ?> fa-lg me-3 ms-2"></i> <?php echo htmlspecialchars($file['file_name']); ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="form-text text-danger mt-2">Active el interruptor para marcar un archivo y eliminarlo permanentemente al guardar.</small>
                        <?php endif; ?>

                        <hr class="my-4">
                        <h5 class="mb-3 fw-bold">Añadir Nuevos Archivos</h5>
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

                        <hr class="my-5">
                        <div class="d-flex justify-content-end gap-3">
                            <a href="history.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-secondary btn-lg px-4">Cancelar</a>
                            <button type="submit" class="btn btn-primary btn-lg px-5">Guardar Cambios</button>
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
                    const list = document.createElement('div');
                    list.className = 'alert alert-light';
                    const listTitle = document.createElement('h6');
                    listTitle.className = 'alert-heading';
                    listTitle.textContent = 'Archivos nuevos para subir:';
                    list.appendChild(listTitle);
                    const ul = document.createElement('ul');
                    ul.className = 'list-unstyled mb-0';
                    for (const file of fileInput.files) {
                        const li = document.createElement('li');
                        li.className = 'file-list-item';
                        li.textContent = file.name;
                        ul.appendChild(li);
                    }
                    list.appendChild(ul);
                    fileListDisplay.appendChild(list);
                }
            }
        });
    </script>
</body>
</html>