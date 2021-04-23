<?php
include_cache(S_PAGE . 'model' . DS . 'admin' . DS. 'drawalinfo.php');

class quanyin extends drawalinfo
{
    public $url = "http://agent.quanyinzf.com:8050/rb-pay-web-merchant/agentPay/pay"; //请求接口
    public $queryurl = "http://agent.quanyinzf.com:8050/rb-pay-web-merchant/agentPay/query"; //请求接口
    public $bOptimize = true;

    public $returnInfo = [
        'code' => 1,                 //10：数据获取成功，1数据获取失败 2正在处理
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'drawal_name' => '全银代付',    //支付方式名称
        'serial_no' => '',            //第三方回调返回的第三方支付订单号（支付流水号）
        'account_cash_id' => '',            //提现表ID
        'user_id' => '' ,           //提现表ID
        'payment_id' => '',            //payment_config ID
        'nid' => 'quan_yin_withdraw',            //nid
        'msg' => ''            //错误信息
    ];

    public function doWithdraw($data)
    {
//        payLog('quanyind.txt',print_r($data,true));
        $this->returnInfo['account_cash_id'] = $data['accountCashId'];
        $this->returnInfo['user_id'] = $data['user_id'];
        $this->returnInfo['payment_id'] = $data['payment_id'];
        $this->returnInfo['order_no'] = $data['order_sn'];
        $config = $data['config'];
        unset($data['config']);
        $postData = array(
            'payKey' => $config['merchantID'],
            'orderNo' => $this->returnInfo['order_no'],
            'bankAccountName' => $data['name'],
            'bankAccountNo' => $data['account'],
            'bankCode' => $data['bankcode'],
            'settAmount' => $data['money'],
            'signType' => 'MD5',
        );
        $postData['sign'] = $this->getSign($postData,$config['merchantKey']);
        payLog('quanyind.txt',print_r($postData,true) . "----444---");
        $return = $this->curlPost($postData,$this->url);

        if ($return['code'] != 200) {
            payLog('quanyind.txt',"请求".$this->returnInfo['drawal_name']."接口失败".print_r($return,true));
            $this->queryOrder($this->returnInfo,true);          //查询订单 确认代付请求是否成功
            return($this->returnInfo);
        }

        $retData = json_decode($return['data'],true);
        $this->returnInfo['msg'] = $retData['msg'];
        if ($retData['result'] != "success") {
            payLog('quanyind.txt',$this->returnInfo['drawal_name']."返回代付失败".print_r($retData,true));
            $this->returnInfo['code'] =1;
            return($this->returnInfo);
        }

        $retSign = $retData['sign'];
        $returnSign = $this->getSign($retData,$config['merchantKey']);
        if ($retSign != $returnSign) {
            payLog('quanyind.txt',$this->returnInfo['drawal_name']."返回代付验签失败".print_r($retData,true));
            $this->returnInfo['code'] = 2;          //代付请求成功  但是验签失败 需要轮询处理
            $this->addAutoDrawalListForRedis(true);
            return($this->returnInfo);
        }

        $resutl = $this->queryOrder($this->returnInfo,true);
        $this->returnInfo['amount'] = $data['money'];
        payLog('quanyind.txt',print_r($this->returnInfo,true) . "+++25");
        return($this->returnInfo);

    }

    public function queryOrder($data,$channel=false)
    {
        $config = $this->neededData($data['payment_id']);
        $postData = array(
            'payKey' => $config['merchantID'],
            'orderNo' => $data['order_no'],
            'signType' => 'MD5',
        );
        $postData['sign'] = $this->getSign($postData,$config['merchantKey']);
        $result = $this->curlPost($postData,$this->queryurl);
        payLog('quanyind.txt',print_r($result,true). "+++=84+++".$channel);

        if ($result['code'] != 200) {
            payLog('quanyind.txt',"查询请求".$this->returnInfo['drawal_name']."接口失败".print_r($result,true));
            $this->returnInfo['code'] =2;
            $this->addAutoDrawalListForRedis($channel);
            return $this->returnInfo;
        }

        $result = json_decode($result['data'],true);
        $returnSign =  $this->getSign($result,$config['merchantKey']);

        if($result['sign'] != $returnSign) {
            $result['returnSign'] = $returnSign;
            payLog('quanyind.txt',$this->returnInfo['drawal_name']."查询代付验签失败".print_r($result,true));
            $this->returnInfo['code'] =2;
            $this->addAutoDrawalListForRedis($channel);
            return $this->returnInfo;
        }

        if ($result['result'] != "success") {
            payLog('quanyind.txt',$this->returnInfo['drawal_name']."查询代付失败".print_r($result,true));
            $this->returnInfo['code'] =2;           //本次请求失败         并不能确定代付失败
            $this->addAutoDrawalListForRedis($channel);
            return $this->returnInfo;
        }

        if ($result['sett_status'] == 'process') {
            $this->returnInfo['code'] = 2;
            $this->addAutoDrawalListForRedis($channel);
        } elseif ($result['sett_status'] == 'false' || $result['sett_status'] == 'nofind') {
            $this->returnInfo['code'] = 1;
        } elseif ($result['sett_status'] == 'success') {
            $this->returnInfo['code'] = 10;
            $this->returnInfo['amount'] = $result['settAmount'];
        }
        return $this->returnInfo;
    }


    private function addAutoDrawalListForRedis($channel) {
        if($channel) {
            $redis = initCacheRedis();
            $redisinfo = $this->redisInfo($this->returnInfo);
            $redis->hset("autodrawallist",$this->returnInfo['order_no'],encode($redisinfo));
            deinitCacheRedis($redis);
        }
    }

    public function getSign($data,$key)
    {
        unset($data['sign']);
        ksort($data);
        $str = '';
        foreach ($data as $k=> $v) {
            if ($v != '') {
                $str .= $k ."=".$v."&";
            }
        }
        $str .= 'paySecret='.$key;
        return strtoupper(md5($str));
    }



    public function curlPost($data,$url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,360);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        payLog('quanyind.txt', "curl_返回数据_".print_r(['response' => $response, 'httpCode' => $httpCode], true));
        return array('code' => $httpCode, 'data' => $response);
    }
}
