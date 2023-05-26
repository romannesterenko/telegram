<?php
namespace Models;
use Api\Mattermost;
use Api\Telegram;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Helpers\StringHelper;
use Models\ElementModel as Model;
class CashRoomDay extends Model {
    const IBLOCK_ID = 6;

    public function getOpeningStarted($cash_room_id): CashRoomDay
    {
        return $this->where('PROPERTY_STATUS', 30)->where('PROPERTY_CASH_ROOM', $cash_room_id)->first();
    }

    public function getOpeningStartedDays(): CashRoomDay
    {
        return $this->where('PROPERTY_STATUS', 30)->first();
    }

    public function isExistsOpeningStarted($cash_room_id): bool
    {
        return $this->getOpeningStarted($cash_room_id)->getId()>0;
    }

    public function isExistsOpeningStartedDays(): bool
    {
        return $this->getOpeningStartedDays()->getId()>0;
    }

    public function setOpenedTime()
    {
        $this->setField('START_WORK', date('d.m.Y H:i:s'));
    }

    public function setSum($sum)
    {
        $this->setField('START_SUM', $sum);
    }

    public function setEndSum($sum)
    {
        $this->setField('END_SUM', $sum);
    }

    public function setOpen()
    {
        $staff = new Staff();
        if($this->isNeedApprove()) {
            $this->setField('APPROVED_BY', $staff->getSenior()->getId());
            $this->removeNeedApprove();
        }
        $this->setField('APPROVED_DATE', date('d.m.Y H:i:s'));
        $this->setField('SUM_ENTER_STEP', 0);
        $this->setOpenedTime();
        $this->setStatus(32);
        $n_d = new CashRoomDay();
        $n_d_o = $n_d->find($this->getId());
        $currencies = $n_d_o->getField('START_CURRENCIES');
        $sums = $n_d_o->getField('START_SUM');
        $mattermost_message = "Открытие смены - ".$this->cash_room()->getName().". ";
        if (ArrayHelper::checkFullArray($currencies)){
            foreach ($currencies as $key => $currency){
                if($sums[$key]>0) {
                    $curr = new Currency();
                    $c = $curr->find($currency);
                    if($key!=0){
                        $mattermost_message .= ", ";
                    }
                    $mattermost_message .= StringHelper::formatSum($sums[$key])." ".$c->getCode();
                }
            }
        }
        Telegram::sendMessageToResp($mattermost_message);
        Mattermost::send($mattermost_message, $n_d_o->cash_room()->getMatterMostChannel());
    }

    public function setWaitForSenior()
    {
        $this->setStatus(36);
    }

    public function setStatus($int)
    {
        $this->setField('STATUS', $int);
    }

    public function isExistsOpenToday($cash_room_id): bool
    {
        $day = $this->getExistsOpenToday($cash_room_id);
        return $day->getId()>0;
    }

    public function getExistsOpenToday($cash_room_id): CashRoomDay
    {
        return $this->where('PROPERTY_STATUS', [32, 33, 36])
            ->where('PROPERTY_DATE', date('Y-m-d'))
            ->where('PROPERTY_CASH_ROOM', $cash_room_id)
            ->first();
    }

    public function setClosing()
    {
        $this->setStatus(33);
    }

    public function isExistsClosingStarted(): bool
    {
        return $this->getClosingStarted()->getId()>0;
    }

    public function getClosingStarted(): CashRoomDay
    {
        return $this->where('PROPERTY_STATUS', 33)->first();
    }

    public function setClose()
    {
        $this->removeNeedApprove();
        $this->setClosedTime();
        $this->setStatus(34);
        $n_d = new CashRoomDay();
        $n_d_o = $n_d->find($this->getId());
        $currencies = $n_d_o->getField('END_CURRENCIES');
        $sums = $n_d_o->getField('END_SUM');
        $mattermost_message = "Закрытие смены - ".$this->cash_room()->getName().". ";
        if (ArrayHelper::checkFullArray($currencies)){
            foreach ($currencies as $key => $currency){
                if($sums[$key]>0) {
                    $curr = new Currency();
                    $c = $curr->find($currency);
                    if($key!=0){
                        $mattermost_message .= ", ";
                    }
                    $mattermost_message .= StringHelper::formatSum($sums[$key])." ".$c->getCode();
                }
            }
        }
        Telegram::sendMessageToResp($mattermost_message);
        Mattermost::send($mattermost_message, $n_d_o->cash_room()->getMatterMostChannel());
    }

    public function setClosedTime()
    {
        $this->setField('END_WORK', date('d.m.Y H:i:s'));
    }

