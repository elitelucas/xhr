<?php

abstract class drawalinfo
{

    public abstract function doWithdraw($data);

    public abstract function queryOrder($data,$channel = false);

    public function redisInfo($data)
    {
        $infos = array(
            'account_cash_id'=>$data['account_cash_id'],
            'dealtime'=>time(),
            'order_no'=>$data['order_no'],
            'payment_id' =>$data['payment_id'],
            'drawal_name' =>$data['drawal_name'],
            'nid' => $data['nid']
        );
        return $infos;
    }

    public function neededData($paymentid)
    {
        $sql = "select config from un_payment_config where id = ".$paymentid;
        $result = O('model')->db->getone($sql);
        $config = unserialize($result['config']);
        return $config;
    }

}
