<?php
$_SERVER["DOCUMENT_ROOT"] = "/home/bitrix/www";
const NO_KEEP_STATISTIC = true;
const NO_AGENT_STATISTIC = true;
const NOT_CHECK_PERMISSIONS = true;
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Helpers\ArrayHelper;
use Models\Crew;
$applications = new \Models\Applications();
$minutes = \Settings\Common::getTimeForCrewWait();
if ($minutes>0){
    $need_timestamp = time()-($minutes*60);
    $apps = $applications
        ->where('PROPERTY_STATUS', 25)
        ->where('DATE_MODIFY_TO', date('d.m.Y H:i:s', $need_timestamp))
        ->get()
        ->getArray();
    if(ArrayHelper::checkFullArray($apps)){
        foreach ($apps as $application){
            if($application['ID']>0){
                $app = $applications->find($application['ID']);
                $text = "Заявка №<b>".$app->getId()."</b>. Прошло ".$minutes." мин. и экипаж <b>".$app->crew()->getName()."</b> не подтвердил заявку. Выберите экипаж заново";
                $crews = new Crew();
                $list = $crews->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();

                if (ArrayHelper::checkFullArray($list)) {

                    foreach ($list as $crew) {
                        $crew_list[] = [
                            'text' => $crew['NAME'],
                            "callback_data" => "setCrewToApp_".$app->getId().'_'.$crew['ID']

                        ];
                    }
                }

                $buttons = json_encode([
                    'resize_keyboard' => true,
                    'inline_keyboard' => [$crew_list]
                ]);
                if($app->isPayment())
                    \Api\Telegram::sendMessageToResp($text, 0, $buttons);
                else
                    \Api\Telegram::sendMessageToCollResp($text, 0, $buttons);
            }
        }
    }
}