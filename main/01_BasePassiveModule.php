<?php
/*
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
* File last changed at $LastChangedDate: 2008-07-23 16:44:39 +0100 (Wed, 23 Jul 2008) $
* Revision: $Id: Alias.php 1673 2008-07-23 15:44:39Z temar $
*/

class BasePassiveModule
{
    protected $bot; // A reference to the bot
    public $module_name; //Name of the module extending this class.
    protected $error; //This holds an error class.
    protected $link_name;


    function __construct(&$bot, $module_name)
    {
        //Save reference to bot
        $this->bot         = &$bot;
        $this->module_name = $module_name;
        $this->link_name   = NULL;
        $this->error       = new BotError($bot, $module_name);
    }


    protected function register_event($event, $target = false)
    {
        $ret = $this->bot->register_event($event, $target, &$this);
        if ($ret) {
            $this->error->set($ret);
        }
    }


    protected function unregister_event($event, $target = false)
    {
        $ret = $this->bot->unregister_event($event, $target, &$this);
        if ($ret) {
            $this->error->set($ret);
        }
    }


    protected function register_module($name)
    {
        if ($this->link_name == NULL) {
            $this->link_name = strtolower($name);
            $this->bot->register_module(&$this, strtolower($name));
        }
    }


    protected function unregister_module()
    {
        if ($this->link_name != NULL) {
            $this->bot->unregister_module($this->link_name);
        }
    }


    protected function output($name, $msg, $channel = false)
    {
        if ($channel !== false) {
            if ($channel & SAME) {
                if ($channel & $this->source) {
                    $channel -= SAME;
                }
                else
                {
                    $channel += $this->source;
                }
            }
        }
        else
        {
            $channel += $this->source;
        }

        if ($channel & TELL) {
            $this->bot->send_tell($name, $msg);
        }
        if ($channel & GC) {
            $this->bot->send_gc($msg);
        }
        if ($channel & PG) {
            $this->bot->send_pgroup($msg);
        }
        if ($channel & RELAY) {
            $this->bot->core("relay")->relay_to_pgroup($name, $msg);
        }
        if ($channel & IRC) {
            $this->bot->send_irc($this->module_name, $name, $msg);
        }
    }


    public function __call($name, $args)
    {
        foreach ($args as $i => $arg)
        {
            if (is_object($arg)) {
                $args[$i] = "::object::";
            }
        }
        $args = implode(', ', $args);
        $msg  = "Undefined function $name($args)!";
        $this->error->set($msg);
        return $this->error->message();
    }
}

?>
