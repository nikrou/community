<?php
// +-----------------------------------------------------------------------+
// | Community - a plugin for Phyxo                                        |
// | Copyright(C) 2015 Nicolas Roudaire             http://www.nikrou.net  |
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

class CommunityAdmin
{
    public static function admin_menu($menu) {
        global $page, $conn;

        $query = 'SELECT COUNT(1) FROM '.COMMUNITY_PENDINGS_TABLE;
        $query .= ' LEFT JOIN '.IMAGES_TABLE.' ON image_id = id';
        $query .= ' WHERE state = \'moderation_pending\'';
        $result = $conn->db_query($query);
        list($page['community_nb_pendings']) = $conn->db_fetch_row($result);

        $name = 'Community';
        if ($page['community_nb_pendings'] > 0) {
            $style = 'background-color:#666;';
            $style.= 'color:white;';
            $style.= 'padding:1px 5px;';
            $style.= 'border-radius:10px;';
            $style.= 'margin-left:5px;';

            $name.= '<span style="'.$style.'">'.$page['community_nb_pendings'].'</span>';

            if (defined('IN_ADMIN') and IN_ADMIN and $page['page'] == 'intro') {
                global $template;

                $template->set_prefilter('intro', array(self, 'pendings_on_intro'));
                $template->assign(
                    array(
                        'COMMUNITY_PENDINGS' => sprintf(
                            '<a href="%s">'.l10n('%u pending photos').'</a>',
                            get_root_url().'admin.php?page=plugin-community-pendings',
                            $page['community_nb_pendings']
                        ),
                    )
                );
            }
        }

        $menu[] = array(
            'NAME' => $name,
            'URL'  => get_root_url().'admin.php?page=plugin-community'
        );

        return $menu;
    }

    private static function pendings_on_intro($content, &$smarty) {
        $pattern = '#<li>\s*{\$DB_ELEMENTS\}#ms';
        $replacement = '<li>{$COMMUNITY_PENDINGS}</li><li>{$DB_ELEMENTS}';
        return preg_replace($pattern, $replacement, $content);
    }

    public static function delete_user($user_id) {
        global $conn;

        $query = 'DELETE FROM '.COMMUNITY_PERMISSIONS_TABLE.' WHERE user_id = '.$conn->db_real_escape_string($user_id);
        $conn->db_query($query);

        community_reject_user_pendings($user_id);
    }

    public static function delete_category($category_ids) {
        global $conn;

        // $category_ids includes all the sub-category ids
        $query = 'DELETE FROM '.COMMUNITY_PERMISSIONS_TABLE;
        $query .= ' WHERE category_id '.$conn->in($category_ids);
        $conn->db_query($query);

        community_update_cache_key();
    }

    public static function delete_elements($image_ids) {
        global $conn;

        $query = 'DELETE FROM '.COMMUNITY_PENDINGS_TABLE;
        $query .= ' WHERE image_id '.$conn->in($image_ids);
        $conn->db_query($query);
    }

    public static function refresh_cache_update_time() {
        community_update_cache_key();
    }
}