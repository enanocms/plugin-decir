SELECT f.forum_id,f.forum_type,f.forum_name,f.forum_desc,
       COUNT(t.topic_id) AS num_topics, COUNT(p.post_id) AS num_posts,
       p.post_id,t.topic_id,t.topic_title,u.username,u.user_level,p.timestamp FROM decir_forums AS f
  LEFT JOIN decir_topics AS t
    ON (t.forum_id=f.forum_id)
  LEFT JOIN decir_posts AS p
    ON (p.topic_id=t.topic_id)
  LEFT JOIN users AS u
    ON (u.user_id=f.last_post_user)
  WHERE ( t.topic_id=f.last_post_topic AND p.post_id=f.last_post_id ) OR ( f.last_post_topic IS NULL AND f.last_post_id IS NULL )
    GROUP BY f.parent,f.forum_id
    ORDER BY f.forum_order;
    
SELECT COUNT(t.topic_id) AS num_topics, COUNT(p.post_id) AS num_posts FROM decir_forums AS f
  LEFT JOIN decir_topics AS t
    ON (t.forum_id=f.forum_id)
  LEFT JOIN decir_posts AS p
    ON (p.topic_id=t.topic_id)
  GROUP BY f.forum_id
  ORDER BY f.forum_order;

INSERT INTO decir_forums(forum_id,forum_type,forum_name,forum_order) VALUES(1,2,'Test category',1);
INSERT INTO decir_forums(forum_id,forum_type,forum_name,forum_desc,parent,forum_order,last_post_id,last_post_topic,last_post_user) VALUES(3,1,'Test forum 1','This is just a test forum.',1,2,3,3,2);
INSERT INTO decir_topics(topic_id,forum_id,topic_title,topic_icon,topic_starter,timestamp) VALUES(1,3,'Test topic 1',1,2,UNIX_TIMESTAMP());
INSERT INTO decir_posts(post_id,topic_id,poster_id,poster_name,timestamp) VALUES(1,1,2,'Dan',UNIX_TIMESTAMP());
INSERT INTO decir_posts_text(post_id,post_text,bbcode_uid) VALUES(1,'This post was created manually using SQL queries.
It is nothing more than a [b:0123456789]proof of concept[/b:0123456789]!

-Dan','0123456789');
INSERT INTO decir_forums(forum_id,forum_type,forum_name,forum_desc,parent,forum_order,last_post_id,last_post_topic,last_post_user) VALUES(4,1,'Test forum 2','This is just a test forum.',1,3,2,2,2);
INSERT INTO decir_topics(topic_id,forum_id,topic_title,topic_icon,topic_starter,timestamp) VALUES(2,4,'Test topic 2',1,2,UNIX_TIMESTAMP());
INSERT INTO decir_posts(post_id,topic_id,poster_id,poster_name,timestamp) VALUES(2,2,2,'Dan',UNIX_TIMESTAMP());
INSERT INTO decir_posts_text(post_id,post_text,bbcode_uid) VALUES(2,'This post was created manually using SQL queries.
It is nothing more than a [b:0123456789]proof of concept[/b:0123456789]!

-Dan','0123456789');
INSERT INTO decir_topics(topic_id,forum_id,topic_title,topic_icon,topic_starter,timestamp) VALUES(3,3,'Test topic 3',1,2,UNIX_TIMESTAMP());
INSERT INTO decir_posts(post_id,topic_id,poster_id,poster_name,timestamp) VALUES(3,3,2,'Dan',UNIX_TIMESTAMP());
INSERT INTO decir_posts_text(post_id,post_text,bbcode_uid) VALUES(3,'This post was created manually using SQL queries.
It is nothing more than a [b:0123456789]proof of concept[/b:0123456789]!

-Dan','0123456789');

