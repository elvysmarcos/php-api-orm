<?php
/**
 * Created by PhpStorm.
 * User: elvys.marcos
 * Date: 23/11/2018
 * Time: 18:51
 */

namespace Core\Resources;

class Content
{
    static function GET($index, $default = null)
    {
        if (isset($_GET[$index])) {
            $content = $_GET[$index];

            if ($content === "true") {
                $content = true;
            } else if ($content === "false") {
                $content = false;
            } else if ($content === 'null') {
                $content = null;
            }

            return $content;
        }

        return $default;
    }

    static function Fix($value)
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        if ($int !== false && $int !== null) {
            return $int;
        }

        if ($value === 'true' || $value === 'TRUE' || $value === 'false' || $value === 'FALSE') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $numDouble = (double)$value;

        if (gettype($numDouble) === 'double' && strlen($numDouble) === strlen($value)) {
            return $numDouble;
        }

        $extractDate = substr($value, 0, 10);

        if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $extractDate)) {
            return date('Y-m-d H:i:s', strtotime($value));
        }

        return $value;
    }
}