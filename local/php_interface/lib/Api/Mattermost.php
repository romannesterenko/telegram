<?php
namespace Api;

use Bitrix\Main\Web\HttpClient;
use Helpers\LogHelper;
use Settings\Common;

class Mattermost
{
    public static function send($message, $channel = 'cash')
    {
        $is_testing_mode = Common::get('mattermost_is_testing_mode')=='Y';
        if($is_testing_mode){
            $channel_name = $channel=='operation'?'операция':'касса';
            $message = "Тестовый режим. Сообщение в канал $channel_name!\n\n".$message;
            LogHelper::write($message);
        } else {
            $url = Common::get('mattermost_webhook_token');
            if($channel=='operation')
                $url = Common::get('mattermost_webhook_operation_token');
            $httpClient = new HttpClient();
            $httpClient->setHeader('Content-Type', 'application/json');
            $httpClient->post($url, json_encode(['text' => $message]));
        }

    }

}