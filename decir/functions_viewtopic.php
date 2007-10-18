<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * functions_viewtopic.php - Postbit compiler
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Internally used in viewtopic; called by paginate()
 * @package Decir
 * @subpackage Presentation/UI
 * @access private
 */

class DecirPostbit
{
  var $post_template = '
    <!-- Start of post: {POST_ID} -->
    
    <a name="{POST_ID}" id="{POST_ID}"></a>
    <div class="post tblholder">
      <table border="0" cellspacing="1" cellpadding="4" style="width: 100%;">
        <!-- BEGIN post_deleted -->
        <tr>
          <td class="row3" valign="top" style="height: 100%;">
            <i>This post was deleted by {LAST_EDITED_BY}.<br />
            <b>Reason:</b> {EDIT_REASON}</i>
            <!-- BEGIN show_post -->
            <br />
            <br />
            <b>The deleted post is shown below:</b>
            <!-- END show_post -->
          </td>
          <td class="row1" style="width: 120px;" valign="top">
            {USER_LINK}
          </td>
        </tr>
        <!-- END post_deleted -->
        <!-- BEGIN show_post -->
        <tr>
          <th colspan="2" style="text-align: left;">Posted: {TIMESTAMP}</th>
        </tr>
        <tr>
          <td class="row3" valign="top" style="height: 100%;">
            <table border="0" width="100%" style="height: 100%; background-color: transparent;">
              <tr>
                <td valign="top" colspan="2">
                  {POST_TEXT}
                </td>
              </tr>
              <!-- BEGINNOT post_deleted -->
              <tr>
                <td valign="bottom" style="text-align: left; font-size: smaller;">
                  <!-- BEGIN post_edited -->
                  <i>Last edited by {LAST_EDITED_BY}; edited <b>{EDIT_COUNT}</b> time{EDIT_COUNT_S} in total<br />
                  <b>Reason:</b> {EDIT_REASON}</i>
                  <!-- END post_edited -->
                </td>
                <td valign="bottom" style="text-align: right;">
                  <small><a href="{EDIT_LINK}">edit</a> | <a href="{DELETE_LINK}">delete</a> | <a href="{QUOTE_LINK}">+ quote</a></small>
                </td>
              </tr>
              <!-- BEGINELSE post_deleted -->
              <tr>
                <td valign="bottom" style="text-align: left; font-size: smaller;">
                </td>
                <td valign="bottom" style="text-align: right;">
                  <small><a href="{RESTORE_LINK}">restore post</a> | <a href="{DELETE_LINK}">physically delete</a></small>
                </td>
              </tr>
              <!-- END post_deleted -->
            </table>
          </td>
          <td class="row1" style="width: 120px;" valign="top">
            <div class="menu_nojs">
              {USER_LINK}
              <ul>
                <li><a>View profile</a></li>
                <li><a>Visit homepage</a></li>
                <li><a href="{QUOTE_LINK}">Quote this post</a></li>
                <li><a>Vote to ban this user</a></li>
                <li><a>Send private message</a></li>
                <li><a>View all messages posted by {USERNAME}</a></li>
              </ul>
            </div>
            <span class="menuclear"></span>
            {USER_TITLE}<br />
            <br />
            Joined: {REG_TIME}
            <!-- BEGIN whos_online_support -->
              <br />
              <!-- BEGIN user_is_online -->
              <span style="color: #007900;"><b>Online</b></span>
              <!-- BEGINELSE user_is_online -->
              <span style="color: #666666;">Offline</span>
              <!-- END user_is_online -->
            <!-- END whos_online_support -->
          </td>
        </tr>
        <!-- END show_post -->
      </table>
    </div>
    
    <!-- End of post: {POST_ID} -->
    ';

  var $parser;

  /**
   * Constructor. Sets up parser object.
   */
  
