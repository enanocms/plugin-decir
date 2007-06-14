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

if ( $session->auth_level < USER_LEVEL_ADMIN )
{
  header('Location: ' . makeUrlComplete('Special', 'Login/' . $paths->page, 'level=9', true));
  exit;
}

if ( $v = getConfig('decir_version') )
{
  $mode = 'upgrade';
  $upg_ver = $v;
}
else
{
  $mode = 'install';
}

$page = ( isset($_POST['step']) && in_array($_POST['step'], array('welcome', 'install', 'finish')) ) ? $_POST['step'] : 'welcome';

$template->header();

switch($page)
{
  case 'welcome':
    ?>
    <h3>Welcome to Decir, the Enano bulletin board suite.</h3>
    <p>Before you can use your forum, we'll need to run a few database queries to get the forum set up.</p>
    <form action="<?php echo makeUrl($paths->page); ?>" method="post">
      <input type="hidden" name="step" value="install" />
      <input type="submit" value="Continue" style="display: block; margin: 0 auto;" />
    </form>
    <?php
    break;
  case 'install':
    setConfig('decir_version', ENANO_DECIR_VERSION);
    ?>
    <form action="<?php echo makeUrl($paths->page); ?>" method="post">
      <input type="hidden" name="step" value="finish" />
      <input type="submit" name="do_install_finish" value="Next &gt;" style="display: block; margin: 0 auto;" />
    </form>
    <?php
    break;
}

$template->footer();

