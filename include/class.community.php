<?php
// +-----------------------------------------------------------------------+
// | Community - a plugin for Phyxo                                        |
// | Copyright(C) 2015 Nicolas Roudaire             http://www.nikrou.net  |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

class Community
{
    /**
     * plugin initialization
     *   - check for upgrades
     *   - unserialize configuration
     *   - load language
     */
    public static function init() {
        global $conf, $user, $services;

        // prepare plugin configuration
        $conf['community'] = json_decode($conf['community'], true);

        // @TODO: generate permissions in $user['community_permissions'] if ws.php
        // + remove all calls of community_get_user_permissions related to webservices
        if (!defined('IN_ADMIN') or !IN_ADMIN) {
            $user['community_permissions'] = community_get_user_permissions($user['id']);
        }

        if (!defined('IN_ADMIN') or !IN_ADMIN) {
            load_language('admin.lang');
        }

        load_language('plugin.lang', COMMUNITY_PATH);

        if (script_basename() == 'uploadify' and !$services['users']->isAdmin()) {
            $_POST['level'] = 16;
        }
    }
}