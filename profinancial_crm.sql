-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 19-09-2025 a las 22:40:27
-- Versión del servidor: 9.1.0
-- Versión de PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `profinancial_crm`
--

DELIMITER $$
--
-- Procedimientos
--
DROP PROCEDURE IF EXISTS `sp_marcar_pagado`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_marcar_pagado` (IN `p_presentacion_id` BIGINT, IN `p_usuario_id` BIGINT, IN `p_fecha` DATE, IN `p_comprobante` VARCHAR(160))   BEGIN
  SET @current_user_id = p_usuario_id;

  -- Nota: la máquina de estados impide pagar si no está presentado
  UPDATE presentaciones
     SET pagado = 1,
         fecha_pago = COALESCE(p_fecha, CURRENT_DATE),
         comprobante_pago = p_comprobante
   WHERE id = p_presentacion_id;

  SET @current_user_id = NULL;
END$$

DROP PROCEDURE IF EXISTS `sp_marcar_presentado`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_marcar_presentado` (IN `p_presentacion_id` BIGINT, IN `p_usuario_id` BIGINT, IN `p_fecha` DATE)   BEGIN
  SET @current_user_id = p_usuario_id;

  UPDATE presentaciones
     SET presentado = 1,
         fecha_presentacion = COALESCE(p_fecha, CURRENT_DATE),
         presentado_por = p_usuario_id
   WHERE id = p_presentacion_id;

  SET @current_user_id = NULL;
END$$

DROP PROCEDURE IF EXISTS `sp_registrar_auditoria`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_auditoria` (IN `p_usuario_id` BIGINT, IN `p_modulo` VARCHAR(20), IN `p_cliente_id` BIGINT, IN `p_campo_afectado` VARCHAR(50), IN `p_valor_anterior` TEXT, IN `p_valor_nuevo` TEXT, IN `p_ip` VARCHAR(45), IN `p_user_agent` VARCHAR(255))   BEGIN
  -- Construir el detalle JSON
  SET @detalle_json = JSON_OBJECT(
    'campo', p_campo_afectado,
    'valor_anterior', p_valor_anterior,
    'valor_nuevo', p_valor_nuevo,
    'cliente_id', p_cliente_id,
    'ip', p_ip,
    'user_agent', p_user_agent
  );

  -- Insertar en auditoría
  INSERT INTO auditoria (usuario_id, accion, modulo, detalle, ip)
  VALUES (p_usuario_id, 'ACTUALIZACION', p_modulo, @detalle_json, p_ip);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones_cliente`
--

