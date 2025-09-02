<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

require_once '../config.php';

$response = ['success' => false, 'message' => 'Error desconocido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $patient_id = filter_var($input['patient_id'] ?? null, FILTER_VALIDATE_INT);
    
    // ** MODIFICACIÓN AQUÍ **
    // Se acepta cualquier texto, y el valor por defecto es un string vacío.
    $evaluacion_color = trim($input['evaluacion_color'] ?? '');
    
    $dientes_existentes_str = trim($input['dientes_existentes'] ?? '');
    $dientes_existentes = ($dientes_existentes_str === '') ? null : filter_var($dientes_existentes_str, FILTER_VALIDATE_INT);

    $enfermedad_periodontal = $input['enfermedad_periodontal'] ?? 'No Evaluado';
    if (!in_array($enfermedad_periodontal, ['Si', 'No', 'No Evaluado'])) {
        $enfermedad_periodontal = 'No Evaluado';
    }

    if ($patient_id === false) {
        $response['message'] = 'ID de paciente no válido.';
        echo json_encode($response);
        exit;
    }
    
    if ($dientes_existentes !== null && $dientes_existentes === false) {
        $response['message'] = 'El valor para dientes existentes no es un número válido.';
        echo json_encode($response);
        exit;
    }

    try {
        $stmt = $mysqli->prepare("UPDATE patients SET dientes_existentes = ?, evaluacion_color = ?, enfermedad_periodontal = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Error en la preparación de la consulta: ' . $mysqli->error);
        }
        
        $stmt->bind_param("issi", $dientes_existentes, $evaluacion_color, $enfermedad_periodontal, $patient_id);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Estado bucal del paciente actualizado con éxito.';
        } else {
            throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Error en update_patient_bucal_status.php: " . $e->getMessage());
    }
} else {
    $response['message'] = 'Método de solicitud no válido.';
}

echo json_encode($response);
exit;
?>