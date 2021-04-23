<?php
/**
 *  action.php
 *
 */
!defined('IN_SNYNI') && die('Access Denied!');
ini_set('max_execution_time','0');
define('REQUEST_METHOD',$_SERVER['REQUEST_METHOD']);
define('IS_GET',        REQUEST_METHOD =='GET' ? true : false);
define('IS_POST',       REQUEST_METHOD =='POST' ? true : false);
define('IS_PUT',        REQUEST_METHOD =='PUT' ? true : false);
define('IS_DELETE',     REQUEST_METHOD =='DELETE' ? true : false);
define('IS_AJAX',       ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || !empty($_POST[C('VAR_AJAX_SUBMIT')]) || !empty($_GET[C('VAR_AJAX_SUBMIT')])) ? true : false);

class Action {

    public $db;
    public $userId = 0;

    public function __construct()
    {

//        //跨域
//        header("Access-Control-Allow-Origin: *");
        //注册时不同域名跨域问题
        $allow_origin=C('allow_origin');
        if(!empty($allow_origin)){
            $origin = isset($_SERVER['HTTP_ORIGIN'])? $_SERVER['HTTP_ORIGIN'] : '';
            lg('allow_origin',var_export(array(
                '$origin'=>$origin,
                '$allow_origin'=>$allow_origin,
            ),1));
            if(in_array($origin, $allow_origin)){
                header('Access-Control-Allow-Origin:'.$origin);
            }
        }

        $this->db = getconn();
        $this->refreshRedis();
        O('session','',0);
        O('cookie','',0);
        session::start();
        $ip = ip();
        $ipBlackInfo = $this->db->getone("select status,url_content from un_ipBlacklist where ip = '".$ip."'");
        if(!empty($ipBlackInfo)){
            if($ipBlackInfo['status'] == 0) {
                $m = $_REQUEST['m'];
                $c = $_REQUEST['c'];
                $a = $_REQUEST['a'];
                if($ipBlackInfo['url_content'] == "*")
                {
                    exit("您没有权限访问本网站");
                }
                else
                {
                    $ipBlackInfo['url_content'] = explode(",",$ipBlackInfo['url_content']);
                    foreach ($ipBlackInfo['url_content'] as $val) {
                        $url = explode("/",$val);
                        if($url[0] == $m && $url['1'] == $c && $url[2] == $a) {
                            $login = url('','lobby','index');

                            header("Location: {$login}");
                        }
                    }
                }
            }
        }
    }

    /**
     * 定义完整URL
     */
    public function URL($url = array('c'=>'index','a'=>'index','param'=>'')){
//        $pageURL = 'http';
//
//        if ($_SERVER["HTTPS"] == "on")
//        {
//            $pageURL .= "s";
//        }
//        $pageURL .= "://";
//		$host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER["SERVER_NAME"] );
//
//        if ($_SERVER["SERVER_PORT"] != "80")
//        {
//            $pageURL .= $host. ":" . $_SERVER["SERVER_PORT"] . '/index.php?m=web&c='.$url['c'].'&a='.$url['a'].$url['param'];
//        }
//        else
//        {
//            $pageURL .= $host . '/index.php?m=web&c='.$url['c'].'&a='.$url['a'].$url['param'] ;
//        }
        $pageURL = url('web',$url['c'],$url['a'].$url['param'] );

        return $pageURL;
    }

    /**
     * 跳转页面
    */
    public function alertMsg($JumpUrl='',$msg = '',$time=4){
        $JumpUrl = empty($JumpUrl)?$this->URL(array('c'=>'user','a'=>'login')):$JumpUrl;
        include template('msg');
    }

