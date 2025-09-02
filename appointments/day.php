<?php
session_start();
if (!isset($_SESSION['admin_id'])) { http_response_code(403); exit; }
require_once '../config.php';

$date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { echo '<p>Fecha inválida</p>'; exit; }

$start = $date . ' 00:00:00';
$end   = $date . ' 23:59:59';

$stmt = $mysqli->prepare("SELECT TIME_FORMAT(a.datetime, '%H:%i') AS hora,
                                 p.fname, p.lname, a.reason, a.status
                          FROM appointments a
                          JOIN patients p ON p.id = a.patient_id
                          WHERE a.datetime BETWEEN ? AND ?
                          ORDER BY a.datetime ASC");
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo '<p>No hay turnos para esta fecha.</p>'; exit;
}
echo '<ul class="list-group">';
while ($row = $res->fetch_assoc()) {
    echo '<li class="list-group-item">';
    echo '<strong>' . htmlspecialchars($row['hora']) . '</strong> - ';
    echo htmlspecialchars($row['fname'] . ' ' . $row['lname']);
    if ($row['reason']) echo ' — ' . htmlspecialchars($row['reason']);
    echo ' <span class="badge bg-secondary">' . htmlspecialchars($row['status']) . '</span>';
    echo '</li>';
}
echo '</ul>';
?>
