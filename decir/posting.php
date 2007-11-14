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
    $errors = Array();
    
    // Decrypt authorization array
    $parms = $aes->decrypt($_POST['authorization'], $session->private_key, ENC_HEX);
    if ( !$parms )
      $errors[] = 'Could not decrypt authorization key.';
    $parms = unserialize($parms);
    
    // Perform a little input validation
    if ( empty($_POST['post_text']) )
      $errors[] = 'Please enter a post.';
    if ( empty($_POST['subject']) && $parms['mode'] == 'topic' )
      $errors[] = 'Please enter a topic title.';
    // It's OK to trust this! The auth key is encrypted with the site's private key.
    if ( !$parms['authorized'] )
      $errors[] = 'Invalid authorization key';
    
    // If the user isn't logged in, check the CAPTCHA code
    if ( !$session->user_logged_in )
    {
      $captcha_hash = $_POST['captcha_hash'];
      $captcha_code = $_POST['captcha_code'];
      $real_code = $session->get_captcha($captcha_hash);
      if ( $real_code != $captcha_code )
        $errors[] = 'The confirmation code you entered was incorrect.';
    }
    
    if ( sizeof($errors) < 1 )
    {
      // Collect other options
      
      // Submit post
      if ( $parms['mode'] == 'reply' || $parms['mode'] == 'quote' )
      {
        $result = decir_submit_post($parms['topic_in'], $_POST['subject'], $_POST['post_text'], $post_id);
        if ( $result )
        {
          // update forum stats
          $user = $db->escape($session->username);
          $q = $db->sql_query('UPDATE '.table_prefix."decir_forums SET num_posts = num_posts+1, last_post_id = $post_id, last_post_topic = {$parms['topic_in']}, last_post_user = $session->user_id WHERE forum_id={$parms['forum_in']};");
          if ( !$q )
          {
            $db->_die('Decir posting.php under Submit post [reply]');
          }
          $url = makeUrlNS('Special', 'Forum/Topic/' . $parms['topic_in'], false, true);
          redirect($url, 'Post submitted', 'Your post has been submitted successfully.', 4);
        }
      }
      else if ( $parms['mode'] == 'topic' )
      {
        $result = decir_submit_topic($parms['forum_id'], $_POST['subject'], $_POST['post_text'], $topic_id, $post_id);
        if ( $result )
        {
          // update forum stats
          $q = $db->sql_query('UPDATE '.table_prefix."decir_forums SET num_posts = num_posts+1, num_topics = num_topics+1, last_post_id = $post_id, last_post_topic = $topic_id, last_post_user = $session->user_id WHERE forum_id={$parms['forum_id']};");
          if ( !$q )
          {
            $db->_die('Decir posting.php under Submit post [topic]');
          }
          $url = makeUrlNS('Special', 'Forum/Topic/' . $topic_id, false, true);
          redirect($url, 'Post submitted', 'Your post has been submitted successfully.', 4);
        }
      }
      return;
    }
    $mode = 'already_taken_care_of';
    $parms2 = $parms;
    $parms = htmlspecialchars($_POST['authorization']);
  }
}

