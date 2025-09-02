<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; } // Asume que login.php está en la raíz
require_once 'config.php'; // config.php está en la raíz

// Definir $path_to_root para este archivo que está en la raíz
$path_to_root = ''; 

$search_term_raw = trim($_GET['term'] ?? '');
$search_term_sql = "%" . $search_term_raw . "%";
$search_performed = !empty($search_term_raw);

$results_patients = [];
$results_appointments = [];
$results_history_notes = [];

if ($search_performed) {
    // Buscar en Pacientes (DNI, Nombre, Apellido)
    $stmt_patients = $mysqli->prepare("SELECT id, dni, fname, lname, birthdate FROM patients WHERE dni LIKE ? OR fname LIKE ? OR lname LIKE ? ORDER BY lname, fname");
    if ($stmt_patients) {
        $stmt_patients->bind_param("sss", $search_term_sql, $search_term_sql, $search_term_sql);
        $stmt_patients->execute();
        $result = $stmt_patients->get_result();
        while ($row = $result->fetch_assoc()) {
            $results_patients[] = $row;
        }
        $stmt_patients->close();
    }

    // Buscar en Turnos (Motivo y Nombre del Paciente)
    $stmt_appts = $mysqli->prepare("SELECT a.id, a.datetime, a.reason, a.status, p.fname AS patient_fname, p.lname AS patient_lname, p.id AS patient_id
                                    FROM appointments a
                                    JOIN patients p ON a.patient_id = p.id
                                    WHERE a.reason LIKE ? OR p.fname LIKE ? OR p.lname LIKE ?
                                    ORDER BY a.datetime DESC");
    if ($stmt_appts) {
        $stmt_appts->bind_param("sss", $search_term_sql, $search_term_sql, $search_term_sql);
        $stmt_appts->execute();
        $result = $stmt_appts->get_result();
        while ($row = $result->fetch_assoc()) {
            $results_appointments[] = $row;
        }
        $stmt_appts->close();
    }
    
    // Buscar en Notas del Historial Clínico
    $stmt_history = $mysqli->prepare("SELECT ph.id as history_id, ph.note, ph.created_at, p.id AS patient_id, p.fname AS patient_fname, p.lname AS patient_lname
                                      FROM patient_history ph
                                      JOIN patients p ON ph.patient_id = p.id
                                      WHERE ph.note LIKE ?
                                      ORDER BY ph.created_at DESC");
    if ($stmt_history) {
        $stmt_history->bind_param("s", $search_term_sql);
        $stmt_history->execute();
        $result = $stmt_history->get_result();
        while ($row = $result->fetch_assoc()) {
            $results_history_notes[] = $row;
        }
        $stmt_history->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultados de Búsqueda Global - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body{overflow-x:hidden; background-color: #f8f9fa;}
        #sidebar{min-height:100vh;}
        .chevron{transition:transform .2s;}
        .nav-link[aria-expanded="true"] .chevron{transform:rotate(180deg);}
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
        .search-result-section { margin-bottom: 2rem; }
        .search-result-section h4 { border-bottom: 1px solid #dee2e6; padding-bottom: 0.5rem; margin-bottom: 1rem;}
        .result-item { padding: 0.75rem; border: 1px solid #e9ecef; border-radius: .25rem; margin-bottom: 0.75rem; background-color: #fff;}
        .result-item:hover { background-color: #f8f9fa; }
        .result-item .result-title { font-weight: 500; }
        .result-item .result-context { font-size: 0.9em; color: #6c757d; }
        .result-item .result-link { font-size: 0.9em; }
        .highlight { background-color: yellow; font-weight: bold; }
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
                            <i class="fas fa-search me-3 text-primary"></i>Resultados de Búsqueda Global
                        </h2>
                    </div>

                    <?php if (!$search_performed): ?>
                        <div class="alert alert-info">Ingrese un término en la barra de búsqueda global para comenzar.</div>
                    <?php else: ?>
                        <p class="lead mb-4">Resultados para: <strong>"<?php echo htmlspecialchars($search_term_raw); ?>"</strong></p>

                        <?php 
                        $total_results = count($results_patients) + count($results_appointments) + count($results_history_notes);
                        if ($total_results === 0): 
                        ?>
                            <div class="alert alert-warning">No se encontraron resultados para "<?php echo htmlspecialchars($search_term_raw); ?>".</div>
                        <?php else: ?>

                            <?php if (!empty($results_patients)): ?>
                            <section class="search-result-section">
                                <h4><i class="fas fa-user-injured me-2 text-info"></i>Pacientes Encontrados (<?php echo count($results_patients); ?>)</h4>
                                <?php foreach ($results_patients as $patient): ?>
                                    <div class="result-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="result-title">
                                                    <?php echo preg_replace('/(' . preg_quote($search_term_raw, '/') . ')/i', '<span class="highlight">$1</span>', htmlspecialchars($patient['fname'] . ' ' . $patient['lname'])); ?>
                                                </div>
                                                <div class="result-context">
                                                    DNI: <?php echo preg_replace('/(' . preg_quote($search_term_raw, '/') . ')/i', '<span class="highlight">$1</span>', htmlspecialchars($patient['dni'])); ?>
                                                    <?php if($patient['birthdate']): ?>
                                                        | Nac: <?php echo htmlspecialchars(date('d/m/Y', strtotime($patient['birthdate']))); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <a href="<?php echo $path_to_root; ?>patients/history.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-primary result-link">Ver Historial</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </section>
                            <?php endif; ?>

                            <?php if (!empty($results_appointments)): ?>
                            <section class="search-result-section">
                                <h4><i class="fas fa-calendar-check me-2 text-success"></i>Turnos Encontrados (<?php echo count($results_appointments); ?>)</h4>
                                <?php foreach ($results_appointments as $appointment): ?>
                                    <div class="result-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="result-title">
                                                    Turno para: <?php echo preg_replace('/(' . preg_quote($search_term_raw, '/') . ')/i', '<span class="highlight">$1</span>', htmlspecialchars($appointment['patient_fname'] . ' ' . $appointment['patient_lname'])); ?>
                                                </div>
                                                <div class="result-context">
                                                    Fecha: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($appointment['datetime']))); ?>
                                                    <br>Motivo: <?php echo preg_replace('/(' . preg_quote($search_term_raw, '/') . ')/i', '<span class="highlight">$1</span>', htmlspecialchars($appointment['reason'] ?: 'N/A')); ?>
                                                    <br>Estado: <span class="badge bg-<?php 
                                                        switch ($appointment['status']) {
                                                            case 'Programado': echo 'primary'; break;
                                                            case 'Completado': echo 'success'; break;
                                                            case 'Cancelado': echo 'danger'; break;
                                                            default: echo 'secondary'; break;
                                                        }
                                                    ?>"><?php echo htmlspecialchars($appointment['status']); ?></span>
                                                </div>
                                            </div>
                                            <a href="<?php echo $path_to_root; ?>appointments/edit.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary result-link">Ver/Editar Turno</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </section>
                            <?php endif; ?>

                            <?php if (!empty($results_history_notes)): ?>
                            <section class="search-result-section">
                                <h4><i class="fas fa-notes-medical me-2 text-warning"></i>Notas de Historial Encontradas (<?php echo count($results_history_notes); ?>)</h4>
                                <?php foreach ($results_history_notes as $history_note): 
                                    $note_snippet = mb_substr(strip_tags($history_note['note']), 0, 150);
                                    if (mb_strlen(strip_tags($history_note['note'])) > 150) {
                                        $note_snippet .= '...';
                                    }
                                ?>
                                    <div class="result-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="result-title">
                                                    Nota para: <?php echo htmlspecialchars($history_note['patient_fname'] . ' ' . $history_note['patient_lname']); ?>
                                                </div>
                                                <div class="result-context">
                                                    Fecha de Registro: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($history_note['created_at']))); ?>
                                                    <br>Contenido: <?php echo preg_replace('/(' . preg_quote($search_term_raw, '/') . ')/i', '<span class="highlight">$1</span>', htmlspecialchars($note_snippet)); ?>
                                                </div>
                                            </div>
                                            <a href="<?php echo $path_to_root; ?>patients/history.php?patient_id=<?php echo $history_note['patient_id']; ?>#history-entry-<?php echo $history_note['history_id']; ?>" class="btn btn-sm btn-outline-primary result-link">Ver en Historial</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </section>
                            <?php endif; ?>

                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>