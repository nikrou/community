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

/*
  Plugin Name: Community
  Version: 0.3.1
  Description: Non admin users can add photos
  Plugin URI: http://ext.phyxo.net/extension_view.php?eid=NNN
  Author: Nicolas
  Author URI: http://www.nikrou.net
 */

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}


// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+

global $prefixeTable;
defined('COMMUNITY_ID') or define('COMMUNITY_ID', basename(__DIR__));
define('COMMUNITY_PATH', PHPWG_PLUGINS_PATH . '/' . basename(__DIR__) . '/');
define('COMMUNITY_PERMISSIONS_TABLE', $prefixeTable . 'community_permissions');
define('COMMUNITY_PENDINGS_TABLE', $prefixeTable . 'community_pendings');

include_once(COMMUNITY_PATH . 'include/autoload.inc.php');
include_once(COMMUNITY_PATH . 'include/functions_community.inc.php');

// init the plugin
add_event_handler('init', array('Community', 'init'));

if (defined('IN_ADMIN') and IN_ADMIN) {
    add_event_handler('get_admin_plugin_menu_links', array('CommunityAdmin', 'admin_menu'));

    add_event_handler('delete_user', array('CommunityAdmin', 'delete_user'));
    add_event_handler('delete_categories', array('CommunityAdmin', 'delete_category'));
    add_event_handler('delete_elements', array('CommunityAdmin', 'delete_elements'));
    add_event_handler('invalidate_user_cache', array('CommunityAdmin', 'refresh_cache_update_time'));
} else {
    add_event_handler('loc_end_section_init', array('CommunityPublic', 'section_init'));
    add_event_handler('loc_end_index', array('CommunityPublic', 'index'));
    add_event_handler('blockmanager_apply', array('CommunityPublic', 'gallery_menu'), EVENT_HANDLER_PRIORITY_NEUTRAL + 10);
    add_event_handler('loc_begin_cat_modify', array('CommunityPublic', 'cat_modify_submit'));
    add_event_handler('loc_end_cat_modify', array('CommunityPublic', 'set_prefilter_cat_modify'), 50);

    add_event_handler('ws_add_methods', array('wsCommunity', 'addMethods'), EVENT_HANDLER_PRIORITY_NEUTRAL + 5);
    add_event_handler('sendResponse', array('wsCommunity', 'sendResponse'));
}
