<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * viewtopic.php - Shows individual posts
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
require('common.php');
require('bbcode.php');
require('functions_viewtopic.php');

global $whos_online;

if ( strtolower($paths->getParam(0)) == 'post' || isset($_GET['pid']) )
{
  $pid = ( $n = $paths->getParam(1) ) ? $n : ( ( isset($_GET['pid']) ) ? $_GET['pid'] : 0 );
  $pid = intval($pid);
  
  if(empty($pid))
  {
    $template->header();
    echo '<p>Invalid topic ID</p>';
    $template->footer();
    return;
  }
  
  $q = $db->sql_query('SELECT topic_id FROM '.table_prefix.'decir_posts WHERE post_id='.$pid.';');
  if ( !$q )
    $db->_die();
  
  $row = $db->fetchrow();
  $tid = intval($row['topic_id']);
  $db->free_result();
}
else
{
  $tid = ( $n = $paths->getParam(1) ) ? $n : ( ( isset($_GET['tid']) ) ? $_GET['tid'] : 0 );
  $tid = intval($tid);
  
  if(empty($tid))
  {
    $template->header();
    echo '<p>Invalid topic ID</p>';
    $template->footer();
    return;
  }
}

$q = $db->sql_query('SELECT t.forum_id, t.topic_title, f.forum_name, f.forum_id, t.topic_id, t.topic_deleted, t.topic_deletor, t.topic_delete_reason, u.username AS deletor FROM '.table_prefix.'decir_topics AS t
                       LEFT JOIN '.table_prefix.'users AS u
                         ON ( u.user_id = t.topic_deletor OR t.topic_deletor IS NULL )
                       LEFT JOIN '.table_prefix.'decir_forums AS f
                         ON ( f.forum_id = t.forum_id )
                       WHERE t.topic_id='.$tid.';');

if ( !$q )
  $db->_die();

$topic_exists = true;

if ( $db->numrows() > 0 )
{
  $row = $db->fetchrow();
  $forum_id = $row['forum_id'];
  $topic_id = $row['topic_id'];
  $topic_exists = true;
  // FIXME: This will be controlled by an ACL rule
  if ( $row['topic_deleted'] == 1 && !$session->get_permissions('decir_see_deleted_topic_full') )
  {
    $topic_exists = false;
  }
}
else
{
  $topic_exists = false;
}

// Set page title
$template->tpl_strings['PAGE_NAME'] = htmlspecialchars($row['topic_title']) . ' &laquo; ' . htmlspecialchars($row['forum_name']) . ' &laquo; Forums';

$template->header();

// build breadcrumbs
echo '<div class="breadcrumbs">';
echo '<a href="' . makeUrlNS('Special', 'Forum') . '">Forum index</a> &raquo; ';
echo '<a href="' . makeUrlNS('Special', 'Forum/ViewForum/' . $row['forum_id']) . '">' . htmlspecialchars($row['forum_name']) . '</a> &raquo; ';
echo htmlspecialchars($row['topic_title']);
echo '</div>';

if ( $row['topic_deleted'] == 1 )
{
  // User will at this point have permission to read the deleted topic (and thus restore it)
  $restore_url = makeUrlNS('Special', 'Forum/RestoreTopic/' . $topic_id, false, true);
  echo '<div class="usermessage">This topic was deleted by ' . htmlspecialchars($row['deletor']) . '. You can <a href="' . $restore_url . '">restore this topic</a>.<br />
                                 <i>Reason for deletion: ' . htmlspecialchars($row['topic_delete_reason']) . '</i></div>';
}

if ( !$topic_exists )
{
  die_friendly('Error', '<p>The topic you requested does not exist.</p>');
}

$sql = 'SELECT COUNT(post_id) AS np FROM '.table_prefix."decir_posts WHERE topic_id=$tid;";
$q = $db->sql_query($sql);
if ( !$q )
  $db->_die();
list($num_posts) = $db->fetchrow_num();
$db->free_result();

$offset = 0;
if ( $p = $paths->getParam(2) )
{
  if ( preg_match('/^start=([0-9]+)$/', $p, $m) )
  {
    $offset = intval($m[1]);
  }
}

$sql = 'SELECT p.post_id,p.poster_name,p.poster_id,u.username,p.timestamp,p.edit_count,p.last_edited_by,p.post_deleted,u2.username AS editor,p.edit_reason,u.user_level,u.reg_time,t.post_text,t.bbcode_uid FROM '.table_prefix.'decir_posts AS p
          LEFT JOIN '.table_prefix.'users AS u
            ON u.user_id=p.poster_id
          LEFT JOIN '.table_prefix.'users AS u2
            ON (u2.user_id=p.last_edited_by OR p.last_edited_by IS NULL)
          LEFT JOIN '.table_prefix.'decir_posts_text AS t
            ON p.post_id=t.post_id
          WHERE p.topic_id='.$tid.'
          GROUP BY p.post_id
          ORDER BY p.timestamp ASC;';

$q = $db->sql_unbuffered_query($sql);
if ( !$q )
  $db->_die();

$formatter = new DecirPostbit();

$html = paginate(
    $q, // MySQL result resource
    '{post_id}',
    $num_posts,
    makeUrlNS('Special', 'Forum/Topic/' . $tid . '/start=%s', false, true),
    $offset,
    15,
    array('post_id' => array($formatter, '_render'))
  );

if ( $html )
  echo $html;
else
  die_friendly('Error', '<p>The topic you requested does not exist.</p>');

$db->free_result();

if ( $topic_exists )
{
  $can_post_replies = false;
  $can_post_topics  = false;
  
  $forum_perms = $session->fetch_page_acl($forum_id, 'DecirForum');
  $topic_perms = $session->fetch_page_acl($topic_id, 'DecirTopic');
  
  if ( $forum_perms->get_permissions('decir_post') )
    $can_post_topics = true;
  
  if ( $topic_perms->get_permissions('decir_reply') )
    $can_post_replies = true;
  
  echo '<p>';
  if ( $can_post_topics )
  {
    echo '<a href="' . makeUrlNS('Special', 'Forum/New/Topic/' . $forum_id) . '">Post new topic</a>';
  }
  if ( $can_post_topics && $can_post_replies )
  {
    echo '&nbsp;&nbsp;|&nbsp;&nbsp;';
  }
  if ( $can_post_replies )
  {
    echo '<a href="' . makeUrlNS('Special', 'Forum/New/Reply/' . $topic_id) . '">Add reply</a>';
  }
  echo '</p>';
}

// log the hit
$time = time();
$q = $db->sql_query('INSERT INTO '.table_prefix."decir_hits(user_id, topic_id, timestamp) VALUES($session->user_id, $tid, $time);");
$q = $db->sql_query('UPDATE '.table_prefix."decir_topics SET num_views = num_views + 1 WHERE topic_id = $tid;");

$template->footer();

?>
