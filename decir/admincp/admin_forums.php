<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * admin_forums.php - forum creation and management frontend
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

$decir_menu['DecirForums'] = 'Manage forums';
require( DECIR_ROOT . '/constants.php' );

function page_Admin_DecirForums()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    echo '<h3>Error: Not authenticated</h3><p>It looks like your administration session is invalid or you are not authorized to access this administration page. Please <a href="' . makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true) . '">re-authenticate</a> to continue.</p>';
    return;
  }
  
  $show_main_menu = true;
  
  if ( isset($_POST['act']) )
  {
    $act = ( strpos($_POST['act'], ';') ) ? substr($_POST['act'], 0, strpos($_POST['act'], ';')) : $_POST['act'];
    if ( strpos($_POST['act'], ';') )
    {
      $parms = substr($_POST['act'], strpos($_POST['act'], ';') + 1);
      preg_match_all('/([a-z0-9_]+)=([^;]*)/', $parms, $matches);
      $parms = array();
      foreach ( $matches[2] as $id => $parmdata )
      {
        if ( preg_match('/^[0-9]+$/', $parmdata) )
          $parmdata = intval($parmdata);
        $parms[ $matches[1][$id] ] = $parmdata;
      }
    }
    switch ( $act )
    {
      case "create":
      case "create_finish":
        
        // Do we have any categories yet?
        $q = $db->sql_query('SELECT forum_id, forum_name FROM ' . table_prefix . 'decir_forums WHERE forum_type = ' . FORUM_CATEGORY . ';');
        if ( !$q )
          $db->_die('Decir admin_forums.php retrieving category count');
        $need_category = ( $db->numrows() < 1 );
        $cats = array();
        if ( !$need_category )
        {
          while ( list($cat_id, $cat_name) = $db->fetchrow_num() )
          {
            $cats[ $cat_id ] = $cat_name;
          }
        }
        
        $db->free_result();
        
        if ( $act == 'create_finish' )
        {
          $errors = array();
          $forum_type = intval($_POST['forum_type']);
          if ( $forum_type != FORUM_FORUM && $forum_type != FORUM_CATEGORY )
            $errors[] = 'Invalid forum type. <tt>X.X</tt>';
          $forum_name = trim($_POST['forum_name']);
          if ( empty($forum_name) )
            $errors[] = 'Please enter a name for this forum.';
          $forum_desc = '';
          $forum_parent = 0;
          if ( $forum_type == FORUM_FORUM )
          {
            $forum_desc = trim($_POST['forum_desc']);
            if ( empty($forum_desc) )
              $errors[] = 'Please enter a description for this forum.';
            $forum_parent = intval($_POST['forum_parent']);
            if ( !isset($cats[$forum_parent]) )
              $errors[] = 'Invalid parent category';
          }
          if ( count($errors) > 0 )
          {
            // Errors encountered - bounce form back to the user
            $show_main_menu = false;
            echo '<div class="error-box">The forum could not be created.<ul><li>' . implode("</li>\n      <li>", $errors) . '</li></ul></div>';
            $form = new Decir_Admin_SmartForm_Forum(DECIR_ADMIN_MODE_CREATE);
            $form->forum_name = $forum_name;
            $form->forum_desc = $forum_desc;
            $form->forum_type = $forum_type;
            $form->need_category = $need_category;
            $form->category_list = $cats;
            echo $form->html();
            break;
          }
          // All checks passed. Create forum.
          $forum_name_db = $db->escape($forum_name);
          $forum_desc_db = $db->escape($forum_desc);
          $sql = 'INSERT INTO ' . table_prefix . "decir_forums(forum_name, forum_desc, forum_type, parent, num_topics, num_posts) VALUES\n"
                 . "  ( '$forum_name_db', '$forum_desc_db', $forum_type, $forum_parent, 0, 0 );";
          if ( $db->sql_query($sql) )
          {
            $forum_name = htmlspecialchars($forum_name);
            $type = ( $forum_type == FORUM_FORUM ) ? 'forum' : 'category';
            echo "<div class=\"info-box\">The {$type} \"{$forum_name}\" has been created successfully.</div>";
          }
          break;
        }
        // Create a smartform
        $show_main_menu = false;
        $form = new Decir_Admin_SmartForm_Forum(DECIR_ADMIN_MODE_CREATE);
        $form->need_category = $need_category;
        $form->category_list = $cats;
        echo $form->html();
        break;
      case 'edit':
      case 'edit_finish':
        
        if ( !isset($parms['fid']) || ( isset($parms['fid']) && !is_int($parms['fid']) ) )
        {
          echo '<div class="error-box">Invalid forum ID passed to editor.</div>';
          break;
        }
        
        // Fetch category list
        $q = $db->sql_query('SELECT forum_id, forum_name FROM ' . table_prefix . 'decir_forums WHERE forum_type = ' . FORUM_CATEGORY . ';');
        if ( !$q )
          $db->_die('Decir admin_forums.php retrieving category count');
        $need_category = ( $db->numrows() < 1 );
        $cats = array();
        if ( !$need_category )
        {
          while ( list($cat_id, $cat_name) = $db->fetchrow_num() )
          {
            $cats[ $cat_id ] = $cat_name;
          }
        }
        
        $db->free_result();
        
        // $fid is safe (validated as an integer).
        $fid =& $parms['fid'];
        $q = $db->sql_query('SELECT forum_id, forum_name, forum_desc, parent, forum_type FROM ' . table_prefix . 'decir_forums WHERE forum_id = ' . $fid . ';');
        if ( !$q )
          $db->_die('Decir admin_forums.php selecting forum data for edit');
        
        $row = $db->fetchrow();
        $db->free_result();
        
        if ( $act == 'edit_finish' )
        {
          $errors = array();
          // Validate and update
          if ( $row['forum_type'] == FORUM_FORUM )
          {
            $forum_name = trim($_POST['forum_name']);
            if ( empty($forum_name) )
              $errors[] = 'Please enter a name for this forum.';
            
            $forum_desc = trim($_POST['forum_desc']);
            if ( empty($forum_desc) )
              $errors[] = 'Please enter a description for this forum.';
            
            $forum_parent = intval($_POST['forum_parent']);
            if ( !isset($cats[$forum_parent]) )
              $errors[] = 'Invalid parent category';
            
            $forum_name_db = $db->escape($forum_name);
            $forum_desc_db = $db->escape($forum_desc);
            
            $sql = 'UPDATE ' . table_prefix . "decir_forums SET forum_name='$forum_name_db',forum_desc='$forum_desc_db',parent=$forum_parent WHERE forum_id = $fid;";
          }
          else if ( $row['forum_type'] == FORUM_CATEGORY )
          {
            $forum_name = trim($_POST['forum_name']);
            if ( empty($forum_name) )
              $errors[] = 'Please enter a name for this forum.';
            $forum_name_db = $db->escape($forum_name);
            
            $sql = 'UPDATE ' . table_prefix . "decir_forums SET forum_name='$forum_name_db' WHERE forum_id = $fid;";
          }
          else
          {
            $db->_die('Mom, I feel sick. Can I lay down for a while? ' . __FILE__ . ':' . __LINE__);
          }
          if ( count($errors) < 1 )
          {
            if ( $db->sql_query($sql) )
            {
              $show_main_menu = true;
              echo '<div class="info-box">The forum or category was updated.</div>';
              break;
            }
            else
            {
              $db->_die('Decir admin_forums.php update forum main SQL query');
            }
          }
          else
          {
            echo '<div class="error-box">The forum was not updated because you entered something invalid.<ul><li>' . implode("</li>\n      <li>", $errors) . '</li></ul></div>';
          }
        }
        
        // This is the amazing part. We'll let the smart form do the work for us.
        $form = new Decir_Admin_SmartForm_Forum(DECIR_ADMIN_MODE_EDIT);
        $form->forum_name = $row['forum_name'];
        $form->forum_desc = $row['forum_desc'];
        $form->forum_type = $row['forum_type'];
        $form->forum_parent = $row['parent'];
        $form->forum_id = $row['forum_id'];
        $form->category_list = $cats;
        echo $form->html();
        
        $show_main_menu = false;
        break;
      case 'save_order':
        $order = explode(',', $_POST['forum_order']);
        $i = 0;
        $sql = array();
        foreach ( $order as $forum_id )
        {
          $i++;
          if ( strval(intval($forum_id)) != $forum_id )
          {
            echo '<p>Hacking attempt</p>';
            break;
          }
          $sql[] = 'UPDATE ' . table_prefix . "decir_forums SET forum_order = $i WHERE forum_id = $forum_id;";
        }
        foreach ( $sql as $s )
        {
          if ( !$db->sql_query($s) )
            $db->_die('Decir admin_forums.php updating forum order');
        }
        echo '<div class="info-box">The forum order was updated.</div>';
        break;
    }
  }
  
  if ( $show_main_menu )
  {
    // Display the main forum admin interface
    $form_url = makeUrlNS('Special', 'DecirAdmin', "module={$paths->nslist['Admin']}DecirForums", true);
    echo "<form action=\"$form_url\" method=\"post\" enctype=\"multipart/form-data\">";
    echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">
              <tr>
                <th colspan="4">Forum administration</th>
              </tr>';
    // Select and display all forums
    $q = $db->sql_unbuffered_query('SELECT forum_id, forum_name, forum_desc, forum_type, num_topics, num_posts FROM ' . table_prefix . 'decir_forums GROUP BY parent, forum_id ORDER BY forum_order;');
    
    if ( !$q )
      $db->_die('Decir admin_forums.php selecting main forum datum');
    
    $order_forums = array();
    $order_cats = array();
    if ( $row = $db->fetchrow() )
    {
      $cat_open = false;
      echo '<tr>
              <th class="subhead">Forum</th>
              <th class="subhead" style="max-width: 50px;">Topics</th>
              <th class="subhead" style="max-width: 50px;">Posts</th>
              <th class="subhead">Admin tasks</th>
            </tr>';
      do
      {
        switch ( $row['forum_type'] )
        {
          case FORUM_FORUM:
            // Forum
            echo '<tr>
                    <td class="row2 decir_forum"><input type="hidden" value="' . $row['forum_id'] . '" />
                      <b><a href="' . makeUrlNS('DecirForum', $row['forum_id']) . '">'
                      . $row['forum_name'] . '</a></b><br />' . $row['forum_desc'].'
                    </td>
                   <td class="row3" style="text-align: center;">' . $row['num_topics'] . '</td>
                   <td class="row3" style="text-align: center;">' . $row['num_posts'] . '</td>
                   <td class="row1" style="text-align: center;">';
            
            echo '<button name="act" value="edit;fid=' . $row['forum_id'] . '">Edit</button>&nbsp;';
            echo '<button name="act" value="delete;fid=' . $row['forum_id'] . '">Delete</button>';
            
            echo '</td>
                 </tr>';
            $order_forums[] = $row['forum_id'];
            break;
          case FORUM_CATEGORY:
            // Category
            if ( $cat_open )
              echo '</tbody>';
            echo '<tr>
                    <td class="row1 decir_category" colspan="1"><input type="hidden" value="' . $row['forum_id'] . '" />
                      <h3 style="margin: 0; padding: 0;">' . $row['forum_name'] . '</h3>
                    </td>
                    <td class="row2" colspan="2"></td>';
            echo '<td class="row1" style="text-align: center;">';
            echo '<button name="act" value="edit;fid=' . $row['forum_id'] . '">Edit</button>&nbsp;';
            echo '<button name="act" value="delete;fid=' . $row['forum_id'] . '">Delete</button>';
            echo '</td>';
            echo '</tr>
                  <tbody id="forum_cat_' . $row['forum_id'] . '">';
            $cat_open = true;
            $order_cats[] = $row['forum_id'];
            break;
        }
      }
      while ( $row = $db->fetchrow($q) );
    }
    else
    {
      echo '<td colspan="4" class="row3">There are no forums on this board.</td>';
    }
    
    // Create forum button
    echo '    <tr>
                <th class="subhead" colspan="4">
                  <button name="act" value="create">Create new forum</button>
                  <button name="act" value="save_order">Save forum order</button>
                </th>
              </tr>';
    
    echo '  </table>
          </div>';
    $order = /* implode(',', $order_cats) . ';' . */ implode(',', $order_forums);
    echo '<input type="text" name="forum_order" id="forum_order" value="' . $order . '" />';
    echo "</form>";
  }
}

