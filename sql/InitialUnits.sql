CREATE TABLE IF NOT EXISTS `InitialUnits` (
  `MapID` integer unsigned NOT NULL,
  `SeqNum` smallint unsigned,
  `UnitType` integer unsigned NOT NULL,
  `Xloc` integer NOT NULL,
  `Yloc` integer NOT NULL,
  `State` varchar(100),
  `Health` integer,
  PRIMARY KEY (`MapID`, `XLoc`, `YLoc`),
  FOREIGN KEY (MapID)
    REFERENCES Maps(MapID)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (UnitType)
    REFERENCES UnitType(UnitType)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) DEFAULT CHARSET=utf8;
