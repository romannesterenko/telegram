<?php
namespace Processing\Responsible;
use Api\Mattermost;
use Api\Telegram;
use Exception;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\Crew;
use Models\Currency;
use Processing\Responsible\Markup as RespMarkup;
use Settings\Common;

class Buttons
{
    public static function process($data): array
    {
        $message = Common::getWrongCallBackData();
        $array_data = explode('_', $data);
        $markup['buttons'] = self::getMenuButtons();
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
                                        'text' => "Одобрить выдачу",
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
                    $message = $apps->prepareAppDataMessage($app->getId(), true);

                    if($app->isReadyToWork()){
                        $button_text = Common::getButtonText('resp_allow_app');
                        $callback_data = "setToRefinement_".(int)$array_data[1];
                    } elseif ($app->isInRefinement()) {
                        $button_text = "Выдать деньги";
                        $callback_data = "allowAppByResp_".(int)$array_data[1];
                    } else {
                        if($app->isPayment()&&$app->getField('RESP_STEP')==1) {
                            $button_text = "Продолжить работу";
                            $callback_data = "restoreAppByResp_" . (int)$array_data[1];
                        }
                    }
                    $inline_keyboard = [
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
                    ];
                    if(empty($button_text))
                        $inline_keyboard = [
                            [
                                [
                                    'text' => Common::getButtonText('resp_denie_app'),
                                    "callback_data" => "RespCancelApp_".(int)$array_data[1]
                                ],
                            ]
                        ];
                    $response['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => $inline_keyboard
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
                            $app->setStatus(45);
                            $coll_resp_markup['message'] = "Заявка на выдачу №".$app->getId()."\n";
                            $coll_resp_markup['message'].= "Необходимо забрать деньги в точке выдачи ".$app->cash_room()->getName()."\n";
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

                        } else {
                            $app->setField('RESP_STEP', 4);
                            $app->setStatus(45);
                            $coll_resp_markup['message'] = "Заявка на забор №".$app->getId()."\n";
                            $coll_resp_markup['message'].= "Необходимо забрать деньги в ".$app->getTime()." по адресу ".$app->getAddress()."\n";
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
                        $before_apps = new Applications();
                        $before_app = $before_apps->find((int)$array_data[2]);
                        //$man_message = 'Заявка на забор №'.$before_app->getId()." была назначена ответственным за учет, как обязательная для выполнении заявки на выдачу №".$app->getId();
                        $app->setBeforeApp((int)$array_data[2]);
                        //Telegram::sendCommonMessageToManager($man_message);
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
                        if($app->hasBeforeApps()){
                            $app->setField('RESP_STEP', 1);
                            $app->setStatus(44);
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                            $exists = $app->getField('GIVE_AFTER');
                            if(ArrayHelper::checkFullArray($exists)){
                                $man_message = 'Заявка №'.$app->getId().". Выдача после №".implode(', ', $exists);
                                Telegram::sendCommonMessageToManager($man_message);
                                Telegram::sendMessageToCollResp($man_message);
                            }
                        } else {
                            $app->setField('RESP_STEP', 1);
                            $app->setStatus(15);
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                        }

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
                    if ( $app->getStatus()!=44 ) {
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        $app->setField('RESP_STEP', 2);
                        $app->setStatus(15);
                        $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
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
            case 'SetCurrencyToApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if((int)$app->getField('SUM_ENTER_STEP')!=0){
                        $message = Common::getWrongAppActionText();
                    } else {
                        $app->setCurrency((int)$array_data[2]);
                        $app->setField('SUM_ENTER_STEP', 1);
                        $currencies = new Currency();
                        $currency = $currencies->find((int)$array_data[2]);
                        $message = 'Введите сумму в валюте ' . $currency->getName();
                    }
                }
                break;
            case 'AddMoreSum':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if((int)$app->getField('RESP_STEP')!=1&&$app->getField('SUM_ENTER_STEP')==0){
                        $message = Common::getWrongAppActionText();
                    } else {
                        $message = 'Выберите валюту';

                        $list = $app->cash_room()->getCurrencies();
                        $inline_keys = [];
                        foreach ($list as $item) {
                            $currencies = new Currency();
                            $item = $currencies->find($item)->getArray();
                            $inline_keys[] = [
                                [
                                    'text' => $item['NAME'],
                                    "callback_data" => 'SetCurrencyToApp_' . $app->getId()."_".$item['ID']
                                ]
                            ];
                        }
                        $response['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => $inline_keys
                        ]);
                    }
                }
                break;
            case 'CompleteAddSum':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if((int)$app->getField('RESP_STEP')!=1){
                        if((int)$app->getField('RESP_STEP')==2&&$app->hasBeforeApps()){
                            $app->setField("RESP_STEP", 2);
                            $app->setReadyToWorkStatus();
                            $cash = $app->getCash();
                            $contact_message = "По заявке №".$app->getId()." ожидайте звонка от службы доставки";
                            $message_to_cash_room['message'] = "Выдать ".$app->getField("AGENT_OFF_NAME")." ".implode(', ', $cash).". №".$app->getId();
                            $message_to_channel = "Выдать ".$app->getField("AGENT_OFF_NAME")." ".implode(', ', $cash);
                            Mattermost::send($message_to_channel);
                            Telegram::sendCommonMessageToCashRoom($message_to_cash_room);
                            Telegram::sendMessageToCollResp($app->prepareAppDataMessage($app->getField('ID')), $app->getField('ID'));

                            try {
                                \Api\Sender::send($app, $contact_message);
                            } catch (Exception $exception) {

                            }

                            $message = "Заявка №".$app->getId()." оформлена и передана ответственному за инкассацию";
                            $response['buttons'] = Buttons::getMenuButtons();
                        } else {
                            $message = Common::getWrongAppActionText();
                        }
                    } else {
                        $app->setField("RESP_STEP", 2);
                        $app->setReadyToWorkStatus();
                        $cash = $app->getCash();
                        $contact_message = "По заявке №".$app->getId()." ожидайте звонка от службы доставки";
                        $message_to_cash_room['message'] = "Выдать ".$app->getField("AGENT_OFF_NAME")." ".implode(', ', $cash).". №".$app->getId();
                        $message_to_channel = "Выдать ".$app->getField("AGENT_OFF_NAME")." ".implode(', ', $cash);
                        Mattermost::send($message_to_channel);
                        Telegram::sendCommonMessageToCashRoom($message_to_cash_room);
                        Telegram::sendMessageToCollResp($app->prepareAppDataMessage($app->getField('ID')), $app->getField('ID'));

                        try {
                            \Api\Sender::send($app, $contact_message);
                        } catch (Exception $exception) {

                        }

                        $message = "Заявка №".$app->getId()." оформлена и передана ответственному за инкассацию";
                        $response['buttons'] = Buttons::getMenuButtons();
                    }
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }

    public static function getMenuButtons()
    {
        $buttons_array = [];
        $applications = new Applications();
        //$list = $applications->getToWorkAppsForCashResp();
        //if(ArrayHelper::checkFullArray($list))
            $buttons_array[] = ['text' => Common::getButtonText('resp_apps_list_to_work')];
        $applications = new Applications();
        //$list = $applications->getAppsForResp();
        //if(ArrayHelper::checkFullArray($list))
            $buttons_array[] = ['text' => Common::getButtonText('resp_apps_list_new')];
        $buttons_array[] = ['text' => Common::getButtonText('resp_cash_room_list')];
        return json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                $buttons_array
            ]
        ]);
    }
}