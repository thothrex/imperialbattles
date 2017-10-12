-- --------------------------------------------------------

--
-- Table structure for table `Maps`
--

CREATE TABLE IF NOT EXISTS `Maps` (
  `MapID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `MapName` varchar(255) NOT NULL,
  `MaxPlayers` int(11) unsigned DEFAULT '2',
  `Width` int(11) DEFAULT '0',
  `Height` int(11) DEFAULT '0',
  PRIMARY KEY (`MapID`)
) DEFAULT CHARSET=utf8;
