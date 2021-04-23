<?php

/**
 *	Author: Kevin
 * 	CreateDate: 2017/10/18 14:25
 *  description: 易宝支付
 */
include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class YiBaoPay extends PayInfo
{
    //请求接口Url
    public $url = 'https://www.yeepay.com/app-merchant-proxy/node';  //部署
    public $payName = '易宝支付';
    public $retArr = [
        'code' => 1,
        'msg' => '',
        'data' => []
    ];
    public $orderInfo = [       //异步验签结果返回格式
        'code' => 1,
        'bank_num' => 220050,   //银行站内编号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => '',
        'ret_success' => 'SUCCESS',
        'bank_name' => '易宝支付',
        'serial_no' => ''  //流水号
    ];

    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 获取支付信息
     * {@inheritDoc}
     * @see PayInfo::doPay()
     */
    public function doPay($data)
    {
        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 220000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 22001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'p0_Cmd'    => 'Buy',                  //固定值
            'p1_MerId'  => $config['merchantID'],  //商户ID
            'p2_Order'  => $orderInfo,             //商户支付订单号,每次访问生成的唯一订单标识
            'p3_Amt'    => number_format($data['money'], 2, '.', ''),  //单位:元，精确到分
            'p4_Cur'    => 'CNY',                  //固定值,交易币种
            'p5_Pid'    => 'recharge',             //商品名称
            'p6_Pcat'   => '',
            'p7_Pdesc'   => '',
            'p8_Url' =>  "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'p9_SAF'   => 0,
            'pa_MP'   => '',
            'pb_ServerNotifyUrl' => "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'pd_FrpId' => '',
            'pm_Period' => 7,
            'pn_Unit'  => 'day',
            'pr_NeedResponse' => 1,
            'pt_UserName' => '',
            'pt_PostalCode' => '',
            'pt_Address' => '',
            'pt_TeleNo' => '',
            'pt_Mobile' => '',
            'pt_Email' => '',
            'pt_LeaveMessage' => ''
        );
        
        $str  = '';
        foreach ($post_data as $k => $v) {
            $str .= $v;
        }
        
        $post_data['hmac'] = $this->hmacMd5($str, $config['merchantKey']);
        
        //type =2返回html跳转页面数
        $retData =  [
            'type' => $config['payType'][$data['pay_type']]['request_type'],
            'html' => $this->httpHtml($post_data, $this->url), 
            'modes' => $data['pay_model']
        ];
    
        $this->retArr['code'] = 0;
        $this->retArr['data']  = $retData;
    
        return $this->retArr;
    }

    /**
     * 支付回调方法
     * {@inheritDoc}
     * @see PayInfo::doPaycallBack()
     */
    public function doPaycallBack($postData)
    {
        $payment_id = $postData['payment_id'];  //处理post回调数据
        $data = array(
            'p1_MerId'       => $_REQUEST['p1_MerId'],
            'r0_Cmd'		 => $_REQUEST['r0_Cmd'],
            'r1_Code'		 => $_REQUEST['r1_Code'],
            'r2_TrxId'		 => $_REQUEST['r2_TrxId'],
            'r3_Amt'		 => $_REQUEST['r3_Amt'],
            'r4_Cur'		 => $_REQUEST['r4_Cur'],
            'r5_Pid'		 => $_REQUEST['r5_Pid'],
            'r6_Order'		 => $_REQUEST['r6_Order'],
            'r7_Uid'		 => $_REQUEST['r7_Uid'],
            'r8_MP'			 => $_REQUEST['r8_MP'],
            'r9_BType'		 => $_REQUEST['r9_BType']
        );
        $hmac      = $_REQUEST['hmac'];
        $hmac_safe = $_REQUEST['hmac_safe'];

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 220020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }
        
        if ($data['r1_Code'] != 1 || empty($data['r9_BType'])) {
            $this->orderInfo['code'] = 220021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '支付异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        //防止错调
        $data['p1_MerId'] = $config['merchantID'];
        
        $str  = '';
        foreach ($data as $k => $v) {
            $str .= $v;
        }

        //签名验证
        $rethmac = $this->hmacMd5($str, $config['merchantKey']);
        if($rethmac != $hmac){
            $this->orderInfo['code'] = 220022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '返回数据成功，但验证签名失败！' . print_r($data, true));
        
            return $this->orderInfo;
        }

        //安全签名数据验证 
        $rethmac_safe = $this->hamcSafe($data, $config['merchantKey']);
        if($rethmac_safe != $hmac_safe){
            $this->orderInfo['code'] = 220023;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '返回数据成功，但安全签名验证签名失败！' . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['r9_BType'] != 2) {
            header("Location: https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $data['r6_Order']); //支付返回页面跳转

            exit;
        }

        $this->orderInfo['code']  = 0;
        $this->orderInfo['order_no']  = $data['r6_Order'];
        $this->orderInfo['amount']    = $data['r3_Amt'];
        $this->orderInfo['serial_no'] = $data['r2_TrxId'];

        return $this->orderInfo;
    }
    
    /**
     * 字符串加密
     * @param string $data
     * @param string $key
     * @return string
     */
    public function hmacMd5($data,$key)
    {
        // RFC 2104 HMAC implementation for php.
        // Creates an md5 HMAC.
        // Eliminates the need to install mhash to compute a HMAC
        // Hacked by Lance Rushing(NOTE: Hacked means written)
    
        //需要配置环境支持iconv，否则中文参数不能正常处理
        //$key = iconv("GB2312","UTF-8",$key);
        //$data = iconv("GB2312","UTF-8",$data);

        $b = 64; // byte length for md5
        if (strlen($key) > $b) {
            $key = pack("H*",md5($key));
        }
        $key = str_pad($key, $b, chr(0x00));
        $ipad = str_pad('', $b, chr(0x36));
        $opad = str_pad('', $b, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;
    
        return md5($k_opad . pack("H*",md5($k_ipad . $data)));
    }
    
    //生成本地的安全签名数据
    /**
     * 生成本地的安全签名数据
     * @param array $data
     * @param string $key
     * @return string
     */
    public function hamcSafe($data, $key)
    {
        $text = "";
    
        foreach ($data as $k => $v) {
            if($v != null && $v != '') {
                $text .=  $v. "#";
            }
        }

        $text1= rtrim(trim($text), '#');

        $sgin = $this->HmacMd5($text1, $key);

        return $sgin;
    }
    
    /**
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    public function httpHtml($post_data, $url)
    {
        $html =  "<html>";
        $html .= "<head>";
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= "<title>会员充值</title>";
        $html .= "</head>";
        $html .= '<body onLoad="document.payForms.submit();">';
        $html .= '<form id="payFrom" name="payForms" method="post" action="' . $url . '">';
        foreach ($post_data as $key => $value) {
            $html .= '<input type="hidden" name="' . $key . '"  value="' . $value.'"/>';
        }

        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';
        return $html;
    }
}
