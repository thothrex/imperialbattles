-- --------------------------------------------------------

--
-- Table structure for table `Units`
--

CREATE TABLE IF NOT EXISTS `Units` (
  `UnitID` integer unsigned NOT NULL AUTO_INCREMENT,
  `GameID` bigint unsigned NOT NULL,
  `UnitType` integer unsigned NOT NULL,
  `SeqNum` smallint unsigned,
  `Xloc` integer NOT NULL,
  `Yloc` integer NOT NULL,
  `State` varchar(100),
  `Health` integer,
  PRIMARY KEY (`UnitID`),
  FOREIGN KEY (GameID)
    REFERENCES Games(GameID)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (UnitType)
    REFERENCES UnitType(UnitType)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) DEFAULT CHARSET=utf8;
