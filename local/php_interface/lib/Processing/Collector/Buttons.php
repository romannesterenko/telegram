<?php
namespace Processing\Collector;
use Api\Mattermost;
use Api\Telegram;
use Models\Applications;
use Models\Order;
use Processing\Responsible\Markup as RespMarkup;
use Settings\Common;

class Buttons
{
    public static function process($data): array
    {
        $message = Common::getWrongCallBackData();
        $array_data = explode('_', $data);
        switch ($array_data[0]){
            //Кнопка экипажу "Взять в работу"
            case 'AllowAppByCrew':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->crew()->employee()->getNotGaveMoneySession()>0){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotGaveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelGave_".$app->crew()->employee()->getNotGaveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } elseif ($app->crew()->employee()->getNotReceiveMoneySession()>0){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotReceiveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelRecieve_".$app->crew()->employee()->getNotReceiveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } else {
                        if ($app->getStatus() != 25) {
                            $message = Common::getWrongAppActionText();
                        } else {
                            $app->setCrew((int)$array_data[2]);
                            //Экипаж одобрил заявку и ставим статус "Назначена"
                            $app->setStatus(23);
                            if ($app->isPayment()) {
                                /*$order = new Order();
                                $order->createFromAppID($app->getId());*/
                                $message = "Заявка №" . $app->getId() . " принята в работу. \nЗаберите деньги в  - <b>" . $app->cash_room()->getName() . "</b> и отвезите по адресу <b>" . $app->getField('ADDRESS') . "</b>\n";
                                $message .= "Контактное лицо - " . $app->getField('AGENT_NAME') . "\n";
                                $message .= "Телефон - " . $app->getField('CONTACT_PHONE') . "\n";
                                $response['buttons'] = Buttons::getCommonButtons($app->crew()->getId());
                            } else {
                                $message = "Заявка №" . $app->getId() . " принята в работу. \nЗаберите деньги в  <b>" . $app->getField('TIME') . "</b>, по адресу <b>" . $app->getField('ADDRESS') . "</b>, и отвезите в кассу <b>" . $app->cash_room()->getName() . "</b>\n";
                                $message .= "Контактное лицо - " . $app->getField('AGENT_NAME') . "\n";
                                $message .= "Телефон - " . $app->getField('CONTACT_PHONE') . "\n";
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => [
                                        [
                                            [
                                                'text' => 'Деньги получены',
                                                "callback_data" => "CrewGotMoney_" . $app->getID()
                                            ],
                                            [
                                                'text' => 'Деньги не получены',
                                                "callback_data" => "CrewNotGotMoney_" . $app->getID()
                                            ],
                                        ]
                                    ]
                                ]);
                            }
                        }
                    }

                }
                break;
            case 'toTomorrow':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->crew()->employee()->getNotGaveMoneySession()>0){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotGaveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelGave_".$app->crew()->employee()->getNotGaveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } elseif ($app->crew()->employee()->getNotReceiveMoneySession()>0){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotReceiveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelRecieve_".$app->crew()->employee()->getNotReceiveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } else {
                        if ($app->getStatus() != 20 || $app->getField('TO_TOMORROW')==1) {
                            $message = Common::getWrongAppActionText();
                        } else {
                            $app->setField('TO_TOMORROW', 1);
                            $message = "Заявка №".$app->getId()." перенесена на завтра. ";
                            $response['buttons'] = json_encode([
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => "Отменить перенос",
                                            "callback_data" => "cancelToTomorrow_".$app->getId()
                                        ],
                                    ]
                                ]
                            ]);
                        }
                    }
                }
                break;
            case 'cancelToTomorrow':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if ($app->getStatus() != 20 || $app->getField('TO_TOMORROW')!=1) {
                        $message = Common::getWrongAppActionText();
                    } else {
                        $app->setField('TO_TOMORROW', false);
                        $message = "Перенос заявки №".$app->getId()." на завтра отменен.";
                    }
                }
                break;
            //Экипаж забрал деньги по адресу
            case 'CrewGotMoney':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->crew()->employee()->getNotGaveMoneySession()>0){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotGaveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelGave_".$app->crew()->employee()->getNotGaveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } elseif ($app->crew()->employee()->getNotReceiveMoneySession()>0){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotReceiveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelRecieve_".$app->crew()->employee()->getNotReceiveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } else {
                        if ($app->getStatus() != 23) {
                            $message = \Settings\Common::getWrongAppActionText();
                        } else {
                            //Экипаж одобрил заявку и ставим стату "Назначена"
                            $app->setInDeliveryStatus();
                            $message = "Деньги забраны. Отвезите деньги в кассу - " . $app->cash_room()->getName();
                            //сообщения менеджеру и ответственному об изменении статуса
                            $markup['message'] = "Информация по заявке №" . $app->getId() . "\n";
                            $markup['message'] .= "Деньги от контрагента <b>" . $app->getField('AGENT_OFF_NAME') . "</b> в доставке\n";
                            Telegram::sendMessageToManagerByAppID($app->getId(), $markup['message']);
                            //Telegram::sendMessageToResp($markup['message']);
                            $response['buttons'] = Buttons::getCommonButtons($app->crew()->getId());
                        }
                    }
                }
                break;
            //Экипаж не забрал деньги по адресу
            case 'CrewNotGotMoney':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->crew()->employee()->getNotGaveMoneySession()>0){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotGaveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelGave_".$app->crew()->employee()->getNotGaveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } elseif ($app->crew()->employee()->getNotReceiveMoneySession()>0&&$app->crew()->employee()->getNotReceiveMoneySession()!=(int)$array_data[1]){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotReceiveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelRecieve_".$app->crew()->employee()->getNotReceiveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } else {
                        if($app->getStatus()!=23){
                            $message = \Settings\Common::getWrongAppActionText();
                        } else {
                            $app->crew()->employee()->setNotReceiveMoneySession($app->getId());
                            $message = "Деньги не получены. Введите причину невозможности получения денег";
                            $response['buttons'] = json_encode([
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => "Отклонить отмену заявки",
                                            "callback_data" => "restoreCancelRecieve_".$app->getId()
                                        ],
                                    ]
                                ]
                            ]);
                            $app->setStatus(56);
                        }
                    }

                }
                break;
            //Экипаж передал деньги
            case 'CompleteApp':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->crew()->employee()->getNotGaveMoneySession()>0){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotGaveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelGave_".$app->crew()->employee()->getNotGaveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } elseif ($app->crew()->employee()->getNotReceiveMoneySession()>0){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotReceiveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelRecieve_".$app->crew()->employee()->getNotReceiveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } else {
                        if ($app->isComplete() || $app->isFailed()) {
                            $message = \Settings\Common::getWrongAppActionText();
                        } else {
                            $app->setComplete();
                            $markup['message'] = $app->getField("AGENT_OFF_NAME") . ". №" . (int)$array_data[1] . ". Заявка выполнена";
                            Telegram::sendMessageToManager($markup, (int)$array_data[1]);
                            \Api\Sender::send($app, $markup['message']);
                            $message = "Заявка №" . $app->getID() . " выполнена";
                            $response['buttons'] = Buttons::getCommonButtons($app->crew()->getId());
                        }
                    }
                }
                break;
            //Отмена "непередачи" денег
            case 'restoreCancelGave':
                if((int)$array_data[1]>0){
                    $app = (new Applications())->find((int)$array_data[1]);
                    if($app->crew()->employee()->getNotGaveMoneySession()==(int)$array_data[1]){
                        $app->crew()->employee()->resetNotGaveMoneySession();
                        $app->setStatus(20);
                        $message = "Заявка №" . $app->getID() . ". Ввод комментария отклонен. Заявка возвращена в раздел 'В доставке'";
                    }
                }
                break;
            //Отмена "неполучения" денег
            case 'restoreCancelRecieve':
                if((int)$array_data[1]>0){
                    $app = (new Applications())->find((int)$array_data[1]);
                    if($app->crew()->employee()->getNotReceiveMoneySession()==(int)$array_data[1]){
                        $app->crew()->employee()->resetNotReceiveMoneySession();
                        $app->setStatus(23);
                        $message = "Заявка №" . $app->getID() . ". Ввод комментария отклонен. Заявка возвращена в раздел 'Забор'";
                    }
                }
                break;
            case 'InCompleteApp':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);

                    if($app->crew()->employee()->getNotGaveMoneySession()>0&&$app->crew()->employee()->getNotGaveMoneySession()!=(int)$array_data[1]){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotGaveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelGave_".$app->crew()->employee()->getNotGaveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } elseif ($app->crew()->employee()->getNotReceiveMoneySession()>0&&$app->crew()->employee()->getNotReceiveMoneySession()){
                        $message = "Невозможно! Заявка №".$app->crew()->employee()->getNotReceiveMoneySession()." ожидает ввода комментария отмены, введите комментарий или отклоните отмену заявки для продолжения работы";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отклонить отмену заявки",
                                        "callback_data" => "restoreCancelRecieve_".$app->crew()->employee()->getNotReceiveMoneySession()
                                    ],
                                ]
                            ]
                        ]);
                    } else {
                        if ($app->getStatus() != 23 && $app->getStatus() != 20) {
                            $message = \Settings\Common::getWrongAppActionText();
                        } else {
                            $app->crew()->employee()->setNotGaveMoneySession((int)$array_data[1]);
                            $message = "Деньги не переданы. Введите причину по которой деньги не переданы";
                            $response['buttons'] = json_encode([
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => "Отклонить отмену заявки",
                                            "callback_data" => "restoreCancelGave_" . $app->getId()
                                        ],
                                    ]
                                ]
                            ]);
                            $app->setStatus(55);
                        }
                    }
                }
                break;
            //Просмотр заявки экипажем
            case 'showApplicationForCollector':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    $message = "Заявка №".$app->getID()."\n";
                    if($app->isPayment()){
                        if($app->getStatus()==25){
                            $markup = Markup::getCollectorNewAppMarkup($app->crew()->getId(), $app->getId());
                            $message = $markup['message'];
                            $response['buttons'] = $markup['buttons'];
                        } elseif ($app->getStatus()==23) {
                            $message = "Заявка №".$app->getId().". Выдача\nЗабрать деньги в  - ".$app->cash_room()->getName();
                        } elseif ($app->getStatus()==20) {
                            $message.= "Отвезите деньги по адресу <b>".$app->getAddress()."</b>\n";
                            $message.= "Контактное лицо - ".$app->getField('AGENT_NAME')."\n";
                            $message.= "Телефон - ".$app->getField('CONTACT_PHONE')."\n";
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Деньги переданы',
                                            "callback_data" => "CompleteApp_".$app->getId()
                                        ],
                                        [
                                            'text' => 'Деньги не переданы',
                                            "callback_data" => "InCompleteApp_".$app->getId()
                                        ],
                                        [
                                            'text' => 'На завтра',
                                            "callback_data" => "toTomorrow_".$app->getId()
                                        ],
                                    ]
                                ]
                            ]);
                        } elseif ($app->getStatus()==52){
                            $message = "Деньги не переданы.\nВозврат из доставки заявки №".$app->getId();
                            $message.= "\nОтвезите деньги обратно в кассу ".$app->cash_room()->getName();
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Отменить возврат',
                                            "callback_data" => "cancelPayback_".$app->getId()
                                        ],
                                    ]
                                ]
                            ]);
                        }
                    } else {
                        if ($app->getStatus()==25) {
                            $markup = Markup::getCollectorNewAppMarkup($app->crew()->getId(), $app->getId());
                            $message = $markup['message'];
                            $response['buttons'] = $markup['buttons'];
                        } elseif ($app->getStatus()==23) {
                            $message.= "Заберите деньги в <b>".$app->getField('TIME')."</b>, по адресу - <b>" . $app->getField('ADDRESS')."</b>, и отвезите в кассу <b>".$app->cash_room()->getName()."</b>\n";
                            $message.= "Контактное лицо - ".$app->getField('AGENT_NAME')."\n";
                            $message.= "Телефон - ".$app->getField('CONTACT_PHONE')."\n";
                            if($app->getField('RESP_COMENT'))
                                $message.= "Комментарий ответственного - ".$app->getField('RESP_COMENT')."\n";
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Деньги получены',
                                            "callback_data" => "CrewGotMoney_".$app->getID()
                                        ],
                                        [
                                            'text' => 'Деньги не получены',
                                            "callback_data" => "CrewNotGotMoney_".$app->getID()
                                        ]/*,
                                        [
                                            'text' => 'На завтра',
                                            "callback_data" => "toTomorrow_".$app->getId()
                                        ],*/
                                    ]
                                ]
                            ]);
                        } elseif ($app->getStatus() == 20) {
                            $message.= "Деньги забраны. Отвезите деньги в кассу - ".$app->cash_room()->getName();
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [

                                        [
                                            'text' => 'На завтра',
                                            "callback_data" => "toTomorrow_".$app->getId()
                                        ]
                                    ]
                                ]
                            ]);
                        } else {
                            $message.= "У вас нет прав на просмотр информации по этой заявке";
                        }
                    }
                }
                break;
            case 'cancelPayback':
                if((int)$array_data[1]>0){
                    (new Applications())->find((int)$array_data[1])->setStatus(20);
                    $message = "Возврат заявки отменен, заявка возвращена в доставку";
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }

    public static function getCommonButtons($crew_id):string
    {
        $buttons_array = [];
        $applications = new Applications();
        $list = $applications->getPaymentsAppsByCrew($crew_id);
        $buttons_array[] = ['text' => 'Выдача ('.count($list).')'];
        $applications = new Applications();
        $list1 = $applications->getGiveAppsByCrew($crew_id);
        $buttons_array[] = ['text' => 'Забор ('.count($list1).')'];
        $applications = new Applications();
        $list2 = $applications->getAppsInDeliveryByCrew($crew_id);
        $buttons_array[] = ['text' => 'В доставке ('.count($list2).')'];
        return json_encode([
            'resize_keyboard' => true,
            'keyboard' => [$buttons_array]
        ]);
    }
}