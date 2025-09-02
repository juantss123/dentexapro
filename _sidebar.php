<?php
// _sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$path_to_root = $path_to_root ?? '';

if ((!isset($mysqli) || !($mysqli instanceof mysqli)) && file_exists(($path_to_root ?: './') . 'config.php')) {
    require_once (($path_to_root ?: './') . 'config.php');
}

$sidebar_admin_name_display = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrador');
$sidebar_profile_pic_src = ($path_to_root ?: './') . 'assets/img/default-avatar.png';

if (!empty($_SESSION['admin_profile_image']) &&
    defined('UPLOAD_DIR_NAME_CONST') &&
    defined('PROJECT_ROOT') &&
    file_exists(PROJECT_ROOT . '/' . UPLOAD_DIR_NAME_CONST . '/profiles/' . $_SESSION['admin_profile_image'])) {
    $sidebar_profile_pic_src = ($path_to_root ?: './') . UPLOAD_DIR_NAME_CONST . '/profiles/' . rawurlencode($_SESSION['admin_profile_image']);
}

$new_notifications_count = 0;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $today_date_for_badge = date('Y-m-d');
    $tomorrow_date_for_badge = date('Y-m-d', strtotime('+1 day'));
    $seven_days_from_today_for_badge = date('Y-m-d', strtotime('+7 day'));
    $twelve_months_ago_for_badge = date('Y-m-d', strtotime('-12 months'));

    $count_badge_tomorrow = 0;
    $stmt_badge_tomorrow = $mysqli->prepare("SELECT COUNT(*) as c FROM appointments WHERE DATE(datetime) = ? AND status = 'Programado'");
    if ($stmt_badge_tomorrow) {
        $stmt_badge_tomorrow->bind_param('s', $tomorrow_date_for_badge);
        $stmt_badge_tomorrow->execute();
        $res_tomorrow_badge = $stmt_badge_tomorrow->get_result();
        $count_badge_tomorrow = $res_tomorrow_badge->fetch_assoc()['c'] ?? 0;
        $stmt_badge_tomorrow->close();
    }

    $count_badge_birthdays = 0;
    $stmt_badge_birthdays = $mysqli->prepare("
        SELECT COUNT(*) as c FROM patients WHERE birthdate IS NOT NULL AND birthdate != '0000-00-00' AND
        (CASE
            WHEN STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d') < CURDATE()
            THEN STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d')
            ELSE STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d')
        END)
        BETWEEN ? AND ?");
    if ($stmt_badge_birthdays) {
        $stmt_badge_birthdays->bind_param('ss', $today_date_for_badge, $seven_days_from_today_for_badge);
        $stmt_badge_birthdays->execute();
        $res_bday_badge = $stmt_badge_birthdays->get_result();
        $count_badge_birthdays = $res_bday_badge->fetch_assoc()['c'] ?? 0;
        $stmt_badge_birthdays->close();
    }

    $count_badge_inactive = 0;
    $stmt_badge_inactive = $mysqli->prepare("
        SELECT p.id FROM patients p
        LEFT JOIN appointments a ON p.id = a.patient_id
        GROUP BY p.id, p.created_at
        HAVING (MAX(a.datetime) IS NOT NULL AND MAX(a.datetime) < ?) OR (MAX(a.datetime) IS NULL AND p.created_at < ?)");
    if ($stmt_badge_inactive) {
        $stmt_badge_inactive->bind_param('ss', $twelve_months_ago_for_badge, $twelve_months_ago_for_badge);
        $stmt_badge_inactive->execute();
        $result_badge_inactive = $stmt_badge_inactive->get_result();
        $count_badge_inactive = $result_badge_inactive->num_rows;
        $result_badge_inactive->close();
        $stmt_badge_inactive->close();
    }

    $current_total_notifications_for_badge = $count_badge_tomorrow + $count_badge_birthdays + $count_badge_inactive;
    $last_viewed_notification_items_count = $_SESSION['last_viewed_notification_items_count'] ?? 0;

    $new_notifications_count = $current_total_notifications_for_badge - $last_viewed_notification_items_count;
    if ($new_notifications_count < 0) {
        $new_notifications_count = 0;
    }
}

$unread_staff_messages_count = 0;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $stmt_unread_messages = $mysqli->prepare("SELECT COUNT(*) as c FROM messages WHERE sent_by_patient = TRUE AND read_by_staff_at IS NULL");
    if ($stmt_unread_messages) {
        $stmt_unread_messages->execute();
        $res_unread_messages = $stmt_unread_messages->get_result();
        $unread_staff_messages_count = $res_unread_messages->fetch_assoc()['c'] ?? 0;
        $stmt_unread_messages->close();
    }
}

