<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!user_has_permission('patients_list', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$success_message = '';
$error_message = '';
$info_message = '';


// --- INICIO DE LA LÓGICA DE PAGINACIÓN ---
$results_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $results_per_page;

$search = $_GET['search'] ?? '';
$search_param = "%{$search}%";
$where_clause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause = "WHERE fname LIKE ? OR lname LIKE ? OR dni LIKE ?";
    $params = [$search_param, $search_param, $search_param];
    $types = 'sss';
}

$count_sql = "SELECT COUNT(id) as total FROM patients {$where_clause}";
$stmt_count = $mysqli->prepare($count_sql);
if (!empty($search)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result()->fetch_assoc();
$total_results = $count_result['total'];
$total_pages = ceil($total_results / $results_per_page);
$stmt_count->close();

$sql = "SELECT id, fname, lname, dni, phone, email, patient_portal_access FROM patients {$where_clause} ORDER BY lname ASC, fname ASC LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($sql);

if (!empty($search)) {
    $types .= 'ii';
    $params[] = $results_per_page;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $results_per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
// --- FIN DE LA LÓGICA DE PAGINACIÓN ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pacientes - Sistema de Gestión de Odontología</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $path_to_root; ?>assets/css/style.css">
    <style>
        body {
            overflow-x: hidden;
            background-color: #f0f2f5;
        }
        .main-content {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .header-title {
            color: #343a40; /* <-- CAMBIO DE COLOR DEL TÍTULO A NEGRO */
        }
        .btn-primary {
             background-color: #0d6efd;
             border-color: #0d6efd;
        }
        .btn-success {
             background-color: #198754;
             border-color: #198754;
        }
        .table thead {
            background-color: #e9ecef;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        .empty-state i {
            font-size: 4rem;
            color: #ced4da;
            margin-bottom: 1rem;
        }
        .empty-state p {
            font-size: 1.2rem;
            color: #6c757d;
        }
        .pagination .page-link {
            color: #0d6efd;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
        }
        .search-card {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        .btn-group .btn {
            /* Evita que el tooltip se corte en los bordes del btn-group */
            position: relative; 
        }
    </style>
    <style>
        body{ 
            overflow-x:hidden; 
            background-color: #f0f2f5; 
          
        }
        #sidebar{min-height:100vh;}
        .chevron{transition:transform .2s;}
        .nav-link[aria-expanded="true"] .chevron{transform:rotate(180deg);}
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .btn-toggle-nav .nav-link .fa-angle-right { color: #ffc107; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .stat-card { border: none; border-radius: .5rem; transition: transform .2s ease-out, box-shadow .2s ease-out; background-color: #fff; display: flex; flex-direction: column; justify-content: space-between; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 .75rem 1.5rem rgba(0,0,0,.1)!important; }
        .stat-card .card-body { padding: 1.25rem; display: flex; align-items: center; }
        .stat-card .stat-icon-wrapper { font-size: 2rem; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; flex-shrink: 0; }
        .stat-card .stat-icon-wrapper i { font-size: 1.75rem; }
        .stat-card .stat-number { font-size: 2rem; font-weight: 700; font-family: 'Montserrat', sans-serif; color: #343a40; }
        .stat-card .card-title { font-size: 0.85rem; font-weight: 500; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
        .stat-card.border-left-primary .stat-icon-wrapper { background-color: rgba(13, 110, 253, 0.1); color: var(--bs-primary); }
        .stat-card.border-left-success .stat-icon-wrapper { background-color: rgba(25, 135, 84, 0.1); color: var(--bs-success); }
        .stat-card.border-left-info .stat-icon-wrapper { background-color: rgba(13, 202, 240, 0.1); color: var(--bs-info); }
        .stat-card.border-left-warning .stat-icon-wrapper { background-color: rgba(255, 193, 7, 0.1); color: var(--bs-warning); }
        .stat-card.border-left-danger .stat-icon-wrapper { background-color: rgba(220, 53, 69, 0.1); color: var(--bs-danger); }
        .quick-action-card { text-align: center; border: 1px solid #e0e0e0; transition: transform .2s ease-in-out, box-shadow .2s ease-in-out, border-color .2s ease-in-out; background-color: #fff; border-radius: .5rem; }
        .quick-action-card:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.12)!important; border-color: var(--bs-primary); }
        .quick-action-card i { font-size: 2.25rem; margin-bottom: 0.75rem; }
        .quick-action-card .card-title { font-size: 0.95rem; font-weight: 500;}
        .today-appointments-list .list-group-item { border-left: 4px solid var(--bs-primary); margin-bottom: 0.75rem; padding: 0.8rem 1.25rem; border-radius: .375rem; background-color: #fff; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
        .today-appointments-list .list-group-item.appointment-passed { border-left-color: var(--bs-secondary); opacity: 0.7; }
        .today-appointments-list .list-group-item .appointment-time { font-weight: 700; font-size: 1.1rem; }
        .dashboard-section-header { font-family: 'Montserrat', sans-serif; font-weight: 600; color: #495057; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #dee2e6; }
        .dashboard-section-header i { color: var(--bs-primary); }
        .chart-container { background-color: #fff; padding: 1.5rem; border-radius: .5rem; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); display: flex; flex-direction: column; }
        .chart-canvas-wrapper { height: 300px; position: relative; flex-grow: 1; }
        .row.d-flex.align-items-stretch > [class*="col-"] { display: flex; flex-direction: column; }
        .row.d-flex.align-items-stretch > [class*="col-"] > .card,
        .row.d-flex.align-items-stretch > [class*="col-"] > .chart-container { flex-grow: 1; }
    </style>
</head>
<body>
    <div class="row g-0">
        <?php 
        if (isMobileDevice()) {
            require_once '../_sidebarmovil.php';
            echo '<nav class="navbar navbar-dark d-md-none sticky-top mb-3" style="background-color: #0d6efd;"><div class="container-fluid" style="position: relative; display: flex; align-items: center; justify-content: flex-start; min-height: 56px;"><button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile" aria-controls="sidebarMobile" style="z-index: 10;"><i class="fas fa-bars"></i><span class="d-none d-sm-inline ms-1">Menú</span></button><a class="navbar-brand" href="' . $path_to_root . 'dashboard.php" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); padding: 0; line-height: 1;"><img src="' . $path_to_root . 'assets/img/dentexapro-logo-iso-blanco.png" alt="DentexaPro" style="height: 40px; width: auto; max-height: 100%;"></a></div></nav>';
        } else {
            require_once '../_sidebar.php';
        }
        ?>
        <main class="col-12 <?php if (!isMobileDevice()) echo 'col-md-9 col-lg-10'; ?> p-4">
            <div class="main-content p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                    <h1 class="h2 mb-0 header-title"><i class="fas fa-user-injured me-3 text-info"></i>Gestión de Pacientes</h1>
                    <a href="create.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Añadir Paciente</a> </div>

                <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars(urldecode($_GET['msg'])); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars(urldecode($_GET['msg'])); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card search-card p-3 mb-4">
                    <form action="index.php" method="GET" class="d-flex align-items-center">
                        <div class="input-group">
                             <span class="input-group-text bg-white border-end-0"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Buscar por nombre, apellido o DNI..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary ms-2">Buscar</button>
                         <?php if (!empty($search)): ?>
                            <a href="index.php" class="btn btn-outline-secondary ms-2" title="Limpiar búsqueda"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                 <th>Apellido</th>
                                <th>Nombre</th>
                                   <th>DNI</th>
                                <th>Teléfono</th>
                                <th>Email</th>
                                <th class="text-center">Acceso Portal</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(mb_strtoupper($row['lname'])); ?></td>
                        <td><?php echo htmlspecialchars(mb_strtoupper($row['fname'])); ?></td>
            
                                    <td><?php echo htmlspecialchars($row['dni']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
 <td class="text-center">
    <?php if ($row['patient_portal_access'] === 'habilitado'): ?>
        <span class="badge bg-success rounded-pill px-3 py-2">Habilitado</span>
    <?php else: ?>
        <span class="badge bg-secondary rounded-pill px-3 py-2">Deshabilitado</span>
    <?php endif; ?>
</td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group" aria-label="Acciones del Paciente">
                                            <a href="odontogram.php?patient_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info" title="Odontograma"><i class="fas fa-tooth"></i></a>
                                            <a href="history.php?patient_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary" title="Historial"><i class="fas fa-history"></i></a>
                                            <a href="../messages/chat.php?patient_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-success" title="Mensaje"><i class="fas fa-envelope"></i></a>
                                            <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pencil-alt"></i></a>
                                            <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="return confirm('¿Está seguro de que desea eliminar a este paciente? Esta acción no se puede deshacer.');"><i class="fas fa-trash-alt"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-users-slash"></i>
                                            <p>No se encontraron pacientes.</p>
                                            <?php if (empty($search)): ?>
                                            <a href="create.php" class="btn btn-success mt-2"><i class="fas fa-plus me-2"></i>Crear el primer paciente</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <nav aria-label="Navegación de páginas" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left"></i></a>
                        </li>
                        <?php
                        $window = 1;
                        for ($i = 1; $i <= $total_pages; $i++):
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $window && $i <= $page + $window)):
                        ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php
                            elseif ($i == 2 && $page - $window > 2 || $i == $total_pages - 1 && $page + $window < $total_pages - 1):
                        ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php
                            endif;
                        endfor;
                        ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Habilitar los tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Script para cerrar las alertas automáticamente
    window.setTimeout(function() {
        var alerts = document.querySelectorAll(".alert-dismissible");
        alerts.forEach(function(alert) {
            if (alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        });
    }, 5000);
    </script>
</body>
</html>