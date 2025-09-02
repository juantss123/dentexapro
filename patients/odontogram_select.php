<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!user_has_permission('patients_odontogram', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$success_message = '';
$error_message = '';
$info_message = '';


$search_term = trim($_GET['search_term'] ?? '');
$search_by = trim($_GET['search_by'] ?? 'lname'); // Por defecto buscar por apellido
$patients_result = null;
$search_performed = false;

if (!empty($search_term)) {
    $search_performed = true;
    $search_query_param = "%" . $search_term . "%";
    
    if ($search_by === 'dni') {
        $stmt_patients = $mysqli->prepare("SELECT id, dni, fname, lname, birthdate FROM patients WHERE dni LIKE ? ORDER BY lname ASC, fname ASC");
        $stmt_patients->bind_param("s", $search_query_param);
    } elseif ($search_by === 'fname') {
        $stmt_patients = $mysqli->prepare("SELECT id, dni, fname, lname, birthdate FROM patients WHERE fname LIKE ? ORDER BY lname ASC, fname ASC");
        $stmt_patients->bind_param("s", $search_query_param);
    } else { // Por defecto o si es 'lname'
        $stmt_patients = $mysqli->prepare("SELECT id, dni, fname, lname, birthdate FROM patients WHERE lname LIKE ? ORDER BY lname ASC, fname ASC");
        $stmt_patients->bind_param("s", $search_query_param);
    }
    
    if ($stmt_patients) { // Verificar si la preparación fue exitosa
        $stmt_patients->execute();
        $patients_result = $stmt_patients->get_result();
    } else {
        // Manejar error de preparación de consulta si es necesario
        // echo "Error en la preparación de la consulta: " . $mysqli->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seleccionar Paciente para Odontograma - Clínica Dental</title>
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
        .table th i { margin-right: 5px; }
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
                        <h2 class="mb-0 d-flex align-items-center"><i class="fas fa-tooth me-3 text-info"></i>Seleccionar Paciente para Odontograma</h2>
                        <a href="<?php echo $path_to_root; ?>patients/index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Volver al Listado General</a>
                    </div>

                    <form class="mb-4 p-3 border rounded bg-light" method="GET" action="odontogram_select.php">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="search_term" class="form-label fw-bold">Término de Búsqueda:</label>
                                <input type="text" name="search_term" id="search_term" class="form-control form-control-lg" placeholder="DNI, Nombre o Apellido..." value="<?php echo htmlspecialchars($search_term); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="search_by" class="form-label fw-bold">Buscar por:</label>
                                <select name="search_by" id="search_by" class="form-select form-select-lg">
                                    <option value="lname" <?php if($search_by == 'lname') echo 'selected'; ?>>Apellido</option>
                                    <option value="fname" <?php if($search_by == 'fname') echo 'selected'; ?>>Nombre</option>
                                    <option value="dni" <?php if($search_by == 'dni') echo 'selected'; ?>>DNI</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary btn-lg w-100" type="submit"><i class="fas fa-search me-1"></i>Buscar Paciente</button>
                            </div>
                        </div>
                    </form>

                    <?php if ($search_performed): ?>
                        <?php if ($patients_result && $patients_result->num_rows > 0): ?>
                            <h4 class="mt-4 mb-3">Resultados de la Búsqueda <small class="text-muted fs-6">(<?php echo $patients_result->num_rows; ?> encontrados)</small></h4>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-bordered align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th><i class="fas fa-id-card"></i>DNI</th>
                                            <th><i class="fas fa-user"></i>Nombre Completo</th>
                                            <th><i class="fas fa-calendar-alt"></i>Fecha Nac.</th>
                                            <th><i class="fas fa-cogs"></i>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $patients_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['dni']); ?></td>
                                                <td><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></td>
                                                <td><?php echo $row['birthdate'] ? htmlspecialchars(date('d/m/Y', strtotime($row['birthdate']))) : '-'; ?></td>
                                                <td>
                                                    <a href="odontogram.php?patient_id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-tooth me-1"></i>Ver Odontograma
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mt-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>No se encontraron pacientes con el término "<?php echo htmlspecialchars($search_term); ?>" buscando por <?php echo htmlspecialchars(ucfirst($search_by)); // Muestra el criterio de búsqueda ?>.
                            </div>
                        <?php endif; ?>
                        <?php if ($patients_result) $patients_result->close(); ?>
                        <?php if (isset($stmt_patients) && $stmt_patients) $stmt_patients->close(); ?>
                    <?php elseif(isset($_GET['search_term'])): ?>
                        <div class="alert alert-info mt-4 text-center">
                            <i class="fas fa-info-circle me-2"></i>Por favor, ingrese un término para buscar un paciente.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light mt-4 text-center">
                            <i class="fas fa-search me-2"></i>Utilice el formulario de arriba para buscar un paciente y ver su odontograma.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>