<?php
namespace Api;

use Bitrix\Main\Web\HttpClient;
use Helpers\LogHelper;
use Models\Applications;
use Settings\Common;

class Mattermost
{
    public static function send($message, $url)
    {
        $is_testing_mode = Common::get('mattermost_is_testing_mode')=='Y';
        if($is_testing_mode){
            $message = "Тестовый режим. Сообщение в канал $url!\n\n".$message;
            LogHelper::write($message);
        } else {
            $httpClient = new HttpClient();
            $httpClient->setHeader('Content-Type', 'application/json');
            $message = $message."\n\n\n";
            $httpClient->post($url, json_encode(['text' => $message]));
        }

    }

}