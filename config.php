<?php
// Iniciar la sesión al principio de config.php si aún no se ha iniciado,
// ya que la función de permisos podría necesitar acceso a $_SESSION.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dentexap_appfinal');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    die('Error al conectar a MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

if (!$mysqli->set_charset("utf8mb4")) {
    // printf("Error cargando el conjunto de caracteres utf8mb4: %s\n", $mysqli->error);
}

define('PROJECT_ROOT', __DIR__); 
define('UPLOAD_DIR', PROJECT_ROOT . '/uploads/'); 
if (!file_exists(UPLOAD_DIR)) {
    if (!@mkdir(UPLOAD_DIR, 0777, true) && !is_dir(UPLOAD_DIR)) {
        // Consider logging this error instead of die() in production
        // die('Error al crear el directorio de subidas: ' . UPLOAD_DIR);
    }
}
define('UPLOAD_DIR_NAME_CONST', 'uploads'); 
if (!defined('MAIN_ADMIN_ID_PROFILE')) { // Para profile.php
    define('MAIN_ADMIN_ID_PROFILE', 1);
}
if (!defined('SUPER_ADMIN_MAIN_ID')) { // Para delete_admin.php y employees/index.php
    define('SUPER_ADMIN_MAIN_ID', 1);
}


// --- FUNCIÓN DE VERIFICACIÓN DE PERMISOS ---
if (!function_exists('user_has_permission')) {
    /**
     * Verifica si el usuario logueado tiene permiso para acceder a una sección específica.
     *
     * @param string $section_code El código de la sección a verificar (debe coincidir con sidebar_sections.section_code).
     * @param string|null $user_permissions_json El string JSON de permisos del usuario desde la BD.
     * @param string|null $user_role El rol del usuario (ej: 'superadmin', 'admin', 'employee').
     * @return bool True si tiene permiso, False en caso contrario.
     */
    function user_has_permission($section_code, $user_permissions_json, $user_role) {
        // El superadmin siempre tiene permiso para todo.
        if ($user_role === 'superadmin') {
            return true;
        }

        // El perfil propio siempre debe ser accesible si el usuario está logueado.
        // (El control de si está logueado se hace antes de llamar a esta función usualmente)
        if ($section_code === 'profile') {
            return true; 
        }

        // Si no es superadmin y no tiene permisos definidos (ej. string JSON vacío o nulo),
        // por defecto no tiene permiso para secciones específicas (excepto 'profile').
        if (empty($user_permissions_json)) {
            return false;
        }

        $allowed_sections = json_decode($user_permissions_json, true);

        // Verificar si el JSON es válido y es un array
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($allowed_sections)) {
            error_log("Error al decodificar JSON de permisos o no es un array para el usuario. JSON: " . $user_permissions_json);
            return false; 
        }

        return in_array($section_code, $allowed_sections);
    }
}

if (!function_exists('isMobileDevice')) {
    function isMobileDevice() {
        // Lista simplificada de keywords comunes en user agents móviles
        $mobileKeywords = ['Mobile','Android','iPhone','iPad','iPod','BlackBerry','Opera Mini','IEMobile','WPDesktop','tablet'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        foreach ($mobileKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}
// date_default_timezone_set('America/Argentina/Buenos_Aires'); 
?>
