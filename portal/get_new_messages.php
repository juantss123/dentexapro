<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['patient_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}
require_once '../config.php'; 

$patient_id = $_SESSION['patient_id'];
$last_message_id_client_knows = filter_input(INPUT_GET, 'last_message_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

$new_messages_array = [];
$deleted_staff_message_ids_array = []; 
$edited_staff_messages_array = []; 
$error = null;

if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->begin_transaction();
    try {
        // 1. Obtener mensajes NUEVOS del staff (no eliminados)
        $stmt_select_new = $mysqli->prepare("
            SELECT m.id, m.admin_user_id, m.sent_by_patient, m.message_content, m.sent_at, m.edited_at, m.is_deleted_by_staff,
                   IFNULL(au.name, 'Clínica') as sender_name 
            FROM messages m
            LEFT JOIN admin_users au ON m.admin_user_id = au.id AND m.sent_by_patient = FALSE
            WHERE m.patient_id = ? 
              AND m.id > ?
              AND m.sent_by_patient = FALSE 
              AND m.is_deleted_by_staff = FALSE 
            ORDER BY m.sent_at ASC
        ");

        if (!$stmt_select_new) throw new Exception("Error preparando consulta de nuevos mensajes: " . $mysqli->error);
        
        $stmt_select_new->bind_param("ii", $patient_id, $last_message_id_client_knows);
        $stmt_select_new->execute();
        $result_new_messages = $stmt_select_new->get_result();
        
        $ids_to_mark_read_by_patient = [];
        while ($row = $result_new_messages->fetch_assoc()) {
            $new_messages_array[] = $row;
            if (!$row['sent_by_patient']) { 
                $ids_to_mark_read_by_patient[] = $row['id'];
            }
        }
        $stmt_select_new->close();

        // Marcar los mensajes nuevos como leídos por el paciente
        if (!empty($ids_to_mark_read_by_patient)) {
            $ids_placeholder = implode(',', array_fill(0, count($ids_to_mark_read_by_patient), '?'));
            $types_mark_read = str_repeat('i', count($ids_to_mark_read_by_patient));
            
            $stmt_mark_read_poll = $mysqli->prepare("UPDATE messages SET read_by_patient_at = NOW() WHERE id IN ($ids_placeholder) AND patient_id = ?");
            if ($stmt_mark_read_poll) {
                $params_mark_read = $ids_to_mark_read_by_patient;
                $params_mark_read[] = $patient_id; 
                $stmt_mark_read_poll->bind_param($types_mark_read . "i", ...$params_mark_read);
                if(!$stmt_mark_read_poll->execute()){
                    error_log("Error al marcar mensajes como leídos por paciente (polling): " . $stmt_mark_read_poll->error);
                }
                $stmt_mark_read_poll->close();
            } else {
                error_log("Error al preparar actualización de leídos por paciente en polling: " . $mysqli->error);
            }
        }

        // 2. Obtener mensajes del STAFF que fueron ELIMINADOS (is_deleted_by_staff = TRUE)
        // Se buscan todos los eliminados para esta conversación; el cliente filtrará si ya los ocultó.
        $stmt_select_deleted = $mysqli->prepare("
            SELECT m.id
            FROM messages m
            WHERE m.patient_id = ?
              AND m.sent_by_patient = FALSE
              AND m.is_deleted_by_staff = TRUE
        ");
        if (!$stmt_select_deleted) throw new Exception("Error preparando consulta de mensajes eliminados: " . $mysqli->error);

        $stmt_select_deleted->bind_param("i", $patient_id);
        $stmt_select_deleted->execute();
        $result_deleted_messages = $stmt_select_deleted->get_result();
        while($row_deleted = $result_deleted_messages->fetch_assoc()){
            $deleted_staff_message_ids_array[] = $row_deleted['id'];
        }
        $stmt_select_deleted->close();
        
        // 3. Obtener mensajes del STAFF que fueron EDITADOS (edited_at IS NOT NULL y no eliminados)
        // Se buscan todos los editados; el cliente comparará el contenido o timestamp de edición.
        $stmt_select_edited = $mysqli->prepare("
            SELECT m.id, m.message_content, m.edited_at, m.sent_at, /* Incluir sent_at para que el JS pueda identificarlo */
                   IFNULL(au.name, 'Clínica') as sender_name
            FROM messages m
            LEFT JOIN admin_users au ON m.admin_user_id = au.id AND m.sent_by_patient = FALSE
            WHERE m.patient_id = ?
              AND m.sent_by_patient = FALSE
              AND m.is_deleted_by_staff = FALSE 
              AND m.edited_at IS NOT NULL
            ORDER BY m.sent_at ASC 
        "); 
        if (!$stmt_select_edited) throw new Exception("Error preparando consulta de mensajes editados: " . $mysqli->error);
        
        $stmt_select_edited->bind_param("i", $patient_id);
        $stmt_select_edited->execute();
        $result_edited_messages = $stmt_select_edited->get_result();
        while($row_edited = $result_edited_messages->fetch_assoc()){
            $edited_staff_messages_array[] = $row_edited;
        }
        $stmt_select_edited->close();
        
        $mysqli->commit();
        echo json_encode([
            'success' => true, 
            'new_messages' => $new_messages_array, 
            'deleted_staff_message_ids' => $deleted_staff_message_ids_array,
            'edited_staff_messages' => $edited_staff_messages_array
        ]);

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error en get_new_messages.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al procesar la solicitud de mensajes.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos.']);
}
exit;
?>
