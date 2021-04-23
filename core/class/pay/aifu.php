<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description 艾付支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class AiFuPay extends PayInfo
{
    //请求接口Url
    //public $url = 'http://pay.ifeepay.com/gateway/pay.jsp';               //扫码测试调用接口
    public $url = 'http://pay.ifeepay.com/gateway/pay.jsp';               //扫码正式（线上）调用接口
    public $bank_url = 'http://pay.ifeepay.com/gateway/pay.jsp';  //第三方网银接口
    public $payName = '艾付支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 222050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '艾付支付',       //支付方式名称
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
            $this->retArr['code'] = 222001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 222001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        //保证数组字段位置不变
        $post_data = array(
            'version' => 'v1',                  
            'merchant_no' => $config['merchantID'],       //商户ID
            'order_no'    => $orderInfo,                  //商户支付订单号,每次访问生成的唯一订单标识
            'goods_name'  => base64_encode('recharge'),   //商品主题
            'order_amount'=> number_format($data['money'], 2, '.', ''),  //金额,精确到两位小数(没有两位小数会出错）
            'backend_url' =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'],
            //'backend_url' =>  "https://".$_SERVER['HTTP_HOST']."/api/pay/doPaycallBack/payment_id/" . $data['payment_id'],
            'frontend_url'=> "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo,
            'reserve'     => '',
            'pay_mode'    => $config['payType'][$data['pay_type']]['pay_model'],
            'bank_code'   => $config['payType'][$data['pay_type']]['payStr'], 
            'card_type'   => 2
        );
        
        if ($data['pay_type'] == 'wy') {
            //网银支付
            $post_data['bank_code'] = $data['bank_code'];
            $sign = '';
            foreach ($post_data as $key => $val) {
                $sign .= $key . '=' . $val . '&';
            }
            $sign .= 'key=' . $config['merchantKey'];
            $post_data['sign'] = md5($sign);
        
            //type = 2返回html跳转页面数
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
            $sign = '';
    		foreach ($post_data as $key => $val) {
    			$sign .= $key . '=' . $val . '&';
    		}
    		$sign .= 'key=' . $config['merchantKey'];
    		//var_dump($sign);
    		$post_data['sign'] = md5($sign);
            
            //curl接口
            $curlData = $this->httpPost($post_data, $this->url);
            //var_dump($curlData);
    
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 222002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }
            
            $retData = json_decode($curlData['data'], true);
            if (!isset($retData['result_code']) || $retData['result_code'] != '00') {
                $this->retArr['code'] = 222003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            /*
            $retSign = getSigned($retData, ['key' => $config['merchantKey']], ['sign']);
            if($retSign != $retData['sign']){
                $this->retArr['code'] = 200004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功，但验证签名失败！' . print_r($retData, true));
            
                return $this->retArr;
            }
            */
            
            $retOderNo = isset($retData['order_no']) ? $retData['order_no'] : '';
            $retOderPayNo = 'aifu'. date('YmdHis');
            $retOderPayQrcodrUrl = isset($retData['code_url']) ? $retData['code_url'] : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty(retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 222005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据错误，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 222006;
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
        //处理get回调数据
        $payment_id = $postData['payment_id'];
        D('accountrecharge')->save(['verify_remark' => print_r($_REQUEST, true)], ['id' => 6]);
        $data = array(
            'merchant_no'    => $_REQUEST['merchant_no'],
            'order_no'		 => $_REQUEST['order_no'],
            'order_amount'	 => $_REQUEST['order_amount'],
            'original_amount' => $_REQUEST['original_amount'],
            'upstream_settle' => $_REQUEST['upstream_settle'],
            'result'		 => $_REQUEST['result'],
            'pay_time'		 => $_REQUEST['pay_time'],
            'trace_id'		 => $_REQUEST['trace_id'],
            'reserve'		 => $_REQUEST['reserve']
        );
        
        $retSign = $_REQUEST['sign'];
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 222020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        if (!isset($data['result']) || $data['result'] != 'S') {
            $this->orderInfo['code'] = 200021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchant_no'] = $config['merchantID'];
        $sign = '';
        $signStr = '';
        foreach ($data as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr .= 'key=' . $config['merchantKey'];
        $sign = md5($signStr);
        if($retSign != $sign){
            $this->orderInfo['code'] = 222022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['order_no'];
        $this->orderInfo['amount']    = $data['order_amount'];
        $this->orderInfo['serial_no'] = $data['trace_id'];

        return $this->orderInfo;
    }
        
        
    
    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $post_data 提交数据
     * @return array[]|mixed[] 返回数据
     */
    function httpPost($post_data, $url)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        //$response=simplexml_load_string($response);
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
        $html .= '<head>';
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
     * 支付初始数据配置数据库
     * @param int $payment_id 支付类型ID
     */
    public function setBaseConfig($payment_id)
    {
        $data['name'] = $this->payName;
        $data['merchantID'] = '144711003290';
        $data['merchantKey'] = 'f77b68ba-cf2c-11e7-a7ce-cd82d7a120c2';
        $data['partnerId'] = 'ORG_1495786664861';
        $data['payType']['wx']['name'] = '微信支付';
        $data['payType']['wx']['payStr'] = 'WECHAT';
        $data['payType']['wx']['pay_model'] = '09';
        $data['payType']['wx']['request_type'] = 1;      //request_type： 1，获取二维码，2，跳转html
        $data['payType']['ali']['name'] = '支付宝支付';
        $data['payType']['ali']['payStr'] = 'ALIPAY';
        $data['payType']['ali']['pay_model'] = '09';
        $data['payType']['ali']['request_type'] = 1;
        $data['payType']['qq']['name'] = 'QQ钱包支付';
        $data['payType']['qq']['payStr'] = 'QQSCAN';
        $data['payType']['qq']['pay_model'] = '09';
        $data['payType']['qq']['request_type'] = 1;
        $data['payType']['jd']['name'] = '京东钱包';
        $data['payType']['jd']['payStr'] = 'JDSCAN';
        $data['payType']['jd']['pay_model'] = '09';
        $data['payType']['jd']['request_type'] = 1;
        $data['payType']['wy']['name'] = '网银支付';
        $data['payType']['wy']['payStr'] = '';
        $data['payType']['wy']['pay_model'] = '01';
        $data['payType']['wy']['request_type'] = 2;  //request_type： 1，获取二维码字符串，2，跳转html页面
        $data['payType']['wy']['bank_id'] = '2';
        
        $serData = serialize($data);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}