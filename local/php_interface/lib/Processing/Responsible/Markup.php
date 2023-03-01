<?php
namespace Processing\Responsible;
use Helpers\ArrayHelper;
use Models\Applications;
use Models\CashRoom;
use Models\Crew;
use Settings\Common;
use \Processing\CashRoomEmployee\Markup as CREMarkup;

class Markup
{
    public static function getMarkupByResp($app_id): array
    {
        $applications = new Applications();
        $app = $applications->find($app_id)->get();
        $markup = [];
        switch ((int)$app->getField('RESP_STEP')){
            case 0:
                $markup = self::getRespAddSumMarkup();
                break;
            case 1:
                if($app->isPayment()){
                    $markup = self::getRespCashRoomListMarkupInProcess($app->prepareAppDataMessage($app_id), $app_id);
                }else{
                    $markup = self::getRespAddTimeMarkup('');

                }
                break;
            case 2:
                if($app->isPayment()){
                    $markup = self::getRespCrewListMarkup('', $app_id);
                }else{
                    $markup = self::getRespAddAddressMarkup('');

                }
                break;
            case 3:
                if($app->isPayment()){
                    $markup = self::getRespAddComentMarkup('', $app_id);
                }else{
                    $markup = self::getRespCashRoomListMarkupInProcess('', $app_id);

                }
                break;
            case 4:
                if($app->isPayment()){
                    $markup = self::getRespCompleteAppMarkup('');
                }else{
                    $markup = self::getRespCrewListMarkup('', $app_id);
                }
                break;
            case 5:
                if($app->isPayment()){

                }else{
                    $markup = self::getRespAddComentMarkup('', $app_id);
                }
                break;
            case 6:
                if($app->isPayment()){

                }else{
                    $markup = self::getRespCompleteAppMarkup('');
                }
                break;
        }
        return $markup;
    }
    public static function getMessagetoRespNewAppMarkup($text, $app_id, $hello_message=true): array
    {
        $response['message'] = "Сформирована новая заявка\n\n";
        if($hello_message)
            $response['message'].= $text;
        else
            $response['message'] = $text;

        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => Common::getButtonText('resp_allow_app'),
                        'callback_data' => 'allowAppByResp_'.$app_id
                    ],
                    [
                        'text' => Common::getButtonText('resp_denie_app'),
                        'callback_data' => 'RespCancelApp_'.$app_id
                    ],
                ]
            ]
        ]);
        return $response;
    }
    //Выдача. Шаг №1. Сумма сделки
    public static function getRespAddSumMarkup($error=''): array
    {
        $response['message'] = $error."Шаг №1. \nВведите <b>сумму сделки</b>";
        return $response;
    }



    public static function getRespAddTimeMarkup($text, $error=''): array
    {
        $response['message'] = $text;
        $response['message'].= "\n\n".$error."Шаг №2. \nВведите <b>время</b>";
        return $response;
    }
    public static function getRespAddAddressMarkup($text, $error=''): array
    {
        $response['message'] = $text;
        $response['message'].= "\n\n".$error."Шаг №3. \nВведите <b>адрес</b>";
        return $response;
    }
    public static function getRespAddComentMarkup($text, $app_id): array
    {
        $applications = new Applications();
        $app = $applications->find($app_id);
        $step = $app->isPayment()?4:6;
        $response['message'] = $text;
        $response['message'].= "Шаг №$step.\nВведите <b>Комментарий к заявке</b>  (Шаг можно пропустить)";
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Пропустить шаг',
                        "callback_data" => "NotSetRespComment_".$app_id
                    ],
                ]
            ]
        ]);
        return $response;
    }
    public static function getRespCompleteAppMarkup($text): array
    {
        $response['message']= "Заявка оформлена\n\n";
        $response['message'].= $text;
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [
                    [
                        'text' => Common::getButtonText('resp_apps_list_new')
                    ],
                    [
                        'text' => Common::getButtonText('resp_cash_room_list')
                    ]
                ]
            ]
        ]);
        return $response;
    }
    public static function getRespCashRoomListMarkup(): array
    {
        $response['message'] = "";
        $cash_rooms = new CashRoom();
        $list = $cash_rooms->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();
        if (ArrayHelper::checkFullArray($list)) {
            foreach ($list as $cash_room) {
                $response['message'] .= CREMarkup::getCashRoomInfoMarkup($cash_room['ID']);
            }
        } else {
            $response['message'] = 'Список касс пуст';
        }
        return $response;
    }
    public static function getRespCashRoomListMarkupInProcess($text, $id): array
    {
        $response['message'] = $text;
        $response['message'] = '';
        $cash_room_list = [];
        $cash_rooms = new CashRoom();
        $applications = new Applications();
        $app = $applications->find($id);
        $list = $cash_rooms->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();
        if($app->isPayment()){
            $response['message'].= "Шаг №2. \nВыберите <b>Кассу</b>";
        }else{
            $response['message'].= "Шаг №4. \nВыберите <b>Кассу</b>";
        }
        $response['message'].= "\nИнформация по кассам:\n";
        if (ArrayHelper::checkFullArray($list)) {
            foreach ($list as $cash_room) {
                $response['message'] .= CREMarkup::getCashRoomInfoMarkup($cash_room['ID']);
                $cash_room_list[] = [
                    'text' => $cash_room['NAME'],
                    "callback_data" => "setCashRoomToApp_".$app->getId().'_'.$cash_room['ID']
                ];
            }
        }
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [$cash_room_list]
        ]);
        return $response;
    }
    private static function getRespCrewListMarkup($text, $id): array
    {
        $applications = new Applications();
        $app = $applications->find($id);
        $crew_list = [];
        $response['message'] = $text;
        if($app->isPayment())
            $response['message'].= "\n\nШаг №3. \nВыберите <b>экипаж</b>";
        else
            $response['message'].= "\n\nШаг №5. \nВыберите <b>экипаж</b>";
        $crews = new Crew();
        $list = $crews->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();

        if (ArrayHelper::checkFullArray($list)) {

            foreach ($list as $crew) {
                $crew_list[] = [

                    'text' => $crew['NAME'],
                    "callback_data" => "setCrewToApp_".$id.'_'.$crew['ID']

                ];
            }
        }
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [$crew_list]
        ]);
        return $response;
    }
}