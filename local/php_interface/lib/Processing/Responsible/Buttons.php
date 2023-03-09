<?php
namespace Processing\Responsible;
use Api\Telegram;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\Crew;
use Processing\Responsible\Markup as RespMarkup;
use Settings\Common;

class Buttons
{
    public static function process($data): array
    {
        $message = Common::getWrongCallBackData();
        $array_data = explode('_', $data);
        $markup['buttons'] = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [
                    [
                        'text' => "Новые заявки"
                    ],
                    [
                        'text' => Common::getButtonText('resp_apps_list_new')
                    ],
                    [
                        'text' => Common::getButtonText('resp_cash_room_list')
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
                    $app = $apps->find((int)$array_data[1]);
                    $message = $apps->prepareAppDataMessage($app->getId());

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
                            $app->setField('RESP_STEP', 4);
                            if($app->hasBeforeApps()){
                                $app->setStatus(44);
                                $markup['message'] = "Заявка оформлена и ожидает выполнения других заявок";
                            }else{
                                $app->setStatus(45);
                                $coll_resp_markup['message'] = "Заявка на выдачу №".$app->getId()."\n";
                                $coll_resp_markup['message'].= "Для продолжения выполнения заявки выберите экипаж";
                                $crews = new Crew();
                                $crew_list = [];
                                $list = $crews->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();

                                if (ArrayHelper::checkFullArray($list)) {

                                    foreach ($list as $crew) {
                                        $crew_list[] = [
                                            'text' => $crew['NAME'],
                                            "callback_data" => "setCrewToApp_".$app->getId().'_'.$crew['ID']

                                        ];
                                    }
                                }

                                $buttons = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => [$crew_list]
                                ]);
                                Telegram::sendMessageToCollResp($coll_resp_markup['message'], 0, $buttons);
                                $markup['message'] = "Заявка №".$app->getId()." оформлена и ожидает установки экипажа ответственным за инкассацию";
                            }

                        }
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];

                }
                break;
            //нажатие кнопки отклонить
            case 'RespCancelApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if($app->isInRefinement()||$app->isReadyToWork()) {
                        $app->setToRespCancelComent();
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
                    if( $app->getStatus()!=15&&$app->getStatus()!=43 ) {
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        if ($app->isPayment()) {
                            $app->setField('CASH_ROOM', (int)$array_data[2]);
                            if($app->hasBeforeApps()) {
                                $app->setField('RESP_STEP', 2);
                            } else {
                                $app->setField('RESP_STEP', 3);
                            }
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                            if(!$app->hasBeforeApps()) {
                                $cash_room_cash = $app->cash_room()->getCash();
                                if($cash_room_cash['free']<$app->getSum()){
                                    $markup['message']="Свободная сумма в кассе меньше суммы в заявке\n\n".$markup['message'];
                                }
                            }
                        } else {
                            $app->setField('CASH_ROOM', (int)$array_data[2]);
                            $coll_resp_markup['message'] = "Заявка №".$app->getId()." оформлена\n";
                            Telegram::sendMessageToCollResp($coll_resp_markup['message']);
                            $app->setCompleteFromResp();
                            $markup['message'] = "Касса сохранена в заявку";
                        }

                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //установка не привязываем доп заявки
            case 'setAfterApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if( $app->getStatus()!=15&&$app->getField('RESP_STEP')!=0) {
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        $app->setBeforeApp((int)$array_data[2]);
                        $markup = Markup::getMoreRespGiveAfterMarkup((int)$array_data[1]);
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //установка не привязываем доп заявки
            case 'NotSetAfterApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if( $app->getStatus()!=15&&$app->getField('RESP_STEP')!=0) {
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        $app->setField('RESP_STEP', 1);
                        $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //не ждём
            case 'GiveMoney':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if( $app->getStatus()!=44) {
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        $app->setField('RESP_STEP', 5);
                        $app->setStatus(15);
                        $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                        /*$coll_resp_markup['message'] = "Заявка на выдачу №".$app->getId()."\n";
                        $coll_resp_markup['message'].= "Для продолжения выполнения заявки выберите экипаж";
                        $crews = new Crew();
                        $crew_list = [];
                        $list = $crews->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();

                        if (ArrayHelper::checkFullArray($list)) {

                            foreach ($list as $crew) {
                                $crew_list[] = [
                                    'text' => $crew['NAME'],
                                    "callback_data" => "setCrewToApp_".$app->getId().'_'.$crew['ID']

                                ];
                            }
                        }

                        $buttons = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => [$crew_list]
                        ]);
                        Telegram::sendMessageToCollResp($coll_resp_markup['message'], 0, $buttons);
                        $markup['message'] = "Заявка оформлена и ожидает установки экипажа ответственным за инкассацию";*/
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //ждём
            case 'waitMore':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if( $app->getStatus()!=44) {
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        $markup['message'] = "Заявка ожидает выполнения других заявок";
                        $response['buttons'] = $markup['buttons'];
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