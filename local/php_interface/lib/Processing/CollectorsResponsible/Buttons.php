<?php
namespace Processing\CollectorsResponsible;
use Api\Telegram;
use Exception;
use Helpers\LogHelper;
use Models\Applications;
use Models\Order;
use Processing\CollectorsResponsible\Markup as RespMarkup;
use Processing\Collector\Markup as CollectorMarkup;
use Settings\Common;

class Buttons
{
    public static function process($data): array
    {
        $message = Common::getWrongCallBackData();
        $array_data = explode('_', $data);
        $buttons = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [
                    [
                        'text' => "Заявки в работу"
                    ],
                    [
                        'text' => Common::getButtonText('resp_apps_list_new')
                    ]
                ]
            ]
        ]);

        switch ($array_data[0]){
            case 'setToRefinement':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->isReadyToWork()){
                        $app->setInRefinementStatus();
                        $message = "Заявка №".$app->getId()." принята в работу, уточните данные и продолжите оформление заявки";
                        $response['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Создать ордер",
                                        "callback_data" => "allowAppByResp_".(int)$array_data[1]
                                    ],

                                    [
                                        'text' => Common::getButtonText('resp_denie_app'),
                                        "callback_data" => "RespCancelApp_".(int)$array_data[1]
                                    ],
                                ]
                            ]
                        ]);
                    }
                }
                break;
            case 'showApplicationForResponse':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $message = $apps->prepareAppDataMessage((int)$array_data[1]);
                    $app = $apps->find((int)$array_data[1]);
                    if($app->isReadyToWork()){
                        $button_text = Common::getButtonText('resp_allow_app');
                        $callback_data = "setToRefinement_".(int)$array_data[1];
                    } elseif ($app->isInRefinement()) {
                        $button_text = "Создать ордер";
                        $callback_data = "allowAppByResp_".(int)$array_data[1];
                    } else {
                        $button_text = "Продолжить работу";
                        $callback_data = "restoreAppByResp_".(int)$array_data[1];
                    }
                    $response['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => $button_text,
                                    "callback_data" => $callback_data
                                ],

                                [
                                    'text' => Common::getButtonText('resp_denie_app'),
                                    "callback_data" => "RespCancelApp_".(int)$array_data[1]
                                ],
                            ]
                        ]
                    ]);
                }
                break;
            case 'NotSetRespComment':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if($app->getStatus()!=15){
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        if($app->isPayment()) {
                            $app->setStatus(25);
                            $app->setField('RESP_STEP', 7);
                            $markup['message'] = "Информация по заявке №".$app->getId()." сохранена. Ожидаем подтверждения экипажем.";
                            $collector_markup = CollectorMarkup::getMarkupByCollector($app->getId(), $app->crew()->getId(), 'new_app');
                            Telegram::sendMessageToCollector($app->crew()->getId(), $collector_markup);
                        }else{
                            $app->setStatus(25);
                            $app->setField('RESP_STEP', 4);
                            $markup['message'] = "Информация по заявке №".$app->getId()." сохранена. Ожидаем подтверждения экипажем.";
                            $collector_markup = CollectorMarkup::getMarkupByCollector($app->getId(), $app->crew()->getId(), 'new_app');
                            Telegram::sendMessageToCollector($app->crew()->getId(), $collector_markup);

                            $manager_text = "По заявке №".$app->getId()." планируемое время забора от контрагента ".$app->getField('AGENT_OFF_NAME')." - ".$app->getTime();
                            Telegram::sendCommonMessageToManager($manager_text);
                            Telegram::sendMessageToResp($manager_text);

                            $contact_message = "По заявке №".$app->getId()." планируемое время забора  - ".$app->getTime().". Планируемое место забора - ".$app->getAddress();
                            try {
                                \Api\Sender::send($app, $contact_message);
                            }catch (Exception $exception){

                            }
                            $markup['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'keyboard' => [
                                    [
                                        [
                                            'text' => "Заявки в работу"
                                        ],
                                        [
                                            'text' => Common::getButtonText('resp_apps_list_new')
                                        ]
                                    ]
                                ]
                            ]);
                        }
                        /*$cash_resp_markup = RespMarkup::getNeedSetCashRoomByAppMarkup($app->getId());
                        Telegram::sendMessageToCashResp($cash_resp_markup);*/
                    }

                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                    //$app->setCompleteFromResp();
                }
                break;
            //нажатие кнопки отклонить
            case 'RespCancelApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if($app->isInRefinement()||$app->isReadyToWork()) {
                        $app->setToCollRespCancelComent();
                        $message = "Введите причину отмены заявки №" . (int)$array_data[1] . ", или отмените это действие";
                        $buttons = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => Common::getButtonText('resp_reset_cancel_app'),
                                        'callback_data' => 'resetRespCancelApp_'.(int)$array_data[1]
                                    ],

                                ]
                            ]
                        ]);
                    } else {
                        $message = Common::getWrongAppActionText();
                    }

                    $response['buttons'] = $buttons;
                }
                break;
            //одобрение заявки в работу
            case 'allowAppByResp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if($app->isInRefinement()) {
                        if($app->isPayment()&&$app->getField('RESP_STEP')==2){
                            $app->setField('RESP_STEP', 3);
                        } else {
                            $app->setField('RESP_STEP', 0);
                        }
                        $app->setField('STATUS', 15);
                        $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                    } else {
                        $markup['message'] = \Settings\Common::getWrongAppActionText();
                    }
                    $message = $markup['message'];
                    if(!$markup['buttons'])
                        $markup['buttons'] = $buttons;
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //Продолжить работу над заявкой
            case 'restoreAppByResp':
                if((int)$array_data[1]>0) {
                    $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //сброс отмены заявки
            case 'resetRespCancelApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    //возвращаем статус, который был на момент отмены
                    if($app->getStatus()==40) {
                        $app->setField('STATUS', $app->getField('BEFORE_RESP_CANCEL_STATUS'));
                        $message = "Процесс отмены заявки №" . (int)$array_data[1] . " был сброшен";
                    }else{
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            //установка кассы
            case 'setCashRoomToApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if($app->cash_room()->getId()>0){
                        $markup['message'] = Common::getWrongAppActionText();
                    }else {
                        $app->setField('CASH_ROOM', (int)$array_data[2]);
                        if ($app->isPayment())
                            $app->setField('RESP_STEP', 2);
                        else
                            $app->setField('RESP_STEP', 4);
                        $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //установка экипажа
            case 'setCrewToApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if( $app->isPayment() ) {
                        if($app->getStatus()!=15&&$app->getStatus()!=45){
                            $markup['message'] = Common::getWrongAppActionText();
                        } else {
                            $app->setField('CREW', (int)$array_data[2]);
                            $app->setField('RESP_STEP', 6);
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                            /*$markup['message'] = "Экипаж назначен.\nЗаявка №".$app->getId()." оформлена";
                            $app->setField('CREW', (int)$array_data[2]);
                            $collector_markup = CollectorMarkup::getMarkupByCollector($app->getId(), (int)$array_data[2], 'new_app');
                            Telegram::sendMessageToCollector((int)$array_data[2], $collector_markup);
                            $app->setField('STATUS', 25, true);
                            $order = new Order();
                            $order->createFromApp($app);*/
                        }

                    } else {
                        if($app->getStatus()!=15&&$app->getStatus()!=45){
                            $markup['message'] = Common::getWrongAppActionText();
                        } else {
                            if($app->getStatus()==15) {
                                //$markup['message'] = "Экипаж назначен.\nЗаявка №".$app->getId()." оформлена";
                                $app->setField('CREW', (int)$array_data[2]);
                                $app->setField('RESP_STEP', 3);

                                $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                                //$app->setField('STATUS', 25, true);
                                //$order = new Order();
                                //$order->createFromApp($app);
                            }else{
                                $app->setField('CREW', (int)$array_data[2]);
                                $markup['message'] = 'Экипаж изменен';
                                //$app->setCompleteFromResp();
                            }
                        }
                    }
/*

                    if($app->getStatus()!=25&&$app->getStatus()!=15){
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        $app->setField('CREW', (int)$array_data[2]);
                        if($app->getStatus()==25){
                            $markup['message'] = 'Экипаж изменен';
                            $app->setCompleteFromResp();
                        } else {
                            $app->setField('RESP_STEP', 4);
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                        }
                    }*/
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }
}