    public function getLastByCashRoom($id): CashRoomDay
    {
        $this->resetFilter();
        return $this->where('PROPERTY_CASH_ROOM', $id)->where('PROPERTY_STATUS', 34)->first();
    }

    public function getTodayByCashRoom($id): CashRoomDay
    {
        return $this->where('PROPERTY_CASH_ROOM', $id)->where('PROPERTY_DATE', date('Y-m-d'))->where('PROPERTY_STATUS', 32)->first();
    }

    public function cash_room(): CashRoom
    {
        $cash_rooms = new CashRoom();
        return $cash_rooms->find((int)$this->getField('CASH_ROOM'));
    }

    public function setWaitForCloseBySenior()
    {
        $this->setStatus(37);
    }

    public function isExistsWaitingForOpen($cash_room_id): bool
    {
        $day = $this->getExistsWaitingForOpen($cash_room_id);
        return $day->getId()>0;
    }

    public function getExistsWaitingForOpen($cash_room_id): CashRoomDay
    {
        return $this->where('PROPERTY_STATUS', [30, 36])
            ->where('PROPERTY_DATE', date('Y-m-d'))
            ->where('PROPERTY_CASH_ROOM', $cash_room_id)
            ->first();
    }

    public function isExistsWaitingForClose($cash_room_id): bool
    {
        $day = $this->getExistsWaitingForClose($cash_room_id);
        return $day->getId()>0;
    }
    public function getExistsWaitingForClose($cash_room_id): CashRoomDay
    {
        return $this->where('PROPERTY_STATUS', [33, 37])
            ->where('PROPERTY_CASH_ROOM', $cash_room_id)
            ->where('PROPERTY_DATE', date('Y-m-d'))
            ->first();
    }

    public function getStatus()
    {
        return $this->getField('PROPERTY_STATUS_ENUM_ID');
    }

    public function getLastClosedFromCashRoom($cash_room_id): CashRoomDay
    {
        $this->resetFilter();
        return $this->where('PROPERTY_STATUS', 34)
            ->where('PROPERTY_CASH_ROOM', $cash_room_id)
            ->first();
    }

    public function getCashByCashRoom($cash_room_id)
    {
        $today = $this->getExistsOpenToday($cash_room_id);

        if($today->getId()>0&&$today->getField('START_SUM'))
            return (int)$today->getField('START_SUM');
        else{

            $last_day = $this->getLastClosedFromCashRoom($cash_room_id);
            return (int)$last_day->getField('END_SUM');
        }
    }

    public function setNeedApprove()
    {
        $this->setField('NEED_APPROVE', 46);
    }

    public function isNeedApprove():bool {

        return $this->getField('PROPERTY_NEED_APPROVE_ENUM_ID')==46;
    }

    public function removeNeedApprove()
    {
        $this->setField('NEED_APPROVE', false);
    }

    public function getWaitingForOpenToday(): array
    {
        return $this
            ->where('PROPERTY_DATE', date('Y-m-d'))
            ->where('PROPERTY_NEED_APPROVE',46)
            ->where('PROPERTY_STATUS', 36)
            ->buildQuery()
            ->getArray();
    }

    public function getWaitingForCloseToday(): array
    {
        return $this
            ->where('PROPERTY_DATE', date('Y-m-d'))
            ->where('PROPERTY_NEED_APPROVE',46)
            ->where('PROPERTY_STATUS', 37)
            ->buildQuery()
            ->getArray();
    }

    public function setCountAttempts($int)
    {
        $this->setField('SUM_ENTER_ATTEMPTS', $int);
    }

    public function getCountAttempts()
    {
        return $this->getField('SUM_ENTER_ATTEMPTS');
    }

    public function resetCountAttempts()
    {
        $this->setField('SUM_ENTER_ATTEMPTS', 0);
    }

    public function startNewDay($ID)
    {
        $cash_rooms = new CashRoom();
        $cash_room_days = new CashRoomDay();
        $cash_room = $cash_rooms->find($ID);
        $fields['NAME'] = $cash_room->getName() . ". " . date('d.m.Y');
        $properties['STATUS'] = 30;
        $properties['CASH_ROOM'] = $ID;
        $properties['SUM_ENTER_STEP'] = 0;
        $properties['DAY'] = date('d.m.Y');
        $fields['PROPERTY_VALUES'] = $properties;
        return $cash_room_days->create($fields);
    }

    public function setStartSum()
    {

    }

