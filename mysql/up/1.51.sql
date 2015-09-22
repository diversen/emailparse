DROP TABLE IF EXISTS `emailparse`;

CREATE TABLE `emailparse` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `title` varchar(128) NOT NULL,
  `entry` text,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `email` varchar(255) NOT NULL,
  `user_id` int(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

DROP TABLE IF EXISTS `emailparse_email`;

CREATE TABLE `emailparse_email` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `email` varchar(60) NOT NULL,
  `email_from` varchar (80) NOT NULL,
  `password` varchar(32) NOT NULL,
  `server_name` varchar (80) NOT NULL,
  `user_id` int(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

CREATE INDEX `idx_email` ON emailparse(`email`);