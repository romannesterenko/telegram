<?php
namespace Api;

use Bitrix\Main\Web\HttpClient;
use Helpers\LogHelper;
use Settings\Common;

class Mattermost
{
    public static function send($message)
    {
        $url = Common::get('mattermost_webhook_token');
        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->post($url, json_encode(['text' => $message]));
    }

}