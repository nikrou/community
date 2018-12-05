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

define('PHPWG_ROOT_PATH','../../../');
define('IN_ADMIN', true);

$_COOKIE['pwg_id'] = $_POST['session_id'];

include_once(PHPWG_ROOT_PATH.'include/common.inc.php');
include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'admin/include/functions_upload.inc.php');

check_pwg_token();

if ($_FILES['Filedata']['error'] !== UPLOAD_ERR_OK) {
    $error_message = file_upload_error_message($_FILES['Filedata']['error']);

    add_upload_error(
        $_POST['upload_id'],
        sprintf(
            l10n('Error on file "%s" : %s'),
            $_FILES['Filedata']['name'],
            $error_message
        )
    );

    echo "File Size Error";
    exit();
}

ob_start();

// @TODO: improve
// ugly patch to have correct url for i.php
add_event_handler('get_derivative_url', 'community_get_derivative_url');
function community_get_derivative_url($url) {
    return str_replace('plugins/community/', '', $url);
}

$image_id = add_uploaded_file(
    $_FILES['Filedata']['tmp_name'],
    $_FILES['Filedata']['name'],
    array($_POST['category_id']),
    isset($_POST['level'])?(int) $_POST['level']:16
);

$_SESSION['uploads'][ $_POST['upload_id'] ][] = $image_id;

$query = 'SELECT id,path FROM '.IMAGES_TABLE.' WHERE id = '.$conn->db_real_escape_string($image_id);
$image_infos = $conn->db_fetch_assoc($conn->db_query($query));

$thumbnail_url = preg_replace('#^'.PHPWG_ROOT_PATH.'#', './', DerivativeImage::thumb_url($image_infos));

$return = array(
    'image_id' => $image_id,
    'category_id' => $_POST['category_id'],
    'thumbnail_url' => $thumbnail_url,
);

$output = ob_get_contents();
ob_end_clean();
if (!empty($output)) {
    add_upload_error($_POST['upload_id'], $output);
    $return['error_message'] = $output;
}

echo json_encode($return);
