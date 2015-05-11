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

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

global $__autoload;
$__autoload['Community'] = __DIR__.'/class.community.php';
$__autoload['CommunityAdmin'] = __DIR__.'/class.community.admin.php';
$__autoload['CommunityPublic'] = __DIR__.'/class.community.public.php';
$__autoload['wsCommunity'] = __DIR__.'/class.ws.community.php';

if (function_exists('spl_autoload_register')) {
    spl_autoload_register('community_autoload');
} else {
    function __autoload($name) {
        community_autoload($name);
    }
}

function community_autoload($name) {
    global $__autoload;

    if (!empty($__autoload[$name])) {
        require_once $__autoload[$name];
    }
}
