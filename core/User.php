<?php
/*
* User.php - Handle all user functions
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
* File last changed at $LastChangedDate: 2008-11-30 23:09:06 +0100 (Sun, 30 Nov 2008) $
* Revision: $Id: User.php 1833 2008-11-30 22:09:06Z alreadythere $
*/

$user_core = new User_Core($bot);

class User_Core extends BasePassiveModule
{
    function __construct(&$bot)
    {
        parent::__construct($bot, get_class($this));

        $this->register_module("user");

        if ($this->bot->guildbot) {
            $defnot = TRUE;
        }
        else {
            $defnot = FALSE;
        }
        $this->bot->core("settings")
            ->create("Members", "Mark_notify", $defnot, "Are members or guests automatically put on notify?");
        $this->bot->core("settings")
            ->create(
            "Members", "Notify_level", 2,
            "Are only members (2) or guests too (1) automatically put on notify if Mark_notify is true?", "1;2"
        );
        if ($this->bot->core("settings")->exists("Members", "AutoInvite")) {
            // Remove the outdated autoinvite setting if it still exists, this is handled via preferences now:
            $this->bot->core("settings")->del("Members", "AutoInvite");
        }
        $this->bot->core("settings")
            ->create(
            "Members", "AutoInviteGroup", "guests",
            "Which user group(s) should be automatically marked for autoinvite if AutoInvite is set to On?",
            "none;members;guests;both"
        );
    }


    /*
    Add a user to the bot.
    */
    function add($source, $name, $id = 0, $user_level, $silent = 0)
    {
        $return["error"] = false;
        $return["errordesc"] = '';
        $return["content"] = '';
        $change_level = false;

        $name = ucfirst(strtolower($name));

        // Check if we have been passed a name at all
        if (empty($name)) {
            $return["error"] = true;
            $return["errordesc"] = "You have to give a character to be added.";
            return $return;
        }

        // Make sure $name is a valid character
        if (!$this->bot->core("chat")->get_uid($name)) {
            $return["error"] = true;
            $return["errordesc"] = $name . " is not a valid character!";
            return $return;
        }

        // If we didn't get an id, look it up
        if ($id == 0) {
            $id = $this->bot->core("chat")->get_uid($name);
        }

        // Make sure the character exsists.
        if (!$id) {
            $return["error"] = true;
            $return["errordesc"] = "Player ##highlight##" . $name . " ##end##does not exist";
            return $return;
        }

        //Make sure the user is not already added.
        $result = $this->bot->db->select("SELECT nickname, user_level FROM #___users WHERE char_id = '" . $id . "'");
        if (!empty($result)) {
            if ($result[0][1] == -1 && !($this->bot->guildbot)) {
                $return["error"] = true;
                $return["errordesc"] = "##highlight##" . $name . " ##end##is banned and cannot be added.";
                return $return;
            }
            else {
                if (($result[0][1] != $user_level && $user_level > 0)) {
                    $change_level = true;
                }
                else {
                    $return["error"] = true;
                    $return["errordesc"] = "##highlight##" . $result[0][0] . " ##end##is already a member.";
                    // Make sure correct name is in the table, same ID may have different names after name change.
                    if ($name != $result[0][0]) {
                        $this->bot->db->query(
                            "UPDATE #___users SET nickname = '" . $name . "' where char_id = '" . $id . "'"
                        );
                    }
                    return $return;
                }
            }
        }
        $result = $this->bot->db->select("SELECT char_id, user_level FROM #___users WHERE nickname = '" . $name . "'");
        if (!empty($result)) {
            if ($result[0][1] == -1 && !($this->bot->guildbot)) {
                $return["error"] = true;
                $return["errordesc"] = "##highlight##" . $name . " ##end##is banned and cannot be added.";
                return $return;
            }
            else {
                // Ok, we already have someone with the same name, double check userid's and erase the old user to avoid problems.
                if ($id != $result[0][0]) {
                    $this->erase("", $name, TRUE, $result[0][0]);
                }
            }
        }


        // Make sure we have a valid access level for the user.
        else {
            if ($user_level < 0) {
                $return["error"] = true;
                $return["errordesc"] = "##highlight##" . $level
                    . " ##end##is not a valid access level. The plugin trying to add a user might be broken.";
                return $return;
            }
        }

        if (AOCHAT_GAME == "ao") {
            // Add the user to the whois cache.
            $members = $this->bot->core("whois")->lookup($name);
            if ($members["error"] == true) {
                $this->bot->log("USER", "ERROR", "Could not lookup $name whois.");
                $members["id"] = $id;
                $members["nickname"] = $name;
            }
        }
        else {
            $members["id"] = $id;
            $members["nickname"] = $name;
        }

        // Mark members for notify in org bots, otherwise no notify as default
        if ($this->bot->core("settings")->get("Members", "Mark_notify")
            && $user_level >= $this->bot->core("settings")
                ->get("Members", "Notify_level")
        ) {
            $notifystate = 1;
        }
        else {
            $notifystate = 0;
        }

        // Add the user to the users table
        if ($change_level) {
            $this->bot->db->query(
                "UPDATE #___users SET user_level = '" . $user_level . "', notify = '" . $notifystate . "', added_by = '"
                    . mysql_real_escape_string($source) . "' WHERE char_id = '" . $members["id"] . "'"
            );
        }
        else {
            $this->bot->db->query(
                "INSERT INTO #___users (char_id, nickname, added_by, added_at, user_level, notify) VALUES('"
                    . $members["id"] . "', '" . $members["nickname"] . "', '" . mysql_real_escape_string($source)
                    . "', '" . time() . "', '" . $user_level . "', '" . $notifystate . "')"
            );
        }

        // If character is on notify add to buddy list
        // We probably will want to add some sort of buddylist number tracking to ensure we dont go over 1k buddies at some point.
        if ($notifystate == 1
            && !$this->bot->core("chat")
                ->buddy_exists($members["id"])
        ) {
            $this->bot->core("notify")->update_cache();
            $this->bot->core("chat")->buddy_add($members["id"]);
        }

        // Tell them they have been added.
        if ($silent == 0) {
            $this->bot->send_tell(
                $name, "##highlight##" . $source . " ##end##has added you to the bot." . $autoinvitestring
            );
        }

        // Make sure the security cache is up-to-date:
        if ($user_level > 0) {
            if ($user_level == 1) {
                $cache = 'guests';
            }
            else {
                $cache = 'members';
            }
            $this->bot->core("security")->cache_mgr("add", $cache, $name);
        }

        $return["content"]
            = "Player ##highlight##" . $name . " ##end##has been added to the bot as " . $this->access_name($user_level);
        return $return;
    }


