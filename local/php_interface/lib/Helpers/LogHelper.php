<?php

namespace Helpers;

use Api\Sender;

class LogHelper{
    public static function write($expr, $tracking = false, $to_tg = false)
    {

        $log = $_SERVER["DOCUMENT_ROOT"].'/file.txt';
        $string = 'Дата записи: '.date('d.m.Y H:i:s').PHP_EOL.PHP_EOL;
        if($to_tg){
            Sender::sendCommonMessage('+380991809511', $string.print_r($expr, 1));
        }else{
            file_put_contents($log, $string.print_r($expr, 1)."\n", FILE_APPEND);
        }

    }

    private static function prepareString($expr)
    {
        if(is_array($expr)){
            return print_r($expr, TRUE);
        }
        if(is_object($expr)){
            return print_r($expr, TRUE);
        }
        return (string)$expr;
    }
}
