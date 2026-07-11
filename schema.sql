-- Glommer database schema.
--
-- The database name is configurable (DB_DATABASE in .env, defaults to
-- "glommer") - substitute whatever you've actually set it to below.
-- `php bin/install.php` runs this automatically given DB_ADMIN_USERNAME/
-- DB_ADMIN_PASSWORD; to do it manually instead (the app's own DB user is
-- intentionally least-privilege and typically lacks ALTER/CREATE, so this
-- needs to be run as a user that has them, e.g.):
--   mysql -u root -p glommer < schema.sql
-- or, if the database itself doesn't exist yet:
--   mysql -u root -p -e "CREATE DATABASE glommer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root -p glommer < schema.sql

CREATE TABLE `Users` (
  `userId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `displayName` varchar(100) DEFAULT NULL,
  `hasAvatar` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `banned` tinyint(1) NOT NULL DEFAULT 0,
  `isMod` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `verified` tinyint(1) NOT NULL DEFAULT 1,
  `theme` varchar(10) NOT NULL DEFAULT 'system',
  `skinTone` varchar(16) DEFAULT NULL,
  `lastNotificationId` int(10) unsigned NOT NULL DEFAULT 0,
  `friendCount` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`userId`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Posts` (
  `postId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `parentId` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `linkURL` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`postId`),
  KEY `parentId_postId` (`parentId`,`postId`),
  KEY `userId_parentId_postId` (`userId`,`parentId`,`postId`),
  CONSTRAINT `Posts_ibfk_1` FOREIGN KEY (`parentId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE,
  CONSTRAINT `Posts_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `FeedItems` (
  `itemId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `postId` int(10) unsigned NOT NULL,
  `itemType` varchar(50) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`itemId`),
  KEY `fk_feeditems_post` (`postId`),
  CONSTRAINT `fk_feeditems_post` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Likes` (
  `userId` int(10) unsigned NOT NULL,
  `postId` int(10) unsigned NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`userId`,`postId`),
  KEY `fk_likes_post` (`postId`),
  CONSTRAINT `Likes_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE,
  CONSTRAINT `fk_likes_post` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Friendships` (
  `friendshipId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `requesterId` int(10) unsigned NOT NULL,
  `addresseeId` int(10) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`friendshipId`),
  UNIQUE KEY `uniq_pair` (`requesterId`,`addresseeId`),
  KEY `requesterId_status_friendshipId` (`requesterId`,`status`,`friendshipId`),
  KEY `addresseeId_status_friendshipId` (`addresseeId`,`status`,`friendshipId`),
  CONSTRAINT `Friendships_ibfk_1` FOREIGN KEY (`requesterId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE,
  CONSTRAINT `Friendships_ibfk_2` FOREIGN KEY (`addresseeId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Blocks` (
  `blockId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `blockerId` int(10) unsigned NOT NULL,
  `blockedId` int(10) unsigned NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`blockId`),
  UNIQUE KEY `uniq_pair` (`blockerId`,`blockedId`),
  KEY `blockedId` (`blockedId`),
  CONSTRAINT `Blocks_ibfk_1` FOREIGN KEY (`blockerId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE,
  CONSTRAINT `Blocks_ibfk_2` FOREIGN KEY (`blockedId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Messages` (
  `messageId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `senderId` int(10) unsigned NOT NULL,
  `recipientId` int(10) unsigned NOT NULL,
  `body` text NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`messageId`),
  KEY `senderId_recipientId_messageId` (`senderId`,`recipientId`,`messageId`),
  KEY `recipientId_senderId_messageId` (`recipientId`,`senderId`,`messageId`),
  CONSTRAINT `Messages_ibfk_1` FOREIGN KEY (`senderId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE,
  CONSTRAINT `Messages_ibfk_2` FOREIGN KEY (`recipientId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Notifications` (
  `notificationId` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `actorId` int(11) NOT NULL,
  `type` varchar(32) NOT NULL,
  `postId` int(11) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notificationId`),
  KEY `userId` (`userId`,`notificationId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Reports` (
  `reportId` int(11) NOT NULL AUTO_INCREMENT,
  `reporterId` int(11) NOT NULL,
  `targetType` varchar(16) NOT NULL,
  `targetId` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reportId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `EmailVerifications` (
  `verificationId` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `tokenHash` varchar(64) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`verificationId`),
  KEY `tokenHash` (`tokenHash`),
  KEY `expiresAt` (`expiresAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `PasswordResets` (
  `resetId` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `tokenHash` varchar(64) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`resetId`),
  KEY `tokenHash` (`tokenHash`),
  KEY `expiresAt` (`expiresAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `RateLimitAttempts` (
  `attemptId` int(11) NOT NULL AUTO_INCREMENT,
  `rateKey` varchar(255) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attemptId`),
  KEY `rateKey` (`rateKey`,`createdAt`),
  KEY `createdAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Timelines` (
  `userId` int(10) unsigned NOT NULL,
  `postId` int(10) unsigned NOT NULL,
  PRIMARY KEY (`userId`,`postId`),
  KEY `fk_timelines_post` (`postId`),
  CONSTRAINT `Timelines_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE,
  CONSTRAINT `fk_timelines_post` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `LinkPreviews` (
  `url` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `imageURL` varchar(2048) DEFAULT NULL,
  `succeeded` tinyint(1) NOT NULL DEFAULT 0,
  `fetchedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`url`),
  KEY `fetchedAt` (`fetchedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index migrations (safe to re-run): bring an existing install's indexes up to
-- date. Fresh installs already get these from the CREATE TABLE blocks above -
-- these idempotent ALTERs apply the same changes to a database built from an
-- older schema. Friendships: a composite index per direction
-- (requesterId/addresseeId, status, friendshipId) serves the status-filtered,
-- friendshipId-ordered friends and friend-request lists without a filesort, and
-- supersedes the bare addresseeId index. Reports: the (targetType, targetId)
-- index was not used by any query.
ALTER TABLE `Friendships` ADD INDEX IF NOT EXISTS `requesterId_status_friendshipId` (`requesterId`, `status`, `friendshipId`);
ALTER TABLE `Friendships` ADD INDEX IF NOT EXISTS `addresseeId_status_friendshipId` (`addresseeId`, `status`, `friendshipId`);
ALTER TABLE `Friendships` DROP INDEX IF EXISTS `addresseeId`;
ALTER TABLE `Reports` DROP INDEX IF EXISTS `targetType`;

-- Maintenance (safe to re-run): recompute the denormalized Users.friendCount
-- from the actual accepted friendships. Runs after every install and upgrade -
-- a no-op on a fresh database, and on an upgrade it corrects existing rows the
-- moment the column is added rather than waiting for each user's next sign-in.
-- The installer runs every non-CREATE-TABLE statement in this file here.
UPDATE `Users` `u`
    SET `u`.`friendCount` = (
        SELECT COUNT(*)
            FROM `Friendships` `f`
            WHERE `f`.`status` = 'accepted' AND (`f`.`requesterId` = `u`.`userId` OR `f`.`addresseeId` = `u`.`userId`)
    );
