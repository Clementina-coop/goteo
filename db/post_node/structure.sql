CREATE TABLE IF NOT EXISTS `post_node` (
  `post` bigint(20) unsigned NOT NULL,
  `node` varchar(50) NOT NULL,
  `order` int(11) DEFAULT '1',
  PRIMARY KEY (`post`,`node`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Entradas para la portada de nodos';
