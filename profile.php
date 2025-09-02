<?php
session_start(); // Ensure session starts at the very beginning
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config.php'; 

$current_admin_id = $_SESSION['admin_id']; 
$current_user_role = $_SESSION['admin_role'] ?? 'employee'; 

$errors = []; 
$success_message = ''; 
$password_errors = []; 
$password_success_message = ''; 
$new_admin_errors = []; 
$new_admin_success_message = ''; 
$delete_admin_message = ''; 
$delete_admin_status_type = ''; 

if (!defined('MAIN_ADMIN_ID_PROFILE')) { 
    define('MAIN_ADMIN_ID_PROFILE', 1); 
}
if (!defined('SUPER_ADMIN_MAIN_ID')) { 
    define('SUPER_ADMIN_MAIN_ID', 1);
}

$path_to_root = ''; 

// Handle status messages from GET parameters
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $status_from_action = $_GET['status']; 
    $msg_from_action = urldecode($_GET['msg']);

    if ($status_from_action === 'success_profile_updated') {
        $success_message = $msg_from_action;
    } elseif ($status_from_action === 'success_password_updated') {
        $password_success_message = $msg_from_action;
    } elseif ($status_from_action === 'success_admin_created') {
        $new_admin_success_message = $msg_from_action;
    } elseif (isset($_GET['status_user_action']) && strpos($_GET['status_user_action'], 'success') !== false && isset($_GET['msg_user_action'])) { 
        $delete_admin_status_type = 'success';
        $delete_admin_message = urldecode($_GET['msg_user_action']);
    } elseif (strpos($status_from_action, 'error') !== false || (isset($_GET['status_user_action']) && strpos($_GET['status_user_action'], 'error') !== false) ) {
        $error_context = $_GET['context'] ?? (isset($_GET['status_user_action']) ? 'delete_admin' : 'general');
        $error_msg_to_display = isset($_GET['msg_user_action']) ? urldecode($_GET['msg_user_action']) : $msg_from_action;

        switch ($error_context) {
            case 'photo': $errors[] = $error_msg_to_display; break;
            case 'password': $password_errors[] = $error_msg_to_display; break;
            case 'new_admin': $new_admin_errors[] = $error_msg_to_display; break;
            case 'delete_admin':
                $delete_admin_status_type = 'danger';
                $delete_admin_message = $error_msg_to_display;
                break;
            default:
                 if(empty($errors) && empty($password_errors) && empty($new_admin_errors) && empty($delete_admin_message)){
                    $errors[] = $error_msg_to_display; 
                 }
                break;
        }
    }
}


$profile_upload_dir = UPLOAD_DIR . 'profiles/';
if (!file_exists($profile_upload_dir)) {
    if (!@mkdir($profile_upload_dir, 0777, true) && !is_dir($profile_upload_dir)) {
        // $errors[] = "Advertencia: No se pudo crear el directorio de subida de perfiles: " . $profile_upload_dir;
    }
}

$stmt_current_admin_details = $mysqli->prepare("SELECT name, email, profile_image, password AS current_password_hash FROM admin_users WHERE id = ?");
$current_name = $_SESSION['admin_name'] ?? ''; 
$current_email = ''; 
$db_profile_image = $_SESSION['admin_profile_image'] ?? null; 
$current_password_hash = '';

if ($stmt_current_admin_details) {
    $stmt_current_admin_details->bind_param("i", $current_admin_id);
    $stmt_current_admin_details->execute();
    $result_admin_details = $stmt_current_admin_details->get_result();
    if($admin_details_row = $result_admin_details->fetch_assoc()){
        $current_name = $admin_details_row['name'];
        $current_email = $admin_details_row['email'];
        $db_profile_image = $admin_details_row['profile_image']; 
        $current_password_hash = $admin_details_row['current_password_hash'];
    }
    $stmt_current_admin_details->close();
} else {
    $errors[] = "Error al cargar los detalles del perfil: " . $mysqli->error;
}
$current_profile_image_filename = $_SESSION['admin_profile_image'] ?? $db_profile_image;

