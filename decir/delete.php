<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * edit.php - edit posts that already exist
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

require('common.php');

$pid = $paths->getParam(1);
if ( strval(intval($pid)) !== $pid )
{
  die_friendly('Error', '<p>Invalid post ID</p>');
}

$pid = intval($pid);

// Obtain post info
$q = $db->sql_query('SELECT p.post_id, p.topic_id, p.post_subject, t.post_text, t.bbcode_uid, p.poster_id, p.post_deleted FROM '.table_prefix."decir_posts AS p
                       LEFT JOIN ".table_prefix."decir_posts_text AS t
                         ON (t.post_id = p.post_id)
                       WHERE p.post_id = $pid;");
if ( !$q )
  $db->_die('Decir delete.php');

if ( $db->numrows() < 1 )
{
  die_friendly('Error', '<p>The post you requested does not exist.</p>');
}

$row = $db->fetchrow();
$db->free_result();

$tid = intval($row['topic_id']);

$own_post = ( $row['poster_id'] == $session->user_id && $session->user_logged_in );
$acl_type = ( $own_post ) ? 'decir_edit_own' : 'decir_edit_other';
  
$post_perms = $session->fetch_page_acl(strval($pid), 'DecirPost');
if ( !$post_perms->get_permissions($acl_type) )
{
  die_friendly('Error', '<p>You do not have permission to edit this post.</p>');
}

$edit_reason = '';
if ( isset($_GET['act']) && $_GET['act'] == 'submit' )
{
  if ( isset($_POST['do']['delete']) )
  {
    // Check permissions (of course!)
    $acl_type = ( $own_post
                  ? ( $_POST['delete_method'] == 'hard' ? 'decir_delete_own_post_hard'   : 'decir_delete_own_post_soft' )
                  : ( $_POST['delete_method'] == 'hard' ? 'decir_delete_other_post_hard' : 'decir_delete_other_post_soft' )
                );
    if ( !$post_perms->get_permissions($acl_type) )
    {
      die_friendly('Error', '<p>You do not have access to perform this type of deletion on this post.</p>');
    }
    // Nuke it
    $result = decir_delete_post($pid, $_POST['edit_reason'], ( $_POST['delete_method'] == 'hard' ));
    if ( $result )
    {
      $url = makeUrlNS('Special', 'Forum/Topic/' . $tid, false, true) . '#post' . $pid;
      redirect($url, 'Post deleted', 'The selected post has been deleted.', 4);
    }
    $edit_reason = htmlspecialchars($_POST['edit_reason']);
  }
  else if ( isset($_POST['do']['restore']) )
  {
    $result = decir_restore_post($pid);
    if ( $result )
    {
      $url = makeUrlNS('Special', 'Forum/Topic/' . $tid, false, true) . '#post' . $pid;
      redirect($url, 'Post restored', 'The selected post has been restored.', 4);
    }
  }
  else if ( isset($_POST['do']['noop']) )
  {
    $url = makeUrlNS('Special', 'Forum/Post/' . $pid, false, true) . '#post' . $pid;
    redirect($url, '', '', 0);
  }
}

$template->header(true);
$form_submit_url = makeUrlNS('Special', 'Forum/Delete/' . $pid, 'act=submit', true);
?>
<form action="<?php echo $form_submit_url; ?>" method="post" enctype="multipart/form-data">
  <?php if ( $row['post_deleted'] == 1 ):
  if ( isset($_GET['act']) && $_GET['act'] == 'restore' ): ?>
  <p>Are you sure you want to restore this post so that it is visible to the public?</p>
  <p><input type="submit" name="do[restore]" value="Restore post" tabindex="3" /> <input tabindex="4" type="submit" name="do[noop]" value="Cancel" /></p>
  <?php else: ?>
  <p>Are you sure you want to permanently delete this post?</p>
  <p><input type="hidden" name="delete_method" value="hard" /><input type="submit" name="do[delete]" value="Delete post" tabindex="3" /> <input tabindex="4" type="submit" name="do[noop]" value="Cancel" /></p>
  <?php endif;
        else: ?>
  <p>To delete this post, please enter a reason for deletion and click the appropriate button below.</p>
  <p>Please note that if this the first post in the thread, the entire thread will be removed.</p>
  <p><label><input type="radio" name="delete_method" value="soft" onclick="document.getElementById('decir_reason_box').style.display = 'inline';" checked="checked" tabindex="1" /> Soft delete</label> - <small>Post is replaced with the message you enter here. The original post is not removed from the database and is still visible to administrators.</small></p>
  <p><input type="text" name="edit_reason" value="<?php echo $edit_reason; ?>" tabindex="2" style="width: 97%;" id="decir_reason_box" /></p>
  <p><label><input type="radio" name="delete_method" value="hard" onclick="document.getElementById('decir_reason_box').style.display = 'none';" /> Physically remove post</label> - <small>Irreversibly removes the post from the database.</small></p>
  <p><input type="submit" name="do[delete]" value="Delete post" tabindex="3" /> <input tabindex="4" type="submit" name="do[noop]" value="Cancel" /></p>
  <?php endif; ?>
</form>
<?php
$template->footer(true);

?>
