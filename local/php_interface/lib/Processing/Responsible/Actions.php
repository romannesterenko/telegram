<?php
namespace Processing\Responsible;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Processing\Responsible\Buttons as RespButtons;
use Processing\Responsible\Markup as RespMarkup;
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
                        'text' => Common::getButtonText('resp_apps_list_to_work')
                    ],
                    [
                        'text' => Common::getButtonText('resp_apps_list_new')
                    ],
                    [
                        'text' => Common::getButtonText('resp_cash_room_list')
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
                case Common::getButtonText('resp_apps_list_to_work'):
                    $applications = new Applications();
                    $list = $applications->getToWorkAppsForCashResp();
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $text = '';
                            if($application['PROPERTY_OPERATION_TYPE_VALUE'])
                                $text.=$application['PROPERTY_OPERATION_TYPE_VALUE'] . ". ";
                            $inline_keyboard[] = [
                                [
                                    "text" => '№'.$application['ID'].'. '.$text . $application['PROPERTY_STATUS_VALUE'] . '. Создана ' . $application['CREATED_DATE'],
                                    "callback_data" => "showApplicationForResponse_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите заявку из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Заявок пока нет';
                    }
                    break;
                //запрос списка заявок
                case Common::getButtonText('resp_apps_list_new'):
                    $applications = new Applications();
                    $list = $applications->getAppsForResp();
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $text = '';
                            if($application['PROPERTY_OPERATION_TYPE_VALUE'])
                                $text.=$application['PROPERTY_OPERATION_TYPE_VALUE'] . ". ";
                            $inline_keyboard[] = [
                                [
                                    "text" => '№'.$application['ID'].'. '.$text . $application['PROPERTY_STATUS_VALUE'] . '. Создана ' . $application['CREATED_DATE'],
                                    "callback_data" => "showApplicationForResponse_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите заявку из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Заявок пока нет';
                    }
                    break;
                //запрос списка касс
                case Common::getButtonText('resp_cash_room_list'):
                    $response = RespMarkup::getRespCashRoomListMarkup();
                    $message = $response['message'];
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
                    if ($applications->getNeedCancelByRespId() > 0){
                        $response = $applications->getNeedCancelByResp()->cancelByResp($data['text']);
                        $message = $response['message'];
                        if ($response['buttons'])
                            $buttons = $response['buttons'];
                        //заполнение данных в заявке
                    } elseif ($applications->getInProcessByResp() > 0) {
                        $response = $applications->setFieldToInProcess($applications->getInProcessByResp(), $data['text']);
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