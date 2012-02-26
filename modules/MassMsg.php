<?php
/*
* MassMsg.php - Sends out mass messages and invites.
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
* File last changed at $LastChangedDate: 2009-03-12 04:45:44 +0000 (Thu, 12 Mar 2009) $
* Revision: $Id: MassMsg.php 5 2009-03-12 04:45:44Z temar $
*/


$massmsg = new MassMsg($bot);

/*
The Class itself...
*/
class MassMsg extends BaseActiveModule
{
    function __construct(&$bot)
    {
        parent::__construct(&$bot, get_class($this));

        $this->register_command('all', 'announce', 'LEADER');
        $this->register_command('all', 'massinv', 'LEADER');
        $this->bot->core("queue")->register($this, "invite", 0.2, 5);

        $this->help['description']                   = 'Sends out mass messages and invites.';
        $this->help['command']['announce <message>'] = "Sends out announcement <message> as tells to all online members.";
        $this->help['command']['massinv <message>']  = "Sends out announcement <message> as tells to all online members and invites them to the private group.";

        $this->bot->core("settings")
            ->create('MassMsg', 'MassMsg', 'Both', 'Who should get mass messages and invites?', 'Guild;Private;Both');
        $this->bot->core("settings")
            ->create('MassMsg', 'MinAccess', 'GUEST', 'Which access level must characters online have to receive mass messages and invites?', 'ANONYMOUS;GUEST;MEMBER;LEADER;ADMIN;SUPERADMIN;OWNER');
        $this->bot->core("settings")
            ->create('MassMsg', 'IncludePrefLink', TRUE, 'Should a link to preferences be included in the messages/invites?');
        $this->bot->core("settings")
            ->create('MassMsg', 'tell_to_PG_users', FALSE, 'Should Bot Send message to users in PG instead of just Outputing to PG and ignoreing them');
        $this->bot->core("settings")
            ->create('MassMsg', 'msgFormat', '##massmsg_type####type## from##end## ##highlight####name####end##: ##massmsg_msg####msg####end####disable##', 'What format should Mass message be, use ##type##, ##msg##, ##name## and ##disable## disable being the line telling user how to disable if enabled.');

        $this->bot->core('prefs')
            ->create('MassMsg', 'recieve_message', 'Do you want to recieve mass-messages?', 'Yes', 'Yes;No');
        $this->bot->core('prefs')
            ->create('MassMsg', 'recieve_invites', 'Do you want to recieve mass-invites?', 'Yes', 'No;Yes');

        $this->bot->core("colors")->define_scheme("massmsg", "type", "aqua");
        $this->bot->core("colors")->define_scheme("massmsg", "msg", "orange");
        $this->bot->core("colors")
            ->define_scheme("massmsg", "disable", "seablue");
    }


    function command_handler($name, $msg, $origin)
    {
        $com = $this->parse_com($msg, array('com',
                                            'args'));
        switch ($com['com'])
        {
            case 'announce':
                $this->bot->send_output($name, "Mass message being sent. Please stand by...", $origin);
                return ($this->mass_msg($name, $com['args'], 'Message', $source));
                break;
            case 'massinv':
                $this->bot->send_output($name, "Mass invite being sent. Please stand by...", $origin);
                return ($this->mass_msg($name, $com['args'], 'Invite', $source));
                break;
            default:
                $this->bot->send_help($name);
        }
    }


