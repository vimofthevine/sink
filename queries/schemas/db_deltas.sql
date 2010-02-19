CREATE TABLE IF NOT EXISTS `db_deltas` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `file` varchar(128) NOT NULL,
    `date` int(10) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

