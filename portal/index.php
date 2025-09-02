<?php
session_start();
// Verify if the patient is logged in
if (!isset($_SESSION['patient_id'])) {
    header('Location: login.php'); 
    exit;
}
require_once '../config.php'; 

$patient_id = $_SESSION['patient_id'];
// $patient_fname is already in the session and will be used by _portal_header.php

// Define which page this is for the header to mark the active link
$currentPage = 'dashboard'; 

// --- Lógica para el Aviso de Mensajes Nuevos en esta página ---
$unread_messages_for_dashboard_count = 0;
if (isset($mysqli) && $mysqli instanceof mysqli) { // Asegurar que $mysqli esté disponible
    $stmt_unread_dashboard = $mysqli->prepare("
        SELECT COUNT(*) as c 
        FROM messages 
        WHERE patient_id = ? 
          AND sent_by_patient = FALSE 
          AND read_by_patient_at IS NULL
    ");
    if ($stmt_unread_dashboard) {
        $stmt_unread_dashboard->bind_param("i", $patient_id);
        $stmt_unread_dashboard->execute();
        $res_unread_dashboard = $stmt_unread_dashboard->get_result();
        $unread_messages_for_dashboard_count = $res_unread_dashboard->fetch_assoc()['c'] ?? 0;
        $stmt_unread_dashboard->close();
    }
}
// --- FIN LÓGICA AVISO MENSAJES ---


// --- Consultas para el Dashboard del Paciente (Próximos Turnos) ---
$upcoming_appointments = [];
$today_for_query = date('Y-m-d H:i:s'); 

$stmt_upcoming = $mysqli->prepare("
    SELECT id, datetime, reason, status
    FROM appointments 
    WHERE patient_id = ? 
      AND datetime >= ? 
      AND status = 'Programado'
    ORDER BY datetime ASC
    LIMIT 5 
");

if ($stmt_upcoming) {
    $stmt_upcoming->bind_param("is", $patient_id, $today_for_query);
    $stmt_upcoming->execute();
    $result_upcoming = $stmt_upcoming->get_result();
    while ($row = $result_upcoming->fetch_assoc()) {
        $upcoming_appointments[] = $row;
    }
    $stmt_upcoming->close();
} 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Portal del Paciente - <?php echo htmlspecialchars($_SESSION['patient_fname'] ?? 'Paciente'); ?></title>
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
        .welcome-banner {
            background: linear-gradient(to right, #0d6efd, #0a58ca);
            color: white;
            padding: 2.5rem 1.5rem;
            border-radius: .5rem;
            margin-bottom: 1rem; /* Reducido para dar espacio al aviso de mensajes */
        }
        .welcome-banner h1 { font-family: 'Montserrat', sans-serif; font-weight: 700; }
        .new-messages-alert {
            animation: pulse-animation 2s infinite;
        }
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        .appointment-card { border-left: 5px solid #0dcaf0; margin-bottom: 1rem; background-color: #fff; }
        .appointment-card .card-body { padding: 1rem 1.25rem; }
        .appointment-card .card-title { font-weight: 700; color: #0dcaf0; }
        .appointment-card .card-time { font-size: 1.5rem; font-weight: 700; }
        .no-appointments { text-align: center; padding: 2rem; color: #6c757d; background-color: #fff; border-radius: .375rem; }
        .no-appointments i { font-size: 3rem; margin-bottom: 1rem; display: block; }
        .footer { padding: 1.5rem 0; background-color: #343a40; color: #adb5bd; font-size: 0.9rem; flex-shrink: 0; }
    </style>
</head>
<body>
    <?php require_once '_portal_header.php'; // INCLUIR EL HEADER CENTRALIZADO ?>

    <div class="container portal-content">
        <div class="welcome-banner shadow-sm">
            <h1>¡Hola, <?php echo htmlspecialchars($_SESSION['patient_fname'] ?? 'Paciente'); ?>!</h1>
            <p class="lead mb-0">Bienvenido/a a tu portal de paciente. Aquí podrás ver tus próximos turnos y mensajes.</p>
        </div>

        <?php if ($unread_messages_for_dashboard_count > 0): ?>
        <div class="alert alert-danger d-flex align-items-center justify-content-between new-messages-alert shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-envelope-open-text fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-0">¡Tienes Mensajes Nuevos!</h5>
                    <p class="mb-0 small">Tienes <?php echo $unread_messages_for_dashboard_count; ?> mensaje(s) sin leer de la clínica.</p>
                </div>
            </div>
            <a href="messages.php" class="btn btn-danger btn-sm">
                <i class="fas fa-arrow-right me-1"></i> Ver Mensajes
            </a>
        </div>
        <?php endif; ?>


        <div class="row">
            <div class="col-md-12">
                <h3 class="mb-3"><i class="fas fa-calendar-alt me-2 text-primary"></i>Tus Próximos Turnos Programados</h3>
                <?php if (!empty($upcoming_appointments)): ?>
                    <?php foreach ($upcoming_appointments as $appt): 
                        $appt_datetime = new DateTime($appt['datetime']);
                    ?>
                    <div class="card shadow-sm appointment-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-2 text-center text-md-start mb-2 mb-md-0">
                                    <div class="card-time"><?php echo $appt_datetime->format('H:i'); ?></div>
                                    <div class="text-muted small"><?php echo $appt_datetime->format('d/m/Y'); ?></div>
                                </div>
                                <div class="col-md-7">
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($appt['reason'] ?: 'Consulta General'); ?></h5>
                                    <p class="card-text small text-muted">
                                        Estado: <span class="badge bg-primary"><?php echo htmlspecialchars($appt['status']); ?></span>
                                    </p>
                                </div>
                                <div class="col-md-3 text-md-end">
                                  
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card shadow-sm">
                        <div class="card-body no-appointments">
                            <i class="fas fa-calendar-check text-muted"></i>
                            <p class="lead">No tienes turnos programados próximamente.</p>
                            <p>Si necesitas agendar una consulta, por favor contacta a la clínica.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer text-center">
        <div class="container">
            <span>&copy; <?php echo date("Y"); ?> Dra. Fernanda Turano - Clínica Dental. Todos los derechos reservados.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>