$current_page_basename = basename($_SERVER['PHP_SELF']);
$current_page_script_path = $_SERVER['PHP_SELF'];
if (!function_exists('is_section_active_sidebar')) { function is_section_active_sidebar($section_path_fragment, $current_script_path) { $section_path_fragment = rtrim($section_path_fragment, '/') . '/'; return strpos($current_script_path, $section_path_fragment) !== false; }}
$user_role = $_SESSION['admin_role'] ?? 'employee';
$user_permissions_json = $_SESSION['admin_permissions'] ?? '[]';

// Variables de estado para los enlaces del menú
$is_dashboard_active = ($current_page_basename == 'dashboard.php');
$is_profile_active = ($current_page_basename == 'profile.php');
$is_notifications_active = ($current_page_basename == 'notifications.php');

$is_patients_section_active = is_section_active_sidebar('patients', $current_page_script_path);
$is_patients_create_active = ($current_page_basename == 'create.php' && $is_patients_section_active);
$is_patients_list_active = ($current_page_basename == 'index.php' && $is_patients_section_active);
$is_patients_odontogram_link_active = (($current_page_basename == 'odontogram_select.php' || $current_page_basename == 'odontogram.php') && $is_patients_section_active);
$is_patients_history_select_active = (($current_page_basename == 'history_select.php' || $current_page_basename == 'history.php' || $current_page_basename == 'add_history.php' || $current_page_basename == 'edit_history.php') && $is_patients_section_active);


$is_appointments_section_active = is_section_active_sidebar('appointments', $current_page_script_path);
$is_appointments_create_active = ($current_page_basename == 'create.php' && $is_appointments_section_active);
$is_appointments_calendar_active = ($current_page_basename == 'index.php' && $is_appointments_section_active);
$is_appointments_list_active = ($current_page_basename == 'list.php' && $is_appointments_section_active);

$is_messaging_section_active = is_section_active_sidebar('messages', $current_page_script_path);
$is_reports_section_active = is_section_active_sidebar('reports', $current_page_script_path);
$is_inventory_section_active = is_section_active_sidebar('inventory', $current_page_script_path);

$is_billing_section_active = (
    is_section_active_sidebar('billing', $current_page_script_path) ||
    is_section_active_sidebar('estimates', $current_page_script_path) ||
    ($current_page_basename == 'customize_pdf.php' && is_section_active_sidebar('billing', $current_page_script_path))
);
$is_estimates_page_active = is_section_active_sidebar('estimates', $current_page_script_path);
$is_billing_customize_pdf_active = ($current_page_basename == 'customize_pdf.php' && is_section_active_sidebar('billing', $current_page_script_path));


$is_employees_section_active = is_section_active_sidebar('employees', $current_page_script_path);
$is_user_guide_active = ($current_page_basename == 'user_guide.php');
$is_system_section_active = is_section_active_sidebar('system', $current_page_script_path);
$is_system_db_backup_active = ($current_page_basename == 'database_backup.php' && $is_system_section_active);

