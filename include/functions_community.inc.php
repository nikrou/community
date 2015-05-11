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

function community_get_user_permissions($user_id) {
    global $conf, $user, $conn, $services;

    $cache_key = community_get_cache_key();
    if (!isset($cache_key)) {
        $cache_key = community_update_cache_key();
    }

    // I (plg) don't understand why, but when you connect, you keep the
    // permissions calculated for the "guest" : the session "inherits"
    // variables from guest to the connected user, so I add a
    // $_SESSION['community_user_id'] to force refresh if the permissions were
    // not calculated for the right user
    if (isset($_SESSION['community_user_id'])
        and $_SESSION['community_user_id'] == $user_id
        and $_SESSION['community_cache_key'] == $cache_key) {
        return $_SESSION['community_user_permissions'];
    }

    $return = array(
        'upload_whole_gallery' => false,
        'create_whole_gallery' => false,
        'user_album' => false,
        'create_categories' => array(),
        'upload_categories' => array(),
        'permission_ids' => array(),
        'nb_photos' => 0,
        'storage' => 0,
    );

    // what are the user groups?
    $query = 'SELECT group_id FROM '.USER_GROUP_TABLE;
    $query .= ' WHERE user_id = '.$conn->db_real_escape_string($user_id);
    $user_group_ids = $conn->query2array($query, null, 'group_id');

    $query = 'SELECT id,type,category_id,user_album,recursive,create_subcategories,';
    $query .= 'nb_photos,storage FROM '.COMMUNITY_PERMISSIONS_TABLE;
    $query .= ' WHERE (type = \'any_visitor\')';

    if ($user_id != $conf['guest_id']) {
        $query .= ' OR (type = \'any_registered_user\')';
        $query .= ' OR (type = \'user\' AND user_id = '.$conn->db_real_escape_string($user_id).')';

        if (count($user_group_ids) > 0) {
            $query .= ' OR (type = \'group\' AND group_id '.$conn->in($user_group_ids).')';
        }
    }

    $recursive_categories = array();

    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $return['permission_ids'][] = $row['id'];

        if ($conn->get_boolean($row['user_album'])==false) {
            if (empty($row['category_id'])) {
                $return['upload_whole_gallery'] = true;
            } else {
                $return['upload_categories'][] = $row['category_id'];

                if ($conn->get_boolean($row['recursive'])==true) {
                    $recursive_categories[] = $row['category_id'];
                }
            }
        }

        if ($conn->get_boolean($row['create_subcategories'])==true) {
            if (empty($row['category_id'])) {
                $return ['create_whole_gallery'] = true;
            } else {
                $return['create_categories'][] = $row['category_id'];
            }
        }

        if ($return['nb_photos'] != -1) {
            if (empty($row['nb_photos']) or -1 == $row['nb_photos']) {
                // that means "no limit"
                $return['nb_photos'] = -1;
            } elseif ($row['nb_photos'] > $return['nb_photos']) {
                $return['nb_photos'] = $row['nb_photos'];
            }
        }

        if ($return['storage'] != -1) {
            if (empty($row['storage']) or -1 == $row['storage']) {
                // that means "no limit"
                $return['storage'] = -1;
            } elseif ($row['storage'] > $return['storage']) {
                $return['storage'] = $row['storage'];
            }
        }

        if ($conf['community']['user_albums'] and 'any_visitor' != $row['type']) {
            $return['user_album'] = true;
        }
    }

    if ($services['users']->isAdmin()) {
        $return ['upload_whole_gallery'] = true;
        $return ['create_whole_gallery'] = true;
        $return['nb_photos'] = -1;
        $return['storage'] = -1;
    }

    // these are categories with access permission but considering the user
    // has a level 8 (maximum level). We want to keep categories with no
    // photos inside (for nobody)
    $forbidden_categories = $services['users']->calculatePermissions($user['id'], $user['status']);

    $empty_categories = array_diff(
        explode(',', $user['forbidden_categories']),
        explode(',', $forbidden_categories)
    );

    if (count($empty_categories) > 0) {
        $query = 'SELECT category_id FROM '.IMAGE_CATEGORY_TABLE;
        $query .= ' LEFT JOIN '.IMAGES_TABLE.' ON image_id = id';
        $query .= ' WHERE category_id '.$conn->in($empty_categories);
        $query .= ' AND level > '.$conn->db_real_escape_string($user['level']);
        $query .= ' AND level <= 8';
        $query .= ' GROUP BY category_id';
        $not_really_empty_categories = array_keys($conn->query2array($query, 'category_id'));
        $forbidden_categories.= ','.implode(',', $not_really_empty_categories);
    }

    $query = 'SELECT id FROM '.CATEGORIES_TABLE;
    $all_categories = array_keys($conn->query2array($query, 'id'));

    if ($return['upload_whole_gallery']) {
        $return['upload_categories'] = array_diff(
            $all_categories,
            explode(',', $forbidden_categories)
        );
    } elseif (count($return['upload_categories']) > 0) {
        if (count($recursive_categories) > 0) {
            $return['upload_categories'] = array_unique(
                array_merge(
                    $return['upload_categories'],
                    get_subcat_ids($recursive_categories)
                )
            );
        }

        $return['upload_categories'] = array_diff(
            $return['upload_categories'],
            explode(',', $forbidden_categories)
        );
    }

    if ($return ['create_whole_gallery']) {
        $return['create_categories'] = array_diff(
            $all_categories,
            explode(',', $forbidden_categories)
        );
    } elseif (count($return['create_categories']) > 0) {
        // no need to check for "recursive", an upload permission can't be
        // "create_subcategories" without being "recursive"
        $return['create_categories'] = get_subcat_ids($return['create_categories']);

        $return['create_categories'] = array_diff(
            $return['create_categories'],
            explode(',', $forbidden_categories)
        );
    }

    if ($return['user_album']) {
        $user_album_category_id = community_get_user_album($user_id);

        if (!empty($user_album_category_id) and !in_array($user_album_category_id, $return['upload_categories'])) {
            array_push($return['upload_categories'], $user_album_category_id);
        }
    }

    // is the user allowed to use community upload?
    if (count($return['upload_categories']) > 0 or $return['create_whole_gallery'] or $return['user_album']) {
        $return['community_enabled'] = true;
    } else {
        $return['community_enabled'] = false;
    }

    $_SESSION['community_user_permissions'] = $return;
    $_SESSION['community_cache_key'] = $cache_key;
    $_SESSION['community_user_id'] = $user_id;

    return $_SESSION['community_user_permissions'];
}

