<?php
session_start();
if (!isset($_SESSION['admin_id']) || (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] !== 'superadmin')) {
    header('Location: ../dashboard.php?status=error&msg=' . urlencode('Acceso no autorizado.'));
    exit;
}
require_once '../config.php';

$path_to_root = '../'; 

$user_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!defined('SUPER_ADMIN_MAIN_ID')) {
    define('SUPER_ADMIN_MAIN_ID', 1); 
}

if (!$user_id_to_edit) {
    header('Location: index.php?status_user_action=error&msg_user_action=' . urlencode('ID de usuario no válido.'));
    exit;
}

if ($user_id_to_edit == SUPER_ADMIN_MAIN_ID || $user_id_to_edit == $_SESSION['admin_id']) {
     header('Location: index.php?status_user_action=error&msg_user_action=' . urlencode('Este usuario no se puede editar desde esta interfaz.'));
    exit;
}

$errors = [];
$name_val = ''; 
$email_val = ''; 
$role_val = ''; 
$selected_permissions_val = [];
$user_data_from_db = null; 

$stmt_load_user = $mysqli->prepare("SELECT name, email, role, permissions FROM admin_users WHERE id = ?");
if ($stmt_load_user) {
    $stmt_load_user->bind_param("i", $user_id_to_edit);
    $stmt_load_user->execute();
    $result_load_user = $stmt_load_user->get_result();
    if ($user_data_from_db = $result_load_user->fetch_assoc()) {
        $name_val = $user_data_from_db['name'];
        $email_val = $user_data_from_db['email'];
        $role_val = $user_data_from_db['role'];
        $current_permissions_json = $user_data_from_db['permissions'];
        $decoded_perms = json_decode($current_permissions_json, true);
        $selected_permissions_val = (json_last_error() === JSON_ERROR_NONE && is_array($decoded_perms)) ? $decoded_perms : [];
    } else {
        header('Location: index.php?status_user_action=error&msg_user_action=' . urlencode('Usuario no encontrado.'));
        exit;
    }
    $stmt_load_user->close();
} else {
    die("Error al preparar la consulta de carga de usuario: " . $mysqli->error);
}

// Obtener todas las secciones disponibles para asignar permisos
$all_db_sections = [];
// MODIFICADO: La consulta ahora NO excluye 'employees'. Solo excluye 'profile'.
$result_sections = $mysqli->query("SELECT section_code, section_name, parent_code FROM sidebar_sections WHERE section_code != 'profile' ORDER BY display_order ASC, section_name ASC");
if ($result_sections) {
    while ($row_s = $result_sections->fetch_assoc()) { 
        $all_db_sections[] = $row_s;
    }
    $result_sections->free();
}

