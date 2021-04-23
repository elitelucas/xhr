<?php

/**
 *	Author: Kevin
 * 	CreateDate: 2017/10/18 14:25
 *  description: 畅汇支付
 */
include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class ChangHuiPay extends PayInfo
{
    //请求接口Url
    public $url = 'https://changcon.92up.cn/controller.action';  //部署
    public $retArr = [
        'code' => 1,
        'msg' => '',
        'data' => []
    ];
    public $orderInfo = [       //异步验签结果返回格式
        'code' => 1,
        'bank_num' => 210050,  //银行站内编号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => 'ERROR',
        'ret_success' => 'SUCCESS',
        'bank_name' => '畅汇支付',
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
            $this->retArr['code'] = 210000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 210001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'p0_Cmd'    => 'Buy',                  //固定值
            'p1_MerId'  => $config['merchantID'],  //商户ID
            'p2_Order'  => $orderInfo,             //商户支付订单号,每次访问生成的唯一订单标识
            'p3_Cur'    => 'CNY',                  //固定值,交易币种
            'p4_Amt'    => number_format($data['money'], 2, '.', ''),  //单位:元，精确到分
            'p5_Pid'    => 'recharge',             //商品名称
            'p6_Pcat'   => '',
            'p7_Pdesc'   => '',
            //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
            'p8_Url' =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'p9_MP'   => '',
            'pa_FrpId' => $config['payType'][$data['pay_type']]['payStr'],  //此字段区分支付方式，含微信扫码支付，网银支付，支付宝扫码支付，快捷支付等
            'p9_MP'   => '',
            'pb_CusUserId'   => '',
            'pb_OpenId'   => '',
            'pb_AuthCode'   => '',
            'p4_sonCustNumber'   => '',
            'pc_CardNo'   => '',
            'pc_ExpireYear'   => '',
            'pc_ExpireMonth'   => '',
            'pc_CVV'   => '',
            'pd_Name'   => '',
            'pe_CredType'   => '',
            'pe_IdNum'   => '',
            'pf_PhoneNum'   => '',
            'pf_SmsTrxId'   => '',
            'pf_kaptcha'   => '',
            'pg_BankCode'  => '',
            'ph_Ip'    => ip(),
            'pi_Url'   => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo 
        );
        
        //网银支付调用
        if ($data['pay_type'] == 'wy') {
            $post_data['pg_BankCode'] = $data['bank_code'];
            $retStr = $this->toUrlParamsStr($post_data);
            $post_data['hmac'] = $this->HmacMd5($retStr, $config['merchantKey']);
            $curlData = $this->httpPost($this->url, $post_data);
            
            if (json_decode($curlData['data'], true)) {
                $this->retArr['code'] = 210004;
                $this->retArr['msg']  = '暂不支持该网银支付【请选择其他银行】！';
                
                return $this->retArr;
            }

            //type =2返回html跳转页面数
            $retData =  [
                'type' => $config['payType'][$data['pay_type']]['request_type'],
                'html' => $curlData['data'], 
                'modes' => $data['pay_model']
            ];
        
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;
        
            return $this->retArr;
        } else {

            $retStr = $this->toUrlParamsStr($post_data);
            $post_data['hmac'] = $this->HmacMd5($retStr, $config['merchantKey']);
            
            //curl接口
            $curlData = $this->httpPost($this->url, $post_data);
            var_dump($curlData);
    
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 210005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '畅汇支付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }
            
            $retData = json_decode($curlData['data'],true);
            if (!isset($retData['r1_Code']) || $retData['r1_Code'] != 1) {
                $this->retArr['code'] = 210006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '畅汇支付接口调用返回信息成功，但返回结果中二维码信息获取失败' . print_r($retData, true));
                
                return $this->retArr;
            }

            //验签
            $retData['p1_MerId'] = $config['merchantID'];
            $hmac = $retData['hmac'];
            unset($retData['hmac']);
            $hmacStr = $this->toUrlParamsStr($retData);
            $retSign = $this->HmacMd5($hmacStr, $config['merchantKey']);
            if($retSign != $hmac){
                $this->retArr['code'] = 210007;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '畅汇支付返回数据成功，但验证签名失败！' . print_r($retData, true));
            
                return $this->retArr;
            }
            
            $retOderPayNo = isset($retData['r2_TrxId']) ? $retData['r2_TrxId'] : '';
            $retOderPayQrcodrUrl = isset($retData['r3_PayInfo']) ? $retData['r3_PayInfo'] : '';
            if (empty($retOderPayNo) || empty(retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 210008;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '畅汇支付返回数据错误，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $orderInfo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 210009;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '畅汇支付返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            D('accountRecharge')->save(['remark' => $retOderPayNo], ['order_sn' => $orderInfo]);
    
            //用于安全验证返回url是否非法
            session::set('qrcode_url', $retOderPayQrcodrUrl);
            session::set('pay_url', '');
            //type =1 返回二维码数据 2，返回html整页数据
            $ret =  ['type' => $config['payType'][$data['pay_type']]['request_type'], 'code_url' => $retOderPayQrcodrUrl, 'pay_url' => '', 'order_no' => $orderInfo, 'modes' => $data['pay_model']];
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $ret;
    
            return $this->retArr;
        }
    }

    /**
     * 支付回调方法
     * {@inheritDoc}
     * @see PayInfo::doPaycallBack()
     */
    public function doPaycallBack($postData)
    {
        //处理post回调数据
        $data = array(
            'p1_MerId'       => $_REQUEST['p1_MerId'],
            'r0_Cmd'		 => $_REQUEST['r0_Cmd'],
            'r1_Code'		 => $_REQUEST['r1_Code'],
            'r2_TrxId'		 => $_REQUEST['r2_TrxId'],
            'r3_Amt'		 => $_REQUEST['r3_Amt'],
            'r4_Cur'		 => $_REQUEST['r4_Cur'],
            'r5_Pid'		 => $_REQUEST['r5_Pid'],
            'r6_Order'		 => $_REQUEST['r6_Order'],
            'r8_MP'			 => $_REQUEST['r8_MP'],
            'r9_BType'		 => $_REQUEST['r9_BType'],
            'ro_BankOrderId' => $_REQUEST['ro_BankOrderId'],
            'rp_PayDate'	 => $_REQUEST['rp_PayDate'],
            'hmac'			 => $_REQUEST['hmac']
        );
        
        //$data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];
        
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 210010;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）畅汇支付异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if (!isset($data['r1_Code']) || $data['r1_Code'] != 1 || !isset($data['r9_BType']) ||  $data['r9_BType'] != 2) {
            $this->orderInfo['code'] = 210011;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）畅汇支付异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        //验签
        $data['p1_MerId']    = $config['merchantID'];
        $hmac = $data['hmac'];
        unset($data['hmac']);
        $hmacStr = $this->toUrlParamsStr($data);
        $retSign = $this->HmacMd5($hmacStr, $config['merchantKey']);
        if($retSign != $hmac){
            $this->orderInfo['code'] = 210012;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . '畅汇支付返回数据成功，但验证签名失败！' . print_r($data, true));
        
            return $this->orderInfo;
        }
            
        $this->orderInfo['code']  = 0;
        $this->orderInfo['order_no']  = $data['r6_Order'];
        $this->orderInfo['amount']    = $data['r3_Amt'];
        $this->orderInfo['serial_no'] = $data['r2_TrxId'];

        return $this->orderInfo;
    }

    /**
     * 格式化参数格式化成url参数
     * @param $data array 生成url需要的一维键值对数据
     * @return string 生成url
     */
    function toUrlParamsStr($data)
    {
        $buff = "";

        foreach ($data as $k => $v)
        {
            $buff .= $v;
        }
    
        return $buff;
    }
    
    /**
     * 字符串加密
     * @param string $data
     * @param string $key
     * @return string
     */
    function HmacMd5($data,$key)
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
        
    /**
     * post请求数据
     * @param 字符串  $url  post请求url
     * @param array $postData post请求数据
     * @return mixed[]
     */
    function httpPost($url, $postData)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Curl_HTTP_Client v2.0');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return array('code' => $httpCode, 'data' => $response);
    }

    /**
     * 支付初始配置
     * @param int $payment_id 支付类型ID
     */
    public function setBaseConfig($payment_id)
    {
        $data1['name'] = '畅汇支付';
        $data1['merchantID'] = 'CHANG1507808402037';
        $data1['merchantKey'] = 'qikdw1c5ym7ilur6g64im4khe8zz0wnzexf0pjuoxamms2o5iqetf4l50r2y';
        $data1['payType']['wx']['name'] = '微信支付';
        $data1['payType']['wx']['payStr'] = 'WEIXIN';
        $data1['payType']['wx']['request_type'] = 1;
        $data1['payType']['ali']['name'] = '支付宝支付';
        $data1['payType']['ali']['payStr'] = 'ALIPAY';
        $data1['payType']['ali']['request_type'] = 1;
        $data1['payType']['qq']['name'] = 'QQ钱包支付';
        $data1['payType']['qq']['payStr'] = 'QQ';
        $data1['payType']['qq']['request_type'] = 1;
        $data1['payType']['wy']['name'] = '网银支付';
        $data1['payType']['wy']['payStr'] = 'OnlinePay';
        $data1['payType']['wy']['request_type'] = 2;
        $data1['payType']['wy']['bank_id'] = '1,2,3,6,7,8,9,10,11,12,13,14,15,16,17,26,27,28,29,30,31,32,33';
        
        $serData = serialize($data1);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}