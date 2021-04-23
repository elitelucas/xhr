<?php

/**

 *  action.php

 *

 */

!defined('IN_SNYNI') && die('Access Denied!');

class Action {



    public $db;

    private $mdb;

    public $userId = 0;



    public function __construct()

    {

        //跨域

        header("Access-Control-Allow-Origin: *");

        $origin = isset($_SERVER['HTTP_ORIGIN'])? $_SERVER['HTTP_ORIGIN'] : '';

        $this->db = getconn();

        $this->refreshRedis();

        O('session','',0);

        session::start();

    }





    public function  testmdb()

    {

        $re = $this->mdb->getall('SELECT * FROM `admin`');

        dump($re);

    }

    //访问权限控制

    public function checkAuth()

    {

    	$token = trim($_REQUEST['token']);

    	$now = time();

    	//验证token

    	$userId = $this->getUserIdByToken($token);

    	if(!$userId)

    	{

    		ErrorCode::errorResponse(ErrorCode::INVALID_TOKEN);

    	}



        $mac = $this->db->getone("select mac from un_session where user_id = $userId");

        $res = D('user')->isIpBlack($mac['mac'],$_REQUEST['m'],$_REQUEST['c'],$_REQUEST['a']);

        if(!$res)

        {

            ErrorCode::errorResponse(ErrorCode::DEFAULT_MSG,"Sorry! You don't have enough permissions");

        }

    	//修改最后访问时间

        $data = array(

            'lastvisit' => SYS_TIME,

        );

        $where = array(

            'user_id' => $userId,

            'sessionid' => $token

        );

        $this->db->update('#@_session',$data,$where);

    	$this->userId = $userId;

    }



    //检查参数完整性

    public function checkInput($input,$need,$verify=array())

    {

    	if(empty($need) || !is_array($need))

    	{

    		return;

    	}

    	if(empty($input) || !is_array($input))

    	{

    		ErrorCode::errorResponse(ErrorCode::SHORT_PARAMS,'Missing parameters:' . implode('，', $input));

    	}

    	foreach ($need as $v)

    	{

    		if(!array_key_exists($v, $input))

    		{

    			ErrorCode::errorResponse(ErrorCode::SHORT_PARAMS,'Missing parameters:' . $v);

    		}

    		if(($verify == 'all') || in_array($v,$verify)){

                $temp = trim($input[$v]);

                if(empty($temp) && $input[$v] != '0')

                {

                    ErrorCode::errorResponse(100011,'The parameter cannot be empty:' . $v);

                }

            }

    	}

    }



    //设置登录信息

    public function setToken($userId,$mac, $ipData = [])

    {

    	//删除之前的token信息

    	$this->db->delete('#@_session', array('user_id' => $userId));

    	//生成token

    	$token = md5(uniqid($userId, true));



    	if (empty($ipData)) {

    	    $ipData['ip'] = '';

    	    $ipData['attribution'] = '';

    	}



    	$data = array(

    			'user_id' => $userId,

    			'sessionid' => $token,

    			'ip' => $ipData['ip'],

    	        'ip_attribution' => $ipData['attribution'],

    			'lastvisit' => SYS_TIME,

                'mac'=>$mac

    	);

    	$this->db->replace('#@_session',$data);



    	return $token;

    }



    //退出

    public function clearToken()

    {

    	//删除token信息

    	$result = $this->db->delete('#@_session', array('user_id' => $this->userId));

    }



    //根据token获取userId

    private function getUserIdByToken($token)

    {

    	$sql = "SELECT user_id FROM #@_session WHERE sessionid = '{$token}' LIMIT 1";

    	return $this->db->result($sql);

    }



    /**

     * 换算游戏币

     * @param

    */

    public function convert($money,$rmbratio = null){

        if($rmbratio === null){

            $redis = initCacheRedis();

            $rmbratio = $redis -> hGet("Config:rmbratio",'value');

            //关闭redis链接

            deinitCacheRedis($redis);

        }



        $newMoney = bcmul($money,$rmbratio,2) ;

        if(strlen($newMoney)>2 && strstr($newMoney,'.')){

            if (substr($newMoney, -2) == '00') {

                $newMoney = substr($newMoney, 0, -3);

            } elseif (substr($newMoney, -2, 1) != '0' && substr($newMoney, -1) == '0') {

                $newMoney = substr($newMoney, 0, -1);

            }

        }



        return $newMoney;

    }



    /**

     * 游戏币换算

     * @param

     */

