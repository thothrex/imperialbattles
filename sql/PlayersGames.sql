CREATE TABLE IF NOT EXISTS `PlayersGames` (
  `UserName` varchar(125) NOT NULL,
  `GameID` bigint unsigned NOT NULL,
  `Colour` varchar(125),
  `SeqNum` smallint unsigned,
  `Team` smallint unsigned,
  `Ready` boolean,
  `Alive` boolean NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`GameID`, `UserName`),
  UNIQUE INDEX(`GameID`, `SeqNum`),
  FOREIGN KEY (GameID)
    REFERENCES Games(GameID)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (UserName)
    REFERENCES Players(UserName)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) DEFAULT CHARSET=utf8;
