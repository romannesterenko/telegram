<?php
$_SERVER["DOCUMENT_ROOT"] = "/home/bitrix/www";
const NO_KEEP_STATISTIC = true;
const NO_AGENT_STATISTIC = true;
const NOT_CHECK_PERMISSIONS = true;
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Api\Telegram;
use Helpers\ArrayHelper;
use Models\Applications;
use Models\Crew;
use Settings\Common;

$applications = new Applications();
$applications_wr = new Applications();
$minutes = Common::getTimeForCrewWait();
$resp_minutes = Common::getTimeForRespWait();
if ($minutes>0){
    //заводим поле для хранения последней обработки
    if((int)Common::get('last_time_from_crew_remind')==0)
        Common::set('last_time_from_crew_remind', time());
    //получаем интервал для уведомлений из настроек
    $interval_crew_minutes = (int)Common::get('interval_to_remind_for_crew');
    if($interval_crew_minutes > 0) {
        $need_crew_time = (int)Common::get('last_time_from_crew_remind')+($interval_crew_minutes*60);
        //если время сейчас достигло нужного значения, выполняем поиск просроченных заявок
        if( time()>=$need_crew_time ) {
            //обновляем время последней обработки
            Common::set('last_time_from_crew_remind', time());
            $need_timestamp = time() - ($minutes * 60);
            $apps = $applications
                ->where('PROPERTY_STATUS', 25)
                ->where('DATE_MODIFY_TO', date('d.m.Y H:i:s', $need_timestamp))
                ->get()
                ->getArray();
            //если заявки найдены, формируем и отсылаем сообщение
            if (ArrayHelper::checkFullArray($apps)) {
                foreach ($apps as $application) {
                    if ($application['ID'] > 0) {
                        $app = $applications->find($application['ID']);
                        $template = Common::get('text_to_remind_for_crew');
                        $vars = [
                            'APP_ID' => $app->getId(),
                            'MINUTES' => $minutes,
                            'CREW_NAME' => $app->crew()->getName(),
                            'RETURN' => "\n",
                        ];
                        $text = \Helpers\StringHelper::genMessageFromTemplate(Common::get('text_to_remind_for_crew'), $vars);
                        $crews = new Crew();
                        $list = $crews->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();
                        if (ArrayHelper::checkFullArray($list)) {

                            foreach ($list as $crew) {
                                $crew_list[] = [
                                    'text' => $crew['NAME'],
                                    "callback_data" => "setCrewToApp_" . $app->getId() . '_' . $crew['ID']

                                ];
                            }
                        }
                        $buttons = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => [$crew_list]
                        ]);
                        Telegram::sendMessageToCollResp($text, 0, $buttons);
                    }
                }
            }
        }
    }
}
if($resp_minutes>0){
    //заводим поле для хранения последней обработки
    if((int)Common::get('last_time_from_resp_remind')==0)
        Common::set('last_time_from_resp_remind', time());
    //получаем интервал для уведомлений из настроек
    $interval_minutes = (int)Common::get('interval_to_remind_for_responsible');
    if($interval_minutes>0) {
        $need_time = (int)Common::get('last_time_from_resp_remind')+($interval_minutes*60);
        //если время сейчас достигло нужного значения, выполняем поиск просроченных заявок
        if( time()>=$need_time ) {
            //обновляем время последней обработки
            Common::set('last_time_from_resp_remind', time());
            $need_timestamp_resp = time() - ($resp_minutes * 60);
            $apps_wait_resp = $applications_wr
                ->where('PROPERTY_STATUS', [48, 49])
                ->where('PROPERTY_OPERATION_TYPE', 7)
                ->where('DATE_MODIFY_TO', date('d.m.Y H:i:s', $need_timestamp_resp))
                ->get()
                ->getArray();
            //если заявки найдены, формируем и отсылаем сообщение
            if (ArrayHelper::checkFullArray($apps_wait_resp)) {
                $markup['message'] = Common::get('text_to_remind_for_responsible');
                $inline_keyboard = [];
                foreach ($apps_wait_resp as $application_wr) {
                    if ($application_wr['ID'] > 0) {
                        $app_wr = $applications->find($application_wr['ID']);
                        $inline_keyboard[] = [
                            [
                                "text" => '№' . $app_wr->getId() . '. ' . $app_wr->getField('STATUS'),
                                "callback_data" => "showApplicationForResponse_" . $app_wr->getId()
                            ]
                        ];
                    }
                }
                $keyboard = array("inline_keyboard" => $inline_keyboard);
                $markup['buttons'] = json_encode($keyboard);
                Telegram::sendMessageToCollResp($markup['message'], 0, $markup['buttons']);
            }
        }
    }
}