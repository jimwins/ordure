-- MySQL dump 10.13  Distrib 5.7.10, for osx10.9 (x86_64)
--
-- Host: localhost    Database: ordure
-- ------------------------------------------------------
-- Server version	5.7.10

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
-- Table structure for table `auth`
--

DROP TABLE IF EXISTS `auth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth` (
  `person_id` int(10) unsigned NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `otp_key` varchar(255) DEFAULT NULL,
  `last_auth` datetime DEFAULT NULL,
  `failures` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_token`
--

DROP TABLE IF EXISTS `auth_token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_token` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `selector` char(12) DEFAULT NULL,
  `token` char(64) DEFAULT NULL,
  `person_id` int(10) unsigned NOT NULL,
  `expires` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `brand`
--

DROP TABLE IF EXISTS `brand`;
/*!50001 DROP VIEW IF EXISTS `brand`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `brand` AS SELECT 
 1 AS `id`,
 1 AS `name`,
 1 AS `slug`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `department`
--

DROP TABLE IF EXISTS `department`;
/*!50001 DROP VIEW IF EXISTS `department`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `department` AS SELECT 
 1 AS `id`,
 1 AS `parent`,
 1 AS `name`,
 1 AS `slug`,
 1 AS `pos`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `item`
--

DROP TABLE IF EXISTS `item`;
/*!50001 DROP VIEW IF EXISTS `item`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `item` AS SELECT 
 1 AS `id`,
 1 AS `product`,
 1 AS `code`,
 1 AS `name`,
 1 AS `short_name`,
 1 AS `variation`,
 1 AS `unit_of_sale`,
 1 AS `retail_price`,
 1 AS `discount_type`,
 1 AS `discount`,
 1 AS `purchase_quantity`,
 1 AS `length`,
 1 AS `width`,
 1 AS `height`,
 1 AS `weight`,
 1 AS `thumbnail`,
 1 AS `tic`,
 1 AS `added`,
 1 AS `modified`,
 1 AS `active`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `loyalty`
--

DROP TABLE IF EXISTS `loyalty`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loyalty` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `name` varchar(25) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `loyalty_number` varchar(32) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `page`
--

DROP TABLE IF EXISTS `page`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT '',
  `slug` varchar(255) NOT NULL,
  `format` enum('markdown','html') NOT NULL DEFAULT 'markdown',
  `content` mediumtext,
  `script` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `person`
--

DROP TABLE IF EXISTS `person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role` enum('customer','employee','vendor') DEFAULT 'customer',
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT '1',
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `product`
--

DROP TABLE IF EXISTS `product`;
/*!50001 DROP VIEW IF EXISTS `product`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `product` AS SELECT 
 1 AS `id`,
 1 AS `department`,
 1 AS `brand`,
 1 AS `name`,
 1 AS `description`,
 1 AS `slug`,
 1 AS `image`,
 1 AS `variation_style`,
 1 AS `added`,
 1 AS `modified`,
 1 AS `active`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `redirect`
--

DROP TABLE IF EXISTS `redirect`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `redirect` (
  `source` varchar(255) NOT NULL,
  `dest` varchar(255) NOT NULL,
  PRIMARY KEY (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sale`
--

DROP TABLE IF EXISTS `sale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(50) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('new','cart','review','unpaid','paid','processing','shipped','cancelled','onhold') NOT NULL DEFAULT 'new',
  `person_id` int(10) unsigned NOT NULL,
  `billing_address_id` int(10) unsigned DEFAULT NULL,
  `shipping_address_id` int(10) unsigned DEFAULT NULL,
  `shipping` decimal(9,2) NOT NULL DEFAULT '0.00',
  `shipping_tax` decimal(9,3) DEFAULT '0.000',
  `shipping_manual` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `tax_exemption` varchar(50) DEFAULT NULL,
  `tax_calculated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sale_address`
--

DROP TABLE IF EXISTS `sale_address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_address` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `address1` varchar(255) NOT NULL,
  `address2` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL,
  `state` char(2) NOT NULL,
  `zip5` char(5) NOT NULL,
  `zip4` char(4) NOT NULL DEFAULT '0000',
  `phone` varchar(50) NOT NULL DEFAULT '',
  `verified` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sale_item`
--

DROP TABLE IF EXISTS `sale_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `quantity` int(11) NOT NULL,
  `override_name` varchar(255) DEFAULT NULL,
  `retail_price` decimal(9,2) NOT NULL,
  `discount_type` enum('percentage','relative','fixed') DEFAULT NULL,
  `discount` decimal(9,2) DEFAULT NULL,
  `discount_manual` tinyint(4) NOT NULL DEFAULT '0',
  `tic` char(5) NOT NULL DEFAULT '00000',
  `tax` decimal(9,3) NOT NULL DEFAULT '0.000',
  PRIMARY KEY (`id`),
  KEY `sale` (`sale_id`),
  KEY `item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sale_payment`
--

DROP TABLE IF EXISTS `sale_payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_payment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` int(10) unsigned NOT NULL,
  `method` enum('credit','bitcoin','paypal','gift','other') NOT NULL,
  `amount` decimal(9,3) NOT NULL,
  `processed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data` mediumblob,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sale_shipment`
--

DROP TABLE IF EXISTS `sale_shipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_shipment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` int(10) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `ship_date` date NOT NULL,
  `carrier` varchar(50) NOT NULL,
  `service` varchar(50) NOT NULL,
  `tracking_number` varchar(50) NOT NULL,
  `shipping_cost` decimal(9,2) NOT NULL,
  `data` mediumblob,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scat_item`
--

DROP TABLE IF EXISTS `scat_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scat_item` (
  `retail_price` decimal(9,2) NOT NULL DEFAULT '0.00',
  `discount_type` enum('percentage','relative','fixed') DEFAULT NULL,
  `discount` decimal(9,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `minimum_quantity` int(11) DEFAULT '0',
  `purchase_quantity` int(11) DEFAULT '1',
  `code` varchar(255) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'ordure'
--
/*!50003 DROP FUNCTION IF EXISTS `round_to_even` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `round_to_even`(value decimal(32,16), places int) RETURNS decimal(32,16)
BEGIN  RETURN IF(ABS(value - TRUNCATE(value, places)) * POWER(10, places + 1) = 5            AND NOT CONVERT(TRUNCATE(abs(value) * POWER(10, places), 0),                            UNSIGNED) % 2 = 1,            TRUNCATE(value, places), ROUND(value, places));
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `sale_price` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `sale_price`(retail_price decimal(9,2), type char(32), discount decimal(9,2)) RETURNS decimal(9,2)
BEGIN   RETURN IF(type IS NOT NULL AND type != '',             CASE type             WHEN 'percentage' THEN               CAST(ROUND_TO_EVEN(retail_price * ((100 - discount) / 100), 2) AS DECIMAL(9,2))             WHEN 'relative' THEN               (retail_price - discount)             WHEN 'fixed' THEN               (discount)             END,             retail_price); END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `slug` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `slug`(val VARCHAR(255)) RETURNS varchar(255) CHARSET utf8
    DETERMINISTIC
RETURN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(REPLACE(LOWER(val), CHAR(0xC2A0), '-')), '&', 'and'), ' ', '-'), '"', ''), "'", ''), '/', '-'), ':', ''), '.', ''), '#', ''), '!', ''), '(', ''), ')', ''), '[', ''), ']', ''), ',', ''), '+', ''), '@', 'a'), '%', ''), '‘', ''), '’', ''), '“', ''), '”', ''), '®', ''), '°', '') ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `brand`
--

/*!50001 DROP VIEW IF EXISTS `brand`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `brand` AS select `scat`.`brand`.`id` AS `id`,`scat`.`brand`.`name` AS `name`,`scat`.`brand`.`slug` AS `slug` from `scat`.`brand` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `department`
--

/*!50001 DROP VIEW IF EXISTS `department`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `department` AS select `scat`.`department`.`id` AS `id`,`scat`.`department`.`parent_id` AS `parent`,`scat`.`department`.`name` AS `name`,`scat`.`department`.`slug` AS `slug`,0 AS `pos` from `scat`.`department` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `item`
--

/*!50001 DROP VIEW IF EXISTS `item`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `item` AS select `scat`.`item`.`id` AS `id`,`scat`.`item`.`product_id` AS `product`,`scat`.`item`.`code` AS `code`,`scat`.`item`.`name` AS `name`,`scat`.`item`.`short_name` AS `short_name`,`scat`.`item`.`variation` AS `variation`,'' AS `unit_of_sale`,`scat`.`item`.`retail_price` AS `retail_price`,`scat`.`item`.`discount_type` AS `discount_type`,`scat`.`item`.`discount` AS `discount`,`scat`.`item`.`purchase_quantity` AS `purchase_quantity`,`scat`.`item`.`length` AS `length`,`scat`.`item`.`width` AS `width`,`scat`.`item`.`height` AS `height`,`scat`.`item`.`weight` AS `weight`,if((`scat`.`item`.`color` <> ''),concat('color:',`scat`.`item`.`color`),'') AS `thumbnail`,`scat`.`item`.`tic` AS `tic`,`scat`.`item`.`added` AS `added`,`scat`.`item`.`modified` AS `modified`,`scat`.`item`.`active` AS `active` from `scat`.`item` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `product`
--

/*!50001 DROP VIEW IF EXISTS `product`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `product` AS select `scat`.`product`.`id` AS `id`,`scat`.`product`.`department_id` AS `department`,`scat`.`product`.`brand_id` AS `brand`,`scat`.`product`.`name` AS `name`,`scat`.`product`.`description` AS `description`,`scat`.`product`.`slug` AS `slug`,`scat`.`product`.`image` AS `image`,`scat`.`product`.`variation_style` AS `variation_style`,`scat`.`product`.`added` AS `added`,`scat`.`product`.`modified` AS `modified`,`scat`.`product`.`active` AS `active` from `scat`.`product` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2018-02-01 16:06:07