if ( $mode == 'reply' || $mode == 'quote' )
{
  if ( $mode == 'reply' )
  {
    $message = '';
    $subject = '';
    // Validate topic ID
    $topic_id = intval($paths->getParam(2));
    if ( empty($topic_id) )
      die_friendly('Error', '<p>Invalid topic ID</p>');
    $title = 'Reply to topic';
  }
  else if ( $mode == 'quote' )
  {
    
    /**
     * @FIXME: validate read permissions
     */
    
    $post_id = intval($paths->getParam(2));
    if ( empty($post_id) )
      die_friendly('Error', '<p>Invalid post ID</p>');
    
    // Get post text and topic ID
    $q = $db->sql_query('SELECT p.topic_id,t.post_text,t.bbcode_uid,p.poster_name,p.post_subject FROM '.table_prefix.'decir_posts AS p
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
    $subject = 'Re: ' . htmlspecialchars($row['post_subject']);
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
  
  $forum_perms = $session->fetch_page_acl($row['forum_id'], 'DecirForum');
  $topic_perms = $session->fetch_page_acl($row['topic_id'], 'DecirTopic');
  
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
  $subject = '';
  // Validate topic ID
  $forum_id = intval($paths->getParam(2));
  if ( empty($forum_id) )
    die_friendly('Error', '<p>Invalid forum ID</p>');
  $title = 'Post new topic';
  
  // Topic ID is good, verify topic status
  $q = $db->sql_query('SELECT forum_id, forum_name FROM '.table_prefix.'decir_forums WHERE forum_id=' . $forum_id . ';');
  
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() < 1 )
    die_friendly('Error', '<p>The forum you requested does not exist.</p>');
  
  $row = $db->fetchrow();
  $db->free_result();
  
  $forum_perms = $session->fetch_page_acl($row['forum_id'], 'DecirForum');
  
  if ( !$forum_perms->get_permissions('decir_see_forum') )
    die_friendly('Error', '<p>The forum you requested does not exist.</p>');
  
  $parms = Array(
      'mode' => $mode,
      'forum_id' => $forum_id,
      'timestamp' => time(),
      'authorized' => true
    );
  
  $parms = serialize($parms);
  $parms = $aes->encrypt($parms, $session->private_key, ENC_HEX);
  
}
else if ( $mode == 'already_taken_care_of' )
{
  $mode = $parms2['mode'];
  $title = ( $mode == 'topic' ) ? 'Post new topic' : ( ( $mode == 'reply' ) ? 'Reply to topic' : ( $mode  == 'quote' ) ? 'Reply to topic with quote' : 'Duh...' );
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

if ( isset($errors) )
{
  echo '<div class="error-box" style="margin: 10px 0;">
          <b>Your post could not be submitted.</b>
          <ul>
            <li>' . implode("</li>\n            <li>", $errors) . '</li>
          </ul>
        </div>';
}

if ( $do_preview )
{
  $message = $_POST['post_text'];
  $subject = htmlspecialchars($_POST['subject']);
  $message_render = render_bbcode($message);
  $message_render = RenderMan::smilieyize($message_render);
  echo '<div style="border: 1px solid #222222; background-color: #F0F0F0; padding: 10px; max-height: 300px; clip: rect(0px,auto,auto,0px); overflow: auto; margin: 10px 0;">
          <h2>Post preview</h2>
          <p>' . $message_render . '</p>
        </div>';
}

$url = makeUrlNS('Special', 'Forum/New', 'act=post', true);
echo '<br />
      <form action="' . $url . '" method="post" enctype="multipart/form-data">';
echo '<div class="tblholder">
        <table border="0" cellspacing="1" cellpadding="4">';
echo '<tr><td class="row2">Post subject:</td><td class="row1"><input name="subject" type="text" size="50" style="width: 100%;" value="' . $subject . '" /></td>';
if ( !$session->user_logged_in )
{
  $hash = $session->make_captcha();
  $captcha_url = makeUrlNS('Special', 'Captcha/' . $hash);
  $captcha_img = "<img alt=\"If you cannot read this image please contact the site administrator for assistance.\" src=\"$captcha_url\" onclick=\"this.src=this.src+'/a';\" style=\"cursor: pointer;\" />";
  echo '<tr><td class="row2" rowspan="2">Image verification:</td><td class="row1">' . $captcha_img . '</td></tr>';
  echo '<tr><td class="row1">Please input the code you see in the image: <input type="hidden" name="captcha_hash" value="' . $hash . '" /><input type="text" name="captcha_code" size="8" /></td></tr>';
}
echo '<tr><td class="row3" colspan="2">';
echo '<textarea name="post_text" class="bbcode" rows="20" cols="80">' . $message . '</textarea>';
echo '</td></tr>';
echo '
      <!-- This authorization code is encrypted with '.AES_BITS.'-bit AES. -->
      ';
echo '<tr><th colspan="2" class="subhead"><input type="hidden" name="authorization" value="' . $parms . '" />';
echo '<input type="submit" name="do[post]" value="Submit post" style="font-weight: bold;" />&nbsp;<input type="submit" name="do[preview]" value="Show preview" /></th></tr>';
echo '</table></div>';
echo '</form>';

decir_show_footers();
$template->footer();

?>
