<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../config.php';

$path_to_root = '../';

$patient_id = intval($_GET['patient_id'] ?? 0);
if (!$patient_id) {
    header('Location: index.php');
    exit;
}

// Obtener datos del paciente
$stmt_patient = $mysqli->prepare("SELECT fname, lname FROM patients WHERE id = ?");
$stmt_patient->bind_param("i", $patient_id);
$stmt_patient->execute();
$patient_result = $stmt_patient->get_result();
$patient = $patient_result->fetch_assoc();
$patient_name = htmlspecialchars(($patient['fname'] ?? '') . ' ' . ($patient['lname'] ?? ''));
$stmt_patient->close();

// MODIFICACIÓN: Usar `created_at` en lugar de `entry_date`
$stmt_gallery = $mysqli->prepare(
    "SELECT mha.file_name, mh.created_at
     FROM medical_history_attachments mha
     JOIN medical_history mh ON mha.medical_history_id = mh.id
     WHERE mh.patient_id = ?
     ORDER BY mh.created_at DESC, mha.id DESC"
);
$stmt_gallery->bind_param("i", $patient_id);
$stmt_gallery->execute();
$gallery_files = $stmt_gallery->get_result();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Galería de Archivos de <?php echo $patient_name; ?> - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        <?php /* Usando los mismos estilos que tu history.php */ ?>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .gallery-card { transition: box-shadow .3s ease, transform .3s ease; }
        .gallery-card:hover { box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15); transform: translateY(-3px); }
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
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
        <main class="col-12 <?php if (!isMobileDevice()) echo 'col-md-9 col-lg-10'; ?> p-md-4 p-2">
            <div class="card main-content-card rounded-3">
                <div class="card-body p-lg-4 p-md-3 p-2">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap border-bottom pb-3 page-main-header">
                        <h2 class="card-title mb-0 d-flex align-items-center text-primary">
                            <i class="fas fa-images me-3 fa-fw"></i>
                            Galería de: <span class="fw-bold ms-2"><?php echo $patient_name; ?></span>
                        </h2>
                        <a href="history.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Historial
                        </a>
                    </div>
                    
                    <?php if ($gallery_files->num_rows > 0): ?>
                        <div class="row">
                            <?php while ($file = $gallery_files->fetch_assoc()): ?>
                                <?php
                                $file_path = $path_to_root . 'uploads/patient_history/' . htmlspecialchars($file['file_name']);
                                $file_ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                ?>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
                                    <div class="card h-100 gallery-card">
                                        <a href="<?php echo $file_path; ?>" target="_blank" title="Abrir en nueva pestaña">
                                            <?php if ($is_image): ?>
                                                <img src="<?php echo $file_path; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($file['file_name']); ?>" style="height: 150px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="text-center p-4" style="height: 150px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa;">
                                                    <i class="fas fa-file-pdf fa-4x text-danger"></i>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                        <div class="card-body p-2">
                                            <p class="card-text small text-truncate mb-1" title="<?php echo htmlspecialchars($file['file_name']); ?>">
                                               <?php echo htmlspecialchars($file['file_name']); ?>
                                            </p>
                                        </div>
                                        <div class="card-footer p-2 text-center">
                                            <small class="text-muted">Fecha: <?php echo date("d/m/Y", strtotime($file['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">No se encontraron archivos en la galería para este paciente.</div>
                    <?php endif; ?>
                    <?php $gallery_files->close(); ?>

                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>