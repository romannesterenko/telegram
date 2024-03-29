<?php
namespace Processing\CashRoomEmployee;
use Api\Mattermost;
use Api\Sender;
use Api\Telegram;
use Helpers\StringHelper;
use Models\Currency;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoom;
use Models\CashRoomDay;
use Models\Order;
use Models\Staff;
use Processing\CashRoomEmployee\Buttons as CREButtons;
use Settings\Common;

class Actions
{
    public static function startNewCashRoomDay($show_button=true)
    {
        $cashes = new CashRoom();
        $cash_list = $cashes->get()->getArray();
        $return_array = [];
        if(ArrayHelper::checkFullArray($cash_list)){
            foreach ($cash_list as $cash_room_array){
                if(CashRoom::isClosed($cash_room_array['ID'])){
                    $currencies_obj = new Currency();
                    $crds = new CashRoomDay();
                    $crd_id = $crds->startNewDay($cash_room_array['ID']);
                    $cash_rooms = new CashRoom();
                    $cash_room = $cash_rooms->find($cash_room_array['ID']);
                    $currencies = $cash_room->getCurrencies();
                    if(!empty($currencies[0])) {
                        $currency = $currencies_obj->find($currencies[0]);
                        $return_array['message'] = $cash_room_array['NAME'] . ".\nСумма в ".$currency->getGenitive();
                        if($show_button) {
                            $return_array['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Отменить начало смены',
                                            "callback_data" => 'ResetStartDay_' . $crd_id
                                        ]
                                    ]
                                ]
                            ]);
                        }
                    }
                    break;
                }
            }
        }
        return $return_array;
    }
    public static function process(Staff $employee, $data, $is_callback): array
    {
        $buttons = Buttons::getCommonButtons();
        if($is_callback){
            $data['chat']['id'] = $data['message']['chat']['id'];
            if(!empty($data['data'])){
                $response = CREButtons::process($data['data']);
                $message = $response['message'];
                if ($response['buttons'])
                    $buttons = $response['buttons'];
            }
        } else {
            $cashRoomDays = new CashRoomDay();


            if(str_contains($data['text'], Common::getButtonText('cre_apps_list_receive')." (")){
                $arr = explode(" (", $data['text']);
                $data['text'] = $arr[0];
            }
            if(str_contains($data['text'], Common::getButtonText('cre_apps_list_payment')." (")){
                $arr = explode(" (", $data['text']);
                $data['text'] = $arr[0];
            }
            switch ($data['text']) {
                case Common::getButtonText('cre_apps_list_receive'):
                    $applications = new Applications();
                    $list = $applications->getRecieveAppsForCRE();
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
                                    "callback_data" => "showApplicationForCRE_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите заявку из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок на забор пока нет';
                    }
                    break;
                case Common::getButtonText('cre_apps_list_payment'):
                    $applications = new Applications();
                    $list = $applications->getPaymentsAppsForCRE();
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
                                    "callback_data" => "showApplicationForCRE_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите заявку из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок на выдачу пока нет';
                    }
                    break;
                case Common::getButtonText('cre_start_new_work_day'):
                    $cashRoomDays = new CashRoomDay();
                    if($cashRoomDays->isExistsOpenToday($employee->cash_room()->getId())){
                        $message = 'Смена уже открыта';
                    } else {
                        $cashRoomDays = new CashRoomDay();
                        $crd = $cashRoomDays->getOpeningStartedDays();
                        if($crd->getId()>0){
                            $currencies_array = $crd->cash_room()->getCurrencies();
                            $step = (int)$crd->getField("SUM_ENTER_STEP");
                            $currency = (new Currency())->find($currencies_array[$step]);
                            $message = "Действие невозможно. Вы уже открываете смену\n";
                            $message.= $crd->cash_room()->getField('NAME').".";
                            $message.= "\nСумма в ".$currency->getGenitive();
                        } else {
                            $arr = self::startNewCashRoomDay();
                            $message = $arr['message'];
                            $buttons = $arr['buttons'];
                        }

                    }
                    break;
                case Common::getButtonText('cre_end_work_day'):
                    //Common::resetCloseDaySession();
                    $cashRoomDays = new CashRoomDay();
                    $open_today = $cashRoomDays->getOpenToday();
                    if(Common::getCREGiveMoneySession()>0) {
                        $message = "Невозможно начать закрытие смены! Вы работаете с заявкой на выдачу №".Common::getCREGiveMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                    } elseif (Common::getCREReceivePaybackMoneySession()>0) {
                        $message = "Невозможно! Вы работаете с возвратом заявки №".Common::getCREReceivePaybackMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                    } elseif (Common::getCREReceiveMoneySession()>0) {
                        $message = "Невозможно! Вы работаете с заявкой на забор №".Common::getCREReceiveMoneySession().".\nЗакончите её оформление или сбросьте для работы с остальными заявками";
                    } elseif (Common::getCloseDaySession()>0) {
                        $message = "Вы уже закрываете смену\n";
                        $this_day = (new CashRoomDay())->find(Common::getCloseDaySession());
                        $currencies = $this_day->cash_room()->getCurrencies();
                        if (!empty($currencies[0])) {
                            $currency = (new Currency())->find($currencies[0]);
                            $message.= $this_day->cash_room()->getName() . "\nСумма в " . $currency->getGenitive();
                            $buttons = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Отменить завершение смены',
                                            //"callback_data" => 'ResetCloseDay_' . $this_day->getId()
                                            "callback_data" => 'ResetCloseDays'
                                        ]
                                    ]
                                ]
                            ]);
                        }
                    } else {
                        if (count($open_today) > 0) {
                            foreach ($open_today as $cashroomday) {
                                $this_days = new CashRoomDay();
                                $currencies_obj = new Currency();
                                $this_day = $this_days->find($cashroomday['ID']);
                                $this_day->setClosing();
                                Common::setCloseDaySession($cashroomday['ID']);
                                $currencies = $this_day->cash_room()->getCurrencies();
                                if (!empty($currencies[0])) {
                                    $currency = $currencies_obj->find($currencies[0]);
                                    $message = $this_day->cash_room()->getName() . "\nСумма в " . $currency->getGenitive();
                                    $buttons = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => [
                                            [
                                                [
                                                    'text' => 'Отменить завершение смены',
                                                    //"callback_data" => 'ResetCloseDay_' . $this_day->getId()
                                                    "callback_data" => 'ResetCloseDays'
                                                ]
                                            ]
                                        ]
                                    ]);
                                }
                                break;
                            }
                        } else {
                            $message = "Неверная операция";
                        }
                    }
                    break;
                case '/start':
                    $employee->setChatID($data['chat']['id']);
                    $message = 'Здравствуйте. Вы зарегистрированы в системе, приятной работы';

                    break;
                default:
                    $cashRoomDays = new CashRoomDay();
                    $cashRooms = new CashRoom();
                    $applications = new Applications();
                    $app = $applications->getNeedSumEnterApp();
                    if($cashRoomDays->isExistsOpeningStartedDays()){
                        $data['text'] = trim(str_replace(" ","",$data['text']));
                        if ( !is_numeric( $data['text'] ) ) {
                            $message = "Сумма должна быть числовым значением. Повторите ввод";
                            $day = $cashRoomDays->getOpeningStarted($employee->cash_room()->getId());
                            /*$buttons = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Отменить начало смены',
                                            "callback_data" => 'ResetStartDay_' . $day->getId()
                                        ]
                                    ]
                                ]
                            ]);*/
                        } else {
                            $cashRoomDays = new CashRoomDay();
                            $crd = $cashRoomDays->getOpeningStartedDays();
                            if( $crd->getId()>0 ){
                                $currencies_array = $crd->cash_room()->getCurrencies();
                                $step = (int)$crd->getField("SUM_ENTER_STEP");
                                $check_crds = new CashRoomDay();
                                $last_crds = new CashRoomDay();
                                if($check_crds->checkSum($crd->cash_room()->getId(), $step, $data['text'])){
                                    $crd->setStartSumMultiple($data['text']);
                                    $crd->setEstimatedStartSumMultiple($data['text']);
                                    $crd->setStartCurrencyMultiple($currencies_array[$step]);
                                    $crd->setField("SUM_ENTER_STEP", ++$step);
                                    $crd->resetCountAttempts();
                                    if (count($currencies_array) > $step) {
                                        $currencies_obj = new Currency();
                                        $currency = $currencies_obj->find($currencies_array[$step]);
                                        $message = "Сумма в " . $currency->getGenitive();
                                    } else {
                                        $sss = new CashRoomDay();
                                        $ssss = $sss->find($crd->getId());
                                        if($crd->isNeedApprove()) {
                                            $crd->setWaitForSenior();
                                            $senior_markup['message'] = $crd->cash_room()->getName() . ". Суммы не совпали при вводе. Поступил запрос на одобрение открытия смены.\n";
                                            $fact_sums = $ssss->getField('START_SUM');
                                            $fact_currencies = $ssss->getField('START_CURRENCIES');
                                            $estimated_sums = $ssss->getField('ST_SUM');
                                            foreach ($fact_sums as $key => $fact_sum){
                                                if($fact_sum!=$estimated_sums[$key]){
                                                    $c = new Currency();
                                                    $cur = $c->find($fact_currencies[$key]);
                                                    $senior_markup['message'].="\nРасчетная сумма - ".StringHelper::formatSum($estimated_sums[$key])." ".$cur->getCode();
                                                    $senior_markup['message'].="\nВведенная сумма - ".StringHelper::formatSum($fact_sum)." ".$cur->getCode();
                                                    $senior_markup['message'].="\n=======================";
                                                }
                                            }
                                            $senior_markup['buttons'] = json_encode([
                                                'resize_keyboard' => true,
                                                'inline_keyboard' => [
                                                    [
                                                        [
                                                            'text' => 'Одобрить',
                                                            "callback_data" => "AllowOpenDayBySenior_" . $crd->getId()
                                                        ]
                                                    ]
                                                ]
                                            ]);
                                            Telegram::sendMessageToResp($senior_markup['message']);
                                            Telegram::sendMessageToSenior($senior_markup);
                                        } else {
                                            $ssss->setOpen();
                                        }
                                        $another_days = new CashRoomDay();
                                        if ($another_days->isExistsClosedDays()) {
                                            $arr = self::startNewCashRoomDay(false);
                                            $message = $arr['message'];
                                            $buttons = $arr['buttons'];
                                        } else {
                                            $approveDays = new CashRoomDay();
                                            $array = $approveDays->getWaitingForOpenToday();
                                            $crs = new CashRoom();
                                            $crl = $crs->where('ACTIVE', 'Y')->buildQuery()->getArray();
                                            if(ArrayHelper::checkFullArray($array)){
                                                $message = "Открытие смены";
                                                $cashrooms = new CashRoom();
                                                foreach ($array as $crd){
                                                    $cashroom = $cashrooms->find($crd['PROPERTY_CASH_ROOM_VALUE']);
                                                    $message.= "\n\n".$cashroom->getName()." ожидает одобрения открытия старшим";
                                                }
                                                if(count($crl)!=count($array)){
                                                    $message.= "\n\nОткрытие смен в остальных кассах прошло успешно. Приятной работы";
                                                }
                                            } else {
                                                $message = "Открытие смен прошло успешно. Приятной работы";
                                            }
                                            $buttons = json_encode([
                                                'resize_keyboard' => true,
                                                'keyboard' => [
                                                    [
                                                        [
                                                            'text' => Common::getButtonText('cre_apps_list_payment'),
                                                        ],
                                                        [
                                                            'text' => Common::getButtonText('cre_apps_list_receive'),
                                                        ],
                                                        [
                                                            'text' => Common::getButtonText('cre_end_work_day'),
                                                        ],
                                                    ]
                                                ]
                                            ]);
                                        }
                                    }
                                } else {
                                    if((int)$crd->getCountAttempts()==1){
                                        $last_sums = $last_crds->getLastByCashRoom($crd->cash_room()->getId())->getField('END_SUM');
                                        $crd->setStartSumMultiple($data['text']);
                                        $crd->setEstimatedStartSumMultiple($last_sums[$step]);
                                        $crd->setNeedApprove();
                                        $crd->setStartCurrencyMultiple($currencies_array[$step]);
                                        $crd->setField("SUM_ENTER_STEP", ++$step);
                                        if (count($currencies_array) > $step) {
                                            $crd->resetCountAttempts();
                                            $currencies_obj = new Currency();
                                            $currency = $currencies_obj->find($currencies_array[$step]);
                                            $message = $crd->cash_room()->getName() . "\nСумма в " . $currency->getGenitive();
                                        } else {
                                            $sss = new CashRoomDay();
                                            $ssss = $sss->find($crd->getId());
                                            $ssss->setWaitForSenior();
                                            $senior_markup['message'] = $ssss->cash_room()->getName() . ". Суммы не совпали при вводе. Поступил запрос на одобрение открытия смены.\n";
                                            $fact_sums = $ssss->getField('START_SUM');
                                            $fact_currencies = $ssss->getField('START_CURRENCIES');
                                            $estimated_sums = $ssss->getField('ST_SUM');
                                            foreach ($fact_sums as $key => $fact_sum){
                                                if($fact_sum!=$estimated_sums[$key]){
                                                    $c = new Currency();
                                                    $cur = $c->find($fact_currencies[$key]);
                                                    $senior_markup['message'].="\nРасчетная сумма - ".StringHelper::formatSum($estimated_sums[$key])." ".$cur->getCode();
                                                    $senior_markup['message'].="\nВведенная сумма - ".StringHelper::formatSum($fact_sum)." ".$cur->getCode();
                                                    $senior_markup['message'].="\n=======================";
                                                }
                                            }
                                            $senior_markup['buttons'] = json_encode([
                                                'resize_keyboard' => true,
                                                'inline_keyboard' => [
                                                    [
                                                        [
                                                            'text' => 'Одобрить',
                                                            "callback_data" => "AllowOpenDayBySenior_" . $ssss->getId()
                                                        ]
                                                    ]
                                                ]
                                            ]);
                                            Telegram::sendMessageToResp($senior_markup['message']);
                                            Telegram::sendMessageToSenior($senior_markup);
                                            $another_days = new CashRoomDay();

                                            if ($another_days->isExistsClosedDays()) {
                                                $arr = self::startNewCashRoomDay();
                                                $message = $arr['message'];
                                                $buttons = $arr['buttons'];
                                            } else {
                                                $approveDays = new CashRoomDay();
                                                $crs = new CashRoom();
                                                $crl = $crs->where('ACTIVE', 'Y')->buildQuery()->getArray();
                                                $array = $approveDays->getWaitingForOpenToday();
                                                if(ArrayHelper::checkFullArray($array)){
                                                    $message = "Открытие смены";
                                                    $cash_rooms = new CashRoom();
                                                    foreach ($array as $crd2){
                                                        $cash_room = $cash_rooms->find($crd2['PROPERTY_CASH_ROOM_VALUE']);
                                                        $message.= "\n\n".$cash_room->getName()." ожидает одобрения открытия старшим";
                                                    }
                                                    if(count($crl)!=count($array)){
                                                        $message.= "\n\nОткрытие смен в остальных кассах прошло успешно. Приятной работы";
                                                    }
                                                } else {
                                                    $message = "Открытие смен прошло успешно. Приятной работы";
                                                }
                                                $buttons = Buttons::getCommonButtons();
                                            }
                                        }

                                    } else {
                                        $message = "Сумма введена неверно. Повторите ввод";
                                        $crd->setCountAttempts(1);
                                    }
                                }
                            }
                        }
                    } elseif (Common::getCloseDaySession() > 0) {
                        $buttons = Buttons::getCommonButtons();
                        $data['text'] = trim(str_replace(" ","",$data['text']));
                        if ( !is_numeric( $data['text'] ) ) {
                            $message = "Сумма должна быть числовым значением. Повторите ввод";
                            $buttons = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => 'Отменить завершение смены',
                                            //"callback_data" => 'ResetCloseDay_' . $this_day->getId()
                                            "callback_data" => 'ResetCloseDays'
                                        ]
                                    ]
                                ]
                            ]);
                        } else {
                            $cashRoomDays = new CashRoomDay();
                            $crd = $cashRoomDays->find(Common::getCloseDaySession());
                            if( $crd->getId()>0 ) {
                                $currencies_array = $crd->cash_room()->getCurrencies();
                                $step = (int)$crd->getField("SUM_ENTER_STEP");
                                $cash_rooms = new CashRoom();
                                if($cash_rooms->checkClosedSumCurrency($crd->cash_room()->getId(), $currencies_array[$step], $data['text'])){
                                    $crd->setEndSumMultiple($data['text']);
                                    $crd->setEstimatedEndSumMultiple($data['text']);
                                    $crd->setEndCurrencyMultiple($currencies_array[$step]);
                                    $crd->setField("SUM_ENTER_STEP", ++$step);
                                    $crd->resetCountAttempts();
                                    if (count($currencies_array) > $step) {
                                        $currencies_obj = new Currency();
                                        $currency = $currencies_obj->find($currencies_array[$step]);
                                        $message = "Сумма в " . $currency->getGenitive();
                                        $buttons = json_encode([
                                            'resize_keyboard' => true,
                                            'inline_keyboard' => [
                                                [
                                                    [
                                                        'text' => 'Отменить завершение смены',
                                                        "callback_data" => 'ResetCloseDays'
                                                    ]
                                                ]
                                            ]
                                        ]);
                                        /*$buttons = json_encode([
                                            'resize_keyboard' => true,
                                            'inline_keyboard' => [
                                                [
                                                    [
                                                        'text' => 'Отменить завершение смены',
                                                        "callback_data" => 'ResetCloseDay_' . $crd->getId()
                                                    ]
                                                ]
                                            ]
                                        ]);*/
                                    } else {
                                        $sss = new CashRoomDay();
                                        $ssss = $sss->find($crd->getId());
                                        if($crd->isNeedApprove()) {
                                            $ssss->setWaitForCloseBySenior();
                                            $senior_markup['message'] = $ssss->cash_room()->getName() . ". Закрытие смены. Суммы не совпали при вводе. Поступил запрос на одобрение закрытия смены.\n";
                                            $fact_sums = $ssss->getField('END_SUM');
                                            $fact_currencies = $ssss->getField('END_CURRENCIES');
                                            $estimated_sums = $ssss->getField('EN_SUM');
                                            foreach ($fact_sums as $key => $fact_sum){
                                                if($fact_sum!=$estimated_sums[$key]){
                                                    $c = new Currency();
                                                    $cur = $c->find($fact_currencies[$key]);
                                                    $senior_markup['message'].="\nРасчетная сумма - ".StringHelper::formatSum($estimated_sums[$key])." ".$cur->getCode();
                                                    $senior_markup['message'].="\nВведенная сумма - ".StringHelper::formatSum($fact_sum)." ".$cur->getCode();
                                                    $senior_markup['message'].="\n=======================";
                                                }
                                            }
                                            $senior_markup['buttons'] = json_encode([
                                                'resize_keyboard' => true,
                                                'inline_keyboard' => [
                                                    [
                                                        [
                                                            'text' => 'Одобрить',
                                                            "callback_data" => "CloseDayBySenior_" . $ssss->getId()
                                                        ]
                                                    ]
                                                ]
                                            ]);
                                            Telegram::sendMessageToResp($senior_markup['message']);
                                            Telegram::sendMessageToSenior($senior_markup);
                                        } else {
                                            $ssss->setClose();
                                        }
                                        $another_days = new CashRoomDay();
                                        $another_days_array = $another_days->getOpenToday();
                                        if(count($another_days_array)>0){
                                            foreach ($another_days_array as $cashroomday){
                                                $this_days = new CashRoomDay();
                                                $currencies_obj = new Currency();
                                                $this_day = $this_days->find($cashroomday['ID']);
                                                $this_day->setClosing();
                                                Common::setCloseDaySession($this_day->getId());
                                                $currencies = $this_day->cash_room()->getCurrencies();
                                                if(!empty($currencies[0])) {
                                                    $currency = $currencies_obj->find($currencies[0]);
                                                    $message = $this_day->cash_room()->getName() . ".\nСумма в ".$currency->getGenitive();
                                                    $buttons = json_encode([
                                                        'resize_keyboard' => true,
                                                        'inline_keyboard' => [
                                                            [
                                                                [
                                                                    'text' => 'Отменить завершение смены',
                                                                    "callback_data" => 'ResetCloseDays'
                                                                ]
                                                            ]
                                                        ]
                                                    ]);
                                                }
                                                break;
                                            }
                                        } else {
                                            $approveDays = new CashRoomDay();
                                            $array = $approveDays->getWaitingForCloseToday();
                                            $crs = new CashRoom();
                                            $crl = $crs->where('ACTIVE', 'Y')->buildQuery()->getArray();
                                            if(ArrayHelper::checkFullArray($array)){
                                                $message = "Закрытие смены";
                                                $cashrooms = new CashRoom();
                                                foreach ($array as $crd){
                                                    $cashroom = $cashrooms->find($crd['PROPERTY_CASH_ROOM_VALUE']);
                                                    $message.= "\n\n".$cashroom->getName()." ожидает одобрения закрытия старшим";
                                                }
                                                if(count($crl)!=count($array)){
                                                    $message.= "\n\nЗакрытие смен в остальных кассах прошло успешно";
                                                }
                                            } else {
                                                $message = "Закрытие смен прошло успешно";
                                                $buttons = json_encode([
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
                                            Common::resetCloseDaySession();
                                        }
                                    }
                                } else {
                                    if((int)$crd->getCountAttempts()==1){
                                        $cash = $crd->cash_room()->getCash();
                                        $crd->setEndSumMultiple($data['text']);
                                        $crd->setEstimatedEndSumMultiple((int)$cash[$currencies_array[$step]]['free']);
                                        $crd->setNeedApprove();
                                        $crd->setEndCurrencyMultiple($currencies_array[$step]);
                                        $crd->setField("SUM_ENTER_STEP", ++$step);
                                        if (count($currencies_array) > $step) {
                                            $crd->resetCountAttempts();
                                            $currencies_obj = new Currency();
                                            $currency = $currencies_obj->find($currencies_array[$step]);
                                            $message = $crd->cash_room()->getName() . "\nСумма в " . $currency->getGenitive();
                                        } else {
                                            $sss = new CashRoomDay();
                                            $ssss = $sss->find($crd->getId());
                                            $ssss->setWaitForCloseBySenior();
                                            $another_days = new CashRoomDay();
                                            $another_days_array = $another_days->getOpenToday();
                                            $senior_markup['message'] = $ssss->cash_room()->getName() . ". Закрытие смены. Суммы не совпали при вводе. Поступил запрос на одобрение закрытия смены.";
                                            $fact_sums = $ssss->getField('END_SUM');
                                            $fact_currencies = $ssss->getField('END_CURRENCIES');
                                            $estimated_sums = $ssss->getField('EN_SUM');
                                            foreach ($fact_sums as $key => $fact_sum){
                                                if($fact_sum!=$estimated_sums[$key]){
                                                    $c = new Currency();
                                                    $cur = $c->find($fact_currencies[$key]);
                                                    $senior_markup['message'].="\nРасчетная сумма - ".StringHelper::formatSum($estimated_sums[$key])." ".$cur->getCode();
                                                    $senior_markup['message'].="\nВведенная сумма - ".StringHelper::formatSum($fact_sum)." ".$cur->getCode();
                                                    $senior_markup['message'].="\n=======================";
                                                }
                                            }
                                            $senior_markup['buttons'] = json_encode([
                                                'resize_keyboard' => true,
                                                'inline_keyboard' => [
                                                    [
                                                        [
                                                            'text' => 'Одобрить',
                                                            "callback_data" => "CloseDayBySenior_" . $ssss->getId()
                                                        ]
                                                    ]
                                                ]
                                            ]);

                                            Telegram::sendMessageToResp($senior_markup['message']);
                                            Telegram::sendMessageToSenior($senior_markup);
                                            if(count($another_days_array)>0){
                                                foreach ($another_days_array as $cashroomday){
                                                    $this_days = new CashRoomDay();
                                                    $currencies_obj = new Currency();
                                                    $this_day = $this_days->find($cashroomday['ID']);
                                                    $this_day->setClosing();
                                                    Common::setCloseDaySession($this_day->getId());
                                                    $currencies = $this_day->cash_room()->getCurrencies();
                                                    if(!empty($currencies[0])) {
                                                        $currency = $currencies_obj->find($currencies[0]);
                                                        $message = $this_day->cash_room()->getName() . ". Закрытие смены.\nСумма в ".$currency->getGenitive();
                                                    }
                                                    break;
                                                }
                                            } else {
                                                $approveDays = new CashRoomDay();
                                                $crs = new CashRoom();
                                                $crl = $crs->where('ACTIVE', 'Y')->buildQuery()->getArray();
                                                $array = $approveDays->getWaitingForCloseToday();
                                                if(ArrayHelper::checkFullArray($array)){
                                                    $message = "Закрытие смены";
                                                    $cash_rooms = new CashRoom();
                                                    foreach ($array as $crd2){
                                                        $cash_room = $cash_rooms->find($crd2['PROPERTY_CASH_ROOM_VALUE']);
                                                        $message.= "\n\n".$cash_room->getName()." ожидает закрытия открытия старшим";
                                                    }
                                                    if(count($crl)!=count($array)){
                                                        $message.= "\n\nЗакрытие смен в остальных кассах прошло успешно. Приятной работы";
                                                    }
                                                } else {
                                                    $message = "Закрытие смен прошло успешно.";
                                                    $buttons = json_encode([
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
                                                Common::resetCloseDaySession();
                                            }
                                        }

                                    } else {
                                        $message = "Сумма введена неверно. Повторите ввод";
                                        $crd->setCountAttempts(1);
                                    }
                                }
                            }

                        }
                    } elseif( Common::getCREReceiveMoneySession() > 0 ){
                        $data['text'] = trim(str_replace(" ","",$data['text']));
                        if ( !is_numeric( $data['text'] ) ) {
                            $message = "Сумма должна быть числовым значением\nВведите привезенную сумму";
                        } else {
                            $app = (new Applications())->find(Common::getCREReceiveMoneySession());
                            if($app->getField('SUM_ENTER_STEP')==1) {
                                $app->setSumMultiple($data['text']);
                                $app->setField('SUM_ENTER_STEP', 0);
                                $cash_room_currencies = $app->cash_room()->getCurrencies();
                                $exists_app_currencies = (new Applications())->find($app->getId())->getCurrencies();
                                if(count($cash_room_currencies)==count($exists_app_currencies)){
                                    $message = "Данные записаны. Введенная сумма ".implode(', ', (new Applications())->find($app->getId())->getCash());
                                    $buttons = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => [
                                            [
                                                [
                                                    'text' => 'Изменить сумму',
                                                    "callback_data" => "CorrectPrevSum_".$app->getId()
                                                ],
                                                [
                                                    'text' => 'Закрыть заявку',
                                                    "callback_data" => "CRECompleteReceiveSum_".$app->getId()
                                                ],
                                                [
                                                    'text' => 'Сброс заявки',
                                                    "callback_data" => "ResetCREApp_".$app->getId()
                                                ]
                                            ]
                                        ]
                                    ]);
                                } else {
                                    $message = "Данные записаны. Введенная сумма ".implode(', ', (new Applications())->find($app->getId())->getCash()).". Есть сумма в другой валюте?";
                                    $buttons = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => [
                                            [
                                                [
                                                    'text' => 'Изменить сумму',
                                                    "callback_data" => "CorrectPrevSum_".$app->getId()
                                                ],
                                                [
                                                    'text' => 'Закрыть заявку',
                                                    "callback_data" => "CRECompleteReceiveSum_".$app->getId()
                                                ],
                                                [
                                                    'text' => 'Добавить сумму',
                                                    "callback_data" => "CREReceiveSum_".$app->getId()
                                                ],
                                                [
                                                    'text' => 'Сброс заявки',
                                                    "callback_data" => "ResetCREApp_".$app->getId()
                                                ]
                                            ]
                                        ]
                                    ]);
                                }

                            }
                        }
                    } elseif ( Common::getCREReceivePaybackMoneySession() > 0 ) {
                        $data['text'] = trim(str_replace(" ","",$data['text']));
                        if ( !is_numeric( $data['text'] ) ) {
                            $message = "Сумма должна быть числовым значением\nВведите привезенную сумму";
                        } else {
                            $return_application = (new Applications())->find(Common::getCREReceivePaybackMoneySession());
                            $cash = $return_application->getCash();
                            if(count($cash) == 1){
                                $real_sums = $return_application->getRealSum();
                                $exists_sum = $real_sums[0];
                                if( (int)$data['text'] != $exists_sum ){
                                    $message = "Суммы не совпадают\nПовторите ввод";
                                    $inline_keys[] = [
                                        [
                                            'text' => "Сброс заявки",
                                            "callback_data" => 'ResetPaybackCREApp_' . $app->getId()
                                        ]
                                    ];

                                    $buttons = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $inline_keys
                                    ]);
                                } else {
                                    $return_application->setReturned();
                                    $orders = $return_application->order();
                                    if(ArrayHelper::checkFullArray($orders)){
                                        foreach ($orders as $order){
                                            $ord_obj = new Order();
                                            $ord_obj->find($order['ID'])->setStatus(51);
                                        }
                                    }
                                    $cash = $return_application->getCash();
                                    $message = "Приход (Возврат) ".$return_application->getField('AGENT_OFF_NAME').". ".implode(", ", $cash)."\n";
                                    Mattermost::send($message, $return_application->cash_room()->getMatterMostChannel());
                                    $markup['message'] = $return_application->getField("AGENT_OFF_NAME").". №" . (int)$return_application->getId() . ". Средства были возвращены";
                                    Telegram::sendMessageToManager($markup, (int)$return_application->getId());
                                    Telegram::sendMessageToResp($markup['message']);
                                    Common::resetCREReceivePaybackMoneySession();
                                    $message = "Средства возвращены, заявка №".$return_application->getId()." помечена как отмененная";
                                }
                            } else {
                                $step = (int)$return_application->getField('PAYBACK_SUM_ENTER_STEP');
                                $real_sums = $return_application->getRealSum();
                                $exists_sum = $real_sums[$step];
                                if( (int)$data['text'] != $exists_sum ){
                                    $message = "Суммы не совпадают\nПовторите ввод";
                                    $inline_keys[] = [
                                        [
                                            'text' => "Сброс заявки",
                                            "callback_data" => 'ResetPaybackCREApp_' . $app->getId()
                                        ]
                                    ];

                                    $buttons = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $inline_keys
                                    ]);
                                } else {
                                    $count_of_enters = $step+1;
                                    if($count_of_enters<count($cash)){
                                        $return_application->setField('PAYBACK_SUM_ENTER_STEP', $count_of_enters);
                                        $currencies = new Currency();
                                        $currencies_array = $return_application->getCurrencies();
                                        if(ArrayHelper::checkFullArray($currencies_array)){
                                            $currency = $currencies->find($currencies_array[$count_of_enters]);
                                            $message = 'Введите привезенную сумму в '.$currency->getGenitive();
                                            $inline_keys[] = [
                                                [
                                                    'text' => "Сброс заявки",
                                                    "callback_data" => 'ResetPaybackCREApp_' . $app->getId()
                                                ]
                                            ];

                                            $buttons = json_encode([
                                                'resize_keyboard' => true,
                                                'inline_keyboard' => $inline_keys
                                            ]);
                                        }
                                    } else {
                                        $return_application->setReturned();
                                        $orders = $return_application->order();
                                        if(ArrayHelper::checkFullArray($orders)){
                                            foreach ($orders as $order){
                                                $ord_obj = new Order();
                                                $ord_obj->find($order['ID'])->setStatus(51);
                                            }
                                        }
                                        $cash = $return_application->getCash();
                                        $message = "Приход (Возврат) ".$return_application->getField('AGENT_OFF_NAME').". ".implode(", ", $cash)."\n";
                                        Mattermost::send($message, $return_application->cash_room()->getMatterMostChannel());
                                        $markup['message'] = "Средства по заявке №" . (int)$return_application->getId() . " были возвращены";
                                        Telegram::sendMessageToManager($markup, (int)$return_application->getId());
                                        Telegram::sendMessageToResp($markup['message']);
                                        Common::resetCREReceivePaybackMoneySession();
                                        $message = "Средства возвращены, заявка №" . $return_application->getId() . " помечена как отмененная";
                                    }
                                }
                            }

                        }
                    } else {
                        $message = "Вы ввели неизвестную мне команду :/";
                    }

            }
        }
        return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
    }
}