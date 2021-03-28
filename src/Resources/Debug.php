<?php
function debug($text, $dump = false, $line = 0)
{
    if ($_ENV['ENVIRONMENT'] == '#{ENVIRONMENT}#') {
        $fp = fopen($_ENV['PATH_ROOT'] . 'Logs/DEBUG', "a+");

        is_array($text) ? $dump = true : null;

        $backtrace = debug_backtrace();

        $text = $dump ? print_r($text, true) : $text;

        $url = '[http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . ']';

        $resume = '[File=' . $backtrace[$line + 1]['file'] . ']' . "\n" . '[line=' . $backtrace[$line + 1]['line'] . "\n" . '[File=' . $backtrace[$line]['file'] . ']' . "\n" . '[line=' . $backtrace[$line]['line'] . ']';

        fwrite($fp, '####### [' . date('d/m/Y') . ' ' . date('H:i:s') . '] ######' . "\n" . $resume . "\n" . $url . "\n" . $text . " \n\n");

        fclose($fp);
    }
}

function debugSql($text, $dump = false, $line = 2)
{
    if ($_ENV['ENVIRONMENT'] == 'local') {
        $fp = fopen($_ENV['PATH_ROOT'] . 'Logs/SQL', "a+");

        is_array($text) ? $dump = true : null;

        $backtrace = debug_backtrace();

        $text = $dump ? print_r($text, true) : $text;

        $url = '# [http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '] #';

        $resume = '# [File=' . $backtrace[$line]['file'] . '] #' . "\n" . '# [line=' . $backtrace[$line]['line'] . '] #' . "\n" . '# [index=' . $line . '] #';

        fwrite($fp, '####### [' . date('d/m/Y') . ' ' . date('H:i:s') . '] ######' . "\n" . $resume . "\n" . $url . "\n" . $text . " \n\n");

        fclose($fp);
    }
}