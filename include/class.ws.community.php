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

class wsCommunity
{
    public static function addMethods($arr)
    {
        self::switchUserToAdmin($arr);
        self::wsReplaceMethods($arr);
    }

    private static function switchUserToAdmin($arr)
    {
        global $user, $community, $services;

        $service = &$arr[0];

        if ($services['users']->isAdmin()) {
            return;
        }

        $community = array('method' => $_REQUEST['method']);

        if ('pwg.images.addSimple' == $community['method']) {
            $community['category'] = $_REQUEST['category'];
        } elseif ('pwg.images.add' == $community['method']) {
            $community['category'] = $_REQUEST['categories'];
            $community['md5sum'] = $_REQUEST['original_sum'];
        }

        // conditional : depending on community permissions, display the "Add
        // photos" link in the gallery menu
        $user_permissions = community_get_user_permissions($user['id']);

        if (count($user_permissions['upload_categories']) == 0 and !$user_permissions['create_whole_gallery']) {
            return;
        }

        // if level of trust is low, then we have to set level to 16 @TODO: why ?

        $methods = array();
        $methods[] = 'pwg.tags.add';
        $methods[] = 'pwg.images.exist';
        $methods[] = 'pwg.images.add';
        $methods[] = 'pwg.images.addSimple';
        $methods[] = 'pwg.images.addChunk';
        $methods[] = 'pwg.images.checkUpload';
        $methods[] = 'pwg.images.checkFiles';
        $methods[] = 'pwg.images.setInfo';

        if (in_array($community['method'], $methods)) {
            $user['status'] = 'admin';
        }

        if ('pwg.categories.add' == $community['method']) {
            if (in_array($_REQUEST['parent'], $user_permissions['create_categories'])
                or $user_permissions['create_whole_gallery']) {
                $user['status'] = 'admin';
            }
        }

        return;
    }

    private static function wsReplaceMethods($arr)
    {
        global $conf, $user, $services;

        $service = &$arr[0];

        if ($services['users']->isAdmin()) {
            return;
        }

        $user_permissions = community_get_user_permissions($user['id']);

        if (count($user_permissions['permission_ids']) == 0) {
            return;
        }

        // the plugin Community is activated, the user has upload permissions, we
        // use a specific function to list available categories, assuming the user
        // wants to list categories where upload is possible for him

        $service->addMethod(
            'pwg.categories.getList',
            'wsCommunity::ws_categories_getList',
            array(
                'cat_id' => array('default' => 0),
                'recursive' => array('default' => false),
                'public' => array('default' => false),
                'tree_output' => array('default' => false),
                'fullname' => array('default' => false),
            ),
            'retrieves a list of categories'
        );

        $service->addMethod(
            'pwg.tags.getAdminList',
            'wsCommunit::ws_tags_getAdminList',
            array(),
            'administration method only'
        );
    }

    public static function sendResponse($encodedResponse)
    {
        global $community, $user, $conn;

        if (!isset($community['method'])) {
            return;
        }

        if ('pwg.images.addSimple' == $community['method']) {
            $response = json_decode($encodedResponse);
            $image_id = $response->result->image_id;
        } elseif ('pwg.images.add' == $community['method']) {
            $query = 'SELECT id FROM ' . IMAGES_TABLE;
            $query .= ' WHERE md5sum = \'' . $community['md5sum'] . '\'';
            $query .= ' ORDER BY id DESC LIMIT 1';
            list($image_id) = $conn->db_fetch_row($conn->db_query($query));
        } else {
            return;
        }

        $image_ids = array($image_id);

        // $category_id is set in the photos_add_direct_process.inc.php included script
        $category_infos = get_cat_info($community['category']);

        // should the photos be moderated?
        //
        // if one of the user community permissions is not moderated on the path
        // to gallery root, then the upload is not moderated. For example, if the
        // user is allowed to upload to events/parties with no admin moderation,
        // then he's not moderated when uploading in
        // events/parties/happyNewYear2011
        $moderate = true;

        $user_permissions = community_get_user_permissions($user['id']);
        $query = 'SELECT cp.category_id,c.uppercats FROM ' . COMMUNITY_PERMISSIONS_TABLE . ' AS cp';
        $query .= ' LEFT JOIN ' . CATEGORIES_TABLE . ' AS c ON category_id = c.id';
        $query .= ' WHERE cp.id ' . $conn->in($user_permissions['permission_ids']);
        $query .= ' AND cp.moderated = \'' . $conn->boolean_to_db(false) . '\'';
        $result = $conn->db_query($query);
        while ($row = $conn->db_fetch_assoc($result)) {
            if (empty($row['category_id'])) {
                $moderate = false;
            } elseif (preg_match('/^' . $row['uppercats'] . '(,|$)/', $category_infos['uppercats'])) {
                $moderate = false;
            }
        }

        if ($moderate) {
            $inserts = array();

            $query = 'SELECT id,date_available FROM ' . IMAGES_TABLE;
            $query .= ' WHERE id ' . $conn->in($image_ids);
            $result = $conn->db_query($query);
            while ($row = $conn->db_fetch_assoc($result)) {
                $inserts[] = array(
                    'image_id' => $row['id'],
                    'added_on' => $row['date_available'],
                    'state' => 'moderation_pending',
                );
            }

            $conn->mass_inserts(
                COMMUNITY_PENDINGS_TABLE,
                array_keys($inserts[0]),
                $inserts
            );

            // the level of a user upload photo with moderation is 16
            $level = 16;
        } else {
            // the level of a user upload photo with no moderation is 0
            $level = 0;
        }

        $query = 'UPDATE ' . IMAGES_TABLE;
        $query .= ' SET level = ' . $conn->db_real_escape_string($level);
        $query .= ' WHERE id ' . $conn->in($image_ids);
        $conn->db_query($query);

        invalidate_user_cache();
    }

