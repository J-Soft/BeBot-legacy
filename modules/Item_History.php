<?php
/*
* item.php - Announces a item.
*
* BeBot - An Anarchy Online & Age of Conan Chat Automaton
* Copyright (C) 2004 Jonas Jax
* Copyright (C) 2005-2007 Thomas Juberg Stensås, ShadowRealm Creations and the BeBot development team.
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
* File last changed at $LastChangedDate: 2009-04-20 04:55:23 +0100 (Mon, 20 Apr 2009) $
* Revision: $Id: Item_History.php 1 2009-04-20 03:55:23Z temar $
*/


$item_history = new Item_History($bot);

/*
The Class itself...
*/
class Item_History extends BaseActiveModule
{
	function __construct(&$bot)
	{
		parent::__construct(&$bot, get_class($this));

	//	$this -> register_command("all", "c", "LEADER");
		$this -> register_command("all", "itemhistory", "MEMBER");
		$this -> register_alias("itemhistory", "ih");

		//$this -> register_module("item");
 
	}


	function command_handler($name, $msg, $type)
	{	
		$var = explode (" ", $msg, 2);

		switch(strtolower($var[0]))
		{
			case 'itemhistory':
				Return $this -> get_item($var[1]);
				switch(strtolower($var[1]))
				{
					case 'view':
						Return $this -> view($name, $var[2]);
					Default:
						if ($this -> bot -> core("security") -> check_access($name, $this -> bot -> core("settings") -> get('item', 'Command')))
							Return $this -> item_list();
						else
							Return $this -> item_list();
				}
			Default:
				Return "##error##Error : Broken plugin, item_history.php recieved unhandled command: ".$var[0]."##end##";
		}
	}


	/*
	Starts a item
	*/
	function get_item($item)
	{
		$item = mysql_real_escape_string($item);
		$history = $this -> bot -> db -> select("SELECT points, time, why FROM #___raid_points_log WHERE why LIKE 'Auction: %".$item."%'");
		if(!empty($history))
		{
			foreach($history as $h)
			{
				$h[2] = substr($h[2], 9);
				$h[0] = str_replace("-", "", $h[0]);
				$list[$h[2]][$h[1]] = $h[0];
			}
			$inside .= " :: Item Auction History ::";
			foreach($list as $item => $v)
			{
				$inside .= "\n\n- $item";
				foreach($v as $time => $points)
				{
					$inside .= "\n".$points." (".gmdate("H:i", $time).")";
				}
			}
			Return "Item Auction History :: ".$this -> bot -> core("tools") -> make_blob("click to view", $inside);
		}
		else
			Return("Search Returned no Results");
	}
}
?>