<?php
/**=============================================================================
*
*
* Last modified:	2016-09-27 12:01:22
*
* Filename:		function.php
*
* Description: 常用函数和常量
*
*=============================================================================*/



require_once __DIR__.'/config.php';


/**
 * @desc 连接CacheRedis缓存数据库
 */
function initCacheRedis() {
    //该参数待放入配置文件
    $cache_redis = new redis();
    $ret = $cache_redis->connect(RD_HOST, RD_PORT);
    if (!$ret) {
        //ErrorCode::errorResponse(9001, 'redis connect error');
        echo 'redis connect error';

        return;
    }
    // 关闭redis密码认证
    $ret = $cache_redis->auth(RD_PASS);
    if (!$ret) {
        echo 'redis auth error';

        return;
        //ErrorCode::errorResponse(9002, 'redis auth error');
    }
    //如查有配置Redis的库
    if(!empty($redis_config['db'])){
        $cache_redis->select($redis_config['db']);
    }
    return $cache_redis;
}

/**
 * @desc 关闭CacheRedis缓存
 */
function deinitCacheRedis($redis) {
    $redis->close();
}


function lg($file,$s) {
	$fp = fopen(S_ROOT.'log/'.date('Y_m_d').'_'.$file,"a");
	fwrite($fp, date('Y-m-d H:i:s').'--------->'.$s."\n\n");
	fclose($fp);
}


/**
 * PHP发送Json对象数据
 *
 * @param $url 请求url
 * @param $jsonStr 发送的json字符串
 * @return array
 * @param gzip 
 */
function http_post_json($url, $jsonStr='[]',$gzip=false)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('CLIENT-IP:58.68.44.61','X-FORWARDED-FOR:58.68.44.61'));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);//脚本执行时间
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);//连接超时间
  curl_setopt($ch, CURLOPT_MAXREDIRS, 5);//查找次数，防止查找太深
  if($gzip) curl_setopt($ch, CURLOPT_ENCODING, "gzip");
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json; charset=utf-8',
      'Content-Length: ' . strlen($jsonStr)
    )
  );
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $response;
}

function objectToArray($e){
    $e=(array)$e;
    foreach($e as $k=>$v){
        if( gettype($v)=='resource' ) return;
        if( gettype($v)=='object' || gettype($v)=='array' )
            $e[$k]=(array)objectToArray($v);
    }
    return $e;
}

function get_code($url){
    $ch = curl_init ();  
    curl_setopt($ch, CURLOPT_URL, $url);  
    curl_setopt($ch, CURLOPT_HEADER, FALSE);  
    curl_setopt($ch, CURLOPT_NOBODY, FALSE);  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);  
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);  
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');  
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);//脚本执行时间
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);//连接超时间
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);//查找次数，防止查找太深
    curl_exec($ch);  
    $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);  
    var_dump($httpCode);
    curl_close($ch);
    return $httpCode;
}

/**
* 获取新的url
* @param $url str
*
*/
function new_domain($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    // 不需要页面内容
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    // 不直接输出
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // 返回最后的Location
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);//脚本执行时间
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);//连接超时间
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);//查找次数，防止查找太深
    curl_exec($ch);
    $info = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return $info;
}

function mbstringtoarray($str,$charset='utf-8') {
  $strlen=mb_strlen($str);
  while($strlen){
    $array[]=mb_substr($str,0,1,$charset);
    $str=mb_substr($str,1,$strlen,$charset);
    $strlen=mb_strlen($str);
  }
  return $array;
}

//生成流水号
function get_random($length = 3) {  
    $min = pow(10 , ($length - 1));  
    $max = pow(10, $length) - 1;  
    return date('YmdHis',time()).mt_rand($min, $max);  //当前时间加上3位随机数
}

function exit_php(){
    if(date('i')%10==2 || date('i')%10==7){
        return true;
    }else{
        return false;
    }
}

function ltid($id,$s){
    $fp = fopen('/tmp/lt'.$id,"w");                                                                                                                                                                               
    fwrite($fp, $s."\n");
    fclose($fp);
}


function ccid($s){
	$fp = fopen('/tmp/cid.log',"w");
	fwrite($fp, $s."\n");
	fclose($fp);
}

