<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';

$path_to_root = ''; 

// Contenido de la guía (HTML más detallado)
$guide_content_html = <<<HTML
<div class="accordion" id="userGuideAccordion">

    <div class="accordion-item guide-section" data-searchable-section>
        <h2 class="accordion-header" id="headingOne" data-searchable-heading>
            <button class="accordion-button fs-4 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                <i class="fas fa-sign-in-alt me-3 text-primary"></i>Capítulo 1: Acceso al Sistema
            </button>
        </h2>
        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#userGuideAccordion">
            <div class="accordion-body">
                <p data-searchable-content>DentexaPro ofrece dos puntos de acceso principales, diseñados para la seguridad y facilidad de uso de cada tipo de usuario.</p>
                
                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-user-shield me-2 text-info"></i>1.1. Acceso al Panel de Administración (Profesionales)</h3>
                <p data-searchable-content>El Panel de Administración es el centro de control para todo el personal autorizado de la clínica.</p>
                <strong data-searchable-content>Pasos para Ingresar:</strong>
                <ol data-searchable-content>
                    <li>Diríjase a la Página Principal de la clínica (ej. sudominio.com/dentistaahora/).</li>
                    <li>Haga clic en el botón <strong>"Acceso Profesional"</strong>.</li>
                    <li>Ingrese su <strong>Email</strong> y <strong>Contraseña</strong> de administrador o empleado.</li>
                    <li>Haga clic en <strong>"Ingresar"</strong>.</li>
                    <li data-searchable-content>Si los datos son correctos, será redirigido al Dashboard del panel.</li>
                </ol>

                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-user-circle me-2 text-success"></i>1.2. Acceso al Portal del Paciente</h3>
                <p data-searchable-content>El Portal del Paciente ofrece a sus pacientes una forma conveniente de interactuar con la clínica.</p>
                <strong data-searchable-content>Pasos para Ingresar (Paciente):</strong>
                <ol data-searchable-content>
                    <li>Diríjase a la Página Principal de la clínica.</li>
                    <li>Seleccione <strong>"Portal del Paciente"</strong>.</li>
                    <li>Ingrese el <strong>Email de Acceso al Portal</strong> y la <strong>Contraseña</strong> que le fueron proporcionados.</li>
                    <li>Haga clic en <strong>"Ingresar"</strong>.</li>
                    <li data-searchable-content>Si los datos son correctos y su acceso está habilitado, accederá a su página principal del portal.</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="accordion-item guide-section" data-searchable-section>
        <h2 class="accordion-header" id="headingTwo" data-searchable-heading>
            <button class="accordion-button fs-4 fw-semibold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                <i class="fas fa-cogs me-3 text-primary"></i>Capítulo 2: Panel de Administración
            </button>
        </h2>
        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#userGuideAccordion">
            <div class="accordion-body">
                <p data-searchable-content>Al iniciar sesión, la interfaz se divide en una barra lateral de navegación (izquierda) y el área de contenido principal (derecha).</p>
                
                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-tachometer-alt me-2 text-info"></i>2.1. Dashboard (Panel de Inicio)</h3>
                <p data-searchable-content>Su centro de operaciones visual. Aquí encontrará:</p>
                <ul data-searchable-content>
                    <li><strong>Tarjetas de Estadísticas Clave:</strong> Turnos de Hoy (Pendientes/Completados), Total de Pacientes, Pacientes Nuevos (Últimos 7 días).</li>
                    <li><strong>Gráficos Dinámicos:</strong> Tendencia de turnos diarios y captación de nuevos pacientes por mes.</li>
                    <li><strong>Turnos Programados para Hoy:</strong> Listado rápido con detalles y acciones.</li>
                    <li><strong>Atajos Rápidos:</strong> Acceso directo a funciones comunes.</li>
                </ul>

                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-id-card-alt me-2 text-info"></i>2.2. Mi Perfil (`profile.php`)</h3>
                <p data-searchable-content>Gestione su información personal y, si es Superadmin, las cuentas de otros usuarios.</p>
                <ul data-searchable-content>
                    <li><strong>Actualizar su Foto de Perfil:</strong> Haga clic en su foto en la barra lateral o en el modal. Seleccione su nueva imagen (JPG, PNG, GIF; máx. 2MB) y confirme.</li>
                    <li><strong>Cambiar su Contraseña:</strong> Ingrese su contraseña actual, luego la nueva y su confirmación (mín. 8 caracteres).</li>
                    <li><strong>Gestión de Otros Usuarios (Solo Superadmin):</strong>
                        <ul>
                            <li><strong>Crear Nuevo Usuario/Empleado:</strong> Complete nombre, email, contraseña, rol (Admin/Empleado) y asigne permisos a secciones.</li>
                            <li><strong>Ver y Editar Usuarios Existentes:</strong> Modifique datos o permisos.</li>
                            <li><strong>Eliminar Usuarios:</strong> Con protecciones para el superadmin principal y la propia cuenta.</li>
                        </ul>
                    </li>
                </ul>
                
                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-bell me-2 text-info"></i>2.3. Notificaciones (`notifications.php`)</h3>
                <p data-searchable-content>Un contador en la barra lateral le alertará sobre novedades.</p>
                <ul data-searchable-content>
                    <li><strong>Turnos Mañana:</strong> Lista y acciones (recordar por WhatsApp, editar, cancelar).</li>
                    <li><strong>Cumpleaños:</strong> Pacientes que cumplen años. Acción para saludar por WhatsApp.</li>
                    <li><strong>Pac. Inactivos:</strong> Pacientes sin actividad reciente. Enlace para revisar ficha.</li>
                </ul>

                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-user-injured me-2 text-info"></i>2.4. Gestión de Pacientes (`patients/`)</h3>
                <p data-searchable-content>Administración completa de la información de sus pacientes.</p>
                <ul data-searchable-content>
                    <li><strong>Listado de Pacientes:</strong> Búsqueda por DNI, Nombre, Apellido. Acciones: Ver Historial, Odontograma, Editar Ficha, Eliminar.</li>
                    <li><strong>Crear/Editar Ficha:</strong> Datos personales, contacto, información médica, gestión de acceso al portal.</li>
                    <li><strong>Historial Clínico:</strong> Registre evoluciones, tratamientos, adjunte archivos. Edite y exporte a CSV.</li>
                    <li><strong>Odontograma Interactivo:</strong> Registro por diente y superficie, paleta de condiciones, historial de odontogramas, guardar/actualizar, eliminar registro.</li>
                </ul>

                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-calendar-check me-2 text-info"></i>2.5. Gestión de Turnos (`appointments/`)</h3>
                <p data-searchable-content>Organice la agenda de la clínica.</p>
                <ul data-searchable-content>
                    <li><strong>Calendario Interactivo:</strong> Vistas mensual, semanal, diaria. Acceso rápido para editar turnos.</li>
                    <li><strong>Listado de Turnos:</strong> Tabla paginada con acciones (Editar, Eliminar, Recordatorio WhatsApp).</li>
                    <li><strong>Crear/Editar Turnos:</strong> Búsqueda de pacientes integrada.</li>
                </ul>

                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-comments me-2 text-info"></i>2.6. Mensajería Clínica (`messages/`)</h3>
                <p data-searchable-content>Comunicación directa con sus pacientes.</p>
                <ul data-searchable-content>
                    <li><strong>Bandeja de Entrada:</strong> Lista de conversaciones, resaltando no leídos. Inicie nuevas conversaciones.</li>
                    <li><strong>Ventana de Chat:</strong> Visualice y responda. Edite o elimine sus mensajes. Mensajes nuevos de pacientes aparecen "en tiempo real".</li>
                </ul>
                
                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-chart-bar me-2 text-info"></i>2.7. Reportes (`reports/`)</h3>
                <p data-searchable-content>Información consolidada.</p>
                <ul data-searchable-content>
                    <li><strong>Reporte de Turnos por Período:</strong> Filtre por fechas y exporte a CSV.</li>
                </ul>

                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-boxes-stacked me-2 text-info"></i>2.8. Gestión de Insumos (`inventory/`)</h3>
                <p data-searchable-content>Control de stock.</p>
                <ul data-searchable-content>
                    <li><strong>Listado de Insumos:</strong> Filtros, indicadores visuales de stock.</li>
                    <li><strong>Ajuste de Stock:</strong> Registre ingresos/egresos con motivo.</li>
                    <li><strong>Historial de Movimientos:</strong> Trazabilidad completa.</li>
                </ul>

                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-users-cog me-2 text-info"></i>2.9. Gestión de Empleados (`employees/` - Solo Superadmin)</h3>
                <p data-searchable-content>Administre los usuarios del panel.</p>
                <ul data-searchable-content>
                    <li><strong>Listado, Creación y Edición de Usuarios:</strong> Defina nombre, email, rol.</li>
                    <li><strong>Asignación de Permisos:</strong> Controle el acceso a secciones del CMS.</li>
                </ul>

                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-database me-2 text-info"></i>2.10. Sistema (`system/` - Principalmente Superadmin)</h3>
                <p data-searchable-content>Herramientas de mantenimiento.</p>
                <ul data-searchable-content>
                    <li><strong>Backup y Restauración:</strong> Copias de BD (.sql) y del sitio completo (.zip).</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="accordion-item guide-section" data-searchable-section>
        <h2 class="accordion-header" id="headingThree" data-searchable-heading>
            <button class="accordion-button fs-4 fw-semibold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                <i class="fas fa-laptop-medical me-3 text-primary"></i>Capítulo 3: Portal del Paciente
            </button>
        </h2>
        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#userGuideAccordion">
            <div class="accordion-body">
                <p data-searchable-content>Un espacio dedicado para que sus pacientes accedan a su información.</p>
                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-desktop me-2 text-success"></i>3.1. Inicio del Portal</h3>
                <ul data-searchable-content>
                    <li>Bienvenida, aviso de mensajes nuevos, próximos turnos.</li>
                </ul>
                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-calendar-alt me-2 text-success"></i>3.2. Mis Turnos</h3>
                <ul data-searchable-content>
                    <li>Lista de turnos próximos e historial de pasados.</li>
                </ul>
                <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-envelope-open-text me-2 text-success"></i>3.3. Mis Mensajes</h3>
                <ul data-searchable-content>
                    <li>Chat con la clínica, envío y recepción de mensajes con actualización "en tiempo real".</li>
                </ul>
                 <h3 class="guide-subtitle" data-searchable-heading><i class="fas fa-user-cog me-2 text-success"></i>3.4. Mi Perfil (Paciente)</h3>
                <ul data-searchable-content>
                    <li>Ver información personal, actualizar datos de contacto y contraseña del portal.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<div class="mt-4 text-center">
    <p class="text-muted small">Fin de la guía. Para cualquier consulta adicional, contacte al soporte.</p>
