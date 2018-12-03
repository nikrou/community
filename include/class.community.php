<?php
/*
 * This file is part of Community, a plugin for Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Community
{
    /**
     * plugin initialization
     *   - check for upgrades
     *   - unserialize configuration
     *   - load language
     */
    public static function init()
    {
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