<?php
session_start();
header('Content-Type: application/json'); 

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}
require_once '../config.php'; 

$response = ['success' => false, 'message' => 'Error desconocido.', 'is_update' => false, 'odontogram_record_id' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $patient_id = filter_var($input['patient_id'] ?? null, FILTER_VALIDATE_INT);
    $record_date_str_input = $input['record_date'] ?? date('Y-m-d'); 
    $odontogram_general_notes = trim($input['odontogram_notes'] ?? ''); 
    $teeth_data_received = $input['teeth_data'] ?? []; 
    $existing_record_id = filter_var($input['existing_record_id'] ?? null, FILTER_VALIDATE_INT);
    
    $record_type_from_input = $input['record_type'] ?? null;
    $allowed_record_types = ['existente', 'a_realizar', 'realizadas'];

    if (!in_array($record_type_from_input, $allowed_record_types, true)) {
        error_log("save_odontogram_data.php - ERROR: Tipo de registro (record_type) inválido o no proporcionado. Recibido: " . var_export($record_type_from_input, true) . ". Input completo: " . print_r($input, true));
        $response['message'] = "Tipo de registro (record_type) inválido o no proporcionado en la solicitud. Se esperaba 'existente', 'a_realizar' o 'realizadas'.";
        echo json_encode($response);
        exit;
    }

    if (!$patient_id) {
        $response['message'] = 'ID de paciente no válido.';
        echo json_encode($response);
        exit;
    }
    
    $record_date_obj = DateTime::createFromFormat('Y-m-d', $record_date_str_input);
    if (!$record_date_obj || $record_date_obj->format('Y-m-d') !== $record_date_str_input) {
        $response['message'] = 'Formato de fecha de registro no válido. Se esperaba YYYY-MM-DD.';
        echo json_encode($response);
        exit;
    }
    $record_date_sql = $record_date_obj->format('Y-m-d');

    if (empty($teeth_data_received) && empty($odontogram_general_notes) && !$existing_record_id) {
        $response['message'] = 'No hay datos del odontograma para guardar en un nuevo registro.';
        echo json_encode($response);
        exit;
    }

    $mysqli->begin_transaction(); 

    try {
        $valid_condition_codes = [];
        $result_conds = $mysqli->query("SELECT condition_code FROM odontogram_conditions");
        if ($result_conds) {
            while ($row_cond = $result_conds->fetch_assoc()) {
                $valid_condition_codes[] = $row_cond['condition_code'];
            }
            $result_conds->free();
        } else {
            throw new Exception("Error al cargar los códigos de condición válidos: " . $mysqli->error);
        }

        $current_odontogram_record_id_to_use = $existing_record_id;

        if ($existing_record_id) {
            $stmt_update_record = $mysqli->prepare("UPDATE odontogram_records SET notes = ?, record_date = ? WHERE id = ? AND patient_id = ? AND record_type = ?");
            if(!$stmt_update_record) throw new Exception("Error preparando actualización de registro: " . $mysqli->error);
            $stmt_update_record->bind_param("ssiis", $odontogram_general_notes, $record_date_sql, $existing_record_id, $patient_id, $record_type_from_input);
            if(!$stmt_update_record->execute()) throw new Exception("Error actualizando registro de odontograma: " . $stmt_update_record->error);
            $stmt_update_record->close();
            $response['is_update'] = true;
            
            $stmt_delete_old_details = $mysqli->prepare("DELETE FROM odontogram_tooth_details WHERE odontogram_record_id = ?");
            if(!$stmt_delete_old_details) throw new Exception("Error preparando borrado de detalles antiguos: " . $mysqli->error);
            $stmt_delete_old_details->bind_param("i", $existing_record_id);
            if(!$stmt_delete_old_details->execute()) throw new Exception("Error borrando detalles antiguos: " . $stmt_delete_old_details->error);
            $stmt_delete_old_details->close();

        } else {
            $stmt_record = $mysqli->prepare("INSERT INTO odontogram_records (patient_id, record_date, notes, record_type) VALUES (?, ?, ?, ?)");
            if(!$stmt_record) throw new Exception("Error creando registro: " . $mysqli->error);
            $stmt_record->bind_param("isss", $patient_id, $record_date_sql, $odontogram_general_notes, $record_type_from_input);
            if(!$stmt_record->execute()) throw new Exception("Error al crear el registro del odontograma: " . $stmt_record->error);
            $current_odontogram_record_id_to_use = $mysqli->insert_id;
            $stmt_record->close();
            if (!$current_odontogram_record_id_to_use) throw new Exception("No se pudo obtener el ID del nuevo registro.");
            $response['is_update'] = false;
        }
        
        $response['odontogram_record_id'] = $current_odontogram_record_id_to_use;

        if (!empty($teeth_data_received)) {
            $stmt_tooth_detail_insert = $mysqli->prepare("INSERT INTO odontogram_tooth_details (odontogram_record_id, tooth_number, surface_o_condition_code, surface_v_condition_code, surface_l_condition_code, surface_p_condition_code, surface_m_condition_code, surface_d_condition_code, surface_c_condition_code, whole_tooth_condition_code, observations) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if(!$stmt_tooth_detail_insert) throw new Exception("Error preparando inserción de detalles: " . $mysqli->error);

            foreach ($teeth_data_received as $tooth_number_key => $data) {
                $tooth_number = (string)$tooth_number_key;
                if (!preg_match('/^[1-8][1-8]$|^[5-8][1-5]$/', $tooth_number)) { 
                    error_log("Número de diente inválido omitido: " . $tooth_number);
                    continue; 
                }
                $s_o = !empty($data['O']) ? $data['O'] : (!empty($data['I']) ? $data['I'] : null);
                $s_v = !empty($data['V']) ? $data['V'] : null;
                $s_l = !empty($data['L']) ? $data['L'] : null; 
                $s_p = !empty($data['P']) ? $data['P'] : null; 
                $s_m = !empty($data['M']) ? $data['M'] : null;
                $s_d = !empty($data['D']) ? $data['D'] : null;
                $s_c = !empty($data['C']) ? $data['C'] : null; 
                $whole = !empty($data['whole']) ? $data['whole'] : null;
                $obs = trim($data['obs'] ?? '');
                $obs_param = !empty($obs) ? $obs : null;
                $conditions_to_validate = ['Oclusal/Incisal' => $s_o, 'Vestibular' => $s_v, 'Lingual' => $s_l, 'Palatina' => $s_p, 'Mesial' => $s_m, 'Distal' => $s_d, 'Cervical' => $s_c, 'Diente Completo' => $whole];
                foreach ($conditions_to_validate as $surface_name => $code_to_check) {
                    if ($code_to_check !== null && !in_array($code_to_check, $valid_condition_codes)) {
                        throw new Exception("Código de condición inválido ('" . htmlspecialchars($code_to_check) . "') para " . $surface_name . " del diente " . $tooth_number . ".");
                    }
                }
                if ($s_o || $s_v || $s_l || $s_p || $s_m || $s_d || $s_c || $whole || $obs_param !== null ) {
                    $stmt_tooth_detail_insert->bind_param("issssssssss", $current_odontogram_record_id_to_use, $tooth_number, $s_o, $s_v, $s_l, $s_p, $s_m, $s_d, $s_c, $whole, $obs_param);
                    if (!$stmt_tooth_detail_insert->execute()) {
                        throw new Exception("Error guardando diente " . $tooth_number . ": " . $stmt_tooth_detail_insert->error);
                    }
                }
            }
            $stmt_tooth_detail_insert->close();
        }
        
        $mysqli->commit(); 
        $response['success'] = true;
        $response['message'] = 'Odontograma (' . htmlspecialchars($record_type_from_input) . ') guardado con éxito.';

    } catch (Exception $e) {
        $mysqli->rollback(); 
        // ** MODIFICACIÓN IMPORTANTE AQUÍ **
        // Se envía el mensaje de error detallado de vuelta al navegador.
        $response['message'] = "Error en la transacción: " . $e->getMessage();
        error_log("Error en save_odontogram_data.php: " . $e->getMessage() . " - Input: " . file_get_contents('php://input')); 
    }

} else {
    $response['message'] = 'Método de solicitud no válido.';
}

echo json_encode($response);
exit;
?>