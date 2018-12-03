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

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

global $__autoload;
$__autoload['Community'] = __DIR__ . '/class.community.php';
$__autoload['CommunityAdmin'] = __DIR__ . '/class.community.admin.php';
$__autoload['CommunityPublic'] = __DIR__ . '/class.community.public.php';
$__autoload['wsCommunity'] = __DIR__ . '/class.ws.community.php';

if (function_exists('spl_autoload_register')) {
    spl_autoload_register('community_autoload');
} else {
    function __autoload($name)
    {
        community_autoload($name);
    }
}

function community_autoload($name)
{
    global $__autoload;

    if (!empty($__autoload[$name])) {
        require_once $__autoload[$name];
    }
}