    public static function ws_categories_getList($params, &$service)
    {
        global $user, $conf, $conn;

        if ($params['tree_output']) {
            if (!isset($_GET['format']) or !in_array($_GET['format'], array('php', 'json'))) {
                // the algorithm used to build a tree from a flat list of categories
                // keeps original array keys, which is not compatible with
                // PwgNamedArray.
                //
                // PwgNamedArray is useful to define which data is an attribute and
                // which is an element in the XML output. The "hierarchy" output is
                // only compatible with json/php output.

                return new PwgError(405, "The tree_output option is only compatible with json/php output formats");
            }
        }

        $where = array('1=1');
        $join_user = $user['id'];

        if (!$params['recursive']) {
            if ($params['cat_id'] > 0) {
                $where[] = '(id_uppercat=' . (int)($params['cat_id']) . ' OR id=' . (int)($params['cat_id']) . ')';
            } else {
                $where[] = 'id_uppercat IS NULL';
            }
        } elseif ($params['cat_id'] > 0) {
            $where[] = 'uppercats ' . DB_REGEX_OPERATOR . ' \'(^|,)' . (int)($params['cat_id']) . '(,|$)\'';
        }

        if ($params['public']) {
            $where[] = 'status = \'public\'';
            $where[] = 'visible = \'' . $conn->boolean_to_db(true) . '\'';

            $join_user = $conf['guest_id'];
        }

        $user_permissions = community_get_user_permissions($user['id']);
        $upload_categories = $user_permissions['upload_categories'];
        if (count($upload_categories) == 0) {
            $upload_categories = array(-1);
        }

        $where[] = 'id ' . $conn->in($upload_categories);

        $query = 'SELECT id,name,permalink,uppercats,global_rank,comment,nb_images,';
        $query .= 'count_images AS total_nb_images,date_last,max_date_last,count_categories AS nb_categories';
        $query .= ' FROM ' . CATEGORIES_TABLE;
        $query .= ' LEFT JOIN ' . USER_CACHE_CATEGORIES_TABLE . ' ON id=cat_id AND user_id=' . $join_user;
        $query .= ' WHERE ' . implode(' AND ', $where);

        $result = $conn->db_query($query);

        $cats = array();
        while ($row = $conn->db_fetch_assoc($result)) {
            $row['url'] = make_index_url(array('category' => $row));
            foreach (array('id', 'nb_images', 'total_nb_images', 'nb_categories') as $key) {
                $row[$key] = (int)$row[$key];
            }

            if ($params['fullname']) {
                $row['name'] = strip_tags(get_cat_display_name_cache($row['uppercats'], null, false));
            } else {
                $row['name'] = strip_tags(
                    trigger_change(
                        'render_category_name',
                        $row['name'],
                        'ws_categories_getList'
                    )
                );
            }

            $row['comment'] = strip_tags(
                trigger_change(
                    'render_category_description',
                    $row['comment'],
                    'ws_categories_getList'
                )
            );

            $cats[] = $row;
        }
        usort($cats, 'global_rank_compare');

        if ($params['tree_output']) {
            return categories_flatlist_to_tree($cats);
        } else {
            return array(
                'categories' => new \Phyxo\Ws\NamedArray(
                    $cats,
                    'category',
                    array(
                        'id',
                        'url',
                        'nb_images',
                        'total_nb_images',
                        'nb_categories',
                        'date_last',
                        'max_date_last',
                    )
                )
            );
        }
    }

    public static function ws_tags_getAdminList($params, &$service)
    {
        global $services, $conn;

        $tags = $services['tags']->getAvailableTags();

        // keep orphan tags
        $orphan_tags = $services['tags']->getOrphanTags();
        if (count($orphan_tags) > 0) {
            $orphan_tag_ids = array();
            foreach ($orphan_tags as $tag) {
                $orphan_tag_ids[] = $tag['id'];
            }

            $query = 'SELECT * FROM ' . TAGS_TABLE;
            $query .= ' WHERE id ' . $conn->in($orphan_tag_ids);
            $result = $conn->db_query($query);
            while ($row = $conn->db_fetch_assoc($result)) {
                $tags[] = $row;
            }
        }

        usort($tags, 'tag_alpha_compare');

        return array(
            'tags' => new \Phyxo\Ws\NamedArray(
                $tags,
                'tag',
                array(
                    'name',
                    'id',
                    'url_name',
                )
            )
        );
    }
}
