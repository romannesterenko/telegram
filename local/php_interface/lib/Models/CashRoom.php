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
        $cash_room_days_open = new CashRoomDay();
        $cash_currencies = $this->getField('CURRENCY');
        $crdo = $cash_room_days_open->getExistsOpenToday($this->getField('ID'));
        if ($crdo->getId()>0) {
            $start_cash = $crdo->getField('START_SUM');
        } else {
            $closed_crds = new CashRoomDay();
            $crdo = $closed_crds->getLastClosedFromCashRoom($this->getField('ID'));
            $start_cash = $crdo->getField('START_SUM');
        }
        $start_cash_array = [];
        foreach ($start_cash as $i => $c){
            $start_cash_array[$cash_currencies[$i]] = $c;
        }
        if($cash_room_days_open->isExistsOpenToday($this->getField('ID'))) {
            if(ArrayHelper::checkFullArray($cash_currencies)){
                foreach ($cash_currencies as $currency_id) {
                    $start_cash_ = $start_cash_array[$currency_id];
                    $cur_obj = new Currency();
                    $current_currency = $cur_obj->find($currency_id);
                    $return_array[$currency_id] = [
                        'currency_title' => $current_currency->getField('NAME'),
                        'currency_code' => $current_currency->getField('CODE'),
                        'all' => $start_cash_,
                        'reserve' => 0,
                        'free' => 0
                    ];
                    $orders = new Order();
                    $order_list = $orders->getByDayAndCurrency($this->getNearestWorkDay()->getId(), $currency_id);
                    if (ArrayHelper::checkFullArray($order_list->getArray())) {
                        foreach ($order_list->getArray() as $order) {
                            $sum = (int)$order['PROPERTY_SUM_FACT_VALUE'] > 0 ? (int)$order['PROPERTY_SUM_FACT_VALUE'] : (int)$order['PROPERTY_SUM_VALUE'];
                            if ($order['PROPERTY_STATUS_ENUM_ID'] == 38) {
                                if ($order['PROPERTY_OPERATION_TYPE_ENUM_ID'] == 28) {
                                    $return_array[$currency_id]['reserve'] += $sum;
                                }
                            } elseif ($order['PROPERTY_STATUS_ENUM_ID'] == 39 || $order['PROPERTY_STATUS_ENUM_ID'] == 50) {
                                if ($order['PROPERTY_OPERATION_TYPE_ENUM_ID'] == 29) {
                                    $return_array[$currency_id]['all'] += $sum;
                                }
                                if ($order['PROPERTY_OPERATION_TYPE_ENUM_ID'] == 28) {
                                    $return_array[$currency_id]['all'] -= $sum;
                                }
                            }

                        }
                    }
                    $return_array[$currency_id]['free'] = $return_array[$currency_id]['all'] - $return_array[$currency_id]['reserve'];
                }
            }
        } else {
            if(ArrayHelper::checkFullArray($cash_currencies)) {
                foreach ($cash_currencies as $currency_id) {
                    $start_cash_ = $start_cash_array[$currency_id];
                    $cur_obj = new Currency();
                    $current_currency = $cur_obj->find($currency_id);
                    $return_array[$currency_id] = [
                        'currency_title' => $current_currency->getField('NAME'),
                        'currency_code' => $current_currency->getField('CODE'),
                        'all' => $start_cash_,
                        'reserve' => 0,
                        'free' => 0
                    ];
                }
            }
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



    public function checkClosedSumCurrency($cash_room_id, $currency_id, $sum): bool
    {
        $cash_room = $this->find($cash_room_id);
        $cash = $cash_room->getCash();
        if ((int)$sum == (int)$cash[$currency_id]['free']) {
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

    public function getCurrencies()
    {
        return $this->getField('CURRENCY');
    }
    public static function isClosed($cr_id): bool
    {
        $crd = new CashRoomDay();
        $today = $crd->getExistsOpenToday($cr_id)->getArray();
        return (int)$today['ID']==0;
    }

    public function getCurencies()
    {
        return $this->getField('CURRENCY');
    }
}
