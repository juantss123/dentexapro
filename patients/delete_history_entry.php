<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$history_id = filter_input(INPUT_POST, 'history_id_to_delete', FILTER_VALIDATE_INT);
$patient_id = filter_input(INPUT_POST, 'patient_id_for_redirect', FILTER_VALIDATE_INT);

if (!$history_id || !$patient_id) {
    header('Location: index.php?status=error&msg=invalid_request');
    exit;
}

$path_to_root = '../';
$upload_dir = $path_to_root . 'uploads/patient_history/';

// Iniciar transacción para asegurar que todo se borre correctamente o no se borre nada.
$mysqli->begin_transaction();

try {
    // 1. Obtener los nombres de todos los archivos asociados al registro para poder borrarlos del servidor después.
    $stmt_get_files = $mysqli->prepare("SELECT file_name FROM medical_history_attachments WHERE medical_history_id = ?");
    $stmt_get_files->bind_param("i", $history_id);
    $stmt_get_files->execute();
    $files_result = $stmt_get_files->get_result();
    $files_to_delete = [];
    while ($row = $files_result->fetch_assoc()) {
        $files_to_delete[] = $row['file_name'];
    }
    $stmt_get_files->close();

    // 2. Eliminar todas las referencias a los archivos de la tabla `medical_history_attachments`.
    $stmt_delete_attachments = $mysqli->prepare("DELETE FROM medical_history_attachments WHERE medical_history_id = ?");
    $stmt_delete_attachments->bind_param("i", $history_id);
    $stmt_delete_attachments->execute();
    $stmt_delete_attachments->close();

    // 3. Eliminar el registro principal de la tabla `medical_history`.
    $stmt_delete_history = $mysqli->prepare("DELETE FROM medical_history WHERE id = ?");
    $stmt_delete_history->bind_param("i", $history_id);
    $stmt_delete_history->execute();
    $deleted_rows = $stmt_delete_history->affected_rows;
    $stmt_delete_history->close();

    if ($deleted_rows > 0) {
        // Si la base de datos se actualizó correctamente, confirmar la transacción.
        $mysqli->commit();

        // Y ahora, borrar los archivos físicos del servidor.
        foreach ($files_to_delete as $filename) {
            $file_path = $upload_dir . $filename;
            if (!empty($filename) && file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Redirigir con mensaje de éxito.
        header('Location: history.php?patient_id=' . $patient_id . '&status=success_history_deleted');
        exit;

    } else {
        // Si no se borró ninguna fila (ej. el registro ya no existía), revertir por seguridad.
        $mysqli->rollback();
        header('Location: history.php?patient_id=' . $patient_id . '&status=error_history_delete&msg=' . urlencode('El registro no existía o no se pudo eliminar.'));
        exit;
    }

} catch (Exception $e) {
    // Si hay cualquier error en el proceso, revertir la transacción para no dejar datos corruptos.
    $mysqli->rollback();
    error_log("Error al eliminar historial (ID: $history_id): " . $e->getMessage());
    header('Location: history.php?patient_id=' . $patient_id . '&status=error_history_delete&msg=' . urlencode($e->getMessage()));
    exit;
}
?>