</div>
HTML;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Guía del Usuario - DentexaPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.075); }
        .guide-search-header { background-color: #e9ecef; padding: 1.5rem; border-radius: .5rem .5rem 0 0; margin-bottom: 0; }
        #guideSearchInput { border-radius: 50px; padding-left: 2.5rem; font-size: 1.1rem; }
        .input-group-text.search-icon { position: absolute; left: 0; top: 0; bottom: 0; z-index: 10; display: flex; align-items: center; padding: 0 1rem; background: transparent; border: none; color: #6c757d; }
        .guide-content .accordion-button { background-color: #f8f9fa; color: #212529; border-bottom: 1px solid #dee2e6 !important; }
        .guide-content .accordion-button:not(.collapsed) { color: #0c63e4; background-color: #e7f1ff; box-shadow: inset 0 -1px 0 rgba(0,0,0,.125); }
        .guide-content .accordion-button:focus { box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25); }
        .guide-content .accordion-button i.fa-chevron-down { transition: transform 0.2s ease-in-out; }
        .guide-content .accordion-button:not(.collapsed) i.fa-chevron-down { transform: rotate(-180deg); }
        .guide-content .accordion-body { padding: 1.5rem 2rem; background-color: #fff; }
        .guide-subtitle { font-size: 1.25rem; font-weight: 600; color: #495057; margin-top: 1.5rem; margin-bottom: 0.75rem; padding-bottom: 0.25rem; border-bottom: 1px solid #eee; }
        .guide-subtitle i { color: var(--bs-secondary); }
        .guide-content p, .guide-content li { line-height: 1.7; color: #343a40; font-size:0.95rem; }
        .guide-content strong { color: #000; }
        .guide-content ol, .guide-content ul { padding-left: 1.5rem; margin-bottom: 1rem; }
        mark.highlight { background-color: #fff3cd; font-weight: bold; padding: 0.1em 0.2em; border-radius: .2em;}
        mark.highlight-active { background-color: #ffc107; color: #000; padding: 0.1em 0.3em; border-radius: .3em; box-shadow: 0 0 5px #ffc107;}
        .search-nav-controls { margin-top: 0.5rem; }
        .search-nav-controls .btn { padding: 0.25rem 0.6rem; font-size: 0.8rem; }
        .search-nav-controls .search-result-counter { font-size: 0.85rem; color: #6c757d; margin: 0 0.5rem; } /* Clase para el contador */
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .chevron{transition:transform .2s;}
        .nav-link[aria-expanded="true"] .chevron{transform:rotate(180deg);}
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
if (isMobileDevice()) { // Asume que isMobileDevice() está en config.php
    require_once ($path_to_root ?: './') . '_sidebarmovil.php';

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
                <div class="guide-search-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="mb-0 d-flex align-items-center text-primary" style="font-size: 1.75rem; font-weight: 600;">
                            <i class="fas fa-book-open-reader me-3"></i>Guía del Usuario de DentexaPro
                        </h1>
                       
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Dashboard
                        </a>
                    </div>
                    <p class="text-muted mt-2">Encuentre rápidamente la información que necesita para utilizar el sistema.</p>
                    <div class="mt-3 mb-2">
                        <div class="input-group input-group-lg position-relative">
                            <span class="input-group-text search-icon"><i class="fas fa-search"></i></span>
                            <input type="text" id="guideSearchInput" class="form-control" placeholder="Buscar en la guía (ej: 'crear paciente', 'odontograma')...">
                        </div>
                    
                        <div class="d-flex justify-content-center align-items-center search-nav-controls mt-2 search-navigation-controls" style="display: none;">
                            <button class="btn btn-outline-secondary btn-sm prev-match-btn" title="Resultado Anterior"><i class="fas fa-chevron-left"></i></button>
                            <span class="search-result-counter mx-2"></span>
                            <button class="btn btn-outline-secondary btn-sm next-match-btn" title="Resultado Siguiente"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-lg-4 p-3">
                    <div id="guideContentContainer" class="guide-content">
                        <?php echo $guide_content_html; ?>
                    </div>
                     <div id="noResultsMessage" class="alert alert-warning mt-3" style="display: none;">
                        No se encontraron resultados para su búsqueda.
                    </div>
                  
                    <div class="d-flex justify-content-center align-items-center search-nav-controls mt-4 pt-3 border-top search-navigation-controls" style="display: none;">
                        <button class="btn btn-outline-secondary btn-sm prev-match-btn" title="Resultado Anterior"><i class="fas fa-chevron-left"></i></button>
                        <span class="search-result-counter mx-2"></span>
                        <button class="btn btn-outline-secondary btn-sm next-match-btn" title="Resultado Siguiente"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('guideSearchInput');
        const contentContainer = document.getElementById('guideContentContainer');
        const allAccordionItems = contentContainer.querySelectorAll('.accordion-item');
        
        // Seleccionar todos los contenedores de navegación y sus elementos
        const allSearchNavigations = document.querySelectorAll('.search-navigation-controls');
        const allPrevMatchBtns = document.querySelectorAll('.prev-match-btn');
        const allNextMatchBtns = document.querySelectorAll('.next-match-btn');
        const allSearchResultCounters = document.querySelectorAll('.search-result-counter');
        
        const noResultsMessage = document.getElementById('noResultsMessage');

        let originalContent = new Map();
        let foundMatches = []; 
        let currentMatchIndex = -1;

        allAccordionItems.forEach(item => {
            item.querySelectorAll('[data-searchable-content], [data-searchable-heading] > button').forEach(el => {
                originalContent.set(el, el.innerHTML);
            });
        });

        function highlightMatch(element, searchTerm) {
            const originalText = originalContent.get(element) || element.innerHTML;
            const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            element.innerHTML = originalText.replace(regex, '<mark class="highlight">$1</mark>');
            return Array.from(element.getElementsByClassName('highlight'));
        }
        
        function clearAllHighlights() {
            originalContent.forEach((html, el) => {
                el.innerHTML = html; 
            });
        }

        function updateActiveHighlight(newIndex) {
            if (currentMatchIndex !== -1 && foundMatches[currentMatchIndex]) {
                foundMatches[currentMatchIndex].classList.remove('highlight-active');
            }
            currentMatchIndex = newIndex;
            if (currentMatchIndex !== -1 && foundMatches[currentMatchIndex]) {
                foundMatches[currentMatchIndex].classList.add('highlight-active');
                foundMatches[currentMatchIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        function navigateToMatch(index) {
            if (index < 0 || index >= foundMatches.length) return;
            
            updateActiveHighlight(index); 

            let parentAccordionItem = foundMatches[index].closest('.accordion-item');
            if (parentAccordionItem) {
                let collapseElement = parentAccordionItem.querySelector('.accordion-collapse');
                let bsCollapse = bootstrap.Collapse.getInstance(collapseElement);
                if (!bsCollapse) { 
                    bsCollapse = new bootstrap.Collapse(collapseElement, { toggle: false });
                }
                if (!collapseElement.classList.contains('show')) { 
                    bsCollapse.show();
                }
            }
            updateSearchCounterUI(); 
        }

        function updateSearchCounterUI() {
            allSearchNavigations.forEach(nav => {
                nav.style.display = foundMatches.length > 0 ? 'flex' : 'none';
            });
            allSearchResultCounters.forEach(counter => {
                counter.textContent = foundMatches.length > 0 ? `Resultado ${currentMatchIndex + 1} de ${foundMatches.length}` : '';
            });
            allPrevMatchBtns.forEach(btn => {
                btn.disabled = currentMatchIndex <= 0;
            });
            allNextMatchBtns.forEach(btn => {
                btn.disabled = currentMatchIndex >= foundMatches.length - 1;
            });
        }

        function performSearch() {
            clearAllHighlights();
            foundMatches = []; 
            currentMatchIndex = -1;
            
            const searchTerm = searchInput.value.trim().toLowerCase();

            if (noResultsMessage) noResultsMessage.style.display = 'none';

            if (searchTerm === "") {
                allAccordionItems.forEach(section => section.style.display = 'block');
                updateSearchCounterUI(); 
                return;
            }

            let foundOverall = false;
            allAccordionItems.forEach(accordionItem => {
                let sectionHasMatch = false;
                const headerButton = accordionItem.querySelector('.accordion-header > button');
                
                if (headerButton && originalContent.has(headerButton) && originalContent.get(headerButton).toLowerCase().includes(searchTerm)) {
                    sectionHasMatch = true;
                    foundMatches.push(...highlightMatch(headerButton, searchTerm));
                }

                accordionItem.querySelectorAll('[data-searchable-content]').forEach(el => {
                    if (originalContent.has(el) && originalContent.get(el).toLowerCase().includes(searchTerm)) {
                        sectionHasMatch = true;
                        foundMatches.push(...highlightMatch(el, searchTerm));
                    }
                });

                const collapseElement = accordionItem.querySelector('.accordion-collapse');
                const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement, { toggle: false });

                if (sectionHasMatch) {
                    accordionItem.style.display = 'block';
                    if (!collapseElement.classList.contains('show')) {
                       bsCollapse.show();
                    }
                    foundOverall = true;
                } else {
                    accordionItem.style.display = 'none';
                    bsCollapse.hide();
                }
            });
            
            if (foundMatches.length > 0) {
                currentMatchIndex = 0; 
                updateActiveHighlight(currentMatchIndex); 
                foundMatches[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); 
            } else {
                if (noResultsMessage) noResultsMessage.style.display = 'block';
            }
            updateSearchCounterUI(); 
        }
        
        if (searchInput) {
            searchInput.addEventListener('input', performSearch);
        }

        allPrevMatchBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                if (currentMatchIndex > 0) {
                    currentMatchIndex--;
                    navigateToMatch(currentMatchIndex);
                }
            });
        });

        allNextMatchBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                if (currentMatchIndex < foundMatches.length - 1) {
                    currentMatchIndex++;
                    navigateToMatch(currentMatchIndex);
                }
            });
        });

        if (allAccordionItems.length > 0 && (!searchInput || searchInput.value.trim() === "")) {
            const firstCollapseElement = allAccordionItems[0].querySelector('.accordion-collapse');
            if (firstCollapseElement && !firstCollapseElement.classList.contains('show')) {
                new bootstrap.Collapse(firstCollapseElement, { toggle: false }).show();
            }
        }
    });
    </script>
</body>
</html>
