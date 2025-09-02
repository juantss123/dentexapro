<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!user_has_permission('system', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$success_message = '';
$error_message = '';
$info_message = '';

$db_backup_success_message = '';
$db_backup_error_message = '';
$restore_success_message = '';
$restore_error_message = '';
$full_backup_success_message = ''; // No se usará si la descarga es directa
$full_backup_error_message = '';

// --- Lógica para Crear Backup SOLO de Base de Datos ---
if (isset($_POST['create_db_backup'])) {
    $db_backup_file_name = DB_NAME . '_db_backup_' . date("Y-m-d_H-i-s") . '.sql';
    // Asegúrate de que la ruta a mysqldump sea correcta para tu entorno o que esté en el PATH
    $mysqldump_path = 'mysqldump'; // EJEMPLO: 'C:/xampp/mysql/bin/mysqldump.exe' o '/usr/bin/mysqldump'

    $db_command = sprintf('%s --user="%s" --password="%s" --host="%s" --opt --skip-lock-tables --databases "%s"',
        $mysqldump_path, escapeshellarg(DB_USER), escapeshellarg(DB_PASS),
        escapeshellarg(DB_HOST), escapeshellarg(DB_NAME)
    );
    if (empty(DB_PASS)) { 
         $db_command = sprintf('%s --user="%s" --host="%s" --opt --skip-lock-tables --databases "%s"',
            $mysqldump_path, escapeshellarg(DB_USER), escapeshellarg(DB_HOST),
            escapeshellarg(DB_NAME)
        );
    }
    
    // Limpiar cualquier salida previa
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Forzar descarga del archivo SQL directamente
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($db_backup_file_name) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    flush(); // Enviar cabeceras

    // Ejecutar el comando y enviar la salida directamente al navegador
    passthru($db_command, $db_return_var);

    if ($db_return_var !== 0) {
        // Si hubo un error, es difícil mostrar un mensaje en la página porque ya se enviaron cabeceras.
        // Se podría loguear el error.
        error_log("Error al crear el backup de la BD (passthru). Código: " . $db_return_var);
        // No se puede redirigir con header() aquí.
    }
    exit; // Terminar script después de la descarga
}

// --- Lógica para Crear Backup COMPLETO (Archivos + BD) ---
if (isset($_POST['create_full_backup'])) {
    @set_time_limit(600); // Aumentar tiempo de ejecución (10 minutos)
    @ini_set('memory_limit', '256M'); // Aumentar límite de memoria

    $temp_sql_file_name = 'temp_db_dump_' . time() . '.sql';
    $temp_sql_file_path = UPLOAD_DIR . $temp_sql_file_name; // UPLOAD_DIR debe ser escribible

    // 1. Crear dump de la BD temporalmente
    $mysqldump_path_full = 'C:/xampp/mysql/bin/mysqldump.exe'; // Ajusta si es necesario
    $db_dump_command = sprintf('%s --user="%s" --password="%s" --host="%s" --opt --skip-lock-tables --databases "%s" > %s',
        $mysqldump_path_full, escapeshellarg(DB_USER), escapeshellarg(DB_PASS),
        escapeshellarg(DB_HOST), escapeshellarg(DB_NAME), escapeshellarg($temp_sql_file_path)
    );
    if (empty(DB_PASS)) {
         $db_dump_command = sprintf('%s --user="%s" --host="%s" --opt --skip-lock-tables --databases "%s" > %s',
            $mysqldump_path_full, escapeshellarg(DB_USER), escapeshellarg(DB_HOST),
            escapeshellarg(DB_NAME), escapeshellarg($temp_sql_file_path)
        );
    }

    @exec($db_dump_command, $dump_output, $dump_return_var);

    if ($dump_return_var === 0 && file_exists($temp_sql_file_path)) {
        // 2. Crear archivo ZIP
        $zip_file_name = 'sitio_completo_' . str_replace(' ', '_', DB_NAME) . '_' . date("Y-m-d_H-i-s") . '.zip';
        $zip_file_path = UPLOAD_DIR . $zip_file_name; // Guardar ZIP temporalmente

        $zip = new ZipArchive();
        if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            // Añadir el archivo SQL al ZIP
            $zip->addFile($temp_sql_file_path, 'database_backup/' . basename($temp_sql_file_name));

            // Añadir archivos del proyecto
            $source_path = realpath(PROJECT_ROOT); // PROJECT_ROOT viene de config.php
            
            $excluded_paths = [
                basename(UPLOAD_DIR) . DIRECTORY_SEPARATOR . $zip_file_name, 
                basename(UPLOAD_DIR) . DIRECTORY_SEPARATOR . $temp_sql_file_name, 
                '.git', 
                '.vscode',
                'vendor', // Común para excluir dependencias de Composer
                'node_modules', // Común para excluir dependencias de Node
                // basename(__FILE__), // Excluir este mismo script si está dentro de PROJECT_ROOT/system
            ];
            // Asegurarse que la exclusión de este script sea correcta si está dentro de lo que se zipea
            $this_script_relative_path = str_replace($source_path . DIRECTORY_SEPARATOR, '', __FILE__);
            $excluded_paths[] = $this_script_relative_path;


            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($source_path) + 1);

                    $exclude_this = false;
                    foreach ($excluded_paths as $excluded_path) {
                        // Normalizar separadores para la comparación
                        $normalized_relative_path = str_replace('\\', '/', $relativePath);
                        $normalized_excluded_path = str_replace('\\', '/', $excluded_path);
                        if (strpos($normalized_relative_path, $normalized_excluded_path) === 0) {
                            $exclude_this = true;
                            break;
                        }
                    }
                    if ($exclude_this) continue;

                    $zip->addFile($filePath, $relativePath);
                }
            }
            
            $zip_close_status = $zip->close();

            if (file_exists($temp_sql_file_path)) {
                unlink($temp_sql_file_path); // Eliminar el archivo SQL temporal
            }

            if ($zip_close_status === TRUE && file_exists($zip_file_path)) {
                // Limpiar cualquier buffer de salida ANTES de enviar cabeceras
                while (ob_get_level()) {
                    ob_end_clean();
                }

                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($zip_file_name) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($zip_file_path));
                flush(); // Enviar cabeceras al navegador
                
                $readfile_result = readfile($zip_file_path); // Enviar el archivo
                
                if (file_exists($zip_file_path)) { // Eliminar el archivo ZIP del servidor después de la descarga
                    unlink($zip_file_path);
                }

                if ($readfile_result === false) {
                    // Si readfile falla, es difícil enviar un error al usuario porque las cabeceras ya se enviaron.
                    error_log("Error al leer el archivo ZIP para la descarga: " . $zip_file_path);
                }
                exit; // Terminar el script
            } else {
                $full_backup_error_message = 'Error al finalizar la creación del archivo ZIP.';
                 if (file_exists($zip_file_path)) unlink($zip_file_path);
            }
        } else {
            $full_backup_error_message = 'Error al abrir/crear el archivo ZIP. Código: ' . $zip->status;
            if (file_exists($temp_sql_file_path)) unlink($temp_sql_file_path);
        }
    } else {
        $full_backup_error_message = "Error al crear el dump de la BD para el backup completo. Código: " . $dump_return_var;
        if (file_exists($temp_sql_file_path)) unlink($temp_sql_file_path);
    }
}


