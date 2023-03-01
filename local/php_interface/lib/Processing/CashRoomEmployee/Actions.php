<?php
namespace Processing\CashRoomEmployee;
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
            switch ($data['text']) {
                case Common::getButtonText('cre_apps_list_new'):
                    $applications = new Applications();
                    $list = $applications->getAppsForCRE($employee);
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
                        $message = 'Действующих заявок пока нет';
                    }
                    break;
                case Common::getButtonText('cre_start_new_work_day'):
                    $cashRoomDays = new CashRoomDay();
                    if($cashRoomDays->isExistsOpenToday($employee->cash_room()->getId())){
                        $message = 'Смена уже открыта';
                        $second_button_text = Common::getButtonText('cre_end_work_day');
                        $buttons = json_encode([
                            'resize_keyboard' => true,
                            'keyboard' => [
                                [
                                    [
                                        'text' => Common::getButtonText('cre_apps_list_new')
                                    ],
                                    [
                                        'text' => $second_button_text
                                    ],
                                ]
                            ]
                        ]);
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
                            }else{
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
                                        "callback_data" => 'ResetStartDay_' . $day->getId()
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
                                                "callback_data" => 'ResetStartDay_' . $day->getId()
                                            ]
                                        ]
                                    ]
                                ]);
                            } else {
                                $message = "Процедура закрытия рабочего дня уже начата\nОтправлен запрос старшему на одобрение закрытия смены";
                            }
                        } else {

                        }
                    }
                    break;
                case '/start':
                    $cashRoomDays = new CashRoomDay();
                    $employee->setChatID($data['chat']['id']);
                    $message = 'Здравствуйте. Вы зарегистрированы в системе, приятной работы';
                    $second_button_text = Common::getButtonText('cre_start_new_work_day');
                    if($cashRoomDays->isExistsOpenToday($employee->cash_room()->getId())){
                        $second_button_text = Common::getButtonText('cre_end_work_day');
                    }
                    if($cashRoomDays->isExistsClosingStarted()){
                        $second_button_text = Common::getButtonText('cre_end_work_day');
                    }
                    $buttons = json_encode([
                        'resize_keyboard' => true,
                        'keyboard' => [
                            [
                                [
                                    'text' => Common::getButtonText('cre_apps_list_new'),
                                ],
                                [
                                    'text' => $second_button_text,
                                ],
                            ]
                        ]
                    ]);
                    break;
                default:
                    $cashRoomDays = new CashRoomDay();
                    $cashRoomDays3 = new CashRoomDay();
                    $cashRooms = new CashRoom();
                    $applications = new Applications();
                    $app = $applications->getNeedSumEnterApp();
                    $second_button_text = Common::getButtonText('cre_start_new_work_day');
                    if($cashRoomDays3->isExistsOpenToday($employee->cash_room()->getId())){
                        $second_button_text = Common::getButtonText('cre_end_work_day');
                    }
                    if($cashRoomDays3->isExistsClosingStarted()){
                        $second_button_text = Common::getButtonText('cre_end_work_day');
                    }
                    $buttons = json_encode([
                        'resize_keyboard' => true,
                        'keyboard' => [
                            [
                                [
                                    'text' => Common::getButtonText('cre_apps_list_new'),
                                ],
                                [
                                    'text' => $second_button_text,
                                ],
                            ]
                        ]
                    ]);
                    if($cashRoomDays->isExistsOpeningStarted($employee->cash_room()->getId())){
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
                                $message = "Суммы не совпадают\nВведите сумму на начало смены";
                                $senior_markup['message'] = $employee->cash_room()->getName().". Проблема при открытии смены.\nВведенная на начало смены сумма не совпадает с фактическим наличием";
                                Telegram::sendMessageToSenior($senior_markup);
                            } else {
                                $day = $cashRoomDays->getOpeningStarted($employee->cash_room()->getId());
                                $day->setWaitForSenior();
                                $day->setSum($data['text']);
                                $message = "Ожидаем подтверждения открытия старшим смены";
                                $senior_markup['message'] = $employee->cash_room()->getName().". Поступил запрос на открытие смены.\nСумма на начало дня - ".number_format($data['text'], 0, '', ' ');
                                $senior_markup['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => [
                                        [
                                            [
                                                'text' => 'Одобрить',
                                                "callback_data" => "AllowOpenDayBySenior_".$day->getId()
                                            ],
                                            [
                                                'text' => 'Отклонить',
                                                "callback_data" => "DenyOpenDayBySenior_".$day->getId()
                                            ],
                                        ]
                                    ]
                                ]);
                                Telegram::sendMessageToSenior($senior_markup);
                            }
                        }
                    } elseif ($cashRoomDays->isExistsClosingStarted()) {
                        if ( !is_numeric( $data['text'] ) ) {
                            $message = "Сумма должна быть числовым значением\nВведите сумму на конец смены";
                        } else {
                            if (!$cashRooms->checkClosedSum($employee->cash_room()->getId(), $data['text'])) {
                                $message = "Суммы не совпадают\nВведите сумму на конец смены";
                                $senior_markup['message'] = $employee->cash_room()->getName().". Проблема при закрытии смены.\nВведенная на конец смены сумма не совпадает с фактическим наличием";
                                Telegram::sendMessageToSenior($senior_markup);
                            } else {
                                $day = $cashRoomDays->getClosingStarted();
                                $day->setWaitForCloseBySenior();
                                $day->setEndSum($data['text']);
                                $message = "Ожидаем подтверждения закрытия старшим смены";
                                $buttons = json_encode([
                                    'resize_keyboard' => true,
                                    'keyboard' => [
                                        [
                                            [
                                                'text' => Common::getButtonText('cre_apps_list_new')
                                            ],
                                            [
                                                'text' => Common::getButtonText('cre_end_work_day')
                                            ]
                                        ]
                                    ]
                                ]);
                                $senior_markup['message'] = "Поступил запрос на закрытие смены. ".$employee->cash_room()->getName();
                                $senior_markup['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => [
                                        [
                                            [
                                                'text' => 'Одобрить',
                                                "callback_data" => "AllowCloseDayBySenior_".$day->getId()
                                            ],
                                            [
                                                'text' => 'Отклонить',
                                                "callback_data" => "DenyCloseDayBySenior_".$day->getId()
                                            ],
                                        ]
                                    ]
                                ]);
                                Telegram::sendMessageToSenior($senior_markup);
                            }

                        }
                    } elseif( $app->getId() > 0 ){
                        if ( !is_numeric( $data['text'] ) ) {
                            $message = "Сумма должна быть числовым значением\nВведите привезенную сумму";
                        } else {
                            if( (int)$data['text'] != $app->getSum() ){
                                $message = "Суммы не совпадают\nВведите привезенную сумму";
                            } else {
                                $app->setComplete();
                                $markup['message'] = "Заявка №" . (int)$app->getId() . " была успешно выполнена";
                                Telegram::sendMessageToManager($markup, (int)$app->getId());
                                if ($app->isPayment())
                                    Telegram::sendMessageToResp($markup['message']);
                                else
                                    Telegram::sendMessageToCollResp($markup['message']);
                                $contact_message = "Заявка №".$app->getId()." выполнена. Спасибо за работу";
                                Sender::send($app, $contact_message);
                                $message = "Заявка выполнена";
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