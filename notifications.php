<?php

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php'); 
    exit;
}
require_once 'config.php'; 

$path_to_root = ''; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!user_has_permission('notifications', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$success_message = '';
$error_message = '';
$info_message = '';


// --- LÓGICA PARA EL BADGE DE NOTIFICACIONES (SE REPITE AQUÍ PARA ACTUALIZAR LA SESIÓN) ---
$current_total_notifications_on_page_load = 0;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $today_date_for_badge_notif = date('Y-m-d');
    $tomorrow_date_for_badge_notif = date('Y-m-d', strtotime('+1 day'));
    $seven_days_from_today_for_badge_notif = date('Y-m-d', strtotime('+7 day'));
    $twelve_months_ago_for_badge_notif = date('Y-m-d', strtotime('-12 months'));

    $count_badge_tomorrow_notif = 0;
    $stmt_badge_tomorrow_notif = $mysqli->prepare("SELECT COUNT(*) as c FROM appointments WHERE DATE(datetime) = ? AND status = 'Programado'");
    if ($stmt_badge_tomorrow_notif) {
        $stmt_badge_tomorrow_notif->bind_param('s', $tomorrow_date_for_badge_notif);
        $stmt_badge_tomorrow_notif->execute();
        $res_tomorrow = $stmt_badge_tomorrow_notif->get_result();
        $count_badge_tomorrow_notif = $res_tomorrow->fetch_assoc()['c'] ?? 0;
        $stmt_badge_tomorrow_notif->close();
    }

    $count_badge_birthdays_notif = 0;
    $stmt_badge_birthdays_notif = $mysqli->prepare("
        SELECT COUNT(*) as c FROM patients WHERE birthdate IS NOT NULL AND birthdate != '0000-00-00' AND
        (CASE
            WHEN STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d') < CURDATE()
            THEN STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d')
            ELSE STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d')
        END)
        BETWEEN ? AND ?");
    if ($stmt_badge_birthdays_notif) {
        $stmt_badge_birthdays_notif->bind_param('ss', $today_date_for_badge_notif, $seven_days_from_today_for_badge_notif);
        $stmt_badge_birthdays_notif->execute();
        $res_bday = $stmt_badge_birthdays_notif->get_result();
        $count_badge_birthdays_notif = $res_bday->fetch_assoc()['c'] ?? 0;
        $stmt_badge_birthdays_notif->close();
    }

    $count_badge_inactive_notif = 0;
    $stmt_badge_inactive_notif = $mysqli->prepare("
        SELECT p.id FROM patients p
        LEFT JOIN appointments a ON p.id = a.patient_id
        GROUP BY p.id, p.created_at
        HAVING (MAX(a.datetime) IS NOT NULL AND MAX(a.datetime) < ?) OR (MAX(a.datetime) IS NULL AND p.created_at < ?)");
    if ($stmt_badge_inactive_notif) {
        $stmt_badge_inactive_notif->bind_param('ss', $twelve_months_ago_for_badge_notif, $twelve_months_ago_for_badge_notif);
        $stmt_badge_inactive_notif->execute();
        $result_badge_inactive_notif = $stmt_badge_inactive_notif->get_result();
        $count_badge_inactive_notif = $result_badge_inactive_notif->num_rows;
        $result_badge_inactive_notif->close();
        $stmt_badge_inactive_notif->close();
    }
    $current_total_notifications_on_page_load = $count_badge_tomorrow_notif + $count_badge_birthdays_notif + $count_badge_inactive_notif;
    $_SESSION['last_viewed_notification_items_count'] = $current_total_notifications_on_page_load;
}
// --- FIN LÓGICA BADGE ---

$status_message = '';
$status_type = '';
if(isset($_GET['status']) && isset($_GET['msg'])){ // Modificado para requerir msg también
    if($_GET['status'] == 'success_delete'){
        $status_message = urldecode($_GET['msg']); // Usar el mensaje del GET
        $status_type = 'success';
    } elseif($_GET['status'] == 'error_delete'){
        $status_message = urldecode($_GET['msg']); // Usar el mensaje del GET
        $status_type = 'danger';
    }
}


if (!function_exists('format_phone_for_whatsapp')) {
    function format_phone_for_whatsapp($phone) {
        if (empty($phone)) {
            return null;
        }
        $cleaned_phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($cleaned_phone, 0, 2) === "54") {
            return $cleaned_phone;
        }
        if (strlen($cleaned_phone) == 10) {
            return "549" . $cleaned_phone;
        }
        return "54" . $cleaned_phone;
    }
}

$tomorrow_date_page = date('Y-m-d', strtotime('+1 day'));
$stmt_tomorrow_appts_page = $mysqli->prepare("
    SELECT TIME_FORMAT(a.datetime, '%H:%i') AS hora,
           a.id AS appointment_id,
           p.id AS patient_id,
           p.fname, p.lname,
           p.phone AS patient_phone,
           a.reason, a.status
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    WHERE DATE(a.datetime) = ? AND a.status = 'Programado'
    ORDER BY a.datetime
");
$tomorrow_appts_result = null;
$count_tomorrow_appts_display = 0;
if($stmt_tomorrow_appts_page){
    $stmt_tomorrow_appts_page->bind_param('s', $tomorrow_date_page);
    $stmt_tomorrow_appts_page->execute();
    $tomorrow_appts_result = $stmt_tomorrow_appts_page->get_result();
    $count_tomorrow_appts_display = $tomorrow_appts_result->num_rows;
}

$today_date_page = date('Y-m-d');
$seven_days_from_today_page = date('Y-m-d', strtotime('+7 day'));
$stmt_birthdays_page = $mysqli->prepare("
    SELECT id, fname, lname, birthdate, phone,
        CASE WHEN STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d') < CURDATE()
        THEN STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d')
        ELSE STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d') END AS next_birthday_date,
        TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age
    FROM patients WHERE birthdate IS NOT NULL AND birthdate != '0000-00-00'
    HAVING next_birthday_date BETWEEN ? AND ?
    ORDER BY MONTH(birthdate) ASC, DAY(birthdate) ASC, lname ASC, fname ASC
");
$birthdays_result = null;
$count_birthdays_display = 0;
if($stmt_birthdays_page){
    $stmt_birthdays_page->bind_param('ss', $today_date_page, $seven_days_from_today_page);
    $stmt_birthdays_page->execute();
    $birthdays_result = $stmt_birthdays_page->get_result();
    $count_birthdays_display = $birthdays_result->num_rows;
}

$twelve_months_ago_page = date('Y-m-d', strtotime('-12 months'));
$stmt_inactive_patients_page = $mysqli->prepare("
    SELECT p.id, p.fname, p.lname, p.phone, p.email, MAX(a.datetime) AS last_appointment_date, p.created_at AS patient_registration_date
    FROM patients p LEFT JOIN appointments a ON p.id = a.patient_id
    GROUP BY p.id, p.fname, p.lname, p.phone, p.email, p.created_at
    HAVING (MAX(a.datetime) IS NOT NULL AND MAX(a.datetime) < ?) OR (MAX(a.datetime) IS NULL AND p.created_at < ?)
    ORDER BY last_appointment_date ASC, p.created_at ASC, p.lname, p.fname LIMIT 15
");
$inactive_patients_result = null;
$count_inactive_patients_display = 0;
if($stmt_inactive_patients_page){
    $stmt_inactive_patients_page->bind_param('ss', $twelve_months_ago_page, $twelve_months_ago_page);
    $stmt_inactive_patients_page->execute();
    $inactive_patients_result = $stmt_inactive_patients_page->get_result();
    $count_inactive_patients_display = $inactive_patients_result->num_rows;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Notificaciones - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; }
        /* .chevron y .nav-link[aria-expanded="true"] .chevron no son necesarios directamente */
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        /* .btn-toggle-nav .nav-link .fa-angle-right no es necesario directamente */
        .notification-section { margin-bottom: 1.5rem; padding-top:1.5rem; }
        .notification-card { transition: box-shadow .3s ease; }
        .notification-card:hover { box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15) !important; }
        .card-turnos { border-left: 5px solid #ffc107; }
        .card-cumpleanos { border-left: 5px solid #198754; }
        .card-inactivos { border-left: 5px solid #dc3545; }
        .action-buttons .btn { margin-bottom: 0.25rem; margin-right:0.25rem; }
        .notification-list-item { padding: 0.75rem 1rem; border-bottom: 1px solid #eee; }
        .notification-list-item:last-child { border-bottom: none; }
        .nav-tabs .nav-link { color: #495057; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-color: #dee2e6 #dee2e6 #fff; font-weight: bold;}
        .nav-tabs .nav-link .badge { font-size: 0.75em; vertical-align: super;}

        @media (max-width: 576px) {
            .nav-tabs .nav-item {
                margin-bottom: 0.25rem; /* Espacio si las pestañas se envuelven */
                width: 100%; /* Hacer que cada pestaña ocupe el ancho completo si se envuelven */
            }
            .nav-tabs .nav-link {
                text-align: center; /* Centrar texto de la pestaña */
            }
            .action-buttons .btn, .d-grid .btn { /* Para botones de acción en tarjetas de turno */
                font-size: 0.8rem; /* Botones un poco más pequeños */
                padding: 0.3rem 0.5rem;
            }
            .notification-list-item .btn { /* Para botones en listas de cumpleaños/inactivos */
                 font-size: 0.8rem;
                 padding: 0.25rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
        if (isMobileDevice()) {
            require_once ($path_to_root ?: './') . '_sidebarmovil.php';
            echo '<nav class="navbar navbar-dark d-md-none sticky-top mb-3" style="background-color: #0d6efd;">
                    <div class="container-fluid" style="position: relative; display: flex; align-items: center; justify-content: flex-start; min-height: 56px;">
                        <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile" aria-controls="sidebarMobile" style="z-index: 10;">
                            <i class="fas fa-bars"></i>
                            <span class="d-none d-sm-inline ms-1">Menú</span>
                        </button>
                        <a class="navbar-brand" href="' . ($path_to_root ?: './') . 'dashboard.php" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); padding: 0; line-height: 1;">
                            <img src="' . ($path_to_root ?: './') . 'assets/img/dentexapro-logo-iso-blanco.png" alt="DentexaPro" style="height: 40px; width: auto; max-height: 100%;">
                        </a>
                    </div>
                  </nav>';
        } else {
            require_once ($path_to_root ?: './') . '_sidebar.php';
        }
        ?>

        <main class="col-12 <?php if (!isMobileDevice()) echo 'col-md-9 col-lg-10'; ?> p-md-4 p-2">
            <div class="card main-content-card shadow-sm rounded-3">
                <div class="card-body p-lg-4 p-2">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <h2 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-bell me-3 text-warning"></i>Central de Notificaciones
                        </h2>
                    </div>

                    <?php if ($status_message): ?>
                        <div class="alert alert-<?php echo $status_type; ?> alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-<?php echo $status_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($status_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <ul class="nav nav-tabs mb-4" id="notificationTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="turnos-manana-tab" data-bs-toggle="tab" data-bs-target="#turnos-manana-pane" type="button" role="tab" aria-controls="turnos-manana-pane" aria-selected="true">
                                <i class="fas fa-calendar-day me-1"></i> Turnos Mañana
                                <?php if($count_tomorrow_appts_display > 0): ?><span class="badge rounded-pill bg-warning text-dark ms-1"><?php echo $count_tomorrow_appts_display; ?></span><?php endif; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cumpleanos-tab" data-bs-toggle="tab" data-bs-target="#cumpleanos-pane" type="button" role="tab" aria-controls="cumpleanos-pane" aria-selected="false">
                                <i class="fas fa-birthday-cake me-1"></i> Cumpleaños
                                <?php if($count_birthdays_display > 0): ?><span class="badge rounded-pill bg-success ms-1"><?php echo $count_birthdays_display; ?></span><?php endif; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="inactivos-tab" data-bs-toggle="tab" data-bs-target="#inactivos-pane" type="button" role="tab" aria-controls="inactivos-pane" aria-selected="false">
                                <i class="fas fa-user-clock me-1"></i> Pac. Inactivos
                                <?php if($count_inactive_patients_display > 0): ?><span class="badge rounded-pill bg-danger ms-1"><?php echo $count_inactive_patients_display; ?></span><?php endif; ?>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="notificationTabsContent">
                        <div class="tab-pane fade show active" id="turnos-manana-pane" role="tabpanel" aria-labelledby="turnos-manana-tab">
                            <section class="notification-section">
                                <?php if ($tomorrow_appts_result && $tomorrow_appts_result->num_rows === 0): ?>
                                    <div class="alert alert-light d-flex align-items-center" role="alert"><i class="fas fa-calendar-check fa-2x me-3 text-success"></i><div><h5 class="alert-heading mb-0">¡Todo tranquilo!</h5><p class="mb-0">No hay turnos programados para mañana.</p></div></div>
                                <?php elseif($tomorrow_appts_result): ?>
                                    <div class="row g-3">
                                        <?php mysqli_data_seek($tomorrow_appts_result, 0);
                                        while ($row_tm = $tomorrow_appts_result->fetch_assoc()):
                                            $formatted_datetime_turno_tm = new DateTime($tomorrow_date_page . ' ' . $row_tm['hora']);
                                            $fecha_turno_display_tm = $formatted_datetime_turno_tm->format('d/m/Y');
                                            $hora_turno_display_tm = $formatted_datetime_turno_tm->format('H:i');
                                            $whatsapp_phone_paciente_turno_tm = format_phone_for_whatsapp($row_tm['patient_phone']);
                                            $nombre_paciente_completo_turno_tm = htmlspecialchars($row_tm['fname'].' '.$row_tm['lname']);
                                            $motivo_turno_display_tm = htmlspecialchars($row_tm['reason'] ?: 'Consulta');
                                            $nombre_clinica_tm = "Clínica Dental Dra. Fernanda Turano";
                                            $mensaje_whatsapp_turno_tm = "Hola {$nombre_paciente_completo_turno_tm},\n\nTe recordamos tu turno en {$nombre_clinica_tm} para *mañana*:\nFecha: *{$fecha_turno_display_tm}*\nHora: *{$hora_turno_display_tm} hs*\nMotivo: {$motivo_turno_display_tm}\n\nPor favor, avísanos con anticipación si necesitas reprogramar o cancelar.\n¡Te esperamos!";
                                            $whatsapp_url_turno_tm = '';
                                            if ($whatsapp_phone_paciente_turno_tm) {
                                                $whatsapp_url_turno_tm = "https://wa.me/{$whatsapp_phone_paciente_turno_tm}?text=" . rawurlencode($mensaje_whatsapp_turno_tm);
                                            }
                                        ?>
                                        <div class="col-12 col-md-6 col-lg-4 d-flex">
                                            <div class="card notification-card card-turnos shadow-sm w-100">
                                                <div class="card-body d-flex flex-column">
                                                    <div class="mb-2"><i class="fas fa-clock fa-fw text-secondary me-2"></i><span class="fw-bold fs-5"><?php echo $hora_turno_display_tm; ?></span></div>
                                                    <div class="mb-2"><i class="fas fa-user-injured fa-fw text-primary me-2"></i><span><?php echo $nombre_paciente_completo_turno_tm; ?></span></div>
                                                    <?php if ($row_tm['patient_phone']): ?><div class="mb-2"><i class="fas fa-phone fa-fw text-secondary me-2"></i><span><?php echo htmlspecialchars($row_tm['patient_phone']); ?></span></div><?php endif; ?>
                                                    <?php if ($row_tm['reason']): ?><div class="mb-3"><i class="fas fa-file-alt fa-fw text-secondary me-2"></i><span><?php echo htmlspecialchars($row_tm['reason']); ?></span></div><?php endif; ?>
                                                    <div class="mt-auto pt-2 border-top">
                                                        <div class="d-flex flex-wrap justify-content-start action-buttons">
                                                            <a href="<?php echo $path_to_root; ?>patients/history.php?patient_id=<?php echo $row_tm['patient_id']; ?>" class="btn btn-sm btn-outline-info flex-grow-1"><i class="fas fa-history me-1"></i>Historial</a>
                                                            <a href="<?php echo $path_to_root; ?>appointments/edit.php?id=<?php echo $row_tm['appointment_id']; ?>" class="btn btn-sm btn-outline-warning flex-grow-1"><i class="fas fa-edit me-1"></i>Editar</a>
                                                        </div>
                                                        <div class="d-grid gap-1 mt-1">
                                                            <?php if ($whatsapp_url_turno_tm): ?><a href="<?php echo $whatsapp_url_turno_tm; ?>" target="_blank" class="btn btn-sm btn-success w-100"><i class="fab fa-whatsapp me-1"></i>Recordar Turno</a>
                                                            <?php else: ?><button class="btn btn-sm btn-success w-100" title="Recordar Turno (Teléfono no disponible)" disabled><i class="fab fa-whatsapp me-1"></i>Recordar Turno</button><?php endif; ?>
                                                            <a href="<?php echo $path_to_root; ?>appointments/delete.php?id=<?php echo $row_tm['appointment_id']; ?>&redirect_page=notifications" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('¿Está seguro de que desea cancelar este turno?');"><i class="fas fa-times-circle me-1"></i>Cancelar Turno</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                     <div class="alert alert-light d-flex align-items-center" role="alert"><i class="fas fa-info-circle fa-2x me-3 text-muted"></i><div><p class="mb-0 lead">Error al cargar los turnos de mañana.</p></div></div>
                                <?php endif;
                                if($tomorrow_appts_result) $tomorrow_appts_result->close();
                                if($stmt_tomorrow_appts_page) $stmt_tomorrow_appts_page->close();
                                ?>
                            </section>
                        </div>
                        <div class="tab-pane fade" id="cumpleanos-pane" role="tabpanel" aria-labelledby="cumpleanos-tab">
                            <section class="notification-section">
                                <?php if ($birthdays_result && $birthdays_result->num_rows === 0): ?>
                                     <div class="alert alert-light d-flex align-items-center" role="alert"><i class="fas fa-gift fa-2x me-3 text-muted"></i><div><p class="mb-0 lead">No hay cumpleaños de pacientes en los próximos 7 días.</p></div></div>
                                <?php elseif($birthdays_result): ?>
                                    <div class="list-group shadow-sm">
                                        <?php mysqli_data_seek($birthdays_result, 0);
                                        while ($row_bday = $birthdays_result->fetch_assoc()):
                                            $next_bday_obj = new DateTime($row_bday['next_birthday_date']);
                                            $is_today_bday = ($next_bday_obj->format('Y-m-d') === $today_date_page);
                                            $nombre_paciente_bday = htmlspecialchars($row_bday['fname'].' '.$row_bday['lname']);
                                            $whatsapp_phone_bday = format_phone_for_whatsapp($row_bday['phone']);
                                            $mensaje_whatsapp_bday = "Estimado/a {$nombre_paciente_bday},\n\nEn nombre de todo el equipo de Clínica Dental Dra. Fernanda Turano, queremos desearle un muy ¡Feliz Cumpleaños! 🥳\n\nQue tenga un día excelente lleno de alegría.\n¡Muchas felicidades!\n\nAtentamente,\nClínica Dental Dra. Fernanda Turano";
                                            $whatsapp_url_bday = '';
                                            if ($whatsapp_phone_bday) { $whatsapp_url_bday = "https://wa.me/{$whatsapp_phone_bday}?text=" . rawurlencode($mensaje_whatsapp_bday); }
                                        ?>
                                        <div class="list-group-item notification-list-item notification-card card-cumpleanos <?php echo $is_today_bday ? 'border-success border-2 shadow-lg' : 'shadow-sm'; ?>">
                                            <div class="d-flex w-100 justify-content-between align-items-center flex-wrap gap-2">
                                                <div>
                                                    <h5 class="mb-1 d-flex align-items-center"><?php if ($is_today_bday): ?><i class="fas fa-star text-warning me-2 fa-beat" style="--fa-animation-duration: 2s;"></i><?php endif; ?><?php echo $nombre_paciente_bday; ?><small class="text-muted ms-2">(Cumple <?php echo $row_bday['age'] + 1; ?> años)</small></h5>
                                                    <p class="mb-1"><i class="fas fa-calendar-alt fa-fw text-secondary me-1"></i> Fecha: <?php echo $next_bday_obj->format('d/m/Y'); ?><?php if ($is_today_bday): ?><span class="badge bg-success ms-2">¡HOY!</span><?php endif; ?></p>
                                                    <?php if ($row_bday['phone']): ?><p class="mb-0 small"><i class="fas fa-phone fa-fw text-secondary me-1"></i> <?php echo htmlspecialchars($row_bday['phone']); ?></p><?php endif; ?>
                                                </div>
                                                <?php if ($whatsapp_url_bday): ?><a href="<?php echo $whatsapp_url_bday; ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Enviar Saludo por WhatsApp"><i class="fab fa-whatsapp me-1"></i> Enviar Saludo</a>
                                                <?php else: ?><button class="btn btn-sm btn-outline-success" title="Saludar (Teléfono no disponible para WhatsApp)" disabled><i class="fab fa-whatsapp me-1"></i> Enviar Saludo</button><?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-light d-flex align-items-center" role="alert"><i class="fas fa-info-circle fa-2x me-3 text-muted"></i><div><p class="mb-0 lead">Error al cargar los cumpleaños.</p></div></div>
                                <?php endif;
                                if($birthdays_result) $birthdays_result->close();
                                if($stmt_birthdays_page) $stmt_birthdays_page->close();
                                ?>
                            </section>
                        </div>
                        <div class="tab-pane fade" id="inactivos-pane" role="tabpanel" aria-labelledby="inactivos-tab">
                             <section class="notification-section">
                                <?php if ($inactive_patients_result && $inactive_patients_result->num_rows === 0): ?>
                                    <div class="alert alert-light d-flex align-items-center" role="alert"><i class="fas fa-user-check fa-2x me-3 text-muted"></i><div><p class="mb-0 lead">No hay pacientes inactivos según el criterio actual.</p></div></div>
                                <?php elseif($inactive_patients_result): ?>
                                     <div class="list-group shadow-sm">
                                        <?php mysqli_data_seek($inactive_patients_result, 0);
                                        while ($row_inactive = $inactive_patients_result->fetch_assoc()): ?>
                                        <div class="list-group-item notification-list-item notification-card card-inactivos">
                                            <div class="d-flex w-100 justify-content-between align-items-center flex-wrap gap-2">
                                                <div>
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($row_inactive['fname'].' '.$row_inactive['lname']); ?></h5>
                                                    <p class="mb-1 small"><i class="fas fa-calendar-times fa-fw text-secondary me-1"></i><?php if ($row_inactive['last_appointment_date']): ?>Último turno: <?php echo date('d/m/Y', strtotime($row_inactive['last_appointment_date'])); ?><?php else: ?>Registrado el: <?php echo date('d/m/Y', strtotime($row_inactive['patient_registration_date'])); ?> (Sin turnos)<?php endif; ?></p>
                                                     <?php if ($row_inactive['phone'] || $row_inactive['email']): ?><p class="mb-0 small"><?php if ($row_inactive['phone']): ?><i class="fas fa-phone fa-fw text-secondary me-1"></i> <?php echo htmlspecialchars($row_inactive['phone']); ?><?php endif; ?><?php if ($row_inactive['phone'] && $row_inactive['email']): ?> | <?php endif; ?><?php if ($row_inactive['email']): ?><i class="fas fa-envelope fa-fw text-secondary me-1"></i> <?php echo htmlspecialchars($row_inactive['email']); ?><?php endif; ?></p><?php endif; ?>
                                                </div>
                                                <a href="<?php echo $path_to_root; ?>patients/history.php?patient_id=<?php echo $row_inactive['id']; ?>" class="btn btn-sm btn-outline-danger" title="Ver Historial y Contactar"><i class="fas fa-user-edit me-1"></i> Revisar Ficha</a>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                     <div class="alert alert-light d-flex align-items-center" role="alert"><i class="fas fa-info-circle fa-2x me-3 text-muted"></i><div><p class="mb-0 lead">Error al cargar pacientes inactivos.</p></div></div>
                                <?php endif;
                                if($inactive_patients_result) $inactive_patients_result->close();
                                if($stmt_inactive_patients_page) $stmt_inactive_patients_page->close();
                                ?>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var autoDismissAlerts = document.querySelectorAll('.alert.alert-dismissible');
            autoDismissAlerts.forEach(function(alertEl) {
                setTimeout(function() {
                    if (document.body.contains(alertEl)) {
                        var bsAlert = bootstrap.Alert.getInstance(alertEl);
                        if (bsAlert) { bsAlert.close(); }
                    }
                }, 7000);
            });
        });
    </script>
</body>
</html>