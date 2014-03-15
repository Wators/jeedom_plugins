CREATE TABLE IF NOT EXISTS `energy` (
  `eqLogic_id` INT NOT NULL,
  `category` varchar(127) DEFAULT NULL,
  `consumption` varchar(255) DEFAULT NULL,
  `power` varchar(255) DEFAULT NULL,
  `options` text,
  KEY `category` (`category`),
  KEY `eqLogic_id` (`eqLogic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
