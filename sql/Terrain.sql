-- --------------------------------------------------------

--
-- Table structure for table `Terrain`
--

CREATE TABLE IF NOT EXISTS `Terrain` (
  `MapID` int(11) unsigned NOT NULL,
  `XLoc` int(11) NOT NULL,
  `YLoc` int(11) NOT NULL,
  `TerrainType` int(11) unsigned NOT NULL,
  PRIMARY KEY (`MapID`, `XLoc`, `YLoc`),
  FOREIGN KEY (MapID)
    REFERENCES Maps(MapID)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (TerrainType)
    REFERENCES TerrainType(TerrainType)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) DEFAULT CHARSET=utf8;
