<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; 

$path_to_root = '../'; 
$current_page_script_path = $_SERVER['PHP_SELF']; 

if (!user_has_permission('appointments_list', $_SESSION['admin_permissions'] ?? '[]', $_SESSION['admin_role'] ?? 'employee')) {
    header('Location: ' . $path_to_root . 'dashboard.php?status=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
    exit;
}

$success_message = '';
$error_message = '';
$info_message = '';

$status_message = '';
$status_type = 'success';
if(isset($_GET['status'])){
    $status_code = $_GET['status'];
    if($status_code == 'success_delete'){
        $status_message = 'Turno eliminado con éxito.';
    } else if($status_code == 'success_create'){
        $status_message = 'Turno creado con éxito.';
    } else if($status_code == 'success_update'){
        $status_message = 'Turno actualizado con éxito.';
    } else if (isset($_GET['msg'])) {
        $status_message = urldecode($_GET['msg']);
        if ($status_code == 'error') {
            $status_type = 'danger';
        }
    }
}

$items_per_page = 10;
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

$total_items_query = $mysqli->query("SELECT COUNT(*) as total FROM appointments");
$total_items = 0;
if ($total_items_query) {
    $total_items_row = $total_items_query->fetch_assoc();
    $total_items = $total_items_row['total'];
    $total_items_query->close();
}
$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $items_per_page;

$stmt_app_list = $mysqli->prepare("SELECT a.id, a.datetime, a.reason, a.status,
                                          p.fname AS patient_fname, p.lname AS patient_lname, p.phone AS patient_phone
                                   FROM appointments a
                                   JOIN patients p ON p.id = a.patient_id
                                   ORDER BY a.datetime DESC
                                   LIMIT ? OFFSET ?");
$appointments_result = null;
if ($stmt_app_list) {
    $stmt_app_list->bind_param("ii", $items_per_page, $offset);
    $stmt_app_list->execute();
    $appointments_result = $stmt_app_list->get_result();
} else {
    error_log("Error al preparar la consulta de listado de turnos: " . $mysqli->error);
    $status_message = "Error al cargar la lista de turnos.";
    $status_type = "danger";
}

if (!function_exists('format_phone_for_whatsapp')) {
    function format_phone_for_whatsapp($phone) {
        if (empty($phone)) {
            return null;
        }
        $cleaned_phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($cleaned_phone, 0, 2) === "54") {
            return $cleaned_phone;
        }
        return "54" . $cleaned_phone;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Turnos - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body{overflow-x:hidden; background-color: #f8f9fa;}
        #sidebar{min-height:100vh;}
        /* .chevron y .nav-link[aria-expanded="true"] .chevron no son necesarios directamente */
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        .table th i { margin-right: 5px; }
        /* .btn-toggle-nav .nav-link .fa-angle-right no es necesario directamente */
        .actions-cell .btn {
            margin-right: 5px;
            margin-bottom: 5px; /* Ya ayuda a que se apilen si la celda es estrecha */
        }
        /* Para pantallas muy pequeñas, podríamos hacer que los botones de acción ocupen más ancho si es necesario */
        @media (max-width: 576px) {
            .actions-cell {
                min-width: 120px; /* Asegurar un ancho mínimo para que los botones no se vean demasiado pequeños */
            }
            /* Opcional: hacer que los botones sean más grandes o se apilen explícitamente */
            /*
            .actions-cell .btn {
                display: block;
                width: 100%;
                margin-right: 0;
            }
            .actions-cell .btn + .btn {
                margin-top: 5px;
            }
            */
        }
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
        if (isMobileDevice()) { // Asume que isMobileDevice() está en config.php
            require_once ($path_to_root ?: './') . '_sidebarmovil.php';
            echo '<nav class="navbar navbar-dark d-md-none sticky-top mb-3" style="background-color: #0d6efd;">
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
            <div class="card main-content-card shadow-sm rounded-3">
                <div class="card-body p-lg-4 p-3"> 
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-3 gap-2"> 
                        <h2 class="mb-0 d-flex align-items-center"><i class="fas fa-list me-3 text-info"></i>Todos los Turnos</h2>
                        <a href="create.php" class="btn btn-primary btn-lg"><i class="fas fa-plus-circle me-2"></i> Nuevo Turno</a>
                    </div>

                     <?php if ($status_message): ?>
                        <div class="alert alert-<?php echo $status_type; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $status_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($status_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-calendar-alt"></i>Fecha y Hora</th>
                                    <th><i class="fas fa-user"></i>Paciente</th>
                                    <th><i class="fas fa-clipboard"></i>Motivo</th>
                                    <th><i class="fas fa-info-circle"></i>Estado</th>
                                    <th style="min-width: 180px;"><i class="fas fa-toolbox"></i>Acciones</th>  
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($appointments_result && $appointments_result->num_rows > 0): ?>
                                <?php while($row=$appointments_result->fetch_assoc()):
                                    $formatted_datetime = new DateTime($row['datetime']);
                                    $fecha_turno = $formatted_datetime->format('d/m/Y');
                                    $hora_turno = $formatted_datetime->format('H:i');

                                    $whatsapp_phone = format_phone_for_whatsapp($row['patient_phone']);
                                    $nombre_paciente_completo = htmlspecialchars($row['patient_fname'] . ' ' . $row['patient_lname']);
                                    $motivo_turno = htmlspecialchars($row['reason'] ?: 'Consulta general');
                                    $nombre_clinica = "Clínica Dental Dra. Fernanda Turano";

                                    $mensaje_whatsapp = "Hola {$nombre_paciente_completo},\n\n";
                                    $mensaje_whatsapp .= "Te recordamos tu turno en {$nombre_clinica}:\n";
                                    $mensaje_whatsapp .= "Fecha: *{$fecha_turno}*\n";
                                    $mensaje_whatsapp .= "Hora: *{$hora_turno} hs*\n";
                                    $mensaje_whatsapp .= "Motivo: {$motivo_turno}\n\n";
                                    $mensaje_whatsapp .= "Por favor, avísanos con anticipación si necesitas reprogramar o cancelar.\n";
                                    $mensaje_whatsapp .= "¡Te esperamos!";

                                    $whatsapp_url = '';
                                    if ($whatsapp_phone) {
                                        $whatsapp_url = "https://wa.me/{$whatsapp_phone}?text=" . rawurlencode($mensaje_whatsapp);
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $fecha_turno . ' ' . $hora_turno; ?> hs</td>
                                        <td><?php echo $nombre_paciente_completo; ?></td>
                                        <td><?php echo htmlspecialchars($row['reason'] ?: '-'); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch ($row['status']) {
                                                case 'Programado': $status_class = 'badge bg-primary'; break;
                                                case 'Completado': $status_class = 'badge bg-success'; break;
                                                case 'Cancelado': $status_class = 'badge bg-danger'; break;
                                                default: $status_class = 'badge bg-secondary'; break;
                                            }
                                            echo '<span class="' . $status_class . '">' . htmlspecialchars($row['status']) . '</span>';
                                            ?>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning" title="Editar Turno"><i class="fas fa-edit"></i></a>
                                            <a href="delete.php?id=<?php echo $row['id']; ?>&redirect_page=list" class="btn btn-sm btn-danger" title="Eliminar Turno" onclick="return confirm('¿Está seguro de que desea eliminar este turno?');"><i class="fas fa-trash"></i></a>
                                            <?php if ($whatsapp_url): ?>
                                                <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="btn btn-sm btn-success" title="Enviar Recordatorio WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-success" title="Enviar Recordatorio WhatsApp (Teléfono no disponible)" disabled>
                                                    <i class="fab fa-whatsapp"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">No hay turnos registrados<?php if($total_items > 0) echo " para esta página";?>.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Navegación de páginas de turnos" class="mt-4">
                        <ul class="pagination justify-content-center flex-wrap">
                            <li class="page-item <?php if ($current_page <= 1) echo 'disabled'; ?>">
                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <li class="page-item active" aria-current="page"><span class="page-link"><?php echo $i; ?></span></li>
                                <?php else: ?>
                                    <li class="page-item"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <li class="page-item <?php if ($current_page >= $total_pages) echo 'disabled'; ?>">
                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Siguiente">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>

                    <?php
                    if ($appointments_result) $appointments_result->close();
                    if (isset($stmt_app_list) && $stmt_app_list) $stmt_app_list->close();
                    ?>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var autoDismissAlerts = document.querySelectorAll('.alert.alert-dismissible');
            autoDismissAlerts.forEach(function(alertEl) {
                setTimeout(function() {
                    if (document.body.contains(alertEl)) {
                        var bsAlert = bootstrap.Alert.getInstance(alertEl);
                        if (bsAlert) { bsAlert.close(); }
                    }
                }, 7000);
            });
        });
    </script>
</body>
</html>