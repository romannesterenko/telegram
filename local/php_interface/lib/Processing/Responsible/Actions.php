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
        $buttons = Buttons::getMenuButtons();
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
                case 'Черновики':
                    $applications = new Applications();
                    $list = $applications->where('PROPERTY_DRAFT', 1)->where('PROPERTY_STATUS', [4, 15, 49, 44])->where('PROPERTY_OPERATION_TYPE', 8)->where('!PROPERTY_FOR_COL_RESP', 1)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE', 'PROPERTY_SUMM', 'PROPERTY_AGENT_OFF_NAME'])->get()->getArray();
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $text = '';
                            if($application['PROPERTY_OPERATION_TYPE_VALUE'])
                                $text.=$application['PROPERTY_OPERATION_TYPE_VALUE'] . ". ";
                            $inline_keyboard[] = [
                                [
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
                                    "callback_data" => "showDraftForResponse_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите черновик из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Черновиков пока нет';
                    }
                    break;
                case 'Создать заявку':
                    if(!Common::isAllowToCreateApps()){
                        $message = "Создание заявок после ".Common::getTimeForApps()." запрещено";
                    } else {
                        /*Common::resetDuringCreateAppByResponsible();
                        Common::resetDuringAppByResponsible();*/
                        if (Common::DuringCreateAppByResponsible() > 0) {
                            $message = 'Вы уже создаете заявку №' . Common::DuringCreateAppByResponsible();
                            $markup = Markup::getCreateAppMarkup(Common::DuringCreateAppByResponsible());
                            $message .= "\n" . $markup['message'] . "\n";
                            $buttons = $markup['buttons'];
                        } elseif (Common::DuringAppByResponsible() > 0) {
                            $during_app = (new Applications())->find(Common::DuringAppByResponsible());
                            $message = 'Создание невозможно! Вы уже работаете с заявкой №' . $during_app->getId() . " (" . $during_app->contragent() . ")";
                            $markup = Markup::getCreateAppMarkup(Common::DuringAppByResponsible());
                            $message .= "\n" . $markup['message'] . "\n";
                            $buttons = $markup['buttons'];
                        } else {
                            $app_id = (new Applications())->createNewDraft((int)$employee->getField('ID'));
                            Common::SetDuringCreateAppByResponsible($app_id);
                            $markup = Markup::getCreateAppMarkup($app_id);
                            $message = $markup['message'];
                            $buttons = $markup['buttons'];
                        }
                    }
                    break;
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
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
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
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
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
                    Common::resetDuringAppByResponsible();
                    $employee->setChatID($data['chat']['id']);
                    $message = 'Здравствуйте. Управляйте заявками из меню ниже';
                    break;
                //другие текстовые данные
                default:
                    $message = 'К сожалению, вы ввели неизвестную мне команду :/';
                    $applications = new Applications();
                    if(Common::DuringCreateAppByResponsible()>0){
                        $response = $applications->setFieldToInDraftByResp(Common::DuringCreateAppByResponsible(), $data['text']);
                        $message = $response['message'];
                        if ($response['buttons'])
                            $buttons = $response['buttons'];
                    } else {
                        //если заявка в стадии черновик
                        if ($applications->getNeedCancelByRespId() > 0) {
                            $response = $applications->getNeedCancelByResp()->cancelByResp($data['text']);
                            $message = $response['message'];
                            if ($response['buttons'])
                                $buttons = $response['buttons'];
                            //заполнение данных в заявке
                        } elseif ( Common::DuringAppByResponsible() > 0 ) {
                            $response = $applications->setFieldToInProcess(Common::DuringAppByResponsible(), $data['text']);
                            $message = $response['message'];
                            if ($response['buttons'])
                                $buttons = $response['buttons'];
                        }
                    }
            }
        }
        return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
    }
}