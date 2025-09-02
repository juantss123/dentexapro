<?php
// Exporta historial clínico como CSV
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ../login.php'); exit; }
require_once '../config.php';

$patient_id = intval($_GET['patient_id'] ?? 0);
if (!$patient_id) { die('Paciente no especificado'); }

// Obtener datos del paciente
$stmt = $mysqli->prepare("SELECT fname, lname, dni FROM patients WHERE id=? LIMIT 1");
$stmt->bind_param("i", $patient_id); $stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) { die('Paciente no encontrado'); }

// Obtener historial
$stmt = $mysqli->prepare("SELECT created_at, note, file FROM patient_history WHERE patient_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $patient_id); $stmt->execute();
$history = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
$filename = 'historial_' . $patient['dni'] . '_' . date('Ymd_His') . '.csv';
header('Content-Disposition: attachment; filename="'.$filename.'"');
$output = fopen('php://output', 'w');

// Encabezados CSV
fputcsv($output, ['Paciente', $patient['fname'].' '.$patient['lname']]);
fputcsv($output, ['DNI', $patient['dni']]);
fputcsv($output, []); // Línea en blanco
fputcsv($output, ['Fecha', 'Nota', 'Adjunto']);

while ($row = $history->fetch_assoc()) {
    $fileLink = $row['file'] ? ('uploads/'.$row['file']) : '';
    fputcsv($output, [$row['created_at'], $row['note'], $fileLink]);
}

fclose($output);
exit;
?>
