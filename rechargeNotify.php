<?php
/**
 * Created by Kevin.
 * User: Kevin
 * Date: 2018/02/26
 * Time: 14:00
 * desc: 处理线上支付无参数路由支付回调处理方法：通过url调用执行该文件，获取相关支付确认信息后，通过curl转发到带参数路由的api接口
 */

define('S_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('DS', DIRECTORY_SEPARATOR);
define('S_CORE', S_ROOT . 'core' . DS);
define('S_CACHE', S_ROOT . 'caches' . DS);
define('IN_SNYNI', TRUE);

//require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';

$data = [];           //支付回调数据
$payData = '';        //支付回传的支付参数，规定：使用"_"将支付名称与支付ID连接起来，如魔宝云支付：'mobaoyun_123'
$payType = '';        //支付类型
$payment_id = '';     //支付方式ID

//不同的支付方式获取数据的方式可能不同，根据不同的支付，添加不同的获取回调数据的方法，如：$_GET\$_POST\$_REQUEST\（php://input）
$postData = file_get_contents("php://input");  //获取post参数字符串类型数据

payLog('callbackRecharge.txt',print_r($postData,true).'---23---');

//数据处理，不同的支付方式，可能数据处理方式不同
if  (!empty($postData)) {
    if (@!simplexml_load_string($postData, 'SimpleXMLElement', LIBXML_NOCDATA)) {
        $data = json_decode($postData, true);
        $json_error = json_last_error();
        if($json_error) {           //非json格式
            parse_str($postData, $data);
        }
    } else {
        $data = json_decode(json_encode(simplexml_load_string($postData, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }
}else {
    $data = $_REQUEST;
}

if (!empty($data)) {
    payLog('callbackRecharge.txt', '异步充值接参数通知数据：' . print_r($data, true));  //日志，记录转接数据

    if(isset($data['remark']) && strstr($data['remark'], 'zhifupingfang', false)){          //智付平方
        $arr = explode('_',$data['remark']);       //ruicheng_111_143633
        $payment_id = $arr[1];
    }elseif(isset($data['attach']) && strstr($data['attach'], 'jingyang', false)) {          //金阳支付
        $arr = explode('_',$data['attach']);       //ruicheng_111_143633
        $payment_id = $arr[1];
    }elseif(isset($data['fxattch']) && strstr($data['fxattch'], 'jiefutong', false)) {           //捷付通支付
        $arr = explode('_',$data['fxattch']);       //ruicheng_111_143633
        $payment_id = $arr[1];
    }elseif(isset($_GET['pid']) && $_GET['pid'] == 'hengcheng') {            //恒诚支付
        $orderno = $data['out_order_number'];
        if(!$orderno) return false;
        $db = getDb();
        $sql = "select payment_id from un_account_recharge where order_sn = '".$orderno."'";
        $payment_id = $db->result($sql);
        unset($data['pid']);
    }elseif(isset($data['remark']) && strstr($data['remark'], 'ruicheng', false)) {         //瑞成     [ruicheng]_[payment_id]_[user_id]
        $arr = explode('_',$data['remark']);       //ruicheng_111_143633
        $payment_id = $arr[1];
    }elseif(isset($data['user_id']) && strstr($data['user_id'], 'yifa', false)) {      //易发支付
        $arr = explode('_',$data['user_id']);       //yifa_111_143633
        $payment_id = $arr[1];
    }elseif(isset($data['r8_MP']) && strstr($data['r8_MP'], 'yizhibao', false)) {         //易支宝支付
        $arr = explode('_',$data['r8_MP']);
        $payment_id = $arr[1];        //易支宝支付
    }elseif (isset($data['apiName']) && $data['apiName'] == 'PAY_RESULT_NOTIFY') {
        $payData = explode('_', $data['merchParam']);       //从商户传输的参数中获取回传参数
        if (empty($payData)) {
            echo '0';
            exit;
        }
        
        //逸支付、魔宝云
        if (in_array($payData[0], ['yizhifu', 'mobaoyun', 'yisheng'])) {
            $payType    = empty($payData[0]) ? '' : $payData[0];
            $payment_id = empty($payData[1]) ? '' : $payData[1];
            
            //支付回调数据判断 1：异步通知，其他：url跳转
            if ($data['notifyType'] != 1) {
                header("Location: https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $data['orderNo']);   //支付返回页面跳转
                exit;
            }
        }
    } elseif (!empty($data['attach'])) {   //仁信支付  讯宝 通达 诚优 共享
        $payData = explode('_', $data['attach']);       //从商户传输的参数中获取回传参数
        $payType    = empty($payData[0]) ? '' : $payData[0];
        $payment_id = empty($payData[1]) ? '' : $payData[1];
    } elseif (isset($data['goodsClauses']) && isset($data['shopCode'])) {
        $payment_id = explode(',',$data['goodsClauses']);
        $payment_id = $payment_id[1];
        $data['payment_id'] = $payment_id;
    } elseif (isset($data['orderuid']) && isset($data['orderid'])) {
        $payment_id = $data['orderuid'];
    } elseif (isset($data['tradeNo']) && strpos($data['extra'],'_XING')&& isset($data['extra'])) { //星付
        $payment_id = explode('_',$data['extra']);

        $payment_id = $payment_id[0];
    } elseif (isset($data['tradeNo']) && strpos($data['extra'],'_YUN') && isset($data['extra'])) { //尚付云
        $payment_id = explode('_',$data['extra']);
        $payment_id = $payment_id[0];
    } elseif (isset($data['customerid']) && isset($data['sdorderno']) && isset($data['paytype'])) { //企鹅
        $payment_id = explode('_',$data['sdorderno']);
        $payment_id = $payment_id[1];
    } elseif (isset($data['userId']) && isset($data['orderNo'])) { //汇通宝
        $payment_id = explode('_',$data['orderNo']);
        $payment_id = $payment_id[1];
    } elseif (isset($data['TimeEnd']) && isset($data['Msg'])) { //柒柒
        $payment_id = explode('_',$data['Msg'])[1];
    } elseif (isset($data['orderNo'])) {  // 闪支付  人人        新呗
        if(strpos($data['payment_id'], '/')) {      //新呗
            $arr = explode('/', $data['payment_id']);
            $payment_id = $arr[0];
            $other = explode('=', $arr[1]);

            if($other)
                $data[$other[0]] = $other[1];
        }else {
            $payment_id = explode('_',$data['orderNo'])[1];
        }

    } elseif (isset($data['r8_MP'])) { //金流
        $payment_id = explode('_',$data['Msg'])[1];
    } elseif (isset($data['productName'])) { //A支付
        $payment_id = explode('_',$data['productName']);
    } elseif (isset($data['ext1'])) { //开联通
        $payment_id = $data['ext1'];
    } elseif (isset($data['pay_orderid'])) {  //支付家
        $payment_id = explode('_',$data['pay_orderid']);
    } elseif (isset($data['remark'])) {  //支付家
        $payment_id = $data['remark'];
    }
}

//var_dump($data);
//var_dump($payment_id);
payLog('callbackRecharge.txt',print_r($payment_id,true).'----payment_id--');

if (!empty($data) && !empty($payment_id)) {

    payLog('curlRecharge.log', '异步充值url转接参数通知数据：' . print_r($data, true));  //日志，记录转接数据

    $ret = httpPostApi($data, $payment_id);   //异步充值通知转接
    payLog('callbackRecharge.txt',print_r($ret,true).'----73--');
    
    echo $ret;  //输出结果数据
}


function getDb() {          //获取数据库连接

    $config = require S_CACHE . 'config.php';
    static $db = null;

    if($db) return $db;

    $arr = [
        'db_host'   => $config['db_host'],
        'db_user'   => $config['db_user'],
        'db_pwd'    => $config['db_pwd'],
        'db_name'   => $config['db_name'],
        'db_lang'   => $config['db_lang'],
        'db_prefix' => $config['db_prefix'],
        'pconnect'  => $config['db_pcon'],
        'debug'     => $config['db_debug'],
    ];
    include S_CORE . 'class' . DS . 'mysql.php';
    $db = new mysql($arr);
    if(empty($config['db_port'])) {
        $db->connect()->select_db();
    }
    return $db;
}


function xml_parser($str){
    $xml_parser = xml_parser_create();
    if(!xml_parse($xml_parser,$str,true)){
        xml_parser_free($xml_parser);
        return false;
    }else {
        return (json_decode(json_encode(simplexml_load_string($str)),true));
    }
}


/**
 * 充值异步转接接口调用，post数据转发，表单提交数据库格式
 * @param array $payData  需要转发的数据
 * @param int $paymentId  线上支付方式ID
 * @return mixed|number   返回数据
 */
function httpPostApi($payData, $paymentId)
{
    $h = isset($_SERVER['REQUEST_SCHEME'])?$_SERVER['REQUEST_SCHEME']:'https';
    //路由api接口
    $payUrl = $h."://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $paymentId;
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL, $payUrl);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response=curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);
    if ($httpCode == 200) {
        return $response;
    } else {
        return 0;
    }
}

/**
 * 支付日志
 * @param  string $fileName 文件名,如：payerror.log或者pay/error.log
 * @param  array $data 数据
 */
function payLog($fileName, $data)
{
    $pathLog = S_ROOT . 'caches' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($pathLog)) {
        if (filesize($pathLog) >= 10000000) {
            $new_file = dirname($pathLog) . '/' . date('Y_m_d_H_i_s').'_'  . basename($pathLog);
            copy($pathLog,$new_file);
            file_put_contents($pathLog, '<----' . date('Y-m-d H:i:s').'---->' . $data . "\n");
        }else {
            file_put_contents($pathLog, '<----' . date('Y-m-d H:i:s').'---->' . $data . "\n", FILE_APPEND);
        }
    }else {
        file_put_contents($pathLog, '<----' . date('Y-m-d H:i:s').'---->' . $data . "\n", FILE_APPEND);
    }
}
