<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description 支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class AiYiPay extends PayInfo
{
    //请求接口Url
    public $url = 'https://vip.iyibank.com/pay/gateway';       //扫码正式（线上）调用接口
    public $bank_url = 'https://vip.iyibank.com/pay/gateway';  //第三方网银接口
    public $payName = '爱益支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 223050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '爱益支付',       //支付方式名称
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
        //首次生成payment_config表中的config信息
        $this->setBaseConfig($data['payment_id']);
    
        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 223000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 223001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'service' => $config['payType'][$data['pay_type']]['payStr'],
            'version' => '1.0',
            'charset' => 'UTF-8',
            'sign_type' => 'MD5',
            'mch_id' => $config['merchantID'],
            'out_trade_no' => $orderInfo,
            'device_info' => '',
            'body' => 'recharge',
            'sub_openid' => '',
            'attach' => '',
            'total_fee' => number_format($data['money'], 2, '.', ''),  //金额,精确到两位小数(没有两位小数会出错）
            'mch_create_ip' => ip(),
            'notify_url' => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'],
            'callback_url' =>  "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo,
            'time_start' => '',
            'time_expire' => '',
            'goods_tag' => '',
            'auth_code' => '',
            'nonce_str' => time(),
        );

        if ($data['pay_type'] == 'wy') {
            //网银支付
            $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
            //var_dump($post_data);
        
            //type =2返回html跳转页面数
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data, $this->bank_url)
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        }else {
            //非网银支付，微信、支付宝、QQ钱包支付
            $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
            
            $xmlData = $this->getXml($post_data);
            
            $curlData = $this->httpPost($xmlData, $this->url);  //curl接口
            //var_dump($curlData);
    
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 223002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }

            $retData = json_decode($curlData['data'], true);
            if (!isset($retData['status']) || $retData['status'] != 0 || !isset($retData['result_code']) || $retData['result_code'] != 0) {
                $this->retArr['code'] = 223003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $retSign = $this->getSign($retData, $config['merchantKey']);
            if($retSign != $retData['sign']){
                $this->retArr['code'] = 223004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功，但验证签名失败！' . print_r($retData, true));
            
                return $this->retArr;
            }
            
            $retOderNo = $orderInfo;
            $retPayInfo = isset($retData['token_id']) ? $retData['token_id'] : '';
            $retOderPayQrcodrUrl = isset($retData['pay_info']) ? $retData['pay_info'] : '';
            if (empty($retOderNo) || empty($retPayInfo) || empty(retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 223005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据错误，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            //用于安全验证返回url是否非法
            session::set('qrcode_url', $retOderPayQrcodrUrl);
            session::set('pay_url', $retPayInfo);
            //type =1 返回二维码数据 2，返回html整页数据
            $ret =  [
                'type'     => $config['payType'][$data['pay_type']]['request_type'],
                'code_url' => $retOderPayQrcodrUrl,
                'pay_url'  => $retPayInfo,
                'order_no' => $retOderNo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data'] = $ret;
    
            return $this->retArr;
        }
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
        $data = simplexml_load_string($postData['data']);
        $payment_id = $postData['payment_id'];
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 223020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        if (!isset($data['status']) || $data['status'] != 0 || !isset($data['result_code']) || $data['result_code'] != 0) {
            $this->orderInfo['code'] = 200021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['mch_id'] = $config['merchantID'];
        $retSign = $this->getSign($data, $config['merchantKey']);
        //var_dump($retSign);
        if($retSign != $data['sign']){
            $this->orderInfo['code'] = 223022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['out_trade_no'];
        $this->orderInfo['amount']    = $data['total_fee'];
        $this->orderInfo['serial_no'] = $data['orderid'];

        return $this->orderInfo;
    }
        
        
    
    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    function httpPost($xmlStr, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: text/xml'));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        var_dump($response);
        $response=simplexml_load_string($response);
        
        return array('code' => $httpCode, 'data' => trim($response));
    }

    /**
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    function httpHtml($post_data, $url)
    {
        
        $html = '<html>';
        $html = '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="post" action="' . $url . '">';
        foreach ($post_data as $key => $value) {
            $html .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
        } 
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';
       
        return $html;
    }
    
    /**
     * 生成xml数据
     * @param array $data 提交数组
     * @return stirng 
     */
    private function getXml($data)
    {
        $src = "<xml>";
        foreach ($data as $k => $v) {
            $src .= "<" . $k . ">" .  $v . "</" . $k  . ">";
        }
        $src .="</xml>";
    
        return $src;
    }

    /**
     * 校验签名
     * @param type $msgData
     * @return int
     */
    private function getSign($data, $key)
    {
        $postbuff = "";
        
        ksort($data); //对数组进行排序
        
        foreach ($data as $k => $v) {
            if($k != "sign" &&  $v != "" && !is_array($v)) {
                $postbuff .= $k . "=" .  $v . "&";
            }
        }
        $retSgin = strtoupper(md5($postbuff . "key=" . $key));
    
        return $retSgin;
    }
    
    /**
     * 支付初始数据配置数据库
     * @param int $payment_id 支付类型ID
     */
    public function setBaseConfig($payment_id)
    {
        $data['name'] = $this->payName;
        $data['merchantID'] = '1791';
        $data['merchantKey'] = '7378bbb54c3d4de0927bbf4eee769560';
        $data['payType']['wx']['name'] = '微信支付';
        $data['payType']['wx']['payStr'] = 'cibweixin';
        $data['payType']['wx']['request_type'] = 1;      //request_type： 1，获取二维码，2，跳转html
        $data['payType']['ali']['name'] = '支付宝支付';
        $data['payType']['ali']['payStr'] = 'cibalipay';
        $data['payType']['ali']['request_type'] = 1;
        $data['payType']['wy']['name'] = '网银支付';
        $data['payType']['wy']['payStr'] = 'cnp_u';
        $data['payType']['wy']['request_type'] = 2;  //request_type： 1，获取二维码字符串，2，跳转html页面
        $data['payType']['wy']['bank_id'] = '';
        
        $serData = serialize($data);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}