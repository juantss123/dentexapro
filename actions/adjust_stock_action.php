<?php
session_start();
// Asegurarse de que solo administradores logueados puedan acceder
if (!isset($_SESSION['admin_id'])) {
    // Si es una llamada AJAX, podría ser mejor devolver un error JSON
    // pero como redirigimos al final, un redirect a login es aceptable.
    header('Location: ../login.php'); 
    exit;
}
require_once '../config.php'; // Ajustar ruta a config.php

$status = 'error'; // Estado por defecto para la redirección
$message = 'Datos inválidos o faltantes para el ajuste de stock.'; // Mensaje por defecto
$item_id_redirect = null; // Para la redirección, aunque no se usa para redirigir al log del item específico aquí

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $adjustment_type = trim($_POST['adjustment_type'] ?? ''); // 'add' o 'subtract'
    $quantity_adjusted = filter_input(INPUT_POST, 'quantity_adjusted', FILTER_VALIDATE_INT);
    $adjustment_reason = trim($_POST['adjustment_reason'] ?? ''); // Opcional
    $current_admin_id = $_SESSION['admin_id']; // ID del administrador que realiza la acción

    // Validaciones básicas de los datos recibidos
    if ($item_id && $quantity_adjusted !== false && $quantity_adjusted > 0 && ($adjustment_type == 'add' || $adjustment_type == 'subtract')) {
        
        $item_id_redirect = $item_id; // Guardar para posible uso futuro en redirección
        
        $mysqli->begin_transaction(); // Iniciar transacción para asegurar atomicidad

        try {
            // 1. Obtener el stock actual del insumo (con bloqueo para actualización - FOR UPDATE)
            // Esto previene condiciones de carrera si múltiples usuarios intentan ajustar el stock al mismo tiempo.
            $stmt_current_stock = $mysqli->prepare("SELECT current_quantity FROM inventory_items WHERE id = ? FOR UPDATE");
            if(!$stmt_current_stock) {
                throw new Exception("Error al preparar la consulta para obtener stock actual: " . $mysqli->error);
            }
            $stmt_current_stock->bind_param("i", $item_id);
            $stmt_current_stock->execute();
            $result_stock = $stmt_current_stock->get_result();

            if ($result_stock->num_rows === 0) {
                throw new Exception("Insumo con ID {$item_id} no encontrado.");
            }
            $current_stock_row = $result_stock->fetch_assoc();
            $stock_before_adj = (int)$current_stock_row['current_quantity'];
            $stmt_current_stock->close();

            // Calcular el nuevo stock
            $new_stock = $stock_before_adj;
            if ($adjustment_type == 'add') {
                $new_stock += $quantity_adjusted;
            } else { // 'subtract'
                $new_stock -= $quantity_adjusted;
                // Opcional: Impedir que el stock sea negativo estrictamente
                // if ($new_stock < 0) {
                //    throw new Exception("No se puede restar la cantidad especificada. Stock actual: {$stock_before_adj}, Cantidad a restar: {$quantity_adjusted}. El stock no puede ser negativo.");
                // }
                // O permitirlo y que quede negativo si es necesario (ej. para corregir un error de conteo previo)
            }

            // 2. Actualizar la cantidad de stock en la tabla `inventory_items`
            $stmt_update_stock = $mysqli->prepare("UPDATE inventory_items SET current_quantity = ? WHERE id = ?");
            if(!$stmt_update_stock) {
                throw new Exception("Error al preparar la consulta para actualizar stock: " . $mysqli->error);
            }
            $stmt_update_stock->bind_param("ii", $new_stock, $item_id);
            if (!$stmt_update_stock->execute()) {
                throw new Exception("Error al actualizar el stock del insumo: " . $stmt_update_stock->error);
            }
            $stmt_update_stock->close();

            // 3. Registrar el movimiento de stock en la tabla `inventory_stock_log`
            $stmt_log = $mysqli->prepare("INSERT INTO inventory_stock_log 
                                          (item_id, admin_id, adjustment_type, quantity_adjusted, stock_before_adj, stock_after_adj, reason) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?)");
            if(!$stmt_log) {
                throw new Exception("Error al preparar la consulta para registrar el log de stock: " . $mysqli->error);
            }
            
            // Usar $current_admin_id que obtuvimos de la sesión
            $stmt_log->bind_param("iisiiis", 
                $item_id, 
                $current_admin_id, 
                $adjustment_type, 
                $quantity_adjusted, 
                $stock_before_adj, 
                $new_stock, 
                $adjustment_reason
            );
            if (!$stmt_log->execute()) {
                throw new Exception("Error al registrar el movimiento de stock en el log: " . $stmt_log->error);
            }
            $stmt_log->close();

            $mysqli->commit(); // Si todo fue bien, confirmar los cambios
            $status = 'success_update'; // Usamos 'success_update' para que index.php muestre un mensaje genérico de éxito
            $message = 'Stock ajustado con éxito. Nuevo stock para el ítem ID ' . $item_id . ': ' . $new_stock . '.';

        } catch (Exception $e) {
            $mysqli->rollback(); // Si algo falló, revertir todos los cambios de la transacción
            $message = "Error en la transacción del ajuste de stock: " . $e->getMessage();
            $status = 'error'; // Asegurar que el status sea de error
        }

    } else {
        // Si la validación inicial de los datos POST falla
        if ($quantity_adjusted !== false && $quantity_adjusted <= 0) {
            $message = "La cantidad a ajustar debe ser un número entero positivo mayor que cero.";
        }
        // $message ya tiene 'Datos inválidos o faltantes.' por defecto si otras condiciones no se cumplen
    }
} else {
    // Si el método no es POST
    $message = "Método de solicitud no permitido.";
}

// Redirigir de vuelta al listado de insumos con un mensaje de estado
// Podrías considerar redirigir a `stock_log.php?item_id=$item_id_redirect` si quieres ver el log inmediatamente.
// Por ahora, volvemos al listado general de insumos.
header('Location: ../inventory/index.php?status=' . $status . '&msg=' . urlencode($message));
exit;
?>