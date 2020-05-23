<?php
/**
 * Created by PhpStorm.
 * User: elvys.marcos
 * Date: 07/10/2018
 * Time: 13:58
 */

namespace Core;

use Core\Enums\TypeResponseEnum;
use Exception;
use Throwable;

class ApiCustomException extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        if (isset($_SESSION['link'])) {
            mysqli_rollback($_SESSION['link']);
        }

        Response::show(TypeResponseEnum::BadRequest, $message);
    }
}