    public function setStartSumMultiple(int $param)
    {
        $old_values = $this->getField('START_SUM');
        $new_values = [];
        if (ArrayHelper::checkFullArray($old_values)){
            foreach ($old_values as $old_value)
                if($old_value>0)
                    $new_values[] = ["VALUE" => $old_value, "DESCRIPTION"=>""];
        }
        $new_values[] = ["VALUE" => $param, "DESCRIPTION"=>""];
        $this->setField('START_SUM', $new_values);
    }

    public function setEstimatedStartSumMultiple(int $param)
    {
        $old_values = $this->getField('ST_SUM');
        $new_values = [];
        if (ArrayHelper::checkFullArray($old_values)){
            foreach ($old_values as $old_value)
                if($old_value>0)
                    $new_values[] = ["VALUE" => $old_value, "DESCRIPTION"=>""];
        }
        $new_values[] = ["VALUE" => $param, "DESCRIPTION"=>""];
        $this->setField('ST_SUM', $new_values);
    }



    public function setEndSumMultiple($param)
    {
        $old_values = $this->getField('END_SUM');
        $new_values = [];
        if (ArrayHelper::checkFullArray($old_values)){
            foreach ($old_values as $old_value)
                if($old_value>0)
                    $new_values[] = ["VALUE" => $old_value, "DESCRIPTION"=>""];
        }
        $new_values[] = ["VALUE" => $param, "DESCRIPTION"=>""];
        $this->setField('END_SUM', $new_values);
    }



    public function setEstimatedEndSumMultiple($param)
    {
        $old_values = $this->getField('EN_SUM');
        $new_values = [];
        if (ArrayHelper::checkFullArray($old_values)){
            foreach ($old_values as $old_value)
                if($old_value>0)
                    $new_values[] = ["VALUE" => $old_value, "DESCRIPTION"=>""];
        }
        $new_values[] = ["VALUE" => $param, "DESCRIPTION"=>""];
        $this->setField('EN_SUM', $new_values);
    }

    public function setStartCurrencyMultiple(int $param)
    {
        $old_values = $this->getField('START_CURRENCIES');
        $new_values = [];
        if (ArrayHelper::checkFullArray($old_values)){
            foreach ($old_values as $old_value)
                if($old_value>0)
                    $new_values[] = ["VALUE" => $old_value, "DESCRIPTION"=>""];
        }
        $new_values[] = ["VALUE" => $param, "DESCRIPTION"=>""];
        $this->setField('START_CURRENCIES', $new_values);
    }

    public function setEndCurrencyMultiple(int $param)
    {
        $old_values = $this->getField('END_CURRENCIES');
        $new_values = [];
        if (ArrayHelper::checkFullArray($old_values)){
            foreach ($old_values as $old_value)
                if($old_value>0)
                    $new_values[] = ["VALUE" => $old_value, "DESCRIPTION"=>""];
        }
        $new_values[] = ["VALUE" => $param, "DESCRIPTION"=>""];
        $this->setField('END_CURRENCIES', $new_values);
    }

    public function isExistsClosedDays():bool
    {
        $cash_rooms = new CashRoom();
        $cash_rooms_array = $cash_rooms->where('ACTIVE', 'Y')->buildQuery()->getArray();
        foreach ($cash_rooms_array as $cash_room){
            $open_cash_room_days = new CashRoomDay();
            $open_crd = $open_cash_room_days
                ->where('PROPERTY_DATE', date('Y-m-d'))
                ->where('END_WORK', false)
                ->where('PROPERTY_CASH_ROOM', $cash_room['ID'])
                ->where('PROPERTY_STATUS', [32, 36])
                ->first();
            if( $open_crd->getId() > 0 ) {

            } else {
                return true;
            }
        }
        return false;
    }

    public function checkSum($cash_room_id, $step, $text)
    {
        $crd = $this->getLastByCashRoom($cash_room_id);
        if($crd->getId()>0){
            $sums_array = $crd->getField('END_SUM');
            return $sums_array[$step]==$text;
        }
        return true;
    }

    public function getOpenToday()
    {
        return $this
            ->where('PROPERTY_DATE', date('Y-m-d'))
            ->where('END_WORK', false)
            ->where('PROPERTY_STATUS', [32, 36])
            ->buildQuery()->getArray();
    }

    public function resetEndSums()
    {
        $this->setField('END_SUM', false);
    }

    public function resetEndCurrencies()
    {
        $this->setField('END_CURRENCIES', false);
    }

    public function resetSumStep()
    {
        $this->setField("SUM_ENTER_STEP", 0);
    }
}
