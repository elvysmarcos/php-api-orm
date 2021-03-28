<?php

namespace APIORM;

use APIORM\Enums\ResponseTypeEnum;

class Response
{
    static function Show(int $code = ResponseTypeEnum::Success, $response = null)
    {
        header("content-type: application/json; charset=utf-8");

        http_response_code($code);

        if ($response) {
            echo json_encode($response, JSON_NUMERIC_CHECK);
        }

        self::closeConnection($code);

        exit;
    }

    static function Download(int $code = ResponseTypeEnum::Success, $response = null)
    {
        http_response_code($code);

        if ($response) {
            echo $response;
        }

        self::closeConnection($code);

        exit;
    }

    private static function closeConnection(int $code)
    {
        $database = new Database();

        $rollback = false;
        if (in_array($code, [ResponseTypeEnum::Exception, ResponseTypeEnum::BadRequest])) {
            $rollback = true;
        }
        $database->CloseConnection($rollback);
    }
}