    /*
    Remove a user from the bot.
    Please note that the del function only marks a member as inactive and removes their bot access. However all their data
    and information remain in the database.
    */
    function del($source, $name, $id = 0, $silent = 0)
    {
        $reroll = 0;
        $return["error"] = false;
        $return["errordesc"] = '';
        $return["content"] = '';
        $name = ucfirst(strtolower($name));

        if (empty($name)) {
            $return["error"] = true;
            $return["errordesc"] = "You have to give a character to be deleted.";
            return $return;
        }

        // Check if we have a member by that name.
        $result = $this->bot->db->select(
            "SELECT char_id, nickname, user_level FROM #___users WHERE nickname = '" . $name . "'"
        );
        if (empty($result)) {
            $return["error"] = true;
            $return["errordesc"] = "##highlight##" . $name . " ##end##is not in the user table, and cannot be deleted.";
            return $return;
        }

        // Check if the member is already deleted.
        else {
            if ($result[0][2] == 0) {
                $return["error"] = true;
                $return["errordesc"] = "##highlight##" . $name . " ##end##is not a member.";
                return $return;
            }

            // Make sure we are not trying to delete a banned member.
            else {
                if ($result[0][2] == -1) {
                    $return["error"] = true;
                    $return["errordesc"] = "##highlight##" . $name . " ##end##is banned and cannot be deleted.";
                    return $return;
                }

                // Revoke the members
                else {
                    if ($new_id = $this->bot->core("chat")->get_uid($name)) {
                        // Make sure we have a sane userid to work with to determine if the user exsists.
                        if ($id == 0) {
                            $id = $result[0][0];
                        }

                        // Check if we are dealing with a rerolled character, if thats the case we need to handle it specially since we don't physically delete characters.
                        else {
                            if ($id != $new_id) {
                                $reroll = 1;
                            }
                        }
                    }
                    else {
                        $this->erase("Automated delete for invalid userid", $name);
                        $return["error"] = true;
                        $return["errordesc"] = "##highlight##" . $name
                            . " ##end##does not appear to be a valid character. You might want to erase this user.";
                        return $return;
                    }

                    // Rerolled character, we need to make sure our information is updated.
                    if ($reroll == 1) {
                        $this->bot->db->query(
                            "UPDATE #___users SET char_id = '" . $id . "', user_level = '0', deleted_by = '"
                                . mysql_real_escape_string($source) . "', deleted_at = '" . time()
                                . "', nofity = '0' WHERE nickname = '" . $name . "'"
                        );
                    }
                    else {
                        $this->bot->db->query(
                            "UPDATE #___users SET user_level = '0', deleted_by = '" . mysql_real_escape_string($source)
                                . "', deleted_at = '" . time() . "', notify = '0' WHERE char_id = '" . $id . "'"
                        );
                        $this->bot->core("chat")->buddy_remove($id);
                    }

                    if ($rerolled != 1 && $silent == 0) {
                        $this->bot->send_tell(
                            $name, "##highlight##" . $source . " ##end##has removed you from the bot."
                        );
                    }

                    // Make sure the security cache is up-to-date:
                    if ($result[0][2] > 0) {
                        if ($result[0][2] == 1) {
                            $cache = 'guests';
                        }
                        else {
                            $cache = 'members';
                        }
                        $this->bot->core("security")->cache_mgr("rem", $cache, $name);
                    }

                    //Make sure the usr isnt left on the online list
                    $this->bot->db->query(
                        "UPDATE #___online SET status_gc = 0 WHERE botname = '" . $this->bot->botname
                            . "' AND nickname = '"
                            . $name . "'"
                    );

                    $this->bot->core("online")->logoff($name);
                    $this->bot->core("notify")->update_cache();
                    $return["content"] = "##highlight##" . $name . " ##end##has been removed from member list.";
                    return $return;
                }
            }
        }
    }


