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
 
$decir_menu = array();

// Only load the actual admin modules if they're needed
function page_Special_DecirAdmin_preloader()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $decir_menu;
  require( DECIR_ROOT . '/admincp/admin_index.php' );
  require( DECIR_ROOT . '/admincp/admin_forums.php' );
  
  $decir_menu['Special|Administration'] = 'Main administration panel';
}

$plugins->attachHook('base_classes_initted', '
    $paths->add_page(Array(
      \'name\'=>\'Decir Administration Panel\',
      \'urlname\'=>\'DecirAdmin\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>0,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
  ');

function page_Special_DecirAdmin()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( $session->user_level < USER_LEVEL_ADMIN )
    die_friendly('Access denied', '<p>This page is restricted access.</p>');
  
  if ( $session->auth_level < USER_LEVEL_ADMIN )
    redirect(makeUrlNS('Special', 'Login/' . $paths->page, 'level=' . USER_LEVEL_ADMIN, true), '', '', 0);
  
  $session->theme = 'admin';
  $session->style = 'default';
  $template = false;
  unset($GLOBALS['template']);
  unset($template);
  $GLOBALS['template'] = new template();
  $template =& $GLOBALS['template'];
  
  $template->add_header('<script type="text/javascript" src="' . scriptPath . '/decir/js/admin/reorder.js"></script>');
  
  $template->header();
  ?>
  Add or remove forums, control user permissions, and check forum statistics.
  <table border="0" cellspacing="0" cellpadding="0" width="100%" style="margin-top: 10px;">
    <tr>
      <td style="padding-right: 20px; width: 200px;" valign="top">
        <h4 style="margin: 0 0 10px 0;">Decir configuration</h4>
        <ul>
          <?php
            global $decir_menu;
            foreach ( $decir_menu as $page_id => $link_text )
            {
              if ( strpos($page_id, '|') )
              {
                $namesp  = substr($page_id, 0, strpos($page_id, '|'));
                $page_id = substr($page_id, strpos($page_id, '|') + 1);                
              }
              else
              {
                $namesp = 'Admin';
              }
              $link_text = htmlspecialchars($link_text);
              if ( $namesp == 'Admin' )
              {
                $url = makeUrlNS('Special', 'DecirAdmin', 'module=' . $paths->nslist[$namesp] . $page_id, true);
              }
              else
              {
                $url = makeUrlNS($namesp, $page_id);
              }
              echo '<li><a href="' . $url . "\">$link_text</a></li>";
            }
          ?>
        </ul>
      </td>
      <td valign="top">
        <?php
          $module = ( isset($_GET['module']) ) ? $_GET['module'] : $paths->nslist['Admin'] . 'DecirIndex';
          list($page_id, $namespace) = RenderMan::strToPageID($module);
          $page = new PageProcessor($page_id, $namespace);
          $page->send();
        ?>
      </td>
    </tr>
  </table>
  <?php
  
  $template->footer();
}

?>
