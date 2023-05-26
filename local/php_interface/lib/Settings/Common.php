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

    public static function get($code){
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

    public static function getWrongTextData()
    {
        return self::get('telegram_bot_wrong_command');
    }

    public static function getWrongAppActionText()
    {
        return self::get('telegram_bot_wrong_action_for_app');
    }

    public static function getTimeForCrewWait(): int
    {
        return (int)self::get('time_for_crew_wait');
    }

    public static function getHelloCommonMessage()
    {
        return self::get('telegram_bot_common_hello_message');
    }

    public static function getTimeForRespWait()
    {
        return (int)self::get('time_for_responsible_wait');
    }

    public static function set($code, $value):bool{
        Option::set( "common.settings", $code, $value);
        return self::get($code)==$value;
    }

    public static function getTGToken(){
        return self::get('telegram_bot_token');
    }

    public static function DuringAppByResponsible()
    {
        return (int)self::get('during_app_by_responsible');
    }

    public static function SetDuringAppByResponsible($app_id)
    {
        self::set('during_app_by_responsible', $app_id);
    }

    public static function ResetDuringAppByResponsible()
    {
        self::set('during_app_by_responsible', 0);
    }

    public static function DuringAppByCollResponsible()
    {
        return (int)self::get('during_app_by_coll_responsible');
    }

    public static function SetDuringAppByCollResponsible($app_id)
    {
        self::set('during_app_by_coll_responsible', $app_id);
    }

    public static function ResetDuringAppByCollResponsible()
    {
        self::set('during_app_by_coll_responsible', 0);
    }

    public static function DuringCreateAppByResponsible()
    {
        return (int)self::get('during_create_app_by_responsible');
    }

    public static function SetDuringCreateAppByResponsible($app_id)
    {
        self::set('during_create_app_by_responsible', $app_id);
    }

    public static function ResetDuringCreateAppByResponsible()
    {
        self::set('during_create_app_by_responsible', 0);
    }

    public static function setCreatingOperationProcess($id, $app_id)
    {
        self::set('during_create_operation_by_'.$id, $app_id);
    }

    public static function resetCreatingOperationProcess($id)
    {
        self::set('during_create_operation_by_'.$id, 0);
    }

    public static function getCreatingOperationProcess($id)
    {
        return (int)self::get('during_create_operation_by_'.$id);
    }


    /*Менеджер создание заявки*/

    public static function setCreateAppManagerSession($manager_id, $app_id)
    {
        self::set('manager_create_app_'.$manager_id, $app_id);
    }

    public static function resetCreateAppManagerSession($manager_id)
    {
        self::set('manager_create_app_'.$manager_id, 0);
    }

    public static function getCreateAppManagerSession($manager_id):int
    {
        return (int)self::get('manager_create_app_'.$manager_id);
    }
    /*Менеджер создание заявки*/

    public static function setSearchManagerSession($manager_id)
    {
        self::set('manager_search_'.$manager_id, 'Y');
    }

    public static function resetSearchManagerSession($manager_id)
    {
        self::set('manager_search_'.$manager_id, 'N');
    }

    public static function isSearchManagerSession($manager_id):bool
    {
        return self::get('manager_search_'.$manager_id)=='Y';
    }

    public static function getSendMediaGroupSession($manager_id)
    {
        return self::get('manager_send_media_group_'.$manager_id);
    }

    public static function setSendMediaGroupSession($manager_id, $send_media_group_id)
    {
        return self::set('manager_send_media_group_'.$manager_id, $send_media_group_id);
    }

    public static function resetSendMediaGroupSession($manager_id)
    {
        return self::set('manager_send_media_group_'.$manager_id, 0);
    }

    public static function setNotGaveMoneySession($collector_id, $app_id){
        return self::set('not_gave_money_session_'.$collector_id, $app_id);
    }

    public static function getNotGaveMoneySession($collector_id){
        return (int)self::get('not_gave_money_session_'.$collector_id);
    }

    public static function resetNotGaveMoneySession($collector_id){
        return self::set('not_gave_money_session_'.$collector_id, 0);
    }

    public static function setNotReceiveMoneySession($collector_id, $app_id){
        return self::set('not_receive_money_session_'.$collector_id, $app_id);
    }

    public static function getNotReceiveMoneySession($collector_id){
        return (int)self::get('not_receive_money_session_'.$collector_id);
    }

    public static function resetNotReceiveMoneySession($collector_id){
        return self::set('not_receive_money_session_'.$collector_id, 0);
    }

    public static function isEnabledTimeForApps()
    {
        return self::get('is_enable_time_for_apps')=='Y';
    }

    public static function isAllowToCreateApps():bool
    {
        if(!self::isEnabledTimeForApps())
            return true;
        if(time()>strtotime(date('d.m.Y '.self::getTimeForApps().':00')))
            return false;

        return true;
    }

    public static function getTimeForApps(): string
    {
        $time =  self::get('time_for_apps');
        $return_time = '13:00';
        $array_time = explode(':', $time);
        if((int)$array_time[0]>=0&&(int)$array_time[0]<=24){
            if((int)$array_time[1]>=0&&(int)$array_time[1]<=59){
                $return_time = $time;
            }
        }
        return $return_time;
    }

    public static function getCREReceiveMoneySession():int
    {
        return (int)self::get('cre_receive_money_session');
    }

    public static function setCREReceiveMoneySession($param)
    {
        self::set('cre_receive_money_session', $param);
    }

    public static function resetCREReceiveMoneySession()
    {
        self::set('cre_receive_money_session', 0);
    }

    public static function GetAllowCashRoomEmployee()
    {
        return self::get('allow_cash_rooms_login')??'';
    }

    public static function setCREGiveMoneySession($app_id)
    {
        self::set('cre_give_money_session', $app_id);
    }

    public static function resetCREGiveMoneySession()
    {
        self::set('cre_give_money_session', 0);
    }

    public static function getCREGiveMoneySession()
    {
        return (int)self::get('cre_give_money_session');
    }

    public static function setCREReceivePaybackMoneySession($app_id)
    {
        self::set('cre_receive_payback_money_session', $app_id);
    }

    public static function resetCREReceivePaybackMoneySession()
    {
        self::set('cre_receive_payback_money_session', 0);
    }

    public static function getCREReceivePaybackMoneySession()
    {
        return (int)self::get('cre_receive_payback_money_session');
    }

    public static function resetCloseDaySession()
    {
        self::set('close_day_session', 0);
    }

    public static function setCloseDaySession($id)
    {
        self::set('close_day_session', $id);
    }

    public static function getCloseDaySession()
    {
        return (int)self::get('close_day_session');
    }
}