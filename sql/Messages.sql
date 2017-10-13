CREATE TABLE IF NOT EXISTS `Messages` (
  `MessageID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `GameID` bigint unsigned,
  `UserName` varchar(125),
  `Time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `MessageText` varchar(3000) NOT NULL,
  PRIMARY KEY (`MessageID`),
  FOREIGN KEY (GameID)
    REFERENCES Games(GameID)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (UserName)
    REFERENCES Players(UserName)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) DEFAULT CHARSET=utf8;
