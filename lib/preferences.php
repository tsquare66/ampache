<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2014 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License v2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

/*
 * update_preferences
 * grabs the current keys that should be added
 * and then runs throught $_REQUEST looking for those
 * values and updates them for this user
 */
function update_preferences($pref_id=0)
{
    /* Get current keys */
    $sql = "SELECT `id`,`name`,`type` FROM `preference`";

    /* If it isn't the System Account's preferences */
    if ($pref_id != '-1') { $sql .= " WHERE `catagory` != 'system'"; }

    $db_results = Dba::read($sql);

    $results = array();
    // Collect the current possible keys
    while ($r = Dba::fetch_assoc($db_results)) {
        $results[] = array('id' => $r['id'], 'name' => $r['name'],'type' => $r['type']);
    } // end collecting keys

    /* Foreach through possible keys and assign them */
    foreach ($results as $data) {
        /* Get the Value from POST/GET var called $data */
        $name         = $data['name'];
        $apply_to_all    = 'check_' . $data['name'];
        $new_level    = 'level_' . $data['name'];
        $id        = $data['id'];
        $value         = scrub_in($_REQUEST[$name]);

        /* Some preferences require some extra checks to be performed */
        switch ($name) {
            case 'sample_rate':
                $value = Stream::validate_bitrate($value);
            break;
            default:
            break;
        }

        if (preg_match('/_pass$/', $name)) {
            if ($value == '******') { unset($_REQUEST[$name]); } else if (preg_match('/md5_pass$/', $name)) {
                $value = md5($value);
            }
        }

        /* Run the update for this preference only if it's set */
        if (isset($_REQUEST[$name])) {
            Preference::update($id,$pref_id,$value,$_REQUEST[$apply_to_all]);
        }

        if (Access::check('interface','100') AND $_REQUEST[$new_level]) {
            Preference::update_level($id,$_REQUEST[$new_level]);
        }

    } // end foreach preferences

    // Now that we've done that we need to invalidate the cached preverences
    Preference::clear_from_session();

} // update_preferences

/**
 * update_preference
 * This function updates a single preference and is called by the update_preferences function
 */
function update_preference($user_id,$name,$pref_id,$value)
{
    $apply_check = "check_" . $name;
    $level_check = "level_" . $name;

    /* First see if they are an administrator and we are applying this to everything */
    if ($GLOBALS['user']->has_access(100) AND make_bool($_REQUEST[$apply_check])) {
        Preference::update_all($pref_id,$value);
        return true;
    }

    /* Check and see if they are an admin and the level def is set */
    if ($GLOBALS['user']->has_access(100) AND make_bool($_REQUEST[$level_check])) {
        Preference::update_level($pref_id,$_REQUEST[$level_check]);
    }

    /* Else make sure that the current users has the right to do this */
    if (Preference::has_access($name)) {
        $sql = "UPDATE `user_preference` SET `value` = ? WHERE `preference` = ? AND `user` = ?";
        Dba::write($sql, array($value, $pref_id, $user_id));
        return true;
    }

    return false;

} // update_preference

/**
 * create_preference_input
 * takes the key and then creates the correct type of input for updating it
 */
