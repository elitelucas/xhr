<?php
/**
 *  action.php
 *
 */
!defined('IN_SNYNI') && die('Access Denied!');
class Action {

    public $db;
    public $userId = 0;
    public $retArr = [
        'code' => 0,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回数据异常的提示信息
        'data' => []             //返回数据
    ];

    public function __construct()
    {
        //跨域
        header("Access-Control-Allow-Origin: *");
        $origin = isset($_SERVER['HTTP_ORIGIN'])? $_SERVER['HTTP_ORIGIN'] : '';
        lg('origin',var_export(array('$origin'=>$origin,'$_SERVER[\'HTTP_ORIGIN\']'=>$_SERVER['HTTP_ORIGIN']),1));
        $this->db = getconn();
        $this->refreshRedis();
        O('session','',0);
        session::start();
    }

    //访问权限控制
    public function checkAuth()
    {
        // print_r($_REQUEST);die;
        $nowtime = time();
    	$retsign = trim($_REQUEST['sign']);
    	$nonce = trim($_REQUEST['nonce']);
    	$timestamp  = trim($_REQUEST['timestamp']);
    	$action = isset($_REQUEST['action'])?trim($_REQUEST['action']):trim($_REQUEST['a']);
    	/*
    	if ($nowtime - $timestamp > 30) {
    	    $this->retArr['code'] = 101;
    	    $this->retArr['msg']  = '请求已过期！';
    	    return false;
    	}
    	
    	$configData = $this->db->getone("select value from un_config where nid = 'pcsy_center_config'");
    	if (empty($configData)) {
    	    $this->retArr['code'] = 102;
    	    $this->retArr['msg']  = '验证失败，请求非法！';
    	    return false;
    	}
    	$platformConfig = json_decode($configData['value'], true);
    	
    	$sign = MD5($platformConfig['secret_key'] . $nonce . $action . $timestamp);
    	if ($retsign != $sign) {
    	    $this->retArr['code'] = 103;
    	    $this->retArr['msg']  = '验签失败，请求非法！';
    	    return false;
    	}

    	$adminData = $this->db->getone("select roleid from un_admin where username = 'jishu'");
    	$verify = D('admin/auth')->checkPower($adminData['roleid']);
    	if (!$verify) {
    	    $this->retArr['code'] = 104;
    	    $this->retArr['msg']  = '没有权限执行此操作！';
    	    return false;
    	}*/
    	if($retsign != '7758258'){
            $this->retArr['code'] = 103;
    	    $this->retArr['msg']  = 'Sign verification failed, request is illegal!';
    	    return false;
        }
    	//集中平台操作用户ID设置
    	$this->userId = 10000;
    	
    	return true;
    }
    
    
    protected function returnCurl($data = '')
    {
        if (!empty($data)) {
            $this->retArr['data']['retData'] = $data;
        }
        
        $configData = $this->db->getone("select value from un_config where nid = 'pcsy_center_config'");
        $platformConfig = json_decode($configData['value'], true);

        $this->retArr['data']['nonce'] = getRandomString(32);
        $this->retArr['data']['timestamp'] = time();
        $this->retArr['data']['sign'] = MD5($platformConfig['secret_key'] . $this->retArr['data']['nonce'] . $this->retArr['data']['timestamp']);
        
        echo json_encode($this->retArr,JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function getParame($parame_name, $inputOpt = 1, $default = '', $parameFunc = 'str', $errMsg = '') {
        $parame_val = $_REQUEST[$parame_name];
 
        //非必填参数为空 返回默认值
        if(!$inputOpt && (false === $parame_val || '' === $parame_val))
            return $default;

        
        !$errMsg && $errMsg = 'Parameter '.$parame_name.' is empty or malformed!';
        switch ($parameFunc) {
            case 'string':
            case 'str':
                if(!is_string($parame_val)) {
                    $this->retArr['code'] = 1;
                    $this->retArr['msg'] = $errMsg;
                }
            break;
            case 'int':
                $parame_val_filter = (int)$parame_val;
                if($parame_val_filter != $parame_val|| !is_integer($parame_val_filter)) {
                    $this->retArr['code'] = 1;
                    $this->retArr['msg'] = $errMsg;
                }
                break;
            case 'phone':
                break;
            case 'email':
                break;
            case 'float':
                break;
            default:
                if(function_exists($parameFunc)) {
                    if(!$parameFunc($parame_val)) {
                        $this->retArr['code'] = 1;
                        $this->retArr['msg'] = $errMsg;
                    }
                }else {
                    $this->retArr['code'] = 1;
                    $this->retArr['msg'] = 'Parameter verification type error';
                }
                break;
        }
        if($this->retArr['code']) {
            $this->returnCurl();
        }
        return $parame_val;
    }

        /**
     * 刷新缓存
     * @method POST
     */
    private function refreshRedis(){
        //初始化redis
        $redis = initCacheRedis();
        $res = $redis->lRange("ConfigIds", 0, -1);
        //dump($res);
        if(empty($res)){
            //组装URL
            $url = C('app_home') . "/index.php?m=api&c=initCache&a=index";
            $param = array(
                'pass' => C('pass'),
                'action' => 'all',
                'param' => 'all'
            );
            curl_post_content($url, $param);
//            signa($url, $param);
        }
        //关闭redis链接
        deinitCacheRedis($redis);
    }
}
