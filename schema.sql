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
  `slug` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `hasAvatar` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `banned` tinyint(1) NOT NULL DEFAULT 0,
  `banReason` text DEFAULT NULL,
  `isMod` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `twoFactorEnabled` tinyint(1) NOT NULL DEFAULT 0,
  `theme` varchar(10) NOT NULL DEFAULT 'system',
  `skinTone` varchar(16) DEFAULT NULL,
  `lastNotificationId` int(10) unsigned NOT NULL DEFAULT 0,
  `friendCount` int(10) unsigned NOT NULL DEFAULT 0,
  `sessionVersion` int(10) unsigned NOT NULL DEFAULT 0,
  `remoteActorURI` varchar(255) DEFAULT NULL,
  `remoteActorPublicKeyPem` text DEFAULT NULL,
  PRIMARY KEY (`userId`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `remoteActorURI` (`remoteActorURI`),
  KEY `banned_userId` (`banned`,`userId`),
  FULLTEXT KEY `description` (`description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Posts` (
  `postId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `parentId` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `descriptionDelta` mediumtext DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `linkURL` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `editedAt` datetime DEFAULT NULL,
  `reportsDismissed` tinyint(1) NOT NULL DEFAULT 0,
  `remoteObjectURI` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`postId`),
  KEY `parentId_postId` (`parentId`,`postId`),
  KEY `userId_parentId_postId` (`userId`,`parentId`,`postId`),
  UNIQUE KEY `remoteObjectURI` (`remoteObjectURI`),
  FULLTEXT KEY `title_description_keywords` (`title`,`description`,`keywords`),
  CONSTRAINT `Posts_ibfk_1` FOREIGN KEY (`parentId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE,
  CONSTRAINT `Posts_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Hashtags` (
  `hashtagId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `title` varchar(64) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`hashtagId`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `PostHashtags` (
  `postId` int(10) unsigned NOT NULL,
  `hashtagId` int(10) unsigned NOT NULL,
  PRIMARY KEY (`postId`,`hashtagId`),
  KEY `hashtagId_postId` (`hashtagId`,`postId`),
  CONSTRAINT `PostHashtags_ibfk_1` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE,
  CONSTRAINT `PostHashtags_ibfk_2` FOREIGN KEY (`hashtagId`) REFERENCES `Hashtags` (`hashtagId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `PostMentions` (
  `postId` int(10) unsigned NOT NULL,
  `userId` int(10) unsigned NOT NULL,
  PRIMARY KEY (`postId`,`userId`),
  KEY `userId_postId` (`userId`,`postId`),
  CONSTRAINT `PostMentions_ibfk_1` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE,
  CONSTRAINT `PostMentions_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Materialized top-tag lists, recomputed on a timer (bin/compute-trending.php)
-- or lazily via each list's read-path lottery self-heal - never aggregated at
-- read time (the full GROUP BY over PostHashtags is the expensive part). One
-- row per tag currently in the list; each recompute replaces the set.
-- PopularHashtags is all-time by post count (the /tags/ graph + its fallback
-- pills), TrendingHashtags is the last-7-days window (the /tags/ Trending
-- cloud). Populated by HashtagGraph::recompute() / TrendingHashtagList::recompute().
CREATE TABLE `PopularHashtags` (
  `hashtagId` int(10) unsigned NOT NULL,
  `slug` varchar(64) NOT NULL,
  `title` varchar(64) NOT NULL,
  `postCount` int(10) unsigned NOT NULL,
  `computedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`hashtagId`),
  KEY `postCount` (`postCount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `TrendingHashtags` (
  `hashtagId` int(10) unsigned NOT NULL,
  `slug` varchar(64) NOT NULL,
  `title` varchar(64) NOT NULL,
  `postCount` int(10) unsigned NOT NULL,
  `computedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`hashtagId`),
  KEY `postCount` (`postCount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `FeedItems` (
  `itemId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `postId` int(10) unsigned NOT NULL,
  `type` varchar(50) NOT NULL,
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

CREATE TABLE `Bookmarks` (
  `bookmarkId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `postId` int(10) unsigned NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`userId`,`postId`),
  UNIQUE KEY `bookmarkId` (`bookmarkId`),
  KEY `fk_bookmarks_post` (`postId`),
  KEY `userId_bookmarkId` (`userId`,`bookmarkId`),
  CONSTRAINT `Bookmarks_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookmarks_post` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Friendships` (
  `friendshipId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `requesterId` int(10) unsigned NOT NULL,
  `addresseeId` int(10) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `pairLow` int(10) unsigned GENERATED ALWAYS AS (LEAST(`requesterId`,`addresseeId`)) STORED,
  `pairHigh` int(10) unsigned GENERATED ALWAYS AS (GREATEST(`requesterId`,`addresseeId`)) STORED,
  PRIMARY KEY (`friendshipId`),
  UNIQUE KEY `uniq_pair` (`requesterId`,`addresseeId`),
  UNIQUE KEY `uniq_unordered_pair` (`pairLow`,`pairHigh`),
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
  `reportsDismissed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`messageId`),
  KEY `senderId_recipientId_messageId` (`senderId`,`recipientId`,`messageId`),
  KEY `recipientId_senderId_messageId` (`recipientId`,`senderId`,`messageId`),
  CONSTRAINT `Messages_ibfk_1` FOREIGN KEY (`senderId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE,
  CONSTRAINT `Messages_ibfk_2` FOREIGN KEY (`recipientId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Notifications` (
  `notificationId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `actorId` int(10) unsigned NOT NULL,
  `type` varchar(32) NOT NULL,
  `postId` int(10) unsigned DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notificationId`),
  KEY `userId` (`userId`,`notificationId`),
  KEY `postId` (`postId`),
  CONSTRAINT `Notifications_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE,
  CONSTRAINT `Notifications_ibfk_2` FOREIGN KEY (`actorId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Reports` (
  `reportId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reporterId` int(10) unsigned NOT NULL,
  `type` varchar(16) NOT NULL,
  `targetId` int(10) unsigned NOT NULL,
  `reason` text DEFAULT NULL,
  `snapshot` longtext DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reportId`),
  UNIQUE KEY `reporter_target` (`reporterId`,`type`,`targetId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `EmailVerifications` (
  `verificationId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `tokenHash` varchar(64) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`verificationId`),
  KEY `tokenHash` (`tokenHash`),
  KEY `expiresAt` (`expiresAt`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `PasswordResets` (
  `resetId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `tokenHash` varchar(64) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`resetId`),
  KEY `tokenHash` (`tokenHash`),
  KEY `expiresAt` (`expiresAt`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One active opt-in email 2FA code per user (the UNIQUE key on userId means a
-- new code replaces the old rather than piling up). Only the SHA-256 hash of
-- the code is stored, never the code itself, and attempts is capped so a code
-- can't be brute-forced within its short lifetime.
CREATE TABLE `TwoFactorCodes` (
  `codeId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `codeHash` varchar(64) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`codeId`),
  UNIQUE KEY `userId` (`userId`),
  KEY `expiresAt` (`expiresAt`),
  CONSTRAINT `TwoFactorCodes_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `EmailChangeReverts` (
  `revertId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `previousEmail` varchar(255) NOT NULL,
  `tokenHash` varchar(64) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`revertId`),
  KEY `tokenHash` (`tokenHash`),
  KEY `expiresAt` (`expiresAt`),
  KEY `userId` (`userId`),
  UNIQUE KEY `previousEmail` (`previousEmail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `RateLimitAttempts` (
  `attemptId` int(10) unsigned NOT NULL AUTO_INCREMENT,
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

CREATE TABLE `RemoteFollows` (
  `remoteFollowId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `localUserId` int(10) unsigned NOT NULL,
  `remoteActorURI` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `followActivityId` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`remoteFollowId`),
  UNIQUE KEY `localUserId_remoteActorURI` (`localUserId`,`remoteActorURI`),
  KEY `remoteActorURI_status` (`remoteActorURI`,`status`),
  CONSTRAINT `RemoteFollows_ibfk_1` FOREIGN KEY (`localUserId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `RemoteObjectTombstones` (
  `remoteObjectURI` varchar(255) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`remoteObjectURI`)
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

CREATE TABLE `Settings` (
  `name` varchar(64) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `RememberTokens` (
  `tokenId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `selector` varchar(32) NOT NULL,
  `validatorHash` varchar(64) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `lastUsedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `userAgent` varchar(255) DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`tokenId`),
  UNIQUE KEY `selector` (`selector`),
  KEY `userId` (`userId`),
  KEY `expiresAt` (`expiresAt`),
  CONSTRAINT `RememberTokens_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `LoginFingerprints` (
  `fingerprintId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `userAgent` varchar(255) DEFAULT NULL,
  `acceptLanguage` varchar(255) DEFAULT NULL,
  `acceptEncoding` varchar(255) DEFAULT NULL,
  `referer` varchar(255) DEFAULT NULL,
  `secChUa` varchar(255) DEFAULT NULL,
  `secChUaMobile` varchar(8) DEFAULT NULL,
  `secChUaPlatform` varchar(64) DEFAULT NULL,
  `secFetchSite` varchar(32) DEFAULT NULL,
  `secFetchMode` varchar(32) DEFAULT NULL,
  `secFetchDest` varchar(32) DEFAULT NULL,
  `secFetchUser` varchar(16) DEFAULT NULL,
  `dnt` varchar(8) DEFAULT NULL,
  `httpProtocol` varchar(16) DEFAULT NULL,
  `tlsCipher` varchar(64) DEFAULT NULL,
  `tlsProtocol` varchar(16) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`fingerprintId`),
  KEY `userId` (`userId`),
  CONSTRAINT `LoginFingerprints_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ModerationActions` (
  `actionId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `moderatorId` int(10) unsigned NOT NULL,
  `action` varchar(32) NOT NULL,
  `targetUserId` int(10) unsigned DEFAULT NULL,
  `type` varchar(16) DEFAULT NULL,
  `targetId` int(10) unsigned DEFAULT NULL,
  `reportId` int(10) unsigned DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`actionId`),
  KEY `moderatorId_actionId` (`moderatorId`,`actionId`),
  KEY `createdAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Materialized, recomputed on a timer by bin/compute-trending.php (or lazily,
-- lottery-triggered, from Trending::current() if that timer isn't installed) -
-- never computed at read time. See Trending.php for the scoring/window/abuse-
-- guard design. entityId is a real surrogate key (not just entityType+
-- entityValue, unlike this app's pure join tables) so moderation tooling has
-- a stable single id to act on a trending row by.
CREATE TABLE `TrendingEntities` (
  `entityId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(16) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `score` double NOT NULL,
  `postCount` int(10) unsigned NOT NULL,
  `userCount` int(10) unsigned NOT NULL,
  `computedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`entityId`),
  UNIQUE KEY `type_slug` (`type`,`slug`),
  KEY `score` (`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A standing moderation rule, not a recomputed row - survives TrendingEntities
-- being fully replaced every run (a banned entity is excluded from scoring
-- entirely, not just hidden after the fact) and survives falling out of the
-- trending window too (still banned if it becomes active again later).
CREATE TABLE `BannedTrendingEntities` (
  `type` varchar(16) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `bannedBy` int(10) unsigned NOT NULL,
  `reason` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`type`,`slug`),
  KEY `bannedBy` (`bannedBy`),
  CONSTRAINT `BannedTrendingEntities_ibfk_1` FOREIGN KEY (`bannedBy`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
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
-- Bookmarks orders by bookmarkId (insertion order IS bookmarked order),
-- served by (userId, bookmarkId); no query uses a (userId, createdAt,
-- postId) index.
ALTER TABLE `Bookmarks` DROP INDEX IF EXISTS `idx_bookmarks_user_created`;

-- Column-type migrations (safe to re-run): these six tables were originally
-- created with signed int(11) id/userId columns, unlike every other table's
-- int(10) unsigned - a plain INT is 4 bytes either way regardless of the
-- display-width number in parens, so unsigned simply has roughly double the
-- usable positive range (~4.29 billion vs ~2.15 billion) for a column that's
-- never negative, and it matches everything else in this schema. Also what
-- makes Notifications.userId/actorId eligible for the FOREIGN KEY added to
-- that CREATE TABLE block above (Users.userId is int(10) unsigned - MySQL/
-- MariaDB require a FK's column to match the referenced column's type).
-- Each statement mirrors its column's definition in the CREATE TABLE block
-- above exactly. neededIndexMigrations() only includes one of these when the
-- live column doesn't already match, so this is a no-op on a healthy re-run.
ALTER TABLE `Notifications` MODIFY COLUMN `notificationId` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `Notifications` MODIFY COLUMN `userId` int(10) unsigned NOT NULL;
ALTER TABLE `Notifications` MODIFY COLUMN `actorId` int(10) unsigned NOT NULL;
ALTER TABLE `Notifications` MODIFY COLUMN `postId` int(10) unsigned DEFAULT NULL;
ALTER TABLE `Reports` MODIFY COLUMN `reportId` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `Reports` MODIFY COLUMN `reporterId` int(10) unsigned NOT NULL;
ALTER TABLE `Reports` MODIFY COLUMN `targetId` int(10) unsigned NOT NULL;
ALTER TABLE `EmailVerifications` MODIFY COLUMN `verificationId` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `EmailVerifications` MODIFY COLUMN `userId` int(10) unsigned NOT NULL;
ALTER TABLE `PasswordResets` MODIFY COLUMN `resetId` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `PasswordResets` MODIFY COLUMN `userId` int(10) unsigned NOT NULL;
ALTER TABLE `EmailChangeReverts` MODIFY COLUMN `revertId` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `EmailChangeReverts` MODIFY COLUMN `userId` int(10) unsigned NOT NULL;
ALTER TABLE `RateLimitAttempts` MODIFY COLUMN `attemptId` int(10) unsigned NOT NULL AUTO_INCREMENT;

-- Foreign-key rule migration (safe to re-run): Posts_ibfk_2 was RESTRICT
-- (the implicit default - it never named an ON DELETE rule), unlike every
-- other Users-referencing FK in this schema, which blocked deleting a user
-- who had ever posted. Account deletion (User::delete()) needs this to
-- actually cascade, same as everything else tied to a user.
-- Two separate statements, not one combined DROP+ADD - MariaDB errors
-- ("Duplicate key on write or update") re-adding the same constraint name
-- in the same ALTER TABLE it was just dropped in.
-- neededIndexMigrations() only includes this when the live constraint's
-- DELETE_RULE isn't already CASCADE, so this is a no-op on a healthy re-run.
ALTER TABLE `Posts` DROP FOREIGN KEY `Posts_ibfk_2`;
ALTER TABLE `Posts` ADD CONSTRAINT `Posts_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE;

-- Column-default migration (safe to re-run): Users.verified defaulted to 1
-- (already verified) rather than the fail-safe 0 - it's a security gate
-- (unverified users are blocked from the site), and it only stayed safe
-- because signup.php explicitly binds verified=0 on every insert; any
-- future insert path that omitted the column would have silently created an
-- already-verified account. neededIndexMigrations() compares COLUMN_DEFAULT,
-- not just the type (which isn't changing here), so this is a no-op once applied.
ALTER TABLE `Users` MODIFY COLUMN `verified` tinyint(1) NOT NULL DEFAULT 0;
-- A followed Fediverse account's slug is its full handle (user@host), which is
-- longer than any local username: those are capped far shorter at signup, and
-- widening this column doesn't raise that cap.
ALTER TABLE `Users` MODIFY COLUMN `slug` varchar(255) NOT NULL;

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
