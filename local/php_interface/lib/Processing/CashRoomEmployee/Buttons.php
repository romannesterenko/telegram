<?php
namespace Processing\CashRoomEmployee;
use Api\Mattermost;
use Api\Sender;
use Api\Telegram;
use danog\MadelineProto\Exception;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Helpers\StringHelper;
use Models\Applications;
use Models\CashRoom;
use Models\CashRoomDay;
use Models\Currency;
use Models\Order;
use Models\Staff;
use Processing\Responsible\Markup as RespMarkup;
use Settings\Common;

class Buttons
{
    public static function process($data): array
    {
        $message = Common::getWrongCallBackData();
        $array_data = explode('_', $data);
        switch ($array_data[0]){

            case 'ResetStartDay':
                if((int)$array_data[1]>0){
                    $days = new CashRoomDay();
                    $day = $days->find((int)$array_data[1]);
                    if($day->getStatus()==30){
                        $days::delete($day->getId());
                        $message = 'Открытие смены отменено';
                    } else {
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'ResetCloseDay':
                if((int)$array_data[1]>0){
                    $days = new CashRoomDay();
                    $day = $days->find((int)$array_data[1]);
                    if($day->getStatus()==33){
                        $day->setStatus(32);
                        $day->resetCountAttempts();
                        $day->resetEndSums();
                        $day->resetSumStep();
                        $day->resetEndCurrencies();
                        $day->removeNeedApprove();
                        Common::resetCloseDaySession();
                        $message = 'Закрытие смены отменено';
                    } else {
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'ResetCloseDays':
                $cash_rooms = (new CashRoom())->get()->getArray();
                $need_reset = false;
                $a = [33];
                foreach ($cash_rooms as $cash_room){
                    $crd = (new CashRoomDay())
                        ->where('PROPERTY_CASH_ROOM', $cash_room['ID'])
                        ->where('>DATE_CREATE', date('d.m.Y 00:00:01'))
                        ->where('<DATE_CREATE', date('d.m.Y 23:59:59'))
                        ->first()->getArray();
                    if(!$need_reset&&in_array($crd['PROPERTY_STATUS_ENUM_ID'], $a)){

                        $need_reset = true;
                    }
                }
                if($need_reset){
                    foreach ($cash_rooms as $cash_room) {
                        $crd = (new CashRoomDay())
                            ->where('PROPERTY_CASH_ROOM', $cash_room['ID'])
                            ->where('>DATE_CREATE', date('d.m.Y 00:00:01'))
                            ->where('<DATE_CREATE', date('d.m.Y 23:59:59'))
                            ->first()->getArray();

                        $day = (new CashRoomDay())->find($crd['ID']);
                        $day->setStatus(32);
                        $day->resetCountAttempts();
                        $day->resetEndSums();
                        $day->resetSumStep();
                        $day->resetEndCurrencies();
                        $day->removeNeedApprove();

                    }
                    Common::resetCloseDaySession();
                    $message = 'Закрытие смен отменено';
                    $response['buttons'] = self::getCommonButtons();
                }
                break;
            case 'showApplicationForCRE':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if ($app->isPayment()) {
                        if($app->isPayBack()||$app->getStatus()==54){
                            if($app->getStatus()==54) {
                                if(Common::getCREGiveMoneySession()>0){
                                    $message = "Невозможно! Вы уже работаете с заявкой на выдачу №".Common::getCREGiveMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                                } elseif(Common::getCREReceiveMoneySession()>0){
                                    $message = "Невозможно! Вы уже работаете с заявкой на забор №".Common::getCREReceiveMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                                } elseif (Common::getCREReceivePaybackMoneySession()>0&&Common::getCREReceivePaybackMoneySession()!=$app->getId()){
                                    $message = "Невозможно! Вы уже работаете с возвратом заявки №".Common::getCREReceivePaybackMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                                } else {
                                    $cash = $app->getCash();
                                    if (count($cash) == 1) {
                                        $currencies = new Currency();
                                        $currencies_array = $app->getCurrencies();
                                        $currency = $currencies->find($currencies_array[0]);
                                        $message = 'Введите привезенную сумму в ' . $currency->getGenitive();
                                        $inline_keys[] = [
                                            [
                                                'text' => "Сброс заявки",
                                                "callback_data" => 'ResetPaybackCREApp_' . $app->getId()
                                            ]
                                        ];

                                        $response['buttons'] = json_encode([
                                            'resize_keyboard' => true,
                                            'inline_keyboard' => $inline_keys
                                        ]);
                                    } else {
                                        $currencies = new Currency();
                                        $currencies_array = $app->getCurrencies();
                                        if (ArrayHelper::checkFullArray($currencies_array)) {
                                            $app->setField('PAYBACK_SUM_ENTER_STEP', 0);
                                            $currency = $currencies->find($currencies_array[0]);
                                            $message = 'Введите привезенную сумму в ' . $currency->getGenitive();
                                            $inline_keys[] = [
                                                [
                                                    'text' => "Сброс заявки",
                                                    "callback_data" => 'ResetPaybackCREApp_' . $app->getId()
                                                ]
                                            ];

                                            $response['buttons'] = json_encode([
                                                'resize_keyboard' => true,
                                                'inline_keyboard' => $inline_keys
                                            ]);
                                        }
                                    }
                                }
                            }else{
                                $message = "Заявка №" . $app->getId() . "\nВозврат средств от контрагента " . $app->getField('AGENT_OFF_NAME');
                                $inline_keys = [];
                                $inline_keys[] = [
                                    [
                                        'text' => 'Получить деньги (Указание валюты и суммы)',
                                        "callback_data" => 'GivePayBackMoney_' . $app->getId()
                                    ]
                                ];
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $inline_keys
                                ]);
                            }
                        } else {
                            $message = "Заявка №" . $app->getId() . " (".$app->cash_room()->getName().")\nВыдать из <b>".$app->cash_room()->getName()."</b>, экипажу <b>" . $app->crew()->getName()."</b> для контрагента <b>".$app->getField('AGENT_OFF_NAME')."</b>";
                            $sums = $app->getField("SUMM");
                            $app_currencies = $app->getField("CURRENCY");
                            $app_cash = $app->getCash();
                            if(ArrayHelper::checkFullArray($sums)&&ArrayHelper::checkFullArray($app_currencies)){
                                foreach ($sums as $id => $sum){
                                    $currencies = new Currency();
                                    $currency_ = $currencies->find($app_currencies[$id]);
                                    $message.= "\n<b>".StringHelper::formatSum($sum)."</b> ".$currency_->getField('CODE');
                                }
                                $cash_currencies = $app->cash_room()->getCurrencies();
                                if(ArrayHelper::checkFullArray($cash_currencies)){
                                    $allow_app = true;
                                    foreach ($app_currencies as $app_currency){
                                        if(!in_array($app_currency, $cash_currencies)){
                                            $allow_app = false;
                                            break;
                                        }
                                    }
                                    $app_sums = $app->getField('SUMM');
                                    $app_real_sums = $app->getField('REAL_SUM');
                                    if(ArrayHelper::checkFullArray($app_cash)&&count($app_real_sums)>0&&(count($app_sums)!=count($app_real_sums))){
                                        $message.= "\nВыдана сумма - ".implode(', ', $app_cash);
                                    }
                                    if($allow_app){
                                        if(count($app_currencies)==1){
                                            $sum = $sums[0];
                                            $inline_keys = [];
                                            $not_need_round = true;
                                            if ($sum % 50 != 0) {
                                                $not_need_round = false;
                                            }
                                            if ($sum % 100 != 0) {
                                                $not_need_round = false;
                                            }
                                            if ($sum>=1000&&$sum % 1000 != 0) {
                                                $not_need_round = false;
                                            }
                                            if ($not_need_round) {
                                                $inline_keys[] = [
                                                    [
                                                        'text' => 'Выдать деньги',
                                                        "callback_data" => 'CREGiveAsIs_' . $app->getId().'_'.$sum
                                                    ]
                                                ];
                                            } else {
                                                $inline_keys[] = [
                                                    [
                                                        'text' => 'Выдать как есть (' . number_format($sum, 0, ',', ' ') . ')',
                                                        "callback_data" => 'CREGiveAsIs_' . $app->getId().'_'.$sum
                                                    ]
                                                ];
                                                if ($sum % 50 != 0) {
                                                    $ss = 50 * round($sum / 50);
                                                    $inline_keys[] = [
                                                        [
                                                            'text' => 'Округлить до 50 (' . number_format($ss, 0, ',', ' ') . ')',
                                                            "callback_data" => 'CREGiveRound50_' . $app->getId().'_'.$ss
                                                        ]
                                                    ];
                                                }
                                                if ($sum % 100 != 0) {
                                                    $ss = round($sum, -2);
                                                    $inline_keys[] = [
                                                        [
                                                            'text' => 'Округлить до 100 (' . number_format($ss, 0, ',', ' ') . ')',
                                                            "callback_data" => 'CREGiveRound100_' . $app->getId().'_'.$ss
                                                        ]
                                                    ];
                                                }
                                                if ($sum>=1000&&$sum % 1000 != 0) {
                                                    $ss = round($sum, -3);
                                                    $inline_keys[] = [
                                                        [
                                                            'text' => 'Округлить до 1000 (' . number_format($ss, 0, ',', ' ') . ')',
                                                            "callback_data" => 'CREGiveRound1000_' . $app->getId().'_'.$ss
                                                        ]
                                                    ];
                                                }
                                            }
                                            $inline_keys[] = [
                                                [
                                                    'text' => "Сброс заявки",
                                                    "callback_data" => 'ResetCREApp_' . $app->getId()
                                                ]
                                            ];
                                            $response['buttons'] = json_encode([
                                                'resize_keyboard' => true,
                                                'inline_keyboard' => $inline_keys
                                            ]);
                                        } else {
                                            $fact_sums = $app->getField('REAL_SUM');
                                            $sum = $sums[0];
                                            $currency = $app_currencies[0];
                                            if(ArrayHelper::checkFullArray($fact_sums)){
                                                foreach ($sums as $i => $sum_)
                                                    if(empty($fact_sums[$i])) {
                                                        $sum = $sum_;
                                                        $currency = $app_currencies[$i];
                                                        break;
                                                    }
                                            }
                                            $object_currencies = new Currency();
                                            $this_currency = $object_currencies->find($currency);
                                            $message.= "\n\nВыдайте сумму в размере ".StringHelper::formatSum($sum)." ".$this_currency->getField('CODE')." в валюте ".$this_currency->getName();
                                            if ($sum % 50 != 0) {
                                                $not_need_round = false;
                                            }
                                            if ($sum % 100 != 0) {
                                                $not_need_round = false;
                                            }
                                            if ($sum>=1000&&$sum % 1000 != 0) {
                                                $not_need_round = false;
                                            }
                                            if ($not_need_round) {
                                                $inline_keys[] = [
                                                    [
                                                        'text' => 'Выдать деньги',
                                                        "callback_data" => 'CREGiveAsIs_' . $app->getId().'_'.$sum
                                                    ]
                                                ];
                                            } else {
                                                $inline_keys[] = [
                                                    [
                                                        'text' => 'Выдать как есть (' . number_format($sum, 0, ',', ' ') .' '.$this_currency->getField('CODE').')',
                                                        "callback_data" => 'CREGiveAsIs_' . $app->getId().'_'.$sum
                                                    ]
                                                ];
                                                if ($sum % 50 != 0) {
                                                    $ss = 50 * round($sum / 50);
                                                    $inline_keys[] = [
                                                        [
                                                            'text' => 'Округлить до 50 (' . number_format($ss, 0, ',', ' ') . ' '.$this_currency->getField('CODE').')',
                                                            "callback_data" => 'CREGiveRound50_' . $app->getId().'_'.$ss
                                                        ]
                                                    ];
                                                }
                                                if ($sum % 100 != 0) {
                                                    $ss = round($sum, -2);
                                                    $inline_keys[] = [
                                                        [
                                                            'text' => 'Округлить до 100 (' . number_format($ss, 0, ',', ' ') .' '.$this_currency->getField('CODE').')',
                                                            "callback_data" => 'CREGiveRound100_' . $app->getId().'_'.$ss
                                                        ]
                                                    ];
                                                }
                                                if ($sum>=1000&&$sum % 1000 != 0) {
                                                    $ss = round($sum, -3);
                                                    $inline_keys[] = [
                                                        [
                                                            'text' => 'Округлить до 1000 (' . number_format($ss, 0, ',', ' ') .' '.$this_currency->getField('CODE').')',
                                                            "callback_data" => 'CREGiveRound1000_' . $app->getId().'_'.$ss
                                                        ]
                                                    ];
                                                }
                                            }
                                            $inline_keys[] = [
                                                [
                                                    'text' => "Сброс заявки",
                                                    "callback_data" => 'ResetCREApp_' . $app->getId()
                                                ]
                                            ];
                                            $response['buttons'] = json_encode([
                                                'resize_keyboard' => true,
                                                'inline_keyboard' => $inline_keys
                                            ]);
                                        }
                                    }
                                }
                            }

                        }
                    } else {
                        $message = "Заявка №" . $app->getId() . " (".$app->cash_room()->getName().")\nПолучить деньги в <b>".$app->cash_room()->getName()."</b> от <b>" . $app->crew()->getName() . "</b>";
                        $response['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => 'Получить деньги (Указание валюты и суммы)',
                                        "callback_data" => 'CREReceiveSum_' . $app->getId()
                                    ]
                                ]
                            ]
                        ]);
                    }
                }
                break;
            case 'CREGiveAsIs':
                if ((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);

                        if ($app->getStatus() == 23) {
                            $array = self::processRealSum($app, $array_data);
                            $message = $array['message'];
                            if ($array['buttons'])
                                $response['buttons'] = $array['buttons'];
                        } else {
                            $message = Common::getWrongAppActionText();
                        }

                }
                break;
            case 'CREReceiveSum':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if(Common::getCloseDaySession()>0){
                        $message = "Невозможно! Вы закрываете смену.";
                    } elseif(Common::getCREGiveMoneySession()>0){
                        $message = "Невозможно! Вы уже работаете с заявкой на выдачу №".Common::getCREGiveMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                    } elseif (Common::getCREReceivePaybackMoneySession()>0){
                        $message = "Невозможно! Вы уже работаете с возвратом заявки №".Common::getCREReceivePaybackMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                    } elseif(Common::getCREReceiveMoneySession()>0&&Common::getCREReceiveMoneySession()!=(int)$array_data[1]){
                        $message = "Невозможно! Вы уже работаете с заявкой на забор №".Common::getCREReceiveMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                    } else {
                        if ($app->getStatus() == 26 || $app->getStatus() == 20) {
                            $app->setStatus(26);
                            Common::setCREReceiveMoneySession((int)$array_data[1]);
                            $sums = $app->getCash();
                            if($app->getField('SUM_ENTER_STEP')==0) {
                                if (ArrayHelper::checkFullArray($sums)) {
                                    $message = 'Уже введенные суммы - ' . implode(', ', $sums);
                                    $message .= "\nВыберите валюту";
                                } else {
                                    $message = 'Выберите валюту';
                                }

                                $list = $app->cash_room()->getCurrencies();
                                $exists_currencies = $app->getCurrencies();
                                $inline_keys = [];
                                foreach ($list as $item) {
                                    if(in_array($item, $exists_currencies))
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
                                if(!ArrayHelper::checkFullArray($inline_keys)){
                                    $message = 'Уже введенные суммы - ' . implode(', ', $sums);
                                    $message .= "\nВсе доступные валюты выбраны и введены, закройте заявку или сбросьте её заполнение";
                                    $inline_keys[] = [
                                        [
                                            'text' => 'Закрыть заявку',
                                            "callback_data" => "CRECompleteReceiveSum_".$app->getId()
                                        ]
                                    ];
                                }
                                $inline_keys[] = [
                                    [
                                        'text' => "Сброс заявки",
                                        "callback_data" => 'ResetCREApp_' . $app->getId()
                                    ]
                                ];

                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $inline_keys
                                ]);
                            } else {
                                $currencies = $app->getCurrencies();
                                $sums = $app->getSum();
                                $diff = array_diff(array_keys($currencies), array_keys($sums));
                                $currency = (new Currency())->find($currencies[current($diff)]);
                                if (ArrayHelper::checkFullArray($app->getCash())) {
                                    $message = 'Уже введенные суммы - ' . implode(', ', $app->getCash());
                                    $message .= "\nВведите сумму в ".$currency->getGenitive();
                                } else {
                                    $message = 'Введите сумму в '.$currency->getGenitive();
                                }
                                $inline_keys[] = [
                                    [
                                        'text' => "Сброс заявки",
                                        "callback_data" => 'ResetCREApp_' . $app->getId()
                                    ]
                                ];

                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $inline_keys
                                ]);
                            }
                        } else {
                            $message = Common::getWrongAppActionText();
                        }
                    }
                }
                break;
            case 'CorrectPrevSum':
                if((int)$array_data[1]>0){
                    $app = (new Applications())->find((int)$array_data[1]);
                    if($app->getStatus()==26||$app->getStatus()==20) {
                        $app->removeLastSum();
                        $app->setStatus(26);
                        $app->setField('SUM_ENTER_STEP', 0);
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
                    } else {
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'CRECompleteReceiveSum':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if((int)$array_data[1]==Common::getCREReceiveMoneySession()) {
                        if ($app->getStatus() == 26) {
                            $order = new Order();
                            $order->createFromAppID($app->getId());
                            $app->setComplete();
                            $markup['message'] = "Заявка №" . (int)$app->getId();
                            $markup['message'] .= "\nОт контрагента " . $app->getField("AGENT_OFF_NAME") . " поступили деньги: ";
                            $cash = $app->getCash();
                            if (ArrayHelper::checkFullArray($cash))
                                $markup['message'] .= implode(', ', $cash);
                            Telegram::sendMessageToManager($markup, (int)$app->getId());
                            if($app->manager()->getId()!=(new Staff())->getResp()->getId())
                                Telegram::sendMessageToResp($markup['message']);
                            $contact_message = "По заявке №" . $app->getId() . " от вас получена сумма " . implode(', ', $cash);
                            $message = "Заявка выполнена";
                            $response['buttons'] = Buttons::getCommonButtons();
                            try {
                                \Api\Sender::send($app, $contact_message);
                            } catch (Exception $exception) {

                            }
                            Common::resetCREReceiveMoneySession();
                        } else {
                            $message = Common::getWrongAppActionText();
                        }
                    } else {
                        $message = "Невозможно! Вы уже работаете с заявкой №".Common::getCREReceiveMoneySession();
                    }
                }
                break;
            case 'CREGiveRound50':
                if ((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()==23) {
                        $array = self::processRealSum($app, $array_data);
                        $message = $array['message'];
                        if($array['buttons'])
                            $response['buttons'] = $array['buttons'];
                    }else{
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'CREGiveRound100':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()==23) {
                        $array = self::processRealSum($app, $array_data);
                        $message = $array['message'];
                        if($array['buttons'])
                            $response['buttons'] = $array['buttons'];
                    }else{
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'CREGiveRound1000':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()==23) {
                        $array = self::processRealSum($app, $array_data);
                        $message = $array['message'];
                        if($array['buttons'])
                            $response['buttons'] = $array['buttons'];
                    }else{
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'GivePayBackMoney':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if(Common::getCloseDaySession()>0){
                        $message = "Невозможно! Вы закрываете смену.";
                    } elseif(Common::getCREGiveMoneySession()>0){
                        $message = "Невозможно! Вы уже работаете с заявкой на выдачу №".Common::getCREGiveMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                    } elseif(Common::getCREReceiveMoneySession()>0){
                        $message = "Невозможно! Вы уже работаете с заявкой на забор №".Common::getCREReceiveMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                    } else {
                        if ($app->getStatus() != 52) {
                            $message = Common::getWrongAppActionText();
                        } else {
                            $app->setStatus(54);
                            Common::setCREReceivePaybackMoneySession($app->getId());
                            $cash = $app->getCash();
                            if (count($cash) == 1) {
                                $currencies = new Currency();
                                $currencies_array = $app->getCurrencies();
                                $currency = $currencies->find($currencies_array[0]);
                                $message = 'Введите привезенную сумму в ' . $currency->getGenitive();
                                $inline_keys[] = [
                                    [
                                        'text' => "Сброс заявки",
                                        "callback_data" => 'ResetPaybackCREApp_' . $app->getId()
                                    ]
                                ];

                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $inline_keys
                                ]);
                            } else {
                                $currencies = new Currency();
                                $currencies_array = $app->getCurrencies();
                                if (ArrayHelper::checkFullArray($currencies_array)) {
                                    $app->setField('PAYBACK_SUM_ENTER_STEP', 0);
                                    $currency = $currencies->find($currencies_array[0]);
                                    $message = 'Введите привезенную сумму в ' . $currency->getGenitive();
                                    $inline_keys[] = [
                                        [
                                            'text' => "Сброс заявки",
                                            "callback_data" => 'ResetPaybackCREApp_' . $app->getId()
                                        ]
                                    ];

                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $inline_keys
                                    ]);
                                }
                            }
                        }
                    }
                }
                break;
            case 'SetCurrencyToApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getField('SUM_ENTER_STEP')!=0){
                        $message = Common::getWrongAppActionText();
                    } else {
                        $app->setCurrency((int)$array_data[2]);
                        $app->setField('SUM_ENTER_STEP', 1);
                        $currencies = new Currency();
                        $currency = $currencies->find((int)$array_data[2]);
                        $message = 'Введите привезенную сумму в валюте ' . $currency->getName();
                        $inline_keys[] = [
                            [
                                'text' => "Сброс заявки",
                                "callback_data" => 'ResetCREApp_' . $app->getId()
                            ]
                        ];

                        $response['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => $inline_keys
                        ]);
                    }
                }
                break;
            case 'ResetCREApp':
                if((int)$array_data[1]>0) {
                    $app = (new Applications())->find((int)$array_data[1]);
                    if($app->isPayment()){
                        Common::resetCREGiveMoneySession();
                        $app->setStatus(23);
                        $app->resetField('REAL_SUM');
                        $orders = $app->order();
                        if(ArrayHelper::checkFullArray($orders)){
                            foreach ($orders as $order){
                                $ord_obj = new Order();
                                $ord_obj->find($order)->reset();
                            }
                        }
                        $message = "Заявка ".$app->getFullName()." была сброшена и возвращена в раздел 'Выдача'";
                    } else {
                        Common::resetCREReceiveMoneySession();
                        $app->resetField('SUMM');
                        $app->resetField('CURRENCY');
                        $app->resetField('SUM_ENTER_STEP');
                        $app->setStatus(20);
                        $message = "Заявка ".$app->getFullName()." была сброшена и возвращена в раздел 'Забор'";
                    }
                }
                break;
            case 'ResetPaybackCREApp':
                if((int)$array_data[1]>0) {
                    $app = (new Applications())->find((int)$array_data[1]);
                    Common::resetCREReceivePaybackMoneySession();
                    if($app->getStatus()==54) {
                        $app->setStatus(52);
                        $message = "Заявка ".$app->getFullName()." была сброшена и возвращена в раздел 'Выдача'";
                    }
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }

    public static function drawButtons($sum, $app_id, $currency){
        if ($sum % 50 != 0) {
            $not_need_round = false;
        }
        if ($sum % 100 != 0) {
            $not_need_round = false;
        }
        if ($sum>=1000&&$sum % 1000 != 0) {
            $not_need_round = false;
        }
        if ($not_need_round) {
            $inline_keys[] = [
                [
                    'text' => 'Выдать деньги',
                    "callback_data" => 'CREGiveAsIs_' . $app_id.'_'.$sum
                ]
            ];
        } else {
            $inline_keys[] = [
                [
                    'text' => 'Выдать как есть (' . number_format($sum, 0, ',', ' ') .' '.$currency.')',
                    "callback_data" => 'CREGiveAsIs_' . $app_id.'_'.$sum
                ]
            ];
            if ($sum % 50 != 0) {
                $ss = 50 * round($sum / 50);
                $inline_keys[] = [
                    [
                        'text' => 'Округлить до 50 (' . number_format($ss, 0, ',', ' ') . ' '.$currency.')',
                        "callback_data" => 'CREGiveRound50_' . $app_id.'_'.$ss
                    ]
                ];
            }
            if ($sum % 100 != 0) {
                $ss = round($sum, -2);
                $inline_keys[] = [
                    [
                        'text' => 'Округлить до 100 (' . number_format($ss, 0, ',', ' ') .' '.$currency.')',
                        "callback_data" => 'CREGiveRound100_' . $app_id.'_'.$ss
                    ]
                ];
            }
            if ($sum>=1000&&$sum % 1000 != 0) {
                $ss = round($sum, -3);
                $inline_keys[] = [
                    [
                        'text' => 'Округлить до 1000 (' . number_format($ss, 0, ',', ' ') .' '.$currency.')',
                        "callback_data" => 'CREGiveRound1000_' . $app_id.'_'.$ss
                    ]
                ];
            }
        }
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => $inline_keys
        ]);
        return $response['buttons'];
    }

    public static function getCommonButtons(): string
    {
        $buttons_array = [];
        $cashRoomDays = new CashRoomDay();
        if(!Common::getCloseDaySession()>0) {
            $buttons_array[] = ['text' => Common::getButtonText('cre_apps_list_payment') . " (" . count((new Applications())->getPaymentsAppsForCRE()) . ")"];
            $buttons_array[] = ['text' => Common::getButtonText('cre_apps_list_receive') . " (" . count((new Applications())->getRecieveAppsForCRE()) . ")"];
        }


        $open_today_array = $cashRoomDays->getOpenToday();
        if(ArrayHelper::checkFullArray($open_today_array)||Common::getCloseDaySession()>0){
            $buttons_array[] = ['text' => Common::getButtonText('cre_end_work_day')];
            return json_encode([
                'resize_keyboard' => true,
                'keyboard' => [
                    $buttons_array
                ]
            ]);
        } else {
            return json_encode([
                'resize_keyboard' => true,
                'keyboard' => [
                    [
                        [
                            'text' => Common::getButtonText('cre_start_new_work_day'),
                        ],
                    ]
                ]
            ]);
        }
    }

    private static function processRealSum(Applications $app, array $array_data)
    {
        if(Common::getCREReceiveMoneySession()>0){
            $message = "Невозможно! Вы уже работаете с заявкой на забор №".Common::getCREReceiveMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
        } elseif (Common::getCREReceivePaybackMoneySession()>0){
            $message = "Невозможно! Вы уже работаете с возвратом заявки №".Common::getCREReceivePaybackMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
        } elseif (Common::getCREGiveMoneySession()>0&&Common::getCREGiveMoneySession()!=(int)$array_data[1]){
            $message = "Невозможно! Вы уже работаете с заявкой на выдачу №".Common::getCREGiveMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
        } else {
            $app->setRealSumMultiple((int)$array_data[2]);
            Common::setCREGiveMoneySession((int)$array_data[1]);
            $message = "По заявке №" . $app->getId() . " выдана сумма " . implode(', ', (new Applications())->find($app->getId())->getCash());
            $response['buttons'] = self::getCommonButtons();
            $apps_ = new Applications();
            $temp_app = $apps_->find((int)$array_data[1]);
            $app_sums = $temp_app->getField('SUMM');
            $app_real_sums = $temp_app->getField('REAL_SUM');
            $app_currencies = $temp_app->getField('CURRENCY');
            $index = count($app_real_sums) - 1;
            $setted_currency = $app_currencies[$index];
            $orders = new Order();
            $order = $orders->where('PROPERTY_APP', (int)$array_data[1])->where('PROPERTY_CURRENCY', $setted_currency)->first();
            $order->setRealSum((int)$array_data[2]);
            $order->setInDelivery();
            if (count($app_sums) > 1) {
                if (count($app_sums) != count($app_real_sums)) {
                    foreach ($app_sums as $i => $app_sum) {
                        if (empty($app_real_sums[$i])) {
                            $this_cur_id = $app_currencies[$i];
                            $this_curr_object = new Currency();
                            $this_cur = $this_curr_object->find($this_cur_id);
                            $message .= "\n\nВведите сумму в размере " . StringHelper::formatSum($app_sum) . " " . $this_cur->getField('CODE') . " в валюте " . $this_cur->getName();
                            $response['buttons'] = self::drawButtons($app_sum, $app->getId(), $this_cur->getField('CODE'));
                            break;
                        }
                    }
                } else {
                    $app->setStatus(20);
                    $cash = $app->getCash();
                    Common::resetCREGiveMoneySession();
                    $message_to_man = "Заявка №" . $app->getId() . ". Контрагент - " . $app->getField('AGENT_OFF_NAME') . ". Передано в доставку " . implode(', ', $cash);
                    Telegram::sendMessageToManagerByAppID($app->getId(), $message_to_man);
                    $message_to_client = "Заявка №" . $app->getId() . ". Передано в доставку " . implode(', ', $cash) . ". Время - " . $app->getTime();
                    try {
                        Sender::send($app, $message_to_client);
                    } catch (\Exception $e) {

                    }
                    $to_channel = "Выдал " . $app->getField('AGENT_OFF_NAME') . " " . implode(', ', $cash);
                    Mattermost::send($to_channel, $app->cash_room()->getMatterMostChannel());

                }
            } else {
                $app->setStatus(20);
                $cash = $app->getCash();
                Common::resetCREGiveMoneySession();
                $message_to_man = "Заявка №" . $app->getId() . ". Контрагент - " . $app->getField('AGENT_OFF_NAME') . ". Передано в доставку " . implode(', ', $cash);
                Telegram::sendMessageToManagerByAppID($app->getId(), $message_to_man);
                $message_to_client = "Заявка №" . $app->getId() . ". Передано в доставку " . implode(', ', $cash) . ". Время - " . $app->getTime();
                try {
                    Sender::send($app, $message_to_client);
                } catch (\Exception $e) {

                }
                $to_channel = "Выдал " . $app->getField('AGENT_OFF_NAME') . " " . implode(', ', $cash);
                Mattermost::send($to_channel, $app->cash_room()->getMatterMostChannel());
            }
        }
        return ['message' => $message, 'buttons' => $response['buttons']];
    }
}