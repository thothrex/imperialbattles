-- phpMyAdmin SQL Dump
-- version 3.3.10.4
-- http://www.phpmyadmin.net
--
-- Host: imperialdata.ameredistraction.com
-- Generation Time: Jul 10, 2014 at 02:28 PM
-- Server version: 5.1.56
-- PHP Version: 5.4.20

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `imperialbattles`
--

-- --------------------------------------------------------

--
-- Table structure for table `Games`
--

CREATE TABLE IF NOT EXISTS `Games` (
  `GameID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `GameName` varchar(255) NOT NULL,
  `MapID` int(11) DEFAULT NULL,
  `PlayersLimit` int(11) DEFAULT NULL,
  `NoPlayers` int(11) DEFAULT '1',
  `InProgress` tinyint(1) DEFAULT '0',
  `TurnTimeout` int(11) DEFAULT '0',
  `LastUpdated` varchar(19) NOT NULL DEFAULT '0000-00-00T00:00:00',
  `HostName` text,
  `Day` int(11) DEFAULT NULL,
  `Turn` int(11) DEFAULT NULL,
  PRIMARY KEY (`GameID`),
  UNIQUE KEY `GameName` (`GameName`)
) DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Games`
--

INSERT INTO `Games` (`GameID`, `GameName`, `MapID`, `PlayersLimit`, `NoPlayers`, `InProgress`, `TurnTimeout`, `LastUpdated`, `HostName`, `Day`, `Turn`) VALUES
(1, 'idle', 1, NULL, 1, 0, 0, '1337-02-02T13:37:02', NULL, NULL, NULL);