?>
<style>
    #sidebar {
        min-height: 100vh;
        background-color: #0d6efd !important; 
        color: #fff; 
    }
    #sidebar .text-center.mb-3 { 
        border-bottom-color: rgba(255, 255, 255, 0.2) !important;
    }
    #sidebar .sidebar-profile-pic {
        border-color: rgba(255, 255, 255, 0.5) !important; 
    }
    #sidebar h5.text-white, #sidebar small.text-muted { 
        color: #fff !important;
    }
    #sidebar small.text-muted {
        opacity: 0.8;
    }
    #sidebar .input-group .form-control-sm {
        background-color: rgba(255,255,255,0.1);
        color: #fff;
        border-color: rgba(255,255,255,0.3);
    }
    #sidebar .input-group .form-control-sm::placeholder {
        color: rgba(255,255,255,0.7);
    }
    #sidebar .input-group .btn-outline-light { 
        border-color: rgba(255,255,255,0.3);
        color: #fff;
    }
    #sidebar .input-group .btn-outline-light:hover {
        background-color: rgba(255,255,255,0.2);
    }
    #sidebar .nav-link { 
        color: #f8f9fa; 
        padding-top: 0.6rem; 
        padding-bottom: 0.6rem;
    }
    #sidebar .nav-link i.fas, #sidebar .nav-link i.fab { 
        color: #fff; 
    }
     #sidebar .nav-link i.text-warning { 
        color: #ffc107 !important;
     }
    #sidebar .nav-link:hover,
    #sidebar .btn-toggle-nav .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: #fff !important;
    }
    #sidebar .nav-link.active {
        background-color: rgba(255, 255, 255, 0.2) !important; 
        color: #fff !important;
        font-weight: bold;
    }
    #sidebar .btn-toggle-nav .nav-link { 
        color: #e9ecef; 
    }
    #sidebar .btn-toggle-nav .nav-link.fw-bold, 
    #sidebar .btn-toggle-nav .nav-link:hover {
        color: #fff !important;
        background-color: rgba(255, 255, 255, 0.08);
    }
     #sidebar .btn-toggle-nav .nav-link.fw-bold {
        background-color: rgba(255, 255, 255, 0.12);
     }
    .chevron { transition: transform .2s; }
    #sidebar .nav-link[aria-expanded="true"] .chevron { transform: rotate(180deg); }
    #sidebar .nav-link[data-bs-toggle="collapse"] .chevron { 
        color: rgba(255,255,255,0.7);
    }
    .sidebar-profile-image-wrapper { position: relative; display: inline-block; width: 80px; height: 80px; border-radius: 50%; overflow: hidden; cursor: pointer; vertical-align: middle; }
    .sidebar-profile-image-wrapper .change-pic-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); color: white; display: flex; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.2s ease-in-out; border-radius: 50%; }
    .sidebar-profile-image-wrapper:hover .change-pic-overlay { opacity: 1; }
    .sidebar-profile-image-wrapper .change-pic-overlay i.fa-camera { font-size: 1.75rem; }
    #profilePicPreviewModal { width: 150px; height: 150px; border: 1px solid #ddd; border-radius: 50%; object-fit: cover; margin: 0 auto 1rem auto; display: block; }
    .sidebar-notification-badge { font-size: 0.7em; padding: .25em .5em; }
    #sidebar .badge.bg-danger { 
         color: #fff;
    }
    #sidebar .badge.bg-info.text-dark { 
        background-color: #fff !important;
        color: #0d6efd !important;
    }
    .sidebar-logout-section {
        padding-top: 0.75rem;
        margin-top: 0.75rem;
        border-top: 1px solid rgba(255, 255, 255, 0.2) !important; 
    }
    .sidebar-logout-section .btn-outline-light {
        border-color: rgba(255,255,255,0.5);
        color: #fff;
    }
    .sidebar-logout-section .btn-outline-light:hover {
        background-color: rgba(255,255,255,0.1);
    }
    #changeProfilePicModal .modal-content {
        background-color: #0a58ca; 
        color: #fff;
    }
    #changeProfilePicModal .modal-header,
    #changeProfilePicModal .modal-footer {
        border-color: rgba(255, 255, 255, 0.2);
    }
    #changeProfilePicModal .form-text {
        color: rgba(255,255,255,0.8) !important;
    }
     #changeProfilePicModal .form-control {
        background-color: rgba(255,255,255,0.1);
        color: #fff;
        border-color: rgba(255,255,255,0.3);
    }
    #changeProfilePicModal .form-control::placeholder {
        color: rgba(255,255,255,0.7);
    }
