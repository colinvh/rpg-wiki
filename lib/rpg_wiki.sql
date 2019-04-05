CREATE DATABASE IF NOT EXISTS rpg_wiki DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE rpg_wiki;

CREATE TABLE game (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    url varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name varchar(8192) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY `UNIQUE` (url) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE game_user (
    game_id int(10) UNSIGNED NOT NULL COMMENT 'FK game.id',
    user_id int(10) UNSIGNED NOT NULL COMMENT 'FK user.id',
    gm tinyint(1) NOT NULL DEFAULT '0',
    UNIQUE KEY `UNIQUE` (game_id, user_id) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE subject (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    game_id int(10) UNSIGNED NOT NULL COMMENT 'FK game.id',
    path varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name text NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY `UNIQUE` (path) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE revision (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    subj_id int(10) UNSIGNED NOT NULL COMMENT 'FK subject.id',
    type ENUM('gmpriv', 'gmpub', 'plr'),
    date timestamp,
    hash char(64) COLLATE ascii_bin,
    PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=ascii;

CREATE TABLE user (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    admin tinyint(1) NOT NULL DEFAULT '0',
    nickname text NOT NULL,
    email varchar(254) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    password char(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY UNIQUE_name (name) USING BTREE,
    UNIQUE KEY UNIQUE_email (email) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE VIEW head AS
    SELECT id, subj_id, type, date, hash FROM revision
    WHERE id IN (SELECT max(id) FROM revision GROUP BY subj_id, type);
