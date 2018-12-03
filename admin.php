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

use Phyxo\TabSheet\TabSheet;

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

define('COMMUNITY_BASE_URL', get_root_url() . 'admin/index.php?page=plugin-community');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | Tabs                                                                  |
// +-----------------------------------------------------------------------+

$pendings_label = l10n('Pending Photos');
if ($page['community_nb_pendings'] > 0) {
    $pendings_label .= ' (' . $page['community_nb_pendings'] . ')';
}

$tabs = array(
    array(
        'code' => 'permissions',
        'label' => l10n('Upload Permissions'),
    ),
    array(
        'code' => 'pendings',
        'label' => $pendings_label,
    ),
    array(
        'code' => 'config',
        'label' => l10n('Configuration'),
    ),
);

$tab_codes = array_map(function ($a) {
    return $a["code"];
}, $tabs);

if (isset($_GET['tab']) and in_array($_GET['tab'], $tab_codes)) {
    $page['tab'] = $_GET['tab'];
} else {
    $page['tab'] = $tabs[0]['code'];
}

$tabsheet = new TabSheet();
foreach ($tabs as $tab) {
    $tabsheet->add(
        $tab['code'],
        $tab['label'],
        COMMUNITY_BASE_URL . '-' . $tab['code']
    );
}
$tabsheet->select($page['tab']);
$tabsheet->assign($template);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('photos_add' => 'photos_add_' . $page['tab'] . '.tpl'));

// +-----------------------------------------------------------------------+
// |                             Load the tab                              |
// +-----------------------------------------------------------------------+

include(COMMUNITY_PATH . 'admin_' . $page['tab'] . '.php');

// +-----------------------------------------------------------------------+
// | sending html code                                                     |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
