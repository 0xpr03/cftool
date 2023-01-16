/* Copyright (c) 2016-2020 Aron Heinecke
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * This file is parsed & compiled into clanntol-backend
 */
CREATE TABLE `clan` (
 `date` datetime NOT NULL,
 `wins` int NOT NULL,
 `losses` int NOT NULL,
 `draws` int NOT NULL,
 `members` int NOT NULL,
 PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `member` (
 `id` int NOT NULL,
 `date` datetime NOT NULL,
 `exp` BIGINT NOT NULL,
 `cp` int NOT NULL,
 PRIMARY KEY (`id`,`date`) USING BTREE,
 KEY `id` (`id`),
 KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `member_names` (
 `id` int NOT NULL,
 `name` varchar(12) NOT NULL, /* account name */
 `date` datetime NOT NULL,
 `updated` datetime NOT NULL,
 UNIQUE KEY `id` (`id`,`name`),
 KEY `name` (`name`),
 KEY `updated` (`updated`),
 KEY `updated_2` (`updated`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `missing_entries` (
 `date` datetime NOT NULL PRIMARY KEY,
 `member` bit(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `member_addition` (
 `id` int NOT NULL,
 `name` varchar(25) NOT NULL, /* person first name */
 `vip` bit(1) NOT NULL,
 `comment` text,
 `diff_comment` VARCHAR(70),
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `membership_cause` (
 `nr` INT NOT NULL,
 `kicked` bit(1) NOT NULL,
 `cause` varchar(200),
 PRIMARY KEY (`nr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `membership` (
 `nr` INT NOT NULL AUTO_INCREMENT,
 `id` int NOT NULL,
 `from` date NOT NULL,
 `to` date,
 PRIMARY KEY (`nr`),
 UNIQUE KEY `idf` (`id`,`from`),
 KEY `id` (`id`),
 KEY `from` (`from`),
 KEY `to` (`to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ts_relation` (
 `id` int NOT NULL,
 `client_id` int NOT NULL,
 PRIMARY KEY (`id`,`client_id`),
 KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `second_acc` (
 `id` int NOT NULL,
 `id_sec` int NOT NULL,
 PRIMARY KEY (`id`,`id_sec`),
 KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `afk` (
 `id` int NOT NULL,
 `from` date NOT NULL,
 `to` date NOT NULL,
 `added` datetime NOT NULL,
 `cause` text,
 PRIMARY KEY (`id`,`from`,`to`),
 KEY `id` (`id`),
 KEY `from` (`from`),
 KEY `to` (`to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `caution` (
  `id` int NOT NULL,
  `from` date NOT NULL,
  `to` date NOT NULL,
  `added` datetime NOT NULL,
  `cause` text,
  PRIMARY KEY (`id`,`from`),
  KEY `id` (`id`),
  KEY `from` (`from`),
  KEY `to` (`to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `member_trial` (
 `id` int NOT NULL,
 `from` date NOT NULL,
 `to` date,
 PRIMARY KEY (`id`,`from`),
 KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `log` (
 `date` datetime NOT NULL,
 `msg` text NOT NULL,
 KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `settings` (
 `key` VARCHAR(50) NOT NULL,
 `value` VARCHAR(250) NOT NULL,
 PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `unknown_ts_ids` (
  `client_id` int NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ignore_ts_ids` (
  `client_id` int NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW `unknown_ts_unignored` AS
SELECT `t`.`client_id` from `unknown_ts_ids` t where `t`.`client_id` NOT IN (
    select `ignore_ts_ids`.`client_id` from `ignore_ts_ids`
);

CREATE TABLE `ts_activity` (
  `channel_id` int NOT NULL,
  `date` date NOT NULL,
  `time` INT NOT NULL,
  `client_id` int NOT NULL,
  PRIMARY KEY (`date`,`client_id`,`channel_id`),
  KEY `client_date` (`date`,`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `ts_names` (
  `name` VARCHAR(100) NOT NULL,
  `client_id` int NOT NULL,
  PRIMARY KEY (`client_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB CHARACTER SET 'utf8mb4';

CREATE TABLE `ts_channels` (
  `name` VARCHAR(100) NOT NULL,
  `channel_id` int NOT NULL,
  PRIMARY KEY (`channel_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB CHARACTER SET 'utf8mb4';

CREATE TABLE `ts_channel_group_names` (
  `group_id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`group_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB CHARACTER SET 'utf8mb4';

CREATE TABLE `ts_channel_groups` (
  `group_id` INT NOT NULL,
  `channel_id` int NOT NULL UNIQUE,
  PRIMARY KEY `primary` (`channel_id`,`group_id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `fk_group_id`
    FOREIGN KEY (group_id) REFERENCES ts_channel_group_names (group_id)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_channel_id`
    FOREIGN KEY (channel_id) REFERENCES ts_channels (channel_id)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `global_note` (
 `id` INT NOT NULL AUTO_INCREMENT,
 `from` date NOT NULL,
 `to` date NOT NULL,
 `added` datetime NOT NULL,
 `message` text,
 PRIMARY KEY (`id`),
 KEY `from` (`from`),
 KEY `to` (`to`) 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
