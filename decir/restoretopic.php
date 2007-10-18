<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * restoretopic.php - restores a deleted topic
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

require('common.php');

$tid = $paths->getParam(1);
if ( strval(intval($tid)) !== $tid )
{
  die_friendly('Error', '<p>Invalid topic ID</p>');
}

$tid = intval($tid);

// Obtain topic info
$q = $db->sql_query('SELECT t.forum_id, t.topic_id, t.topic_deleted, t.topic_deletor, t.topic_starter, t.topic_delete_reason, u.username AS deletor FROM '.table_prefix.'decir_topics AS t
                       LEFT JOIN '.table_prefix.'users AS u
                         ON ( u.user_id = t.topic_deletor OR t.topic_deletor IS NULL )
                       WHERE t.topic_id='.$tid.';');
if ( !$q )
  $db->_die('Decir restoretopic.php');

if ( $db->numrows() < 1 )
{
  die_friendly('Error', '<p>The topic you requested does not exist.</p>');
}

$row = $db->fetchrow();
$db->free_result();

$tid = intval($row['topic_id']);

$acl_type = ( $row['topic_starter'] == $session->user_id && $session->user_logged_in ) ? 'decir_undelete_own_topic' : 'decir_undelete_other_topic';

$post_perms = $session->fetch_page_acl(strval($pid), 'DecirPost');
if ( !$post_perms->get_permissions($acl_type) )
{
  die_friendly('Error', '<p>You do not have permission to restore this topic.</p>');
}

$edit_reason = '';
if ( isset($_GET['act']) && $_GET['act'] == 'submit' )
{
  if ( isset($_POST['do']['restore']) )
  {
    $result = decir_restore_topic($tid);
    if ( $result )
    {
      $url = makeUrlNS('Special', 'Forum/Topic/' . $tid, false, true);
      redirect($url, 'Topic restored', 'The selected topic has been restored.', 4);
    }
  }
  else if ( isset($_POST['do']['noop']) )
  {
    $url = makeUrlNS('Special', 'Forum/Topic/' . $tid, false, true);
    redirect($url, '', '', 0);
  }
}

$template->header(true);
$form_submit_url = makeUrlNS('Special', 'Forum/RestoreTopic/' . $tid, 'act=submit', true);
?>
<form action="<?php echo $form_submit_url; ?>" method="post" enctype="multipart/form-data">
  <p>Are you sure you want to restore this topic? If you do this, the public will be able to view it (providing that an access rule hasn't specified otherwise).</p>
  <p><input type="submit" name="do[restore]" value="Restore topic" tabindex="3" /> <input tabindex="4" type="submit" name="do[noop]" value="Cancel" /></p>
</form>
<?php
$template->footer(true);

?>
