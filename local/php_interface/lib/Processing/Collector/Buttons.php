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
                    if ($app->getStatus()!=25){
                        $message = Common::getWrongAppActionText();
                    } else {
                        $app->setCrew((int)$array_data[2]);
                        //Экипаж одобрил заявку и ставим статус "Назначена"
                        $app->setStatus(23);
                        if ($app->isPayment()) {
                            $order = new Order();
                            $order->createFromAppID($app->getId());
                            $message = "Заявка принята в работу. \nЗаберите деньги в точке выдачи - <b>" . $app->cash_room()->getName()."</b> и отвезите по адресу <b>" . $app->getField('ADDRESS')."</b>\n";
                            $message.= "Контактное лицо - ".$app->getField('AGENT_NAME')."\n";
                            $message.= "Телефон - ".$app->getField('CONTACT_PHONE')."\n";
                            $response['buttons'] = Buttons::getCommonButtons($app->crew()->getId());
                        } else {
                            $message = "Заявка №".$app->getId()." принята в работу. \nЗаберите деньги в  <b>".$app->getField('TIME')."</b>, по адресу <b>" . $app->getField('ADDRESS')."</b>, и отвезите в кассу <b>".$app->cash_room()->getName()."</b>\n";
                            $message.= "Контактное лицо - ".$app->getField('AGENT_NAME')."\n";
                            $message.= "Телефон - ".$app->getField('CONTACT_PHONE')."\n";
                            /*$contact_message = "Заявка №".$app->getId()."\nК вам выехал экипаж инкассаторов для забора денег.";
                            \Api\Sender::send($app, $contact_message);*/
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
                        $markup['message'] .= "Деньги от контрагента <b>" . $app->getField('AGENT_OFF_NAME') . "</b> в доставке\n";
                        Telegram::sendCommonMessageToManager($markup['message']);
                        Telegram::sendMessageToResp($markup['message']);
                        $response['buttons'] = Buttons::getCommonButtons($app->crew()->getId());
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
                        $message = "Деньги не получены. Введите причину невозможности получения денег";
                        $app->setStatus(56);
                        /*//Экипаж одобрил заявку и ставим статус "Проблема"
                        $app->setProblemStatus();
                        $message = "Деньги не получены. Заявка помечена как проблемная.";
                        //сообщения менеджеру и ответственному об изменении статуса
                        $markup['message'] = "Информация по заявке №" . $app->getId() . "\n";
                        $markup['message'] .= "Экипаж <b>" . $app->crew()->getName() . "</b> не забрал деньги по адресу <b>" . $app->getField('ADDRESS') . "</b>\nЗаявка помечена как проблемная.";
                        Telegram::sendMessageToManager($markup, (int)$array_data[1]);
                        if($app->isPayment())
                            Telegram::sendMessageToResp($markup['message']);
                        else
                            Telegram::sendMessageToCollResp($markup['message']);*/
                    }
                }
                break;
            //Экипаж передал деньги
            case 'CompleteApp':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->isComplete()||$app->isFailed()){
                        $message = \Settings\Common::getWrongAppActionText();
                    } else {
                        $app->setComplete();
                        $markup['message'] = $app->getField("AGENT_OFF_NAME").". №" . (int)$array_data[1] . ". Заявка выполнена";

                        Telegram::sendMessageToManager($markup, (int)$array_data[1]);
                        \Api\Sender::send($app, $markup['message']);
                        $message = "Заявка №" . $app->getID() . " выполнена";
                        $response['buttons'] = Buttons::getCommonButtons($app->crew()->getId());
                    }
                }
                break;
            //Экипаж не передал деньги
            case 'InCompleteApp':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if( $app->getStatus()!=23&&$app->getStatus()!=20 ){
                        $message = \Settings\Common::getWrongAppActionText();
                    } else {
                        $message = "Деньги не переданы. Введите причину невозможности передачи денег";
                        $app->setStatus(55);
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
                        } elseif ($app->getStatus()==23) {
                            $message = "Заявка №".$app->getId().". Выдача\nЗабрать сумму в размере <b>".number_format($app->getSum(), 0, '', ' ')."</b> в точке выдачи - ".$app->cash_room()->getName();
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
                                    ]
                                ]
                            ]);
                        }
                    } else {
                        if($app->getStatus()==23){
                            $message.= "Заберите сумму в размере <b>".number_format($app->getSum(), 0, '', ' ')."</b>, назначенное время - <b>".$app->getField('TIME')."</b>, по адресу - <b>" . $app->getField('ADDRESS')."</b>, и отвезите в кассу <b>".$app->cash_room()->getName()."</b>\n";
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

    public static function getCommonButtons($crew_id)
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