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

$q = $db->sql_query('SELECT t.topic_id,t.topic_title,t.topic_type,t.topic_icon,COUNT(p.post_id)-1 AS num_replies,
                     COUNT(h.hit_id) AS num_views,t.topic_starter AS starter_id, u.username AS topic_starter,
                     p.poster_name AS last_post_name, p.timestamp AS last_post_time
                       FROM '.table_prefix.'decir_topics AS t
                     LEFT JOIN '.table_prefix.'decir_posts AS p
                       ON (t.last_post=p.post_id)
                     LEFT JOIN '.table_prefix.'decir_hits AS h
                       ON (t.topic_id=h.topic_id)
                     LEFT JOIN '.table_prefix.'users AS u
                       ON (u.user_id=t.topic_starter)
                     WHERE t.forum_id='.$fid.'
                     GROUP BY t.topic_id
                     ORDER BY '.$sort_column.' '.$sort_dir.';');

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
  do
  {
    echo '<tr>
            <td class="row2"></td>
            <td class="row2"></td>
            <td class="row2" style="width: 100%;"><b><a href="' . makeUrlNS('DecirTopic', $row['topic_id']) . '">' . $row['topic_title'] . '</a></b></td>
            <td class="row3" style="text-align: center; max-width: 100px;">' . $row['topic_starter'] . '</td>
            <td class="row1" style="text-align: center; width: 50px;">' . $row['num_replies'] . '</td>
            <td class="row1" style="text-align: center; width: 50px;">' . $row['num_views'] . '</td>
            <td class="row3" style="text-align: center;"><small style="white-space: nowrap;">' . date('d M Y h:i a', $row['last_post_time']) . '<br />by '.$row['last_post_name'].'</small></td>
          </tr>';
  } while ( $row = $db->fetchrow() );
}

echo '</table></div>';

$template->footer();

?>
