<?php
namespace Processing\CollectorsResponsible;
use Models\Applications;
use Processing\CollectorsResponsible\Markup as RespMarkup;
use Settings\Common;

class Buttons
{
    public static function process($data): array
    {
        $message = Common::getWrongCallBackData();
        $array_data = explode('_', $data);
        switch ($array_data[0]){
            case 'showApplicationForResponse':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $message = $apps->prepareAppDataMessage((int)$array_data[1]);
                    if($apps->find((int)$array_data[1])->isNew()){
                        $button_text = Common::getButtonText('resp_allow_app');
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
                            $app->setField('RESP_STEP', 4);
                        }else{
                            $app->setField('RESP_STEP', 5);
                        }
                        $markup = RespMarkup::getRespCompleteAppMarkup('');
                    }

                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                    $app->setCompleteFromResp();
                }
                break;
            //нажатие кнопки отклонить
            case 'RespCancelApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if($app->getStatus()==4) {
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
                    if($app->getStatus()==4) {
                        $app->setField('RESP_STEP', 0);
                        $app->setField('STATUS', 15);
                        $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                    } else {
                        $markup['message'] = \Settings\Common::getWrongAppActionText();
                    }
                    $message = $markup['message'];
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
                    if($app->getStatus()==13) {
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
                    if($app->getStatus()!=25&&$app->getStatus()!=15){
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        $app->setField('CREW', (int)$array_data[2]);
                        if($app->getStatus()==25){
                            $markup['message'] = 'Экипаж изменен';
                            $app->setCompleteFromResp();
                        } else {
                            if ($app->isPayment())
                                $app->setField('RESP_STEP', 3);
                            else
                                $app->setField('RESP_STEP', 5);
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                        }
                    }


                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }
}