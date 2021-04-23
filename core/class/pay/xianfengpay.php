<?php

/**
 * Created by H.
 * @author hady
 * @copyright HCHT 2018/1/19 11:06
 * @description 先疯支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class XianFengPay extends PayInfo
{
    //请求接口Url
    public $url = 'http://pay.xfengpay.com/Business/pay?method=';    //扫码正式（线上）调用接口
    public $bank_url = 'http://pay.xfengpay.com/Business/pay?method=';  //第三方网银接口
    public $payName = '先疯支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 234060,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 'error',            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCEED',  //回调处理成功时，返回接口字符串
        'bank_name' => '先疯支付',  //支付方式名称
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
            $this->retArr['code'] = 234000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('xianf.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true).'--63--');
        
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 234001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('xianf.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $post_data = array(
            'merchant'    => $config['merchantID'],
            'm_orderNo'    => $orderInfo,
            'tranAmt'    => number_format($data['money'], 2, '.', ''),  //金额,精确到两位小数(没有两位小数会出错）,
            'pname'    => 'recharge',
            'notifyUrl'    => "http://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'pnum' => 1,
            'retUrl' => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo,
        );

        $post_data['sign'] = $this->getSigned($post_data, $config['merchantKey']);
        $payStr = $config['payType'][$data['pay_type']]['payStr'];
        payLog('xianf.txt','--89--'.$payStr);

        ksort($post_data);
        $url = $this->bank_url . $config['payType'][$data['pay_type']]['payStr'];
        $curlData = $this->httpPost($post_data, $url);

           //接口调用成功与否
           if ($curlData['code'] != 200) {
               $this->retArr['code'] = 234002;
               $this->retArr['msg']  = '支付调用失败！';
               payLog('xianf.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

               return $this->retArr;
           }
        payLog('xianf.txt',print_r($curlData,true).'---105--');
            $retData = json_decode($curlData['data'], true);
           payLog('xianf.txt',print_r($retData,true).'---105--');
            $right_return = $retData['retMsg'];

            if (!is_array($right_return)) {
                $this->retArr['code'] = 234003;
                $this->retArr['msg']  = $right_return;
                payLog('xianf.txt', '（' . $this->retArr['code'] . '）' . '暂不支持此方式，' . print_r($curlData, true).'--112--');

                return $this->retArr;
            }
           if ((!isset($retData['retCode']) || $retData['retCode'] != '000000') && empty($right_return['paymentInfoType'])) {
               $this->retArr['code'] = 234005;
               $this->retArr['msg']  = '支付二维码生成失败！';
               payLog('xianf.txt', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($retData, true));
           
               return $this->retArr;
           }

            if ((!isset($retData['retCode']) || $retData['retCode'] == '000000') && empty($right_return['paymentInfoType'])) {
                $qr_code = $right_return['paymentInfo'];
                //用于安全验证返回url是否非法
                session::set('qrcode_url', $qr_code);
                session::set('pay_url', $right_return['paymentInfoType']);
                //type =1 返回二维码数据 2，返回html整页数据
                $ret =  [
                    'type'     => 1,
                    'code_url' => $qr_code,
                    'pay_url'  => $retData['paymentInfoType'],
                    'order_no' => $orderInfo,
                    'modes'    => $data['pay_model']
                ];

                $this->retArr['code'] = 0;
                $this->retArr['data'] = $ret;

                return $this->retArr;
            }

            if ((!isset($retData['retCode']) || $retData['retCode'] == '000000') && $right_return['paymentInfoType'] == 1) {
                $jump_url = $right_return['paymentInfo'];
                $retData =  [
                    'type'  => $config['payType'][$data['pay_type']]['request_type'],
                    'modes' => $data['pay_model'],
                    'html'  => $this->httpHtml($post_data, $jump_url)
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

        $data = json_decode($postData['data'],true);
        payLog('xianf.txt',print_r($data,true).'--165--');
        $data = [
            'merchant'        => $data['merchant'],
            'amount'   => $data['amount'],
            'currency'      => $data['currency'],
            'orderNo'  => $data['orderNo'],
            'm_orderNo' => $data['m_orderNo'],
            'payType'      => $data['payType'],
            'status'       => $data['status'],
            'sign'          => $data['sign'],
        ];
        payLog('xianf.txt',print_r($data,true).'--167--');

        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);
        
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 233020;
            payLog('xianf.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if (empty($data['status']) || $data['status'] != 2) {
            $this->orderInfo['code'] = 233021;
            payLog('xianf.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchant'] = $config['merchantID'];

        $retSign = $this->getSigned($data, $config['merchantKey']);
        var_dump($retSign);
        if($retSign != $data['sign']){
            $this->orderInfo['code'] = 233022;
            payLog('xianf.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['m_orderNo'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['orderNo'];

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
        ksort($data);
        foreach ($data as $k => $v) {
            if ($k != 'sign') {
            $sign_str = $sign_str . $k. '=' . $v . '&';
            }
        }
        $sign_str  = $sign_str . 'key=' . $key;
        $sign = md5($sign_str);
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

        ksort($data);
        foreach ($data as $k => $v) {
            $signStr = $signStr . $k. '=' . $v . '&';
        }
        $signStr  = $signStr . 'key=' . $key;
    
        $sign = strtolower(md5($signStr));
    
        return $sign;
    }
}