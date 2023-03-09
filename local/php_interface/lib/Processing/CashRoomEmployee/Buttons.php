<?php
namespace Processing\CashRoomEmployee;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoomDay;
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
                            $message = "Заявка №" . $app->getId() . "\nВозврат средств. Получить от <b>" . $app->crew()->getName() . "</b> сумму в размере <b>" . number_format($app->getSum(), 0, ',', ' ') . "</b>";
                            $inline_keys = [];
                            $inline_keys[] = [
                                [
                                    'text' => 'Получить деньги',
                                    "callback_data" => 'GivePayBackMoney_' . $app->getId()
                                ]
                            ];
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => $inline_keys
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
                            ]);
                        } else {
                            $message = "Заявка №" . $app->getId() . "\nВыдать <b>" . $app->crew()->getName() . "</b> сумму в размере <b>" . number_format($app->getSum(), 0, ',', ' ') . "</b>";
                            //if($app->getSum())
                            $inline_keys = [];
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
                            }
                            $response['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => $inline_keys
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
                            ]);
                        }
                    } else {
                        $message = "Заявка №" . $app->getId() . "\nПолучить деньги от <b>" . $app->crew()->getName() . "</b>";
                        $response['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => 'Получить деньги (Ввод суммы)',
                                        "callback_data" => 'CREReceiveSum_' . $app->getId()
                                    ]
                                ]
                            ]
                        ]);
                    }
                }
                break;
            case 'CREGiveAsIs':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()==23) {
                        $sum = $real_sum = $app->getSum();
                        $app->setField('REAL_SUM', $real_sum);
                        $app->setStatus(20);
                        $app->order()->setRealSum($real_sum);
                        $app->order()->setInDelivery();
                        $message = 'Выдана сумма в размере <b>' . number_format($sum, 0, ',', ' ') . "</b> экипажу <b>".$app->crew()->getName()."</b> согласно заявки №".$app->getId();
                    } else {
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'CREReceiveSum':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()==20) {
                        $app->setStatus(26);
                        $message = 'Введите привезенную сумму';
                    } else {
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'CREGiveRound50':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()==23) {
                        $sum = $app->getSum();
                        $real_sum = 50 * round($sum / 50);
                        $app->setField('REAL_SUM', $real_sum);
                        $app->setStatus(20);
                        $app->order()->setRealSum($real_sum);
                        $app->order()->setInDelivery();
                        $message = 'Выдана сумма в размере <b>' . number_format($real_sum, 0, ',', ' ') . "</b> экипажу <b>".$app->crew()->getName()."</b> согласно заявки №".$app->getId();
                    }else{
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'CREGiveRound100':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()==23) {
                        $sum = $app->getSum();
                        $real_sum = round($sum, -2);
                        $app->setField('REAL_SUM', $real_sum);
                        $app->setStatus(20);
                        $app->order()->setRealSum($real_sum);
                        $app->order()->setInDelivery();
                        $message = 'Выдана сумма в размере <b>' . number_format($real_sum, 0, ',', ' ') . "</b> экипажу <b>".$app->crew()->getName()."</b> согласно заявки №".$app->getId();
                    } else {
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'CREGiveRound1000':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus()==23) {
                        $sum = $app->getSum();
                        $real_sum = round($sum, -3);
                        $app->setField('REAL_SUM', $real_sum);
                        $app->setStatus(20);
                        $app->order()->setRealSum($real_sum);
                        $app->order()->setInDelivery();
                        $message = "Выдана сумма в размере <b>" . number_format($real_sum, 0, ',', ' ') . "</b> экипажу <b>".$app->crew()->getName()."</b> согласно заявки №".$app->getId();
                    } else {
                        $message = Common::getWrongAppActionText();
                    }
                }
                break;
            case 'GivePayBackMoney':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    $app->setStatus(54);
                    $message = 'Введите привезенную сумму';
                    //$message = $app->getSum();
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }
}