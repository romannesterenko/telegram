<?php
namespace Processing\Collector;
use Api\Telegram;
use Models\Applications;
use Processing\Responsible\Markup as RespMarkup;
use Settings\Common;

class Buttons
{
    public static function process($data): array
    {
        $message = Common::getWrongCallBackData();
        $array_data = explode('_', $data);
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [
                    [
                        'text' => 'Заявки в доставке'
                    ],
                    [
                        'text' => 'Заявки на забор'
                    ]
                ]
            ]
        ]);
        switch ($array_data[0]){
            //Кнопка экипажу "Взять в работу"
            case 'AllowAppByCrew':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if ($app->getStatus()!=25){
                        $message = Common::getWrongAppActionText();
                    } else {
                        $app->setCrew((int)$array_data[2]);
                        //Экипаж одобрил заявку и ставим статус "Назначена"
                        $app->setStatus(23);
                        if ($app->isPayment()) {
                            $message = "Заявка принята в работу. \nЗаберите сумму в размере - " . number_format($app->getSum(), '0', '.', ' ') . " в точке выдачи - " . $app->cash_room()->getName();
                        } else {
                            $message = "Заявка принята в работу. \nЗаберите деньги в ".$app->getField('TIME')." по адресу - " . $app->getField('ADDRESS');
                            $contact_message = "Заявка №".$app->getId()."\nК вам выехал экипаж инкассаторов для забора денег.";
                            \Api\Sender::send($app, $contact_message);
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
                                        ],
                                    ]
                                ]
                            ]);
                        }
                    }

                }
                break;
            //Экипаж забрал деньги по адресу
            case 'CrewGotMoney':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()!=23){
                        $message = \Settings\Common::getWrongAppActionText();
                    } else {
                        //Экипаж одобрил заявку и ставим стату "Назначена"
                        $app->setInDeliveryStatus();
                        $message = "Деньги забраны. Отвезите деньги в кассу - " . $app->cash_room()->getName();
                        //сообщения менеджеру и ответственному об изменении статуса
                        $markup['message'] = "Информация по заявке №" . $app->getId() . "\n";
                        $markup['message'] .= "Экипаж <b>" . $app->crew()->getName() . "</b> забрал деньги по адресу <b>" . $app->getField('ADDRESS') . "</b> и везет их в кассу <b>" . $app->cash_room()->getName() . "</b>\n";
                        Telegram::sendMessageToManager($markup, (int)$array_data[1]);
                        if($app->isPayment())
                            Telegram::sendMessageToResp($markup['message']);
                        else
                            Telegram::sendMessageToCollResp($markup['message']);
                    }
                }
                break;
            //Экипаж не забрал деньги по адресу
            case 'CrewNotGotMoney':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()!=23){
                        $message = \Settings\Common::getWrongAppActionText();
                    } else {
                        //Экипаж одобрил заявку и ставим статус "Проблема"
                        $app->setProblemStatus();
                        $message = "Деньги не забраны. Заявка помечена как проблемная.";
                        //сообщения менеджеру и ответственному об изменении статуса
                        $markup['message'] = "Информация по заявке №" . $app->getId() . "\n";
                        $markup['message'] .= "Экипаж <b>" . $app->crew()->getName() . "</b> не забрал деньги по адресу <b>" . $app->getField('ADDRESS') . "</b>\nЗаявка помечена как проблемная.";
                        Telegram::sendMessageToManager($markup, (int)$array_data[1]);
                        if($app->isPayment())
                            Telegram::sendMessageToResp($markup['message']);
                        else
                            Telegram::sendMessageToCollResp($markup['message']);
                    }
                }
                break;
            case 'CompleteApp':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->isComplete()||$app->isFailed()){
                        $message = \Settings\Common::getWrongAppActionText();
                    } else {
                        $app->setComplete();
                        $markup['message'] = "Заявка №" . (int)$array_data[1] . " была успешно выполнена";
                        Telegram::sendMessageToManager($markup, (int)$array_data[1]);
                        if($app->isPayment())
                            Telegram::sendMessageToResp($markup['message']);
                        else
                            Telegram::sendMessageToCollResp($markup['message']);
                        $contact_message = "Заявка №".$app->getId()." выполнена. Спасибо за работу";
                        \Api\Sender::send($app, $contact_message);
                        $message = "Заявка №" . $app->getID() . " выполнена";
                    }
                }
                break;
            case 'InCompleteApp':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()!=23){
                        $message = \Settings\Common::getWrongAppActionText();
                    } else {
                        $app->setFailed();
                        $message = "Заявка №" . $app->getID() . " не выполнена";
                        $markup['message'] = "Заявка №" . (int)$array_data[1] . " не была выполнена";
                        Telegram::sendMessageToManager($markup, (int)$array_data[1]);
                        if($app->isPayment())
                            Telegram::sendMessageToResp($markup['message']);
                        else
                            Telegram::sendMessageToCollResp($markup['message']);
                    }
                }
                break;
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
                        } else {
                            $message.= "Касса - ".$app->cash_room()->getName()."\n";
                            $message.= "Контактное лицо - ".$app->getField('AGENT_NAME')."\n";
                            $message.= "Телефон - ".$app->getField('CONTACT_PHONE')."\n";
                            $message.= "Сумма - ".number_format($app->getRealSum(), '0', '.', ' ')."\n";
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Деньги переданы',
                                            "callback_data" => "CompleteApp_".$app->getID()
                                        ],
                                        [
                                            'text' => 'Деньги не переданы',
                                            "callback_data" => "InCompleteApp_".$app->getID()
                                        ],
                                    ]
                                ]
                            ]);
                        }

                    } else {
                        if($app->getStatus()==23){
                            $message.= "Заберите деньги в ".$app->getField('TIME')." по адресу - " . $app->getField('ADDRESS');
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
                                        ],
                                    ]
                                ]
                            ]);
                        } elseif ($app->getStatus() == 20) {
                            $message.= "Деньги забраны. Отвезите деньги в кассу - ".$app->cash_room()->getName();
                        } else {
                            $message.= "У вас нет прав на просмотр информации по этой заявке";
                        }
                    }
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }
}