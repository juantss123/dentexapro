<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../config.php';

$appointment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$redirect_page = $_GET['redirect_page'] ?? 'index.php'; // Default to calendar if not specified

if (!$appointment_id) {
    // Si no hay ID, decidir a dónde redirigir. Si venía de notifications, podría ser allí.
    // O a la lista de turnos como un fallback general si no hay redirect_page.
    $fallback_redirect = ($redirect_page === 'notifications') ? '../notifications.php' : 'list.php';
    header('Location: ' . $fallback_redirect . '?status=error_delete&msg=' . urlencode('ID de turno no válido.'));
    exit;
}

// Verificar si el turno existe antes de intentar eliminarlo (opcional pero recomendado)
$stmt_check = $mysqli->prepare("SELECT id FROM appointments WHERE id = ?");
if ($stmt_check) {
    $stmt_check->bind_param("i", $appointment_id);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows == 0) {
        $fallback_redirect_nf = ($redirect_page === 'notifications') ? '../notifications.php' : 'list.php';
        header('Location: ' . $fallback_redirect_nf . '?status=error_delete&msg=' . urlencode('Turno no encontrado.'));
        $stmt_check->close();
        exit;
    }
    $stmt_check->close();
}


$stmt = $mysqli->prepare("DELETE FROM appointments WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $appointment_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $status = 'success_delete';
            $msg = 'Turno eliminado correctamente.';
        } else {
            $status = 'error_delete';
            $msg = 'El turno no se encontró o ya fue eliminado.';
        }
    } else {
        $status = 'error_delete';
        $msg = 'Error al eliminar el turno: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $status = 'error_delete';
    $msg = 'Error al preparar la consulta de eliminación: ' . $mysqli->error;
}
$mysqli->close();

// Determinar a dónde redirigir
$redirect_url = 'list.php'; // MODIFICADO: Redirigir siempre a list.php por defecto
if ($redirect_page === 'notifications') {
    $redirect_url = '../notifications.php';
} elseif ($redirect_page === 'calendar_day_view' && isset($_GET['date'])) {
    // Si se quisiera volver a una vista de día específica del calendario, se necesitaría la fecha.
    // $redirect_url = 'day.php?date=' . urlencode($_GET['date']); 
    // Por ahora, si venía de una vista de día, también lo mandamos a la lista general.
}


header('Location: ' . $redirect_url . '?status=' . $status . '&msg=' . urlencode($msg));
exit;
?>
