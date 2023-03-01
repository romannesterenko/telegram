<?php
namespace Models;
use Models\ElementModel as Model;
class Order extends Model {
    const IBLOCK_ID = 8;
    public function add($fields)
    {
        self::create($fields);
    }

    public function getByDay($day_id): Order
    {
        return $this->where('PROPERTY_WORK_DAY', $day_id)->get();
    }

    public function getByCashRoom($cash_room_id): Order
    {
        return $this->where('PROPERTY_CASH_ROOM', $cash_room_id)->get();
    }

    public function createFromApp(Applications $app)
    {
        $fields = [];
        $properties['CASH_ROOM'] = $app->cash_room()->getId();
        $properties['APP'] = $app->getId();
        $properties['SUM'] = $app->getSum();
        $properties['STATUS'] = 38;
        $properties['WORK_DAY'] = $app->cash_room()->getNearestWorkDay()->getId();
        if($app->isPayment()){
            $fields['NAME'] = 'Расходный ордер от '.date('d.m.Y H:i:s').". Заявка №".$app->getId();
            $properties['OPERATION_TYPE'] = 28;
        }else{
            $fields['NAME'] = 'Приходный ордер от '.date('d.m.Y H:i:s').". Заявка №".$app->getId();
            $properties['OPERATION_TYPE'] = 29;
            $properties['SUM_FACT'] = $properties['SUM'];
        }
        $fields['PROPERTY_VALUES'] = $properties;
        $order_id = self::create($fields);
        $app->setOrder($order_id);
    }

    public function setComplete()
    {
        $this->setStatus(39);
    }

    public function setStatus($status_id)
    {
        $this->setField('STATUS', $status_id);
    }

    public function setRealSum($real_sum)
    {
        $this->setField('SUM_FACT', $real_sum);
    }
}
