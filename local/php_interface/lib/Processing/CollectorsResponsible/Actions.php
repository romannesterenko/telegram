<?php
namespace Processing\CollectorsResponsible;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Processing\CollectorsResponsible\Buttons as RespButtons;
use Processing\CollectorsResponsible\Markup as RespMarkup;
use Settings\Common;

class Actions
{
    public static function process(\Models\Staff $employee, $data, $is_callback): array
    {
        $message = 'К сожалению, вы ввели неизвестную мне команду :/';
        $buttons = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [
                    [
                        'text' => "Архив"
                    ],
                    [
                        'text' => "Заявки экипажей"
                    ],
                    [
                        'text' => "Заявки в работу"
                    ],
                    [
                        'text' => Common::getButtonText('resp_apps_list_new')
                    ]
                ]
            ]
        ]);
        if($is_callback){
            $data['chat']['id'] = $data['message']['chat']['id'];
            if(!empty($data['data'])){
                $response = RespButtons::process($data['data']);
                $message = $response['message'];
                if ($response['buttons'])
                    $buttons = $response['buttons'];
            }
        } else {
            switch ($data['text']) {
                //запрос списка заявок
                case "Архив":
                    $inline_keyboard[] = [
                        [
                            "text" => "Заявки за сегодня",
                            "callback_data" => "todayApps_10_1"
                        ]
                    ];
                    $inline_keyboard[] = [
                        [
                            "text" => "Заявки за вчера",
                            "callback_data" => "yesterdayApps_10_1"
                        ]
                    ];
                    $inline_keyboard[] = [
                        [
                            "text" => "Заявки за неделю",
                            "callback_data" => "weekApps_10_1"
                        ]
                    ];
                    $message = "Меню архива заявок";
                    $keyboard = array("inline_keyboard" => $inline_keyboard);
                    $buttons = json_encode($keyboard);
                    break;
                case "Заявки экипажей":
                    $applications = new Applications();
                    $list = $applications->getCrewAppsForCollResp();
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $inline_keyboard[] = [
                                [
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
                                    "callback_data" => "showCollectorsApplicationForResponse_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите заявку из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок пока нет';
                    }
                    break;
                case "Заявки в работу":
                    $applications = new Applications();
                    $list = $applications->getToWorkAppsForCollResp();
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $text = '';
                            if($application['PROPERTY_OPERATION_TYPE_VALUE'])
                                $text.=$application['PROPERTY_OPERATION_TYPE_VALUE'] . ". ";
                            $inline_keyboard[] = [
                                [
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
                                    "callback_data" => "showApplicationForResponse_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите заявку из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок пока нет';
                    }
                    break;
                case Common::getButtonText('resp_apps_list_new'):
                    $applications = new Applications();
                    $list = $applications->getAppsForCollResp();
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $text = '';
                            if($application['PROPERTY_OPERATION_TYPE_VALUE'])
                                $text.=$application['PROPERTY_OPERATION_TYPE_VALUE'] . ". ";
                            $inline_keyboard[] = [
                                [
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
                                    "callback_data" => "showApplicationForResponse_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите заявку из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок пока нет';
                    }
                    break;
                //успешная авторизация в приложении команда /start
                case '/start':
                    $employee->setChatID($data['chat']['id']);
                    $message = 'Здравствуйте. Управляйте заявками из меню ниже';
                    break;
                //другие текстовые данные
                default:
                    $applications = new Applications();
                    //если заявка в стадии черновик
                    if ($applications->getNeedCancelByCollRespId() > 0){
                        $response = $applications->getNeedCancelByCollResp()->cancelByResp($data['text']);
                        $message = $response['message'];
                        if ($response['buttons'])
                            $buttons = $response['buttons'];
                        //заполнение данных в заявке
                    } elseif (Common::DuringAppByCollResponsible()>0) {
                        $response = $applications->setFieldToInProcess(Common::DuringAppByCollResponsible(), $data['text']);
                        $message = $response['message'];
                        if ($response['buttons'])
                            $buttons = $response['buttons'];
                    } else {
                        $message = 'К сожалению, вы ввели неизвестную мне команду :/';
                    }
            }
        }
        return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
    }
}