<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * functions.php - Utility functions used by most Decir modules
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Inserts a post in reply to a topic. Does NOT check any type of authorization at all.
 * @param int Topic ID
 * @param string Post subject
 * @param string Post text
 * @param reference Will be set to the new post ID.
 */

function decir_submit_post($topic_id, $post_subject, $post_text, &$post_id = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !is_int($topic_id) )
    return false;
  
  $poster_id = $session->user_id;
  $poster_name = ( $session->user_logged_in ) ? $db->escape($session->username) : 'Anonymous';
  $timestamp = time();
  
  $post_text = bbcode_inject_uid($post_text, $bbcode_uid);
  $post_text = $db->escape($post_text);
  
  $post_subject = $db->escape($post_subject);
  
  $q = $db->sql_query('INSERT INTO '.table_prefix."decir_posts(topic_id,poster_id,poster_name,post_subject,timestamp) VALUES($topic_id, $poster_id, '$poster_name', '$post_subject', $timestamp);");
  if ( !$q )
    $db->_die('Decir functions.php in decir_submit_post()');
  
  $post_id = $db->insert_id();
  $q = $db->sql_query('INSERT INTO '.table_prefix."decir_posts_text(post_id, post_text, bbcode_uid) VALUES($post_id, '$post_text', '$bbcode_uid');");
  if ( !$q )
    $db->_die('Decir functions.php in decir_submit_post()');
  
  return true;
}

/**
 * Registers a new topic. Does not perform any type of authorization checks at all.
 * @param int Forum ID
 * @param string Post subject
 * @param string Post text
 * @param reference Will be set to the new topic ID
 */

function decir_submit_topic($forum_id, $post_subject, $post_text, &$topic_id = false, &$post_id = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !is_int($forum_id) )
    return false;
  
  $poster_id = $session->user_id;
  $timestamp = time();
  
  $topic_subject = $db->escape($post_subject);
  
  $q = $db->sql_query('INSERT INTO ' . table_prefix . "decir_topics(forum_id, topic_title, topic_starter, timestamp) VALUES( $forum_id, '$topic_subject', $poster_id, $timestamp );");
  if ( !$q )
    $db->_die('Decir functions.php in decir_submit_topic()');
  $topic_id = $db->insert_id();
  
  // Submit the post
  $postsub = decir_submit_post($topic_id, $post_subject, $post_text, $post_id);
  
  if ( !$postsub )
    return false;
  
  // Update "last post"
  $q = $db->sql_query('UPDATE '.table_prefix."decir_topics SET last_post=$post_id WHERE topic_id=$topic_id;");
  if ( !$q )
    $db->_die('Decir functions.php in decir_submit_topic()');
  
  return true;
}

/**
 * Modifies a post's text. Does not perform any type of authorization checks at all.
 * @param int Post ID
 * @param string Post subject
 * @param string Post text
 * @param string Reason for editing
 */

function decir_edit_post($post_id, $subject, $message, $reason)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !is_int($post_id) )
    return false;
  
  $last_edited_by = $session->user_id;
  $edit_reason = $db->escape($reason);
  $post_subject = $db->escape($subject);
  $post_text = bbcode_inject_uid($message, $bbcode_uid);
  $post_text = $db->escape($post_text);
  
  // grace period: if the user is editing his/her own post 10 minutes or less after they originally submitted it, don't mark it as edited
  $grace = time() - ( 10 * 60 );
  $q = $db->sql_query('UPDATE '.table_prefix."decir_posts SET post_subject='$post_subject', edit_count = edit_count + 1, edit_reason='$edit_reason', last_edited_by=$last_edited_by WHERE post_id=$post_id AND timestamp < $grace;");
  if ( !$q )
    $db->_die('Decir functions.php in decir_edit_post()');

  $q = $db->sql_query('UPDATE '.table_prefix."decir_posts_text SET post_text='$post_text' WHERE post_id=$post_id;");
  if ( !$q )
    $db->_die('Decir functions.php in decir_edit_post()');
  
  return true;
}

/**
 * Deletes a post, or a topic if the post is the first topic in the thread. Does not perform any type of authorization checks at all.
 * @param int Post id
 * @param string Reason for deletion
 * @param bool If true, removes the post physically from the database instead of "soft" deleting it
 */

