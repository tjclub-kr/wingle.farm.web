/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.7.2-MariaDB, for Win64 (AMD64)
--
-- Host: www.tjclub.kr    Database: wingleDB
-- ------------------------------------------------------
-- Server version	10.11.11-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `attachment`
--

DROP TABLE IF EXISTS `attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attachment` (
  `att_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '첨부파일 ID',
  `att_grp_id` bigint(20) DEFAULT NULL COMMENT '첨부파일 그룹 ID',
  `original_name` varchar(255) NOT NULL COMMENT '원본 파일명',
  `stored_name` varchar(255) NOT NULL COMMENT '저장된 파일명',
  `storage_type` varchar(30) NOT NULL COMMENT '저장소 타입 (LOCAL, S3, GCS 등)',
  `storage_path` varchar(1000) NOT NULL COMMENT '저장 경로',
  `mime_type` varchar(255) DEFAULT NULL COMMENT 'MIME 타입',
  `file_size` bigint(20) unsigned NOT NULL COMMENT '파일 크기(Byte)',
  `extension` varchar(20) DEFAULT NULL COMMENT '파일 확장자',
  `checksum` varchar(128) DEFAULT NULL COMMENT '파일 체크섬(SHA-256 등)',
  `status` varchar(30) NOT NULL DEFAULT 'ACTIVE' COMMENT '파일 상태 (ACTIVE, DELETED 등)',
  `created_by` bigint(20) DEFAULT NULL COMMENT '등록자 ID',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`att_id`),
  KEY `idx_attachment_status` (`status`),
  KEY `idx_attachment_created_by` (`created_by`),
  KEY `idx_attachment_checksum` (`checksum`),
  KEY `idx_attachment_created_at` (`created_at`),
  KEY `idx_attachment_group_id` (`att_grp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='첨부파일';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attachment`
--

LOCK TABLES `attachment` WRITE;
/*!40000 ALTER TABLE `attachment` DISABLE KEYS */;
INSERT INTO `attachment` VALUES
(1,107,'DCP_0788.jpg','20260924220218_77938c12.jpg','LOCAL','uploads/20260924/20260924220218_77938c12.jpg','image/jpeg',449964,'jpg','eec6dc587cedb8ef82e8e282655572ebf15824ce24c6cd04f4598328e52e2cf8','ACTIVE',NULL,'2026-09-24 22:02:18','2026-09-24 22:02:18'),
(2,107,'DCP_0789.jpg','20260924220218_c51e5d04.jpg','LOCAL','uploads/20260924/20260924220218_c51e5d04.jpg','image/jpeg',413169,'jpg','4d9fd0e87a23174bb4cf55592903af1b74f5853e50da317ff1f2aaf394dcb09f','ACTIVE',NULL,'2026-09-24 22:02:18','2026-09-24 22:02:18'),
(3,107,'DCP_0790.jpg','20260924220218_deb65a0c.jpg','LOCAL','uploads/20260924/20260924220218_deb65a0c.jpg','image/jpeg',542542,'jpg','76c6c06ae8f293a8696024c4927c8103ae7a25a379f174c400d34044b5dc1a0e','ACTIVE',NULL,'2026-09-24 22:02:18','2026-09-24 22:02:18'),
(4,107,'DCP_0791.jpg','20260924220218_5b4f98fc.jpg','LOCAL','uploads/20260924/20260924220218_5b4f98fc.jpg','image/jpeg',533835,'jpg','b09fc73c5b7da25cd457cca18a7f6a2cee10ad06a64c231f420f4d821303f79f','ACTIVE',NULL,'2026-09-24 22:02:18','2026-09-24 22:02:18'),
(5,111,'DCP_0785.jpg','20260926201337_a5c4a47c.jpg','LOCAL','uploads/20260926/20260926201337_a5c4a47c.jpg','image/jpeg',141396,'jpg','7903f05ac000307b59cea3a84181bcba1e57afab6de8b6193fcbf70fcfc6cc4a','ACTIVE',NULL,'2026-09-26 20:13:37','2026-09-26 20:13:37'),
(8,111,'DCP_0789.jpg','20260926201337_b0a00281.jpg','LOCAL','uploads/20260926/20260926201337_b0a00281.jpg','image/jpeg',413169,'jpg','4d9fd0e87a23174bb4cf55592903af1b74f5853e50da317ff1f2aaf394dcb09f','ACTIVE',NULL,'2026-09-26 20:13:37','2026-09-26 20:13:37'),
(10,110,'DCP_0790.jpg','20260926204543_bb18be19.jpg','LOCAL','uploads/20260926/20260926204543_bb18be19.jpg','image/jpeg',542542,'jpg','76c6c06ae8f293a8696024c4927c8103ae7a25a379f174c400d34044b5dc1a0e','ACTIVE',NULL,'2026-09-26 20:45:43','2026-09-26 20:45:43'),
(11,111,'DCP_0814.jpg','20260927202645_e593e3bd.jpg','LOCAL','uploads/20260927/20260927202645_e593e3bd.jpg','image/jpeg',238146,'jpg','6c68c83192b43134685ff8e82c3811cabed1901e0daee31f04c164e1a2de0dba','ACTIVE',NULL,'2026-09-27 20:26:45','2026-09-27 20:26:45'),
(12,111,'DCP_0815.jpg','20260927202645_834af2e3.jpg','LOCAL','uploads/20260927/20260927202645_834af2e3.jpg','image/jpeg',314967,'jpg','814cb434f609245c9b4aa3926037d7823de00b6409d406c02f94aadb77ba0063','ACTIVE',NULL,'2026-09-27 20:26:45','2026-09-27 20:26:45'),
(13,111,'DCP_0816.jpg','20260927202645_9d36158c.jpg','LOCAL','uploads/20260927/20260927202645_9d36158c.jpg','image/jpeg',306025,'jpg','3d445c6d7f1725a57f76e7f91aab37d5f09d724aa99c8ada5e7c04d8a4a2a884','ACTIVE',NULL,'2026-09-27 20:26:46','2026-09-27 20:26:46'),
(14,113,'DCP_0849.jpg','20260927211130_522056eb.jpg','LOCAL','uploads/20260927/20260927211130_522056eb.jpg','image/jpeg',136380,'jpg','4ddfcf394b03d9c707dc152feb8c169637dbaf0f8fe75386444828db8ad53dc8','ACTIVE',NULL,'2026-09-27 21:11:30','2026-09-27 21:11:30'),
(15,113,'DCP_0998.jpg','20260927211130_daea3a09.jpg','LOCAL','uploads/20260927/20260927211130_daea3a09.jpg','image/jpeg',465003,'jpg','cea1be526cdfc75d9c73c36f691e9c05d388f0eb11083ced329144c82893f366','ACTIVE',NULL,'2026-09-27 21:11:30','2026-09-27 21:11:30'),
(16,113,'DCP_1000.jpg','20260927211130_c89fa6db.jpg','LOCAL','uploads/20260927/20260927211130_c89fa6db.jpg','image/jpeg',91118,'jpg','f2b3e035c9ce4460f7e9bacde8ce029c96084b0a98e831ed59d86ccc8a009905','ACTIVE',NULL,'2026-09-27 21:11:30','2026-09-27 21:11:30'),
(17,113,'DSCN3069.JPG','20260927211151_fcdf33bd.jpg','LOCAL','uploads/20260927/20260927211151_fcdf33bd.jpg','image/jpeg',173672,'jpg','b001e514eb130ed10e26c6acdd671b41c9e0d774a697e602debd4f02742caaac','ACTIVE',NULL,'2026-09-27 21:11:51','2026-09-27 21:11:51'),
(18,113,'DSCN3070.JPG','20260927211151_6dd05be3.jpg','LOCAL','uploads/20260927/20260927211151_6dd05be3.jpg','image/jpeg',178995,'jpg','22ecda37e657f4b20aa61adba0e43da5e18e25ca9af2a69ea7bb7360397943b9','ACTIVE',NULL,'2026-09-27 21:11:51','2026-09-27 21:11:51'),
(19,114,'DCP_0998.jpg','20260927213120_35261254.jpg','LOCAL','uploads/20260927/20260927213120_35261254.jpg','image/jpeg',465003,'jpg','cea1be526cdfc75d9c73c36f691e9c05d388f0eb11083ced329144c82893f366','ACTIVE',NULL,'2026-09-27 21:31:20','2026-09-27 21:31:20'),
(21,114,'DCP_1018.jpg','20260927213120_f571c142.jpg','LOCAL','uploads/20260927/20260927213120_f571c142.jpg','image/jpeg',116137,'jpg','6abccfa5952f626243ea4ff0ba28ea7f4ee3ef298289808186c0619bed911e42','ACTIVE',NULL,'2026-09-27 21:31:20','2026-09-27 21:31:20'),
(22,114,'DSCN3070.JPG','20260927213158_72445e92.jpg','LOCAL','uploads/20260927/20260927213158_72445e92.jpg','image/jpeg',178995,'jpg','22ecda37e657f4b20aa61adba0e43da5e18e25ca9af2a69ea7bb7360397943b9','ACTIVE',NULL,'2026-09-27 21:31:58','2026-09-27 21:31:58'),
(23,114,'DSCN3071.JPG','20260927213158_f2c91344.jpg','LOCAL','uploads/20260927/20260927213158_f2c91344.jpg','image/jpeg',174293,'jpg','447807eb3466dc81cd3399b4d988cf543f128d8b38060481bb9fbef21c57fae4','ACTIVE',NULL,'2026-09-27 21:31:58','2026-09-27 21:31:58'),
(24,114,'DCP_0815.jpg','20260929195805_70f7d4a8.jpg','LOCAL','uploads/20260929/20260929195805_70f7d4a8.jpg','image/jpeg',314967,'jpg','814cb434f609245c9b4aa3926037d7823de00b6409d406c02f94aadb77ba0063','ACTIVE',NULL,'2026-09-29 19:58:05','2026-09-29 19:58:05'),
(35,126,'20261005_083606.jpg','20261005083822_8b86fa7e.jpg','LOCAL','uploads/20261005/20261005083822_8b86fa7e.jpg','image/jpeg',4461603,'jpg','3ae869e7c5983d92ad7434c6ceabe301fdb0333adfcb43f228e9c447fed35f03','ACTIVE',NULL,'2026-10-05 08:38:22','2026-10-05 08:38:22'),
(36,126,'20261005_083618.jpg','20261005083822_8c9f0ee7.jpg','LOCAL','uploads/20261005/20261005083822_8c9f0ee7.jpg','image/jpeg',4024996,'jpg','f9b80cced31f04f050f5868288895c49d47991862b39fa19ed73288df2376381','ACTIVE',NULL,'2026-10-05 08:38:22','2026-10-05 08:38:22'),
(37,126,'20261005_083555.jpg','20261005083822_98d74a55.jpg','LOCAL','uploads/20261005/20261005083822_98d74a55.jpg','image/jpeg',4407284,'jpg','38176e18196a63ce97d51b2886408ab7995388863b49d4a6be15717720adedcd','ACTIVE',NULL,'2026-10-05 08:38:22','2026-10-05 08:38:22'),
(38,-46,'17914559902883377161136269588298.jpg','0f817ec8c7dde9f09146e665b4fb2776.jpg','LOCAL','uploads/attachments/2026/10/0f817ec8c7dde9f09146e665b4fb2776.jpg','image/jpeg',3713579,'jpg','2cd0cb79b52c7c31bccc6288a2d24cc031c712cbbaedf762f549c4f905824046','ACTIVE',NULL,'2026-10-08 19:40:04','2026-10-08 19:40:04'),
(39,-48,'20261005_110830.jpg','d7f523e350aac2e15c74adf5bf86ab42.jpg','LOCAL','uploads/attachments/2026/10/d7f523e350aac2e15c74adf5bf86ab42.jpg','image/jpeg',1429675,'jpg','9181494221ba5188e51eeec85620b64aee14f5c4f7308500b291de69a24cdb81','DELETED',NULL,'2026-10-08 19:45:00','2026-10-08 20:51:16'),
(40,130,'17914564850987932798250235081395.jpg','20261008194813_93c44e5e.jpg','LOCAL','uploads/20261008/20261008194813_93c44e5e.jpg','image/jpeg',4188024,'jpg','3d2fbd78e436da98409af618c641aae53a0b4c1085d613af4f9427165223c804','DELETED',NULL,'2026-10-08 19:48:13','2026-10-09 12:15:34'),
(42,-47,'111111.JPG','e5c0cb28729ad7e54469d69a7da2f091.jpg','LOCAL','uploads/attachments/2026/10/e5c0cb28729ad7e54469d69a7da2f091.jpg','image/jpeg',87584,'jpg','35e4432f19a015fbf0366cfb19b0508bf021b8e913b5987dce80cb026bd86ee5','ACTIVE',NULL,'2026-10-08 20:38:44','2026-10-08 20:38:44'),
(43,-50,'111111.JPG','e5c0cb28729ad7e54469d69a7da2f091.jpg','LOCAL','uploads/attachments/2026/10/e5c0cb28729ad7e54469d69a7da2f091.jpg','image/jpeg',87584,'jpg','35e4432f19a015fbf0366cfb19b0508bf021b8e913b5987dce80cb026bd86ee5','ACTIVE',NULL,'2026-10-08 20:38:50','2026-10-08 20:38:50');
/*!40000 ALTER TABLE `attachment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attachment2`
--

DROP TABLE IF EXISTS `attachment2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attachment2` (
  `att_id` bigint(20) NOT NULL DEFAULT 0 COMMENT '첨부파일 ID',
  `att_grp_id` bigint(20) DEFAULT NULL COMMENT '첨부파일 그룹 ID',
  `original_name` varchar(255) NOT NULL COMMENT '원본 파일명',
  `stored_name` varchar(255) NOT NULL COMMENT '저장된 파일명',
  `storage_type` varchar(30) NOT NULL COMMENT '저장소 타입 (LOCAL, S3, GCS 등)',
  `storage_path` varchar(1000) NOT NULL COMMENT '저장 경로',
  `mime_type` varchar(255) DEFAULT NULL COMMENT 'MIME 타입',
  `file_size` bigint(20) unsigned NOT NULL COMMENT '파일 크기(Byte)',
  `extension` varchar(20) DEFAULT NULL COMMENT '파일 확장자',
  `checksum` varchar(128) DEFAULT NULL COMMENT '파일 체크섬(SHA-256 등)',
  `status` varchar(30) NOT NULL DEFAULT 'ACTIVE' COMMENT '파일 상태 (ACTIVE, DELETED 등)',
  `created_by` bigint(20) DEFAULT NULL COMMENT '등록자 ID',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attachment2`
--

LOCK TABLES `attachment2` WRITE;
/*!40000 ALTER TABLE `attachment2` DISABLE KEYS */;
INSERT INTO `attachment2` VALUES
(1,107,'DCP_0788.jpg','20260924220218_77938c12.jpg','LOCAL','uploads/20260924/20260924220218_77938c12.jpg','image/jpeg',449964,'jpg','eec6dc587cedb8ef82e8e282655572ebf15824ce24c6cd04f4598328e52e2cf8','ACTIVE',NULL,'2026-09-24 22:02:18','2026-09-24 22:02:18'),
(2,107,'DCP_0789.jpg','20260924220218_c51e5d04.jpg','LOCAL','uploads/20260924/20260924220218_c51e5d04.jpg','image/jpeg',413169,'jpg','4d9fd0e87a23174bb4cf55592903af1b74f5853e50da317ff1f2aaf394dcb09f','ACTIVE',NULL,'2026-09-24 22:02:18','2026-09-24 22:02:18'),
(3,107,'DCP_0790.jpg','20260924220218_deb65a0c.jpg','LOCAL','uploads/20260924/20260924220218_deb65a0c.jpg','image/jpeg',542542,'jpg','76c6c06ae8f293a8696024c4927c8103ae7a25a379f174c400d34044b5dc1a0e','ACTIVE',NULL,'2026-09-24 22:02:18','2026-09-24 22:02:18'),
(4,107,'DCP_0791.jpg','20260924220218_5b4f98fc.jpg','LOCAL','uploads/20260924/20260924220218_5b4f98fc.jpg','image/jpeg',533835,'jpg','b09fc73c5b7da25cd457cca18a7f6a2cee10ad06a64c231f420f4d821303f79f','ACTIVE',NULL,'2026-09-24 22:02:18','2026-09-24 22:02:18'),
(5,111,'DCP_0785.jpg','20260926201337_a5c4a47c.jpg','LOCAL','uploads/20260926/20260926201337_a5c4a47c.jpg','image/jpeg',141396,'jpg','7903f05ac000307b59cea3a84181bcba1e57afab6de8b6193fcbf70fcfc6cc4a','ACTIVE',NULL,'2026-09-26 20:13:37','2026-09-26 20:13:37'),
(8,111,'DCP_0789.jpg','20260926201337_b0a00281.jpg','LOCAL','uploads/20260926/20260926201337_b0a00281.jpg','image/jpeg',413169,'jpg','4d9fd0e87a23174bb4cf55592903af1b74f5853e50da317ff1f2aaf394dcb09f','ACTIVE',NULL,'2026-09-26 20:13:37','2026-09-26 20:13:37'),
(10,110,'DCP_0790.jpg','20260926204543_bb18be19.jpg','LOCAL','uploads/20260926/20260926204543_bb18be19.jpg','image/jpeg',542542,'jpg','76c6c06ae8f293a8696024c4927c8103ae7a25a379f174c400d34044b5dc1a0e','ACTIVE',NULL,'2026-09-26 20:45:43','2026-09-26 20:45:43'),
(11,111,'DCP_0814.jpg','20260927202645_e593e3bd.jpg','LOCAL','uploads/20260927/20260927202645_e593e3bd.jpg','image/jpeg',238146,'jpg','6c68c83192b43134685ff8e82c3811cabed1901e0daee31f04c164e1a2de0dba','ACTIVE',NULL,'2026-09-27 20:26:45','2026-09-27 20:26:45'),
(12,111,'DCP_0815.jpg','20260927202645_834af2e3.jpg','LOCAL','uploads/20260927/20260927202645_834af2e3.jpg','image/jpeg',314967,'jpg','814cb434f609245c9b4aa3926037d7823de00b6409d406c02f94aadb77ba0063','ACTIVE',NULL,'2026-09-27 20:26:45','2026-09-27 20:26:45'),
(13,111,'DCP_0816.jpg','20260927202645_9d36158c.jpg','LOCAL','uploads/20260927/20260927202645_9d36158c.jpg','image/jpeg',306025,'jpg','3d445c6d7f1725a57f76e7f91aab37d5f09d724aa99c8ada5e7c04d8a4a2a884','ACTIVE',NULL,'2026-09-27 20:26:46','2026-09-27 20:26:46'),
(14,113,'DCP_0849.jpg','20260927211130_522056eb.jpg','LOCAL','uploads/20260927/20260927211130_522056eb.jpg','image/jpeg',136380,'jpg','4ddfcf394b03d9c707dc152feb8c169637dbaf0f8fe75386444828db8ad53dc8','ACTIVE',NULL,'2026-09-27 21:11:30','2026-09-27 21:11:30'),
(15,113,'DCP_0998.jpg','20260927211130_daea3a09.jpg','LOCAL','uploads/20260927/20260927211130_daea3a09.jpg','image/jpeg',465003,'jpg','cea1be526cdfc75d9c73c36f691e9c05d388f0eb11083ced329144c82893f366','ACTIVE',NULL,'2026-09-27 21:11:30','2026-09-27 21:11:30'),
(16,113,'DCP_1000.jpg','20260927211130_c89fa6db.jpg','LOCAL','uploads/20260927/20260927211130_c89fa6db.jpg','image/jpeg',91118,'jpg','f2b3e035c9ce4460f7e9bacde8ce029c96084b0a98e831ed59d86ccc8a009905','ACTIVE',NULL,'2026-09-27 21:11:30','2026-09-27 21:11:30'),
(17,113,'DSCN3069.JPG','20260927211151_fcdf33bd.jpg','LOCAL','uploads/20260927/20260927211151_fcdf33bd.jpg','image/jpeg',173672,'jpg','b001e514eb130ed10e26c6acdd671b41c9e0d774a697e602debd4f02742caaac','ACTIVE',NULL,'2026-09-27 21:11:51','2026-09-27 21:11:51'),
(18,113,'DSCN3070.JPG','20260927211151_6dd05be3.jpg','LOCAL','uploads/20260927/20260927211151_6dd05be3.jpg','image/jpeg',178995,'jpg','22ecda37e657f4b20aa61adba0e43da5e18e25ca9af2a69ea7bb7360397943b9','ACTIVE',NULL,'2026-09-27 21:11:51','2026-09-27 21:11:51'),
(19,114,'DCP_0998.jpg','20260927213120_35261254.jpg','LOCAL','uploads/20260927/20260927213120_35261254.jpg','image/jpeg',465003,'jpg','cea1be526cdfc75d9c73c36f691e9c05d388f0eb11083ced329144c82893f366','ACTIVE',NULL,'2026-09-27 21:31:20','2026-09-27 21:31:20'),
(21,114,'DCP_1018.jpg','20260927213120_f571c142.jpg','LOCAL','uploads/20260927/20260927213120_f571c142.jpg','image/jpeg',116137,'jpg','6abccfa5952f626243ea4ff0ba28ea7f4ee3ef298289808186c0619bed911e42','ACTIVE',NULL,'2026-09-27 21:31:20','2026-09-27 21:31:20'),
(22,114,'DSCN3070.JPG','20260927213158_72445e92.jpg','LOCAL','uploads/20260927/20260927213158_72445e92.jpg','image/jpeg',178995,'jpg','22ecda37e657f4b20aa61adba0e43da5e18e25ca9af2a69ea7bb7360397943b9','ACTIVE',NULL,'2026-09-27 21:31:58','2026-09-27 21:31:58'),
(23,114,'DSCN3071.JPG','20260927213158_f2c91344.jpg','LOCAL','uploads/20260927/20260927213158_f2c91344.jpg','image/jpeg',174293,'jpg','447807eb3466dc81cd3399b4d988cf543f128d8b38060481bb9fbef21c57fae4','ACTIVE',NULL,'2026-09-27 21:31:58','2026-09-27 21:31:58'),
(24,114,'DCP_0815.jpg','20260929195805_70f7d4a8.jpg','LOCAL','uploads/20260929/20260929195805_70f7d4a8.jpg','image/jpeg',314967,'jpg','814cb434f609245c9b4aa3926037d7823de00b6409d406c02f94aadb77ba0063','ACTIVE',NULL,'2026-09-29 19:58:05','2026-09-29 19:58:05');
/*!40000 ALTER TABLE `attachment2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `common_code`
--

DROP TABLE IF EXISTS `common_code`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `common_code` (
  `code_group` varchar(500) NOT NULL COMMENT '공통코드 그룹',
  `code` varchar(50) NOT NULL COMMENT '공통코드',
  `code_nm` varchar(100) NOT NULL COMMENT '공통코드명',
  `description` varchar(500) DEFAULT NULL COMMENT '설명',
  `use_yn` char(1) NOT NULL DEFAULT 'Y' COMMENT '사용 여부',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '정렬 순서',
  `extra_value1` varchar(255) DEFAULT NULL COMMENT '확장값1',
  `extra_value2` varchar(255) DEFAULT NULL COMMENT '확장값2',
  `extra_value3` varchar(255) DEFAULT NULL COMMENT '확장값3',
  `extra_value4` varchar(255) DEFAULT NULL COMMENT '확장값4',
  `extra_value5` varchar(255) DEFAULT NULL COMMENT '확장값5',
  `created_by` bigint(20) DEFAULT NULL COMMENT '등록자 ID',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  `updated_by` bigint(20) DEFAULT NULL COMMENT '수정자 ID',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`code_group`,`code`),
  UNIQUE KEY `uk_common_code_group_code` (`code_group`,`code`),
  KEY `idx_common_code_group` (`code_group`),
  KEY `idx_common_code_use_yn` (`use_yn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='공통코드';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `common_code`
--

LOCK TABLES `common_code` WRITE;
/*!40000 ALTER TABLE `common_code` DISABLE KEYS */;
INSERT INTO `common_code` VALUES
('농약지속일','1','1','1','Y',1,'1일',NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:54:36'),
('농약지속일','3','3','3','Y',2,'3일',NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:54:36'),
('농약지속일','5','5','5','Y',3,'5일',NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:54:36'),
('농약지속일','7','7','7','Y',4,'7일',NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:54:36'),
('밭이벤트','1','물','물','Y',1,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('밭이벤트','2','수확','수확','Y',2,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('밭이벤트','3','옆순','옆순','Y',3,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('밭이벤트','4','농약','농약','Y',4,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('밭이벤트','5','병충해','병충해','Y',5,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('병충해','1','청벌래','청벌래','Y',1,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('병충해','2','녹병','녹병','Y',2,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('병충해','3','노균병','노균병','Y',3,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35');
/*!40000 ALTER TABLE `common_code` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `common_code2`
--

DROP TABLE IF EXISTS `common_code2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `common_code2` (
  `code_group` varchar(500) NOT NULL COMMENT '공통코드 그룹',
  `code` varchar(50) NOT NULL COMMENT '공통코드',
  `code_nm` varchar(100) NOT NULL COMMENT '공통코드명',
  `description` varchar(500) DEFAULT NULL COMMENT '설명',
  `use_yn` char(1) NOT NULL DEFAULT 'Y' COMMENT '사용 여부',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '정렬 순서',
  `extra_value1` varchar(255) DEFAULT NULL COMMENT '확장값1',
  `extra_value2` varchar(255) DEFAULT NULL COMMENT '확장값2',
  `extra_value3` varchar(255) DEFAULT NULL COMMENT '확장값3',
  `extra_value4` varchar(255) DEFAULT NULL COMMENT '확장값4',
  `extra_value5` varchar(255) DEFAULT NULL COMMENT '확장값5',
  `created_by` bigint(20) DEFAULT NULL COMMENT '등록자 ID',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  `updated_by` bigint(20) DEFAULT NULL COMMENT '수정자 ID',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `common_code2`
--

LOCK TABLES `common_code2` WRITE;
/*!40000 ALTER TABLE `common_code2` DISABLE KEYS */;
INSERT INTO `common_code2` VALUES
('농약지속일','1','1','1','Y',1,'1일',NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:54:36'),
('농약지속일','3','3','3','Y',2,'3일',NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:54:36'),
('농약지속일','5','5','5','Y',3,'5일',NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:54:36'),
('농약지속일','7','7','7','Y',4,'7일',NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:54:36'),
('밭이벤트','1','물','물','Y',1,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('밭이벤트','2','수확','수확','Y',2,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('밭이벤트','3','옆순','옆순','Y',3,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('밭이벤트','4','농약','농약','Y',4,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('밭이벤트','5','병충해','병충해','Y',5,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('병충해','1','청벌래','청벌래','Y',1,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('병충해','2','녹병','녹병','Y',2,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35'),
('병충해','3','노균병','노균병','Y',3,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 23:17:35',NULL,'2026-09-22 23:17:35');
/*!40000 ALTER TABLE `common_code2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dailytasksT`
--

DROP TABLE IF EXISTS `dailytasksT`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dailytasksT` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유 번호',
  `task_date` date NOT NULL COMMENT '할 일이 지정된 날짜',
  `content` text NOT NULL COMMENT '할 일 내용',
  `completion_status` varchar(20) DEFAULT NULL COMMENT '완료여부 (완료 또는 미뤄진 날짜 YYYY-MM-DD)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록일시',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='오늘의 할 일 관리 테이블';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dailytasksT`
--

LOCK TABLES `dailytasksT` WRITE;
/*!40000 ALTER TABLE `dailytasksT` DISABLE KEYS */;
INSERT INTO `dailytasksT` VALUES
(30,'2026-10-04','겨울 1중 비닐 자동화','2026-10-05','2026-10-03 18:59:45'),
(31,'2026-10-04','겨울 수확 d ','완료','2026-10-03 19:00:09'),
(32,'2026-10-04','공구 정리','2026-10-05','2026-10-03 19:00:29'),
(33,'2026-10-04','겨울 부직포','완료','2026-10-03 19:01:37'),
(34,'2026-10-05','공구 정리','2026-10-08','2026-10-04 22:47:45'),
(35,'2026-10-05','겨울 1중 비닐 자동화','2026-10-08','2026-10-04 22:47:53'),
(36,'2026-10-05','물고임 정리','2026-10-07','2026-10-04 22:48:29'),
(37,'2026-10-05','겨울밭 C D 약 줘야 함','완료','2026-10-05 08:33:55'),
(38,'2026-10-07','물고임 정리','2026-10-09','2026-10-05 10:25:38'),
(39,'2026-10-08','겨울 1중 비닐 자동화','완료','2026-10-05 11:34:07'),
(40,'2026-10-08','공구 정리','완료','2026-10-05 11:34:26'),
(42,'2026-10-08','겨울 소독 ab','2026-10-09','2026-10-07 20:15:16'),
(43,'2026-10-08','겨울 물 고임 정리','2026-10-09','2026-10-07 20:15:50'),
(44,'2026-10-09','겨울 물 고임 정리','2026-10-10','2026-10-08 19:38:01'),
(45,'2026-10-09','겨울 소독 ab','완료','2026-10-08 19:38:11'),
(46,'2026-10-08','테스트','2026-10-09','2026-10-08 19:40:03'),
(47,'2026-10-09','테스트','2026-10-10','2026-10-08 19:40:47'),
(48,'2026-10-08','테스트2','2026-10-09','2026-10-08 19:45:00'),
(51,'2026-10-10','겨울 물 고임 정리','2026-10-11','2026-10-09 11:09:37'),
(52,'2026-10-09','수확 : 겨울cd, 갑a','2026-10-10','2026-10-09 12:13:20'),
(53,'2026-10-10','수확 : 겨울cd, 갑a','완료','2026-10-09 12:13:40'),
(54,'2026-10-11','겨울 물 고임 정리','','2026-10-10 14:25:22'),
(55,'2026-10-11','수확 : 갑a','','2026-10-10 14:25:54');
/*!40000 ALTER TABLE `dailytasksT` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dailytasksT2`
--

DROP TABLE IF EXISTS `dailytasksT2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dailytasksT2` (
  `id` int(11) NOT NULL DEFAULT 0 COMMENT '고유 번호',
  `task_date` date NOT NULL COMMENT '할 일이 지정된 날짜',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '할 일 내용',
  `completion_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '완료여부 (완료 또는 미뤄진 날짜 YYYY-MM-DD)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록일시'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dailytasksT2`
--

LOCK TABLES `dailytasksT2` WRITE;
/*!40000 ALTER TABLE `dailytasksT2` DISABLE KEYS */;
INSERT INTO `dailytasksT2` VALUES
(1,'2026-09-20','새할일','2026-09-24','2026-09-20 21:58:33'),
(3,'2026-09-20','상하 소독','2026-09-21','2026-09-20 22:20:36'),
(4,'2026-09-21','상하겨 소독','완료','2026-09-20 22:20:59'),
(5,'2026-09-21','모기장 구입','완료','2026-09-21 06:11:46'),
(6,'2026-09-21','오토바이 관련','완료','2026-09-21 06:12:06'),
(7,'2026-09-21','박스 스티커','2026-09-22','2026-09-21 06:12:31'),
(8,'2026-09-21','다이소. 호일 물티슈','완료','2026-09-21 06:13:04'),
(9,'2026-09-21','겨밭 입구 비닐','완료','2026-09-21 06:16:38'),
(15,'2026-09-22','박스 스티커','2026-09-26','2026-09-21 21:18:37'),
(20,'2026-09-23','겨울밭 옆순 정리 : 1 2 3 단까지...','완료','2026-09-22 19:31:47'),
(23,'2026-09-26','수확 갑? 병?','','2026-09-25 06:44:18'),
(24,'2026-09-25','겨밭 정리','','2026-09-25 06:44:46'),
(25,'2026-09-26','박스 스티커','','2026-09-25 06:45:24'),
(26,'2026-09-27','수확 갑? 병?','','2026-09-25 06:46:28'),
(27,'2026-09-29','겨울밭 수확','','2026-09-29 05:24:09'),
(28,'2026-09-29','병밭 소독','','2026-09-29 05:24:33'),
(30,'2026-10-04','겨울 1중 비닐 자동화','','2026-10-03 18:59:45'),
(31,'2026-10-04','겨울 수확 d ','완료','2026-10-03 19:00:09'),
(32,'2026-10-04','공구 정리','','2026-10-03 19:00:29'),
(33,'2026-10-04','겨울 부직포','완료','2026-10-03 19:01:37');
/*!40000 ALTER TABLE `dailytasksT2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `farms`
--

DROP TABLE IF EXISTS `farms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farms` (
  `farm_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '밭ID',
  `farm_name` varchar(100) NOT NULL COMMENT '밭이름',
  `row_count` int(11) NOT NULL DEFAULT 0 COMMENT '행갯수',
  `col_count` int(11) NOT NULL DEFAULT 0 COMMENT '열갯수',
  `farm_type` enum('노지','하우스') NOT NULL DEFAULT '노지' COMMENT '유형(노지,하우스)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록일시',
  `use_yn` char(1) NOT NULL DEFAULT 'Y' COMMENT '사용 여부',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '정렬순서',
  PRIMARY KEY (`farm_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='밭 정보';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `farms`
--

LOCK TABLES `farms` WRITE;
/*!40000 ALTER TABLE `farms` DISABLE KEYS */;
INSERT INTO `farms` VALUES
(10,'갑',2,10,'노지','2026-09-23 17:36:12','Y',3),
(11,'병',4,10,'노지','2026-09-23 17:36:28','Y',5),
(12,'겨울',4,10,'노지','2026-09-23 17:36:42','Y',1),
(13,'을',2,10,'노지','2026-10-08 19:46:27','N',4);
/*!40000 ALTER TABLE `farms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `farms2`
--

DROP TABLE IF EXISTS `farms2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farms2` (
  `farm_id` int(11) NOT NULL DEFAULT 0 COMMENT '밭ID',
  `farm_name` varchar(100) NOT NULL COMMENT '밭이름',
  `row_count` int(11) NOT NULL DEFAULT 0 COMMENT '행갯수',
  `col_count` int(11) NOT NULL DEFAULT 0 COMMENT '열갯수',
  `farm_type` enum('노지','하우스') NOT NULL DEFAULT '노지' COMMENT '유형(노지,하우스)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록일시',
  `use_yn` char(1) NOT NULL DEFAULT 'Y' COMMENT '사용 여부'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `farms2`
--

LOCK TABLES `farms2` WRITE;
/*!40000 ALTER TABLE `farms2` DISABLE KEYS */;
INSERT INTO `farms2` VALUES
(10,'갑',2,10,'노지','2026-09-23 17:36:12','Y'),
(11,'병',4,10,'노지','2026-09-23 17:36:28','Y'),
(12,'겨울',4,10,'노지','2026-09-23 17:36:42','Y');
/*!40000 ALTER TABLE `farms2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `memoT`
--

DROP TABLE IF EXISTS `memoT`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `memoT` (
  `group_id` varchar(50) NOT NULL COMMENT '그룹 ID (문자형)',
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유 식별자 ID',
  `reg_date` datetime DEFAULT current_timestamp() COMMENT '날짜 (기본값: 현재 시간)',
  `content` text DEFAULT NULL COMMENT '내용',
  `att_grp_id` bigint(20) DEFAULT NULL COMMENT '첨부파일 그룹 ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `memoT`
--

LOCK TABLES `memoT` WRITE;
/*!40000 ALTER TABLE `memoT` DISABLE KEYS */;
INSERT INTO `memoT` VALUES
('memo',128,'2026-10-08 19:42:01','오늘의할일 작성중 사진 등 등록하고 이관시 첨부 파일 사라짐.',NULL),
('memo',129,'2026-10-08 19:47:27','갑 병 겨울, 을 작성시 밭관리에서 소트?  -> 해결됨 ?',NULL),
('memo',131,'2026-10-08 19:49:50','을 메모 상세정보에서만 사진 확인시 깜빡 거림. 겨울도 다른 것도?!\r\n메모장에서는 이상 없음',NULL),
('memo',136,'2026-10-08 19:55:18','밭관리 맨아래..안 보임\r\n\r\n메모장 스크롤? 이상해',NULL),
('memo',137,'2026-10-08 19:55:46','스크롤 테스트',NULL),
('memo',139,'2026-10-08 20:03:33','벌레로 수정\r\n\r\n1 병해\r\n2 충해\r\n로 구분 요망',NULL),
('memo',140,'2026-10-08 20:04:30','달력 병 아이콘 차?',NULL),
('memo',141,'2026-10-08 20:05:52','약 가군1 ?',NULL),
('12',149,'2026-10-10 16:11:32','D 12 456에 토사 추가\r\nC 12 1수레 토사 추가\r\nD 78 45678 토사 추가\r\nD 90 7 토사 양쪽 추가',NULL);
/*!40000 ALTER TABLE `memoT` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `memoT2`
--

DROP TABLE IF EXISTS `memoT2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `memoT2` (
  `group_id` varchar(50) NOT NULL COMMENT '그룹 ID (문자형)',
  `id` int(11) NOT NULL DEFAULT 0 COMMENT '고유 식별자 ID',
  `reg_date` datetime DEFAULT current_timestamp() COMMENT '날짜 (기본값: 현재 시간)',
  `content` text DEFAULT NULL COMMENT '내용',
  `att_grp_id` bigint(20) DEFAULT NULL COMMENT '첨부파일 그룹 ID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `memoT2`
--

LOCK TABLES `memoT2` WRITE;
/*!40000 ALTER TABLE `memoT2` DISABLE KEYS */;
INSERT INTO `memoT2` VALUES
('memo',24,'2026-09-15 22:17:04','여기에는 각 밭에서 작성된 메세지가 다 나와야 하지 않나?',NULL),
('memo',26,'2026-09-16 04:10:13','전체 메모장에 밭관리 클릭. 3선버튼?\r\n밭 관리, 메모장 메뉴를 상단에 표시했음',NULL),
('memo',27,'2026-09-16 04:11:24','밭관리 입장시 암것도 않나옴\r\n수정했음. 아직 미흡',NULL),
('memo',28,'2026-09-16 04:12:20','각 밭 메세지 클릭시 내용 않뜸\r\n작업중',NULL),
('memo',30,'2026-09-16 04:14:41','각 밭 메세지 어케 지워?\r\n삭제기능 넣음',NULL),
('memo',32,'2026-09-16 04:16:07','메세지 수정 필요\r\n밭 메시지는 아직 수정기능 없음, 상세보기도 없음',NULL),
('memo',33,'2026-09-16 05:11:33','밭 상세관리 내역 삭제....',NULL),
('11',35,'2026-09-16 10:30:43','황병 오티바',NULL),
('memo',37,'2026-09-19 22:51:57','메모에 댓글 기능도 넣을까???',NULL),
('memo',42,'2026-09-20 11:16:16','밭 이름 갑을병정\r\n순서는 각 작업장 위치에서 바라보는 오른쪽에서 왼쪽으로',NULL),
('memo',51,'2026-09-20 16:52:33','비닐 보수. 입구 비닐작업',NULL),
('memo',52,'2026-09-20 16:52:55','저녁에 퇴근해서 오토바이 연수 및 인수',NULL),
('memo',63,'2026-09-21 06:17:56','밭관리 메모 전체? 스크롤 않됨',NULL),
('memo',70,'2026-09-21 06:23:25','오늘의할일 좌우 화살표로 오늘 지정 불가',NULL),
('12',72,'2026-09-21 08:35:36','나군 농약과 칼슘 녹병 잘록병 노균',NULL),
('12',73,'2026-09-21 09:02:08','6_a 끝 부분 모종 바꿈. 자라는거 관찰',NULL),
('memo',83,'2026-09-22 05:53:32','받 관리 상세에 이랑별 상세정보 못가져옴',NULL),
('memo',84,'2026-09-22 07:01:54','밭관리 상세에서 하단 메모 부분 마지막까지 스크롤이 안내려감',NULL),
('memo',85,'2026-09-22 07:04:55','밭 설정에서 삭제기능 추가. 노출 시킬지여부 항목 추가. 노출 안시킨 밭(과거 밭) 이력조회 페이지 필요. 밭 이력을 연도별로 새로 가져갈건지 이어서 가져갈건지도 확인 필요',NULL),
('memo',87,'2026-09-22 22:49:04','받 관리 상세에서 이랑별 이력 들어가서 이력 하나를 클릭하면 해당이력과 동일한 시기에 등록된 정보를 일괄로 수정하도록하는 팝업창 추가할 예정',NULL),
('memo',105,'2026-09-24 19:42:27','병충해 추가 입력',NULL);
/*!40000 ALTER TABLE `memoT2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ridge_records`
--

DROP TABLE IF EXISTS `ridge_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ridge_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '기록 PK',
  `farm_id` int(11) NOT NULL COMMENT '밭ID',
  `row_num` int(11) NOT NULL COMMENT '행',
  `col_num` int(11) NOT NULL COMMENT '열',
  `task_id` varchar(10) NOT NULL COMMENT '작업 ID',
  `work_date` date NOT NULL COMMENT '날짜',
  `check_status` char(1) NOT NULL DEFAULT 'N' COMMENT '체크상태 (Y/N)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록일시',
  `task_type` varchar(20) DEFAULT NULL COMMENT '작업 타입',
  `content` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1483 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='이랑 작업 기록';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ridge_records`
--

LOCK TABLES `ridge_records` WRITE;
/*!40000 ALTER TABLE `ridge_records` DISABLE KEYS */;
INSERT INTO `ridge_records` VALUES
(1123,12,1,1,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1124,12,1,2,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1125,12,1,3,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1126,12,1,4,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1127,12,2,1,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1128,12,2,2,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1129,12,2,3,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1130,12,2,4,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1131,12,3,1,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1132,12,3,2,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1133,12,3,3,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1134,12,3,4,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1135,12,4,1,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1136,12,4,2,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1137,12,4,3,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1138,12,4,4,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1139,12,5,1,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1140,12,5,2,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1141,12,5,3,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1142,12,5,4,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1143,12,6,1,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1144,12,6,2,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1145,12,6,3,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1146,12,6,4,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1147,12,7,1,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1148,12,7,2,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1149,12,7,3,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1150,12,7,4,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1151,12,8,1,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1152,12,8,2,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1153,12,8,3,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1154,12,8,4,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1155,12,9,1,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1156,12,9,2,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1157,12,9,3,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1158,12,9,4,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1159,12,10,1,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1160,12,10,2,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1161,12,10,3,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1162,12,10,4,'10','2026-10-07','Y','2026-10-07 20:43:03',NULL,''),
(1163,12,1,1,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1164,12,1,2,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1165,12,1,3,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1166,12,1,4,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1167,12,2,1,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1168,12,2,2,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1169,12,2,3,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1170,12,2,4,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1171,12,3,1,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1172,12,3,2,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1173,12,3,3,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1174,12,3,4,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1175,12,4,1,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1176,12,4,2,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1177,12,4,3,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1178,12,4,4,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1179,12,5,1,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1180,12,5,2,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1181,12,5,3,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1182,12,5,4,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1183,12,6,1,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1184,12,6,2,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1185,12,6,3,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1186,12,6,4,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1187,12,7,1,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1188,12,7,2,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1189,12,7,3,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1190,12,7,4,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1191,12,8,1,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1192,12,8,2,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1193,12,8,3,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1194,12,8,4,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1195,12,9,1,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1196,12,9,2,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1197,12,9,3,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1198,12,9,4,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1199,12,10,1,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1200,12,10,2,'20','2026-10-07','Y','2026-10-07 20:43:09',NULL,''),
(1201,12,10,3,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1202,12,10,4,'20','2026-10-07','N','2026-10-07 20:43:09',NULL,''),
(1203,13,1,1,'70','2026-10-08','Y','2026-10-08 19:58:41',NULL,'녹병?'),
(1204,13,1,2,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1205,13,2,1,'70','2026-10-08','Y','2026-10-08 19:58:41',NULL,'녹병?'),
(1206,13,2,2,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1207,13,3,1,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1208,13,3,2,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1209,13,4,1,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1210,13,4,2,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1211,13,5,1,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1212,13,5,2,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1213,13,6,1,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1214,13,6,2,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1215,13,7,1,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1216,13,7,2,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1217,13,8,1,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1218,13,8,2,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1219,13,9,1,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1220,13,9,2,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1221,13,10,1,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1222,13,10,2,'70','2026-10-08','N','2026-10-08 19:58:41',NULL,'녹병?'),
(1223,13,1,1,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1224,13,1,2,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1225,13,2,1,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1226,13,2,2,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1227,13,3,1,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1228,13,3,2,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1229,13,4,1,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1230,13,4,2,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1231,13,5,1,'70','2026-10-08','Y','2026-10-08 20:26:48',NULL,''),
(1232,13,5,2,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1233,13,6,1,'70','2026-10-08','Y','2026-10-08 20:26:48',NULL,''),
(1234,13,6,2,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1235,13,7,1,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1236,13,7,2,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1237,13,8,1,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1238,13,8,2,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1239,13,9,1,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1240,13,9,2,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1241,13,10,1,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1242,13,10,2,'70','2026-10-08','N','2026-10-08 20:26:48',NULL,''),
(1243,10,1,1,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1244,10,1,2,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1245,10,2,1,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1246,10,2,2,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1247,10,3,1,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1248,10,3,2,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1249,10,4,1,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1250,10,4,2,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1251,10,5,1,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1252,10,5,2,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1253,10,6,1,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1254,10,6,2,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1255,10,7,1,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1256,10,7,2,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1257,10,8,1,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1258,10,8,2,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1259,10,9,1,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1260,10,9,2,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1261,10,10,1,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1262,10,10,2,'40','2026-10-08','N','2026-10-08 20:50:53',NULL,''),
(1263,12,1,1,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1264,12,1,2,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1265,12,1,3,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1266,12,1,4,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1267,12,2,1,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1268,12,2,2,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1269,12,2,3,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1270,12,2,4,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1271,12,3,1,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1272,12,3,2,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1273,12,3,3,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1274,12,3,4,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1275,12,4,1,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1276,12,4,2,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1277,12,4,3,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1278,12,4,4,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1279,12,5,1,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1280,12,5,2,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1281,12,5,3,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1282,12,5,4,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1283,12,6,1,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1284,12,6,2,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1285,12,6,3,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1286,12,6,4,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1287,12,7,1,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1288,12,7,2,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1289,12,7,3,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1290,12,7,4,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1291,12,8,1,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1292,12,8,2,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1293,12,8,3,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1294,12,8,4,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1295,12,9,1,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1296,12,9,2,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1297,12,9,3,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1298,12,9,4,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1299,12,10,1,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1300,12,10,2,'41','2026-10-09','Y','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1301,12,10,3,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1302,12,10,4,'41','2026-10-09','N','2026-10-09 11:09:09',NULL,'슈퍼펀치'),
(1303,12,1,1,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1304,12,1,2,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1305,12,1,3,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1306,12,1,4,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1307,12,2,1,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1308,12,2,2,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1309,12,2,3,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1310,12,2,4,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1311,12,3,1,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1312,12,3,2,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1313,12,3,3,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1314,12,3,4,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1315,12,4,1,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1316,12,4,2,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1317,12,4,3,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1318,12,4,4,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1319,12,5,1,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1320,12,5,2,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1321,12,5,3,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1322,12,5,4,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1323,12,6,1,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1324,12,6,2,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1325,12,6,3,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1326,12,6,4,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1327,12,7,1,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1328,12,7,2,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1329,12,7,3,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1330,12,7,4,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1331,12,8,1,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1332,12,8,2,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1333,12,8,3,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1334,12,8,4,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1335,12,9,1,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1336,12,9,2,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1337,12,9,3,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1338,12,9,4,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1339,12,10,1,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1340,12,10,2,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1341,12,10,3,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1342,12,10,4,'10','2026-10-09','Y','2026-10-09 12:12:19',NULL,''),
(1343,10,1,1,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1344,10,1,2,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1345,10,2,1,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1346,10,2,2,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1347,10,3,1,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1348,10,3,2,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1349,10,4,1,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1350,10,4,2,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1351,10,5,1,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1352,10,5,2,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1353,10,6,1,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1354,10,6,2,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1355,10,7,1,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1356,10,7,2,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1357,10,8,1,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1358,10,8,2,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1359,10,9,1,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1360,10,9,2,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1361,10,10,1,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1362,10,10,2,'10','2026-10-09','Y','2026-10-09 19:12:50',NULL,''),
(1363,12,1,1,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1364,12,1,2,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1365,12,1,3,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1366,12,1,4,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1367,12,2,1,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1368,12,2,2,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1369,12,2,3,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1370,12,2,4,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1371,12,3,1,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1372,12,3,2,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1373,12,3,3,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1374,12,3,4,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1375,12,4,1,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1376,12,4,2,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1377,12,4,3,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1378,12,4,4,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1379,12,5,1,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1380,12,5,2,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1381,12,5,3,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1382,12,5,4,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1383,12,6,1,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1384,12,6,2,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1385,12,6,3,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1386,12,6,4,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1387,12,7,1,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1388,12,7,2,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1389,12,7,3,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1390,12,7,4,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1391,12,8,1,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1392,12,8,2,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1393,12,8,3,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1394,12,8,4,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1395,12,9,1,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1396,12,9,2,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1397,12,9,3,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1398,12,9,4,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1399,12,10,1,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1400,12,10,2,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1401,12,10,3,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1402,12,10,4,'30','2026-10-10','N','2026-10-10 14:23:58',NULL,''),
(1403,12,1,1,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1404,12,1,2,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1405,12,1,3,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1406,12,1,4,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1407,12,2,1,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1408,12,2,2,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1409,12,2,3,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1410,12,2,4,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1411,12,3,1,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1412,12,3,2,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1413,12,3,3,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1414,12,3,4,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1415,12,4,1,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1416,12,4,2,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1417,12,4,3,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1418,12,4,4,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1419,12,5,1,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1420,12,5,2,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1421,12,5,3,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1422,12,5,4,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1423,12,6,1,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1424,12,6,2,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1425,12,6,3,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1426,12,6,4,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1427,12,7,1,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1428,12,7,2,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1429,12,7,3,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1430,12,7,4,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1431,12,8,1,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1432,12,8,2,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1433,12,8,3,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1434,12,8,4,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1435,12,9,1,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1436,12,9,2,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1437,12,9,3,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1438,12,9,4,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1439,12,10,1,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1440,12,10,2,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1441,12,10,3,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1442,12,10,4,'30','2026-10-10','N','2026-10-10 14:24:23',NULL,''),
(1443,12,1,1,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1444,12,1,2,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1445,12,1,3,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1446,12,1,4,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1447,12,2,1,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1448,12,2,2,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1449,12,2,3,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1450,12,2,4,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1451,12,3,1,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1452,12,3,2,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1453,12,3,3,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1454,12,3,4,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1455,12,4,1,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1456,12,4,2,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1457,12,4,3,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1458,12,4,4,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1459,12,5,1,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1460,12,5,2,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1461,12,5,3,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1462,12,5,4,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1463,12,6,1,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1464,12,6,2,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1465,12,6,3,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1466,12,6,4,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1467,12,7,1,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1468,12,7,2,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1469,12,7,3,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1470,12,7,4,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1471,12,8,1,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1472,12,8,2,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1473,12,8,3,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1474,12,8,4,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1475,12,9,1,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1476,12,9,2,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1477,12,9,3,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1478,12,9,4,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1479,12,10,1,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1480,12,10,2,'20','2026-10-10','N','2026-10-10 14:24:51',NULL,''),
(1481,12,10,3,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,''),
(1482,12,10,4,'20','2026-10-10','Y','2026-10-10 14:24:51',NULL,'');
/*!40000 ALTER TABLE `ridge_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ridge_records2`
--

DROP TABLE IF EXISTS `ridge_records2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ridge_records2` (
  `id` int(11) NOT NULL DEFAULT 0 COMMENT '기록 PK',
  `farm_id` int(11) NOT NULL COMMENT '밭ID',
  `row_num` int(11) NOT NULL COMMENT '행',
  `col_num` int(11) NOT NULL COMMENT '열',
  `task_id` int(11) NOT NULL COMMENT '작업ID',
  `work_date` date NOT NULL COMMENT '날짜',
  `check_status` char(1) NOT NULL DEFAULT 'N' COMMENT '체크상태 (Y/N)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록일시'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ridge_records2`
--

LOCK TABLES `ridge_records2` WRITE;
/*!40000 ALTER TABLE `ridge_records2` DISABLE KEYS */;
INSERT INTO `ridge_records2` VALUES
(210,2,1,3,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(211,2,1,4,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(212,2,2,3,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(213,2,2,4,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(214,2,3,3,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(215,2,3,4,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(216,2,4,3,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(217,2,4,4,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(218,2,5,3,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(219,2,5,4,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(220,2,6,3,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(221,2,6,4,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(222,2,7,3,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(223,2,7,4,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(224,2,8,3,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(225,2,8,4,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(226,2,9,3,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(227,2,9,4,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(228,2,10,3,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(229,2,10,4,1,'2026-09-22','Y','2026-09-22 21:01:01'),
(230,2,1,1,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(231,2,1,2,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(232,2,1,3,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(233,2,1,4,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(234,2,2,1,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(235,2,2,2,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(236,2,2,3,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(237,2,2,4,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(238,2,3,1,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(239,2,3,2,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(240,2,3,3,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(241,2,3,4,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(242,2,4,1,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(243,2,4,2,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(244,2,4,3,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(245,2,4,4,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(246,2,5,1,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(247,2,5,2,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(248,2,5,3,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(249,2,5,4,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(250,2,6,1,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(251,2,6,2,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(252,2,6,3,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(253,2,6,4,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(254,2,7,1,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(255,2,7,2,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(256,2,7,3,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(257,2,7,4,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(258,2,8,1,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(259,2,8,2,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(260,2,8,3,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(261,2,8,4,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(262,2,9,1,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(263,2,9,2,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(264,2,9,3,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(265,2,9,4,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(266,2,10,1,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(267,2,10,2,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(268,2,10,3,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(269,2,10,4,2,'2026-09-22','Y','2026-09-22 21:06:48'),
(270,2,1,3,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(271,2,1,4,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(272,2,2,3,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(273,2,2,4,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(274,2,3,3,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(275,2,3,4,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(276,2,4,3,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(277,2,4,4,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(278,2,5,3,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(279,2,5,4,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(280,2,6,3,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(281,2,6,4,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(282,2,7,3,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(283,2,7,4,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(284,2,8,3,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(285,2,8,4,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(286,2,9,3,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(287,2,9,4,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(288,2,10,3,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(289,2,10,4,1,'2026-09-22','Y','2026-09-22 21:07:17'),
(290,2,1,3,3,'2026-09-22','Y','2026-09-22 21:07:48'),
(291,2,2,3,3,'2026-09-22','Y','2026-09-22 21:07:48'),
(292,2,3,3,3,'2026-09-22','Y','2026-09-22 21:07:48'),
(293,2,4,3,3,'2026-09-22','Y','2026-09-22 21:07:48'),
(294,2,5,3,3,'2026-09-22','Y','2026-09-22 21:07:48'),
(295,2,6,3,3,'2026-09-22','Y','2026-09-22 21:07:48'),
(296,2,7,3,3,'2026-09-22','Y','2026-09-22 21:07:48'),
(297,2,8,3,3,'2026-09-22','Y','2026-09-22 21:07:48'),
(298,2,9,3,3,'2026-09-22','Y','2026-09-22 21:07:48'),
(299,2,10,3,3,'2026-09-22','Y','2026-09-22 21:07:48'),
(300,3,1,4,4,'2026-09-22','Y','2026-09-22 22:40:07'),
(301,3,2,4,4,'2026-09-22','Y','2026-09-22 22:40:07'),
(302,3,3,4,4,'2026-09-22','Y','2026-09-22 22:40:07'),
(303,3,4,4,4,'2026-09-22','Y','2026-09-22 22:40:07'),
(304,3,5,4,4,'2026-09-22','Y','2026-09-22 22:40:07'),
(305,3,6,4,4,'2026-09-22','Y','2026-09-22 22:40:07'),
(306,3,7,4,4,'2026-09-22','Y','2026-09-22 22:40:07'),
(307,3,8,4,4,'2026-09-22','Y','2026-09-22 22:40:07'),
(308,3,9,4,4,'2026-09-22','Y','2026-09-22 22:40:07'),
(309,3,10,4,4,'2026-09-22','Y','2026-09-22 22:40:07'),
(310,3,1,2,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(311,3,1,3,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(312,3,2,2,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(313,3,2,3,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(314,3,3,2,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(315,3,3,3,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(316,3,4,2,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(317,3,4,3,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(318,3,5,2,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(319,3,5,3,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(320,3,6,2,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(321,3,6,3,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(322,3,7,2,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(323,3,7,3,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(324,3,8,2,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(325,3,8,3,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(326,3,9,2,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(327,3,9,3,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(328,3,10,2,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(329,3,10,3,3,'2026-09-22','Y','2026-09-22 22:40:37'),
(330,3,2,1,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(331,3,3,1,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(332,3,4,1,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(333,3,5,1,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(334,3,5,2,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(335,3,5,3,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(336,3,5,4,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(337,3,6,1,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(338,3,6,2,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(339,3,6,3,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(340,3,6,4,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(341,3,7,1,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(342,3,7,2,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(343,3,7,3,3,'2026-09-23','Y','2026-09-23 17:38:58'),
(344,3,2,1,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(345,3,3,1,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(346,3,4,1,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(347,3,4,2,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(348,3,4,3,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(349,3,4,4,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(350,3,5,1,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(351,3,5,2,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(352,3,5,3,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(353,3,5,4,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(354,3,6,1,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(355,3,6,2,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(356,3,6,3,3,'2026-09-23','Y','2026-09-23 17:39:45'),
(357,12,1,1,4,'2026-09-23','N','2026-09-23 19:57:37'),
(358,12,2,1,4,'2026-09-23','N','2026-09-23 19:57:37'),
(359,12,1,2,4,'2026-09-23','N','2026-09-23 19:58:22'),
(360,12,2,2,4,'2026-09-23','N','2026-09-23 19:58:22'),
(361,12,3,2,4,'2026-09-23','N','2026-09-23 19:58:22'),
(362,12,1,2,7,'2026-09-23','N','2026-09-23 19:58:52'),
(363,12,2,2,7,'2026-09-23','N','2026-09-23 19:58:52'),
(364,12,3,2,7,'2026-09-23','N','2026-09-23 19:58:52'),
(365,10,1,2,9,'2026-09-23','N','2026-09-23 20:03:28'),
(366,10,5,2,9,'2026-09-23','N','2026-09-23 20:03:28'),
(367,12,2,1,3,'2026-09-23','N','2026-09-23 20:35:52'),
(368,12,3,1,3,'2026-09-23','N','2026-09-23 20:35:52'),
(369,12,4,1,3,'2026-09-23','N','2026-09-23 20:35:52'),
(370,12,4,2,3,'2026-09-23','N','2026-09-23 20:35:52'),
(371,12,4,3,3,'2026-09-23','N','2026-09-23 20:35:52'),
(372,12,4,4,3,'2026-09-23','N','2026-09-23 20:35:52'),
(373,12,5,1,3,'2026-09-23','N','2026-09-23 20:35:52'),
(374,12,5,2,3,'2026-09-23','N','2026-09-23 20:35:52'),
(375,12,5,3,3,'2026-09-23','N','2026-09-23 20:35:52'),
(376,12,5,4,3,'2026-09-23','N','2026-09-23 20:35:53'),
(377,12,6,1,3,'2026-09-23','N','2026-09-23 20:35:53'),
(378,12,6,2,3,'2026-09-23','N','2026-09-23 20:35:53'),
(379,12,6,3,3,'2026-09-23','N','2026-09-23 20:35:53'),
(380,10,2,2,3,'2026-09-23','N','2026-09-23 20:37:38'),
(381,10,3,2,3,'2026-09-23','N','2026-09-23 20:37:38'),
(382,10,5,2,3,'2026-09-23','N','2026-09-23 20:37:38'),
(383,10,1,1,2,'2026-09-23','N','2026-09-23 20:50:40'),
(384,10,1,2,2,'2026-09-23','N','2026-09-23 20:50:40'),
(385,10,2,1,2,'2026-09-23','N','2026-09-23 20:50:40'),
(386,10,2,2,2,'2026-09-23','N','2026-09-23 20:50:40'),
(387,10,3,1,2,'2026-09-23','N','2026-09-23 20:50:40'),
(388,10,3,2,2,'2026-09-23','N','2026-09-23 20:50:40'),
(389,10,4,1,2,'2026-09-23','N','2026-09-23 20:50:40'),
(390,10,4,2,2,'2026-09-23','N','2026-09-23 20:50:40'),
(391,10,5,1,2,'2026-09-23','N','2026-09-23 20:50:40'),
(392,10,5,2,2,'2026-09-23','N','2026-09-23 20:50:40'),
(393,10,6,1,2,'2026-09-23','N','2026-09-23 20:50:40'),
(394,10,6,2,2,'2026-09-23','N','2026-09-23 20:50:40'),
(395,10,7,1,2,'2026-09-23','N','2026-09-23 20:50:40'),
(396,10,7,2,2,'2026-09-23','N','2026-09-23 20:50:40'),
(397,10,8,1,2,'2026-09-23','N','2026-09-23 20:50:40'),
(398,10,8,2,2,'2026-09-23','N','2026-09-23 20:50:40'),
(399,10,9,1,2,'2026-09-23','N','2026-09-23 20:50:40'),
(400,10,9,2,2,'2026-09-23','N','2026-09-23 20:50:40'),
(401,10,10,1,2,'2026-09-23','N','2026-09-23 20:50:40'),
(402,10,10,2,2,'2026-09-23','N','2026-09-23 20:50:40'),
(403,10,1,1,3,'2026-09-24','N','2026-09-24 05:28:02'),
(404,10,1,2,3,'2026-09-24','N','2026-09-24 05:28:02'),
(405,10,2,1,3,'2026-09-24','N','2026-09-24 05:28:03'),
(406,10,2,2,3,'2026-09-24','N','2026-09-24 05:28:03'),
(407,10,3,1,3,'2026-09-24','N','2026-09-24 05:28:03'),
(408,10,3,2,3,'2026-09-24','N','2026-09-24 05:28:03'),
(409,10,4,1,3,'2026-09-24','Y','2026-09-24 05:28:03'),
(410,10,4,2,3,'2026-09-24','Y','2026-09-24 05:28:03'),
(411,10,5,1,3,'2026-09-24','Y','2026-09-24 05:28:03'),
(412,10,5,2,3,'2026-09-24','Y','2026-09-24 05:28:03'),
(413,10,6,1,3,'2026-09-24','Y','2026-09-24 05:28:03'),
(414,10,6,2,3,'2026-09-24','Y','2026-09-24 05:28:03'),
(415,10,7,1,3,'2026-09-24','Y','2026-09-24 05:28:03'),
(416,10,7,2,3,'2026-09-24','N','2026-09-24 05:28:03'),
(417,10,8,1,3,'2026-09-24','Y','2026-09-24 05:28:03'),
(418,10,8,2,3,'2026-09-24','N','2026-09-24 05:28:03'),
(419,10,9,1,3,'2026-09-24','Y','2026-09-24 05:28:03'),
(420,10,9,2,3,'2026-09-24','N','2026-09-24 05:28:03'),
(421,10,10,1,3,'2026-09-24','N','2026-09-24 05:28:03'),
(422,10,10,2,3,'2026-09-24','N','2026-09-24 05:28:03'),
(423,10,1,1,1,'2026-09-24','N','2026-09-24 05:30:43'),
(424,10,1,2,1,'2026-09-24','N','2026-09-24 05:30:43'),
(425,10,2,1,1,'2026-09-24','N','2026-09-24 05:30:43'),
(426,10,2,2,1,'2026-09-24','N','2026-09-24 05:30:43'),
(427,10,3,1,1,'2026-09-24','N','2026-09-24 05:30:43'),
(428,10,3,2,1,'2026-09-24','N','2026-09-24 05:30:43'),
(429,10,4,1,1,'2026-09-24','N','2026-09-24 05:30:43'),
(430,10,4,2,1,'2026-09-24','N','2026-09-24 05:30:44'),
(431,10,5,1,1,'2026-09-24','N','2026-09-24 05:30:44'),
(432,10,5,2,1,'2026-09-24','N','2026-09-24 05:30:44'),
(433,10,6,1,1,'2026-09-24','N','2026-09-24 05:30:44'),
(434,10,6,2,1,'2026-09-24','N','2026-09-24 05:30:44'),
(435,10,7,1,1,'2026-09-24','N','2026-09-24 05:30:44'),
(436,10,7,2,1,'2026-09-24','Y','2026-09-24 05:30:44'),
(437,10,8,1,1,'2026-09-24','N','2026-09-24 05:30:44'),
(438,10,8,2,1,'2026-09-24','N','2026-09-24 05:30:44'),
(439,10,9,1,1,'2026-09-24','N','2026-09-24 05:30:44'),
(440,10,9,2,1,'2026-09-24','N','2026-09-24 05:30:44'),
(441,10,10,1,1,'2026-09-24','N','2026-09-24 05:30:44'),
(442,10,10,2,1,'2026-09-24','N','2026-09-24 05:30:44'),
(443,10,1,1,3,'2026-09-24','Y','2026-09-24 05:30:51'),
(444,10,1,2,3,'2026-09-24','N','2026-09-24 05:30:51'),
(445,10,2,1,3,'2026-09-24','N','2026-09-24 05:30:51'),
(446,10,2,2,3,'2026-09-24','N','2026-09-24 05:30:51'),
(447,10,3,1,3,'2026-09-24','Y','2026-09-24 05:30:51'),
(448,10,3,2,3,'2026-09-24','N','2026-09-24 05:30:51'),
(449,10,4,1,3,'2026-09-24','Y','2026-09-24 05:30:51'),
(450,10,4,2,3,'2026-09-24','N','2026-09-24 05:30:51'),
(451,10,5,1,3,'2026-09-24','Y','2026-09-24 05:30:51'),
(452,10,5,2,3,'2026-09-24','N','2026-09-24 05:30:51'),
(453,10,6,1,3,'2026-09-24','N','2026-09-24 05:30:51'),
(454,10,6,2,3,'2026-09-24','N','2026-09-24 05:30:51'),
(455,10,7,1,3,'2026-09-24','N','2026-09-24 05:30:51'),
(456,10,7,2,3,'2026-09-24','N','2026-09-24 05:30:51'),
(457,10,8,1,3,'2026-09-24','N','2026-09-24 05:30:51'),
(458,10,8,2,3,'2026-09-24','N','2026-09-24 05:30:51'),
(459,10,9,1,3,'2026-09-24','N','2026-09-24 05:30:51'),
(460,10,9,2,3,'2026-09-24','N','2026-09-24 05:30:51'),
(461,10,10,1,3,'2026-09-24','N','2026-09-24 05:30:51'),
(462,10,10,2,3,'2026-09-24','N','2026-09-24 05:30:51'),
(463,10,1,1,2,'2026-09-24','N','2026-09-24 05:31:00'),
(464,10,1,2,2,'2026-09-24','Y','2026-09-24 05:31:00'),
(465,10,2,1,2,'2026-09-24','N','2026-09-24 05:31:00'),
(466,10,2,2,2,'2026-09-24','Y','2026-09-24 05:31:00'),
(467,10,3,1,2,'2026-09-24','N','2026-09-24 05:31:00'),
(468,10,3,2,2,'2026-09-24','Y','2026-09-24 05:31:00'),
(469,10,4,1,2,'2026-09-24','N','2026-09-24 05:31:00'),
(470,10,4,2,2,'2026-09-24','Y','2026-09-24 05:31:00'),
(471,10,5,1,2,'2026-09-24','N','2026-09-24 05:31:00'),
(472,10,5,2,2,'2026-09-24','Y','2026-09-24 05:31:00'),
(473,10,6,1,2,'2026-09-24','N','2026-09-24 05:31:00'),
(474,10,6,2,2,'2026-09-24','Y','2026-09-24 05:31:00'),
(475,10,7,1,2,'2026-09-24','N','2026-09-24 05:31:00'),
(476,10,7,2,2,'2026-09-24','Y','2026-09-24 05:31:00'),
(477,10,8,1,2,'2026-09-24','N','2026-09-24 05:31:00'),
(478,10,8,2,2,'2026-09-24','Y','2026-09-24 05:31:00'),
(479,10,9,1,2,'2026-09-24','N','2026-09-24 05:31:00'),
(480,10,9,2,2,'2026-09-24','Y','2026-09-24 05:31:00'),
(481,10,10,1,2,'2026-09-24','N','2026-09-24 05:31:00'),
(482,10,10,2,2,'2026-09-24','Y','2026-09-24 05:31:00'),
(483,10,1,1,4,'2026-09-24','N','2026-09-24 05:31:08'),
(484,10,1,2,4,'2026-09-24','Y','2026-09-24 05:31:08'),
(485,10,2,1,4,'2026-09-24','N','2026-09-24 05:31:08'),
(486,10,2,2,4,'2026-09-24','Y','2026-09-24 05:31:08'),
(487,10,3,1,4,'2026-09-24','N','2026-09-24 05:31:08'),
(488,10,3,2,4,'2026-09-24','Y','2026-09-24 05:31:08'),
(489,10,4,1,4,'2026-09-24','N','2026-09-24 05:31:08'),
(490,10,4,2,4,'2026-09-24','Y','2026-09-24 05:31:08'),
(491,10,5,1,4,'2026-09-24','N','2026-09-24 05:31:08'),
(492,10,5,2,4,'2026-09-24','Y','2026-09-24 05:31:08'),
(493,10,6,1,4,'2026-09-24','N','2026-09-24 05:31:08'),
(494,10,6,2,4,'2026-09-24','Y','2026-09-24 05:31:08'),
(495,10,7,1,4,'2026-09-24','N','2026-09-24 05:31:08'),
(496,10,7,2,4,'2026-09-24','Y','2026-09-24 05:31:08'),
(497,10,8,1,4,'2026-09-24','N','2026-09-24 05:31:08'),
(498,10,8,2,4,'2026-09-24','Y','2026-09-24 05:31:08'),
(499,10,9,1,4,'2026-09-24','N','2026-09-24 05:31:08'),
(500,10,9,2,4,'2026-09-24','Y','2026-09-24 05:31:08'),
(501,10,10,1,4,'2026-09-24','N','2026-09-24 05:31:08'),
(502,10,10,2,4,'2026-09-24','Y','2026-09-24 05:31:08'),
(503,10,1,1,8,'2026-09-24','N','2026-09-24 05:31:19'),
(504,10,1,2,8,'2026-09-24','N','2026-09-24 05:31:19'),
(505,10,2,1,8,'2026-09-24','N','2026-09-24 05:31:19'),
(506,10,2,2,8,'2026-09-24','N','2026-09-24 05:31:19'),
(507,10,3,1,8,'2026-09-24','N','2026-09-24 05:31:19'),
(508,10,3,2,8,'2026-09-24','N','2026-09-24 05:31:19'),
(509,10,4,1,8,'2026-09-24','N','2026-09-24 05:31:19'),
(510,10,4,2,8,'2026-09-24','N','2026-09-24 05:31:19'),
(511,10,5,1,8,'2026-09-24','N','2026-09-24 05:31:19'),
(512,10,5,2,8,'2026-09-24','N','2026-09-24 05:31:19'),
(513,10,6,1,8,'2026-09-24','N','2026-09-24 05:31:19'),
(514,10,6,2,8,'2026-09-24','N','2026-09-24 05:31:19'),
(515,10,7,1,8,'2026-09-24','N','2026-09-24 05:31:19'),
(516,10,7,2,8,'2026-09-24','N','2026-09-24 05:31:19'),
(517,10,8,1,8,'2026-09-24','N','2026-09-24 05:31:19'),
(518,10,8,2,8,'2026-09-24','N','2026-09-24 05:31:19'),
(519,10,9,1,8,'2026-09-24','N','2026-09-24 05:31:19'),
(520,10,9,2,8,'2026-09-24','N','2026-09-24 05:31:19'),
(521,10,10,1,8,'2026-09-24','N','2026-09-24 05:31:19'),
(522,10,10,2,8,'2026-09-24','N','2026-09-24 05:31:19'),
(523,10,1,1,10,'2026-09-24','Y','2026-09-24 20:51:46'),
(524,10,1,2,10,'2026-09-24','N','2026-09-24 20:51:46'),
(525,10,2,1,10,'2026-09-24','Y','2026-09-24 20:51:46'),
(526,10,2,2,10,'2026-09-24','N','2026-09-24 20:51:46'),
(527,10,3,1,10,'2026-09-24','Y','2026-09-24 20:51:46'),
(528,10,3,2,10,'2026-09-24','N','2026-09-24 20:51:46'),
(529,10,4,1,10,'2026-09-24','Y','2026-09-24 20:51:46'),
(530,10,4,2,10,'2026-09-24','N','2026-09-24 20:51:46'),
(531,10,5,1,10,'2026-09-24','Y','2026-09-24 20:51:46'),
(532,10,5,2,10,'2026-09-24','N','2026-09-24 20:51:46'),
(533,10,6,1,10,'2026-09-24','Y','2026-09-24 20:51:46'),
(534,10,6,2,10,'2026-09-24','N','2026-09-24 20:51:46'),
(535,10,7,1,10,'2026-09-24','Y','2026-09-24 20:51:46'),
(536,10,7,2,10,'2026-09-24','N','2026-09-24 20:51:46'),
(537,10,8,1,10,'2026-09-24','Y','2026-09-24 20:51:46'),
(538,10,8,2,10,'2026-09-24','N','2026-09-24 20:51:46'),
(539,10,9,1,10,'2026-09-24','Y','2026-09-24 20:51:46'),
(540,10,9,2,10,'2026-09-24','N','2026-09-24 20:51:46'),
(541,10,10,1,10,'2026-09-24','Y','2026-09-24 20:51:46'),
(542,10,10,2,10,'2026-09-24','N','2026-09-24 20:51:46'),
(543,12,1,1,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(544,12,1,2,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(545,12,1,3,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(546,12,1,4,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(547,12,2,1,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(548,12,2,2,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(549,12,2,3,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(550,12,2,4,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(551,12,3,1,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(552,12,3,2,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(553,12,3,3,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(554,12,3,4,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(555,12,4,1,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(556,12,4,2,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(557,12,4,3,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(558,12,4,4,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(559,12,5,1,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(560,12,5,2,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(561,12,5,3,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(562,12,5,4,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(563,12,6,1,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(564,12,6,2,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(565,12,6,3,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(566,12,6,4,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(567,12,7,1,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(568,12,7,2,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(569,12,7,3,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(570,12,7,4,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(571,12,8,1,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(572,12,8,2,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(573,12,8,3,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(574,12,8,4,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(575,12,9,1,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(576,12,9,2,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(577,12,9,3,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(578,12,9,4,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(579,12,10,1,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(580,12,10,2,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(581,12,10,3,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(582,12,10,4,3,'2026-09-25','Y','2026-09-25 06:43:37'),
(583,12,1,1,2,'2026-09-30','Y','2026-09-30 15:06:45'),
(584,12,1,2,2,'2026-09-30','N','2026-09-30 15:06:45'),
(585,12,1,3,2,'2026-09-30','N','2026-09-30 15:06:45'),
(586,12,1,4,2,'2026-09-30','N','2026-09-30 15:06:45'),
(587,12,2,1,2,'2026-09-30','Y','2026-09-30 15:06:45'),
(588,12,2,2,2,'2026-09-30','N','2026-09-30 15:06:45'),
(589,12,2,3,2,'2026-09-30','N','2026-09-30 15:06:45'),
(590,12,2,4,2,'2026-09-30','N','2026-09-30 15:06:45'),
(591,12,3,1,2,'2026-09-30','Y','2026-09-30 15:06:45'),
(592,12,3,2,2,'2026-09-30','N','2026-09-30 15:06:45'),
(593,12,3,3,2,'2026-09-30','N','2026-09-30 15:06:46'),
(594,12,3,4,2,'2026-09-30','N','2026-09-30 15:06:46'),
(595,12,4,1,2,'2026-09-30','N','2026-09-30 15:06:46'),
(596,12,4,2,2,'2026-09-30','N','2026-09-30 15:06:46'),
(597,12,4,3,2,'2026-09-30','N','2026-09-30 15:06:46'),
(598,12,4,4,2,'2026-09-30','N','2026-09-30 15:06:46'),
(599,12,5,1,2,'2026-09-30','N','2026-09-30 15:06:46'),
(600,12,5,2,2,'2026-09-30','N','2026-09-30 15:06:46'),
(601,12,5,3,2,'2026-09-30','N','2026-09-30 15:06:46'),
(602,12,5,4,2,'2026-09-30','N','2026-09-30 15:06:46'),
(603,12,6,1,2,'2026-09-30','N','2026-09-30 15:06:46'),
(604,12,6,2,2,'2026-09-30','N','2026-09-30 15:06:46'),
(605,12,6,3,2,'2026-09-30','N','2026-09-30 15:06:46'),
(606,12,6,4,2,'2026-09-30','N','2026-09-30 15:06:46'),
(607,12,7,1,2,'2026-09-30','Y','2026-09-30 15:06:46'),
(608,12,7,2,2,'2026-09-30','N','2026-09-30 15:06:46'),
(609,12,7,3,2,'2026-09-30','N','2026-09-30 15:06:46'),
(610,12,7,4,2,'2026-09-30','N','2026-09-30 15:06:46'),
(611,12,8,1,2,'2026-09-30','Y','2026-09-30 15:06:46'),
(612,12,8,2,2,'2026-09-30','N','2026-09-30 15:06:46'),
(613,12,8,3,2,'2026-09-30','N','2026-09-30 15:06:46'),
(614,12,8,4,2,'2026-09-30','N','2026-09-30 15:06:46'),
(615,12,9,1,2,'2026-09-30','Y','2026-09-30 15:06:46'),
(616,12,9,2,2,'2026-09-30','N','2026-09-30 15:06:46'),
(617,12,9,3,2,'2026-09-30','N','2026-09-30 15:06:46'),
(618,12,9,4,2,'2026-09-30','N','2026-09-30 15:06:46'),
(619,12,10,1,2,'2026-09-30','Y','2026-09-30 15:06:46'),
(620,12,10,2,2,'2026-09-30','N','2026-09-30 15:06:46'),
(621,12,10,3,2,'2026-09-30','N','2026-09-30 15:06:46'),
(622,12,10,4,2,'2026-09-30','N','2026-09-30 15:06:46'),
(623,10,1,1,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(624,10,1,2,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(625,10,2,1,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(626,10,2,2,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(627,10,3,1,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(628,10,3,2,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(629,10,4,1,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(630,10,4,2,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(631,10,5,1,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(632,10,5,2,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(633,10,6,1,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(634,10,6,2,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(635,10,7,1,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(636,10,7,2,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(637,10,8,1,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(638,10,8,2,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(639,10,9,1,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(640,10,9,2,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(641,10,10,1,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(642,10,10,2,1,'2026-10-01','Y','2026-10-01 08:05:14'),
(643,12,1,1,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(644,12,1,2,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(645,12,1,3,4,'2026-10-01','N','2026-10-01 16:56:21'),
(646,12,1,4,4,'2026-10-01','N','2026-10-01 16:56:21'),
(647,12,2,1,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(648,12,2,2,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(649,12,2,3,4,'2026-10-01','N','2026-10-01 16:56:21'),
(650,12,2,4,4,'2026-10-01','N','2026-10-01 16:56:21'),
(651,12,3,1,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(652,12,3,2,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(653,12,3,3,4,'2026-10-01','N','2026-10-01 16:56:21'),
(654,12,3,4,4,'2026-10-01','N','2026-10-01 16:56:21'),
(655,12,4,1,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(656,12,4,2,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(657,12,4,3,4,'2026-10-01','N','2026-10-01 16:56:21'),
(658,12,4,4,4,'2026-10-01','N','2026-10-01 16:56:21'),
(659,12,5,1,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(660,12,5,2,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(661,12,5,3,4,'2026-10-01','N','2026-10-01 16:56:21'),
(662,12,5,4,4,'2026-10-01','N','2026-10-01 16:56:21'),
(663,12,6,1,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(664,12,6,2,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(665,12,6,3,4,'2026-10-01','N','2026-10-01 16:56:21'),
(666,12,6,4,4,'2026-10-01','N','2026-10-01 16:56:21'),
(667,12,7,1,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(668,12,7,2,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(669,12,7,3,4,'2026-10-01','N','2026-10-01 16:56:21'),
(670,12,7,4,4,'2026-10-01','N','2026-10-01 16:56:21'),
(671,12,8,1,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(672,12,8,2,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(673,12,8,3,4,'2026-10-01','N','2026-10-01 16:56:21'),
(674,12,8,4,4,'2026-10-01','N','2026-10-01 16:56:21'),
(675,12,9,1,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(676,12,9,2,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(677,12,9,3,4,'2026-10-01','N','2026-10-01 16:56:21'),
(678,12,9,4,4,'2026-10-01','N','2026-10-01 16:56:21'),
(679,12,10,1,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(680,12,10,2,4,'2026-10-01','Y','2026-10-01 16:56:21'),
(681,12,10,3,4,'2026-10-01','N','2026-10-01 16:56:21'),
(682,12,10,4,4,'2026-10-01','N','2026-10-01 16:56:21'),
(683,12,1,1,2,'2026-10-01','N','2026-10-01 16:57:46'),
(684,12,1,2,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(685,12,1,3,2,'2026-10-01','N','2026-10-01 16:57:46'),
(686,12,1,4,2,'2026-10-01','N','2026-10-01 16:57:46'),
(687,12,2,1,2,'2026-10-01','N','2026-10-01 16:57:46'),
(688,12,2,2,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(689,12,2,3,2,'2026-10-01','N','2026-10-01 16:57:46'),
(690,12,2,4,2,'2026-10-01','N','2026-10-01 16:57:46'),
(691,12,3,1,2,'2026-10-01','N','2026-10-01 16:57:46'),
(692,12,3,2,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(693,12,3,3,2,'2026-10-01','N','2026-10-01 16:57:46'),
(694,12,3,4,2,'2026-10-01','N','2026-10-01 16:57:46'),
(695,12,4,1,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(696,12,4,2,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(697,12,4,3,2,'2026-10-01','N','2026-10-01 16:57:46'),
(698,12,4,4,2,'2026-10-01','N','2026-10-01 16:57:46'),
(699,12,5,1,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(700,12,5,2,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(701,12,5,3,2,'2026-10-01','N','2026-10-01 16:57:46'),
(702,12,5,4,2,'2026-10-01','N','2026-10-01 16:57:46'),
(703,12,6,1,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(704,12,6,2,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(705,12,6,3,2,'2026-10-01','N','2026-10-01 16:57:46'),
(706,12,6,4,2,'2026-10-01','N','2026-10-01 16:57:46'),
(707,12,7,1,2,'2026-10-01','N','2026-10-01 16:57:46'),
(708,12,7,2,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(709,12,7,3,2,'2026-10-01','N','2026-10-01 16:57:46'),
(710,12,7,4,2,'2026-10-01','N','2026-10-01 16:57:46'),
(711,12,8,1,2,'2026-10-01','N','2026-10-01 16:57:46'),
(712,12,8,2,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(713,12,8,3,2,'2026-10-01','N','2026-10-01 16:57:46'),
(714,12,8,4,2,'2026-10-01','N','2026-10-01 16:57:46'),
(715,12,9,1,2,'2026-10-01','N','2026-10-01 16:57:46'),
(716,12,9,2,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(717,12,9,3,2,'2026-10-01','N','2026-10-01 16:57:46'),
(718,12,9,4,2,'2026-10-01','N','2026-10-01 16:57:46'),
(719,12,10,1,2,'2026-10-01','N','2026-10-01 16:57:46'),
(720,12,10,2,2,'2026-10-01','Y','2026-10-01 16:57:46'),
(721,12,10,3,2,'2026-10-01','N','2026-10-01 16:57:46'),
(722,12,10,4,2,'2026-10-01','N','2026-10-01 16:57:46'),
(723,12,1,1,2,'2026-10-03','N','2026-10-03 18:57:29'),
(724,12,1,2,2,'2026-10-03','N','2026-10-03 18:57:29'),
(725,12,1,3,2,'2026-10-03','Y','2026-10-03 18:57:29'),
(726,12,1,4,2,'2026-10-03','N','2026-10-03 18:57:29'),
(727,12,2,1,2,'2026-10-03','N','2026-10-03 18:57:29'),
(728,12,2,2,2,'2026-10-03','N','2026-10-03 18:57:29'),
(729,12,2,3,2,'2026-10-03','Y','2026-10-03 18:57:29'),
(730,12,2,4,2,'2026-10-03','N','2026-10-03 18:57:29'),
(731,12,3,1,2,'2026-10-03','N','2026-10-03 18:57:29'),
(732,12,3,2,2,'2026-10-03','N','2026-10-03 18:57:29'),
(733,12,3,3,2,'2026-10-03','Y','2026-10-03 18:57:29'),
(734,12,3,4,2,'2026-10-03','N','2026-10-03 18:57:29'),
(735,12,4,1,2,'2026-10-03','N','2026-10-03 18:57:29'),
(736,12,4,2,2,'2026-10-03','N','2026-10-03 18:57:29'),
(737,12,4,3,2,'2026-10-03','Y','2026-10-03 18:57:29'),
(738,12,4,4,2,'2026-10-03','N','2026-10-03 18:57:29'),
(739,12,5,1,2,'2026-10-03','N','2026-10-03 18:57:29'),
(740,12,5,2,2,'2026-10-03','N','2026-10-03 18:57:29'),
(741,12,5,3,2,'2026-10-03','Y','2026-10-03 18:57:29'),
(742,12,5,4,2,'2026-10-03','N','2026-10-03 18:57:29'),
(743,12,6,1,2,'2026-10-03','N','2026-10-03 18:57:29'),
(744,12,6,2,2,'2026-10-03','N','2026-10-03 18:57:29'),
(745,12,6,3,2,'2026-10-03','Y','2026-10-03 18:57:29'),
(746,12,6,4,2,'2026-10-03','N','2026-10-03 18:57:29'),
(747,12,7,1,2,'2026-10-03','N','2026-10-03 18:57:29'),
(748,12,7,2,2,'2026-10-03','N','2026-10-03 18:57:29'),
(749,12,7,3,2,'2026-10-03','Y','2026-10-03 18:57:29'),
(750,12,7,4,2,'2026-10-03','N','2026-10-03 18:57:29'),
(751,12,8,1,2,'2026-10-03','N','2026-10-03 18:57:29'),
(752,12,8,2,2,'2026-10-03','N','2026-10-03 18:57:29'),
(753,12,8,3,2,'2026-10-03','Y','2026-10-03 18:57:29'),
(754,12,8,4,2,'2026-10-03','N','2026-10-03 18:57:29'),
(755,12,9,1,2,'2026-10-03','N','2026-10-03 18:57:29'),
(756,12,9,2,2,'2026-10-03','N','2026-10-03 18:57:29'),
(757,12,9,3,2,'2026-10-03','Y','2026-10-03 18:57:29'),
(758,12,9,4,2,'2026-10-03','N','2026-10-03 18:57:29'),
(759,12,10,1,2,'2026-10-03','N','2026-10-03 18:57:29'),
(760,12,10,2,2,'2026-10-03','N','2026-10-03 18:57:29'),
(761,12,10,3,2,'2026-10-03','Y','2026-10-03 18:57:29'),
(762,12,10,4,2,'2026-10-03','N','2026-10-03 18:57:29'),
(763,10,1,1,2,'2026-10-03','Y','2026-10-03 18:58:34'),
(764,10,1,2,2,'2026-10-03','N','2026-10-03 18:58:34'),
(765,10,2,1,2,'2026-10-03','Y','2026-10-03 18:58:34'),
(766,10,2,2,2,'2026-10-03','N','2026-10-03 18:58:34'),
(767,10,3,1,2,'2026-10-03','Y','2026-10-03 18:58:34'),
(768,10,3,2,2,'2026-10-03','N','2026-10-03 18:58:34'),
(769,10,4,1,2,'2026-10-03','Y','2026-10-03 18:58:34'),
(770,10,4,2,2,'2026-10-03','N','2026-10-03 18:58:34'),
(771,10,5,1,2,'2026-10-03','Y','2026-10-03 18:58:34'),
(772,10,5,2,2,'2026-10-03','N','2026-10-03 18:58:34'),
(773,10,6,1,2,'2026-10-03','Y','2026-10-03 18:58:34'),
(774,10,6,2,2,'2026-10-03','N','2026-10-03 18:58:34'),
(775,10,7,1,2,'2026-10-03','Y','2026-10-03 18:58:34'),
(776,10,7,2,2,'2026-10-03','N','2026-10-03 18:58:34'),
(777,10,8,1,2,'2026-10-03','Y','2026-10-03 18:58:34'),
(778,10,8,2,2,'2026-10-03','N','2026-10-03 18:58:34'),
(779,10,9,1,2,'2026-10-03','Y','2026-10-03 18:58:34'),
(780,10,9,2,2,'2026-10-03','N','2026-10-03 18:58:34'),
(781,10,10,1,2,'2026-10-03','Y','2026-10-03 18:58:34'),
(782,10,10,2,2,'2026-10-03','N','2026-10-03 18:58:34'),
(783,10,1,1,2,'2026-10-03','N','2026-10-03 18:59:02'),
(784,10,1,2,2,'2026-10-03','Y','2026-10-03 18:59:02'),
(785,10,2,1,2,'2026-10-03','N','2026-10-03 18:59:02'),
(786,10,2,2,2,'2026-10-03','Y','2026-10-03 18:59:02'),
(787,10,3,1,2,'2026-10-03','N','2026-10-03 18:59:02'),
(788,10,3,2,2,'2026-10-03','Y','2026-10-03 18:59:02'),
(789,10,4,1,2,'2026-10-03','N','2026-10-03 18:59:02'),
(790,10,4,2,2,'2026-10-03','Y','2026-10-03 18:59:02'),
(791,10,5,1,2,'2026-10-03','N','2026-10-03 18:59:02'),
(792,10,5,2,2,'2026-10-03','Y','2026-10-03 18:59:02'),
(793,10,6,1,2,'2026-10-03','N','2026-10-03 18:59:02'),
(794,10,6,2,2,'2026-10-03','Y','2026-10-03 18:59:02'),
(795,10,7,1,2,'2026-10-03','N','2026-10-03 18:59:02'),
(796,10,7,2,2,'2026-10-03','Y','2026-10-03 18:59:02'),
(797,10,8,1,2,'2026-10-03','N','2026-10-03 18:59:02'),
(798,10,8,2,2,'2026-10-03','Y','2026-10-03 18:59:02'),
(799,10,9,1,2,'2026-10-03','N','2026-10-03 18:59:02'),
(800,10,9,2,2,'2026-10-03','Y','2026-10-03 18:59:02'),
(801,10,10,1,2,'2026-10-03','N','2026-10-03 18:59:02'),
(802,10,10,2,2,'2026-10-03','Y','2026-10-03 18:59:02'),
(803,10,1,1,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(804,10,1,2,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(805,10,2,1,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(806,10,2,2,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(807,10,3,1,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(808,10,3,2,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(809,10,4,1,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(810,10,4,2,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(811,10,5,1,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(812,10,5,2,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(813,10,6,1,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(814,10,6,2,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(815,10,7,1,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(816,10,7,2,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(817,10,8,1,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(818,10,8,2,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(819,10,9,1,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(820,10,9,2,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(821,10,10,1,4,'2026-10-04','Y','2026-10-04 09:14:22'),
(822,10,10,2,4,'2026-10-04','Y','2026-10-04 09:14:22');
/*!40000 ALTER TABLE `ridge_records2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '작업ID',
  `task_name` varchar(100) NOT NULL COMMENT '작업명',
  `task_type` varchar(50) DEFAULT NULL COMMENT '작업유형',
  `task_duration` int(11) DEFAULT NULL COMMENT '작업기간 (일 단위)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록일시',
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='작업 정보';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
INSERT INTO `tasks` VALUES
(1,'물','관수',0,'2026-09-15 23:12:42'),
(2,'수확','수확',0,'2026-09-15 23:12:42'),
(3,'옆순','관리',0,'2026-09-15 23:12:42'),
(4,'농약1','방제',1,'2026-09-15 23:12:42'),
(5,'농약3','방제',3,'2026-09-15 23:12:42'),
(6,'농약5','방제',5,'2026-09-15 23:12:42'),
(7,'농약7','방제',7,'2026-09-15 23:12:42');
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks2`
--

DROP TABLE IF EXISTS `tasks2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks2` (
  `task_id` int(11) NOT NULL DEFAULT 0 COMMENT '작업ID',
  `task_name` varchar(100) NOT NULL COMMENT '작업명',
  `task_type` varchar(50) DEFAULT NULL COMMENT '작업유형',
  `task_duration` int(11) DEFAULT NULL COMMENT '작업기간 (일 단위)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '등록일시'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks2`
--

LOCK TABLES `tasks2` WRITE;
/*!40000 ALTER TABLE `tasks2` DISABLE KEYS */;
INSERT INTO `tasks2` VALUES
(1,'물','관수',0,'2026-09-15 23:12:42'),
(2,'수확','수확',0,'2026-09-15 23:12:42'),
(3,'옆순','관리',0,'2026-09-15 23:12:42'),
(4,'농약1','방제',1,'2026-09-15 23:12:42'),
(5,'농약3','방제',3,'2026-09-15 23:12:42'),
(6,'농약5','방제',5,'2026-09-15 23:12:42'),
(7,'농약7','방제',7,'2026-09-15 23:12:42');
/*!40000 ALTER TABLE `tasks2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `testT`
--

DROP TABLE IF EXISTS `testT`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `testT` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유 식별자 ID',
  `reg_date` datetime DEFAULT current_timestamp() COMMENT '날짜 (기본값: 현재 시간)',
  `content` text DEFAULT NULL COMMENT '내용',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testT`
--

LOCK TABLES `testT` WRITE;
/*!40000 ALTER TABLE `testT` DISABLE KEYS */;
INSERT INTO `testT` VALUES
(1,'2026-09-13 21:57:28','sko;dlfgka\';flkas\'df;asdf'),
(2,'2026-09-14 07:37:43','ㅜ어더ㅏ부쿠'),
(3,'2026-09-15 20:32:29','ㅁㅇㄴ려ㅑㅐ;ㅕㅂㅈㄷ\'개럼ㄴ\'애럽ㅈ\'ㄷ개ㅔㅓㅁㄴㅇ\'ㅐ럼ㅈ\'래ㅔㅁ젇ㄹ\'ㅁㄴ얼\'ㅁ닝라ㅓㅁㄴ\'이라먼ㅇ\'림너ㅏㅇㄹ\'ㅣㅁㄴㅇ러ㅏㅁ니;ㅇ라ㅓㅁㄴㅇ;ㅣ라먼ㅇㄹ;ㅣㅁ나얼\'ㅁ니;ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅇ라ㅓㄴㅁ;ㅣㅇ라먼ㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴ아럼ㄴ;ㅣㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ라ㅓㅁ;닝라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ;ㅣㄹ마ㅓㄴㅇㄹ;ㅣㅁ나얼;민ㅇ러ㅏㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ리;ㅁ나ㅓㅇㄹ;ㅣㅁ나얾;닝럼ㄴ;ㅣㅇ라ㅓㅁㄴㅇ;림나ㅓㅇㄹ;민ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴ;이럼ㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ럼ㄴ;ㅣㅇ러ㅏㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅏㅇㄹ'),
(4,'2026-09-15 20:46:12','ㅁㅇㄴ려ㅑㅐ;ㅕㅂㅈㄷ\'개럼ㄴ\'애럽ㅈ\'ㄷ개ㅔㅓㅁㄴㅇ\'ㅐ럼ㅈ\'래ㅔㅁ젇ㄹ\'ㅁㄴ얼\'ㅁ닝라ㅓㅁㄴ\'이라먼ㅇ\'림너ㅏㅇㄹ\'ㅣㅁㄴㅇ러ㅏㅁ니;ㅇ라ㅓㅁㄴㅇ;ㅣ라먼ㅇㄹ;ㅣㅁ나얼\'ㅁ니;ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅇ라ㅓㄴㅁ;ㅣㅇ라먼ㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴ아럼ㄴ;ㅣㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ라ㅓㅁ;닝라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ;ㅣㄹ마ㅓㄴㅇㄹ;ㅣㅁ나얼;민ㅇ러ㅏㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ리;ㅁ나ㅓㅇㄹ;ㅣㅁ나얾;닝럼ㄴ;ㅣㅇ라ㅓㅁㄴㅇ;림나ㅓㅇㄹ;민ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴ;이럼ㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ럼ㄴ;ㅣㅇ러ㅏㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅏㅇㄹ\r\nㅁㅇㄴ려ㅑㅐ;ㅕㅂㅈㄷ\'개럼ㄴ\'애럽ㅈ\'ㄷ개ㅔㅓㅁㄴㅇ\'ㅐ럼ㅈ\'래ㅔㅁ젇ㄹ\'ㅁㄴ얼\'ㅁ닝라ㅓㅁㄴ\'이라먼ㅇ\'림너ㅏㅇㄹ\'ㅣㅁㄴㅇ러ㅏㅁ니;ㅇ라ㅓㅁㄴㅇ;ㅣ라먼ㅇㄹ;ㅣㅁ나얼\'ㅁ니;ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅇ라ㅓㄴㅁ;ㅣㅇ라먼ㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴ아럼ㄴ;ㅣㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ라ㅓㅁ;닝라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ;ㅣㄹ마ㅓㄴㅇㄹ;ㅣㅁ나얼;민ㅇ러ㅏㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ리;ㅁ나ㅓㅇㄹ;ㅣㅁ나얾;닝럼ㄴ;ㅣㅇ라ㅓㅁㄴㅇ;림나ㅓㅇㄹ;민ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴ;이럼ㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ럼ㄴ;ㅣㅇ러ㅏㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅏㅇㄹ\r\nㅁㅇㄴ려ㅑㅐ;ㅕㅂㅈㄷ\'개럼ㄴ\'애럽ㅈ\'ㄷ개ㅔㅓㅁㄴㅇ\'ㅐ럼ㅈ\'래ㅔㅁ젇ㄹ\'ㅁㄴ얼\'ㅁ닝라ㅓㅁㄴ\'이라먼ㅇ\'림너ㅏㅇㄹ\'ㅣㅁㄴㅇ러ㅏㅁ니;ㅇ라ㅓㅁㄴㅇ;ㅣ라먼ㅇㄹ;ㅣㅁ나얼\'ㅁ니;ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅇ라ㅓㄴㅁ;ㅣㅇ라먼ㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴ아럼ㄴ;ㅣㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ라ㅓㅁ;닝라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ;ㅣㄹ마ㅓㄴㅇㄹ;ㅣㅁ나얼;민ㅇ러ㅏㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ리;ㅁ나ㅓㅇㄹ;ㅣㅁ나얾;닝럼ㄴ;ㅣㅇ라ㅓㅁㄴㅇ;림나ㅓㅇㄹ;민ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴ;이럼ㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ럼ㅁㅇㄴ려ㅑㅐ;ㅕㅂㅈㄷ\'개럼ㄴ\'애럽ㅈ\'ㄷ개ㅔㅓㅁㄴㅇ\'ㅐ럼ㅈ\'래ㅔㅁ젇ㄹ\'ㅁㄴ얼\'ㅁ닝라ㅓㅁㄴ\'이라먼ㅇ\'림너ㅏㅇㄹ\'ㅣㅁㄴㅇ러ㅏㅁ니;ㅇ라ㅓㅁㄴㅇ;ㅣ라먼ㅇㄹ;ㅣㅁ나얼\'ㅁ니;ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅇ라ㅓㄴㅁ;ㅣㅇ라먼ㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴ아럼ㄴ;ㅣㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ라ㅓㅁ;닝라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ;ㅣㄹ마ㅓㄴㅇㄹ;ㅣㅁ나얼;민ㅇ러ㅏㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ리;ㅁ나ㅓㅇㄹ;ㅣㅁ나얾;닝럼ㄴ;ㅣㅇ라ㅓㅁㄴㅇ;림나ㅓㅇㄹ;민ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴ;이럼ㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ럼ㄴ;ㅣㅇ러ㅏㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅏㅇㄹ\r\nㅁㅇㄴ려ㅑㅐ;ㅕㅂㅈㄷ\'개럼ㄴ\'애럽ㅈ\'ㄷ개ㅔㅓㅁㄴㅇ\'ㅐ럼ㅈ\'래ㅔㅁ젇ㄹ\'ㅁㄴ얼\'ㅁ닝라ㅓㅁㄴ\'이라먼ㅇ\'림너ㅏㅇㄹ\'ㅣㅁㄴㅇ러ㅏㅁ니;ㅇ라ㅓㅁㄴㅇ;ㅣ라먼ㅇㄹ;ㅣㅁ나얼\'ㅁ니;ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅇ라ㅓㄴㅁ;ㅣㅇ라먼ㅇㄹ;ㅣㅁ나얼;ㅣㅁㄴ아럼ㄴ;ㅣㅇ라ㅓㅁㄴ;이라ㅓㅁㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ라ㅓㅁ;닝라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ;ㅣㄹ마ㅓㄴㅇㄹ;ㅣㅁ나얼;민ㅇ러ㅏㅁㄴ;ㅣㅇ라ㅓㅁㄴㅇ리;ㅁ나ㅓㅇㄹ;ㅣㅁ나얾;닝럼ㄴ;ㅣㅇ라ㅓㅁㄴㅇ;림나ㅓㅇㄹ;민ㅇ라ㅓㅁㄴ;ㅣㅇ라ㅓㅁㄴ;이럼ㄴㅇㄹ;ㅣㅁ나ㅓㅇㄹ;ㅣㅁㄴㅇ럼ㄴ;ㅣㅇ러ㅏㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅏㅇㄹ\r\n;ㅣㅇ러ㅏㅁㄴㅇ;ㅣ람넝ㄹ;ㅣㅁㄴ아ㅓㄻㄴ;ㅣㅏㅇㄹ');
/*!40000 ALTER TABLE `testT` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'wingleDB'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-10-10 19:29:30
