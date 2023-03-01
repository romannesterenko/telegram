<?php
namespace Models;
use Api\Telegram;
use danog\MadelineProto\Exception;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\ElementModel as Model;
use Processing\Manager\Markup as ManagerMarkup;
use Processing\Responsible\Markup as RespMarkup;
use Processing\CashRoomEmployee\Markup as CREMarkup;
use Processing\Collector\Markup as CollectorMarkup;

class Applications extends Model {
    const IBLOCK_ID = 4;

    public function getDrartedByManager($manager_id)
    {
        return (int)$this->where('PROPERTY_CREATED_MANAGER', $manager_id)->where('PROPERTY_STATUS', 3)->first()->getField('ID');
    }

    public function getByManager($manager_id){
        return $this->where('PROPERTY_CREATED_MANAGER', $manager_id)
            ->where('PROPERTY_STATUS', [3, 4, 15, 16, 17, 25])
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
        self::create($fields);
    }

    public function removeDrafted($manager_id)
    {
        $id = $this->getDrartedByManager($manager_id);
        self::delete($id);
    }

    public function setFieldToDraft($manager_id, $text)
    {
        $return_array = [];
        $draft = $this->getDrarted($manager_id);
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
                $return_array = ManagerMarkup::getAgentPhoneMarkup($this->prepareAppDataMessage($draft->getField('ID')));
                break;
            case 3:
                if(!\Helpers\StringHelper::checkPhone($text)){
                    $return_array = ManagerMarkup::getAgentPhoneMarkup($this->prepareAppDataMessage($draft->getField('ID')), "Некорректно введен номер телефона, проверьте вводимые Вами данные!\n");
                }else {
                    $draft->setField('CONTACT_PHONE', $text);
                    $draft->setField('DRAFT_STEP', 4);
                    $return_array = ManagerMarkup::getComentMarkup($this->prepareAppDataMessage($draft->getField('ID')), $draft->getField('ID'));
                }
                break;
            case 4:
                $draft->setField('MANAGER_COMENT', $text);
                $draft->setField('DRAFT_STEP', 5);
                $draft->setField('STATUS', 4);
                if($draft->isPayment()) {
                    Telegram::sendMessageToResp($draft->prepareAppDataMessage($draft->getField('ID')), $draft->getField('ID'));
                }else{
                    Telegram::sendMessageToCollResp($draft->prepareAppDataMessage($draft->getField('ID')), $draft->getField('ID'));
                }
                $return_array = ManagerMarkup::getCompletedAppMarkup($this->prepareAppDataMessage($draft->getField('ID')));
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
        $prev_status = $this->getStatus();
        $needCancel->setField('STATUS', 6);
        $return_array['message'] = "Заявка №".$needCancel->getField('ID')." успешно отклонена";

        //Формируем сообщение ответственному
        $resp_text = "Заявка №".$needCancel->getField('ID')." была отклонена менеджером.\nПричина отмены - ".$text;
        if($prev_status!=3) {
            if($needCancel->isPayment())
                Telegram::sendMessageToResp($resp_text);
            else
                Telegram::sendMessageToCollResp($resp_text);
        }
        return $return_array;
    }

    /*продолжение заполнения заявки*/
    public function restoreProcessDraft($manager_id)
    {
        $return_array = [];
        $draft = $this->getDrarted($manager_id);
        //получаем шаг, на котором закончено заполнение заявки, и подставляем нужную разметку
        switch ($draft->getField('DRAFT_STEP')){
            case 0:
                $return_array = ManagerMarkup::getAgentNameMarkup();
                break;
            case 1:
                $return_array = ManagerMarkup::getOperationTypeMarkup('', $draft->getField('ID'));
                break;
            case 2:
                $return_array = ManagerMarkup::getAgentSecondNameMarkup($this->prepareAppDataMessage($draft->getField('ID')));
                break;
            case 3:
                $return_array = ManagerMarkup::getAgentPhoneMarkup($this->prepareAppDataMessage($draft->getField('ID')));
                break;
            case 4:
                $return_array = ManagerMarkup::getComentMarkup($this->prepareAppDataMessage($draft->getField('ID')), $draft->getField('ID'));
                break;
        }
        return $return_array;
    }

