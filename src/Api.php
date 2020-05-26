<?php

namespace APIORM;

use APIORM\Enums\TypeResponseEnum;

class Api
{
    function __construct()
    {
        if (isset($_SESSION['link'])) {
            \mysqli_rollback($_SESSION['link']);
            \mysqli_close($_SESSION['link']);
            unset($_SESSION['link']);
        }

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
        //</editor-fold>

        //<editor-fold desc=" [ Resources ] ">
        include('Resources/Debug.php');
        //</editor-fold>

        $Route = new Route();
        $Route->Controller();

        Response::Show(TypeResponseEnum::NotImplemented);
    }
}