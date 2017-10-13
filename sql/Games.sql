
SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- --------------------------------------------------------

--
-- Table structure for table `Games`
--

CREATE TABLE IF NOT EXISTS `Games` (
  `GameID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `GameName` varchar(255) NOT NULL,
  `MapID` integer unsigned DEFAULT NULL,
  `PlayersLimit` int(11) DEFAULT NULL,
  `NoPlayers` int(11) DEFAULT '1',
  `InProgress` tinyint(1) DEFAULT '0',
  `TurnTimeout` int(11) DEFAULT '0',
  `LastUpdated` varchar(19) NOT NULL DEFAULT '0000-00-00T00:00:00',
  `HostName` varchar(125),
  `Day` int(11) DEFAULT NULL,
  `Turn` int(11) DEFAULT NULL,
  PRIMARY KEY (`GameID`),
  UNIQUE KEY `GameName` (`GameName`),
  FOREIGN KEY (MapID)
    REFERENCES Maps(MapID)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  FOREIGN KEY (HostName)
    REFERENCES Players(UserName)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Games`
--

INSERT INTO `Games` (`GameID`, `GameName`, `MapID`, `PlayersLimit`, `NumPlayers`, `InProgress`, `TurnTimeout`, `LastUpdated`, `HostName`, `Day`, `Turn`) VALUES
(1, 'idle', NULL, NULL, 1, 0, 0, '1337-02-02T13:37:02', NULL, NULL, NULL);