<?php
/**
 *	Author: Kevin
 * 	CreateDate: 2017/09/25 16:05
 *  description: 钱通支付
 */
include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class QianTongPay extends PayInfo
{
    //请求接口Url
    public $server_url = 'https://123.56.119.177:8443/pay/pay.htm'; //测试
    //public $server_url = 'https://www.qtongpay.com/pay/pay.htm';    //部署
    public $retArr = [     //dopay()请求返回格式
            'code' => 0,
            'msg' => '',
            'data' => []
        ];
    public $orderInfo = [   //doPaycallBack()请求返回格式 
        'code' => 0,
        'bank_num' => 205050,  //银行区分号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => '',
        'ret_success' => 'SUCCESS',
        'bank_name' => '钱通支付',
        'serial_no' => ''  //流水号
    ];
    

    public function __construct()
    {
        parent::__construct();
    }

    public function doPay($data)
    {
        //首次生成payment_config表中的config信息
        //$this->setBaseConfig($data['payment_id']);
        
        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 205000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 205001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
            
            return $this->retArr;
        }

        $money = $data['money']*100;
        $str = "";
        $str .= '<?xml version="1.0" encoding="utf-8" standalone="no"?>';
        $str .= '<message ';
        $str .= 'application="' . $config['payType'][$data['pay_type']]['payStr'] . '" ';
        $str .= 'version="1.0.1" ';
        $str .= 'merchantId="' . $config['merchantID'] . '" ';
        $str .= 'merchantOrderId="' . $orderInfo . '" ';
        $str .= 'merchantOrderAmt="' . $money . '" ';
        $str .= 'merchantPayNotifyUrl="' . "https://".$_SERVER['HTTP_HOST']."/?m=api&amp;c=pay&amp;a=doPaycallBack&amp;payment_id=" . $data['payment_id'] . '" ';
        $str .= 'merchantOrderDesc="会员充值" ';
        
        //网银支付调用
        if ($data['pay_type'] == 'wy') {
            $str .= 'merchantFrontEndUrl="'."https://" . $_SERVER['HTTP_HOST']."/?m=web&amp;c=pay&amp;a=payOk&amp;order_sn=" . $orderInfo . '" ';
            $str .= 'accountType="0" ';
            $str .= 'orderTime="' . date("YmdHis") . '" ';
            $str .= 'rptType="1" ';
            $str .= 'payMode="0" ';
            $str .= '/>';

            $strMD5 =  MD5($str,true);
            $strsign =  $this->sign($strMD5,$config['merchantCertPassword'], S_ROOT . $config['merchantCertPath']);
            $base64_src = base64_encode($str);
            $retStr = $base64_src."|".$strsign;

            //type =2返回html跳转页面数
            $retData =  [
                'html' => $this->httpHtml($this->server_url, $retStr), 
                'type' => $config['payType'][$data['pay_type']]['request_type'], 
                'modes' => $data['pay_model']
            ];
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {
            $str .= 'timestamp="'.date("YmdHis").'" ';
            $str .= 'userName="kevin" ';
            $str .= '/>';

            $strMD5 =  MD5($str,true);
            $strsign =  $this->sign($strMD5,$config['merchantCertPassword'], S_ROOT . $config['merchantCertPath']);
            $base64_src = base64_encode($str);
            $retStr = $base64_src."|".$strsign;
        
            //curl接口
            $curlData = $this->httpPostJson($this->server_url, $retStr);
            
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 205002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '钱通支付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }
            
            $tmp = explode("|", $curlData['data']);
            $resp_xml = base64_decode($tmp[0]);
            $resp_sign = $tmp[1];

            $resData = $this->xmlToArray($resp_xml);
            //var_dump($resData);
            if(!isset($resData['attributes']['RESPCODE']) || $resData['attributes']['RESPCODE'] != '000'){
                $this->retArr['code'] = 205003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '钱通支付接口调用失败，' . print_r($resData, true));
            
                return $this->retArr;
            }else {
                //验签
                if(!$this->verity(MD5($resp_xml,true),$resp_sign, S_ROOT . $config['platformCertPath'])) {
                    $this->retArr['code'] = 205004;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '钱通支付返回数据成功，但验证签名失败！' . print_r($curlData['data'], true));
                
                    return $this->retArr;
                }  
                
                if ($resData['attributes']['MERCHANTID'] != $config['merchantID']) {
                    $this->retArr['code'] = 205005;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '钱通支付返回数据验签成功，但商家号错误！' . print_r($curlData['data'], true));
                    
                    return $this->retArr;
                }
                
                payLog('payinfo.log', '华银支付接口调用成功，' . print_r($resData, true));
                $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $resData['attributes']['MERCHANTORDERID'], 'status' => 0));
                if (empty($result)) {
                    $this->retArr['code'] = 205006;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '钱通支付返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($resData, true));
            
                    return $this->retArr;
                }

                D('accountRecharge')->save(['remark' => $resData['attributes']['RESPDESC']], ['order_sn' => $resData['attributes']['MERCHANTORDERID']]);
                //不知道为什么这样做
                session::set('qrcode_url', $resData['attributes']['CODEURL']);
                session::set('pay_url', '');
                //type =1 返回二维码数据
                $retData =  [
                    'type' => $config['payType'][$data['pay_type']]['request_type'],
                    'code_url' => $resData['attributes']['CODEURL'],
                    'pay_url' => '',
                    'order_no' => $resData['attributes']['MERCHANTORDERID'],
                    'modes' => $data['pay_model']
                ];
                $this->retArr['code'] = 0;
                $this->retArr['data']  = $retData;
            
                return $this->retArr;
            }
        }
    }

    //支付回调方法
    public function doPaycallBack($postData)
    {
        $payment_id = $postData['payment_id'];
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 205010;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）钱通支付异步通知：获取数据库配置错误！'  . print_r($postData['data'], true));
            
            return $this->orderInfo;
        }
        
        //处理post回调数据
        $retStr = explode("|", $postData['data']);
        //var_dump($retStr);
        if (empty($retStr) || count($retStr) < 2) {
            $this->orderInfo['code'] = 205011;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）钱通支付异步通知：异步通知数据格式错误！'  . print_r($postData['data'], true));
        
            return $this->orderInfo;
        }

        $resp_xml = base64_decode($retStr[0]);
        $resp_sign = $retStr[1];
        $resData = $this->xmlToArray($resp_xml);
        var_dump($resData);
        
        $resData['attributes']['PAYAMT'] = $resData['attributes']['PAYAMT'] / 100;

        //验签
        if(!$this->verity(MD5($resp_xml,true),$resp_sign, S_ROOT . $config['platformCertPath'])) {
            $this->orderInfo['code'] = 205012;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）钱通支付异步通知,验签失败！'  . print_r($resData, true));

            return $this->orderInfo;
        }

        if (!isset($resData['attributes']['PAYSTATUS']) || $resData['attributes']['PAYSTATUS'] != '01') {
            $this->orderInfo['code'] = 205013;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）钱通支付异步通知：返回信息不是充值成功信息，出现错误！'  . print_r($resData, true));
            
            return $this->orderInfo;
        }
        
        if ($resData['attributes']['MERCHANTID'] != $config['merchantID']) {
            $this->orderInfo['code'] = 205014;
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '钱通支付返回数据验签成功，但商家号错误！' . print_r($resData, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['order_no']  = $resData['attributes']['MERCHANTORDERID'];
        $this->orderInfo['amount']    = $resData['attributes']['PAYAMT'];
        $this->orderInfo['serial_no'] = $resData['attributes']['PAYORDERID'];

        return $this->orderInfo;
    }
    
    /**
     * 签名  生成签名串  基于sha1withRSA
     * @param data：签名前的字符串
     * @param pwd： 证书密码
     * @param merchantCertPath： 用户证书地址
     * @return string 返回：签名字符串
     */
    function sign($data,$pwd,$merchantCertPath) {
        $certs = array();
        openssl_pkcs12_read(file_get_contents($merchantCertPath), $certs,$pwd); //其中password为你的证书密码
        if(!$certs) return ;
        $signature = '';
        openssl_sign($data, $signature, $certs['pkey']);
        return base64_encode($signature);
    }
    
    /**
     * 验证签名：
     * @param data：原文
     * @param signature：签名
     * @param serverCertPath： 证书地址
     * @return bool 返回：签名结果，true为验签成功，false为验签失败
     */
    function verity($data,$signature,$serverCertPath)
    {
        $pubKey = file_get_contents($serverCertPath);
        $res = openssl_get_publickey($pubKey);
        $result = (bool)openssl_verify($data, base64_decode($signature), $res);
        openssl_free_key($res);
        return $result;
    }

    /**
     * 生成签名
     * @param $data arrary 生成签名需要的数据
     * @param $key arrary 用户密锁,键值对(一对)
     * @return string 生成签名
     */
    public function getSigned($data = [], $key = [])
    {
        $string = $this->ToUrlParams($data);
        if (!empty($key)) {
            foreach ($key as $k => $v) {
                if (!is_array($v)) {
                    $string .= '&' . $k . '=' . $v;
                }
            }
        }
    
        $string = md5($string);
        
        return $string;
    }
    
    /**
     * 格式化参数格式化成url参数
     * @param $data arrary 生成url需要的数据
     * @return string 生成url
     */
    public function ToUrlParams($data)
    {
        $buff = "";

        ksort($data);
        foreach ($data as $k => $v)
        {
            if($k != "sign" && $k != "sign_type" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }
    
        $buff = trim($buff, "&");
    
        return $buff;
    }
    
    /**
     * 建立请求form表单类型
     * @param String $post_data请求参数数组
     * @return string 
     */
    function httpHtml($url, $post_data)
    {
        $html =  "<html>";
        $html .= "<head>";
        $html .= "<meta http-equiv='Content-Type' content='text/html'; charset='UTF-8'>";
        $html .= "</head>";
        $html .= "<body onLoad='document.ipspay.submit();'>";
        $html .= "<form id='payFrom' name='ipspay' action='{$url}' method='post'>";
        $html .= "<input type='hidden' name='msg' value='".$post_data."' />";
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';

        return $html;
    }
    
    /*
     * 建立请求curl类型
     * @param array $post_data 请求参数数组
     */
    function httpPostJson($url, $post_data){
        $ch = curl_init ();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS,$post_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        
        $response=curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);

        //$res=simplexml_load_string($response);

        return array('code' => $httpCode, 'data' => $response);
    }
    
    function xmlToArray($xml){
        $p = xml_parser_create();
        xml_parse_into_struct($p, $xml, $vals, $index);
        xml_parser_free($p);
        return $vals[0];
    }

    //支付初始配置
    public function setBaseConfig($payment_id)
    {
         $data1['name'] = '钱通支付';
         $data1['merchantID'] = '1002206';
         $data1['merchantName'] = '新余庆和宝安装维修有限公司';
         $data1['merchantCertPassword'] = '123456';
         $data1['merchantCertPath'] = 'up_files' . DS . 'certs' . DS . 'qiantong' . DS . 'merchant_cert.pfx';  //商户证书，自己生成，并上传商户证书到平台上
         $data1['platformCertPath'] = 'up_files' . DS . 'certs' . DS . 'qiantong' . DS . 'server_cert.cer';    //平台证书，平台后台管理界面下载保存到服务器
         $data1['payType']['wx']['name'] = '微信支付';
         $data1['payType']['wx']['payStr'] = 'WeiXinScanOrder';
         $data1['payType']['wx']['request_type'] = 1;
         $data1['payType']['ali']['name'] = '支付宝支付';
         $data1['payType']['ali']['payStr'] = 'ZFBScanOrder';
         $data1['payType']['ali']['request_type'] = 1;
         $data1['payType']['qq']['name'] = 'QQ钱包支付';
         $data1['payType']['qq']['payStr'] = 'QQScanOrder';
         $data1['payType']['qq']['request_type'] = 1;
         $data1['payType']['wy']['name'] = '网银支付';
         $data1['payType']['wy']['payStr'] = 'SubmitOrder';
         $data1['payType']['wy']['request_type'] = 2;
        
         $serData = serialize($data1);
         D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}