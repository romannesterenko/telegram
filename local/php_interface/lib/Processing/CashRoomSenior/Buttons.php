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
            case 'showApproveDay':
                if((int)$array_data[1]>0){
                    $cash_room_days = new CashRoomDay();
                    $crd = $cash_room_days->find((int)$array_data[1]);
                    $message = $crd->getName() . ". Запрос на открытие смены.\nСумма на начало дня - " . number_format($crd->getField('START_SUM'), 0, '', ' ');
                    $response['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => 'Одобрить',
                                    "callback_data" => "AllowOpenDayBySenior_" . $crd->getId()
                                ],
                                [
                                    'text' => 'Отклонить',
                                    "callback_data" => "DenyOpenDayBySenior_" . $crd->getId()
                                ],
                            ]
                        ]
                    ]);
                }
                break;
            case 'showApproveCloseDay':
                if((int)$array_data[1]>0){
                    $cash_room_days = new CashRoomDay();
                    $crd = $cash_room_days->find((int)$array_data[1]);
                    $message = $crd->getName() . ". Запрос на закрытие смены.\nСумма на конец дня - " . number_format($crd->getField('END_SUM'), 0, '', ' ');
                    $response['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => 'Одобрить',
                                    "callback_data" => "AllowCloseDayBySenior_" . $crd->getId()
                                ],
                                [
                                    'text' => 'Отклонить',
                                    "callback_data" => "DenyCloseDayBySenior_" . $crd->getId()
                                ],
                            ]
                        ]
                    ]);
                }
                break;

            case 'AllowOpenDayBySenior':
                if((int)$array_data[1]>0){
                    $cash_room_days = new CashRoomDay();
                    $crd = $cash_room_days->find((int)$array_data[1]);
                    if ($crd->getStatus()!=36) {
                        $message = "Действие недоступно";
                    } else {
                        $crd->setOpen();
                        $cre_markup['message'] = "Открытие смены одобрено. Приятной работы";
                        $cre_markup['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'keyboard' => [
                                [
                                    [
                                        'text' => "Выдача",
                                    ],
                                    [
                                        'text' => "Забор",
                                    ],
                                    [
                                        'text' => Common::getButtonText('cre_end_work_day'),
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
                        $crd->setClose();
                        $cre_markup['message'] = "Закрытие смены одобрено";
                        $cre_markup['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'keyboard' => [
                                [
                                    [
                                        'text' => Common::getButtonText('cre_start_new_work_day'),
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

            case 'DenyCloseDayBySenior':
                if((int)$array_data[1]>0){
                    $cash_room_days = new CashRoomDay();
                    $crd = $cash_room_days->find((int)$array_data[1]);
                    if($crd->getStatus()==37){
                        $message = "Отклонено";
                        $crd->setStatus(32);
                        $cre_markup['message'] = "Закрытие смены отклонено старшим";
                        $cre_markup['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'keyboard' => [
                                [
                                    [
                                        'text' => "Выдача",
                                    ],
                                    [
                                        'text' => "Забор",
                                    ],
                                    [
                                        'text' => Common::getButtonText('cre_end_work_day'),
                                    ],
                                ]
                            ]
                        ]);
                        Telegram::sendCommonMessageToCashRoom($crd->cash_room(), $cre_markup);
                    }
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }
}