<?php
require_once 'vendor/autoload.php';

spl_autoload_register(
    function ($class) {
        $file = str_replace("\\", "/", $class . '.php');

        $path = isset($_ENV['PATH_ROOT']) ? $_ENV['PATH_ROOT'] : getEnv('DOCUMENT_ROOT') . "/";

        if (file_exists($path . $file)) {
            include_once($path . $file);
        }
    }
);