<?php

namespace APIORM;

use APIORM\Enums\ResponseTypeEnum;
use Exception;
use Throwable;

class ApiCustomException extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        if (isset($_SESSION['link'])) {
            $database = new Database();
            $database->CloseConnection(true);
        }

        Response::Show(ResponseTypeEnum::BadRequest, $message);
    }
}