/**
 * Smart form for creating and editing Decir forums.
 * @package Decir
 * @subpackage Administration
 * @copyright 2007 Dan Fuhry
 * @license GPL
 */

class Decir_Admin_SmartForm_Forum
{
  
  /**
   * Whether we are creating or editing a forum.
   * @var int
   */
  
  var $form_mode;
  
  /**
   * The unique ID of the forum - only used in edit mode.
   * @var int
   */
  
  var $forum_id = 0;
  
  /**
   * The name of the forum - only used in edit mode or if performing a bounceback from a failed form validation.
   * @var string
   */
  
  var $forum_name = '';
  
  /**
   * The description of the forum - only used in edit mode or if performing a bounceback from a failed form validation.
   * @var string
   */
  
  var $forum_desc = '';
  
  /**
   * The type of entry this is (forum or category)
   * @var int
   */
  
  var $forum_type = -1;
  
  /**
   * Track if we need to make the user create a category as opposed to a forum.
   * @var bool
   */
  
  var $need_category = false;
  
  /**
   * The list of categories on the site.
   * @var array
   */
  
  var $category_list = array();
  
  /**
   * The parent category of the forum we're editing.
   * @var int
   */
  
  var $forum_parent = -1;
  
  /**
   * Instance ID for javascripting
   * @var string
   */
  