$new_admin_name_val = '';
$new_admin_email_val = '';
$new_admin_role_val = 'employee';
$new_admin_posted_permissions = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile_picture'])) {
        // ... (lógica de actualización de foto, sin cambios) ...
        if (isset($_FILES['profile_image_file']) && $_FILES['profile_image_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['profile_image_file']['tmp_name']; $file_name_original = basename($_FILES['profile_image_file']['name']); $file_size = $_FILES['profile_image_file']['size']; $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif']; $max_file_size = 2 * 1024 * 1024; $post_photo_errors = [];
            if (!in_array($file_extension, $allowed_extensions)) { $post_photo_errors[] = 'Tipo de archivo no permitido.'; } elseif ($file_size > $max_file_size) { $post_photo_errors[] = 'El archivo es demasiado grande.';} 
            if(empty($post_photo_errors)) {
                $new_filename = 'admin_' . $current_admin_id . '_' . time() . '.' . $file_extension; $destination_path = $profile_upload_dir . $new_filename;
                if (move_uploaded_file($file_tmp_path, $destination_path)) {
                    if (!empty($current_profile_image_filename) && $current_profile_image_filename !== $new_filename && file_exists($profile_upload_dir . $current_profile_image_filename)) { @unlink($profile_upload_dir . $current_profile_image_filename); }
                    $stmt_update_pic = $mysqli->prepare("UPDATE admin_users SET profile_image = ? WHERE id = ?"); 
                    if ($stmt_update_pic) { $stmt_update_pic->bind_param("si", $new_filename, $current_admin_id);
                        if ($stmt_update_pic->execute()) { $_SESSION['admin_profile_image'] = $new_filename; session_write_close(); header('Location: profile.php?status=success_profile_updated&msg=' . urlencode('¡Foto de perfil actualizada!')); exit;
                        } else { $errors[] = 'Error al actualizar foto en BD: ' . $stmt_update_pic->error; } $stmt_update_pic->close();
                    } else { $errors[] = 'Error preparando BD para foto.';}
                } else { $errors[] = 'Error al mover archivo.'; }
            } else { $errors = array_merge($errors, $post_photo_errors); }
        } elseif (isset($_FILES['profile_image_file']) && $_FILES['profile_image_file']['error'] !== UPLOAD_ERR_NO_FILE) { $errors[] = 'Error subiendo foto. Código: ' . $_FILES['profile_image_file']['error']; }
    }
    elseif (isset($_POST['change_password_submit'])) {
        // ... (lógica de cambio de contraseña, sin cambios) ...
        $current_password_input = $_POST['current_password'] ?? ''; $new_password = $_POST['new_password'] ?? ''; $confirm_new_password = $_POST['confirm_new_password'] ?? '';
        if (empty($current_password_input) || empty($new_password) || empty($confirm_new_password)) { $password_errors[] = 'Todos los campos de contraseña son obligatorios.';
        } else {
            if (empty($current_password_hash) || !password_verify($current_password_input, $current_password_hash)) { $password_errors[] = 'La contraseña actual ingresada es incorrecta.'; }
            if ($new_password !== $confirm_new_password) { $password_errors[] = 'La nueva contraseña y su confirmación no coinciden.'; }
            if (strlen($new_password) < 8) { $password_errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.'; }
            if (empty($password_errors)) {
                $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update_pass = $mysqli->prepare("UPDATE admin_users SET password = ? WHERE id = ?"); 
                if($stmt_update_pass){ $stmt_update_pass->bind_param("si", $new_password_hashed, $current_admin_id);
                    if ($stmt_update_pass->execute()) { $current_password_hash = $new_password_hashed; session_write_close(); header('Location: profile.php?status=success_password_updated&msg=' . urlencode('¡Contraseña actualizada con éxito!')); exit;
                    } else { $password_errors[] = 'Error al actualizar la contraseña: ' . $stmt_update_pass->error; } $stmt_update_pass->close();
                } else { $password_errors[] = 'Error al preparar la actualización de contraseña.';}
            }
        }
    }
    elseif (isset($_POST['create_admin_submit'])) {
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin') {
            $new_admin_name_val = trim($_POST['new_admin_name'] ?? ''); 
            $new_admin_email_val = trim($_POST['new_admin_email'] ?? ''); 
            $new_admin_password = $_POST['new_admin_password'] ?? ''; 
            $new_admin_confirm_password = $_POST['new_admin_confirm_password'] ?? '';
            $new_admin_role_val = $_POST['new_admin_role'] ?? 'employee';
            $new_admin_posted_permissions = $_POST['new_admin_permissions'] ?? [];

            if (empty($new_admin_name_val)) $new_admin_errors[] = "El nombre es obligatorio.";
            if (empty($new_admin_email_val)) $new_admin_errors[] = "El email es obligatorio."; 
            elseif (!filter_var($new_admin_email_val, FILTER_VALIDATE_EMAIL)) $new_admin_errors[] = "El formato del email no es válido.";
            if (empty($new_admin_password)) $new_admin_errors[] = "La contraseña es obligatoria.";
            if ($new_admin_password !== $new_admin_confirm_password) $new_admin_errors[] = "Las contraseñas no coinciden.";
            if (strlen($new_admin_password) < 8) $new_admin_errors[] = "La contraseña debe tener al menos 8 caracteres.";
            if (!in_array($new_admin_role_val, ['admin', 'employee'])) { $new_admin_errors[] = "Rol inválido."; $new_admin_role_val = 'employee'; }
            
            $db_section_codes = []; 
            $temp_result_sections = $mysqli->query("SELECT section_code FROM sidebar_sections WHERE section_code != 'profile'"); // Excluir 'profile'
            if ($temp_result_sections) { while ($s_row = $temp_result_sections->fetch_assoc()) { $db_section_codes[] = $s_row['section_code']; } $temp_result_sections->free(); }

            $valid_new_permissions = [];
            foreach ($new_admin_posted_permissions as $perm_code) { if (in_array($perm_code, $db_section_codes)) { $valid_new_permissions[] = $perm_code; } }
            $new_permissions_json = json_encode($valid_new_permissions);

            if (empty($new_admin_errors)) {
                $stmt_check_email = $mysqli->prepare("SELECT id FROM admin_users WHERE email = ?"); 
                if($stmt_check_email){
                    $stmt_check_email->bind_param("s", $new_admin_email_val); $stmt_check_email->execute(); $stmt_check_email->store_result();
                    if ($stmt_check_email->num_rows > 0) { $new_admin_errors[] = "El email ingresado ya está registrado."; }
                    $stmt_check_email->close();
                } else {$new_admin_errors[] = "Error al verificar email.";}
            }
            if (empty($new_admin_errors)) {
                $hashed_password = password_hash($new_admin_password, PASSWORD_DEFAULT);
                $stmt_insert_admin = $mysqli->prepare("INSERT INTO admin_users (name, email, password, role, permissions) VALUES (?, ?, ?, ?, ?)"); 
                if($stmt_insert_admin){
                    $stmt_insert_admin->bind_param("sssss", $new_admin_name_val, $new_admin_email_val, $hashed_password, $new_admin_role_val, $new_permissions_json);
                    if ($stmt_insert_admin->execute()) { 
                        session_write_close();
                        header('Location: profile.php?status=success_admin_created&msg=' . urlencode('¡Nuevo usuario "' . htmlspecialchars($new_admin_name_val) . '" creado!'));
                        exit;
                    } else { $new_admin_errors[] = 'Error al crear el nuevo usuario: ' . $stmt_insert_admin->error; }
                    $stmt_insert_admin->close();
                } else {$new_admin_errors[] = 'Error al preparar inserción de usuario.';}
            }
        } else {
            $new_admin_errors[] = "No tiene permisos para crear nuevos usuarios.";
        }
    }
}

