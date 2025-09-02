<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    // No debería llegar aquí si se accede desde un botón en el panel, pero por seguridad:
    echo "Acceso no autorizado."; 
    exit;
}
require_once '../config.php'; 

$response_status = 'error';
$response_message = 'Solicitud no válida.';
$patient_id_redirect = null; // Para redirigir de vuelta al odontograma del paciente

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_odontogram_record_id'])) {
    $odontogram_record_id_to_delete = filter_input(INPUT_POST, 'delete_odontogram_record_id', FILTER_VALIDATE_INT);
    
    // Obtener el patient_id asociado a este odontogram_record_id para la redirección
    if ($odontogram_record_id_to_delete) {
        $stmt_get_patient = $mysqli->prepare("SELECT patient_id FROM odontogram_records WHERE id = ?");
        if ($stmt_get_patient) {
            $stmt_get_patient->bind_param("i", $odontogram_record_id_to_delete);
            $stmt_get_patient->execute();
            $result_patient = $stmt_get_patient->get_result();
            if ($record_owner = $result_patient->fetch_assoc()) {
                $patient_id_redirect = $record_owner['patient_id'];
            }
            $stmt_get_patient->close();
        }
    }


    if ($odontogram_record_id_to_delete && $patient_id_redirect) {
        $mysqli->begin_transaction();
        try {
            // 1. Eliminar los detalles de los dientes asociados a este registro de odontograma
            $stmt_delete_details = $mysqli->prepare("DELETE FROM odontogram_tooth_details WHERE odontogram_record_id = ?");
            if (!$stmt_delete_details) throw new Exception("Error preparando borrado de detalles: " . $mysqli->error);
            $stmt_delete_details->bind_param("i", $odontogram_record_id_to_delete);
            if (!$stmt_delete_details->execute()) throw new Exception("Error borrando detalles del odontograma: " . $stmt_delete_details->error);
            $stmt_delete_details->close();

            // 2. Eliminar el registro principal del odontograma
            $stmt_delete_record = $mysqli->prepare("DELETE FROM odontogram_records WHERE id = ? AND patient_id = ?");
            if (!$stmt_delete_record) throw new Exception("Error preparando borrado de registro: " . $mysqli->error);
            $stmt_delete_record->bind_param("ii", $odontogram_record_id_to_delete, $patient_id_redirect);
            if (!$stmt_delete_record->execute()) throw new Exception("Error borrando el registro principal del odontograma: " . $stmt_delete_record->error);
            
            if ($stmt_delete_record->affected_rows > 0) {
                $mysqli->commit();
                $response_status = 'success_delete_odontogram';
                $response_message = 'Registro de odontograma eliminado con éxito.';
            } else {
                // Esto podría pasar si el record_id no existe o no pertenece al paciente (aunque ya lo verificamos arriba)
                $mysqli->rollback();
                $response_message = 'No se encontró el registro de odontograma para eliminar o no pertenece al paciente.';
            }
            $stmt_delete_record->close();

        } catch (Exception $e) {
            $mysqli->rollback();
            $response_message = "Error en la transacción: " . $e->getMessage();
            error_log("Error al eliminar odontograma: " . $e->getMessage());
        }
    } else {
        $response_message = 'ID de registro de odontograma no válido o no se pudo determinar el paciente.';
    }
} else {
    // Si no es POST o no está el ID esperado
    $response_message = 'Solicitud incorrecta para eliminar odontograma.';
}

// Redirigir a la página de selección de odontograma del paciente o al odontograma (si se crea uno nuevo por defecto)
$redirect_url = '../patients/odontogram_select.php';
if ($patient_id_redirect) {
    // Después de eliminar, es mejor ir al selector, ya que el odontograma específico ya no existe.
    // O podrías redirigir a odontogram.php?patient_id=$patient_id_redirect para que cargue el más reciente (o uno nuevo).
    $redirect_url = '../patients/odontogram.php?patient_id=' . $patient_id_redirect;
    if ($response_status === 'success_delete_odontogram') {
         // Si se eliminó con éxito, no pasar record_id para que cargue el más reciente o muestre para crear nuevo
    }
}

header('Location: ' . $redirect_url . '&status_odontogram=' . $response_status . '&msg_odontogram=' . urlencode($response_message));
exit;
?>
