<?php
namespace Processing\Collector;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoom;
use Models\Crew;
use Settings\Common;

class Markup
{
    public static function getMarkupByCollector($app_id, $crew_id, $action)
    {
        $response = [];
        switch ($action){
            case 'new_app':
                $response = self::getCollectorNewAppMarkup($crew_id, $app_id);
                break;
        }
        return $response;
    }
    public static function getCollectorNewAppMarkup($crew_id, $app_id)
    {
        $applications = new Applications();
        $app = $applications->find($app_id);
        if($app->isPayment()){
            $response['message'] = "Новая заявка №".$app->getID().". Выдача\n";
            $response['message'].= "Забрать сумму в размере ".number_format($app->getSum(), '0', '.', ' ')." в точке выдачи - ".$app->cash_room()->getName()."\n";
            $response['buttons'] = json_encode([
                'resize_keyboard' => true,
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Взять в работу',
                            "callback_data" => "AllowAppByCrew_".$app_id."_".$crew_id
                        ],
                    ]
                ]
            ]);
            //LogHelper::write($response);
        }else{
            $response['message'] = "Новая заявка №".$app->getID().". Забор\n";
            $response['message'].= "Адрес - ".$app->cash_room()->getName()."\n";
            $response['message'].= "Время - ".$app->getField('TIME')."\n";
            $response['buttons'] = json_encode([
                'resize_keyboard' => true,
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Взять в работу',
                            "callback_data" => "AllowAppByCrew_".$app_id."_".$crew_id
                        ],
                    ]
                ]
            ]);
        }
        return $response;
    }
}