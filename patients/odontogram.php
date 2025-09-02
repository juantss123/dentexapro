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

$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
if (!$patient_id) {
    header('Location: ' . $path_to_root . 'patients/index.php?status=error&msg=' . urlencode('ID de paciente no proporcionado.'));
    exit;
}

$stmt_patient = $mysqli->prepare("SELECT fname, lname, dni, dientes_existentes, evaluacion_color, enfermedad_periodontal FROM patients WHERE id = ?");
$patient_name_display = "Paciente Desconocido";
$patient_dni_display = "";
$patient_dientes_existentes_val = '';
$patient_evaluacion_color_val = '';
$patient_enfermedad_periodontal_val = 'No Evaluado';

if ($stmt_patient) {
    $stmt_patient->bind_param("i", $patient_id);
    $stmt_patient->execute();
    $patient_result = $stmt_patient->get_result();
    if($patient_data_row = $patient_result->fetch_assoc()) {
        $patient_name_display = htmlspecialchars($patient_data_row['fname'] . ' ' . $patient_data_row['lname']);
        $patient_dni_display = htmlspecialchars($patient_data_row['dni']);
        $patient_dientes_existentes_val = $patient_data_row['dientes_existentes'] !== null ? htmlspecialchars($patient_data_row['dientes_existentes']) : '';
        $patient_evaluacion_color_val = $patient_data_row['evaluacion_color'] ?? ''; 
        $patient_enfermedad_periodontal_val = $patient_data_row['enfermedad_periodontal'] ?? 'No Evaluado';
    } else {
        header('Location: ' . $path_to_root . 'patients/index.php?status=error&msg=' . urlencode('Paciente no encontrado.'));
        exit;
    }
    $stmt_patient->close();
} else {
    error_log("Error al preparar datos del paciente en odontogram.php: " . $mysqli->error);
    die("Error crítico al cargar datos del paciente.");
}

$odontogram_action_message = '';
$odontogram_action_status_type = '';
if (isset($_GET['status_odontogram']) && isset($_GET['msg_odontogram'])) {
    $odontogram_action_status_type = (strpos($_GET['status_odontogram'], 'success') !== false) ? 'success' : 'danger';
    $odontogram_action_message = urldecode($_GET['msg_odontogram']);
}

$odontogram_conditions = [];
$result_conditions = $mysqli->query("SELECT condition_code, description, color, type FROM odontogram_conditions ORDER BY type, description ASC");
if ($result_conditions) {
    while($row_cond = $result_conditions->fetch_assoc()){ 
        $odontogram_conditions[] = $row_cond;
    }
    $result_conditions->free();
}

$data_existente_default = ['record_id' => null, 'notes' => '', 'teeth_data' => new stdClass(), 'record_date_sql' => date('Y-m-d'), 'record_date_display' => 'Nuevo (' . date('d/m/Y') . ')'];
$data_a_realizar_default = ['record_id' => null, 'notes' => '', 'teeth_data' => new stdClass(), 'record_date_sql' => date('Y-m-d'), 'record_date_display' => 'Nuevo (' . date('d/m/Y') . ')'];
$data_realizadas_default = ['record_id' => null, 'notes' => '', 'teeth_data' => new stdClass(), 'record_date_sql' => date('Y-m-d'), 'record_date_display' => 'Nuevo (' . date('d/m/Y') . ')'];


function loadOdontogramDataForTab($mysqli_conn, $patient_id_param, $record_type_param, $default_data) {
    $tab_data = $default_data; 
    $stmt_record = $mysqli_conn->prepare("SELECT id, notes, record_date FROM odontogram_records WHERE patient_id = ? AND record_type = ? ORDER BY record_date DESC, id DESC LIMIT 1");
    if ($stmt_record) {
        $stmt_record->bind_param("is", $patient_id_param, $record_type_param);
        $stmt_record->execute();
        $result_record = $stmt_record->get_result();
        if ($record_row = $result_record->fetch_assoc()) {
            $tab_data['record_id'] = $record_row['id'];
            $tab_data['notes'] = $record_row['notes'] ?? '';
            $tab_data['record_date_sql'] = $record_row['record_date'];
            try { 
                $dt = new DateTime($record_row['record_date']); 
                $tab_data['record_date_display'] = $dt->format('d/m/Y'); 
            } catch (Exception $e) {}

            $teeth_details_temp = [];
            $stmt_details = $mysqli_conn->prepare("SELECT tooth_number, surface_o_condition_code, surface_v_condition_code, surface_l_condition_code, surface_p_condition_code, surface_m_condition_code, surface_d_condition_code, surface_c_condition_code, whole_tooth_condition_code, observations FROM odontogram_tooth_details WHERE odontogram_record_id = ?");
            if($stmt_details){
                $stmt_details->bind_param("i", $tab_data['record_id']);
                $stmt_details->execute();
                $result_details_q = $stmt_details->get_result();
                while($detail_row = $result_details_q->fetch_assoc()){
                    $current_tooth_data = [];
                    
                    if ($detail_row['surface_o_condition_code'] !== null) {
                        $current_tooth_data['O'] = $detail_row['surface_o_condition_code'];
                    }

                    if ($detail_row['surface_v_condition_code'] !== null) $current_tooth_data['V'] = $detail_row['surface_v_condition_code'];
                    if ($detail_row['surface_l_condition_code'] !== null) $current_tooth_data['L'] = $detail_row['surface_l_condition_code'];
                    if ($detail_row['surface_p_condition_code'] !== null) $current_tooth_data['P'] = $detail_row['surface_p_condition_code'];
                    if ($detail_row['surface_m_condition_code'] !== null) $current_tooth_data['M'] = $detail_row['surface_m_condition_code'];
                    if ($detail_row['surface_d_condition_code'] !== null) $current_tooth_data['D'] = $detail_row['surface_d_condition_code'];
                    if ($detail_row['surface_c_condition_code'] !== null) $current_tooth_data['C'] = $detail_row['surface_c_condition_code'];
                    if ($detail_row['whole_tooth_condition_code'] !== null) $current_tooth_data['whole'] = $detail_row['whole_tooth_condition_code'];
                    if ($detail_row['observations'] !== null) $current_tooth_data['obs'] = $detail_row['observations'];
                    
                    if (!empty($current_tooth_data)) {
                       $teeth_details_temp[$detail_row['tooth_number']] = $current_tooth_data;
                    }
                }
                if(!empty($teeth_details_temp)){ 
                    $tab_data['teeth_data'] = (object)$teeth_details_temp; 
                } else {
                    $tab_data['teeth_data'] = new stdClass(); 
                }
                $stmt_details->close();
            } else { 
                 error_log("Error preparando stmt_details para $record_type_param (paciente $patient_id_param): " . $mysqli_conn->error);
            }
        }
        $stmt_record->close();
    } else {
        error_log("Error preparando stmt_record para $record_type_param (paciente $patient_id_param): " . $mysqli_conn->error);
    }
    return $tab_data;
}

