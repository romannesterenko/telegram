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
use Models\CashRoomDay;
use Models\Currency;
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
                        $message = 'Закрытие смены отменено';
                    } else {
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'showApplicationForCRE':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if ($app->isPayment()) {
                        if($app->isPayBack()){
                            $message = "Заявка №" . $app->getId() . "\nВозврат средств от контрагента ".$app->getField('AGENT_OFF_NAME');
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
                        } else {
                            $message = "Заявка №" . $app->getId() . "\nВыдать <b>" . $app->crew()->getName()."</b> для контрагента <b>".$app->getField('AGENT_OFF_NAME')."</b>";
                            $sums = $app->getField("SUMM");
                            $app_currencies = $app->getField("CURRENCY");
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
                                            if ($sum % 1000 != 0) {
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
                                                if ($sum % 1000 != 0) {
                                                    $ss = round($sum, -3);
                                                    $inline_keys[] = [
                                                        [
                                                            'text' => 'Округлить до 1000 (' . number_format($ss, 0, ',', ' ') . ')',
                                                            "callback_data" => 'CREGiveRound1000_' . $app->getId().'_'.$ss
                                                        ]
                                                    ];
                                                }
                                            }
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
                                            if ($sum % 1000 != 0) {
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
                                                if ($sum % 1000 != 0) {
                                                    $ss = round($sum, -3);
                                                    $inline_keys[] = [
                                                        [
                                                            'text' => 'Округлить до 1000 (' . number_format($ss, 0, ',', ' ') .' '.$this_currency->getField('CODE').')',
                                                            "callback_data" => 'CREGiveRound1000_' . $app->getId().'_'.$ss
                                                        ]
                                                    ];
                                                }
                                            }
                                            $response['buttons'] = json_encode([
                                                'resize_keyboard' => true,
                                                'inline_keyboard' => $inline_keys
                                            ]);
                                        }
                                    }
                                }
                            }





                            //if($app->getSum())
                            /*$inline_keys = [];
                            $not_need_round = true;
                            if ($app->getSum() % 50 != 0) {
                                $not_need_round = false;
                            }
                            if ($app->getSum() % 100 != 0) {
                                $not_need_round = false;
                            }
                            if ($app->getSum() % 1000 != 0) {
                                $not_need_round = false;
                            }

                            if ($not_need_round) {
                                $inline_keys[] = [
                                    [
                                        'text' => 'Выдать деньги',
                                        "callback_data" => 'CREGiveAsIs_' . $app->getId()
                                    ]
                                ];
                            } else {
                                $inline_keys[] = [
                                    [
                                        'text' => 'Выдать как есть (' . number_format($app->getSum(), 0, ',', ' ') . ')',
                                        "callback_data" => 'CREGiveAsIs_' . $app->getId()
                                    ]
                                ];
                                if ($app->getSum() % 50 != 0) {
                                    $inline_keys[] = [
                                        [
                                            'text' => 'Округлить до 50 (' . number_format(50 * round($app->getSum() / 50), 0, ',', ' ') . ')',
                                            "callback_data" => 'CREGiveRound50_' . $app->getId()
                                        ]
                                    ];
                                }
                                if ($app->getSum() % 100 != 0) {
                                    $inline_keys[] = [
                                        [
                                            'text' => 'Округлить до 100 (' . number_format(round($app->getSum(), -2), 0, ',', ' ') . ')',
                                            "callback_data" => 'CREGiveRound100_' . $app->getId()
                                        ]
                                    ];
                                }
                                if ($app->getSum() % 1000 != 0) {
                                    $inline_keys[] = [
                                        [
                                            'text' => 'Округлить до 1000 (' . number_format(round($app->getSum(), -3), 0, ',', ' ') . ')',
                                            "callback_data" => 'CREGiveRound1000_' . $app->getId()
                                        ]
                                    ];
                                }
                            }*/
                            /*$response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => $inline_keys*/
                                /*'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Выдать как есть (' . number_format($app->getSum(), 0, ',', ' ') . ')',
                                            "callback_data" => 'CREGiveAsIs_' . $app->getId()
                                        ]
                                    ],
                                    [
                                        [
                                            'text' => 'Округлить до 50 (' . number_format(50 * round($app->getSum() / 50), 0, ',', ' ') . ')',
                                            "callback_data" => 'CREGiveRound50_' . $app->getId()
                                        ]
                                    ],
                                    [
                                        [
                                            'text' => 'Округлить до 100 (' . number_format(round($app->getSum(), -2), 0, ',', ' ') . ')',
                                            "callback_data" => 'CREGiveRound100_' . $app->getId()
                                        ]
                                    ],
                                    [
                                        [
                                            'text' => 'Округлить до 1000 (' . number_format(round($app->getSum(), -3), 0, ',', ' ') . ')',
                                            "callback_data" => 'CREGiveRound1000_' . $app->getId()
                                        ]
                                    ],
                                ]*/
                            //]);

                        }
                    } else {
                        $message = "Заявка №" . $app->getId() . "\nПолучить деньги от <b>" . $app->crew()->getName() . "</b>";
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
                    if ($app->getStatus()==23) {
                        $array = self::processRealSum($app, $array_data);
                        $message = $array['message'];
                        if($array['buttons'])
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
                    //if($app->getStatus()==20) {
                    if($app->getStatus()==26||$app->getStatus()==20) {
                        $app->setStatus(26);
                        $app->setField('SUM_ENTER_STEP', 1);
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
                    if($app->getStatus()==26) {
                        $order = new Order();
                        $order->createFromAppID($app->getId());
                        $app->setComplete();
                        $markup['message'] = "Заявка №" . (int)$app->getId();
                        $markup['message'].= "\nОт контрагента ".$app->getField("AGENT_OFF_NAME")." поступили деньги: ";
                        $cash = $app->getCash();
                        if(ArrayHelper::checkFullArray($cash))
                            $markup['message'].= implode(', ', $cash);
                        Telegram::sendMessageToManager($markup, (int)$app->getId());
                        $contact_message = "По заявке №".$app->getId()." от вас получена сумма ".implode(', ', $cash);
                        $message = "Заявка выполнена";
                        $response['buttons'] = Buttons::getCommonButtons();
                        try {
                            \Api\Sender::send($app, $contact_message);
                        } catch (Exception $exception) {

                        }

                    } else {
                        $message = Common::getWrongAppActionText();
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
                    if($app->getStatus()!=52){
                        $message = Common::getWrongAppActionText();
                    } else {
                        $app->setStatus(54);
                        $cash = $app->getCash();
                        if (count($cash) == 1) {
                            $currencies = new Currency();
                            $currencies_array = $app->getCurrencies();
                            $currency = $currencies->find($currencies_array[0]);
                            $message = 'Введите привезенную сумму в ' . $currency->getGenitive();
                        } else {
                            $currencies = new Currency();
                            $currencies_array = $app->getCurrencies();
                            if (ArrayHelper::checkFullArray($currencies_array)) {
                                $app->setField('PAYBACK_SUM_ENTER_STEP', 0);
                                $currency = $currencies->find($currencies_array[0]);
                                $message = 'Введите привезенную сумму в ' . $currency->getGenitive();
                            }
                        }
                    }
                }
                break;
            case 'SetCurrencyToApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getField('SUM_ENTER_STEP')!=1){
                        $message = Common::getWrongAppActionText();
                    } else {
                        $app->setCurrency((int)$array_data[2]);
                        $app->setField('SUM_ENTER_STEP', 1);
                        $currencies = new Currency();
                        $currency = $currencies->find((int)$array_data[2]);
                        $message = 'Введите привезенную сумму в валюте ' . $currency->getName();
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
        if ($sum % 1000 != 0) {
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
            if ($sum % 1000 != 0) {
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

    public static function getCommonButtons()
    {
        $buttons_array = [];
        $cashRoomDays = new CashRoomDay();

        $applications = new Applications();
        $list = $applications->getPaymentsAppsForCRE();
        //if (ArrayHelper::checkFullArray($list)) {
            $buttons_array[] = ['text' => Common::getButtonText('cre_apps_list_payment')." (".count($list).")"];
        //}

        $applications = new Applications();
        $list = $applications->getRecieveAppsForCRE();
        //if (ArrayHelper::checkFullArray($list)) {
            $buttons_array[] = ['text' => Common::getButtonText('cre_apps_list_receive')." (".count($list).")"];
        //}

        $open_today_array = $cashRoomDays->getOpenToday();
        if(ArrayHelper::checkFullArray($open_today_array)){
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
        $app->setRealSumMultiple((int)$array_data[2]);
        //$message = "По  Выдана сумма в размере <b>" . number_format((int)$array_data[2], 0, ',', ' ') . "</b> экипажу <b>".$app->crew()->getName()."</b> согласно заявки №".$app->getId();
        $message = "По заявке №".$app->getId()." выдана сумма";
        $response['buttons'] = self::getCommonButtons();
        $apps_ = new Applications();
        $temp_app = $apps_->find((int)$array_data[1]);
        $app_sums = $temp_app->getField('SUMM');
        $app_real_sums = $temp_app->getField('REAL_SUM');
        $app_currencies = $temp_app->getField('CURRENCY');
        $index = count($app_real_sums)-1;
        $setted_currency = $app_currencies[$index];
        $orders = new Order();
        $order = $orders->where('PROPERTY_APP', (int)$array_data[1])->where('PROPERTY_CURRENCY', $setted_currency)->first();
        $order->setRealSum((int)$array_data[2]);
        $order->setInDelivery();
        if(count($app_sums)>1){
            if (count($app_sums) != count($app_real_sums)) {
                foreach ($app_sums as $i => $app_sum) {
                    if (empty($app_real_sums[$i])) {
                        $this_cur_id = $app_currencies[$i];
                        $this_curr_object = new Currency();
                        $this_cur = $this_curr_object->find($this_cur_id);
                        $message .= "\n\nВведите сумму в размере " . StringHelper::formatSum($app_sum) . " ".$this_cur->getField('CODE')." в валюте " . $this_cur->getName();
                        $response['buttons'] = self::drawButtons($app_sum, $app->getId(), $this_cur->getField('CODE'));
                        break;
                    }
                }
            } else {
                $app->setStatus(20);
                $cash = $app->getCash();
                $message_to_man = "Заявка №".$app->getId().". Контрагент - ".$app->getField('AGENT_OFF_NAME').". Передано в доставку ".implode(', ', $cash);
                Telegram::sendCommonMessageToManager($message_to_man);
                $message_to_client = "Заявка №".$app->getId().". Передано в доставку ".implode(', ', $cash).". Время - ".$app->getTime();
                try {
                    Sender::send($app, $message_to_client);
                } catch (\Exception $e){

                }
                $to_channel = "Выдал ".$app->getField('AGENT_OFF_NAME')." ".implode(', ', $cash);
                Mattermost::send($to_channel);

            }
        } else {
            $app->setStatus(20);
            $cash = $app->getCash();
            $message_to_man = "Заявка №".$app->getId().". Контрагент - ".$app->getField('AGENT_OFF_NAME').". Передано в доставку ".implode(', ', $cash);
            Telegram::sendCommonMessageToManager($message_to_man);
            $message_to_client = "Заявка №".$app->getId().". Передано в доставку ".implode(', ', $cash).". Время - ".$app->getTime();
            try {
                Sender::send($app, $message_to_client);
            } catch (\Exception $e){

            }
            $to_channel = "Выдал ".$app->getField('AGENT_OFF_NAME')." ".implode(', ', $cash);
            Mattermost::send($to_channel);
        }
        return ['message' => $message, 'buttons' => $response['buttons']];
    }
}