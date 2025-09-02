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

// Lógica para la barra lateral
$path_to_root = '../';
$admin_name_display = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrador');
$profile_pic_src = $path_to_root . 'assets/img/default-avatar.png';
if (!empty($_SESSION['admin_profile_image']) && defined('UPLOAD_DIR_NAME_CONST') && file_exists(UPLOAD_DIR . 'profiles/' . $_SESSION['admin_profile_image'])) {
    $profile_pic_src = $path_to_root . UPLOAD_DIR_NAME_CONST . '/profiles/' . $_SESSION['admin_profile_image'];
}

$status_message = '';
$status_type = '';
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success_create': $status_message = '¡Turno programado con éxito!'; $status_type = 'success'; break;
        case 'success_update': $status_message = '¡Turno actualizado con éxito!'; $status_type = 'success'; break;
        case 'success_delete': $status_message = '¡Turno eliminado con éxito!'; $status_type = 'success'; break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agenda de Turnos - Clínica Dental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; }
        /* .chevron y .nav-link[aria-expanded="true"] .chevron no son necesarios aquí directamente */
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        #calendar {
            max-width: 100%;
            /* min-height: 650px; <- Removido para mejor responsividad, FullCalendar ajustará su altura */
            margin: 0 auto;
        }
        .calendar-card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10);
        }
        .fc .fc-toolbar.fc-header-toolbar {
            margin-bottom: 1.5rem;
            flex-wrap: wrap; /* Permitir que la barra de herramientas se ajuste en múltiples líneas */
            row-gap: 0.5rem; /* Espacio entre filas de la barra de herramientas si se envuelven */
        }
        .fc .fc-button-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .fc .fc-button-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: #0a58ca;
            border-color: #0a53be;
        }
        /* .btn-toggle-nav .nav-link .fa-angle-right no es necesario aquí directamente */

        /* Ajustes para pantallas pequeñas */
        @media (max-width: 767.98px) { /* Menor que md */
            .fc .fc-toolbar.fc-header-toolbar {
                flex-direction: column; /* Apilar secciones de la toolbar */
                align-items: stretch; /* Estirar secciones al ancho completo */
            }
            .fc .fc-toolbar-chunk { /* Cada parte de la toolbar (left, center, right) */
                display: flex;
                justify-content: center; /* Centrar botones dentro de cada chunk */
                margin-bottom: 0.5rem;
                flex-wrap: wrap; /* Permitir que los botones dentro de un chunk también se envuelvan */
                gap: 0.25rem;
            }
            .fc .fc-toolbar-title {
                font-size: 1.25rem; /* Título un poco más pequeño */
            }
            .fc .fc-button { /* Hacer botones un poco más pequeños */
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }
            .calendar-card .card-body {
                padding: 0.75rem; /* Menos padding en el card del calendario en móviles */
            }
            #calendar {
                 /* Podrías considerar un aspectRatio diferente para móviles si se vuelve muy alto */
                 /* aspectRatio: 1.5, // por ejemplo */
            }
        }
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
        if (isMobileDevice()) {
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
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-3 gap-2">
               <h2 class="mb-0 text-dark d-flex align-items-center">
    <i class="fas fa-calendar-alt me-3 text-info"></i>Agenda de Turnos
</h2>
                <a href="create.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus-circle me-2"></i> Nuevo Turno
                </a>
            </div>

            <?php if ($status_message): ?>
                <div class="alert alert-<?php echo $status_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $status_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($status_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card calendar-card rounded-3">
                <div class="card-body p-lg-4 p-2"> 
                    <div id="calendar"></div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="dayModal" tabindex="-1" aria-labelledby="dayModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="dayModalLabel">Turnos del Día</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body" id="dayModalBody">Cargando...</div></div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'es',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            navLinks: true,
            selectable: true,
            selectMirror: true,
            eventClick: function(info) {
                window.location.href = 'edit.php?id=' + info.event.id;
            },
            dateClick: function(info) {
                var dayModal = new bootstrap.Modal(document.getElementById('dayModal'));
                var modalBody = document.getElementById('dayModalBody');
                var dateForTitle = new Date(info.dateStr + 'T00:00:00'); // Asegurar que se interprete como local
                var formattedDate = dateForTitle.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                document.getElementById('dayModalLabel').textContent = 'Turnos del ' + formattedDate;
                modalBody.innerHTML = '<div class="d-flex justify-content-center align-items-center" style="min-height: 150px;"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
                fetch('day.php?date=' + info.dateStr)
                    .then(response => response.text()).then(data => { modalBody.innerHTML = data; })
                    .catch(error => { modalBody.innerHTML = '<p class="text-danger">Error al cargar los turnos.</p>'; console.error('Error:', error);});
                dayModal.show();
            },
            buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día', list: 'Lista' },
            themeSystem: 'bootstrap5',
            height: 'auto', // Clave para la responsividad de altura
            aspectRatio: 1.8, // Ajusta la proporción ancho/alto
            events: 'events.php',
            eventDidMount: function(info) {
                if (info.event.title) {
                    var startTime = info.event.start ? info.event.start.toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'}) : '';
                    var tooltipTitle = info.event.title + (startTime ? ' (' + startTime + ')' : '');
                    // Usar el title del elemento para el tooltip de Bootstrap
                    info.el.setAttribute('title', tooltipTitle);
                    new bootstrap.Tooltip(info.el, {
                        placement: 'top',
                        trigger: 'hover',
                        container: 'body'
                    });
                }
            },
            windowResize: function(arg) {
                // FullCalendar se redibuja automáticamente.
                // Aquí podrías añadir lógica para cambiar opciones si fuera necesario,
                // por ejemplo, cambiar initialView o headerToolbar en pantallas muy pequeñas.
                // console.log('Calendar resized', arg.view.type);
            }
        });
        calendar.render();

        var autoDismissAlerts = document.querySelectorAll('.alert.alert-dismissible');
        autoDismissAlerts.forEach(function(alertEl) {
            setTimeout(function() { if (document.body.contains(alertEl)) { var bsAlert = bootstrap.Alert.getInstance(alertEl); if (bsAlert) { bsAlert.close(); } } }, 7000);
        });
    });
    </script>
</body>
</html>