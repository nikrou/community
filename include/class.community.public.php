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

class CommunityPublic
{
    public static function section_init()
    {
        global $tokens, $page;

        if ($tokens[0] == 'add_photos') {
            $page['section'] = 'add_photos';
        }
    }

    public static function index()
    {
        global $page;

        if (isset($page['section']) and $page['section'] == 'add_photos') {
            include(COMMUNITY_PATH . 'add_photos.php');
        }
    }

    public static function gallery_menu($menu_ref_arr)
    {
        global $conf, $user;

        // conditional : depending on community permissions, display the "Add
        // photos" link in the gallery menu
        $user_permissions = $user['community_permissions'];

        if (!$user_permissions['community_enabled']) {
            return;
        }

        $menu = &$menu_ref_arr[0];

        if (($block = $menu->get_block('mbMenu')) != null) {
            load_language('plugin.lang', COMMUNITY_PATH);

            array_splice(
                $block->data,
                count($block->data),
                0,
                array(
                    '' => array(
                        'URL' => make_index_url(array('section' => 'add_photos')),
                        'TITLE' => l10n('Upload your own photos'),
                        'NAME' => l10n('Upload Photos')
                    )
                )
            );
        }
    }

    public static function community_set_prefilter_cat_modify()
    {
        global $template, $conf, $category, $conn;

        if (!isset($conf['community']['user_albums']) or !$conf['community']['user_albums']) {
            return;
        }

        $template->set_prefilter('album_properties', array(self, 'cat_modify_prefilter'));

        $query = 'SELECT ' . $conf['user_fields']['id'] . ' AS id,';
        $query .= $conf['user_fields']['username'] . ' AS username';
        $query .= ' FROM ' . USERS_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . USER_INFOS_TABLE . ' AS uf ON uf.user_id = u.' . $conf['user_fields']['id'];
        $query .= ' WHERE uf.status ' . $conn->in(array('normal', 'generic'));
        $result = $conn->db_query($query);
        $users = array();
        while ($row = $conn->db_fetch_assoc($result)) {
            $users[$row['id']] = $row['username'];
        }

        $template->assign(
            array(
                'community_user_options' => $users,
                'community_user_selected' => $category['community_user'],
            )
        );
    }

    private static function cat_modify_prefilter($content, &$smarty)
    {
        $search = "#<strong>{'Name'#";

        // We use the <tr> from the Creation date, and give them a new <tr>
        $replacement = '<strong>(Community) {\'Album of user\'|translate}</strong>
		<br>
			<select name="community_user">
				<option value="">--</option>
				{html_options options=$community_user_options selected=$community_user_selected}
			</select>
            <em>{\'a user can own only one album\'|translate}</em>
		 </p>
	     </p>
         <p>
		 <strong>{\'Name\'';

        return preg_replace($search, $replacement, $content);
    }

    public static function cat_modify_submit()
    {
        global $category, $conf, $conn;

        if (!isset($conf['community']['user_albums']) or !$conf['community']['user_albums']) {
            return;
        }

        if (isset($_POST['community_user'])) {
            // only one album for each user, first we remove ownership on any other album
            $conn->single_update(
                CATEGORIES_TABLE,
                array('community_user' => null),
                array('community_user' => $_POST['community_user'])
            );

            // then we give the album to the user
            $conn->single_update(
                CATEGORIES_TABLE,
                array('community_user' => $_POST['community_user']),
                array('id' => $category['id'])
            );
        }
    }
}
