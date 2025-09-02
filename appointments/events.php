<?php
// Devuelve citas en formato JSON para FullCalendar
session_start();
if (!isset($_SESSION['admin_id'])) { http_response_code(403); exit; }
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$result = $mysqli->query("SELECT a.id, a.datetime, p.fname, p.lname, a.status FROM appointments a JOIN patients p ON p.id = a.patient_id");
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'id'    => $row['id'],
        'title' => $row['fname'] . ' ' . $row['lname'],
        'start' => date('c', strtotime($row['datetime'])), // ISO 8601
        'color' => ($row['status'] === 'Completado') ? '#28a745' : (($row['status'] === 'Cancelado') ? '#dc3545' : '#0d6efd')
    ];
}
echo json_encode($events);
?>
