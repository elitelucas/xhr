<?php

//Hady
include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class XinBaoPay extends PayInfo
{
    //请求接口Url
    public $url = 'http://qrpay.naodaida.com/pay/json';               //扫码正式（线上）调用接口
    //public $url = 'http://qrpay.naodaida.com/guide/board';               //扫码测试调用接口
    public $payName = '鑫宝付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 270000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => '000000',  //回调处理成功时，返回接口字符串
        'bank_name' => '鑫宝支付',    //支付方式名称
        'serial_no' => ''            //第三方回调返回的第三方支付订单号（支付流水号）
    ];

    /**
     * 构成函数
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 调用第三方支付接口，获取支付信息
     * @param array $data 前端返回信息，payment_id，支付类型ID，config，支付类型配置信息
     * {@inheritDoc}
     * @see PayInfo::doPay()
     * @return $this->$retArr;
     */
    public function doPay($data)
    {
        //生成订单
        $orderInfo = $this->makeOrder($data);
        payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        if (!$orderInfo) {
            $this->retArr['code'] = 270001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 270002;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $post_data = array(
            'app_id'      => $config['merchantID'], // 支付系统分配给商户的账号
            'channel'     => $config['payType'][$data['pay_type']]['payStr'],
            'order_sn'    => $orderInfo,
            'amount'      => $data['money'],
            'notify_url'  => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'] ,  //商户通知地址
            'return_url'  => "https://".$_SERVER['HTTP_HOST'],  //商户通知地址
            'nonce_str'   => getRandomString(32)
        );
        
        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        $post_str = http_build_query($post_data);

        $curlData = $this->httpPost($post_data, $this->url);
        var_dump($curlData);
        //接口调用成功与否
        if ($curlData['code'] != 200) {
            $this->retArr['code'] = 270003;
            $this->retArr['msg']  = '付支付接口调用失败';
            payLog('baifu.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，--89--' . print_r($curlData, true).'--101--');

            return $this->retArr;
        }

        $retData = json_decode($curlData['data'], true);
        if ($retData['code'] != 200 || $retData['msg'] != 'ok') {
            $this->retArr['code'] = 270004;
            $this->retArr['msg']  = '付支付接口调用失败';
            payLog('baifu.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，--98--' . print_r($retData, true).'--94--');

            return $this->retArr;
        }
        
        $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retData['data']['order_sn'], 'status' => 0));
        if (empty($result)) {
            $this->retArr['code'] = 270006;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '可可支付返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
        
            return $this->retArr;
        }
        
        D('accountRecharge')->save(['remark' => $retData['data']['system_sn']], ['order_sn' => $retData['data']['order_sn']]);

        if ($retData['merchantNo'] != $config['merchantID']) {
            $this->retArr['code'] = 240005;
            $this->retArr['msg']  = $retData['resultMsg'];
            payLog('baifu.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，--114--' . print_r($retData, true));

            return $this->retArr;
        }
//        $reSign = $retData['sign'];
//        unset($retData['sign']);
//        $sign = strtoupper($this->getSign($retData));
//        payLog('baifu.txt', $sign.'--121--');

        //用于安全验证返回url是否非法
        session::set('qrcode_url', $retData['data']['data']);
        session::set('pay_url', '');

        $ret =  [
            'type'     => 1,
            'code_url' => $retData['data']['data'],
            'pay_url'  => '',
            'order_no' => $orderInfo,
            'modes'    => $data['pay_model']
        ];

        $this->retArr['code'] = 0;
        $this->retArr['data'] = $ret;

        return $this->retArr;
    }

    /**
     * 支付回调处理
     * @param array $postData data：回调返回的数据，payment_Id：支付类型ID
     * {@inheritDoc}
     * @see PayInfo::doPaycallBack()
     * @return array $this->$retArr;
     */
    public function doPaycallBack($postData)
    {
        //处理post回调数据
        $payData = explode('=', $postData['data']);       //从商户传输的参数中获取回传参数
        $data = json_decode($payData[1],true);

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 270006;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchantNo'] = $config['merchantID'];
        $sign = $data['sign'];
        unset($data['sign']);
        $retSign = $this->getSign($data, $config['merchantKey']);
        if(strtoupper($retSign) != $sign){
            $this->orderInfo['code'] = 270008;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['order_sn'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['payment_sn'];

        return $this->orderInfo;
    }

    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    public function httpPost($data, $url)
    {
        $ch = curl_init(); //初始化curl
        curl_setopt($ch,CURLOPT_URL, $url);  //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);  //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return array('code' => $httpCode, 'data' => trim($response));
    }

    public function getSign($data, $key)
    {
        ksort($data);
        
        $data['app_key'] = $key;
        $str = urldecode(http_build_query($data));
        
        $sign = md5($str);

        return $sign;
    }

}