</style>

<nav id="sidebar" class="col-12 col-md-3 col-lg-2 p-3 d-none d-md-flex flex-column">
    <div>
        <div class="text-center mb-3 border-bottom pb-3">
            <a href="#" class="sidebar-profile-image-wrapper mb-2"
               data-bs-toggle="modal" data-bs-target="#changeProfilePicModal"
               title="Cambiar foto de perfil">
                <img src="<?php echo $sidebar_profile_pic_src; ?>?t=<?php echo time(); ?>" alt="Foto de Perfil"
                     class="img-fluid rounded-circle sidebar-profile-pic">
                <span class="change-pic-overlay">
                    <i class="fas fa-camera"></i>
                </span>
            </a>
            <h5 class="text-white mb-0 mt-1"><?php echo $sidebar_admin_name_display; ?></h5>
            <small class="text-muted"><?php echo htmlspecialchars(ucfirst($user_role)); ?></small>
        </div>
        <form action="<?php echo $path_to_root; ?>global_search.php" method="GET" class="mb-3">
            <div class="input-group"><input type="text" name="term" class="form-control form-control-sm" placeholder="Buscar..." aria-label="Buscar en todo el sitio" value="<?php echo isset($_GET['term']) && $current_page_basename == 'global_search.php' ? htmlspecialchars($_GET['term']) : ''; ?>"><button class="btn btn-sm btn-outline-light" type="submit"><i class="fas fa-search"></i></button></div>
        </form>
    </div>

    <ul class="nav nav-pills flex-column">
        <?php if (user_has_permission('dashboard', $user_permissions_json, $user_role)): ?><li class="nav-item"><a href="<?php echo $path_to_root; ?>dashboard.php" class="nav-link <?php if ($is_dashboard_active) echo 'active'; ?>"><i class="fas fa-home me-2"></i>Inicio</a></li><?php endif; ?>
        <?php if (user_has_permission('profile', $user_permissions_json, $user_role)): ?> <li class="nav-item"><a href="<?php echo $path_to_root; ?>profile.php" class="nav-link <?php if ($is_profile_active) echo 'active'; ?>"><i class="fas fa-user-circle me-2"></i>Mi Perfil</a></li><?php endif; ?>
        <?php if (user_has_permission('notifications', $user_permissions_json, $user_role)): ?>
        <li class="nav-item">
            <a href="<?php echo $path_to_root; ?>notifications.php" class="nav-link <?php if ($is_notifications_active) echo 'active'; ?>">
                <i class="fas fa-bell me-2 text-warning"></i>Notificaciones
                <?php if ($new_notifications_count > 0): ?>
                    <span class="badge rounded-pill bg-danger ms-1 sidebar-notification-badge"><?php echo $new_notifications_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (user_has_permission('patients', $user_permissions_json, $user_role)): ?>
        <li class="nav-item">
            <a class="nav-link <?php if ($is_patients_section_active) echo 'active'; ?>" data-bs-toggle="collapse" href="#submenuPacientesDesk" role="button" aria-expanded="<?php echo $is_patients_section_active ? 'true' : 'false'; ?>" aria-controls="submenuPacientesDesk"><i class="fas fa-user-injured me-2"></i>Pacientes <i class="fas fa-chevron-down ms-auto chevron"></i></a>
            <div class="collapse <?php if ($is_patients_section_active) echo 'show'; ?>" id="submenuPacientesDesk">
                <ul class="btn-toggle-nav list-unstyled fw-normal ps-3 small">
                    <?php if (user_has_permission('patients_create', $user_permissions_json, $user_role)): ?><li><a href="<?php echo $path_to_root; ?>patients/create.php" class="nav-link <?php if ($is_patients_create_active) echo 'fw-bold'; ?>"><?php if ($is_patients_create_active): ?><i class="fas fa-angle-right me-1"></i><?php endif; ?>Agregar Paciente</a></li><?php endif; ?>
                    <?php if (user_has_permission('patients_list', $user_permissions_json, $user_role)): ?><li><a href="<?php echo $path_to_root; ?>patients/index.php" class="nav-link <?php if ($is_patients_list_active) echo 'fw-bold'; ?>"><?php if ($is_patients_list_active): ?><i class="fas fa-angle-right me-1"></i><?php endif; ?>Listado</a></li><?php endif; ?>
                    <?php if (user_has_permission('patients_history', $user_permissions_json, $user_role)): ?><li><a href="<?php echo $path_to_root; ?>patients/history_select.php" class="nav-link <?php if ($is_patients_history_select_active) echo 'fw-bold'; ?>"><?php if ($is_patients_history_select_active): ?><i class="fas fa-angle-right me-1"></i><?php endif; ?>Historial Clínico</a></li><?php endif; ?>
                    <?php if (user_has_permission('patients_odontogram', $user_permissions_json, $user_role)): ?><li><a href="<?php echo $path_to_root; ?>patients/odontogram_select.php" class="nav-link <?php if ($is_patients_odontogram_link_active) echo 'fw-bold'; ?>"><?php if ($is_patients_odontogram_link_active): ?><i class="fas fa-angle-right me-1"></i><?php endif; ?>Odontograma</a></li><?php endif; ?>
                </ul>
            </div>
        </li>
        <?php endif; ?>
        
        <?php if (user_has_permission('appointments', $user_permissions_json, $user_role)): ?>
        <li class="nav-item">
            <a class="nav-link <?php if ($is_appointments_section_active) echo 'active'; ?>" data-bs-toggle="collapse" href="#submenuTurnosDesk" role="button" aria-expanded="<?php echo $is_appointments_section_active ? 'true' : 'false'; ?>" aria-controls="submenuTurnosDesk"><i class="fas fa-calendar-check me-2"></i>Turnos <i class="fas fa-chevron-down ms-auto chevron"></i></a>
            <div class="collapse <?php if ($is_appointments_section_active) echo 'show'; ?>" id="submenuTurnosDesk">
                <ul class="btn-toggle-nav list-unstyled fw-normal ps-3 small">
                    <?php if (user_has_permission('appointments_create', $user_permissions_json, $user_role)): ?><li><a href="<?php echo $path_to_root; ?>appointments/create.php" class="nav-link <?php if ($is_appointments_create_active) echo 'fw-bold'; ?>"><?php if ($is_appointments_create_active): ?><i class="fas fa-angle-right me-1"></i><?php endif; ?>Crear Turno</a></li><?php endif; ?>
                    <?php if (user_has_permission('appointments_calendar', $user_permissions_json, $user_role)): ?><li><a href="<?php echo $path_to_root; ?>appointments/index.php" class="nav-link <?php if ($is_appointments_calendar_active) echo 'fw-bold'; ?>"><?php if ($is_appointments_calendar_active): ?><i class="fas fa-angle-right me-1"></i><?php endif; ?>Calendario</a></li><?php endif; ?>
                    <?php if (user_has_permission('appointments_list', $user_permissions_json, $user_role)): ?><li><a href="<?php echo $path_to_root; ?>appointments/list.php" class="nav-link <?php if ($is_appointments_list_active) echo 'fw-bold'; ?>"><?php if ($is_appointments_list_active): ?><i class="fas fa-angle-right me-1"></i><?php endif; ?>Listado de Turnos</a></li><?php endif; ?>
                </ul>
            </div>
        </li>
        <?php endif; ?>
        
        <?php if (user_has_permission('messaging_admin', $user_permissions_json, $user_role)): ?>
        <li class="nav-item"><a href="<?php echo $path_to_root; ?>messages/index.php" class="nav-link <?php if ($is_messaging_section_active) echo 'active'; ?>"><i class="fas fa-envelope-open-text me-2"></i>Mensajería Clínica <?php if ($unread_staff_messages_count > 0): ?><span class="badge rounded-pill bg-info text-dark ms-1 sidebar-notification-badge"><?php echo $unread_staff_messages_count; ?></span><?php endif; ?></a></li>
        <?php endif; ?>

        <?php if (user_has_permission('billing', $user_permissions_json, $user_role)): ?>
        <li class="nav-item">
            <a class="nav-link <?php if ($is_billing_section_active) echo 'active'; ?>" data-bs-toggle="collapse" href="#submenuCobrosDesk" role="button" aria-expanded="<?php echo $is_billing_section_active ? 'true' : 'false'; ?>" aria-controls="submenuCobrosDesk">
                <i class="fas fa-file-invoice-dollar me-2"></i>Cobros <i class="fas fa-chevron-down ms-auto chevron"></i>
            </a>
            <div class="collapse <?php if ($is_billing_section_active) echo 'show'; ?>" id="submenuCobrosDesk">
                <ul class="btn-toggle-nav list-unstyled fw-normal ps-3 small">
                    <?php if (user_has_permission('estimates', $user_permissions_json, $user_role)): ?>
                        <li>
                            <a href="<?php echo $path_to_root; ?>estimates/index.php" class="nav-link <?php if ($is_estimates_page_active) echo 'fw-bold'; ?>">
                                <?php if ($is_estimates_page_active): ?><i class="fas fa-angle-right me-1"></i><?php endif; ?>Presupuestos
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (user_has_permission('billing_customize_pdf', $user_permissions_json, $user_role)): ?>
                        <li>
                            <a href="<?php echo $path_to_root; ?>billing/customize_pdf.php" class="nav-link <?php if ($is_billing_customize_pdf_active) echo 'fw-bold'; ?>">
                                <?php if ($is_billing_customize_pdf_active): ?><i class="fas fa-angle-right me-1"></i><?php endif; ?>Personalizar PDF
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </li>
        <?php endif; ?>
        <?php if (user_has_permission('reports', $user_permissions_json, $user_role)): ?><li class="nav-item"><a href="<?php echo $path_to_root; ?>reports/index.php" class="nav-link <?php if ($is_reports_section_active) echo 'active'; ?>"><i class="fas fa-chart-line me-2"></i>Reportes</a></li><?php endif; ?>
        <?php if (user_has_permission('inventory', $user_permissions_json, $user_role)): ?><li class="nav-item"><a href="<?php echo $path_to_root; ?>inventory/index.php" class="nav-link <?php if ($is_inventory_section_active) echo 'active'; ?>"><i class="fas fa-boxes me-2"></i>Insumos</a></li><?php endif; ?>
        
        <?php if ($user_role === 'superadmin'): ?>
            <?php if (user_has_permission('employees', $user_permissions_json, $user_role)): ?>
                <li class="nav-item"><a href="<?php echo $path_to_root; ?>employees/index.php" class="nav-link <?php if ($is_employees_section_active) echo 'active'; ?>"><i class="fas fa-users-cog me-2"></i>Empleados</a></li>
            <?php endif; ?>
            <?php if (user_has_permission('system', $user_permissions_json, $user_role)): ?>
             <li class="nav-item">
                <a class="nav-link <?php if ($is_system_section_active) echo 'active'; ?>" data-bs-toggle="collapse" href="#submenuSistemaDesk" role="button" aria-expanded="<?php echo $is_system_section_active ? 'true' : 'false'; ?>" aria-controls="submenuSistemaDesk"><i class="fas fa-cogs me-2"></i>Sistema <i class="fas fa-chevron-down ms-auto chevron"></i></a>
                <div class="collapse <?php if ($is_system_section_active) echo 'show'; ?>" id="submenuSistemaDesk">
                    <ul class="btn-toggle-nav list-unstyled fw-normal ps-3 small">
                        <?php if (user_has_permission('system_backup', $user_permissions_json, $user_role)): ?><li><a href="<?php echo $path_to_root; ?>system/database_backup.php" class="nav-link <?php if ($is_system_db_backup_active) echo 'fw-bold'; ?>"><?php if ($is_system_db_backup_active): ?><i class="fas fa-angle-right me-1"></i><?php endif; ?>Backup/Restaurar BD</a></li><?php endif; ?>
                    </ul>
                </div>
            </li>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['admin_id'])): ?>
        <li class="nav-item"><a href="<?php echo $path_to_root; ?>user_guide.php" class="nav-link <?php if ($is_user_guide_active) echo 'active'; ?>"><i class="fas fa-book-reader me-2"></i>Guía del Usuario</a></li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-logout-section">
        <a href="<?php echo $path_to_root; ?>logout.php" class="btn btn-outline-light w-100"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a>
    </div>

    <div style="flex-grow: 1;"></div>