DROP TABLE IF EXISTS `asignaciones_cliente`;
CREATE TABLE IF NOT EXISTS `asignaciones_cliente` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `cliente_id` bigint NOT NULL,
  `usuario_id` bigint NOT NULL,
  `rol_en_cliente` enum('RESPONSABLE','APOYO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RESPONSABLE',
  `desde` date NOT NULL,
  `hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asig_cliente` (`cliente_id`),
  KEY `idx_asig_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
CREATE TABLE IF NOT EXISTS `auditoria` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint NOT NULL,
  `accion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulo` enum('CLIENTE','IVA','PA','CONTABILIDAD','PLANILLA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_id` bigint DEFAULT NULL COMMENT 'ID del cliente afectado',
  `campo_afectado` varchar(64) DEFAULT NULL COMMENT 'Nombre del campo modificado',
  `valor_anterior` text COMMENT 'Valor antes del cambio',
  `valor_nuevo` text COMMENT 'Valor después del cambio',
  `detalle` json DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL COMMENT 'IP desde donde se realizó la acción',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Agente de usuario del navegador',
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_usuario` (`usuario_id`),
  KEY `idx_auditoria_cliente` (`cliente_id`),
  KEY `idx_auditoria_fecha` (`fecha`),
  KEY `idx_auditoria_modulo` (`modulo`),
  KEY `idx_auditoria_campo` (`campo_afectado`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro de auditoría de cambios en el sistema';

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id`, `usuario_id`, `accion`, `modulo`, `cliente_id`, `campo_afectado`, `valor_anterior`, `valor_nuevo`, `detalle`, `ip`, `user_agent`, `fecha`) VALUES
(1, 2, 'CAMBIO_ESTADO', 'PA', NULL, NULL, NULL, NULL, '{\"antes\": \"documento pendiente\", \"despues\": \"pagada\", \"cliente_id\": 1, \"cliente_nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}', NULL, NULL, '2025-09-03 22:04:25'),
(2, 2, 'ACTUALIZAR', 'CLIENTE', 12, 'nombre', 'Juan', 'Juan Guarnizo', '{\"tipo\": \"campo_cliente\", \"campo\": \"nombre\", \"timestamp\": \"2025-09-03 22:16:30\", \"valor_nuevo\": \"Juan Guarnizo\", \"valor_anterior\": \"Juan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-03 22:16:30'),
(3, 2, 'ACTUALIZAR', 'CLIENTE', 17, 'contador', 'Jose', 'Jose Jose', '{\"tipo\": \"campo_cliente\", \"campo\": \"contador\", \"timestamp\": \"2025-09-03 22:28:32\", \"valor_nuevo\": \"Jose Jose\", \"valor_anterior\": \"Jose\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', '2025-09-03 22:28:32'),
(4, 2, 'ACTUALIZAR', 'CLIENTE', 16, 'nombre', 'Julio', 'TELETTON', '{\"tipo\": \"campo_cliente\", \"campo\": \"nombre\", \"timestamp\": \"2025-09-03 22:29:21\", \"valor_nuevo\": \"TELETTON\", \"valor_anterior\": \"Julio\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', '2025-09-03 22:29:21'),
(5, 2, 'ACTUALIZAR', 'CLIENTE', 16, 'nombre', 'TELETTON', 'TELETTONÑAÑÑA', '{\"tipo\": \"campo_cliente\", \"campo\": \"nombre\", \"timestamp\": \"2025-09-03 22:29:33\", \"valor_nuevo\": \"TELETTONÑAÑÑA\", \"valor_anterior\": \"TELETTON\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', '2025-09-03 22:29:33'),
(6, 3, 'CAMBIO_ESTADO', 'CONTABILIDAD', NULL, NULL, NULL, NULL, '{\"antes\": \"pendiente de procesar\", \"despues\": \"en proceso\", \"cliente_id\": 13, \"cliente_nombre\": \"tommy\"}', NULL, NULL, '2025-09-16 23:00:50'),
(7, 3, 'ACTUALIZAR_ESTADO', 'CONTABILIDAD', 13, 'declaracion_contabilidad', 'pendiente de procesar', 'en proceso', '{\"tipo\": \"estado_declaracion\", \"timestamp\": \"2025-09-16 23:00:50\", \"declaracion\": \"conta\", \"valor_nuevo\": \"en proceso\", \"valor_anterior\": \"pendiente de procesar\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-16 23:00:50'),
(8, 3, 'CAMBIO_ESTADO', 'CONTABILIDAD', NULL, NULL, NULL, NULL, '{\"antes\": \"en proceso\", \"despues\": \"pendiente de procesar\", \"cliente_id\": 13, \"cliente_nombre\": \"tommy\"}', NULL, NULL, '2025-09-16 23:10:11'),
(9, 3, 'ACTUALIZAR_ESTADO', 'CONTABILIDAD', 13, 'declaracion_contabilidad', 'en proceso', 'pendiente de procesar', '{\"tipo\": \"estado_declaracion\", \"timestamp\": \"2025-09-16 23:10:11\", \"declaracion\": \"conta\", \"valor_nuevo\": \"pendiente de procesar\", \"valor_anterior\": \"en proceso\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-16 23:10:11'),
(10, 3, 'CAMBIO_ESTADO', 'CONTABILIDAD', NULL, NULL, NULL, NULL, '{\"antes\": \"pendiente de procesar\", \"despues\": \"presentada\", \"cliente_id\": 13, \"cliente_nombre\": \"tommy\"}', NULL, NULL, '2025-09-16 23:12:49'),
(11, 3, 'ACTUALIZAR_ESTADO', 'CONTABILIDAD', 13, 'declaracion_contabilidad', 'pendiente de procesar', 'presentada', '{\"tipo\": \"estado_declaracion\", \"timestamp\": \"2025-09-16 23:12:49\", \"declaracion\": \"conta\", \"valor_nuevo\": \"presentada\", \"valor_anterior\": \"pendiente de procesar\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-16 23:12:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bitacora_actividad`
--

DROP TABLE IF EXISTS `bitacora_actividad`;
CREATE TABLE IF NOT EXISTS `bitacora_actividad` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint DEFAULT NULL,
  `accion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_id` bigint DEFAULT NULL,
  `detalle` json DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bitacora_usuario` (`usuario_id`),
  KEY `idx_bitacora_accion` (`accion`),
  KEY `idx_bitacora_entidad` (`entidad`,`entidad_id`)
) ENGINE=InnoDB AUTO_INCREMENT=171 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `bitacora_actividad`
--

INSERT INTO `bitacora_actividad` (`id`, `usuario_id`, `accion`, `entidad`, `entidad_id`, `detalle`, `ip`, `creado_en`) VALUES
(1, NULL, 'CREAR_CLIENTE', 'clientes', 1, '{\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V.\"}', NULL, '2025-08-18 23:39:07'),
(2, NULL, 'CREAR_CLIENTE', 'clientes', 2, '{\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}', NULL, '2025-08-18 23:39:07'),
(3, NULL, 'CREAR_CLIENTE', 'clientes', 3, '{\"nit\": \"0614-100303-003-2\", \"nrc\": \"20003-3\", \"nombre\": \"Cobalto Foods S.A. de C.V.\"}', NULL, '2025-08-18 23:39:07'),
(4, NULL, 'CREAR_CLIENTE', 'clientes', 4, '{\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}', NULL, '2025-08-18 23:39:07'),
(5, NULL, 'CREAR_CLIENTE', 'clientes', 5, '{\"nit\": \"0614-100505-005-4\", \"nrc\": \"20005-5\", \"nombre\": \"Fénix Agro S.A. de C.V.\"}', NULL, '2025-08-18 23:39:07'),
(6, NULL, 'CREAR_CLIENTE', 'clientes', 6, '{\"nit\": \"0614-100606-006-5\", \"nrc\": \"20006-6\", \"nombre\": \"Galaxia Media S.A. de C.V.\"}', NULL, '2025-08-18 23:39:07'),
(7, NULL, 'CREAR_CLIENTE', 'clientes', 7, '{\"nit\": \"0614-100707-007-6\", \"nrc\": \"20007-7\", \"nombre\": \"Horizonte Construcciones S.A. de C.V.\"}', NULL, '2025-08-18 23:39:07'),
(8, NULL, 'CREAR_CLIENTE', 'clientes', 8, '{\"nit\": \"0614-100808-008-7\", \"nrc\": \"20008-8\", \"nombre\": \"Ícaro Travel S.A. de C.V.\"}', NULL, '2025-08-18 23:39:07'),
(9, NULL, 'CREAR_CLIENTE', 'clientes', 9, '{\"nit\": \"0614-100909-009-8\", \"nrc\": \"20009-9\", \"nombre\": \"Jaguar Security S.A. de C.V.\"}', NULL, '2025-08-18 23:39:07'),
(10, NULL, 'CREAR_CLIENTE', 'clientes', 10, '{\"nit\": \"0614-101010-010-9\", \"nrc\": \"20010-0\", \"nombre\": \"Kappa Servicios S.A. de C.V.\"}', NULL, '2025-08-18 23:39:07'),
(11, NULL, 'CREAR_PRESENTACION', 'presentaciones', 1, '{\"pagado\": 1, \"periodo_id\": 1, \"presentado\": 1, \"tipo_formulario_id\": 1}', NULL, '2025-08-18 23:39:07'),
(12, NULL, 'CREAR_PRESENTACION', 'presentaciones', 2, '{\"pagado\": 0, \"periodo_id\": 1, \"presentado\": 1, \"tipo_formulario_id\": 2}', NULL, '2025-08-18 23:39:07'),
(13, NULL, 'CREAR_PRESENTACION', 'presentaciones', 3, '{\"pagado\": 0, \"periodo_id\": 1, \"presentado\": 0, \"tipo_formulario_id\": 3}', NULL, '2025-08-18 23:39:07'),
(14, NULL, 'CREAR_PRESENTACION', 'presentaciones', 4, '{\"pagado\": 0, \"periodo_id\": 1, \"presentado\": 1, \"tipo_formulario_id\": 4}', NULL, '2025-08-18 23:39:07'),
(15, NULL, 'CREAR_PRESENTACION', 'presentaciones', 5, '{\"pagado\": 1, \"periodo_id\": 1, \"presentado\": 1, \"tipo_formulario_id\": 5}', NULL, '2025-08-18 23:39:07'),
(16, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V.\"}}', NULL, '2025-08-18 23:55:58'),
(17, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 2, '{\"antes\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}}', NULL, '2025-08-18 23:55:58'),
(18, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 3, '{\"antes\": {\"nit\": \"0614-100303-003-2\", \"nrc\": \"20003-3\", \"nombre\": \"Cobalto Foods S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100303-003-2\", \"nrc\": \"20003-3\", \"nombre\": \"Cobalto Foods S.A. de C.V.\"}}', NULL, '2025-08-18 23:55:58'),
(19, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 4, '{\"antes\": {\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}}', NULL, '2025-08-18 23:55:58'),
(20, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 5, '{\"antes\": {\"nit\": \"0614-100505-005-4\", \"nrc\": \"20005-5\", \"nombre\": \"Fénix Agro S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100505-005-4\", \"nrc\": \"20005-5\", \"nombre\": \"Fénix Agro S.A. de C.V.\"}}', NULL, '2025-08-18 23:55:58'),
(21, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 6, '{\"antes\": {\"nit\": \"0614-100606-006-5\", \"nrc\": \"20006-6\", \"nombre\": \"Galaxia Media S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100606-006-5\", \"nrc\": \"20006-6\", \"nombre\": \"Galaxia Media S.A. de C.V.\"}}', NULL, '2025-08-18 23:55:58'),
(22, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 7, '{\"antes\": {\"nit\": \"0614-100707-007-6\", \"nrc\": \"20007-7\", \"nombre\": \"Horizonte Construcciones S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100707-007-6\", \"nrc\": \"20007-7\", \"nombre\": \"Horizonte Construcciones S.A. de C.V.\"}}', NULL, '2025-08-18 23:55:58'),
(23, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 8, '{\"antes\": {\"nit\": \"0614-100808-008-7\", \"nrc\": \"20008-8\", \"nombre\": \"Ícaro Travel S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100808-008-7\", \"nrc\": \"20008-8\", \"nombre\": \"Ícaro Travel S.A. de C.V.\"}}', NULL, '2025-08-18 23:55:58'),
(24, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 9, '{\"antes\": {\"nit\": \"0614-100909-009-8\", \"nrc\": \"20009-9\", \"nombre\": \"Jaguar Security S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100909-009-8\", \"nrc\": \"20009-9\", \"nombre\": \"Jaguar Security S.A. de C.V.\"}}', NULL, '2025-08-18 23:55:58'),
(25, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 10, '{\"antes\": {\"nit\": \"0614-101010-010-9\", \"nrc\": \"20010-0\", \"nombre\": \"Kappa Servicios S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-101010-010-9\", \"nrc\": \"20010-0\", \"nombre\": \"Kappa Servicios S.A. de C.V.\"}}', NULL, '2025-08-18 23:55:58'),
(26, NULL, 'ACTUALIZAR_PRESENTACION', 'presentaciones', 1, '{\"antes\": {\"pagado\": 1, \"fecha_pago\": \"2025-07-07\", \"presentado\": 1, \"fecha_presentacion\": \"2025-07-05\"}, \"despues\": {\"pagado\": 1, \"fecha_pago\": \"2025-07-07\", \"presentado\": 1, \"fecha_presentacion\": \"2025-07-05\"}}', NULL, '2025-08-18 23:55:58'),
(27, NULL, 'ACTUALIZAR_PRESENTACION', 'presentaciones', 2, '{\"antes\": {\"pagado\": 0, \"fecha_pago\": null, \"presentado\": 1, \"fecha_presentacion\": \"2025-07-12\"}, \"despues\": {\"pagado\": 0, \"fecha_pago\": null, \"presentado\": 1, \"fecha_presentacion\": \"2025-07-12\"}}', NULL, '2025-08-18 23:55:58'),
(28, NULL, 'ACTUALIZAR_PRESENTACION', 'presentaciones', 3, '{\"antes\": {\"pagado\": 0, \"fecha_pago\": null, \"presentado\": 0, \"fecha_presentacion\": null}, \"despues\": {\"pagado\": 0, \"fecha_pago\": null, \"presentado\": 0, \"fecha_presentacion\": null}}', NULL, '2025-08-18 23:55:58'),
(29, NULL, 'ACTUALIZAR_PRESENTACION', 'presentaciones', 4, '{\"antes\": {\"pagado\": 0, \"fecha_pago\": null, \"presentado\": 1, \"fecha_presentacion\": \"2025-07-20\"}, \"despues\": {\"pagado\": 0, \"fecha_pago\": null, \"presentado\": 1, \"fecha_presentacion\": \"2025-07-20\"}}', NULL, '2025-08-18 23:55:58'),
(30, NULL, 'ACTUALIZAR_PRESENTACION', 'presentaciones', 5, '{\"antes\": {\"pagado\": 1, \"fecha_pago\": \"2025-07-25\", \"presentado\": 1, \"fecha_presentacion\": \"2025-07-22\"}, \"despues\": {\"pagado\": 1, \"fecha_pago\": \"2025-07-25\", \"presentado\": 1, \"fecha_presentacion\": \"2025-07-22\"}}', NULL, '2025-08-18 23:55:58'),
(31, NULL, 'CREAR_PRESENTACION', 'presentaciones', 11, '{\"pagado\": 0, \"periodo_id\": 2, \"presentado\": 1, \"tipo_formulario_id\": 1}', NULL, '2025-08-18 23:55:58'),
(32, NULL, 'CREAR_PRESENTACION', 'presentaciones', 12, '{\"pagado\": 0, \"periodo_id\": 2, \"presentado\": 0, \"tipo_formulario_id\": 2}', NULL, '2025-08-18 23:55:58'),
(33, NULL, 'CREAR_PRESENTACION', 'presentaciones', 13, '{\"pagado\": 1, \"periodo_id\": 2, \"presentado\": 1, \"tipo_formulario_id\": 3}', NULL, '2025-08-18 23:55:58'),
(34, NULL, 'CREAR_PRESENTACION', 'presentaciones', 14, '{\"pagado\": 0, \"periodo_id\": 2, \"presentado\": 0, \"tipo_formulario_id\": 4}', NULL, '2025-08-18 23:55:58'),
(35, NULL, 'CREAR_PRESENTACION', 'presentaciones', 15, '{\"pagado\": 0, \"periodo_id\": 2, \"presentado\": 1, \"tipo_formulario_id\": 5}', NULL, '2025-08-18 23:55:58'),
(36, NULL, 'CREAR_PRESENTACION', 'presentaciones', 16, '{\"pagado\": 0, \"periodo_id\": 3, \"presentado\": 0, \"tipo_formulario_id\": 1}', NULL, '2025-08-18 23:55:58'),
(37, NULL, 'CREAR_PRESENTACION', 'presentaciones', 17, '{\"pagado\": 1, \"periodo_id\": 3, \"presentado\": 1, \"tipo_formulario_id\": 2}', NULL, '2025-08-18 23:55:58'),
(38, NULL, 'CREAR_PRESENTACION', 'presentaciones', 18, '{\"pagado\": 0, \"periodo_id\": 3, \"presentado\": 1, \"tipo_formulario_id\": 3}', NULL, '2025-08-18 23:55:58'),
(39, NULL, 'CREAR_PRESENTACION', 'presentaciones', 19, '{\"pagado\": 1, \"periodo_id\": 3, \"presentado\": 1, \"tipo_formulario_id\": 4}', NULL, '2025-08-18 23:55:58'),
(40, NULL, 'CREAR_PRESENTACION', 'presentaciones', 20, '{\"pagado\": 0, \"periodo_id\": 3, \"presentado\": 0, \"tipo_formulario_id\": 5}', NULL, '2025-08-18 23:55:58'),
(41, NULL, 'CREAR_PRESENTACION', 'presentaciones', 21, '{\"pagado\": 1, \"periodo_id\": 4, \"presentado\": 1, \"tipo_formulario_id\": 1}', NULL, '2025-08-18 23:55:58'),
(42, NULL, 'CREAR_PRESENTACION', 'presentaciones', 22, '{\"pagado\": 1, \"periodo_id\": 4, \"presentado\": 1, \"tipo_formulario_id\": 2}', NULL, '2025-08-18 23:55:58'),
(43, NULL, 'CREAR_PRESENTACION', 'presentaciones', 23, '{\"pagado\": 0, \"periodo_id\": 4, \"presentado\": 0, \"tipo_formulario_id\": 3}', NULL, '2025-08-18 23:55:58'),
(44, NULL, 'CREAR_PRESENTACION', 'presentaciones', 24, '{\"pagado\": 0, \"periodo_id\": 4, \"presentado\": 1, \"tipo_formulario_id\": 4}', NULL, '2025-08-18 23:55:58'),
(45, NULL, 'CREAR_PRESENTACION', 'presentaciones', 25, '{\"pagado\": 0, \"periodo_id\": 4, \"presentado\": 1, \"tipo_formulario_id\": 5}', NULL, '2025-08-18 23:55:58'),
(46, NULL, 'CREAR_PRESENTACION', 'presentaciones', 26, '{\"pagado\": 0, \"periodo_id\": 5, \"presentado\": 1, \"tipo_formulario_id\": 1}', NULL, '2025-08-18 23:55:58'),
(47, NULL, 'CREAR_PRESENTACION', 'presentaciones', 27, '{\"pagado\": 0, \"periodo_id\": 5, \"presentado\": 1, \"tipo_formulario_id\": 2}', NULL, '2025-08-18 23:55:58'),
(48, NULL, 'CREAR_PRESENTACION', 'presentaciones', 28, '{\"pagado\": 1, \"periodo_id\": 5, \"presentado\": 1, \"tipo_formulario_id\": 3}', NULL, '2025-08-18 23:55:58'),
(49, NULL, 'CREAR_PRESENTACION', 'presentaciones', 29, '{\"pagado\": 0, \"periodo_id\": 5, \"presentado\": 0, \"tipo_formulario_id\": 4}', NULL, '2025-08-18 23:55:58'),
(50, NULL, 'CREAR_PRESENTACION', 'presentaciones', 30, '{\"pagado\": 0, \"periodo_id\": 5, \"presentado\": 0, \"tipo_formulario_id\": 5}', NULL, '2025-08-18 23:55:58'),
(51, NULL, 'CREAR_PRESENTACION', 'presentaciones', 31, '{\"pagado\": 0, \"periodo_id\": 6, \"presentado\": 0, \"tipo_formulario_id\": 1}', NULL, '2025-08-18 23:55:58'),
(52, NULL, 'CREAR_PRESENTACION', 'presentaciones', 32, '{\"pagado\": 0, \"periodo_id\": 6, \"presentado\": 0, \"tipo_formulario_id\": 2}', NULL, '2025-08-18 23:55:58'),
(53, NULL, 'CREAR_PRESENTACION', 'presentaciones', 33, '{\"pagado\": 0, \"periodo_id\": 6, \"presentado\": 1, \"tipo_formulario_id\": 3}', NULL, '2025-08-18 23:55:58'),
(54, NULL, 'CREAR_PRESENTACION', 'presentaciones', 34, '{\"pagado\": 0, \"periodo_id\": 6, \"presentado\": 1, \"tipo_formulario_id\": 4}', NULL, '2025-08-18 23:55:58'),
(55, NULL, 'CREAR_PRESENTACION', 'presentaciones', 35, '{\"pagado\": 1, \"periodo_id\": 6, \"presentado\": 1, \"tipo_formulario_id\": 5}', NULL, '2025-08-18 23:55:58'),
(56, NULL, 'CREAR_PRESENTACION', 'presentaciones', 36, '{\"pagado\": 0, \"periodo_id\": 7, \"presentado\": 1, \"tipo_formulario_id\": 1}', NULL, '2025-08-18 23:55:58'),
(57, NULL, 'CREAR_PRESENTACION', 'presentaciones', 37, '{\"pagado\": 1, \"periodo_id\": 7, \"presentado\": 1, \"tipo_formulario_id\": 2}', NULL, '2025-08-18 23:55:58'),
(58, NULL, 'CREAR_PRESENTACION', 'presentaciones', 38, '{\"pagado\": 0, \"periodo_id\": 7, \"presentado\": 0, \"tipo_formulario_id\": 3}', NULL, '2025-08-18 23:55:58'),
(59, NULL, 'CREAR_PRESENTACION', 'presentaciones', 39, '{\"pagado\": 1, \"periodo_id\": 7, \"presentado\": 1, \"tipo_formulario_id\": 4}', NULL, '2025-08-18 23:55:58'),
(60, NULL, 'CREAR_PRESENTACION', 'presentaciones', 40, '{\"pagado\": 0, \"periodo_id\": 7, \"presentado\": 1, \"tipo_formulario_id\": 5}', NULL, '2025-08-18 23:55:58'),
(61, NULL, 'CREAR_PRESENTACION', 'presentaciones', 41, '{\"pagado\": 1, \"periodo_id\": 8, \"presentado\": 1, \"tipo_formulario_id\": 1}', NULL, '2025-08-18 23:55:58'),
(62, NULL, 'CREAR_PRESENTACION', 'presentaciones', 42, '{\"pagado\": 0, \"periodo_id\": 8, \"presentado\": 0, \"tipo_formulario_id\": 2}', NULL, '2025-08-18 23:55:58'),
(63, NULL, 'CREAR_PRESENTACION', 'presentaciones', 43, '{\"pagado\": 0, \"periodo_id\": 8, \"presentado\": 1, \"tipo_formulario_id\": 3}', NULL, '2025-08-18 23:55:58'),
(64, NULL, 'CREAR_PRESENTACION', 'presentaciones', 44, '{\"pagado\": 0, \"periodo_id\": 8, \"presentado\": 0, \"tipo_formulario_id\": 4}', NULL, '2025-08-18 23:55:58'),
(65, NULL, 'CREAR_PRESENTACION', 'presentaciones', 45, '{\"pagado\": 1, \"periodo_id\": 8, \"presentado\": 1, \"tipo_formulario_id\": 5}', NULL, '2025-08-18 23:55:58'),
(66, NULL, 'CREAR_PRESENTACION', 'presentaciones', 46, '{\"pagado\": 0, \"periodo_id\": 9, \"presentado\": 1, \"tipo_formulario_id\": 1}', NULL, '2025-08-18 23:55:58'),
(67, NULL, 'CREAR_PRESENTACION', 'presentaciones', 47, '{\"pagado\": 0, \"periodo_id\": 9, \"presentado\": 1, \"tipo_formulario_id\": 2}', NULL, '2025-08-18 23:55:58'),
(68, NULL, 'CREAR_PRESENTACION', 'presentaciones', 48, '{\"pagado\": 0, \"periodo_id\": 9, \"presentado\": 0, \"tipo_formulario_id\": 3}', NULL, '2025-08-18 23:55:58'),
(69, NULL, 'CREAR_PRESENTACION', 'presentaciones', 49, '{\"pagado\": 1, \"periodo_id\": 9, \"presentado\": 1, \"tipo_formulario_id\": 4}', NULL, '2025-08-18 23:55:58'),
(70, NULL, 'CREAR_PRESENTACION', 'presentaciones', 50, '{\"pagado\": 0, \"periodo_id\": 9, \"presentado\": 0, \"tipo_formulario_id\": 5}', NULL, '2025-08-18 23:55:58'),
(71, NULL, 'CREAR_PRESENTACION', 'presentaciones', 51, '{\"pagado\": 1, \"periodo_id\": 10, \"presentado\": 1, \"tipo_formulario_id\": 1}', NULL, '2025-08-18 23:55:58'),
(72, NULL, 'CREAR_PRESENTACION', 'presentaciones', 52, '{\"pagado\": 0, \"periodo_id\": 10, \"presentado\": 1, \"tipo_formulario_id\": 2}', NULL, '2025-08-18 23:55:58'),
(73, NULL, 'CREAR_PRESENTACION', 'presentaciones', 53, '{\"pagado\": 0, \"periodo_id\": 10, \"presentado\": 0, \"tipo_formulario_id\": 3}', NULL, '2025-08-18 23:55:58'),
(74, NULL, 'CREAR_PRESENTACION', 'presentaciones', 54, '{\"pagado\": 0, \"periodo_id\": 10, \"presentado\": 1, \"tipo_formulario_id\": 4}', NULL, '2025-08-18 23:55:58'),
(75, NULL, 'CREAR_PRESENTACION', 'presentaciones', 55, '{\"pagado\": 1, \"periodo_id\": 10, \"presentado\": 1, \"tipo_formulario_id\": 5}', NULL, '2025-08-18 23:55:58'),
(76, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V.\"}}', NULL, '2025-08-20 23:49:28'),
(77, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 2, '{\"antes\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}}', NULL, '2025-08-20 23:49:28'),
(78, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 3, '{\"antes\": {\"nit\": \"0614-100303-003-2\", \"nrc\": \"20003-3\", \"nombre\": \"Cobalto Foods S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100303-003-2\", \"nrc\": \"20003-3\", \"nombre\": \"Cobalto Foods S.A. de C.V.\"}}', NULL, '2025-08-20 23:49:28'),
(79, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 4, '{\"antes\": {\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}}', NULL, '2025-08-20 23:49:28'),
(80, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 5, '{\"antes\": {\"nit\": \"0614-100505-005-4\", \"nrc\": \"20005-5\", \"nombre\": \"Fénix Agro S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100505-005-4\", \"nrc\": \"20005-5\", \"nombre\": \"Fénix Agro S.A. de C.V.\"}}', NULL, '2025-08-20 23:49:28'),
(81, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 6, '{\"antes\": {\"nit\": \"0614-100606-006-5\", \"nrc\": \"20006-6\", \"nombre\": \"Galaxia Media S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100606-006-5\", \"nrc\": \"20006-6\", \"nombre\": \"Galaxia Media S.A. de C.V.\"}}', NULL, '2025-08-20 23:49:28'),
(82, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 7, '{\"antes\": {\"nit\": \"0614-100707-007-6\", \"nrc\": \"20007-7\", \"nombre\": \"Horizonte Construcciones S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100707-007-6\", \"nrc\": \"20007-7\", \"nombre\": \"Horizonte Construcciones S.A. de C.V.\"}}', NULL, '2025-08-20 23:49:28'),
(83, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 8, '{\"antes\": {\"nit\": \"0614-100808-008-7\", \"nrc\": \"20008-8\", \"nombre\": \"Ícaro Travel S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100808-008-7\", \"nrc\": \"20008-8\", \"nombre\": \"Ícaro Travel S.A. de C.V.\"}}', NULL, '2025-08-20 23:49:28'),
(84, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 9, '{\"antes\": {\"nit\": \"0614-100909-009-8\", \"nrc\": \"20009-9\", \"nombre\": \"Jaguar Security S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100909-009-8\", \"nrc\": \"20009-9\", \"nombre\": \"Jaguar Security S.A. de C.V.\"}}', NULL, '2025-08-20 23:49:28'),
(85, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-08-21 00:46:17'),
(86, NULL, 'CREAR_CLIENTE', 'clientes', 12, '{\"nit\": \"01702630\", \"nrc\": \"25326326\", \"nombre\": \"Juan\"}', NULL, '2025-08-29 20:52:31'),
(87, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 12, '{\"antes\": {\"nit\": \"01702630\", \"nrc\": \"25326326\", \"nombre\": \"Juan\"}, \"despues\": {\"nit\": \"01702630\", \"nrc\": \"25326326\", \"nombre\": \"Juan\"}}', NULL, '2025-08-29 21:35:40'),
(88, NULL, 'CREAR_CLIENTE', 'clientes', 13, '{\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}', NULL, '2025-08-29 21:45:42'),
(89, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 5, '{\"antes\": {\"nit\": \"0614-100505-005-4\", \"nrc\": \"20005-5\", \"nombre\": \"Fénix Agro S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100505-005-4\", \"nrc\": \"20005-5\", \"nombre\": \"Fénix Agro S.A. de C.V.\"}}', NULL, '2025-08-29 22:15:08'),
(90, NULL, 'CREAR_CLIENTE', 'clientes', 14, '{\"nit\": \"017026309\", \"nrc\": \"253263269\", \"nombre\": \"Julio\"}', NULL, '2025-08-29 22:52:24'),
(91, NULL, 'CREAR_CLIENTE', 'clientes', 15, '{\"nit\": \"017026360\", \"nrc\": \"253263269\", \"nombre\": \"Julio\"}', NULL, '2025-08-29 23:01:49'),
(92, NULL, 'CREAR_CLIENTE', 'clientes', 16, '{\"nit\": \"01702630000\", \"nrc\": \"25326326\", \"nombre\": \"Julio\"}', NULL, '2025-08-29 23:05:51'),
(93, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-08-29 23:19:23'),
(94, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-08-29 23:19:24'),
(95, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 10, '{\"antes\": {\"nit\": \"0614-101010-010-9\", \"nrc\": \"20010-0\", \"nombre\": \"Kappa Servicios S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-101010-010-9\", \"nrc\": \"20010-0\", \"nombre\": \"Kappa Servicios S.A. de C.V.\"}}', NULL, '2025-09-01 21:29:35'),
(96, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 10, '{\"antes\": {\"nit\": \"0614-101010-010-9\", \"nrc\": \"20010-0\", \"nombre\": \"Kappa Servicios S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-101010-010-9\", \"nrc\": \"20010-0\", \"nombre\": \"Kappa Servicios S.A. de C.V.\"}}', NULL, '2025-09-01 21:29:35'),
(97, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 14, '{\"antes\": {\"nit\": \"017026309\", \"nrc\": \"253263269\", \"nombre\": \"Julio\"}, \"despues\": {\"nit\": \"017026309\", \"nrc\": \"253263269\", \"nombre\": \"Julio\"}}', NULL, '2025-09-01 21:47:50'),
(98, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 14, '{\"antes\": {\"nit\": \"017026309\", \"nrc\": \"253263269\", \"nombre\": \"Julio\"}, \"despues\": {\"nit\": \"017026309\", \"nrc\": \"253263269\", \"nombre\": \"Julio\"}}', NULL, '2025-09-01 21:47:50'),
(99, NULL, 'CREAR_CLIENTE', 'clientes', 17, '{\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}', NULL, '2025-09-01 21:49:31'),
(100, NULL, 'CREAR_CLIENTE', 'clientes', 18, '{\"nit\": \"0170263000077\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo j\"}', NULL, '2025-09-01 21:50:17'),
(101, NULL, 'CREAR_CLIENTE', 'clientes', 19, '{\"nit\": \"01702630000773\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo ja\"}', NULL, '2025-09-01 21:57:20'),
(102, NULL, 'CREAR_CLIENTE', 'clientes', 20, '{\"nit\": \"017026300007733\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo jac\"}', NULL, '2025-09-01 22:04:30'),
(103, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 17, '{\"antes\": {\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}, \"despues\": {\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}}', NULL, '2025-09-01 22:21:06'),
(104, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 17, '{\"antes\": {\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}, \"despues\": {\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}}', NULL, '2025-09-01 22:21:06'),
(105, NULL, 'CREAR_CLIENTE', 'clientes', 21, '{\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}', NULL, '2025-09-01 22:22:59'),
(106, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 21, '{\"antes\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}, \"despues\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}}', NULL, '2025-09-01 22:24:20'),
(107, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 21, '{\"antes\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}, \"despues\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}}', NULL, '2025-09-01 22:24:21'),
(108, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 21, '{\"antes\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}, \"despues\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}}', NULL, '2025-09-01 23:10:04'),
(109, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 21, '{\"antes\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}, \"despues\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}}', NULL, '2025-09-01 23:10:20'),
(110, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 21, '{\"antes\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}, \"despues\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}}', NULL, '2025-09-01 23:10:28'),
(111, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 21, '{\"antes\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}, \"despues\": {\"nit\": \"0170263000077333\", \"nrc\": \"253263263\", \"nombre\": \"Rodrigo jaci\"}}', NULL, '2025-09-01 23:11:21'),
(112, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 3, '{\"antes\": {\"nit\": \"0614-100303-003-2\", \"nrc\": \"20003-3\", \"nombre\": \"Cobalto Foods S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100303-003-2\", \"nrc\": \"20003-3\", \"nombre\": \"Cobalto Foods S.A. de C.V.\"}}', NULL, '2025-09-01 23:11:56'),
(113, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 2, '{\"antes\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}}', NULL, '2025-09-01 23:12:57'),
(114, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 2, '{\"antes\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}}', NULL, '2025-09-01 23:29:07'),
(115, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-01 23:29:32'),
(116, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-01 23:29:54'),
(117, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-01 23:30:21'),
(118, 2, 'ACTUALIZAR_USUARIO', 'usuarios', 2, '{\"antes\": {\"email\": \"jrsanchezjacinto72@gmail.com\", \"activo\": 1, \"nombre\": \"Julio Rodrigo Sanchez Jacinto\"}, \"despues\": {\"email\": \"jrsanchezjacinto72@gmail.com\", \"activo\": 1, \"nombre\": \"Julio Rodrigo Sanchez Jacintoo\"}}', NULL, '2025-09-03 20:44:55'),
(119, 2, 'ACTUALIZAR_USUARIO', 'usuarios', 2, '{\"nombre\": {\"antes\": \"Julio Rodrigo Sanchez Jacinto\", \"despues\": \"Julio Rodrigo Sanchez Jacintoo\"}}', NULL, '2025-09-03 20:44:55'),
(123, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 19, '{\"antes\": {\"nit\": \"01702630000773\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo ja\"}, \"despues\": {\"nit\": \"01702630000773\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo ja\"}}', NULL, '2025-09-03 20:55:42'),
(125, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 19, '{\"antes\": {\"nit\": \"01702630000773\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo ja\"}, \"despues\": {\"nit\": \"01702630000773\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo ja\"}}', NULL, '2025-09-03 20:56:54'),
(129, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 4, '{\"antes\": {\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}}', NULL, '2025-09-03 21:03:27'),
(131, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 4, '{\"antes\": {\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}}', NULL, '2025-09-03 21:06:15'),
(132, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 4, '{\"antes\": {\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100404-004-3\", \"nrc\": \"20004-4\", \"nombre\": \"Eclipse Retail S.A. de C.V.\"}}', NULL, '2025-09-03 21:06:15'),
(133, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 21:06:25'),
(134, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 21:06:25'),
(135, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 21:06:25'),
(137, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 2, '{\"antes\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}}', NULL, '2025-09-03 21:07:21'),
(139, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 2, '{\"antes\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}}', NULL, '2025-09-03 21:09:12'),
(140, NULL, 'ACTUALIZAR_CLIENTE', 'clientes', 2, '{\"antes\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}, \"despues\": {\"nit\": \"0614-100202-002-1\", \"nrc\": \"20002-2\", \"nombre\": \"Brisa Textiles S.A. de C.V.\"}}', NULL, '2025-09-03 21:09:12'),
(142, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 21:54:51'),
(145, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 22:03:28'),
(147, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 22:04:25'),
(148, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 22:04:25'),
(149, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 22:04:28'),
(150, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 22:04:28'),
(151, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 22:04:43'),
(152, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 22:04:43'),
(153, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 1, '{\"antes\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}, \"despues\": {\"nit\": \"0614-100101-001-0\", \"nrc\": \"20001-1\", \"nombre\": \"Aurora Logistics S.A. de C.V. Prueba\"}}', NULL, '2025-09-03 22:04:43'),
(154, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 12, '{\"antes\": {\"nit\": \"01702630\", \"nrc\": \"25326326\", \"nombre\": \"Juan\"}, \"despues\": {\"nit\": \"01702630\", \"nrc\": \"25326326\", \"nombre\": \"Juan Guarnizo\"}}', NULL, '2025-09-03 22:16:30'),
(155, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 12, '{\"antes\": {\"nit\": \"01702630\", \"nrc\": \"25326326\", \"nombre\": \"Juan Guarnizo\"}, \"despues\": {\"nit\": \"01702630\", \"nrc\": \"25326326\", \"nombre\": \"Juan Guarnizo\"}}', NULL, '2025-09-03 22:16:31'),
(156, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 12, '{\"antes\": {\"nit\": \"01702630\", \"nrc\": \"25326326\", \"nombre\": \"Juan Guarnizo\"}, \"despues\": {\"nit\": \"01702630\", \"nrc\": \"25326326\", \"nombre\": \"Juan Guarnizo\"}}', NULL, '2025-09-03 22:16:31'),
(157, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 17, '{\"antes\": {\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}, \"despues\": {\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}}', NULL, '2025-09-03 22:28:32'),
(158, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 17, '{\"antes\": {\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}, \"despues\": {\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}}', NULL, '2025-09-03 22:28:42'),
(159, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 17, '{\"antes\": {\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}, \"despues\": {\"nit\": \"017026300007\", \"nrc\": \"25326326\", \"nombre\": \"Rodrigo\"}}', NULL, '2025-09-03 22:28:42'),
(160, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 16, '{\"antes\": {\"nit\": \"01702630000\", \"nrc\": \"25326326\", \"nombre\": \"Julio\"}, \"despues\": {\"nit\": \"01702630000\", \"nrc\": \"25326326\", \"nombre\": \"TELETTON\"}}', NULL, '2025-09-03 22:29:21'),
(161, 2, 'ACTUALIZAR_CLIENTE', 'clientes', 16, '{\"antes\": {\"nit\": \"01702630000\", \"nrc\": \"25326326\", \"nombre\": \"TELETTON\"}, \"despues\": {\"nit\": \"01702630000\", \"nrc\": \"25326326\", \"nombre\": \"TELETTONÑAÑÑA\"}}', NULL, '2025-09-03 22:29:33'),
(162, 3, 'ACTUALIZAR_CLIENTE', 'clientes', 13, '{\"antes\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}, \"despues\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}}', NULL, '2025-09-16 23:00:50'),
(163, 3, 'ACTUALIZAR_CLIENTE', 'clientes', 13, '{\"antes\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}, \"despues\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}}', NULL, '2025-09-16 23:00:51'),
(164, 3, 'ACTUALIZAR_CLIENTE', 'clientes', 13, '{\"antes\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}, \"despues\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}}', NULL, '2025-09-16 23:00:51'),
(165, 3, 'ACTUALIZAR_CLIENTE', 'clientes', 13, '{\"antes\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}, \"despues\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}}', NULL, '2025-09-16 23:10:11'),
(166, 3, 'ACTUALIZAR_CLIENTE', 'clientes', 13, '{\"antes\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}, \"despues\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}}', NULL, '2025-09-16 23:10:11'),
(167, 3, 'ACTUALIZAR_CLIENTE', 'clientes', 13, '{\"antes\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}, \"despues\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}}', NULL, '2025-09-16 23:10:11'),
(168, 3, 'ACTUALIZAR_CLIENTE', 'clientes', 13, '{\"antes\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}, \"despues\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}}', NULL, '2025-09-16 23:12:49'),
(169, 3, 'ACTUALIZAR_CLIENTE', 'clientes', 13, '{\"antes\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}, \"despues\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}}', NULL, '2025-09-16 23:12:49'),
(170, 3, 'ACTUALIZAR_CLIENTE', 'clientes', 13, '{\"antes\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}, \"despues\": {\"nit\": \"017026300\", \"nrc\": \"253263260\", \"nombre\": \"tommy\"}}', NULL, '2025-09-16 23:12:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `nombre` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nit` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nrc` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clave_hacienda` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clave_planilla` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `declaracion_iva` enum('documento pendiente','pendiente de procesar','en proceso','presentada','pagada') COLLATE utf8mb4_unicode_ci DEFAULT 'documento pendiente',
  `declaracion_pa` enum('documento pendiente','pendiente de procesar','en proceso','presentada','pagada') COLLATE utf8mb4_unicode_ci DEFAULT 'documento pendiente',
  `declaracion_planilla` enum('documento pendiente','pendiente de procesar','en proceso','presentada','pagada') COLLATE utf8mb4_unicode_ci DEFAULT 'documento pendiente',
  `declaracion_contabilidad` enum('documento pendiente','pendiente de procesar','en proceso','presentada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente de procesar',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_clientes_ident` (`nit`,`nrc`),
  KEY `idx_clientes_nombre` (`nombre`),
  KEY `idx_declaracion_iva` (`declaracion_iva`),
  KEY `idx_declaracion_pa` (`declaracion_pa`),
  KEY `idx_declaracion_planilla` (`declaracion_planilla`),
  KEY `idx_declaracion_contabilidad` (`declaracion_contabilidad`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `nit`, `nrc`, `contacto`, `telefono`, `email`, `clave_hacienda`, `clave_planilla`, `contador`, `direccion`, `activo`, `creado_en`, `actualizado_en`, `declaracion_iva`, `declaracion_pa`, `declaracion_planilla`, `declaracion_contabilidad`) VALUES
(1, 'Aurora Logistics S.A. de C.V. Prueba', '0614-100101-001-0', '20001-1', 'Ana Peron', '+503 2222 1111', 'contacto@auroralog.com', 'AH-AUR-2025', 'PL-AUR-012', 'María Torres', 'San Salvador, Escalón', 1, '2025-08-18 23:39:07', '2025-09-03 22:04:43', 'pagada', 'pagada', 'documento pendiente', 'presentada'),
(2, 'Brisa Textiles S.A. de C.V.', '0614-100202-002-1', '20002-2', 'Bruno Díaz', '+503 2222 2002', 'admin@brisatex.com', 'AH-BRI-2025', 'PL-BRI-02', 'Luis Herrera', 'San Salvador, San Benito', 1, '2025-08-18 23:39:07', '2025-09-03 21:09:12', 'documento pendiente', 'en proceso', 'pagada', 'presentada'),
(3, 'Cobalto Foods S.A. de C.V.', '0614-100303-003-2', '20003-3', 'Carla Ramos', '+503 2222 3003', 'finanzas@cobaltofoods.com', 'AH-COB-2025', 'PL-COB-03', 'Carolina Gómez', 'Santa Tecla, La Libertad', 1, '2025-08-18 23:39:07', '2025-09-01 23:11:56', 'en proceso', 'documento pendiente', 'documento pendiente', 'pendiente de procesar'),
(4, 'Eclipse Retail S.A. de C.V.', '0614-100404-004-3', '20004-4', 'Eduardo Molina', '+503 2222 4004', 'contacto@eclipseretail.com', 'AH-ECL-2025', 'PL-ECL-04', 'Eduardo Rivera', 'Antiguo Cuscatlán', 1, '2025-08-18 23:39:07', '2025-09-03 21:06:15', 'documento pendiente', 'documento pendiente', 'documento pendiente', 'presentada'),
(5, 'Fénix Agro S.A. de C.V.', '0614-100505-005-4', '20005-5', 'Fernanda Cruz', '+503 2222 5005', 'info@fenixagro.com', 'AH-FEN-2025', 'PL-FEN-05', 'Fernanda Soto', 'Soyapango', 1, '2025-08-18 23:39:07', '2025-08-29 22:15:08', 'documento pendiente', 'documento pendiente', 'documento pendiente', 'pendiente de procesar'),
(6, 'Galaxia Media S.A. de C.V.', '0614-100606-006-5', '20006-6', 'Gabriela Soto', '+503 2222 6006', 'contacto@galaxiamedia.com', 'AH-GAL-2025', 'PL-GAL-06', 'Gabriela Núñez', 'Santa Tecla', 1, '2025-08-18 23:39:07', '2025-08-20 23:49:28', 'documento pendiente', 'documento pendiente', 'documento pendiente', 'pendiente de procesar'),
(7, 'Horizonte Construcciones S.A. de C.V.', '0614-100707-007-6', '20007-7', 'Héctor Pineda', '+503 2222 7007', 'proyectos@horizonte.com', 'AH-HOR-2025', 'PL-HOR-07', 'Héctor Ramos', 'San Miguel', 1, '2025-08-18 23:39:07', '2025-08-20 23:49:28', 'documento pendiente', 'documento pendiente', 'documento pendiente', 'pendiente de procesar'),
(8, 'Ícaro Travel S.A. de C.V.', '0614-100808-008-7', '20008-8', 'Irene Lazo', '+503 2222 8008', 'ventas@icarotravel.com', 'AH-ICA-2025', 'PL-ICA-08', 'Irene Morales', 'La Libertad', 1, '2025-08-18 23:39:07', '2025-08-20 23:49:28', 'documento pendiente', 'documento pendiente', 'documento pendiente', 'pendiente de procesar'),
(9, 'Jaguar Security S.A. de C.V.', '0614-100909-009-8', '20009-9', 'Javier Campos', '+503 2222 9009', 'operaciones@jaguarsec.com', 'AH-JAG-2025', 'PL-JAG-09', 'Javier Pineda', 'San Salvador, Centro', 1, '2025-08-18 23:39:07', '2025-08-20 23:49:28', 'documento pendiente', 'documento pendiente', 'documento pendiente', 'pendiente de procesar'),
(10, 'Kappa Servicios S.A. de C.V.', '0614-101010-010-9', '20010-0', 'Karla Mejía', '+503 2222 1010', 'soporte@kappasv.com', '', '', '', 'Mejicanos', 1, '2025-08-18 23:39:07', '2025-09-01 21:29:35', 'pagada', 'documento pendiente', 'documento pendiente', 'pendiente de procesar'),
(12, 'Juan Guarnizo', '01702630', '25326326', 'messi', '75747039', 'jr@gmail.com', '20170293', '20170291', 'Jose', 'Calle', 1, '2025-08-29 20:52:31', '2025-09-03 22:16:30', 'documento pendiente', 'documento pendiente', 'documento pendiente', 'pendiente de procesar'),
(13, 'tommy', '017026300', '253263260', 'messi', '75747039', 'jr@gmail.com', '20170295', '20170295', 'Jose', 'Colonia Zacamil', 1, '2025-08-29 21:45:42', '2025-09-16 23:12:49', 'documento pendiente', 'documento pendiente', 'documento pendiente', 'presentada'),
(14, 'Julio', '017026309', '253263269', 'messi', '75747039', 'jr@gmail.com', '20170293', '20170293', 'Jose', 'Calle', 1, '2025-08-29 22:52:24', '2025-09-01 21:47:50', 'presentada', 'presentada', 'pagada', 'presentada'),
(15, 'Julio', '017026360', '253263269', 'messi', '75747039', 'jr@gmail.com', '20354982', '20354982', 'Jose', 'Calle', 1, '2025-08-29 23:01:49', NULL, 'presentada', 'presentada', 'presentada', 'en proceso'),
(16, 'TELETTONÑAÑÑA', '01702630000', '25326326', 'messi', '75747039', 'jr@gmail.com', '2', '2', 'Jose', 'Calle', 1, '2025-08-29 23:05:51', '2025-09-03 22:29:33', 'documento pendiente', 'documento pendiente', 'documento pendiente', ''),
(17, 'Rodrigo', '017026300007', '25326326', 'messi', '75747039', 'jr@gmail.com', '12345678', '123456789', 'Jose Jose', 'Colonia Zacamil', 1, '2025-09-01 21:49:31', '2025-09-03 22:28:32', 'pagada', 'pagada', 'pagada', 'presentada'),
(18, 'Rodrigo j', '0170263000077', '25326326', 'messi', '75747039', 'jr@gmail.com', '17171', '171717', 'Jose', 'Colonia Zacamil', 1, '2025-09-01 21:50:17', NULL, 'documento pendiente', 'documento pendiente', 'documento pendiente', ''),
(19, 'Rodrigo ja', '01702630000773', '25326326', 'messi', '75747039', 'jr@gmail.com', '2', '2', 'Jose', '', 1, '2025-09-01 21:57:20', NULL, 'documento pendiente', 'documento pendiente', 'documento pendiente', ''),
(20, 'Rodrigo jac', '017026300007733', '25326326', 'messi', '75747039', 'jr@gmail.com', '30', '30', 'Jose', 'Colonia Zacamil', 1, '2025-09-01 22:04:30', NULL, 'documento pendiente', 'documento pendiente', 'documento pendiente', 'documento pendiente'),
(21, 'Rodrigo jaci', '0170263000077333', '253263263', 'Tommy', '75747039', 'jr72@gmail.com', '12', '12', 'Jose', '', 1, '2025-09-01 22:22:59', '2025-09-01 23:11:21', 'en proceso', 'presentada', 'pagada', 'documento pendiente');

--
-- Disparadores `clientes`
--
DROP TRIGGER IF EXISTS `trg_clientes_ai`;
DELIMITER $$
CREATE TRIGGER `trg_clientes_ai` AFTER INSERT ON `clientes` FOR EACH ROW BEGIN
  INSERT INTO bitacora_actividad (usuario_id, accion, entidad, entidad_id, detalle)
  VALUES (NULLIF(@current_user_id,0), 'CREAR_CLIENTE', 'clientes', NEW.id,
          JSON_OBJECT('nombre', NEW.nombre, 'nit', NEW.nit, 'nrc', NEW.nrc));
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_clientes_au`;
DELIMITER $$
CREATE TRIGGER `trg_clientes_au` AFTER UPDATE ON `clientes` FOR EACH ROW BEGIN
  INSERT INTO bitacora_actividad (usuario_id, accion, entidad, entidad_id, detalle)
  VALUES (NULLIF(@current_user_id,0), 'ACTUALIZAR_CLIENTE', 'clientes', NEW.id,
          JSON_OBJECT(
            'antes',   JSON_OBJECT('nombre', OLD.nombre, 'nit', OLD.nit, 'nrc', OLD.nrc),
            'despues', JSON_OBJECT('nombre', NEW.nombre, 'nit', NEW.nit, 'nrc', NEW.nrc)
          ));
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_clientes_auditoria`;
DELIMITER $$
CREATE TRIGGER `trg_clientes_auditoria` AFTER UPDATE ON `clientes` FOR EACH ROW BEGIN
  DECLARE v_usuario_id BIGINT;
  
  -- Obtener el usuario actual de la variable de sesión
  SET v_usuario_id = NULLIF(@current_user_id, 0);
  
  -- Verificar si el usuario existe en la tabla usuarios
  IF v_usuario_id IS NOT NULL THEN
    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = v_usuario_id) THEN
      SET v_usuario_id = NULL;
    END IF;
  END IF;
  
  -- Si no hay usuario válido, usar usuario "Sistema" (ID 1)
  IF v_usuario_id IS NULL THEN
    SET v_usuario_id = 1; -- ID del usuario Sistema
  END IF;

  -- Si cambia el estado de IVA
  IF (OLD.declaracion_iva <> NEW.declaracion_iva) THEN
    INSERT INTO auditoria (usuario_id, accion, modulo, detalle)
    VALUES (v_usuario_id, 'CAMBIO_ESTADO', 'IVA',
            JSON_OBJECT(
              'cliente_id', NEW.id,
              'cliente_nombre', NEW.nombre,
              'antes', OLD.declaracion_iva, 
              'despues', NEW.declaracion_iva
            ));
  END IF;

  -- Si cambia el estado de PA
  IF (OLD.declaracion_pa <> NEW.declaracion_pa) THEN
    INSERT INTO auditoria (usuario_id, accion, modulo, detalle)
    VALUES (v_usuario_id, 'CAMBIO_ESTADO', 'PA',
            JSON_OBJECT(
              'cliente_id', NEW.id,
              'cliente_nombre', NEW.nombre,
              'antes', OLD.declaracion_pa, 
              'despues', NEW.declaracion_pa
            ));
  END IF;

  -- Si cambia el estado de PLANILLA
  IF (OLD.declaracion_planilla <> NEW.declaracion_planilla) THEN
    INSERT INTO auditoria (usuario_id, accion, modulo, detalle)
    VALUES (v_usuario_id, 'CAMBIO_ESTADO', 'PLANILLA',
            JSON_OBJECT(
              'cliente_id', NEW.id,
              'cliente_nombre', NEW.nombre,
              'antes', OLD.declaracion_planilla, 
              'despues', NEW.declaracion_planilla
            ));
  END IF;

  -- Si cambia el estado de CONTABILIDAD
  IF (OLD.declaracion_contabilidad <> NEW.declaracion_contabilidad) THEN
    INSERT INTO auditoria (usuario_id, accion, modulo, detalle)
    VALUES (v_usuario_id, 'CAMBIO_ESTADO', 'CONTABILIDAD',
            JSON_OBJECT(
              'cliente_id', NEW.id,
              'cliente_nombre', NEW.nombre,
              'antes', OLD.declaracion_contabilidad, 
              'despues', NEW.declaracion_contabilidad
            ));
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_clientes_protect_delete`;
DELIMITER $$
CREATE TRIGGER `trg_clientes_protect_delete` BEFORE DELETE ON `clientes` FOR EACH ROW BEGIN
  IF EXISTS (SELECT 1 FROM periodos WHERE cliente_id = OLD.id LIMIT 1) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No puede eliminarse el cliente: tiene datos asociados (periodos/presentaciones).';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `credenciales_cliente`
--

DROP TABLE IF EXISTS `credenciales_cliente`;
CREATE TABLE IF NOT EXISTS `credenciales_cliente` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `cliente_id` bigint NOT NULL,
  `servicio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_servicio` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secreto` varbinary(1024) DEFAULT NULL,
  `notas` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cred` (`cliente_id`,`servicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `periodos`
