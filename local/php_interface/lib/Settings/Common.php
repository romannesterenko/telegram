<?php
namespace Settings;
use Bitrix\Main\Config\Option;
class Common
{
    public static function getDeniedMessage()
    {
        return self::get('telegram_bot_denie_message');
    }
    public static function getManagerHelloMessage()
    {
        return self::get('telegram_bot_manager_hello_message');
    }

    private static function get($code){
        return Option::get( "common.settings", $code)??'';
    }

    public static function getRoleDeniedMessage()
    {
        return self::get('telegram_bot_denie_rights_message');
    }

    public static function getButtonText($button_code)
    {
        return self::get('button_text_'.$button_code);
    }

    public static function getWrongCallBackData()
    {
        return self::get('telegram_bot_wrong_callback');
    }

    public static function getWrongAppActionText()
    {
        return self::get('telegram_bot_wrong_action_for_app');
    }

    public static function getTimeForCrewWait(): int
    {
        return (int)self::get('time_for_crew_wait');
    }

    private static function set($code, $value):bool{
        Option::set( "common.settings", $code, $value);
        return self::get($code)==$value;
    }

    public static function getTGToken(){
        return self::get('telegram_bot_token');
    }
}