/**
 * return the user album category_id. The album is automatically created if
 * it does not exist (or has been deleted)
 */
function community_get_user_album($user_id) {
    global $conf, $conn, $services;

    $user_album_category_id = null;

    $query = 'SELECT * FROM '.CATEGORIES_TABLE;
    $query .= ' WHERE community_user = '.$conn->db_real_escape_string($user_id);
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $user_album_category_id = $row['id'];
        break;
    }

    if (!isset($user_album_category_id)) {
        $user_infos = $services['users']->getUserData($user_id, false);

        include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
        $category_info = create_virtual_category($user_infos['username'], $conf['community']['user_albums_parent']);

        $conn->single_update(
            CATEGORIES_TABLE,
            array('community_user' => $user_id),
            array('id' => $category_info['id'])
        );

        $user_album_category_id = $category_info['id'];

        // in functions_html::get_cat_display_name_cache we use a cache and this
        // cache must be reset so that new album is included inside it.
        global $cache;
        unset($cache['cat_names']);
    }

    return $user_album_category_id;
}

function community_reject_pendings($image_ids) {
    global $conn;

    if (count($image_ids) == 0) {
        return;
    }

    $query = 'DELETE FROM '.COMMUNITY_PENDINGS_TABLE;
    $query .= ' WHERE image_id '.$conn->in($image_ids);
    $conn->db_query($query);

    // needs to be in administration panel
    delete_elements($image_ids, true);
}

function community_reject_user_pendings($user_id) {
    global $conn;

    $query = 'SELECT image_id FROM '.COMMUNITY_PENDINGS_TABLE.' AS cp';
    $query .= ' LEFT JOIN '.IMAGES_TABLE.' AS i ON i.id = cp.image_id';
    $query .= ' WHERE state != \'validated\'';
    $query .= ' AND added_by = '.$conn->db_real_escape_string($user_id);
    $result = $conn->db_query($query);
    $image_ids = array();
    while ($row = $conn->db_fetch_assoc($result)) {
        $image_ids[] = $row['image_id'];
    }

    community_reject_pendings($image_ids);
}

function community_update_cache_key() {
    $cache_key = generate_key(20);
    conf_update_param('community_cache_key', $cache_key);
    return $cache_key;
}

function community_get_cache_key() {
    global $conf;

    if (isset($conf['community_cache_key'])) {
        return $conf['community_cache_key'];
    } else {
        return null;
    }
}

function community_get_user_limits($user_id) {
    global $conn;

    // how many photos and storage for this user?
    $query = 'SELECT COUNT(id) AS nb_photos, FLOOR(SUM(filesize)/1024) AS storage';
    $query .= ' FROM '.IMAGES_TABLE;
    $query .= ' WHERE added_by = '.$conn->db_real_escape_string($user_id);
    $row = $conn->db_fetch_assoc($conn->db_query($query));
    if (empty($row['storage'])) {
        $row['storage'] = 0;
    }

    return $row;
}