// --- Lógica para Restaurar Backup de BD (sin cambios significativos, solo se mantiene) ---
if (isset($_POST['restore_backup'])) {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file_path = $_FILES['backup_file']['tmp_name'];
        $uploaded_file_name = $_FILES['backup_file']['name'];
        $file_extension = strtolower(pathinfo($uploaded_file_name, PATHINFO_EXTENSION));

        if ($file_extension !== 'sql') {
            $restore_error_message = 'Error: El archivo debe ser de tipo .sql para restaurar la base de datos.';
        } else {
            $mysql_path = 'mysql'; // Ajusta si es necesario
            $command_restore = sprintf('%s --user="%s" --password="%s" --host="%s" "%s" < %s',
                $mysql_path, escapeshellarg(DB_USER), escapeshellarg(DB_PASS),
                escapeshellarg(DB_HOST), escapeshellarg(DB_NAME), escapeshellarg($uploaded_file_path)
            );
             if (empty(DB_PASS)) { 
                $command_restore = sprintf('%s --user="%s" --host="%s" "%s" < %s',
                    $mysql_path, escapeshellarg(DB_USER), escapeshellarg(DB_HOST),
                    escapeshellarg(DB_NAME), escapeshellarg($uploaded_file_path)
                );
            }
            @exec($command_restore, $output_restore, $return_var_restore);
            if ($return_var_restore === 0) {
                $restore_success_message = '¡Base de datos restaurada con éxito desde "' . htmlspecialchars($uploaded_file_name) . '"!';
            } else {
                $restore_error_message = "Error al restaurar la base de datos. Código de error: " . $return_var_restore;
            }
        }
    } elseif (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $restore_error_message = 'Error al subir el archivo de backup. Código: ' . $_FILES['backup_file']['error'];
    } else {
        $restore_error_message = 'Por favor, seleccione un archivo .sql para restaurar la base de datos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Backup y Restauración - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; }
        .chevron { transition: transform .2s; }
        .nav-link[aria-expanded="true"] .chevron { transform: rotate(180deg); }
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
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
            <div class="card main-content-card shadow-sm rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <h2 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-database me-3"></i>Gestión de Base de Datos y Sitio
                        </h2>
                    </div>

                    <?php if ($db_backup_error_message): ?> <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $db_backup_error_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
                    <?php if ($restore_success_message): ?> <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($restore_success_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
                    <?php if ($restore_error_message): ?> <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $restore_error_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
                    <?php if ($full_backup_error_message): ?> <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($full_backup_error_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

                    <div class="row">
                        <div class="col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-database me-2"></i>Backup Solo Base de Datos</h5></div>
                                <div class="card-body d-flex flex-column"><p>Crea una copia de seguridad de la base de datos (archivo <code>.sql</code>).</p><form method="POST" class="mt-auto"><button type="submit" name="create_db_backup" class="btn btn-info w-100"><i class="fas fa-download me-2"></i>Crear Backup BD</button></form></div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-archive me-2"></i>Backup Completo del Sitio</h5></div>
                                <div class="card-body d-flex flex-column"><p>Crea un archivo <code>.zip</code> con todos los archivos del sitio y la base de datos.</p><form method="POST" class="mt-auto"><button type="submit" name="create_full_backup" class="btn btn-primary w-100"><i class="fas fa-file-archive me-2"></i>Crear Backup Completo</button></form></div>
                                <div class="card-footer text-muted small">Este proceso puede tardar unos momentos.</div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm border-danger">
                                <div class="card-header bg-danger text-white"><h5 class="mb-0"><i class="fas fa-upload me-2"></i>Restaurar Base de Datos</h5></div>
                                <div class="card-body d-flex flex-column">
                                    <div class="alert alert-warning p-2 small mb-3" role="alert"><strong class="d-block"><i class="fas fa-exclamation-triangle"></i> ¡ADVERTENCIA!</strong>Esto sobreescribirá la BD actual. Use con cautela.</div>
                                    <form method="POST" enctype="multipart/form-data" onsubmit="return confirmRestore();" class="mt-auto">
                                        <div class="mb-2"><label for="backup_file" class="form-label small fw-bold">Archivo <code>.sql</code>:</label><input class="form-control form-control-sm" type="file" id="backup_file" name="backup_file" accept=".sql" required></div>
                                        <button type="submit" name="restore_backup" class="btn btn-danger w-100"><i class="fas fa-undo me-2"></i>Restaurar BD</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmRestore() {
            const confirmationText = "CONFIRMAR RESTAURACIÓN";
            const userInput = prompt("Esta acción es IRREVERSIBLE y sobreescribirá todos los datos actuales.\n\nPara confirmar, escriba exactamente la siguiente frase (sin comillas y en mayúsculas):\n\"" + confirmationText + "\"");
            if (userInput === null) { alert("Restauración cancelada por el usuario."); return false; }
            if (userInput.trim() === confirmationText) { return confirm("ADVERTENCIA FINAL:\n¿Está ABSOLUTAMENTE SEGURO de que desea restaurar la base de datos con el archivo seleccionado? Todos los datos actuales se perderán y esta acción no se puede deshacer.");
            } else { alert("Texto de confirmación incorrecto. La restauración ha sido cancelada para su seguridad."); return false; }
        }
        document.addEventListener('DOMContentLoaded', function () {
            var autoDismissAlerts = document.querySelectorAll('.alert.alert-dismissible');
            autoDismissAlerts.forEach(function(alertEl) {
                setTimeout(function() { if (document.body.contains(alertEl)) { var bsAlert = bootstrap.Alert.getInstance(alertEl); if (bsAlert) { bsAlert.close(); } } }, 7000);
            });
        });
    </script>
</body>
</html>
