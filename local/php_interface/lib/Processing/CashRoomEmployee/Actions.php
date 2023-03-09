<?php
namespace Processing\CashRoomEmployee;
use Api\Mattermost;
use Api\Sender;
use Api\Telegram;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoom;
use Models\CashRoomDay;
use Models\Staff;
use Processing\CashRoomEmployee\Buttons as CREButtons;
use Settings\Common;

class Actions
{
    public static function process(Staff $employee, $data, $is_callback): array
    {
        if($is_callback){
            $data['chat']['id'] = $data['message']['chat']['id'];
            if(!empty($data['data'])){
                $response = CREButtons::process($data['data']);
                $message = $response['message'];
                if ($response['buttons'])
                    $buttons = $response['buttons'];
            }
        } else {
            $cashRoomDays = new CashRoomDay();
            $buttons = json_encode([
                'resize_keyboard' => true,
                'keyboard' => [
                    [
                        [
                            'text' => Common::getButtonText('cre_start_new_work_day'),
                        ],
                    ]
                ]
            ]);
            if($cashRoomDays->isExistsOpenToday($employee->cash_room()->getId())){
                $buttons = json_encode([
                    'resize_keyboard' => true,
                    'keyboard' => [
                        [
                            [
                                'text' => Common::getButtonText('cre_apps_list_payment'),
                            ],
                            [
                                'text' => Common::getButtonText('cre_apps_list_receive'),
                            ],
                            [
                                'text' => Common::getButtonText('cre_end_work_day'),
                            ],
                        ]
                    ]
                ]);

            }
            if($cashRoomDays->isExistsClosingStarted($employee->cash_room()->getId())){
                $buttons = json_encode([
                    'resize_keyboard' => true,
                    'keyboard' => [
                        [
                            [
                                'text' => Common::getButtonText('cre_apps_list_payment'),
                            ],
                            [
                                'text' => Common::getButtonText('cre_apps_list_receive'),
                            ],
                            [
                                'text' => Common::getButtonText('cre_end_work_day'),
                            ],
                        ]
                    ]
                ]);
            }

            switch ($data['text']) {
                case Common::getButtonText('cre_apps_list_receive'):
                    $applications = new Applications();
                    $list = $applications->getRecieveAppsForCRE($employee);
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => '№'.$application['ID'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].'. Создана ' . $application['CREATED_DATE'],
                                    "callback_data" => "showApplicationForCRE_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите заявку из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок на забор пока нет';
                    }
                    break;
                case Common::getButtonText('cre_apps_list_payment'):
                    $applications = new Applications();
                    $list = $applications->getPaymentsAppsForCRE($employee);
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => '№'.$application['ID'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].'. '.$application['PROPERTY_STATUS_VALUE'],
                                    "callback_data" => "showApplicationForCRE_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите заявку из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок на выдачу пока нет';
                    }
                    break;
                case Common::getButtonText('cre_start_new_work_day'):
                    $cashRoomDays = new CashRoomDay();
                    if($cashRoomDays->isExistsOpenToday($employee->cash_room()->getId())){
                        $message = 'Смена уже открыта';
                    } else {
                        $cashRoomDays = new CashRoomDay();
                        if ($cashRoomDays->isExistsWaitingForOpen($employee->cash_room()->getId())){
                            $day = $cashRoomDays->getExistsWaitingForOpen($employee->cash_room()->getId());
                            if($day->getStatus()==30){
                                $message = "Процедура открытия рабочего дня уже начата\nВведите сумму на начало смены";
                                $buttons = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => [
                                        [
                                            [
                                                'text' => 'Отменить начало смены',
                                                "callback_data" => 'ResetStartDay_' . $day->getId()
                                            ]
                                        ]
                                    ]
                                ]);
                            } else {
                                $message = "Процедура открытия рабочего дня уже начата\nОтправлен запрос старшему на одобрение начала смены";
                            }
                        } else {
                            $day = $employee->startWorkDay();
                            $message = 'Введите сумму на начало смены';
                            $buttons = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Отменить начало смены',
                                            "callback_data" => 'ResetStartDay_' . $day
                                        ]
                                    ]
                                ]
                            ]);
                        }
                    }

                    break;
                case Common::getButtonText('cre_end_work_day'):
                    $cashRoomDays = new CashRoomDay();
                    if ($cashRoomDays->isExistsOpenToday($employee->cash_room()->getId())) {
                        $day = $cashRoomDays->getExistsOpenToday($employee->cash_room()->getId());
                        $employee->endWorkDay();
                        $message = 'Введите сумму на конец смены';
                        $buttons = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => 'Отменить завершение смены',
                                        "callback_data" => 'ResetCloseDay_' . $day->getId()
                                    ]
                                ]
                            ]
                        ]);
                    } else {
                        $cashRoomDays = new CashRoomDay();
                        if ($cashRoomDays->isExistsWaitingForClose($employee->cash_room()->getId())){
                            $day = $cashRoomDays->getExistsWaitingForClose($employee->cash_room()->getId());
                            if ($day->getStatus()==33) {
                                $message = "Процедура закрытия рабочего дня уже начата\nВведите сумму на конец смены";
                                $buttons = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => [
                                        [
                                            [
                                                'text' => 'Отменить завершение смены',
                                                "callback_data" => 'ResetCloseDay' . $day->getId()
                                            ]
                                        ]
                                    ]
                                ]);
                            } else {
                                $message = "Процедура закрытия рабочего дня уже начата\nОтправлен запрос старшему на одобрение закрытия смены";
                            }
                        } else {
                            $message = "Неверная операция";
                        }
                    }
                    break;
                case '/start':
                    $employee->setChatID($data['chat']['id']);
                    $message = 'Здравствуйте. Вы зарегистрированы в системе, приятной работы';

                    break;
                default:
                    $cashRoomDays = new CashRoomDay();
                    $cashRooms = new CashRoom();
                    $applications = new Applications();
                    $return_applications = new Applications();
                    $app = $applications->getNeedSumEnterApp();
                    $return_application = $return_applications->getNeedSumEnterToPayBack();
                    if($cashRoomDays->isExistsOpeningStarted($employee->cash_room()->getId())){
                        $data['text'] = trim(str_replace(" ","",$data['text']));
                        if ( !is_numeric( $data['text'] ) ) {
                            $message = "Сумма должна быть числовым значением\nВведите сумму на начало смены";
                            $day = $cashRoomDays->getOpeningStarted($employee->cash_room()->getId());
                            $buttons = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Отменить начало смены',
                                            "callback_data" => 'ResetStartDay_' . $day->getId()
                                        ]
                                    ]
                                ]
                            ]);
                        } else {
                            if (!$cashRooms->checkSum($employee->cash_room()->getId(), $data['text'])) {
                                $day = $cashRoomDays->getOpeningStarted($employee->cash_room()->getId());
                                $buttons = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => [
                                        [
                                            [
                                                'text' => 'Отменить начало смены',
                                                "callback_data" => 'ResetStartDay_' . $day->getId()
                                            ]
                                        ]
                                    ]
                                ]);
                                $day->setNeedApprove();
                                $message = "Суммы не совпадают\nВведите сумму на начало смены";
                                $senior_markup['message'] = $employee->cash_room()->getName().". Проблема при открытии смены.\nВведенная кассиром на начало смены сумма не совпадает с фактическим наличием";
                                Mattermost::send($senior_markup['message']);
                                Telegram::sendMessageToSenior($senior_markup);
                            } else {
                                $day = $cashRoomDays->getOpeningStarted($employee->cash_room()->getId());
                                if ($day->isNeedApprove()) {
                                    $day->setWaitForSenior();
                                    $day->setSum($data['text']);
                                    $message = "Ожидаем подтверждения открытия старшим смены";
                                    $senior_markup['message'] = $employee->cash_room()->getName() . ". Поступил запрос на открытие смены.\nСумма на начало дня - " . number_format($data['text'], 0, '', ' ');
                                    $senior_markup['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => [
                                            [
                                                [
                                                    'text' => 'Одобрить',
                                                    "callback_data" => "AllowOpenDayBySenior_" . $day->getId()
                                                ],
                                                [
                                                    'text' => 'Отклонить',
                                                    "callback_data" => "DenyOpenDayBySenior_" . $day->getId()
                                                ],
                                            ]
                                        ]
                                    ]);
                                    Telegram::sendMessageToSenior($senior_markup);
                                } else {
                                    $day->setOpen();
                                    $day->setSum($data['text']);
                                    $message = "Открытие смены прошло успешно. Приятной работы";
                                    $buttons = json_encode([
                                        'resize_keyboard' => true,
                                        'keyboard' => [
                                            [
                                                [
                                                    'text' => Common::getButtonText('cre_apps_list_payment'),
                                                ],
                                                [
                                                    'text' => Common::getButtonText('cre_apps_list_receive'),
                                                ],
                                                [
                                                    'text' => Common::getButtonText('cre_end_work_day'),
                                                ],
                                            ]
                                        ]
                                    ]);
                                }
                            }
                        }
                    } elseif ($cashRoomDays->isExistsClosingStarted($employee->cash_room()->getId())) {
                        $data['text'] = trim(str_replace(" ","",$data['text']));
                        if ( !is_numeric( $data['text'] ) ) {
                            $message = "Сумма должна быть числовым значением\nВведите сумму на конец смены";
                            $day = $cashRoomDays->getClosingStarted($employee->cash_room()->getId());
                            $buttons = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Отменить завершение смены',
                                            "callback_data" => 'ResetCloseDay_' . $day->getId()
                                        ]
                                    ]
                                ]
                            ]);
                        } else {
                            if (!$cashRooms->checkClosedSum($employee->cash_room()->getId(), $data['text'])) {
                                $message = "Суммы не совпадают\nВведите сумму на конец смены";
                                $senior_markup['message'] = $employee->cash_room()->getName().". Проблема при закрытии смены.\nВведенная на конец смены сумма не совпадает с фактическим наличием";
                                Mattermost::send($senior_markup['message']);
                                $day = $cashRoomDays->getClosingStarted($employee->cash_room()->getId());
                                $day->setNeedApprove();
                                $buttons = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => [
                                        [
                                            [
                                                'text' => 'Отменить завершение смены',
                                                "callback_data" => 'ResetCloseDay_' . $day->getId()
                                            ]
                                        ]
                                    ]
                                ]);
                                Telegram::sendMessageToSenior($senior_markup);
                            } else {
                                $day = $cashRoomDays->getClosingStarted($employee->cash_room()->getId());
                                if ($day->isNeedApprove()) {
                                    $day->setWaitForCloseBySenior();
                                    $day->setEndSum($data['text']);
                                    $message = "Ожидаем подтверждения закрытия старшим смены";
                                    $senior_markup['message'] = "Поступил запрос на закрытие смены. " . $employee->cash_room()->getName();
                                    $senior_markup['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => [
                                            [
                                                [
                                                    'text' => 'Одобрить',
                                                    "callback_data" => "AllowCloseDayBySenior_" . $day->getId()
                                                ],
                                                [
                                                    'text' => 'Отклонить',
                                                    "callback_data" => "DenyCloseDayBySenior_" . $day->getId()
                                                ],
                                            ]
                                        ]
                                    ]);
                                    Telegram::sendMessageToSenior($senior_markup);
                                } else {
                                    $day->setClose();
                                    $day->setEndSum($data['text']);
                                    $message = "Смена закрыта";
                                    $buttons = json_encode([
                                        'resize_keyboard' => true,
                                        'keyboard' => [
                                            [
                                                [
                                                    'text' => Common::getButtonText('cre_start_new_work_day'),
                                                ],
                                            ]
                                        ]
                                    ]);
                                }
                            }

                        }
                    } elseif( $app->getId() > 0 ){
                        $data['text'] = trim(str_replace(" ","",$data['text']));
                        if ( !is_numeric( $data['text'] ) ) {
                            $message = "Сумма должна быть числовым значением\nВведите привезенную сумму";
                        } else {
                            if( (int)$data['text'] != $app->getSum() ){
                                $message = "Суммы не совпадают\nВведите привезенную сумму";
                            } else {
                                $app->setComplete();
                                $markup['message'] = "Заявка №" . (int)$app->getId() . " была успешно выполнена";
                                $cash_room_channel_message = "Информация по заявке №" . (int)$app->getId() . "\n";
                                $cash_room_channel_message.="Экипаж ".$app->crew()->getName()." передал сумму в размере ".number_format($app->getSum(), 0, '', ' ')." в кассу №".$app->cash_room()->getName();
                                Mattermost::send($cash_room_channel_message);
                                Telegram::sendMessageToManager($markup, (int)$app->getId());
                                if ($app->isPayment())
                                    Telegram::sendMessageToResp($markup['message']);
                                else
                                    Telegram::sendMessageToCollResp($markup['message']);
                                $message = "Заявка выполнена";
                            }
                        }
                    } elseif ( $return_application->getId() > 0 ) {
                        $data['text'] = trim(str_replace(" ","",$data['text']));
                        if ( !is_numeric( $data['text'] ) ) {
                            $message = "Сумма должна быть числовым значением\nВведите привезенную сумму";
                        } else {
                            if( (int)$data['text'] != $return_application->getSum() ){
                                $message = "Суммы не совпадают\nВведите привезенную сумму";
                            } else {
                                $return_application->setReturned();
                                $return_application->order()->setStatus(51);
                                $markup['message'] = "Средства по заявке №" . (int)$return_application->getId() . " были возвращены";
                                Telegram::sendMessageToManager($markup, (int)$return_application->getId());
                                if ($return_application->isPayment())
                                    Telegram::sendMessageToResp($markup['message']);
                                else
                                    Telegram::sendMessageToCollResp($markup['message']);
                                $message = "Средства возвращены, заявка №".$return_application->getId()." помечена как отмененная";
                            }
                        }
                    } else {
                        $message = "Вы ввели неизвестную мне команду :/";
                    }

            }
        }
        return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
    }
}