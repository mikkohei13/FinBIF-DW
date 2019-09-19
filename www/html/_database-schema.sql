-- Adminer 4.7.3 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `latest_update`;
CREATE TABLE `latest_update` (
  `id` int(11) NOT NULL,
  `observation_id` int(11) DEFAULT NULL,
  `latest_update` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

INSERT INTO `latest_update` (`id`, `observation_id`, `latest_update`) VALUES
(0,	0,	'2019-09-30T00:00:00+03:00');

DROP TABLE IF EXISTS `observations`;
CREATE TABLE `observations` (
  `id` int(11) NOT NULL,
  `hash` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci DEFAULT NULL,
  `timestamp` bigint(20) DEFAULT NULL,
  `status` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;


-- 2019-09-19 12:43:11
