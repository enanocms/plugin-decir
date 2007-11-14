<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * admin_base.php - lowlevel loader for the Decir admin panel
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

$decir_menu['DecirIndex'] = 'Administration home';

function page_Admin_DecirIndex()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    echo '<h3>Error: Not authenticated</h3><p>It looks like your administration session is invalid or you are not authorized to access this administration page. Please <a href="' . makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true) . '">re-authenticate</a> to continue.</p>';
    return;
  }
  
  //
  // Obtain forum statistics
  //
  
  // Number of users
  $q = $db->sql_query('SELECT COUNT(user_id)-1 AS num_users FROM ' . table_prefix . 'users;');
  if ( !$q )
    $db->_die();
  
  $row = $db->fetchrow();
  $db->free_result();
  $num_users = $row['num_users'];
  
  // Number of posts
  $q = $db->sql_query('SELECT COUNT(post_id) AS num_posts FROM ' . table_prefix . 'decir_posts;');
  if ( !$q )
    $db->_die();
  
  $row = $db->fetchrow();
  $db->free_result();
  $num_posts = $row['num_posts'];
  
  // Board start date
  $date = intval( getConfig('decir_install_date') );
  if ( !$date )
  {
    $date = time();
    setConfig('decir_install_date', $date);
  }
  $start_date = date('F d, Y h:i a', $date);
  
  // Average posts per day
  $board_age_days = round( ( time() / ( 60*60*24 ) ) - ( $date / ( 60*60*24 ) ) );
  if ( $board_age_days < 1 )
  {
    $avg_posts = $num_posts;
  }
  else
  {
    $avg_posts = $num_posts / $board_age_days;
  }
  
  echo '<h3>Administration home</h3>';
  echo '<p>Thank you for choosing Decir as your forum solution. From this panel you can control every aspect of your forum\'s behavior and appearance. If you need support
           for Decir, you can visit the <a href="http://forum.enanocms.org/">Enano support forums</a>.</p>';
  echo '<h3>Board statistics</h3>';
  echo "<div class=\"tblholder\">
          <table border=\"0\" cellspacing=\"1\" cellpadding=\"4\">
            <tr>
              <th colspan=\"4\">Board statistics</th>
            </tr>
            <tr>
              <td style=\"width: 25%;\" class=\"row1\"><b>Number of users:</b></td>
              <td style=\"width: 25%;\" class=\"row2\">{$num_users}</td>
              
              <td style=\"width: 25%;\" class=\"row1\"><b>Number of posts:</b></td>
              <td style=\"width: 25%;\" class=\"row2\">{$num_posts}</td>
            </tr>
            <tr>
              <td style=\"width: 25%;\" class=\"row1\"><b>Board started:</b></td>
              <td style=\"width: 25%;\" class=\"row2\">{$start_date} ({$board_age_days} days ago)</td>
              
              <td style=\"width: 25%;\" class=\"row1\"><b>Average posts per day:</b></td>
              <td style=\"width: 25%;\" class=\"row2\">{$avg_posts}</td>
            </tr>
          </table>
        </div>";
}

?>
