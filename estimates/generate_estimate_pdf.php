<?php
// estimates/generate_estimate_pdf.php

if (file_exists('../../config.php')) {
    require_once '../../config.php';
    if (defined('PROJECT_ROOT') && file_exists(PROJECT_ROOT . '/lib/fpdf/fpdf.php')) {
        require_once PROJECT_ROOT . '/lib/fpdf/fpdf.php';
    } else {
        die('Error: FPDF library not found at default location.');
    }
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
    if (defined('PROJECT_ROOT') && file_exists(PROJECT_ROOT . '/lib/fpdf/fpdf.php')) {
         require_once PROJECT_ROOT . '/lib/fpdf/fpdf.php';
    } else {
        die('Error: FPDF library not found (fallback path).');
    }
} else {
    die('Error: No se pudo encontrar config.php para generar PDF.');
}


function generate_estimate_pdf($estimate_id, $mysqli_conn) {
    // --- 1. Obtener Datos del Presupuesto (sin cambios) ---
    $estimate_data = null;
    $estimate_items = [];
    
    $stmt_est = $mysqli_conn->prepare("SELECT e.*, p.fname as p_fname, p.lname as p_lname, p.dni as p_dni, p.email as p_email,
                                         p.address as p_address, p.phone as p_phone, 
                                         au.name as admin_creator_name, e.professional_name_text
                                   FROM estimates e 
                                   JOIN patients p ON e.patient_id = p.id
                                   JOIN admin_users au ON e.admin_user_id = au.id
                                   WHERE e.id = ?");
    if (!$stmt_est) return ['success' => false, 'message' => 'Error preparando presupuesto: ' . $mysqli_conn->error];
    $stmt_est->bind_param("i", $estimate_id);
    $stmt_est->execute();
    $result_est = $stmt_est->get_result();
    $estimate_data = $result_est->fetch_assoc();
    $stmt_est->close();

    if (!$estimate_data) return ['success' => false, 'message' => 'Presupuesto no encontrado para generar PDF. ID: ' . $estimate_id];
    
    $professional_attending_name_pdf = !empty($estimate_data['professional_name_text']) ? $estimate_data['professional_name_text'] : $estimate_data['admin_creator_name'];

    $stmt_items = $mysqli_conn->prepare("SELECT * FROM estimate_items WHERE estimate_id = ? ORDER BY id ASC");
    if (!$stmt_items) return ['success' => false, 'message' => 'Error preparando ítems: ' . $mysqli_conn->error];
    $stmt_items->bind_param("i", $estimate_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while ($row = $result_items->fetch_assoc()) {
        $estimate_items[] = $row;
    }
    $stmt_items->close();

    if (empty($estimate_items)) return ['success' => false, 'message' => 'No hay ítems en el presupuesto ID: ' . $estimate_id];

    // --- 2. Obtener Datos de la Clínica desde clinic_settings (sin cambios) ---
    $clinic_settings_pdf = [];
    $default_clinic_settings = [ 
        'clinic_pdf_logo_path' => '', 'clinic_pdf_name' => 'Nombre de Clínica No Configurado',
        'clinic_pdf_address' => 'Dirección No Configurada', 'clinic_pdf_phone' => '',
        'clinic_pdf_email' => '', 'clinic_pdf_cuit' => '',
        'clinic_pdf_footer_notes' => 'Gracias por su confianza.'
    ];
    $keys_to_fetch = array_keys($default_clinic_settings);
    $placeholders = implode(',', array_fill(0, count($keys_to_fetch), '?'));
    $types = str_repeat('s', count($keys_to_fetch));
    $stmt_settings = $mysqli_conn->prepare("SELECT setting_key, setting_value FROM clinic_settings WHERE setting_key IN ($placeholders)");
    if ($stmt_settings) {
        $stmt_settings->bind_param($types, ...$keys_to_fetch);
        $stmt_settings->execute();
        $result_settings = $stmt_settings->get_result();
        while ($row_setting = $result_settings->fetch_assoc()) {
            if (isset($row_setting['setting_value'])) { // Guardar incluso si está vacío, para evitar usar el default si se borró intencionalmente
                 $clinic_settings_pdf[$row_setting['setting_key']] = $row_setting['setting_value'];
            }
        }
        $stmt_settings->close();
    }
    $clinic_settings_pdf = array_merge($default_clinic_settings, $clinic_settings_pdf);


    // --- 3. Crear PDF con FPDF y Diseño Mejorado ---
    class PDF_Estimate_FPDF_V2 extends FPDF { // Renombrar para evitar conflictos si la clase antigua se carga
        private $clinicSettings;
        private $currentEstimateData;
        private $primaryColorRGB = [13, 110, 253]; 
        private $secondaryColorRGB = [108, 117, 125]; 
        private $tableHeaderBgRGB = [60, 70, 80]; // Un gris más oscuro para cabecera de tabla
        private $tableHeaderTextRGB = [255, 255, 255]; // Texto blanco para cabecera de tabla
        private $borderColorRGB = [200, 200, 200]; // Borde más sutil para celdas
        private $textColorRGB = [33, 37, 41]; 
        private $rowHeight = 6; // Altura base de línea/celda en mm

        function setClinicSettings($settings) { $this->clinicSettings = $settings; }
        function setCurrentEstimateData($data) { $this->currentEstimateData = $data; }

        function NbLines($w, $txt) {
            $cw = &$this->CurrentFont['cw'];
            if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
            $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
            $s = str_replace("\r", '', $txt);
            $nb = strlen($s);
            if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
            $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
            while ($i < $nb) {
                $c = $s[$i];
                if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
                if ($c == ' ') $sep = $i;
                $l += $cw[$c] ?? 600; // Fallback si el caracter no está en cw
                if ($l > $wmax) {
                    if ($sep == -1) { if ($i == $j) $i++; } 
                    else $i = $sep + 1;
                    $sep = -1; $j = $i; $l = 0; $nl++;
                } else $i++;
            }
            return $nl;
        }

        function Header() {
            $this->SetFillColor($this->primaryColorRGB[0], $this->primaryColorRGB[1], $this->primaryColorRGB[2]);
            $this->Rect(0, 0, $this->GetPageWidth(), 4, 'F'); 
            $this->Ln(6);

            $logoPath = $this->clinicSettings['clinic_pdf_logo_path'] ?? null;
            $logoDisplayWidth = 0; $logoDisplayHeight = 0;
            $initialY = $this->GetY();

            if ($logoPath && file_exists(PROJECT_ROOT . '/' . $logoPath)) {
                list($logoWidthPx, $logoHeightPx) = @getimagesize(PROJECT_ROOT . '/' . $logoPath);
                if ($logoWidthPx && $logoHeightPx) {
                    $aspectRatio = $logoWidthPx / $logoHeightPx;
                    $logoDisplayHeight = 18; 
                    $logoDisplayWidth = $logoDisplayHeight * $aspectRatio;
                    if ($logoDisplayWidth > 50) { $logoDisplayWidth = 50; $logoDisplayHeight = $logoDisplayWidth / $aspectRatio; }
                    $this->Image(PROJECT_ROOT . '/' . $logoPath, 15, $initialY, $logoDisplayWidth, $logoDisplayHeight);
                }
            }
            
            $rightColX = $this->GetPageWidth() - 15 - 70; 

            $this->SetXY($rightColX, $initialY);
            $this->SetFont('Helvetica', 'B', 20);
            $this->SetTextColor($this->primaryColorRGB[0], $this->primaryColorRGB[1], $this->primaryColorRGB[2]);
            $this->Cell(70, 9, 'PRESUPUESTO', 0, 1, 'R');
            
            $this->SetFont('Helvetica', '', 10);
            $this->SetTextColor($this->textColorRGB[0], $this->textColorRGB[1], $this->textColorRGB[2]);
            $this->SetX($rightColX);
            $this->Cell(70, 5, utf8_decode('Número: ') . ($this->currentEstimateData['estimate_number'] ?? 'N/A'), 0, 1, 'R');
            $this->SetX($rightColX);
            $this->Cell(70, 5, utf8_decode('Fecha: ') . date('d/m/Y', strtotime($this->currentEstimateData['estimate_date'] ?? date('Y-m-d'))), 0, 1, 'R');

            $textStartX = 15;
            $yForClinicDetails = $initialY;
            if ($logoDisplayWidth > 0) {
                 $textStartX = 15 + $logoDisplayWidth + 5; 
                 if ($yForClinicDetails < ($initialY + $logoDisplayHeight - 5)) { // Si el logo es alto
                    $yForClinicDetails = $initialY + $logoDisplayHeight - 15; // Ajustar para que no se superponga
                 }
            }
            
            $this->SetXY($textStartX, $yForClinicDetails);
            $this->SetFont('Helvetica', 'B', 11);
            $this->Cell($rightColX - $textStartX - 10, 6, utf8_decode($this->clinicSettings['clinic_pdf_name'] ?? 'Clínica Dental'), 0, 1, 'L');
            $this->SetFont('Helvetica', '', 8);
            if (!empty($this->clinicSettings['clinic_pdf_address'])) {
                $this->SetX($textStartX); $this->MultiCell($rightColX - $textStartX - 10, 4, utf8_decode($this->clinicSettings['clinic_pdf_address']), 0, 'L');
            }
            if (!empty($this->clinicSettings['clinic_pdf_phone'])) {
                $this->SetX($textStartX); $this->Cell($rightColX - $textStartX - 10, 4, utf8_decode('Tel: ' . $this->clinicSettings['clinic_pdf_phone']), 0, 1, 'L');
            }
            if (!empty($this->clinicSettings['clinic_pdf_email'])) {
                $this->SetX($textStartX); $this->Cell($rightColX - $textStartX - 10, 4, utf8_decode('Email: ' . $this->clinicSettings['clinic_pdf_email']), 0, 1, 'L');
            }
            if (!empty($this->clinicSettings['clinic_pdf_cuit'])) {
                $this->SetX($textStartX); $this->Cell($rightColX - $textStartX - 10, 4, utf8_decode('CUIT: ' . $this->clinicSettings['clinic_pdf_cuit']), 0, 1, 'L');
            }
            
            $currentMaxY = max($this->GetY(), $initialY + $logoDisplayHeight);
            $this->SetY($currentMaxY + 5); // Espacio antes de los detalles del paciente
        }

        function Footer() {
            $this->SetY(-25); 
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor($this->secondaryColorRGB[0], $this->secondaryColorRGB[1], $this->secondaryColorRGB[2]);
            $footerNotes = $this->clinicSettings['clinic_pdf_footer_notes'] ?? '';
            if(!empty($footerNotes)) {
                $this->MultiCell(0, 4, utf8_decode($footerNotes), 0, 'C');
            } else { $this->Ln(4); }
            
            $this->SetY(-12); 
            $this->SetFont('Helvetica', 'I', 7);
            $this->SetTextColor(150,150,150); 
            $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
            $this->SetFillColor($this->primaryColorRGB[0], $this->primaryColorRGB[1], $this->primaryColorRGB[2]);
            $this->Rect(0, $this->GetPageHeight() - 3, $this->GetPageWidth(), 3, 'F');
        }

        function PatientDetailsBox($patient_name, $patient_dni, $insurance_details, $professional_name, $patient_address, $patient_phone) {
            $this->SetFont('Helvetica', 'B', 10);
            $this->SetFillColor($this->tableHeaderBgRGB[0], $this->tableHeaderBgRGB[1], $this->tableHeaderBgRGB[2]);
            $this->SetTextColor($this->tableHeaderTextRGB[0], $this->tableHeaderTextRGB[1], $this->tableHeaderTextRGB[2]);
            $this->Cell(0, 7, utf8_decode('  DATOS DEL PACIENTE Y PRESUPUESTO'), 0, 1, 'L', true);
            $this->Ln(1);
            
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor($this->textColorRGB[0], $this->textColorRGB[1], $this->textColorRGB[2]);
            $lineHeight = 5.5;
            $labelWidth = 35;

            $this->SetFont('', 'B'); $this->Cell($labelWidth, $lineHeight, utf8_decode('Paciente:'), 0, 0);
            $this->SetFont(''); $this->MultiCell(0, $lineHeight, utf8_decode($patient_name), 0, 'L');
            $this->SetFont('', 'B'); $this->Cell($labelWidth, $lineHeight, utf8_decode('DNI:'), 0, 0);
            $this->SetFont(''); $this->Cell(0, $lineHeight, utf8_decode($patient_dni ?? 'N/A'), 0, 1);
             if(!empty($patient_address)){
                $this->SetFont('', 'B'); $this->Cell($labelWidth, $lineHeight, utf8_decode('Dirección:'), 0, 0);
                $this->SetFont(''); $this->MultiCell(0, $lineHeight, utf8_decode($patient_address), 0, 'L');
            }
            if(!empty($patient_phone)){
                $this->SetFont('', 'B'); $this->Cell($labelWidth, $lineHeight, utf8_decode('Teléfono:'), 0, 0);
                $this->SetFont(''); $this->Cell(0, $lineHeight, utf8_decode($patient_phone), 0, 1);
            }
            if (!empty($insurance_details)) {
                $this->SetFont('', 'B'); $this->Cell($labelWidth, $lineHeight, utf8_decode('Cobertura:'), 0, 0);
                $this->SetFont(''); $this->MultiCell(0, $lineHeight, utf8_decode($insurance_details), 0, 'L');
            }
            $this->SetFont('', 'B'); $this->Cell($labelWidth, $lineHeight, utf8_decode('Profesional:'), 0, 0); 
            $this->SetFont(''); $this->Cell(0, $lineHeight, utf8_decode($professional_name), 0, 1); 
            $this->Ln(5);
        }

        function EstimateItemsTable($header, $data_items) {
            $this->SetFillColor($this->tableHeaderBgRGB[0], $this->tableHeaderBgRGB[1], $this->tableHeaderBgRGB[2]);
            $this->SetTextColor($this->tableHeaderTextRGB[0], $this->tableHeaderTextRGB[1], $this->tableHeaderTextRGB[2]);
            $this->SetDrawColor($this->borderColorRGB[0], $this->borderColorRGB[1], $this->borderColorRGB[2]);
            $this->SetLineWidth(.2);
            $this->SetFont('Helvetica', 'B', 9);
            
            $w = array(90, 25, 35, 30); 
            for($i=0; $i<count($header); $i++)
                $this->Cell($w[$i], 8, utf8_decode($header[$i]), 1, 0, 'C', true); // Alto de celda 8
            $this->Ln();
            
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor($this->textColorRGB[0], $this->textColorRGB[1], $this->textColorRGB[2]);
            $fill = false;
            foreach($data_items as $row) {
                $this->SetFillColor($fill ? 248 : 255, $fill ? 248 : 255, $fill ? 248 : 255);
                
                // Calcular altura necesaria para la descripción
                $linesInDescription = $this->NbLines($w[0] - 2, utf8_decode($row['description'])); // -2 for padding
                $cellHeight = $this->rowHeight * $linesInDescription; // Altura dinámica

                $x = $this->GetX();
                $y = $this->GetY();
                $this->MultiCell($w[0], $this->rowHeight, utf8_decode($row['description']), 1, 'L', $fill);
                
                $this->SetXY($x + $w[0], $y); // Volver al inicio Y de la fila, y X de la siguiente celda

                $this->Cell($w[1], $cellHeight, $row['quantity'], 1, 0, 'C', $fill);
                $this->Cell($w[2], $cellHeight, '$ ' . number_format($row['unit_price'], 2, ',', '.'), 1, 0, 'R', $fill);
                $this->Cell($w[3], $cellHeight, '$ ' . number_format($row['item_total'], 2, ',', '.'), 1, 0, 'R', $fill);
                $this->Ln($cellHeight); // Salto de línea según la altura de la descripción
                
                $fill = !$fill;
            }
            $this->Ln(1); // Pequeño espacio después de la tabla
        }

        function TotalsSection($total_amount) {
            $this->SetFont('Helvetica', 'B', 11);
            $this->SetFillColor($this->tableHeaderBgRGB[0], $this->tableHeaderBgRGB[1], $this->tableHeaderBgRGB[2]);
            $this->SetTextColor($this->tableHeaderTextRGB[0], $this->tableHeaderTextRGB[1], $this->tableHeaderTextRGB[2]);
            
            $xPos = $this->GetX() + 90 + 25; // X_inicio + ancho_desc + ancho_cant
            $this->SetX($xPos);
            $this->Cell(35, 8, utf8_decode('TOTAL:'), 1, 0, 'C', true);
            
            $this->SetFont('Helvetica', 'B', 11); // Total más grande
            $this->SetTextColor($this->textColorRGB[0], $this->textColorRGB[1], $this->textColorRGB[2]);
            $this->SetFillColor(255,255,255);
            $this->Cell(30, 8, '$ ' . number_format($total_amount, 2, ',', '.'), 1, 1, 'R', true); 
            $this->Ln(6);
        }

        function AdditionalNotesSection($notes) {
            if (!empty($notes)) {
                $this->Ln(2);
                $this->SetFont('Helvetica', 'B', 10);
                $this->SetTextColor($this->secondaryColorRGB[0], $this->secondaryColorRGB[1], $this->secondaryColorRGB[2]);
                $this->Cell(0, 6, utf8_decode('Notas Adicionales:'), 0, 1, 'L');
                $this->SetFont('Helvetica', '', 9);
                $this->SetTextColor($this->textColorRGB[0], $this->textColorRGB[1], $this->textColorRGB[2]);
                $this->SetFillColor(249,249,249); // Fondo muy sutil para notas
                $this->SetDrawColor($this->borderColorRGB[0],$this->borderColorRGB[1],$this->borderColorRGB[2]);
                $this->MultiCell(0, 5, utf8_decode($notes), 1, 'L', true); 
                $this->Ln(5);
            }
        }
    } 

    $pdf = new PDF_Estimate_FPDF_V2('P', 'mm', 'A4');
    $pdf->setClinicSettings($clinic_settings_pdf);
    $pdf->setCurrentEstimateData($estimate_data);
    $pdf->AliasNbPages(); 
    $pdf->SetMargins(15, 8, 15); 
    $pdf->SetAutoPageBreak(true, 30); 
    $pdf->AddPage();
    
    $pdf->PatientDetailsBox(
        $estimate_data['p_fname'] . ' ' . $estimate_data['p_lname'],
        $estimate_data['p_dni'],
        $estimate_data['insurance_details'] ?? 'Particular',
        $professional_attending_name_pdf,
        $estimate_data['p_address'], // Pasar dirección y teléfono del paciente
        $estimate_data['p_phone']
    );

    $table_header_pdf = ['TRATAMIENTO / SERVICIO', 'CANT.', 'P. UNITARIO', 'SUBTOTAL'];
    $pdf->EstimateItemsTable($table_header_pdf, $estimate_items);
    
    $pdf->TotalsSection($estimate_data['total_amount']);
    
    $pdf->AdditionalNotesSection($estimate_data['notes']);

    $estimates_pdf_dir_relative = UPLOAD_DIR_NAME_CONST . '/estimates_pdf/'; 
    $estimates_pdf_dir_absolute = PROJECT_ROOT . '/' . $estimates_pdf_dir_relative;
    if (!file_exists($estimates_pdf_dir_absolute)) {
        if (!@mkdir($estimates_pdf_dir_absolute, 0777, true) && !is_dir($estimates_pdf_dir_absolute)) {
             return ['success' => false, 'message' => 'Error: No se pudo crear el directorio para PDFs de presupuestos.'];
        }
    }
    $safe_patient_lname = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($estimate_data['p_lname']));
    $estimate_number_sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $estimate_data['estimate_number']);
    $pdf_file_name_db = 'presupuesto_' . $safe_patient_lname . '_' . $estimate_number_sanitized . '.pdf';
    $pdf_file_path_absolute = $estimates_pdf_dir_absolute . $pdf_file_name_db;
    $pdf_file_path_relative_for_db = $estimates_pdf_dir_relative . $pdf_file_name_db;
    try {
        $pdf->Output('F', $pdf_file_path_absolute); 
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error al generar el archivo PDF: ' . $e->getMessage(), 'details' => $e];
    }
    if (file_exists($pdf_file_path_absolute)) {
        return [
            'success' => true, 'filepath_absolute' => $pdf_file_path_absolute, 
            'filename_db_relative' => $pdf_file_path_relative_for_db,
            'patient_email' => $estimate_data['p_email'], 
            'patient_name' => $estimate_data['p_fname'] . ' ' . $estimate_data['p_lname'],
            'estimate_number' => $estimate_data['estimate_number']
        ];
    } else {
        return ['success' => false, 'message' => 'El archivo PDF no se pudo guardar. Ruta: ' . $pdf_file_path_absolute];
    }
}
?>