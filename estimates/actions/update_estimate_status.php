<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}
require_once '../../config.php'; // Ajustar ruta a config.php

$response_status = 'error';
$response_message = 'Solicitud no válida.';

// Verificar permisos (puedes usar el permiso 'estimates' o uno más específico)
$can_update_status = false;
if (isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] === 'superadmin' || $_SESSION['admin_role'] === 'admin')) {
    $can_update_status = true; 
} elseif (user_has_permission('estimates', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    // Podrías tener un permiso más granular como 'estimates_update_status'
    $can_update_status = true;
}

if (!$can_update_status) {
    $response_message = 'No tiene permiso para actualizar el estado de los presupuestos.';
    header('Location: ../index.php?status=' . $response_status . '&msg=' . urlencode($response_message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['estimate_id_status_update']) && isset($_POST['new_status'])) {
    $estimate_id = filter_input(INPUT_POST, 'estimate_id_status_update', FILTER_VALIDATE_INT);
    $new_status = trim($_POST['new_status']);
    $estimate_number_for_msg = trim($_POST['estimate_number_for_msg'] ?? 'N/A');


    $valid_statuses = ['borrador', 'enviado', 'aprobado', 'rechazado', 'pagado'];

    if ($estimate_id && $estimate_id > 0 && in_array($new_status, $valid_statuses)) {
        
        // Si el nuevo estado es "enviado", podríamos querer (re)generar PDF y (re)enviar email.
        // Esta lógica puede ser compleja y es similar a la de save_estimate.php.
        // Por ahora, solo actualizaremos el estado. El reenvío de email/PDF puede ser una acción separada.
        $pdf_filename_to_update = null; // Placeholder si regeneramos PDF

        // Si se cambia a 'enviado' y no tiene PDF, o si se quiere reenviar, se podría regenerar.
        // Por simplicidad, por ahora solo actualizamos el estado.
        // La lógica de (re)generación y (re)envío de PDF/email cuando se cambia a 'enviado'
        // desde aquí se podría añadir si es un requisito.

        $stmt = $mysqli->prepare("UPDATE estimates SET status = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $new_status, $estimate_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response_status = 'success_status_update';
                    $response_message = 'Estado del presupuesto N° ' . htmlspecialchars($estimate_number_for_msg) . ' actualizado a "' . ucfirst($new_status) . '" con éxito.';
                } else {
                    $response_message = 'No se realizaron cambios en el estado del presupuesto N° ' . htmlspecialchars($estimate_number_for_msg) . ' (quizás ya tenía ese estado).';
                    $response_status = 'info'; // Usar 'info' si no hay error pero no hay cambio
                }
            } else {
                $response_message = 'Error al actualizar el estado del presupuesto: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $response_message = 'Error al preparar la actualización de estado: ' . $mysqli->error;
        }
    } else {
        $response_message = 'Datos inválidos para actualizar el estado.';
    }
}

header('Location: ../index.php?status=' . $response_status . '&msg=' . urlencode($response_message) . '#estimate-row-' . ($estimate_id ?? 0));
exit;
?>