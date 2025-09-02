<?php
// _sidebarmovil.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Asegurarse de que $path_to_root esté definido.
$path_to_root = $path_to_root ?? '';

if ((!isset($mysqli) || !($mysqli instanceof mysqli)) && file_exists(($path_to_root ?: './') . 'config.php')) {
    require_once (($path_to_root ?: './') . 'config.php');
}

$sidebar_admin_name_display_mobile = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrador');
$sidebar_profile_pic_src_mobile = ($path_to_root ?: './') . 'assets/img/default-avatar.png';
if (!empty($_SESSION['admin_profile_image']) &&
    defined('UPLOAD_DIR_NAME_CONST') &&
    defined('PROJECT_ROOT') &&
    file_exists(PROJECT_ROOT . '/' . UPLOAD_DIR_NAME_CONST . '/profiles/' . $_SESSION['admin_profile_image'])) {
    $sidebar_profile_pic_src_mobile = ($path_to_root ?: './') . UPLOAD_DIR_NAME_CONST . '/profiles/' . rawurlencode($_SESSION['admin_profile_image']);
}

$user_role_mobile = $_SESSION['admin_role'] ?? 'employee';
$user_permissions_json_mobile = $_SESSION['admin_permissions'] ?? '[]';

// Lógica para badges de notificaciones y mensajes
$new_notifications_count_mobile = 0;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $today_date_for_badge_sm = date('Y-m-d');
    $tomorrow_date_for_badge_sm = date('Y-m-d', strtotime('+1 day'));
    $seven_days_from_today_for_badge_sm = date('Y-m-d', strtotime('+7 day'));
    $twelve_months_ago_for_badge_sm = date('Y-m-d', strtotime('-12 months'));

    $count_badge_tomorrow_sm = 0;
    $stmt_badge_tomorrow_sm = $mysqli->prepare("SELECT COUNT(*) as c FROM appointments WHERE DATE(datetime) = ? AND status = 'Programado'");
    if ($stmt_badge_tomorrow_sm) {
        $stmt_badge_tomorrow_sm->bind_param('s', $tomorrow_date_for_badge_sm);
        $stmt_badge_tomorrow_sm->execute();
        $res_tomorrow_badge_sm = $stmt_badge_tomorrow_sm->get_result();
        $count_badge_tomorrow_sm = $res_tomorrow_badge_sm->fetch_assoc()['c'] ?? 0;
        $stmt_badge_tomorrow_sm->close();
    }

    $count_badge_birthdays_sm = 0;
    $stmt_badge_birthdays_sm = $mysqli->prepare("
        SELECT COUNT(*) as c FROM patients WHERE birthdate IS NOT NULL AND birthdate != '0000-00-00' AND
        (CASE
            WHEN STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d') < CURDATE()
            THEN STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d')
            ELSE STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(birthdate), '-', DAY(birthdate)), '%Y-%m-%d')
        END)
        BETWEEN ? AND ?");
    if ($stmt_badge_birthdays_sm) {
        $stmt_badge_birthdays_sm->bind_param('ss', $today_date_for_badge_sm, $seven_days_from_today_for_badge_sm);
        $stmt_badge_birthdays_sm->execute();
        $res_bday_badge_sm = $stmt_badge_birthdays_sm->get_result();
        $count_badge_birthdays_sm = $res_bday_badge_sm->fetch_assoc()['c'] ?? 0;
        $stmt_badge_birthdays_sm->close();
    }

    $count_badge_inactive_sm = 0;
    $stmt_badge_inactive_sm = $mysqli->prepare("
        SELECT p.id FROM patients p
        LEFT JOIN appointments a ON p.id = a.patient_id
        GROUP BY p.id, p.created_at
        HAVING (MAX(a.datetime) IS NOT NULL AND MAX(a.datetime) < ?) OR (MAX(a.datetime) IS NULL AND p.created_at < ?)");
    if ($stmt_badge_inactive_sm) {
        $stmt_badge_inactive_sm->bind_param('ss', $twelve_months_ago_for_badge_sm, $twelve_months_ago_for_badge_sm);
        $stmt_badge_inactive_sm->execute();
        $result_badge_inactive_sm = $stmt_badge_inactive_sm->get_result();
        $count_badge_inactive_sm = $result_badge_inactive_sm->num_rows;
        $result_badge_inactive_sm->close();
        $stmt_badge_inactive_sm->close();
    }

    $current_total_notifications_for_badge_sm = $count_badge_tomorrow_sm + $count_badge_birthdays_sm + $count_badge_inactive_sm;
    $last_viewed_notification_items_count_sm = $_SESSION['last_viewed_notification_items_count'] ?? 0;

    $new_notifications_count_mobile = $current_total_notifications_for_badge_sm - $last_viewed_notification_items_count_sm;
    if ($new_notifications_count_mobile < 0) {
        $new_notifications_count_mobile = 0;
    }
}

