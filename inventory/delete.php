<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../config.php';

$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$status = 'error';
$message = 'ID de insumo no válido o no proporcionado.';

if ($item_id) {
    $stmt = $mysqli->prepare("DELETE FROM inventory_items WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $item_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $status = 'success_delete';
                $message = 'Insumo eliminado con éxito.';
            } else {
                $message = 'Insumo no encontrado o ya eliminado.';
            }
        } else {
            $message = 'Error al eliminar el insumo: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = 'Error al preparar la consulta de eliminación: ' . $mysqli->error;
    }
}

header('Location: index.php?status=' . $status . '&msg=' . urlencode($message));
exit;
?>