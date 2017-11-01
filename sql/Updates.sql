-- --------------------------------------------------------

--
-- Table structure for table `Updates`
--

CREATE TABLE IF NOT EXISTS `Updates` (
  `UpdateID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `GameID` bigint unsigned NOT NULL,
  `UserName` varchar(125) NOT NULL,
  `Time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Action` varchar(3000) NOT NULL,
  PRIMARY KEY (`UpdateID`),
  FOREIGN KEY (GameID)
    REFERENCES Games(GameID)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (UserName)
    REFERENCES Players(UserName)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) DEFAULT CHARSET=utf8;
