<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * install.php - Database installation wizard
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

require('common.php');

$template->header();

$fid = ( $n = $paths->getParam(1) ) ? $n : ( ( isset($_GET['fid']) ) ? $_GET['fid'] : 0 );
$fid = intval($fid);

if(empty($fid))
{
  echo '<p>Invalid forum ID</p>';
  $template->footer();
  return;
}

$perms = $session->fetch_page_acl((string)$fid, 'DecirForum');
if ( !$perms->get_permissions('decir_view_forum') )
{
  die_friendly('Access denied', '<p>You are not authorized to view this forum.</p>');
}

$sort_column = ( isset($_GET['sort_column']) && in_array($_GET['sort_column'], array('t.timestamp', 't.topic_title')) ) ? $_GET['sort_column'] : 't.timestamp';
$sort_dir    = ( isset($_GET['sort_dir'])    && in_array($_GET['sort_dir'],    array('ASC', 'DESC')) ) ? $_GET['sort_dir'] : 'DESC';

$q = $db->sql_query('SELECT t.topic_id,t.topic_title,t.topic_type,t.topic_icon,
                     t.num_views,t.topic_starter AS starter_id, u.username AS topic_starter,
                     p.poster_name AS last_post_name, p.timestamp AS last_post_time, t.topic_deleted, u2.username AS deletor,
                     t.topic_delete_reason
                       FROM '.table_prefix.'decir_topics AS t
                     LEFT JOIN '.table_prefix.'decir_posts AS p
                       ON (t.topic_id = p.topic_id)
                     LEFT JOIN '.table_prefix.'decir_hits AS h
                       ON (t.topic_id=h.topic_id)
                     LEFT JOIN '.table_prefix.'users AS u
                       ON (u.user_id=t.topic_starter)
                     LEFT JOIN '.table_prefix.'users AS u2
                       ON (u2.user_id = t.topic_deletor OR t.topic_deletor IS NULL)
                     WHERE t.forum_id='.$fid.'
                     GROUP BY p.post_id
                     ORDER BY '.$sort_column.' '.$sort_dir.', p.timestamp DESC;');

if(!$q)
  $db->_die();

echo '<div class="tblholder">
      <table border="0" cellspacing="1" cellpadding="4">
      <tr>
        <th colspan="3">Topic</th>
        <th>Author</th>
        <th>Replies</th>
        <th>Views</th>
        <th>Last post</th>
      </th>';

if ( $row = $db->fetchrow() )
{
  $last_row = $row;
  $i = 0;
  $num_replies = -1;
  do
  {
    $i++;
    if ( $last_row['topic_id'] != $row['topic_id'] || $i == $db->numrows() )
    {
      if ( $num_replies < 0 )
        $num_replies = 0;
      if ( $last_row['topic_deleted'] == 1 )
      {
        $thread_link = '&lt;Deleted&gt;';
        if ( $session->get_permissions('decir_see_deleted_topic_full') )
        {
          $thread_link = '<b><a class="wikilink-nonexistent" href="' . makeUrlNS('DecirTopic', $last_row['topic_id']) . '">' . $last_row['topic_title'] . '</a></b>';
        }
        echo '<tr>
              <td class="row2"></td>
              <td class="row2"></td>
              <td class="row2" style="width: 100%;">' . $thread_link . '</td>
              <td class="row3" style="text-align: center;" colspan="4">Thread deleted by <b>' . htmlspecialchars($row['deletor']) . '</b><br />Reason: <i>' . htmlspecialchars($row['topic_delete_reason']) . '</i></td>
            </tr>';
      }
      else
      {
        echo '<tr>
              <td class="row2"></td>
              <td class="row2"></td>
              <td class="row2" style="width: 100%;"><b><a href="' . makeUrlNS('DecirTopic', $last_row['topic_id']) . '">' . $last_row['topic_title'] . '</a></b></td>
              <td class="row3" style="text-align: center; max-width: 100px;">' . $last_row['topic_starter'] . '</td>
              <td class="row1" style="text-align: center; width: 50px;">' . $num_replies . '</td>
              <td class="row1" style="text-align: center; width: 50px;">' . $last_row['num_views'] . '</td>
              <td class="row3" style="text-align: center;"><small style="white-space: nowrap;">' . date('d M Y h:i a', $last_row['last_post_time']) . '<br />by '.$last_row['last_post_name'].'</small></td>
            </tr>';
      }
      $num_replies = 0;
    }
    $num_replies++;
    $last_row = $row;
  } while ( $row = $db->fetchrow() );
}
else
{
  echo '<tr>
          <td colspan="7" style="text-align: center;" class="row1">There are no topics in this forum.</td>
        </tr>';
}

echo '</table></div>';

if ( $perms->get_permissions('decir_post') )
{
  echo '<p><a href="' . makeUrlNS('Special', 'Forum/New/Topic/' . $fid) . '">Post new topic</a></p>';
}

$template->footer();

?>
