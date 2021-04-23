<?php
/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description 聚合支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class JuHePay extends PayInfo
{
    //请求接口Url
    //public $url = 'http://103.193.173.114/onlinepay_mobile/services/mainScan/pay';             //扫码测试调用接口
    //public $bank_web = 'http://103.193.173.114/onlinepay_mobile /services/wap/pay';             //wap支付测试接口
    //public $bank_url = 'http://103.193.173.114/onlinepay_mobile/services/order/onlineBank';    //第三方网银测试接口
    public $url = 'http://103.94.76.203/onlinepay_mobile/services/mainScan/pay';             //扫码正式（线上）调用接口
    public $url_wap = 'http://103.94.76.203/onlinepay_mobile/services/wap/pay';             //wap支付正式（线上）接口
    public $bank_url = 'http://103.94.76.203/onlinepay_mobile/services/order/onlineBank';   //第三方网银正式（线上）接口
    public $payName = '聚合支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 244050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '聚合支付',   //支付方式名称
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
            $this->retArr['code'] = 200001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 200001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'merchant_id' => $config['merchantID'],  //商户ID
            'order_id'    => $orderInfo,           //商户支付订单号,每次访问生成的唯一订单标识
            'order_amt'   => (string)(number_format($data['money'], 2, '.', '') * 100),  //金额,单位：分
            'bg_url'      => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'return_url'  => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo
        );

        if ($data['pay_type'] == 'wy') {
            //网银支付
            $post_data['bank_code'] = 'ABC';
            $post_data['card_type'] = '0';
            $post_data['sign'] = $this->getSigned($post_data, $config['merchantKey']);
            $post_data['extra'] = 'recharge';
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
            $post_data['biz_code'] = (string)$config['payType'][$data['pay_type']]['payStr'];  //签名类型,RSA或MD5
            $post_data['sign'] = $this->getSigned($post_data, $config['merchantKey']);
            $post_data['extra'] = 'recharge';
            //$post_data['open_id'] = '';  //微信用户在appid下唯一标识
            //$post_data['app_id']  = '';  //微信公众号appid
            //var_dump($post_data);
            
            //WAP支付
            if (in_array($data['pay_type'], ['wxwap','aliwap'])) {
                //type =2返回html跳转页面数
                $retData =  [
                    'type'  => $config['payType'][$data['pay_type']]['request_type'],
                    'modes' => $data['pay_model'],
                    'html'  => $this->httpHtml($post_data, $this->url_wap)
                ];
                
                $this->retArr['code'] = 0;
                $this->retArr['data']  = $retData;
                
                return $this->retArr;
            }

            //curl接口
            $dataJson = json_encode($post_data);
            //var_dump($dataJson);
            $curlData = $this->httpPostJson($dataJson, $this->url);
            //var_dump($curlData);
    
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 202002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }
            
            $retData = json_decode($curlData['data'], true);
            if (!isset($retData['rsp_code']) || $retData['rsp_code'] != '00' || !isset($retData['state']) || $retData['state'] != 0) {
                $this->retArr['code'] = 200003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($retData, true));
                
                return $this->retArr;
            }
            
            //防止错传
            $data['merchant_id']    = $config['merchantID'];
    
            $retSign = $this->getSigned($retData, $config['merchantKey']);
            if($retSign != $retData['sign']){
                $this->retArr['code'] = 200004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功，但验证签名失败！' . print_r($retData, true));
            
                return $this->retArr;
            }
            
            $retOderNo = isset($retData['order_id']) ? $retData['order_id'] : '';
            $retOderPayNo = isset($retData['up_order_id']) ? $retData['up_order_id'] : '';
            $retOderPayQrcodrUrl = isset($retData['pay_url']) ? $retData['pay_url'] : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty($retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 200005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据错误，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 200006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
                
                return $this->retArr;
            }
            
            if ($post_data['order_amt'] != $retData['order_amt']) {
                $this->retArr['code'] = 200006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功,但接口返回的订单金额与实际订单金额不相同，' . print_r($retData, true));
                
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
        parse_str($postData['data'], $data);
        //$data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 200020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if (!isset($data['state']) || $data['state'] != 0) {
            $this->orderInfo['code'] = 200021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchant_id'] = $config['merchantID'];
        $retSign = $this->getSigned($data, $config['merchantKey']);

        if($retSign != $data['sign']){
            $this->orderInfo['code'] = 200022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['order_id'];
        $this->orderInfo['amount']    = ($data['order_amt'] / 100);
        $this->orderInfo['serial_no'] = $data['up_order_id'];

        return $this->orderInfo;
    }
        
        
    
    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    public function httpPostJson($jsonStr, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              'Content-Type: application/json;charset=utf-8',
              'Content-Length: ' . strlen($jsonStr)
            )
        );

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$response=simplexml_load_string($response);
        return array('code' => $httpCode, 'data' => trim($response));
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

    /**
     * 参数签名
     * @param array $data 签名参数
     * @param string $key MD5秘钥
     * @return string
     */
    public function getSigned($data, $key)
    {
        $sign  = '';
 
        ksort($data);

        foreach ($data as $k => $v) {
            if ($k != 'sign' && $k != 'extra' && $k != 'rsp_code' && $k != 'rsp_msg') {
                $sign .= $k . '=' . $v . '&';
            }
        }
    
        $sign  .= $key;
    
        //var_dump($sign);
    
        $strMd5 = strtoupper(md5($sign));
    
        return $strMd5;
    }
}