$unread_staff_messages_count_mobile = 0;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $stmt_unread_messages_sm = $mysqli->prepare("SELECT COUNT(*) as c FROM messages WHERE sent_by_patient = TRUE AND read_by_staff_at IS NULL");
    if ($stmt_unread_messages_sm) {
        $stmt_unread_messages_sm->execute();
        $res_unread_messages_sm = $stmt_unread_messages_sm->get_result();
        $unread_staff_messages_count_mobile = $res_unread_messages_sm->fetch_assoc()['c'] ?? 0;
        $stmt_unread_messages_sm->close();
    }
}
$current_page_basename_mobile = basename($_SERVER['PHP_SELF']);
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!function_exists('is_section_active_sidebar')) {
    function is_section_active_sidebar($section_path_fragment, $current_script_path) {
        $section_path_fragment = rtrim($section_path_fragment, '/') . '/';
        // Asegurarse de que $current_script_path no sea nulo o vacío
        if (empty($current_script_path)) return false;
        return strpos($current_script_path, $section_path_fragment) !== false;
    }
}

// Variables de estado para el menú móvil
$is_dashboard_active_mobile = ($current_page_basename_mobile == 'dashboard.php');
$is_profile_active_mobile = ($current_page_basename_mobile == 'profile.php');
$is_notifications_active_mobile = ($current_page_basename_mobile == 'notifications.php');

$is_patients_list_active_mobile = ($current_page_basename_mobile == 'index.php' && is_section_active_sidebar('patients', $current_page_script_path));
$is_patients_create_active_mobile = ($current_page_basename_mobile == 'create.php' && is_section_active_sidebar('patients', $current_page_script_path));
$is_patients_history_active_mobile = (($current_page_basename_mobile == 'history_select.php' || $current_page_basename_mobile == 'history.php' || $current_page_basename_mobile == 'add_history.php' || $current_page_basename_mobile == 'edit_history.php') && is_section_active_sidebar('patients', $current_page_script_path));
$is_patients_odontogram_active_mobile = (($current_page_basename_mobile == 'odontogram_select.php' || $current_page_basename_mobile == 'odontogram.php') && is_section_active_sidebar('patients', $current_page_script_path));


$is_appointments_calendar_active_mobile = ($current_page_basename_mobile == 'index.php' && is_section_active_sidebar('appointments', $current_page_script_path));
$is_appointments_create_active_mobile = ($current_page_basename_mobile == 'create.php' && is_section_active_sidebar('appointments', $current_page_script_path));
$is_appointments_list_active_mobile = ($current_page_basename_mobile == 'list.php' && is_section_active_sidebar('appointments', $current_page_script_path));

$is_messaging_active_mobile = is_section_active_sidebar('messages', $current_page_script_path);

$is_billing_index_active_mobile = ($current_page_basename_mobile == 'index.php' && is_section_active_sidebar('billing', $current_page_script_path));
$is_estimates_page_active_mobile = is_section_active_sidebar('estimates', $current_page_script_path);
$is_billing_customize_pdf_active_mobile = ($current_page_basename_mobile == 'customize_pdf.php' && is_section_active_sidebar('billing', $current_page_script_path));

