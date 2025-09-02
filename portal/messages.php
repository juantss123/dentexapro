<?php
session_start();
// Verify if the patient is logged in
if (!isset($_SESSION['patient_id'])) {
    header('Location: login.php'); 
    exit;
}
require_once '../config.php'; 

$patient_id = $_SESSION['patient_id'];
$patient_fname_session = $_SESSION['patient_fname'] ?? 'Tú'; // Nombre para mostrar como remitente
$currentPage = 'my_messages'; // Para el _portal_header.php active link

$error_message = '';
$success_message = '';

// Marcar mensajes del personal como leídos por el paciente al cargar la página INICIALMENTE
$stmt_mark_read_initial = $mysqli->prepare("UPDATE messages SET read_by_patient_at = NOW() WHERE patient_id = ? AND sent_by_patient = FALSE AND read_by_patient_at IS NULL");
if ($stmt_mark_read_initial) {
    $stmt_mark_read_initial->bind_param("i", $patient_id);
    $stmt_mark_read_initial->execute();
    $stmt_mark_read_initial->close();
}

// Enviar nuevo mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_content = trim($_POST['message_content'] ?? '');

    if (empty($message_content)) {
        $error_message = 'El contenido del mensaje no puede estar vacío.';
    } else {
        // Al enviar un mensaje, nos aseguramos que no esté marcado como eliminado por el staff
        $stmt_send = $mysqli->prepare("INSERT INTO messages (patient_id, sent_by_patient, message_content, read_by_staff_at, is_deleted_by_staff) VALUES (?, TRUE, ?, NULL, FALSE)");
        if ($stmt_send) {
            $stmt_send->bind_param("is", $patient_id, $message_content);
            if ($stmt_send->execute()) {
                // Redirigir para limpiar el POST y permitir que el polling cargue el mensaje
                header("Location: messages.php"); 
                exit;
            } else {
                $error_message = 'Error al enviar el mensaje: ' . $stmt_send->error;
            }
            $stmt_send->close();
        } else {
            $error_message = 'Error al preparar el mensaje: ' . $mysqli->error;
        }
    }
}

// Cargar historial de mensajes para este paciente
$messages = [];
$last_message_id_on_page = 0; // Para el polling

