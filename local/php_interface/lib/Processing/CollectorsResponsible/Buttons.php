<?php
namespace Processing\CollectorsResponsible;
use Api\Telegram;
use Exception;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\Crew;
use Models\ElementModel;
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
            case 'todayApps':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0){
                    $all_list = (new Applications())->where('PROPERTY_FOR_COL_RESP', 1)->where('>DATE_CREATE', date('d.m.Y 00:00:01'))->where('<DATE_CREATE', date('d.m.Y 23:59:59'))->buildQuery()->getArray();
                    $list = (new Applications())->where('PROPERTY_FOR_COL_RESP', 1)->where('>DATE_CREATE', date('d.m.Y 00:00:01'))->where('<DATE_CREATE', date('d.m.Y 23:59:59'))->setLimit($array_data[1])->setPage($array_data[2])->buildQuery()->getArray();
                    $full_keyboard = [];
                    if(ArrayHelper::checkFullArray($list)){
                        $message = "Список заявок за сегодня\n";
                        foreach ($list as $application) {
                            $full_keyboard[] = [
                                [
                                    'text' => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. №'.$application['ID'],
                                    "callback_data" => "showCollectorsApplicationForResponse_".$application['ID']
                                ]
                            ];
                        }
                        $all_count = count($all_list);
                        $current_count = count($list);
                        if($current_count<$array_data[1]){
                            if($array_data[2]>1){
                                $prev_step = (int)$array_data[2]-1;
                                $next_step = (int)$array_data[2]+1;
                                $menu = $array_data[1]==10?100:10;
                                if($array_data[1]>count($list)){
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "todayApps_".$array_data[1]."_".$prev_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Показать ".$menu,
                                        "callback_data" => "todayApps_".$menu."_1"
                                    ];
                                    $full_keyboard[] = $keyboard;
                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $full_keyboard
                                    ]);
                                } else {
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "todayApps_".$array_data[1]."_".$prev_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Следующие ".$array_data[1],
                                        "callback_data" => "todayApps_".$array_data[1]."_".$next_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Показать ".$menu,
                                        "callback_data" => "todayApps_".$menu."_1"
                                    ];
                                    $full_keyboard[] = $keyboard;
                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $full_keyboard
                                    ]);
                                }
                            } else {
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            }
                        } else {
                            if($all_count>$current_count){
                                $menu = $array_data[1]==10?100:10;
                                $next_step = $array_data[2]+1;
                                $keyboard = [];
                                if($array_data[2]>1){
                                    $prev_step = $array_data[2]-1;
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "todayApps_".$array_data[1]."_".$prev_step
                                    ];
                                }
                                $keyboard[] = [
                                    'text' => "Следующие ".$array_data[1],
                                    "callback_data" => "todayApps_".$array_data[1]."_".$next_step
                                ];
                                $keyboard[] = [
                                    'text' => "Показать ".$menu,
                                    "callback_data" => "todayApps_".$menu."_1"
                                ];
                                $full_keyboard[] = $keyboard;
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            } else {
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            }
                        }
                    } else {
                        $message = "Заявок за сегодня нет";
                    }
                }
                break;
            case 'yesterdayApps':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0){
                    $all_list = (new Applications())->where('PROPERTY_FOR_COL_RESP', 1)->where('>DATE_CREATE', date('d.m.Y 00:00:01', strtotime('yesterday')))->where('<DATE_CREATE', date('d.m.Y 23:59:59', strtotime('yesterday')))->buildQuery()->getArray();
                    $list = (new Applications())->where('PROPERTY_FOR_COL_RESP', 1)->where('>DATE_CREATE', date('d.m.Y 00:00:01', strtotime('yesterday')))->where('<DATE_CREATE', date('d.m.Y 23:59:59', strtotime('yesterday')))->setLimit($array_data[1])->setPage($array_data[2])->buildQuery()->getArray();
                    $full_keyboard = [];
                    if(ArrayHelper::checkFullArray($list)){
                        $message = "Список заявок за вчера\n";
                        foreach ($list as $application) {
                            $full_keyboard[] = [
                                [
                                    'text' => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. №'.$application['ID'],
                                    "callback_data" => "showCollectorsApplicationForResponse_".$application['ID']
                                ]
                            ];
                        }
                        $all_count = count($all_list);
                        $current_count = count($list);
                        if($current_count<$array_data[1]){
                            if($array_data[2]>1){
                                $prev_step = (int)$array_data[2]-1;
                                $next_step = (int)$array_data[2]+1;
                                $menu = $array_data[1]==10?100:10;
                                if($array_data[1]>count($list)){
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "yesterdayApps_".$array_data[1]."_".$prev_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Показать ".$menu,
                                        "callback_data" => "yesterdayApps_".$menu."_1"
                                    ];
                                    $full_keyboard[] = $keyboard;
                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $full_keyboard
                                    ]);
                                } else {
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "yesterdayApps_".$array_data[1]."_".$prev_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Следующие ".$array_data[1],
                                        "callback_data" => "yesterdayApps_".$array_data[1]."_".$next_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Показать ".$menu,
                                        "callback_data" => "yesterdayApps_".$menu."_1"
                                    ];
                                    $full_keyboard[] = $keyboard;
                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $full_keyboard
                                    ]);
                                }
                            } else {
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            }
                        } else {
                            if($all_count>$current_count){
                                $menu = $array_data[1]==10?100:10;
                                $next_step = $array_data[2]+1;
                                $keyboard = [];
                                if($array_data[2]>1){
                                    $prev_step = $array_data[2]-1;
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "yesterdayApps_".$array_data[1]."_".$prev_step
                                    ];
                                }
                                $keyboard[] = [
                                    'text' => "Следующие ".$array_data[1],
                                    "callback_data" => "yesterdayApps_".$array_data[1]."_".$next_step
                                ];
                                $keyboard[] = [
                                    'text' => "Показать ".$menu,
                                    "callback_data" => "yesterdayApps_".$menu."_1"
                                ];
                                $full_keyboard[] = $keyboard;
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            } else {
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            }
                        }
                    } else {
                        $message = "Заявок за сегодня нет";
                    }
                }
                break;
            case 'weekApps':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0){
                    $all_list = (new Applications())->where('PROPERTY_FOR_COL_RESP', 1) -> where('>DATE_CREATE', date('d.m.Y 00:00:01', strtotime('-7 days')))->where('<DATE_CREATE', date('d.m.Y 23:59:59'))->buildQuery()->getArray();
                    $list = (new Applications())->where('PROPERTY_FOR_COL_RESP', 1) -> where('>DATE_CREATE', date('d.m.Y 00:00:01', strtotime('-7 days')))->where('<DATE_CREATE', date('d.m.Y 23:59:59'))->setLimit($array_data[1])->setPage($array_data[2])->buildQuery()->getArray();
                    $full_keyboard = [];
                    if(ArrayHelper::checkFullArray($list)){
                        $message = "Список заявок за неделю\n";
                        foreach ($list as $application) {
                            $full_keyboard[] = [
                                [
                                    'text' => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. №'.$application['ID'],
                                    "callback_data" => "showCollectorsApplicationForResponse_".$application['ID']
                                ]
                            ];
                        }
                        $all_count = count($all_list);
                        $current_count = count($list);
                        if($current_count<$array_data[1]){
                            if($array_data[2]>1){
                                $prev_step = (int)$array_data[2]-1;
                                $next_step = (int)$array_data[2]+1;
                                $menu = $array_data[1]==10?100:10;
                                if($array_data[1]>count($list)){
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "weekApps_".$array_data[1]."_".$prev_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Показать ".$menu,
                                        "callback_data" => "weekApps_".$menu."_1"
                                    ];
                                    $full_keyboard[] = $keyboard;
                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $full_keyboard
                                    ]);
                                } else {
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "weekApps_".$array_data[1]."_".$prev_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Следующие ".$array_data[1],
                                        "callback_data" => "weekApps_".$array_data[1]."_".$next_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Показать ".$menu,
                                        "callback_data" => "weekApps_".$menu."_1"
                                    ];
                                    $full_keyboard[] = $keyboard;
                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $full_keyboard
                                    ]);
                                }
                            } else {
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            }
                        } else {
                            if($all_count>$current_count){
                                $menu = $array_data[1]==10?100:10;
                                $next_step = $array_data[2]+1;
                                $keyboard = [];
                                if($array_data[2]>1){
                                    $prev_step = $array_data[2]-1;
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "weekApps_".$array_data[1]."_".$prev_step
                                    ];
                                }
                                $keyboard[] = [
                                    'text' => "Следующие ".$array_data[1],
                                    "callback_data" => "weekApps_".$array_data[1]."_".$next_step
                                ];
                                $keyboard[] = [
                                    'text' => "Показать ".$menu,
                                    "callback_data" => "weekApps_".$menu."_1"
                                ];
                                $full_keyboard[] = $keyboard;
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            } else {
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            }
                        }
                    } else {
                        $message = "Заявок за сегодня нет";
                    }
                }
                break;
            case 'setToRefinement':
                if((int)$array_data[1]>0){
                    if (Common::DuringAppByCollResponsible()>0&&(int)$array_data[1]!=Common::DuringAppByCollResponsible()) {
                        $during_app = (new Applications())->find(Common::DuringAppByCollResponsible());
                        $message = "Невозможно! Закончите оформление заявки №".$during_app->getId()." (".$during_app->contragent().")";
                    } else {
                        $apps = new Applications();
                        $app = $apps->find((int)$array_data[1]);
                        if ($app->isReadyToWork()) {
                            Common::SetDuringAppByCollResponsible((int)$array_data[1]);
                            $app->setInRefinementStatus();
                            $message = "Заявка №" . $app->getId() . " принята в работу, уточните данные и продолжите оформление заявки";
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => "Создать ордер",
                                            "callback_data" => "allowAppByResp_" . (int)$array_data[1]
                                        ],
                                        [
                                            'text' => Common::getButtonText('resp_denie_app'),
                                            "callback_data" => "RespCancelApp_" . (int)$array_data[1]
                                        ],
                                    ]
                                ]
                            ]);
                        }
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
            case 'showCollectorsApplicationForResponse':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $message = $apps->prepareAppDataMessage((int)$array_data[1]);
                    $app_array = (new Applications())->find((int)$array_data[1])->getArray();
                    $message.="Статус заявки - <b>".$app_array['PROPERTY_STATUS_VALUE']."</b>";
                    if(!(new Applications())->find((int)$array_data[1])->isInDelivery()&&!(new Applications())->find((int)$array_data[1])->isComplete()) {
                        $app = $apps->find((int)$array_data[1]);
                        $buttons = [];
                        if($app->isProblem()){
                            $message .= "\n\nЗаявка помечена как проблемная, восстановить?\n";
                            $buttons[] = [
                                'text' => "Восстановить из проблемных",
                                "callback_data" => 'restoreProblemApp_' . (int)$array_data[1]
                            ];
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [$buttons]
                            ]);
                        } else {
                            $message .= "\n\nНазначить заявке другой экипаж\n";
                            $crews = (new Crew())->select(['ID', 'NAME'])->get()->getArray();
                            foreach ($crews as $crew) {
                                if ($app->crew()->getId() != $crew['ID']) {
                                    $buttons[] = [
                                        'text' => $crew['NAME'],
                                        "callback_data" => 'resetCrewToApp_' . $app->getId() . "_" . $crew['ID']
                                    ];
                                }
                            }
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [$buttons]
                            ]);
                        }
                    }
                }
                break;
            case 'NotSetRespComment':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()!=15){
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
                        Common::ResetDuringAppByCollResponsible();
                        if($app->isPayment()) {
                            $app->setStatus(25);
                            $app->setField('RESP_STEP', 7);
                            $markup['message'] = "Информация по заявке №".$app->getId()." сохранена. Ожидаем подтверждения экипажем.";
                            $collector_markup = CollectorMarkup::getMarkupByCollector($app->getId(), $app->crew()->getId(), 'new_app');
                            Telegram::sendMessageToCollector($app->crew()->getId(), $collector_markup);
                        } else {
                            $app->setStatus(25);
                            $app->setField('RESP_STEP', 4);
                            $markup['message'] = "Информация по заявке №".$app->getId()." сохранена. Ожидаем подтверждения экипажем.";
                            $collector_markup = CollectorMarkup::getMarkupByCollector($app->getId(), $app->crew()->getId(), 'new_app');
                            Telegram::sendMessageToCollector($app->crew()->getId(), $collector_markup);

                            $manager_text = "По заявке №".$app->getId()." планируемое время забора от контрагента ".$app->getField('AGENT_OFF_NAME')." - ".$app->getTime();
                            Telegram::sendMessageToManagerByAppID($app->getId(), $manager_text);
                            //Telegram::sendMessageToResp($manager_text);

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
                    $app = $apps->find((int)$array_data[1]);
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
                    if(Common::DuringAppByCollResponsible()>0&&(int)$array_data[1]!=Common::DuringAppByCollResponsible()){
                        $during_app = (new Applications())->find(Common::DuringAppByCollResponsible());
                        $markup['message'] = "Невозможно! Закончите оформление заявки №".$during_app->getId()." (".$during_app->contragent().")";
                    } else {
                        $apps = new Applications();
                        $app = $apps->find((int)$array_data[1]);
                        $markup['message'] = \Settings\Common::getWrongAppActionText();
                        if ($app->isInRefinement()) {
                            if ($app->isPayment() && $app->getField('RESP_STEP') == 2) {
                                $app->setField('RESP_STEP', 3);
                            } else {
                                $app->setField('RESP_STEP', 0);
                            }
                            $app->setField('STATUS', 15);
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
                        }
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
                    $app = $apps->find((int)$array_data[1]);
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
                    $app = $apps->find((int)$array_data[1]);
                    if($app->cash_room()->getId()>0){
                        $markup['message'] = Common::getWrongAppActionText();
                    } else {
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
                    $app = $apps->find((int)$array_data[1]);
                    if( $app->isPayment() ) {
                        if($app->getStatus()!=15&&$app->getStatus()!=45){
                            $markup['message'] = Common::getWrongAppActionText();
                        } else {
                            $app->setField('CREW', (int)$array_data[2]);
                            $app->setField('RESP_STEP', 6);
                            $markup = RespMarkup::getMarkupByResp((int)$array_data[1]);
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
            case 'restoreProblemApp':
                if((int)$array_data[1]>0) {
                    $app = (new Applications())->find((int)$array_data[1]);
                    $app->setStatus(23);
                    $markup['message'] = 'Заявка №'.$app->getId()." успешно восстановлена из проблемных";
                    $collector_markup['message'] = 'Заявка №'.$app->getId()." была восстановлена ответственным из проблемных. Для её выполнения перейдите в раздел 'Забор'";
                    $collector_markup['buttons'] = \Processing\Collector\Buttons::getCommonButtons($app->crew()->getId());
                    Telegram::sendMessageToCollector($app->crew()->getId(), $collector_markup);
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            case 'resetCrewToApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    $app->setField('CREW', (int)$array_data[2]);
                    $markup['message'] = "Заявке №".$app->getId()." переназначен экипаж ".(new Crew)->find((int)$array_data[2])->getName();
                    $collector_message['message'] = "Вам была переназначена заявка №".$app->getId();
                    Telegram::sendMessageToCollector((int)$array_data[2], $collector_message);
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //сброс заявки
            case 'ResetRespApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    (new Applications())->find((int)$array_data[1])->resetCollRespApp();
                    $message = "Заявка №".$app->getId()." была сброшена и возвращена в раздел 'Заявки в работу'";
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }
}