    public function convert_rmb($money){

        $redis = initCacheRedis();

        $rmbratio = $redis -> HMGet("Config:rmbratio",array('value'));

        $newMoney = round($money / $rmbratio['value'], 2);

        //关闭redis链接

        deinitCacheRedis($redis);

        return $newMoney;

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

    

    /**

     * 刷新缓存

     * @method POST

     * @param $action string 方法名 刷新全部 all

     * @param $param string 参数  刷新全部 all

     * @return array

     */

    protected function refreshRedis2($action, $param) {

        if (empty($action) || empty($param)) {

            return array('status' => 100002, 'data' => " 缺少刷新参数");

        }

        //组装URL

        $url = C('app_home') . "/index.php?m=api&c=initCache&a=index";

        $param = array(

            'pass' => C('pass'),

            'action' => $action,

            'param' => $param

        );

        $result = curl_post_content($url, $param);

//        $result = signa($url, $param);

        return $result;

    }       



    /**

     * 数据查询(慎用，会出现如果条件范围内直属会员不满足，则其下级及时满足条件也不会出现在结果中）

     * @return mixed sql

     */

    public function recursive_query($id,$field='*',$where=''){

        $sql = "SELECT {$field} FROM un_user WHERE parent_id = {$id} {$where}";

        $res = O('model')->db->getAll($sql);

        if($res){

            foreach ($res as $v){

                if($v['id'] == $v['parent_id'])       //避免死循环

                    continue;

                $res_c = $this->recursive_query($v['id'],$field,$where);

                $res = array_merge($res,$res_c);

            }

        }

        return $res;

    }



    /**

     * 获取The key

     * @param $key string The keykey

     * @param $source int 接口来源:1 ios;  2 安卓; 3 H5; 4 PC;

     * @param $project string 项目来源(0:pc手游, 1:讯彩; 2其它;  [待存字典表])

     */

    public function getSecret($key,$source,$project){

        $sql = "SELECT secret_key,status FROM `un_aip_keys` WHERE `key` = '{$key}' AND `type` = 1 AND `source` = {$source} AND `project` = {$project}";

        @file_put_contents('./scripts/api.log', "sql: ".$sql."\n", FILE_APPEND);

        $res = O("model")->db->getone($sql);

        return $res;

    }



    /**

     * 验证签名

     * @param

     * @method

     */

    public function verificationSignature(){

        //当前时间戳

        $nowtime = time();



        //不需要签名的控制器

        $nosigna = array('iyzf','workerman');

        if(in_array(strtolower($_REQUEST['c']),$nosigna)){

            return array('status'=>'success','msg'=>'','code'=>0);

        }



        //不需要签名的方法

        $nosigna = array('getappversion','versionlog');

        if(in_array(strtolower($_REQUEST['a']),$nosigna)){

            return array('status'=>'success','msg'=>'','code'=>0);

        }



        //验证签名

        @file_put_contents('./scripts/api.log', "API: ".json_encode($_REQUEST)."\n", FILE_APPEND);

        if(!$_REQUEST['param']){

            return array('status'=>'fail','msg'=>'Signature failed','code'=>1);

        }

        $param = base64_decode($_REQUEST['param']);

        @file_put_contents('./scripts/api.log', "signa: ".$param."\n", FILE_APPEND);

        $param = json_decode($param,true);

        $timestamp = $param['timestamp'];//时间戳

        $signature = $param['signature'];//签名

        $key = $param['key'];//key

        $source= $param['source'];//接口来源:1 ios;  2 安卓; 3 H5; 4 PC

        $project = $param['project'];//项目来源(0:pc手游, 1:讯彩; 2其它;  [待存字典表])

        $method = strtoupper($param['method']);//POST, GET

        if(!$timestamp){

            @file_put_contents('./scripts/api.log', "error-2: timestamp can't be empty \n", FILE_APPEND);

            return array('status'=>'fail','msg'=>"timestamp can't be empty",'code'=>2);

        }

        if(!$key){

            @file_put_contents('./scripts/api.log', "error-2: secretKey can't be empty \n", FILE_APPEND);

            return array('status'=>'fail','msg'=>"secretKey can't be empty",'code'=>2);

        }

        if(!$signature){

            @file_put_contents('./scripts/api.log', "error-2: signature can't be empty \n", FILE_APPEND);

            return array('status'=>'fail','msg'=>"signature can't be empty",'code'=>2);

        }

        if(abs($nowtime-$timestamp)>300){

            @file_put_contents('./scripts/api.log', "error-3: The signature time must not exceed 5 minutes ".abs($nowtime-$timestamp)." - ".$nowtime ."\n", FILE_APPEND);

            return array('status'=>'fail','msg'=>'The signature time must not exceed 5 minutes','code'=>3);

        }

        $res = $this->getSecret($key,$source,$project);

        if(!$res){

            @file_put_contents('./scripts/api.log', "error-4: The key(".$key.") does not exist\n", FILE_APPEND);

            return array('status'=>'fail','msg'=>"The key($key) does not exist",'code'=>4);

        }

        if((int)$res['status'] !== 0){

            @file_put_contents('./scripts/api.log', "error-5: The key(".$key.") is restricted\n", FILE_APPEND);

            return array('status'=>'fail','msg'=>"The key($key) is restricted",'code'=>5);

        }

        $signature2=md5(md5($timestamp).$res['secret_key']);

        if($signature!=$signature2){

            @file_put_contents('./scripts/api.log', "error-6: Signature failed(".$signature." -- ".$signature2.")\n", FILE_APPEND);

            return array('status'=>'fail','msg'=>'Signature failed','code'=>1);

        }



        //文件处理-图片

        $files = "";

        if($_REQUEST['images']){

            $files = $_REQUEST['images'];

            @file_put_contents('./scripts/api.log', "files: ".$_REQUEST['images']."\n", FILE_APPEND);

        }



        //业务数据

        if($_REQUEST['data']){

            $decrypted = base64_decode(dencrypt($_REQUEST['data'],'DECODE',$signature));

            @file_put_contents('./scripts/api.log', "data-1: ".$decrypted."\n", FILE_APPEND);

//            $pi_key =  openssl_pkey_get_private($res['private_key']);//这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id

//            if(!$pi_key){

//                @file_put_contents('./scripts/api.log', "error-7: 私钥不可用,请联系管理员重新生成秘钥\n", FILE_APPEND);

//                return array('status'=>'fail','msg'=>'私钥不可用,请联系管理员重新生成秘钥','code'=>6);

//            }

//            $decrypted = "";

//            openssl_private_decrypt(base64_decode($_REQUEST['data']),$decrypted,$pi_key);//私钥解密

//            @file_put_contents('./scripts/api.log', "data: ".$decrypted."\n", FILE_APPEND);

//

//            if(empty($decrypted)){

//                return array('status'=>'fail','msg'=>'数据格式有误,无法解析','code'=>7);

//            }

            $data = json_decode($decrypted,true);

            if(!empty($files)){

                $data['images'] = $files;

            }

            $_REQUEST = $data;

            if($method === "POST"){

                $_POST = $data;

            }

            if($method === "GET"){

                $_GET = $data;

            }

        }



        return array('status'=>'success','msg'=>'Signed successfully',);

    }



    /**

     * 获取集中平台数据

    */

    protected function cp(){

        O('mysql', '', 0);

        $config = C('cp');

        $ndb = new mysql($config['db']);

        $ndb->connect()->select_db();

        $sql = "SELECT platform_status FROM `cp_platform` WHERE `id` = '{$config['cp_id']}'";

        $res = $ndb->result($sql);

        return $res;

    }



    /**

     * 公用处理app端传过来的加密数据方法

     */

    public function handle_post () {

        $_REQUEST['json'] = str_replace('\\', '', $_REQUEST['json']);

        $json_arr = json_decode($_REQUEST['json'], true);



        //The key固定为 'pcsy_2017'

        $json_data = new_decrypt($json_arr['data'], 'pcsy_2017');

        return json_decode($json_data, true);

    }

    

    /**

     * 获取团队成员id (不包括自己)

     * @return array 

     */

    public function teamLists($userId)

    {

        $teamIds = [];

        $sql = "SELECT user_id FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},%' ";

        $resArr = O('model')->db->getAll($sql);

        if (!empty($resArr)) {

            $teamIds = array_column($resArr, 'user_id');

        }

        return $teamIds;

    }

    

    //直属会员    包括自己

    /**

     * 获取直属会员id（不包括自己）

     * @param  int $userId

     * @return array

     */

    public function leaguer($userId)

    {

        $leaguerIds = [];

        

        $sql = "SELECT user_id AS id FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},'";

        $resArr = O('model')->db->getAll($sql);

        if (!empty($resArr)) {

                $leaguerIds = array_column($resArr, 'user_id');

        }

        return $leaguerIds;

    }

    

    //直属会员    包括自己

    /**

     * 获取json数据

     * @return array

     */

    public function getJsonData()

    {

       return;

    }

}

