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
  -- The language this member asked to read the site in. Null until they say,
  -- and only ever set by them saying so - a browser's Accept-Language is a
  -- guess that answers for them until then, and re-guessing on every browser
  -- they open is what having said so is meant to stop.
  `locale` varchar(5) DEFAULT NULL,
  `skinTone` varchar(16) DEFAULT NULL,
  -- Whether this member has asked to be shown media classified as sensitive
  -- without having to open it each time. Off unless they say otherwise: the
  -- cover exists so nobody meets that media without choosing to.
  `showSensitiveMedia` tinyint(1) NOT NULL DEFAULT 0,
  -- Whether this member has said they are done with the welcome on the home
  -- feed. Only ticking its checkbox sets this: dismissing without ticking
  -- puts it away for the moment and it comes back, which is what somebody
  -- who has not finished reading it would want.
  `welcomeDismissed` tinyint(1) NOT NULL DEFAULT 0,
  -- When this member was last here, to the nearest few minutes - init.php
  -- writes it on a request from somebody signed in, but only once the stored
  -- value has gone stale, so reading a long thread is one write rather than
  -- one per page. Null for an account that has never signed in since the
  -- column existed.
  `lastSeen` datetime DEFAULT NULL,
  -- Whether this member wants the mail that tells them what they missed while
  -- they were away. On unless they say otherwise, since somebody who has
  -- stopped visiting is exactly who it is for and would never see an in-site
  -- invitation to switch it on.
  `emailDigests` tinyint(1) NOT NULL DEFAULT 1,
  -- When the last one went out, so the next is a week away whatever else
  -- happens. Written whether or not the send succeeded: a bad address must
  -- not be retried every pass, and the cadence is the point.
  `emailDigestSent` datetime DEFAULT NULL,
  `lastNotificationId` int(10) unsigned NOT NULL DEFAULT 0,
  -- The newest message this member had received when they last opened their
  -- conversations, which is what the unread dot compares against. Same shape
  -- as lastNotificationId, and for the same reason: a per-message read flag
  -- would be a row per message per person to answer a yes/no question.
  `lastMessageId` int(10) unsigned NOT NULL DEFAULT 0,
  `friendCount` int(10) unsigned NOT NULL DEFAULT 0,
  `sessionVersion` int(10) unsigned NOT NULL DEFAULT 0,
  `remoteActorURI` varchar(255) DEFAULT NULL,
  `remoteActorPublicKeyPem` text DEFAULT NULL,
  `remoteActorInboxURL` varchar(255) DEFAULT NULL,
  `remoteActorSharedInboxURL` varchar(255) DEFAULT NULL,
  -- What the account says it is: Person for somebody typing, Service for a
  -- bot. Self-declared and standard - Mastodon's "this is a bot account"
  -- checkbox publishes Service - and taken at its word, since nobody has a
  -- reason to claim to be a bot and it is not load-bearing for anything but
  -- what trending reads.
  --
  -- Null on a row recorded before the actor's type was kept, which is why the
  -- trending query asks for what is NOT a bot rather than what is a person: an
  -- account nobody has fetched again yet must not vanish out of the corpus.
  `remoteActorType` varchar(20) DEFAULT NULL,
  -- The avatar the actor document points at. Kept as the URL rather than the
  -- picture: RemoteMedia's reasoning applies here too - copying it would make
  -- this server the host of whatever another chose to publish, and hotlinking
  -- would hand that server a log line for every member who scrolls past. It is
  -- proxied, and this column is what makes that a lookup rather than an open
  -- fetch of whatever a visitor asks for.
  `remoteActorIconURL` varchar(500) DEFAULT NULL,
  -- The account's own labelled fields - "Website", "Pronouns" - as the
  -- PropertyValue attachments the actor document carries them in, kept as the
  -- JSON array of {name, value} this reads back. A side table would be a join
  -- on every profile render for data that is only ever read whole, and is
  -- replaced whole every time the actor is re-read.
  `remoteActorFields` text DEFAULT NULL,
  `actorPublicKeyPem` text DEFAULT NULL,
  `actorEncryptedPrivateKey` text DEFAULT NULL,
  `movedToURI` varchar(255) DEFAULT NULL,
  `alsoKnownAs` text DEFAULT NULL,
  `chatOtherUserId` int(10) unsigned DEFAULT NULL,
  `chatLastSeen` datetime DEFAULT NULL,
  -- End-to-end encrypted messaging keys, both produced client-side. The
  -- public key is a P-256 ECDH JWK; the private key arrives already wrapped
  -- under a key derived from a passphrase this server never sees, so the row
  -- only ever holds ciphertext - storing it here is what makes the same
  -- passphrase unlock the history from any browser. Losing the passphrase
  -- loses the history; there is deliberately no server-side recovery.
  `messagePublicKey` text DEFAULT NULL,
  `messageWrappedPrivateKey` text DEFAULT NULL,
  PRIMARY KEY (`userId`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `remoteActorURI` (`remoteActorURI`),
  KEY `banned_userId` (`banned`,`userId`),
  -- Counting the people who are actually of this server. Both questions asked
  -- about them - how many there are, and how many have been here lately -
  -- start by separating local accounts from the shadow rows federation
  -- creates, which is what leads these two.
  KEY `remoteActorURI_banned` (`remoteActorURI`,`banned`),
  KEY `remoteActorURI_lastSeen` (`remoteActorURI`,`lastSeen`),
  -- Two full-text indexes because user search asks two questions: the wide one
  -- answers "does this account match at all" and drives the WHERE; the narrow
  -- one answers "was that a hit on the name" and orders name matches ahead of
  -- bio-only matches.
  FULLTEXT KEY `slug_title_description` (`slug`,`title`,`description`),
  FULLTEXT KEY `slug_title` (`slug`,`title`)
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
  -- The author's (or a moderator's) classification of this post's media as
  -- something to opt into seeing. Federates both ways as ActivityStreams'
  -- `sensitive`.
  `sensitive` tinyint(1) NOT NULL DEFAULT 0,
  -- The warning to read before the post, as ActivityStreams' `summary`. Where
  -- there is one it is not a label on the post but a gate in front of it: the
  -- words go behind it along with the media, because a spoiler warning whose
  -- spoiler is the text is the one case `sensitive` alone cannot cover.
  `contentWarning` varchar(255) DEFAULT NULL,
  `remoteObjectURI` varchar(255) DEFAULT NULL,
  -- What language the sender SAID this was written in - ActivityPub's
  -- contentMap - which is not the same as what it is written in. Mastodon
  -- fills it from the poster's own account setting rather than from the
  -- words, so an account set to English writing in French says English.
  --
  -- Recorded because it is a fact about the post, and nothing is decided on
  -- it: the translate button is offered on everything, since withholding it
  -- on a wrong reading leaves somebody unable to read a post with no way to
  -- ask. Null means nobody said, which is every post written here.
  `language` varchar(35) DEFAULT NULL,
  -- What the words are actually in, as opposed to what the sender said above.
  -- Read off the text by the entity extractor, which has to know anyway to
  -- pick a model that can read it, and kept because knowing is useful on its
  -- own. Null where nothing could tell - too short, punctuation, a bare URL -
  -- which is a real answer and not a gap to fill in with a guess.
  `detectedLanguage` varchar(20) DEFAULT NULL,
  -- The post this one quotes - repost-with-commentary. SET NULL rather than
  -- CASCADE: the commentary is its author's own writing and outlives the
  -- deletion of what it commented on, rendering as an ordinary post.
  `quotedPostId` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`postId`),
  KEY `parentId_postId` (`parentId`,`postId`),
  KEY `userId_parentId_postId` (`userId`,`parentId`,`postId`),
  -- What a profile feed reads: one author's posts, newest first. The index
  -- above cannot serve it now that a profile shows replies too - parentId sits
  -- between the author and the order, so every post of theirs was read and
  -- then sorted. Cheap while somebody has a few hundred; this is for the
  -- server where somebody has thousands.
  KEY `userId_postId` (`userId`,`postId`),
  KEY `parentId_remoteObjectURI_postId` (`parentId`,`remoteObjectURI`,`postId`),
  -- What the global feed reads: everything written here, newest first. The
  -- unique key on remoteObjectURI alone cannot serve it - it groups the local
  -- posts together but carries no order within the group, so the page was
  -- filesorted out of every one of them. Here postId is ordered inside the
  -- NULL group, so the feed is read backward off the index and stops when the
  -- page is full.
  KEY `remoteObjectURI_postId` (`remoteObjectURI`,`postId`),
  KEY `quotedPostId` (`quotedPostId`),
  UNIQUE KEY `remoteObjectURI` (`remoteObjectURI`),
  FULLTEXT KEY `title_description_keywords` (`title`,`description`,`keywords`),
  CONSTRAINT `Posts_ibfk_1` FOREIGN KEY (`parentId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE,
  CONSTRAINT `Posts_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_quoted` FOREIGN KEY (`quotedPostId`) REFERENCES `Posts` (`postId`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `PostLocations` (
  `postId` int(10) unsigned NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  PRIMARY KEY (`postId`),
  KEY `latitude_longitude` (`latitude`,`longitude`),
  CONSTRAINT `fk_postlocations_post` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The world's towns and cities, from the GeoNames gazetteer (geonames.org,
-- CC BY 4.0) - what turns a post's coordinates into "near Kingston, Ontario"
-- without asking any outside service at request time. Loaded by
-- bin/install.php from the cities500 dump; empty until that step has run,
-- and everything reading it treats empty as "show coordinates".
--
-- placeId is the GeoNames id, not AUTO_INCREMENT, so a reload upserts in
-- place instead of duplicating the planet.
CREATE TABLE `Places` (
  `placeId` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `region` varchar(100) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL DEFAULT '',
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `population` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`placeId`),
  KEY `latitude_longitude` (`latitude`,`longitude`),
  -- Serves the nearby page's place autocomplete: a name-prefix range walk
  -- instead of a scan of the whole gazetteer per keystroke. Carrying
  -- population as well makes that walk covering - the rows still have to be
  -- sorted, since a range on the name cannot also order by size, but they are
  -- sorted out of the index rather than by fetching each row to read one
  -- column off it.
  KEY `title_population` (`title`,`population`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A poll attached to a post. Its own table rather than columns on Posts, the
-- same shape PostLocations uses: most posts are not polls, and the options are
-- a list that could never be columns anyway.
--
-- A poll IS its post as far as the network is concerned - ActivityPub has no
-- separate poll object, the post's type simply becomes Question - so there is
-- one poll per post and no independent identity to publish.
--
-- endsAt is not nullable. Every implementation that reads these requires an
-- endTime, and a poll that never closes has no result anyone can quote; the
-- composer offers a set of durations rather than a free date.
CREATE TABLE `Polls` (
  `pollId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `postId` int(10) unsigned NOT NULL,
  -- Whether more than one option may be chosen. Decides oneOf vs anyOf on the
  -- wire, and whether a second vote replaces the first or adds to it.
  `multiple` tinyint(1) NOT NULL DEFAULT 0,
  `endsAt` datetime NOT NULL,
  -- How many distinct people voted, as the origin server reported it. Only ever
  -- set for a poll that came from elsewhere: for one of ours the answer is a
  -- count of our own rows. A multiple-choice poll needs it because the votes
  -- across its options cannot be added up into a number of voters.
  `remoteVotersCount` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`pollId`),
  UNIQUE KEY `postId` (`postId`),
  CONSTRAINT `fk_polls_post` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One choice on a poll. `position` fixes the order the author wrote them in,
