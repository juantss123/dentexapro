<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}
require_once '../../config.php'; 
require_once '../generate_estimate_pdf.php'; // Para generar PDF si se reenvía

$response = ['success' => false, 'message' => 'Error desconocido.', 'estimate_number' => null, 'action_type' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estimate_id_to_update = filter_input(INPUT_POST, 'estimate_id', FILTER_VALIDATE_INT);
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $professional_name_input = trim($_POST['professional_name'] ?? ''); 
    $estimate_date_str = trim($_POST['estimate_date'] ?? '');
    $insurance_details = trim($_POST['insurance_details'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status_input = $_POST['status'] ?? 'borrador'; 
    $action_type = $_POST['update_estimate_action'] ?? 'update_only'; // 'update_only' o 'update_send'

    $item_descriptions = $_POST['item_description'] ?? [];
    $item_quantities = $_POST['item_quantity'] ?? [];
    $item_unit_prices = $_POST['item_unit_price'] ?? [];

    // --- Validaciones ---
    if (!$estimate_id_to_update) $errors[] = "ID de presupuesto para actualizar no válido.";
    if (!$patient_id) $errors[] = "Debe seleccionar un paciente.";
    if (empty($professional_name_input)) $errors[] = "El nombre del profesional es obligatorio.";
    
    $date_obj = null;
    if (empty($estimate_date_str)) {
        $errors[] = "La fecha del presupuesto es obligatoria.";
    } else {
        $date_obj = DateTime::createFromFormat('Y-m-d', $estimate_date_str);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $estimate_date_str) {
            $errors[] = "Formato de fecha del presupuesto inválido.";
        }
    }
    $valid_statuses = ['borrador', 'enviado', 'aprobado', 'rechazado', 'pagado'];
    if (!in_array($status_input, $valid_statuses)) $errors[] = "Estado de presupuesto inválido.";
    
    if (empty($item_descriptions) || !is_array($item_descriptions) || empty(array_filter($item_descriptions, 'trim'))) {
        $errors[] = "Debe agregar al menos un ítem al presupuesto con descripción.";
    }
    if (count($item_descriptions) !== count($item_quantities) || count($item_descriptions) !== count($item_unit_prices)) {
        $errors[] = "Datos de ítems incompletos o corruptos.";
    }

    $parsed_items = [];
    $grand_total_calculated = 0;
    if (empty($errors)) {
        for ($i = 0; $i < count($item_descriptions); $i++) {
            $desc = trim($item_descriptions[$i]);
            $qty = filter_var($item_quantities[$i] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
            $price_str = str_replace('.', '', $item_unit_prices[$i] ?? '0');
            $price_str = str_replace(',', '.', $price_str); 
            $price = filter_var($price_str, FILTER_VALIDATE_FLOAT);

            if (empty($desc)) { $errors[] = "La descripción del ítem #" . ($i + 1) . " no puede estar vacía."; continue; }
            if ($qty === false || $qty <=0) { $errors[] = "La cantidad del ítem #" . ($i + 1) . " no es válida."; continue; }
            if ($price === false || $price < 0) { $errors[] = "El precio unitario del ítem #" . ($i + 1) . " ('".$item_unit_prices[$i]."') no es válido."; continue;}
            
            $item_total = $qty * $price;
            $parsed_items[] = ['description' => $desc, 'quantity' => $qty, 'unit_price' => $price, 'item_total' => $item_total];
            $grand_total_calculated += $item_total;
        }
    }
    // --- Fin Validaciones ---

    if (!empty($errors)) {
        $_SESSION['estimate_form_errors_edit'] = $errors; // Usar clave de sesión para edición
        $_SESSION['estimate_form_data_edit'] = $_POST;   // Usar clave de sesión para edición
        header('Location: ../edit.php?id=' . $estimate_id_to_update . '&status=error_validation_save_edit');
        exit;
    }

    // No se regenera el número de presupuesto al editar, se mantiene el original.
    // Se obtiene de la BD si fuera necesario para el email o mensajes.
    $estimate_number_from_db = '';
    $stmt_get_num = $mysqli->prepare("SELECT estimate_number FROM estimates WHERE id = ?");
    if($stmt_get_num){
        $stmt_get_num->bind_param("i", $estimate_id_to_update);
        $stmt_get_num->execute();
        $res_get_num = $stmt_get_num->get_result();
        if($row_get_num = $res_get_num->fetch_assoc()){
            $estimate_number_from_db = $row_get_num['estimate_number'];
        }
        $stmt_get_num->close();
    }
    if(empty($estimate_number_from_db) && $estimate_id_to_update) { // Fallback si no se pudo obtener, aunque no debería pasar
        $errors[] = "No se pudo obtener el número del presupuesto original.";
        $_SESSION['estimate_form_errors_edit'] = $errors;
        $_SESSION['estimate_form_data_edit'] = $_POST;
        header('Location: ../edit.php?id=' . $estimate_id_to_update . '&status=error_internal');
        exit;
    }


    $mysqli->begin_transaction();
    try {
        $pdf_filename_for_db = null; // Se actualizará si se (re)genera PDF

        // Obtener el nombre del PDF actual para poder borrarlo si se genera uno nuevo
        $current_pdf_filename_stmt = $mysqli->prepare("SELECT pdf_filename FROM estimates WHERE id = ?");
        $current_pdf_relative_path = null;
        if($current_pdf_filename_stmt){
            $current_pdf_filename_stmt->bind_param("i", $estimate_id_to_update);
            $current_pdf_filename_stmt->execute();
            $res_curr_pdf = $current_pdf_filename_stmt->get_result();
            if($row_curr_pdf = $res_curr_pdf->fetch_assoc()){
                $current_pdf_relative_path = $row_curr_pdf['pdf_filename'];
            }
            $current_pdf_filename_stmt->close();
        }


        // Actualizar la cabecera del presupuesto
        $stmt_update_estimate = $mysqli->prepare(
            "UPDATE estimates SET patient_id = ?, estimate_date = ?, insurance_details = ?, 
             total_amount = ?, status = ?, notes = ?, professional_name_text = ?, updated_at = NOW() 
             WHERE id = ?" 
             // admin_user_id (creador original) y estimate_number no se cambian al editar
             // pdf_filename se actualiza por separado si se genera uno nuevo
        );
        if (!$stmt_update_estimate) throw new Exception("Error preparando actualización de cabecera: " . $mysqli->error);
        
        $stmt_update_estimate->bind_param("issdsssi", 
            $patient_id, 
            $estimate_date_str, 
            $insurance_details, 
            $grand_total_calculated, 
            $status_input, 
            $notes,
            $professional_name_input,
            $estimate_id_to_update
        );
        if (!$stmt_update_estimate->execute()) throw new Exception("Error actualizando cabecera: " . $stmt_update_estimate->error);
        $stmt_update_estimate->close();

        // Eliminar ítems antiguos
        $stmt_delete_items = $mysqli->prepare("DELETE FROM estimate_items WHERE estimate_id = ?");
        if (!$stmt_delete_items) throw new Exception("Error preparando borrado de ítems antiguos: " . $mysqli->error);
        $stmt_delete_items->bind_param("i", $estimate_id_to_update);
        if (!$stmt_delete_items->execute()) throw new Exception("Error borrando ítems antiguos: " . $stmt_delete_items->error);
        $stmt_delete_items->close();

        // Insertar nuevos ítems
        $stmt_insert_item = $mysqli->prepare("INSERT INTO estimate_items (estimate_id, description, quantity, unit_price, item_total) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt_insert_item) throw new Exception("Error preparando inserción de ítems: " . $mysqli->error);
        foreach ($parsed_items as $item) {
            $stmt_insert_item->bind_param("isidd", $estimate_id_to_update, $item['description'], $item['quantity'], $item['unit_price'], $item['item_total']);
            if (!$stmt_insert_item->execute()) throw new Exception("Error guardando nuevo ítem: " . $stmt_insert_item->error);
        }
        $stmt_insert_item->close();

        $redirect_status_key = 'success_update'; 

        if ($action_type === 'update_send' && $status_input === 'enviado') {
            // Borrar el PDF antiguo del servidor si existía antes de generar uno nuevo
            if (!empty($current_pdf_relative_path) && file_exists(PROJECT_ROOT . '/' . $current_pdf_relative_path)) {
                @unlink(PROJECT_ROOT . '/' . $current_pdf_relative_path);
            }

            $pdf_result = generate_estimate_pdf($estimate_id_to_update, $mysqli); 

            if ($pdf_result['success']) {
                $pdf_filename_for_db = $pdf_result['filename_db_relative'];
                $absolute_pdf_path = $pdf_result['filepath_absolute'];

                $stmt_update_pdf_name = $mysqli->prepare("UPDATE estimates SET pdf_filename = ? WHERE id = ?");
                if ($stmt_update_pdf_name) {
                    $stmt_update_pdf_name->bind_param("si", $pdf_filename_for_db, $estimate_id_to_update);
                    $stmt_update_pdf_name->execute();
                    $stmt_update_pdf_name->close();
                } else {
                    error_log("Error al actualizar pdf_filename (edit) para estimate ID {$estimate_id_to_update}: " . $mysqli->error);
                }
                
                $patient_email_to = $pdf_result['patient_email'];
                $patient_name_to = $pdf_result['patient_name'];
                // $estimate_number_from_db ya lo tenemos

                if (!empty($patient_email_to)) {
                    $clinic_email_from_settings = 'configuracion_email_clinica@sudominio.com'; 
                    $clinic_name_from_settings = 'Su Clínica Dental'; 
                    
                    $s_clinic_email_q = $mysqli->query("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_pdf_email' LIMIT 1");
                    if($s_clinic_email_q && $r_ce = $s_clinic_email_q->fetch_assoc()) $clinic_email_from_settings = $r_ce['setting_value'];
                    if($s_clinic_email_q) $s_clinic_email_q->free();

                    $s_clinic_name_q = $mysqli->query("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_pdf_name' LIMIT 1");
                    if($s_clinic_name_q && $r_cn = $s_clinic_name_q->fetch_assoc()) $clinic_name_from_settings = $r_cn['setting_value'];
                    if($s_clinic_name_q) $s_clinic_name_q->free();

                    $subject = "Actualización de Presupuesto Dental N° {$estimate_number_from_db} - {$clinic_name_from_settings}";
                    $email_body_html = "
                    <html><body style='font-family: Arial, sans-serif; color: #333;'>
                    <p>Estimado/a {$patient_name_to},</p>
                    <p>Le informamos que su presupuesto N° <strong>{$estimate_number_from_db}</strong> ha sido actualizado.</p>
                    <p>Adjunto encontrará la versión más reciente del mismo.</p>
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

                        if (!@mail($patient_email_to, $subject, $message_email, $headers)) {
                            error_log("Error al (re)enviar email del presupuesto {$estimate_id_to_update} a {$patient_email_to}");
                            $_SESSION['estimate_form_errors_edit_temp'] = ["Presupuesto actualizado y PDF generado, pero hubo un error al (re)enviar el email."];
                            $redirect_status_key = 'warning_email_failed_edit'; 
                        } else {
                             $redirect_status_key = 'success_update_sent'; 
                        }
                    } else {
                        error_log("Error crítico: PDF no encontrado en {$absolute_pdf_path} para adjuntar al email (edit).");
                        $_SESSION['estimate_form_errors_edit_temp'] = ["Presupuesto actualizado, pero el PDF no se encontró para el envío."];
                        $redirect_status_key = 'warning_email_failed_edit';
                    }
                } else {
                     $_SESSION['estimate_form_errors_edit_temp'] = ["Presupuesto actualizado y PDF generado, pero el paciente no tiene un email registrado para el envío."];
                     $redirect_status_key = 'warning_email_failed_edit';
                }
            } else if ($action_type === 'update_send') { 
                 throw new Exception("Error (re)generando PDF: " . ($pdf_result['message'] ?? 'Desconocido'));
            }
        } 

        $mysqli->commit();
        
        $redirect_url = '../index.php?status=' . $redirect_status_key . '&estimate_number=' . urlencode($estimate_number_from_db);
        if (isset($_SESSION['estimate_form_errors_edit_temp']) && !empty($_SESSION['estimate_form_errors_edit_temp'])) {
             $redirect_url .= '&msg=' . urlencode(implode("; ", $_SESSION['estimate_form_errors_edit_temp']));
             unset($_SESSION['estimate_form_errors_edit_temp']); 
        }
        header('Location: ' . $redirect_url);
        exit;

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error en update_estimate.php: " . $e->getMessage());
        $_SESSION['estimate_form_errors_edit'] = ["Error interno del servidor: " . $e->getMessage()];
        $_SESSION['estimate_form_data_edit'] = $_POST; 
        header('Location: ../edit.php?id=' . $estimate_id_to_update . '&status=error_db_update&msg=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: ../index.php?status=error_invalid_request');
    exit;
}
?>