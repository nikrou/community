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

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');

define('COMMUNITY_BASE_URL', get_root_url().'admin.php?page=plugin-community');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | Tabs                                                                  |
// +-----------------------------------------------------------------------+

$pendings_label = l10n('Pending Photos');
if ($page['community_nb_pendings'] > 0) {
    $pendings_label.= ' ('.$page['community_nb_pendings'].')';
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

$tab_codes = array_map(
    create_function('$a', 'return $a["code"];'),
    $tabs
); // @TODO: anonymous function

if (isset($_GET['tab']) and in_array($_GET['tab'], $tab_codes)) {
    $page['tab'] = $_GET['tab'];
} else {
    $page['tab'] = $tabs[0]['code'];
}

$tabsheet = new tabsheet();
foreach ($tabs as $tab) {
    $tabsheet->add(
        $tab['code'],
        $tab['label'],
        COMMUNITY_BASE_URL.'-'.$tab['code']
    );
}
$tabsheet->select($page['tab']);
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('photos_add' => 'photos_add_'.$page['tab'].'.tpl'));

// +-----------------------------------------------------------------------+
// |                             Load the tab                              |
// +-----------------------------------------------------------------------+

include(COMMUNITY_PATH.'admin_'.$page['tab'].'.php');

// +-----------------------------------------------------------------------+
// | sending html code                                                     |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
