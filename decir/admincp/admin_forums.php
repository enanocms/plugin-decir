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
    switch ( $_POST['act'] )
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
        
        if ( $_POST['act'] == 'create_finish' )
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
    $q = $db->sql_unbuffered_query('SELECT forum_id, forum_name, forum_type FROM ' . table_prefix . 'decir_forums ORDER BY ( forum_type = ' . FORUM_CATEGORY . ' ) DESC, forum_order;');
    
    if ( !$q )
      $db->_die('Decir admin_forums.php selecting main forum datum');
    
    if ( $row = $db->fetchrow() )
    {
      do
      {
      }
      while ( $row = $db->fetchrow() );
    }
    else
    {
      echo '<td colspan="4" class="row3">There are no forums on this board.</td>';
    }
    
    // Create forum button
    echo '    <tr>
                <th class="subhead">
                  <button name="act" value="create">Create new forum</button>
                </th>
              </tr>';
    
    echo '  </table>
          </div>';
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
   * The name of the forum - only used in edit mode.
   * @var string
   */
  
  var $forum_name = '';
  
  /**
   * The description of the forum - only used in edit mode.
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
        
        <form action="{FORM_ACTION}" name="decir_forum_smartform_{INSTANCE_ID}" method="post" enctype="multipart/form-data">
        
        <div class="tblholder">
          <table border="0" cellspacing="1" cellpadding="4">
            <tr>
              <th colspan="2">
                <!-- BEGIN mode_is_create -->
                Create new forum
                <!-- BEGINELSE mode_is_create -->
                Edit forum {FORUM_NAME}
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
                Forum description:
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
                <button name="act" value="create_finish"><b>Create category</b></button>
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
                  Create in category:
                </td>
                <td class="row1">
                  <select name="forum_parent">
                    {CATEGORY_LIST}</select>
                </td>
              </tr>
              <tr>
                <th class="subhead" colspan="2">
                  <button name="act" value="create_finish"><b>Create forum</b></button>
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
      $category_list .= "<option value=\"$cat_id\">$cat_name</option>\n                    ";
    }
    
    // FIXME: these should really call addslashes and htmlspecialchars
    
    $parser->assign_vars(array(
        'INSTANCE_ID' => $this->instance_id,
        'FORUM_NAME' => htmlspecialchars($this->forum_name),
        'FORUM_DESC' => htmlspecialchars($this->forum_desc),
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
