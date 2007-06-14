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

// Not much left now but to just do it...
$q = $db->sql_query('SELECT f.forum_id,f.forum_type,f.forum_name,f.forum_desc,f.num_topics,f.num_posts,
       p.post_id,t.topic_id,t.topic_title,u.username,u.user_level,p.timestamp FROM '.table_prefix.'decir_forums AS f
  LEFT JOIN '.table_prefix.'decir_topics AS t
    ON (t.forum_id=f.forum_id)
  LEFT JOIN '.table_prefix.'decir_posts AS p
    ON (p.topic_id=t.topic_id)
  LEFT JOIN '.table_prefix.'users AS u
    ON (u.user_id=f.last_post_user OR f.last_post_user IS NULL)
  WHERE ( t.topic_id=f.last_post_topic AND p.post_id=f.last_post_id ) OR ( f.last_post_topic IS NULL AND f.last_post_id IS NULL )
    GROUP BY f.parent,f.forum_id
    ORDER BY f.forum_order;');

if (!$q)
  $db->_die();

echo '<div class="tblholder">
      <table border="0" cellspacing="1" cellpadding="4">
        <tr>
          <th colspan="2">Forum</th>
          <th style="max-width: 50px;">Topics</th>
          <th style="max-width: 50px;">Posts</th>
          <th>Last post</th>
        </tr>';
$cat_open = false;
if ( $row = $db->fetchrow($q) )
{
  do {
    switch ( $row['forum_type'] )
    {
      case FORUM_FORUM:
        $color = ( $row['user_level'] >= USER_LEVEL_ADMIN ) ? 'AA0000' : ( ( $row['user_level'] >= USER_LEVEL_MOD ) ? '00AA00' : '0000AA' );
        // Forum
        echo '<tr><td class="row3" style="text-align: center;">&lt;icon&gt;</td><td class="row2"><b><a href="' . makeUrlNS('DecirForum', $row['forum_id']) . '">'
             . $row['forum_name'] . '</a></b><br />' . $row['forum_desc'].'</td>
             <td class="row3" style="text-align: center;">' . $row['num_topics'] . '</td>
             <td class="row3" style="text-align: center;">' . $row['num_posts'] . '</td>
             <td class="row1" style="text-align: center;">
               <small>
                 <a href="' . makeUrlNS('DecirTopic', $row['topic_id']) . '#post' . $row['post_id'] . '">' . $row['topic_title'] . '</a><br />
                 ' . date('d M Y h:i a', $row['timestamp']) . '<br />
                 by <b><a style="color: #' . $color . '" href="' . makeUrlNS('User', $row['username']) . '">' . $row['username'] . '</a></b>
               </small>
             </td>
             </tr>';
        break;
      case FORUM_CATEGORY:
        // Category
        if ( $cat_open )
          echo '</tbody>';
        echo '<tr><td class="row1" colspan="2"><h3 style="margin: 0; padding: 0;">' . $row['forum_name'] . '</h3></td><td class="row2" colspan="3"></td></tr>
              <tbody id="forum_cat_' . $row['forum_id'] . '">';
        $cat_open = true;
        break;
    }
  } while ( $row = $db->fetchrow($q) );
}
else
{
  echo '<td class="row1" colspan="4">This board has no forums.</td>';
}
if ( $cat_open )
  echo '</tbody>';
echo '</table>
      </div>';

$template->footer();

?>