-- since a poll's options are a sequence and re-ordering them would change what
-- a result means.
--
-- `title` is the option's text, and it is also its identity on the wire: a vote
-- arrives naming the option by its text rather than by any id, so two options
-- on one poll may not read the same.
--
-- remoteVoteCount is null for a poll of ours, where the count is a count of
-- PollVotes rows, and set for one that came from elsewhere, where we hold no
-- votes and the origin server's tally is the only figure there is.
CREATE TABLE `PollOptions` (
  `pollOptionId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pollId` int(10) unsigned NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `remoteVoteCount` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`pollOptionId`),
  UNIQUE KEY `pollId_title` (`pollId`,`title`),
  KEY `pollId_position` (`pollId`,`position`),
  CONSTRAINT `fk_polloptions_poll` FOREIGN KEY (`pollId`) REFERENCES `Polls` (`pollId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One person's vote for one option. Keyed on the option rather than the poll so
-- a multiple-choice poll is simply several rows, and carrying pollId as well so
-- "has this person voted at all" is one indexed read rather than a join.
--
-- A remote voter has a shadow Users row like any other account, so an inbound
-- vote on a poll of ours is the same row a member here would make - the same
-- way Likes already works.
CREATE TABLE `PollVotes` (
  `pollId` int(10) unsigned NOT NULL,
  `pollOptionId` int(10) unsigned NOT NULL,
  `userId` int(10) unsigned NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pollOptionId`,`userId`),
  KEY `pollId_userId` (`pollId`,`userId`),
  KEY `fk_pollvotes_user` (`userId`),
  CONSTRAINT `fk_pollvotes_poll` FOREIGN KEY (`pollId`) REFERENCES `Polls` (`pollId`) ON DELETE CASCADE,
  CONSTRAINT `fk_pollvotes_option` FOREIGN KEY (`pollOptionId`) REFERENCES `PollOptions` (`pollOptionId`) ON DELETE CASCADE,
  CONSTRAINT `fk_pollvotes_user` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
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
  -- Set only on an item that arrived from another server, where it names the
  -- file on that server. A local item's bytes live under uploads/ at a path
  -- derived from its itemId, so it needs no URL of its own; a remote one is
  -- never copied here, it's fetched per request through RemoteMedia. altText
  -- likewise only ever holds what a remote server sent, since local items
  -- derive their alt text from the post (see Post::imageAltText()).
  `remoteURL` varchar(500) DEFAULT NULL,
  `altText` varchar(1000) DEFAULT NULL,
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

