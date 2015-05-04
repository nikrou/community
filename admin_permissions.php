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
load_language('plugin.lang', COMMUNITY_PATH);

$admin_base_url = get_root_url().'admin.php?page=plugin-community-permissions';

$who_options = array(
    'any_visitor' => l10n('any visitor'),
    'any_registered_user' => l10n('any registered user'),
    'user' => l10n('a specific user'),
    'group' => l10n('a group'),
);

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                            add permissions                            |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit_add'])) {
    if (!in_array($_POST['who'], array_keys($who_options))) {
        die('hacking attempt: invalid "who" option');
    }

    if ('user' == $_POST['who']) {
        check_input_parameter('who_user', $_POST, false, PATTERN_ID);
    }

    if ('group' == $_POST['who']) {
        check_input_parameter('who_group', $_POST, false, PATTERN_ID);
    }

    if (-1 != $_POST['category']) {
        check_input_parameter('category', $_POST, false, PATTERN_ID);
    }

    check_input_parameter('moderated', $_POST, false, '/^(true|false)$/');

    if (-1 != $_POST['nb_photos']) {
        check_input_parameter('nb_photos', $_POST, false, PATTERN_ID);
    }

    if (-1 != $_POST['storage']) {
        check_input_parameter('storage', $_POST, false, PATTERN_ID);
    }

    // it is already blocked by Javascript, but an extra check is usefull
    if ('any_visitor' == $_POST['who'] and -1 == $_POST['category']) {
        die('hacking attempt: invalid "where" option for this user');
    }

    if (-1 == $_POST['category']) {
        unset($_POST['recursive']);
        unset($_POST['create_subcategories']);
    }

    // creating the permission
    $insert = array(
        'type' => $_POST['who'],
        'group_id' => ('group' == $_POST['who']) ? $_POST['who_group'] : null,
        'user_id' => ('user' == $_POST['who']) ? $_POST['who_user'] : null,
        'category_id' => ($_POST['category'] > 0) ? $_POST['category'] : null,
        'user_album' => $conn->boolean_to_db(-1 == $_POST['category']),
        'recursive' => isset($_POST['recursive']) ? 'true' : 'false',
        'create_subcategories' => isset($_POST['create_subcategories']) ? 'true' : 'false',
        'moderated' => $_POST['moderated'],
        'nb_photos' => $_POST['nb_photos'],
        'storage' => $_POST['storage'],
    );

    // does this permission already exist?
    //
    // a permission is identified by a who+where
    $query = 'SELECT id FROM '.COMMUNITY_PERMISSIONS_TABLE;
    $query .= ' WHERE type = \''.$conn->db_real_escape_string($insert['type']).'\'';
    $query .= ' AND user_id '.(isset($insert['user_id']) ? '= '.$conn->db_real_escape_string($insert['user_id']) : 'is null');
    $query .= ' AND group_id '.(isset($insert['group_id']) ? '= '.$conn->db_real_escape_string($insert['group_id']) : 'is null');
    $query .= ' AND category_id '.(isset($insert['category_id']) ? '= '.$conn->db_real_escape_string($insert['category_id']) : 'is null');
    $query .= ' AND user_album = \''.$conn->db_real_escape_string($insert['user_album']).'\'';
    $result = $conn->db_query($query);
    $row = $conn->db_fetch_assoc($result);
    if (isset($row['id'])) {
        if (isset($_POST['edit'])) {
            check_input_parameter('edit', $_POST, false, PATTERN_ID);

            if ($_POST['edit'] != $row['id']) {
                // we have to delete the edited permission
                $query = 'DELETE FROM '.COMMUNITY_PERMISSIONS_TABLE;
                $query .= ' WHERE id = '.$conn->db_real_escape_string($_POST['edit']);
                $conn->db_query($query);
            }
        }

        $_POST['edit'] = $row['id'];
    }

    if (isset($_POST['edit'])) {
        check_input_parameter('edit', $_POST, false, PATTERN_ID);

        $insert['id'] = $_POST['edit'];

        $conn->mass_updates(
            COMMUNITY_PERMISSIONS_TABLE,
            array(
                'primary' => array('id'),
                'update' => array_keys($insert),
            ),
            array($insert)
        );

        $page['highlight'] = $insert['id'];

        $page['infos'][] = l10n('Permission updated');
    } else {
        $conn->mass_inserts(
            COMMUNITY_PERMISSIONS_TABLE,
            array_keys($insert),
            array($insert)
        );

        $page['highlight'] = $conn->db_insert_id(COMMUNITY_PERMISSIONS_TABLE);

        $page['infos'][] = l10n('Permission added');
    }

    community_update_cache_key();
}

