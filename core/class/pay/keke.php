<?php
/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/11/14 15:30
 * @description 可可支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class KeKePay extends PayInfo
{
    //请求接口Url
    public $url = 'http://gateway.kekepay.com/cnpPay/initPay';              //扫码正式（线上）调用接口
    public $bank_wurl = 'http://gateway.kekepay.com/b2cPay/initPay';        //第三方网银接口
    public $bank_url = 'http://gateway.kekepay.com/quickGateWayPay/apiPay'; //第三方快捷支付接口
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 214050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '可可支付',   //支付方式名称
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
            $this->retArr['code'] = 214000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '可可支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 214001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '可可支付银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            //notyUrl,必须在最前面，防止“&”转义参数名notifyUrl
            'notifyUrl'   => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'] . '&flag=1AA1', //flag参数辨别get返回参数时添加的"?"，处理使用flag标签
            'payKey'    => $config['merchantPayKey'],
            //'productType' => $config['payType'][$data['pay_type']]['payStr'],
            'orderTime'   => date('YmdHis'),
            'orderIp'     => ip(),
            'outTradeNo'  => $orderInfo,
            'orderPrice'  => number_format($data['money'], 2, '.', ''),
            'productName' => 'recharge',
            'returnUrl'   => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo . '&flag=1AA1',  // flag参数辨别get返回参数时添加的"?"，处理使用flag标签
            );

        if ($data['pay_type'] == 'wy' || $data['pay_type'] == 'wykj') {  //网银支付或快捷支付
            $bank_url = '';
            $post_data['payBankAccountNo'] = '';
            $post_data['productType'] = $config['payType'][$data['pay_type']]['payStr'];

            if ($data['pay_type'] == 'wy') {
                $bank_url = $this->bank_wurl;
                $post_data['bankCode'] = $data['bank_code'];
                $post_data['bankAccountType'] = 'PRIVATE_DEBIT_ACCOUNT';  //固定为私借记卡，私贷记卡"PRIVATE_CREDIT_ACCOUNT"不考虑(简化设置)
            } else {
                $bank_url = $this->bank_url;
            }

            $post_data['sign'] = getSigned($post_data, ['paySecret' => $config['merchantKey']], [], '&', 1, 1);
            
            //$curlData = $this->httpPostJson($post_data, $this->bank_url);

            //type =2返回html跳转页面数
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data, $bank_url)
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        }else {
            //非网银支付，微信、支付宝、QQ钱包支付
            $post_data['productType'] = $config['payType'][$data['pay_type']]['payStr'];
            $post_data['sign'] = getSigned($post_data, ['paySecret' => $config['merchantKey']], [], '&', 1, 1);

            $curlData = $this->httpPost($post_data, $this->url);
            //var_dump($curlData);

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 214002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '可可付支付接口调用失败，' . print_r($curlData, true));

                return $this->retArr;
            }
            
            $retData = json_decode($curlData['data'], true);
            if (!isset($retData['resultCode']) || $retData['resultCode'] != '0000') {
                $this->retArr['code'] = 214003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '可可支付接口调用失败，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            //验签
            $retSign = getSigned($retData, ['paySecret' => $config['merchantKey']], ['sign'], '&', 1, 1);
            if($retSign != $retData['sign']){
                $this->retArr['code'] = 214004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '可可支付返回数据成功，但验证签名失败！' . print_r($retData, true));
            
                return $this->retArr;
            }
            
            $retOderNo = isset($retData['orderNo']) ? $retData['orderNo'] : $orderInfo;
            $retOderPayNo = isset($retData['payOrderNo']) ? $retData['payOrderNo'] : 'kekepay' . date('YmdHis');
            $retOderPayQrcodrUrl = isset($retData['payMessage']) ? $retData['payMessage'] : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty($retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 214005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '可可支付返回数据错误，' . print_r($retData, true));

                return $this->retArr;
            }
    
            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 214006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '可可支付返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
                
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
        // 处理post回调数据
        $getData = $_GET;
        
        //处理get传输数据flag参数时，在原有带参数的数据上加的"?"
        $flag = str_replace('1AA1?', '', $getData['flag']);
        $flagData = explode('=', $flag);
        $getData[$flagData[0]] = $flagData[1];

        $data = array();
        $data['payKey']       = trim($getData['payKey']);      //商户支付Key
        $data['orderPrice']   = trim($getData['orderPrice']);  //订单金额，单位：元保留小数点后两位
        $data['outTradeNo']   = trim($getData['outTradeNo']);  //商户订单号
        $data['productType']  = trim($getData['productType']); //产品类型
        $data['productName']  = trim($getData['productName']); //产品名称
        $data['orderTime']    = trim($getData['orderTime']);   //下单时间，格式yyyyMMddHHmmss
        $data['tradeStatus']  = trim($getData['tradeStatus']); //订单状态
        $data['successTime']  = trim($getData['successTime']); //成功时间，格式yyyyMMddHHmmss
        $data['trxNo']        = trim($getData['trxNo']);       //交易流水号
        $data['sign']         = trim($getData['sign']);        //交易流水号
        if(isset($getData['remark']) && !empty($getData['remark'])){
            $data['remark'] = trim($getData['remark']);        //订单备注
        }

        //支付类型ID
        $payment_id = $postData['payment_id'];

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 214020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if ($data['tradeStatus'] != 'SUCCESS') {
            $this->orderInfo['code'] = 214021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }
        
        //防止错传
        $data['payKey'] = $config['merchantPayKey'];

        //验签
        $retSign = getSigned($data, ['paySecret' => $config['merchantKey']], ['sign'], '&', 1, 1);
        if($retSign != $data['sign']){
            $this->orderInfo['code'] = 214022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['outTradeNo'];
        $this->orderInfo['amount']    = $data['orderPrice'];
        $this->orderInfo['serial_no'] = $data['trxNo'];
        
        return $this->orderInfo;
    }
    
    /**
     * 对数组排序
     * @param $para 排序前的数组
     * return 排序后的数组
     */
    public function argSort($para) {
        ksort($para);
        reset($para);
        return $para;
    }
    
    /**
     * 组装回调SIGN验证字符串
     * */
    public function resSign ($data, $paySecret) {
    
        $data = $this->argSort($data);
    
        $sign  = '';
        foreach ($data as $key => $val)
        {
            $sign .= "$key=$val&";
        }
    
        $sign  .= "paySecret=". $paySecret;

        return strtoupper(md5($sign));
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
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array('code' => $httpCode, 'data' => $response);
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
     * 支付初始数据配置数据库
     * @param int $payment_id 支付类型ID
     */
    public function setBaseConfig($payment_id)
    {
        $data['name'] = '可可支付';
        $data['merchantID'] = '18659319253';  //用户账号名称（程序中无实际用处）
        $data['merchantPayKey'] = 'f6d8e21fa0aa48b3988bb2b5fdd35e4c';  //支付Key（相当于用户merchantID）
        $data['merchantKey'] = 'bcc7d0657e944cdc841bbbf0e4984442';
        $data['payType']['wx']['name'] = '微信支付';
        $data['payType']['wx']['payStr'] = '10000103';
        $data['payType']['wx']['request_type'] = 1;
        $data['payType']['ali']['name'] = '支付宝支付';
        $data['payType']['ali']['payStr'] = '20000303';
        $data['payType']['ali']['request_type'] = 1;
        $data['payType']['qq']['name'] = 'QQ钱包支付';
        $data['payType']['qq']['payStr'] = '70000203';
        $data['payType']['qq']['request_type'] = 1;
        $data['payType']['wy']['name'] = '快捷支付';
        $data['payType']['wy']['payStr'] = '40000103';    //手机web端快捷支付
        $data['payType']['wy']['request_type'] = 2;
        $data['payType']['wy']['bank_code'] = '';  //银行简码对应字段
        $data['payType']['wy']['bank_id'] = '';
        /*
        $data['payType']['wy']['name'] = '网银支付';
        $data['payType']['wy']['payStr'] = '50000103';
        $data['payType']['wy']['request_type'] = 2;
        $data['payType']['wy']['bank_code'] = 'keke_bank_code';  //银行简码对应字段
        $data['payType']['wy']['bank_id'] = '1,2,7';
        */
        
        $serData = serialize($data);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}