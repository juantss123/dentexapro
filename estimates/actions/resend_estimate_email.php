<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}
require_once '../../config.php'; 
require_once '../generate_estimate_pdf.php'; // Para (re)generar el PDF

$response_status = 'error';
$response_message = 'Solicitud no válida o ID de presupuesto faltante.';
$estimate_id_for_redirect = null;
$estimate_number_for_msg = 'N/A';


// Verificar permisos
$can_resend = false;
if (isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] === 'superadmin' || $_SESSION['admin_role'] === 'admin')) {
    $can_resend = true; 
} elseif (user_has_permission('estimates', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    // Podrías tener un permiso más granular como 'estimates_send_email'
    $can_resend = true;
}

if (!$can_resend) {
    $response_message = 'No tiene permiso para reenviar presupuestos.';
    header('Location: ../index.php?status=' . $response_status . '&msg=' . urlencode($response_message));
    exit;
}


if (($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') && isset($_REQUEST['estimate_id'])) {
    $estimate_id = filter_var($_REQUEST['estimate_id'], FILTER_VALIDATE_INT);
    $estimate_id_for_redirect = $estimate_id; // Para el ancla en la redirección

    if ($estimate_id && $estimate_id > 0) {
        // Obtener el número de presupuesto para los mensajes de feedback
        $stmt_get_num = $mysqli->prepare("SELECT estimate_number FROM estimates WHERE id = ?");
        if ($stmt_get_num) {
            $stmt_get_num->bind_param("i", $estimate_id);
            $stmt_get_num->execute();
            $res_get_num = $stmt_get_num->get_result();
            if ($row_get_num = $res_get_num->fetch_assoc()) {
                $estimate_number_for_msg = $row_get_num['estimate_number'];
            }
            $stmt_get_num->close();
        }

        // (Re)Generar el PDF para asegurar que se envía la última versión
        $pdf_result = generate_estimate_pdf($estimate_id, $mysqli);

        if ($pdf_result['success']) {
            $pdf_filename_for_db = $pdf_result['filename_db_relative'];
            $absolute_pdf_path = $pdf_result['filepath_absolute'];

            // Actualizar el nombre del PDF en la tabla estimates (por si cambió o no existía)
            $stmt_update_pdf_name = $mysqli->prepare("UPDATE estimates SET pdf_filename = ?, status = 'enviado', updated_at = NOW() WHERE id = ?");
            if ($stmt_update_pdf_name) {
                $new_status_if_resent = 'enviado'; // Asumimos que reenviar lo marca como enviado
                $stmt_update_pdf_name->bind_param("si", $pdf_filename_for_db, $estimate_id);
                $stmt_update_pdf_name->execute();
                $stmt_update_pdf_name->close();
            } else {
                error_log("Error al actualizar pdf_filename (reenvío) para estimate ID {$estimate_id}: " . $mysqli->error);
                // No es un error fatal para el envío, pero se debe loguear.
            }
            
            $patient_email_to = $pdf_result['patient_email'];
            $patient_name_to = $pdf_result['patient_name'];
            // $estimate_number_for_msg ya tiene el número del presupuesto

            if (!empty($patient_email_to)) {
                $clinic_email_from_settings = 'configuracion_email_clinica@sudominio.com'; 
                $clinic_name_from_settings = 'Su Clínica Dental'; 
                
                $s_clinic_email_q = $mysqli->query("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_pdf_email' LIMIT 1");
                if($s_clinic_email_q && $r_ce = $s_clinic_email_q->fetch_assoc()) $clinic_email_from_settings = $r_ce['setting_value'];
                if($s_clinic_email_q) $s_clinic_email_q->free();

                $s_clinic_name_q = $mysqli->query("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_pdf_name' LIMIT 1");
                if($s_clinic_name_q && $r_cn = $s_clinic_name_q->fetch_assoc()) $clinic_name_from_settings = $r_cn['setting_value'];
                if($s_clinic_name_q) $s_clinic_name_q->free();

                $subject = "Reenvío Presupuesto Dental N° {$estimate_number_for_msg} - {$clinic_name_from_settings}";
                $email_body_html = "
                <html><body style='font-family: Arial, sans-serif; color: #333;'>
                <p>Estimado/a {$patient_name_to},</p>
                <p>Le reenviamos el presupuesto N° <strong>{$estimate_number_for_msg}</strong> solicitado o actualizado.</p>
                <p>Adjunto encontrará el documento PDF.</p>
                <p>Por favor, revíselo y no dude en contactarnos si tiene alguna consulta.</p>
                <p>Saludos cordiales,<br>{$clinic_name_from_settings}</p>
                </body></html>";

                $boundary = "PHP-mixed-" . md5(time());
                $headers = "From: \"" . mb_encode_mimeheader($clinic_name_from_settings, "UTF-8", "Q") . "\" <{$clinic_email_from_settings}>\r\n";
                $headers .= "Reply-To: {$clinic_email_from_settings}\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
                $message_email = "--{$boundary}\r\n";
                $message_email .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
                $message_email .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $message_email .= $email_body_html . "\r\n\r\n";

                if (file_exists($absolute_pdf_path)) {
                    $file_content = chunk_split(base64_encode(file_get_contents($absolute_pdf_path)));
                    $message_email .= "--{$boundary}\r\n";
                    $message_email .= "Content-Type: application/pdf; name=\"" . basename($pdf_filename_for_db) . "\"\r\n";
                    $message_email .= "Content-Transfer-Encoding: base64\r\n";
                    $message_email .= "Content-Disposition: attachment; filename=\"" . basename($pdf_filename_for_db) . "\"\r\n\r\n";
                    $message_email .= $file_content . "\r\n\r\n";
                    $message_email .= "--{$boundary}--";

                    if (@mail($patient_email_to, $subject, $message_email, $headers)) {
                        $response_status = 'success_resent';
                        $response_message = 'Presupuesto N° ' . htmlspecialchars($estimate_number_for_msg) . ' reenviado por email con éxito.';
                    } else {
                        error_log("Error al reenviar email del presupuesto {$estimate_id} a {$patient_email_to}");
                        $response_message = 'PDF del presupuesto N° ' . htmlspecialchars($estimate_number_for_msg) . ' generado, pero hubo un error al reenviar el email.';
                        $response_status = 'warning_email_failed_resend';
                    }
                } else {
                    error_log("Error crítico: PDF no encontrado en {$absolute_pdf_path} para adjuntar al email (reenvío).");
                    $response_message = 'No se pudo encontrar el archivo PDF para el presupuesto N° ' . htmlspecialchars($estimate_number_for_msg) . '.';
                }
            } else {
                 $response_message = 'El paciente no tiene un email registrado para el envío del presupuesto N° ' . htmlspecialchars($estimate_number_for_msg) . '.';
                 $response_status = 'warning_no_email';
            }
        } else { // Falló la generación del PDF
             $response_message = 'Error al (re)generar el PDF para el presupuesto N° ' . htmlspecialchars($estimate_number_for_msg) . ': ' . ($pdf_result['message'] ?? 'Desconocido');
        }
    } else {
        $response_message = 'ID de presupuesto no válido para reenviar.';
    }
}

$redirect_location = '../index.php?status=' . $response_status . '&msg=' . urlencode($response_message);
if ($estimate_id_for_redirect) {
    $redirect_location .= '#estimate-row-' . $estimate_id_for_redirect;
}
header('Location: ' . $redirect_location);
exit;
?>