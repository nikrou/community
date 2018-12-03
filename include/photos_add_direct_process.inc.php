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

if (isset($_GET['processed'])) {
    // sometimes, you have submitted the form but you have nothing in $_POST
    // and $_FILES. This may happen when you have an HTML upload and you
    // exceeded the post_max_size (but not the upload_max_size)
    if (!isset($_POST['submit_upload'])) {
        $page['errors'][] = l10n(
            'The uploaded files exceed the post_max_size directive in php.ini: %sB',
            ini_get('post_max_size')
        );
    } else {
        $category_id = $_POST['category'];
    }

    if (isset($_POST['onUploadError']) and is_array($_POST['onUploadError']) and count($_POST['onUploadError']) > 0) {
        foreach ($_POST['onUploadError'] as $error) {
            $page['errors'][] = $error;
        }
    }

    $image_ids = array();

    if (isset($_FILES) and !empty($_FILES['image_upload'])) {
        $starttime = get_moment();

        foreach ($_FILES['image_upload']['error'] as $idx => $error) {
            if (UPLOAD_ERR_OK == $error) {
                $images_to_add = array();

                $extension = pathinfo($_FILES['image_upload']['name'][$idx], PATHINFO_EXTENSION);
                if ('zip' == strtolower($extension)) {
                    $upload_dir = $conf['upload_dir'] . '/buffer';
                    prepare_directory($upload_dir);

                    $temporary_archive_name = date('YmdHis') . '-' . generate_key(10);
                    $archive_path = $upload_dir . '/' . $temporary_archive_name . '.zip';

                    move_uploaded_file(
                        $_FILES['image_upload']['tmp_name'][$idx],
                        $archive_path
                    );

                    define('PCLZIP_TEMPORARY_DIR', $upload_dir . '/');
                    $zip = new PclZip($archive_path);
                    if ($list = $zip->listContent()) {
                        $indexes_to_extract = array();

                        foreach ($list as $node) {
                            if (1 == $node['folder']) {
                                continue;
                            }

                            if (is_valid_image_extension(pathinfo($node['filename'], PATHINFO_EXTENSION))) {
                                $indexes_to_extract[] = $node['index'];

                                $images_to_add[] = array(
                                    'source_filepath' => $upload_dir . '/' . $temporary_archive_name . '/' . $node['filename'],
                                    'original_filename' => basename($node['filename']),
                                );
                            }
                        }

                        if (count($indexes_to_extract) > 0) {
                            $zip->extract(
                                PCLZIP_OPT_PATH,
                                $upload_dir . '/' . $temporary_archive_name,
                                PCLZIP_OPT_BY_INDEX,
                                $indexes_to_extract,
                                PCLZIP_OPT_ADD_TEMP_FILE_ON
                            );
                        }
                    }
                } elseif (is_valid_image_extension($extension)) {
                    $images_to_add[] = array(
                        'source_filepath' => $_FILES['image_upload']['tmp_name'][$idx],
                        'original_filename' => $_FILES['image_upload']['name'][$idx],
                    );
                }

                foreach ($images_to_add as $image_to_add) {
                    $image_id = add_uploaded_file(
                        $image_to_add['source_filepath'],
                        $image_to_add['original_filename'],
                        array($category_id),
                        $_POST['level']
                    );

                    $image_ids[] = $image_id;

                    // TODO: if $image_id is not an integer, something went wrong
                }
            } else {
                $error_message = file_upload_error_message($error);

                $page['errors'][] = l10n(
                    'Error on file "%s" : %s',
                    $_FILES['image_upload']['name'][$idx],
                    $error_message
                );
            }
        }

        $endtime = get_moment();
        $elapsed = ($endtime - $starttime) * 1000;
    } // if (!empty($_FILES))

    if (isset($_POST['upload_id'])) {
        // we're on a multiple upload, with uploadify and so on
        if (isset($_SESSION['uploads_error'][$_POST['upload_id']])) {
            foreach ($_SESSION['uploads_error'][$_POST['upload_id']] as $error) {
                $page['errors'][] = $error;
            }
        }

        if (isset($_SESSION['uploads'][$_POST['upload_id']])) {
            $image_ids = $_SESSION['uploads'][$_POST['upload_id']];
        }
    }

    $page['thumbnails'] = array();
    foreach ($image_ids as $image_id) {
        // we could return the list of properties from the add_uploaded_file
        // function, but I like the "double check". And it costs nothing
        // compared to the upload process.
        $thumbnail = array();

        $query = 'SELECT id,file,path FROM ' . IMAGES_TABLE;
        $query .= ' WHERE id = ' . $conn->db_real_escape_string($image_id);
        $image_infos = $conn->db_fetch_assoc($conn->db_query($query));

        $thumbnail['file'] = $image_infos['file'];

        $thumbnail['src'] = DerivativeImage::thumb_url($image_infos);

        // TODO: when implementing this plugin in core, we should have
        // a function get_image_name($name, $file) (if name is null, then
        // compute a temporary name from filename) that would be also used in
        // picture.php. UPDATE: in fact, "get_name_from_file($file)" already
        // exists and is used twice (batch_manager_unit + comments, but not in
        // picture.php I don't know why) with the same pattern if
        // (empty($name)) {$name = get_name_from_file($file)}, a clean
        // function get_image_name($name, $file) would be better
        $thumbnail['title'] = get_name_from_file($image_infos['file']);

        $thumbnail['link'] = get_root_url() . 'admin.php?page=photo-' . $image_id . '&amp;cat_id=' . $category_id;

        $page['thumbnails'][] = $thumbnail;
    }

    if (!empty($page['thumbnails'])) {
        $page['infos'][] = l10n('%d photos uploaded', count($page['thumbnails']));

        if (0 != $_POST['level']) {
            $page['infos'][] = l10n(
                'Privacy level set to "%s"',
                l10n(sprintf('Level %d', $_POST['level']))
            );
        }

        $query = 'SELECT COUNT(1) FROM ' . IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id = ' . $conn->db_real_escape_string($category_id);
        list($count) = $conn->db_fetch_row($conn->db_query($query));
        $category_name = get_cat_display_name_from_id($category_id, 'admin.php?page=album-');

        // information
        $page['infos'][] = l10n(
            'Album "%s" now contains %d photos',
            '<em>' . $category_name . '</em>',
            $count
        );

        $page['batch_link'] = PHOTOS_ADD_BASE_URL . '&batch=' . implode(',', $image_ids);
    }
}
