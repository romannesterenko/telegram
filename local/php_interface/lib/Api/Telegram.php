<?php
namespace Api;
use CURLFile;
use \Helpers\ArrayHelper;
use Bitrix\Main\Web\HttpClient;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoom;
use Models\Crew;
use Models\Staff;
use \Processing\Manager\Actions as ManagerActions;
use \Processing\Responsible\Actions as RespActions;
use \Processing\CollectorsResponsible\Actions as CollRespActions;
use \Processing\CashRoomEmployee\Actions as CREActions;
use \Processing\CashRoomSenior\Actions as CRSActions;
use \Processing\Collector\Actions as CollectorActions;
use Settings\Common;

class Telegram
{

    public static function getCashRoomEmployeeMarkUp(string $string)
    {
    }

    public static function sendMessageToSenior($markup)
    {
        $staff = new Staff();
        $chat_id = $staff->getSenior()->getChatId();
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $markup['message'], 'parse_mode' => 'HTML', 'reply_markup' => $markup['buttons']];
            self::sendMessage($params);
        }
    }

    public static function sendMessageToCashResp($markup)
    {
        $staff = new Staff();
        $chat_id = $staff->getCashResp()->getField('TG_CHAT_ID');
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $markup['message'], 'parse_mode' => 'HTML', 'reply_markup' => $markup['buttons']];
            self::sendMessage($params);
        }
    }

    public static function sendMessageToCashRoomSenior($message)
    {
        $staff = new Staff();
        $chat_id = $staff->getSenior()->getField('TG_CHAT_ID');
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $message, 'parse_mode' => 'HTML'];
            self::sendMessage($params);
        }
    }


    private static function getToken()
    {
        return Common::getTGToken();
    }

    public static function startProcess($data)
    {
        $is_callback = false;
        if(ArrayHelper::checkFullArray($data['callback_query'])) {
            $data = $data['callback_query'];
            $data['chat']['username'] = $data['message']['chat']['username'];
            $is_callback = true;
        }else {
            $data = $data['message'];
        }
        $staff = new \Models\Staff();
        if(!$data['chat']['username']){
            $message = Common::getDeniedMessage();
            $params = ["chat_id" => $data['chat']['id'], "text" => $message];
        } else {
            $employee = $staff->getByLogin($data['chat']['username']);

            if ((int)$employee->getField('ID') > 0) {
                //обработка действий для менеджера
                if ($employee->isManager()) {
                    $params = ManagerActions::process($employee, $data, $is_callback);
                    //обработка действий для ответственного за учет
                } elseif ($employee->isRespForAccounting()) {
                    $params = RespActions::process($employee, $data, $is_callback);
                    //обработка действий для ответственного за инкассацию
                } elseif ($employee->isRespForCollectors()) {
                    $params = CollRespActions::process($employee, $data, $is_callback);
                    //обработка действий для кассира
                } elseif ($employee->isCashRoomEmployee()) {
                    $params = CREActions::process($employee, $data, $is_callback);
                    //обработка действий для старшего смены
                } elseif ($employee->isCashRoomSenior()) {
                    $params = CRSActions::process($employee, $data, $is_callback);
                    //обработка действий для инкассатора
                } elseif ($employee->isCollector()) {
                    $params = CollectorActions::process($employee, $data, $is_callback);
                    //если нет роли
                } else {
                    $message = Common::getRoleDeniedMessage();
                    $params = ["chat_id" => $data['chat']['id'], "text" => $message];
                }
                //если нет пользователя в системе
            } else {
                $message = Common::getDeniedMessage();
                $params = ["chat_id" => $data['chat']['id'], "text" => $message];
            }
        }
        if(ArrayHelper::checkFullArray($params['photo'])) {
            self::sendMedia($params);
        }else
            self::sendMessage($params);
    }

    /*отправка сообщений*/
    public static function sendMessage($params)
    {
        $url = "https://api.telegram.org/bot". Common::getTGToken()."/sendMessage?" . http_build_query($params);

        $httpClient = new HttpClient();
        $httpClient->get($url);
    }

    /*отправка сообщений с вложениями фото*/
    public static function sendPhoto($params)
    {
        $arrayQuery = array(
            'chat_id' => $params['chat_id'],
            'caption' => $params['caption'],
            'photo' => "http://ci01.amg.pw".\CFile::GetPath($params['photo'][0]['ID']),
        );
        $url = "https://api.telegram.org/bot". Common::getTGToken()."/sendPhoto?" . http_build_query($arrayQuery);
        $httpClient = new HttpClient();
        $httpClient->get($url);
    }

    /*отправка сообщений с вложениями фото*/
    public static function sendMedia($params)
    {
        $url = "https://api.telegram.org/bot". Common::getTGToken() ."/sendMediaGroup";
        $photos = [];
        $types = [
            'image/jpeg' => 'photo',
            'image/png' => 'photo',
        ];
        foreach ($params['photo'] as $key => $photo){
            $type = $types[$photo['CONTENT_TYPE']]??'document';
            if($key==0)
                $photos[] = ['type'=>$type, 'caption' => $params['caption'], 'media' => "http://ci01.amg.pw".\CFile::GetPath($photo['ID'])];
            else
                $photos[] = ['type'=>$type, 'media' => "http://ci01.amg.pw".\CFile::GetPath($photo['ID'])];
        }
        $postContent = [
            'chat_id' => $params['chat_id'],
            'caption' => $params['caption'],
            'media' => json_encode($photos)
        ];
        $httpClient = new HttpClient();
        $httpClient->post($url, $postContent);

    }
    public static function sendMessageToResp($text, $app_id=0, $buttons = '')
    {
        if($app_id>0){
            $markup = \Processing\Responsible\Markup::getMessagetoRespNewAppMarkup($text, $app_id);
        }else{
            $markup['message'] = $text;
        }
        if(!empty($buttons)){
            $markup['buttons'] = $buttons;
        }
        $staff = new Staff();
        $chat_id = $staff->getResp()->getField('TG_CHAT_ID');
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $markup['message'], 'parse_mode' => 'HTML', 'reply_markup' => $markup['buttons']];
            self::sendMessage($params);
        }
    }
    public static function sendMessageToCollResp($text, $app_id=0, $buttons = '')
    {
        if($app_id>0){
            $markup = \Processing\Responsible\Markup::getMessagetoRespNewAppMarkup($text, $app_id);
        }else{
            $markup['message'] = $text;
        }
        if(!empty($buttons)){
            $markup['buttons'] = $buttons;
        }
        $staff = new Staff();
        $chat_id = $staff->getCollResp()->getField('TG_CHAT_ID');
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $markup['message'], 'parse_mode' => 'HTML', 'reply_markup' => $markup['buttons']];
            self::sendMessage($params);
        }
    }
    public static function sendMessageToCashRoom($app_id, $markup)
    {
        $applications = new Applications();
        $chat_id = $applications->find($app_id)->cash_room()->employee()->getChatId();
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $markup['message'], 'parse_mode' => 'HTML', 'reply_markup' => $markup['buttons']];
            self::sendMessage($params);
        }
    }
    public static function sendCommonMessageToCashRoom($markup)
    {
        $staff = new Staff();
        $chat_id = $staff->getCashRoomEmployee()->getChatId();
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $markup['message'], 'parse_mode' => 'HTML', 'reply_markup' => $markup['buttons']];
            self::sendMessage($params);
        }
    }
    public static function sendMessageToCollector($crew_id, $markup)
    {
        $crews = new Crew();
        $chat_id = $crews->find($crew_id)->employee()->getChatId();
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $markup['message'], 'parse_mode' => 'HTML', 'reply_markup' => $markup['buttons']];
            self::sendMessage($params);
        }
    }
    public static function sendMessageToManager($data, $app_id)
    {
        $applications = new Applications();
        $chat_id = $applications->find($app_id)->manager()->getField('TG_CHAT_ID');
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $data['message'], 'parse_mode' => 'HTML', 'reply_markup' => $data['buttons']];
            self::sendMessage($params);
        }
    }
    public static function sendCommonMessageToManager($message)
    {
        $staff = new Staff();
        $chat_id = $staff->getManager()->getField('TG_CHAT_ID');
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $message, 'parse_mode' => 'HTML'];
            self::sendMessage($params);
        }
    }
    private static function sendMessageToManagerByAppID($app_id, $text)
    {
        $applications = new Applications();
        $chat_id = $applications->find($app_id)->manager()->getField('TG_CHAT_ID');
        if(!empty($chat_id)){
            $params = ["chat_id" => $chat_id, "text" => $text, 'parse_mode' => 'HTML'/*, 'reply_markup' => $markup['buttons']*/];
            self::sendMessage($params);
        }
    }
}