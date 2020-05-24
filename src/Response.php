<?php

namespace APIORM;

class Response
{
    static function show(int $code = 200, $response = null)
    {
        header("content-type: application/json; charset=utf-8");

        http_response_code($code);

        if ($response) {
            echo json_encode($response, JSON_NUMERIC_CHECK);
        }

        if (isset($_SESSION['link'])) {
            mysqli_close($_SESSION['link']);
            unset($_SESSION['link']);
        }

        exit;
    }

    static function download(int $code = 200, $response = null)
    {
        http_response_code($code);

        if ($response) {
            echo $response;
        }

        if (isset($_SESSION['link'])) {
            mysqli_close($_SESSION['link']);
            unset($_SESSION['link']);
        }

        exit;
    }
}