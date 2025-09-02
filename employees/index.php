<?php
session_start();
// Solo el superadmin puede acceder a esta página
if (!isset($_SESSION['admin_id']) || (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] !== 'superadmin')) {
    header('Location: ../dashboard.php?status=error&msg=' . urlencode('Acceso no autorizado a gestión de empleados.'));
    exit;
}
require_once '../config.php';

$path_to_root = '../'; 

$users = [];
$result = $mysqli->query("SELECT id, name, email, role, profile_image FROM admin_users ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
}

$action_message = ''; 
$action_status_type = ''; 

if (isset($_GET['status_user_action']) && isset($_GET['msg_user_action'])) {
    $action_status_type = (strpos($_GET['status_user_action'], 'success') !== false) ? 'success' : 'danger';
    $action_message = urldecode($_GET['msg_user_action']);
} elseif (isset($_GET['status_create']) && $_GET['status_create'] == 'success') {
    $action_status_type = 'success';
    $action_message = '¡Nuevo usuario/empleado creado con éxito!';
} elseif (isset($_GET['status_update']) && $_GET['status_update'] == 'success') {
    $action_status_type = 'success';
    $action_message = '¡Usuario/empleado actualizado con éxito!';
}

// ID del superadmin principal que no se puede eliminar (generalmente ID 1)
if (!defined('SUPER_ADMIN_MAIN_ID')) { // Definir solo si no está ya definida (ej. en config.php)
    define('SUPER_ADMIN_MAIN_ID', 1);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Empleados/Usuarios - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body{overflow-x:hidden; background-color: #f8f9fa;}
        #sidebar{min-height:100vh;}
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .table th i { margin-right: 5px; }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
        .user-list-avatar { width: 45px; height: 45px; object-fit: cover; border-radius: 50%; margin-right: 10px; border: 1px solid #ccc;}
        .actions-cell .btn { margin-right: 0.25rem; margin-bottom: 0.25rem;}
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
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <h2 class="mb-0 d-flex align-items-center"><i class="fas fa-users-cog me-3"></i>Gestionar Empleados y Usuarios</h2>
                        <a href="create.php" class="btn btn-primary btn-lg"><i class="fas fa-user-plus me-2"></i> Nuevo Usuario/Empleado</a>
                    </div>

                    <?php if ($action_message): ?>
                        <div class="alert alert-<?php echo $action_status_type; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $action_status_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($action_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th class="text-center">Avatar</th>
                                    <th><i class="fas fa-user"></i>Nombre</th>
                                    <th><i class="fas fa-at"></i>Email (Login)</th>
                                    <th><i class="fas fa-user-tag"></i>Rol</th>
                                    <th style="min-width: 120px;"><i class="fas fa-toolbox"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): 
                                    $user_pic = $path_to_root . 'assets/img/default-avatar.png'; // Ruta por defecto
                                    if (!empty($user['profile_image']) && defined('UPLOAD_DIR_NAME_CONST') && file_exists(PROJECT_ROOT . '/' . UPLOAD_DIR_NAME_CONST . '/profiles/' . $user['profile_image'])) {
                                        // Ruta directa al archivo
                                        $user_pic = $path_to_root . UPLOAD_DIR_NAME_CONST . '/profiles/' . rawurlencode($user['profile_image']);
                                    }
                                ?>
                                    <tr>
                                        <td class="text-center"><img src="<?php echo $user_pic; ?>?t=<?php echo time();?>" alt="Avatar de <?php echo htmlspecialchars($user['name']); ?>" class="user-list-avatar"></td>
                                        <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php 
                                            $role_display = ucfirst($user['role']);
                                            $role_badge = 'bg-secondary'; 
                                            if ($user['role'] == 'superadmin') $role_badge = 'bg-danger';
                                            else if ($user['role'] == 'admin') $role_badge = 'bg-info text-dark';
                                            else if ($user['role'] == 'employee') $role_badge = 'bg-light text-dark border';
                                            echo "<span class='badge {$role_badge}'>{$role_display}</span>";
                                            ?>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Editar Usuario y Permisos"><i class="fas fa-user-edit"></i></a>
                                            <?php 
                                            $can_delete = true;
                                            $delete_title = "Eliminar Usuario";
                                            if ($user['id'] == SUPER_ADMIN_MAIN_ID) {
                                                $can_delete = false;
                                                $delete_title = "No se puede eliminar al Super Administrador principal.";
                                            } elseif ($user['id'] == $_SESSION['admin_id']) {
                                                $can_delete = false;
                                                $delete_title = "No puedes eliminar tu propia cuenta.";
                                            }
                                            ?>
                                            <?php if ($can_delete): ?>
                                                <a href="<?php echo $path_to_root; ?>actions/delete_admin.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="<?php echo $delete_title; ?>" onclick="return confirm('¿Está seguro de que desea eliminar al usuario \'<?php echo htmlspecialchars(addslashes($user['name'])); ?>\'? Esta acción es irreversible.');"><i class="fas fa-trash"></i></a>
                                            <?php else: ?>
                                                 <button class="btn btn-sm btn-danger" disabled title="<?php echo $delete_title; ?>"><i class="fas fa-trash"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">No hay usuarios/empleados registrados.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
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
