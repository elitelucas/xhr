<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/12/25 10:00
 * @description 支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class XunHuiBaoPay extends PayInfo
{
    //请求接口Url
    //public $url = '';              //扫码测试调用接口
    public $url = 'http://pay.x6pay.com:8082/posp-api/passivePay';                //扫码正式（线上）调用接口
    public $bank_url = 'http://pay.x6pay.com:8082/posp-api/passivePay';           //第三方网银接口
    public $payName = '讯汇宝支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 227050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'success',  //回调处理成功时，返回接口字符串
        'bank_name' => '讯汇宝支付',       //支付方式名称
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
            $this->retArr['code'] = 227001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 227001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'merchno' => $config['merchantID'],
            'amount' => number_format($data['money'], 2, '.', ''),
            'traceno' => $orderInfo,
            'payType' => $config['payType'][$data['pay_type']]['payStr'],
            'goodsName'=>'Recharge',
            'notifyUrl'=> "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'],
            'remark' => '',
            'settleType' => 1,
            'certno' => '',
            'mobile'  => '',
            'accountno' => '',
            'accountName' => '',
            'fee' => ''
            
        );
        
        if ($data['pay_type'] == 'wy') {
            //网银支付
            $post_data['signData'] = $this->getSigned($post_data, ['key' => $config['merchantKey']], '', 0, 0);
            //var_dump($post_data);
        
            //type =2返回html跳转页面数
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data, $this->bank_url)
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        }else {
            //非网银支付，微信、支付宝、QQ钱包支付
            $post_data['signature'] = $this->getSign($post_data, $config['merchantKey']);
            
            //curl接口
            $curlData = $this->httpPost($post_data, $this->url);
            var_dump($curlData);
    
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 227002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }
            
            $retData = json_decode($curlData['data'], true);
            if (!isset($retData['respCode']) || $retData['respCode'] != '00' || empty($retData['barCode'])) {
                $this->retArr['code'] = 227003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $retSign = $this->getSigned($retData, $config['merchantKey']);
            if($retSign != $retData['signature']){
                $this->retArr['code'] = 227004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功，但验证签名失败！' . print_r($retData, true));
            
                return $this->retArr;
            }

            $retOderNo = isset($retData['traceno']) ? $retData['traceno'] : '';
            //$retOderPayNo = '';
            $retOderPayNo = isset($retData['refno']) ? $retData['refno'] : '';
            $retOderPayQrcodrUrl = isset($retData['barCode']) ? $retData['barCode'] : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty(retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 227005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据错误，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 227006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
                
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
        //处理post回调数据
        $data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 227020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if (!isset($data['status']) || $data['status'] != 1) {
            $this->orderInfo['code'] = 227021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchno']    = $config['merchantID'];
        $retSign = $this->getSigned($data,$config['merchantKey']);
        //var_dump($retSign);
        if($retSign != $data['signInfo']){
            $this->orderInfo['code'] = 227022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['traceno'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['channelTraceno'];

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
        $ch=curl_init((string)$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true); //使用curl_setopt获取页面内容或提交数据，有时候希望返回的内容作为变量存储，而不是直接输出，这时候希望返回的内容作为变量
        curl_setopt($ch,CURLOPT_TIMEOUT,30);          //30秒超时限制
        curl_setopt($ch,CURLOPT_HEADER,false);        //将文件头输出直接可见。
        curl_setopt($ch,CURLOPT_POST,true);           //设置这个选项为一个零非值，这个post是普通的application/x-www-from-urlencoded类型，多数被HTTP表调用。
        curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));   //post操作的所有数据的字符串。

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return array('code' => $httpCode, 'data' => trim(iconv('GB2312', 'UTF-8', $response)));
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
     * 生成请求签名结果
     * @param $data 已排序要签名的数组
     * @return 签名结果字符串
     */
    function getSign($data,$key)
    {
        ksort($data);
        $sign = '';

        foreach($data as $k => $v)
        {
            if ($k != 'signature' && $v != '') {
                $sign .= $k . "=" . iconv('UTF-8', 'GB2312',$v) . "&";
            }
        }
        $ret = md5($sign . $key);

        return $ret;
    }
}