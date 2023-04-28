<?php
namespace Processing\Collector;
use Api\Sender;
use Api\Telegram;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\ElementModel;
use Models\Order;
use Processing\Responsible\Buttons as RespButtons;
use Processing\CashRoomEmployee\Buttons as CREButtons;
use Processing\Collector\Buttons as CollectorButtons;
use Processing\Responsible\Markup as RespMarkup;
use Settings\Common;

class Actions
{
    public static function process(\Models\Staff $employee, $data, $is_callback): array
    {
        if($is_callback){
            $data['chat']['id'] = $data['message']['chat']['id'];
            if(!empty($data['data'])){
                $buttons = Buttons::getCommonButtons($employee->crew()->getId());
                $response = CollectorButtons::process($data['data']);
                $message = $response['message'];
                if ($response['buttons'])
                    $buttons = $response['buttons'];
            }
        } else {
            $buttons = Buttons::getCommonButtons($employee->crew()->getId());
            if(str_contains($data['text'], "Выдача (")){
                $arr = explode(" (", $data['text']);
                $data['text'] = $arr[0];
            }
            if(str_contains($data['text'], "Забор (")){
                $arr = explode(" (", $data['text']);
                $data['text'] = $arr[0];
            }
            if(str_contains($data['text'], "В доставке (")){
                $arr = explode(" (", $data['text']);
                $data['text'] = $arr[0];
            }
            switch ($data['text']) {
                case 'Выдача':
                    $applications = new Applications();
                    $list = $applications->getPaymentsAppsByCrew($employee->crew()->getId());
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
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
                case 'В доставке':
                    $applications = new Applications();
                    $list = $applications->getAppsInDeliveryByCrew($employee->crew()->getId());
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
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
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
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
                        $orders = $not_recieve_application->order();
                        if(ArrayHelper::checkFullArray($orders)){
                            foreach ($orders as $order){
                                $ord_obj = new Order();
                                $ord_obj->find($order['ID'])->setStatus(57);
                            }
                        }
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
                    } elseif ( $not_gave_application->getId()>0 ) {
                        $not_gave_application->setField('WHY_NOT_GIVE', $data['text']);
                        $not_gave_application->setFailed();
                        $message = "Отвезите деньги обратно в кассу ".$not_gave_application->cash_room()->getName();
                        $markup["message"] = "Возврат из доставки заявки №".$not_gave_application->getId();
                        Telegram::sendMessageToManager($markup, $not_gave_application->getId());
                        $cash = $not_gave_application->getCash();
                        $message_to_cash_room['message'] = "Принять приход (возврат доставки). Контрагент - ".$not_gave_application->getField('AGENT_OFF_NAME').". ".implode(', ', $cash);
                        $message_to_cash_room['buttons'] = \Processing\CashRoomEmployee\Buttons::getCommonButtons();
                        Telegram::sendCommonMessageToCashRoom($message_to_cash_room);

                        $client_message = "Отмена доставки заявки №".$not_gave_application->getId();
                        try {
                            Sender::send($not_gave_application, $client_message);
                        } catch(\Exception $e) {
                            LogHelper::write($e->getMessage());
                        }
                    } else {
                        $message = 'К сожалению, вы ввели неизвестную мне команду :/';
                    }

            }
        }
        return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
    }
}