-- --------------------------------------------------------

--
-- Table structure for table `Attack`
--

CREATE TABLE IF NOT EXISTS `Attack` (
  `Attacker` integer unsigned NOT NULL,
  `Defender` integer unsigned NOT NULL,
  `Modifier` integer,
  PRIMARY KEY (`Attacker`,`Defender`),
  FOREIGN KEY (Attacker)
    REFERENCES UnitType(UnitType)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (Defender)
    REFERENCES UnitType(UnitType)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) DEFAULT CHARSET=utf8;