$is_reports_active_mobile = is_section_active_sidebar('reports', $current_page_script_path);
$is_inventory_active_mobile = is_section_active_sidebar('inventory', $current_page_script_path);
$is_employees_active_mobile = is_section_active_sidebar('employees', $current_page_script_path);
$is_system_active_mobile = is_section_active_sidebar('system', $current_page_script_path); // Cubre system/database_backup.php
$is_user_guide_active_mobile = ($current_page_basename_mobile == 'user_guide.php');

?>

<style>
    #sidebarMobile.offcanvas {
        background-color: #0d6efd; 
        color: #fff; 
    }
    #sidebarMobile .offcanvas-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.2); 
        padding: 0.8rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    #sidebarMobile .offcanvas-title-container { 
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    #sidebarMobile .user-info-mobile { 
        display: flex;
        align-items: center;
        font-size: 0.9rem;
    }
    #sidebarMobile .user-info-mobile img.profile-pic-mobile-header {
        width: 30px;
        height: 30px;
        object-fit: cover;
        border-radius: 50%;
        margin-right: 0.5rem;
        border: 1px solid rgba(255, 255, 255, 0.5);
    }
    #sidebarMobile .btn-close-white { 
        filter: none;
    }
    #sidebarMobile .offcanvas-body {
        padding: 1rem;
    }
    #sidebarMobile .input-group .form-control-sm {
        font-size: 0.875rem;
        background-color: rgba(255,255,255,0.1); 
        color: #fff;
        border-color: rgba(255,255,255,0.3);
    }
    #sidebarMobile .input-group .form-control-sm::placeholder {
        color: rgba(255,255,255,0.7);
    }
     #sidebarMobile .input-group .btn-sm {
        font-size: 0.875rem;
        border-color: rgba(255,255,255,0.3);
        color: #fff; 
    }
    #sidebarMobile .input-group .btn-sm:hover {
        background-color: rgba(255,255,255,0.2);
    }
    #sidebarMobile .nav-link {
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        border-radius: 0.3rem;
        display: flex;
        align-items: center;
        color: #f8f9fa; 
        transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
    }
    #sidebarMobile .nav-link i.fas,
    #sidebarMobile .nav-link i.fab {
        margin-right: 0.8rem;
        width: 20px;
        text-align: center;
    }
    #sidebarMobile .nav-link.active,
    #sidebarMobile .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.2); 
        color: #fff !important;
    }
    #sidebarMobile .nav-link.active {
        background-color: rgba(255, 255, 255, 0.25); 
        font-weight: bold;
    }
    #sidebarMobile .badge.bg-info.text-dark { 
        background-color: #fff !important;
        color: #0d6efd !important;
    }
    #sidebarMobile .sidebar-logout-section-mobile {
        padding-top: 0.75rem;
        margin-top: auto;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
    }
     #sidebarMobile .sidebar-logout-section-mobile .btn-outline-light {
        font-size: 0.95rem;
        padding: 0.6rem 1rem;
        border-color: rgba(255,255,255,0.5);
        color: #fff;
     }
     #sidebarMobile .sidebar-logout-section-mobile .btn-outline-light:hover {
        background-color: rgba(255,255,255,0.1);
     }
    #profilePicPreviewModalMobile {
        width: 120px;
        height: 120px;
        border: 1px solid #dee2e6;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 1rem auto;
        display: block;
    }
    #changeProfilePicModalMobile .modal-content {
        background-color: #0a58ca; 
        color: #fff;
    }
    #changeProfilePicModalMobile .modal-header,
    #changeProfilePicModalMobile .modal-footer {
        border-color: rgba(255, 255, 255, 0.2);
    }
    #changeProfilePicModalMobile .form-text { 
        color: rgba(255,255,255,0.8) !important;
    }
    #changeProfilePicModalMobile .form-control { 
        background-color: rgba(255,255,255,0.1);
        color: #fff;
        border-color: rgba(255,255,255,0.3);
    }
    #changeProfilePicModalMobile .form-control::placeholder {
        color: rgba(255,255,255,0.7);
    }