  function __construct()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $this->parser = $template->makeParserText($this->post_template);
  }

  /**
   * Renders a post.
   * @todo document
   * @access private
   */
  
  function _render($_, $row)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $whos_online;
    
    $poster_name = ( $row['poster_id'] == 1 ) ? $row['poster_name'] : $row['username'];
    $datetime = date('F d, Y h:i a', $row['timestamp']);
    $post_text = render_bbcode($row['post_text'], $row['bbcode_uid']);
    $post_text = RenderMan::smilieyize($post_text);
    $regtime = date('F Y', $row['reg_time']);
    
    $user_color = '#0000AA';
    switch ( $row['user_level'] )
    {
      case USER_LEVEL_ADMIN: $user_color = '#AA0000'; break;
      case USER_LEVEL_MOD:   $user_color = '#00AA00'; break;
    }
    if ( $row['poster_id'] > 1 )
    {
      $user_link = "<a style='color: $user_color; background-color: transparent; display: inline; padding: 0;' href='".makeUrlNS('User', str_replace(' ', '_', $poster_name))."'><big>$poster_name</big></a>";
    }
    else
    {
      $user_link = '<big>'.$poster_name.'</big>';
    }
    $quote_link  = makeUrlNS('Special', 'Forum/New/Quote/' . $row['post_id'], false, true);
    $edit_link   = makeUrlNS('Special', 'Forum/Edit/' . $row['post_id'], false, true);
    $delete_link = makeUrlNS('Special', 'Forum/Delete/' . $row['post_id'], false, true);
    $restore_link = makeUrlNS('Special', 'Forum/Delete/' . $row['post_id'], 'act=restore', true);
    $user_title = 'Anonymous user';
    switch ( $row['user_level'] )
    {
      case USER_LEVEL_ADMIN: $user_title = 'Administrator'; break;
      case USER_LEVEL_MOD:   $user_title = 'Moderator'; break;
      case USER_LEVEL_MEMBER:$user_title = 'Member'; break;
      case USER_LEVEL_GUEST: $user_title = 'Guest'; break;
    }
    $leb_link = '';
    if ( $row['editor'] )
    {
      $userpage_url = makeUrlNS('User', sanitize_page_id($row['editor']), false, true);
      $row['editor'] = htmlspecialchars($row['editor']);
      $leb_link = "<a href=\"$userpage_url\">{$row['editor']}</a>";
    }
    $this->parser->assign_vars(Array(
        'POST_ID' => (string)$row['post_id'],
        'USERNAME' => $poster_name,
        'USER_LINK' => $user_link,
        'REG_TIME' => $regtime,
        'TIMESTAMP' => $datetime,
        'POST_TEXT' => $post_text,
        'USER_TITLE' => $user_title,
        'QUOTE_LINK' => $quote_link,
        'EDIT_LINK' => $edit_link,
        'DELETE_LINK' => $delete_link,
        'RESTORE_LINK' => $restore_link,
        'EDIT_COUNT' => $row['edit_count'],
        'EDIT_COUNT_S' => ( $row['edit_count'] == 1 ? '' : 's' ),
        'LAST_EDITED_BY' => $leb_link,
        'EDIT_REASON' => htmlspecialchars($row['edit_reason'])
      ));
    // Decir can integrate with the Who's Online plugin
    $who_support = $plugins->loaded('WhosOnline') && $row['user_level'] >= USER_LEVEL_GUEST;
    $user_online = false;
    if ( $who_support && in_array($row['username'], $whos_online['users']) )
    {
      $user_online = true;
    }
    elseif ( $row['poster_id'] < 2 )
    {
      $who_support = false;
    }
    $this->parser->assign_bool(Array(
        'whos_online_support' => $who_support,
        'user_is_online' => $user_online,
        'post_edited' => ( $row['edit_count'] > 0 ),
        'post_deleted' => ( $row['post_deleted'] == 1 ),
        // FIXME: This should check something on ACLs
        'show_post' => ( $row['post_deleted'] != 1 || $session->user_level >= USER_LEVEL_MOD )
      ));
    return $this->parser->run();
  }
}

?>
