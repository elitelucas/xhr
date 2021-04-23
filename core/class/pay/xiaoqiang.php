<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description 小强支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class XiaoQiangPay extends PayInfo
{
    //请求接口Url
    //public $url = 'http://test.xqiangpay.net/website/pay.htm';       //扫码测试调用接口
    public $url = 'https://www.aklpay.com/website/pay.htm';       //扫码正式（线上）调用接口
    public $bank_url = 'https://www.aklpay.com/website/pay.htm';  //第三方网银接口
    public $payName = '小强支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 224050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '小强支付',    //支付方式名称
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
            $this->retArr['code'] = 224001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];

        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 224001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'version'         => '1.0',                  
            'serialID'        => date('Ymdhis',time()) . rand(100,999), //每次访问生成的唯一订单标识
            'submitTime'      => date('YmdHis'),
            'failureTime'     => '',
            'customerIP'      => '',
            'orderDetails'    => $orderInfo . ',' . number_format($data['money'], 2, '.', '') * 100 . ',' . 'recharge' . ',' . 'recharge' . ',' . 1,
            'totalAmount'     => number_format($data['money'], 2, '.', '') * 100,  //金额,精确到两位小数
            'type'            => '1000',
            'buyerMarked'     => '',
            'payType'         => $config['payType'][$data['pay_type']]['payStr'],  
            'orgCode'         => $config['payType'][$data['pay_type']]['payCode'],  
            'currencyCode'    => 1,
            'directFlag'      => 1,
            'borrowingMarked' => 0,
            'couponFlag'      => 0,
            'platformID'      => '',
            'returnUrl'       => "https://".$callbackurl."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo,
            'noticeUrl'       => "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'partnerID'       => $config['merchantID'],  //商户ID
            'remark'          => '',
            'charset'         => 1,
            'signType'        => 2
        );
        
        $post_data['signMsg'] = $this->getSign($post_data, $config['merchantKey']);
        //$post_data['pureQr']  = '';   //页面跳转扫码

        if (in_array($data['pay_type'],['wxh', 'qqh', 'alih', 'jdh', 'wykj'])) {
            //type =2返回html跳转页面数

            $retData =  [
                'type'  => 2,
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data, $this->bank_url)
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
            
        }else {
            //返回二维数据（只返回二维码数据，没有验签机制，有安全风险）
            $post_data['pureQr']  = 'true';

            $curlData = $this->httpPost($post_data, $this->url);
            //var_dump($curlData);
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 224002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }

            $retData = json_decode($curlData['data'], true);
            if (empty($retData['codeUrl'])) {
                $this->retArr['code'] = 224003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($retData, true));
            
                return $this->retArr;
            }
            
            $retOderNo = $orderInfo;
            $retOderPayNo = 'xiaoqiang' . date('YmdHis');
            $retOderPayQrcodrUrl = isset($retData['codeUrl']) ? $retData['codeUrl'] : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty($retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 224004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据错误，' . print_r($retData, true));
                
                return $this->retArr;
            }

            D('accountRecharge')->save(['remark' => $retOderPayNo], ['order_sn' => $retOderNo]);
    
            //用于安全验证返回url是否非法
            session::set('qrcode_url', $retOderPayQrcodrUrl);
            session::set('pay_url', '');
            //type =1 返回二维码数据 
            $ret =  [
                'type'     => 1,
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
        $payment_id = $postData['payment_id'];

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 224020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        //注意：根据文档返回数据resultCode == 0000时为成功，但是实际返回值为空
        //if (!isset($data['resultCode']) || $data['resultCode'] != '0000' || !isset($data['stateCode']) ||  $data['stateCode'] != 2) {
        if (!isset($data['stateCode']) ||  $data['stateCode'] != 2) {
            $this->orderInfo['code'] = 224021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['partnerID']    = $config['merchantID'];
        $retSign = $this->getSign($data, $config['merchantKey']);

        if($retSign != $data['signMsg']){
            $this->orderInfo['code'] = 224022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['orderID'];
        $this->orderInfo['amount']    = number_format(($data['payAmount'] / 100), 2, '.', '');
        $this->orderInfo['serial_no'] = $data['orderNo'];

        return $this->orderInfo;
    }
    
    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    function httpPost($data, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

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
     * 参数签名
     * @param array $data 签名参数
     * @param string $key MD5秘钥
     * @return string 
     */
    public function getSign($data, $key)
    {
        $sign  = '';

        foreach ($data as $k => $v) {
            if ($k != 'signMsg') {
                $sign .= $k . '=' . $v . '&';
            }
        }
    
        $sign  .= "pkey=" . $key;
        
        //var_dump($sign);

        $strMd5 = md5($sign);

        return $strMd5;
    } 
}