    /*
    Erase a user from the bot.
    Please note that the del function only marks a member as inactive and removes their bot access. However all their data
    and information remain in the database.
    */
    function erase($source, $name, $silent = 0, $id = 0)
    {
        $reroll = 0;
        $deleted = 0;
        $return["error"] = false;
        $return["errordesc"] = '';
        $return["content"] = '';

        if (empty($name)) {
            $return["error"] = true;
            $return["errordesc"] = "You have to give a character name to be erased.";
            return $return;
        }

        $result = $this->bot->db->select(
            "SELECT char_id, nickname, user_level FROM #___users WHERE nickname = '" . $name . "'"
        );
        if (empty($result)) {
            $return["error"] = true;
            $return["errordesc"] = "##highlight##" . $name . " ##end##is not in the user table, and cannot be erased.";
            return $return;
        }

        //Make sure we are not trying to delete a banned member.
        else {
            if ($result[0][1] == -1) {
                $return["error"] = true;
                $return["errordesc"] = "##highlight##" . $name . " ##end##is banned and cannot be deleted.";
                return $return;
            }
            else {
                if ($new_id = $this->bot->core("chat")->get_uid($name)) {
                    // Make sure we have a sane userid to work with to determine if the user exsists.
                    if ($id == 0) {
                        $id = $result[0][0];
                    }

                    // Check if we are dealing with a rerolled character, if thats the case we need to handle it specially since we don't physically delete characters.
                    else {
                        if ($id != $new_id) {
                            $reroll = 1;
                        }
                    }
                }
                else {
                    $deleted = 1;
                }

                // The character
                if ($reroll == 1 || $deleted == 1) {
                    $this->bot->db->query("DELETE FROM #___users WHERE nickname = '" . $name . "'");
                }
                else {
                    $this->bot->db->query("DELETE FROM #___users WHERE char_id = " . $id);
                    $this->bot->core("chat")->buddy_remove($id);
                }

                if ($deleted != 1 && $rerolled != 1 && $silent == 0) {
                    $this->bot->send_tell($name, "##highlight##" . $source . " ##end##has removed you from the bot.");
                }

                // Make sure the security cache is up-to-date:
                if ($result[0][2] > 0) {
                    if ($result[0][2] == 1) {
                        $cache = 'guests';
                    }
                    else {
                        $cache = 'members';
                    }
                    $this->bot->core("security")->cache_mgr("rem", $cache, $name);
                }


                $this->bot->core("online")->logoff($name);
                $this->bot->core("notify")->update_cache();
                $return["content"] = "##highlight##" . $name . " ##end##has been erased from member list.";
                return $return;
            }
        }
    }


    function access_name($level)
    {
        switch ($level) {
        case '1':
            return "a guest";
            break;
        case '2':
            return "a member";
            break;
        case '3':
            return "an admin";
            break;
        default:
            return "Error, unknown level";
        }
    }


    function admin_group_name($level)
    {
        switch ($level) {
        case '4':
            return "owner";
        case '3':
            return "superadmin";
        case '2':
            return "admin";
        case '1':
            return "raidleader";
        }
    }


    function admin_group_level($name)
    {
        switch ($name) {
        case 'owner':
            return 4;
        case 'superadmin':
            return 3;
        case 'admin':
            return 2;
        case 'raidleader':
            return 1;
        default:
            return 0;

        }
    }


    // Grab the userid associated with a name in the users database
    function get_db_uid($name)
    {
        $result = $this->bot->db->select("SELECT char_id FROM #___users WHERE nickname = '" . $name . "'");
        if (!empty($result)) {
            return $result[0][0];
        }
        else {
            return 0;
        }
    }
}

?>
