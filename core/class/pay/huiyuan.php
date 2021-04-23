<?php
/**
 *	Author: Kevin
 * 	CreateDate: 2017/09/14 15:05
 *  description: 汇元支付
 */
include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class HuiYuanPay extends PayInfo
{
    //请求接口Url
    //public $url = 'http://tseanet.xicp.net/api/pay/onlinepay.json';  //测试
    public $url = 'http://pay.drippayment.com/pay/onlinepay.json';
    public $bankUrl = 'http://pay.drippayment.com/pay/netbank.html';   //web网银地址请求
    //public $url = '';  //部署
    public $retArr = [
            'code' => 0,
            'msg' => '',
            'data' => []
        ];
    public $orderInfo = [       //异步验签结果返回格式
        'code' => 0,
        'bank_num' => 206050,  //银行区分号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => '',
        'ret_success' => 'SUCCESS',
        'bank_name' => '汇元支付',
        'serial_no' => ''  //流水号
    ];
   

    public function __construct()
    {
        parent::__construct();
    }

    //生成支付
    public function doPay($data)
    {
        //首次生成payment_config表中的config信息
        //$this->setBaseConfig($data['payment_id']);

        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 206000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 201001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
            
            return $this->retArr;
        }

        //网银支付调用
        if ($data['pay_type'] == 'wy') {
            //顺序固定
            $post_data = array(
                'versionId'      => '001',       //接口版本
                'businessType'   => '1100',   //商户号
                'insCode'        => '',
                'merId'          => $config['merchantID'],   //商户号
                'orderId'        => $orderInfo,           //商户支付订单号
                'transDate'      => date('YmdHis'),  //商户订单时间
                'transAmount'    => number_format($data['money'], 2, '.', ''),  //金额
                'transCurrency'  => '156',
                'transChanlName' => 'ICBC',
                'openBankName'   => '',
                //页面跳转同步通知地址
                'pageNotifyUrl'  => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo,
                //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
                'backNotifyUrl'  => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'],
                'orderDesc'      => urlencode(iconv('UTF-8','GBK','会员充值')),  //商品主题
                'dev'            => urlencode(iconv('UTF-8','GBK','会员充值'))
            );

            //签名
            $post_data['signData'] = $this->getSigned($post_data, ['key' => $config['merchantKey']], '', 0, 0);
            //var_dump($post_data);

            //type =2返回html跳转页面数
            $retData =  ['type' => $config['payType'][$data['pay_type']]['request_type'], 'html' => $this->httpHtml($post_data, $this->bankUrl)];
            
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        }else {
            //微信、支付宝扫码支付
            $post_data = array(
                'versionId'      => '001',       //接口版本
                'businessType'   => '1100',   //商户号
                'transChanlName' => $config['payType'][$data['pay_type']]['payStr'],
                'merId'          => $config['merchantID'],   //商户号
                'orderId'        => $orderInfo,           //商户支付订单号,每次访问生成的唯一订单标识
                'transDate'      => date('YmdHis'),  //商户订单时间
                'transAmount'    => number_format($data['money'], 2, '.', ''),  //金额
                //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
                'backNotifyUrl'  => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'],
                'orderDesc'      => urlencode(iconv('UTF-8','GBK','会员充值')),  //商品主题
                'dev'            => ''
            );
            $sign = $this->getSigned($post_data, ['key' => $config['merchantKey']]);
            $post_data['signType'] = 'MD5';  //不需要签名
            $post_data['signData'] = $sign;
            var_dump($post_data);

            //curl接口
            $dataJson = json_encode($post_data);
            $curlData = $this->httpPostJson($this->url, $dataJson);
            var_dump($curlData);

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 202002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '闪云付支付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }
            $retData = json_decode($curlData['data'], true);
            var_dump($retData);
            var_dump(iconv('GBK','UTF-8//IGNORE',urldecode($retData['refMsg'])));
            if ($retData['status'] == '00' && $retData['refCode'] == '01') {
                $sign = $retData['signData'];
                unset($retData['signData']);
                $retData['merId'] = $config['merchantID'];
                $retSign = $this->getSigned($retData, ['key' => $config['merchantKey']]);
                if($retSign != $sign) {
                    $this->retArr['code'] = 202003;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '闪云付支付返回数据成功，但验证签名失败！' . print_r($retData, true));
            
                    return $this->retArr;
                }
            
                /*
                $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retData['orderNo'], 'status' => 0));
                if (empty($result)) {
                    $this->retArr['code'] = 202004;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '闪云付返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
            
                    return $this->retArr;
                }
                */
            
                D('accountRecharge')->save(['remark' => $retData['ksPayOrderId']], ['order_sn' => $orderInfo]);
                //不知道为什么这样做
                session::set('qrcode_url', $retData['codeUrl']);
                session::set('pay_url', '');
                //type =1 返回二维码数据
                $ret =  ['type' => $config['payType'][$data['pay_type']]['request_type'], 'code_url' => $retData['codeUrl'], 'pay_url' => '', 'order_no' => $orderInfo, 'modes' => $data['pay_model']];
                $this->retArr['code'] = 0;
                $this->retArr['data']  = $ret;
            
                return $this->retArr;
            }else {
                $this->retArr['code'] = 202005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '闪云付支付接口调用失败，' . print_r($retData, true));
            
                return $this->retArr;
            }
        }
    }

    //支付回调方法
    public function doPaycallBack($postData)
    {
        $remark = '';
        //处理post回调数据
        $data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];
        
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 202006;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）闪云付支付异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $sign = $data['signData'];
        unset($data['signData']);
        $retSign = $this->getSigned($data, ['key' => $config['merchantKey']]);
        //echo $sign;
         
        if($sign != $retSign){
            $this->orderInfo['code'] = 202007;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）华银支付异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if ($data['refCode'] != '00') {
            $this->orderInfo['code'] = 201010;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）华银支付异步通知：返回信息不是充值成功信息，出现错误！'  . print_r($data, true));
            
            return $this->orderInfo;
        }
        
        $this->orderInfo['order_no']  = $data['orderId'];
        $this->orderInfo['amount']    = $data['transAmount'];
        $this->orderInfo['serial_no'] = $data['ksPayOrderId'];
        
        return $this->orderInfo;
    }

    /**
     * 生成签名
     * @param $data arrary 生成签名需要的数据
     * @param $arrKey arrary 用户密锁,键值对(一对)
     * @param $type sort 1：需要按键名字段名的 ascii  码从小到大排序，0：不用。
     * @param $add string 只使用键值时(不使用键名)是否使用'&'分割
     * @return string 生成签名
     */
    function getSigned($data = [], $arrKey = [], $add = '&', $type = 1, $sort = 1)
    {
        $string = '';
        $retStr = '';

        $string = $this->toUrlParams($data, $sort);

        if (!empty($arrKey)) {
            foreach ($arrKey as $k => $v) {
                if (!is_array($v)) {
                    if ($type == 0) {
                        $string .= $add . $v;
                    }else {
                        $string .= $add . $k . '=' . $v;
                    }
                }
            }
        }
        //var_dump($string);

        $retStr = strtoupper(md5($string));
    
        return $retStr;
    }
    
    /**
     * 格式化参数格式化成url参数
     * @param $data arrary 生成url需要的数据
     * @return string 生成url
     */
    public function ToUrlParams($data, $sort)
    {
        $buff = "";
        
        if ($sort == 1) {
            ksort($data);
        }
        foreach ($data as $k => $v)
        {
            $buff .= $k . "=" . $v . "&";
        }
    
        $buff = trim($buff, "&");

        return $buff;
    }
    
