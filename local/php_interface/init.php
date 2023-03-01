<?php
//подключение собственных классов
require_once ($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/composer/vendor/autoload.php");
require_once ($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/lib/autoload.php");
function dd($arr){
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}