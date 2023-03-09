<?php
namespace Models;
use Helpers\LogHelper;
use Models\ElementModel as Model;
class CashRoomDay extends Model {
    const IBLOCK_ID = 6;

    public function getOpeningStarted($cash_room_id): CashRoomDay
    {
        return $this->where('PROPERTY_STATUS', 30)->where('PROPERTY_CASH_ROOM', $cash_room_id)->first();
    }

    public function isExistsOpeningStarted($cash_room_id): bool
    {
        return $this->getOpeningStarted($cash_room_id)->getId()>0;
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
        $this->setOpenedTime();
        $this->setStatus(32);
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
        return $this->where('PROPERTY_STATUS', [32, 33])
            ->where('PROPERTY_DATE', date('Y-m-d'))
            ->where('PROPERTY_CASH_ROOM', $cash_room_id)
            ->where('>PROPERTY_START_WORK', date('Y-m-d 00:00:01'))
            ->where('<PROPERTY_START_WORK', date('Y-m-d 23:59:59'))
            ->first();
    }

    public function setClosing()
    {
        $this->setStatus(33);
    }

    public function isExistsClosingStarted($cash_room_id): bool
    {
        return $this->getClosingStarted($cash_room_id)->getId()>0;
    }

    public function getClosingStarted($cash_room_id): CashRoomDay
    {
        return $this->where('PROPERTY_STATUS', 33)->where('PROPERTY_CASH_ROOM', $cash_room_id)->first();
    }

    public function setClose()
    {
        //$day = $this->find($day_id);
        $this->removeNeedApprove();
        $this->setClosedTime();
        $this->setStatus(34);
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


}
