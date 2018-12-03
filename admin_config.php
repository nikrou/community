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

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH . 'include/functions_picture.inc.php');
load_language('plugin.lang', COMMUNITY_PATH);

$admin_base_url = get_root_url() . 'admin/index.php?page=plugin-community-config';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

if (!empty($_POST)) {
    check_input_parameter('user_albums_parent', $_POST, false, PATTERN_ID);

    $conf['community'] = array(
        'user_albums' => !empty($_POST['user_albums']),
        'user_albums_parent' => $_POST['user_albums_parent'],
    );

    conf_update_param('community', $conf['community']);

    $page['infos'][] = l10n('Information data registered in database');
}

// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

$template->set_filename('plugin_admin_content', __DIR__ . '/tpl/admin_config.tpl');

// +-----------------------------------------------------------------------+
// | form options                                                          |
// +-----------------------------------------------------------------------+

$template->assign('user_albums', $conf['community']['user_albums']);

if (isset($conf['community']['user_albums_parent'])) {
    $category_options_selected = $conf['community']['user_albums_parent'];
} else {
    // is there a "Community" album?
    $query = 'SELECT id FROM ' . CATEGORIES_TABLE . ' WHERE name = \'Community\'';
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $category_options_selected = $row['id'];
        break;
    }
}

// list of albums
$query = 'SELECT id,name,uppercats,global_rank FROM ' . CATEGORIES_TABLE;
display_select_cat_wrapper(
    $query,
    isset($category_options_selected) ? $category_options_selected : array(),
    'category_options'
);

// image level options
$selected_level = isset($_POST['level']) ? $_POST['level'] : 0;
$template->assign(
    array(
        'level_options' => get_privacy_level_options(),
        'level_options_selected' => array($selected_level)
    )
);
