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
                                    "text" => '№'.$application['ID'].'. '.$text . $application['PROPERTY_STATUS_VALUE'] . '. Создана ' . $application['CREATED_DATE'],
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
                //запрос списка касс
                case Common::getButtonText('resp_cash_room_list'):
                    $response = RespMarkup::getRespCashRoomListMarkup();
                    $message = $response['message'];
                    break;
                //успешная авторизация в приложении команда /start
                case '/start':
                    $employee->setChatID($data['chat']['id']);
                    $message = 'Здравствуйте. Управляйте заявками из меню ниже';
                    $buttons = json_encode([
                        'resize_keyboard' => true,
                        'keyboard' => [
                            [
                                [
                                    'text' => Common::getButtonText('resp_apps_list_new')
                                ]
                            ]
                        ]
                    ]);
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
                    } elseif ($applications->getInProcessByCollResp() > 0) {
                        $response = $applications->setFieldToInProcess($applications->getInProcessByCollResp(), $data['text']);
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