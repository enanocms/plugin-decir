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
require('bbcode.php');

$pid = $paths->getParam(1);
if ( strval(intval($pid)) !== $pid )
{
  die_friendly('Error', '<p>Invalid post ID</p>');
}

$pid = intval($pid);

// Obtain post info
$q = $db->sql_query('SELECT p.post_id, p.post_subject, t.post_text, t.bbcode_uid, p.poster_id FROM '.table_prefix."decir_posts AS p
                       LEFT JOIN ".table_prefix."decir_posts_text AS t
                         ON (t.post_id = p.post_id)
                       WHERE p.post_id = $pid;");
if ( !$q )
  $db->_die('Decir edit.php');

if ( $db->numrows() < 1 )
{
  die_friendly('Error', '<p>The post you requested does not exist.</p>');
}

$row = $db->fetchrow();
$db->free_result();

$acl_type = ( $row['poster_id'] == $session->user_id && $session->user_logged_in ) ? 'decir_edit_own' : 'decir_edit_other';
  
$post_perms = $session->fetch_page_acl(strval($pid), 'DecirPost');
if ( !$post_perms->get_permissions($acl_type) )
{
  die_friendly('Error', '<p>You do not have permission to edit this post.</p>');
}

$show_preview = false;
$form_submit_url = makeUrlNS('Special', 'Forum/Edit/' . $pid, 'act=submit', true);
$post_text = htmlspecialchars(bbcode_strip_uid($row['post_text'], $row['bbcode_uid']));
$post_subject = htmlspecialchars($row['post_subject']);
$edit_reason = '';

if ( isset($_GET['act']) && $_GET['act'] == 'submit' )
{
  if ( isset($_POST['do']['preview']) )
  {
    $show_preview = true;
    $post_text = htmlspecialchars($_POST['post_text']);
    $post_subject = htmlspecialchars($_POST['post_subject']);
    $edit_reason = htmlspecialchars($_POST['edit_reason']);
    $message_render = render_bbcode($post_text);
    $message_render = RenderMan::smilieyize($message_render);
  }
  else if ( isset($_POST['do']['save']) )
  {
    // Save changes
    if ( isset($_POST['do']['delete']) )
    {
      // Nuke it
      $result = decir_delete_post($pid, $_POST['edit_reason']);
      if ( $result )
      {
        $url = makeUrlNS('Special', 'Forum/Post/' . $pid, false, true) . '#post' . $pid;
        redirect($url, 'Post deleted', 'The selected post has been deleted.', 4);
      }
    }
    $post_text = trim(htmlspecialchars($_POST['post_text']));
    $post_subject = trim(htmlspecialchars($_POST['post_subject']));
    $edit_reason = trim(htmlspecialchars($_POST['edit_reason']));
    $errors = array();
    
    if ( empty($post_text) )
      $errors[] = 'Please enter some post text.';
    
    $result = decir_edit_post($pid, $post_subject, $post_text, $edit_reason);
    if ( $result )
    {
      $url = makeUrlNS('Special', 'Forum/Post/' . $pid, false, true) . '#post' . $pid;
      redirect($url, 'Post successful', 'Your changes to this post have been saved.', 4);
    }
  }
  else if ( isset($_POST['do']['noop']) )
  {
    $url = makeUrlNS('Special', 'Forum/Post/' . $pid, false, true) . '#post' . $pid;
    redirect($url, '', '', 0);
  }
}

// add JS for editor
$template->add_header('<!-- DECIR BEGIN -->
    <script type="text/javascript" src="' . scriptPath . '/decir/js/bbcedit.js"></script>
    <script type="text/javascript" src="' . scriptPath . '/decir/js/colorpick/jquery.js"></script>
    <script type="text/javascript" src="' . scriptPath . '/decir/js/colorpick/farbtastic.js"></script>
    <link rel="stylesheet" type="text/css" href="' . scriptPath . '/decir/js/bbcedit.css" />
    <link rel="stylesheet" type="text/css" href="' . scriptPath . '/decir/js/colorpick/farbtastic.css" />
    <!-- DECIR END -->');

$template->header();

if ( $show_preview )
{
  echo '<div style="border: 1px solid #222222; background-color: #F0F0F0; padding: 10px; max-height: 300px; clip: rect(0px,auto,auto,0px); overflow: auto; margin: 10px 0;">
          <h2>Post preview</h2>
          <p>' . $message_render . '</p>
        </div>';
}

?>
<form action="<?php echo $form_submit_url; ?>" method="post" enctype="multipart/form-data">
  <div class="tblholder">
    <table border="0" cellspacing="1" cellpadding="4">
      <tr>
        <th colspan="2">Editing post: <?php echo $post_subject; ?></th>
      </tr>
      <tr>
        <td class="row2">Delete post:</td>
        <td class="row1"><label><input type="checkbox" name="do[delete]" /> To delete this post, check this box and click Save.</label><br /><small>If this is the first post in the thread, the entire thread will be deleted.</small></td>
      </tr>
      <tr>
        <td class="row2">Post subject:</td>
        <td class="row1"><input type="text" name="post_subject" value="<?php echo $post_subject; ?>" style="width: 100%;" /></td>
      </tr>
      <tr>
        <td class="row2">Reason for editing:</td>
        <td class="row1"><input type="text" name="edit_reason" value="<?php echo $edit_reason; ?>" style="width: 100%;" /></td>
      </tr>
      <tr>
        <td class="row3" colspan="2">
          <textarea name="post_text" class="bbcode" rows="20" cols="80"><?php echo $post_text; ?></textarea>
        </td>
      </tr>
      <tr>
        <th class="subhead" colspan="2">
          <input type="submit" name="do[save]" value="Save changes" />
          <input type="submit" name="do[preview]" value="Show preview" />
          <input type="submit" name="do[noop]" value="Cancel" />
        </th>
      </tr>
    </table>
  </div>
</form>
<?php

$template->footer();

?>
