<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 
require_once 'generate_estimate_pdf.php'; // Asegúrate que la ruta sea correcta

// Verificar permisos (puedes usar el permiso 'estimates' o uno más específico si lo creas)
if (!user_has_permission('estimates', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('HTTP/1.1 403 Forbidden');
    die('Acceso denegado a este recurso.');
}

$estimate_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$action = trim($_GET['action'] ?? 'view'); // 'view' o 'download'

if (!$estimate_id) {
    header('HTTP/1.1 400 Bad Request');
    die('ID de presupuesto no válido o no proporcionado.');
}

// Generar el PDF
$pdf_result = generate_estimate_pdf($estimate_id, $mysqli);

if ($pdf_result['success']) {
    $pdf_filepath_absolute = $pdf_result['filepath_absolute'];
    $pdf_display_name = basename($pdf_result['filename_db_relative']); // Nombre para descarga o título

    if (file_exists($pdf_filepath_absolute)) {
        header('Content-Type: application/pdf');
        
        if ($action === 'download') {
            header('Content-Disposition: attachment; filename="' . $pdf_display_name . '"');
        } else {
            // Por defecto, o si action es 'view', mostrar en el navegador
            header('Content-Disposition: inline; filename="' . $pdf_display_name . '"');
        }
        
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . filesize($pdf_filepath_absolute));
        
        // Limpiar cualquier buffer de salida antes de enviar el archivo
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        readfile($pdf_filepath_absolute);
        
        // Opcional: Eliminar el archivo PDF del servidor después de enviarlo si no quieres conservarlo
        // Por ahora lo dejamos, ya que lo guardamos en la BD y podría ser útil para reenvíos.
        // Si lo borras aquí, asegúrate que el `save_estimate.php` no lo necesite después.
        // unlink($pdf_filepath_absolute); 

        exit;
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        error_log("Error en view_pdf.php: PDF generado pero archivo no encontrado en " . $pdf_filepath_absolute . " para estimate_id " . $estimate_id);
        die('Error: El archivo PDF generado no se pudo encontrar en el servidor.');
    }
} else {
    header('HTTP/1.1 500 Internal Server Error');
    error_log("Error en view_pdf.php al llamar a generate_estimate_pdf para estimate_id " . $estimate_id . ": " . ($pdf_result['message'] ?? 'Desconocido'));
    die('Error al generar el PDF del presupuesto: ' . htmlspecialchars($pdf_result['message'] ?? 'Error desconocido.'));
}

?>