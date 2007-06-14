<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * posting.php - post topics and replies
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

require('common.php');
require('bbcode.php');

//
// Set mode and parameters
//

$mode = 'topic';

if ( $paths->getParam(1) )
{
  $n = strtolower($paths->getParam(1));
  if ( $n == 'reply' || $n == 'post' )
  {
    $mode = 'reply';
  }
  elseif ( $n == 'quote' )
  {
    $mode = 'quote';
  }
}

// Set the parameters for posting, then encrypt it so we don't have to do authorization checks again
// Why? Because it's better than going through some session system for postings where the data is stored on the server
// We already have AES encryption - might as well use it ;-)
$aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);

$do_preview = false;

if ( isset($_GET['act']) && $_GET['act'] == 'post' )
{
  if ( !is_array($_POST['do']) )
    die('Hacking attempt');
  
  if ( isset($_POST['do']['preview']) )
  {
    $do_preview = true;
    $parms  = $_POST['authorization'];
    $parms2 = $aes->decrypt($parms, $session->private_key, ENC_HEX);
    if ( !$parms2 || substr($parms2, 0, 1) != 'a' )
    {
      die('Hacking attempt: ' . $parms2);
    }
    $parms2 = unserialize($parms2);
    $mode = 'already_taken_care_of';
  }
  else if ( isset($_POST['do']['post']) )
  {
    // Decrypt authorization array
    $parms = $aes->decrypt($_POST['authorization'], $session->private_key, ENC_HEX);
    $parms = unserialize($parms);
    
    // Perform a little input validation
    $errors = Array();
    if ( empty($_POST['post_text']) )
      $errors[] = 'Please enter a post.';
    if ( empty($_POST['subject']) && $parms['mode'] == 'topic' )
      $errors[] = 'Please enter a topic title.';
    // It's OK to trust this! The auth key is encrypted with the site's private key.
    if ( !$parms['authorized'] )
      $errors[] = 'Invalid authorization key';
    
    if ( sizeof($errors) > 0 )
    {
      // Collect other options
      
      // Submit post
      decir_submit_post();
      return;
    }
  }
}