function decir_delete_post($post_id, $del_reason, $for_real = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !is_int($post_id) )
    return false;
  
  // Is this the first post in the thread?
  $q = $db->sql_query('SELECT topic_id FROM '.table_prefix."decir_posts WHERE post_id = $post_id;");
  if ( !$q )
    $db->_die('Decir functions.php in decir_delete_post()');
  if ( $db->numrows() < 1 )
    // Post doesn't exist
    return false;
  $row = $db->fetchrow();
  $db->free_result();
  
  $topic_id = intval($row['topic_id']);
  
  // while we're at it, also get the forum id
  $q = $db->sql_query('SELECT p.post_id, t.forum_id FROM '.table_prefix."decir_posts AS p
                         LEFT JOIN ".table_prefix."decir_topics AS t
                           ON ( t.topic_id = p.topic_id )
                         WHERE p.topic_id = $topic_id ORDER BY p.timestamp ASC LIMIT 1;");
  if ( !$q )
    $db->_die('Decir functions.php in decir_delete_post()');
  $row = $db->fetchrow();
  $db->free_result();
  
  $forum_id = intval($row['forum_id']);
  
  if ( $row['post_id'] == $post_id )
  {
    // first post in the thread
    return decir_delete_topic($topic_id, $del_reason, $for_real);
  }
  
  $del_reason = $db->escape($del_reason);
  
  if ( $for_real )
  {
    $q = $db->sql_query('DELETE FROM '.table_prefix."decir_posts_text WHERE post_id = $post_id;");
    if ( !$q )
      $db->_die('Decir functions.php in decir_delete_post()');
    $q = $db->sql_query('DELETE FROM '.table_prefix."decir_posts WHERE post_id = $post_id;");
    if ( !$q )
      $db->_die('Decir functions.php in decir_delete_post()');
  }
  else
  {
    // Delete the post
    $q = $db->sql_query('UPDATE '.table_prefix."decir_posts SET post_deleted = 1, last_edited_by = $session->user_id, edit_reason = '$del_reason' WHERE post_id = $post_id;");
    if ( !$q )
      $db->_die('Decir functions.php in decir_delete_post()');
  }
  
  // update forum stats
  $q = $db->sql_query('UPDATE '.table_prefix."decir_forums SET num_posts = num_posts - 1 WHERE forum_id = $forum_id;");
  if ( !$q )
      $db->_die('Decir functions.php in decir_delete_post()');
    
  // update last post and topic
  decir_update_forum_stats($forum_id);
  
  return true;
}

/**
 * Deletes a topic. Does not perform any type of authorization checks at all.
 * @param int Topic ID
 * @param string Reason for deleting the topic
 * @param bool If true, physically removes the topic from the database; else, just turns on the delete switch
 */

function decir_delete_topic($topic_id, $del_reason, $unlink = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !is_int($topic_id) )
    return false;
  
  // Obtain a list of posts in the topic
  $q = $db->sql_query('SELECT post_id, post_deleted FROM '.table_prefix.'decir_posts WHERE topic_id = ' . $topic_id . ';');
  if ( !$q )
    $db->_die('Decir functions.php in decir_delete_topic()');
  if ( $db->numrows() < 1 )
    return false;
  $posts = array();
  $del_count = 0;
  while ( $row = $db->fetchrow() )
  {
    if ( $row['post_deleted'] == 1 )
      // Don't decrement the post count for deleted posts
      $del_count++;
    $posts[] = $row['post_id'];
  }
  
  // Obtain forum ID
  $q = $db->sql_query('SELECT forum_id FROM '.table_prefix."decir_topics WHERE topic_id = $topic_id;");
  if ( !$q )
    $db->_die('Decir functions.php in decir_delete_topic()');
  list($forum_id) = $db->fetchrow_num();
  $db->free_result();
  
  // Perform delete
  if ( $unlink )
  {
    // Remove all posts from the database
    $post_list = implode(' OR post_id=', $posts);
    $q = $db->sql_query('DELETE FROM '.table_prefix."decir_posts_text WHERE $post_list;");
    if ( !$q )
      $db->_die('Decir functions.php in decir_delete_topic()');
    $q = $db->sql_query('DELETE FROM '.table_prefix."decir_posts WHERE $post_list;");
    if ( !$q )
      $db->_die('Decir functions.php in decir_delete_topic()');
    // Remove the topic itself
    $q = $db->sql_query('DELETE FROM '.table_prefix."decir_topics WHERE topic_id = $topic_id;");
    if ( !$q )
      $db->_die('Decir functions.php in decir_delete_topic()');
  }
  else
  {
    $reason = $db->escape($del_reason);
    $topic_deletor = $session->user_id;
    $q = $db->sql_query('UPDATE ' . table_prefix . "decir_topics SET topic_deleted = 1, topic_deletor = $topic_deletor, topic_delete_reason = '$reason' WHERE topic_id = $topic_id;");
  }
  
  // Update forum stats
  $post_count = count($posts) - $del_count;
  $q = $db->sql_query('UPDATE '.table_prefix."decir_forums SET num_topics = num_topics - 1, num_posts = num_posts - $post_count WHERE forum_id = $forum_id;");
  if ( !$q )
    $db->_die('Decir functions.php in decir_delete_topic()');
  decir_update_forum_stats($forum_id);
  
  return true;
}