// +-----------------------------------------------------------------------+
// |                           remove permissions                          |
// +-----------------------------------------------------------------------+

if (isset($_GET['delete'])) {
    check_input_parameter('delete', $_GET, false, PATTERN_ID);

    $query = 'DELETE FROM '.COMMUNITY_PERMISSIONS_TABLE;
    $query .= ' WHERE id = '.$conn->db_real_escape_string($_GET['delete']);
    $conn->db_query($query);

    community_update_cache_key();

    $_SESSION['page_infos'] = array(l10n('Permission removed'));
    redirect($admin_base_url);
}

// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('plugin_admin_content' => __DIR__.'/tpl/admin_permissions.tpl'));

// +-----------------------------------------------------------------------+
// | prepare form                                                          |
// +-----------------------------------------------------------------------+

// edit mode?
if (isset($_GET['edit'])) {
    check_input_parameter('edit', $_GET, false, PATTERN_ID);

    $query = 'SELECT * FROM '.COMMUNITY_PERMISSIONS_TABLE;
    $query .= ' WHERE id = '.$conn->db_real_escape_string($_GET['edit']);
    $result = $conn->db_query($query);
    $row = $conn->db_fetch_assoc($result);

    if (isset($row['id'])) {
        $category_options_selected = $row['category_id'];

        $template->assign(
            array(
                'edit' => $row['id'],
                'who_options_selected' => $row['type'],
                'user_options_selected' => $row['user_id'],
                'group_options_selected' => $row['group_id'],
                'whole_gallery_selected' => empty($row['category_id']) and !$conn->get_boolean($row['user_album']),
                'user_album_selected' => $conn->get_boolean($row['user_album']),
                'recursive' => $conn->get_boolean($row['recursive']),
                'create_subcategories' => $conn->get_boolean($row['create_subcategories']),
                'moderated' => $conn->get_boolean($row['moderated']),
                'nb_photos' => empty($row['nb_photos']) ? -1 : $row['nb_photos'],
                'storage' => empty($row['storage']) ? -1 : $row['storage'],
            )
        );
    }
} else {
    $template->assign(
        array(
            'whole_gallery_selected' => !$conf['community']['user_albums'],
            'user_album_selected' => $conf['community']['user_albums'],
            'moderated' => true,
            'nb_photos' => -1,
            'storage' => -1,
        )
    );
}

// who options
$template->assign(array('who_options' => $who_options));

// list of users
$users = array();

$query = 'SELECT '.$conf['user_fields']['id'].' AS id,'.$conf['user_fields']['username'].' AS username';
$query .= ' FROM '.USERS_TABLE.' AS u';
$query .= ' LEFT JOIN '.USER_INFOS_TABLE.' AS uf ON uf.user_id = u.'.$conf['user_fields']['id'];
$query .= ' WHERE uf.status IN (\'normal\',\'generic\')';
$result = $conn->db_query($query);
while ($row = $conn->db_fetch_assoc($result)) {
    $users[$row['id']] = $row['username'];
}

natcasesort($users);

$template->assign(array('user_options' => $users));

// list of groups
$groups = array();

$query = 'SELECT id,name FROM '.GROUPS_TABLE;
$result = $conn->db_query($query);
while ($row = $conn->db_fetch_assoc($result)) {
    $groups[$row['id']] = $row['name'];
}

natcasesort($groups);

$template->assign(array('group_options' => $groups));

$template->assign(
    array(
        'F_ADD_ACTION' => COMMUNITY_BASE_URL.'-'.$page['tab'],
        'community_conf' => $conf['community'],
    )
);

// list of albums
$query = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
display_select_cat_wrapper(
    $query,
    isset($category_options_selected) ? $category_options_selected : array(),
    'category_options'
);

// +-----------------------------------------------------------------------+
// | permission list                                                       |
// +-----------------------------------------------------------------------+

// user with community permissions
$query = 'SELECT * FROM '.COMMUNITY_PERMISSIONS_TABLE.' ORDER BY id DESC';
$result = $conn->db_query($query);

$permissions = array();
$user_ids = array();
$group_ids = array();
$category_ids = array();

