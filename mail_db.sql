USE `mail`;

DROP table if exists `users`;

DROP DATABASE IF EXISTS `mail`;

CREATE database `mail`;

USE `mail`;

CREATE TABLE `users` (
    `email` varchar(80) NOT NULL,
    `password` varchar(20) NOT NULL,
    PRIMARY KEY (`email`)
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE `users_alias` (
    `server_name` varchar(80) NOT NULL,
    `user_id` int(10) NOT NULL,
    `email` varchar(80) NOT NULL,
    PRIMARY KEY (`email`)
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1