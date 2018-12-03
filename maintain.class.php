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

class community_maintain extends \Phyxo\Plugin\PluginMaintain
{
    private $installed = false;

    public function __construct($plugin_id)
    {
        parent::__construct($plugin_id);
    }

    public function install($plugin_version, &$errors = array())
    {
        global $conf, $prefixeTable;

        include_once(PHPWG_ROOT_PATH . 'admin/include/functions_install.inc.php');
        execute_sqlfile(
            dirname(__FILE__) . '/sql/community-structure-' . $conf['dblayer'] . '.sql',
            'phyxo_', // DEFAULT_PREFIX_TABLE is not easily available from here
            $prefixeTable,
            $conf['dblayer']
        );

        // dblayer specific installation
        $dblayer_install = 'install_' . $conf['dblayer'];
        if (method_exists($this, $dblayer_install)) {
            $this->$dblayer_install();
        }

        if (!isset($conf['community'])) {
            $community_default_config = array('user_albums' => false);

            conf_update_param('community', $community_default_config, true);
        }

        $this->installed = true;
    }

    // protected to avoid external calls
    protected function install_mysql()
    {
        global $prefixeTable, $conn;

        $query = 'ALTER TABLE `' . $prefixeTable . 'categories` ADD `community_user` mediumint unsigned DEFAULT NULL;';
        $conn->db_query($query);

        $current_enums = $conn->get_enums($prefixeTable . 'history', 'section');
        $current_enums[] = 'add_photos';

        $query = 'ALTER TABLE ' . $prefixeTable . 'history CHANGE section';
        $query .= ' section ENUM(\'' . implode("','", $current_enums) . '\') DEFAULT NULL';
        $conn->db_query($query);
    }

    protected function install_mysqli()
    {
        $this->install_mysql();
    }

    protected function install_pgsql()
    {
        global $conn, $prefixeTable;

        $query = 'ALTER TABLE "' . $prefixeTable . 'categories" ADD "community_user" INTEGER DEFAULT NULL;';
        $conn->db_query($query);

        $query = 'ALTER TYPE history_section ADD VALUE IF NOT EXISTS \'add_photos\'';
        $conn->db_query($query);
    }

    protected function install_sqlite()
    {
        global $conn, $prefixeTable;

        $query = 'ALTER TABLE "' . $prefixeTable . 'categories" ADD "community_user" INTEGER DEFAULT NULL;';
        $conn->db_query($query);
    }

    public function activate($plugin_version, &$errors = array())
    {
        global $prefixeTable, $conn;

        if (!$this->installed) {
            $this->install($plugin_version, $errors);
        }

        $query = 'SELECT COUNT(1) FROM ' . $prefixeTable . 'community_permissions';
        list($counter) = $conn->db_fetch_row($conn->db_query($query));
        if (0 == $counter) {
            // is there a "Community" album?
            $query = 'SELECT id FROM ' . CATEGORIES_TABLE . ' WHERE name = \'Community\'';
            $result = $conn->db_query($query);
            while ($row = $conn->db_fetch_assoc($result)) {
                $category_id = $row['id'];
                break;
            }

            if (!isset($category_id)) {
                // create an album "Community"
                include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
                $category_info = create_virtual_category('Community');
                $category_id = $category_info['id'];
            }

            $conn->single_insert(
                $prefixeTable . 'community_permissions',
                array(
                    'type' => 'any_registered_user',
                    'category_id' => $category_id,
                    'recursive' => 'true',
                    'create_subcategories' => 'true',
                    'moderated' => 'true',
                )
            );
        }

        include_once(dirname(__FILE__) . '/include/functions_community.inc.php');
        community_update_cache_key();
    }

    public function update($old_version, $new_version, &$errors = array())
    {
        \App\Log::getInstance()->debug("try to update from $old_version to $new_version");
        $this->install($new_version, $errors);
    }

    public function deactivate()
    {
    }

    public function uninstall()
    {
        global $prefixeTable, $conn, $conf;

        $query = 'DROP TABLE ' . $prefixeTable . 'community_permissions;';
        $conn->db_query($query);

        $query = 'DROP TABLE ' . $prefixeTable . 'community_pendings;';
        $conn->db_query($query);

        $query = 'ALTER TABLE ' . $prefixeTable . 'categories drop column community_user;';
        $conn->db_query($query);

        // delete configuration
        conf_delete_param(array('community', 'community_cache_key'));
    }
}