function create_preference_input($name,$value)
{
    if (!Preference::has_access($name)) {
        if ($value == '1') {
            echo "Enabled";
        } elseif ($value == '0') {
            echo "Disabled";
        } else {
            if (preg_match('/_pass$/', $name)) {
                echo "******";
            } else {
                echo $value;
            }
        }
        return;
    } // if we don't have access to it

    switch ($name) {
        case 'display_menu':
        case 'download':
        case 'quarantine':
        case 'upload':
        case 'access_list':
        case 'lock_songs':
        case 'xml_rpc':
        case 'force_http_play':
        case 'no_symlinks':
        case 'use_auth':
        case 'access_control':
        case 'allow_stream_playback':
        case 'allow_democratic_playback':
        case 'allow_localplay_playback':
        case 'demo_mode':
        case 'condPL':
        case 'rio_track_stats':
        case 'rio_global_stats':
        case 'direct_link':
        case 'iframes':
        case 'now_playing_per_user':
        case 'show_played_times':
        case 'song_page_title':
        case 'subsonic_backend':
        case 'plex_backend':
        case 'webplayer_flash':
        case 'webplayer_html5':
        case 'allow_personal_info_now':
        case 'allow_personal_info_recent':
        case 'allow_personal_info_time':
        case 'allow_personal_info_agent':
        case 'ui_fixed':
        case 'autoupdate':
        case 'webplayer_confirmclose':
        case 'webplayer_pausetabs':
        case 'stream_beautiful_url':
        case 'share':
        case 'share_social':
        case 'broadcast_by_default':
        case 'album_group':
        case 'topmenu':
        case 'demo_clear_sessions':
        case 'show_donate':
        case 'allow_upload':
        case 'upload_subdir':
        case 'upload_user_artist':
        case 'upload_allow_edit':
            $is_true = '';
            $is_false = '';
            if ($value == '1') {
                $is_true = "selected=\"selected\""; } else {
                $is_false = "selected=\"selected\"";
            }
            echo "<select name=\"$name\">\n";
            echo "\t<option value=\"1\" $is_true>" . T_("Enable") . "</option>\n";
            echo "\t<option value=\"0\" $is_false>" . T_("Disable") . "</option>\n";
            echo "</select>\n";
        break;
        case 'upload_catalog':
            show_catalog_select('upload_catalog', $value, '', true);
        break;
        case 'play_type':
            $is_localplay = '';
            $is_democratic = '';
            $is_web_player = '';
            $is_stream = '';
            if ($value == 'localplay') {
                $is_localplay = 'selected="selected"';
            } elseif ($value == 'democratic') {
                $is_democratic = 'selected="selected"';
            } elseif ($value == 'web_player') {
                $is_web_player = 'selected="selected"';
            } else {
                $is_stream = "selected=\"selected\"";
            }
            echo "<select name=\"$name\">\n";
            echo "\t<option value=\"\">" . T_('None') . "</option>\n";
            if (AmpConfig::get('allow_stream_playback')) {
                echo "\t<option value=\"stream\" $is_stream>" . T_('Stream') . "</option>\n";
            }
            if (AmpConfig::get('allow_democratic_playback')) {
                echo "\t<option value=\"democratic\" $is_democratic>" . T_('Democratic') . "</option>\n";
            }
            if (AmpConfig::get('allow_localplay_playback')) {
                echo "\t<option value=\"localplay\" $is_localplay>" . T_('Localplay') . "</option>\n";
            }
            echo "\t<option value=\"web_player\" $is_web_player>" . _('Web Player') . "</option>\n";
            echo "</select>\n";
        break;
        case 'playlist_type':
            $var_name = $value . "_type";
            ${$var_name} = "selected=\"selected\"";
            echo "<select name=\"$name\">\n";
            echo "\t<option value=\"m3u\" $m3u_type>" . T_('M3U') . "</option>\n";
            echo "\t<option value=\"simple_m3u\" $simple_m3u_type>" . T_('Simple M3U') . "</option>\n";
            echo "\t<option value=\"pls\" $pls_type>" . T_('PLS') . "</option>\n";
            echo "\t<option value=\"asx\" $asx_type>" . T_('Asx') . "</option>\n";
            echo "\t<option value=\"ram\" $ram_type>" . T_('RAM') . "</option>\n";
            echo "\t<option value=\"xspf\" $xspf_type>" . T_('XSPF') . "</option>\n";
            echo "</select>\n";
        break;
        case 'lang':
            $languages = get_languages();
            echo '<select name="' . $name . '">' . "\n";
            foreach ($languages as $lang=>$name) {
                $selected = ($lang == $value) ? 'selected="selected"' : '';
                echo "\t<option value=\"$lang\" " . $selected . ">$name</option>\n";
            } // end foreach
            echo "</select>\n";
        break;
        case 'localplay_controller':
            $controllers = Localplay::get_controllers();
            echo "<select name=\"$name\">\n";
            echo "\t<option value=\"\">" . T_('None') . "</option>\n";
            foreach ($controllers as $controller) {
                if (!Localplay::is_enabled($controller)) { continue; }
                $is_selected = '';
                if ($value == $controller) { $is_selected = 'selected="selected"'; }
                echo "\t<option value=\"" . $controller . "\" $is_selected>" . ucfirst($controller) . "</option>\n";
            } // end foreach
            echo "</select>\n";
        break;
        case 'localplay_level':
            $is_user = '';
            $is_admin = '';
            $is_manager = '';
            if ($value == '25') {
                $is_user = 'selected="selected"';
            } elseif ($value == '100') {
                $is_admin = 'selected="selected"';
            } elseif ($value == '50') {
                $is_manager = 'selected="selected"';
            }
            echo "<select name=\"$name\">\n";
            echo "<option value=\"0\">" . T_('Disabled') . "</option>\n";
            echo "<option value=\"25\" $is_user>" . T_('User') . "</option>\n";
            echo "<option value=\"50\" $is_manager>" . T_('Manager') . "</option>\n";
            echo "<option value=\"100\" $is_admin>" . T_('Admin') . "</option>\n";
            echo "</select>\n";
        break;
        case 'theme_name':
            $themes = get_themes();
            echo "<select name=\"$name\">\n";
            foreach ($themes as $theme) {
                $is_selected = "";
                if ($value == $theme['path']) { $is_selected = "selected=\"selected\""; }
                echo "\t<option value=\"" . $theme['path'] . "\" $is_selected>" . $theme['name'] . "</option>\n";
            } // foreach themes
            echo "</select>\n";
        break;
        case 'playlist_method':
            ${$value} = ' selected="selected"';
            echo "<select name=\"$name\">\n";
            echo "\t<option value=\"send\"$send>" . T_('Send on Add') . "</option>\n";
            echo "\t<option value=\"send_clear\"$send_clear>" . T_('Send and Clear on Add') . "</option>\n";
            echo "\t<option value=\"clear\"$clear>" . T_('Clear on Send') . "</option>\n";
            echo "\t<option value=\"default\"$default>" . T_('Default') . "</option>\n";
            echo "</select>\n";
        break;
        case 'transcode':
            ${$value} = ' selected="selected"';
            echo "<select name=\"$name\">\n";
            echo "\t<option value=\"never\"$never>" . T_('Never') . "</option>\n";
            echo "\t<option value=\"default\"$default>" . T_('Default') . "</option>\n";
            echo "\t<option value=\"always\"$always>" . T_('Always') . "</option>\n";
            echo "</select>\n";
        break;
        case 'show_lyrics':
            $is_true = '';
            $is_false = '';
            if ($value == '1') {
                $is_true = "selected=\"selected\"";
            } else {
                $is_false = "selected=\"selected\"";
            }
            echo "<select name=\"$name\">\n";
            echo "\t<option value=\"1\" $is_true>" . T_("Enable") . "</option>\n";
            echo "\t<option value=\"0\" $is_false>" . T_("Disable") . "</option>\n";
            echo "</select>\n";
        break;
        case 'album_sort':
            $is_sort_year_asc = '';
            $is_sort_year_desc = '';
            $is_sort_name_asc = '';
            $is_sort_name_desc = '';
            $is_sort_default = '';
            if ($value == 'year_asc') {
                $is_sort_year_asc = 'selected="selected"';
            } elseif ($value == 'year_desc') {
                $is_sort_year_desc = 'selected="selected"';
            } elseif ($value == 'name_asc') {
                $is_sort_name_asc = 'selected="selected"';
            } elseif ($value == 'name_desc') {
                $is_sort_name_desc = 'selected="selected"';
            } else {
                $is_sort_default = 'selected="selected"';
            }

            echo "<select name=\"$name\">\n";
            echo "\t<option value=\"default\" $is_sort_default>" . T_('Default') . "</option>\n";
            echo "\t<option value=\"year_asc\" $is_sort_year_asc>" . T_('Year ascending') . "</option>\n";
            echo "\t<option value=\"year_desc\" $is_sort_year_desc>" . T_('Year descending') . "</option>\n";
            echo "\t<option value=\"name_asc\" $is_sort_name_asc>" . T_('Name ascending') . "</option>\n";
            echo "\t<option value=\"name_desc\" $is_sort_name_desc>" . T_('Name descending') . "</option>\n";
            echo "</select>\n";
        break;
        default:
            if (preg_match('/_pass$/', $name)) {
                echo '<input type="password" name="' . $name . '" value="******" />';
            } else {
                echo '<input type="text" name="' . $name . '" value="' . $value .'" />';
            }
        break;

    }

} // create_preference_input