while ($row = $conn->db_fetch_assoc($result)) {
    $permissions[] = $row;

    if (!empty($row['user_id'])) {
        $user_ids[] = $row['user_id'];
    }

    if (!empty($row['group_id'])) {
        $group_ids[] = $row['group_id'];
    }

    if (!empty($row['category_id'])) {
        $category_ids[] = $row['category_id'];
    }
}

if (!empty($user_ids)) {
    $query = 'SELECT '.$conf['user_fields']['id'].' AS id,'.$conf['user_fields']['username'].' AS username';
    $query .= ' FROM '.USERS_TABLE;
    $query .= ' WHERE '.$conf['user_fields']['id'].' '.$conn->in($user_ids);
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $name_of_user[ $row['id'] ] = $row['username'];
    }
}

if (!empty($group_ids)) {
    $query = 'SELECT id,name FROM '.GROUPS_TABLE;
    $query .= ' WHERE id '.$conn->in($group_ids);
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $name_of_group[ $row['id'] ] = $row['name'];
    }
}

if (!empty($category_ids)) {
    $query = 'SELECT id,uppercats FROM '.CATEGORIES_TABLE;
    $query .= ' WHERE id '.$conn->in($category_ids);
    $result = $conn->db_query($query);

    while ($row = $conn->db_fetch_assoc($result)) {
        $name_of_category[ $row['id'] ] = get_cat_display_name_cache(
            $row['uppercats'],
            null,
            false
        );
    }
}

foreach ($permissions as $permission) {
    $where = l10n('User album only');
    if (!$conn->get_boolean($permission['user_album'])) {
        $where = l10n('The whole gallery');
        if (isset($permission['category_id'])) {
            $where = $name_of_category[ $permission['category_id'] ];
        }
    }

    $who = l10n('any visitor');
    if ('any_registered_user' == $permission['type']) {
        $who = l10n('any registered user');
    } elseif ('user' == $permission['type']) {
        $who = sprintf(
            l10n('%s (the user)'),
            $name_of_user[$permission['user_id']]
        );
    } elseif ('group' == $permission['type']) {
        $who = sprintf(
            l10n('%s (the group)'),
            $name_of_group[$permission['group_id']]
        );
    }

    $trust = l10n('low trust');
    $trust_tooltip = l10n('uploaded photos must be validated by an administrator');
    if ('false' == $permission['moderated']) {
        $trust = l10n('high trust');
        $trust_tooltip = l10n('uploaded photos are directly displayed in the gallery');
    }

    $highlight = false;
    if (isset($_GET['edit']) and $permission['id'] == $_GET['edit']) {
        $highlight = true;
    }
    if (isset($page['highlight']) and $permission['id'] == $page['highlight']) {
        $highlight = true;
    }

    $nb_photos = false;
    $nb_photos_tooltip = null;
    if (!empty($permission['nb_photos']) and $permission['nb_photos'] > 0) {
        $nb_photos = $permission['nb_photos'];
        $nb_photos_tooltip = sprintf(
            l10n('up to %d photos (for each user)'),
            $nb_photos
        );
    }

    $storage = false;
    $storage_tooltip = null;
    if (!empty($permission['storage']) and $permission['storage'] > 0) {
        $storage = $permission['storage'];
        $storage_tooltip = sprintf(
            l10n('up to %dMB (for each user)'),
            $storage
        );
    }

    $template->append(
        'permissions',
        array(
            'WHO' => $who,
            'WHERE' => $where,
            'TRUST' => $trust,
            'TRUST_TOOLTIP' => $trust_tooltip,
            'RECURSIVE' => $conn->get_boolean($permission['recursive']),
            'RECURSIVE_TOOLTIP' => l10n('Apply to sub-albums'),
            'NB_PHOTOS' => $nb_photos,
            'NB_PHOTOS_TOOLTIP' => $nb_photos_tooltip,
            'STORAGE' => $storage,
            'STORAGE_TOOLTIP' => $storage_tooltip,
            'CREATE_SUBCATEGORIES' => $conn->get_boolean($permission['create_subcategories']),
            'U_DELETE' => $admin_base_url.'&amp;delete='.$permission['id'],
            'U_EDIT' => $admin_base_url.'&amp;edit='.$permission['id'],
            'HIGHLIGHT' => $highlight,
        )
    );
}

// +-----------------------------------------------------------------------+
// | sending html code                                                     |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
