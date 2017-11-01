-- --------------------------------------------------------

--
-- Table structure for table `Movement`
--

CREATE TABLE IF NOT EXISTS `Movement` (
  `UnitType` integer unsigned NOT NULL,
  `TerrainType` integer unsigned NOT NULL,
  `Modifier` smallint,
  PRIMARY KEY (`UnitType`,`TerrainType`),
  FOREIGN KEY (UnitType)
    REFERENCES UnitType(UnitType)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (TerrainType)
    REFERENCES TerrainType(TerrainType)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) DEFAULT CHARSET=utf8;
