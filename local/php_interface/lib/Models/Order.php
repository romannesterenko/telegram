<?php
namespace Models;
use Api\Mattermost;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
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

    public function getByDayAndCurrency($day_id, $currency_id): Order
    {

        return $this->where('PROPERTY_WORK_DAY', $day_id)->where('PROPERTY_CURRENCY', $currency_id)->get();
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
    public function createFromAppID($app_id)
    {
        $applications = new Applications();
        $app = $applications->find($app_id);
        $fields = [];
        $properties['CASH_ROOM'] = $app->cash_room()->getId();
        $properties['APP'] = $app->getId();

        $properties['WORK_DAY'] = $app->cash_room()->getNearestWorkDay()->getId();
        $values = $app->getField('SUMM');
        $currencies = $app->getField('CURRENCY');
        if (ArrayHelper::checkFullArray($values)&&ArrayHelper::checkFullArray($currencies)){
            foreach ($values as $id=>$sum){
                $currencies_o = new Currency();
                $currency = $currencies_o->find($currencies[$id]);
                $properties['SUM'] = $sum;
                $properties['CURRENCY'] = $currencies[$id];
                if($app->isPayment()){
                    $fields['NAME'] = 'Расходный ордер от '.date('d.m.Y H:i:s').". Заявка №".$app->getId().". Валюта ".$currency->getName();
                    $properties['OPERATION_TYPE'] = 28;
                    $properties['STATUS'] = 38;
                }else{
                    $fields['NAME'] = 'Приходный ордер от '.date('d.m.Y H:i:s').". Заявка №".$app->getId().". Валюта ".$currency->getName();
                    $properties['OPERATION_TYPE'] = 29;
                    $properties['SUM_FACT'] = $properties['SUM'];
                    $properties['STATUS'] = 39;
                }
                $fields['PROPERTY_VALUES'] = $properties;
                $order_id = self::create($fields);
            }
        }
    }

    public function setComplete()
    {
        $this->setStatus(39);
        $app = $this->app();
        if($app->isPayment()){
            $message = "Выдача. Контрагент - ".$app->getField('AGENT_OFF_NAME').". Касса ".$app->cash_room()->getName()."\n";
            $values = $app->getField('SUMM');
            $currencies = $app->getField('CURRENCY');
            if (ArrayHelper::checkFullArray($values)&&ArrayHelper::checkFullArray($currencies)) {
                foreach ($values as $id => $sum) {
                    $currencies_o = new Currency();
                    $currency = $currencies_o->find($currencies[$id]);
                    $message.="\n".$currency->getName()." - ".number_format($sum, 0, '', ' ')." ".$currency->getField("CODE");
                }
            }
        }else{
            $cash = $app->getCash();
            $message = "Приход ".$app->getField('AGENT_OFF_NAME').". ".implode(", ", $cash)."\n";
        }
        Mattermost::send($message);
    }

    public function setStatus($status_id)
    {
        $this->setField('STATUS', $status_id);

    }

    public function setReturnedStatus(){
        $this->setStatus(51);
        $app = $this->app();
        $cash = $app->getCash();
        $message = "Приход (Возврат заявки) ".$app->getField('AGENT_OFF_NAME').". ".implode(", ", $cash)."\n";
        Mattermost::send($message);
    }

    public function setRealSum($real_sum)
    {
        $this->setField('SUM_FACT', $real_sum);
    }

    public function setCrew($crew_id)
    {
        $this->setField('CREW', $crew_id);
    }

    public function setInDelivery()
    {
        $this->setStatus(50);
    }

    private function app():Applications
    {
        $applications = new Applications();
        return $applications->find($this->getField('APP'));
    }
}
