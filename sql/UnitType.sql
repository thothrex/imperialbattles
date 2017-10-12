-- --------------------------------------------------------

--
-- Table structure for table `UnitType`
--

CREATE TABLE IF NOT EXISTS `UnitType` (
  `UnitType` int(11) unsigned NOT NULL,
  `MoveAllowance` int(11) unsigned,
  `PrimaryAttackMinDist` int(11),
  `PrimaryAttackMaxDist` int(11),
  PRIMARY KEY (`UnitType`)
) DEFAULT CHARSET=utf8;