    /**
     * 访问地址
     */
    public function getUrl(){
        $JumpUrl = array();
        //个人信息
        $JumpUrl[1] = url('','user','userInfo');
        //钱包
        $JumpUrl[2] = url('','account','index');
        //代理制度
        $JumpUrl[3] = url('','user','agentSystem');
        //代理分享
        $JumpUrl[4] = url('','user','agentSharing');
        //投注记录
        $JumpUrl[5] = url('','order','betRecordWeb');
        //团队管理
        $JumpUrl[6] = url('','user','workTeam');
        //设置
        $JumpUrl[7] = url('','user','setup');
        //大厅
        $JumpUrl[8] = url('','lobby','index');
        //信息列表
        $JumpUrl[9] = url('','message','dataList');
        //天天返利
        $JumpUrl[10] = url('','app','rebate');
        //开奖结果
        $JumpUrl[11] = url('','openAward','openAwardRes');
        //玩法介绍
        $JumpUrl[12] = url('','app','gameList');
        //开奖走势
        //$JumpUrl[13] = url('','openAward','trendWeb');
        $JumpUrl[13] = url('','openAward','trendChart');
        //客服
        $JumpUrl[14] = $this->URL(array('c'=>'app','a'=>'customService'));
        $JumpUrl[14] = url('','account','index');
        //修改昵称
        $JumpUrl[15] = url('','user','setNickname');
        //修改性别
        $JumpUrl[16] = url('','user','setSex');
        //修改中文名
        $JumpUrl[17] = url('','user','setRealname');
        //修改出生日期
        $JumpUrl[18] = url('','user','setBirthday');
        //修改微信号
        $JumpUrl[19] = url('','user','setWeixin');
        //修改电子邮箱
        $JumpUrl[20] = url('','user','setEmail');
        //修改手机号
        $JumpUrl[21] = url('','user','setMobile');
        //保存用户资料
        $JumpUrl[22] = url('','user','setInfo');
        //个人中心
        $JumpUrl[23] = url('','user','my');
        //充值界面
        $JumpUrl[24] = url('','recharge','index');
        //线上充值
        $JumpUrl[25] = url('','recharge','rechargeOnline');
        //线下充值
        $JumpUrl[26] = url('','recharge','offlineIndex');
        //提现
        $JumpUrl[27] = url('','cash','getBankCard');
        //绑定银行卡
        $JumpUrl[28] = url('','bank','bindBank');
        //交易记录
        $JumpUrl[29] = url('','account','billsWeb');
		//房间
        $JumpUrl[30] = url('','chatRoom','index');
        //支付安全
        $JumpUrl[31] = url('','user','setPayWeb');
        //银行卡
        $JumpUrl[32] = url('','bank','getUserBank');
        //设置支付密码
        $JumpUrl[33] = url('','user','setPayWeb');
        //上传头像
        $JumpUrl[50] = url('','user','saveAvatar');
        //会员报表
        $JumpUrl[51] = url('','user','myMemberWeb');
        //团队报表
        $JumpUrl[52] = url('','user','myGroupWeb');
        //自身统计
        $JumpUrl[53] = url('','user','myOneselfWeb');
        //下线开户
        $JumpUrl[54] = url('','user','openAccount');
        //会员报表详情
        $JumpUrl[55] = url('','user','memberDetailWeb');
        //会员报表详情
        $JumpUrl[56] = url('','user','myGroupDetailWeb');
        //博饼活动
        $JumpUrl[57] = url('','activity','boBinIndex');
        //转盘活动
        $JumpUrl[58] = url('','turntable','index');
        //圣诞活动
        $JumpUrl[59] = url('','activity','christmasIndex');
        //我要提现
        $JumpUrl[60] = url('','cash','getBankCard');
        //荣誉等级详情
        $JumpUrl[61] = url('','user','getHonor');
        //修改微信号
        $JumpUrl[62] = url('','user','setQQ');
        //其他
        $JumpUrl[63] = url('','user','activityCenter');
        //活动中心


        //代理报表
        $JumpUrl[64] = url('','user','agentReportForms');
        return $JumpUrl;
    }

