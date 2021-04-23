<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description 汇付宝支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class HuiFuBaoPay extends PayInfo
{
    //请求接口Url
    public $url = 'https://pay.heepay.com/Payment/Index.aspx';    //扫码正式（线上）调用接口
    public $bank_url = 'https://pay.heepay.com/Payment/Index.aspx';  //第三方网银接口
    public $payName = '汇付宝支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 233050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 'error',            //回调处理失败时，返回接口字符串
        'ret_success' => 'ok',  //回调处理成功时，返回接口字符串
        'bank_name' => '汇付宝支付',  //支付方式名称
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
        $port = '';    //PC端扫码
        $qr_code = ''; //二维码信息

        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 233000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 233001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $post_data = array(
            'version'    => 1,
            'pay_type'   => (int)$config['payType'][$data['pay_type']]['payStr'],
            'agent_id'   => $config['merchantID'],  //商户ID
            'agent_bill_id' => $orderInfo,           //商户支付订单号,每次访问生成的唯一订单标识
            'pay_amt'    => number_format($data['money'], 2, '.', ''),  //金额,精确到两位小数(没有两位小数会出错）
            //'notify_url' =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'],
            //'return_url' => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo,
            'notify_url' =>  "https://".$_SERVER['HTTP_HOST']."/api/pay/doPaycallBack/payment_id/" . $data['payment_id'],
            //'return_url' =>  "https://".$_SERVER['HTTP_HOST']."/api/pay/doPaycallBack/payment_id/" . $data['payment_id'],
            'return_url' => "https://".$_SERVER['HTTP_HOST']."/web/pay/payOk/order_sn/" . $orderInfo,
            'user_ip'    => str_replace('.', '_', ip()),   //用户IP,发起支付请求客户端的 ip
            'agent_bill_time' => date('YmdHis'),
            'goods_name' => 'recharge',
            'goods_num'  => 1,
            'remark'     => $data['payment_id'],
            'goods_note' => ''
        );
        
        if ($data['pay_type'] == 'wy') {
            $post_data['version'] = 3;  //是否使用手机触屏版，1=是，PC版请不用传本参数
            $post_data['is_phone'] = 1;  //是否使用手机触屏版，1=是，PC版请不用传本参数
            $post_data['pay_code'] = 0;  //pay_code此参数值为0时，则为跳到汇付宝界面选择银行。为银行编码时直接跳转到银行编码对应的银行。
            $post_data['bank_card_type'] = -1;  //银行类型：未知=-1，储蓄卡=0，信用卡=1。
        } elseif ($data['pay_type'] == 'wx' || $data['pay_type'] == 'ali' || $data['pay_type'] == 'qq' || $data['pay_type'] == 'jd') { //扫码支付
            $port = 'PC'; //如果是PC扫码支付，只需要设置$port = 'PC';
        } else {
            //s=应用场景
            //n=应用名称（应用名称或者WAP网站名称）
            //id=IOS应用唯一标识或者,Android应用在一台设备上的唯一标识在manifest文件里面的声明或者WAP网站的首页
            if ($data['pay_type'] == 'wxwap') {  //微信H5
                //meta_option="{"s":"WAP","n":"WAP网站名","id":"WAP网站的首页URL"}"
                $meta_option = json_encode(['s' => 'WAP', 'n' => $_SERVER['HTTP_HOST'], 'id' => "https://".$_SERVER['HTTP_HOST']]);
                $post_data['meta_option'] = urlencode(base64_encode(iconv("UTF-8","GB2312",$meta_option)));
                $post_data['is_phone'] = 1;  //是否使用手机端微信支付，1=是，微信扫码支付不用传本参数
                $post_data['is_frame'] = 0;  //1（默认值）=使用微信公众号支付，0=使用wap微信支付
            } elseif($data['pay_type'] == 'aliwap') { //支付宝H5支付
                $post_data['is_phone'] = 1;  //是否使用手机触屏版，1=是（不参加签名）
            } elseif($data['pay_type'] == 'qqwap') { //QQH5支付
                $post_data['is_phone'] = 1;  //是否使用手机触屏版，1=是（不参加签名）
            } else { //wap支付，由于需要提供应用信息，和app代码重新，所以放弃使用WAP支付
                //meta_option="{"s":"IOS","n":"应用在App Store中唯一应用名","id":"IOS应用唯一标识"}"
                //meta_option="{"s":"Android","n":"应用在安卓分发市场中的应用名","id":"应用在一台设备上的唯一标识在manifest文件里面的声明"}"
                //$meta_option = json_encode(['s' => 'Android', 'n' => $_SERVER['HTTP_HOST'], 'id' => $_SERVER['HTTP_HOST']]);
                $meta_option = json_encode(['s' => 'Android', 'n' => $_SERVER['HTTP_HOST'], 'id' => $_SERVER['HTTP_HOST']]);
                $post_data['meta_option'] = urlencode(base64_encode(iconv("UTF-8","GB2312",$meta_option)));
                $post_data['is_phone'] = 1;  //是否使用手机端微信支付，1=是，微信扫码支付不用传本参数
                $post_data['is_frame'] = 0;  //1（默认值）=使用微信公众号支付，0=使用wap微信支付
            }
        }

        $post_data['sign'] = $this->getSigned($post_data, $config['merchantKey']);
        //var_dump($post_data);
    
        if ($port == 'PC') {
           $curlData = $this->httpPost($post_data, $this->bank_url);
           //var_dump($curlData);
           //接口调用成功与否
           if ($curlData['code'] != 200) {
               $this->retArr['code'] = 233002;
               $this->retArr['msg']  = '支付二维码生成失败！';
               payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true));
           
               return $this->retArr;
           }
           
           $retData = json_decode($curlData['data'], true);
           if (!isset($retData['code']) || $retData['code'] != '0000' || !isset($retData['message']) || $retData['message'] != 'success') {
               $this->retArr['code'] = 233003;
               $this->retArr['msg']  = '支付二维码生成失败！';
               payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($retData, true));
           
               return $this->retArr;
           }
           
           //qr_code_url:https://pay.swiftpass.cn/pay/qrcode?uuid=weixin%3A%2F%2Fwxpay%2Fbizpayurl%3Fpr%3DUPf5GTi
           //禁止使用其他url链接生成支付二维码，所有获取链接中多得支付二维码信息
           if (!empty($retData['qr_code_url'])) {
               if ($data['pay_type'] == 'wx') {
                   $codeUrl = explode('=', $retData['qr_code_url']);
                   $qr_code = urldecode($codeUrl[1]);
               } else {
                   $qr_code = $retData['qr_code_url'];
               }
           }

           //用于安全验证返回url是否非法
           session::set('qrcode_url', $qr_code);
           session::set('pay_url', $retData['qr_code_url']);
           //type =1 返回二维码数据 2，返回html整页数据
           $ret =  [
               'type'     => 1,
               'code_url' => $qr_code,
               'pay_url'  => $retData['qr_code_url'],
               'order_no' => $orderInfo,
               'modes'    => $data['pay_model']
           ];
           
           $this->retArr['code'] = 0;
           $this->retArr['data'] = $ret;
           
           return $this->retArr;
        } else {
            //type =2返回html跳转页面数
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data, $this->bank_url)
            ];
    
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;
    
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
        $data = [
            'result'        => $_REQUEST['result'], 
            'pay_message'   => $_REQUEST['pay_message'],
            'agent_id'      => $_REQUEST['agent_id'],
            'jnet_bill_no'  => $_REQUEST['jnet_bill_no'],
            'agent_bill_id' => $_REQUEST['agent_bill_id'],
            'pay_type'      => $_REQUEST['pay_type'],
            'pay_amt'       => $_REQUEST['pay_amt'],
            'remark'        => $_REQUEST['remark'],
            'sign'          => $_REQUEST['sign'],
            'fbtn'          => $_REQUEST['fbtn']
        ];

        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);
        
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 233020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if (empty($data['result']) || $data['result'] != 1) {
            $this->orderInfo['code'] = 233021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['agent_id'] = $config['merchantID'];

        $retSign = $this->retSigned($data, $config['merchantKey']);
        if($retSign != $data['sign']){
            $this->orderInfo['code'] = 233022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['agent_bill_id'];
        $this->orderInfo['amount']    = $data['pay_amt'];
        $this->orderInfo['serial_no'] = $data['jnet_bill_no'];

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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

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
     * 上传参数签名
     * @param array $data 签名参数
     * @param string $key MD5秘钥
     * @return string
     */
    public function getSigned($data, $key)
    {
        $sign  = '';
        $sign_str = '';

        $sign_str  = $sign_str . 'version=' . $data['version'];
        $sign_str  = $sign_str . '&agent_id=' . $data['agent_id'];
        $sign_str  = $sign_str . '&agent_bill_id=' . $data['agent_bill_id'];
        $sign_str  = $sign_str . '&agent_bill_time=' . $data['agent_bill_time'];
        $sign_str  = $sign_str . '&pay_type=' . $data['pay_type'];
        $sign_str  = $sign_str . '&pay_amt=' . $data['pay_amt'];
        $sign_str  = $sign_str . '&notify_url=' . $data['notify_url'];
        $sign_str  = $sign_str . '&return_url=' . $data['return_url'];
        $sign_str  = $sign_str . '&user_ip=' . $data['user_ip'];
        if (!empty($data['bank_card_type'])) {
            $sign_str  = $sign_str . '&bank_card_type=' . $data['bank_card_type'];
        }
        $sign_str  = $sign_str . '&key=' . $key;
        
        //var_dump($sign_str);
        
        $sign = strtolower(md5($sign_str));
    
        return $sign;
    }
    
    /**
     * 回调支付通知参数签名
     * @param array $data 签名参数
     * @param string $key MD5秘钥
     * @return string
     */
    public function retSigned($data, $key)
    {
        $sign  = '';
        $signStr = '';

    	$signStr  = $signStr . 'result=' . $data['result'];
    	$signStr  = $signStr . '&agent_id=' . $data['agent_id'];
    	$signStr  = $signStr . '&jnet_bill_no=' . $data['jnet_bill_no'];
    	$signStr  = $signStr . '&agent_bill_id=' . $data['agent_bill_id'];
    	$signStr  = $signStr . '&pay_type=' . $data['pay_type'];
    	$signStr  = $signStr . '&pay_amt=' . $data['pay_amt'];
    	$signStr  = $signStr . '&remark=' . $data['remark'];
    	$signStr  = $signStr . '&key=' . $key;
    
        $sign = strtolower(md5($signStr));
    
        return $sign;
    }
}