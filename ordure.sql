-- MySQL dump 10.13  Distrib 5.6.11, for osx10.6 (x86_64)
--
-- Host: localhost    Database: ordure
-- ------------------------------------------------------
-- Server version	5.6.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `brand`
--

DROP TABLE IF EXISTS `brand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `department`
--

DROP TABLE IF EXISTS `department`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `department` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent` int(10) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `pos` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parent` (`parent`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item`
--

DROP TABLE IF EXISTS `item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product` int(10) unsigned NOT NULL,
  `code` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(255) DEFAULT NULL,
  `unit_of_sale` varchar(16) NOT NULL,
  `retail_price` decimal(9,2) NOT NULL,
  `purchase_qty` int(10) unsigned NOT NULL,
  `length` decimal(9,2) DEFAULT NULL,
  `width` decimal(9,2) DEFAULT NULL,
  `height` decimal(9,2) DEFAULT NULL,
  `weight` decimal(9,2) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `inactive` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `product` (`product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mac_catalog`
--

DROP TABLE IF EXISTS `mac_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mac_catalog` (
  `item_no` varchar(32) NOT NULL,
  `sku` varchar(12) NOT NULL,
  `internal_name` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `unit_of_sale` varchar(16) NOT NULL,
  `retail_price` decimal(9,2) NOT NULL,
  `product_code_type` char(1) NOT NULL,
  `product_code` varchar(32) NOT NULL,
  `purchase_qty` int(10) unsigned NOT NULL,
  `abcflag` char(1) NOT NULL,
  `category_code` varchar(32) NOT NULL,
  `category_description` varchar(255) NOT NULL,
  `breadcrumb` text NOT NULL,
  `chapter` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `product_title` varchar(255) NOT NULL,
  `product_subtitle` varchar(255) NOT NULL,
  `phaseoutdate` date DEFAULT NULL,
  `length` decimal(9,2) DEFAULT NULL,
  `width` decimal(9,2) DEFAULT NULL,
  `height` decimal(9,2) DEFAULT NULL,
  `weight` decimal(9,2) DEFAULT NULL,
  `small_100` varchar(255) DEFAULT NULL,
  `medium_200` varchar(255) DEFAULT NULL,
  `large_480` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`item_no`),
  KEY `item_no` (`item_no`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mac_item_brands`
--

DROP TABLE IF EXISTS `mac_item_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mac_item_brands` (
  `item_no` varchar(255) NOT NULL DEFAULT '',
  `internal_name` varchar(255) DEFAULT NULL,
  `brand_id` int(10) unsigned DEFAULT NULL,
  `brand_name` varchar(255) DEFAULT NULL,
  `vendor_number` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`item_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `product`
--

DROP TABLE IF EXISTS `product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `department` int(10) unsigned DEFAULT NULL,
  `brand` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `slug` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL DEFAULT '',
  `from_item_no` varchar(255) DEFAULT NULL,
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `inactive` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `department` (`department`,`brand`,`slug`),
  KEY `from_item_no` (`from_item_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-11-14 16:07:51
