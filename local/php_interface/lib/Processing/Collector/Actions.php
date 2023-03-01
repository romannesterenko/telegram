<?php
namespace Processing\Collector;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
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
            switch ($data['text']) {
                case 'Новые заявки':
                    $applications = new Applications();
                    $list = $applications->getNewAppsByCrew($employee->crew()->getId());
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => '№'.$application['ID'].'. Создана ' . $application['CREATED_DATE'],
                                    "callback_data" => "showApplicationForCollector_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Список новых заявок';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок пока нет';
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
                                    "text" => '№'.$application['ID'].'. Создана ' . $application['CREATED_DATE'],
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
                case 'Заявки на забор':
                    $applications = new Applications();
                    $list = $applications->getGiveAppsByCrew($employee->crew()->getId());
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => '№'.$application['ID'].'. Создана ' . $application['CREATED_DATE'],
                                    "callback_data" => "showApplicationForCollector_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Список назначеных заявок';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок пока нет';
                    }
                    break;
                case '/start':
                    $employee->setChatID($data['chat']['id']);
                    $message = 'Здравствуйте, вы успешно зарегистрировались в системе';
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
                    break;
                //другие текстовые данные
                default:
                    $message = 'К сожалению, вы ввели неизвестную мне команду :/';
            }
        }
        return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
    }
}