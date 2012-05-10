CREATE TABLE IF NOT EXISTS `plugin_storage` (
  `id` int(255) NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text,
  `range_id` varchar(255) DEFAULT NULL,
  `pluginname` varchar(255) NOT NULL,
  PRIMARY KEY (`id`,`key`)
);
