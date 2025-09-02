<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403); 
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}
require_once '../../config.php'; 
require_once '../generate_estimate_pdf.php'; 

$response = ['success' => false, 'message' => 'Error desconocido.', 'estimate_number' => null, 'action_type' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $professional_name_input = trim($_POST['professional_name'] ?? ''); 
    $estimate_date_str = trim($_POST['estimate_date'] ?? '');
    $insurance_details = trim($_POST['insurance_details'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    // $status_input = $_POST['status'] ?? 'borrador'; // Ya no se recibe del formulario
    $action_type = $_POST['save_estimate_action'] ?? 'save_draft'; // 'save_draft' o 'save_send'

    // Determinar el estado basado en la acción
    $status_to_save = 'borrador'; // Por defecto
    if ($action_type === 'save_send') {
        $status_to_save = 'enviado';
    }

    $item_descriptions = $_POST['item_description'] ?? [];
    $item_quantities = $_POST['item_quantity'] ?? [];
    $item_unit_prices = $_POST['item_unit_price'] ?? [];

    // --- Validaciones ---
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
    // La validación de $status_input ya no es necesaria aquí porque se determina internamente
    
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
        $_SESSION['estimate_form_errors'] = $errors;
        $_SESSION['estimate_form_data'] = $_POST;
        header('Location: ../create.php?status=error_validation_save');
        exit;
    }

    // Generar número de presupuesto (sin cambios)
    $year = $date_obj ? $date_obj->format('Y') : date('Y');
    $last_number = 0;
    $estimate_number = '';
    $stmt_last_num = $mysqli->prepare("SELECT estimate_number FROM estimates WHERE estimate_number LIKE ? ORDER BY id DESC LIMIT 1");
    if ($stmt_last_num) {
        $prefix_search = "PRES-" . $year . "-%";
        $stmt_last_num->bind_param("s", $prefix_search);
        $stmt_last_num->execute();
        $res_last_num = $stmt_last_num->get_result();
        if ($row_last_num = $res_last_num->fetch_assoc()) {
            $parts = explode('-', $row_last_num['estimate_number']);
            $last_number = intval(end($parts));
        }
        $stmt_last_num->close();
    }
    $new_sequence_num = $last_number + 1;
    $estimate_number = "PRES-" . $year . "-" . str_pad($new_sequence_num, 4, '0', STR_PAD_LEFT);

    $mysqli->begin_transaction();
    try {
        $admin_id_creator = $_SESSION['admin_id'];
        $pdf_filename_for_db = null;

        $stmt_insert_estimate = $mysqli->prepare(
            "INSERT INTO estimates (patient_id, admin_user_id, estimate_number, estimate_date, insurance_details, total_amount, status, notes, professional_name_text, pdf_filename) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt_insert_estimate) throw new Exception("Error preparando cabecera: " . $mysqli->error);
        
        // Usar $status_to_save en lugar de $status_input
        $stmt_insert_estimate->bind_param("iisssdssss", 
            $patient_id, 
            $admin_id_creator, 
            $estimate_number, 
            $estimate_date_str, 
            $insurance_details, 
            $grand_total_calculated, 
            $status_to_save, // CAMBIO AQUÍ
            $notes,
            $professional_name_input,
            $pdf_filename_for_db 
        );
        if (!$stmt_insert_estimate->execute()) throw new Exception("Error guardando cabecera: " . $stmt_insert_estimate->error);
        
        $estimate_id = $mysqli->insert_id;
        $stmt_insert_estimate->close();

        $stmt_insert_item = $mysqli->prepare("INSERT INTO estimate_items (estimate_id, description, quantity, unit_price, item_total) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt_insert_item) throw new Exception("Error preparando ítems: " . $mysqli->error);
        foreach ($parsed_items as $item) {
            $stmt_insert_item->bind_param("isidd", $estimate_id, $item['description'], $item['quantity'], $item['unit_price'], $item['item_total']);
            if (!$stmt_insert_item->execute()) throw new Exception("Error guardando ítem: " . $stmt_insert_item->error);
        }
        $stmt_insert_item->close();

        $redirect_status_key = ($status_to_save === 'borrador') ? 'success_saved_draft' : 'success_create'; 

        // La lógica de PDF y email ahora solo depende de $action_type
        if ($action_type === 'save_send') {
            $pdf_result = generate_estimate_pdf($estimate_id, $mysqli); 

            if ($pdf_result['success']) {
                $pdf_filename_for_db = $pdf_result['filename_db_relative'];
                $absolute_pdf_path = $pdf_result['filepath_absolute'];

                $stmt_update_pdf_name = $mysqli->prepare("UPDATE estimates SET pdf_filename = ? WHERE id = ?");
                if ($stmt_update_pdf_name) {
                    $stmt_update_pdf_name->bind_param("si", $pdf_filename_for_db, $estimate_id);
                    $stmt_update_pdf_name->execute();
                    $stmt_update_pdf_name->close();
                } else {
                    error_log("Error al actualizar pdf_filename para estimate ID {$estimate_id}: " . $mysqli->error);
                }
                
                $patient_email_to = $pdf_result['patient_email'];
                $patient_name_to = $pdf_result['patient_name'];
                $estimate_num_for_subject = $pdf_result['estimate_number'];

                if (!empty($patient_email_to)) {
                    $clinic_email_from_settings = 'configuracion_email_clinica@sudominio.com'; 
                    $clinic_name_from_settings = 'Su Clínica Dental'; 
                    
                    $s_clinic_email_q = $mysqli->query("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_pdf_email' LIMIT 1");
                    if($s_clinic_email_q && $r_ce = $s_clinic_email_q->fetch_assoc()) $clinic_email_from_settings = $r_ce['setting_value'];
                    if($s_clinic_email_q) $s_clinic_email_q->free();

                    $s_clinic_name_q = $mysqli->query("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_pdf_name' LIMIT 1");
                    if($s_clinic_name_q && $r_cn = $s_clinic_name_q->fetch_assoc()) $clinic_name_from_settings = $r_cn['setting_value'];
                    if($s_clinic_name_q) $s_clinic_name_q->free();

                    $subject = "Presupuesto Dental N° {$estimate_num_for_subject} - {$clinic_name_from_settings}";
                    $email_body_html = "
                    <html><body style='font-family: Arial, sans-serif; color: #333;'>
                    <p>Estimado/a {$patient_name_to},</p>
                    <p>Adjunto encontrará el presupuesto N° <strong>{$estimate_num_for_subject}</strong> correspondiente a los tratamientos dentales conversados.</p>
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
                            error_log("Error al enviar email del presupuesto {$estimate_id} a {$patient_email_to}");
                            $_SESSION['estimate_form_errors_temp'] = ["Presupuesto guardado y PDF generado, pero hubo un error al enviar el email."];
                            $redirect_status_key = 'warning_email_failed'; 
                        } else {
                             $redirect_status_key = 'success_create'; 
                        }
                    } else {
                        error_log("Error crítico: PDF no encontrado en {$absolute_pdf_path} para adjuntar al email.");
                        $_SESSION['estimate_form_errors_temp'] = ["Presupuesto guardado, pero el PDF no se encontró para el envío."];
                        $redirect_status_key = 'warning_email_failed';
                    }
                } else {
                     $_SESSION['estimate_form_errors_temp'] = ["Presupuesto guardado y PDF generado, pero el paciente no tiene un email registrado para el envío."];
                     $redirect_status_key = 'warning_email_failed';
                }
            } else { 
                 throw new Exception("Error generando PDF: " . ($pdf_result['message'] ?? 'Desconocido'));
            }
        }

        $mysqli->commit();
        
        $redirect_url = '../index.php?status=' . $redirect_status_key . '&estimate_number=' . urlencode($estimate_number);
        if (isset($_SESSION['estimate_form_errors_temp']) && !empty($_SESSION['estimate_form_errors_temp'])) {
             $redirect_url .= '&msg=' . urlencode(implode("; ", $_SESSION['estimate_form_errors_temp']));
             unset($_SESSION['estimate_form_errors_temp']); 
        }
        header('Location: ' . $redirect_url);
        exit;

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error en save_estimate.php: " . $e->getMessage());
        $_SESSION['estimate_form_errors'] = ["Error interno del servidor: " . $e->getMessage()];
        $_SESSION['estimate_form_data'] = $_POST; 
        header('Location: ../create.php?status=error_db_save&msg=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: ../create.php?status=error_invalid_request');
    exit;
}
?>