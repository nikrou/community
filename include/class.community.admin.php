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

class CommunityAdmin
{
    public static function admin_menu($menu)
    {
        global $page, $conn;

        $query = 'SELECT COUNT(1) FROM ' . COMMUNITY_PENDINGS_TABLE;
        $query .= ' LEFT JOIN ' . IMAGES_TABLE . ' ON image_id = id';
        $query .= ' WHERE state = \'moderation_pending\'';
        $result = $conn->db_query($query);
        list($page['community_nb_pendings']) = $conn->db_fetch_row($result);

        $name = 'Community';
        if ($page['community_nb_pendings'] > 0) {
            $style = 'background-color:#666;';
            $style .= 'color:white;';
            $style .= 'padding:1px 5px;';
            $style .= 'border-radius:10px;';
            $style .= 'margin-left:5px;';

            $name .= '<span style="' . $style . '">' . $page['community_nb_pendings'] . '</span>';

            if (defined('IN_ADMIN') and IN_ADMIN and $page['page'] == 'intro') {
                global $template;

                $template->set_prefilter('intro', array(self, 'pendings_on_intro'));
                $template->assign(
                    array(
                        'COMMUNITY_PENDINGS' => sprintf(
                            '<a href="%s">' . l10n('%u pending photos') . '</a>',
                            get_root_url() . 'admin.php?page=plugin-community-pendings',
                            $page['community_nb_pendings']
                        ),
                    )
                );
            }
        }

        $menu[] = array(
            'NAME' => $name,
            'URL' => get_root_url() . 'admin/index.php?page=plugin-community'
        );

        return $menu;
    }

    private static function pendings_on_intro($content, &$smarty)
    {
        $pattern = '#<li>\s*{\$DB_ELEMENTS\}#ms';
        $replacement = '<li>{$COMMUNITY_PENDINGS}</li><li>{$DB_ELEMENTS}';
        return preg_replace($pattern, $replacement, $content);
    }

    public static function delete_user($user_id)
    {
        global $conn;

        $query = 'DELETE FROM ' . COMMUNITY_PERMISSIONS_TABLE . ' WHERE user_id = ' . $conn->db_real_escape_string($user_id);
        $conn->db_query($query);

        community_reject_user_pendings($user_id);
    }

    public static function delete_category($category_ids)
    {
        global $conn;

        // $category_ids includes all the sub-category ids
        $query = 'DELETE FROM ' . COMMUNITY_PERMISSIONS_TABLE;
        $query .= ' WHERE category_id ' . $conn->in($category_ids);
        $conn->db_query($query);

        community_update_cache_key();
    }

    public static function delete_elements($image_ids)
    {
        global $conn;

        $query = 'DELETE FROM ' . COMMUNITY_PENDINGS_TABLE;
        $query .= ' WHERE image_id ' . $conn->in($image_ids);
        $conn->db_query($query);
    }

    public static function refresh_cache_update_time()
    {
        community_update_cache_key();
    }
}
