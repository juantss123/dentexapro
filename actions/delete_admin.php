<?php
session_start();
// Solo el superadmin puede ejecutar esta acción
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    // Redirigir si no es superadmin o no hay sesión
    // Es importante decidir a dónde redirigir. ../employees/index.php es una opción si el error es de permisos.
    // Si es por no estar logueado, ../login.php.
    // Para simplificar, redirigimos al dashboard con un mensaje genérico.
    header('Location: ../dashboard.php?status=error&msg=' . urlencode('Acceso no autorizado para esta acción.'));
    exit;
}
require_once '../config.php'; 

$admin_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$current_logged_in_admin_id = $_SESSION['admin_id'];

$status = 'error'; 
$message_code = ''; 

// Definir el ID del superadmin principal que NO se puede eliminar
if (!defined('SUPER_ADMIN_MAIN_ID')) { // Podrías tener esto en config.php
    define('SUPER_ADMIN_MAIN_ID', 1); 
}

if (!$admin_id_to_delete) {
    $message_code = 'invalid_id_to_delete';
    header('Location: ../employees/index.php?status_user_action=' . $status . '&msg_user_action=' . $message_code);
    exit;
}

if ($admin_id_to_delete == $current_logged_in_admin_id) {
    $message_code = 'cannot_delete_self_via_list'; // No se puede eliminar a sí mismo desde la lista de empleados
    header('Location: ../employees/index.php?status_user_action=' . $status . '&msg_user_action=' . $message_code);
    exit;
}

if ($admin_id_to_delete == SUPER_ADMIN_MAIN_ID) {
    $message_code = 'cannot_delete_main_superadmin'; // No se puede eliminar al superadmin principal
    header('Location: ../employees/index.php?status_user_action=' . $status . '&msg_user_action=' . $message_code);
    exit;
}

// Verificar que no se esté eliminando al único administrador restante (si solo hay 2 y se intenta borrar el otro no principal)
// Esta lógica es más para el caso de que solo quede un superadmin y un admin/empleado.
// Si solo queda el superadmin, la condición de arriba ($admin_id_to_delete == SUPER_ADMIN_MAIN_ID) ya lo protege.
$count_stmt = $mysqli->prepare("SELECT COUNT(*) FROM admin_users");
$total_admins = 1; // Default a 1 para evitar problemas si la consulta falla
if ($count_stmt) {
    $count_stmt->execute();
    $count_stmt->bind_result($total_admins_db);
    $count_stmt->fetch();
    $total_admins = $total_admins_db;
    $count_stmt->close();
}

if ($total_admins <= 1) { 
    $message_code = 'cannot_delete_last_user'; 
    header('Location: ../employees/index.php?status_user_action=' . $status . '&msg_user_action=' . $message_code);
    exit;
}

// Proceder con la eliminación
$mysqli->begin_transaction();
try {
    // Primero, obtener el nombre del archivo de imagen de perfil para eliminarlo del servidor
    $img_stmt = $mysqli->prepare("SELECT profile_image FROM admin_users WHERE id = ?");
    $profile_image_to_delete = null;
    if ($img_stmt) {
        $img_stmt->bind_param("i", $admin_id_to_delete);
        $img_stmt->execute();
        $img_stmt->bind_result($profile_image_to_delete);
        $img_stmt->fetch();
        $img_stmt->close();
    } else {
        throw new Exception("Error al preparar consulta para obtener imagen de perfil: " . $mysqli->error);
    }

    // Eliminar el registro de la base de datos
    $delete_stmt = $mysqli->prepare("DELETE FROM admin_users WHERE id = ?");
    if (!$delete_stmt) {
        throw new Exception("Error al preparar la consulta de eliminación: " . $mysqli->error);
    }
    $delete_stmt->bind_param("i", $admin_id_to_delete);

    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            // Si se eliminó de la BD, intentar eliminar la imagen de perfil
            if (!empty($profile_image_to_delete) && defined('UPLOAD_DIR')) {
                $profile_image_path = UPLOAD_DIR . 'profiles/' . $profile_image_to_delete;
                if (file_exists($profile_image_path)) {
                    @unlink($profile_image_path); // @ para suprimir errores si el archivo no se puede borrar por permisos, etc.
                }
            }
            $mysqli->commit();
            $status = 'success';
            $message_code = 'user_deleted_successfully';
        } else {
            $mysqli->rollback(); // No se afectaron filas, el usuario no existía
            $message_code = 'user_not_found_for_delete';
        }
    } else {
        throw new Exception("Error al ejecutar la eliminación: " . $delete_stmt->error);
    }
    $delete_stmt->close();

} catch (Exception $e) {
    $mysqli->rollback();
    $message_code = 'db_transaction_error';
    error_log("Error al eliminar usuario (ID: {$admin_id_to_delete}): " . $e->getMessage());
}

// Construir el mensaje para la URL
$final_message = '';
switch ($message_code) {
    case 'user_deleted_successfully': $final_message = 'Usuario eliminado con éxito.'; break;
    case 'invalid_id_to_delete': $final_message = 'ID de usuario no válido.'; break;
    case 'cannot_delete_self_via_list': $final_message = 'No puedes eliminar tu propia cuenta desde esta lista.'; break;
    case 'cannot_delete_main_superadmin': $final_message = 'No se puede eliminar al administrador principal del sistema.'; break;
    case 'cannot_delete_last_user': $final_message = 'No se puede eliminar el último usuario del sistema.'; break;
    case 'user_not_found_for_delete': $final_message = 'Usuario no encontrado o ya fue eliminado.'; break;
    case 'db_transaction_error': $final_message = 'Ocurrió un error durante la transacción. Intente de nuevo.'; break;
    default: $final_message = 'Ocurrió un error desconocido.'; break;
}

header('Location: ../employees/index.php?status_user_action=' . $status . '&msg_user_action=' . urlencode($final_message));
exit;
?>
