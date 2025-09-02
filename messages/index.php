<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../config.php';

$path_to_root = '../';
$current_admin_id = $_SESSION['admin_id'];

if (!user_has_permission('messaging_admin', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
     if(!in_array($_SESSION['admin_role'], ['superadmin', 'admin'])){
        header('Location: ../dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
        exit;
     }
}

$error_message_new = '';
$success_message_new = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_new_message_from_admin'])) {
    $new_message_patient_id = filter_input(INPUT_POST, 'new_message_patient_id', FILTER_VALIDATE_INT);
    $new_message_content = trim($_POST['new_message_content'] ?? '');

    if (!$new_message_patient_id) {
        $error_message_new = 'Debe seleccionar un paciente.';
    } elseif (empty($new_message_content)) {
        $error_message_new = 'El contenido del mensaje no puede estar vacío.';
    } else {
        $stmt_send_new = $mysqli->prepare("INSERT INTO messages (patient_id, admin_user_id, sent_by_patient, message_content, read_by_patient_at) VALUES (?, ?, FALSE, ?, NULL)");
        if ($stmt_send_new) {
            $stmt_send_new->bind_param("iis", $new_message_patient_id, $current_admin_id, $new_message_content);
            if ($stmt_send_new->execute()) {
                header('Location: chat.php?patient_id=' . $new_message_patient_id . '&send_status=success_staff_init');
                exit;
            } else {
                $error_message_new = 'Error al enviar el nuevo mensaje: ' . $stmt_send_new->error;
            }
            $stmt_send_new->close();
        } else {
            $error_message_new = 'Error al preparar el nuevo mensaje: ' . $mysqli->error;
        }
    }
}

$conversations = [];
$stmt_conversations = $mysqli->prepare("
    SELECT
        p.id as patient_id,
        p.fname as patient_fname,
        p.lname as patient_lname,
        MAX(m.sent_at) as last_message_time,
        (SELECT message_content FROM messages
         WHERE patient_id = p.id ORDER BY sent_at DESC LIMIT 1) as last_message_content,
        (SELECT sent_by_patient FROM messages
         WHERE patient_id = p.id ORDER BY sent_at DESC LIMIT 1) as last_message_sent_by_patient,
        SUM(CASE WHEN m.sent_by_patient = TRUE AND m.read_by_staff_at IS NULL THEN 1 ELSE 0 END) as unread_from_patient_count
    FROM messages m
    JOIN patients p ON m.patient_id = p.id
    GROUP BY p.id, p.fname, p.lname
    ORDER BY unread_from_patient_count DESC, last_message_time DESC
");

if ($stmt_conversations) {
    $stmt_conversations->execute();
    $result_conversations = $stmt_conversations->get_result();
    while ($row = $result_conversations->fetch_assoc()) {
        $conversations[] = $row;
    }
    $stmt_conversations->close();
} else {
    error_log("Error al preparar consulta de conversaciones: " . $mysqli->error);
}

$all_patients_for_select = [];
$patients_query_modal = $mysqli->query("SELECT id, fname, lname, dni FROM patients ORDER BY lname ASC, fname ASC");
if ($patients_query_modal) {
    while ($patient_row_modal = $patients_query_modal->fetch_assoc()) {
        $all_patients_for_select[] = $patient_row_modal;
    }
    $patients_query_modal->free();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mensajería con Pacientes - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body{overflow-x:hidden; background-color: #f8f9fa;}
        #sidebar{min-height:100vh;}
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.075); }
        .conversation-item {
            transition: background-color 0.2s ease-in-out, border-left-color 0.2s ease-in-out;
            border-left: 4px solid transparent;
            padding: 1rem 1.25rem;
        }
        .conversation-item:hover {
            background-color: #eef1f5;
            border-left-color: var(--bs-primary-border-subtle);
        }
        .conversation-item.list-group-item-warning {
            border-left-color: var(--bs-danger);
        }
        .conversation-item.list-group-item-warning:hover {
            background-color: #fff3cd;
        }
        .patient-avatar-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-right: 1rem;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            border: 2px solid #dee2e6;
            flex-shrink: 0;
        }
        .conversation-item .message-preview {
            color: #6c757d;
            font-size: 0.9em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 90%;
        }
        .conversation-item .unread-badge {
            font-size: 0.75em;
            padding: .3em .6em;
            vertical-align: middle;
        }
        .conversation-item .last-message-time {
            font-size: 0.8em;
            color: #6c757d;
        }
        .card-header-custom {
            background-color: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
        }
        .card-header-custom h3 {
            font-weight: 600;
            color: #343a40;
        }
        .list-group-flush .list-group-item:last-child {
            border-bottom-width: 0;
        }
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
        if (isMobileDevice()) { // Asume que isMobileDevice() está en config.php
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

        <main class="col-12 <?php if (!isMobileDevice()) echo 'col-md-9 col-lg-10'; ?> p-4">
            <div class="card main-content-card shadow-sm rounded-3">
                <div class="card-header-custom">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h3 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-comments me-2 text-primary text-info"></i>Mensajería Clínica
                        </h3>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newConversationModal">
                            <i class="fas fa-plus-circle me-1"></i> Nuevo Mensaje
                        </button>
                    </div>
                </div>
                <div class="card-body p-lg-3 p-2">
                    <?php if (isset($_GET['status']) && isset($_GET['msg'])): ?>
                        <div class="alert alert-<?php echo (strpos($_GET['status'], 'success') !== false ? 'success' : 'danger'); ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars(urldecode($_GET['msg'])); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error_message_new): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error_message_new); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($conversations)): ?>
                        <div class="alert alert-light text-center mt-3 border shadow-sm">
                            <i class="fas fa-envelope-open fa-3x mb-3 d-block text-muted"></i>
                            <p class="lead mb-1">No hay conversaciones activas.</p>
                            <p class="text-muted">Inicia una nueva conversación usando el botón "Nuevo Mensaje".</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($conversations as $convo):
                                $last_msg_time_obj = new DateTime($convo['last_message_time']);
                                $is_unread_by_staff = ($convo['unread_from_patient_count'] > 0);
                            ?>
                            <a href="chat.php?patient_id=<?php echo $convo['patient_id']; ?>" class="list-group-item list-group-item-action conversation-item <?php if($is_unread_by_staff) echo 'list-group-item-warning'; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="patient-avatar-placeholder">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-2" style="min-width: 0;">
                                        <div class="d-flex w-100 justify-content-between flex-wrap">
                                            <h5 class="mb-1 <?php if($is_unread_by_staff) echo 'fw-bold text-danger'; else echo 'fw-semibold'; ?> me-2">
                                                <?php echo htmlspecialchars($convo['patient_fname'] . ' ' . $convo['patient_lname']); ?>
                                            </h5>
                                            <small class="last-message-time"><?php echo $last_msg_time_obj->format('d/m H:i'); ?></small>
                                        </div>
                                        <p class="mb-0 message-preview">
                                            <?php if ($convo['last_message_sent_by_patient']): ?>
                                                <i class="fas fa-reply text-info me-1" title="Paciente escribió"></i>
                                            <?php else: ?>
                                                <i class="fas fa-share text-success me-1" title="Clínica escribió"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars(mb_strimwidth($convo['last_message_content'], 0, 70, "...")); ?>
                                        </p>
                                    </div>
                                     <?php if ($is_unread_by_staff): ?>
                                        <span class="badge bg-danger rounded-pill ms-2 ms-sm-3 unread-badge align-self-center"><?php echo $convo['unread_from_patient_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="newConversationModal" tabindex="-1" aria-labelledby="newConversationModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="newConversationModalLabel"><i class="fas fa-comment-medical me-2"></i>Enviar Nuevo Mensaje</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="index.php">
            <div class="modal-body">
              <div class="mb-3">
                <label for="new_message_patient_id" class="form-label">Seleccionar Paciente <span class="text-danger">*</span></label>
                <select class="form-select form-select-lg" id="new_message_patient_id" name="new_message_patient_id" required>
                    <option value="">-- Elija un paciente --</option>
                    <?php foreach($all_patients_for_select as $patient_opt): ?>
                        <option value="<?php echo $patient_opt['id']; ?>">
                            <?php echo htmlspecialchars($patient_opt['lname'] . ', ' . $patient_opt['fname'] . ($patient_opt['dni'] ? ' (DNI: '.$patient_opt['dni'].')' : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label for="new_message_content" class="form-label">Mensaje <span class="text-danger">*</span></label>
                <textarea class="form-control form-control-lg" id="new_message_content" name="new_message_content" rows="4" required placeholder="Escriba su mensaje aquí..."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="send_new_message_from_admin" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Enviar Mensaje</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var pageAlerts = document.querySelectorAll('.card-body > .alert-dismissible');
            pageAlerts.forEach(function(alertEl) {
                setTimeout(function() {
                    if (document.body.contains(alertEl)) {
                        var bsAlert = bootstrap.Alert.getInstance(alertEl);
                        if (bsAlert) { bsAlert.close(); } else { new bootstrap.Alert(alertEl).close(); }
                    }
                }, 7000);
            });
        });
    </script>
</body>
</html>