$existing_admins = [];
$result_admins = $mysqli->query("SELECT id, name, email, role, profile_image FROM admin_users ORDER BY name ASC");
if ($result_admins) { while ($admin_row = $result_admins->fetch_assoc()) { $existing_admins[] = $admin_row; } $result_admins->free(); }

$page_profile_image_path = ($path_to_root ?: './') . 'assets/img/default-avatar.png';
if (!empty($current_profile_image_filename) && defined('UPLOAD_DIR_NAME_CONST') && file_exists(UPLOAD_DIR . 'profiles/' . $current_profile_image_filename)) {
    $page_profile_image_path = ($path_to_root ?: './') . UPLOAD_DIR_NAME_CONST . '/profiles/' . rawurlencode($current_profile_image_filename);
}

// Cargar secciones para el formulario de crear admin
$all_db_sections_for_form = []; 
// MODIFICADO: Quitar 'employees' de la exclusión para que aparezca en la lista de permisos asignables
$result_sections_form = $mysqli->query("SELECT section_code, section_name, parent_code FROM sidebar_sections WHERE section_code != 'profile' ORDER BY display_order ASC, section_name ASC");
if ($result_sections_form) {
    while ($row_section_form = $result_sections_form->fetch_assoc()) { 
        $all_db_sections_for_form[] = $row_section_form;
    }
    $result_sections_form->free();
}
// Pre-procesar secciones para agrupar padres e hijos para el formulario de creación
$main_sections_map_form = [];
$sub_sections_map_form = [];
foreach ($all_db_sections_for_form as $section_form) {
    if ($section_form['parent_code'] === null) {
        $main_sections_map_form[$section_form['section_code']] = $section_form;
    } else {
        if (!isset($sub_sections_map_form[$section_form['parent_code']])) {
            $sub_sections_map_form[$section_form['parent_code']] = [];
        }
        $sub_sections_map_form[$section_form['parent_code']][] = $section_form;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil y Gestión de Usuarios - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .profile-picture-container { width: 150px; height: 150px; border-radius: 50%; overflow: hidden; margin: 0 auto 1.5rem auto; border: 4px solid #dee2e6; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .profile-picture-container img { width: 100%; height: 100%; object-fit: cover; }
        .form-label i { margin-right: 8px; }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
        .admin-list-avatar { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; margin-right: 10px;}
        .permissions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; }
        .permission-group { margin-bottom: 1rem; padding: 0.75rem; border: 1px solid #eee; border-radius: .25rem; background-color:#fff;}
        .permission-group-title { font-weight: bold; margin-bottom: 0.5rem; font-size: 0.95rem; color: #0d6efd; }
        .permission-group-title .form-check-label { cursor: pointer; }
        .permission-item .form-check-label { font-size: 0.9rem; cursor: pointer; }
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .chevron{transition:transform .2s;}
        .nav-link[aria-expanded="true"] .chevron{transform:rotate(180deg);}
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
if (isMobileDevice()) { // Asume que isMobileDevice() está en config.php
    require_once ($path_to_root ?: './') . '_sidebarmovil.php';

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
                        <h2 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-user-edit me-3 text-info"></i>Mi Perfil y Gestión de Usuarios
                        </h2>
                    </div>

                    <?php if (!empty($errors)): ?> <div class="alert alert-danger alert-dismissible fade show" role="alert"><h5 class="alert-heading mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Error:</h5><ul class="mb-0 ps-4"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div> <?php endif; ?>
                    <?php if ($success_message): ?> <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div> <?php endif; ?>
                    <?php if ($delete_admin_message): ?> <div class="alert alert-<?php echo $delete_admin_status_type; ?> alert-dismissible fade show" role="alert"><i class="fas fa-<?php echo $delete_admin_status_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i><?php echo htmlspecialchars($delete_admin_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div> <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-5 border-end pe-lg-4 mb-4 mb-lg-0">
                          
                            <div class="text-center mb-4">
                                <div class="profile-picture-container"><img src="<?php echo $page_profile_image_path; ?>?t=<?php echo time(); ?>" alt="Foto de Perfil Actual"></div>
                                <form method="POST" enctype="multipart/form-data" class="mt-2" action="profile.php">
                                    <div class="mb-3"><label for="profile_image_file_page" class="form-label small">Cambiar foto:</label><input class="form-control" type="file" id="profile_image_file_page" name="profile_image_file" accept="image/png, image/jpeg, image/gif"></div>
                                    <button type="submit" name="update_profile_picture" value="1" class="btn btn-primary btn-sm"><i class="fas fa-camera me-1"></i>Actualizar Foto</button>
                                </form>
                            </div>
                            <h5>Información del Usuario</h5>
                            <table class="table table-sm mt-2">
                                <tbody>
                                    <tr><th scope="row" style="width: 120px;"><small><i class="fas fa-user me-2 text-secondary"></i>Nombre:</small></th><td><small><?php echo htmlspecialchars($current_name); ?></small></td></tr>
                                    <tr><th scope="row"><small><i class="fas fa-envelope me-2 text-secondary"></i>Email:</small></th><td><small><?php echo htmlspecialchars($current_email); ?></small></td></tr>
                                </tbody>
                            </table>
                            <div class="card shadow-sm mt-4">
                                <div class="card-body p-3">
                                    <h5 class="card-title mb-3"><i class="fas fa-key me-2 text-warning"></i>Cambiar Mi Contraseña</h5>
                                    <?php if (!empty($password_errors)): ?><div class="alert alert-danger py-2 small" role="alert"><ul class="mb-0 ps-3"><?php foreach ($password_errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                                    <?php if ($password_success_message): ?><div class="alert alert-success py-2 small" role="alert"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($password_success_message); ?></div><?php endif; ?>
                                    <form method="POST" action="profile.php">
                                        <div class="mb-2"><label for="current_password" class="form-label small fw-bold">Contraseña Actual</label><input type="password" class="form-control form-control-sm" id="current_password" name="current_password" required></div>
                                        <div class="mb-2"><label for="new_password" class="form-label small fw-bold">Nueva Contraseña</label><input type="password" class="form-control form-control-sm" id="new_password" name="new_password" required><div class="form-text small">Mínimo 8 caracteres.</div></div>
                                        <div class="mb-3"><label for="confirm_new_password" class="form-label small fw-bold">Confirmar Nueva</label><input type="password" class="form-control form-control-sm" id="confirm_new_password" name="confirm_new_password" required></div>
                                        <button type="submit" name="change_password_submit" value="1" class="btn btn-warning btn-sm w-100"><i class="fas fa-save me-2"></i>Cambiar Mi Contraseña</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7 ps-lg-4">
                            <h4><i class="fas fa-users-cog me-2"></i>Gestionar Usuarios</h4>
                            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin'): ?>
                            <div class="card shadow-sm mt-3 mb-4">
                                <div class="card-header bg-light"><h5 class="mb-0"><i class="fas fa-user-plus me-2 text-success"></i>Crear Nuevo Usuario/Empleado</h5></div>
                                <div class="card-body p-3">
                                    <?php if (!empty($new_admin_errors)): ?><div class="alert alert-danger py-2 small" role="alert"><ul class="mb-0 ps-3"><?php foreach ($new_admin_errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                                    <?php if ($new_admin_success_message): ?><div class="alert alert-success py-2 small" role="alert"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($new_admin_success_message); ?></div><?php endif; ?>
                                    <form method="POST" action="profile.php">
                                        <div class="mb-2"><label for="new_admin_name" class="form-label small fw-bold">Nombre Completo</label><input type="text" class="form-control form-control-sm" id="new_admin_name" name="new_admin_name" required value="<?php echo htmlspecialchars($new_admin_name_val); ?>"></div>
                                        <div class="mb-2"><label for="new_admin_email" class="form-label small fw-bold">Email de Acceso</label><input type="email" class="form-control form-control-sm" id="new_admin_email" name="new_admin_email" required value="<?php echo htmlspecialchars($new_admin_email_val); ?>"></div>
                                        <div class="mb-2"><label for="new_admin_password" class="form-label small fw-bold">Contraseña</label><input type="password" class="form-control form-control-sm" id="new_admin_password" name="new_admin_password" required><div class="form-text small">Mínimo 8 caracteres.</div></div>
                                        <div class="mb-3"><label for="new_admin_confirm_password" class="form-label small fw-bold">Confirmar Contraseña</label><input type="password" class="form-control form-control-sm" id="new_admin_confirm_password" name="new_admin_confirm_password" required></div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Rol del Nuevo Usuario:</label>
                                            <select name="new_admin_role" class="form-select form-select-sm">
                                                <option value="employee" <?php if($new_admin_role_val == 'employee') echo 'selected';?>>Empleado</option>
                                                <option value="admin" <?php if($new_admin_role_val == 'admin') echo 'selected';?>>Administrador</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Permisos de Acceso:</label>
                                            <div class="permissions-grid p-3 border rounded bg-light" style="max-height: 250px; overflow-y: auto;">
                                                <?php foreach ($main_sections_map_form as $main_code => $main_section): ?>
                                                    <div class="permission-group">
                                                        <div class="form-check form-switch mb-1 permission-group-title">
                                                            <input class="form-check-input" type="checkbox" role="switch" 
                                                                   id="perm_new_<?php echo htmlspecialchars($main_section['section_code']); ?>" 
                                                                   name="new_admin_permissions[]" 
                                                                   value="<?php echo htmlspecialchars($main_section['section_code']); ?>"
                                                                   <?php if (in_array($main_section['section_code'], $new_admin_posted_permissions)) echo 'checked'; ?>>
                                                            <label class="form-check-label" for="perm_new_<?php echo htmlspecialchars($main_section['section_code']); ?>">
                                                                <?php echo htmlspecialchars($main_section['section_name']); ?>
                                                            </label>
                                                        </div>
                                                        <?php if (isset($sub_sections_map_form[$main_code]) && !empty($sub_sections_map_form[$main_code])): ?>
                                                            <div class="ps-4">
                                                                <?php foreach ($sub_sections_map_form[$main_code] as $sub_section): ?>
                                                                <div class="form-check form-switch mb-1 permission-item">
                                                                     <input class="form-check-input" type="checkbox" role="switch" 
                                                                           id="perm_new_<?php echo htmlspecialchars($sub_section['section_code']); ?>" 
                                                                           name="new_admin_permissions[]" 
                                                                           value="<?php echo htmlspecialchars($sub_section['section_code']); ?>"
                                                                           <?php if (in_array($sub_section['section_code'], $new_admin_posted_permissions)) echo 'checked'; ?>>
                                                                    <label class="form-check-label small" for="perm_new_<?php echo htmlspecialchars($sub_section['section_code']); ?>">
                                                                        <?php echo htmlspecialchars($sub_section['section_name']); ?>
                                                                    </label>
                                                                </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <button type="submit" name="create_admin_submit" value="1" class="btn btn-success btn-sm w-100"><i class="fas fa-user-plus me-2"></i>Crear Usuario</button>
                                    </form>
                                </div>
                            </div>
                            <h5 class="mt-4">Usuarios Existentes</h5>
                            <?php if (empty($existing_admins)): ?><p class="text-muted">No hay otros usuarios registrados.</p>
                            <?php else: ?>
                                <ul class="list-group mt-2 shadow-sm">
                                    <?php foreach ($existing_admins as $admin): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php
                                                $admin_list_pic_path_default = ($path_to_root ?: './') . 'assets/img/default-avatar.png';
                                                $admin_list_pic_path = $admin_list_pic_path_default;
                                                if (!empty($admin['profile_image']) && defined('UPLOAD_DIR_NAME_CONST') && file_exists(UPLOAD_DIR . 'profiles/' . $admin['profile_image'])) {
                                                    $admin_list_pic_path = ($path_to_root ?: './') . UPLOAD_DIR_NAME_CONST . '/profiles/' . rawurlencode($admin['profile_image']);
                                                }
                                                ?>
                                                <img src="<?php echo $admin_list_pic_path; ?>?t=<?php echo time(); ?>" alt="Avatar de <?php echo htmlspecialchars($admin['name']); ?>" class="admin-list-avatar">
                                                <strong><?php echo htmlspecialchars($admin['name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($admin['email']); ?> (Rol: <?php echo ucfirst($admin['role']); ?>)</small>
                                            </div>
                                            <div>
                                                <?php if ($admin['id'] == $current_admin_id): ?>
                                                    <span class="badge bg-primary rounded-pill">Tú</span>
                                                <?php elseif ($admin['id'] == SUPER_ADMIN_MAIN_ID): ?>
                                                    <span class="badge bg-secondary rounded-pill">Principal</span>
                                                <?php else: ?>
                                                    <a href="<?php echo $path_to_root; ?>employees/edit.php?id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-outline-warning" title="Editar Permisos/Datos"><i class="fas fa-user-shield"></i></a>
                                                    <a href="<?php echo $path_to_root; ?>actions/delete_admin.php?id=<?php echo $admin['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('¿Está seguro de que desea eliminar al administrador \'<?php echo htmlspecialchars(addslashes($admin['name'])); ?>\'? Esta acción es irreversible.');"
                                                       title="Eliminar Administrador"><i class="fas fa-trash"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                             <?php else: ?>
                                <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>La gestión de otros usuarios solo está disponible para el rol Superadmin.</div>
                            <?php endif; ?>
                        </div>
                    </div>
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
                        if (bsAlert) { bsAlert.close(); }
                    }
                }, 7000); 
            });
        });
    </script>
</body>
</html>
