<?php
namespace Processing\Collector;
use Api\Telegram;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\ElementModel;
use Processing\Responsible\Buttons as RespButtons;
use Processing\CashRoomEmployee\Buttons as CREButtons;
use Processing\Collector\Buttons as CollectorButtons;
use Processing\Responsible\Markup as RespMarkup;
use Settings\Common;

class Actions
{
    public static function process(\Models\Staff $employee, $data, $is_callback): array
    {
        $buttons = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [
                    [
                        'text' => 'Новые заявки'
                    ],
                    [
                        'text' => 'Заявки в доставке'
                    ],
                    [
                        'text' => 'Заявки на забор'
                    ]
                ]
            ]
        ]);
        if($is_callback){
            $data['chat']['id'] = $data['message']['chat']['id'];
            if(!empty($data['data'])){
                $response = CollectorButtons::process($data['data']);
                $message = $response['message'];
                if ($response['buttons'])
                    $buttons = $response['buttons'];
            }
        } else {
            $buttons = json_encode([
                'resize_keyboard' => true,
                'keyboard' => [
                    [
                        [
                            'text' => 'Выдача'
                        ],
                        [
                            'text' => 'Забор'
                        ],
                        [
                            'text' => 'Заявки в доставке'
                        ]
                    ]
                ]
            ]);
            switch ($data['text']) {
                case 'Выдача':
                    $applications = new Applications();
                    //$list = $applications->getNewAppsByCrew($employee->crew()->getId());
                    $list = $applications->getPaymentsAppsByCrew($employee->crew()->getId());
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => '№'.$application['ID'].'. Сумма ' . $application['PROPERTY_SUM_VALUE'],
                                    "callback_data" => "showApplicationForCollector_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Список новых заявок';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок на выдачу пока нет';
                    }
                    break;
                case 'Заявки в доставке':
                    $applications = new Applications();
                    $list = $applications->getAppsInDeliveryByCrew($employee->crew()->getId());
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => '№'.$application['ID'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].'. Сумма ' . number_format($application['PROPERTY_SUMM_VALUE'], 0, '', ' ').". Контактное лицо - ".$application['PROPERTY_AGENT_NAME_VALUE'],
                                    "callback_data" => "showApplicationForCollector_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Список заявок в доставке';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок пока нет';
                    }
                    break;
                case 'Забор':
                    $applications = new Applications();
                    $list = $applications->getGiveAppsByCrew($employee->crew()->getId());
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => '№'.$application['ID'].'. Сумма ' . number_format($application['PROPERTY_SUMM_VALUE'], 0, '', ' ').". Контактное лицо - ".$application['PROPERTY_AGENT_NAME_VALUE'],
                                    "callback_data" => "showApplicationForCollector_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Список назначеных заявок';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок на забор пока нет';
                    }
                    break;
                case '/start':
                    $employee->setChatID($data['chat']['id']);
                    $message = 'Здравствуйте, вы успешно зарегистрировались в системе';
                    break;
                //другие текстовые данные
                default:
                    $nr_applications = new Applications();
                    $g_applications = new Applications();
                    $not_recieve_application = $nr_applications->getNeedCommentToReceive($employee->crew());
                    $not_gave_application = $g_applications->getNeedCommentToGive($employee->crew());
                    //Деньги не получены, ввод коментария
                    if($not_recieve_application->getId()>0){
                        $not_recieve_application->setField('WHY_NOT_RECIEVE', $data['text']);
                        $not_recieve_application->setProblemStatus();
                        $not_recieve_application->order()->setStatus(57);
                        $message = "Деньги не получены. Заявка помечена как проблемная.";
                        //сообщения менеджеру и ответственному об изменении статуса
                        $markup['message'] = "Информация по заявке №" . $not_recieve_application->getId() . "\n";
                        $markup['message'] .= "Экипаж <b>" . $not_recieve_application->crew()->getName() . "</b> не забрал деньги по адресу <b>" . $not_recieve_application->getField('ADDRESS') . "</b> \nПричина - <b>" . $data['text'] . "</b>\nЗаявка помечена как проблемная.";
                        Telegram::sendMessageToManager($markup, $not_recieve_application->getId());
                        if($not_recieve_application->isPayment())
                            Telegram::sendMessageToResp($markup['message']);
                        else
                            Telegram::sendMessageToCollResp($markup['message']);
                    //Деньги не переданы, ввод коментария
                    } elseif ($not_gave_application->getId()>0){
                        $not_gave_application->setField('WHY_NOT_GIVE', $data['text']);
                        $not_gave_application->setFailed();
                        $markup['message'] = "Заявка №" . $not_gave_application->getID() . " не выполнена. Экипаж <b>".$not_gave_application->crew()->getName()."</b> не передал сумму контрагенту <b>".$not_gave_application->getField('AGENT_NAME')."</b>. \nПричина - <b>" . $data['text'] . "</b>. \nДеньги направляются обратно в кассу <b>".$not_gave_application->cash_room()->getName()."</b>";
                        $message = "Заявка №" . $not_gave_application->getId() . " не была выполнена. Отвезите средства в кассу <b>".$not_gave_application->cash_room()->getName()."</b>";
                        Telegram::sendMessageToManager($markup, $not_gave_application->getId());
                        if($not_gave_application->isPayment())
                            Telegram::sendMessageToResp($markup['message']);
                        else
                            Telegram::sendMessageToCollResp($markup['message']);
                    } else {
                        $message = 'К сожалению, вы ввели неизвестную мне команду :/';
                    }

            }
        }
        return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
    }
}