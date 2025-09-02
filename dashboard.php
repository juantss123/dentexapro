<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once 'config.php'; 

$current_section_code = 'dashboard'; 
if (!user_has_permission($current_section_code, $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: profile.php?status=error&msg=' . urlencode('No tiene permiso para acceder al dashboard.'));
    exit;
}

$path_to_root = ''; 

$today_date_sql = date('Y-m-d');
$current_datetime_sql = date('Y-m-d H:i:s'); 

// ... (Toda la lógica PHP para obtener datos del dashboard se mantiene igual) ...
// 1. Total Pacientes
$total_patients_res = $mysqli->query("SELECT COUNT(*) AS c FROM patients");
$total_patients = $total_patients_res ? $total_patients_res->fetch_assoc()['c'] : 0;
if ($total_patients_res) $total_patients_res->close();
// 2. Turnos Programados para Hoy (que aún no han pasado)
$stmt_today_scheduled = $mysqli->prepare("SELECT COUNT(*) AS c FROM appointments WHERE DATE(datetime) = ? AND datetime >= ? AND status = 'Programado'");
$total_today_scheduled = 0;
if ($stmt_today_scheduled) {
    $stmt_today_scheduled->bind_param('ss', $today_date_sql, $current_datetime_sql);
    $stmt_today_scheduled->execute();
    $result_today_scheduled = $stmt_today_scheduled->get_result();
    $total_today_scheduled = $result_today_scheduled ? ($result_today_scheduled->fetch_assoc()['c'] ?? 0) : 0;
    $stmt_today_scheduled->close();
} else { error_log("Error en prepare para total_today_scheduled: " . $mysqli->error); }
// 3. Turnos Completados Hoy
$stmt_today_completed = $mysqli->prepare("SELECT COUNT(*) AS c FROM appointments WHERE DATE(datetime) = ? AND status = 'Completado'");
$total_today_completed = 0;
if ($stmt_today_completed) {
    $stmt_today_completed->bind_param('s', $today_date_sql);
    $stmt_today_completed->execute();
    $result_today_completed = $stmt_today_completed->get_result();
    $total_today_completed = $result_today_completed ? ($result_today_completed->fetch_assoc()['c'] ?? 0) : 0;
    $stmt_today_completed->close();
} else { error_log("Error en prepare para total_today_completed: " . $mysqli->error); }
// 4. Turnos Próximos 7 Días
$seven_days_later_sql = date('Y-m-d', strtotime('+6 days'));
$stmt_next_week = $mysqli->prepare("SELECT COUNT(*) AS c FROM appointments WHERE datetime >= ? AND DATE(datetime) <= ? AND status = 'Programado'");
$total_next_week_appointments = 0;
if ($stmt_next_week) {
    $stmt_next_week->bind_param('ss', $current_datetime_sql, $seven_days_later_sql);
    $stmt_next_week->execute();
    $result_next_week = $stmt_next_week->get_result();
    $total_next_week_appointments = $result_next_week ? ($result_next_week->fetch_assoc()['c'] ?? 0) : 0;
    $stmt_next_week->close();
} else { error_log("Error en prepare para total_next_week_appointments: " . $mysqli->error); }
// 5. Nuevos Pacientes (Últimos 7 días)
$seven_days_ago_sql_card = date('Y-m-d', strtotime('-6 days'));
$stmt_new_patients_card = $mysqli->prepare("SELECT COUNT(*) AS c FROM patients WHERE DATE(created_at) BETWEEN ? AND ?");
$total_new_patients_week = 0;
if ($stmt_new_patients_card) {
    $stmt_new_patients_card->bind_param('ss', $seven_days_ago_sql_card, $today_date_sql);
    $stmt_new_patients_card->execute();
    $result_new_patients_card = $stmt_new_patients_card->get_result();
    $total_new_patients_week = $result_new_patients_card ? ($result_new_patients_card->fetch_assoc()['c'] ?? 0) : 0;
    $stmt_new_patients_card->close();
} else { error_log("Error en prepare para total_new_patients_week: " . $mysqli->error); }
// 6. Listado de Turnos para Hoy
$stmt_today_list = $mysqli->prepare("SELECT a.id, TIME_FORMAT(a.datetime, '%H:%i') AS hora, a.datetime AS full_datetime, p.fname, p.lname, a.reason, p.id as patient_id FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE DATE(a.datetime) = ? AND a.status = 'Programado' ORDER BY a.datetime ASC");
$today_appointments_list_result = null;
$num_today_appts_for_list = 0;
if ($stmt_today_list) {
    $stmt_today_list->bind_param('s', $today_date_sql);
    $stmt_today_list->execute();
    $today_appointments_list_result = $stmt_today_list->get_result();
    $num_today_appts_for_list = $today_appointments_list_result ? $today_appointments_list_result->num_rows : 0; 
} else { error_log("Error en prepare para stmt_today_list: " . $mysqli->error); }
// 7. Turnos por Día (Últimos 7 Días)
$appointments_last_7_days_data = []; $labels_last_7_days = [];
for ($i = 6; $i >= 0; $i--) { $day = date('Y-m-d', strtotime("-$i days")); $labels_last_7_days[] = date('d/m', strtotime($day)); $stmt_day_appts = $mysqli->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE(datetime) = ?"); $count_result_day = 0;
    if ($stmt_day_appts) { $stmt_day_appts->bind_param('s', $day); $stmt_day_appts->execute(); $result_day_appts = $stmt_day_appts->get_result(); $count_result_day = $result_day_appts ? ($result_day_appts->fetch_assoc()['count'] ?? 0) : 0; $stmt_day_appts->close(); 
    } else { error_log("Error en prepare para stmt_day_appts (día {$day}): " . $mysqli->error); } $appointments_last_7_days_data[] = $count_result_day; }
$appointments_last_7_days_json = json_encode($appointments_last_7_days_data); $labels_last_7_days_json = json_encode($labels_last_7_days);
// 8. Pacientes Nuevos por Mes (Últimos 6 Meses)
$new_patients_last_6_months_data = []; $labels_last_6_months = []; $month_names_es = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
for ($i = 5; $i >= 0; $i--) { $target_month_date = strtotime("-$i months"); $year = date('Y', $target_month_date); $month = date('m', $target_month_date); $labels_last_6_months[] = $month_names_es[intval($month) - 1] . "'" . substr($year, 2); $stmt_month_new_patients = $mysqli->prepare("SELECT COUNT(*) as count FROM patients WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?"); $count_result_month = 0;
    if ($stmt_month_new_patients) { $stmt_month_new_patients->bind_param('ss', $year, $month); $stmt_month_new_patients->execute(); $result_month_new_patients = $stmt_month_new_patients->get_result(); $count_result_month = $result_month_new_patients ? ($result_month_new_patients->fetch_assoc()['count'] ?? 0) : 0; $stmt_month_new_patients->close(); 
    } else { error_log("Error en prepare para stmt_month_new_patients (mes {$month}/{$year}): " . $mysqli->error); } $new_patients_last_6_months_data[] = $count_result_month; }
$new_patients_last_6_months_json = json_encode($new_patients_last_6_months_data); $labels_last_6_months_json = json_encode($labels_last_6_months);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body{ 
            overflow-x:hidden; 
            background-color: #f0f2f5; 
          
        }
        #sidebar{min-height:100vh;}
        .chevron{transition:transform .2s;}
        .nav-link[aria-expanded="true"] .chevron{transform:rotate(180deg);}
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .stat-card { border: none; border-radius: .5rem; transition: transform .2s ease-out, box-shadow .2s ease-out; background-color: #fff; display: flex; flex-direction: column; justify-content: space-between; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 .75rem 1.5rem rgba(0,0,0,.1)!important; }
        .stat-card .card-body { padding: 1.25rem; display: flex; align-items: center; }
        .stat-card .stat-icon-wrapper { font-size: 2rem; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; flex-shrink: 0; }
        .stat-card .stat-icon-wrapper i { font-size: 1.75rem; }
        .stat-card .stat-number { font-size: 2rem; font-weight: 700; font-family: 'Montserrat', sans-serif; color: #343a40; }
        .stat-card .card-title { font-size: 0.85rem; font-weight: 500; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
        .stat-card.border-left-primary .stat-icon-wrapper { background-color: rgba(13, 110, 253, 0.1); color: var(--bs-primary); }
        .stat-card.border-left-success .stat-icon-wrapper { background-color: rgba(25, 135, 84, 0.1); color: var(--bs-success); }
        .stat-card.border-left-info .stat-icon-wrapper { background-color: rgba(13, 202, 240, 0.1); color: var(--bs-info); }
        .stat-card.border-left-warning .stat-icon-wrapper { background-color: rgba(255, 193, 7, 0.1); color: var(--bs-warning); }
        .stat-card.border-left-danger .stat-icon-wrapper { background-color: rgba(220, 53, 69, 0.1); color: var(--bs-danger); }
        .quick-action-card { text-align: center; border: 1px solid #e0e0e0; transition: transform .2s ease-in-out, box-shadow .2s ease-in-out, border-color .2s ease-in-out; background-color: #fff; border-radius: .5rem; }
        .quick-action-card:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.12)!important; border-color: var(--bs-primary); }
        .quick-action-card i { font-size: 2.25rem; margin-bottom: 0.75rem; }
        .quick-action-card .card-title { font-size: 0.95rem; font-weight: 500;}
        .today-appointments-list .list-group-item { border-left: 4px solid var(--bs-primary); margin-bottom: 0.75rem; padding: 0.8rem 1.25rem; border-radius: .375rem; background-color: #fff; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
        .today-appointments-list .list-group-item.appointment-passed { border-left-color: var(--bs-secondary); opacity: 0.7; }
        .today-appointments-list .list-group-item .appointment-time { font-weight: 700; font-size: 1.1rem; }
        .dashboard-section-header { font-family: 'Montserrat', sans-serif; font-weight: 600; color: #495057; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #dee2e6; }
        .dashboard-section-header i { color: var(--bs-primary); }
        .chart-container { background-color: #fff; padding: 1.5rem; border-radius: .5rem; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); display: flex; flex-direction: column; }
        .chart-canvas-wrapper { height: 300px; position: relative; flex-grow: 1; }
        .row.d-flex.align-items-stretch > [class*="col-"] { display: flex; flex-direction: column; }
        .row.d-flex.align-items-stretch > [class*="col-"] > .card,
        .row.d-flex.align-items-stretch > [class*="col-"] > .chart-container { flex-grow: 1; }
    </style>
</head>
<body>
  
    <div class="row g-0"> 
        
<?php
if (isMobileDevice()) { // Asume que isMobileDevice() está en config.php
    require_once ($path_to_root ?: './') . '_sidebarmovil.php';

    // Barra de navegación superior para móviles CON LOGO CENTRADO
    echo '
    <nav class="navbar navbar-dark d-md-none sticky-top mb-3" style="background-color: #0d6efd;">
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
        
   
        <main class="col-md-9 col-lg-10 p-4"> 
            <?php if(isset($_GET['status']) && isset($_GET['msg'])): ?>
                <div class="alert alert-<?php echo ($_GET['status'] == 'error' ? 'danger' : 'info'); ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo ($_GET['status'] == 'error' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                    <?php echo htmlspecialchars(urldecode($_GET['msg'])); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4"> 
                 <h1 class="h2 mb-0 d-flex align-items-center" style="font-family: 'Montserrat', sans-serif; font-weight:700;">
                    <i class="fas fa-tachometer-alt me-3 text-primary"></i>¡Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrador'); ?>!
                </h1>
            </div>
            <p class="lead text-muted mb-4">Este es el resumen de la actividad y accesos rápidos de la clínica.</p>

          
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-4 col-xl-3 mb-3"><a href="<?php echo $path_to_root; ?>appointments/index.php" class="text-decoration-none d-block h-100"><div class="card stat-card shadow-sm h-100 border-left-info"><div class="card-body"><div class="stat-icon-wrapper"><i class="fas fa-calendar-day"></i></div><div> <div class="stat-number"><?php echo $total_today_scheduled; ?></div> <div class="card-title">Turnos Hoy (Pend.)</div> </div></div></div></a></div>
                <div class="col-sm-6 col-md-4 col-xl-3 mb-3"><a href="<?php echo $path_to_root; ?>appointments/list.php?status_filter=Completado&date_filter=today" class="text-decoration-none d-block h-100"><div class="card stat-card shadow-sm h-100 border-left-success"><div class="card-body"> <div class="stat-icon-wrapper"><i class="fas fa-calendar-check"></i></div><div> <div class="stat-number"><?php echo $total_today_completed; ?></div> <div class="card-title">Turnos Hoy (Comp.)</div> </div></div></div></a></div>
                <div class="col-sm-6 col-md-4 col-xl-3 mb-3"><a href="<?php echo $path_to_root; ?>patients/index.php" class="text-decoration-none d-block h-100"><div class="card stat-card shadow-sm h-100 border-left-primary"><div class="card-body"><div class="stat-icon-wrapper"><i class="fas fa-user-friends"></i></div><div> <div class="stat-number"><?php echo $total_patients; ?></div> <div class="card-title">Pacientes Totales</div> </div></div></div></a></div>
                <div class="col-sm-6 col-md-4 col-xl-3 mb-3"><a href="<?php echo $path_to_root; ?>patients/index.php?filter=new_week" class="text-decoration-none d-block h-100"><div class="card stat-card shadow-sm h-100 border-left-danger"><div class="card-body"><div class="stat-icon-wrapper"><i class="fas fa-user-plus"></i></div><div> <div class="stat-number"><?php echo $total_new_patients_week; ?></div> <div class="card-title">Pac. Nuevos (7 Días)</div> </div></div></div></a></div>
            </div>
            <div class="row g-4 mb-4 d-flex align-items-stretch">
                <div class="col-lg-7"><div class="chart-container"><h5 class="dashboard-section-header"><i class="fas fa-chart-line me-2"></i>Turnos por Día (Últimos 7 Días)</h5><div class="chart-canvas-wrapper"><canvas id="appointmentsByDayChart"></canvas></div></div></div>
                <div class="col-lg-5"><div class="chart-container"><h5 class="dashboard-section-header"><i class="fas fa-users-line me-2"></i>Pacientes Nuevos por Mes (Últ. 6 Meses)</h5><div class="chart-canvas-wrapper"><canvas id="newPatientsByMonthChart"></canvas></div></div></div>
            </div>
            <div class="row g-4">
                <div class="col-lg-7"><div class="card shadow-sm mb-4 main-content-card d-flex flex-column"><div class="card-header bg-transparent border-bottom-0 pt-3"><h4 class="dashboard-section-header"><i class="fas fa-clock me-2"></i>Turnos Programados para Hoy (<?php echo date('d/m/Y'); ?>)</h4></div><div class="card-body p-3 <?php if ($num_today_appts_for_list > 3) echo 'overflow-auto'; ?>" style="<?php if ($num_today_appts_for_list > 3) echo 'max-height: 300px;'; ?> flex-grow: 1;"><?php if ($num_today_appts_for_list > 0): ?><ul class="list-group list-group-flush today-appointments-list"><?php $current_time_obj_list = new DateTime("now", new DateTimeZone('America/Argentina/Buenos_Aires')); mysqli_data_seek($today_appointments_list_result, 0); while($appt = $today_appointments_list_result->fetch_assoc()): $appt_datetime_obj_list = new DateTime($appt['full_datetime']); $is_past_list = $appt_datetime_obj_list < $current_time_obj_list; ?><li class="list-group-item d-flex justify-content-between align-items-center <?php if($is_past_list) echo 'appointment-passed text-muted'; ?>"><div><span class="fw-bold <?php echo $is_past_list ? '' : 'text-primary'; ?> appointment-time"><?php echo htmlspecialchars($appt['hora']); ?></span><a href="<?php echo $path_to_root; ?>patients/history.php?patient_id=<?php echo $appt['patient_id']; ?>" class="text-decoration-none ms-2 <?php echo $is_past_list ? 'text-muted' : ''; ?>"><?php echo htmlspecialchars($appt['fname'] . ' ' . $appt['lname']); ?></a><small class="d-block ps-4">- <?php echo htmlspecialchars($appt['reason'] ?: 'Sin motivo específico'); ?></small></div><a href="<?php echo $path_to_root; ?>appointments/edit.php?id=<?php echo $appt['id']; ?>" class="btn btn-sm btn-outline-secondary <?php if($is_past_list) echo 'disabled'; ?>" title="Ver/Editar Turno"><i class="fas fa-eye"></i></a></li><?php endwhile; ?></ul><?php else: ?><div class="p-3 text-center text-muted d-flex flex-column justify-content-center h-100"><i class="fas fa-calendar-check fa-3x mb-2 d-block text-success"></i><p class="mb-0">No hay turnos programados para hoy.</p></div><?php endif; ?></div><?php if ($num_today_appts_for_list > 0): ?><div class="card-footer text-center bg-transparent border-top-0 pb-3"><a href="<?php echo $path_to_root; ?>appointments/index.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-calendar-alt me-1"></i> Ver Agenda Completa</a></div><?php endif; ?><?php if($today_appointments_list_result) $today_appointments_list_result->close(); if($stmt_today_list) $stmt_today_list->close(); ?></div></div>
                <div class="col-lg-5"><div class="card shadow-sm main-content-card d-flex flex-column"><div class="card-header bg-transparent border-bottom-0 pt-3"><h4 class="dashboard-section-header"><i class="fas fa-bolt me-2"></i>Atajos Rápidos</h4></div><div class="card-body d-flex flex-column justify-content-center flex-grow-1"><div class="row g-3"><div class="col-6 d-flex"><a href="<?php echo $path_to_root; ?>appointments/create.php" class="card quick-action-card text-decoration-none text-dark d-block p-3 w-100"><i class="fas fa-calendar-plus text-primary"></i><span class="card-title d-block">Nuevo Turno</span></a></div><div class="col-6 d-flex"><a href="<?php echo $path_to_root; ?>patients/create.php" class="card quick-action-card text-decoration-none text-dark d-block p-3 w-100"><i class="fas fa-user-plus text-success"></i><span class="card-title d-block">Nuevo Paciente</span></a></div><div class="col-6 d-flex"><a href="<?php echo $path_to_root; ?>appointments/index.php" class="card quick-action-card text-decoration-none text-dark d-block p-3 w-100"><i class="fas fa-calendar-alt text-info"></i><span class="card-title d-block">Ver Agenda</span></a></div><div class="col-6 d-flex"><a href="<?php echo $path_to_root; ?>patients/index.php" class="card quick-action-card text-decoration-none text-dark d-block p-3 w-100"><i class="fas fa-users text-secondary"></i><span class="card-title d-block">Ver Pacientes</span></a></div></div></div></div></div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ... (JavaScript de los gráficos sin cambios) ...
        document.addEventListener('DOMContentLoaded', function () {
            const appointmentsByDayCtx = document.getElementById('appointmentsByDayChart');
            if (appointmentsByDayCtx) {
                new Chart(appointmentsByDayCtx, {
                    type: 'line', data: { labels: <?php echo $labels_last_7_days_json; ?>, datasets: [{ label: 'Turnos Registrados', data: <?php echo $appointments_last_7_days_json; ?>, borderColor: 'rgb(13, 110, 253)', backgroundColor: 'rgba(13, 110, 253, 0.1)', fill: true, tension: 0.3, pointBackgroundColor: 'rgb(13, 110, 253)', pointBorderColor: '#fff', pointHoverRadius: 7, pointHoverBackgroundColor: 'rgb(13, 110, 253)' }] },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }}}, plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false, callbacks: { label: function(context) { return ' Turnos: ' + context.parsed.y; }}}}, hover: { mode: 'nearest', intersect: true } }
                });
            }
            const newPatientsCtx = document.getElementById('newPatientsByMonthChart');
            if (newPatientsCtx) {
                new Chart(newPatientsCtx, {
                    type: 'bar', data: { labels: <?php echo $labels_last_6_months_json; ?>, datasets: [{ label: 'Pacientes Nuevos', data: <?php echo $new_patients_last_6_months_json; ?>, backgroundColor: 'rgba(25, 135, 84, 0.6)', borderColor: 'rgba(25, 135, 84, 1)', borderWidth: 1, borderRadius: 5, hoverBackgroundColor: 'rgba(25, 135, 84, 0.8)' }] },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }}}, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { return ' Pacientes: ' + context.parsed.y; }}}} }
                });
            }
        });
    </script>
</body>
</html>