-- Usernames that have been used and given up. A name is never handed to anyone
-- else, because on this site a username is permanent - it is the whole reason
-- display names exist - and reusing one would make a new person indistinguishable
-- from the old one to anybody holding a link.
--
-- Federation makes that worse than a mix-up. A member's actor URI is built from
-- their username, so a recycled name inherits the dead account's URI, and every
-- remote server still holding a follow would start delivering someone else's
-- posts to it.
--
-- Its own table rather than a flag on a kept Users row: the account is deleted,
-- and a row retained only to reserve a string would keep a foreign key alive for
-- every post, message and follow that was supposed to go with it.
-- Whole servers this instance refuses to deal with. Blocking accounts one at a
-- time does not work against a server that mints them faster than a moderator
-- can act, which is the usual shape of federated abuse.
--
-- The domain is stored lowercased and without a port, and matching includes
-- subdomains, so blocking a host blocks what it hands out.
CREATE TABLE `BlockedServers` (
  `domain` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `blockedBy` int(10) unsigned DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`domain`),
  KEY `blockedBy` (`blockedBy`),
  CONSTRAINT `BlockedServers_ibfk_1` FOREIGN KEY (`blockedBy`) REFERENCES `Users` (`userId`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `RetiredUsernames` (
  `slug` varchar(255) NOT NULL,
  `retiredAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Custom emoji, keyed by the server they belong to. Every instance has its own
-- set and nobody can resolve a name they were not told about, so :blobcat: from
-- one server and :blobcat: from another are different pictures and both have to
-- be storable at once.
--
-- Learned from the Emoji tags that ride alongside a post's content: the tag is
-- how a sender says what a name in its text means, and without one a shortcode
-- is simply text.
CREATE TABLE `CustomEmojis` (
  `customEmojiId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL,
  `shortcode` varchar(64) NOT NULL,
  `imageURL` varchar(255) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`customEmojiId`),
  UNIQUE KEY `domain_shortcode` (`domain`,`shortcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who has boosted a post, whether from here or from another server. A like
-- needs no table of its own - Likes is keyed on (userId, postId) and a remote
-- account that has one already has a shadow Users row, so a favourite from
-- Mastodon is the same row a member here would make. A boost has no such row
-- to reuse, and it has to be remembered anyway or an Undo would have nothing
-- to find: an Undo is matched on this row's key, the post and the account.
--
-- activityURI is the id this server publishes for a boost made here, so
-- another server can name it. Nothing reads it back.
CREATE TABLE `Announces` (
  `postId` int(10) unsigned NOT NULL,
  `userId` int(10) unsigned NOT NULL,
  `activityURI` varchar(255) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`postId`,`userId`),
  KEY `userId_postId` (`userId`,`postId`),
  -- A profile lists its owner's reposts newest-first, straight off this index.
  KEY `userId_createdAt` (`userId`,`createdAt`),
  CONSTRAINT `Announces_ibfk_1` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE,
  CONSTRAINT `Announces_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Posts a member has pinned to the top of their own profile. Their own posts
-- only, and capped - a pinned list that can hold everything pins nothing.
--
-- Federates as the actor's `featured` collection, which is where the rest of
-- the network looks for exactly this.
CREATE TABLE `PinnedPosts` (
  `userId` int(10) unsigned NOT NULL,
  `postId` int(10) unsigned NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`userId`,`postId`),
  KEY `userId_createdAt` (`userId`,`createdAt`),
  KEY `fk_pinnedposts_post` (`postId`),
  CONSTRAINT `PinnedPosts_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE,
  CONSTRAINT `fk_pinnedposts_post` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE
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
  -- Exactly one of body and bodyCiphertext is set: a message is either
  -- plaintext (the legacy rows, and every federated message - ActivityPub has
  -- no encryption to speak) or an end-to-end encrypted envelope this server
  -- relayed but cannot read (see MessageEnvelope). mediumtext because the
  -- envelope base64-encodes a body that may itself run to the 65535-byte cap.
  `body` text DEFAULT NULL,
  `bodyCiphertext` mediumtext DEFAULT NULL,
  -- HMAC the server computed over the envelope as it relayed it (see
  -- MessageFranking). A report of an encrypted message re-verifies this tag,
  -- proving the reported ciphertext is the one that actually passed through
  -- here before the revealed message key is used to open it.
  `frankingTag` varchar(128) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `reportsDismissed` tinyint(1) NOT NULL DEFAULT 0,
  `remoteObjectURI` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`messageId`),
  UNIQUE KEY `remoteObjectURI` (`remoteObjectURI`),
  KEY `senderId_recipientId_messageId` (`senderId`,`recipientId`,`messageId`),
  KEY `recipientId_senderId_messageId` (`recipientId`,`senderId`,`messageId`),
  -- For the nav's unread check: MAX(messageId) for a recipient is a single
  -- dive here, where the conversation indexes above would leave senderId
  -- unbound and scan the person's whole mailbox on every page render.
  KEY `recipientId_messageId` (`recipientId`,`messageId`),
  -- What the weekly digest counts: one person's messages since a moment. The
  -- index above carries messageId rather than a time, so the digest's date
  -- predicate had nothing to range on and fell back to reading the table.
  KEY `recipientId_createdAt` (`recipientId`,`createdAt`),
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
  -- Declared, not left to the actorId foreign key's implicit index: the
  -- repeat-collapse check (Notification::alreadyNotified) filters on actorId
  -- and only stays fast because an index on it exists.
  KEY `actorId` (`actorId`),
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

-- Single-use 2FA recovery codes, issued in a batch when a user turns 2FA on
-- (and on demand from settings). Each row is one unused code, stored only as
-- a SHA-256 hash; using a code deletes its row. They exist so 2FA can fail
-- closed: when the code email cannot be sent, a recovery code is the way in.
CREATE TABLE `TwoFactorRecoveryCodes` (
  `recoveryCodeId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `codeHash` varchar(64) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`recoveryCodeId`),
  KEY `userId` (`userId`),
  CONSTRAINT `TwoFactorRecoveryCodes_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
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

-- Whose feed holds which post. reposterId records WHY it is there: null means
-- the post reached this feed on its own account (its author is a friend, or
-- followed), and a value means somebody put it there by reposting it.
--
-- That distinction is what makes un-reposting possible. Without it, withdrawing
-- a repost would have to delete the row, which would also remove a post that
-- was in the feed because its author is a friend. With it, only the rows the
-- repost created are removed.
--
-- Still one row per (userId, postId): a post appears once in a feed however many
-- ways it arrived. A repost of something already there leaves the existing row
-- alone, so the reason it was originally shown survives the repost being undone.
-- Signatures already accepted at the inbox, so one cannot be replayed. A
-- signature is valid for as long as its Date header is fresh (an hour), which
-- without this is an hour in which a captured delivery can be posted again by
-- anyone who saw it. Rows older than that window are swept as new ones arrive.
-- A shared firehose this server subscribes to. A relay forwards every public
-- post from every other subscribed server, and takes this server's in return,
-- which is the usual answer to a new instance seeing nothing: federation is
-- follow-shaped, and a server nobody follows from receives nothing to discover
-- anyone by.
--
-- followObject records which form the subscribing Follow named, because
-- implementations disagree about it - some expect the relay's own actor, some
-- expect as:Public. Only ever one per relay: sending both would be one
-- subscription counted twice, and the Undo has to name the same thing the
-- Follow did.
CREATE TABLE `Relays` (
  `relayId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `actorURI` varchar(255) NOT NULL,
  `inboxURL` varchar(255) NOT NULL,
  `followActivityId` varchar(255) NOT NULL,
  `followObject` varchar(16) NOT NULL DEFAULT 'public',
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`relayId`),
  UNIQUE KEY `actorURI` (`actorURI`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Posts a relay has named that have yet to be read from the server that wrote
-- them. A relay forwards an address rather than a post, so ingesting one means
-- fetching it - and doing that while the relay waits would hold a PHP worker
-- for as long as somebody else's server takes to answer. At the inbox's own
-- rate limit that is enough concurrent workers to exhaust the pool and take
-- the site down, so the fetch happens in bin/federation-worker.php instead and
-- the inbox only writes down what to fetch.
--
-- objectURI is unique: two relays naming the same post is one thing to read.
CREATE TABLE `RelayFetches` (
  `relayFetchId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `relayId` int(10) unsigned DEFAULT NULL,
  `objectURI` varchar(255) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `nextAttemptAt` datetime NOT NULL DEFAULT current_timestamp(),
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`relayFetchId`),
  UNIQUE KEY `objectURI` (`objectURI`),
  KEY `nextAttemptAt_relayFetchId` (`nextAttemptAt`,`relayFetchId`),
  CONSTRAINT `fk_relayfetches_relay` FOREIGN KEY (`relayId`) REFERENCES `Relays` (`relayId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which posts arrived through a relay rather than through anybody here
-- following their author. Its own table for the same reason PostLocations is:
-- only a post that actually came that way costs a row, and it is what keeps
-- the firehose to a feed of its own rather than letting it into the main one.
--
-- relayId goes null rather than cascading when a relay is dropped: the posts
-- have already been read, replied to and liked, and removing a subscription is
-- not a reason to make them vanish - only a reason to stop new ones arriving.
CREATE TABLE `RelayPosts` (
  `postId` int(10) unsigned NOT NULL,
  `relayId` int(10) unsigned DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`postId`),
  KEY `relayId` (`relayId`),
  CONSTRAINT `fk_relayposts_post` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE,
  CONSTRAINT `fk_relayposts_relay` FOREIGN KEY (`relayId`) REFERENCES `Relays` (`relayId`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ActivityPubReplays` (
  `signatureHash` char(64) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`signatureHash`),
  KEY `createdAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Timelines` (
  `userId` int(10) unsigned NOT NULL,
  `postId` int(10) unsigned NOT NULL,
  `reposterId` int(10) unsigned DEFAULT NULL,
  -- The moment this row entered the feed: the post's own time for a fanned-out
  -- post (including backfilled history, which must stay buried at its age),
  -- the repost's moment for a repost. The feed orders by it straight off the
  -- index below - a computed order would mean sorting a person's whole
  -- timeline for every page.
  `sortAt` datetime DEFAULT NULL,
  PRIMARY KEY (`userId`,`postId`),
  KEY `userId_sortAt_postId` (`userId`,`sortAt`,`postId`),
  KEY `reposterId_postId` (`reposterId`,`postId`),
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

-- The other direction from RemoteFollows: someone out on the Fediverse
-- following a local member. Its own table rather than a Friendships row,
-- because Friendships allows one row per pair in either direction
-- (uniq_unordered_pair) and federated follows are one-way and independent -
-- two people following each other is two edges, not one.
--
-- inboxURL is denormalized off the actor so publishing a post doesn't have to
-- dereference every follower's actor document first; sharedInboxURL is the
-- batching endpoint where the remote server offers one.
CREATE TABLE `FediverseFollowers` (
  `fediverseFollowerId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `localUserId` int(10) unsigned NOT NULL,
  `remoteActorURI` varchar(255) NOT NULL,
  `inboxURL` varchar(255) NOT NULL,
  `sharedInboxURL` varchar(255) DEFAULT NULL,
  `followActivityId` varchar(255) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`fediverseFollowerId`),
  UNIQUE KEY `localUserId_remoteActorURI` (`localUserId`,`remoteActorURI`),
  KEY `remoteActorURI` (`remoteActorURI`),
  KEY `localUserId_fediverseFollowerId` (`localUserId`,`fediverseFollowerId`),
  CONSTRAINT `FediverseFollowers_ibfk_1` FOREIGN KEY (`localUserId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outbound activities waiting to reach a remote inbox. Delivery cannot happen
-- inside the request that caused it: a post by someone with a thousand
-- followers is a thousand HTTPS round trips to servers that may be slow, down,
-- or gone, and none of that belongs between a person pressing Post and seeing
-- their post.
--
-- One row per destination inbox, holding the exact bytes to send. attempts and
-- nextAttemptAt carry the backoff; a row that exhausts its attempts is dropped
-- rather than retried forever, because a server that has refused a dozen times
-- over several days is not coming back for this one activity.
CREATE TABLE `FediverseDeliveries` (
  `deliveryId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  -- Null means the instance itself signs this one, which is how a Flag goes
  -- out: naming the member who reported would hand a harasser their identity.
  `actorUserId` int(10) unsigned DEFAULT NULL,
  `inboxURL` varchar(255) NOT NULL,
  `activity` mediumtext NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `nextAttemptAt` datetime NOT NULL DEFAULT current_timestamp(),
  `lastError` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`deliveryId`),
  KEY `nextAttemptAt_deliveryId` (`nextAttemptAt`,`deliveryId`),
  CONSTRAINT `FediverseDeliveries_ibfk_1` FOREIGN KEY (`actorUserId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What a remote server said when it refused an activity.
--
-- Only refusals. A delivery that arrived is already counted (Statistic
-- DELIVERED) and its body says nothing anybody needs; a delivery that was
-- turned away is the one case where the reason exists solely in the response,
-- and the queue row that might have carried it is deleted whether the activity
-- arrived or was given up on. Without this an activity the far side rejected
-- is indistinguishable from one it accepted, which makes every interop
-- question a guess.
--
-- Swept on the same retention pass as the rest of the housekeeping, since this
-- is a diagnostic log and an unbounded one would be a slow leak.
CREATE TABLE `FediverseDeliveryRefusals` (
  `refusalId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `inboxURL` varchar(255) NOT NULL,
  `activityType` varchar(64) DEFAULT NULL,
  `activityURI` varchar(255) DEFAULT NULL,
  -- Null when the request never reached HTTP at all - an unresolvable host, a
  -- refused connection - which is a different failure from being turned away.
  `status` smallint(5) unsigned DEFAULT NULL,
  `body` varchar(500) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`refusalId`),
  KEY `createdAt` (`createdAt`),
  KEY `activityType_createdAt` (`activityType`,`createdAt`)
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

-- Things that happened, counted by the day they happened on.
--
-- A queue only ever says what has not been dealt with yet: a delivery that
-- succeeds is deleted, so counting the queue tells an admin nothing about how
-- much got through. This is where anything worth knowing after the fact goes,
-- one row per thing per day.
--
-- Kept as counts rather than as rows per event on purpose. A busy server
-- delivers hundreds of thousands of activities and nobody is going to read
-- them one by one - what a dashboard wants is how many, and old days are
-- pruned once they are too old to be looked at.
CREATE TABLE `Statistics` (
  `name` varchar(64) NOT NULL,
  `day` date NOT NULL,
  `total` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`name`,`day`),
  KEY `day` (`day`)
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
  -- Every post ever counted against this topic, added to run by run and never
  -- recomputed. score/postCount/userCount are the current window and answer
  -- "is this spiking"; this one answers "how much has been said about it",
  -- which is a different question and the one /topics/{type}/ is ordered by.
  `popularity` int(10) unsigned NOT NULL DEFAULT 0,
  `computedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`entityId`),
  UNIQUE KEY `type_slug` (`type`,`slug`),
  KEY `score` (`score`),
  -- The type page, ordered by popularity, and the prune that keeps it bounded.
  KEY `type_popularity` (`type`,`popularity`),
  -- What is trending now: the rows stamped with the latest run.
  KEY `computedAt_score` (`computedAt`,`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A standing moderation rule, not a recomputed row - survives TrendingEntities
-- being fully replaced every run (a banned entity is excluded from scoring
-- entirely, not just hidden after the fact) and survives falling out of the
-- trending window too (still banned if it becomes active again later).
-- A post's body rendered into another language, cached forever per (post,
-- language) so each translation is paid for - in model calls and in waiting -
-- exactly once. The language is whatever BCP 47 tag the reader's browser
-- asked in, normalized lowercase; rows die with their post.
CREATE TABLE `PostTranslations` (
  `postId` int(10) unsigned NOT NULL,
  `language` varchar(35) NOT NULL,
  `body` text NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`postId`,`language`),
  CONSTRAINT `fk_posttranslations_post` FOREIGN KEY (`postId`) REFERENCES `Posts` (`postId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A browser that asked to be told about notifications while no tab is open.
-- One row per browser per member (the endpoint URL is the browser's own
-- identity); rows die with their member, and a push service answering
-- 404/410 for an endpoint deletes its row (the browser revoked it).
CREATE TABLE `PushSubscriptions` (
  `pushSubscriptionId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(64) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pushSubscriptionId`),
  UNIQUE KEY `endpoint` (`endpoint`),
  KEY `userId` (`userId`),
  CONSTRAINT `fk_pushsubscriptions_user` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pushes waiting to be sent, drained by the federation worker - a request
-- that creates a notification must never wait on a push service across the
-- internet. Same nextAttemptAt shape as FediverseDeliveries, but pushes are
-- fire-and-mostly-forget: one retry, then the row is dropped - a
-- notification is news, and stale news isn't worth a retry schedule.
CREATE TABLE `PushDeliveries` (
  `pushDeliveryId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pushSubscriptionId` int(10) unsigned NOT NULL,
  `payload` text NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `nextAttemptAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pushDeliveryId`),
  KEY `nextAttemptAt_pushDeliveryId` (`nextAttemptAt`,`pushDeliveryId`),
  KEY `fk_pushdeliveries_subscription` (`pushSubscriptionId`),
  CONSTRAINT `fk_pushdeliveries_subscription` FOREIGN KEY (`pushSubscriptionId`) REFERENCES `PushSubscriptions` (`pushSubscriptionId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A post written but not published: a draft (publishAt NULL, published by
-- hand) or a scheduled post (published by the upload worker when the clock
-- passes publishAt). Its own table rather than a future timestamp on Posts,
-- so no feed query ever grows a time predicate for a feature most posts
-- never use - a row here becomes a Posts row only at publish. Text, link and
-- location only; attached media still publishes immediately.
CREATE TABLE `StagedPosts` (
  `stagedPostId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `descriptionDelta` mediumtext DEFAULT NULL,
  `linkURL` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `sensitive` tinyint(1) NOT NULL DEFAULT 0,
  `publishAt` datetime DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`stagedPostId`),
  KEY `userId_stagedPostId` (`userId`,`stagedPostId`),
  KEY `publishAt` (`publishAt`),
  CONSTRAINT `fk_stagedposts_user` FOREIGN KEY (`userId`) REFERENCES `Users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One AI-written paragraph per topic - "what people are posting about under
-- #x" - shown atop the topic's tag page. Its own table rather than a column
-- on TrendingEntities, whose rows are rewritten by every rescore: a summary
-- outlives the scoring run that prompted it and keeps serving the tag page
-- after the topic falls off trending. Written by the trending timer, at most
-- one topic per pass (the model call is rate-limited by design); rows are
-- upserted on (type, slug) as topics come back around.
CREATE TABLE `TopicSummaries` (
  `type` varchar(16) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `summary` text NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`type`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Notifications: before actorId was declared above, InnoDB had silently
-- created an index for the actorId foreign key under the constraint's own
-- name. Once the declared one exists (drift adds it), the accidental twin is
-- redundant. Dropping it only ever succeeds after the replacement is in
-- place - the engine refuses to drop the last index backing a foreign key.
ALTER TABLE `Notifications` DROP INDEX IF EXISTS `Notifications_ibfk_2`;
-- Users: the bio-only full-text index is superseded by the two above (drift
-- adds those), which cover the name columns user search actually ranks by.
ALTER TABLE `Users` DROP INDEX IF EXISTS `description`;
-- Bookmarks orders by bookmarkId (insertion order IS bookmarked order),
-- served by (userId, bookmarkId); no query uses a (userId, createdAt,
-- postId) index.
ALTER TABLE `Bookmarks` DROP INDEX IF EXISTS `idx_bookmarks_user_created`;
-- Messages: the weekly digest counts one person's messages since a moment, and
-- the recipient index carries messageId rather than a time, so that count read
-- the whole table for anyone with a full mailbox.
ALTER TABLE `Messages` ADD INDEX IF NOT EXISTS `recipientId_createdAt` (`recipientId`, `createdAt`);
-- Places: the autocomplete walks a name prefix and orders what it finds by
-- population. Carrying population in the same index makes that walk covering;
-- the bare title index it replaces cannot answer the ordering at all.
ALTER TABLE `Places` ADD INDEX IF NOT EXISTS `title_population` (`title`, `population`);
ALTER TABLE `Places` DROP INDEX IF EXISTS `title`;
-- Users: both counts of "people who are actually of this server" start by
-- separating local accounts from the shadow rows federation creates.
ALTER TABLE `Users` ADD INDEX IF NOT EXISTS `remoteActorURI_banned` (`remoteActorURI`, `banned`);
ALTER TABLE `Users` ADD INDEX IF NOT EXISTS `remoteActorURI_lastSeen` (`remoteActorURI`, `lastSeen`);
-- TrendingEntities: rows now outlive the run that produced them, so what is
-- trending is the newest computedAt rather than everything in the table, and
-- the type page reads all of one kind ordered by how much has been said.
-- Users: a remote account's profile is more than a name - the bio, the labelled
-- fields it publishes, and the avatar it points at all arrive in the same actor
-- document and were being dropped.
-- Users: which language a member reads the site in, once they have said.
ALTER TABLE `Users` ADD COLUMN IF NOT EXISTS `locale` varchar(5) DEFAULT NULL AFTER `theme`;
ALTER TABLE `Users` ADD COLUMN IF NOT EXISTS `remoteActorIconURL` varchar(500) DEFAULT NULL AFTER `remoteActorType`;
ALTER TABLE `Users` ADD COLUMN IF NOT EXISTS `remoteActorFields` text DEFAULT NULL AFTER `remoteActorIconURL`;
ALTER TABLE `TrendingEntities` ADD COLUMN IF NOT EXISTS `popularity` int(10) unsigned NOT NULL DEFAULT 0 AFTER `userCount`;
ALTER TABLE `TrendingEntities` ADD INDEX IF NOT EXISTS `type_popularity` (`type`, `popularity`);
ALTER TABLE `TrendingEntities` ADD INDEX IF NOT EXISTS `computedAt_score` (`computedAt`, `score`);

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
-- An end-to-end encrypted message stores its ciphertext in bodyCiphertext and
-- nothing in body, so body can no longer be NOT NULL. Nullability is a column
-- change rather than a missing column, so the drift check won't apply it.
ALTER TABLE `Messages` MODIFY COLUMN `body` text DEFAULT NULL;
-- A franking tag now names the key that made it, so rotating the server key
-- doesn't make every message franked under the old one unreportable. Longer
-- than the bare hash it used to be, and a column change rather than a missing
-- column, so the drift check won't apply it.
ALTER TABLE `Messages` MODIFY COLUMN `frankingTag` varchar(128) DEFAULT NULL;
-- A queued delivery may now be signed by the instance rather than by a member
-- (null actorUserId), which is what lets a Flag be queued and retried instead
-- of being posted once, in-request, and lost if the far server is down.
-- Nullability is a column change rather than a missing column, so the drift
-- check won't apply it.
ALTER TABLE `FediverseDeliveries` MODIFY COLUMN `actorUserId` int(10) unsigned DEFAULT NULL;

-- Maintenance (safe to re-run): recompute the denormalized Users.friendCount
-- from the actual accepted friendships. Runs after every install and upgrade -
-- a no-op on a fresh database, and on an upgrade it corrects existing rows the
-- moment the column is added rather than waiting for each user's next sign-in.
-- The installer runs every non-CREATE-TABLE statement in this file here.
-- Timeline rows from before sortAt existed take the moment they stood for:
-- the repost's time where the row is a repost, the post's own time otherwise.
-- Idempotent - only ever rows the column predates.
UPDATE `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    LEFT JOIN `Announces` ON `Announces`.`postId` = `Timelines`.`postId` AND `Announces`.`userId` = `Timelines`.`reposterId`
    SET `Timelines`.`sortAt` = COALESCE(`Announces`.`createdAt`, `Posts`.`createdAt`)
    WHERE `Timelines`.`sortAt` IS NULL;

UPDATE `Users` `u`
    SET `u`.`friendCount` = (
        SELECT COUNT(*)
            FROM `Friendships` `f`
            WHERE `f`.`status` = 'accepted' AND (`f`.`requesterId` = `u`.`userId` OR `f`.`addresseeId` = `u`.`userId`)
    );
