<?php
namespace Models;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\ElementModel as Model;
class CashRoom extends Model {
    const IBLOCK_ID = 5;

    public function employee()
    {
        $staff = new Staff();
        return $staff->where('PROPERTY_CASH_ROOM', $this->getId())->first();
    }

    public function setExpense(Applications $app)
    {
        $orders = new Order();
        $cash_room_day = new CashRoomDay();
        $fields['NAME'] = 'Выдача. Заявка №'.$app->getId();
        $properties['OPERATION_TYPE'] = 28;
        $properties['APP'] = $app->getId();
        $properties['SUM'] = $app->getSum();
        $properties['CASH_ROOM'] = $this->getId();
        $properties['WORK_DAY'] = $cash_room_day->getTodayByCashRoom($this->getId())->getId();
        $fields['PROPERTY_VALUES'] = $properties;
        $orders->add($fields);
    }

    public function setArrival(Applications $app)
    {
        $orders = new Order();
        $cash_room_day = new CashRoomDay();
        $fields['NAME'] = 'Получение. Заявка №'.$app->getId();
        $properties['OPERATION_TYPE'] = 29;
        $properties['APP'] = $app->getId();
        $properties['SUM'] = $app->getSum();
        $properties['WORK_DAY'] = $cash_room_day->getTodayByCashRoom($this->getId())->getId();
        $properties['CASH_ROOM'] = $this->getId();
        $fields['PROPERTY_VALUES'] = $properties;
        $orders->add($fields);
    }

    public function getCash(): array
    {
        $cash_room_days = new CashRoomDay();
        $cash_room_days_open = new CashRoomDay();
        $orders = new Order();
        $start_cash = $cash_room_days->getCashByCashRoom($this->getField('ID'));
        $return_array = [
            'all' => 0,
            'reserve' => 0,
            'free' => 0
        ];
        if($cash_room_days_open->isExistsOpenToday($this->getField('ID'))) {
            $order_list = $orders->getByDay($this->getNearestWorkDay()->getId());
            $return_array['all'] = $start_cash;
            if (ArrayHelper::checkFullArray($order_list->getArray())) {
                foreach ($order_list->getArray() as $order) {
                    $sum = (int)$order['PROPERTY_SUM_FACT_VALUE'] > 0 ? (int)$order['PROPERTY_SUM_FACT_VALUE'] : (int)$order['PROPERTY_SUM_VALUE'];
                    if ($order['PROPERTY_STATUS_ENUM_ID'] == 38) {
                        if ($order['PROPERTY_OPERATION_TYPE_ENUM_ID'] == 28) {
                            $return_array['reserve'] += $sum;
                        }
                    } elseif ($order['PROPERTY_STATUS_ENUM_ID'] == 39 || $order['PROPERTY_STATUS_ENUM_ID'] == 50) {
                        if ($order['PROPERTY_OPERATION_TYPE_ENUM_ID'] == 29) {
                            $return_array['all'] += $sum;
                        }
                        if ($order['PROPERTY_OPERATION_TYPE_ENUM_ID'] == 28) {
                            $return_array['all'] -= $sum;
                        }
                    }

                }
            }
            $return_array['free'] = $return_array['all'] - $return_array['reserve'];
        } else {
            $return_array = [
                'all' => $start_cash,
                'reserve' => 0,
                'free' => $start_cash
            ];
        }
        return $return_array;
    }

    public function checkSum($cash_room_id, $sum): bool
    {
        $cash_room_days = new CashRoomDay();
        $day = $cash_room_days->getLastClosedFromCashRoom($cash_room_id);
        if ((int)$sum == (int)$day->getField('END_SUM')) {
            return true;
        } else {
            return false;
        }
    }

    public function checkClosedSum($cash_room_id, $sum): bool
    {
        $cash_room = $this->find($cash_room_id);
        $cash = $cash_room->getCash();
        if ((int)$sum == (int)$cash['free']) {
            return true;
        } else {
            return false;
        }
    }

    public function getNearestWorkDay(): CashRoomDay
    {
        $crd = new CashRoomDay();
        $today = $crd->getExistsOpenToday($this->getId());
        if($today->getId()>0)
            return $today;
        else
            return $crd->getLastByCashRoom($this->getId());
    }
}
