<?php
session_start();

// Destruir todas las variables de sesión específicas del paciente
unset($_SESSION['patient_id']);
unset($_SESSION['patient_fname']);
unset($_SESSION['patient_lname']);

// Opcional: Si quieres destruir la sesión completamente (si no hay otras variables de sesión que quieras mantener)
// session_destroy();

// Redirigir a la página de login del portal
header('Location: login.php');
exit;
?>
