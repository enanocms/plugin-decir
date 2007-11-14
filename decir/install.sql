CREATE TABLE {{TABLE_PREFIX}}decir_forums(
  forum_id int(12) unsigned NOT NULL auto_increment,
  forum_type tinyint(2) unsigned NOT NULL DEFAULT 1,
  forum_name varchar(255) NOT NULL,
  forum_desc text NOT NULL,
  parent int(12) unsigned NOT NULL DEFAULT 0,
  forum_order int(12) unsigned NOT NULL DEFAULT 1,
  last_post_id int(18) unsigned,
  last_post_topic int(12) unsigned,
  last_post_user int(12) unsigned,
  num_topics int(15) unsigned,
  num_posts int(18) unsigned,
  forum_extra text,
  PRIMARY KEY ( forum_id )
);
CREATE TABLE {{TABLE_PREFIX}}decir_topics(
  topic_id int(15) unsigned NOT NULL auto_increment,
  forum_id int(12) unsigned NOT NULL,
  topic_title varchar(255) NOT NULL,
  topic_icon tinyint(3) unsigned NOT NULL,
  topic_starter int(12) unsigned NOT NULL,
  topic_type tinyint(2) unsigned NOT NULL DEFAULT 1,
  topic_locked tinyint(1) unsigned NOT NULL DEFAULT 0,
  topic_moved tinyint(1) unsigned NOT NULL DEFAULT 0,
  timestamp int(11) unsigned NOT NULL,
  topic_deleted tinyint(1) NOT NULL DEFAULT 0,
  topic_deletor int(12) DEFAULT NULL,
  topic_delete_reason varchar(255) DEFAULT NULL,
  num_views bigint(21) UNSIGNED NOT NULL DEFAULT 0,
  last_post bigint(18) UNSIGNED NOT NULL,
  PRIMARY KEY ( topic_id )
);
CREATE TABLE {{TABLE_PREFIX}}decir_posts(
  post_id bigint(18) unsigned NOT NULL auto_increment,
  topic_id bigint(15) unsigned NOT NULL,
  poster_id int(12) unsigned NOT NULL,
  poster_name varchar(255) NOT NULL,
  post_subject varchar(255) NOT NULL DEFAULT '',
  timestamp int(11) unsigned NOT NULL,
  last_edited_by int(12) unsigned DEFAULT NULL,
  edit_count int(5) unsigned NOT NULL DEFAULT 0,
  edit_reason varchar(255),
  post_deleted tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY ( post_id )
);
CREATE TABLE {{TABLE_PREFIX}}decir_posts_text(
  post_id bigint(18) unsigned NOT NULL,
  post_text longtext NOT NULL,
  bbcode_uid varchar(10) NOT NULL,
  PRIMARY KEY ( post_id )
);
CREATE TABLE {{TABLE_PREFIX}}decir_hits(
  hit_id bigint(21) unsigned NOT NULL auto_increment,
  user_id int(12) unsigned NOT NULL DEFAULT 1,
  topic_id bigint(15) unsigned NOT NULL,
  timestamp int(11) unsigned NOT NULL,
  PRIMARY KEY ( hit_id )
);
