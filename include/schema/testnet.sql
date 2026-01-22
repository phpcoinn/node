/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.22-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: phpcointestnet
-- ------------------------------------------------------
-- Server version	10.6.22-MariaDB-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` varchar(128) NOT NULL,
  `public_key` varchar(255) NOT NULL,
  `block` varchar(128) NOT NULL,
  `balance` decimal(20,8) NOT NULL,
  `alias` varchar(32) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounts` (`block`),
  KEY `alias` (`alias`),
  CONSTRAINT `accounts` FOREIGN KEY (`block`) REFERENCES `blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blocks`
--

DROP TABLE IF EXISTS `blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `blocks` (
  `id` varchar(128) NOT NULL,
  `generator` varchar(128) NOT NULL,
  `height` int(11) NOT NULL,
  `date` int(11) NOT NULL,
  `nonce` varchar(128) NOT NULL,
  `signature` varchar(255) NOT NULL,
  `difficulty` varchar(64) NOT NULL,
  `transactions` int(11) NOT NULL,
  `version` varchar(10) DEFAULT '010000',
  `argon` varchar(128) NOT NULL,
  `miner` varchar(128) DEFAULT NULL,
  `masternode` varchar(128) DEFAULT NULL,
  `mn_signature` varchar(255) DEFAULT NULL,
  `schash` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `height` (`height`),
  KEY `blocks_masternode_index` (`masternode`),
  KEY `blocks_masternode_height_index` (`masternode`,`height`),
  KEY `blocks_generator_index` (`generator`),
  KEY `blocks_miner_index` (`miner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `config` (
  `cfg` varchar(30) NOT NULL,
  `val` varchar(200) NOT NULL,
  PRIMARY KEY (`cfg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `masternode`
--

DROP TABLE IF EXISTS `masternode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `masternode` (
  `public_key` varchar(255) NOT NULL,
  `height` int(11) NOT NULL,
  `ip` varchar(30) DEFAULT NULL,
  `win_height` int(11) DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `id` varchar(128) DEFAULT NULL,
  `collateral` int(11) NOT NULL DEFAULT 10000,
  `verified` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`public_key`),
  UNIQUE KEY `masternode_ip_uindex` (`ip`),
  KEY `height` (`height`),
  KEY `last_won` (`win_height`),
  KEY `mix` (`height`,`signature`,`id`,`public_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mempool`
--

DROP TABLE IF EXISTS `mempool`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mempool` (
  `id` varchar(128) NOT NULL,
  `height` int(11) NOT NULL,
  `src` varchar(128) NOT NULL,
  `dst` varchar(128) DEFAULT NULL,
  `val` decimal(20,8) NOT NULL,
  `fee` decimal(20,8) NOT NULL,
  `signature` varchar(255) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `message` varchar(255) DEFAULT '',
  `public_key` varchar(255) NOT NULL,
  `date` bigint(20) NOT NULL,
  `peer` varchar(64) DEFAULT NULL,
  `data` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `height` (`height`),
  KEY `peer` (`peer`),
  KEY `src` (`src`),
  KEY `val` (`val`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `peers`
--

DROP TABLE IF EXISTS `peers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `peers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostname` varchar(128) NOT NULL,
  `blacklisted` int(11) NOT NULL DEFAULT 0,
  `ping` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `fails` int(11) NOT NULL DEFAULT 0,
  `stuckfail` tinyint(4) NOT NULL DEFAULT 0,
  `height` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `blacklist_reason` varchar(100) DEFAULT NULL,
  `dappshash` varchar(250) DEFAULT NULL,
  `miner` varchar(128) DEFAULT NULL,
  `generator` varchar(128) DEFAULT NULL,
  `masternode` varchar(128) DEFAULT NULL,
  `dapps_id` varchar(128) DEFAULT NULL,
  `block_id` varchar(128) DEFAULT NULL,
  `response_time` decimal(20,8) DEFAULT 0.00000000,
  `response_cnt` int(11) DEFAULT 0,
  `version` varchar(20) DEFAULT NULL,
  `info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`info`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `hostname` (`hostname`),
  UNIQUE KEY `ip` (`ip`),
  KEY `blacklisted` (`blacklisted`),
  KEY `ping` (`ping`),
  KEY `stuckfail` (`stuckfail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `smart_contract_state`
--

DROP TABLE IF EXISTS `smart_contract_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `smart_contract_state` (
  `sc_address` varchar(128) NOT NULL,
  `variable` varchar(100) NOT NULL,
  `var_key` varchar(128) DEFAULT NULL,
  `var_value` varchar(1000) DEFAULT NULL,
  `height` int(11) NOT NULL,
  UNIQUE KEY `smart_contract_state_sc_address_variable_var_key_height_uindex` (`sc_address`,`variable`,`var_key`,`height`),
  CONSTRAINT `smart_contract_state_smart_contracts_address_fk` FOREIGN KEY (`sc_address`) REFERENCES `smart_contracts` (`address`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `smart_contracts`
--

DROP TABLE IF EXISTS `smart_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `smart_contracts` (
  `address` varchar(128) NOT NULL,
  `height` int(11) NOT NULL,
  `code` mediumtext NOT NULL,
  `signature` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`address`),
  UNIQUE KEY `smart_contracts_address_uindex` (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `token_balances`
--

DROP TABLE IF EXISTS `token_balances`;
/*!50001 DROP VIEW IF EXISTS `token_balances`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `token_balances` AS SELECT
 1 AS `token`,
  1 AS `address`,
  1 AS `balance` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `token_mempool_txs`
--

DROP TABLE IF EXISTS `token_mempool_txs`;
/*!50001 DROP VIEW IF EXISTS `token_mempool_txs`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `token_mempool_txs` AS SELECT
 1 AS `id`,
  1 AS `height`,
  1 AS `date`,
  1 AS `token`,
  1 AS `method`,
  1 AS `src`,
  1 AS `dst`,
  1 AS `amount` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `token_txs`
--

DROP TABLE IF EXISTS `token_txs`;
/*!50001 DROP VIEW IF EXISTS `token_txs`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `token_txs` AS SELECT
 1 AS `id`,
  1 AS `height`,
  1 AS `block`,
  1 AS `date`,
  1 AS `token`,
  1 AS `method`,
  1 AS `src`,
  1 AS `dst`,
  1 AS `amount` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `tokens`
--

DROP TABLE IF EXISTS `tokens`;
/*!50001 DROP VIEW IF EXISTS `tokens`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `tokens` AS SELECT
 1 AS `address`,
  1 AS `metadata`,
  1 AS `name`,
  1 AS `description`,
  1 AS `symbol`,
  1 AS `initialSupply`,
  1 AS `decimals`,
  1 AS `height` */;
SET character_set_client = @saved_cs_client;



--
-- Table structure for table `transaction_data`
--

DROP TABLE IF EXISTS `transaction_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_data` (
                                    `tx_id` varchar(128) NOT NULL,
                                    `data` mediumtext DEFAULT NULL,
                                    PRIMARY KEY (`tx_id`),
                                    CONSTRAINT `fk_tx_data` FOREIGN KEY (`tx_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` varchar(128) NOT NULL,
  `block` varchar(128) NOT NULL,
  `height` int(11) NOT NULL,
  `dst` varchar(128) DEFAULT NULL,
  `val` decimal(20,8) NOT NULL,
  `fee` decimal(20,8) NOT NULL,
  `signature` varchar(255) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `message` varchar(255) DEFAULT '',
  `date` int(11) NOT NULL,
  `public_key` varchar(255) NOT NULL,
  `src` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `block_id` (`block`),
  KEY `dst` (`dst`),
  KEY `height` (`height`),
  KEY `message` (`message`),
  KEY `public_key` (`public_key`),
  KEY `transactions_src_index` (`src`),
  KEY `transactions_type_index` (`type`),
  KEY `transactions_src_dst_val_fee_index` (`src`,`dst`,`val`,`fee`),
  KEY `idx_src_height` (`src`,`height`,`val`),
  KEY `idx_dst_height` (`dst`,`height`,`val`),
  CONSTRAINT `block_id` FOREIGN KEY (`block`) REFERENCES `blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `token_balances`
--

/*!50001 DROP VIEW IF EXISTS `token_balances`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`phpcoin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `token_balances` AS select `b`.`token` AS `token`,`b`.`address` AS `address`,format(`b`.`var_value` / pow(10,`b`.`decimals`),`b`.`decimals`) AS `balance` from (select `ss`.`sc_address` AS `token`,`ss`.`var_key` AS `address`,`ss`.`var_value` AS `var_value`,row_number() over ( partition by `ss`.`sc_address`,`ss`.`var_key` order by `ss`.`height` desc) AS `rn`,`tt`.`decimals` AS `decimals` from (`smart_contract_state` `ss` join `tokens` `tt` on(`ss`.`sc_address` = `tt`.`address`)) where `ss`.`variable` = 'balances') `b` where `b`.`rn` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `token_mempool_txs`
--

/*!50001 DROP VIEW IF EXISTS `token_mempool_txs`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`phpcoin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `token_mempool_txs` AS select `txs`.`id` AS `id`,`txs`.`height` AS `height`,`txs`.`date` AS `date`,`txs`.`dst` AS `token`,`txs`.`method` AS `method`,case when `txs`.`method` = 'transfer' then `txs`.`src` when `txs`.`method` = 'mint' then NULL when `txs`.`method` = 'burn' then `txs`.`src` else `txs`.`p1` end AS `src`,case when `txs`.`method` = 'transfer' then `txs`.`p1` when `txs`.`method` = 'mint' then `txs`.`src` when `txs`.`method` = 'burn' then NULL else `txs`.`p2` end AS `dst`,case when `txs`.`method` = 'transfer' then `txs`.`p2` when `txs`.`method` = 'mint' then `txs`.`p1` when `txs`.`method` = 'burn' then `txs`.`p1` else `txs`.`p3` end AS `amount` from (select `t`.`id` AS `id`,`t`.`height` AS `height`,`t`.`src` AS `src`,`t`.`dst` AS `dst`,`t`.`val` AS `val`,`t`.`fee` AS `fee`,`t`.`signature` AS `signature`,`t`.`type` AS `type`,`t`.`message` AS `message`,`t`.`public_key` AS `public_key`,`t`.`date` AS `date`,`t`.`peer` AS `peer`,`t`.`data` AS `data`,json_unquote(json_extract(from_base64(`t`.`message`),'$.method')) AS `method`,json_unquote(json_extract(from_base64(`t`.`message`),'$.params[0]')) AS `p1`,json_unquote(json_extract(from_base64(`t`.`message`),'$.params[1]')) AS `p2`,json_unquote(json_extract(from_base64(`t`.`message`),'$.params[2]')) AS `p3` from `mempool` `t` where `t`.`type` = 6 and exists(select 1 from `tokens` `tt` where `tt`.`address` = `t`.`dst` limit 1)) `txs` where `txs`.`method` in ('transfer','transferFrom','mint','burn') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `token_txs`
--

/*!50001 DROP VIEW IF EXISTS `token_txs`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`phpcoin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `token_txs` AS select `txs`.`id` AS `id`,`txs`.`height` AS `height`,`txs`.`block` AS `block`,`txs`.`date` AS `date`,`txs`.`dst` AS `token`,`txs`.`method` AS `method`,case when `txs`.`method` = 'transfer' then `txs`.`src` when `txs`.`method` = 'mint' then NULL when `txs`.`method` = 'burn' then `txs`.`src` else `txs`.`p1` end AS `src`,case when `txs`.`method` = 'transfer' then `txs`.`p1` when `txs`.`method` = 'mint' then `txs`.`src` when `txs`.`method` = 'burn' then NULL else `txs`.`p2` end AS `dst`,case when `txs`.`method` = 'transfer' then `txs`.`p2` when `txs`.`method` = 'mint' then `txs`.`p1` when `txs`.`method` = 'burn' then `txs`.`p1` else `txs`.`p3` end AS `amount` from (select `t`.`id` AS `id`,`t`.`block` AS `block`,`t`.`height` AS `height`,`t`.`src` AS `src`,`t`.`dst` AS `dst`,`t`.`val` AS `val`,`t`.`fee` AS `fee`,`t`.`signature` AS `signature`,`t`.`type` AS `type`,`t`.`message` AS `message`,`t`.`date` AS `date`,`t`.`public_key` AS `public_key`,json_unquote(json_extract(from_base64(`t`.`message`),'$.method')) AS `method`,json_unquote(json_extract(from_base64(`t`.`message`),'$.params[0]')) AS `p1`,json_unquote(json_extract(from_base64(`t`.`message`),'$.params[1]')) AS `p2`,json_unquote(json_extract(from_base64(`t`.`message`),'$.params[2]')) AS `p3` from `transactions` `t` where `t`.`type` = 6 and exists(select 1 from `tokens` `tt` where `tt`.`address` = `t`.`dst` limit 1)) `txs` where `txs`.`method` in ('transfer','transferFrom','mint','burn') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `tokens`
--

/*!50001 DROP VIEW IF EXISTS `tokens`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`phpcoin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `tokens` AS select `sc`.`address` AS `address`,`sc`.`metadata` AS `metadata`,json_unquote(json_extract(`sc`.`metadata`,'$.name')) AS `name`,json_unquote(json_extract(`sc`.`metadata`,'$.description')) AS `description`,json_unquote(json_extract(`sc`.`metadata`,'$.symbol')) AS `symbol`,json_extract(`sc`.`metadata`,'$.initialSupply') AS `initialSupply`,json_extract(`sc`.`metadata`,'$.decimals') AS `decimals`,`sc`.`height` AS `height` from `smart_contracts` `sc` where json_extract(`sc`.`metadata`,'$.class') = 'ERC-20' */;
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

-- Dump completed on 2026-01-14 18:37:11
