<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado como personal.']);
    exit;
}
require_once '../config.php'; 

$current_admin_id = $_SESSION['admin_id'];
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
$last_message_id = filter_input(INPUT_GET, 'last_message_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

$new_messages = [];

if (!$patient_id) {
    echo json_encode(['success' => false, 'error' => 'ID de paciente no proporcionado.']);
    exit;
}

if (isset($mysqli) && $mysqli instanceof mysqli) {
    // Seleccionar mensajes NUEVOS enviados POR EL PACIENTE para esta conversación
    // que el personal aún no ha visto (o simplemente más nuevos que el último ID conocido)
    // y marcarlos como leídos por el personal.
    
    $stmt_select_new = $mysqli->prepare("
        SELECT m.id, m.admin_user_id, m.sent_by_patient, m.message_content, m.sent_at, m.edited_at, m.is_deleted_by_staff,
               p.fname as patient_fname_sender,  -- Nombre del paciente que envía
               IFNULL(au.name, 'Clínica') as staff_sender_name -- Nombre del admin si el staff envió (no aplica aquí)
        FROM messages m
        JOIN patients p ON m.patient_id = p.id -- Siempre necesitamos al paciente
        LEFT JOIN admin_users au ON m.admin_user_id = au.id AND m.sent_by_patient = FALSE
        WHERE m.patient_id = ? 
          AND m.id > ?
          AND m.sent_by_patient = TRUE /* Solo mensajes enviados por el paciente */
        ORDER BY m.sent_at ASC
    ");

    if ($stmt_select_new) {
        $stmt_select_new->bind_param("ii", $patient_id, $last_message_id);
        $stmt_select_new->execute();
        $result_new_messages = $stmt_select_new->get_result();
        
        $ids_to_mark_staff_read = [];
        while ($row = $result_new_messages->fetch_assoc()) {
            // Para el admin, el 'sender_name' será el del paciente.
            // El campo 'staff_sender_name' no es relevante para mensajes enviados por el paciente.
            // Lo importante es 'patient_fname_sender'.
            $row['sender_display_name'] = htmlspecialchars($row['patient_fname_sender']);
            $new_messages[] = $row;
            if ($row['sent_by_patient']) { 
                $ids_to_mark_staff_read[] = $row['id'];
            }
        }
        $stmt_select_new->close();

        if (!empty($ids_to_mark_staff_read)) {
            $ids_placeholder = implode(',', array_fill(0, count($ids_to_mark_staff_read), '?'));
            $types_mark_read = str_repeat('i', count($ids_to_mark_staff_read));
            
            // Marcar como leídos por el personal
            $stmt_mark_read_poll = $mysqli->prepare("UPDATE messages SET read_by_staff_at = NOW() WHERE id IN ($ids_placeholder) AND patient_id = ? AND sent_by_patient = TRUE");
            if ($stmt_mark_read_poll) {
                $params_mark_read = $ids_to_mark_staff_read;
                $params_mark_read[] = $patient_id; // Añadir patient_id al final para el WHERE
                
                $stmt_mark_read_poll->bind_param($types_mark_read . "i", ...$params_mark_read);
                $stmt_mark_read_poll->execute();
                if ($stmt_mark_read_poll->error) {
                    error_log("Error al marcar mensajes como leídos por staff (polling): " . $stmt_mark_read_poll->error);
                }
                $stmt_mark_read_poll->close();
            } else {
                error_log("Error al preparar actualización de leídos por staff en polling: " . $mysqli->error);
            }
        }
        
        echo json_encode(['success' => true, 'messages' => $new_messages]);

    } else {
        error_log("Error al preparar consulta de nuevos mensajes para staff: " . $mysqli->error);
        echo json_encode(['success' => false, 'error' => 'Error al obtener mensajes.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos.']);
}
exit;
?>
