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
    die("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH . 'include/functions_picture.inc.php');
load_language('plugin.lang', COMMUNITY_PATH);

$admin_base_url = get_root_url() . 'admin/index.php?page=plugin-community-pendings';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

if (!empty($_POST)) {
    if (empty($_POST['photos'])) {
        $page['errors'][] = l10n('Select at least one photo');
    } else {
        check_input_parameter('photos', $_POST, true, PATTERN_ID);
        check_input_parameter('level', $_POST, false, PATTERN_ID);

        if (isset($_POST['validate'])) {
            $query = 'UPDATE ' . COMMUNITY_PENDINGS_TABLE;
            $query .= ' SET state = \'validated\',';
            $query .= ' validated_by = ' . $conn->db_real_escape_string($user['id']);
            $query .= ' WHERE image_id ' . $conn->in($_POST['photos']);
            $conn->db_query($query);

            $query = 'UPDATE ' . IMAGES_TABLE;
            $query .= ' SET level = ' . $conn->db_real_escape_string($_POST['level']) . ',';
            $query .= ' date_available = NOW()';
            $query .= ' WHERE id ' . $conn->in($_POST['photos']);
            $conn->db_query($query);

            $page['infos'][] = sprintf(l10n('%d photos validated'), count($_POST['photos']));
        }

        if (isset($_POST['reject'])) {
            $query = 'DELETE FROM ' . COMMUNITY_PENDINGS_TABLE;
            $query .= ' WHERE image_id ' . $conn->in($_POST['photos']);
            $conn->db_query($query);

            delete_elements($_POST['photos'], true);

            $page['infos'][] = sprintf(l10n('%d photos rejected'), count($_POST['photos']));
        }

        invalidate_user_cache();
    }
}

// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('plugin_admin_content' => __DIR__ . '/tpl/admin_pendings.tpl'));

// +-----------------------------------------------------------------------+
// | pending photos list                                                   |
// +-----------------------------------------------------------------------+

// just in case (because we had a bug in Community plugin up to version
// 2.5.c) let's remove rows in community_pendings table if related photos
// has been deleted
$query = 'SELECT image_id FROM ' . COMMUNITY_PENDINGS_TABLE;
$query .= ' LEFT JOIN ' . IMAGES_TABLE . ' ON id = image_id WHERE id IS NULL';
$to_delete = $conn->query2array($query, null, 'image_id');

if (count($to_delete) > 0) {
    $query = 'DELETE FROM ' . COMMUNITY_PENDINGS_TABLE;
    $query .= ' WHERE image_id ' . $conn->in($to_delete);
    $conn->db_query($query);
}

$list = array();

$query = 'SELECT image_id,added_on,i.id,path,date_creation,name,comment,added_by,';
$query .= 'file,name,filesize,width,height,rotation,representative_ext,';
$query .= $conf['user_fields']['username'] . ' AS username';
$query .= ' FROM ' . COMMUNITY_PENDINGS_TABLE . ' AS cp';
$query .= ' LEFT JOIN ' . IMAGES_TABLE . ' AS i ON i.id = cp.image_id';
$query .= ' LEFT JOIN ' . USERS_TABLE . ' AS u ON u.' . $conf['user_fields']['id'] . ' = i.added_by';
$query .= ' WHERE state = \'moderation_pending\'';
$query .= ' ORDER BY image_id DESC';
$result = $conn->db_query($query);
$rows = array();
$image_ids = array();
while ($row = $conn->db_fetch_assoc($result)) {
    $rows[] = $row;
    $image_ids[] = $row['id'];
}

$category_for_image = array();

if (count($image_ids) > 0) {
    $query = 'SELECT id,image_id,uppercats FROM ' . IMAGE_CATEGORY_TABLE;
    $query .= ' LEFT JOIN ' . CATEGORIES_TABLE . ' ON id = category_id';
    $query .= ' WHERE image_id ' . $conn->in($image_ids);
    $result = $conn->db_query($query);

    while ($row = $conn->db_fetch_assoc($result)) {
        $category_for_image[$row['image_id']] = get_cat_display_name_cache(
            $row['uppercats'],
            'admin.php?page=album-',
            false,
            true,
            'externalLink'
        );
    }
}

foreach ($rows as $row) {
    $src_image = new SrcImage($row);
    $thumb_url = DerivativeImage::url(IMG_THUMB, $src_image);
    $medium_url = DerivativeImage::url(IMG_MEDIUM, $src_image);

    // file properties
    $dimensions = null;
    $websize_props = $row['width'] . 'x' . $row['height'] . ' ' . l10n('pixels') . ', ' . sprintf(l10n('%d Kb'), $row['filesize']);
    if (!empty($row['has_high']) and $conn->get_boolean($row['has_high'])) {
        $high_path = get_high_path($row);
        list($high_width, $high_height) = getimagesize($high_path);
        $high_props = $high_width . 'x' . $high_height . ' ' . l10n('pixels') . ', ' . sprintf(l10n('%d Kb'), $row['high_filesize']);

        $dimensions = $high_props . ' (' . l10n('web size') . ' ' . $websize_props . ')';
    } else {
        $dimensions = $websize_props;
    }

    $album = null;
    if (isset($category_for_image[$row['id']])) {
        $album = $category_for_image[$row['id']];
    } else {
        $album = '<em>' . l10n('No album, this photo is orphan') . '</em>';
    }

    $template->append(
        'photos',
        array(
            'U_EDIT' => get_root_url() . 'admin.php?page=photo-' . $row['image_id'],
            'ID' => $row['image_id'],
            'TN_SRC' => $thumb_url,
            'MEDIUM_SRC' => $medium_url,
            'ADDED_BY' => $row['username'],
            'ADDED_ON' => format_date($row['added_on'], true),
            'NAME' => $row['name'],
            'DIMENSIONS' => $dimensions,
            'FILE' => $row['file'],
            'DATE_CREATION' => empty($row['date_creation']) ? l10n('N/A') : format_date($row['date_creation']),
            'ALBUM' => $album,
        )
    );
}

// +-----------------------------------------------------------------------+
// | form options                                                          |
// +-----------------------------------------------------------------------+

// image level options
$selected_level = isset($_POST['level']) ? $_POST['level'] : 0;
$template->assign(
    array(
        'level_options' => get_privacy_level_options(),
        'level_options_selected' => array($selected_level)
    )
);