// Asegurarse de que la columna is_deleted_by_staff se selecciona
$stmt_messages = $mysqli->prepare("
    SELECT m.id, m.admin_user_id, m.sent_by_patient, m.message_content, m.sent_at, m.read_by_staff_at, m.read_by_patient_at, m.edited_at, m.is_deleted_by_staff,
           IF(m.sent_by_patient = FALSE AND au.name IS NOT NULL, au.name, 'Clínica') as sender_name
    FROM messages m
    LEFT JOIN admin_users au ON m.admin_user_id = au.id
    WHERE m.patient_id = ?
    ORDER BY m.sent_at ASC
");
if ($stmt_messages) {
    $stmt_messages->bind_param("i", $patient_id);
    $stmt_messages->execute();
    $result_messages = $stmt_messages->get_result();
    while ($row = $result_messages->fetch_assoc()) {
        $messages[] = $row;
        if ($row['id'] > $last_message_id_on_page) {
            $last_message_id_on_page = $row['id']; 
        }
    }
    $stmt_messages->close();
} else {
    error_log("Error al preparar la consulta de mensajes del paciente: " . $mysqli->error);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Mensajes - Portal del Paciente</title>
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
        body { font-family: 'Roboto', sans-serif; background-color: #f4f7f6; display: flex; flex-direction: column; min-height: 100vh; }
        .portal-navbar { background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,.1); flex-shrink: 0; }
        .portal-navbar .navbar-brand { font-family: 'Montserrat', sans-serif; font-weight: 700; color: #0d6efd; }
        .portal-navbar .navbar-brand i { margin-right: 0.5rem; }
        .portal-navbar .nav-link.active { color: #0056b3 !important; font-weight: bold; }
        .portal-navbar .nav-link { padding-top: 0.8rem; padding-bottom: 0.8rem; }
        .portal-content { padding-top: 2rem; padding-bottom: 2rem; flex-grow: 1; }
        .footer { padding: 1.5rem 0; background-color: #343a40; color: #adb5bd; font-size: 0.9rem; flex-shrink: 0; }
        .section-title { font-family: 'Montserrat', sans-serif; font-weight: 700; color: #343a40; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #0d6efd; display: inline-block; }
        .chat-container { max-width: 800px; margin: 0 auto; background-color: #fff; border-radius: .5rem; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); display: flex; flex-direction: column; height: calc(100vh - 220px); min-height: 450px; }
        .chat-header { padding: 1rem; background-color: #0d6efd; color: white; border-top-left-radius: .5rem; border-top-right-radius: .5rem; }
        .chat-header h5 { margin-bottom: 0; }
        .chat-messages { flex-grow: 1; padding: 1rem; overflow-y: auto; border-bottom: 1px solid #eee; }
        .message-bubble { padding: .65rem 1rem; border-radius: 18px; margin-bottom: .75rem; max-width: 75%; word-wrap: break-word; line-height: 1.4; }
        .message-bubble .sender-name { font-size: 0.75rem; font-weight: bold; margin-bottom: 0.25rem; display: block; }
        .message-bubble .message-content-text { font-size: 0.95rem; } /* Clase para el contenido del mensaje */
        .message-bubble .message-time { font-size: 0.7rem; color: #888; display: block; text-align: right; margin-top: 0.25rem; }
        .message-sent { background-color: #dcf8c6; margin-left: auto; border-bottom-right-radius: .25rem; }
        .message-received { background-color: #f1f0f0; margin-right: auto; border-bottom-left-radius: .25rem; }
        .chat-input-form { padding: 1rem; background-color: #f8f9fa; border-top: 1px solid #eee; }
        .message-edited-indicator { font-size: 0.6rem; color: #999; font-style: italic; margin-left: 5px;}
    </style>
</head>
<body>
    <?php require_once '_portal_header.php'; ?>

    <div class="container portal-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title"><i class="fas fa-comments me-2"></i>Mis Mensajes con la Clínica</h2>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="chat-container">
            <div class="chat-header">
                <h5>Conversación con Clínica Dental Dra. Fernanda Turano</h5>
            </div>
            <div class="chat-messages" id="chatMessagesArea">
                <?php if (empty($messages)): ?>
                    <p class="text-center text-muted mt-3 no-messages-yet">No hay mensajes aún. ¡Envía el primero!</p>
                <?php else: ?>
                    <?php foreach ($messages as $msg): 
                        if (!$msg['sent_by_patient'] && $msg['is_deleted_by_staff']) {
                            continue; 
                        }
                        $msg_datetime = new DateTime($msg['sent_at']);
                        $is_sent_by_this_patient = $msg['sent_by_patient'];
                    ?>
                    <div class="message-bubble <?php echo $is_sent_by_this_patient ? 'message-sent' : 'message-received'; ?>" data-message-id="<?php echo $msg['id']; ?>">
                        <span class="sender-name">
                            <?php echo $is_sent_by_this_patient ? htmlspecialchars($patient_fname_session) : htmlspecialchars($msg['sender_name']); ?>
                        </span>
                        <div class="message-content-text"><?php echo nl2br(htmlspecialchars($msg['message_content'])); ?></div>
                        <span class="message-time">
                            <?php echo $msg_datetime->format('d/m/y H:i'); ?>
                            <?php if ($msg['edited_at'] && !$msg['is_deleted_by_staff']): ?>
                                <span class="message-edited-indicator">(editado)</span>
                            <?php endif; ?>
                            <?php if (!$is_sent_by_this_patient && !$msg['read_by_patient_at'] && !$msg['is_deleted_by_staff']): ?>
                                <i class="fas fa-envelope text-warning ms-1" title="Nuevo mensaje no leído"></i>
                            <?php elseif ($is_sent_by_this_patient && !$msg['read_by_staff_at']): ?>
                                <i class="fas fa-paper-plane text-muted ms-1" title="Enviado"></i>
                            <?php elseif ($is_sent_by_this_patient && $msg['read_by_staff_at']): ?>
                                <i class="fas fa-check-double text-info ms-1" title="Leído por personal"></i>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="chat-input-form">
                <form method="POST" action="messages.php" id="sendMessageForm">
                    <div class="input-group">
                        <textarea name="message_content" class="form-control" placeholder="Escribe tu mensaje aquí..." rows="2" required></textarea>
                        <button class="btn btn-primary" type="submit" name="send_message">
                            <i class="fas fa-paper-plane me-1"></i> Enviar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="footer text-center">
        <div class="container">
            <span>&copy; <?php echo date("Y"); ?> Dra. Fernanda Turano - Clínica Dental. Todos los derechos reservados.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatMessagesArea = document.getElementById('chatMessagesArea');
            let lastMessageId = <?php echo $last_message_id_on_page; ?>;
            const patientNameSession = <?php echo json_encode($patient_fname_session); ?>;
            const patientIdForPolling = <?php echo json_encode($patient_id); ?>; 

            function scrollToBottom() {
                if (chatMessagesArea) {
                    chatMessagesArea.scrollTop = chatMessagesArea.scrollHeight;
                }
            }
            scrollToBottom(); 

            function appendMessageToChat(msg, isNew = true) {
                const noMessagesYet = chatMessagesArea.querySelector('.no-messages-yet');
                if (noMessagesYet) {
                    noMessagesYet.remove();
                }

                const messageDiv = document.createElement('div');
                messageDiv.classList.add('message-bubble');
                messageDiv.classList.add(msg.sent_by_patient ? 'message-sent' : 'message-received'); 
                messageDiv.dataset.messageId = msg.id;

                const senderNameSpan = document.createElement('span');
                senderNameSpan.classList.add('sender-name');
                senderNameSpan.textContent = msg.sent_by_patient ? patientNameSession : msg.sender_name;

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
                
                messageDiv.appendChild(senderNameSpan);
                messageDiv.appendChild(contentDiv);
                messageDiv.appendChild(timeSpan);
                chatMessagesArea.appendChild(messageDiv);

                if (isNew && parseInt(msg.id) > lastMessageId) { 
                    lastMessageId = parseInt(msg.id);
                }
            }

            function updateOrRemoveMessage(updatedMsg) {
                const existingMessageElement = chatMessagesArea.querySelector(`.message-bubble[data-message-id='${updatedMsg.id}']`);
                if (existingMessageElement) {
                    if (updatedMsg.is_deleted_by_staff && !updatedMsg.sent_by_patient) { 
                        existingMessageElement.remove();
                    } else if (updatedMsg.edited_at && !updatedMsg.is_deleted_by_staff) {
                        const contentDiv = existingMessageElement.querySelector('.message-content-text');
                        if (contentDiv) {
                            contentDiv.innerHTML = updatedMsg.message_content.replace(/\n/g, '<br>');
                        }
                        let timeSpan = existingMessageElement.querySelector('.message-time');
                        if (timeSpan) {
                            let sentDate = new Date(updatedMsg.edited_at); 
                            timeSpan.textContent = `${sentDate.toLocaleDateString('es-ES', {day:'2-digit', month:'2-digit', year:'2-digit'})} ${sentDate.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'})}`;
                            // Eliminar indicador de editado anterior si existe
                            const oldEditedIndicator = timeSpan.querySelector('.message-edited-indicator');
                            if(oldEditedIndicator) oldEditedIndicator.remove();
                            // Añadir nuevo indicador de editado
                            const editedIndicator = document.createElement('span');
                            editedIndicator.classList.add('message-edited-indicator');
                            editedIndicator.textContent = ' (editado)';
                            timeSpan.appendChild(editedIndicator);
                        }
                    }
                }
            }

            function fetchNewMessages() {
                fetch(`get_new_messages.php?patient_id=${patientIdForPolling}&last_message_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let newMessagesReceived = false;
                        if (data.new_messages && data.new_messages.length > 0) {
                            const noMessagesYet = chatMessagesArea.querySelector('.no-messages-yet');
                            if (noMessagesYet) noMessagesYet.remove();
                            
                            data.new_messages.forEach(msg => {
                                if (!document.querySelector(`.message-bubble[data-message-id='${msg.id}']`)) {
                                    appendMessageToChat(msg, true);
                                    newMessagesReceived = true;
                                }
                            });
                        }
                        
                        if (data.deleted_staff_message_ids && data.deleted_staff_message_ids.length > 0) {
                            data.deleted_staff_message_ids.forEach(id => {
                                const messageElement = chatMessagesArea.querySelector(`.message-bubble[data-message-id='${id}']`);
                                if (messageElement) {
                                    messageElement.remove();
                                }
                            });
                        }

                        if (data.edited_staff_messages && data.edited_staff_messages.length > 0) {
                             data.edited_staff_messages.forEach(msg => {
                                updateOrRemoveMessage(msg); 
                            });
                        }

                        if (newMessagesReceived) {
                            scrollToBottom();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error en polling de mensajes (portal):', error);
                })
                .finally(() => {
                    setTimeout(fetchNewMessages, 7000); 
                });
            }

            if (chatMessagesArea) {
                setTimeout(fetchNewMessages, 7000); 
            }
            
            var autoDismissAlerts = document.querySelectorAll('.alert-dismissible');
             autoDismissAlerts.forEach(function(alertEl) {
                setTimeout(function() {
                    if (document.body.contains(alertEl)) {
                        var bsAlert = bootstrap.Alert.getInstance(alertEl);
                        if (bsAlert) { bsAlert.close(); } else { new bootstrap.Alert(alertEl).close(); }
                    }
                }, 5000); 
            });
        });
    </script>
</body>
</html>
