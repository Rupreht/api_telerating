CREATE DATABASE IF NOT EXISTS `@DB_NAME@`;
USE `@DB_NAME@`;
CREATE TABLE IF NOT EXISTS `api_telerating_counter` (
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
INSERT INTO `api_telerating_counter` (`id`)
    SELECT "0" FROM DUAL
    WHERE NOT EXISTS ( SELECT `id` FROM `api_telerating_counter` LIMIT 1 );