/**
 * Updates the last post information for the specified forum.
 * @param int Forum ID
 */

function decir_update_forum_stats($forum_id)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !is_int($forum_id) )
    return false;
  
  $sql = 'SELECT p.post_id, p.poster_id, p.topic_id FROM ' . table_prefix . "decir_posts AS p
            LEFT JOIN ".table_prefix."decir_topics AS t
              ON ( t.topic_id = p.topic_id )
            WHERE t.forum_id = $forum_id
              AND p.post_deleted != 1
            ORDER BY p.timestamp DESC
            LIMIT 1;";
  $q = $db->sql_query($sql);
  if ( !$q )
    $db->_die('Decir functions.php in decir_update_forum_stats()');
  
  if ( $db->numrows() < 1 )
  {
    $last_post_id = 'NULL';
    $last_post_topic = 'NULL';
    $last_post_user = 'NULL';
  }
  else
  {
    $row = $db->fetchrow();
    $last_post_id = intval($row['post_id']);
    $last_post_topic = intval($row['topic_id']);
    $last_post_user = intval($row['poster_id']);
  }
  $db->free_result();
  
  $sql = 'UPDATE ' . table_prefix . "decir_forums SET last_post_id = $last_post_id, last_post_topic = $last_post_topic,
            last_post_user = $last_post_user WHERE forum_id = $forum_id;";
  if ( $db->sql_query($sql) )
    return true;
  else
    $db->_die('Decir functions.php in decir_update_forum_stats()');
}

/**
 * Un-deletes a post so that the public can see it.
 * @param int Post ID
 */

function decir_restore_post($post_id)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !is_int($post_id) )
    return false;
  
  $q = $db->sql_query('UPDATE ' . table_prefix . "decir_posts SET post_deleted = 0, edit_count = 0, last_edited_by = NULL, edit_reason = '' WHERE post_id = $post_id AND post_deleted = 1;");
  if ( !$q )
    $db->_die('Decir functions.php in decir_restore_post()');
  
  if ( $db->sql_affectedrows() > 0 )
  {
    // get forum id
    $q = $db->sql_query('SELECT t.forum_id FROM '.table_prefix."decir_posts AS p
                           LEFT JOIN ".table_prefix."decir_topics AS t
                             ON ( p.topic_id = t.topic_id )
                           WHERE p.post_id = $post_id;");
    if ( !$q )
      $db->_die('Decir functions.php in decir_restore_post()');
    $row = $db->fetchrow();
    $db->free_result();
    $forum_id = intval($row['forum_id']);
    // Update forum stats
    $q = $db->sql_query('UPDATE ' . table_prefix . "decir_forums SET num_posts = num_posts + 1 WHERE forum_id = $forum_id;");
    if ( !$q )
      $db->_die('Decir functions.php in decir_restore_post()');
    decir_update_forum_stats($forum_id);
    return true;
  }
  return false;
}

/**
 * Un-deletes a topic so that the public can see it.
 * @param int Topic ID
 */

function decir_restore_topic($topic_id)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !is_int($topic_id) )
    return false;
  
  // Obtain a list of posts in the topic
  $q = $db->sql_query('SELECT post_id, post_deleted FROM '.table_prefix.'decir_posts WHERE topic_id = ' . $topic_id . ';');
  if ( !$q )
    $db->_die('Decir functions.php in decir_delete_topic()');
  if ( $db->numrows() < 1 )
    return false;
  $posts = array();
  $del_count = 0;
  while ( $row = $db->fetchrow() )
  {
    if ( $row['post_deleted'] == 1 )
      // Don't decrement the post count for deleted posts
      $del_count++;
    $posts[] = $row['post_id'];
  }
  
  // Obtain forum ID
  $q = $db->sql_query('SELECT forum_id FROM '.table_prefix."decir_topics WHERE topic_id = $topic_id;");
  if ( !$q )
    $db->_die('Decir functions.php in decir_restore_topic()');
  list($forum_id) = $db->fetchrow_num();
  $db->free_result();
  
  $q = $db->sql_query('UPDATE ' . table_prefix . "decir_topics SET topic_deleted = 0, topic_deletor = NULL, topic_delete_reason = NULL WHERE topic_id = $topic_id;");
  
  // Update forum stats
  $post_count = count($posts) - $del_count;
  $q = $db->sql_query('UPDATE '.table_prefix."decir_forums SET num_topics = num_topics + 1, num_posts = num_posts + $post_count WHERE forum_id = $forum_id;");
  if ( !$q )
    $db->_die('Decir functions.php in decir_restore_topic()');
  decir_update_forum_stats($forum_id);
  
  return true;
}

?>
