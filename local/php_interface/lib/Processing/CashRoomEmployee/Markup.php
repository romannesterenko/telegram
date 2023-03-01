<?php
namespace Processing\CashRoomEmployee;
use Models\Applications;
use Models\CashRoom;

class Markup
{
    public static function getMarkupByCRE($app_id, $action): array
    {
        $response = [];
        switch ($action){
            case 'new_app':
                $response = self::getCRENewAppMarkup($app_id);
                break;
        }
        return $response;
    }
    public static function getCRENewAppMarkup($app_id): array
    {
        $applications = new Applications();
        $response['message'] = "Новая заявка\n";
        $response['message'].= $applications->prepareAppDataMessage($app_id);
        return $response;
    }

    public static function getCashRoomInfoMarkup($cash_room_id):string
    {
        $message = '';
        $cash_rooms = new CashRoom();
        $cash_room = $cash_rooms->find($cash_room_id);
        if($cash_room->getId()>0) {
            $cash = $cash_room->getCash();
            $message .= "<b>" . $cash_room->getName() . "</b>\n\n";
            $message .= "<b>Всего наличных</b> - ".number_format($cash['all'], 0, '.', ' ')."\n";
            $message .= "<b>Резерв</b> - ".number_format($cash['reserve'], 0, '.', ' ')."\n";
            $message .= "<b>Свободно</b> - ".number_format($cash['free'], 0, '.', ' ')."\n";
            $message .= "==================================\n";
            $message .= "\n";
        }
        return $message;
    }
}