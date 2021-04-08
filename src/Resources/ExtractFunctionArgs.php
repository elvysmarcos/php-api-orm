<?php


namespace APIORM\Resources;


use APIORM\ApiCustomException;

class ExtractFunctionArgs
{
    public static function Get()
    {
        $bt = debug_backtrace();
        $file = file($bt[1]['file']);
        $src = self::Aux($bt[1]['function'], $file, ($bt[1]['line'] - 1));
        $part = "#(.*){$bt[1]['function']}*?\\( *?(.*) *?\\);#i";
        $var = preg_replace($part, '$2', $src);
        return $var;
    }

    private static function Aux($part, $file, $line)
    {
        $rowsUp = self::Up($part, $file, $line);
        $rowsDow = self::Down($file, $line + 1, $rowsUp);
        return str_replace([' ', "\r\n", "\n"], null, $rowsDow);
    }

    private static function Up($part, $file, $line, $src = null)
    {
        if ($line < 0 or !isset($file[$line])) {
            new ApiCustomException('Invalid class content');
        }

        $row = $file[$line];

        if (preg_match("/{$part}\(/i", $row)) {
            return ($row . $src);
        } else {
            return self::Up($part, $file, $line - 1, ($row . $src));
        }
    }

    private static function Down($file, $line, $src)
    {
        if ($line < 0 or !isset($file[$line])) {
            new ApiCustomException('Invalid class content');
        }

        $row = $file[$line];

        if (preg_match('/\);/i', $src)) {
            return $src;
        } else {
            return self::Down($file, $line + 1, $src . $row);
        }
    }
}