<?php
// portal/_portal_header.php
// Se asume que session_start() ya ha sido llamado y config.php incluido
// y que $_SESSION['patient_id'] y $_SESSION['patient_fname'] están disponibles.

if (session_status() === PHP_SESSION_NONE) { // Por si acaso
    session_start();
}

// Asegurar que config.php (y por ende $mysqli) esté cargado
// Esto es una salvaguarda. La página que incluye este header ya debería haberlo hecho.
if ((!isset($mysqli) || !($mysqli instanceof mysqli)) && file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}


$patient_fname_header = $_SESSION['patient_fname'] ?? 'Paciente';
$patient_id_for_header = $_SESSION['patient_id'] ?? null;

// $path_to_root_portal se define para los enlaces. Como este archivo y las páginas del portal
// están en el mismo directorio 'portal/', los enlaces son directos.
$path_to_root_portal = ''; // O './'

// --- NUEVO: Lógica para el Badge de Mensajes No Leídos por el Paciente ---
$unread_patient_messages_count = 0;
if ($patient_id_for_header && isset($mysqli) && $mysqli instanceof mysqli) {
    $stmt_unread_patient_msg = $mysqli->prepare("
        SELECT COUNT(*) as c 
        FROM messages 
        WHERE patient_id = ? 
          AND sent_by_patient = FALSE 
          AND read_by_patient_at IS NULL
    ");
    if ($stmt_unread_patient_msg) {
        $stmt_unread_patient_msg->bind_param("i", $patient_id_for_header);
        $stmt_unread_patient_msg->execute();
        $res_unread_patient_msg = $stmt_unread_patient_msg->get_result();
        $unread_patient_messages_count = $res_unread_patient_msg->fetch_assoc()['c'] ?? 0;
        $stmt_unread_patient_msg->close();
    }
}
// --- FIN LÓGICA BADGE MENSAJES PACIENTE ---


// Determinar el enlace activo basado en $currentPage (definida en la página que incluye este header)
$is_portal_home_active = (isset($currentPage) && $currentPage === 'dashboard');
$is_portal_my_appointments_active = (isset($currentPage) && $currentPage === 'my_appointments');
$is_portal_my_messages_active = (isset($currentPage) && $currentPage === 'my_messages');
$is_portal_my_profile_active = (isset($currentPage) && $currentPage === 'my_profile');

?>
<nav class="navbar navbar-expand-lg portal-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $path_to_root_portal; ?>index.php">
            <i class="fas fa-tooth"></i> Portal del Paciente
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#patientPortalNav" aria-controls="patientPortalNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="patientPortalNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item">
                    <a class="nav-link <?php if($is_portal_home_active) echo 'active fw-bold'; ?>" aria-current="page" href="<?php echo $path_to_root_portal; ?>index.php">
                        <i class="fas fa-home me-1"></i>Inicio
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($is_portal_my_appointments_active) echo 'active fw-bold'; ?>" href="<?php echo $path_to_root_portal; ?>my_appointments.php">
                        <i class="fas fa-calendar-check me-1"></i>Mis Turnos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($is_portal_my_messages_active) echo 'active fw-bold'; ?>" href="<?php echo $path_to_root_portal; ?>messages.php">
                        <i class="fas fa-envelope me-1"></i>Mis Mensajes
                        <?php if ($unread_patient_messages_count > 0): ?>
                            <span class="badge rounded-pill bg-danger ms-1"><?php echo $unread_patient_messages_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($is_portal_my_profile_active) echo 'active fw-bold'; ?>" href="<?php echo $path_to_root_portal; ?>my_profile.php">
                        <i class="fas fa-user-edit me-1"></i>Mi Perfil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $path_to_root_portal; ?>logout.php"><i class="fas fa-sign-out-alt me-1"></i>Cerrar Sesión</a>
                </li>
            </ul>
            <span class="navbar-text ms-lg-3 d-none d-lg-inline">
                Hola, <?php echo htmlspecialchars($patient_fname_header); ?>
            </span>
        </div>
    </div>
</nav>