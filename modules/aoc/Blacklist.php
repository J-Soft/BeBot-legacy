<?php
/*
* Blacklist.php - Keeps a list of persons blacklisted by the guild and warns on whois
*
* Blacklist plugin by Foxferal (RK1), a variety if News.php by Blondengy
*
* Modified/Extended/Improved for BeBot 0.4 by Glarawyn.
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
* File last changed at $LastChangedDate: 2008-05-03 01:30:21 +0200 (Sat, 03 May 2008) $
* Revision: $Id: Blacklist.php 1512 2008-05-02 23:30:21Z temar $
*/

$blacklist = new Blacklist($bot);

class Blacklist extends BaseActiveModule
{
  private $table_version;

  function __construct(&$bot)
  {
    parent::__construct(&$bot, get_class($this));

    $this->bot->db->query("CREATE TABLE IF NOT EXISTS " . $this->bot->db->define_tablename("blacklist", "true") . "
			  (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
			  name VARCHAR(30) NOT NULL,
			  noteid INT NOT NULL,
			  expire INT UNSIGNED DEFAULT 0,
			  INDEX expire (expire))");

    $this->table_version = 2;
    $this->table_update(); // Update Blacklist table if needed.

    $this->register_command('all', 'blacklist', 'MEMBER', array('add' => 'LEADER',
                                                                'del' => 'LEADER'));
    $this->register_event("cron", "5min");

    $this->help['description'] = "Handles blacklist.";
    $this->help['command']['blacklist'] = "Shows the blacklist.";
    $this->help['command']['blacklist add <target> <reason>'] = "Adds <target> to the blacklist for <reason>.";
    $this->help['command']['blacklist rem <target>'] = "Removes <target> from blacklist.";
  }

  function cron()
  {
    $this->clean_blacklist();
  }

  /*
  This function handles all the inputs and returns FALSE if the
  handler should not send output, otherwise returns a string
  sutible for output via send_tell, send_pgroup, and send_gc.
  */
  function command_handler($name, $msg, $source)
  {
    if (preg_match("/^blacklist add (.+?) (.+)$/i", $msg, $info)) {
      return $this->set_blacklist($name, $info[1], $info[2]);
    }
    elseif (preg_match("/^blacklist rem (.+)$/i", $msg, $info))
    {
      return $this->del_blacklist($name, $info[1]);
    }
    else
    {
      return $this->get_blacklist($name);
    }
  }

  /*
  Get Blacklist
  */
  function get_blacklist($name)
  {
    if ($this->bot->guildbot) {
      $title = "Guild";
    }
    else
    {
      $title = "<botname>";
    }
    $inside = "##blob_title##:::: " . $title . " Blacklist ::::##end##\n\n";

    $result = $this->bot->db->select("SELECT name, noteid, expire FROM #___blacklist WHERE expire >= " . time() . " OR expire = 0 ORDER BY name", MYSQL_ASSOC);
    if (!empty($result)) {
      foreach ($result as $val)
      {
        // Get the reason from notes.
        $note = $this->bot->core("player_notes")->get_notes($name, $val['name'], $val['noteid']);
        $note = $note["content"][0];
        print_r($note);
        unset($tmp);
        if ($val['expire'] == 0) {
          $expire_string = "Never";
        }
        else
        {
          $expire_string = gmdate($this->bot->core("settings")->get("Time", "FormatString"), $val['expire']);
        }
        $inside .= "##blob_text##" . $val['name'] . " - Set by: ##red##" . $note['author'] . "##end## Reason: " . $note['note'] . " Expires: " . $expire_string . "##end##\n";
      }
    }
    else // Nobody on Blacklist.
    {
      return "Blacklist is empty.";
    }
    return $title . " Blacklist :: " . $this->bot->core("tools")->make_blob("click to view", $inside);
  }

  /*
  Sets new name on blacklist
  */
  function set_blacklist($source, $target, $reason, $expire = 0)
  {
    $source = ucfirst(strtolower($source));
    $target = ucfirst(strtolower($target));
    $source = mysql_real_escape_string($source);
    $target = mysql_real_escape_string($target);
    $reason = mysql_real_escape_string($reason);
    if ($this->bot->core("chat")->get_uid($target)) {
      if ($this->bot->core("security")->is_banned($target)) {
        return $target . " is already active on the blacklist.";
      }
      if ($this->bot->core("security")->check_access($source, "LEADER")) {
        // First add a note.
        $note = $this->bot->core("player_notes")->add($target, $source, $reason, "ban");
        if (!$note['error']) {
          $note = $note['pnid'];
        }
        $sql = "INSERT INTO #___blacklist (name, noteid, expire) ";
        $sql .= "VALUES ('" . $target . "', " . $note . ", " . $expire . ")";
        $this->bot->db->query($sql);
        $this->bot->core("security")->set_ban($source, $target, "Blacklisted by " . $source);
        return $target . " has been added to blacklist.";
      }
      else
      {
        return "Your access level must be LEADER or higher to do this.";
      }
    }
    else
    {
      return "There isn't any person named " . $target . " registered on this server!";
    }
  }

  /*
  Removes name from blacklist
  */
  function del_blacklist($admin, $target)
  {
    if ($this->bot->core("security")->is_banned($target)) {
      if ($this->bot->core("security")->check_access($admin, "LEADER")) {
        // First add a note
        $note = $this->bot->core("player_notes")->add($target, $admin, $admin . " removed " . $target . " from blacklist.", "ban");
        $sql = "DELETE FROM #___blacklist WHERE name = '" . $target . "'";
        $this->bot->db->query($sql);
        $this->bot->core("security")->rem_ban($admin, $target);
        return $target . " has been removed from blacklist.";
      }
      else
      {
        return "Your access level must be LEADER or higher to do this.";
      }
    }
    else
    {
      return $target . " is not active on the blacklist.";
    }
  }

  /*
  Clean Blacklist
  */
  function clean_blacklist()
  { // Start function clean_blacklist()
    $sql = "SELECT * FROM #___blacklist WHERE expire > 0 AND expire < " . time();
    $result = $this->bot->db->select($sql, MYSQL_ASSOC);
    if (empty($result)) {
      return FALSE; // Nothing to do.
    }
    foreach ($result as $ban)
    {
      $sql = "DELETE FROM #___blacklist WHERE id = " . $ban['id'];
      $this->bot->db->query($sql);
      $this->bot->core("security")->rem_ban($this->bot->botname, $ban['name']);
    }
  }

  /*
  Table Update.
  */
  function table_update()
  {
    $this->bot->core("settings")->create("Blacklist", "table_version", 0, "Table version for Blacklist database table", NULL, TRUE, 99);
    switch ($this->bot->core("settings")->get('Blacklist', 'Table_version'))
    {
      case 0: // Previous version of BeBot.
        $this->bot->db->update_table("blacklist", "noteid", "add", "ALTER IGNORE TABLE #___blacklist ADD noteid INT NOT NULL");
        $this->bot->db->update_table("blacklist", "expire", "add", "ALTER IGNORE TABLE #___blacklist ADD expire INT UNSIGNED DEFAULT 0");
        $this->bot->log("BLACKLIST", "UPDATE", "Updated blacklist table to version 1.");
        $this->bot->core("settings")->save("Blacklist", "table_version", 1);
      case 1:
        // db->update_table does not work for indexes, so let's do it manually
        $this->bot->db->query("ALTER IGNORE TABLE #___blacklist ADD INDEX expire (expire)");
        $this->bot->log("BLACKLIST", "UPDATE", "Updated blacklist table to version 2.");
        $this->bot->core("settings")->save("Blacklist", "table_version", 2);
        $this->bot->log("BLACKLIST", "UPDATE", "Blacklist table update complete.");
    }
  }

}

?>
