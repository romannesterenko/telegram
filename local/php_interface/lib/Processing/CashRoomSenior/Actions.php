<?php
namespace Processing\CashRoomSenior;
use Api\Telegram;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoomDay;
use Processing\CashRoomSenior\Buttons as CRSButtons;
use Settings\Common;

class Actions
{
    public static function process(\Models\Staff $employee, $data, $is_callback): array
    {
        if($is_callback){
            $data['chat']['id'] = $data['message']['chat']['id'];
            if(!empty($data['data'])){
                $response = CRSButtons::process($data['data']);
                $message = $response['message'];
                if ($response['buttons'])
                    $buttons = $response['buttons'];
            }
        } else {
            switch ($data['text']) {
                case '/start':
                    $employee->setChatID($data['chat']['id']);
                    $message = 'Здравствуйте. Вы зарегистрированы в системе, приятной работы';
                    break;
                default:
                    $message = "Вы ввели неизвестную мне команду :/";

            }
        }
        return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
    }
}