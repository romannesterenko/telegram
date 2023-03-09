<?php
namespace Processing\CashRoomSenior;
use Api\Telegram;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoomDay;
use Processing\CashRoomSenior\Buttons as CRSButtons;
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
                        'text' => Common::getButtonText('crs_waiting_for_open')
                    ],
                    [
                        'text' => Common::getButtonText('crs_waiting_for_close')
                    ]
                ]
            ]
        ]);
        $message = Common::getWrongTextData();

        if($is_callback){
            $data['chat']['id'] = $data['message']['chat']['id'];
            if(!empty($data['data'])){
                $response = CRSButtons::process($data['data']);
                $message = $response['message'];
                if ($response['buttons'])
                    $buttons = $response['buttons'];
            }
        } else {
            switch ($data['text']) {
                case Common::getButtonText('crs_waiting_for_open'):
                    $cash_room_days = new CashRoomDay();
                    $cash_room_day_list = $cash_room_days->getWaitingForOpenToday();
                    if(ArrayHelper::checkFullArray($cash_room_day_list)){
                        $message = "Список касс, ожидающих одобрения открытия смены\n";
                        foreach ($cash_room_day_list as $item) {
                            $inline_keyboard[] = [
                                [
                                    "text" => $item['NAME']." Сумма - ".number_format($item['PROPERTY_START_SUM_VALUE'], 0, '', ' '),
                                    "callback_data" => "showApproveDay_" . $item['ID']
                                ]
                            ];
                        }
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    }else{
                        $message = "Запросов на открытие смены на сегодня нет";
                    }
                    break;
                case Common::getButtonText('crs_waiting_for_close'):
                    $cash_room_days = new CashRoomDay();
                    $cash_room_day_list = $cash_room_days->getWaitingForCloseToday();
                    if(ArrayHelper::checkFullArray($cash_room_day_list)){
                        $inline_keyboard = [];
                        $message = "Список касс, ожидающих одобрения закрытия смены\n";
                        foreach ($cash_room_day_list as $item) {
                            $inline_keyboard[] = [
                                [
                                    "text" => $item['NAME']." Сумма - ".number_format($item['PROPERTY_END_SUM_VALUE'], 0, '', ' '),
                                    "callback_data" => "showApproveCloseDay_" . $item['ID']
                                ]
                            ];
                        }
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    }else{
                        $message = 'Запросов на закрытие смены на сегодня нет';
                    }
                    break;
                case '/start':
                    $employee->setChatID($data['chat']['id']);
                    $message = Common::getHelloCommonMessage();
                    break;
            }
        }
        return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
    }
}