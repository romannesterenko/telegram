<?php
$_SERVER["DOCUMENT_ROOT"] = "/home/bitrix/www";
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
$session_file = \Api\Sender::getMadelineFile();
$appInfo = new danog\MadelineProto\Settings\AppInfo();
$appInfo->setApiId(1813800);
$appInfo->setApiHash("4cd0ff0b655f5773abd1556306683ca7");
$settings = new danog\MadelineProto\Settings;
$settings->getLogger()->setLevel(danog\MadelineProto\Logger::LEVEL_ULTRA_VERBOSE);
$settings->getLogger()->setExtra($_SERVER["DOCUMENT_ROOT"]."/local/php_interface/madeline/MadelineProto.log");
$settings->setAppInfo($appInfo);
class MyEventHandler extends danog\MadelineProto\EventHandler {

}
MyEventHandler::startAndLoop($session_file, $settings);