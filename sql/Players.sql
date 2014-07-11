-- phpMyAdmin SQL Dump
-- version 3.3.10.4
-- http://www.phpmyadmin.net
--
-- Host: imperialdata.ameredistraction.com
-- Generation Time: Jul 06, 2014 at 08:19 AM
-- Server version: 5.1.56
-- PHP Version: 5.4.20

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `imperialbattles`
--

-- --------------------------------------------------------

--
-- Table structure for table `Players`
--

CREATE TABLE IF NOT EXISTS `Players` (
  `UserName` varchar(125) NOT NULL,
  `PwdHash` varchar(255) NOT NULL,
  `Email` text NOT NULL,
  `Code` int(11) DEFAULT NULL,
  `Wins` int(11) DEFAULT '0',
  `Defeats` int(11) DEFAULT '0',
  `LoggedOn` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`UserName`)
) DEFAULT CHARSET=utf8;