  var $instance_id;
  
  /**
   * Constructor
   * @param int Form type - should be DECIR_ADMIN_MODE_CREATE or DECIR_ADMIN_MODE_EDIT
   */
  
  function __construct($form_mode)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $form_mode = intval($form_mode);
    if ( $form_mode != DECIR_ADMIN_MODE_CREATE && $form_mode != DECIR_ADMIN_MODE_EDIT )
      die('Syntax error: $form_mode to Decir_Admin_SmartForm_Forum::__construct should be DECIR_ADMIN_MODE_CREATE or DECIR_ADMIN_MODE_EDIT.');
    
    $this->form_mode = $form_mode;
    $this->instance_id = $session->dss_rand();
  }
  
  /**
   * PHP4 compatibility constructor.
   * @see Decir_Admin_SmartForm_Forum::__construct
   */
  
  function Decir_Admin_SmartForm_Forum($form_type)
  {
    $this->__construct($form_type);
  }
  
  /**
   * Render the form into HTML.
   * @return string
   */
  
  function html()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $f_f = FORUM_FORUM;
    $f_c = FORUM_CATEGORY;
    $tpl_code = <<<EOF
        <!-- Start forum creation/edit smartform {INSTANCE_ID} -->
        
        <!-- BEGIN mode_is_create -->
        <script type="text/javascript">
        
          function set_form_type_category_{INSTANCE_ID}()
          {
            document.getElementById('type_category_{INSTANCE_ID}').style.display = 'block';
            document.getElementById('type_forum_{INSTANCE_ID}').style.display = 'none';
          }
          
          function set_form_type_forum_{INSTANCE_ID}()
          {
            document.getElementById('type_category_{INSTANCE_ID}').style.display = 'none';
            document.getElementById('type_forum_{INSTANCE_ID}').style.display = 'block';
          }
          var set_form_type_auto_{INSTANCE_ID} = function()
          {
            if ( document.getElementById('radio_forum_{INSTANCE_ID}').checked )
            {
              set_form_type_forum_{INSTANCE_ID}();
            }
            else if ( document.getElementById('radio_category_{INSTANCE_ID}').checked )
            {
              set_form_type_category_{INSTANCE_ID}();
            }
          }
          
          addOnloadHook(set_form_type_auto_{INSTANCE_ID});
        </script>
        <!-- END mode_is_create -->
        
        <form action="{FORM_ACTION}" name="decir_forum_smartform_{INSTANCE_ID}" method="post" enctype="multipart/form-data">
        
        <div class="tblholder">
          <table border="0" cellspacing="1" cellpadding="4">
            <tr>
              <th colspan="2">
                <!-- BEGIN mode_is_create -->
                Create new forum
                <!-- BEGINELSE mode_is_create -->
                Editing {FORUM_NAME}
                <!-- END mode_is_create -->
              </th>
            </tr>
            <!-- BEGIN mode_is_create -->
            <tr>
              <td class="row2" style="width: 50%;">
                Forum type:
              </td>
              <td class="row1" style="width: 50%;">
                <label>
                  <input id="radio_forum_{INSTANCE_ID}" type="radio" name="forum_type" value="{TYPE_FORUM}" onclick="set_form_type_forum_{INSTANCE_ID}();" <!-- BEGIN type_is_forum -->checked="checked" <!-- END type_is_forum -->/> Forum
                </label>
                <label>
                  <input id="radio_category_{INSTANCE_ID}" type="radio" name="forum_type" value="{TYPE_CATEGORY}" onclick="set_form_type_category_{INSTANCE_ID}();" <!-- BEGINNOT type_is_forum -->checked="checked" <!-- END type_is_forum -->/> Category
                </label>
              </td>
            </tr>
            <!-- END mode_is_create -->
            <tr>
              <td class="row2" style="width: 50%;">
                <!-- BEGINNOT mode_is_create -->
                  <!-- BEGINNOT type_is_forum -->
                    Category name:
                  <!-- BEGINELSE type_is_forum -->
                    Forum name:
                  <!-- END type_is_forum -->
                <!-- BEGINELSE mode_is_create -->
                  Forum name:
                <!-- END mode_is_create -->                  
              </td>
              <td class="row1" style="width: 50%;">
              <input type="text" name="forum_name" size="40" value="{FORUM_NAME}" />
              </td>
            </tr>
          </table>
        </div>
        
        <!-- BEGIN show_opts_category -->
        <div class="tblholder" id="type_category_{INSTANCE_ID}"<!-- BEGIN mode_is_create --> style="display: none;" <!-- END mode_is_create -->>
          <table border="0" cellspacing="1" cellpadding="4">
            <tr>
              <th colspan="2">
                Category options
              </th>
            </tr>
            <tr>
              <td class="row2" style="width: 50%;">
                Stub
              </td>
              <td class="row1" style="width: 50%;">
                Stub
              </td>
            </tr>
            <tr>
              <th class="subhead" colspan="2">
                <!-- BEGIN mode_is_create -->
                <button name="act" value="create_finish"><b>Create category</b></button>
                <!-- BEGINELSE mode_is_create -->
                <button name="act" value="edit_finish;fid={FORUM_ID}"><b>Save changes</b></button>
                <!-- END mode_is_create -->
                <button name="act" value="noop" style="font-weight: normal;">Cancel</button>
              </th>
            </tr>
          </table>
        </div>
        <!-- END show_opts_category -->
        
        <!-- BEGIN show_opts_forum -->
        <div id="type_forum_{INSTANCE_ID}">
          
          <!-- BEGIN need_category -->
          
          <div class="error-box">
            There aren't any categories on this site yet. You need to create at least one category before you can create a forum.
          </div>
          
          <!-- BEGINELSE need_category -->
          
          <div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">
              <tr>
                <th colspan="2">
                  Forum options
                </th>
              </tr>
              <tr>
                <td class="row2" style="width: 50%;">
                  Forum description:
                </td>
                <td class="row1" style="width: 50%;">
                  <input type="text" name="forum_desc" size="40" value="{FORUM_DESC}" />
                </td>
              </tr>
              <tr>
                <td class="row2">
                  <!-- BEGIN mode_is_create -->Create in category:<!-- BEGINELSE mode_is_create -->Parent category:<!-- END mode_is_create -->
                </td>
                <td class="row1">
                  <select name="forum_parent">
                    {CATEGORY_LIST}</select>
                </td>
              </tr>
              <tr>
                <th class="subhead" colspan="2">
                  <!-- BEGIN mode_is_create -->
                  <button name="act" value="create_finish"><b>Create forum</b></button>
                  <!-- BEGINELSE mode_is_create -->
                  <button name="act" value="edit_finish;fid={FORUM_ID}"><b>Save changes</b></button>
                  <!-- END mode_is_create -->
                  <button name="act" value="noop" style="font-weight: normal;">Cancel</button>
                </th>
              </tr>
            </table>
          </div>
          
          <!-- END need_category -->
          
        </div>
        <!-- END show_opts_forum -->
        
        </form>
        
        <!-- Finish forum creation/edit smartform {INSTANCE_ID} -->
