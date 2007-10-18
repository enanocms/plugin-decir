<?php
/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 * common.php - Loader and common basic functions
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if(!defined('DECIR_ROOT'))
{
  $_GET['title'] = 'null';
  header('HTTP/1.1 403 Forbidden');
  require('../includes/common.php');
  die_friendly('Access denied', '<p>This script cannot be run outside of Enano.</p>');
}

require('constants.php');
require('functions.php');

$html = '    <!-- Decir\'s updated namespace extractor function -->
    <script type="text/javascript">
      function strToPageID(string)
      {
        var ret;
        for(var i in namespace_list)
          if(namespace_list[i] != \'\')
            if(namespace_list[i] == string.substr(0, namespace_list[i].length))
              ret = [string.substr(namespace_list[i].length), i];
        
        if ( ret )
          return ret;
        
        return [string, \'Article\'];
      }
    </script>
  ';
  
$template->add_header($html);

?>
