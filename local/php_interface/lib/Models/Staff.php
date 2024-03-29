<?php
namespace Models;
use Helpers\LogHelper;
use Models\ElementModel as Model;
use Settings\Common;

class Staff extends Model {

    const IBLOCK_ID = 3;

    public function getByLogin($tg_login)
    {
        return $this->where('PROPERTY_TG_LOGIN', $tg_login)->first();
    }

    public function isManager(): bool
    {
        return $this->getField('PROPERTY_ROLE_ENUM_ID')==1;
    }

    public function isCashRoomSenior(): bool
    {
        return $this->getField('PROPERTY_ROLE_ENUM_ID')==35;
    }

    public function isRespForAccounting(): bool
    {
        return $this->getField('PROPERTY_ROLE_ENUM_ID')==11;
    }

    public function isRespForCollectors(): bool
    {
        return $this->getField('PROPERTY_ROLE_ENUM_ID')==10;
    }

    public function setChatID($chat_id)
    {
        $this->setField('TG_CHAT_ID', $chat_id);
    }

    public function getResp()
    {
        return $this->where('PROPERTY_ROLE', 11)->first();
    }

    public function getCollResp()
    {
        return $this->where('PROPERTY_ROLE', 10)->first();
    }

    public function isCashRoomEmployee()
    {
        return $this->getField('PROPERTY_ROLE_ENUM_ID')==2;
    }

    public function getChatId()
    {
        return $this->getField('TG_CHAT_ID');
    }

    public function getManager(): Staff
    {
        return $this->where('PROPERTY_ROLE', 1)->first();
    }

    public function isCollector(): bool
    {
        return $this->getField('PROPERTY_ROLE_ENUM_ID')==19;
    }

    public function crew(): Crew
    {
        $crew = new Crew();
        return $crew->find($this->getField('CREW'));
    }

    public function startWorkDay($cash_room_id=0)
    {
        $work_days = new CashRoomDay();
        if(!$work_days->isExistsOpeningStarted($this->cash_room()->getId())) {
            $fields['NAME'] = $this->cash_room()->getName() . ". " . date('d.m.Y');
            $properties['STATUS'] = 30;
            $properties['CASH_ROOM'] = $cash_room_id>0?$cash_room_id:$this->cash_room()->getId();
            $properties['CASH_ROOM_EMPLOYEE'] = $this->getId();
            $properties['DAY'] = date('d.m.Y');
            $fields['PROPERTY_VALUES'] = $properties;
            return $work_days->create($fields);
        }
    }

    public function manager_cash_rooms()
    {
        $cash_room = new CashRoom();
        return $cash_room->filter(['ID' => $this->getField('MANAGER_CASH_ROOMS')])->buildQuery()->getArray();
    }

    public function cash_room(): CashRoom
    {
        $cash_room = new CashRoom();
        return $cash_room->find((int)$this->getField('CASH_ROOM'));
    }

    public function getSenior(): Staff
    {
        return $this->where('PROPERTY_ROLE', 35)->first();
    }

    public function getCashResp(): Staff
    {
        return $this->where('PROPERTY_ROLE', 11)->first();
    }

    public function getCashRoomEmployee(): Staff
    {
        return $this->where('PROPERTY_ROLE', 2)->first();
    }

    public function getByChatId($chat_id)
    {
        return $this->where('PROPERTY_TG_CHAT_ID', $chat_id)->first();
    }

    public function getManagerCashRooms()
    {
        return $this->getField('MANAGER_CASH_ROOMS');
    }

    public function getCreatingApp():int {
        return Common::getCreateAppManagerSession($this->getId());
    }

    public function getCreatingOperation():int {
        return Common::getCreatingOperationProcess($this->getId());
    }

    public function startCreateAppSession($app_id)
    {
        Common::setCreateAppManagerSession($this->getId(), $app_id);
    }

    public function hasSessions():bool
    {
        return
            $this->getCreatingApp()>0||
            $this->getCreatingOperation()>0||
            $this->isStartedSearchSession();
    }

    public function resetCreateAppSession()
    {
        Common::setCreateAppManagerSession($this->getId(), 0);
    }

    public function startCreateOperationSession($app_id)
    {
        Common::setCreatingOperationProcess($this->getId(), $app_id);
    }

    public function resetCreateOperationSession($app_id)
    {
        Common::setCreatingOperationProcess($this->getId(), 0);
    }

    public function startSearchSession()
    {
        Common::setSearchManagerSession($this->getId());
    }

    public function closeSearchSession()
    {
        Common::resetSearchManagerSession($this->getId());
    }

    public function isStartedSearchSession():bool
    {
        return Common::isSearchManagerSession($this->getId());
    }

    public function getMediaGroupSession()
    {
        return Common::getSendMediaGroupSession($this->getId());
    }

    public function setMediaGroupSession($media_group_id)
    {
        Common::setSendMediaGroupSession($this->getId(), $media_group_id);
    }

    public function resetMediaGroupSession()
    {
        Common::resetSendMediaGroupSession($this->getId());
    }

    public function getDuringCollRespApp()
    {
        return (int)Common::DuringAppByCollResponsible();
    }
    public function setDuringCollRespApp($app_id)
    {
        Common::setDuringAppByCollResponsible($app_id);
    }
    public function resetDuringCollRespApp()
    {
        Common::setDuringAppByCollResponsible(0);
    }

    public function setNotGaveMoneySession($app_id)
    {
        Common::setNotGaveMoneySession($this->getId(), $app_id);
    }

    public function resetNotGaveMoneySession()
    {
        Common::resetNotGaveMoneySession($this->getId());
    }

    public function getNotGaveMoneySession()
    {
        return Common::getNotGaveMoneySession($this->getId());
    }

    public function setNotReceiveMoneySession($app_id)
    {
        Common::setNotReceiveMoneySession($this->getId(), $app_id);
    }

    public function resetNotReceiveMoneySession()
    {
        Common::resetNotReceiveMoneySession($this->getId());
    }

    public function getNotReceiveMoneySession()
    {
        return Common::getNotReceiveMoneySession($this->getId());
    }
}
