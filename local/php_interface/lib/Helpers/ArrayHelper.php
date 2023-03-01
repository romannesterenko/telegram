<?php

namespace Helpers;

class ArrayHelper{
    public static function checkFullArray($array):bool
    {
        return is_array($array)&&count($array)>0;
    }
}