--

DROP TABLE IF EXISTS `periodos`;
CREATE TABLE IF NOT EXISTS `periodos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `cliente_id` bigint NOT NULL,
  `anio` smallint NOT NULL,
  `mes` tinyint NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_periodo` (`cliente_id`,`anio`,`mes`),
  KEY `idx_periodo_anio_mes` (`anio`,`mes`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `periodos`
--

INSERT INTO `periodos` (`id`, `cliente_id`, `anio`, `mes`, `creado_en`) VALUES
(1, 1, 2025, 7, '2025-08-18 23:39:07'),
(2, 2, 2025, 7, '2025-08-18 23:39:07'),
(3, 3, 2025, 7, '2025-08-18 23:39:07'),
(4, 4, 2025, 7, '2025-08-18 23:39:07'),
(5, 5, 2025, 7, '2025-08-18 23:39:07'),
(6, 6, 2025, 7, '2025-08-18 23:39:07'),
(7, 7, 2025, 7, '2025-08-18 23:39:07'),
(8, 8, 2025, 7, '2025-08-18 23:39:07'),
(9, 9, 2025, 7, '2025-08-18 23:39:07'),
(10, 10, 2025, 7, '2025-08-18 23:39:07'),
(11, 12, 2025, 8, '2025-08-29 21:06:13'),
(12, 13, 2025, 8, '2025-08-29 21:45:42'),
(13, 14, 2025, 8, '2025-08-29 22:52:24'),
(14, 15, 2025, 8, '2025-08-29 23:01:49'),
(15, 16, 2025, 8, '2025-08-29 23:05:51'),
(16, 17, 2025, 9, '2025-09-01 21:49:31'),
(17, 18, 2025, 9, '2025-09-01 21:50:17'),
(18, 19, 2025, 9, '2025-09-01 21:57:20'),
(19, 20, 2025, 9, '2025-09-01 22:04:30'),
(20, 21, 2025, 9, '2025-09-01 22:22:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `presentaciones`
--

DROP TABLE IF EXISTS `presentaciones`;
CREATE TABLE IF NOT EXISTS `presentaciones` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `periodo_id` bigint NOT NULL,
  `tipo_formulario_id` smallint NOT NULL,
  `presentado` tinyint(1) NOT NULL DEFAULT '0',
  `fecha_presentacion` date DEFAULT NULL,
  `presentado_por` bigint DEFAULT NULL,
  `pagado` tinyint(1) NOT NULL DEFAULT '0',
  `fecha_pago` date DEFAULT NULL,
  `comprobante_pago` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_presentacion` (`periodo_id`,`tipo_formulario_id`),
  KEY `idx_pres_flags_fecha` (`presentado`,`pagado`,`fecha_presentacion`,`fecha_pago`),
  KEY `fk_pres_tipo` (`tipo_formulario_id`),
  KEY `fk_pres_usuario` (`presentado_por`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `presentaciones`
--

INSERT INTO `presentaciones` (`id`, `periodo_id`, `tipo_formulario_id`, `presentado`, `fecha_presentacion`, `presentado_por`, `pagado`, `fecha_pago`, `comprobante_pago`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 1, 1, '2025-07-05', NULL, 1, '2025-07-07', 'AUR-IVA-0725', 'IVA pagado en línea', '2025-08-18 23:39:07', NULL),
(2, 1, 2, 1, '2025-07-12', NULL, 0, NULL, NULL, 'RENTA presentada, pendiente de pago', '2025-08-18 23:39:07', NULL),
(3, 1, 3, 0, NULL, NULL, 0, NULL, NULL, 'PA pendiente por documentación', '2025-08-18 23:39:07', NULL),
(4, 1, 4, 1, '2025-07-20', NULL, 0, NULL, NULL, 'Planilla cerrada, en cola de pago', '2025-08-18 23:39:07', NULL),
(5, 1, 5, 1, '2025-07-22', NULL, 1, '2025-07-25', 'AUR-CON-0725', 'Contabilidad pagada', '2025-08-18 23:39:07', NULL),
(11, 2, 1, 1, '2025-07-08', NULL, 0, NULL, NULL, 'IVA presentado; falta pago', '2025-08-18 23:55:58', NULL),
(12, 2, 2, 0, NULL, NULL, 0, NULL, NULL, 'RENTA pendiente de revisión interna', '2025-08-18 23:55:58', NULL),
(13, 2, 3, 1, '2025-07-06', NULL, 1, '2025-07-09', 'BRI-PA-0725', 'PA pagada en banco', '2025-08-18 23:55:58', NULL),
(14, 2, 4, 0, NULL, NULL, 0, NULL, NULL, 'Planilla pendiente de nómina', '2025-08-18 23:55:58', NULL),
(15, 2, 5, 1, '2025-07-18', NULL, 0, NULL, NULL, 'Contab presentada; conciliando bancos', '2025-08-18 23:55:58', NULL),
(16, 3, 1, 0, NULL, NULL, 0, NULL, NULL, 'IVA pendiente por facturas', '2025-08-18 23:55:58', NULL),
(17, 3, 2, 1, '2025-07-09', NULL, 1, '2025-07-11', 'COB-REN-0725', 'RENTA pagada', '2025-08-18 23:55:58', NULL),
(18, 3, 3, 1, '2025-07-16', NULL, 0, NULL, NULL, 'PA presentado; en validación', '2025-08-18 23:55:58', NULL),
(19, 3, 4, 1, '2025-07-19', NULL, 1, '2025-07-21', 'COB-PLA-0725', 'Planilla pagada', '2025-08-18 23:55:58', NULL),
(20, 3, 5, 0, NULL, NULL, 0, NULL, NULL, 'Contab pendiente de cierre', '2025-08-18 23:55:58', NULL),
(21, 4, 1, 1, '2025-07-04', NULL, 1, '2025-07-06', 'ECL-IVA-0725', 'IVA pagado temprano', '2025-08-18 23:55:58', NULL),
(22, 4, 2, 1, '2025-07-13', NULL, 1, '2025-07-15', 'ECL-REN-0725', 'RENTA cancelada', '2025-08-18 23:55:58', NULL),
(23, 4, 3, 0, NULL, NULL, 0, NULL, NULL, 'PA pendiente por anexos', '2025-08-18 23:55:58', NULL),
(24, 4, 4, 1, '2025-07-21', NULL, 0, NULL, NULL, 'Planilla presentada', '2025-08-18 23:55:58', NULL),
(25, 4, 5, 1, '2025-07-27', NULL, 0, NULL, NULL, 'Contab en validación', '2025-08-18 23:55:58', NULL),
(26, 5, 1, 1, '2025-07-11', NULL, 0, NULL, NULL, 'IVA presentado; espera pago', '2025-08-18 23:55:58', NULL),
(27, 5, 2, 1, '2025-07-17', NULL, 0, NULL, NULL, 'RENTA presentada; control interno', '2025-08-18 23:55:58', NULL),
(28, 5, 3, 1, '2025-07-09', NULL, 1, '2025-07-10', 'FEN-PA-0725', 'PA pagada', '2025-08-18 23:55:58', NULL),
(29, 5, 4, 0, NULL, NULL, 0, NULL, NULL, 'Planilla pendiente de nómina', '2025-08-18 23:55:58', NULL),
(30, 5, 5, 0, NULL, NULL, 0, NULL, NULL, 'Contab pendiente documentación', '2025-08-18 23:55:58', NULL),
(31, 6, 1, 0, NULL, NULL, 0, NULL, NULL, 'IVA sin anexos aún', '2025-08-18 23:55:58', NULL),
(32, 6, 2, 0, NULL, NULL, 0, NULL, NULL, 'RENTA en preparación', '2025-08-18 23:55:58', NULL),
(33, 6, 3, 1, '2025-07-14', NULL, 0, NULL, NULL, 'PA presentada', '2025-08-18 23:55:58', NULL),
(34, 6, 4, 1, '2025-07-23', NULL, 0, NULL, NULL, 'Planilla presentada; valida RRHH', '2025-08-18 23:55:58', NULL),
(35, 6, 5, 1, '2025-07-24', NULL, 1, '2025-07-26', 'GAL-CON-0725', 'Contab cancelada', '2025-08-18 23:55:58', NULL),
(36, 7, 1, 1, '2025-07-07', NULL, 0, NULL, NULL, 'IVA presentado', '2025-08-18 23:55:58', NULL),
(37, 7, 2, 1, '2025-07-10', NULL, 1, '2025-07-12', 'HOR-REN-0725', 'RENTA pagada', '2025-08-18 23:55:58', NULL),
(38, 7, 3, 0, NULL, NULL, 0, NULL, NULL, 'PA pendiente aprobación cliente', '2025-08-18 23:55:58', NULL),
(39, 7, 4, 1, '2025-07-15', NULL, 1, '2025-07-16', 'HOR-PLA-0725', 'Planilla cancelada', '2025-08-18 23:55:58', NULL),
(40, 7, 5, 1, '2025-07-28', NULL, 0, NULL, NULL, 'Contab presentada; falta pago', '2025-08-18 23:55:58', NULL),
(41, 8, 1, 1, '2025-07-03', NULL, 1, '2025-07-05', 'ICA-IVA-0725', 'IVA pagado', '2025-08-18 23:55:58', NULL),
(42, 8, 2, 0, NULL, NULL, 0, NULL, NULL, 'RENTA pendiente', '2025-08-18 23:55:58', NULL),
(43, 8, 3, 1, '2025-07-17', NULL, 0, NULL, NULL, 'PA presentada; espera pago', '2025-08-18 23:55:58', NULL),
(44, 8, 4, 0, NULL, NULL, 0, NULL, NULL, 'Planilla pendiente RRHH', '2025-08-18 23:55:58', NULL),
(45, 8, 5, 1, '2025-07-26', NULL, 1, '2025-07-27', 'ICA-CON-0725', 'Contab pagada', '2025-08-18 23:55:58', NULL),
(46, 9, 1, 1, '2025-07-06', NULL, 0, NULL, NULL, 'IVA presentado', '2025-08-18 23:55:58', NULL),
(47, 9, 2, 1, '2025-07-08', NULL, 0, NULL, NULL, 'RENTA presentada', '2025-08-18 23:55:58', NULL),
(48, 9, 3, 0, NULL, NULL, 0, NULL, NULL, 'PA pendiente firma', '2025-08-18 23:55:58', NULL),
(49, 9, 4, 1, '2025-07-29', NULL, 1, '2025-07-30', 'JAG-PLA-0725', 'Planilla pagada', '2025-08-18 23:55:58', NULL),
(50, 9, 5, 0, NULL, NULL, 0, NULL, NULL, 'Contab pendiente', '2025-08-18 23:55:58', NULL),
(51, 10, 1, 1, '2025-07-02', NULL, 1, '2025-07-03', 'KAP-IVA-0725', 'IVA pagado', '2025-08-18 23:55:58', NULL),
(52, 10, 2, 1, '2025-07-19', NULL, 0, NULL, NULL, 'RENTA presentada; por pagar', '2025-08-18 23:55:58', NULL),
(53, 10, 3, 0, NULL, NULL, 0, NULL, NULL, 'PA pendiente de anexos', '2025-08-18 23:55:58', NULL),
(54, 10, 4, 1, '2025-07-22', NULL, 0, NULL, NULL, 'Planilla presentada; control', '2025-08-18 23:55:58', NULL),
(55, 10, 5, 1, '2025-07-24', NULL, 1, '2025-07-26', 'KAP-CON-0725', 'Contab pagada', '2025-08-18 23:55:58', NULL);

--
-- Disparadores `presentaciones`
--
DROP TRIGGER IF EXISTS `trg_presentaciones_ai`;
DELIMITER $$
CREATE TRIGGER `trg_presentaciones_ai` AFTER INSERT ON `presentaciones` FOR EACH ROW BEGIN
  INSERT INTO bitacora_actividad (usuario_id, accion, entidad, entidad_id, detalle)
  VALUES (NULLIF(@current_user_id,0), 'CREAR_PRESENTACION', 'presentaciones', NEW.id,
          JSON_OBJECT(
            'periodo_id', NEW.periodo_id,
            'tipo_formulario_id', NEW.tipo_formulario_id,
            'presentado', NEW.presentado,
            'pagado', NEW.pagado
          ));
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_presentaciones_au`;
DELIMITER $$
CREATE TRIGGER `trg_presentaciones_au` AFTER UPDATE ON `presentaciones` FOR EACH ROW BEGIN
  INSERT INTO bitacora_actividad (usuario_id, accion, entidad, entidad_id, detalle)
  VALUES (NULLIF(@current_user_id,0), 'ACTUALIZAR_PRESENTACION', 'presentaciones', NEW.id,
          JSON_OBJECT(
            'antes', JSON_OBJECT(
              'presentado', OLD.presentado,
              'pagado', OLD.pagado,
              'fecha_presentacion', OLD.fecha_presentacion,
              'fecha_pago', OLD.fecha_pago
            ),
            'despues', JSON_OBJECT(
              'presentado', NEW.presentado,
              'pagado', NEW.pagado,
              'fecha_presentacion', NEW.fecha_presentacion,
              'fecha_pago', NEW.fecha_pago
            )
          ));
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_presentaciones_presentado_por`;
DELIMITER $$
CREATE TRIGGER `trg_presentaciones_presentado_por` BEFORE UPDATE ON `presentaciones` FOR EACH ROW BEGIN
  -- Completar presentado_por con @current_user_id si viene nulo y se marca presentado
  IF NEW.presentado = 1 AND NEW.presentado_por IS NULL THEN
    SET NEW.presentado_por = NULLIF(@current_user_id,0);
  END IF;

  -- Si se desmarca presentado, limpiar todo lo asociado y bloquear pago
  IF NEW.presentado = 0 THEN
    SET NEW.presentado_por = NULL;
    SET NEW.fecha_presentacion = NULL;
    SET NEW.pagado = 0;
    SET NEW.fecha_pago = NULL;
    SET NEW.comprobante_pago = NULL;
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_presentaciones_state_check`;
DELIMITER $$
CREATE TRIGGER `trg_presentaciones_state_check` BEFORE UPDATE ON `presentaciones` FOR EACH ROW BEGIN
  -- No se puede pagar si no está presentado
  IF NEW.pagado = 1 AND NEW.presentado = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No se puede marcar como PAGADO si no está PRESENTADO.';
  END IF;

  -- Si se marca presentado y no trae fecha, poner hoy
  IF NEW.presentado = 1 AND NEW.fecha_presentacion IS NULL THEN
    SET NEW.fecha_presentacion = CURRENT_DATE;
  END IF;

  -- Si se marca pagado y no trae fecha, poner hoy
  IF NEW.pagado = 1 AND NEW.fecha_pago IS NULL THEN
    SET NEW.fecha_pago = CURRENT_DATE;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_formulario`
--

DROP TABLE IF EXISTS `tipos_formulario`;
CREATE TABLE IF NOT EXISTS `tipos_formulario` (
  `id` smallint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tipos_formulario`
--

INSERT INTO `tipos_formulario` (`id`, `codigo`, `nombre`, `activo`) VALUES
(1, 'IVA', 'Declaración de IVA', 1),
(2, 'RENTA', 'Declaración de Renta', 1),
(3, 'PA', 'Declaración PA', 1),
(4, 'PLANILLA', 'Planilla', 1),
(5, 'CONTAB', 'Contabilidad', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password_hash`, `activo`, `creado_en`, `actualizado_en`) VALUES
(2, 'Julio Rodrigo Sanchez Jacintoo', 'jrsanchezjacinto72@gmail.com', '$2y$10$OAaDeW6w0y.itts1MrVTmes8ClrK6IB9a57JRXRjIQKW5sQ9kBX.e', 1, '2025-09-03 17:29:40', '2025-09-03 20:44:55'),
(3, 'Julio Rodrigo Sánchez Jacinto', 'jr@gmail.com', '$2y$10$KV9aw9IfvEDr6ixbFoJpH.EBI5sAMJEBOvnGrDpFt9SVoYIj/oMI.', 1, '2025-09-03 17:33:31', NULL);

--
-- Disparadores `usuarios`
--
DROP TRIGGER IF EXISTS `trg_usuarios_ad`;
DELIMITER $$
CREATE TRIGGER `trg_usuarios_ad` AFTER DELETE ON `usuarios` FOR EACH ROW BEGIN
  INSERT INTO bitacora_actividad (usuario_id, accion, entidad, entidad_id, detalle)
  VALUES (NULLIF(@current_user_id,0), 'ELIMINAR_USUARIO', 'usuarios', OLD.id,
          JSON_OBJECT(
            'nombre', OLD.nombre,
            'email', OLD.email
          ));
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_usuarios_ai`;
DELIMITER $$
CREATE TRIGGER `trg_usuarios_ai` AFTER INSERT ON `usuarios` FOR EACH ROW BEGIN
  INSERT INTO bitacora_actividad (usuario_id, accion, entidad, entidad_id, detalle)
  VALUES (NEW.id, 'CREAR_USUARIO', 'usuarios', NEW.id,
          JSON_OBJECT(
            'nombre', NEW.nombre,
            'email', NEW.email,
            'activo', NEW.activo
          ));
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_usuarios_au`;
DELIMITER $$
CREATE TRIGGER `trg_usuarios_au` AFTER UPDATE ON `usuarios` FOR EACH ROW BEGIN
  -- Registrar cambios en la bitácora
  INSERT INTO bitacora_actividad (usuario_id, accion, entidad, entidad_id, detalle)
  VALUES (NEW.id, 'ACTUALIZAR_USUARIO', 'usuarios', NEW.id,
          JSON_OBJECT(
            'antes', JSON_OBJECT(
              'nombre', OLD.nombre,
              'email', OLD.email,
              'activo', OLD.activo
            ),
            'despues', JSON_OBJECT(
              'nombre', NEW.nombre,
              'email', NEW.email,
              'activo', NEW.activo
            )
          ));
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_usuarios_au_detallado`;
DELIMITER $$
CREATE TRIGGER `trg_usuarios_au_detallado` AFTER UPDATE ON `usuarios` FOR EACH ROW BEGIN
  DECLARE cambios JSON;
  SET cambios = JSON_OBJECT();
  
  -- Detectar cambios en el nombre
  IF OLD.nombre != NEW.nombre THEN
    SET cambios = JSON_SET(cambios, '$.nombre', JSON_OBJECT('antes', OLD.nombre, 'despues', NEW.nombre));
  END IF;
  
  -- Detectar cambios en el email
  IF OLD.email != NEW.email THEN
    SET cambios = JSON_SET(cambios, '$.email', JSON_OBJECT('antes', OLD.email, 'despues', NEW.email));
  END IF;
  
  -- Detectar cambios en el estado activo
  IF OLD.activo != NEW.activo THEN
    SET cambios = JSON_SET(cambios, '$.activo', JSON_OBJECT('antes', OLD.activo, 'despues', NEW.activo));
  END IF;
  
  -- Solo registrar si hubo cambios reales
  IF JSON_LENGTH(cambios) > 0 THEN
    INSERT INTO bitacora_actividad (usuario_id, accion, entidad, entidad_id, detalle)
    VALUES (NEW.id, 'ACTUALIZAR_USUARIO', 'usuarios', NEW.id, cambios);
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_usuarios_password_change`;
DELIMITER $$
CREATE TRIGGER `trg_usuarios_password_change` AFTER UPDATE ON `usuarios` FOR EACH ROW BEGIN
  -- Verificar si la contraseña cambió
  IF OLD.password_hash != NEW.password_hash THEN
    INSERT INTO bitacora_actividad (usuario_id, accion, entidad, entidad_id, detalle)
    VALUES (NEW.id, 'CAMBIAR_CONTRASENA', 'usuarios', NEW.id,
            JSON_OBJECT(
              'fecha', NOW(),
              'usuario_afectado', NEW.nombre
            ));
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_usuarios_protect_delete`;
DELIMITER $$
CREATE TRIGGER `trg_usuarios_protect_delete` BEFORE DELETE ON `usuarios` FOR EACH ROW BEGIN
  -- Verificar si el usuario tiene asignaciones de clientes
  IF EXISTS (SELECT 1 FROM asignaciones_cliente WHERE usuario_id = OLD.id LIMIT 1) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No puede eliminarse el usuario: tiene clientes asignados.';
  END IF;
  
  -- Verificar si el usuario tiene presentaciones realizadas
  IF EXISTS (SELECT 1 FROM presentaciones WHERE presentado_por = OLD.id LIMIT 1) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No puede eliminarse el usuario: tiene presentaciones realizadas.';
  END IF;
END
$$
DELIMITER ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asignaciones_cliente`
--
ALTER TABLE `asignaciones_cliente`
  ADD CONSTRAINT `fk_asig_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `fk_asig_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `bitacora_actividad`
--
ALTER TABLE `bitacora_actividad`
  ADD CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `credenciales_cliente`
--
ALTER TABLE `credenciales_cliente`
  ADD CONSTRAINT `fk_cred_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
