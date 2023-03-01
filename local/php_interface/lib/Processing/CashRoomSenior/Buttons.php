<?php
namespace Processing\CashRoomSenior;
use Api\Telegram;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoomDay;
use Processing\Responsible\Markup as RespMarkup;
use Settings\Common;

class Buttons
{
    public static function process($data): array
    {
        $message = Common::getWrongCallBackData();
        $array_data = explode('_', $data);
        switch ($array_data[0]){
            case 'AllowOpenDayBySenior':
                if((int)$array_data[1]>0){
                    $cash_room_days = new CashRoomDay();
                    $crd = $cash_room_days->find((int)$array_data[1]);
                    if ($crd->getStatus()!=36) {
                        $message = "Действие недоступно";
                    } else {
                        $crd->setOpen($crd->getId());
                        $cre_markup['message'] = "Открытие смены одобрено. Приятной работы";
                        $cre_markup['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'keyboard' => [
                                [
                                    [
                                        'text' => Common::getButtonText('cre_apps_list_new')
                                    ],
                                    [
                                        'text' => Common::getButtonText('cre_end_work_day')
                                    ],
                                ]
                            ]
                        ]);
                        Telegram::sendCommonMessageToCashRoom($crd->cash_room(), $cre_markup);
                        $message = "Одобрено. Смена открыта";
                    }
                }
                break;

            case 'DenyOpenDayBySenior':
                if((int)$array_data[1]>0){
                    $cash_room_days = new CashRoomDay();
                    $crd = $cash_room_days->find((int)$array_data[1]);
                    if ($crd->getStatus()!=36) {
                        $message = "Действие недоступно";
                    } else {
                        $crd->setStatus(30);
                        $cre_markup['message'] = "Открытие смены отклонено старшим.";
                        Telegram::sendCommonMessageToCashRoom($crd->cash_room(), $cre_markup);
                        $message = "Отклонено";
                    }
                }
                break;
            case 'AllowCloseDayBySenior':
                if((int)$array_data[1]>0){
                    $cash_room_days = new CashRoomDay();
                    $crd = $cash_room_days->find((int)$array_data[1]);
                    if($crd->isExistsWaitingForClose($crd->cash_room()->getId())){
                        $crd->setClose($crd->getId());
                        $cre_markup['message'] = "Закрытие смены одобрено";
                        $cre_markup['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'keyboard' => [
                                [
                                    [
                                        'text' => Common::getButtonText('cre_apps_list_new')
                                    ],
                                    [
                                        'text' => Common::getButtonText('cre_start_new_work_day')
                                    ],
                                ]
                            ]
                        ]);
                        Telegram::sendCommonMessageToCashRoom($crd->cash_room(), $cre_markup);
                        $message = "Одобрено";
                    } else {
                        $message = "Рабочий день уже закрыт";
                    }
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }
}