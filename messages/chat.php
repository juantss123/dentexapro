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
$current_admin_id = $_SESSION['admin_id'];
$current_admin_name = $_SESSION['admin_name'] ?? 'Clínica'; 

$patient_id_chat = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

if (!$patient_id_chat) {
    header('Location: index.php?status=error&msg=' . urlencode('ID de paciente no válido.'));
    exit;
}

if (!user_has_permission('messaging_admin', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
     if(!in_array($_SESSION['admin_role'], ['superadmin', 'admin'])){
        header('Location: ../dashboard.php?status=error&msg=' . urlencode('Acceso restringido a la mensajería.'));
        exit;
     }
}

$stmt_patient_chat = $mysqli->prepare("SELECT fname, lname FROM patients WHERE id = ?");
$patient_chat_name = "Paciente Desconocido";
if ($stmt_patient_chat) {
    $stmt_patient_chat->bind_param("i", $patient_id_chat);
    $stmt_patient_chat->execute();
    $result_patient_chat = $stmt_patient_chat->get_result();
    if ($patient_info_chat = $result_patient_chat->fetch_assoc()) {
        $patient_chat_name = htmlspecialchars($patient_info_chat['fname'] . ' ' . $patient_info_chat['lname']);
    } else {
        header('Location: index.php?status_user_action=error&msg_user_action=' . urlencode('Paciente no encontrado para iniciar chat.'));
        exit;
    }
    $stmt_patient_chat->close();
} else {
    error_log("Error al preparar consulta de datos del paciente para chat: " . $mysqli->error);
    die("Error crítico al cargar datos del paciente.");
}

$stmt_mark_staff_read_initial = $mysqli->prepare("UPDATE messages SET read_by_staff_at = NOW() WHERE patient_id = ? AND sent_by_patient = TRUE AND read_by_staff_at IS NULL");
if ($stmt_mark_staff_read_initial) {
    $stmt_mark_staff_read_initial->bind_param("i", $patient_id_chat);
    $stmt_mark_staff_read_initial->execute();
    if ($stmt_mark_staff_read_initial->error) {
        error_log("Error al marcar mensajes como leídos por staff: " . $stmt_mark_staff_read_initial->error);
    }
    $stmt_mark_staff_read_initial->close();
} else {
    error_log("Error al preparar consulta para marcar mensajes como leídos: " . $mysqli->error);
}

$error_message_chat = '';
$success_message_chat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Descomentar para depurar el contenido de POST
    // echo "<pre style='background:white; color:black; position:fixed; top:0;left:0;padding:20px;z-index:9999;'>POST DATA: "; print_r($_POST); echo "</pre>"; exit;

    if (isset($_POST['send_staff_message'])) {
        $message_content_staff = trim($_POST['message_content_staff'] ?? '');
        if (empty($message_content_staff)) {
            $error_message_chat = 'El contenido del mensaje no puede estar vacío.';
        } else {
            $stmt_send_staff = $mysqli->prepare("INSERT INTO messages (patient_id, admin_user_id, sent_by_patient, message_content, read_by_patient_at) VALUES (?, ?, FALSE, ?, NULL)");
            if ($stmt_send_staff) {
                $stmt_send_staff->bind_param("iis", $patient_id_chat, $current_admin_id, $message_content_staff);
                if ($stmt_send_staff->execute()) {
                    header("Location: chat.php?patient_id=" . $patient_id_chat . "&send_status=success_sent");
                    exit;
                } else { $error_message_chat = 'Error al enviar el mensaje: ' . $stmt_send_staff->error; }
                $stmt_send_staff->close();
            } else { $error_message_chat = 'Error al preparar el mensaje: ' . $mysqli->error; }
        }
    } elseif (isset($_POST['edit_staff_message'])) {
        $message_id_to_edit_raw = $_POST['edit_message_id'] ?? null;
        $edited_content = trim($_POST['edit_message_content'] ?? '');
        $message_id_to_edit = null;

        if ($message_id_to_edit_raw !== null && filter_var($message_id_to_edit_raw, FILTER_VALIDATE_INT) !== false && intval($message_id_to_edit_raw) > 0) {
            $message_id_to_edit = intval($message_id_to_edit_raw);
        }
        
        if (!$message_id_to_edit) {
            $error_message_chat = "ID de mensaje inválido para editar. (Recibido: '" . htmlspecialchars($message_id_to_edit_raw ?? 'nada') . "')";
        } elseif (empty($edited_content)) {
            $error_message_chat = "El contenido del mensaje editado no puede estar vacío.";
        } else {
            $stmt_edit = $mysqli->prepare("UPDATE messages SET message_content = ?, edited_at = NOW() WHERE id = ? AND admin_user_id = ? AND sent_by_patient = FALSE AND is_deleted_by_staff = FALSE");
            if ($stmt_edit) {
                $stmt_edit->bind_param("sii", $edited_content, $message_id_to_edit, $current_admin_id);
                if ($stmt_edit->execute()) {
                    if ($stmt_edit->affected_rows > 0) {
                        header("Location: chat.php?patient_id=" . $patient_id_chat . "&send_status=success_edited");
                        exit;
                    } else {
                        $error_message_chat = "No se pudo editar el mensaje (quizás no es tuyo, ya fue eliminado, no existe o no hubo cambios).";
                    }
                } else { $error_message_chat = "Error al editar el mensaje: " . $stmt_edit->error; }
                $stmt_edit->close();
            } else { $error_message_chat = "Error al preparar edición: " . $mysqli->error; }
        }
    } elseif (isset($_POST['delete_staff_message'])) {
        $message_id_to_delete = filter_input(INPUT_POST, 'delete_message_id', FILTER_VALIDATE_INT);
        if ($message_id_to_delete && $message_id_to_delete > 0) {
            $stmt_delete = $mysqli->prepare("UPDATE messages SET is_deleted_by_staff = TRUE, edited_at = NOW() WHERE id = ? AND admin_user_id = ? AND sent_by_patient = FALSE");
             if ($stmt_delete) {
                $stmt_delete->bind_param("ii", $message_id_to_delete, $current_admin_id);
                if ($stmt_delete->execute()) {
                    if ($stmt_delete->affected_rows > 0) { header("Location: chat.php?patient_id=" . $patient_id_chat . "&send_status=success_deleted"); exit; } 
                    else { $error_message_chat = "No se pudo eliminar (no es tuyo o ya eliminado)."; }
                } else { $error_message_chat = "Error al eliminar: " . $stmt_delete->error; }
                $stmt_delete->close();
            } else { $error_message_chat = "Error al preparar eliminación: " . $mysqli->error; }
        } else {
            $error_message_chat = "ID de mensaje inválido para eliminar.";
        }
    }
}