$main_sections_map = [];
$sub_sections_map = [];
foreach ($all_db_sections as $section) {
    if ($section['parent_code'] === null) {
        $main_sections_map[$section['section_code']] = $section;
    } else {
        if (!isset($sub_sections_map[$section['parent_code']])) {
            $sub_sections_map[$section['parent_code']] = [];
        }
        $sub_sections_map[$section['parent_code']][] = $section;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name_val_post = trim($_POST['name'] ?? $name_val);
    $email_val_post = trim($_POST['email'] ?? $email_val);
    $new_password = $_POST['new_password'] ?? ''; 
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';
    $role_val_post = $_POST['role'] ?? $role_val;
    $selected_permissions_post = $_POST['permissions'] ?? [];

    if (empty($name_val_post)) $errors[] = "El nombre completo es obligatorio.";
    if (empty($email_val_post)) $errors[] = "El email es obligatorio.";
    elseif (!filter_var($email_val_post, FILTER_VALIDATE_EMAIL)) $errors[] = "El formato del email no es válido.";
    
    if (!empty($new_password)) { 
        if (strlen($new_password) < 8) {
            $errors[] = "La nueva contraseña debe tener al menos 8 caracteres.";
        } elseif ($new_password !== $confirm_new_password) {
            $errors[] = "Las nuevas contraseñas no coinciden.";
        }
    }
    if (!in_array($role_val_post, ['admin', 'employee'])) {
        $errors[] = "Rol inválido seleccionado.";
    }

    if (empty($errors) && !empty($email_val_post) && $email_val_post !== $user_data_from_db['email']) {
        $stmt_check_email_edit = $mysqli->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
        if ($stmt_check_email_edit) {
            $stmt_check_email_edit->bind_param("si", $email_val_post, $user_id_to_edit);
            $stmt_check_email_edit->execute();
            $stmt_check_email_edit->store_result();
            if ($stmt_check_email_edit->num_rows > 0) {
                $errors[] = "El email ingresado ya está registrado para otro usuario.";
            }
            $stmt_check_email_edit->close();
        } else {
             $errors[] = "Error al verificar el email: " . $mysqli->error;
        }
    }

    $valid_permissions = [];
    foreach ($selected_permissions_post as $perm_code) {
        $found = false;
        foreach ($all_db_sections as $section) { if ($section['section_code'] === $perm_code) { $found = true; break; } }
        if ($found) { $valid_permissions[] = $perm_code; }
    }
    $permissions_json_to_save = json_encode($valid_permissions);
    
    if (!empty($errors)) {
        $name_val = $name_val_post;
        $email_val = $email_val_post;
        $role_val = $role_val_post;
        $selected_permissions_val = $valid_permissions;
    }

    if (empty($errors)) {
        $sql_update_user = "UPDATE admin_users SET name = ?, email = ?, role = ?, permissions = ?";
        $types_update = "ssss";
        $params_update = [$name_val_post, $email_val_post, $role_val_post, $permissions_json_to_save];

        if (!empty($new_password)) {
            $hashed_password_new = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_update_user .= ", password = ?";
            $types_update .= "s";
            $params_update[] = $hashed_password_new;
        }
        $sql_update_user .= " WHERE id = ?";
        $types_update .= "i";
        $params_update[] = $user_id_to_edit;

        $stmt_update = $mysqli->prepare($sql_update_user);
        if ($stmt_update) {
            $stmt_update->bind_param($types_update, ...$params_update);
            if ($stmt_update->execute()) {
                header('Location: index.php?status_user_action=success&msg_user_action=' . urlencode('Usuario/empleado "' . htmlspecialchars($name_val_post) . '" actualizado con éxito!'));
                exit;
            } else {
                $errors[] = 'Error al actualizar el usuario/empleado: ' . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors[] = 'Error al preparar la consulta de actualización: ' . $mysqli->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Empleado/Usuario - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body{overflow-x:hidden; background-color: #f8f9fa;}
        #sidebar{min-height:100vh;}
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .form-label i { margin-right: 5px; }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
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
                <div class="card-header bg-warning text-dark">
                    <h3 class="mb-0 d-flex align-items-center">
                        <i class="fas fa-user-edit me-2"></i>Editar Usuario/Empleado: <?php echo htmlspecialchars($name_val); ?>
                    </h3>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Errores al actualizar:</h5>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): echo '<li>' . htmlspecialchars($error) . '</li>'; endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="edit.php?id=<?php echo $user_id_to_edit; ?>">
                        <h5 class="mb-3 text-primary"><i class="fas fa-id-badge me-2"></i>Información del Usuario</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="name" class="form-label fw-bold">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="name" name="name" value="<?php echo htmlspecialchars($name_val); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-bold">Email (Login) <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-lg" id="email" name="email" value="<?php echo htmlspecialchars($email_val); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="new_password" class="form-label fw-bold">Nueva Contraseña (Opcional)</label>
                                <input type="password" class="form-control form-control-lg" id="new_password" name="new_password" placeholder="Dejar vacío para no cambiar">
                                <div class="form-text">Mínimo 8 caracteres si se establece.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_new_password" class="form-label fw-bold">Confirmar Nueva Contraseña</label>
                                <input type="password" class="form-control form-control-lg" id="confirm_new_password" name="confirm_new_password" placeholder="Requerido si establece nueva contraseña">
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label fw-bold">Rol <span class="text-danger">*</span></label>
                                <select name="role" id="role" class="form-select form-select-lg" required>
                                    <option value="employee" <?php if($role_val == 'employee') echo 'selected'; ?>>Empleado</option>
                                    <option value="admin" <?php if($role_val == 'admin') echo 'selected'; ?>>Administrador</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4">
                        <h5 class="mb-3 text-primary"><i class="fas fa-shield-alt me-2"></i>Permisos de Acceso a Secciones</h5>
                        <div class="permissions-grid p-3 border rounded bg-light">
                           <?php foreach ($main_sections_map as $main_code => $main_section): ?>
                                <div class="permission-group">
                                    <div class="form-check form-switch mb-1 permission-group-title">
                                        <input class="form-check-input" type="checkbox" role="switch" 
                                               id="perm_edit_<?php echo htmlspecialchars($main_section['section_code']); ?>" 
                                               name="permissions[]" 
                                               value="<?php echo htmlspecialchars($main_section['section_code']); ?>"
                                               <?php if (in_array($main_section['section_code'], $selected_permissions_val)) echo 'checked'; ?>>
                                        <label class="form-check-label" for="perm_edit_<?php echo htmlspecialchars($main_section['section_code']); ?>">
                                            <?php echo htmlspecialchars($main_section['section_name']); ?>
                                        </label>
                                    </div>
                                    <?php if (isset($sub_sections_map[$main_code]) && !empty($sub_sections_map[$main_code])): ?>
                                        <div class="ps-4">
                                            <?php foreach ($sub_sections_map[$main_code] as $sub_section): ?>
                                            <div class="form-check form-switch mb-1 permission-item">
                                                 <input class="form-check-input" type="checkbox" role="switch" 
                                                       id="perm_edit_<?php echo htmlspecialchars($sub_section['section_code']); ?>" 
                                                       name="permissions[]" 
                                                       value="<?php echo htmlspecialchars($sub_section['section_code']); ?>"
                                                       <?php if (in_array($sub_section['section_code'], $selected_permissions_val)) echo 'checked'; ?>>
                                                <label class="form-check-label small" for="perm_edit_<?php echo htmlspecialchars($sub_section['section_code']); ?>">
                                                    <?php echo htmlspecialchars($sub_section['section_name']); ?>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>Actualizar Usuario</button>
                            <a href="index.php" class="btn btn-outline-secondary btn-lg"><i class="fas fa-times me-2"></i>Cancelar</a>
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
</body>
</html>