//post数据
    function httpPostJson($url, $jsonStr)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              'Content-Type: application/json; charset=utf-8',
              'Content-Length: ' . strlen($jsonStr)
            )
        );

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array('code' => $httpCode, 'data' => $response);
    }

    function httpHtml($data, $url)
    {
        $html = '<html>';
        $html .= '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="post" action="' . $url . '">';
        $html .= '<input type="text" id="versionId"name="versionId"  value="' . $data['versionId'] . '" />';
        $html .= '<input type="text" id="businessType"name="businessType"  value="' . $data['businessType'] . '"/>';
        $html .= '<input type="text" id="insCode"name="insCode"  value="' . $data['insCode'] . '"/>';
        $html .= '<input type="text" id="merId"name="merId"  value="' . $data['merId'] . '"/>';
        $html .= '<input type="text" id="orderId"  name="orderId"  value="' . $data['orderId'] . '"/>';
        $html .= '<input type="text" id="transDate" name="transDate"  value="' . $data['transDate'] . '"/>';
        $html .= '<input type="text" id="transAmount" name="transAmount"  value="' . $data['transAmount'] . '"/>';
        $html .= '<input type="text" id="transCurrency" name="transCurrency"  value="' . $data['transCurrency'] . '"/>';
        $html .= '<input type="text" id="transChanlName" name="transChanlName"  value="' . $data['transChanlName'] . '"/>';
        $html .= '<input type="text" id="openBankName" name="openBankName"  value="' . $data['openBankName'] . '"/>';
        $html .= '<input type="text" id="pageNotifyUrl" name="pageNotifyUrl" value="' . $data['pageNotifyUrl'] . '"/>';
        $html .= '<input type="text" id="backNotifyUrl" name="backNotifyUrl" value="' . $data['backNotifyUrl'] . '"/>';
        $html .= '<input type="text" id="orderDesc" name="orderDesc"  value="' . $data['orderDesc'] . '"/>';
        $html .= '<input type="text" id="dev" name="dev" value="' . $data['dev'] . '"/>';
        $html .= '<input type="text" name="signData" id="signData" value="' . $data['signData'] . '"/>';
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';
       
        return $html;
    }

    //支付初始配置
    public function setBaseConfig($payment_id)
    {
        $data1['name'] = '汇元支付';
        $data1['merchantID'] = '0000003';
        $data1['merchantKey'] = 'F02FBC69588AA022';
        $data1['payType']['wx']['name'] = '微信支付';
        $data1['payType']['wx']['payStr'] = '0002';
        $data1['payType']['wx']['request_type'] = 1;
        $data1['payType']['ali']['name'] = '支付宝支付';
        $data1['payType']['ali']['payStr'] = '0003';
        $data1['payType']['ali']['request_type'] = 1;
        $data1['payType']['wy']['name'] = '网银支付';
        $data1['payType']['wy']['payStr'] = '';
        $data1['payType']['wy']['request_type'] = 2;
        
        $serData = serialize($data1);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}