    //访问权限控制
    public function checkAuth(){
//        return true;
//        return array('token'=>true);
    	$token = session_id();
    	//验证token
    	$userId = $this->getUserIdByToken($token);
    	if(!$userId || empty(session::get('username')))
    	{
            $login = url('','user','login');
//            $this->alertMsg($login,'用户信息验证失败,请重新登录!');
            header("Location: {$login}");
    		exit;
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
        return array('token'=>true);
    }

    //检查参数完整性
    public function checkInput($input,$need,$verify=array())
    {

        //注册时不同域名跨域问题
        header("Access-Control-Allow-Origin: *");

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
    public function setToken($userId, $ipData = [])
    {
    	//删除之前的token信息
    	$this->db->delete('#@_session', array('user_id' => $userId));

    	//生成token
    	$token = session_id();

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
    	);

    	$this->db->replace('#@_session',$data);

    	return $token;
    }

    //退出
    public function clearToken()
    {
        /*$user = Session::get("admin");
        if(!empty($user['userid'])){
            $this->db->delete("un_session",['user_id'=>$user['userid'],"is_admin"=>1]);
        }
        Session::del('admin');*/

    	//删除token信息
    	$this->db->delete('#@_session', array('user_id' => $this->userId));

        if(session::get('rember')==1){
            cookie::del('name');
            cookie::del('pwd');
        }
        session::clearFore();
    }

    //根据token获取userId
    private function getUserIdByToken($token)
    {
    	$sql = "SELECT user_id FROM #@_session WHERE sessionid = '{$token}' LIMIT 1";
    	return $this->db->result($sql);
    }
    /**
     * Ajax方式返回数据到客户端
     * @param mixed $data 要返回的数据
     * @param mixed $info 要返回的信息描述
     * @param mixed $status 要返回的状态码
     * @return json
     */
    public function ajaxReturn($data,$info='',$status=200){
        $map = array();
        $map['data']   =   $data;
        $map['info']   =   $info;
        $map['status'] =   $status;
        // 返回JSON数据格式到客户端 包含状态信息
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($map));
    }

    /**
     * 换算游戏币
     * @param
     */
    public function convert($money){
        $redis = initCacheRedis();
        $rmbratio = $redis -> HMGet("Config:rmbratio",array('value'));
        $newMoney = bcmul($money,$rmbratio['value'],2) ;
        //关闭redis链接
        deinitCacheRedis($redis);
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
     * 数据查询
     * @return mixed sql
     */
    public function recursive_query($id,$field='*',$where=''){
        $sql = "SELECT {$field} FROM un_user WHERE parent_id = {$id} {$where}";
        $res = O('model')->db->getAll($sql);
        if($res){
            foreach ($res as $v){
                if($v['uid'] == $v['parent_id'])            //避免死循环
                    continue;
                $res_c = $this->recursive_query($v['id'],$field,$where);
                $res = array_merge($res,$res_c);
            }
        }
        return $res;
    }

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
     * 按中奖概率数据来计算出中奖奖品
     * @param array $rate_arr 一维数组，并且所有数组元素值相加为100，如[10,20,30,40]
     * @return mixed 返回原数组的下标值
     * 2017-09-16
     */
    public function cal_bingo_prize ($rate_arr) {
        $bingo_key = 0;
        $rand_num = mt_rand(1, 100);

        $tmp_rate_side = 0;
        $new_rate_arr = [];

        //按值划分数据段
        foreach ($rate_arr as $k => $v) {
            $tmp_rate_side += $v;
            $new_rate_arr[$k] = $tmp_rate_side;
        }

        //如果这个随机在段内，则返回key值
        foreach ($new_rate_arr as $k2 => $v2) {
            if ($rand_num <= $v2) {
                $bingo_key = $k2;
                break;
            }
        }
        return $bingo_key;
    }

    /**
     * 提示框信息页面
     * @param string $tips  提示框显示信息
     * @param string $url   提示信息后指定跳转页面
     * @param int $type 提示框类型，1：返回上一页面，2：跳转到url指定页面
     */
    public function prompt_box($tips = '', $url = '',$type = 1)
    {
        if (empty($tips)) {
            $tips = '操作非法！！';
        }
        include template("prompt_box");
        exit;
    }

}