EOF;
    $parser = $template->makeParserText($tpl_code);
    
    $category_list = '';
    foreach ( $this->category_list as $cat_id => $cat_name )
    {
      $cat_id = intval($cat_id);
      $cat_name = htmlspecialchars($cat_name);
      $sel = ( $cat_id == $this->forum_parent ) ? ' selected="selected"' : '';
      $category_list .= "<option {$sel}value=\"$cat_id\">$cat_name</option>\n                    ";
    }
    
    // FIXME: these should really call addslashes and htmlspecialchars
    
    $parser->assign_vars(array(
        'INSTANCE_ID' => $this->instance_id,
        'FORUM_NAME' => htmlspecialchars($this->forum_name),
        'FORUM_DESC' => htmlspecialchars($this->forum_desc),
        'FORUM_ID' => $this->forum_id,
        'FORM_ACTION' => makeUrlNS('Special', 'DecirAdmin', 'module=' . $paths->nslist['Admin'] . 'DecirForums', true),
        'TYPE_FORUM' => FORUM_FORUM,
        'TYPE_CATEGORY' => FORUM_CATEGORY,
        'CATEGORY_LIST' => $category_list
      ));
    $parser->assign_bool(array(
      'mode_is_create' => ( $this->form_mode == DECIR_ADMIN_MODE_CREATE ),
      'show_opts_category' => ( $this->form_mode == DECIR_ADMIN_MODE_CREATE ? true : $this->forum_type == FORUM_CATEGORY ),
      'show_opts_forum' => ( $this->form_mode == DECIR_ADMIN_MODE_CREATE ? true : $this->forum_type == FORUM_FORUM ),
      'type_is_forum' => ( $this->forum_type != FORUM_CATEGORY ),
      'need_category' => ( $this->form_mode == DECIR_ADMIN_MODE_CREATE && $this->need_category )
    ));
    
    return $parser->run();
  }
  
}

?>
