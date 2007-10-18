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
 
function render_bbcode($text, $bbcode_uid = false)
{
  // First things first, strip out all [code] sections
  $text = decir_bbcode_strip_code($text, $bbcode_uid, $_code);
  
  if ( $bbcode_uid )
    $bbcode_uid = ':' . $bbcode_uid;
  
  // Bold text
  $text = preg_replace("/\[b$bbcode_uid\](.*?)\[\/b$bbcode_uid\]/is", '<b>\\1</b>', $text);
  
  // Italicized text
  $text = preg_replace("/\[i$bbcode_uid\](.*?)\[\/i$bbcode_uid\]/is", '<i>\\1</i>', $text);
  
  // Underlined text
  $text = preg_replace("/\[u$bbcode_uid\](.*?)\[\/u$bbcode_uid\]/is", '<u>\\1</u>', $text);                        
  
  // Colored text
  $text = preg_replace("/\[color$bbcode_uid=#([A-Fa-f0-9][A-Fa-f0-9][A-Fa-f0-9]([A-Fa-f0-9][A-Fa-f0-9][A-Fa-f0-9])?)\](.*?)\[\/color$bbcode_uid\]/is", '<span style="color: #\\1">\\3</span>', $text);
  
  // Size
  $text = preg_replace("/\[size$bbcode_uid=([0-4]+(\.[0-9]+)?)\](.*?)\[\/size$bbcode_uid\]/is", '<span style="font-size: \\1em;">\\3</span>', $text);
  
  // Quotes
  $text = preg_replace("/\[quote$bbcode_uid=\"?([^]]+?)\"?\](.*?)\[\/quote$bbcode_uid\]/is", '<span class="decir-quoteheader">\\1 wrote:</span><blockquote>\\2</blockquote>', $text);
  $text = preg_replace("/\[quote$bbcode_uid\](.*?)\[\/quote$bbcode_uid\]/is", '<blockquote class="decir-quotebody">\\1</blockquote>', $text);
  
  // https?:\/\/((([a-z0-9-]+\.)*)[a-z0-9-]+)(\/[A-z0-9_%\|~`!\!@#\$\^&\*\(\):;\.,\/-]*(\?(([a-z0-9_-]+)(=[A-z0-9_%\|~`\!@#\$\^&\*\(\):;\.,\/-\[\]]+)?((&([a-z0-9_-]+)(=[A-z0-9_%\|~`!\!@#\$\^&\*\(\):;\.,\/-]+)?)*))?)?)?
  
  // Links
  // Trial and error.
  $regexp = "/\[url$bbcode_uid(=(https?:\/\/((([a-z0-9-]+\.)*)[a-z0-9-]+)(\/[A-z0-9_%\|~`!\!@#\$\^&\*\(\):;\.,\/-]*(\?(([a-z0-9_-]+)(=[A-z0-9_%\|~`\!@#\$\^&\*\(\):;\.,\/-\[\]]+)?((&([a-z0-9_-]+)(=[A-z0-9_%\|~`!\!@#\$\^&\*\(\):;\.,\/-]+)?)*))?)?)?))?\](.*?)\[\/url$bbcode_uid\]/is";
  $text = preg_replace($regexp, '<a href="\\2">\\15</a>', $text);
  
  // Newlines
  $text = str_replace("\n", "<br />\n", $text);
  
  // Restore [code] blocks
  $text = decir_bbcode_restore_code($text, $bbcode_uid, $_code);
  
  // Code
  $text = preg_replace("/\[code$bbcode_uid\](.*?)\[\/code$bbcode_uid\]/is", '<pre>\\1</pre>', $text);
  
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

function bbcode_inject_uid($text, &$uid)
{
  $seed = md5( implode('.', explode(' ', microtime)) );
  $uid = substr($seed, 0, 10);
  // Bold text
  $text = preg_replace("/\[b\](.*?)\[\/b\]/is", "[b:$uid]\\1[/b:$uid]", $text);
  
  // Italicized text
  $text = preg_replace("/\[i\](.*?)\[\/i\]/is", "[i:$uid]\\1[/i:$uid]", $text);
  
  // Uunderlined text
  $text = preg_replace("/\[u\](.*?)\[\/u\]/is", "[u:$uid]\\1[/u:$uid]", $text);
  
  // Colored text
  $text = preg_replace("/\[color=\#([A-Fa-f0-9][A-Fa-f0-9][A-Fa-f0-9]([A-Fa-f0-9][A-Fa-f0-9][A-Fa-f0-9])?)\](.*?)\[\/color\]/is", "[color:$uid=#\\1]\\3[/color:$uid]", $text);
  
  // Size
  $text = preg_replace('/\[size=([0-4]+(\.[0-9]+)?)\](.*?)\[\/size\]/is', "[size:$uid=\\1]\\3[/size:$uid]", $text);
  
  // Quotes
  $text = preg_replace("/\[quote\](.*?)\[\/quote\]/is", "[quote:$uid]\\1[/quote:$uid]", $text);
  $text = preg_replace("/\[quote=\"?([^]]+)\"?\](.*?)\[\/quote\]/is", "[quote:$uid=\\1]\\2[/quote:$uid]", $text);
  
  // Code
  $text = preg_replace("/\[code\](.*?)\[\/code\]/is", "[code:$uid]\\1[/code:$uid]", $text);
  
  // URLs
  $text = preg_replace('/\[url(=https?:\/\/([^ ]+))?\](.*?)\[\/url\]/is', "[url:$uid\\1]\\3[/url:$uid]", $text);
  
  return $text;
}

