<?php
namespace Processing\CashRoomEmployee;
use Models\Applications;
use Models\CashRoom;
use Models\CashRoomDay;

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
        $cash_room_days = new CashRoomDay();
        $cash_room = $cash_rooms->find($cash_room_id);
        if($cash_room->getId()>0) {
            $status = "Закрыта";
            if($cash_room_days->isExistsOpenToday($cash_room->getId())) {
                $status = "Открыта";
            }
            $cash = $cash_room->getCash();
            $message .= "<b>" . $cash_room->getName() . "</b>\n\n";
            $message .= "Cтатус - <b>".$status."</b>\n";
            $message .= "Всего наличных <b>".number_format($cash['all'], 0, '.', ' ')."</b>\n";
            $message .= "Резерв <b>".number_format($cash['reserve'], 0, '.', ' ')."</b>\n";
            $message .= "Свободно <b>".number_format($cash['free'], 0, '.', ' ')."</b>\n";
            $message .= "==================================\n";
            $message .= "\n";
        }
        return $message;
    }
}