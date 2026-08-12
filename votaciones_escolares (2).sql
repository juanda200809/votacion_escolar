-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-07-2026 a las 06:11:01
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `votaciones_escolares`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `accion` varchar(255) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `candidatos`
--

CREATE TABLE `candidatos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `curso` varchar(20) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `propuestas` text DEFAULT NULL,
  `numero_tarjeton` int(11) NOT NULL,
  `id_eleccion` int(11) NOT NULL,
  `id_cargo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `candidatos`
--

INSERT INTO `candidatos` (`id`, `nombre`, `apellido`, `curso`, `foto`, `propuestas`, `numero_tarjeton`, `id_eleccion`, `id_cargo`) VALUES
(8, 'diomedez', 'diaz', '1104', '6a54464148c25.jpg', 'parranda y ron todos los dias', 1, 4, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargos`
--

CREATE TABLE `cargos` (
  `id` int(11) NOT NULL,
  `nombre_cargo` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cargos`
--

INSERT INTO `cargos` (`id`, `nombre_cargo`) VALUES
(1, 'Personero'),
(2, 'Contralor'),
(3, 'Representante Estudiantil'),
(4, 'Cabildante');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `elecciones`
--

CREATE TABLE `elecciones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('cargo','todos') NOT NULL DEFAULT 'cargo',
  `id_cargo` int(11) DEFAULT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `estado` enum('abierta','cerrada') DEFAULT 'cerrada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `elecciones`
--

INSERT INTO `elecciones` (`id`, `nombre`, `descripcion`, `tipo`, `id_cargo`, `fecha_inicio`, `fecha_fin`, `estado`) VALUES
(4, 'elecciones estudiantiles 2026', 'Proceso democrático institucional', 'cargo', NULL, '2026-08-20 23:00:00', '2026-09-01 12:00:00', 'abierta');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `eleccion_cargos`
--

CREATE TABLE `eleccion_cargos` (
  `id` int(11) NOT NULL,
  `id_eleccion` int(11) NOT NULL,
  `id_cargo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `eleccion_cargos`
--

INSERT INTO `eleccion_cargos` (`id`, `id_eleccion`, `id_cargo`) VALUES
(16, 4, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `documento` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `curso` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('administrador','estudiante') NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `documento`, `nombre`, `apellido`, `correo`, `curso`, `password`, `rol`, `fecha_registro`) VALUES
(1, '1000000001', 'Administrador', 'Sistema', 'admin@colegio.edu', '', 'admin123', 'administrador', '2026-06-13 20:32:03'),
(9, 'admin', 'Administrador', 'Sistema', 'admin@colegio.com', NULL, '$2y$10$88YF1/G8EYQxGUQIo0R53eWhmvOnsJwhVGSOabOB4iUbm9QySS22m', 'administrador', '2026-07-08 21:23:04'),
(10, '1072661698', 'juan', 'Cantor', 'juandavidoterocantor20@gmail.com', '1104', '$2y$10$1cahb2Leo3sMcHKrO4mg/uBtSPFkf3uUf2lG.fOf9N0t8Nn1Q1Vlu', 'estudiante', '2026-07-09 04:40:23'),
(11, '1073535108', 'joseph', 'caina', 'joseph.edu@gmail.com', '4A', '$2y$10$pihUwzSzYicXGLFkeEm3X.VKS1h/EPNtvO0onO0F8UT0zodJ1GswS', 'estudiante', '2026-07-09 17:56:24'),
(12, '1072709874', 'camila', 'otero', 'camila.edu@gmail.com', '705', '', 'estudiante', '2026-07-09 18:04:56'),
(13, '1073535213', 'salome', 'barco', 'salome.edu@gmail.com', '4A', '$2y$10$.4070bZteTAokh5Ge1l8fOSaXmk0xEoxbCVu4LGLta7V52xFeiTRG', 'estudiante', '2026-07-09 18:08:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `votos`
--

CREATE TABLE `votos` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_candidato` int(11) NOT NULL,
  `id_eleccion` int(11) NOT NULL,
  `fecha_voto` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_cargo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `votos`
--

INSERT INTO `votos` (`id`, `id_usuario`, `id_candidato`, `id_eleccion`, `fecha_voto`, `id_cargo`) VALUES
(1, 10, 8, 4, '2026-07-13 09:55:58', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `candidatos`
--
ALTER TABLE `candidatos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_eleccion` (`id_eleccion`),
  ADD KEY `id_cargo` (`id_cargo`);

--
-- Indices de la tabla `cargos`
--
ALTER TABLE `cargos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `elecciones`
--
ALTER TABLE `elecciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `eleccion_cargos`
--
ALTER TABLE `eleccion_cargos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_eleccion` (`id_eleccion`),
  ADD KEY `id_cargo` (`id_cargo`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `documento` (`documento`);

--
-- Indices de la tabla `votos`
--
ALTER TABLE `votos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voto_unico_por_cargo` (`id_usuario`,`id_cargo`),
  ADD KEY `id_candidato` (`id_candidato`),
  ADD KEY `fk_votos_cargo` (`id_cargo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `candidatos`
--
ALTER TABLE `candidatos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `cargos`
--
ALTER TABLE `cargos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `elecciones`
--
ALTER TABLE `elecciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `eleccion_cargos`
--
ALTER TABLE `eleccion_cargos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `votos`
--
ALTER TABLE `votos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `candidatos`
--
ALTER TABLE `candidatos`
  ADD CONSTRAINT `candidatos_ibfk_1` FOREIGN KEY (`id_eleccion`) REFERENCES `elecciones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidatos_ibfk_2` FOREIGN KEY (`id_cargo`) REFERENCES `cargos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `eleccion_cargos`
--
ALTER TABLE `eleccion_cargos`
  ADD CONSTRAINT `eleccion_cargos_ibfk_1` FOREIGN KEY (`id_eleccion`) REFERENCES `elecciones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `eleccion_cargos_ibfk_2` FOREIGN KEY (`id_cargo`) REFERENCES `cargos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `votos`
--
ALTER TABLE `votos`
  ADD CONSTRAINT `fk_votos_cargo` FOREIGN KEY (`id_cargo`) REFERENCES `cargos` (`id`),
  ADD CONSTRAINT `votos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votos_ibfk_2` FOREIGN KEY (`id_candidato`) REFERENCES `candidatos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
