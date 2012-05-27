<?php
/*
* CommandAlias.php
* - Interface to add and remove command aliases.
*
* Written by Temar
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
* File last changed at $LastChangedDate: 2007-07-25 20:54:01 +0200 (Mi, 25 Jul 2007) $
* Revision: $Id: CommandAliasUI.php 1833 2008-11-30 22:09:06Z alreadythere $
*/

$commandaliasinterface = new CommandAliasInterface($bot);

class CommandAliasInterface extends BaseActiveModule
{
    function __construct(&$bot)
    {
        parent::__construct($bot, get_class($this));

        $this->register_command('all', 'comalias', 'OWNER');

        $this->help['description']                               = 'Handles Command Aliases.';
        $this->help['command']['comalias add <alias> <command>'] = "Sets <alias> as an alias of <command>.";
        $this->help['command']['comalias del <alias>']           = "Deletes <alias>.";
        $this->help['command']['comalias rem <alias>']           = $this->help['command']['comalias del <alias>'];
        $this->help['command']['comalias']                       = "Show All Aliases.";
    }


    function command_handler($name, $msg, $origin)
    {
        $var = explode(" ", $msg, 3);

        switch ($var[1])
        {
            case 'add':
                Return ($this->bot->core("command_alias")->add($var[2]));
            case 'del':
            case 'rem':
                Return ($this->bot->core("command_alias")->del($var[2]));
            default:
                Return ($this->bot->core("command_alias")->get_list());
        }
    }
}

?>
