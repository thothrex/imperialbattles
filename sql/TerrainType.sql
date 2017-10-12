-- --------------------------------------------------------

--
-- Table structure for table `TerrainType`
--

CREATE TABLE IF NOT EXISTS `TerrainType` (
  `TerrainType` int(11) unsigned NOT NULL,
  `Defence` int(11) NOT NULL,
  PRIMARY KEY (`TerrainType`)
) DEFAULT CHARSET=utf8;