</style>

<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarMobile" aria-labelledby="sidebarMobileLabel">
    <div class="offcanvas-header">
        <div class="offcanvas-title-container" id="sidebarMobileLabel">
            <div class="user-info-mobile">
                <a href="#" class="sidebar-profile-image-wrapper-mobile"
                   data-bs-toggle="modal" data-bs-target="#changeProfilePicModalMobile"
                   title="Cambiar foto de perfil">
                    <img src="<?php echo $sidebar_profile_pic_src_mobile; ?>?t=<?php echo time(); ?>" alt="Perfil" class="profile-pic-mobile-header">
                </a>
                <span><?php echo $sidebar_admin_name_display_mobile; ?></span>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <form action="<?php echo ($path_to_root ?: './'); ?>global_search.php" method="GET" class="mb-3">
            <div class="input-group">
                <input type="text" name="term" class="form-control form-control-sm" placeholder="Buscar..." aria-label="Buscar" value="<?php echo isset($_GET['term']) && $current_page_basename_mobile == 'global_search.php' ? htmlspecialchars($_GET['term']) : ''; ?>">
                <button class="btn btn-sm btn-outline-light" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>

        <ul class="nav nav-pills flex-column mb-auto">
            <?php if (user_has_permission('dashboard', $user_permissions_json_mobile, $user_role_mobile)): ?>
                <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>dashboard.php" class="nav-link <?php if ($is_dashboard_active_mobile) echo 'active'; ?>"><i class="fas fa-home fa-fw"></i>Inicio</a></li>
            <?php endif; ?>
            <?php if (user_has_permission('profile', $user_permissions_json_mobile, $user_role_mobile)): ?>
                <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>profile.php" class="nav-link <?php if ($is_profile_active_mobile) echo 'active'; ?>"><i class="fas fa-user-circle fa-fw"></i>Mi Perfil</a></li>
            <?php endif; ?>
            <?php if (user_has_permission('notifications', $user_permissions_json_mobile, $user_role_mobile)): ?>
                <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>notifications.php" class="nav-link <?php if ($is_notifications_active_mobile) echo 'active'; ?>"><i class="fas fa-bell fa-fw text-warning"></i>Notificaciones <?php if ($new_notifications_count_mobile > 0): ?><span class="badge rounded-pill bg-danger ms-1"><?php echo $new_notifications_count_mobile; ?></span><?php endif; ?></a></li>
            <?php endif; ?>

            <?php if (user_has_permission('patients', $user_permissions_json_mobile, $user_role_mobile)): ?>
                <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>patients/index.php" class="nav-link <?php if ($is_patients_list_active_mobile && !$is_patients_create_active_mobile && !$is_patients_history_active_mobile && !$is_patients_odontogram_active_mobile) echo 'active'; ?>"><i class="fas fa-user-injured fa-fw"></i>Pacientes</a></li>
                <?php if (user_has_permission('patients_create', $user_permissions_json_mobile, $user_role_mobile)): ?>
                    <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>patients/create.php" class="nav-link ps-4 small <?php if ($is_patients_create_active_mobile) echo 'active fw-bold'; ?>"><i class="fas fa-angle-right me-1"></i>Agregar Paciente</a></li>
                <?php endif; ?>
                 <?php if (user_has_permission('patients_history', $user_permissions_json_mobile, $user_role_mobile)): ?>
                    <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>patients/history_select.php" class="nav-link ps-4 small <?php if ($is_patients_history_active_mobile) echo 'active fw-bold'; ?>"><i class="fas fa-angle-right me-1"></i>Historial Clínico</a></li>
                 <?php endif; ?>
                 <?php if (user_has_permission('patients_odontogram', $user_permissions_json_mobile, $user_role_mobile)): ?>
                    <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>patients/odontogram_select.php" class="nav-link ps-4 small <?php if ($is_patients_odontogram_active_mobile) echo 'active fw-bold'; ?>"><i class="fas fa-angle-right me-1"></i>Odontograma</a></li>
                 <?php endif; ?>
            <?php endif; ?>

            <?php if (user_has_permission('appointments', $user_permissions_json_mobile, $user_role_mobile)): ?>
                <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>appointments/index.php" class="nav-link <?php if ($is_appointments_calendar_active_mobile && !$is_appointments_create_active_mobile && !$is_appointments_list_active_mobile) echo 'active'; ?>"><i class="fas fa-calendar-alt fa-fw"></i>Turnos</a></li>
                <?php if (user_has_permission('appointments_create', $user_permissions_json_mobile, $user_role_mobile)): ?>
                    <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>appointments/create.php" class="nav-link ps-4 small <?php if ($is_appointments_create_active_mobile) echo 'active fw-bold'; ?>"><i class="fas fa-angle-right me-1"></i>Crear Turno</a></li>
                <?php endif; ?>
                <?php if (user_has_permission('appointments_list', $user_permissions_json_mobile, $user_role_mobile)): ?>
                     <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>appointments/list.php" class="nav-link ps-4 small <?php if ($is_appointments_list_active_mobile) echo 'active fw-bold'; ?>"><i class="fas fa-angle-right me-1"></i>Listado de Turnos</a></li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (user_has_permission('messaging_admin', $user_permissions_json_mobile, $user_role_mobile)): ?>
                <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>messages/index.php" class="nav-link <?php if ($is_messaging_active_mobile) echo 'active'; ?>"><i class="fas fa-envelope-open-text fa-fw"></i>Mensajería <?php if ($unread_staff_messages_count_mobile > 0): ?><span class="badge rounded-pill bg-info text-dark ms-1"><?php echo $unread_staff_messages_count_mobile; ?></span><?php endif; ?></a></li>
            <?php endif; ?>

            <?php if (user_has_permission('billing', $user_permissions_json_mobile, $user_role_mobile)): ?>
                <li class="nav-item">
                    <a href="<?php echo ($path_to_root ?: './'); ?>billing/index.php" class="nav-link <?php if ($is_billing_index_active_mobile && !$is_estimates_page_active_mobile && !$is_billing_customize_pdf_active_mobile) echo 'active'; ?>">
                        <i class="fas fa-file-invoice-dollar fa-fw"></i>Cobros
                    </a>
                </li>
                <?php if (user_has_permission('estimates', $user_permissions_json_mobile, $user_role_mobile)): ?>
                    <li class="nav-item"> 
                        <a href="<?php echo ($path_to_root ?: './'); ?>estimates/index.php" class="nav-link ps-4 small <?php if ($is_estimates_page_active_mobile) echo 'active fw-bold'; ?>">
                            <i class="fas fa-angle-right me-1"></i>Presupuestos
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (user_has_permission('billing_customize_pdf', $user_permissions_json_mobile, $user_role_mobile)): ?>
                    <li class="nav-item"> 
                        <a href="<?php echo ($path_to_root ?: './'); ?>billing/customize_pdf.php" class="nav-link ps-4 small <?php if ($is_billing_customize_pdf_active_mobile) echo 'active fw-bold'; ?>">
                            <i class="fas fa-angle-right me-1"></i>Personalizar PDF
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (user_has_permission('reports', $user_permissions_json_mobile, $user_role_mobile)): ?>
                <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>reports/index.php" class="nav-link <?php if ($is_reports_active_mobile) echo 'active'; ?>"><i class="fas fa-chart-line fa-fw"></i>Reportes</a></li>
            <?php endif; ?>

            <?php if (user_has_permission('inventory', $user_permissions_json_mobile, $user_role_mobile)): ?>
                <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>inventory/index.php" class="nav-link <?php if ($is_inventory_active_mobile) echo 'active'; ?>"><i class="fas fa-boxes fa-fw"></i>Insumos</a></li>
            <?php endif; ?>

            <?php if ($user_role_mobile === 'superadmin'): ?>
                <?php if (user_has_permission('employees', $user_permissions_json_mobile, $user_role_mobile)): ?>
                    <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>employees/index.php" class="nav-link <?php if ($is_employees_active_mobile) echo 'active'; ?>"><i class="fas fa-users-cog fa-fw"></i>Empleados</a></li>
                <?php endif; ?>
                <?php if (user_has_permission('system', $user_permissions_json_mobile, $user_role_mobile)): ?>
                     <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>system/database_backup.php" class="nav-link <?php if ($is_system_active_mobile) echo 'active'; ?>"><i class="fas fa-cogs fa-fw"></i>Sistema</a></li>
                <?php endif; ?>
            <?php endif; ?>

            <li class="nav-item"><a href="<?php echo ($path_to_root ?: './'); ?>user_guide.php" class="nav-link <?php if ($is_user_guide_active_mobile) echo 'active'; ?>"><i class="fas fa-book-reader fa-fw"></i>Guía</a></li>
        </ul>

        <div class="sidebar-logout-section-mobile mt-auto">
            <hr class="text-secondary d-none">
            <div class="pt-2">
                <a href="<?php echo ($path_to_root ?: './'); ?>logout.php" class="btn btn-outline-light w-100"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="changeProfilePicModalMobile" tabindex="-1" aria-labelledby="changeProfilePicModalMobileLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changeProfilePicModalMobileLabel"><i class="fas fa-camera-retro me-2"></i>Cambiar Foto de Perfil</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?php echo ($path_to_root ?: './'); ?>profile.php" method="POST" enctype="multipart/form-data" id="profilePicModalFormMobile">
        <div class="modal-body">
          <div class="text-center mb-3">
            <img src="<?php echo $sidebar_profile_pic_src_mobile; ?>?t=<?php echo time(); ?>" id="profilePicPreviewModalMobile" alt="Vista previa">
          </div>
          <div class="mb-3">
            <label for="profile_image_file_modal_mobile" class="form-label">Seleccionar nueva imagen:</label>
            <input class="form-control form-control-sm" type="file" id="profile_image_file_modal_mobile" name="profile_image_file" accept="image/png, image/jpeg, image/gif" required>
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
document.addEventListener('DOMContentLoaded', function() {
    const profileImageInputModalMobile = document.getElementById('profile_image_file_modal_mobile');
    const profilePicPreviewModalMobile = document.getElementById('profilePicPreviewModalMobile');
    let originalModalPicSrcMobile = profilePicPreviewModalMobile ? profilePicPreviewModalMobile.src : '';
    const modalElementMobile = document.getElementById('changeProfilePicModalMobile');

    if(modalElementMobile && profilePicPreviewModalMobile) {
        modalElementMobile.addEventListener('show.bs.modal', function () {
            const currentSidebarPicMobile = document.querySelector('#sidebarMobile .user-info-mobile img.profile-pic-mobile-header');
            if(currentSidebarPicMobile) {
                originalModalPicSrcMobile = currentSidebarPicMobile.src;
                profilePicPreviewModalMobile.src = originalModalPicSrcMobile;
            }
            if(profileImageInputModalMobile) profileImageInputModalMobile.value = null;
        });
    }

    if (profileImageInputModalMobile && profilePicPreviewModalMobile) {
        profileImageInputModalMobile.onchange = evt => {
            const [file] = profileImageInputModalMobile.files;
            if (file) {
                profilePicPreviewModalMobile.src = URL.createObjectURL(file);
            } else {
                profilePicPreviewModalMobile.src = originalModalPicSrcMobile;
            }
        }
    }
});
</script>