//计算冷热
function tj($zh,$tj){
    $xd=array(1,3,5,7,9,11,13);//小单
    $xs=array(0,2,4,6,8,10,12);//小双
    $dd=array(15,17,19,21,23,25,27);//大单
    $ds=array(14,16,18,20,22,24,26);//大双
    $js=array(0,1,2,3,4,5);//极小
    $jd=array(22,23,24,25,26,27);//极大
    $tj_arr = explode(',',$tj);
    foreach($tj_arr as $tjk=> $tjv){
        $tj_arr[$tjk]=$tjv+1;
    }
    if(in_array($zh,$xd)){
        $tj_arr[1]=0;//小
        $tj_arr[2]=0;//单
        $tj_arr[6]=0;//小单
    }

    if(in_array($zh,$xs)){
        $tj_arr[1]=0;//小
        $tj_arr[3]=0;//双
        $tj_arr[7]=0;//小双
    }

    if(in_array($zh,$dd)){
        $tj_arr[0]=0;//大
        $tj_arr[2]=0;//单
        $tj_arr[4]=0;//大单
    }

    if(in_array($zh,$ds)){
        $tj_arr[0]=0;//大
        $tj_arr[3]=0;//双
        $tj_arr[5]=0;//大双
    }

    if(in_array($zh,$js)){
        $tj_arr[1]=0;//小
        $tj_arr[9]=0;//极小
    }

    if(in_array($zh,$jd)){
        $tj_arr[0]=0;//大
        $tj_arr[8]=0;//极大
    }
    return join(',',$tj_arr);
}

/**
 * 验证签名 公钥加密
 * @param $url string 请求地址
 * @param $data array 传入参数
 */
function signa($url, $data)
{
    var_dump('signa');die;
    global $fconfig;
    static $host, $signa, $config;
    $config = $fconfig;
    if (empty($config)) {
        !defined('IN_SNYNI') && define("IN_SNYNI", 1);
        $config = require(S_ROOT.'caches/config.php');
    }
    if (empty($host)) {
        $host = $config['api_host'];
    }
    if (empty($signa)) {
        $signa = $config['signa'];
    }
    //签名数据
    $key = $signa['key'];
    $secret_key = $signa['secret_key'];
    $param['timestamp'] = time();//时间戳
    $param['signature'] = md5(md5($param['timestamp']) . $secret_key);//签名
    $param['key'] = $key;//key
    $param['source'] = 0;//接口来源:1 ios;  2 安卓; 3 H5; 4 PC ; 0 服务器本身
    $param['project'] = 0;//项目来源(0:pc手游, 1:讯彩; 2其它;  [待存字典表])
    $param['method'] = "POST";
    $params = base64_encode(json_encode($param));

    //业务数据
    $encrypted = "";
    if (!empty($data)) {
        $datas = json_encode($data);
        $encrypted = dencrypt(base64_encode($datas), "ENCODE", $param['signature']);
    }
    //@file_put_contents('getopenflag.log', date('Y-m-d H:i:s').PHP_EOL.'请求地址: '.$host.$url.' 数据：'.$encrypted.PHP_EOL,FILE_APPEND);
    //请求接口
  	echo $host.$url;
    $res = curl_post($host . $url, array('param' => $params, 'data' => $encrypted));
    return $res;
}

/**
 * 加密解密处理
 * @param unknown_type $string 密文
 * @param unknown_type $operation 加密 或 解密
 * @param unknown_type $key 密匙
 * @return unknown
 */
function dencrypt($string, $operation = 'DECODE', $key = '')
{
    if (empty($string)) {
        return false;
    }
    $operation != 'ENCODE' && $string = base64_decode(substr($string, 16));  //如果是解密就截16位以后的字符 并base64解密
    $code = '';
    $key = md5($key); //md5密匙
    $keya = strtoupper(substr($key, 0, 16));      //截取新密匙的前16位并大写
    $keylen = strlen($key);                      //计算密匙长度
    $strlen = strlen($string);
    $rndkey = [];
    for ($i = 0; $i < 128; $i++) {
        $rndkey[$i] = ord($key[$i % $keylen]);  //生成128个加密因子  （按密匙中单个字符的ASCII 值）

    }
    for ($i = 0; $i < $strlen; $i++) {
        $code .= chr(ord($string[$i]) ^ $rndkey[$i * $strlen % 128]);  //用字条串的每个字符ASCII值和加密因子里的（当前循环次数*字符串长度 求于 128） 按位异或  最后 ASCII 值返回字符
    }
    return ($operation != 'DECODE' ? $keya . str_replace('=', '', base64_encode($code)) : $code);  // 如果是加密就截取新密匙的前16位并加上base64加密码生成的密文
}

/**
 * curl post
 * @param $url string 请求地址
 * @param $data array 传入参数
 * @param $header array 返回Header
 * @param $nobody array 返回body
 * @return mixed
 */
function curl_post($url, $data = [], $header = false, $nobody = false)
{
  $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT,20); //防止超时卡顿
    curl_setopt($ch, CURLOPT_HEADER, $header);//返回Header
    curl_setopt($ch, CURLOPT_NOBODY, $nobody);//不需要内容
    curl_setopt($ch, CURLOPT_POST, true);//POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}


/**
 * json编码
 * @param $data array
 */
function encode($data){
    return json_encode($data,JSON_UNESCAPED_UNICODE);
}

/**
 *
 * json解码
 * @param $data array
 * 返回数组
 */
function decode($str){
    return json_decode($str,1);
}
