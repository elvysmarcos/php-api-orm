<?php

namespace APIORM;

use APIORM\Enums\TypeResponseEnum;

class Api
{
    function __construct()
    {
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

        //<editor-fold desc=" [Load Env] ">
        $_ENV = [];

        if (file_exists('.env')) {
            $_ENV = parse_ini_file('.env');

            if (defined('ENVIRONMENT') && ENVIRONMENT === '#{environment}') {
                $_ENV = array_merge($_ENV, parse_ini_file('.env.local'));
            } else if (defined('ENVIRONMENT') && file_exists('.env.' + ENVIRONMENT)) {
                $_ENV = array_merge($_ENV, parse_ini_file('.env.' + ENVIRONMENT));
            }
        }
        //</editor-fold>

        //<editor-fold desc=" [Clean last connection] ">
        $database = new Database();
        $database->CloseConnection(true);
        //</editor-fold>

        //<editor-fold desc=" [APP Settings] ">
        date_default_timezone_set($_ENV['TIME_ZONE']);

        $_ENV['TIME'] = time();

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';

        $_ENV['HOST_APP'] = "{$protocol}://{$_SERVER['HTTP_HOST']}/";
        $_ENV['HOST'] = "{$_ENV['HOST_APP']}.{$_ENV['APP_PATH']}";
        $_ENV['PATH_ROOT'] = getEnv('DOCUMENT_ROOT') . "/{$_ENV['APP_PATH']}";
        //</editor-fold>

        //<editor-fold desc=" [ Resources ] ">
        include('Resources/Debug.php');
        //</editor-fold>

        $Route = new Route();
        $Route->Controller();

        Response::Show(TypeResponseEnum::NotImplemented);
    }
}