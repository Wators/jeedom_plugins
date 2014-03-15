CREATE TABLE IF NOT EXISTS `energy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eqLogic_id` INT NOT NULL,
  `category` varchar(127) DEFAULT NULL,
  `consumption` varchar(255) DEFAULT NULL,
  `power` varchar(255) DEFAULT NULL,
  `options` text,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  UNIQUE KEY `eqLogic_id` (`eqLogic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
