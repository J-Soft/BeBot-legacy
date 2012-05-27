<?php
/*
* StartBot.php - Starts and restarts the bot
*
* BeBot - An Anarchy Online & Age of Conan Chat Automaton
* Copyright (C) 2004 Jonas Jax
* Copyright (C) 2005-2010 Thomas Juberg, ShadowRealm Creations and the BeBot development team.
*
* Developed by:
* - Alreadythere (RK2)
* - Blondengy (RK1)
* - Blueeagl3 (RK1)
* - Glarawyn (RK1)
* - Khalem (RK1)
* - Naturalistic (RK1)
* - Temar (RK1)
*
* See Credits file for all aknowledgements.
*
*  This program is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; version 2 of the License only.
*
*  This program is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  You should have received a copy of the GNU General Public License
*  along with this program; if not, write to the Free Software
*  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307
*  USA
*
* File last changed at $LastChangedDate: 2009-01-23 09:21:51 +0100 (Fri, 23 Jan 2009) $
* Revision: $Id: StartBot.php 1959 2009-01-23 08:21:51Z temar $
*/

/*
Take a decent stab at what OS we run on and try using some sane defaults
*/
$os = getenv("OSTYPE");
if (empty($os)) {
    $os = getenv("OS");
}

if (preg_match("/^windows/i", $os)) {
    /*
    This default should work for Windows installs where php is installed to the bot directory.
    */
    $php_bin = "php.exe";
    $php_args = " -c ./ ";
    $main_php = "Main.php";

    /*
    If the above fails you can try specifying full paths, example:
    $php_bin = "C:\php\php.exe";
    $main_php = "C:\BeBot\Main.php";
    */
}
else {
    /*
    This is a sane default for the php binary on Unix systems.
    If your php binary is located someplace else, edit the php_bin path accordingly.
    */
    $php_bin = trim(shell_exec('which php'));
    $php_args = " -c ./ ";
    $main_php = "Main.php";
}
$confc = TRUE;
require_once "./Sources/Conf.php";
if ($argv[1] != $conf->argv) {
    echo "Use \"StartBot.php " . $conf->argv . "\" to start bot next time\n";
    $argv[1] = $conf->argv;
    $conf->ask("Press Enter to load Bot");
    if (!$argv[1] || $argv[1] == "") {
        $argc = 1;
    }
    else {
        $argc = 2;
    }
}
if (!empty($conf->pw)) {
    $pw = $conf->pw;
    $conf->pw = NULL;
}

// Create the command to execute in the system() call of the main loop:
$systemcommand = $php_bin . $php_args . " " . $main_php;
if ($argc > 1) {
    $systemcommand .= " " . $argv[1];
}

while (true) {
    if ($pw) {
        $fp = fopen('./conf/pw', 'w');
        fwrite($fp, $pw);
        fclose($fp);
    }
    $last_line = system($systemcommand);

    if (preg_match("/^The bot has been shutdown/i", $last_line)) {
        die();
    }
    else {
        sleep(1);
    }
}

?>
