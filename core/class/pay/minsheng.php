<?php
/**
 *	Author: Kevin
 * 	CreateDate: 2017/09/14 15:05
 *  description: 民生支付
 */
include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class MinShengPay extends PayInfo
{
    var $server_url = 'http://pay.zhongweipay.net/api/core.php';
    var $version = 'v1.0';
    var $method = '00000003';
    public $retArr = [
            'code' => 0,
            'msg' => '',
            'data' => []
        ];
    public $orderInfo = [       //异步验签结果返回格式
        'code' => 0,
        'bank_num' => 203050,  //银行区分号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => '',
        'ret_success' => 'success',
        'bank_name' => '华银支付',
        'serial_no' => ''  //流水号
    ];

    public function __construct()
    {
        parent::__construct();
    }

    //生成支付
    function doPay($data)
    {
        //首次生成payment_config表中的config信息
        //$this->setBaseConfig($data['payment_id']);

        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 203000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
        
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 203001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
        
            return $this->retArr;
        }

        $post_data['account_no'] = $config['merchantID'];
        $post_data['method']     = $this->method;
        $post_data['nonce_str']  = $this->getRandomStr(32);
        $post_data['version']    = $this->version;
        $post_data['pay_tool']   = $config['payType'][$data['pay_type']]['payStr']; //支付方式：微信，支付宝支付，QQ钱包，网银
        $post_data['productId']  = $config['payType'][$data['pay_type']]['productId'];
        $post_data['order_sn']   = $orderInfo;
        $post_data['money']      = number_format($data['money'], 2, '.', '');
        $post_data['body']       = '会员充值';
        $post_data['ex_field']   = $config['payType'][$data['pay_type']]['payStr'];
        $post_data['notify']     = "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'];

        if($data['pay_type'] == 'wy'){
            $post_data['return_url'] = "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo;
            $post_data['bankCode'] = "";//文档中没有写填入什么参数
        }

        $post_data['signature'] = $this->getSigned($post_data,['key' => $config['merchantKey']]);
        var_dump($post_data);
        $curlData = $this->httpPostJson($this->server_url,$post_data);
        var_dump($curlData);
        return $this->retArr;
        
        if($data['pay_type'] == 'wy'){
            $params = $this->buildRequest($post_data);
            $params = base64_encode($params);
            var_dump($params);
            $url = '';
            $returnData = [
                'url'         => $url,
                'requestType' => 'get',
                'order_no'    => $orderInfo?:''
            ];
        }else {
            $curlData = $this->httpPostJson($this->server_url,$post_data);
            var_dump($params);
            return $this->retArr;
            
            if (empty($curlData['data']['resp_code']) || $curlData['data']['resp_code'] != 'SUCCESS') {
                $this->retArr['code'] = 201004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '民生支付接口调用失败，' . print_r($curlData['data'], true));
            
                return $this->retArr;
            }
            /*
            if($res['res_code'] != "P000"){
                throw new \Exception(ErrorCode::PAY_FIALS_ERROR_MSG, ErrorCode::PAY_FIALS_ERROR);
            }

            $returnData = [
                'url'         => '',
                'requestType' => $res['codeUrl']?:'',
                'order_no'    => $res['order_sn']?:''
            ];
            */
        }


        return $returnData;
    }

    public function doPaycallBack($postData)
    {
        $remark = '';
        //处理post回调数据
        $data = json_decode($postData['data'],true);
        //parse_str($postData['data'], $data);
        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);
        $payment_id = $postData['payment_id'];
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 203008;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）民生支付异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        $signature = $_REQUEST['signature'];
        $retSign = $this->getSigned($data,['key' => $config['merchantKey']]);
        //验签
        $sign = $data['signature'];
        unset($data['signature']);
        $retSign = $this->getSigned($data,['key' => $config['merchantKey']]);
        if($retSign != $sign){
            $this->orderInfo['code'] = 203009;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）民生支付异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        if (!isset($data['status']) || $data['status'] != 1) {
            $this->orderInfo['code'] = 203010;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）民生支付异步通知：交易失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        $this->orderInfo['order_no']  = $data['order_no'];
        $this->orderInfo['amount']    = $data['order_amount'];
        $this->orderInfo['serial_no'] = $data['bank_seq_no'];
        
        return $this->orderInfo;
    }

    //生成随机字符串
    function create_nonce_str($pw_length=24)
    {
        $randpwd = "";
        for ($i = 0; $i < $pw_length; $i++)
        {
            $randpwd .= chr(mt_rand(33, 126));
        }
        return $randpwd;
    }
    
    /**
     * 生成签名
     * @param $data arrary 生成签名需要的数据
     * @param $key arrary 用户密锁,键值对,这里只需要键值
     * @return string 生成签名
     */
    public function getSigned($data = [], $key = [])
    {
        $string = $this->ToUrlParams($data);
        if (!empty($key)) {
            foreach ($key as $k => $v) {
                if (!is_array($v)) {
                    $string .= $v;
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
    
    

    //组装签名字符串
    function createLinkStr($data)
    {
        ksort($data);
        $str = '';
        $i = 0;
        foreach ($data as $key => $val) {
            if (false === $this->checkEmpty($val) && "@" != substr($val, 0, 1)) {
                if ($i == 0) {
                    $str .= $key . '=' . $val;
                } else {
                    $str .= '&' . $key . '=' . $val;
                }
                $i++;
            }
        }
        unset($key, $value);
        return $str;
    }

    /*
     * 生成请求签名结果
     * @param $para_sort 已排序要签名的数组
     * @param $key 秘钥
     * return 签名结果字符串
     */
    function sign($data,$key){
       // writeLog(var_export($this->createLinkStr($data).$key,true));
        return MD5($this->createLinkStr($data).$key);
    }

    /*
    * 建立请求form表单类型
    * @param $para_temp 请求参数数组
    */
    function buildRequest($data)
    {
        $html =  "<html>";
        $html .= "<head>";
        $html .= "<meta http-equiv='Content-Type' content='text/html'; charset='UTF-8'>";
        $html .= "</head>";
        $html .= "<body onLoad='document.minForm.submit();'>";
        $html .= "<form name='payForm' name='minForm' action='{$this->server_url}' method='post'>";
        $html .= "<input type='hidden' name='account_no' value='".$data['account_no']."' />";
        $html .= "<input type='hidden' name='method' value='".$data['method']."' />";
        $html .= "<input type='hidden' name='nonce_str' value='".$data['nonce_str']."' />";
        $html .= "<input type='hidden' name='version' value='".$data['version']."' />";
        $html .= "<input type='hidden' name='pay_tool' value='".$data['pay_tool']."' />";
        $html .= "<input type='hidden' name='order_sn' value='".$data['order_sn']."' />";
        $html .= "<input type='hidden' name='money'	value='".$data['money']."' />";
        $html .= "<input type='hidden' name='body' value='".$data['body']."' />";
        $html .= "<input type='hidden' name='notify' value='".$data['notify']."' />";
        $html .= "<input type='hidden' name='productId' value='".$data['productId']."' />";
        $html .= "<input type='hidden' name='ex_field' value='".$data['ex_field']."' />";
        $html .= "<input type='hidden' name='return_url' value='".$data['return_url']."' />";
        $html .= "<input type='hidden' name='signature' value='".$data['signature']."' />";
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';
        return $html;
    }

    /*
     * 建立请求curl类型
     * @param $paradata 请求参数数组
     */
    function httpPostJson($url, $postData)
    {
        $ch = curl_init ();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS,$postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $result = curl_exec($ch);
        $ret    = json_decode(curl_exec($ch),true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);

        return array('code' => $httpCode, 'data' => $ret);
    }

    /*
     * 验证参数是否为空
     * @param $value 待验证的值
     */
    function checkEmpty($value){
        if (!isset($value)) {
            return true;
        }
        if ($value === NUll) {
            return true;
        }
        if (trim($value) == '') {
            return true;
        }
        return false;
    }
    
    //支付初始配置
    public function setBaseConfig($payment_id)
    {
        $data1['name'] = '民生支付';
        $data1['scanType'] = 0;
        $data1['merchantID'] = '901503286609377514';
        $data1['merchantKey'] = 'GvX648Hr0SjeYO0jWwu3m7';

        $data1['payType']['wx']['name'] = '微信支付';
        $data1['payType']['wx']['payStr'] = 'wxzfxf';
        $data1['payType']['wx']['productId'] = '02';
        $data1['payType']['wx']['request_type'] = 1;
        $data1['payType']['ali']['name'] = '支付宝支付';
        $data1['payType']['ali']['payStr'] = 'alizfxf';
        $data1['payType']['ali']['productId'] = '07';
        $data1['payType']['ali']['request_type'] = 1;
        $data1['payType']['qq']['name'] = 'QQ钱包支付';
        $data1['payType']['qq']['payStr'] = 'qqsmxf';
        $data1['payType']['qq']['productId'] = '06';
        $data1['payType']['qq']['request_type'] = 1;
        $data1['payType']['wy']['name'] = '民生网银支付';
        $data1['payType']['wy']['payStr'] = 'wgzfxf';
        $data1['payType']['wy']['productId'] = '01';
        $data1['payType']['wy']['request_type'] = 1;
    
        $serData = serialize($data1);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}