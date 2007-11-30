<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * search.php - Integration with Enano's search system
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
$plugins->attachHook('search_global_inner', 'decir_search($query, $query_phrase, $scores, $page_data, $case_sensitive, $word_list);');

/**
 * Searches the forums for the specified search terms. Called from a hook.
 * @access private
 */

function decir_search(&$query, &$query_phrase, &$scores, &$page_data, &$case_sensitive, &$word_list)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  require_once( DECIR_ROOT . '/bbcode.php' );
  require_once( DECIR_ROOT . '/functions_viewtopic.php' );
  
  // Based on the search function from Snapr
  
  // Let's do this all in one query
  $terms = array(
      'any' => array_merge($query['any'], $query_phrase['any']),
      'req' => array_merge($query['req'], $query_phrase['req']),
      'not' => $query['not']
    );
  $where = array('any' => array(), 'req' => array(), 'not' => array());
  $where_any =& $where['any'];
  $where_req =& $where['req'];
  $where_not =& $where['not'];
  $title_col = ( $case_sensitive ) ? 'p.post_subject' : 'lcase(p.post_subject)';
  $desc_col = ( $case_sensitive ) ? 't.post_text' : 'lcase(t.post_text)';
  foreach ( $terms['any'] as $term )
  {
    $term = escape_string_like($term);
    if ( !$case_sensitive )
      $term = strtolower($term);
    $where_any[] = "( $title_col LIKE '%{$term}%' OR $desc_col LIKE '%{$term}%' )";
  }
  foreach ( $terms['req'] as $term )
  {
    $term = escape_string_like($term);
    if ( !$case_sensitive )
      $term = strtolower($term);
    $where_req[] = "( $title_col LIKE '%{$term}%' OR $desc_col LIKE '%{$term}%' )";
  }
  foreach ( $terms['not'] as $term )
  {
    $term = escape_string_like($term);
    if ( !$case_sensitive )
      $term = strtolower($term);
    $where_not[] = "$title_col NOT LIKE '%{$term}%' AND $desc_col NOT LIKE '%{$term}%'";
  }
  if ( empty($where_any) )
    unset($where_any, $where['any']);
  if ( empty($where_req) )
    unset($where_req, $where['req']);
  if ( empty($where_not) )
    unset($where_not, $where['not']);
  
  $where_any = '(' . implode(' OR ', $where_any) . '' . ( isset($where['req']) || isset($where['not']) ? ' OR 1 = 1' : '' ) . ')';
  
  if ( isset($where_req) )
    $where_req = implode(' AND ', $where_req);
  if ( isset($where_not) )
  $where_not = implode( 'AND ', $where_not);
  
  $where = implode(' AND ', $where);
  $sql = "SELECT p.post_id, p.post_subject, t.post_text, p.poster_name, p.poster_id, u.username, p.edit_count, p.last_edited_by, p.timestamp,\n"
         . "  p.post_deleted, u2.username AS editor, p.edit_reason, u.user_level, u.reg_time, t.post_text, t.bbcode_uid\n"
         . "    FROM " . table_prefix . "decir_posts AS p\n"
         . "  LEFT JOIN " . table_prefix . "decir_posts_text AS t\n"
         . "    ON ( t.post_id = p.post_id )\n"
         . "  LEFT JOIN " . table_prefix . "users AS u2\n"
         . "    ON (u2.user_id=p.last_edited_by OR p.last_edited_by IS NULL)\n"
         . "  LEFT JOIN " . table_prefix . "users AS u\n"
         . "    ON ( u.user_id = p.poster_id )\n"
         . "  WHERE ( $where ) AND post_deleted != 1\n"
         . "  GROUP BY p.post_id;";
  
  if ( !($q = $db->sql_unbuffered_query($sql)) )
  {
    $db->_die('Error is in auto-generated SQL query in the Decir plugin search module');
  }
  
  $postbit = new DecirPostbit();
  
  if ( $row = $db->fetchrow() )
  {
    do
    {
      $idstring = 'ns=DecirPost;pid=' . $row['post_id'];
      foreach ( $word_list as $term )
      {
        $func = ( $case_sensitive ) ? 'strstr' : 'stristr';
        $inc = ( $func($row['post_subject'], $term) ? 1.5 : ( $func($row['text'], $term) ? 1 : 0 ) );
        ( isset($scores[$idstring]) ) ? $scores[$idstring] = $scores[$idstring] + $inc : $scores[$idstring] = $inc;
      }
      // Generate text...
      $text = render_bbcode($row['post_text'], $row['bbcode_uid']);
      $text = highlight_and_clip_search_result($text, $word_list);
      $post_length = strlen($row['post_text']);
      
      $row['post_text'] = $text;
      $rendered_postbit = $postbit->_render('', $row);
      
      // Inject result
      
      if ( isset($scores[$idstring]) )
      {
        // echo('adding image "' . $row['img_title'] . '" to results<br />');
        $page_data[$idstring] = array(
          'page_name' => highlight_search_result(htmlspecialchars($row['post_subject']), $word_list),
          'page_text' => $rendered_postbit,
          'score' => $scores[$idstring],
          'page_note' => '[Forum post]',
          'page_id' => strval($row['post_id']),
          'namespace' => 'DecirPost',
          'page_length' => $post_length,
        );
      }
    }
    while ( $row = $db->fetchrow() );
  }
}