    /*Отрисовка полей заявки*/
    public function prepareAppDataMessage($id): string
    {
        $app = $this->find($id);
        $application = $app->getArray();
        $message = "<b>Информация по заявке №".$app->getId()."</b> \n";
        $message.= "Статус заявки - <b>".$app->getField('STATUS')."</b> \n";
        if($app->getField('AGENT_OFF_NAME'))
            $message.= "Имя контрагента в учете - <b>".$app->getField('AGENT_OFF_NAME')."</b> \n";
        if($application['PROPERTY_OPERATION_TYPE_VALUE'])
            $message.= "Тип операции - <b>".$application['PROPERTY_OPERATION_TYPE_VALUE']."</b> \n";
        if($application['PROPERTY_AGENT_NAME_VALUE'])
            $message.= "Имя с которым обращаться к контрагенту - <b>".$application['PROPERTY_AGENT_NAME_VALUE']."</b> \n";
        if($application['PROPERTY_CONTACT_PHONE_VALUE'])
            $message.= "Номер телефона контрагента - <b>".$application['PROPERTY_CONTACT_PHONE_VALUE']."</b> \n";
        if($application['PROPERTY_MANAGER_COMENT_VALUE'])
            $message.= "Комментарий менеджера - <b>".$application['PROPERTY_MANAGER_COMENT_VALUE']."</b> \n";
        if($application['PROPERTY_SUMM_VALUE'])
            $message.= "Сумма сделки - <b>".number_format($application['PROPERTY_SUMM_VALUE'], 0, '.', ' ')."</b> \n";
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
        return $this->where('PROPERTY_STATUS', [4, 15])->where('PROPERTY_OPERATION_TYPE', 8)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])->get()->getArray();
    }

    public function getAppsForCollResp()
    {
        return $this->where('PROPERTY_STATUS', [4, 15])->where('PROPERTY_OPERATION_TYPE', 7)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])->get()->getArray();
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

        //Формируем сообщение менеджеру
        $manager_text['message'] = "Заявка №".$this->getField('ID')." была отклонена ответственным.\nПричина отмены - ".$text;
        Telegram::sendMessageToManager($manager_text, $this->getField('ID'));
        return $return_array;
    }

    public function getInProcessByResp()
    {
        return (int)$this->where('PROPERTY_STATUS', 15)->where('PROPERTY_OPERATION_TYPE', 8)->where('!RESP_STEP', false)->first()->getField('ID');
    }

    public function getInProcessByCollResp()
    {
        return (int)$this->where('PROPERTY_STATUS', 15)->where('PROPERTY_OPERATION_TYPE', 7)->where('!RESP_STEP', false)->first()->getField('ID');
    }

    public function setFieldToInProcess($app_id, $text){
        $app = $this->find($app_id);
        $return_array = [];
        switch ($app->getField('RESP_STEP')){
            case 0:
                if (!is_numeric($text)) {
                    $return_array = RespMarkup::getRespAddSumMarkup("Сумма должна быть числовым значением\n");
                } else {
                    $app->setField('SUMM', $text);
                    $app->setField('RESP_STEP', 1);
                }
                break;
            case 1:
                if($app->isPayment()) {
                    if ($text != '') {
                        //$return_array['message'] = "\n\n".$error."Шаг №1. \nВведите <b>сумму сделки</b>"; = Telegram::getOperationTypeMarkup($this->prepareAppDataMessage($draft->getField('ID')), $draft->getField('ID'), "Неверные данные. Выберите одно из предложенных значений!\n");
                    }
                } else {
                    $app->setField('TIME', $text);
                    $app->setField('RESP_STEP', 2);
                }
                break;
            case 2:
                if($app->isPayment()) {

                } else {
                    $app->setField('ADDRESS', $text);
                    $app->setField('RESP_STEP', 3);
                }
                break;
            case 3:
                if($app->isPayment()) {
                    $app->setField('RESP_COMENT', $text);
                    $app->setField('RESP_STEP', 4);
                    $app->setCompleteFromResp();
                } else {

                }
                break;
            case 4:
                if($app->isPayment()) {
                    if ($text != '') {
                        //$return_array['message'] = "\n\n".$error."Шаг №1. \nВведите <b>сумму сделки</b>"; = Telegram::getOperationTypeMarkup($this->prepareAppDataMessage($draft->getField('ID')), $draft->getField('ID'), "Неверные данные. Выберите одно из предложенных значений!\n");
                    }
                } else {
                }
                break;
            case 5:
                if($app->isPayment()) {

                } else {
                    $app->setField('RESP_COMENT', $text);
                    $app->setField('RESP_STEP', 5);
                    $app->setCompleteFromResp();
                }
                break;
        }
        if(!ArrayHelper::checkFullArray($return_array))
            $return_array = RespMarkup::getMarkupByResp($app_id);
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
        return $this->where('PROPERTY_STATUS', [23, 20, 26])->where('PROPERTY_CASH_ROOM', $cash_room_id)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE', 'PROPERTY_CREW'])->get()->getArray();
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
    }

    public function getAppsInDeliveryByCrew($crew_id)
    {
        return $this->where('PROPERTY_STATUS', 20)->where('PROPERTY_CREW', $crew_id)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])->get()->getArray();
    }

    public function setComplete()
    {
        $this->order()->setComplete();
        $this->setStatus(27);
    }

    public function isComplete(): bool
    {
        return $this->getField('PROPERTY_STATUS_ENUM_ID')==21;
    }

    public function setFailed()
    {
        $this->setStatus(22);
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
        $this->getField('ADDRESS');
    }

    public function getTime()
    {
        $this->getField('TIME');
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
        return $this->where('PROPERTY_STATUS', 23)->where('PROPERTY_CREW', $crew_id)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])->get()->getArray();
    }

    public function getNewAppsByCrew($crew_id)
    {
        return $this->where('PROPERTY_STATUS', 25)->where('PROPERTY_CREW', $crew_id)->select(['NAME', 'PROPERTY_STATUS', 'PROPERTY_TYPE', 'CREATED_DATE'])->get()->getArray();
    }

    public function setOrder($order_id)
    {
        $this->setField('ORDER', $order_id);
    }

    public function order(): Order
    {
        $orders = new Order();
        return $orders->find((int)$this->getField('ORDER'));
    }
}