    function mass_msg($sender, $msg, $type)
    {
        if ($msg == "") {
            Return ("##error##Error: Message to Announce Required##end##");
        }

        //get a list of online users in the configured channel.
        $users = $this->bot->core('online')->list_users($this->bot
            ->core('settings')->get('MassMsg', 'MassMsg'));
        if ($users instanceof BotError) {
            return ($users);
        }

        $format = $this->bot->core("settings")->get('MassMsg', 'msgFormat');
        $msg    = str_ireplace("##msg##", $msg, $format);
        $msg    = str_ireplace("##type##", $type, $msg);
        $msg    = str_ireplace("##name##", $sender, $msg);

        $msg = $this->bot->core("colors")->parse($msg);

        $inchattell = $this->bot->core('settings')
            ->get('MassMsg', 'tell_to_PG_users');
        if (!$inchattell) {
            //Send to PG and ignore all in PG
            $pgmsg = str_ireplace("##disable##", "", $msg);
            $this->bot->send_pgroup("\n" . $pgmsg, NULL, TRUE, FALSE);
        }
        if ($this->bot->core('settings')->get('MassMsg', 'IncludePrefLink')) {
            $dis = "\n##massmsg_disable##You can disable reciept of mass messages and invites in the ##end##";
            $dis = $this->bot->core("colors")->parse($dis);
        }
        $msg = $this->bot->core("colors")->colorize("normal", $msg);

        foreach ($users as $recipient)
        {
            if ($this->bot->core('prefs')
                    ->get($recipient, 'MassMsg', 'recieve_message') == 'Yes'
            ) {
                $massmsg = TRUE;
            }
            else
            {
                $massmsg = FALSE;
            }
            if ($this->bot->core('prefs')
                    ->get($recipient, 'MassMsg', 'recieve_invites') == 'Yes'
            ) {
                $massinv = TRUE;
            }
            else
            {
                $massinv = FALSE;
            }

            //Add link to preferences according to settings
            if ($this->bot->core('settings')->get('MassMsg', 'IncludePrefLink')
            ) {
                if (!isset($blobs[(int)$massmsg][(int)$massinv])) {
                    $blob                                = $this->bot
                        ->core('prefs')
                        ->show_prefs($recipient, 'MassMsg', FALSE);
                    $blob                                = $this->bot
                        ->core("colors")->parse($blob);
                    $blob                                = $this->bot
                        ->core("colors")->colorize("normal", $blob);
                    $blobs[(int)$massmsg][(int)$massinv] = $blob;
                }
                $addlink = $dis . $blobs[(int)$massmsg][(int)$massinv];
            }
            else
            {
                $addlink = "";
            }

            $message = str_ireplace("##disable##", $addlink, $msg);
            //If they want messages they will get them regardless of type
            if ($massmsg) {
                if (!$inchattell && $this->bot->core("online")
                    ->in_chat($recipient)
                ) {
                    $status[$recipient]['sent'] = FALSE;
                    $status[$recipient]['pg']   = true;
                }
                else
                {
                    $this->bot->send_tell($recipient, $message, 0, FALSE, TRUE, FALSE);
                    $status[$recipient]['sent'] = true;
                }
            }
            else
            {
                $status[$recipient]['sent'] = false;
            }

            //If type is an invite and they want invites, they will recieve both a message and an invite regardless of recieve_message setting
            if ($type == 'Invite') {
                if ($massinv) {
                    if ($this->bot->core("online")->in_chat($recipient)) {
                        $status[$recipient]['sent'] = FALSE;
                        $status[$recipient]['pg']   = true;
                    }
                    else
                    {
                        //Check if they've already gotten the tell so we don't spam unneccessarily.
                        if (!$status[$recipient]['sent']) {
                            $this->bot->send_tell($recipient, $message, 0, FALSE, TRUE, FALSE);
                            $status[$recipient]['sent'] = true;
                        }
                        if ($this->bot->core("queue")->check_queue("invite")) {
                            $this->bot->core('chat')->pgroup_invite($recipient);
                        }
                        else
                        {
                            $this->bot->core("queue")
                                ->into_queue("invite", $recipient);
                        }
                        $status[$recipient]['invited'] = true;
                    }
                }
                else
                {
                    $status[$recipient]['invited'] = false;
                }
            }
        }

        if ($source == "tell") {
            return ("Mass messages complete. " . $this->make_status_blob($status));
        }
        else
        {
            $this->bot->send_tell($sender, "Sending Mass messages complete.");
            return ("Sending Mass messages. " . $this->make_status_blob($status));
        }
    }


    function make_status_blob($status_array)
    {
        $window = "<center>##blob_title##::: Status report for mass message :::##end##</center>\n";
        foreach ($status_array as $recipient => $status)
        {
            $window .= "\n##highlight##$recipient##end## - Message: ";
            if ($status['sent']) {
                $window .= "##lime##Sent to user##end##";
            }
            elseif ($status['pg'])
            {
                $window .= "##lime##Viewed in PG##end##";
            }
            else
            {
                $window .= "##error##Blocked by preferences##end##";
            }
            if (isset($status['invited'])) {
                if ($status['invited']) {
                    $window .= " - Invite to pgroup: ##lime##sent to user##end##";
                }
                else
                {
                    $window .= " - Invite to pgroup: ##error##blocked by preferences##end##";
                }
            }
            if (strtolower($this->bot->botname) == "bangbot") {
                if ($status['sent'] || $status['pg']) {
                    //Update announce count...
                    $result = $this->bot->db->select("SELECT announces FROM stats WHERE nickname = '" . $recipient . "'");
                    if (!empty($result)) {
                        $this->bot->db->query("UPDATE stats SET announces = announces+1 WHERE nickname = '" . $recipient . "'");
                    }
                }
            }
        }
        return ($this->bot->core('tools')->make_blob('report', $window));
    }


    function queue($name, $recipient)
    {
        $this->bot->core('chat')->pgroup_invite($recipient);
    }
}

?>