</nav>

<div class="modal fade" id="changeProfilePicModal" tabindex="-1" aria-labelledby="changeProfilePicModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changeProfilePicModalLabel"><i class="fas fa-camera-retro me-2"></i>Cambiar Foto de Perfil</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?php echo $path_to_root; ?>profile.php" method="POST" enctype="multipart/form-data" id="profilePicModalForm">
        <div class="modal-body">
          <div class="text-center mb-3">
            <img src="<?php echo $sidebar_profile_pic_src; ?>?t=<?php echo time(); ?>" id="profilePicPreviewModal" alt="Vista previa">
          </div>
          <div class="mb-3">
            <label for="profile_image_file_modal" class="form-label">Seleccionar nueva imagen:</label>
            <input class="form-control form-control-lg" type="file" id="profile_image_file_modal" name="profile_image_file" accept="image/png, image/jpeg, image/gif" required>
            <div class="form-text">Tamaño máximo 2MB. Formatos: JPG, PNG, GIF.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" name="update_profile_picture" value="1" class="btn btn-primary"><i class="fas fa-save me-2"></i>Actualizar Foto</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Script para el modal de cambio de foto de perfil de _sidebar.php
document.addEventListener('DOMContentLoaded', function() {
    const profileImageInputModal = document.getElementById('profile_image_file_modal');
    const profilePicPreviewModal = document.getElementById('profilePicPreviewModal');
    let originalModalPicSrc = profilePicPreviewModal ? profilePicPreviewModal.src : '';
    const modalElement = document.getElementById('changeProfilePicModal');

    if(modalElement && profilePicPreviewModal) {
        modalElement.addEventListener('show.bs.modal', function () {
            const currentSidebarPic = document.querySelector('#sidebar .sidebar-profile-image-wrapper img.sidebar-profile-pic');
            if(currentSidebarPic) {
                originalModalPicSrc = currentSidebarPic.src;
                profilePicPreviewModal.src = originalModalPicSrc;
            }
            if(profileImageInputModal) profileImageInputModal.value = null;
        });
    }

    if (profileImageInputModal && profilePicPreviewModal) {
        profileImageInputModal.onchange = evt => {
            const [file] = profileImageInputModal.files;
            if (file) {
                profilePicPreviewModal.src = URL.createObjectURL(file);
            } else {
                profilePicPreviewModal.src = originalModalPicSrc;
            }
        }
    }
});
</script>