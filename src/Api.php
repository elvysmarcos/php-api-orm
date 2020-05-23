<?php
/**
 * Created by PhpStorm.
 * User: elvys.marcos
 * Date: 19/08/2018
 * Time: 17:54
 */

namespace Core;

use Core\Enums\TypeResponseEnum;

//<editor-fold desc=" [Headers] ">
if (isset($_SERVER['HTTP_ORIGIN'])) {

    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {

        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    }

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }

    exit(0);
}

if (in_array($_SERVER['REQUEST_METHOD'], array('POST', 'PUT')) && empty($_POST)) {
    $_POST = json_decode(file_get_contents('php://input'), true);
}
//</editor-fold>

//<editor-fold desc=" [APP Settings] ">
define('TIME', time());

define('IP', $_SERVER['REMOTE_ADDR']);

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
    define('PROTOCOL', 'https');
} else {
    define('PROTOCOL', 'http');
}

define('HOST_APP', PROTOCOL . '://' . $_SERVER['HTTP_HOST'] . '/');
define('HOST', HOST_APP . APP_PATH);
define('PATH_ROOT', getEnv('DOCUMENT_ROOT') . '/' . APP_PATH);
define('METHOD', $_SERVER['REQUEST_METHOD']);

isset($_GET['path']) ? define('PATH', $_GET['path']) : define('PATH', NULL);
isset($_GET['file']) ? define('FILE', $_GET['file']) : define('FILE', NULL);

spl_autoload_register(
    function ($class) {
        $file = str_replace("\\", "/", $class . '.php');

        if (file_exists(PATH_ROOT . $file)) {
            include_once(PATH_ROOT . $file);
        }
    }
);
//</editor-fold>

//<editor-fold desc=" [ Resources ] ">
include('Core/Resources/Debug.php');
//</editor-fold>

$Route = new Route();
$Route->Controller();

Response::show(TypeResponseEnum::NotImplemented);