<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']); // O redirigir a login
    exit;
}
require_once '../../config.php'; // Ajustar ruta a config.php

$response_status = 'error';
$response_message = 'Solicitud no válida o ID de presupuesto faltante.';

// Verificar permisos (puedes usar el permiso 'estimates' o uno más específico si lo creas)
$can_delete = false;
if (isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] === 'superadmin' || $_SESSION['admin_role'] === 'admin')) {
    $can_delete = true; 
} elseif (user_has_permission('estimates', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    // Podrías tener un permiso más granular como 'estimates_delete'
    $can_delete = true;
}

if (!$can_delete) {
    $response_message = 'No tiene permiso para eliminar presupuestos.';
    header('Location: ../index.php?status=' . $response_status . '&msg=' . urlencode($response_message));
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['estimate_id'])) {
    $estimate_id_to_delete = filter_input(INPUT_POST, 'estimate_id', FILTER_VALIDATE_INT);

    if ($estimate_id_to_delete && $estimate_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            // 1. Obtener el nombre del archivo PDF asociado (si existe) para eliminarlo del servidor
            $pdf_filename_relative = null;
            $stmt_get_pdf = $mysqli->prepare("SELECT pdf_filename FROM estimates WHERE id = ?");
            if (!$stmt_get_pdf) throw new Exception("Error preparando consulta para obtener PDF: " . $mysqli->error);
            
            $stmt_get_pdf->bind_param("i", $estimate_id_to_delete);
            $stmt_get_pdf->execute();
            $result_pdf = $stmt_get_pdf->get_result();
            if ($row_pdf = $result_pdf->fetch_assoc()) {
                $pdf_filename_relative = $row_pdf['pdf_filename'];
            }
            $stmt_get_pdf->close();

            // 2. Eliminar los ítems asociados al presupuesto
            $stmt_delete_items = $mysqli->prepare("DELETE FROM estimate_items WHERE estimate_id = ?");
            if (!$stmt_delete_items) throw new Exception("Error preparando borrado de ítems: " . $mysqli->error);
            
            $stmt_delete_items->bind_param("i", $estimate_id_to_delete);
            if (!$stmt_delete_items->execute()) throw new Exception("Error borrando ítems del presupuesto: " . $stmt_delete_items->error);
            $stmt_delete_items->close();

            // 3. Eliminar el registro principal del presupuesto
            $stmt_delete_estimate = $mysqli->prepare("DELETE FROM estimates WHERE id = ?");
            if (!$stmt_delete_estimate) throw new Exception("Error preparando borrado de presupuesto: " . $mysqli->error);
            
            $stmt_delete_estimate->bind_param("i", $estimate_id_to_delete);
            if (!$stmt_delete_estimate->execute()) throw new Exception("Error borrando el presupuesto principal: " . $stmt_delete_estimate->error);
            
            if ($stmt_delete_estimate->affected_rows > 0) {
                // 4. Si la eliminación de la BD fue exitosa, intentar eliminar el archivo PDF del servidor
                if (!empty($pdf_filename_relative)) {
                    $pdf_filepath_absolute = PROJECT_ROOT . '/' . $pdf_filename_relative;
                    if (file_exists($pdf_filepath_absolute)) {
                        if (!@unlink($pdf_filepath_absolute)) {
                            // Loguear error pero no detener el éxito de la eliminación de la BD
                            error_log("ADVERTENCIA: No se pudo eliminar el archivo PDF {$pdf_filepath_absolute} del servidor para el presupuesto ID {$estimate_id_to_delete}.");
                            // Podrías añadir un mensaje adicional si quieres informar al usuario
                            // $response_message .= " (Advertencia: No se pudo eliminar el archivo PDF del servidor)";
                        }
                    }
                }
                
                $mysqli->commit();
                $response_status = 'success_delete'; // Usar un status específico para la redirección
                $response_message = 'Presupuesto eliminado con éxito.';
            } else {
                $mysqli->rollback(); // Si no se afectaron filas en 'estimates', algo falló o ya no existía
                $response_message = 'No se encontró el presupuesto para eliminar, o ya fue eliminado.';
            }
            $stmt_delete_estimate->close();

        } catch (Exception $e) {
            $mysqli->rollback();
            $response_message = "Error en la transacción: " . $e->getMessage();
            error_log("Error al eliminar presupuesto (ID: {$estimate_id_to_delete}): " . $e->getMessage());
        }
    } else {
        $response_message = 'ID de presupuesto no válido para eliminar.';
    }
}

// Redirigir de vuelta al listado de presupuestos
header('Location: ../index.php?status=' . $response_status . '&msg=' . urlencode($response_message));
exit;
?>