$data_existente = loadOdontogramDataForTab($mysqli, $patient_id, 'existente', $data_existente_default);
$data_a_realizar = loadOdontogramDataForTab($mysqli, $patient_id, 'a_realizar', $data_a_realizar_default);
$data_realizadas = loadOdontogramDataForTab($mysqli, $patient_id, 'realizadas', $data_realizadas_default);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Odontograma: <?php echo $patient_name_display; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
     body { overflow-x: hidden; background-color: #f8f9fa; }
        #sidebar { min-height: 100vh; }
        .main-content-card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.10); }
        #odontogram-svg-container { text-align: center; margin-bottom: 1rem; padding: 10px; background-color: #fff; border-radius: .375rem; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); overflow-x: auto; }
        #odontogram-chart { min-width: 650px; } 
        .tooth-group { cursor: default; }
        .tooth-surface { cursor: pointer; fill: #e0e0e0; stroke: #999; stroke-width: 0.5; transition: fill 0.15s ease; }
        .tooth-surface:hover { fill: #cce5ff; } 
        .tooth-surface.selected-surface { stroke: #007bff; stroke-width: 1.5; }
        .tooth-number-text { font-family: Arial, sans-serif; font-size: 9px; text-anchor: middle; pointer-events: none; fill: #333; }
        .midline { stroke: #aaa; stroke-width: 0.5; stroke-dasharray: 3, 2; }
        #odontogram-controls { padding: 1rem; background-color: #f9f9f9; border-radius: .375rem; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
        .condition-palette button { margin: 3px; font-size: 0.8rem; padding: 0.3rem 0.6rem; white-space: normal; text-align: left; line-height: 1.2; }
        #selected-info-panel { font-size: 0.9rem; }
        #selected-info-panel strong { color: #0d6efd; }
        .nav-tabs .nav-link { color: #495057; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-color: #dee2e6 #dee2e6 #fff; font-weight: bold; background-color: #fff; }
        .sidebar-profile-pic { width: 80px; height: 80px; object-fit: cover; border: 3px solid #495057; }
        .chevron{transition:transform .2s;}
        .nav-link[aria-expanded="true"] .chevron{transform:rotate(180deg);}
        .bucal-status-section { background-color: #e9ecef; padding: 0.75rem; border-radius: .25rem; }
    </style>
</head>
<body>
    <div class="row g-0">
        <?php
        if (false) { 
            require_once ($path_to_root) . '_sidebarmovil.php';
            echo '<nav class="navbar navbar-dark d-md-none sticky-top mb-3" style="background-color: #0d6efd;"><div class="container-fluid" style="position: relative; display: flex; align-items: center; justify-content: flex-start; min-height: 56px;"><button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile" aria-controls="sidebarMobile" style="z-index: 10;"><i class="fas fa-bars"></i><span class="d-none d-sm-inline ms-1">Menú</span></button><a class="navbar-brand" href="' . ($path_to_root) . 'dashboard.php" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); padding: 0; line-height: 1;"><img src="' . ($path_to_root) . 'assets/img/dentexapro-logo-iso-blanco.png" alt="DentexaPro" style="height: 40px; width: auto; max-height: 100%;"></a></div></nav>';
        } else {
            require_once ($path_to_root) . '_sidebar.php';
        }
        ?>
        
        <main class="col-12 <?php if (true) echo 'col-md-9 col-lg-10'; ?> p-4">
            <div class="card main-content-card shadow-sm rounded-3">
                <div class="card-body p-4">
                  
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
                        <h2 class="mb-0 d-flex align-items-center text-primary">
                             <i class="fas fa-tooth me-3"></i><span class="text-dark">Odontograma:</span>&nbsp;<?php echo $patient_name_display; ?>
                            <small class="ms-3 text-muted fs-6">(DNI: <?php echo $patient_dni_display; ?>)</small>
                        </h2>
                        <a href="<?php echo $path_to_root; ?>patients/history.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Historial
                        </a>
                    </div>
                    <div id="odontogram-message-area" class="mb-3">
                        <?php if ($odontogram_action_message): ?>
                            <div class="alert alert-<?php echo $odontogram_action_status_type; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php echo $odontogram_action_status_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                                <?php echo htmlspecialchars($odontogram_action_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div id="bucal-status-message-area" class="mb-3"></div>

                    <div class="d-flex justify-content-start mb-3 gap-2">
                        <button type="button" id="save-odontogram-record-btn-top" class="btn btn-success"><i class="fas fa-save me-1"></i> Guardar Solapa Actual</button>
                        <button type="button" id="save-all-tabs-btn" class="btn btn-primary"><i class="fas fa-server me-1"></i> Guardar Todas las Solapas</button>
                    </div>

                 
                    <ul class="nav nav-tabs" id="odontogramTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="existente-tab" data-bs-toggle="tab" data-bs-target="#odontogram-common-pane" type="button" role="tab" aria-controls="odontogram-common-pane" aria-selected="true" data-tab-type="existente">
                                <i class="fas fa-history me-1 text-danger"></i><span class="text-danger">Prestación Existente</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="a_realizar-tab" data-bs-toggle="tab" data-bs-target="#odontogram-common-pane" type="button" role="tab" aria-controls="odontogram-common-pane" aria-selected="false" data-tab-type="a_realizar">
                                <i class="fas fa-tasks me-1 text-primary"></i><span class="text-primary">Prestación a Realizar</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="realizadas-tab" data-bs-toggle="tab" data-bs-target="#odontogram-common-pane" type="button" role="tab" aria-controls="odontogram-common-pane" aria-selected="false" data-tab-type="realizadas">
                                <i class="fas fa-check-double me-1 text-success"></i><span class="text-success">Prestaciones Realizadas</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="odontogramTabsContent">
                        <div class="tab-pane fade show active" id="odontogram-common-pane" role="tabpanel" aria-labelledby="existente-tab a_realizar-tab">
                            <div class="row mt-3" id="odontogram-layout-common">
                                
                                <div class="col-lg-8 col-md-12 mb-3 mb-lg-0">
                                    <div id="odontogram-svg-container">
                                        <svg id="odontogram-chart" width="100%" viewBox="0 0 620 500" preserveAspectRatio="xMidYMid meet"></svg>
                                    </div>
                                    
                                    <div class="notes-and-actions-section mt-3 border-top pt-3">
                                        <div class="mb-2">
                                             <label for="odontogram-general-notes" class="form-label">Notas Generales de la Solapa (<span id="notes-for-tab-display" class="fw-bold">Existente</span>):</label>
                                             <textarea class="form-control form-control-sm" id="odontogram-general-notes" rows="3" placeholder="Notas generales para este registro..."></textarea>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" id="save-odontogram-record-btn" class="btn btn-success flex-grow-1"><i class="fas fa-save me-1"></i> Guardar Solapa Actual</button>
                                            <button type="button" id="save-all-tabs-btn-bottom" class="btn btn-primary flex-grow-1"><i class="fas fa-server me-1"></i> Guardar Todas las Solapas</button>
                                            <button type="button" id="clear-odontogram-tab-btn" class="btn btn-danger flex-grow-1"><i class="fas fa-undo me-1"></i>Limpiar Solapa Actual</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-12">
                                    <div id="odontogram-controls" class="sticky-lg-top" style="top: 20px;">
                                        <div id="selected-info-panel" class="mb-2">
                                            <p class="mb-1"><strong><i class="fas fa-user fa-fw me-2 text-secondary"></i>Paciente:</strong> <?php echo $patient_name_display; ?></p>
                                            <p class="mb-1"><strong><i class="fas fa-folder-open fa-fw me-2 text-secondary"></i>Solapa Activa:</strong> <span id="active-tab-display" class="fw-bold">Prestación Existente</span></p>
                                            <p class="mb-1"><strong><i class="fas fa-tooth fa-fw me-2 text-secondary"></i>Diente:</strong> <span id="selected-tooth-display" class="fw-bold">-</span></p>
                                            <p class="mb-1"><strong><i class="fas fa-draw-polygon fa-fw me-2 text-secondary"></i>Superficie:</strong> <span id="selected-surface-display" class="fw-bold">-</span></p>
                                            <p class="mb-0"><strong><i class="fas fa-notes-medical fa-fw me-2 text-secondary"></i>Condición/Trat.:</strong> <span id="selected-condition-display" class="fw-bold">-</span></p>
                                        </div><hr>
                                        
                                       <i class="fas fa-notes-medical fa-fw me-2 text-secondary"></i> <label class="form-label fw-bold">Aplicar Condición/Tratamiento:</label>
                                        <div class="condition-palette mb-3 border rounded p-2" style="max-height: 180px; overflow-y: auto;">
                                            <?php foreach($odontogram_conditions as $condition): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary condition-btn d-block w-100 mb-1 text-start" 
                                                        data-code="<?php echo htmlspecialchars($condition['condition_code']); ?>" 
                                                        data-color="<?php echo htmlspecialchars($condition['color']); ?>" 
                                                        title="<?php echo htmlspecialchars($condition['description']); ?>">
                                                    <?php echo htmlspecialchars($condition['description']); ?>
                                                    <small class="text-muted ms-1">(<?php echo htmlspecialchars($condition['condition_code']); ?>)</small>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                          <button type="button" id="apply-to-whole-tooth" class="btn btn-sm btn-outline-primary w-100 mb-2" disabled><i class="fas fa-layer-group me-2"></i>Aplicar Condición a Todo el Diente</button>

                                        <button type="button" class="btn btn-sm btn-outline-dark condition-btn w-100 mt-2" data-code="" title="Limpiar condición aplicada en la superficie seleccionada">
                                            <i class="fas fa-eraser me-1"></i> Limpiar Selección en Superficie
                                        </button>
                                        
                                        
                                        <div class="mb-3 mt-3">
                                            <label for="tooth-observations" class="form-label"><i class="fas fa-comment-alt me-2"></i>Observaciones para Diente <span class="text-primary fw-bold" id="obs-for-tooth-num"></span>:</label>
                                            <textarea class="form-control form-control-sm" id="tooth-observations" rows="2" disabled placeholder="Seleccione un diente/superficie"></textarea>
                                        </div>
                                      
                                        
                                        <div class="bucal-status-section mt-3 mb-3">
                                            <h6 class="mb-2 fw-semibold d-flex justify-content-between align-items-center">
                                               <span><i class="fas fa-clipboard-list me-2 text-secondary"></i>Estado Bucal General</span>
                                                <button type="button" class="btn btn-sm btn-success" id="edit-bucal-status-btn" title="Editar Estado Bucal">
                                                    <i class="fas fa-pencil-alt me-1"></i>Editar
                                                </button>
                                            </h6>
                                            <div class="mb-2">
                                                <label for="dientes_existentes_input" class="form-label small fw-bold">Cantidad de dientes existentes:</label>
                                                <input type="number" class="form-control form-control-sm" id="dientes_existentes_input" name="dientes_existentes" value="<?php echo htmlspecialchars($patient_dientes_existentes_val); ?>" min="0" max="32" disabled>
                                            </div>
                                            <div class="mb-2">
                                                <label for="evaluacion_color_input" class="form-label small fw-bold">Color (evaluación/anomalía):</label>
                                                <input type="text" class="form-control form-control-sm" id="evaluacion_color_input" name="evaluacion_color" 
                                                       value="<?php echo htmlspecialchars($patient_evaluacion_color_val); ?>" 
                                                       placeholder="Ej: A1, B2, mancha blanca..." disabled>
                                            </div>
                                            <div class="mb-2">
                                                <label for="enfermedad_periodontal_select" class="form-label small fw-bold">¿Enfermedad periodontal?:</label>
                                                <select class="form-select form-select-sm" id="enfermedad_periodontal_select" name="enfermedad_periodontal" disabled>
                                                    <option value="No Evaluado" <?php if($patient_enfermedad_periodontal_val == 'No Evaluado') echo 'selected'; ?>>No Evaluado</option>
                                                    <option value="Si" <?php if($patient_enfermedad_periodontal_val == 'Si') echo 'selected'; ?>>Sí</option>
                                                    <option value="No" <?php if($patient_enfermedad_periodontal_val == 'No') echo 'selected'; ?>>No</option>
                                                </select>
                                            </div>
                                            <button type="button" id="save-bucal-status-btn" class="btn btn-info btn-sm w-100 mt-2" style="display: none;"><i class="fas fa-save me-1"></i>Guardar Estado Bucal</button>
                                        </div>

                                        </div>
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
    const patientIdJS = <?php echo json_encode($patient_id); ?>;
    const conditionColorsJS = <?php echo json_encode(array_column($odontogram_conditions, 'color', 'condition_code')); ?>;
    const conditionDescriptionsJS = <?php echo json_encode(array_column($odontogram_conditions, 'description', 'condition_code')); ?>;
    conditionDescriptionsJS[''] = 'Sano/Limpio'; 
    conditionColorsJS[''] = '#e0e0e0'; 
    conditionColorsJS['AUS_VISUAL'] = 'none'; 

    const initialDataExistente = {
        record_id: <?php echo json_encode($data_existente['record_id']); ?>,
        notes: <?php echo json_encode($data_existente['notes']); ?>,
        teeth_data: <?php echo json_encode($data_existente['teeth_data'] ?: new stdClass()); ?>,
        record_date_sql: <?php echo json_encode($data_existente['record_date_sql']); ?>,
        record_date_display: <?php echo json_encode($data_existente['record_date_display']); ?>
    };
    const initialDataARealizar = {
        record_id: <?php echo json_encode($data_a_realizar['record_id']); ?>,
        notes: <?php echo json_encode($data_a_realizar['notes']); ?>,
        teeth_data: <?php echo json_encode($data_a_realizar['teeth_data'] ?: new stdClass()); ?>,
        record_date_sql: <?php echo json_encode($data_a_realizar['record_date_sql']); ?>,
        record_date_display: <?php echo json_encode($data_a_realizar['record_date_display']); ?>
    };
    const initialDataRealizadas = {
        record_id: <?php echo json_encode($data_realizadas['record_id']); ?>,
        notes: <?php echo json_encode($data_realizadas['notes']); ?>,
        teeth_data: <?php echo json_encode($data_realizadas['teeth_data'] ?: new stdClass()); ?>,
        record_date_sql: <?php echo json_encode($data_realizadas['record_date_sql']); ?>,
        record_date_display: <?php echo json_encode($data_realizadas['record_date_display']); ?>
    };


    document.addEventListener('DOMContentLoaded', function() {
        const svgNS = "http://www.w3.org/2000/svg";
        const odontogramSVG = document.getElementById('odontogram-chart');
        const selectedToothDisplay = document.getElementById('selected-tooth-display');
        const selectedSurfaceDisplay = document.getElementById('selected-surface-display');
        const selectedConditionDisplay = document.getElementById('selected-condition-display');
        const toothObservationsTextarea = document.getElementById('tooth-observations');
        const odontogramGeneralNotesTextarea = document.getElementById('odontogram-general-notes');
        const messageArea = document.getElementById('odontogram-message-area');
        const controlsContainer = document.getElementById('odontogram-controls');
        const applyToWholeToothButton = document.getElementById('apply-to-whole-tooth');
        const obsForToothNumSpan = document.getElementById('obs-for-tooth-num');
        const activeTabDisplaySpan = document.getElementById('active-tab-display');
        const notesForTabDisplaySpan = document.getElementById('notes-for-tab-display');
        const clearOdontogramTabButton = document.getElementById('clear-odontogram-tab-btn');
        const odontogramTabButtons = document.querySelectorAll('#odontogramTabs .nav-link');
        
        const dientesExistentesInput = document.getElementById('dientes_existentes_input');
        const evaluacionColorInput = document.getElementById('evaluacion_color_input');
        const enfermedadPeriodontalSelect = document.getElementById('enfermedad_periodontal_select');
        const saveBucalStatusButton = document.getElementById('save-bucal-status-btn'); 
        const editBucalStatusButton = document.getElementById('edit-bucal-status-btn');
        const bucalStatusMessageArea = document.getElementById('bucal-status-message-area');

        const colorExistente = 'red';
        const colorARealizar = 'blue';
        const colorRealizadas = 'green';
        const defaultSurfaceColorJS = '#e0e0e0';

        let currentActiveTabType = 'existente';
        let currentSelectedToothNumber = null;
        let currentSelectedSurfaceCode = null;
        let currentSelectedConditionCode = null;

        let odontogramData = {
            'existente': {
                record_id: initialDataExistente.record_id,
                notes: initialDataExistente.notes,
                teeth_data: JSON.parse(JSON.stringify(initialDataExistente.teeth_data || {})),
                record_date_sql: initialDataExistente.record_date_sql,
                record_date_display: initialDataExistente.record_date_display
            },
            'a_realizar': {
                record_id: initialDataARealizar.record_id,
                notes: initialDataARealizar.notes,
                teeth_data: JSON.parse(JSON.stringify(initialDataARealizar.teeth_data || {})),
                record_date_sql: initialDataARealizar.record_date_sql,
                record_date_display: initialDataARealizar.record_date_display
            },
            'realizadas': {
                record_id: initialDataRealizadas.record_id,
                notes: initialDataRealizadas.notes,
                teeth_data: JSON.parse(JSON.stringify(initialDataRealizadas.teeth_data || {})),
                record_date_sql: initialDataRealizadas.record_date_sql,
                record_date_display: initialDataRealizadas.record_date_display
            }
        };
        
        const toothSizeBase = 28;
        const centerSquareSizeBase = toothSizeBase / 2.5;
        const textYOffsetBase = 12;
        const spacingBase = 5;
        const quadrantHSpacingBase = 15;
        const quadrantVSpacingBase = 60; 

        const toothImageWidth = 50;
        const toothImageHeight = 50;

        const quadrantsFDI_Perm = { 
            topLeftOnScreen:  [18, 17, 16, 15, 14, 13, 12, 11], 
            topRightOnScreen: [21, 22, 23, 24, 25, 26, 27, 28], 
            bottomLeftOnScreen: [48, 47, 46, 45, 44, 43, 42, 41],
            bottomRightOnScreen:[31, 32, 33, 34, 35, 36, 37, 38]
        };
        const quadrantsFDI_Decid = {
            topLeftOnScreen:  [55, 54, 53, 52, 51], 
            topRightOnScreen: [61, 62, 63, 64, 65],
            bottomLeftOnScreen: [85, 84, 83, 82, 81],
            bottomRightOnScreen:[71, 72, 73, 74, 75]
        };

        function drawToothSVG(toothNumber, x, y, isDeciduous = false, isUpperArch = true) {
            const group = document.createElementNS(svgNS, 'g');
            const currentToothSize = isDeciduous ? toothSizeBase * 0.82 : toothSizeBase;
            const currentCenterSquareSize = isDeciduous ? centerSquareSizeBase * 0.82 : centerSquareSizeBase;
            const currentTextYOffset = isDeciduous ? textYOffsetBase * 0.85 : textYOffsetBase;

            group.setAttribute('id', `tooth-group-${toothNumber}`);
            group.setAttribute('class', 'tooth-group');
            group.setAttribute('transform', `translate(${x},${y})`);
            group.setAttribute('data-tooth-number', toothNumber);
            
            const toothImg = document.createElementNS(svgNS, 'image');
            toothImg.setAttribute('href', `images/${toothNumber}.png`);
            toothImg.setAttribute('width', toothImageWidth);
            toothImg.setAttribute('height', toothImageHeight);
            
            const imgX = (currentToothSize - toothImageWidth) / 2;
            let imgY;

            if (isUpperArch) {
                imgY = -(toothImageHeight + 3);
            } else {
                imgY = currentToothSize + currentTextYOffset + 3;
            }
            
            toothImg.setAttribute('x', imgX);
            toothImg.setAttribute('y', imgY);
            group.appendChild(toothImg);

            const halfSize = currentToothSize / 2;
            const centerHalf = currentCenterSquareSize / 2;

            let surfacesConfig = [
                { code: 'C', points: `${halfSize - centerHalf},${halfSize - centerHalf} ${halfSize + centerHalf},${halfSize - centerHalf} ${halfSize + centerHalf},${halfSize + centerHalf} ${halfSize - centerHalf},${halfSize + centerHalf}` },
                { code: 'V', points: `0,${currentToothSize} ${currentToothSize},${currentToothSize} ${halfSize + centerHalf},${halfSize + centerHalf} ${halfSize - centerHalf},${halfSize + centerHalf}` },
                { code: 'L', points: `0,0 ${halfSize - centerHalf},${halfSize - centerHalf} ${halfSize + centerHalf},${halfSize - centerHalf} ${currentToothSize},0` },
                { code: 'M', points: `0,0 ${halfSize - centerHalf},${halfSize - centerHalf} ${halfSize - centerHalf},${halfSize + centerHalf} 0,${currentToothSize}` },
                { code: 'D', points: `${currentToothSize},0 ${currentToothSize},${currentToothSize} ${halfSize + centerHalf},${halfSize + centerHalf} ${halfSize + centerHalf},${halfSize - centerHalf}` }
            ];

            surfacesConfig = surfacesConfig.map(s_conf => {
                let newCode = s_conf.code;
                const firstDigitQuadrant = parseInt(toothNumber.toString().slice(0,1));
                
                const isUpperPermanentArch = [1, 2].includes(firstDigitQuadrant);
                const isUpperArch = [1, 2, 5, 6].includes(firstDigitQuadrant);
                const isScreenLeftQuadrant = [1, 4, 5, 8].includes(firstDigitQuadrant);

                switch(s_conf.code) {
                    case 'C':
                        newCode = 'O';
                        break;
                    
                    case 'L': 
                        if (isUpperPermanentArch) {
                            newCode = 'V'; 
                        } else {
                            newCode = isUpperArch ? 'P' : 'L';
                        }
                        break;

                    case 'V':
                        if (isUpperPermanentArch) {
                            newCode = 'P'; 
                        }
                        break;

                    case 'M':
                        newCode = isScreenLeftQuadrant ? 'D' : 'M';
                        break;

                    case 'D':
                        newCode = isScreenLeftQuadrant ? 'M' : 'D';
                        break;
                }
                
                return { ...s_conf, code: newCode };
            });

            surfacesConfig.forEach(s_conf => {
                const surfacePolygon = document.createElementNS(svgNS, 'polygon');
                surfacePolygon.setAttribute('class', 'tooth-surface');
                surfacePolygon.setAttribute('points', s_conf.points);
                surfacePolygon.setAttribute('data-surface-code', s_conf.code);
                surfacePolygon.setAttribute('data-tooth-number', toothNumber);
                surfacePolygon.addEventListener('click', handleSurfaceClick);
                surfacePolygon.addEventListener('mouseover', handleSurfaceMouseOver);
                surfacePolygon.addEventListener('mouseout', handleSurfaceMouseOut);  
                group.appendChild(surfacePolygon);
            });

            const text = document.createElementNS(svgNS, 'text');
            text.setAttribute('class', 'tooth-number-text');
            text.setAttribute('x', currentToothSize / 2);
            text.setAttribute('y', currentToothSize + currentTextYOffset);
            text.textContent = toothNumber;
            group.appendChild(text);
            if (odontogramSVG) odontogramSVG.appendChild(group);
        }


        function drawOdontogramBase() {
            if (!odontogramSVG) { console.error("SVG element 'odontogram-chart' not found."); return; }
            odontogramSVG.innerHTML = ''; 
            
            const svgViewBoxW = parseFloat(odontogramSVG.getAttribute('viewBox').split(' ')[2]);
            
            let currentY = 65; 

            const permUpperY = currentY;
            currentY += toothSizeBase + textYOffsetBase + toothImageHeight + 25;
            const decidUpperY = currentY;
            currentY += (toothSizeBase * 0.82) + (textYOffsetBase * 0.85) + toothImageHeight + 1;
            const decidLowerY = currentY;
            currentY += (toothSizeBase * 0.82) + (textYOffsetBase * 0.85) + toothImageHeight + 25;
            const permLowerY = currentY;


            let currentX;
            const totalArchWidthPerm = (8 * toothSizeBase) + (7 * spacingBase) + quadrantHSpacingBase + (8 * toothSizeBase) + (7 * spacingBase);
            const decidToothVisualWidth = toothSizeBase * 0.82;
            const decidSpacingVisual = spacingBase * 0.85;  
            const decidQuadrantHSpacingVisual = quadrantHSpacingBase * 0.8;
            const totalArchWidthDecid = (5 * decidToothVisualWidth) + (4 * decidSpacingVisual) + decidQuadrantHSpacingVisual + (5 * decidToothVisualWidth) + (4 * decidSpacingVisual);
            
            currentX = (svgViewBoxW - totalArchWidthPerm) / 2;
            quadrantsFDI_Perm.topLeftOnScreen.forEach(num => { drawToothSVG(num, currentX, permUpperY, false, true); currentX += toothSizeBase + spacingBase; });
            currentX += quadrantHSpacingBase;
            quadrantsFDI_Perm.topRightOnScreen.forEach(num => { drawToothSVG(num, currentX, permUpperY, false, true); currentX += toothSizeBase + spacingBase; });

            currentX = (svgViewBoxW - totalArchWidthDecid) / 2;
            quadrantsFDI_Decid.topLeftOnScreen.forEach(num => { drawToothSVG(num, currentX, decidUpperY, true, true); currentX += decidToothVisualWidth + decidSpacingVisual; });
            currentX += decidQuadrantHSpacingVisual;
            quadrantsFDI_Decid.topRightOnScreen.forEach(num => { drawToothSVG(num, currentX, decidUpperY, true, true); currentX += decidToothVisualWidth + decidSpacingVisual; });
            
            currentX = (svgViewBoxW - totalArchWidthDecid) / 2;
            quadrantsFDI_Decid.bottomLeftOnScreen.forEach(num => { drawToothSVG(num, currentX, decidLowerY, true, false); currentX += decidToothVisualWidth + decidSpacingVisual; });
            currentX += decidQuadrantHSpacingVisual;
            quadrantsFDI_Decid.bottomRightOnScreen.forEach(num => { drawToothSVG(num, currentX, decidLowerY, true, false); currentX += decidToothVisualWidth + decidSpacingVisual; });

            currentX = (svgViewBoxW - totalArchWidthPerm) / 2;
            quadrantsFDI_Perm.bottomLeftOnScreen.forEach(num => { drawToothSVG(num, currentX, permLowerY, false, false); currentX += toothSizeBase + spacingBase; });
            currentX += quadrantHSpacingBase;
            quadrantsFDI_Perm.bottomRightOnScreen.forEach(num => { drawToothSVG(num, currentX, permLowerY, false, false); currentX += toothSizeBase + spacingBase; });

            const midLine = document.createElementNS(svgNS, 'line');
            midLine.setAttribute('x1', svgViewBoxW/2); 
            midLine.setAttribute('y1', permUpperY - toothImageHeight - 10 ); 
            midLine.setAttribute('x2', svgViewBoxW/2); 
            midLine.setAttribute('y2', permLowerY + toothSizeBase + textYOffsetBase + toothImageHeight + 10); 
            midLine.setAttribute('class', 'midline');
            if (odontogramSVG) odontogramSVG.appendChild(midLine);
        }
        
        function handleSurfaceClick(event) {
            event.stopPropagation();
            document.querySelectorAll('.tooth-surface.selected-surface').forEach(sf => sf.classList.remove('selected-surface'));
            this.classList.add('selected-surface');
            currentSelectedToothNumber = this.getAttribute('data-tooth-number');
            currentSelectedSurfaceCode = this.getAttribute('data-surface-code');
            
            updateSelectedInfoPanel(); 
            if (toothObservationsTextarea) toothObservationsTextarea.disabled = false;
            if (applyToWholeToothButton) applyToWholeToothButton.disabled = false;
            
            const activeTeethData = odontogramData[currentActiveTabType].teeth_data;
            if (toothObservationsTextarea) {
                toothObservationsTextarea.value = (activeTeethData[currentSelectedToothNumber] && activeTeethData[currentSelectedToothNumber].hasOwnProperty('obs')) ? activeTeethData[currentSelectedToothNumber].obs : '';
            }
        }

        function handleSurfaceMouseOver(event) {
            if (selectedConditionDisplay) {
                let conditionTextHover = '-';
                const toothNumHover = this.getAttribute('data-tooth-number');
                const surfaceCodeHover = this.getAttribute('data-surface-code');
                const currentTabData = odontogramData[currentActiveTabType].teeth_data;
                const toothData = currentTabData[toothNumHover];
                if (toothData) {
                    let displayedConditionCodeHover = null;
                    if (toothData.hasOwnProperty('whole') && toothData.whole !== null && toothData.whole !== "") {
                        displayedConditionCodeHover = toothData.whole;
                    } else if (toothData.hasOwnProperty(surfaceCodeHover) && toothData[surfaceCodeHover] !== null && toothData[surfaceCodeHover] !== "") {
                        displayedConditionCodeHover = toothData[surfaceCodeHover];
                    }
                    if (displayedConditionCodeHover !== null) {
                        conditionTextHover = conditionDescriptionsJS[displayedConditionCodeHover] || displayedConditionCodeHover;
                    } else if (displayedConditionCodeHover === "") { 
                        conditionTextHover = conditionDescriptionsJS[""];
                    }
                }
                selectedConditionDisplay.textContent = conditionTextHover;
            }
        }

        function handleSurfaceMouseOut(event) {
            updateSelectedInfoPanel(); 
        }
        
        function updateSelectedInfoPanel() {
            if (selectedToothDisplay) selectedToothDisplay.textContent = currentSelectedToothNumber || '-';
            if (selectedSurfaceDisplay) selectedSurfaceDisplay.textContent = currentSelectedSurfaceCode || '-';
            if (obsForToothNumSpan) obsForToothNumSpan.textContent = currentSelectedToothNumber ? `(${currentSelectedToothNumber})` : '';

            if (selectedConditionDisplay) {
                let conditionText = '-';
                if (currentSelectedToothNumber) { 
                    const currentTabData = odontogramData[currentActiveTabType].teeth_data;
                    const toothData = currentTabData[currentSelectedToothNumber];
                    if (toothData) {
                        let displayedConditionCode = null;
                        if (currentSelectedSurfaceCode && toothData.hasOwnProperty(currentSelectedSurfaceCode) && toothData[currentSelectedSurfaceCode] !== null) {
                            displayedConditionCode = toothData[currentSelectedSurfaceCode];
                        } else if (toothData.hasOwnProperty('whole') && toothData.whole !== null) { 
                            displayedConditionCode = toothData.whole;
                        }
                        
                        if (displayedConditionCode !== null) { 
                            conditionText = conditionDescriptionsJS[displayedConditionCode] || displayedConditionCode;
                        }
                    }
                }
                selectedConditionDisplay.textContent = conditionText;
            }
        }
        
        function getColorForTab(tabType) {
            switch(tabType) {
                case 'existente': return colorExistente;
                case 'a_realizar': return colorARealizar;
                case 'realizadas': return colorRealizadas;
                default: return defaultSurfaceColorJS;
            }
        }

        function renderOdontogramForTab(tabType) {
            if (!odontogramSVG) { console.error("RENDER: SVG element 'odontogram-chart' not found."); return; }
            
            const dataForTab = odontogramData[tabType].teeth_data;
            const colorForTabApplication = getColorForTab(tabType);

            odontogramSVG.querySelectorAll('.tooth-surface').forEach(surfaceEl => {
                surfaceEl.style.fill = defaultSurfaceColorJS;
                surfaceEl.style.stroke = '#999';
                surfaceEl.style.strokeDasharray = 'none';
            });
            odontogramSVG.querySelectorAll('.tooth-number-text').forEach(textEl => {
                textEl.style.textDecoration = 'none';
                textEl.style.fill = '#333';
            });
            
            const allPossibleToothNumbers = [
                ...quadrantsFDI_Perm.topLeftOnScreen, ...quadrantsFDI_Perm.topRightOnScreen,
                ...quadrantsFDI_Decid.topLeftOnScreen, ...quadrantsFDI_Decid.topRightOnScreen,
                ...quadrantsFDI_Decid.bottomLeftOnScreen, ...quadrantsFDI_Decid.bottomRightOnScreen, 
                ...quadrantsFDI_Perm.bottomLeftOnScreen, ...quadrantsFDI_Perm.bottomRightOnScreen
            ];
            
            allPossibleToothNumbers.forEach(toothNumCallback => {
                const toothNumStr = toothNumCallback.toString();
                if (dataForTab && dataForTab.hasOwnProperty(toothNumStr)) { 
                    const toothData = dataForTab[toothNumStr];
                    const toothGroup = odontogramSVG.querySelector(`#tooth-group-${toothNumStr}`);
                    if (toothGroup && toothData) { 
                        if (toothData.hasOwnProperty('whole') && toothData.whole !== null && toothData.whole !== "") {
                            const conditionCode = toothData.whole;
                            const isAusente = conditionCode === 'AUS';
                            const isExtraccion = conditionCode === 'EXT';
                            const displayColor = isAusente ? conditionColorsJS['AUS_VISUAL'] : colorForTabApplication;

                            toothGroup.querySelectorAll('.tooth-surface').forEach(surfaceEl => {
                                surfaceEl.style.fill = displayColor;
                                surfaceEl.style.stroke = isAusente ? '#aaa' : '#999';
                                surfaceEl.style.strokeDasharray = isAusente ? '2,2' : 'none';
                            });
                            const toothNumberText = toothGroup.querySelector('.tooth-number-text');
                            if(toothNumberText) {
                                toothNumberText.style.textDecoration = (isAusente || isExtraccion) ? 'line-through' : 'none';
                                toothNumberText.style.fill = (isAusente || isExtraccion) ? '#aaa' : '#333';
                            }
                        } else { 
                            const firstDigit = parseInt(toothNumStr.slice(0,1));
                            const centralSurfCode = 'O'; 
                            const lingualPalatalSurfCode = ((firstDigit === 1 || firstDigit === 2) || (firstDigit === 5 || firstDigit === 6)) ? 'P' : 'L';
                            let surfaceCodesForThisTooth = ['V', 'M', 'D', 'C', centralSurfCode, lingualPalatalSurfCode];
                            surfaceCodesForThisTooth = [...new Set(surfaceCodesForThisTooth)]; 

                            surfaceCodesForThisTooth.forEach(surfCode => {
                                if (toothData.hasOwnProperty(surfCode) && toothData[surfCode] !== null && toothData[surfCode] !== "") { 
                                    const surfaceElement = toothGroup.querySelector(`.tooth-surface[data-surface-code='${surfCode}']`);
                                    if (surfaceElement) {
                                        surfaceElement.style.fill = colorForTabApplication;
                                    }
                                }
                            });
                            const toothNumberText = toothGroup.querySelector('.tooth-number-text');
                            if(toothNumberText) {
                                toothNumberText.style.textDecoration = 'none';
                                toothNumberText.style.fill = '#333';
                            }
                        }
                    }
                }
            });
        }
        
        function getTabDisplayName(tabType) {
            switch(tabType) {
                case 'existente': return 'Prestación Existente';
                case 'a_realizar': return 'Prestación a Realizar';
                case 'realizadas': return 'Prestaciones Realizadas';
                default: return 'Desconocida';
            }
        }

        function switchTab(tabType) {
            currentActiveTabType = tabType;
            const tabDisplayName = getTabDisplayName(tabType);
            
            if (activeTabDisplaySpan) activeTabDisplaySpan.textContent = tabDisplayName;
            if (notesForTabDisplaySpan) notesForTabDisplaySpan.textContent = tabDisplayName;
            
            if (odontogramGeneralNotesTextarea) odontogramGeneralNotesTextarea.value = odontogramData[currentActiveTabType].notes;
            renderOdontogramForTab(currentActiveTabType);

            document.querySelectorAll('.tooth-surface.selected-surface').forEach(sf => sf.classList.remove('selected-surface'));
            currentSelectedToothNumber = null;
            currentSelectedSurfaceCode = null;
            updateSelectedInfoPanel(); 
            if (toothObservationsTextarea) {
                toothObservationsTextarea.value = '';
                toothObservationsTextarea.disabled = true;
            }
            if (applyToWholeToothButton) applyToWholeToothButton.disabled = true;
            
            const buttonText = `Guardar Solapa Actual`;
            const buttonHTML = `<i class="fas fa-save me-1"></i> ${buttonText}`;
            document.getElementById('save-odontogram-record-btn').innerHTML = buttonHTML;
            document.getElementById('save-odontogram-record-btn-top').innerHTML = buttonHTML;
        }
        
        if (controlsContainer) {
            controlsContainer.addEventListener('click', function(e) {
                const targetButton = e.target.closest('.condition-btn');
                if (targetButton) {
                    if (!currentSelectedToothNumber || !currentSelectedSurfaceCode) {
                        showMessage('Por favor, seleccione primero una superficie del diente.', 'warning', 3500);
                        return;
                    }

                    currentSelectedConditionCode = targetButton.getAttribute('data-code');
                    
                    document.querySelectorAll('.condition-palette .condition-btn.active').forEach(btn => {
                        btn.classList.remove('active', 'btn-primary');
                        btn.classList.add('btn-outline-secondary');
                    });
                    
                    if (targetButton.closest('.condition-palette')) {
                        targetButton.classList.add('active', 'btn-primary');
                        targetButton.classList.remove('btn-outline-secondary');
                    }
                    
                    applyConditionToSurface(currentSelectedToothNumber, currentSelectedSurfaceCode, currentSelectedConditionCode);
                    updateSelectedInfoPanel(); 
                }
            });
        }

        function applyConditionToSurface(toothNum, surfaceCode, conditionCode) {
            const activeTeethData = odontogramData[currentActiveTabType].teeth_data; 
            const colorForTab = getColorForTab(currentActiveTabType);

            if (!activeTeethData[toothNum] && conditionCode !== "") {
                activeTeethData[toothNum] = {};
            }
            
            if (conditionCode === "") {
                if (activeTeethData[toothNum]) {
                    delete activeTeethData[toothNum][surfaceCode];
                    if (Object.keys(activeTeethData[toothNum]).length === 0) {
                        delete activeTeethData[toothNum];
                    }
                }
            } else {
                if (!activeTeethData[toothNum]) activeTeethData[toothNum] = {};
                delete activeTeethData[toothNum]['whole'];
                activeTeethData[toothNum][surfaceCode] = conditionCode;
            }
            
            const surfaceElement = odontogramSVG.querySelector(`#tooth-group-${toothNum} .tooth-surface[data-surface-code='${surfaceCode}']`);
            
            if (surfaceElement) {
                surfaceElement.style.fill = (conditionCode === "") ? defaultSurfaceColorJS : colorForTab;
                surfaceElement.style.stroke = '#999';
                surfaceElement.style.strokeDasharray = 'none';
            }
            updateWholeToothVisualFromSurfacesJS(toothNum); 
            if (toothNum === currentSelectedToothNumber && surfaceCode === currentSelectedSurfaceCode) { 
                 updateSelectedInfoPanel();
            }
        }

        if (applyToWholeToothButton) {
            applyToWholeToothButton.addEventListener('click', function() {
                if (currentSelectedToothNumber && currentSelectedConditionCode !== null) { 
                    applyConditionToWholeTooth(currentSelectedToothNumber, currentSelectedConditionCode);
                    updateSelectedInfoPanel(); 
                } else if (!currentSelectedToothNumber) { 
                    showMessage('Por favor, seleccione un diente primero.', 'warning');
                } else if (currentSelectedConditionCode === null) {  
                    showMessage('Por favor, seleccione una condición/tratamiento de la paleta (después de seleccionar un diente).', 'warning'); 
                }
            });
        }

        function applyConditionToWholeTooth(toothNum, conditionCode) {
            const activeTeethData = odontogramData[currentActiveTabType].teeth_data; 
            const colorForTab = getColorForTab(currentActiveTabType);

            if (!activeTeethData[toothNum] && conditionCode !== "") {
                activeTeethData[toothNum] = {};
            }
            
            if (activeTeethData[toothNum]) {
                const surfaceCodesToClear = ['O', 'I', 'V', 'L', 'P', 'M', 'D', 'C'];
                surfaceCodesToClear.forEach(s => delete activeTeethData[toothNum][s]);

                if (conditionCode === "") { 
                    delete activeTeethData[toothNum]['whole'];
                    const obsVal = activeTeethData[toothNum].obs;
                    if (Object.keys(activeTeethData[toothNum]).length === 0 || 
                        (Object.keys(activeTeethData[toothNum]).length === 1 && activeTeethData[toothNum].hasOwnProperty('obs') && (obsVal === null || obsVal.trim() === ''))) {
                        delete activeTeethData[toothNum];
                    }
                } else { 
                    activeTeethData[toothNum]['whole'] = conditionCode;
                }
            }
            
            const isAusente = conditionCode === 'AUS';
            const isExtraccion = conditionCode === 'EXT'; 
            const displayColor = isAusente ? conditionColorsJS['AUS_VISUAL'] : ((conditionCode === "") ? defaultSurfaceColorJS : colorForTab);

            const toothGroup = odontogramSVG.querySelector(`#tooth-group-${toothNum}`);
            if (toothGroup) {
                toothGroup.querySelectorAll('.tooth-surface').forEach(s => { 
                    s.style.fill = displayColor; 
                    s.style.stroke = isAusente ? '#aaa' : ( (conditionCode === "") ? '#999' : '#999');
                    s.style.strokeDasharray = isAusente ? '2,2' : 'none';
                });
                const toothNumberText = toothGroup.querySelector('.tooth-number-text');
                if (toothNumberText) {
                    toothNumberText.style.textDecoration = (isAusente || isExtraccion) ? 'line-through' : 'none';
                    toothNumberText.style.fill = (isAusente || isExtraccion) ? '#aaa' : '#333';
                }
            }
            if (toothNum === currentSelectedToothNumber) { 
                 updateSelectedInfoPanel();
            }
        }

        function updateWholeToothVisualFromSurfacesJS(toothNum) {
            const activeTeethData = odontogramData[currentActiveTabType].teeth_data; 
            const colorForTabToUse = getColorForTab(currentActiveTabType);
            
            if (activeTeethData && activeTeethData.hasOwnProperty(toothNum) && 
                (!activeTeethData[toothNum].hasOwnProperty('whole') || activeTeethData[toothNum].whole === null || activeTeethData[toothNum].whole === "") ) {
                const toothGroup = odontogramSVG.querySelector(`#tooth-group-${toothNum}`);
                if (toothGroup) {
                    const toothDataCurrent = activeTeethData[toothNum]; 
                    const firstDigit = parseInt(toothNum.toString().slice(0,1));
                    const centralSurfCode = 'O';
                    const lingualPalatalSurfCode = ((firstDigit === 1 || firstDigit === 2) || (firstDigit === 5 || firstDigit === 6)) ? 'P' : 'L';
                    const surfaceCodes = [...new Set(['V', 'M', 'D', 'C', centralSurfCode, lingualPalatalSurfCode])];

                    surfaceCodes.forEach(sc => {
                        const surfaceElement = toothGroup.querySelector(`.tooth-surface[data-surface-code='${sc}']`);
                        if (surfaceElement) {
                            const cond = toothDataCurrent[sc]; 
                            surfaceElement.style.fill = (toothDataCurrent.hasOwnProperty(sc) && cond !== null && cond !== "") ? colorForTabToUse : defaultSurfaceColorJS;
                            if (cond !== 'AUS') { 
                                surfaceElement.style.strokeDasharray = 'none';
                                surfaceElement.style.stroke = '#999';
                            }
                        }
                    });
                    const toothNumberText = toothGroup.querySelector('.tooth-number-text');
                    if (toothNumberText) {
                        toothNumberText.style.textDecoration = 'none';
                        toothNumberText.style.fill = '#333';
                    }
                }
            } else if (activeTeethData && !activeTeethData.hasOwnProperty(toothNum)) {
                 const toothGroup = odontogramSVG.querySelector(`#tooth-group-${toothNum}`);
                 if (toothGroup) {
                    toothGroup.querySelectorAll('.tooth-surface').forEach(s => { 
                        s.style.fill = defaultSurfaceColorJS; 
                        s.style.stroke = '#999';
                        s.style.strokeDasharray = 'none';
                    });
                    const toothNumberText = toothGroup.querySelector('.tooth-number-text');
                    if (toothNumberText) {
                        toothNumberText.style.textDecoration = 'none';
                        toothNumberText.style.fill = '#333';
                    }
                 }
            }
        }
        
        if (toothObservationsTextarea) {
            toothObservationsTextarea.addEventListener('change', function() {
                if (currentSelectedToothNumber) {
                    const activeTeethData = odontogramData[currentActiveTabType].teeth_data; 
                    
                    if (this.value.trim() === "") {
                        if (activeTeethData[currentSelectedToothNumber]) {
                            delete activeTeethData[currentSelectedToothNumber].obs;
                            if (Object.keys(activeTeethData[currentSelectedToothNumber]).length === 0) {
                                delete activeTeethData[currentSelectedToothNumber];
                            }
                        }
                    } else {
                        if (!activeTeethData[currentSelectedToothNumber]) {
                            activeTeethData[currentSelectedToothNumber] = {};
                        }
                        activeTeethData[currentSelectedToothNumber].obs = this.value;
                    }
                }
            });
        }
        
        if (odontogramGeneralNotesTextarea) {
            odontogramGeneralNotesTextarea.addEventListener('input', function() {
                odontogramData[currentActiveTabType].notes = this.value;
            });
        }

        const saveBtnBottom = document.getElementById('save-odontogram-record-btn');
        const saveBtnTop = document.getElementById('save-odontogram-record-btn-top');
        const allSingleSaveButtons = [saveBtnTop, saveBtnBottom].filter(Boolean);

        if (allSingleSaveButtons.length > 0) {
            const saveFunction = function() {
                allSingleSaveButtons.forEach(btn => {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando...';
                });
                if(messageArea) messageArea.innerHTML = '';

                const activeTabDataForSave = odontogramData[currentActiveTabType]; 
                const payload = {
                    patient_id: patientIdJS,
                    record_type: currentActiveTabType,
                    record_date: activeTabDataForSave.record_id ? activeTabDataForSave.record_date_sql : new Date().toISOString().slice(0,10),
                    odontogram_notes: activeTabDataForSave.notes,
                    teeth_data: activeTabDataForSave.teeth_data, 
                    existing_record_id: activeTabDataForSave.record_id 
                };
                
                fetch('<?php echo $path_to_root; ?>actions/save_odontogram_data.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload)
                })
                .then(response => {
                    if (!response.ok) { return response.text().then(text => { throw new Error("Error del servidor: " + response.status + " " + response.statusText + ". Detalles: " + text) }); }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const tabDisplayName = getTabDisplayName(currentActiveTabType);
                        let successMsg = `¡Odontograma (${tabDisplayName}) guardado con éxito! `;
                        if (data.is_update) { 
                            successMsg += 'Registro actualizado.'; 
                        } else { 
                            successMsg += 'Nuevo registro creado (ID: ' + data.odontogram_record_id + ').';
                            odontogramData[currentActiveTabType].record_id = data.odontogram_record_id;
                            odontogramData[currentActiveTabType].record_date_sql = payload.record_date;
                            try {
                                const dateParts = payload.record_date.split('-');
                                const dateObjForDisplay = new Date(Date.UTC(dateParts[0], dateParts[1] - 1, dateParts[2]));
                                odontogramData[currentActiveTabType].record_date_display = dateObjForDisplay.toLocaleDateString('es-ES', {day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC'});
                            } catch(e){ /* No hacer nada si falla el formato de fecha */ }
                        }
                        showMessage(successMsg, 'success');
                    } else { 
                        showMessage('Error al guardar: ' + (data.message || 'Error desconocido.'), 'danger', 15000);
                    }
                })
                .catch(error => { 
                    console.error('Error AJAX:', error); 
                    showMessage('Error de conexión o del servidor. Revise la consola del navegador para más detalles.', 'danger', 15000); 
                })
                .finally(() => {
                    const buttonHTML = `<i class="fas fa-save me-1"></i> Guardar Solapa Actual`;
                    allSingleSaveButtons.forEach(btn => {
                        btn.disabled = false;
                        btn.innerHTML = buttonHTML;
                    });
                });
            };

            allSingleSaveButtons.forEach(btn => {
                btn.addEventListener('click', saveFunction);
            });
        }
        
        const saveAllBtnTop = document.getElementById('save-all-tabs-btn');
        const saveAllBtnBottom = document.getElementById('save-all-tabs-btn-bottom');
        const allMassiveSaveButtons = [saveAllBtnTop, saveAllBtnBottom].filter(Boolean);
        const allSaveButtonsInPage = [...allSingleSaveButtons, ...allMassiveSaveButtons];

        if (allMassiveSaveButtons.length > 0) {
            const saveAllFunction = function() {
                allSaveButtonsInPage.forEach(btn => {
                    btn.disabled = true;
                });
                saveAllBtnTop.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando Todo...';
                saveAllBtnBottom.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando Todo...';

                if(messageArea) messageArea.innerHTML = '';
                
                const payload = Object.keys(odontogramData).map(tabType => {
                    const tabData = odontogramData[tabType];
                    return {
                        patient_id: patientIdJS,
                        record_type: tabType,
                        record_date: tabData.record_id ? tabData.record_date_sql : new Date().toISOString().slice(0,10),
                        odontogram_notes: tabData.notes,
                        teeth_data: tabData.teeth_data,
                        existing_record_id: tabData.record_id
                    };
                });

                fetch('<?php echo $path_to_root; ?>actions/save_all_odontogram_data.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message || 'Todas las solapas guardadas con éxito. La página se recargará.', 'success', 0);
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        showMessage('Error al guardar todo: ' + (data.message || 'Error desconocido.'), 'danger', 15000);
                        allSaveButtonsInPage.forEach(btn => {
                            btn.disabled = false;
                        });
                        saveAllBtnTop.innerHTML = '<i class="fas fa-server me-1"></i> Guardar Todas las Solapas';
                        saveAllBtnBottom.innerHTML = '<i class="fas fa-server me-1"></i> Guardar Todas las Solapas';
                    }
                })
                .catch(error => {
                    console.error('Error AJAX (Guardar Todo):', error); 
                    showMessage('Error de conexión o del servidor al guardar todo.', 'danger', 15000); 
                     allSaveButtonsInPage.forEach(btn => {
                        btn.disabled = false;
                    });
                    saveAllBtnTop.innerHTML = '<i class="fas fa-server me-1"></i> Guardar Todas las Solapas';
                    saveAllBtnBottom.innerHTML = '<i class="fas fa-server me-1"></i> Guardar Todas las Solapas';
                });
            };

            allMassiveSaveButtons.forEach(btn => {
                btn.addEventListener('click', saveAllFunction);
            });
        }

        if (clearOdontogramTabButton) {
            clearOdontogramTabButton.addEventListener('click', function() {
                const tabDisplayName = getTabDisplayName(currentActiveTabType);
                if (confirm(`¿Está seguro de que desea limpiar todos los datos de la solapa "${tabDisplayName}"? Esta acción no se puede deshacer hasta que guarde.`)) {
                    odontogramData[currentActiveTabType].teeth_data = {};
                    odontogramData[currentActiveTabType].notes = ""; 
                    if(odontogramGeneralNotesTextarea) odontogramGeneralNotesTextarea.value = "";
                    renderOdontogramForTab(currentActiveTabType);
                    
                    currentSelectedToothNumber = null; currentSelectedSurfaceCode = null;
                    updateSelectedInfoPanel();
                    if(toothObservationsTextarea) { toothObservationsTextarea.value = ''; toothObservationsTextarea.disabled = true; }
                    if(applyToWholeToothButton) applyToWholeToothButton.disabled = true;
                    
                    showMessage(`Odontograma de la solapa "${tabDisplayName}" limpiado localmente. Guarde para aplicar los cambios.`, 'warning');
                }
            });
        }
        
        if (editBucalStatusButton) {
            editBucalStatusButton.addEventListener('click', function() {
                dientesExistentesInput.disabled = false;
                evaluacionColorInput.disabled = false;
                enfermedadPeriodontalSelect.disabled = false;
                saveBucalStatusButton.style.display = 'block';
            });
        }
        
        if (saveBucalStatusButton) {
            saveBucalStatusButton.addEventListener('click', function() {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando Estado...';
                if(bucalStatusMessageArea) bucalStatusMessageArea.innerHTML = '';
                let isSuccess = false;

                const bucalStatusPayload = {
                    patient_id: patientIdJS,
                    dientes_existentes: dientesExistentesInput ? dientesExistentesInput.value : null,
                    evaluacion_color: evaluacionColorInput ? evaluacionColorInput.value : '',
                    enfermedad_periodontal: enfermedadPeriodontalSelect ? enfermedadPeriodontalSelect.value : 'No Evaluado'
                };

                fetch('<?php echo $path_to_root; ?>actions/update_patient_bucal_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(bucalStatusPayload)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        isSuccess = true;
                        showMessageBucalStatus('¡Estado bucal general actualizado con éxito!', 'success');
                        dientesExistentesInput.disabled = true;
                        evaluacionColorInput.disabled = true;
                        enfermedadPeriodontalSelect.disabled = true;
                        this.style.display = 'none';
                    } else {
                        showMessageBucalStatus('Error al actualizar estado bucal: ' + (data.message || 'Error desconocido.'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error AJAX (Estado Bucal):', error);
                    showMessageBucalStatus('Error de conexión al guardar estado bucal.', 'danger');
                })
                .finally(() => {
                    if (!isSuccess) {
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-save me-1"></i>Guardar Estado Bucal';
                    }
                });
            });
        }

        function showMessage(message, type = 'info', duration = 6000, targetArea = messageArea) {
            if(!targetArea) return;
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.role = 'alert';
            alertDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' || type === 'warning' ? 'exclamation-triangle' : 'info-circle')} me-2"></i>
                                  ${message}
                                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
            targetArea.innerHTML = ''; 
            targetArea.appendChild(alertDiv);
            if (duration > 0) {
                setTimeout(() => {
                    if (document.body.contains(alertDiv) && alertDiv.classList.contains('show')) {
                        const bsAlert = bootstrap.Alert.getInstance(alertDiv) || new bootstrap.Alert(alertDiv);
                        if(bsAlert) bsAlert.close();
                    }
                }, duration);
            }
        }
        function showMessageBucalStatus(message, type = 'info', duration = 5000) {
            showMessage(message, type, duration, bucalStatusMessageArea);
        }
        
        odontogramTabButtons.forEach(button => {
            button.addEventListener('shown.bs.tab', function (event) {
                const newTabType = event.target.getAttribute('data-tab-type');
                switchTab(newTabType);
            });
        });

        try {
            drawOdontogramBase(); 
            switchTab('existente'); 
        } catch (e) {
            console.error("Error durante la inicialización del odontograma:", e);
            showMessage("Error crítico al inicializar el odontograma. Revise la consola.", "danger", 0);
        }

        var autoDismissAlertsPHP = document.querySelectorAll('#odontogram-message-area > .alert.alert-dismissible, #bucal-status-message-area > .alert.alert-dismissible');
        autoDismissAlertsPHP.forEach(function(alertEl) {
            setTimeout(function() {
                if (document.body.contains(alertEl)) {
                    const bsAlert = bootstrap.Alert.getInstance(alertEl) || new bootstrap.Alert(alertEl);
                    if(bsAlert) bsAlert.close();
                }
            }, 7000);
        });
    });
    </script>
</body>
</html>