if ( $mode == 'reply' || $mode == 'quote' )
{
  if ( $mode == 'reply' )
  {
    $message = '';
    // Validate topic ID
    $topic_id = intval($paths->getParam(2));
    if ( empty($topic_id) )
      die_friendly('Error', '<p>Invalid topic ID</p>');
    $title = 'Reply to topic';
  }
  else if ( $mode == 'quote' )
  {
    
    /**
     * @TODO: validate read permissions
     */
    
    $post_id = intval($paths->getParam(2));
    if ( empty($post_id) )
      die_friendly('Error', '<p>Invalid post ID</p>');
    
    // Get post text and topic ID
    $q = $db->sql_query('SELECT p.topic_id,t.post_text,t.bbcode_uid,p.poster_name FROM '.table_prefix.'decir_posts AS p
                           LEFT JOIN '.table_prefix.'decir_posts_text AS t
                             ON ( p.post_id = t.post_id )
                           WHERE p.post_id=' . $post_id . ';');
    
    if ( !$q )
      $db->_die();
    
    if ( $db->numrows() < 1 )
      die_friendly('Error', '<p>The post you requested does not exist.</p>');
    
    $row = $db->fetchrow();
    $db->free_result();
    
    $message = '[quote="' . $row['poster_name'] . '"]' . bbcode_strip_uid( $row['post_text'], $row['bbcode_uid'] ) . '[/quote]';
    $quote_poster = $row['poster_name'];
    $topic_id = intval($row['topic_id']);
    
    $title = 'Reply to topic with quote';
    
  }
  
  // Topic ID is good, verify topic status
  $q = $db->sql_query('SELECT topic_id,forum_id,topic_type,topic_locked,topic_moved FROM '.table_prefix.'decir_topics WHERE topic_id=' . $topic_id . ';');
  
  if ( !$q )
    $db->_die();
  
  $row = $db->fetchrow();
  $db->free_result();
  
  $forum_perms = $session->fetch_page_acl('DecirForum', $row['forum_id']);
  $topic_perms = $session->fetch_page_acl('DecirTopic', $row['topic_id']);
  
  if ( !$forum_perms->get_permissions('decir_see_forum') )
    die_friendly('Error', '<p>The forum you requested does not exist.</p>');
  
  if ( !$topic_perms->get_permissions('decir_reply') )
    die_friendly('Access denied', '<p>You are not allowed to post replies in this topic.</p>');
  
  $forum_in = intval($row['forum_id']);
  $topic_in = intval($row['topic_id']);
  
  $parms = Array(
      'mode' => $mode,
      'forum_in' => $forum_in,
      'topic_in' => $topic_in,
      'timestamp' => time(),
      'authorized' => true
    );
  
  $parms = serialize($parms);
  $parms = $aes->encrypt($parms, $session->private_key, ENC_HEX);
  
}
else if ( $mode == 'topic' )
{
  $message = '';
  // Validate topic ID
  $forum_id = intval($paths->getParam(2));
  if ( empty($forum_id) )
    die_friendly('Error', '<p>Invalid forum ID</p>');
  $title = 'Post new topic';
  
  // Topic ID is good, verify topic status
  $q = $db->sql_query('SELECT forum_id FROM '.table_prefix.'decir_forums WHERE forum_id=' . $forum_id . ';');
  
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() < 1 )
    die_friendly('Error', '<p>The forum you requested does not exist.</p>');
  
  $row = $db->fetchrow();
  $db->free_result();
  
  $forum_perms = $session->fetch_page_acl('DecirForum', $row['forum_id']);
  
  if ( !$forum_perms->get_permissions('decir_see_forum') )
    die_friendly('Error', '<p>The forum you requested does not exist.</p>');
  
  $parms = Array(
      'mode' => $mode,
      'forum_in' => $forum_in,
      'timestamp' => time(),
      'authorized' => true
    );
  
  $parms = serialize($parms);
  $parms = $aes->encrypt($parms, $session->private_key, ENC_HEX);
  
}
else if ( $mode == 'already_taken_care_of' )
{
  $mode = $parms2['mode'];
  $title = ( $mode == 'topic' ) ? 'Post new topic' : ( $mode == 'reply' ) ? 'Reply to topic' : ( $mode  == 'quote' ) ? 'Reply to topic with quote' : 'Duh...';
}
else
{
  die_friendly('Invalid request', '<p>Invalid action defined</p>');
}

$template->tpl_strings['PAGE_NAME'] = $title;
$template->add_header('<!-- DECIR BEGIN -->
    <script type="text/javascript" src="' . scriptPath . '/decir/js/bbcedit.js"></script>
    <script type="text/javascript" src="' . scriptPath . '/decir/js/colorpick/jquery.js"></script>
    <script type="text/javascript" src="' . scriptPath . '/decir/js/colorpick/farbtastic.js"></script>
    <link rel="stylesheet" type="text/css" href="' . scriptPath . '/decir/js/bbcedit.css" />
    <link rel="stylesheet" type="text/css" href="' . scriptPath . '/decir/js/colorpick/farbtastic.css" />
    <!-- DECIR END -->');

$template->header();

if ( $do_preview )
{
  echo 'Doing preview';
}

$url = makeUrlNS('Special', 'Forum/New', 'act=post', true);
echo '<br />
      <form action="' . $url . '" method="post" enctype="multipart/form-data">';
echo '<textarea name="post_text" class="bbcode" rows="20" cols="80">' . $message . '</textarea>';
echo '<input type="hidden" name="authorization" value="' . $parms . '" />';
echo '<div style="text-align: center; margin-top: 10px;"><input type="submit" name="do[post]" value="Submit post" style="font-weight: bold;" />&nbsp;<input type="submit" name="do[preview]" value="Show preview" /></div>';
echo '</form>';

$template->footer();

?>
