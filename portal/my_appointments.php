<?php
session_start();
// Verify if the patient is logged in
if (!isset($_SESSION['patient_id'])) {
    header('Location: login.php'); 
    exit;
}
require_once '../config.php'; 

$patient_id = $_SESSION['patient_id'];

// Define which page this is for the header to mark the active link
$currentPage = 'my_appointments'; 

// --- Queries for My Appointments Page ---
$all_appointments = [];
$today_datetime_for_query = date('Y-m-d H:i:s');

$stmt_all_appts = $mysqli->prepare("
    SELECT id, datetime, reason, status
    FROM appointments 
    WHERE patient_id = ? 
    ORDER BY datetime DESC -- Mostrar los más recientes primero en el historial, y los próximos más cercanos primero
");

if ($stmt_all_appts) {
    $stmt_all_appts->bind_param("i", $patient_id);
    $stmt_all_appts->execute();
    $result_all_appts = $stmt_all_appts->get_result();
    while ($row = $result_all_appts->fetch_assoc()) {
        $all_appointments[] = $row;
    }
    $stmt_all_appts->close();
} else {
    // error_log("Error preparing all appointments query: " . $mysqli->error);
}

$upcoming_appointments_list = [];
$past_appointments_list = [];

foreach ($all_appointments as $appt) {
    $appt_datetime_obj = new DateTime($appt['datetime']);
    $current_datetime_obj = new DateTime(); // Hora actual para comparación más precisa

    // Para que un turno sea "próximo", debe ser hoy o en el futuro Y estar programado.
    // Si la fecha del turno es hoy, pero la hora ya pasó, se considerará pasado.
    if ($appt['status'] === 'Programado' && $appt_datetime_obj >= $current_datetime_obj) {
        $upcoming_appointments_list[] = $appt;
    } else {
        $past_appointments_list[] = $appt;
    }
}
// Re-ordenar los próximos turnos para que los más cercanos aparezcan primero
usort($upcoming_appointments_list, function($a, $b) {
    return strtotime($a['datetime']) - strtotime($b['datetime']);
});

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Turnos - Portal del Paciente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        html { height: 100%; }
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f7f6; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }
        .portal-navbar {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            flex-shrink: 0; 
        }
        .portal-navbar .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: #0d6efd; 
        }
        .portal-navbar .navbar-brand i { margin-right: 0.5rem; }
        .portal-navbar .nav-link.active { color: #0056b3 !important; font-weight: bold; }
        .portal-navbar .nav-link { padding-top: 0.8rem; padding-bottom: 0.8rem; }
        .portal-content { padding-top: 2rem; padding-bottom: 2rem; flex-grow: 1; }
        .footer { padding: 1.5rem 0; background-color: #343a40; color: #adb5bd; font-size: 0.9rem; flex-shrink: 0; }
        
        .appointment-list-card {
            margin-bottom: 1rem;
            border: 1px solid #e0e0e0;
        }
        .appointment-list-card .card-header {
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.6rem 1rem;
        }
        .appointment-list-card .card-body {
            padding: 0.8rem 1rem;
            font-size: 0.95rem;
        }
        .appointment-list-card .status-programado { color: var(--bs-primary); }
        .appointment-list-card .status-completado { color: var(--bs-success); }
        .appointment-list-card .status-cancelado { color: var(--bs-danger); }
        .appointment-list-card .text-muted { font-size: 0.85rem; }
        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: #343a40;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0d6efd;
            display: inline-block;
        }
         .no-appointments-section {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            background-color: #fff;
            border-radius: .375rem;
            border: 1px dashed #ccc;
        }
        .no-appointments-section i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            display: block;
        }
    </style>
</head>
<body>
    <?php require_once '_portal_header.php'; // INCLUDE THE CENTRALIZED PORTAL HEADER ?>

    <div class="container portal-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title"><i class="fas fa-calendar-alt me-2"></i>Mis Turnos</h2>
            
        </div>

        <section class="mb-5">
            <h4 class="mb-3"><i class="fas fa-hourglass-start text-primary me-2"></i>Próximos Turnos</h4>
            <?php if (!empty($upcoming_appointments_list)): ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($upcoming_appointments_list as $appt): 
                        $appt_datetime = new DateTime($appt['datetime']);
                    ?>
                    <div class="list-group-item appointment-list-card">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 fw-bold status-programado"><?php echo htmlspecialchars($appt['reason'] ?: 'Consulta General'); ?></h5>
                            <small class="text-muted"><?php echo $appt_datetime->format('d/m/Y'); ?></small>
                        </div>
                        <p class="mb-1"><strong>Hora:</strong> <?php echo $appt_datetime->format('H:i'); ?> hs</p>
                        <small class="text-muted">Estado: <span class="badge bg-primary"><?php echo htmlspecialchars($appt['status']); ?></span></small>
                       
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-appointments-section">
                    <i class="fas fa-calendar-check text-success"></i>
                    <p class="lead">No tienes próximos turnos programados.</p>
                </div>
            <?php endif; ?>
        </section>

        <section>
            <h4 class="mb-3"><i class="fas fa-history text-secondary me-2"></i>Historial de Turnos</h4>
            <?php if (!empty($past_appointments_list)): ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($past_appointments_list as $appt): 
                        $appt_datetime = new DateTime($appt['datetime']);
                        $status_class = '';
                        $status_badge_bg = 'bg-secondary';
                        if ($appt['status'] === 'Completado') {
                            $status_class = 'status-completado';
                            $status_badge_bg = 'bg-success';
                        } elseif ($appt['status'] === 'Cancelado') {
                            $status_class = 'status-cancelado';
                            $status_badge_bg = 'bg-danger';
                        } elseif ($appt['status'] === 'Programado') { // Turnos programados que ya pasaron
                            $status_class = 'text-muted'; // Podrías usar un estilo diferente para "No Asistió" si lo implementas
                            $status_badge_bg = 'bg-warning text-dark'; // Ej. Ausente o Programado (pasado)
                        }
                    ?>
                    <div class="list-group-item appointment-list-card">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 <?php echo $status_class; ?>"><?php echo htmlspecialchars($appt['reason'] ?: 'Consulta General'); ?></h5>
                            <small class="text-muted"><?php echo $appt_datetime->format('d/m/Y'); ?></small>
                        </div>
                        <p class="mb-1"><strong>Hora:</strong> <?php echo $appt_datetime->format('H:i'); ?> hs</p>
                        <small class="text-muted">Estado: <span class="badge <?php echo $status_badge_bg; ?>"><?php echo htmlspecialchars($appt['status']); ?></span></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                 <div class="no-appointments-section">
                    <i class="fas fa-folder-open text-muted"></i>
                    <p class="lead">Aún no tienes un historial de turnos.</p>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <footer class="footer text-center">
        <div class="container">
            <span>&copy; <?php echo date("Y"); ?> Dra. Fernanda Turano - Clínica Dental. Todos los derechos reservados.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
