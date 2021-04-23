<?php
/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2018/1/4 11:06
 * @description 银聚支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class YinJuPay extends PayInfo
{
    //请求接口Url
    //public $url = '';               //扫码测试调用接口
    public $url = 'http://yjpay.eyubao.net/pay/nativePay';  //扫码正式（线上）调用接口
    public $bank_url = '';  //第三方网银接口
    public $payName = '银聚支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 247050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'success',  //回调处理成功时，返回接口字符串
        'bank_name' => '银聚支付',       //支付方式名称
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
        if (!$orderInfo) {
            $this->retArr['code'] = 247001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 247001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'service'  => $config['payType'][$data['pay_type']]['payStr'],
            //'version'  => '2.0',
            //'charset'  => 'UTF-8',
            //'sign_type'  => 'MD5',
            'mch_id'  => $config['merchantID'],  //商户ID
            'out_trade_no'   => $orderInfo,      //商户支付订单号,每次访问生成的唯一订单标识
            //'device_info'  => '',                //终端设备号
            'body'  => 'Recharge',               //商品描述
            //'attach' => 'Recharge',              //商户附加信息，可做扩展参数，255字符内
            //'limit_credit_pay' => 0,             //限定用户使用时能否使用信用卡，值为1，禁用信用卡，值为0或者不传此参数则不禁用
            'total_fee' => (number_format($data['money'], 2, '.', '') * 100),  //金额,单位：分
            'mch_create_ip'  => ip(),            //订单生成的机器 IP
            //'notify_url' =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'],
            'notify_url' =>  "https://".$_SERVER['HTTP_HOST'],
            //'time_start'  => date('YmdHis'),
            //'time_expire' => date('YmdHis',time() + 86400),
            'nonce_str'  => $this->getRandomString(16)
        );
        
        if ($data['pay_type'] == 'wy') {
            //网银支付
            $post_data['signData'] = $this->getSigned($post_data, ['key' => $config['merchantKey']], '', 0, 0);
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
            $post_data['sign'] = $this->getSigned($post_data, $config['merchantKey']);
            
            //curl接口
            $curlData = $this->httpPostXml($post_data, $this->url);
            var_dump($curlData);
    
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 247002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }
            
            $retData = json_decode($curlData['data'], true);
            if (!isset($retData['resCode']) || $retData['resCode'] != 'S01000000' || !isset($retData['resMsg']) || $retData['resMsg'] != '成功') {
                $this->retArr['code'] = 247003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $retSign = getSigned($retData, ['key' => $config['merchantKey']], ['sign']);
            if($retSign != $retData['sign']){
                $this->retArr['code'] = 247004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功，但验证签名失败！' . print_r($retData, true));
            
                return $this->retArr;
            }
            
            //$retOderNo = $orderInfo;
            $retOderNo = isset($retData['orderNo']) ? $retData['orderNo'] : '';
            //$retOderPayNo = '';
            $retOderPayNo = isset($retData['payOrderNo']) ? $retData['payOrderNo'] : '';
            $retOderPayQrcodrUrl = isset($retData['payQrCodeUrl']) ? $retData['payQrCodeUrl'] : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty($retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 247005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据错误，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 247006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            D('accountRecharge')->save(['remark' => $retOderPayNo], ['order_sn' => $retOderNo]);
    
            //用于安全验证返回url是否非法
            session::set('qrcode_url', $retOderPayQrcodrUrl);
            session::set('pay_url', '');
            //type =1 返回二维码数据 2，返回html整页数据
            $ret =  [
                'type'     => $config['payType'][$data['pay_type']]['request_type'],
                'code_url' => $retOderPayQrcodrUrl,
                'pay_url'  => '',
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
        $data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 247020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $retData = array(
            //''   => trim($getData['']),
            //''   => $_REQUEST(''),
            //'partnerId'   => $config['partnerId'],  //合作方编号,代理商ID
            //'merchId'     => $config['merchantID'],  //商户ID
            'merchId'     => isset($data['merchId']) ? $data['merchId'] : '' ,  //商户ID
            'amount'      => isset($data['amount']) ? $data['amount'] : '',
            'platOrderNo' => isset($data['platOrderNo']) ? $data['platOrderNo'] : '',
            'merOrderNo'  => isset($data['merOrderNo']) ? $data['merOrderNo'] : '',
            'resCode'     => isset($data['resCode']) ? $data['resCode'] : '',
            //'completeTime' => isset($data['completeTime']) ? $data['completeTime'] : '',
            //'receiveTime' => isset($data['receiveTime']) ? $data['receiveTime'] : '',
            //'signType' => 'MD5',
            'signType'    => isset($data['signType']) ? $data['signType'] : ''
        );
        if (!isset($data['resCode']) || $data['resCode'] != '0000' || !isset($data['resMsg']) ||  $data['resMsg'] != '支付成功') {
            $this->orderInfo['code'] = 247021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchId']    = $config['merchantID'];
        //$data['signType']   = 'MD5';
        //$data['resCode'] = 'S01000000';
        //var_dump($data);
        //$retSign = getSigned($data, ['key' => $config['merchantKey']], ['signInfo', 'receiveTime', 'resMsg', 'completeTime', 'partnerId', 'signType']);
        $retSign = getSigned($data, ['key' => $config['merchantKey']], ['signInfo', 'receiveTime', 'completeTime', 'resMsg']);
        //var_dump($retSign);
        if($retSign != $data['signInfo']){
            $this->orderInfo['code'] = 247022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['merOrderNo'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['platOrderNo'];

        return $this->orderInfo;
    }
    
    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    public function httpPostXml($data, $url)
    {
        $xml = $this->arrayToXml($data);
var_dump($xml);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $res = simplexml_load_string($response);

        return array('code' => $httpCode, 'data' => (array)$res);
    }

    /**
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    public function httpHtml($post_data, $url)
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
    
    function arrayToXml($data)
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?><xml>';

        if (!is_array($data)) return false;

        foreach ($data as $key=>$val)
        {
            if (is_numeric($val)){
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }else{
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        
        $xml.="</xml>";

        return $xml;
    }
    
    /**
     * 生成签名
     * @param $data arrary 生成签名需要的数据
     * @param $key int 用户秘钥
     * @return string 生成签名
     */
    public function getSigned($data, $key)
    {
        $string = '';
        
        ksort($data);
    
        foreach ($data as $k => $v) {
            if (!is_array($v) && $k != 'sign' && $v != '') {
                $string .= $k . '=' . $v . '&';
            }
        }
        
        $string .= '=' . $key;

        $retStr = strtoupper(md5($string));
    
        return $retStr;
    }
    
    /**
     * 生成随机字符串
     * @param int $len 需要生成随机字符串的长度
     * @param string $chars 给定随机字符串母集，默认为a-Z0-9
     * @return string 生成的随机字符串
     */
    function getRandomString($len, $chars = null)
    {
        if (is_null($chars)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        }
    
        for ($i = 0, $str = '', $lc = strlen($chars) - 1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
    
        return $str;
    }
}