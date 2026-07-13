-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-07-2026 a las 20:55:24
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `agenda_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_super` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `is_super`) VALUES
(1, 'admin', '$2y$10$EBheycAvhI9JdacAoC.w4O2neLWBBEt31h.sqUxtAFPWjx72NYsx.', 1),
(5, 'GESkritch', '$2y$10$Hak7NNMa/T.60BGDtxVpiOVzygxO0wgBlHbOSnxyR8mPpGHfZeECu', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agendamientos`
--

CREATE TABLE `agendamientos` (
  `id` int(11) NOT NULL,
  `rut` varchar(20) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL,
  `correo` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha` varchar(20) NOT NULL,
  `hora` varchar(10) NOT NULL,
  `tipo_tramite` varchar(100) DEFAULT NULL,
  `estado` varchar(50) NOT NULL DEFAULT 'confirmado',
  `created_at` bigint(20) NOT NULL,
  `cliente_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `agendamientos`
--

INSERT INTO `agendamientos` (`id`, `rut`, `nombre`, `apellido`, `correo`, `telefono`, `fecha`, `hora`, `tipo_tramite`, `estado`, `created_at`, `cliente_id`) VALUES
(3, '20983443', 'Mauri', 'Carrillo', 'a@w.e', '977529739', '2026-07-15', '09:20', 'Renovación', 'atendido', 1783967959, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bloqueos_temporales`
--

CREATE TABLE `bloqueos_temporales` (
  `id` int(11) NOT NULL,
  `rut` varchar(20) DEFAULT NULL,
  `fecha` varchar(20) NOT NULL,
  `hora` varchar(10) NOT NULL,
  `token` varchar(255) DEFAULT NULL,
  `expires_at` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `rut` varchar(20) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL,
  `correo` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `created_at` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `rut`, `nombre`, `apellido`, `correo`, `telefono`, `created_at`) VALUES
(1, '20983443', 'Mauri', 'Carrillo', 'a@w.e', '977529739', 1783959431);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disponibilidad`
--

CREATE TABLE `disponibilidad` (
  `id` int(11) NOT NULL,
  `fecha` varchar(20) NOT NULL,
  `hora_inicio` varchar(10) NOT NULL,
  `hora_fin` varchar(10) NOT NULL,
  `duracion_bloque` int(11) NOT NULL DEFAULT 20,
  `max_cupos` int(11) NOT NULL DEFAULT 30,
  `cupos_ocupados` int(11) NOT NULL DEFAULT 0,
  `tipo` varchar(50) DEFAULT 'Ambos',
  `estado` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `disponibilidad`
--

INSERT INTO `disponibilidad` (`id`, `fecha`, `hora_inicio`, `hora_fin`, `duracion_bloque`, `max_cupos`, `cupos_ocupados`, `tipo`, `estado`) VALUES
(1, '2026-07-20', '08:00', '17:00', 20, 10, 0, 'Ambos', 0),
(2, '2026-07-14', '08:00', '17:00', 20, 30, 0, 'Ambos', 0),
(3, '2026-07-15', '08:00', '17:00', 20, 30, 0, 'Ambos', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personas_listas`
--

CREATE TABLE `personas_listas` (
  `id` int(11) NOT NULL,
  `agendamiento_id` int(11) NOT NULL,
  `rut` varchar(20) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL,
  `correo` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha` varchar(20) NOT NULL,
  `hora` varchar(10) NOT NULL,
  `tipo_tramite` varchar(100) DEFAULT NULL,
  `estado` varchar(50) NOT NULL DEFAULT 'atendido',
  `created_at` bigint(20) NOT NULL,
  `atendido_at` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `personas_listas`
--

INSERT INTO `personas_listas` (`id`, `agendamiento_id`, `rut`, `nombre`, `apellido`, `correo`, `telefono`, `fecha`, `hora`, `tipo_tramite`, `estado`, `created_at`, `atendido_at`) VALUES
(1, 1, '20983443', 'Mauri', 'Carrillo', 'n@a.a', '977529739', '2026-07-15', '09:20', 'Renovación', 'atendido', 1783959431, 1783959431),
(15, 3, '20983443', 'Mauri', 'Carrillo', 'a@w.e', '977529739', '2026-07-15', '09:20', 'Renovación', 'atendido', 1783967959, 1783967981);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `superadmin`
--

CREATE TABLE `superadmin` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indices de la tabla `agendamientos`
--
ALTER TABLE `agendamientos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_agendamiento_unico` (`rut`,`fecha`,`hora`,`tipo_tramite`);

--
-- Indices de la tabla `bloqueos_temporales`
--
ALTER TABLE `bloqueos_temporales`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rut` (`rut`);

--
-- Indices de la tabla `disponibilidad`
--
ALTER TABLE `disponibilidad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_disponibilidad_fecha` (`fecha`);

--
-- Indices de la tabla `personas_listas`
--
ALTER TABLE `personas_listas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `agendamiento_id` (`agendamiento_id`);

--
-- Indices de la tabla `superadmin`
--
ALTER TABLE `superadmin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `agendamientos`
--
ALTER TABLE `agendamientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `bloqueos_temporales`
--
ALTER TABLE `bloqueos_temporales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `disponibilidad`
--
ALTER TABLE `disponibilidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `personas_listas`
--
ALTER TABLE `personas_listas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `superadmin`
--
ALTER TABLE `superadmin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
