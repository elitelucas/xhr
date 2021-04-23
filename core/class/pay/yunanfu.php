<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/12/07 10:06
 * @description 云安付支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class YunAnFuPay extends PayInfo
{
    //请求接口Url
    //public $url = 'https://pay.antopay.com/antopay.html';             //扫码测试调用接口
    public $url = 'https://pay.antopay.com/antopay.html';               //扫码正式（线上）调用接口
    public $payName = '云安付支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 217050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'ok',  //回调处理成功时，返回接口字符串
        'bank_name' => '支付',       //支付方式名称
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
        //$this->setBaseConfig($data['payment_id']);
    
        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 217001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 217001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }
        
        $post_data = [
            'partner'     => $config['merchantID'],
            'ordernumber' => $orderInfo,
            'paymoney'    => number_format($data['money'], 2, '.', ''),
            'attach'      => 'recharge',
            'callbackurl' => "https://".$_SERVER['HTTP_HOST'],
            //'callbackurl' => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'],
            'hrefbackurl' => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id']
            //'hrefbackurl' => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo
            //'hrefbackurl' => "https://".$_SERVER['HTTP_HOST']
        ];
        
        if (empty($data['bank_code'])) {
            $post_data['banktype'] = $config['payType'][$data['pay_type']]['payStr'];
        } else {
            $post_data['banktype'] = $data['bank_code'];
        }
    
        $post_data['sign'] = $this->getSigned($post_data, $config['merchantKey']);
        //$post_data['callbackurl'] = urlencode($post_data['callbackurl']);
        //$post_data['hrefbackurl'] = urlencode($post_data['hrefbackurl']);
        //var_dump($post_data);
    
        //type =2返回html跳转页面数
        $retData =  [
            //'type'  => $config['payType'][$data['pay_type']]['request_type'],
            'type'  => 2,
            'modes' => 4,
            'html'  => $this->httpHtml($post_data, $this->url)
        ];
    
        $this->retArr['code'] = 0;
        $this->retArr['data']  = $retData;
    
        return $this->retArr;
        
        /*
        //区分网银和扫码支付
        if ($data['pay_type'] == 'wy') {
            $post_data['banktype'] = $data['bank_code'];
            $post_data['isshow'] = 0;
            
            $post_data['sign'] = $this->getSigned($post_data, $config['merchantKey']);
            //$post_data['callbackurl'] = urlencode($post_data['callbackurl']);
            //$post_data['hrefbackurl'] = urlencode($post_data['hrefbackurl']);
            //var_dump($post_data);
            
            //type =2返回html跳转页面数
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => 4,
                'html'  => $this->httpHtml($post_data, $this->url)
            ];
    
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;
    
            return $this->retArr;
        } else {
            $post_data['banktype'] = $config['payType'][$data['pay_type']]['payStr'];
            $post_data['isshow'] = 0;
            
            $post_data['sign'] = $this->getSigned($post_data, $config['merchantKey']);
            //$post_data['callbackurl'] = urlencode($post_data['callbackurl']);
            //$post_data['hrefbackurl'] = urlencode($post_data['hrefbackurl']);
            //var_dump($post_data);
            
            $curlData = $this->httpGet($post_data, $this->url);

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 217002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }
            
            $retData = $curlData['data'];
            if (!isset($retData)) {
                $this->retArr['code'] = 217003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($retData, true));
            
                return $this->retArr;
            }
            
            $retOderNo    = $orderInfo;
            $retOderPayNo = 'likefupay' . date('YmdHis');
            $retOderPayQrcodrUrl = isset($retData) ? $retData : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty(retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 216005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '返回数据错误，' . print_r($retData, true));
            
                return $this->retArr;
            }
            
            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 217006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
            
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
            */
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
        //$data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 217020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($postData, true));
        
            return $this->orderInfo;
        }
        
        $data = [
            'partner'     => $_REQUEST["partner"],      //商户账号
            'ordernumber' => $_REQUEST["ordernumber"],  //商户账号
            'orderstatus' => $_REQUEST["orderstatus"],  //商户订单号
            'paymoney'    => $_REQUEST["paymoney"],     //订单金额
            'sysnumber'   => $_REQUEST["sysnumber"],    //流水号
            'sign'        => $_REQUEST["sign"]
        ];

        if (!isset($data['orderstatus']) || $data['orderstatus'] != 1) {
            $this->orderInfo['code'] = 217021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['partner']    = $config['merchantID'];
        $retSign = $this->retSigned($data, $config['merchantKey']);
        //var_dump($retSign);
        if($retSign != $data['sign']){
            $this->orderInfo['code'] = 217022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['ordernumber'];
        $this->orderInfo['amount']    = $data['paymoney'];
        $this->orderInfo['serial_no'] = $data['sysnumber'];

        return $this->orderInfo;
    }  
    
    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    function httpGet($postdata, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($postdata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
        $html =  "<html>";
        $html .= "<head>";
        $html .= "<meta http-equiv='Content-Type' content='text/html'; charset='utf-8'>";
        $html .= "<title>会员充值</title>";
        $html .= "</head>";
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= "<form id='payFrom' name='dinpayForm' action='" . $url . "' method='get'>";
        $html .= "<input type='hidden' name='partner' value='" . $post_data['partner']."' />";
        $html .= "<input type='hidden' name='ordernumber' value='".$post_data['ordernumber']."' />";
        $html .= "<input type='hidden' name='banktype' value='".$post_data['banktype']."' />";
        $html .= "<input type='hidden' name='paymoney' value='".$post_data['paymoney']."' />";
        $html .= "<input type='hidden' name='attach' value='".$post_data['attach']."' />";
        $html .= "<input type='hidden' name='isshow' value='".$post_data['isshow']."' />";
        $html .= "<input type='hidden' name='callbackurl' value='".$post_data['callbackurl']."'>";
        $html .= "<input type='hidden' name='hrefbackurl' value='".$post_data['hrefbackurl']."'>";
        $html .= "<input type='hidden' name='sign' value='".$post_data['sign']."'>";
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';

        return $html;
    }
    
    /**
     * 数据签名
     * @param array $data  签名数据
     * @param string $key  秘钥
     */
    public function getSigned($data, $key) {
        $signSource = sprintf("partner=%s&banktype=%s&paymoney=%s&ordernumber=%s&callbackurl=%s%s", $data['partner'], $data['banktype'], $data['paymoney'], $data['ordernumber'], $data['callbackurl'], $key);
        return md5($signSource);
    }
    
    /**
     * 验证签名
     * @param array $data  签名数据
     * @param string $key  秘钥
     */
    public function retSigned($data, $key) {
        $signSource = sprintf("partner=%s&ordernumber=%s&orderstatus=%s&paymoney=%s%s", $data['partner'], $data['ordernumber'], $data['orderstatus'], $data['paymoney'], $key);
        return md5($signSource);
    }
    
    /**
     * 支付初始数据配置数据库
     * @param int $payment_id 支付类型ID
     */
    public function setBaseConfig($payment_id)
    {
        $data['name'] = '云安付支付';
        $data['merchantID'] = '20003000';
        $data['merchantKey'] = '762952ff9cae441ad9c1b462830aa489';
        $data['payType']['wx']['name'] = '微信支付';
        $data['payType']['wx']['payStr'] = 'WEIXINPAY';
        $data['payType']['wx']['request_type'] = 2;
        $data['payType']['ali']['name'] = '支付宝支付';
        $data['payType']['ali']['payStr'] = 'ALIPAY';
        $data['payType']['ali']['request_type'] = 2;
        $data['payType']['qq']['name'] = 'QQ钱包支付';
        $data['payType']['qq']['payStr'] = 'TENPAY';
        $data['payType']['qq']['request_type'] = 2;
        $data['payType']['wy']['name'] = '网银支付';
        $data['payType']['wy']['payStr'] = '';
        $data['payType']['wy']['request_type'] = 2;
        $data['payType']['wy']['bank_id'] = '2';
        
        $serData = serialize($data);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}