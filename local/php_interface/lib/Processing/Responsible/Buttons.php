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
use Models\ElementModel;
use Models\Operation;
use Models\Order;
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
            case 'CancelCreateApp':
                if((int)$array_data[1]>0){
                    $applications = new Applications();
                    $app = $applications->find((int)$array_data[1]);
                    if($app->getStatus()!=3){
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        Common::ResetDuringCreateAppByResponsible();
                        Operation::delete((int)$array_data[1]);
                        $markup['message'] = "Заявка №".(int)$array_data[1]." отменена";
                    }
                    $message = $markup['message'];
                }
                break;
            case 'CorrectPrevSum':
                if((int)$array_data[1]>0){
                    $app = (new Applications())->find((int)$array_data[1]);
                    if(((int)$app->getField('RESP_STEP')!=1||($app->hasBeforeApps()&&(int)$app->getField('RESP_STEP')!=2))&&$app->getField('SUM_ENTER_STEP')==0){
                        $message = Common::getWrongAppActionText();
                    } else {
                        $app->removeLastSum();
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
            case 'showApplicationForResponse':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    $message = $apps->prepareAppDataMessage($app->getId(), true);

                    if ($app->isReadyToWork()) {
                        $button_text = "Выдать деньги";
                        $callback_data = "allowAppByResp_".(int)$array_data[1];
                    } else {
                        if($app->isPayment()) {
                            $button_text = "Продолжить работу";
                            $callback_data = "restoreAppByResp_" . (int)$array_data[1];
                        }
                    }
                    $inline_keyboard = [
                        [
                            [
                                'text' => $button_text,
                                "callback_data" => $callback_data
                            ]
                        ]
                    ];
                    /*if(empty($button_text))
                        $inline_keyboard = [
                            [
                                [
                                    'text' => Common::getButtonText('resp_denie_app'),
                                    "callback_data" => "RespCancelApp_".(int)$array_data[1]
                                ],
                            ]
                        ];*/
                    $response['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => $inline_keyboard
                    ]);
                }
                break;
            case 'showDraftForResponse':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    $message = $apps->prepareAppDataMessage($app->getId(), true);
                    $inline_keyboard = [
                        [
                            [
                                'text' => 'Убрать из черновика',
                                "callback_data" => 'removeFromDraft_'.$app->getId()
                            ]
                        ]
                    ];
                    $response['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => $inline_keyboard
                    ]);
                }
                break;
            case 'NotSetRespCommentAdd':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()!=3){
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        if($app->isPayment()){
                            $app->setField('DRAFT_STEP', 7);
                            Common::ResetDuringCreateAppByResponsible();
                            Common::SetDuringAppByResponsible($app->getId());
                            $app->setField('RESP_STEP', 0);
                            $app->setField('STATUS', 15);
                            $markup = RespMarkup::getMarkupByResp($app->getId());
                        } else {
                            Common::ResetDuringCreateAppByResponsible();
                            $app->setField('DRAFT_STEP', 7);
                            $app->setReadyToWorkStatus();
                            $markup['message'] = "Заявка №" . $app->getId() . " создана";
                        }
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            case 'SetToDraftApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if ( $app->getStatus()!=15 && $app->getField('DRAFT')==1) {
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        Common::ResetDuringAppByResponsible();
                        $app->setField('DRAFT', 1);
                        $markup['message'] = "Заявка №".$app->getId()." сохранена в черновики";
                    }
                    $message = $markup['message'];
                    //$response['buttons'] = $markup['buttons'];
                }
                break;
            case 'removeFromDraft':
                if((int)$array_data[1]>0) {
                    if(Common::DuringCreateAppByResponsible()>0){
                        $markup['message'] = 'Невозможно! Вы уже создаете заявку №'.Common::DuringCreateAppByResponsible()."\nЗакончите её создание либо отмените";
                    } elseif (Common::DuringAppByResponsible()>0){
                        $during_app = (new Applications())->find(Common::DuringAppByResponsible());
                        $markup['message'] = 'Невозможно! Вы уже работаете с заявкой №'.$during_app->getId()." (".$during_app->contragent().")";
                    } else {
                        $apps = new Applications();
                        $app = $apps->find((int)$array_data[1]);
                        if ($app->getStatus() != 15 && $app->getField('DRAFT') == 1) {
                            $markup['message'] = Common::getWrongAppActionText();
                        } else {
                            Common::SetDuringAppByResponsible((int)$array_data[1]);
                            $app->setField('DRAFT', false);
                            $markup['message'] = "Заявка №" . $app->getId() . " убрана из черновиков. Продолжайте с ней работу";

                        }

                    }
                    $message = $markup['message'];
                }
                break;
            case 'NotSetRespComment':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()!=15){
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        if($app->isPayment()) {
                            $app->setField('RESP_STEP', 4);
                            $app->setStatus(45);
                            $coll_resp_markup['message'] = "Заявка на выдачу №".$app->getId()."\n";
                            $coll_resp_markup['message'].= "Необходимо забрать деньги в  ".$app->cash_room()->getName()."\n";
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
                    $app = $apps->find((int)$array_data[1]);
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
                    if(Common::DuringCreateAppByResponsible()>0){
                        $markup['message'] = 'Вы уже создаете заявку №'.Common::DuringCreateAppByResponsible();
                    } elseif (Common::DuringAppByResponsible()>0){
                        $during_app = (new Applications())->find(Common::DuringAppByResponsible());
                        $markup['message'] = 'Вы уже работаете с заявкой №'.$during_app->getId()." (".$during_app->contragent().")";
                    } else {
                        $apps = new Applications();
                        $app = $apps->find((int)$array_data[1]);
                        if ($app->isReadyToWork()) {
                            Common::SetDuringAppByResponsible((int)$array_data[1]);
                            $app->setField('RESP_STEP', 0);
                            $app->setField('STATUS', 15);
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                        } else {
                            $markup['message'] = \Settings\Common::getWrongAppActionText();
                        }
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //Продолжить работу над заявкой
            case 'restoreAppByResp':
                if((int)$array_data[1]>0) {
                    $app = (new Applications())->find((int)$array_data[1]);
                    if(Common::DuringAppByResponsible()>0&&Common::DuringAppByResponsible()!=(int)$array_data[1]){
                        $markup['message'] = "Невозможно! Закончите оформление заявки №".Common::DuringAppByResponsible();
                    } elseif ( Common::DuringCreateAppByResponsible() > 0 ) {
                        $markup['message'] = "Невозможно! Закончите создание заявки №".Common::DuringCreateAppByResponsible();
                    } else {
                        Common::SetDuringAppByResponsible($app->getId());
                        if ($app->hasBeforeApps()) {
                            if ($app->getField('RESP_STEP') == 2) {
                                $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                            } else {
                                $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                            }
                        } else {
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                        }
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //сброс отмены заявки
            case 'resetRespCancelApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
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
                    $app = $apps->find((int)$array_data[1]);
                    if((int)$app->getField('CASH_ROOM') > 0){
                        $markup['message'] = \Settings\Common::getWrongAppActionText();
                    } else {
                        $app->setField('CASH_ROOM', (int)$array_data[2]);
                        if($app->isPayment()) {
                            $app->setField('DRAFT_STEP', 3);
                            $markup = RespMarkup::getCreateAppMarkup((int)$array_data[1]);

                        } else {
                            $app->setField('DRAFT_STEP', 3);
                            $markup = RespMarkup::getCreateAppMarkup((int)$array_data[1]);
                        }
                        $message = $markup['message'];
                        $response['buttons'] = $markup['buttons'];
                    }
                }
                break;
            //установка не привязываем доп заявки
            case 'setAfterApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
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
                    $app = $apps->find((int)$array_data[1]);
                    if( $app->getStatus()!=15&&$app->getField('RESP_STEP')!=0) {
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        if($app->hasBeforeApps()){
                            $app->setField('RESP_STEP', 1);
                            $app->setStatus(44);
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                            $exists = $app->getField('GIVE_AFTER');
                            \Settings\Common::ResetDuringAppByResponsible();
                            if(ArrayHelper::checkFullArray($exists)){
                                $man_message = 'Заявка №'.$app->getId().". Выдача после №№".implode(', ', $exists);
                                Telegram::sendMessageToManagerByAppID($app->getId(), $man_message);
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
                    $app = $apps->find((int)$array_data[1]);
                    if ( $app->getStatus()!=44 ) {
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        Common::SetDuringAppByResponsible($app->getId());
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
                    $app = $apps->find((int)$array_data[1]);
                    if( $app->getStatus()!=44) {
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        Common::ResetDuringAppByResponsible();
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
                        $response['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => 'Сброс заявки',
                                        "callback_data" => "ResetRespApp_".(int)$array_data[2]
                                    ],
                                ]
                            ]
                        ]);
                    }
                }
                break;
            case 'setOperationTypeToApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if ($app->getField('OPERATION_TYPE')!=false){
                        $markup['message'] = \Settings\Common::getWrongAppActionText();
                    }else {
                        $app->setField('OPERATION_TYPE', (int)$array_data[2]);
                        $app->setField('DRAFT_STEP', 2);
                        $app->updateName();
                        $markup = RespMarkup::getCreateAppMarkup((int)$array_data[1]);
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            case 'AddMoreSum':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if(!$app->hasBeforeApps()) {
                        if ((int)$app->getField('RESP_STEP') != 1 && $app->getField('SUM_ENTER_STEP') == 0) {
                            $message = Common::getWrongAppActionText();
                        } else {
                            $text = '';
                            if (count($app->getCurrencies()) > 0 && count($app->getCurrencies()) == count($app->getSum())) {
                                $cash = $app->getCash();
                                $text = "Введенная сумма: " . implode(", ", $cash) . "\n";
                            }
                            $message = $text . 'Выберите валюту';
                            $list = $app->cash_room()->getCurrencies();
                            $inline_keys = [];
                            foreach ($list as $item) {
                                if (in_array($item, $app->getCurrencies()))
                                    continue;
                                $currencies = new Currency();
                                $item = $currencies->find($item)->getArray();
                                $inline_keys[] = [
                                    [
                                        'text' => $item['NAME'],
                                        "callback_data" => 'SetCurrencyToApp_' . $app->getId() . "_" . $item['ID']
                                    ]
                                ];
                            }
                            $inline_keys[] = [
                                [
                                    'text' => 'Сброс заявки',
                                    "callback_data" => "ResetRespApp_".$app->getId()
                                ]
                            ];
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => $inline_keys
                            ]);
                        }
                    } else {
                        if ((int)$app->getField('RESP_STEP') != 2 && $app->getField('SUM_ENTER_STEP') == 0) {
                            $message = Common::getWrongAppActionText();
                        } else {
                            $text = '';
                            if (count($app->getCurrencies()) > 0 && count($app->getCurrencies()) == count($app->getSum())) {
                                $cash = $app->getCash();
                                $text = "Введенная сумма: " . implode(", ", $cash) . "\n";
                            }
                            $message = $text . 'Выберите валюту';
                            $list = $app->cash_room()->getCurrencies();
                            $inline_keys = [];
                            foreach ($list as $item) {
                                if (in_array($item, $app->getCurrencies()))
                                    continue;
                                $currencies = new Currency();
                                $item = $currencies->find($item)->getArray();
                                $inline_keys[] = [
                                    [
                                        'text' => $item['NAME'],
                                        "callback_data" => 'SetCurrencyToApp_' . $app->getId() . "_" . $item['ID']
                                    ]
                                ];
                            }
                            $inline_keys[] = [
                                [
                                    'text' => 'Сброс заявки',
                                    "callback_data" => "ResetRespApp_".$app->getId()
                                ]
                            ];
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => $inline_keys
                            ]);
                        }
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
                            $app->setForColResp();
                            $contact_message = "По заявке №".$app->getId()." ожидайте звонка от службы доставки";
                            $message_to_cash_room['message'] = "Выдать ".$app->getField("AGENT_OFF_NAME")." ".implode(', ', $cash).". №".$app->getId().'. ('.$app->cash_room()->getName().')';
                            $message_to_channel = "Выдать ".$app->getField("AGENT_OFF_NAME")." ".implode(', ', $cash);

                            (new Order())->createFromAppID($app->getId());
                            //Mattermost::send($message_to_channel);
                            Telegram::sendCommonMessageToCashRoom($message_to_cash_room);
                            Telegram::sendMessageToCollResp($app->prepareAppDataMessage($app->getField('ID')), $app->getField('ID'));

                            try {
                                \Api\Sender::send($app, $contact_message);
                            } catch (Exception $exception) {

                            }

                            $message = "Заявка №".$app->getId()." оформлена и передана ответственному за инкассацию";
                            $response['buttons'] = Buttons::getMenuButtons();
                            Common::ResetDuringAppByResponsible();
                        } else {
                            $message = Common::getWrongAppActionText();
                        }
                    } else {
                        $app->setField("RESP_STEP", 2);
                        $app->setReadyToWorkStatus();
                        $cash = $app->getCash();
                        $app->setForColResp();
                        $contact_message = "По заявке №".$app->getId()." ожидайте звонка от службы доставки";
                        $message_to_cash_room['message'] = "Выдать ".$app->getField("AGENT_OFF_NAME")." ".implode(', ', $cash).". №".$app->getId().". (".$app->cash_room()->getName().")";
                        (new Order())->createFromAppID($app->getId());
                        $message_to_channel = "Выдать ".$app->getField("AGENT_OFF_NAME")." ".implode(', ', $cash);
                        //Mattermost::send($message_to_channel);
                        Telegram::sendCommonMessageToCashRoom($message_to_cash_room);
                        Telegram::sendMessageToCollResp($app->prepareAppDataMessage($app->getField('ID')), $app->getField('ID'));

                        try {
                            \Api\Sender::send($app, $contact_message);
                        } catch (Exception $exception) {

                        }
                        Common::ResetDuringAppByResponsible();
                        $message = "Заявка №".$app->getId()." оформлена и передана ответственному за инкассацию";
                        $response['buttons'] = Buttons::getMenuButtons();
                    }
                }
                break;
            case 'ResetRespApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    (new Applications())->find((int)$array_data[1])->resetApp();
                    if($app->hasBeforeApps())
                        $message = "Заявка ".$app->getFullName()." была сброшена и возвращена в раздел 'Заявки в работе'";
                    else
                        $message = "Заявка ".$app->getFullName()." была сброшена и возвращена в раздел 'Новые заявки'";
                }
                break;

        }
        $response['message'] = $message;
        return $response;
    }

    public static function getMenuButtons()
    {
        $buttons_array = [];
        $buttons_array[] = ['text' => 'Черновики'];
        $buttons_array[] = ['text' => 'Создать заявку'];
        $buttons_array[] = ['text' => Common::getButtonText('resp_apps_list_to_work')];
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