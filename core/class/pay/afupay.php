<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description A付支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class AFuPay extends PayInfo
{
    private $scan_url   = "https://gateway.aabill.com/cnpPay/initPay";             //扫码网关
    public  $bank_url   = "https://gateway.rffbe.top/quickGateWayPay/initPay";     //快捷支付
    //获取支付返回数据格式
    public $retArr = [           //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];
    
    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 232050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => 'A付支付',     //支付方式名称
        'serial_no' => ''            //第三方回调返回的第三方支付订单号（支付流水号）
    ];
    
    /**
     * 构成函数
     */
    public function __construct(){
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
            $this->retArr['code'] = 232000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('afu.txt', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 232001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('afu.txt', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
            return $this->retArr;
        }
        $post_data = [
            'subPayKey'     => $config['terminalID'],
            'payKey'        => $config['merchantPayKey'],      //商户支付Key
            'remark'        => "会员充值",
            'orderPrice'    => sprintf("%.2f", $data['money']), //金额
            'outTradeNo'    => $orderInfo, //订单号
            'orderTime'     => date('YmdHis'),
            'productName'   => 'afu_'.$data['payment_id'],
            'orderIp'       =>  ip(),
            'productType'       =>  $config['payType'][$data['pay_type']]['payStr'],
//            'returnUrl'     => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn={$orderInfo}&flag=1AA1",
            'returnUrl'     => "https://".$_SERVER['HTTP_HOST'],
//            'notifyUrl'     => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id={$data['payment_id']}&flag=1AA1" // flag参数辨别get返回参数时添加的"?"，处理使用flag标签
            'notifyUrl'     => "https://".$_SERVER['HTTP_HOST'] . '/rechargeNotify.php' // flag参数辨别get返回参数时添加的"?"，处理使用flag标签
        ];
        
        if($post_data['subPayKey'] == ""){
            unset($post_data['subPayKey']);
        }
        $post_data['sign'] = $this->array_to_sign($post_data, $config['merchantKey']);

        if ($data['pay_type'] == 'wy' || $data['pay_type'] == 'wykj') {
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->postHtml($post_data, $this->bank_url)
            ];
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;
            
            return $this->retArr;
        }else {
            $curlData = $this->httpPost($this->scan_url, $post_data);

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 232002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('afu.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true));
                return $this->retArr;
            }

            $result = json_decode($curlData['data'], true);
            payLog('afu.txt',print_r($result['payMessage'],true) . '  ==118===  '. print_r($result,true));
            if ($result['resultCode'] !== '0000') {
                $this->retArr['code'] = 232003;
                $this->retArr['msg']  = "获取支付二维码失败";
                payLog('afu.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($result, true));
                return $this->retArr;
            }

            if (!empty($result['errMsg']) || empty($result['payMessage'])) {
                $this->retArr['code'] = 232004;
                $this->retArr['msg']  = "获取支付二维码失败";
                payLog('afu.txt', '（' . $this->retArr['code'] . '）' . '获取支付二维码地址失败，' . print_r($result, true));
                return $this->retArr;
            }

            //验签
            $retSign = $this->array_to_sign($result, $config['merchantKey']);
            if($retSign != $result['sign']){
                $this->retArr['code'] = 214005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('afu.txt', '（' . $this->retArr['code'] . '）' . '支付返回数据成功，但验证签名失败！' . print_r($result, true));

                return $this->retArr;
            }
            
            if ($config['payType'][$data['pay_type']]['request_type'] == 2) {
                //网银则生成html页面,跳转页面数
                $params = $result['payMessage'];
                $params = str_replace("pay_form","payFrom",$params);
                $retData =  [
                    'type'  => $config['payType'][$data['pay_type']]['request_type'],
                    'modes' => $data['pay_model'],
                    'html'  => $params
                ];
                $this->retArr['code'] = 0;
                $this->retArr['data']  = $retData;
                return $this->retArr;
            
            }

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $result['payMessage']);
            session::set('pay_url_type', '1');
            session::set('pay_url', '');
            $ret =  [
                'type'     => $config['payType'][$data['pay_type']]['request_type'],
                'code_url' => $result['payMessage'],
                'pay_url'  => '',
                'order_no' => $orderInfo,
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
        //获取get回调数据
        $getData = $_GET;

        //处理get传输数据flag参数时，在原有带参数的数据上加的"?"
        $flag = str_replace('1AA1?', '', $getData['flag']);
        $flagData = explode('=', $flag);
        //$getData[$flagData[0]] = $flagData[1] . '=' . $flagData[2];
        $getData[$flagData[0]] = $flagData[1];

        //组织回调参数
        $data = [
            'field2'        => trim($getData["field2"]),  //field2=1=P88882017112010001469 (文档没有这个字段，这是什么字段，不懂，但没有这个字段验签不过！！！！）
            'payKey'        => trim($getData["payKey"]),  //商户支付Key
            'outTradeNo'    => trim($getData["outTradeNo"]),    //订单号
            'orderPrice'    => trim($getData["orderPrice"]),//实际支付金额
            'productType'   => trim($getData["productType"]),//产品类型
            'productName'   => trim($getData["productName"]),//支付产品名称
            'tradeStatus'   => trim($getData["tradeStatus"]), //支付状态
            'orderTime'     => trim($getData["orderTime"]),//下单时间，格式：yyyyMMddHHmmss
            'successTime'   => trim($getData["successTime"]),//成功时间，格式：yyyyMMddHHmmss
            'remark'        => trim($getData["remark"]),//备注
            'sign'          => trim($getData['sign']),  //签名
            'trxNo'         => trim($getData['trxNo'])  //交易流水号
        ];

        payLog('afu.txt', '异步充值通知数据：' . print_r($data, true)); //支付回调记录

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 232020;
            payLog('afu.txt', '（' . $this->orderInfo['code'] . '）A付支付异步通知,获取数据库配置错误！'  . print_r($data, true));
            return $this->orderInfo;
        }
        
        if($data['tradeStatus'] != 'SUCCESS') {
            $this->orderInfo['code'] = 232021;
            payLog('afu.txt', '（' . $this->retArr['code'] . '）' . 'A付支付异步通知：订单支付失败，' . print_r($data, true));
            return $this->orderInfo;
        }
        
        $sign_str = $this->array_to_sign($data, $config['merchantKey']);

        if ($sign_str != $data['sign']) {
            $this->orderInfo['code'] = 232022;
            payLog('afu.txt', '（' . $this->orderInfo['code'] . '）A付支付异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']  = 0;
        $this->orderInfo['order_no']  = $data["outTradeNo"];
        $this->orderInfo['amount']    = $data["orderPrice"];
        $this->orderInfo['serial_no'] = $data['trxNo'];

        return $this->orderInfo;
    }

    /**
     * @param array $data       前台发起支付的参数
     * @param string   $Md5key     商户密钥
     * @author wesker
     * @copyright 2017/11/27 11:00
     * @lastModifiedUser wesker
     * @lastModifiedTime 2017/11/27 15:00
     * @deprecate 组装参数，加密
     * @return string
     */
    public function array_to_sign($data, $paySecret){
        ksort($data);
        $sign = '';
        foreach ($data as $key => $val) {
            if ($key != 'sign' && $val != '') {
                $sign .= $key.'='.$val.'&';
            }
        }
        $sign .= 'paySecret='.$paySecret;

        return strtoupper(md5($sign));
    }

    /**
     * @param array  $post_data 发起支付的参数
     * @param string $url       支付网关
     * @author wesker
     * @copyright 2017/11/21
     * @lastModifiedUser wesker
     * @lastModifiedTime 2017/11/21
     * @deprecate 发起支付
     * @return array
     */
    public function httpPost($url, $post_data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($post_data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            payLog('afu.txt', 'A付扫码支付请求失败，抓捕异常：' . curl_error($ch));
            return curl_error($ch);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array('code' => $httpCode, 'data' => $response);
    }

    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    function postHtml($data, $url){
        $html =  "<html>";
        $html .= "<head>";
        $html .= "<meta http-equiv='Content-Type' content='text/html'; charset='utf-8'>";
        $html .= "</head>";
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= "<form id='payFrom' name='dinpayForm' action='".$url."' method='post'>";
        $html .= "<input type='hidden' name='payKey' value='".$data['payKey']."' />";
        $html .= "<input type='hidden' name='orderPrice' value='".$data['orderPrice']."' />";
        $html .= "<input type='hidden' name='outTradeNo' value='".$data['outTradeNo']."' />";
        $html .= "<input type='hidden' name='productType' value='".$data['productType']."' />";
        $html .= "<input type='hidden' name='orderTime' value='".$data['orderTime']."' />";
        $html .= "<input type='hidden' name='productName' value='".$data['productName']."' />";
        $html .= "<input type='hidden' name='orderIp'	value='".$data['orderIp']."'>";
        $html .= "<input type='hidden' name='returnUrl' value='".$data['returnUrl']."'>";
        $html .= "<input type='hidden' name='notifyUrl' value='".$data['notifyUrl']."'>";
        $html .= "<input type='hidden' name='subPayKey' value='".$data['subPayKey']."'>";
        $html .= "<input type='hidden' name='remark' value='".$data['remark']."'>";
        $html .= "<input type='hidden' name='sign' value='".$data['sign']."'>";
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';
        return $html;
    }

    public function formData($post_data, $url)
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

//        payLog('wufu.txt',print_r($html,true). "  ==220== ");
        //var_dump($html);
        return $html;
    }
}