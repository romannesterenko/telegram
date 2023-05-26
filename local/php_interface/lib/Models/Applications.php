<?php
namespace Models;
use Api\Sender;
use Api\Telegram;
use Bitrix\Main\UI\Uploader\Log;
use danog\MadelineProto\Exception;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Helpers\StringHelper;
use Models\ElementModel as Model;
use Processing\Manager\Markup as ManagerMarkup;
use Processing\Responsible\Markup as RespMarkup;
use Processing\CollectorsResponsible\Markup as CollRespMarkup;
use Processing\CashRoomEmployee\Markup as CREMarkup;
use Processing\Collector\Markup as CollectorMarkup;
use Settings\Common;

class Applications extends Model {
    const IBLOCK_ID = 4;

    public function getDrartedByManager($manager_id)
    {
        return (int)$this->where('PROPERTY_CREATED_MANAGER', $manager_id)->where('PROPERTY_STATUS', 3)->first()->getField('ID');
    }

    public function getDrartedById($app_id)
    {
        return (int)$this->where('ID', $app_id)->where('PROPERTY_STATUS', 3)->first()->getField('ID');
    }

    public function getByManager($manager_id){
        return $this->where('PROPERTY_CREATED_MANAGER', $manager_id)
            ->where('PROPERTY_STATUS', [3, 4, 15, 16, 17, 20, 23, 25, 44, 45, 48, 49])
            ->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])
            ->get()
            ->getArray();
    }

    public function getDrarted($manager_id)
    {
        return $this->where('PROPERTY_CREATED_MANAGER', $manager_id)->where('PROPERTY_STATUS', 3)->first();
    }

    public function getNeedCancel($manager_id)
    {
        return $this->where('PROPERTY_CREATED_MANAGER', $manager_id)->where('PROPERTY_STATUS', 12)->first();
    }

    public function manager()
    {
        $staff = new Staff();
        return $staff->find($this->getField('CREATED_MANAGER'));
    }

    public function createNewDraft($manager_id)
    {
        $tmstmp = time();
        $date = date('d.m.Y H:i:s', $tmstmp);
        $fields['NAME'] = 'Менеджер с id '.$manager_id.' - '.$date;
        $properties['STATUS'] = 3;
        $properties['CREATED_MANAGER'] = $manager_id;
        $properties['MESSENGER'] = 9;
        $properties['DRAFT_STEP'] = 0;
        $fields['PROPERTY_VALUES'] = $properties;
        return self::create($fields);
    }

    public function removeDrafted($manager_id)
    {
        $id = $this->getDrartedByManager($manager_id);
        self::delete($id);
    }

    public function setFieldToDraft($app_id, $text)
    {
        $return_array = [];
        $draft = $this->find($app_id);
        switch ($draft->getField('DRAFT_STEP')){
            case 0:
                $draft->setField('AGENT_OFF_NAME', $text);
                $draft->setField('DRAFT_STEP', 1);

                $return_array = ManagerMarkup::getOperationTypeMarkup('', $draft->getField('ID'));
                break;
            case 1:
                if($text!=''){
                    $return_array = ManagerMarkup::getOperationTypeMarkup('', $draft->getField('ID'), "Выберите одно из предложенных значений!\n");
                }
                break;
            case 2:
                $draft->setField('AGENT_NAME', $text);
                $draft->setField('DRAFT_STEP', 3);
                $return_array = ManagerMarkup::getAgentPhoneMarkup($this->prepareAppDataMessage($draft->getField('ID')), '', $draft->getField('ID'));
                break;
            case 3:
                if(!\Helpers\StringHelper::checkPhone($text)){
                    $return_array = ManagerMarkup::getAgentPhoneMarkup($this->prepareAppDataMessage($draft->getField('ID')), "Некорректно введен номер телефона, проверьте вводимые Вами данные!\n", $draft->getField('ID'));
                } else {
                    $draft->setField('CONTACT_PHONE', $text);
                    $draft->setField('DRAFT_STEP', 4);
                    $list_cash_rooms = $draft->manager()->getManagerCashRooms();
                    if(count($list_cash_rooms)==1){
                        $draft->setField('CASH_ROOM', (int)current($list_cash_rooms));
                        $draft->setField('DRAFT_STEP', 5);
                        $return_array = ManagerMarkup::getComentMarkup('', $draft->getField('ID'));
                    } else {
                        if ($draft->isPayment()) {
                            $return_array = ManagerMarkup::getRespCashRoomListMarkupInProcess($draft->getField('ID'));
                        } else {
                            $return_array = \Processing\Manager\Markup::getRespCashRoomListMarkupInProcess($draft->getField('ID'));
                        }
                    }
                }
                break;
            case 4:
                if(!$draft->isPayment()){
                    /*$draft->setField('ADDRESS', $text);
                    $draft->setField('DRAFT_STEP', 5);
                    $return_array = \Processing\Manager\Markup::getRespCashRoomListMarkupInProcess('', $draft->getField('ID'));*/
                }
                break;
            case 5:
                $draft->setField('MANAGER_COMENT', $text);
                $draft->setField('DRAFT_STEP', 6);
                $draft->setReadyToWorkStatus();
                $return_array['message'] = "Заявка №".$draft->getId()." создана";
                //Telegram::sendMessageToResp($draft->prepareAppDataMessage($draft->getField('ID'), true), $draft->getField('ID'));
                $app_id = $draft->getId();
                $buttons_array = [];
                if($app_id>0){
                    if((new Applications())->find($app_id)->manager()->getField('TG_LOGIN')==Common::GetAllowCashRoomEmployee()){
                        $buttons_array[] = [
                            'text' => "Инфо по кассам"
                        ];
                    }
                }
                $buttons_array[] = [
                    'text' => "Операции"
                ];
                $buttons_array[] = [
                    'text' => \Settings\Common::getButtonText('manager_app_list')
                ];
                $buttons_array[] = [
                    'text' => \Settings\Common::getButtonText('manager_cancel_new_app'),
                ];

                $return_array['buttons'] = json_encode([
                    'resize_keyboard' => true,
                    'keyboard' => [

                        $buttons_array

                    ]
                ]);
                break;
            case 6:
                if ($draft->isPayment()) {
                    $draft->setField('MANAGER_COMENT', $text);
                    $draft->setField('DRAFT_STEP', 7);
                    $draft->setReadyToWorkStatus();
                    $return_array['message'] = "Заявка №".$draft->getId()." создана";
                    Telegram::sendMessageToResp($draft->prepareAppDataMessage($draft->getField('ID')), $draft->getField('ID'));
                } else {
                    $draft->setField('MANAGER_COMENT', $text);
                    $draft->setField('DRAFT_STEP', 7);
                    $draft->setReadyToWorkStatus();
                    $return_array['message'] = "Заявка №".$draft->getId()." создана";
                    Telegram::sendMessageToResp($draft->prepareAppDataMessage($draft->getField('ID'), true), $draft->getField('ID'));
                }

                break;
        }
        return $return_array;
    }

    //отмена заявки менеджером
    public function setFieldToNeedCancelByManager($manager_id, $text)
    {
        $return_array = [];
        $needCancel = $this->getNeedCancel($manager_id);
        $needCancel->setField('MANAGER_CANCEL_REASON', $text);
        $prev_status = $needCancel->getStatus();
        $needCancel->setField('STATUS', 6);
        $return_array['message'] = "Заявка №".$needCancel->getField('ID')." успешно отклонена";
        $return_array['buttons'] = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [
                    [
                        'text' => \Settings\Common::getButtonText('manager_app_list')
                    ],
                    [
                        'text' => \Settings\Common::getButtonText('manager_new_app')
                    ],
                ]
            ]
        ]);
        //Формируем сообщение ответственному
        $resp_text = "Заявка №".$needCancel->getField('ID')." была отклонена менеджером.\nПричина отмены - ".$text;

        if($prev_status!=3) {
            if($needCancel->isPayment()) {
                Telegram::sendMessageToResp($resp_text);
                \Api\Sender::send($needCancel, $resp_text);
            } else {
                Telegram::sendMessageToCollResp($resp_text);
            }
        }
        return $return_array;
    }

    /*продолжение заполнения заявки*/
    public function restoreProcessDraft($app_id)
    {
        $return_array = [];
        $draft = $this->find($app_id);
        //получаем шаг, на котором закончено заполнение заявки, и подставляем нужную разметку
        switch ($draft->getField('DRAFT_STEP')){
            case 0:
                $return_array = ManagerMarkup::getAgentNameMarkup();
                break;
            case 1:
                $return_array = ManagerMarkup::getOperationTypeMarkup('', $draft->getField('ID'));
                break;
            case 2:
                $return_array = ManagerMarkup::getAgentSecondNameMarkup($this->prepareAppDataMessage($draft->getField('ID')), $draft->getField('ID'));
                break;
            case 3:
                $return_array = ManagerMarkup::getAgentPhoneMarkup($this->prepareAppDataMessage($draft->getField('ID')), '', $draft->getField('ID'));
                break;
            case 5:
                $return_array = ManagerMarkup::getComentMarkup($this->prepareAppDataMessage($draft->getField('ID')), $draft->getField('ID'));
                break;
        }
        return $return_array;
    }

    /*Отрисовка полей заявки*/
    public function prepareAppDataMessage($id, $is_cash_resp=false): string
    {
        $app = $this->find($id);
        $application = $app->getArray();
        $message = "<b>Информация по заявке №".$app->getId()."</b> \n";
        if($application['PROPERTY_OPERATION_TYPE_VALUE'])
            $message.= "Тип операции - <b>".$application['PROPERTY_OPERATION_TYPE_VALUE']."</b> \n";
        if($app->getField('AGENT_OFF_NAME'))
            $message.= "Имя контрагента в учете - <b>".$app->getField('AGENT_OFF_NAME')."</b> \n";
        if(!$is_cash_resp) {
            if ($application['PROPERTY_AGENT_NAME_VALUE'])
                $message .= "Имя с которым обращаться к контрагенту - <b>" . $application['PROPERTY_AGENT_NAME_VALUE'] . "</b> \n";
            if ($application['PROPERTY_CONTACT_PHONE_VALUE'])
                $message .= "Номер телефона контрагента - <b>" . $application['PROPERTY_CONTACT_PHONE_VALUE'] . "</b> \n";
        } else {
            if($app->hasBeforeApps()){
                $message .= "Выдать после - <b>";
                $give_after_array = [];
                foreach ($app->getField('GIVE_AFTER') as $after){
                    $after_app = (new Applications())->find($after);
                    $give_after_array[] = "№".$after_app->getId()." (".$after_app->contragent().")";
                }
                $message.=implode(', ', $give_after_array);
                $message.="</b>\n";
            }
        }
        /*if($application['PROPERTY_SUMM_VALUE'])
            $message.= "Сумма сделки - <b>".number_format($application['PROPERTY_SUMM_VALUE'], 0, '.', ' ')."</b> \n";*/
        if($app->getField('CASH_ROOM')>0) {
            $message .= "Касса - <b>" . $app->cash_room()->getName() . "</b> \n";
        }
        if($application['PROPERTY_TIME_VALUE'])
            $message.= "Время - <b>".$application['PROPERTY_TIME_VALUE']."</b> \n";
        if($application['PROPERTY_ADDRESS_VALUE'])
            $message.= "Адрес - <b>".$application['PROPERTY_ADDRESS_VALUE']."</b> \n";
        if($application['PROPERTY_CREW_VALUE']>0) {
            $message .= "Экипаж - <b>" . $app->crew()->getName() . "</b> \n";
        }
        $message.= "Менеджер - <b>".$app->manager()->getField('NAME')."</b> \n";
        if($application['PROPERTY_MANAGER_COMENT_VALUE'])
            $message.= "Комментарий менеджера - <b>".$application['PROPERTY_MANAGER_COMENT_VALUE']."</b> \n";
        if($app->getField('RESP_COMENT'))
            $message.= "Комментарий ответственного - <b>".$app->getField('RESP_COMENT')."</b> \n";
        return $message;
    }

    public function getNewApps()
    {
        return $this->where('PROPERTY_STATUS', 4)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])->get()->getArray();
    }

    public function getAppsForResp()
    {
        return $this->where('PROPERTY_STATUS', [4, 15, 49, 44])->where('!PROPERTY_DRAFT', 1)->where('PROPERTY_OPERATION_TYPE', 8)->where('!PROPERTY_FOR_COL_RESP', 1)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE', 'PROPERTY_SUMM', 'PROPERTY_AGENT_OFF_NAME'])->get()->getArray();
    }

    public function getAppsForCollResp()
    {
        $list_array = [];
        $give_list = $this->where('PROPERTY_STATUS', [4, 15, 49])->where('PROPERTY_OPERATION_TYPE', 7)->where('PROPERTY_FOR_COL_RESP', 1)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])->get()->getArray();
        $pay_list = $this->where('PROPERTY_STATUS', [4, 15, 49])->where('PROPERTY_OPERATION_TYPE', 8)->where('PROPERTY_FOR_COL_RESP', 1)->where('>=PROPERTY_RESP_STEP', 2)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])->get()->getArray();
        foreach ($give_list as $item)
            $list_array[$item['ID']] = $item;
        foreach ($pay_list as $item)
            $list_array[$item['ID']] = $item;
        return $list_array;
    }

    public function getToWorkAppsForCollResp()
    {
        $list_array = [];
        $give_list = $this->where('PROPERTY_STATUS', 48)->where('PROPERTY_OPERATION_TYPE', 7)->where('PROPERTY_FOR_COL_RESP', 1)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])->get()->getArray();
        $pay_list = $this->where('PROPERTY_STATUS', 48)->where('PROPERTY_OPERATION_TYPE', 8)->where('PROPERTY_FOR_COL_RESP', 1)->where('>=PROPERTY_RESP_STEP', 2)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])->get()->getArray();

        foreach ($give_list as $item)
            $list_array[$item['ID']] = $item;
        foreach ($pay_list as $item)
            $list_array[$item['ID']] = $item;
        return $list_array;
    }

    public function getToWorkAppsForCashResp()
    {
        return $this->where('PROPERTY_STATUS', 48)->where('PROPERTY_OPERATION_TYPE', 8)->where('!PROPERTY_DRAFT', 1)->where('PROPERTY_RESP_STEP', false)->select(["IBLOCK_ID", 'NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE', 'PROPERTY_SUMM', 'PROPERTY_AGENT_OFF_NAME'])->buildQuery()->getArray();
    }

    public function setToManagerCancelComent()
    {
        //запоминаем статус при котором была прожата отмена
        $this->setField('BEFORE_MANAGER_CANCEL_STATUS', $this->getField('PROPERTY_STATUS_ENUM_ID'));
        //меняем статус заявки
        $this->setField('STATUS', 12);
    }

    public function setToRespCancelComent()
    {
        //запоминаем статус при котором была прожата отмена
        $this->setField('BEFORE_RESP_CANCEL_STATUS', $this->getField('PROPERTY_STATUS_ENUM_ID'));
        //меняем статус заявки
        $this->setField('STATUS', 13);
    }

    public function setToCollRespCancelComent()
    {
        //запоминаем статус при котором была прожата отмена
        $this->setField('BEFORE_RESP_CANCEL_STATUS', $this->getField('PROPERTY_STATUS_ENUM_ID'));
        //меняем статус заявки
        $this->setField('STATUS', 40);
    }

    public function cancel()
    {
        $this->setField('STATUS', 6);
    }

    public function getNeedCancelByManager($manager_id)
    {
        return (int)$this->where('PROPERTY_CREATED_MANAGER', $manager_id)->where('PROPERTY_STATUS', 12)->first()->getField('ID');
    }

    public function getNeedCancelByRespId()
    {
        return (int)$this->where('PROPERTY_STATUS', 13)->first()->getField('ID');
    }

    public function getNeedCancelByCollRespId()
    {
        return (int)$this->where('PROPERTY_STATUS', 40)->first()->getField('ID');
    }

    public function getNeedCancelByResp()
    {
        return $this->where('PROPERTY_STATUS', 13)->first();
    }

    public function getNeedCancelByCollResp()
    {
        return $this->where('PROPERTY_STATUS', 40)->first();
    }

    public function cancelByResp($text)
    {
        $this->setField('RESP_CANCEL_REASON', $text);
        $this->setField('STATUS', 6);
        $return_array['message'] = "Заявка №".$this->getField('ID')." успешно отклонена";
        (new Order())->cancelByAppID($this->getField('ID'));
        //Формируем сообщение менеджеру
        $manager_text['message'] = "Заявка №".$this->getField('ID')." была отклонена ответственным.\nПричина отмены - ".$text;
        Telegram::sendMessageToManager($manager_text, $this->getField('ID'));
        \Api\Sender::send($this, $manager_text['message']);
        return $return_array;
    }

    public function getInProcessByResp()
    {
        return (int)$this->where('PROPERTY_STATUS', 15)->where('PROPERTY_OPERATION_TYPE', 8)->where('!RESP_STEP', false)->first()->getField('ID');
    }

    public function getInProcessByCollResp()
    {
        $id = Common::DuringAppByCollResponsible();
        return (int)$this->find($id)->getField('ID');
    }

    public function setFieldToInProcess($app_id, $text){
        $app = $this->find($app_id);
        $return_array = [];
        switch ($app->getField('RESP_STEP')){
            case 0:
                if($app->isPayment()) {
                    $text = trim(str_replace(" ", "", $text));
                    if (!is_numeric($text)) {
                        $return_array = RespMarkup::getRespAddSumMarkup("Сумма должна быть числовым значением\n", $app_id);
                    } else {
                        $app->setField('SUMM', $text);
                        $app->setField('RESP_STEP', 1);
                    }
                } else {
                    $app->setField('TIME', $text);
                    $app->setField('RESP_STEP', 1);
                }
                break;
            case 1:
                if($app->isPayment()) {
                    if($app->hasBeforeApps()){

                    } else {
                        $text = trim(str_replace(" ","",$text));
                        if ($app->getField('SUM_ENTER_STEP')==1) {
                            if (!is_numeric($text)) {
                                $return_array['message'] = "Сумма должна быть числовым значением\nПовторите ввод суммы";
                                $inline_keys[] = [
                                    [
                                        'text' => "Сброс заявки",
                                        "callback_data" => 'ResetRespApp_' . $app->getId()
                                    ]
                                ];
                                $return_array['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $inline_keys
                                ]);
                            } else {
                                $app->setField('SUMM', $text);
                                $app->setSumMultiple($text);
                                $app->setField('SUM_ENTER_STEP', 0);
                                $cash_room_currencies = $app->cash_room()->getCurrencies();
                                $exists_app_currencies = (new Applications())->find($app->getId())->getCurrencies();
                                if(count($cash_room_currencies)==count($exists_app_currencies)){
                                    $return_array['message'] = "Данные записаны. Введенная сумма ".implode(', ', (new Applications())->find($app->getId())->getCash());
                                    $return_array['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => [
                                            [
                                                [
                                                    'text' => 'Изменить сумму',
                                                    "callback_data" => "CorrectPrevSum_" . $app->getId()
                                                ],
                                                [
                                                    'text' => 'Продолжить',
                                                    "callback_data" => "CompleteAddSum_" . $app->getId()
                                                ],
                                                [
                                                    'text' => "Сброс заявки",
                                                    "callback_data" => 'ResetRespApp_' . $app->getId()
                                                ]
                                            ]
                                        ]
                                    ]);
                                } else {
                                    $return_array['message'] = "Данные записаны. Введенная сумма ".implode(', ', (new Applications())->find($app->getId())->getCash())." Есть сумма в другой валюте?";
                                    $return_array['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => [
                                            [
                                                [
                                                    'text' => 'Изменить сумму',
                                                    "callback_data" => "CorrectPrevSum_" . $app->getId()
                                                ],
                                                [
                                                    'text' => 'Продолжить',
                                                    "callback_data" => "CompleteAddSum_" . $app->getId()
                                                ],
                                                [
                                                    'text' => 'Добавить сумму',
                                                    "callback_data" => "AddMoreSum_" . $app->getId()
                                                ],
                                                [
                                                    'text' => "Сброс заявки",
                                                    "callback_data" => 'ResetRespApp_' . $app->getId()
                                                ]
                                            ]
                                        ]
                                    ]);
                                }
                            }
                        }
                    }
                } else {
                    $app->setField('ADDRESS', $text);
                    $app->setField('RESP_STEP', 2);
                }
                break;
            case 2:
                if($app->isPayment()) {
                    if($app->hasBeforeApps()){
                        $text = trim(str_replace(" ","",$text));
                        if ($app->getField('SUM_ENTER_STEP')==1) {
                            if (!is_numeric($text)) {
                                $return_array['message'] = "Сумма должна быть числовым значением\nПовторите ввод суммы";
                            } else {
                                $app->setField('SUMM', $text);
                                $app->setSumMultiple($text);
                                $app->setField('SUM_ENTER_STEP', 0);
                                $cash_room_currencies = $app->cash_room()->getCurrencies();
                                $exists_app_currencies = (new Applications())->find($app->getId())->getCurrencies();
                                if(count($cash_room_currencies)==count($exists_app_currencies)){
                                    $return_array['message'] = "Данные записаны. Введенная сумма ".implode(', ', (new Applications())->find($app->getId())->getCash());
                                    $return_array['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => [
                                            [
                                                [
                                                    'text' => 'Изменить сумму',
                                                    "callback_data" => "CorrectPrevSum_" . $app->getId()
                                                ],
                                                [
                                                    'text' => 'Продолжить',
                                                    "callback_data" => "CompleteAddSum_" . $app->getId()
                                                ],
                                                [
                                                    'text' => "Сброс заявки",
                                                    "callback_data" => 'ResetRespApp_' . $app->getId()
                                                ]
                                            ]
                                        ]
                                    ]);
                                } else {
                                    $return_array['message'] = "Данные записаны. Введенная сумма ".implode(', ', (new Applications())->find($app->getId())->getCash()).". Есть сумма в другой валюте?";
                                    $return_array['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => [
                                            [
                                                [
                                                    'text' => 'Изменить сумму',
                                                    "callback_data" => "CorrectPrevSum_" . $app->getId()
                                                ],
                                                [
                                                    'text' => 'Продолжить',
                                                    "callback_data" => "CompleteAddSum_" . $app->getId()
                                                ],
                                                [
                                                    'text' => 'Добавить сумму',
                                                    "callback_data" => "AddMoreSum_" . $app->getId()
                                                ],
                                                [
                                                    'text' => "Сброс заявки",
                                                    "callback_data" => 'ResetRespApp_' . $app->getId()
                                                ]
                                            ]
                                        ]
                                    ]);
                                }
                            }
                        }
                    }

                } else {
                    $app->setField('ADDRESS', $text);
                    $app->setField('RESP_STEP', 3);
                }
                break;
            case 3:
                if($app->isPayment()) {
                    if($app->hasBeforeApps()) {
                        $app->setField('TIME', $text);
                        $app->setField('RESP_STEP', 4);
                    }else{
                        $app->setField('TIME', $text);
                        $app->setField('RESP_STEP', 4);
                    }
                } else {
                    $app->setStatus(25);
                    $app->setField('RESP_STEP', 4);
                    $return_array['message'] = "Информация по заявке №".$app->getId()." сохранена. Ожидаем подтверждения экипажем.";
                    Common::ResetDuringAppByCollResponsible();
                    $collector_markup = CollectorMarkup::getMarkupByCollector($app->getId(), $app->crew()->getId(), 'new_app');
                    Telegram::sendMessageToCollector($app->crew()->getId(), $collector_markup);

                    $manager_text = "По заявке №".$app->getId()." планируемое время забора от контрагента ".$app->getField('AGENT_OFF_NAME')." - ".$app->getTime();
                    Telegram::sendMessageToManagerByAppID($app->getId(), $manager_text);
                    //Telegram::sendMessageToResp($manager_text);

                    $contact_message = "По заявке №".$app->getId()." планируемое время забора  - ".$app->getTime().". Планируемое место забора - ".$app->getAddress();

                    $return_array['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'keyboard' => [
                            [
                                [
                                    'text' => "Заявки в работу"
                                ],
                                [
                                    'text' => Common::getButtonText('resp_apps_list_new')
                                ]
                            ]
                        ]
                    ]);
                    try {
                        \Api\Sender::send($app, $contact_message);
                    }catch (Exception $exception){

                    }
                }
                break;
            case 4:
                if($app->isPayment()) {
                    if($app->hasBeforeApps()) {
                        $app->setField('ADDRESS', $text);
                        $app->setField('RESP_STEP', 5);
                    }else{
                        $app->setField('ADDRESS', $text);
                        $app->setField('RESP_STEP', 5);
                    }

                } else {

                    $app->setStatus(43);
                    $app->setField('RESP_STEP', 5);
                    $app->setField('COLLECTORS_RESP_COMENT', $text);
                    $return_array['message'] = "Информация по заявке №".$app->getId()." сохранена. Ожидаем установки кассы ответственным за учет.";
                    $cash_resp_markup = CollRespMarkup::getNeedSetCashRoomByAppMarkup($app->getId());
                    Telegram::sendMessageToCashResp($cash_resp_markup);
                }
                break;
            case 5:
                if($app->isPayment()) {

                } else {

                }
                break;
            case 6:
                if($app->isPayment()) {
                    $app->setStatus(25);
                    $app->setField('RESP_STEP', 7);
                    $return_array['message'] = "Информация по заявке №".$app->getId()." сохранена. Ожидаем подтверждения экипажем.";
                    Common::ResetDuringAppByCollResponsible();
                    $collector_markup = CollectorMarkup::getMarkupByCollector($app->getId(), $app->crew()->getId(), 'new_app');
                    Telegram::sendMessageToCollector($app->crew()->getId(), $collector_markup);

                    $manager_text = "По заявке №".$app->getId()." планируемое время забора от контрагента ".$app->getField('AGENT_OFF_NAME')." - ".$app->getTime();
                    Telegram::sendMessageToManagerByAppID($app->getId(), $manager_text);
                    //Telegram::sendMessageToResp($manager_text);

                    $contact_message = "По заявке №".$app->getId()." планируемое время забора  - ".$app->getTime().". Планируемое место забора - ".$app->getAddress();

                    $return_array['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'keyboard' => [
                            [
                                [
                                    'text' => "Заявки в работу"
                                ],
                                [
                                    'text' => Common::getButtonText('resp_apps_list_new')
                                ]
                            ]
                        ]
                    ]);
                    try {
                        \Api\Sender::send($app, $contact_message);
                    }catch (Exception $exception){

                    }
                } else {

                }
                break;
        }
        if(!ArrayHelper::checkFullArray($return_array)) {
            if($app->isPayment()&&$app->getField('RESP_STEP')<2)
                $return_array = RespMarkup::getMarkupByResp($app_id);
            else
                $return_array = CollRespMarkup::getMarkupByResp($app_id);
        }
        return $return_array;
    }

    public function isPayment()
    {
        return $this->getField('PROPERTY_OPERATION_TYPE_ENUM_ID')==8;
    }

    public function isNew()
    {
        return $this->getField('PROPERTY_STATUS_ENUM_ID')==4;
    }

    public function setCompleteFromResp()
    {
        $app = $this->find($this->getId());
        $collector_markup = CollectorMarkup::getMarkupByCollector($app->getId(), $app->crew()->getId(), 'new_app');
        Telegram::sendMessageToCollector($app->crew()->getId(), $collector_markup);
        $this->setField('STATUS', 25, true);
        if($this->getStatus()!=25){
            $order = new Order();
            $order->createFromApp($app);
            $contact_message = "Заявка №".$app->getId()." на сумму ".number_format($app->getSum(), 0, '.', ' ')." подтверждена";
            try {
                \Api\Sender::send($app, $contact_message);
            }catch (Exception $exception){

            }
        }
    }

    public function getAppsForCRE($employee)
    {
        $cash_room_id = $employee->getField('CASH_ROOM');
        return $this->where('PROPERTY_STATUS', [20, 26, 23])->where('PROPERTY_CASH_ROOM', $cash_room_id)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE', 'PROPERTY_CREW'])->get()->getArray();
    }

    public function getRecieveAppsForCRE()
    {
        return $this->where('PROPERTY_STATUS', [20, 26])->where('PROPERTY_OPERATION_TYPE', 7)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE', 'PROPERTY_CREW'])->get()->getArray();
    }

    public function getPaymentsAppsForCRE()
    {
        return $this->where('PROPERTY_STATUS', [23, 52, 54])->where('PROPERTY_OPERATION_TYPE', 8)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE', 'PROPERTY_CREW'])->get()->getArray();
    }

    public function setStatus($int)
    {
        $this->setField('STATUS', $int);
    }

    public function getStatus()
    {
        return $this->getField('PROPERTY_STATUS_ENUM_ID');
    }

    public function getSum()
    {
        return $this->getField('SUMM');
    }

    public function getRealSum()
    {
        return $this->getField('REAL_SUM');
    }

    public function setCrew($crew_id)
    {
        $this->setField('CREW', $crew_id);
        $orders = $this->order();
        if(ArrayHelper::checkFullArray($orders)){
            foreach ($orders as $order){
                $ord_obj = new Order();
                $ord_obj->find($order['ID'])->setField('CREW', $crew_id);
            }
        }
        //$this->order()->setCrew($crew_id);
    }

    public function getAppsInDeliveryByCrew($crew_id)
    {
        return $this->where('PROPERTY_STATUS', [20, 52])->where('PROPERTY_CREW', $crew_id)->where('!PROPERTY_TO_TOMORROW', 1)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_OPERATION_TYPE', 'CREATED_DATE', 'PROPERTY_SUMM', 'PROPERTY_AGENT_NAME'])->get()->getArray();
    }

    public function setComplete()
    {
        $orders = new Order();
        $order_list = $orders->where('PROPERTY_APP', $this->getId())->buildQuery()->getArray();
        if(ArrayHelper::checkFullArray($order_list)){
            foreach($order_list as $order){
                $temp_orders = new Order();
                $temp_orders->find($order['ID'])->setComplete();
            }
        }
        //$this->order()->setComplete();
        $this->setStatus(27);
        if (!$this->isPayment()){
            if($this->isBeforeApp()){
                $main_appl = new Applications();
                $last = new Applications();
                $main_app = $main_appl->where('PROPERTY_GIVE_AFTER', $this->getId())->where('PROPERTY_STATUS', 44)->first();
                $last_apps = $last->whereNot('PROPERTY_STATUS', 27)->where('ID', $main_app->getField('GIVE_AFTER'))->get()->getArray();
                $cash_resp_markup['message'] = "Заявка №".$main_app->getId()." (".$main_app->getField("AGENT_OFF_NAME").")\nВыполнена заявка, указанная как необходимая для выполнения текущей.\n";
                $cash_resp_markup['message'].= "Информация по выполненной заявке:\n";
                $cash_resp_markup['message'].= "Заявка № <b>".$this->getId()."</b>. Контрагент - <b>".$this->getField('AGENT_OFF_NAME')."</b> Сумма - ".implode(', ', $this->getCash())."\n\n";
                if(ArrayHelper::checkFullArray($last_apps)){
                    $cash_resp_markup['message'].= "Список оставшихся заявок для выполнения текущей заявки\n";
                    foreach ($last_apps as $last_app) {
                        $cash_resp_markup['message'] .= "\n===================\n";
                        $cash_resp_markup['message'] .= "Заявка №" . $last_app['ID'] . ". Контрагент - " . $last_app['PROPERTY_AGENT_OFF_NAME_VALUE'];
                    }
                    $cash_resp_markup['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => "Ждать",
                                    'callback_data' => 'waitMore_'.$main_app->getId()
                                ],
                                [
                                    'text' => "Выдать деньги",
                                    'callback_data' => 'GiveMoney_'.$main_app->getId()
                                ],

                            ]
                        ]
                    ]);
                }else{
                    $cash_resp_markup['message'].= "Все связанные заявки выполнены, продолжайте оформление\n";
                    $cash_resp_markup['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => "Выдать деньги",
                                    'callback_data' => 'GiveMoney_'.$main_app->getId()
                                ],
                            ]
                        ]
                    ]);
                }
                Telegram::sendMessageToCashResp($cash_resp_markup);
            }
        }
    }

    public function isComplete(): bool
    {
        return $this->getField('PROPERTY_STATUS_ENUM_ID')==27;
    }

    public function setFailed()
    {
        $this->setStatus(52);


    }

    public function isFailed(): bool
    {
        return $this->getField('PROPERTY_STATUS_ENUM_ID')==22;
    }

    public function setInDeliveryStatus()
    {
        $this->setStatus(20);
    }

    public function isInDelivery(): bool
    {
        return $this->getField('PROPERTY_STATUS_ENUM_ID')==20;
    }
    public function getAddress()
    {
        return $this->getField('ADDRESS');
    }

    public function getTime()
    {
        return $this->getField('TIME');
    }

    public function setProblemStatus()
    {
        $this->setStatus(24);
    }
    public function isProblem(): bool
    {
        return $this->getField('PROPERTY_STATUS_ENUM_ID')==24;
    }

    public function crew(): Crew
    {
        $crew = new Crew();
        return $crew->find((int)$this->getField('CREW'));
    }

    public function getNeedSumEnterApp()
    {
        return $this->where('PROPERTY_STATUS', 26)->first();
    }

    public function processCashRoomOrder()
    {
        $cash_room = $this->cash_room();
        if( $this->isPayment() ){
            $cash_room->setExpense($this);
        } else {
            $cash_room->setArrival($this);
        }
    }

    public function getPhone()
    {
        return $this->getField('CONTACT_PHONE');
    }

    public function cash_room(): CashRoom
    {
        $cash_rooms = new CashRoom();
        return $cash_rooms->find((int)$this->getField('CASH_ROOM'));
    }

    public function getGiveAppsByCrew($crew_id)
    {
        return $this->where('PROPERTY_STATUS', [23, 25])->where('PROPERTY_OPERATION_TYPE', 7)->where('PROPERTY_CREW', $crew_id)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'PROPERTY_SUMM', 'PROPERTY_AGENT_NAME', 'CREATED_DATE'])->get()->getArray();
    }

    public function getNewAppsByCrew($crew_id)
    {
        return $this->where('PROPERTY_STATUS', 25)->where('PROPERTY_CREW', $crew_id)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE', 'PROPERTY_SUMM', 'PROPERTY_AGENT_NAME'])->get()->getArray();
    }

    public function setOrder($order_id)
    {
        $this->setField('ORDER', $order_id);
    }

    public function order()
    {
        $orders = new Order();
        return $orders->where("PROPERTY_APP", $this->getId())->buildQuery()->getArray();
    }

    public function order_object()
    {
        $orders = new Order();
        return $orders->where("PROPERTY_APP", $this->getId())->first();
    }

    public function currency(): Currency
    {
        $currency = new Currency();
        return $currency->find((int)$this->getField('CURRENCY'));
    }

    public function setBeforeApp($app_id)
    {
        $values = [];
        $exists = $this->getField('GIVE_AFTER');
        foreach ($exists as $exist)
            $values[] = ['VALUE' => $exist, 'DESCRIPTION' => ''];
        $values[] = ['VALUE' => $app_id, 'DESCRIPTION' => ''];
        $this->setField('GIVE_AFTER', $values);
    }

    public function getPaymentsAppsByCrew($crew_id)
    {
        return $this->where('PROPERTY_STATUS', [23, 25])->where('PROPERTY_OPERATION_TYPE', 8)->where('PROPERTY_CREW', $crew_id)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE', 'PROPERTY_SUMM', 'PROPERTY_AGENT_NAME'])->get()->getArray();
    }

    private function isBeforeApp(): bool
    {
        $ap = new Applications();
        return $ap->where('PROPERTY_GIVE_AFTER', $this->getId())->where('PROPERTY_STATUS', 44)->first()->getId()>0;
    }

    public function hasBeforeApps(): bool
    {
        return ArrayHelper::checkFullArray($this->getField('GIVE_AFTER'));
    }

    public function setReadyToWorkStatus()
    {
        $this->setField('STATUS', 48, true);
        if ((int)$this->getField('RESP_STEP')==0){
            $this->manager()->resetCreateAppSession();
            if($this->isPayment()) {
                Telegram::sendMessageToResp($this->prepareAppDataMessage($this->getField('ID'), true), $this->getField('ID'));
                $contact_message = "По заявке №".$this->getId()." планируется операция по выдаче.";
            } else {
                $this->setForColResp();
                Telegram::sendMessageToCollResp($this->prepareAppDataMessage($this->getField('ID')), $this->getField('ID'));
                $message_to_resp = "По заявке №".$this->getId()." планируется операция по забору.";
                $message_to_resp.= "\nКонтрагент - ".$this->getField('AGENT_OFF_NAME');
                $message_to_resp.= "\nМенеджер - ".$this->manager()->getName();
                if($this->getField('MANAGER_COMENT'))
                    $message_to_resp.= "\nКомментарий - ".$this->getField('MANAGER_COMENT');
                Telegram::sendMessageToResp($message_to_resp);
                $contact_message = "По заявке №".$this->getId()." планируется операция по забору.";
            }
            try {
                \Api\Sender::send($this, $contact_message);
            } catch (Exception $exception){

            }
        }
    }


    public function isReadyToWork(): bool
    {
        return $this->getStatus()==48;
    }


    public function isInProcess(): bool
    {
        return $this->getStatus()==49;
    }

    public function setRespInProcessStatus()
    {
        $this->setField('STATUS', 4);
    }

    public function setInRefinementStatus()
    {
        $this->setField('STATUS', 49);
    }

    public function isInRefinement(): bool
    {
        return $this->getStatus()==49;
    }

    public function isPayBack(): bool
    {
        return $this->getStatus()==52;
    }

    public function getNeedSumEnterToPayBack(): Applications
    {
        return $this->where('PROPERTY_STATUS', 54)->first();
    }

    public function setReturned()
    {
        $this->setField('STATUS', 53);
    }

    public function getForLink($already_exists)
    {
        return $this
            ->whereNot('ID', $already_exists)
            ->where('PROPERTY_OPERATION_TYPE', 7)
            ->where('!PROPERTY_STATUS', [6, 22, 24, 27])
            ->select(['ID', 'NAME', 'PROPERTY_SUMM', 'PROPERTY_AGENT_OFF_NAME'])
            ->buildQuery()
            ->getArray();
    }

    public function getNeedCommentToReceive(Crew $crew): Applications
    {
        $this->resetFilter();
        return $this->where('PROPERTY_CREW', $crew->getId())->where('PROPERTY_STATUS', 56)->first();
    }

    public function getNeedCommentToGive(Crew $crew): Applications
    {
        $this->resetFilter();
        return $this->where('PROPERTY_CREW', $crew->getId())->where('PROPERTY_STATUS', 55)->first();
    }

    public function updateName()
    {
        $el = new \CIBlockElement;
        $name = "Заявка от ".$this->getField('AGENT_OFF_NAME').". №".$this->getId();
        $el->Update($this->getId(), ['NAME' => $name]);
    }

    public function isAllowToCancelByManager():bool
    {
        $not_allow_statuses = [20];
        return !in_array($this->getStatus(), $not_allow_statuses);
    }

    public function setCurrency($param)
    {
        $old_values = $this->getField('CURRENCY');
        $new_values = [];
        if (ArrayHelper::checkFullArray($old_values)){
            foreach ($old_values as $old_value)
                if($old_value>0)
                    $new_values[] = ["VALUE" => $old_value, "DESCRIPTION"=>""];
        }
        if(!ArrayHelper::checkFullArray($old_values)){
            $new_values[] = ["VALUE" => $param, "DESCRIPTION" => ""];
            $this->setField('CURRENCY', $new_values);
        } elseif (!in_array($param, $old_values)) {
            $new_values[] = ["VALUE" => $param, "DESCRIPTION" => ""];
            $this->setField('CURRENCY', $new_values);
        }
    }

    public function setSumMultiple($param)
    {
        $old_values = $this->getField('SUMM');
        $new_values = [];
        if (ArrayHelper::checkFullArray($old_values)){
            foreach ($old_values as $old_value)
                if($old_value>0)
                    $new_values[] = ["VALUE" => $old_value, "DESCRIPTION"=>""];
        }
        $new_values[] = ["VALUE" => $param, "DESCRIPTION"=>""];
        $this->setField('SUMM', $new_values);
    }

    public function setRealSumMultiple(int $param)
    {
        $old_values = $this->getField('REAL_SUM');
        $new_values = [];
        if (ArrayHelper::checkFullArray($old_values)){
            foreach ($old_values as $old_value)
                if($old_value>0)
                    $new_values[] = ["VALUE" => $old_value, "DESCRIPTION"=>""];
        }
        $new_values[] = ["VALUE" => $param, "DESCRIPTION"=>""];
        $this->setField('REAL_SUM', $new_values);
    }

    public function getCash()
    {
        $cash_apps = new Applications();
        $app = $cash_apps->find($this->getId());
        $values = $app->getField('SUMM');
        if(ArrayHelper::checkFullArray($app->getField('REAL_SUM')))
            $values = $app->getField('REAL_SUM');
        $currencies = $app->getField('CURRENCY');
        $array = [];
        if (ArrayHelper::checkFullArray($values)&&ArrayHelper::checkFullArray($currencies)) {
            foreach ($values as $id => $sum) {
                $currencies_o = new Currency();
                $currency = $currencies_o->find($currencies[$id]);
                $array[] = StringHelper::formatSum($sum)." ".$currency->getField("CODE");
            }
        }
        return $array;
    }

    public function getCurrencies()
    {
        return $this->getField('CURRENCY');
    }

    public function removeLastSum()
    {

        $currencies = $this->getCurrencies();
        $sums = $this->getSum();
        unset($currencies[count($currencies)-1]);
        unset($sums[count($sums)-1]);
        $currencies = count($currencies)>0?$currencies:false;
        $sums = count($sums)>0?$sums:false;
        (new \CIBlockElement())->SetPropertyValuesEx(
            $this->getId(),
            $this->getIblock(),
            array('CURRENCY' => $currencies)
        );
        (new \CIBlockElement())->SetPropertyValuesEx(
            $this->getId(),
            $this->getIblock(),
            array('SUMM' => $sums)
        );
    }

    public function isDraft()
    {
        return $this->getStatus()==3;
    }

    public function setFieldToInDraftByResp($app_id, $text)
    {
        $return_array = [];
        $draft = $this->find($app_id);
        switch ($draft->getField('DRAFT_STEP')){
            case 0:
                $draft->setField('AGENT_OFF_NAME', $text);
                $draft->setField('DRAFT_STEP', 1);
                $return_array = RespMarkup::getCreateAppMarkup($app_id);
                break;
            case 3:
                $draft->setField('MANAGER_COMENT', $text);
                if($draft->isPayment()){
                    $draft->setField('DRAFT_STEP', 7);
                    Common::ResetDuringCreateAppByResponsible();
                    Common::SetDuringAppByResponsible($draft->getId());
                    $draft->setField('RESP_STEP', 0);
                    $draft->setField('STATUS', 15);
                    $return_array = RespMarkup::getMarkupByResp($draft->getId());
                } else {
                    Common::ResetDuringCreateAppByResponsible();
                    $draft->setField('DRAFT_STEP', 7);
                    $draft->setReadyToWorkStatus();
                    $return_array['message'] = "Заявка №" . $draft->getId() . " создана";
                }
                //Telegram::sendMessageToResp($draft->prepareAppDataMessage($draft->getField('ID'), true), $draft->getField('ID'));
                break;

        }
        return $return_array;
    }

    public function setForColResp()
    {
        $this->setField('FOR_COL_RESP', 1);
    }

    public function contragent()
    {
        return $this->getField("AGENT_OFF_NAME")??"";
    }

    public function resetApp()
    {

        $this->resetField('SUMM');
        $this->resetField('CURRENCY');
        $this->resetField('SUM_ENTER_STEP');
        if($this->hasBeforeApps()){
            $this->setField('RESP_STEP', 1);
            $this->setField('STATUS', 44);
        } else {
            $this->resetField('GIVE_AFTER');
            $this->resetField('RESP_STEP');
            $this->setField('STATUS', 48, true);
        }
        Common::ResetDuringAppByResponsible();
    }

    public function getFullName():string
    {
        $contragent = $this->contragent()!=""?" (".$this->contragent().")":"";
        return "№".$this->getId().$contragent;
    }
    public function resetField($string)
    {
        $this->setField($string, false);
    }

    public function resetCollRespApp()
    {
        $this->resetField('ADDRESS');
        $this->resetField('TIME');
        $this->resetField('CREW');
        if($this->isPayment()){
            $this->setField('RESP_STEP', 2);
        } else {
            $this->resetField('RESP_STEP');
        }
        $this->setField('STATUS', 48, true);
        Common::ResetDuringAppByCollResponsible();
    }

    public function getCrewAppsForCollResp()
    {
        return $this->where('PROPERTY_STATUS', [23, 25, 20])->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'PROPERTY_CREW', 'CREATED_DATE', 'PROPERTY_SUMM', 'PROPERTY_AGENT_NAME'])->get()->getArray();
    }
}