if(isset($_GET['send_status'])){
    if($_GET['send_status'] === 'success_sent' && empty($success_message_chat)) $success_message_chat = '¡Mensaje enviado con éxito!';
    if($_GET['send_status'] === 'success_edited' && empty($success_message_chat)) $success_message_chat = '¡Mensaje editado con éxito!';
    if($_GET['send_status'] === 'success_deleted' && empty($success_message_chat)) $success_message_chat = '¡Mensaje eliminado con éxito!';
}

$messages_chat = [];
$last_message_id_on_page_admin = 0; 
$stmt_messages_chat = $mysqli->prepare("
    SELECT m.*, 
           IF(m.sent_by_patient = FALSE AND au.name IS NOT NULL, au.name, ?) as sender_display_name, 
           p.fname as patient_fname_sender
    FROM messages m
    LEFT JOIN admin_users au ON m.admin_user_id = au.id AND m.sent_by_patient = FALSE
    JOIN patients p ON m.patient_id = p.id 
    WHERE m.patient_id = ?
    ORDER BY m.sent_at ASC
");
if ($stmt_messages_chat) {
    $default_clinic_name_for_sender = "Clínica"; 
    $stmt_messages_chat->bind_param("si", $default_clinic_name_for_sender, $patient_id_chat);
    $stmt_messages_chat->execute();
    $result_messages_chat = $stmt_messages_chat->get_result();
    while ($row = $result_messages_chat->fetch_assoc()) {
        $messages_chat[] = $row;
        if ($row['id'] > $last_message_id_on_page_admin) { 
            $last_message_id_on_page_admin = $row['id'];
        }
    }
    $stmt_messages_chat->close();
} else { error_log("Error al preparar consulta de historial de chat: " . $mysqli->error); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat con <?php echo $patient_chat_name; ?> - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body{overflow-y: scroll; background-color: #f8f9fa;}
        #sidebar{min-height:100vh;}
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
        .chat-page-container { display: flex; flex-direction: column; height: calc(100vh - (56px + 3rem)); max-height: 700px; min-height: 500px;}
        .chat-header-admin { padding: 0.8rem 1.25rem; background-color: #495057; color: white; border-top-left-radius: .375rem; border-top-right-radius: .375rem; display: flex; justify-content: space-between; align-items: center; }
        .chat-header-admin h5 { margin-bottom: 0; font-size: 1.15rem; font-weight: 600;}
        .chat-header-admin .btn-light { font-size: 0.85rem; padding: 0.25rem 0.75rem;}
        .chat-messages-admin { flex-grow: 1; padding: 1rem; overflow-y: auto; background-color: #fff; border-left: 1px solid #dee2e6; border-right: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6; }
        .message-bubble { padding: .65rem 1rem; border-radius: 18px; margin-bottom: .75rem; max-width: 70%; word-wrap: break-word; line-height: 1.4; box-shadow: 0 1px 2px rgba(0,0,0,0.1); position: relative; }
        .message-bubble .sender-name { font-size: 0.7rem; font-weight: bold; margin-bottom: 0.2rem; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .message-bubble .message-content-text { font-size: 0.95rem; }
        .message-bubble .message-time { font-size: 0.65rem; color: #777; display: block; text-align: right; margin-top: 0.3rem; }
        .message-staff { background-color: #e2f0ff; margin-left: auto; border-bottom-right-radius: .375rem; }
        .message-staff .sender-name { color: #0056b3; }
        .message-patient { background-color: #f1f3f5;  margin-right: auto; border-bottom-left-radius: .375rem; }
        .message-patient .sender-name { color: #5a6268; }
        .chat-input-form-admin { padding: 1rem; background-color: #f0f2f5; border-top: 1px solid #dee2e6; border-bottom-left-radius: .375rem; border-bottom-right-radius: .375rem; }
        .chat-input-form-admin textarea { border-radius: 18px; padding: 0.75rem 1rem; border-color: #ced4da; }
        .chat-input-form-admin .btn-primary { border-radius: 18px; padding: 0.75rem 1.25rem; font-weight: 500; }
        .chat-input-form-admin .btn-primary i { font-size: 0.9rem; }
        .message-actions { position: absolute; top: 2px; opacity: 0; transition: opacity 0.2s ease-in-out; }
        .message-bubble:hover .message-actions { opacity: 0.7; }
        .message-bubble:hover .message-actions:hover { opacity: 1; }
        .message-staff .message-actions { right: 8px; }
        .message-actions .btn-action { background: none; border: none; padding: 0.1rem 0.3rem; font-size: 0.75rem; color: #6c757d; }
        .message-actions .btn-action:hover { color: #000; }
        .message-edited-indicator { font-size: 0.6rem; color: #999; font-style: italic; margin-left: 5px;}
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .chevron{transition:transform .2s;}
        .nav-link[aria-expanded="true"] .chevron{transform:rotate(180deg);}
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
if (isMobileDevice()) { // Asume que isMobileDevice() está en config.php
    require_once ($path_to_root = '../') . '_sidebarmovil.php';

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
        
        <main class="col-12 col-md-9 col-lg-10 p-4">
            <div class="card main-content-card shadow-sm rounded-3 chat-page-container">
                <div class="chat-header-admin">
                    <h5><i class="fas fa-comments me-2"></i>Chat con: <?php echo $patient_chat_name; ?></h5>
                    <a href="index.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left me-1"></i>Conversaciones</a>
                </div>
                <div class="chat-messages-admin" id="chatMessagesAreaAdmin">
                    <?php if (empty($messages_chat)): ?>
                        <p class="text-center text-muted mt-5 pt-5 no-messages-yet-admin"><i class="fas fa-envelope-open fa-3x mb-3 d-block"></i>No hay mensajes.<br>Envía el primero.</p>
                    <?php else: ?>
                        <?php foreach ($messages_chat as $msg): 
                            if (!$msg['sent_by_patient'] && $msg['is_deleted_by_staff']) {
                                continue; 
                            }
                            $msg_datetime = new DateTime($msg['sent_at']);
                            $is_sent_by_patient_chat = $msg['sent_by_patient'];
                            $sender_name_display = $is_sent_by_patient_chat ? htmlspecialchars($msg['patient_fname_sender']) : htmlspecialchars($msg['sender_display_name']);
                        ?>
                        <div class="message-bubble <?php echo $is_sent_by_patient_chat ? 'message-patient' : 'message-staff'; ?>" data-message-id="<?php echo $msg['id']; ?>">
                            <span class="sender-name"><?php echo $sender_name_display; ?></span>
                            <div class="message-content-text">
                                <?php echo nl2br(htmlspecialchars($msg['message_content'])); ?>
                            </div>
                            <span class="message-time">
                                <?php echo $msg_datetime->format('d/m/y H:i'); ?>
                                <?php if ($msg['edited_at'] && !$msg['is_deleted_by_staff']): ?><span class="message-edited-indicator">(editado)</span><?php endif; ?>
                                <?php if ($is_sent_by_patient_chat && !$msg['read_by_staff_at']): ?><i class="fas fa-envelope text-danger ms-1" title="No leído por personal"></i>
                                <?php elseif (!$is_sent_by_patient_chat && !$msg['read_by_patient_at'] && !$msg['is_deleted_by_staff']): ?><i class="fas fa-paper-plane text-muted ms-1" title="Enviado, no leído por paciente"></i>
                                <?php elseif (!$is_sent_by_patient_chat && $msg['read_by_patient_at'] && !$msg['is_deleted_by_staff']): ?><i class="fas fa-check-double text-info ms-1" title="Leído por paciente"></i><?php endif; ?>
                            </span>
                            <?php if (!$is_sent_by_patient_chat && $msg['admin_user_id'] == $current_admin_id && !$msg['is_deleted_by_staff']): ?>
                            <div class="message-actions">
                                <button type="button" class="btn-action edit-message-btn" data-bs-toggle="modal" data-bs-target="#editMessageModal" data-message-id="<?php echo $msg['id']; ?>" data-message-content="<?php echo htmlspecialchars($msg['message_content']); ?>" title="Editar Mensaje"><i class="fas fa-pencil-alt"></i></button>
                                <form method="POST" action="chat.php?patient_id=<?php echo $patient_id_chat; ?>" style="display: inline;" onsubmit="return confirm('¿Está seguro de que desea eliminar este mensaje?');"><input type="hidden" name="delete_message_id" value="<?php echo $msg['id']; ?>"><button type="submit" name="delete_staff_message" class="btn-action" title="Eliminar Mensaje"><i class="fas fa-trash-alt"></i></button></form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="chat-input-form-admin">
                    <?php if ($success_message_chat): ?><div class="alert alert-success alert-dismissible fade show p-2 small mb-2" role="alert"><?php echo htmlspecialchars($success_message_chat); ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
                    <?php if ($error_message_chat): ?><div class="alert alert-danger alert-dismissible fade show p-2 small mb-2" role="alert"><?php echo htmlspecialchars($error_message_chat); ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
                    <form method="POST" action="chat.php?patient_id=<?php echo $patient_id_chat; ?>" id="sendStaffMessageForm">
                        <div class="input-group">
                            <textarea name="message_content_staff" class="form-control" placeholder="Escriba su respuesta aquí..." rows="2" required aria-label="Mensaje"></textarea>
                            <button class="btn btn-primary" type="submit" name="send_staff_message" title="Enviar Mensaje"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="editMessageModal" tabindex="-1" aria-labelledby="editMessageModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editMessageModalLabel"><i class="fas fa-edit me-2"></i>Editar Mensaje</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="editMessageForm" method="POST" action="chat.php?patient_id=<?php echo $patient_id_chat; ?>">
            <div class="modal-body">
              <input type="hidden" name="edit_message_id" id="modalEditMessageId">
              <div class="mb-3">
                <label for="edit_message_content" class="form-label">Contenido del Mensaje:</label>
                <textarea class="form-control" id="edit_message_content" name="edit_message_content" rows="4" required></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="edit_staff_message" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar Cambios</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatMessagesAreaAdmin = document.getElementById('chatMessagesAreaAdmin');
            let lastMessageIdAdmin = <?php echo $last_message_id_on_page_admin; ?>;
            const currentAdminNameJS = <?php echo json_encode($current_admin_name); ?>; 
            const patientIdForPolling = <?php echo json_encode($patient_id_chat); ?>;

            function scrollToBottomAdmin() {
                if (chatMessagesAreaAdmin) {
                    chatMessagesAreaAdmin.scrollTop = chatMessagesAreaAdmin.scrollHeight;
                }
            }
            scrollToBottomAdmin(); 

            function appendMessageToAdminChat(msg) {
                const noMessagesYetAdmin = chatMessagesAreaAdmin.querySelector('.no-messages-yet-admin');
                if (noMessagesYetAdmin) {
                    noMessagesYetAdmin.remove();
                }

                const messageDiv = document.createElement('div');
                messageDiv.classList.add('message-bubble');
                messageDiv.classList.add(msg.sent_by_patient ? 'message-patient' : 'message-staff'); 
                messageDiv.dataset.messageId = msg.id;

                const senderNameSpan = document.createElement('span');
                senderNameSpan.classList.add('sender-name');
                senderNameSpan.textContent = msg.sender_display_name; 

                const contentDiv = document.createElement('div');
                contentDiv.classList.add('message-content-text');
                contentDiv.innerHTML = msg.message_content.replace(/\n/g, '<br>');

                const timeSpan = document.createElement('span');
                timeSpan.classList.add('message-time');
                let sentDate = new Date(msg.sent_at);
                timeSpan.textContent = `${sentDate.toLocaleDateString('es-ES', {day:'2-digit', month:'2-digit', year:'2-digit'})} ${sentDate.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'})}`;

                if (msg.edited_at && !msg.is_deleted_by_staff) {
                    const editedIndicator = document.createElement('span');
                    editedIndicator.classList.add('message-edited-indicator');
                    editedIndicator.textContent = ' (editado)';
                    timeSpan.appendChild(editedIndicator);
                }
                
                if (msg.sent_by_patient && !msg.read_by_staff_at) { 
                    const unreadIcon = document.createElement('i');
                    unreadIcon.classList.add('fas', 'fa-envelope', 'text-danger', 'ms-1');
                    unreadIcon.title = "No leído por personal";
                    timeSpan.appendChild(unreadIcon);
                }
                
                messageDiv.appendChild(senderNameSpan);
                messageDiv.appendChild(contentDiv);
                messageDiv.appendChild(timeSpan);
                
                // No añadir botones de acción aquí para mensajes que llegan por polling (son del paciente)
                chatMessagesAreaAdmin.appendChild(messageDiv);

                if (parseInt(msg.id) > lastMessageIdAdmin) { 
                    lastMessageIdAdmin = parseInt(msg.id);
                }
            }

            function fetchNewStaffMessages() {
                fetch(`<?php echo $path_to_root; ?>actions/get_staff_new_messages.php?patient_id=${patientIdForPolling}&last_message_id=${lastMessageIdAdmin}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            if (!document.querySelector(`.message-bubble[data-message-id='${msg.id}']`)) {
                                appendMessageToAdminChat(msg);
                            }
                        });
                        scrollToBottomAdmin();
                    }
                })
                .catch(error => {
                    console.error('Error en polling de mensajes para staff:', error);
                })
                .finally(() => {
                    setTimeout(fetchNewStaffMessages, 7000); 
                });
            }

            if (chatMessagesAreaAdmin) {
                setTimeout(fetchNewStaffMessages, 7000); 
            }
            
            var editMessageModal = document.getElementById('editMessageModal');
            if (editMessageModal) {
                editMessageModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget; 
                    var messageId = button.getAttribute('data-message-id');
                    var messageContent = button.getAttribute('data-message-content');
                    var modalMessageIdInput = editMessageModal.querySelector('#modalEditMessageId');
                    var modalMessageContentTextarea = editMessageModal.querySelector('#edit_message_content');
                    
                    if(modalMessageIdInput) modalMessageIdInput.value = messageId;
                    if(modalMessageContentTextarea) modalMessageContentTextarea.value = messageContent;
                });
            }
             var autoDismissAlerts = document.querySelectorAll('.chat-input-form-admin .alert-dismissible');
            autoDismissAlerts.forEach(function(alertEl) {
                setTimeout(function() {
                    if (document.body.contains(alertEl)) {
                        var bsAlert = bootstrap.Alert.getInstance(alertEl);
                        if (bsAlert) { bsAlert.close(); }
                    }
                }, 5000); 
            });
        });
    </script>
</body>
</html>
