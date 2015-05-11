<?php
// +-----------------------------------------------------------------------+
// | Community - a plugin for Phyxo                                        |
// | Copyright(C) 2015 Nicolas Roudaire             http://www.nikrou.net  |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2015 Piwigo Team                  http://piwigo.org |
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

/*
  Plugin Name: Community
  Version: 0.2.0
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
define('COMMUNITY_PATH' , PHPWG_PLUGINS_PATH.basename(__DIR__).'/');
define('COMMUNITY_PERMISSIONS_TABLE', $prefixeTable.'community_permissions');
define('COMMUNITY_PENDINGS_TABLE', $prefixeTable.'community_pendings');

include_once(COMMUNITY_PATH.'include/autoload.inc.php');
include_once(COMMUNITY_PATH.'include/functions_community.inc.php');

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
    add_event_handler('blockmanager_apply', array('CommunityPublic', 'gallery_menu'), EVENT_HANDLER_PRIORITY_NEUTRAL+10);
    add_event_handler('loc_begin_cat_modify', array('CommunityPublic', 'cat_modify_submit'));
    add_event_handler('loc_end_cat_modify', array('CommunityPublic', 'set_prefilter_cat_modify'), 50);

    add_event_handler('ws_add_methods', array('wsCommunity', 'addMethods'), EVENT_HANDLER_PRIORITY_NEUTRAL+5);
    add_event_handler('sendResponse', array('wsCommunity', 'sendResponse'));
}
