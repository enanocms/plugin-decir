<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * bbcode.php - BBcode-to-HTML renderer
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
function str_replace_once($needle1, $needle2, $haystack)
{
  $len_h = strlen($haystack);
  $len_1 = strlen($needle1);
  $len_2 = strlen($needle2);
  if ( $len_h < $len_1 )
    return $haystack;
  if ( $needle1 == $haystack )
    return $needle1;
  for ( $i = 0; $i < $len_h; $i++ )
  {
    if ( substr($haystack, $i, $len_1) == $needle1 )
    {
      $haystack = substr($haystack, 0, $i) .
                  $needle2 .
                  substr($haystack, $i + $len_1);
      return $haystack;
    }
  }
}

function render_bbcode($text, $bbcode_uid)
{
  // First things first, strip out all [code] sections
  $text = decir_bbcode_strip_code($text, $bbcode_uid, $_code);
  
  // Bold text
  $text = preg_replace("/\[b:$bbcode_uid\](.*?)\[\/b:$bbcode_uid\]/is", '<b>\\1</b>', $text);
  
  // Italicized text
  $text = preg_replace("/\[i:$bbcode_uid\](.*?)\[\/i:$bbcode_uid\]/is", '<i>\\1</i>', $text);
  
  // Uunderlined text
  $text = preg_replace("/\[u:$bbcode_uid\](.*?)\[\/u:$bbcode_uid\]/is", '<u>\\1</u>', $text);
  
  // Colored text
  $text = preg_replace("/\[color=\#([A-F0-9]*){3,6}:$bbcode_uid\](.*?)\[\/color:$bbcode_uid\]/is", '<span style="color: #\\1">\\2</span>', $text);
  
  // Quotes
  $text = preg_replace("/\[quote:$bbcode_uid\](.*?)\[\/quote:$bbcode_uid\]/is", '<blockquote>\\1</blockquote>', $text);
  
  // Newlines
  $text = str_replace("\n", "<br />\n", $text);
  
  // Restore [code] blocks
  $text = decir_bbcode_restore_code($text, $bbcode_uid, $_code);
  
  // Code
  $text = preg_replace("/\[code:$bbcode_uid\](.*?)\[\/code:$bbcode_uid\]/is", '<pre>\\1</pre>', $text);
  
  return $text;
}

function decir_bbcode_strip_code($text, $uid, &$code_secs)
{
  preg_match_all("/\[code:$uid\](.*?)\[\/code:$uid\]/is", $text, $matches);
  foreach ( $matches[1] as $i => $m )
  {
    $text = str_replace_once($m, "{CODE_SECTION|$i:$uid}", $text);
    $code_secs[$i] = $m;
  }
  return $text;
}

function decir_bbcode_restore_code($text, $uid, $code_secs)
{
  foreach ( $code_secs as $i => $code )
  {
    $text = str_replace("{CODE_SECTION|$i:$uid}", $code, $text);
  }
  return $text;
}

function bbcode_strip_uid($bbcode, $uid)
{
  // BBcode tags with attributes
  $bbcode = preg_replace("/\[([a-z]+?):{$uid}=([^\]]+?)\](.*?)\[\/\\1:{$uid}\]/is", '[\\1=\\2]\\3[/\\1]', $bbcode);
  
  // BBcode tags without attributes
  $bbcode = preg_replace("/\[([a-z]+?):{$uid}\](.*?)\[\/\\1:{$uid}\]/is", '[\\1]\\2[/\\1]', $bbcode);
  
  return $bbcode;
}

