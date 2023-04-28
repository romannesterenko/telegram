<?php


namespace Helpers;


class StringHelper
{
    public static function checkPhone($_val)
    {
        if (empty($_val)) {
            return false;
        }

        if (!preg_match('/^\+?\d{10,15}$/', $_val)) {
            return false;
        }

        if (mb_substr($_val, 0, 1) == '+' && mb_strlen($_val) < 11 && mb_strlen($_val) > 13) {
            return false;
        }
        return true;
    }

    public static function genMessageFromTemplate($template, $variables=[]){
        if(!is_array($variables)&&count($variables)==0)
            return $template;
        foreach ($variables as $variable=>$replace){
            $template = str_replace('#'.$variable."#", $replace, $template);
        }
        return $template;
    }

    public static function formatSum($sum)
    {
        return number_format($sum, 0, '', ' ');
    }
}