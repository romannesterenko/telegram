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
                        $message = 'Операция недоступна';
                    }
                }
                break;
            case 'showApplicationForCRE':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if ($app->isPayment()) {
                        $message = "Заявка №" . $app->getId() . "\nВыдать <b>" . $app->crew()->getName() . "</b> сумму в размере <b>" . number_format($app->getSum(), 0, ',', ' ') . "</b>";
                        $response['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => [
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
                            ]
                        ]);
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
                    $sum = $real_sum = $app->getSum();
                    $app->setField('REAL_SUM', $real_sum);
                    $app->setStatus(20);
                    $app->order()->setRealSum($real_sum);
                    $message = 'Выдана сумма в размере <b>'.number_format($sum, 0, ',', ' ').'</b>';
                }
                break;
            case 'CREReceiveSum':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    $app->setStatus(26);
                    $message = 'Введите привезенную сумму';
                }
                break;
            case 'CREGiveRound50':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    $sum = $app->getSum();
                    $real_sum = 50*round($sum/50);
                    $app->setField('REAL_SUM', $real_sum);
                    $app->setStatus(20);
                    $app->order()->setRealSum($real_sum);
                    $message = 'Выдана сумма в размере <b>'.number_format($real_sum, 0, ',', ' ').'</b>';
                }
                break;
            case 'CREGiveRound100':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    $sum = $app->getSum();
                    $real_sum = round($sum, -2);
                    $app->setField('REAL_SUM', $real_sum);
                    $app->setStatus(20);
                    $app->order()->setRealSum($real_sum);
                    $message = 'Выдана сумма в размере <b>'.number_format($real_sum, 0, ',', ' ').'</b>';
                }
                break;
            case 'CREGiveRound1000':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    $sum = $app->getSum();
                    $real_sum = round($sum, -3);
                    $app->setField('REAL_SUM', $real_sum);
                    $app->setStatus(20);
                    $app->order()->setRealSum($real_sum);
                    $message = 'Выдана сумма в размере <b>'.number_format($real_sum, 0, ',', ' ').'</b>';
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }
}