<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}
require_once '../config.php';

$response = ['success' => false, 'message' => 'Error desconocido.', 'data' => null, 'notes' => ''];
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
$record_id = filter_input(INPUT_GET, 'record_id', FILTER_VALIDATE_INT);

if (!$patient_id || !$record_id) {
    $response['message'] = 'ID de paciente o registro no válido.';
    echo json_encode($response);
    exit;
}

try {
    // Verificar que el record_id pertenezca al patient_id (seguridad)
    $stmt_check = $mysqli->prepare("SELECT notes FROM odontogram_records WHERE id = ? AND patient_id = ?");
    if (!$stmt_check) throw new Exception("Error preparando verificación: " . $mysqli->error);
    $stmt_check->bind_param("ii", $record_id, $patient_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows === 0) {
        throw new Exception("Registro de odontograma no encontrado para este paciente.");
    }
    $record_data = $result_check->fetch_assoc();
    $response['notes'] = $record_data['notes'] ?? '';
    $stmt_check->close();

    // Cargar los detalles de los dientes para el record_id especificado
    $teeth_details_data = [];
    $stmt_load_details = $mysqli->prepare("SELECT tooth_number, surface_o_condition_code, surface_v_condition_code, surface_l_condition_code, surface_p_condition_code, surface_m_condition_code, surface_d_condition_code, surface_c_condition_code, whole_tooth_condition_code, observations FROM odontogram_tooth_details WHERE odontogram_record_id = ?");
    if (!$stmt_load_details) throw new Exception("Error preparando carga de detalles: " . $mysqli->error);
    
    $stmt_load_details->bind_param("i", $record_id);
    $stmt_load_details->execute();
    $result_details = $stmt_load_details->get_result();
    while ($row = $result_details->fetch_assoc()) {
        $teeth_details_data[$row['tooth_number']] = [
            'O' => $row['surface_o_condition_code'],
            'V' => $row['surface_v_condition_code'],
            'L' => $row['surface_l_condition_code'],
            'P' => $row['surface_p_condition_code'],
            'M' => $row['surface_m_condition_code'],
            'D' => $row['surface_d_condition_code'],
            'C' => $row['surface_c_condition_code'],
            'whole' => $row['whole_tooth_condition_code'],
            'obs' => $row['observations']
        ];
    }
    $stmt_load_details->close();

    $response['success'] = true;
    $response['data'] = $teeth_details_data;
    $response['message'] = 'Datos del odontograma cargados.';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>