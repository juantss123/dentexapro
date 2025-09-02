-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 19-07-2025 a las 00:16:10
-- Versión del servidor: 10.11.13-MariaDB
-- Versión de PHP: 8.3.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `dentexap_appfinal`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Administrador',
  `profile_image` varchar(255) DEFAULT NULL,
  `role` enum('superadmin','admin','employee') NOT NULL DEFAULT 'employee',
  `permissions` text DEFAULT NULL COMMENT 'JSON array de códigos de sección permitidos',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `admin_users`
--

INSERT INTO `admin_users` (`id`, `email`, `password`, `name`, `profile_image`, `role`, `permissions`, `created_at`) VALUES
(1, 'admin@dentexapro.com', '$2b$12$QlzoVmUL4hVv.XZ0NeVpVuCjFT/W2eWP9bXPk5L3eaduse/j6P7de', 'Administrador', 'admin_1_1749442300.png', 'superadmin', NULL, '2025-05-18 05:57:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `datetime` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Programado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clinic_settings`
--

CREATE TABLE `clinic_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clinic_settings`
--

INSERT INTO `clinic_settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'clinic_pdf_name', 'Dentexapro', '2025-06-02 02:31:32', '2025-07-19 03:15:37'),
(2, 'clinic_pdf_address', 'Direccion', '2025-06-02 02:31:32', '2025-06-02 02:31:32'),
(3, 'clinic_pdf_phone', NULL, '2025-06-02 02:31:32', '2025-07-19 03:15:37'),
(4, 'clinic_pdf_email', 'presupuesto@dentexapro.com', '2025-06-02 02:31:32', '2025-06-02 03:09:30'),
(5, 'clinic_pdf_cuit', 'XX-XXXXXXXX-X', '2025-06-02 02:31:32', '2025-06-02 02:31:32'),
(6, 'clinic_pdf_footer_notes', 'Presupuesto válido por 30 días. Los precios pueden estar sujetos a cambios sin previo aviso.', '2025-06-02 02:31:32', '2025-06-02 02:31:32'),
(7, 'clinic_pdf_logo_path', NULL, '2025-06-02 02:31:32', '2025-07-19 03:15:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estimates`
--

CREATE TABLE `estimates` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `admin_user_id` int(11) NOT NULL COMMENT 'Admin que crea el presupuesto',
  `estimate_number` varchar(50) DEFAULT NULL COMMENT 'Número de presupuesto (ej. PRES-2025-001)',
  `estimate_date` date NOT NULL,
  `insurance_details` text DEFAULT NULL COMMENT 'Info de cobertura al momento del presupuesto',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('borrador','enviado','aprobado','rechazado','pagado') NOT NULL DEFAULT 'borrador',
  `notes` text DEFAULT NULL COMMENT 'Notas adicionales para el presupuesto',
  `professional_name_text` varchar(255) DEFAULT NULL COMMENT 'Nombre del profesional (texto libre)',
  `pdf_filename` varchar(255) DEFAULT NULL COMMENT 'Nombre del archivo PDF generado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estimate_items`
--

CREATE TABLE `estimate_items` (
  `id` int(11) NOT NULL,
  `estimate_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `item_total` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL COMMENT 'Nombre del insumo',
  `item_description` text DEFAULT NULL COMMENT 'Descripción detallada',
  `category` varchar(100) DEFAULT NULL COMMENT 'Categoría del insumo (ej: Descartables, Anestesia, Material de Impresión)',
  `current_quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Cantidad actual en stock',
  `min_quantity_alert` int(11) NOT NULL DEFAULT 0 COMMENT 'Nivel mínimo para generar alerta de stock bajo',
  `unit_measure` varchar(50) DEFAULT NULL COMMENT 'Unidad de medida (ej: unidad, caja, ml, gr, paquete)',
  `supplier` varchar(255) DEFAULT NULL COMMENT 'Proveedor habitual (opcional)',
  `last_purchase_date` date DEFAULT NULL COMMENT 'Fecha de la última compra (opcional)',
  `notes` text DEFAULT NULL COMMENT 'Notas adicionales sobre el insumo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_name`, `item_description`, `category`, `current_quantity`, `min_quantity_alert`, `unit_measure`, `supplier`, `last_purchase_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Jeringas', 'Jeringas de medio', 'Descartables', 122074, 4, '', '', NULL, '', '2025-05-21 19:49:04', '2025-05-23 19:07:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventory_stock_log`
--

CREATE TABLE `inventory_stock_log` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL COMMENT 'FK a inventory_items.id',
  `admin_id` int(11) DEFAULT NULL COMMENT 'FK a admin_users.id (quién hizo el ajuste)',
  `adjustment_type` enum('add','subtract') NOT NULL COMMENT 'Tipo de ajuste',
  `quantity_adjusted` int(11) NOT NULL COMMENT 'Cantidad que se añadió o restó',
  `stock_before_adj` int(11) NOT NULL COMMENT 'Stock antes del ajuste',
  `stock_after_adj` int(11) NOT NULL COMMENT 'Stock después del ajuste',
  `reason` text DEFAULT NULL COMMENT 'Motivo del ajuste',
  `adjustment_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora del ajuste'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `inventory_stock_log`
--

INSERT INTO `inventory_stock_log` (`id`, `item_id`, `admin_id`, `adjustment_type`, `quantity_adjusted`, `stock_before_adj`, `stock_after_adj`, `reason`, `adjustment_date`) VALUES
(1, 1, 1, 'add', 123123, 1, 123124, 'asdasdasdasd', '2025-05-21 20:04:53'),
(2, 1, 1, 'subtract', 1000, 123124, 122124, '', '2025-05-22 00:09:08'),
(3, 1, 1, 'subtract', 50, 122124, 122074, 'Porque hice una caries', '2025-05-23 19:07:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medical_history`
--

CREATE TABLE `medical_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `file` varchar(255) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL COMMENT 'Costo total cobrado por este registro/tratamiento',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medical_history_attachments`
--

CREATE TABLE `medical_history_attachments` (
  `id` int(11) NOT NULL,
  `medical_history_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL COMMENT 'FK a patients.id - Identifica la conversación con este paciente',
  `admin_user_id` int(11) DEFAULT NULL COMMENT 'FK a admin_users.id - Quién del personal envió (si aplica)',
  `sent_by_patient` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'TRUE si el paciente envió, FALSE si el personal envió',
  `message_content` text NOT NULL COMMENT 'Contenido del mensaje',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora de envío',
  `read_by_staff_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha y hora en que el personal leyó el mensaje del paciente',
  `read_by_patient_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha y hora en que el paciente leyó el mensaje del personal',
  `edited_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha y hora de la última edición (si aplica)',
  `is_deleted_by_staff` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'TRUE si el personal marcó este mensaje como eliminado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `odontogram_conditions`
--

CREATE TABLE `odontogram_conditions` (
  `id` int(11) NOT NULL,
  `condition_code` varchar(10) NOT NULL COMMENT 'Código corto, ej: C, AM, CO, EXT, SANO',
  `description` varchar(255) NOT NULL COMMENT 'Descripción completa, ej: Caries, Amalgama, Composite, Extracción Indicada, Diente Sano',
  `color` varchar(7) NOT NULL COMMENT 'Color HEX para representar en el odontograma, ej: #FF0000',
  `symbol_svg` text DEFAULT NULL COMMENT 'Pequeño fragmento SVG para un ícono/patrón (opcional, más avanzado)',
  `type` enum('hallazgo','tratamiento_realizado','tratamiento_planificado','estado_base') NOT NULL DEFAULT 'hallazgo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `odontogram_conditions`
--

INSERT INTO `odontogram_conditions` (`id`, `condition_code`, `description`, `color`, `symbol_svg`, `type`) VALUES
(1, 'SANO', 'Diente Sano', '#c8e6c9', NULL, 'estado_base'),
(2, 'C', 'Caries', '#ffcdd2', NULL, 'hallazgo'),
(3, 'OB', 'Obturación', '#b0bec5', NULL, 'tratamiento_realizado'),
(4, 'EXT', 'Extracción Indicada', '#757575', NULL, 'tratamiento_planificado'),
(5, 'AUS', 'Ausente', '#f5f5f5', NULL, 'estado_base'),
(6, 'COR', 'Corona', '#fff9c4', NULL, 'tratamiento_realizado'),
(7, 'IMP', 'Implante', '#b3e5fc', NULL, 'tratamiento_realizado'),
(8, 'ENDO', 'Endodoncia Realizada', '#d1c4e9', NULL, 'tratamiento_realizado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `odontogram_records`
--

CREATE TABLE `odontogram_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `record_type` enum('existente','a_realizar','realizadas') NOT NULL COMMENT 'Tipo de odontograma (solapa)',
  `record_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `odontogram_tooth_details`
--

CREATE TABLE `odontogram_tooth_details` (
  `id` int(11) NOT NULL,
  `odontogram_record_id` int(11) NOT NULL,
  `tooth_number` varchar(3) NOT NULL COMMENT 'Ej: 11, 48, 51',
  `surface_o_condition_code` varchar(10) DEFAULT NULL COMMENT 'FK a odontogram_conditions.condition_code para Oclusal',
  `surface_v_condition_code` varchar(10) DEFAULT NULL COMMENT 'FK a odontogram_conditions.condition_code para Vestibular',
  `surface_l_condition_code` varchar(10) DEFAULT NULL COMMENT 'FK a odontogram_conditions.condition_code para Lingual',
  `surface_p_condition_code` varchar(10) DEFAULT NULL COMMENT 'FK a odontogram_conditions.condition_code para Palatina',
  `surface_m_condition_code` varchar(10) DEFAULT NULL COMMENT 'FK a odontogram_conditions.condition_code para Mesial',
  `surface_d_condition_code` varchar(10) DEFAULT NULL COMMENT 'FK a odontogram_conditions.condition_code para Distal',
  `surface_c_condition_code` varchar(10) DEFAULT NULL COMMENT 'FK a odontogram_conditions.condition_code para Cervical (opcional)',
  `whole_tooth_condition_code` varchar(10) DEFAULT NULL COMMENT 'FK a odontogram_conditions.condition_code para estado general del diente',
  `observations` text DEFAULT NULL COMMENT 'Notas específicas para este diente en este registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `fname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `medical_record_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `allergies` text DEFAULT NULL COMMENT 'Alergias conocidas del paciente',
  `current_medications` text DEFAULT NULL COMMENT 'Medicación actual que toma el paciente',
  `medical_conditions` text DEFAULT NULL COMMENT 'Condiciones médicas preexistentes relevantes',
  `insurance_name` varchar(255) DEFAULT NULL COMMENT 'Nombre de la Prepaga/Obra Social',
  `insurance_number` varchar(100) DEFAULT NULL COMMENT 'Número de Afiliado de la Prepaga',
  `insurance_plan` varchar(100) DEFAULT NULL COMMENT 'Plan de la Prepaga',
  `important_alerts` text DEFAULT NULL COMMENT 'Alertas críticas o notas muy importantes sobre el paciente',
  `patient_user_email` varchar(255) DEFAULT NULL,
  `patient_password_hash` varchar(255) DEFAULT NULL,
  `patient_portal_access` enum('habilitado','deshabilitado') NOT NULL DEFAULT 'deshabilitado',
  `dientes_existentes` int(2) DEFAULT NULL COMMENT 'Cantidad de dientes presentes en boca',
  `evaluacion_color` varchar(255) DEFAULT NULL,
  `enfermedad_periodontal` enum('Si','No','No Evaluado') DEFAULT 'No Evaluado' COMMENT 'Presencia de enfermedad periodontal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `patients`
--

INSERT INTO `patients` (`id`, `dni`, `fname`, `lname`, `phone`, `email`, `address`, `birthdate`, `gender`, `medical_record_number`, `created_at`, `allergies`, `current_medications`, `medical_conditions`, `insurance_name`, `insurance_number`, `insurance_plan`, `important_alerts`, `patient_user_email`, `patient_password_hash`, `patient_portal_access`, `dientes_existentes`, `evaluacion_color`, `enfermedad_periodontal`) VALUES
(22, '35865666', 'Dentexa', 'Pro', '', 'usuario@dentexapro.com', '', '1991-04-18', 'Masculino', '', '2025-07-19 03:15:03', '', '', '', '', '', '', '', 'usuario@dentexapro.com', '$2y$10$5cSdkWdZQ/cGhUjCJY2lbuBNoSDIIh09SRN0WfQqkyazk1qbpWuP.', 'habilitado', NULL, NULL, 'No Evaluado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sidebar_sections`
--

CREATE TABLE `sidebar_sections` (
  `id` int(11) NOT NULL,
  `section_code` varchar(50) NOT NULL COMMENT 'Código único para la sección (ej: dashboard, patients, appointments)',
  `section_name` varchar(100) NOT NULL COMMENT 'Nombre descriptivo de la sección para mostrar en la UI de permisos',
  `parent_code` varchar(50) DEFAULT NULL COMMENT 'Código de la sección padre si es un submenú',
  `display_order` int(11) DEFAULT 0 COMMENT 'Para ordenar los ítems en la gestión de permisos'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sidebar_sections`
--

INSERT INTO `sidebar_sections` (`id`, `section_code`, `section_name`, `parent_code`, `display_order`) VALUES
(1, 'dashboard', 'Inicio (Dashboard)', NULL, 10),
(2, 'profile', 'Mi Perfil', NULL, 20),
(3, 'notifications', 'Notificaciones', NULL, 30),
(4, 'patients', 'Pacientes (Sección)', NULL, 40),
(5, 'patients_list', 'Listado de Pacientes', 'patients', 46),
(6, 'patients_history', 'Historial Clínico (Selector)', 'patients', 47),
(7, 'patients_odontogram', 'Odontograma (Selector)', 'patients', 48),
(8, 'appointments', 'Turnos (Sección)', NULL, 50),
(9, 'appointments_calendar', 'Calendario de Turnos', 'appointments', 56),
(10, 'appointments_list', 'Listado de Turnos', 'appointments', 57),
(11, 'reports', 'Reportes', NULL, 60),
(12, 'inventory', 'Insumos', NULL, 70),
(13, 'system', 'Sistema (Sección)', NULL, 90),
(14, 'system_backup', 'Backup/Restaurar BD', 'system', 91),
(15, 'employees', 'Gestionar Empleados', NULL, 80),
(16, 'messaging_admin', 'Mensajería Clínica (Admin)', NULL, 55),
(32, 'patients_create', 'Agregar Nuevo Paciente', 'patients', 40),
(33, 'appointments_create', 'Crear Nuevo Turno', 'appointments', 50),
(34, 'billing', 'Cobros', NULL, 75),
(35, 'estimates', 'Presupuestos', 'billing', 1),
(36, 'billing_customize_pdf', 'Personalizar PDF', 'billing', 2);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indices de la tabla `clinic_settings`
--
ALTER TABLE `clinic_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key_unique` (`setting_key`);

--
-- Indices de la tabla `estimates`
--
ALTER TABLE `estimates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `estimate_number_unique` (`estimate_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `admin_user_id` (`admin_user_id`);

--
-- Indices de la tabla `estimate_items`
--
ALTER TABLE `estimate_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `estimate_id` (`estimate_id`);

--
-- Indices de la tabla `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_name` (`item_name`),
  ADD KEY `idx_category` (`category`);

--
-- Indices de la tabla `inventory_stock_log`
--
ALTER TABLE `inventory_stock_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indices de la tabla `medical_history`
--
ALTER TABLE `medical_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indices de la tabla `medical_history_attachments`
--
ALTER TABLE `medical_history_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medical_history_id` (`medical_history_id`);

--
-- Indices de la tabla `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_user_id` (`admin_user_id`),
  ADD KEY `idx_messages_patient_sent_at` (`patient_id`,`sent_at`);

--
-- Indices de la tabla `odontogram_conditions`
--
ALTER TABLE `odontogram_conditions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `condition_code` (`condition_code`);

--
-- Indices de la tabla `odontogram_records`
--
ALTER TABLE `odontogram_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indices de la tabla `odontogram_tooth_details`
--
ALTER TABLE `odontogram_tooth_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tooth_in_record` (`odontogram_record_id`,`tooth_number`),
  ADD KEY `surface_o_condition_code` (`surface_o_condition_code`),
  ADD KEY `surface_v_condition_code` (`surface_v_condition_code`),
  ADD KEY `surface_l_condition_code` (`surface_l_condition_code`),
  ADD KEY `surface_p_condition_code` (`surface_p_condition_code`),
  ADD KEY `surface_m_condition_code` (`surface_m_condition_code`),
  ADD KEY `surface_d_condition_code` (`surface_d_condition_code`),
  ADD KEY `surface_c_condition_code` (`surface_c_condition_code`),
  ADD KEY `whole_tooth_condition_code` (`whole_tooth_condition_code`);

--
-- Indices de la tabla `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `idx_patient_user_email_unique` (`patient_user_email`);

--
-- Indices de la tabla `sidebar_sections`
--
ALTER TABLE `sidebar_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `section_code` (`section_code`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de la tabla `clinic_settings`
--
ALTER TABLE `clinic_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `estimates`
--
ALTER TABLE `estimates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `estimate_items`
--
ALTER TABLE `estimate_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `inventory_stock_log`
--
ALTER TABLE `inventory_stock_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `medical_history`
--
ALTER TABLE `medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de la tabla `medical_history_attachments`
--
ALTER TABLE `medical_history_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT de la tabla `odontogram_conditions`
--
ALTER TABLE `odontogram_conditions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `odontogram_records`
--
ALTER TABLE `odontogram_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `odontogram_tooth_details`
--
ALTER TABLE `odontogram_tooth_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=743;

--
-- AUTO_INCREMENT de la tabla `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `sidebar_sections`
--
ALTER TABLE `sidebar_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `estimates`
--
ALTER TABLE `estimates`
  ADD CONSTRAINT `estimates_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `estimates_ibfk_2` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`);

--
-- Filtros para la tabla `estimate_items`
--
ALTER TABLE `estimate_items`
  ADD CONSTRAINT `estimate_items_ibfk_1` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `inventory_stock_log`
--
ALTER TABLE `inventory_stock_log`
  ADD CONSTRAINT `inventory_stock_log_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_stock_log_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `medical_history`
--
ALTER TABLE `medical_history`
  ADD CONSTRAINT `medical_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `medical_history_attachments`
--
ALTER TABLE `medical_history_attachments`
  ADD CONSTRAINT `fk_medical_history_id` FOREIGN KEY (`medical_history_id`) REFERENCES `medical_history` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `odontogram_records`
--
ALTER TABLE `odontogram_records`
  ADD CONSTRAINT `odontogram_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `odontogram_tooth_details`
--
ALTER TABLE `odontogram_tooth_details`
  ADD CONSTRAINT `odontogram_tooth_details_ibfk_1` FOREIGN KEY (`odontogram_record_id`) REFERENCES `odontogram_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `odontogram_tooth_details_ibfk_2` FOREIGN KEY (`surface_o_condition_code`) REFERENCES `odontogram_conditions` (`condition_code`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `odontogram_tooth_details_ibfk_3` FOREIGN KEY (`surface_v_condition_code`) REFERENCES `odontogram_conditions` (`condition_code`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `odontogram_tooth_details_ibfk_4` FOREIGN KEY (`surface_l_condition_code`) REFERENCES `odontogram_conditions` (`condition_code`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `odontogram_tooth_details_ibfk_5` FOREIGN KEY (`surface_p_condition_code`) REFERENCES `odontogram_conditions` (`condition_code`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `odontogram_tooth_details_ibfk_6` FOREIGN KEY (`surface_m_condition_code`) REFERENCES `odontogram_conditions` (`condition_code`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `odontogram_tooth_details_ibfk_7` FOREIGN KEY (`surface_d_condition_code`) REFERENCES `odontogram_conditions` (`condition_code`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `odontogram_tooth_details_ibfk_8` FOREIGN KEY (`surface_c_condition_code`) REFERENCES `odontogram_conditions` (`condition_code`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `odontogram_tooth_details_ibfk_9` FOREIGN KEY (`whole_tooth_condition_code`) REFERENCES `odontogram_conditions` (`condition_code`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
