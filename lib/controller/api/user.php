<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/11
 * Time: 9:43
 * desc: 用户信息
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class UserAction extends Action
{

    private $model;
    private $model2;
    private $model3;
    private $betRankModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = D('user');
        $this->model2 = D('account');
        $this->model3 = D('userlog');
        $this->betRankModel = D('betrank');

        $redis = initCacheRedis();//初始化redis
        $redis->set("mac",$_REQUEST['code']);
        $mac = $redis->get("mac");
        $resss = $this->model->isIpBlack($mac,$_REQUEST['m'],$_REQUEST['c'],$_REQUEST['a']);
        if($resss == false) {
            ErrorCode::errorResponse(ErrorCode::DEFAULT_MSG,"Sorry! You don't have enough permissions");
        }
        deinitCacheRedis($redis);//关闭redis
    }

    public  function opLog($type){
        //记录操作日志
        $ip = ip();
        $sql  = "INSERT INTO `un_user_change_log` (`user_id`, `record_id`, `type`, `tab`, `change_name`, `ip`, `addtime`) VALUES ('{$this->userId}', '{$this->userId}', $type,'un_user', '本人', '{$ip}', '".time()."')";
        $this->db->query($sql);

    }
    

    /**
     * 游客登录
     * @method POST  /index.php?m = api&c = user&a = registerMachine
     * @param flag string 入口标示
     * @param code string 机身码
     * @return json
     */
    public function registerMachine ()
    {
        //注册时不同域名跨域问题
        header("Access-Control-Allow-Origin: *");
        $flag = trim($_REQUEST['flag']);
        $code = trim($_REQUEST['code']);
        if ($flag == 4) {
            $this->checkInput($_REQUEST, array('flag'), 'all');
        }else {
            $this->checkInput($_REQUEST, array('flag', 'code'), 'all');
        }
        
        $username = $this->getUsername(6,8);

        $prefix = $this->db->getone("select value from un_config where nid = 'tourist'");
        //添加用户
        
        //获取IP地址及ip归属地
        $ipData = getIp();

        //生成随机头像
        $random_avatar = D('avatar')->fetchRandomPic();

        $data = array(
            'username' => $username,
            // 'nickname' => $prefix['value'].rand(10000,99999),
            'nickname' => $username,
            'regtime' => SYS_TIME,
            'birthday' => SYS_TIME,
            'regip' => $ipData['ip'],
            'reg_ip_attribution' => $ipData['attribution'],
            'loginip' => $ipData['ip'],
            'login_ip_attribution' => $ipData['attribution'],
            'logintime' => SYS_TIME,
            'logintimes' => 1,
            'reg_type' => 8,
            'entrance' => $flag,
            //随机一个默认头像
            'avatar' => $random_avatar,
        );

        $userId = $this->model->add($data);

        if (!$userId) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }

        //添加资金账户
        $map = array(
            'user_id' => $userId,
            'money' => 2000
        );
        $this->model2->add($map);
        $this->loginLog($userId, $flag, $code);
        //设置登录信息
        if (!empty($parentId)) {
            ErrorCode::successResponse();
        }
        $token = $this->setToken($userId,$code);
        $data = array(
            'uid' => $userId,
            'token' => $token,
            'username' => $username,
            'nickname' => $data['nickname'],
            // 'avatar' => '/up_files/room/avatar.png'
            //随机一个默认头像
            'avatar' => $random_avatar,
        );
        /*
        $honor = get_honor_level($userId);
        if(($honor['status1'] && $honor['status']) || ($honor['status'] && $honor['score']==0)){
            $data['honor'] = $honor['name'];
            $data['icon'] = $honor['icon'];
            $data['num'] = $honor['num'];
        }else{
            $data['honor'] = 0;
        }
        */

        //荣誉机制
        $data['honor'] = get_honor_info($userId);

        ErrorCode::successResponse($data);
    }

    /**
     * 第三方登录 qq 微信
     * @method POST  /index.php?m = api&c = user&a = registerMachine
     * @param flag string 入口标示
     * @param code string 机身码
     * @return json
     */
    public function thirdPartyLogin (){

        log_to_mysql(runtime(),'thirdPartyLogin_start');

        $this->checkInput($_REQUEST, array('openid','nickname','type','flag', 'code'), 'all');


        log_to_mysql(runtime(),'thirdPartyLogin_check_params_end');

        $openid = trim($_REQUEST['openid']);
        $nickname = trim($_REQUEST['nickname']);
        $avatar = trim($_REQUEST['avatar']);
        $type = trim($_REQUEST['type']);
        $flag = trim($_REQUEST['flag']);
        $code = trim($_REQUEST['code']);
        if(!in_array($type,array(5,6,7))){
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }
        
        //获取IP地址及ip归属地
        $ipData = getIp();
        log_to_mysql(runtime(),'thirdPartyLogin_getip_end');

        $sql = "SELECT user_id FROM `un_user_third` WHERE `openid` = '{$openid}' AND `type` = '{$type}'";
        $res = O('model')->db->getOne($sql);
        log_to_mysql(runtime(),'thirdPartyLogin_checkOpenidExists_end');

        if(empty($res['user_id'])){
            $username = $this->getUsername(6,10);
            //添加用户
            $data = array(
                'username' => $username,
                'nickname' => $nickname,
                'regtime' => SYS_TIME,
                'birthday' => SYS_TIME,
                'regip' => $ipData['ip'],
                'reg_ip_attribution' => $ipData['attribution'],
                'loginip' => $ipData['ip'],
                'login_ip_attribution' => $ipData['attribution'],
                'logintime' => SYS_TIME,
                'logintimes' => 1,
                'reg_type' => $type,
                'entrance' => $flag,
                'layer_id' => $this->model2->getDefaultLayer()
            );

            $userId = $this->model->add($data);

            if (!$userId) {
                ErrorCode::errorResponse(ErrorCode::DB_ERROR);
            }

            //添加资金账户
            $map = array(
                'user_id' => $userId,
                'money' => 0
            );
            $this->model2->add($map);

            O('model')->db->query("INSERT INTO `un_user_tree` (`user_id`, `pids`, `layer`) VALUES ({$userId}, ',', 1)");

            //添加第三方数据表记录
            $sql2 = "INSERT INTO `un_user_third` (`user_id`, `openid`, `type`, `addtime`) VALUES ('{$userId}', '{$openid}', '{$type}', '{$data['regtime']}')";
            O('model')->db->query($sql2);

            //下载头像
            if(!empty($avatar)){
                $res = $this->_downloadAvatarFromThird($userId, $avatar);
            }

            //设置登录信息
            $this->loginLog($userId, $flag, $code);

            $token = $this->setToken($userId,$code);
            $data = array(
                'uid' => $userId,
                'token' => $token,
                'username' => $username,
                'nickname' => $nickname,
                'avatar' => $res?$res:'/up_files/room/avatar.png',
                'state' => 1
            );
        }else{
            $userId = $res['user_id'];
            $sql = "SELECT id,username,nickname,avatar,password FROM un_user WHERE id = '" . $userId ."' AND state IN(0,1)";
            $userInfo = O('model')->db->getOne($sql);

            log_to_mysql(runtime(),'thirdPartyLogin_getUserInfo_end');

            if (empty($userInfo)) {
                ErrorCode::errorResponse(ErrorCode::PHONE_OR_PWD_INVALID);
            }
            //更新登录信息
            $this->model->updateLoginInfo($userId);
            log_to_mysql(runtime(),'thirdPartyLogin_updateLogData_end');

            //去掉更新设备，这里更新的设备字段，为注册设备，最后登录设备已记录在 un_user_login_log 表
            // $this->model->save(array('entrance' => $flag), array('id' => $userId)); //更新用户设备登录类型

            //设置登录信息
            $token = $this->setToken($userId,$code);


            log_to_mysql(runtime(),'thirdPartyLogin_setToken_end');

            $this->loginLog($userId, $flag, $code);

            log_to_mysql(runtime(),'thirdPartyLogin_logLoginData_end');

            $data = array(
                'uid' => $userId,
                'token' => $token,
                'username' => $userInfo['username'],
                'nickname' => empty($userInfo['nickname']) ? $userInfo['username'] : $userInfo['nickname'],
                'avatar' => empty($userInfo['avatar']) ? '/up_files/room/avatar.png' : $userInfo['avatar'],
                'state' => empty($userInfo['password']) ?1:2
            );
        }
        
        /*
        $honor = get_honor_level($userId);
        if(($honor['status1'] && $honor['status']) || ($honor['status'] && $honor['score']==0)){
            $data['honor'] = $honor['name'];
            $data['icon'] = $honor['icon'];
            $data['num'] = $honor['num'];
        }else{
            $data['honor'] = 0;
        }
        */
        
        //荣誉机制
        $data['honor'] = get_honor_info($userId);

        log_to_mysql(runtime(),'thirdPartyLogin_getHonor_end');

        ErrorCode::successResponse($data);
    }

    // 下载第三方头像
    private function _downloadAvatarFromThird($userid,$thirdAvatarUrl,$flag=false) {
        $curl = curl_init($thirdAvatarUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $imageData = curl_exec($curl);
        curl_close($curl);

        $avatarFileName = $this->getAvatarFilename($userid, 'jpg');
        $avatarUrl = $this->getAvatarUrl($avatarFileName, 0);
        if($flag){
            $res = $this->model->getUserInfo('avatar', array('id' => $userid), 1);
            if ($res['avatar']) {
                $oldPath = $this->getAvatarPath($res['avatar']);
                @unlink($oldPath);
            }
        }

        $res = file_put_contents($avatarUrl, $imageData);
        if (!$res) {
            return false;
            //ErrorCode::errorResponse(100023, '头像加载失败');
        }
        $data = array('avatar' => '/' . C('upfile_path') . '/avatar/' . $avatarFileName);

        $this->model->save($data, array('id' => $userid));
        return $data['avatar'];
    }
    
    /**
     * 获取注册项配置信息
     * @method POST
     * @return json
     */
    public function getRegister()
    {
        $registerData = [];

        //注册项配置
        $registerText = ['weixin' => '微信号', 'qq' => 'QQ', 'mobile' => '电话号码', 'email' => '邮箱','register'=>'验证码'];
        $dataType = ['weixin' => '*', 'qq' => 'n', 'mobile' => 'm', 'email' => 'e' , 'register'=>'r'];  //Validform验证使用
        $registerJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'set_register_info'");
        $register = json_decode($registerJson['value'],true);
        $registerSetData = $register['register'];
        foreach ($registerSetData as $kr => $vr) {
            if ($vr == 1) {
                $registerData[] = $kr;
            }
        }
        
        $data['registerInfo'] = $registerData;

        ErrorCode::successResponse($data);
    }

    /**
     * 用户注册
     * @method POST  /index.php?m = api&c = user&a = reg&username = wangrui&password = aa112233&password2 = aa112233
     * @param token string 用户token
     * @param username string 账户
     * @param password string 密码
     * @param password2 string 确认密码
     * @param referrer string 推荐人账户
     * @param flag string 入口标示
     * @param code string 机身码
     * @return json
     */
    public function reg($parentId = '', $type = true)
    {
        session_start();
        //$this->checkInput($_REQUEST, $registerData, 'all');
        $this->checkInput($_REQUEST, array('username', 'password', 'password2', 'flag','type'), 'all');
        //检查用户是否存在
        $sql = "SELECT id FROM `un_user` WHERE username='{$_REQUEST['username']}'";
        if(!empty($this->db->getone($sql))){
            ErrorCode::errorResponse(ErrorCode::PWD_DIFFERENT, 'Account already exists');
            return 0;
        }
        $param = array_map('deal_array', $_POST);
        $username  = trim($param['username']);
        $password  = trim($param['password']);
        $password2 = trim($param['password2']);
        $flag      = trim($_REQUEST['flag']);
        $code      = trim($_REQUEST['code']);
        $regType   = trim($_REQUEST['type']);
        $register_code  = md5(strtoupper(trim($_REQUEST['register'])));
        //注册配置项
        $weixin    = isset($param['weixin']) ? trim($param['weixin']) : '';
        $qq        = isset($param['qq']) ? trim($param['qq']) : '';
        $mobile    = isset($param['mobile']) ? trim($param['mobile']) : '';
        $email     = isset($param['email']) ? trim($param['email']) : '';
        $limit = [];
        $limit["register_limit"] = 100;
        $limit["register_times"] = 100;
        $registerData = array('username', 'password', 'password2', 'flag','type');
        
        //下级注册时，不使用注册项配置
        if ($type) {
            //注册项配置
            $registerJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'set_register_info'");
            $register = json_decode($registerJson['value'],true);
            $registerSetData = $register['register'];
            $is_limit = true;
            $limit = $register["limit"];
            if(empty($limit["register_times"])) {
                $is_limit = false;
                $limit["register_times"] = 1;
            }
            foreach ($registerSetData as $kr => $vr) {
                if ($vr == 1) {
                    $registerData[] = $kr;
                    if ($register['status'] == 1) {
                        if (empty($param[$kr])) {
                            $registerText = ['weixin' => 'We chat number', 'qq' => 'QQ', 'mobile' => 'Mobile number', 'email' => 'Email','register'=>'Verification code'];
                            ErrorCode::errorResponse(1720, $registerText[$kr] . 'can not be empty');
                        }
                    }
                }
            }
            
            foreach ($registerData as $va) {
                switch ($va)
                {
                    case 'username':
                        if ($this->model->getUserInfo('username', array('username' => $username), '', '', '', true) || preg_match('/.*(script).*/', $username)) {
                            ErrorCode::errorResponse(ErrorCode::USER_HAS_EXISTS, 'Username already exists');
                        }
            
                        if (!preg_match('/^[a-zA-Z0-9]{6,15}$/', $username)) {
                            ErrorCode::errorResponse(ErrorCode::USER_FORMAT_WRONG, 'User name is limited to English letters and numbers, 6 to 15 characters');
                        }
                        break;
                    case 'password':
                        if (!preg_match('/^[a-zA-Z0-9_]{6,15}$/', $password)) {
                            ErrorCode::errorResponse(ErrorCode::PWD_FORMAT_WRONG, 'The password is limited to English letters, numbers and underscores, 6 to 15 characters');
                        }
            
                        if ($password != $password2) {
                            ErrorCode::errorResponse(ErrorCode::PWD_DIFFERENT, 'Two password entries are inconsistent');
                        }
                        break;
//                    case 'weixin':
//                        if (!empty($weixin) && !preg_match('/^[a-zA-Z]{1}[a-zA-Z0-9_]+$/', $weixin)) {
//                            ErrorCode::errorResponse(1717, '微信号格式错误');
//                        }
//                        break;
//                    case 'qq':
//                        if (!empty($qq) && !preg_match('/^[1-9]{1}[0-9]{4,14}$/', $qq)) {
//                            ErrorCode::errorResponse(1716, 'QQ号格式错误');
//                        }
//                        break;
//                    case 'mobile':
//                        if (!empty($mobile) && !preg_match('/^[0-9]{11}$/', $mobile)) {
//                            ErrorCode::errorResponse(1719, '手机号码错误');
//                        }
//                        break;
                    case 'email':
                        if (!empty($email) && !preg_match('/^([_a-z0-9-]+)(\.[_a-z0-9-]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', $email)) {
                            ErrorCode::errorResponse(1718, 'Email format error');
                        }
                        break;
                    default:
                }
            }
        }

        @$session_code = $this->getCheckCode();
        if(isset($_REQUEST['register'])&&$_REQUEST['register']!=""&&$register_code!=$session_code) {
            ErrorCode::errorResponse(1720, "Verification code error");
        }

        if(isset($_SESSION["register_limits"])&&is_array($_SESSION["register_limits"])&&$is_limit){
            $dif = time() - 60*$limit["register_limit"];
            if($_SESSION["register_limits"]["time"] < $dif) {
                $_SESSION["register_limits"]["time"] = time();
                $_SESSION["register_limits"]["times"] = 1;
            }else $_SESSION["register_limits"]["times"]++;

            if($_SESSION["register_limits"]["times"]>$limit["register_times"]){
                ErrorCode::errorResponse(ErrorCode::PWD_DIFFERENT, 'Do not register repeatedly');
            }
        }else{
            $_SESSION["register_limits"]=[];
            $_SESSION["register_limits"]["time"] = time();
            $_SESSION["register_limits"]["times"]= 1;
        }

        //获取IP地址及ip归属地
        $ipData = getIp();
        
        //随机一个默认头像
        $avatar = D('Avatar')->fetchRandomPic();
        
        //添加用户
        $data = array(
            'username' => $username,
            'password' => md5($password),
            // 'nickname' => "第".rand(10000,99999)."位用户",
            //'nickname' => $username,
            'nickname' => $this->getLetter(2).mt_rand(100000,999999),
            'regtime'  => SYS_TIME,
            'birthday' => SYS_TIME,
            'mobile'   => $mobile,
            'weixin'   => $weixin,
            'qq'       => $qq,
            'email'    => $email,
            'regip'    => $ipData['ip'],
            'reg_ip_attribution' => $ipData['attribution'],
            'loginip'  => $ipData['ip'],
            'login_ip_attribution' => $ipData['attribution'],
            'logintime'  => SYS_TIME,
            'logintimes' => 1,
            'reg_type' => $regType,
            'entrance' => $flag,
            'layer_id' => $this->model2->getDefaultLayer(),
        
            //注册时，也添加最后登录域名（h5端），统一用HTTP_HOST，不用SERVER_NAME
            'source' => $_SERVER['HTTP_HOST'],
            'last_login_source' => $_SERVER['HTTP_HOST'],
            'avatar' => $avatar,
        );

        if (isset($_REQUEST['referrer']) && !empty($_REQUEST['referrer'])) {
            $field = 'id';
            $pid = $this->model->getUserInfo($field, array('username' => trim($_REQUEST['referrer'])), 1);
            if (empty($pid)) {
                ErrorCode::errorResponse(100022, 'Referrer does not exist');
            }
            if($regType == 3){
                $data['share_id'] = $pid['id'];
            }else{
                $data['parent_id'] = $pid['id'];
            }
        }
        if (!empty($parentId)) {
            $data['parent_id'] = $parentId;
        }

        $userId = $this->model->add($data);

        if (!empty($qq)) {
            D('admin/activity')->taskSuccess(2, $userId);
        }
        if (!empty($weixin)) {
            D('admin/activity')->taskSuccess(3, $userId);
        }
        if (!empty($email)) {
            D('admin/activity')->taskSuccess(4, $userId);
        }
        
        if (!$userId) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }

        //添加资金账户
        $map = array(
            'user_id' => $userId
        );
        $this->model2->add($map);
        if(isset($data['parent_id']) && !empty($data['parent_id'])){
            $res = O('model')->db->getOne("SELECT pids,layer FROM `un_user_tree` WHERE `user_id` = {$data['parent_id']}");
            if(!isset($res['pids']) || !$res['pids']) $res['pids'] = ',';
            $parent = $res['pids'].$data['parent_id'].',';
            $layer = $res['layer']+1;
        }else{
            $parent = ',';
            $layer = 1;
        }

        O('model')->db->query("INSERT INTO `un_user_tree` (`user_id`, `pids`, `layer`) VALUES ({$userId}, '".$parent."', {$layer})");
        $this->loginLogs($userId, $flag, $code, $ipData);
        //设置登录信息
        if (!empty($parentId)) {
            ErrorCode::successResponse();
        }
        $token = $this->setToken($userId,$code);
        $data = array(
            'uid' => $userId,
            'token' => $token,
            'username' => $username,
            'nickname' => $data['nickname'],
            
            //随机一个默认头像
            // 'avatar' => '/up_files/room/avatar.png'
            'avatar' => $avatar,
        );
        
        //荣誉机制
        $data['honor'] = get_honor_info($userId);
        unset($_SESSION["register_code"]);
        ErrorCode::successResponse($data);
    }


    public function betAmount(){
        $this->checkInput($_REQUEST, ['uid','token'], 'all');
        $param = array_map('deal_array', $_POST);

        $sql = "SELECT count(*) FROM `un_session` WHERE `user_id` = '{$param['uid']}' AND `sessionid` = '{$param['token']}'";
        $res = O('model')->db->getOne($sql);
        if($res["count(*)"]>0){
            $arr = O('model')->db->getOne("select value from un_config where nid = 'cashLimit'");
            $nRt = $arr["value"];
            $arr = O('model')->db->getOne("select ifnull(sum(money),0) as money from un_orders where user_id = {$param['uid']} AND award_state>0 AND state=0");
            $betRt = $arr["money"];
            $arr = O('model')->db->getOne("select bet_amount from `un_user` where `id` = {$param['uid']}");
            $bet_amount = $arr["bet_amount"];
            $betRt+=$bet_amount;
            $arr  = O('model')->db->getOne("select ifnull(sum(money),0) as money from un_account_recharge where user_id = {$this->userId} and status = 1");
            $regRt = $arr["money"];

            $total = $regRt * $nRt - $betRt;
            if($total<0) $total = 0;

            $data=[];
            $data["total"] = $total;
            ErrorCode::errorResponse($data);

        }

        ErrorCode::errorResponse(ErrorCode::INVALID_TOKEN);
    }

    private function getCheckCode(){
        @$dif_ip = $_SERVER['REMOTE_ADDR'];
        $where = "dif_ip = '$dif_ip'";
        $server_code = $this->db->getone("SELECT `check_code` FROM `un_outside_check` WHERE $where ORDER BY id DESC");
        $server_code = $server_code["check_code"];
        $current_time = time() - 300;
        $where = "time < $current_time";
        $this->db->query("DElETE FROM `un_outside_check` WHERE $where");
        return $server_code;
    }
/*
    public function reg($parentId = '')
    {
        //接收参数
        $this->checkInput($_REQUEST, array('username', 'password', 'password2', 'flag', 'code', 'type'), 'all');
        $param = array_map('deal_array', $_POST);
        // var_dump($param);
        $flag      = trim($_REQUEST['flag']);
        $code      = trim($_REQUEST['code']);
        $regType   = trim($_REQUEST['type']);
        $username  = trim($param['username']);
        $password  = trim($param['password']);
        $password2 = trim($param['password2']);
    
        //        $domain = trim($_REQUEST['domain']);
    
        //验证参数
         */
        //if ($this->model->getUserInfo('username', array('username' => $username), '', '', '', true) || preg_match('/.*(script).*/', $username)) {
        //    ErrorCode::errorResponse(ErrorCode::USER_HAS_EXISTS, 'Username already exists');
       // }
       /*
        if (!preg_match('/^[a-zA-Z0-9]{6,15}$/', $username)) {
            ErrorCode::errorResponse(ErrorCode::USER_FORMAT_WRONG, 'User name is limited to English letters and numbers, 6 to 15 characters');
        }
        if (!preg_match('/^[a-zA-Z0-9_]{6,15}$/', $password)) {
            ErrorCode::errorResponse(ErrorCode::PWD_FORMAT_WRONG, 'The password is limited to English letters, numbers and underscores, 6 to 15 characters');
        }
        if ($password != $password2) {
            ErrorCode::errorResponse(ErrorCode::PWD_DIFFERENT, 'Two password entries are inconsistent');
        }
    
        //获取IP地址及ip归属地
        $ipData = getIp();
    
        //添加随机头像
        $random_avatar = D('Avatar')->fetchRandomPic();
    
        //添加用户
        $data = array(
            'username' => $username,
            'password' => md5($password),
            // 'nickname' => "第".rand(10000,99999)."位用户",
            //'nickname' => $username,
            'nickname' => $this->getLetter(2).mt_rand(100000,999999),
            'regtime' => SYS_TIME,
            'birthday' => SYS_TIME,
            'regip' => $ipData['ip'],
            'reg_ip_attribution' => $ipData['attribution'],
            'loginip' => $ipData['ip'],
            'login_ip_attribution' => $ipData['attribution'],
            'logintime' => SYS_TIME,
            'logintimes' => 1,
            'reg_type' => $regType,
            'entrance' => $flag,
            'layer_id' => $this->model2->getDefaultLayer(),
    
            //添加随机头像
            'avatar' => $random_avatar,
    
            //注册时，也添加最后登录域名（app端）
            'last_login_source' => $_SERVER['HTTP_HOST'],
            //            'last_login_source' => $domain,
            'source' => $_SERVER['HTTP_HOST'],
        );
    
        if (isset($_REQUEST['referrer']) && !empty($_REQUEST['referrer'])) {
            $field = 'id';
            $pid = $this->model->getUserInfo($field, array('username' => trim($_REQUEST['referrer'])), 1);
            if (empty($pid)) {
                ErrorCode::errorResponse(100022, '推荐人不存在');
            }
            if($regType == 3){
                $data['share_id'] = $pid['id'];
            }else{
                $data['parent_id'] = $pid['id'];
            }
        }
        if (!empty($parentId)) {
            $data['parent_id'] = $parentId;
        }
    
        $userId = $this->model->add($data);
    
        if (!$userId) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }
    
        //添加资金账户
        $map = array(
            'user_id' => $userId
        );
        $this->model2->add($map);
        if(isset($data['parent_id']) && !empty($data['parent_id'])){
            $res = O('model')->db->getOne("SELECT pids,layer FROM `un_user_tree` WHERE `user_id` = {$data['parent_id']}");
            $parent = $res['pids'].$data['parent_id'].',';
            $layer = $res['layer']+1;
        }else{
            $parent = ',';
            $layer = 1;
        }
        O('model')->db->query("INSERT INTO `un_user_tree` (`user_id`, `pids`, `layer`) VALUES ({$userId}, '".$parent."', {$layer})");
        $this->loginLogs($userId, $flag, $code, $ipData);
        //设置登录信息
        if (!empty($parentId)) {
            ErrorCode::successResponse();
        }
        $token = $this->setToken($userId,$code);
        $data = array(
            'uid' => $userId,
            'token' => $token,
            'username' => $username,
            'nickname' => $data['nickname'],
    
            //随机一个默认头像
            // 'avatar' => '/up_files/room/avatar.png'
            'avatar' => $random_avatar,
        );
    
        //荣誉机制
        $data['honor'] = get_honor_info($userId);
    
        ErrorCode::successResponse($data);
    }
    */
    
    /**
     * 用户登录
     * @method POST  /index.php?m = api&c = user&a = login&username = wangrui&password = aa112233
     * @param token string 用户token
     * @param username string 账户
     * @param password string 密码
     * @param flag string 入口标示
     * @param code string 机身码
     * @return json
     */
    public function login()
    {
        //var_dump($_REQUEST);
        //接收参数
        $this->checkInput($_REQUEST, array('username', 'password', 'flag', 'code'), 'all');
        //$username = strtolower(trim($_REQUEST['username']));
        $username = trim($_REQUEST['username']);
        $password = trim($_REQUEST['password']);
        $flag = trim($_REQUEST['flag']);
        $code = trim($_REQUEST['code']);

        //验证账号密码
        if(!empty(C('db_port'))) { //使用mycat时 查主库数据
            $sql = "/*#mycat:db_type=master*/ SELECT id,nickname,avatar,reg_type,email FROM un_user WHERE username = '" . $username . "' AND password = '" . md5($password) . "' AND state IN(0,1)";
        }else{
            $sql = "SELECT id,nickname,avatar,reg_type,email FROM un_user WHERE username = '" . $username . "' AND password = '" . md5($password) . "' AND state IN(0,1)";
        }
        $userInfo = O('model')->db->getOne($sql);

        if (empty($userInfo)) {
            ErrorCode::errorResponse(ErrorCode::PHONE_OR_PWD_INVALID);
        }

        $userId = $userInfo['id'];

        $reg_type = $userInfo['reg_type'];
        session::set('reg_type', $reg_type);
        
        //获取IP地址及ip归属地
        $ipData = getIp();

        //如果是讯彩用户登录，则同步登录信息到中间站，传入用户ID并设置在线状态为1
        if ($reg_type == 10) {
            $post_data = array(
                'pcsy_online_status' => 1,
                'pcsy_id' => $userId,
            );
            //TODO: 按需，建立访问API失败后的重试机制
            $ret_data = curl_post_content(C('transfer_site_set_status'), $post_data);

            //当中间站code值返回-2时，表示讯彩端正在投注中，不能登录
            if ($ret_data['code'] == -2) {
                ErrorCode::errorResponse($ret_data['code'], $ret_data['msg']);
            }

//            //将讯彩端的游戏积分带入PC手游端，写入日志
//            $xc_point = $ret_data['xc_point'];
//            $remark = '讯彩用户登录，带入讯彩端积分';
//            //$ip = ip();
//            $sys_time = SYS_TIME;
//            $insert_sql = "INSERT INTO `un_xc_account_log` (`user_id`, `money`, `use_money`, `remark`, `addtime`, `addip`, `addip_attribution`) VALUES ('{$userId}', '{$xc_point}', '{$xc_point}', '{$remark}', '{$sys_time}', '{$ipData['ip']}', '{$ipData['attribution']}')";
//            O('model')->db->query($insert_sql);
//
//            //更新PC手游端的积分数据，在原有积分上，加上讯彩端带过来的积分
//            $update_sql = "UPDATE `un_account` SET `money` = `money` + '{$xc_point}' WHERE `user_id` = '{$userId}' ";
//            O('model')->db->query($update_sql);
        }

        //更新登录信息
        $this->model->updateLoginInfos($userId, $ipData);

        //去掉更新设备，这里更新的设备字段，为注册设备，最后登录设备已记录在 un_user_login_log 表
        // $this->model->save(array('entrance' => $flag), array('id' => $userId)); //更新用户设备登录类型

        loseScore($userId);  //扣除积分
        //设置登录信息
        $token = $this->setToken($userId,$code, $ipData);
        $this->loginLogs($userId, $flag, $code, $ipData);
        

        $data = array(
            'uid' => $userId,
            'token' => $token,
            'email' => $userInfo['email'],
            'username' => $username,
            'nickname' => empty($userInfo['nickname']) ? "" : $userInfo['nickname'],
            'avatar' => empty($userInfo['avatar']) ? '/up_files/room/avatar.png' : $userInfo['avatar']
        );
        
        /*
        $honor = get_honor_level($userId);
        if(($honor['status1'] && $honor['status']) || ($honor['status'] && $honor['score']==0)){
            $data['honor'] = $honor['name'];
            $data['icon'] = $honor['icon'];
            $data['num'] = $honor['num'];
        }else{
            $data['honor'] = 0;
        }
        */
        
        //荣誉机制
        $data['honor'] = get_honor_info($userId);
        
        ErrorCode::successResponse($data);

    }

    /**
     * 用户登出
     * @method POST  /index.php?m = api&c = user&a = logout&token = 61f6325d80723fc94dc73c705bdb240d
     * @param token string 用户token
     * @return json
     */
    public function logout()
    {
        //验证token
        $this->checkAuth();

        $reg_type=O('model')->db->getOne('select reg_type from un_user where id='.$this->userId)['reg_type'];
        if($reg_type==8){
            //用户表
            $sql_1='delete from un_user where id ='.$this->userId;
            $this->db->query($sql_1);
            //资金表
            $sql='delete from un_account where user_id ='.$this->userId;
            $this->db->query($sql);
            //提现表
            $sql='delete from un_account_cash where user_id ='.$this->userId;
            $this->db->query($sql);
            //充值表
            $sql='delete from un_account_recharge where user_id ='.$this->userId;
            $this->db->query($sql);
            //资金交易明细表
            $sql='delete from un_account_log where user_id ='.$this->userId;
            $this->db->query($sql);
            //返水表
            $sql='delete from un_back_log where user_id ='.$this->userId;
            $this->db->query($sql);
            //客服聊天记录表
            $sql='delete from un_custom where user_id ='.$this->userId;
            $this->db->query($sql);
            //禁言表
            $sql='delete from un_gag where user_id ='.$this->userId;
            $this->db->query($sql);
            //站内信表
            $sql='delete from un_message where user_id ='.$this->userId;
            $this->db->query($sql);
            //订单表
            $sql='delete from un_orders where user_id ='.$this->userId;
            $this->db->query($sql);
            //session表
            $sql='delete from un_session where user_id ='.$this->userId;
            $this->db->query($sql);
            //天天返利表
            $sql='delete from un_ttfl_log where user_id ='.$this->userId;
            $this->db->query($sql);
            //用户银行卡表
            $sql='delete from un_user_bank where user_id ='.$this->userId;
            $this->db->query($sql);
            //用户登录日志表
            $sql='delete from un_user_login_log where user_id ='.$this->userId;
            $this->db->query($sql);
            //第三方登录表
            $sql='delete from un_user_third where user_id ='.$this->userId;
            $this->db->query($sql);
            //白名单表
            $sql='delete from un_whitelist where user_id ='.$this->userId;
            $this->db->query($sql);
            //系统审核表
            $sql='delete from un_xitongshenghe where user_id ='.$this->userId;
            $this->db->query($sql);
            //系统审核表
            $sql='delete from un_xitongshenghe where user_id ='.$this->userId;
            $this->db->query($sql);
        }

        //退出
        $token = $this->clearToken();
        ErrorCode::successResponse();

    }

    /**
     * 修改登录密码
     * @method POST /index.php?m = api&c = user&a = updLoginPsd&token = 61f6325d80723fc94dc73c705bdb240d&old_psd = aa112233&new_psd = aa112233&new_psd2 = aa112233
     * @param token string 用户token
     * @param old_psd string 旧密码
     * @param new_psd string 新密码
     * @param new_psd2 string 确认新密码
     * @return json
     */
    public function updLoginPsd()
    {
        //验证token
        $this->checkAuth();

        //接收参数
        $this->checkInput($_REQUEST, array('old_psd', 'new_psd', 'new_psd2'));
        $oldpsd = trim($_REQUEST['old_psd']);
        $newpsd = trim($_REQUEST['new_psd']);
        $newpsd2 = trim($_REQUEST['new_psd2']);

        //验证参数
        if (!$this->model->getUserInfo('username', array('id' => $this->userId, 'password' => md5($oldpsd)), '', '', '', true)) {
            ErrorCode::errorResponse(ErrorCode::OLD_PWD_WRONG, 'Old password is incorrect');
        }
        if (!preg_match('/^[a-zA-Z0-9_]{6,15}$/', $newpsd)) {
            ErrorCode::errorResponse(ErrorCode::PWD_FORMAT_WRONG, 'The password is limited to English letters, numbers and underscores, 6 to 15 characters');
        }
        if ($newpsd != $newpsd2) {
            ErrorCode::errorResponse(ErrorCode::PWD_DIFFERENT, 'Two password entries are inconsistent');
        }

        //记录日志
        $this->opLog(2);

        //修改密码
        $res = $this->model->save(array('password' => md5($newpsd)), array('id' => $this->userId));
        if (!$res) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }
        ErrorCode::successResponse();
    }

    /**
     * 上传头像
     * @method POST  /index.php?m = api&c = user&a = login&username = wangrui&password = aa112233
     * @param token string 用户token
     * @param avatar string base64
     * @return json
     */
    public function saveAvatar()
    {
        //验证token
        $this->checkAuth();
        $userid = $this->userId;
        $avatarFileName = $this->getAvatarFilename($userid, 'jpg');
        lg('save_avatar_debug',var_export(['$_REQUEST'=>$_REQUEST,'$avatarFileName'=>$avatarFileName],1));
        $avatarUrl = $this->getAvatarUrl($avatarFileName, 0);
        lg('save_avatar_debug',var_export(['$_REQUEST'=>$_REQUEST,'$avatarUrl'=>$avatarUrl],1));

        $res = $this->model->getUserInfo('avatar', array('id' => $userid), 1);
        lg('save_avatar_debug',var_export(['$res'=>$res],1));
        if ($res['avatar']) {
            $oldPath = $this->getAvatarPath($res['avatar']);
            @unlink($oldPath);
        }
        $_REQUEST['avatar'] = str_replace(" ","+",$_REQUEST['avatar']);
        $res = file_put_contents($avatarUrl, base64_decode($_REQUEST['avatar']));
        lg('save_avatar_debug',var_export(['$_REQUEST'=>$_REQUEST,'$res'=>$res,'$avatarUrl'=>$avatarUrl],1));
        if (!$res) {
            ErrorCode::errorResponse(100023, 'Avatar upload failed');
        }
        $data = array('avatar' => '/' . C('upfile_path') . '/avatar/' . $avatarFileName);

        $this->model->save($data, array('id' => $userid));

        //完成平台任务
        $arr = D('admin/activity')->taskSuccess(6, $this->userId);
        if (!$arr) {
            ErrorCode::errorResponse('100019','Platform task failed to complete');
        }

        //记录日志
        $this->opLog(1);

        ErrorCode::successResponse($data);
    }

    /**
     * 保存默认头像
     * @method POST  /index.php?m=api&c=user&a=saveDefaultAvatar &token=user_token &avatar_url=pic_url
     * @param token string 用户token
     * @param avatar_url string 默认图片url地址
     * @return json
     */
    public function saveDefaultAvatar()
    {
        //验证token
        $this->checkAuth();
        $user_id = $this->userId;

        //app传过来的默认头像地址
        $avatar_url = trim($_REQUEST['avatar_url']);

        $update_avatar_sql = "UPDATE un_user SET avatar = '{$avatar_url}'
            WHERE id = {$user_id}";

        //根据用户id，修改用户表中的头像字段
        $this->db->query($update_avatar_sql);

        $data = [
            'avatar_url' => $avatar_url,
        ];

        //H5页面需要刷新session，头像从session中读取
        if (intval($_REQUEST['h5']) == '1') {
            session::set('avatar', $avatar_url);
        }

        //完成平台任务
        $arr = D('admin/activity')->taskSuccess(6, $this->userId);
        if (!$arr) {
            ErrorCode::errorResponse('100019','Platform task failed to complete');
        }

        ErrorCode::successResponse($data);
    }

    /**
     * 获取用户信息
     * @method POST  /index.php?m = api&c = user&a = userInfo&token = 4eac2ce02f3d7fc4044e9473b6b79eea
     * @param token string 用户token
     * @return json
     */
    public function userInfo()
    {
        //防止刷接口
        $token = trim($_REQUEST['token']);
        $redis = initCacheRedis();
        $co_str = 'userinfo:'.$token;
//        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去
//            $redis->expire($co_str,3); //设置它的超时
//            deinitCacheRedis($redis);
//        }else{
//            deinitCacheRedis($redis);
//            return false;
//        }

        //验证token
        $this->checkAuth();

        //查询用户信息
        $userInfo = array();
        $fields = 'id,mobile,email,avatar,nickname,username,realname,birthday,weixin,qq,sex,signature,logintime,lastlogintime'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
        if (!empty($userInfo)) {
            $userInfo['avatar'] = !empty($userInfo['avatar']) ? $userInfo['avatar'] : '/up_files/room/avatar.png';

            $userInfo['honor'] = get_honor_info($this->userId);

            $userInfo['mobile']=decrypt($userInfo['mobile']);
            $userInfo['email']=decrypt($userInfo['email']);
            $userInfo['weixin']=decrypt($userInfo['weixin']);
            $userInfo['qq']=decrypt($userInfo['qq']);
            $userInfo['logintime'] = date('Y-m-d H:i:s',$userInfo['logintime']);
            $userInfo['lastlogintime'] = date('Y-m-d H:i:s',$userInfo['lastlogintime']);
            
            //获取未读个人消息
            $msgUser = D('lobby')->getUserMessageList($this->userId);
            
            //系统消息
            $sysMes = D('lobby')->getSysMessageList($this->userId);
            
            $userInfo['un_read_msg'] = $msgUser['num'] + $sysMes['num'];
        }
        
        ErrorCode::successResponse(array('data' => $userInfo));

    }
    
    /**
     * 关闭荣誉升级弹出框
     * @method POST
     * @param token string 用户token
     * @return json
     */
    public function closeHonorBox()
    {
        //验证token
        $this->checkAuth();

        $ret = $this->model->setHonorBox($this->userId);
        
        if (!$ret) {
            ErrorCode::errorResponse(1);
        }
        ErrorCode::successResponse();
    }

    /**
     * 设置用户个人资料
     * @method POST /index.php?m = api&c = user&a = setInfo&token = 61f6325d80723fc94dc73c705bdb240d&nickname = xiaozhang&sex = 2&signature = %E4%B8%AA%E6%80%A7%E7%AD%BE%E5%90%8D&email = 123321@qq.com&realname = %E5%BC%A0%E4%B8%89&birthday = 20120107&weixin = wei123&mobile = 1231
     * @param token string 用户token
     * @param nickname string 用户昵称
     * @param sex int 性别 - 1 男, 2 女
     * @param signature string 签名
     * @param email string 邮箱
     * @param realname string 用户名字
     * @param birthday string 生日
     * @param weixin string 微信
     * @param mobile string 手机
     * @return json
     */
    public function setInfo()
    {
        //验证token
        $this->checkAuth();
        $param = array_map('deal_array', $_POST);
        $param_keys = array_keys($param);
        $filed = array('nickname', 'sex', 'signature', 'email', 'birthday', 'weixin', 'qq', 'mobile');
        $encode_filed = array('email','weixin', 'mobile', 'qq');
        foreach ($param_keys as $v) {
            if (!in_array($v, $filed)) {
                unset($param[$v]);
            }else{
                if(in_array($v,$encode_filed)){
                    $param[$v]=encrypt($param[$v]);
                }
            }
        }

        if(!empty($param['nickname'])){
            if(mb_strlen($param['nickname']) > 8 || !preg_match( "/^[\x{4e00}-\x{9fa5}a-zA-Z]+$/u", $param['nickname']) || $param['nickname'] == "") {
                ErrorCode::errorResponse(1715, 'Incorrect nickname format');
            }
        }

        $res = $this->model->save($param, array('id' => $this->userId));
        if (!$res) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }

        //添加完成平台任务
        $type = 0;
        if ($param['email']){
            $type = 4;
        } elseif($param['weixin']){
            $type = 3;
        } elseif($param['nickname']){
            $type = 5;
        } elseif($param['qq']){
            $type = 2;
        }
        $re = D('admin/activity')->taskSuccess($type, $this->userId);
        if ($re === false) {
            ErrorCode::errorResponse(1715, 'Platform task failed to complete');
        }

        //记录日志
        $this->opLog(1);

        ErrorCode::successResponse();

    }

    /**
     * 设置资金密码
     * @method get /index.php?m = api&c = user&a = setPayPSD&token = b5062b58d2433d1983a5cea888597eb6&psd = aa123&psd2 = aa123
     * @param token string
     * @param psd string 密码
     * @param psd2 string 确认密码
     * @return json
     */
    public function setPayPSD()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'psd', 'psd2'), 'all');

        //验证token
        $this->checkAuth();

        $param = array_map('deal_array', $_POST);
        $psd = trim($param['psd']);
        $res = $this->validate($psd, 2);
        if (!$res) {
            ErrorCode::errorResponse(100014, 'The password format is incorrect, please fill in a 6-digit password');
        }
        $psd2 = trim($param['psd2']);
        if ($psd !== $psd2) {
            ErrorCode::errorResponse(1712, 'Two password entries are inconsistent');
        }

        $fields = 'regtime'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);

        $paypsd = md5($psd);

        $data = array(
            'paypassword' => $paypsd
        );

        $res = $this->model->save($data, array('id' => $this->userId));
        if (!res) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }

        //记录日志
        $this->opLog(3);

        ErrorCode::successResponse();
    }

    /**
     * 修改资金密码
     * @method get /index.php?m = api&c = user&a = updPayPsd&token = b5062b58d2433d1983a5cea888597eb6&old_psd = aa123&new_psd = aa456
     * @param token string
     * @param old_psd string 旧密码
     * @param new_psd string 新密码
     * @param new_psd2 string 确认新密码
     * @return  json
     */
    public function updPayPsd()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'old_psd', 'new_psd', 'new_psd2'), 'all');

        //验证token
        $this->checkAuth();

        $param = array_map('deal_array', $_POST);
        $old_psd = trim($param['old_psd']);
        $res = $this->validate($old_psd, 2);
        if (!$res) {
            ErrorCode::errorResponse(100013, 'The format of the old password is incorrect, please fill in a 6-digit password');
        }
        $psd = trim($param['new_psd']);
        $res = $this->validate($psd, 2);
        if (!$res) {
            ErrorCode::errorResponse(100014, 'The password format is incorrect, please fill in a 6-digit password');
        }
        $psd2 = trim($param['new_psd2']);

        $fields = 'id,paypassword,regtime'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
        //$paypsd = $this->model->passwordMd5($old_psd,$userInfo['regtime']);
        $paypsd = md5($old_psd);
        if ($paypsd !== $userInfo['paypassword']) {
            ErrorCode::errorResponse(1714, 'Old password is incorrect');
        }

        if ($psd !== $psd2) {
            ErrorCode::errorResponse(1712, 'Two password entries are inconsistent');
        }

        //生成新的资金密码
        $newpaypsd = md5($psd);

        $data = array(
            'paypassword' => $newpaypsd
        );

        $res = $this->model->save($data, array('id' => $this->userId));
        if (!res) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }
        session::is_set('paypsd_' . $this->userId) ? session::set('paypsd_' . $this->userId, 0) : '';

        //记录日志
        $this->opLog(3);

        ErrorCode::successResponse();
    }

    /**
     * 代理制度
     * @method get /index.php?m = api&c = user&a = agentSystem&token = b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return  json
     */
    public function agentSystem()
    {
        //$res = $this->getSysMessage();web
        $rows = $this->db->getone("select * from un_config where nid = 'AgencySystemImg'");
        include template('my/app_agencySystem');
    }
    
    /**
     * 代理制度PC端回传图片url就行
     * @method get /index.php?m = api&c = user&a = agentSystem&token = b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return  json
     */
    public function agentSystempc()
    {
        //$res = $this->getSysMessage();
        $rows = $this->db->getone("select * from un_config where nid = 'AgencyWebSystemImg'");
        ErrorCode::successResponse(['img_url' => $rows['value']]);
    }

    /**
     * 代理分享
     * @method get /index.php?m = api&c = user&a = agentSharing&token = b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return  json
     */
    public function agentSharing()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token'));

        //验证token
        $this->checkAuth();
        $redis = initCacheRedis();
        $reg_sw = $redis->hGet('Config:AgencyRegSwitch','value');
        deinitCacheRedis($redis);
        $data = decode($reg_sw);

        $fields = 'id,username'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
//        $userInfo['JumpUrl'] = '/index.php?m=web&c=user&a=register&pid=' . $this->userId;
        //pcmobile/index.html#/reg?pid=6332&type=2
        $userInfo['JumpUrl'] = '/pcmobile/index.html#/reg?pid=' . $this->userId;
        $userInfo['reg_swicth'] = $data;

        ErrorCode::successResponse($userInfo);
    }
    
    /**
     * 代理分享二位码
     * @param url string
     * @param token string
     * @return  json
     */
    public function QRcode()
    {
        $type = $_REQUEST['type'];
        //验证token
        $this->checkAuth();
        O('phpQRcode');
        $value = url('web','user','register', ['pid' => $this->userId, 'type' => $type]);
        $errorCorrectionLevel = "L"; // 纠错级别：L、M、Q、H
        $matrixPointSize = "6"; // 点的大小：1到10
        Qrcode::png($value, false, $errorCorrectionLevel, $matrixPointSize);
    }

    /**
     * 下线开户
     * @method POST /index.php?m = api&c = user&a = openAccount&username = wangrui2354&password = aa112233&password2 = aa112233&token = 45e3845fe6fc08283c1d7ef300dfc6ff
     * @param token string
     * @return  json
     */
    public function openAccount()
    {
        // lg('openAcc', var_export(['$_REQUEST'=>$_REQUEST], true));

        //验证参数
        $this->checkInput($_REQUEST, array('token'));

        // lg('openAcc', '[end-in-1218up]');

        //验证token
        $this->checkAuth();
        $this->reg($this->userId, false);
    }

    /**
     * 会员报表
     * @method POST /index.php?m = api&c = user&a = myMember&token = 45e3845fe6fc08283c1d7ef300dfc6ff
     * @param token string
     * @return  json
     */
    public function myMemberback()
    {
        //验证token
        $this->checkAuth();

        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
        if (!empty($start_date) && !empty($end_date)) {
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date . " 23:59:59");
            $where = " AND u.regtime BETWEEN {$start_time} and {$end_time}";
        } elseif (!empty($start_date)) {
            $start_time = strtotime($start_date);
            $where = " AND u.regtime >= {$start_time}";
        } else {
            $where = "";
        }

        $id = $this->userId;
        //查询user account
        $sql = "SELECT u.id AS uid, u.username, u.nickname, u.regtime, u.parent_id, a.money FROM un_user AS u LEFT JOIN un_account AS a ON u.id = a.user_id WHERE  FIND_IN_SET(id, getChildLst({$id}))" . $where . " ORDER BY u.regtime DESC ";
        $res = O('model')->db->getall($sql);

        //查询orders
        $cids = array();
        $pids = array();
        foreach ($res as $v) {
            if ($v['uid'] == $id) {
                continue;
            }
            if($v['parent_id'] == $id){
                $pids[] = $v['uid'];
            }
            $cids[] = $v['uid'];
        }
        if (!empty($cids)) {
            $ids = implode($cids, ',');
            $today = strtotime(date('Y-m-d 00:00:00', SYS_TIME));
            $sql = "SELECT user_id, SUM(money) AS inputs_money FROM un_orders WHERE user_id IN({$ids}) AND state = 0 AND addtime >= {$today}  GROUP BY user_id";

            $orders = O('model')->db->getAll($sql);
            if (!empty($orders)) {
                $inputsMoney = array();
                foreach ($orders as $v) {
                    $inputsMoney[$v['user_id']] = $v['inputs_money'];
                }
            }
        }

        //初始化redis
        $redis = initCacheRedis();
        $stage = $redis->hMGet("Config:stage", array('value'));
        //关闭redis链接
        deinitCacheRedis($redis);

        $myMemberList = array();
        foreach ($res as $v) {
            if ($v['uid'] == $id) {
                continue;
            }
            //$v['nickname'] = empty($v['nickname']) ? $v['username'] : $v['nickname'];
            $v['nickname'] = ($stage['value'] == 1 && !in_array($v['uid'],$pids)) ? subtext($v['username'],1,0)."****".subtext($v['username'],1,-1) : $v['username'];
            $v['money'] = empty($v['money']) ? '0.00' : $this->convert($v['money']);
            $v['inputsMoney'] = isset($inputsMoney[$v['uid']]) ? $this->convert($inputsMoney[$v['uid']]) : '0.00';
            $myMemberList[] = $v;
        }

        ErrorCode::successResponse(array('list' => $myMemberList));
    }
    /**
     * 优化后的会员报表
     * 2017-11-30 update
     * @param string $start_time 开始时间
     * @param string $end_time 结束时间
     * @param string $user_type 搜索类型，会员账号，或者会员昵称 
     * @param string $user_value 会员账号，或者会员昵称搜索关键字
     */
    public function  myMember()
    {
        //验证token
        $this->checkAuth();

        $start_time = getParame('start_time', 0, date('Y-m-d'));
        $end_time = getParame('end_time', 0, date('Y-m-d'));
        $sort_type = getParame('sort_type', 0, 1, 'int');       //排序1离线时间 2注册时间 3会员盈亏
        $start_time_int = strtotime($start_time);
        $end_time_int = strtotime($end_time . ' 23:59:59');

        $redis = initCacheRedis();
        $res = $redis->hMGet('Config:report_count_max_day',['value']);
        $maxDay = isset($res['value'])?$res['value']:31;
        deinitCacheRedis($redis);
        if((($end_time_int - $start_time_int)/84600) > $maxDay) ErrorCode::errorResponse(400, 'The selected date cannot exceed '.$maxDay.' days');

        $page = getParame('page', 0, 1);
        $pagesize = 20;
        $offset = ($page - 1) * 20;
//        $user_type = getParame('user_type', 0, 2);              //1会员账号, 2会员昵称
        $user_value = getParame('user_value', 0, '');
//        $userWhere = '';
        if($user_value) {
            $userWhere = " AND username = '".$user_value."'";
//            $user_type == 2 && $userWhere = " AND nickname = '".$user_value."'";
        }
        //====================================
        $where = "(uut.pids like '%,".$this->userId.",%' OR uut.user_id = ".$this->userId.") AND uu.id > 0";
        $timeWhere = " and ual.addtime >= $start_time_int and ual.addtime <= $end_time_int";
        $field = "DISTINCT uut.user_id,uu.username,uu.regtime,uu.parent_id,ua.money,us.sessionid sid,SUM(IF(ual.type in (12,14),ual.money,IF(ual.type=13,-ual.money,0))) as total_profit_amt";
        $countSql = "SELECT count(distinct uut.user_id) as page_count FROM un_user_tree uut left join un_user uu ON uut.user_id = uu.id where $where";
        $page_count = O('model')->db->getOne($countSql);
        $pageCount = ceil($page_count['page_count'] / 20);
        if ($pageCount == 1)  $pageCount = 0;

        if($sort_type == 1) $order = ' us.sessionid DESC,uu.logintime DESC ';
        if($sort_type == 2) $order = ' uut.user_id DESC ';
        if($sort_type == 3) $order = ' total_profit_amt DESC ';
        $fetSql = "SELECT $field FROM un_user_tree uut LEFT JOIN un_account ua ON uut.user_id = ua.user_id LEFT JOIN un_account_log ual ON ual.user_id = uut.user_id $timeWhere LEFT JOIN un_user uu ON uut.user_id = uu.id LEFT JOIN un_session us ON uu.id = us.user_id WHERE $where $userWhere GROUP BY uut.user_id ORDER BY $order LIMIT $offset,$pagesize";
        $dataList = O('model')->db->getAll($fetSql);

        $redis = initCacheRedis();
        $stage = $redis->hMGet("Config:stage", array('value'));
        $direct = $redis->hMGet("Config:direct", array('value'));
        deinitCacheRedis($redis);

        foreach($dataList as &$data) {
            $data['id'] = $data['user_id'];         //兼容前端字段
            $data['money'] = $this->convert($data['money']);
            $data['total_profit_amt'] = $this->convert($data['total_profit_amt']);
            $data['n_username'] = $data['username'];
            if($data['parent_id'] == $this->userId) {           //直属
                !$direct['value'] && $data['username'] = interceptChinese($data['username']);
            }
            if($data['parent_id'] != $this->userId) {           //团队
                !$stage['value'] && $data['username'] = interceptChinese($data['username']);
            }
            unset($data);
        }
        ErrorCode::successResponse(array('list' => $dataList,'pagecount' => $pageCount, 'datacount' => $page_count['page_count']));
    }

    /**
     * 会员报表详情
     * @method POST /index.php?m = api&c = user&a = memberDetail&token = 857335bbeeba2b26ac7a60856c195af9&online = 0&start_time = 1480479627&end_time = 1480760149&id = 4
     * @param token string
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function memberDetail()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'id'), all);
        //验证token
        $this->checkAuth();
        $id = trim($_REQUEST['id']);
	
		$start_time = getParame('start_time', 0, date('Y-m-d'));
        $end_time = getParame('end_time', 0, date('Y-m-d'));
        $sort_type = getParame('sort_type', 0, 1, 'int');       //排序1离线时间 2注册时间 3会员盈亏
        $start_time_int = strtotime($start_time);
        $end_time_int = strtotime($end_time . ' 23:59:59');

        $redis = initCacheRedis();
        $res = $redis->hMGet('Config:report_count_max_day',['value']);
        $maxDay = isset($res['value'])?$res['value']:31;
        deinitCacheRedis($redis);

        if((($end_time_int - $start_time_int)/84600) > $maxDay) ErrorCode::errorResponse(400, 'The selected date cannot exceed'.$maxDay.' days');
		$where = " AND addtime BETWEEN {$start_time_int} and {$end_time_int}";

        //团队会员 查询user表
        #$sql = "SELECT u.id AS uid, u.parent_id FROM un_user AS u WHERE FIND_IN_SET(u.id, getChildLst({$id}))";
        #$res = O('model')->db->getall($sql);
        //优化查询
        //查询自身记录
        $sql = "SELECT id as uid, parent_id FROM un_user WHERE id={$id}";
        $self = O('model')->db->getOne($sql);

        //查询user表下级记录
        $field = "id, id AS uid, parent_id";
        $res = $this->recursive_query($id,$field);
        array_unshift($res,$self);

        $directlyIds = array();//直属会员id
        $teamIds = array();//团队会员id
        foreach ($res as $v) {
            if ($v['parent_id'] == $id) {//直属会员人数
                $directlyIds[] = $v['uid'];
            }
            $teamIds [] = $v['uid']; //团队人数
        }

        //交易类型
        $trade = $this->getTrade();
        $ids = implode($trade['tranTypeIds'], ',');

        //直属会员交易记录
        if (!empty($directlyIds)) {
            $SdirectlyIds = implode($directlyIds, ',');
            //查询 orders表
            $directly = $this->getTradeLog($SdirectlyIds, $ids, $where);

            $directlyType = array();
            $directlyTradeType = array();
            foreach ($directly as $v) {
                $directlyType[] = $v['type'];
                $directlyTradeType[$v['type']] = $v['total_money'];
            }
            //无记录的返回默认值
            $diff = array_diff($trade['tranTypeIds'], $directlyType);
            if (!empty($diff)) {
                foreach ($diff as $v) {
                    $directlyTradeType[$v] = '0.00';
                }
            }
        } else {
            foreach ($trade['tranTypeIds'] as $v) {
                $directlyTradeType[$v] = '0.00';
            }
        }

        //团队交易记录
        $STeamIds = implode($teamIds, ',');
        $team = $this->getTradeLog($STeamIds, $ids, $where);
        $teamType = array();
        $teamTradeType = array();
        foreach ($team as $v) {
            $teamType[] = $v['type'];
            $teamTradeType[$v['type']] = $v['total_money'];
        }
        //无记录的返回默认值
        $diff = array_diff($trade['tranTypeIds'], $teamType);
        if (!empty($diff)) {
            foreach ($diff as $v) {
                $teamTradeType[$v] = '0.00';
            }
        }

        //自身交易记录 orders表
        $sql2 = "SELECT user_id, type, SUM(money) AS total_money FROM un_account_log WHERE user_id = {$id} AND type IN({$ids})" . $where . " GROUP BY type";
        $orders = O('model')->db->getall($sql2);

        $type = array();
        $tradeType = array();
        if (!empty($orders)) {
            foreach ($orders as $v) {
                $type[] = $v['type'];
                $tradeType[$v['type']] = $v['total_money'];
            }
        }
        //无记录的返回默认值
        $diff = array_diff($trade['tranTypeIds'], $type);
        if (!empty($diff)) {
            foreach ($diff as $v) {
                $tradeType[$v] = '0.00';
            }
        }

        //初始化redis
        $redis = initCacheRedis();
        $backwater = $redis->hMGet("Config:100012", array('value'));
        $stage = $redis->hMGet("Config:stage", array('value'));
        $direct = $redis->hMGet("Config:direct", array('value'));
        //关闭redis链接
        deinitCacheRedis($redis);
        //自身信息
        $sql3 = "SELECT u.id AS uid, u.username, u.nickname, u.parent_id FROM un_user AS u WHERE u.id = {$id}";
        $self = O('model')->db->getOne($sql3);
        $data = array();
        $data['username'] = $self['username'];
        if($self['parent_id'] == $this->userId && !$direct['value']) {
            $data['username'] = interceptChinese($self['username']);
        }
        if($self['parent_id'] != $this->userId && !$stage['value']) {
            $data['username'] = interceptChinese($self['username']);
        }
        $data['id'] = $data['user_id'];         //兼容前端字段
        $data['nickname'] = $data['username'];      //兼容前端字段
        $data['recharge'] = $this->convert($tradeType['10']); //充值
        $data['cash'] = $this->convert($tradeType['11']); //提现
        $data['award'] = $this->convert($tradeType['12']) - $this->convert($tradeType['120']); //中奖-回滚
        $data['betting'] = $this->convert($tradeType['13'] - $tradeType['14']); //投注
        $data['selfBackwater'] = $this->convert($tradeType['19']); //自身返水
        $data['teamBackwater'] = $this->convert($tradeType['21']); //团队返水
        $data['team'] = count($teamIds); //团队人数
        $data['directly'] = count($directlyIds); //直属会员人数
        $data['directly_Betting'] = $this->convert($directlyTradeType['13'] - $directlyTradeType['14']); //直属会员投注
        $data['team_Betting'] = $this->convert($teamTradeType['13'] - $teamTradeType['14']); //团队会员投注
        $data['team_award'] = $this->convert($teamTradeType['12']); //团队会员中奖
        $data['profit'] = $this->convert(($tradeType['12'] + $tradeType['14'] + $tradeType['19'] + $tradeType['20'] + $tradeType['21'] + $tradeType['18'] + $tradeType['32']) - $tradeType['13'] - $tradeType['120']); //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整)-投注-回滚
        $data['backwater'] = isset($backwater['value']) ? $backwater['value'] : 0; //返点比例
        $data['total_hd_money'] = $this->convert($tradeType['1000'] + $tradeType['999'] + $tradeType['998'] + $tradeType['997'] + $tradeType['995'] + $tradeType['994'] + $tradeType['993'] + $tradeType['992']);      // 活动优惠     （包含：刮刮乐、福袋、转盘奖励 博饼奖励 红包奖励 双旦奖励 平台任务奖励 九宫格奖励）
        $data['total_other_income'] = $this->convert($tradeType['32']);     //其他收入 （包含：会员额度调整）
        ErrorCode::successResponse($data);
    }

    /**
     * 团队报表
     * @method POST /index.php?m = api&c = user&a = myGroup&token = 4a21f9d47e0c437dd3768f31a283d376&online = 0&start_time = 1479657600&end_time = 1479657730
     * @param token string
     * @param online mixed 在线状态
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     * 优化后的方法
     */
    public function myGroup()
    {
        $start_time = getParame('start_time', 0, date('Y-m-d'));
        $end_time = getParame('end_time', 0, date('Y-m-d'));
        $start_time_int = strtotime($start_time);
        $end_time_int = strtotime($end_time . ' 23:59:59');

        $redis = initCacheRedis();
        $res = $redis->hMGet('Config:report_count_max_day',['value']);
        $maxDay = isset($res['value'])?$res['value']:31;
        deinitCacheRedis($redis);

        if((($end_time_int - $start_time_int)/84600) > $maxDay) ErrorCode::errorResponse(400, 'The selected date cannot exceed'.$maxDay.' days');

        $page = getParame('page', 0, 1);
        $user_value = getParame('user_value', 0, '');
        $online = getParame('online', 0, '');
        $pagesize= 20;
        $pageOffer = ($page - 1) * $pagesize;

        //验证token
        $this->checkAuth();

        $where = "(pids like '%,".$this->userId.",%' OR uut.user_id = ".$this->userId.") AND uu.id > 0";
        $betWhere = " AND ual.addtime >= $start_time_int AND ual.addtime <= $end_time_int";
        if($online) {
            $online == 1 && $where .= " AND us.sessionid is NOT NULL";
            $online == 2 && $where .= " AND us.sessionid is null";
        }

        $fields = "DISTINCT uu.id,uu.username,uu.nickname,uu.parent_id,us.sessionid";
        if($user_value) {
            $where .= " AND uu.username = '".$user_value."'";
            $userSql = "SELECT $fields FROM un_user_tree uut LEFT JOIN un_user uu ON uut.user_id = uu.id LEFT JOIN un_session us ON uut.user_id = us.user_id WHERE $where";
        }else {
            $userSql = "SELECT $fields FROM un_user_tree uut LEFT JOIN un_user uu ON uut.user_id = uu.id LEFT JOIN un_session us ON uut.user_id = us.user_id WHERE $where ORDER BY us.sessionid desc,uu.id LIMIT $pageOffer,$pagesize";
        }


        $countSql = "SELECT count(distinct uut.user_id) as page_count FROM un_user_tree uut left join un_user uu ON uut.user_id = uu.id LEFT JOIN un_session us ON uut.user_id = us.user_id where $where";
        $page_count = O('model')->db->getOne($countSql);
        $pageCount = ceil($page_count['page_count'] / 20);
        if ($pageCount == 1)  $pageCount = 0;

        $userData = O('model')->db->getAll($userSql);

        /*  tz_count:投注
        /*  cd_count:撤单
        /*  win_count:中奖
        /*  self_back_count:自身返水
        /*  zs_back_count:直属返水
        /*  td_back_count:团队返水
        /*  fl_give_count:返利赠送
        /*  limit_adjust_count:会员额度调整
        /*  share_rcash_count:分享返现
        /*  roll_back_count:回滚
         * */
        $accountFileds = "SUM(IF(ual.type=13, ual.money, 0)) as tz_count,SUM(IF(ual.type=14, ual.money, 0)) as cd_count,SUM(IF(ual.type=12, ual.money, 0)) as win_count,SUM(IF(ual.type=19, ual.money, 0)) as self_back_count,SUM(IF(ual.type=20, ual.money, 0)) as zs_back_count,SUM(IF(ual.type=21, ual.money, 0)) as td_back_count,SUM(IF(ual.type=18, ual.money, 0)) as fl_give_count,SUM(IF(ual.type=32, ual.money, 0)) as limit_adjust_count,SUM(IF(ual.type=66, ual.money, 0)) as share_rcash_count,SUM(IF(ual.type=120, ual.money, 0)) as roll_back_count";

        $redis = initCacheRedis();
        $backwater = $redis->hMGet("Config:100012", array('value'));
        $stage = $redis->hMGet("Config:stage", array('value'));
        $direct = $redis->hMGet("Config:direct", array('value'));

        //关闭redis链接
        deinitCacheRedis($redis);

        foreach ($userData as &$user) {
            $user['n_username'] = $user['username'];
            if($user['parent_id'] == $this->userId && !$direct['value']) {           //直属
                $user['username'] = interceptChinese($user['username']);
            }
            if($user['parent_id'] != $this->userId && !$stage['value']) {           //直属
                $user['username'] = interceptChinese($user['username']);
            }

            $user['online'] = !empty($user['sessionid'])?'在线':'离线';
            $teamSql = "SELECT $accountFileds FROM un_user_tree uut LEFT JOIN un_account_log ual ON uut.user_id = ual.user_id WHERE (uut.pids LIKE '%,".$user['id'].",%' OR uut.user_id = ".$user['id'].") $betWhere";
            $teamList = O('model')->db->getAll($teamSql);
            $user['betCount'] = 0;
            $user['teamProfit'] = 0;
            foreach($teamList as $team) {
                $user['betCount'] += ($team['tz_count'] - $team['cd_count']);
                $user['teamProfit'] += ($team['cd_count'] + $team['win_count'] + $team['self_back_count'] + $team['zs_back_count'] + $team['td_back_count'] + $team['fl_give_count'] + $team['limit_adjust_count'] + $team['share_rcash_count'] - $team['tz_count']- $team['roll_back_count']);
            }
        }

        if(empty($userData)){
            ErrorCode::successResponse(array('list' => array()));
        }

        ErrorCode::successResponse(array('list' => $userData, 'countPage' => $pageCount));
    }
    

    /**
     * 团队报表详情
     * @method POST /index.php?m = api&c = user&a = myGroupDetail&token = 4efcc95c123197ed313e46b315e85b18&start_time = all&end_time = all&id = 4
     * @param token string
     * @param id int 用户id
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function myGroupDetail()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'id'), all);
        //验证token
        $this->checkAuth();

        $id = getParame('id', 1, '', 'int');  //uid

		$start_time = getParame('start_time', 0, date('Y-m-d'));
        $end_time = getParame('end_time', 0, date('Y-m-d'));
        $sort_type = getParame('sort_type', 0, 1, 'int');       //排序1离线时间 2注册时间 3会员盈亏
        $start_time_int = strtotime($start_time);
        $end_time_int = strtotime($end_time . ' 23:59:59');

        $redis = initCacheRedis();
        $res = $redis->hMGet('Config:report_count_max_day',['value']);
        $maxDay = isset($res['value'])?$res['value']:31;
        deinitCacheRedis($redis);

        if((($end_time_int - $start_time_int)/84600) > $maxDay) ErrorCode::errorResponse(400, 'The selected date cannot exceed'.$maxDay.' days');
		$timewhere = " AND ual.addtime BETWEEN {$start_time_int} and {$end_time_int}";
		$backwhere = " AND ubl.addtime >= '".$start_time."' and ubl.addtime <= '".$end_time."'";

        /*
          * directly 直属人数
          * recharge 充值
          * withdraw 提款
          * team 团队人数
          * directly_Betting 直属投注
          * team_Betting 团队投注
          * team_award   团队中奖
          * profit   盈利总额
          * backwater   返点比例
          * back   反水
          * */
        $fileds = "COUNT(DISTINCT uut.user_id) as team,COUNT(DISTINCT uut.user_id, IF(uu.parent_id = $id, TRUE, NULL)) as directly,SUM(IF(ual.type = 13,ual.money, 0)) as team_Betting,SUM(IF(uu.parent_id = $id, IF(ual.type = 13, ual.money, 0), 0)) as directly_Betting,SUM(IF(ual.type = 12,ual.money, 0)) as team_award,SUM(IF(ual.type = 10,ual.money, 0)) as recharge,SUM(IF(ual.type = 11,ual.money, 0)) as withdraw,SUM(IF(ual.type = 14,ual.money, 0)) as inc14,SUM(IF (uu.parent_id = $id,IF (ual.type = 14, ual.money, 0),0)) AS inc14_zs,SUM(IF(ual.type = 19,ual.money, 0)) as inc19,SUM(IF(ual.type = 20,ual.money, 0)) as inc20,SUM(IF(ual.type = 21,ual.money, 0)) as inc21,SUM(IF(ual.type = 18,ual.money, 0)) as inc18,SUM(IF(ual.type = 32,ual.money, 0)) as inc32,SUM(IF(ual.type = 66,ual.money, 0)) as inc66,SUM(IF(ual.type = 120,ual.money, 0)) as dec120";
        $sql = "SELECT $fileds FROM `un_user_tree` uut LEFT JOIN un_user uu ON uut.user_id = uu.id LEFT JOIN un_account_log ual ON uut.user_id = ual.user_id $timewhere WHERE pids LIKE '%,".$id.",%' OR uut.user_id = $id";
        $data = $this->db->getone($sql);
        $data['profit'] = $this->convert(($data['team_award'] + $data['inc14'] + $data['inc19'] + $data['inc20'] + $data['inc21'] + $data['inc18'] + $data['inc32'] + $data['inc66']) - $data['team_Betting'] - $data['dec120']);

		$data['team_Betting'] = $data['team_Betting'] - $data['inc14'];		//团队投注 = 投注 - 撤单
		$data['directly_Betting'] = $data['directly_Betting'] - $data['inc14_zs'];		//直属投注 = 直属投注 - 直属投注撤单
        $data['back'] = $this->convert($data['inc19'] + $data['inc20'] + $data['inc21']);

        $redis = initCacheRedis();
        $stage = $redis->hMGet("Config:stage", array('value'));
        $direct = $redis->hMGet("Config:direct", array('value'));
        deinitCacheRedis($redis);

        //自身信息
        $sql3 = "SELECT u.id AS uid, u.username,u.loginip, u.nickname, u.parent_id, u.user_type, a.backwater FROM un_user AS u LEFT JOIN un_agent_group AS a ON u.user_type = a.id WHERE u.id = {$id}";
        $self = O('model')->db->getOne($sql3);

        $data['nickname'] = $self['username'];
        $data['last_login_ip'] = $self['loginip'];
        if($self['parent_id'] == $this->userId && !$direct['value']) {
            $data['nickname'] = interceptChinese($self['username']);
        }
        if($self['parent_id'] != $this->userId && !$stage['value']) {
            $data['nickname'] = interceptChinese($self['username']);
        }

        $data['backwater'] = empty($self['backwater']) ? 0 : $self['backwater']; //返点比例

//        $backfileds = "SUM(ubl.selfBack) as selfBack,SUM(IF(ubl.user_id = $id,ubl.sonBack,0)) as sonBack";
//        $backSql = "SELECT $backfileds FROM `un_user_tree` uut LEFT JOIN un_back_log ubl ON uut.user_id = ubl.user_id $backwhere WHERE pids LIKE '%,".$id.",%' OR uut.user_id = $id";
//        $res = $this->db->getone($backSql);
//        $data['back'] = $this->convert($res['selfBack'] + $res['sonBack']);

        ErrorCode::successResponse($data);
    }


    public function myGroupDetailbak180806()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'id'), all);
        //验证token
        $this->checkAuth();
        $id = trim($_REQUEST['id']);
        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
        if (!empty($start_date) && !empty($end_date)) {
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date . " 23:59:59");
            $where = " AND addtime BETWEEN {$start_time} and {$end_time}";
        } elseif (!empty($start_date)) {
            $start_time = strtotime($start_date);
            $where = " AND addtime >= {$start_time}";
        } else {
            $where = "";
        }

        //团队会员 查询user表
        #$sql = "SELECT u.id AS uid, u.parent_id FROM un_user AS u WHERE FIND_IN_SET(u.id, getChildLst({$id}))";
        #$res = O('model')->db->getall($sql);
        //优化查询
        //查询自身记录
        $sql = "SELECT id as uid, parent_id FROM un_user WHERE id={$id}";
        $self = O('model')->db->getOne($sql);

        //查询user表下级记录
        $field = "id, id AS uid, parent_id";
        $res = $this->recursive_query($id,$field);
        array_unshift($res,$self);

        $directlyIds = array();//直属会员id
        $teamIds = array();//团队会员id
        foreach ($res as $v) {
            if ($v['parent_id'] == $id) {//直属会员人数
                $directlyIds[] = $v['uid'];
            }
            $teamIds [] = $v['uid']; //团队人数
        }

        //交易类型
        $trade = $this->getTrade();
        $ids = implode($trade['tranTypeIds'], ',');

        //直属会员交易记录
        if (!empty($directlyIds)) {
            $SdirectlyIds = implode($directlyIds, ',');
            //查询 orders表
            $directly = $this->getTradeLog($SdirectlyIds, $ids, $where);

            $directlyType = array();
            $directlyTradeType = array();
            foreach ($directly as $v) {
                $directlyType[] = $v['type'];
                $directlyTradeType[$v['type']] = $v['total_money'];
            }
            //无记录的返回默认值
            $diff = array_diff($trade['tranTypeIds'], $directlyType);
            if (!empty($diff)) {
                foreach ($diff as $v) {
                    $directlyTradeType[$v] = '0.00';
                }
            }
        } else {
            foreach ($trade['tranTypeIds'] as $v) {
                $directlyTradeType[$v] = '0.00';
            }
        }

        //团队交易记录
        $STeamIds = implode($teamIds, ',');
        $team = $this->getTradeLog($STeamIds, $ids, $where);
        $teamType = array();
        $teamTradeType = array();
        foreach ($team as $v) {
            $teamType[] = $v['type'];
            $teamTradeType[$v['type']] = $v['total_money'];
        }
        //无记录的返回默认值
        $diff = array_diff($trade['tranTypeIds'], $teamType);
        if (!empty($diff)) {
            foreach ($diff as $v) {
                $teamTradeType[$v] = '0.00';
            }
        }

        //自身交易记录 orders表
        $sql2 = "SELECT user_id, type, SUM(money) AS total_money FROM un_account_log WHERE user_id = {$id} AND type IN({$ids})" . $where . " GROUP BY type";
        $orders = O('model')->db->getall($sql2);

        $type = array();
        $tradeType = array();
        if (!empty($orders)) {
            foreach ($orders as $v) {
                $type[] = $v['type'];
                $tradeType[$v['type']] = $v['total_money'];
            }
        }
        //无记录的返回默认值
        $diff = array_diff($trade['tranTypeIds'], $type);
        if (!empty($diff)) {
            foreach ($diff as $v) {
                $tradeType[$v] = '0.00';
            }
        }

        //初始化redis
        $redis = initCacheRedis();
        $stage = $redis->hMGet("Config:stage", array('value'));
        //关闭redis链接
        deinitCacheRedis($redis);

        //自身信息
        $sql3 = "SELECT u.id AS uid, u.username, u.nickname, u.parent_id, u.user_type, a.backwater FROM un_user AS u LEFT JOIN un_agent_group AS a ON u.user_type = a.id WHERE u.id = {$id}";
        $self = O('model')->db->getOne($sql3);
        $data = array();
        //$data['nickname'] = empty($self['nickname']) ? $self['username'] : $self['nickname'];
        $data['nickname'] = ($stage['value'] == 1 && $self['parent_id'] != $this->userId) ? subtext($self['username'],1,0)."****".subtext($self['username'],1,-1) : $self['username'];
        $data['team'] = count($teamIds); //团队人数
        $data['directly'] = count($directlyIds); //直属会员人数
        $data['directly_Betting'] = $this->convert($directlyTradeType['13'] - $directlyTradeType['14']); //直属会员投注
        $data['team_Betting'] = $this->convert($teamTradeType['13'] - $teamTradeType['14']); //团队会员投注
        $data['team_award'] = $this->convert($teamTradeType['12'] - $teamTradeType['120']); //团队会员中奖-回滚
        $data['profit'] = $this->convert(($tradeType['12'] + $tradeType['14'] + $tradeType['19'] + $tradeType['20'] + $tradeType['21'] + $tradeType['18'] + $tradeType['32'] + $tradeType['66']) - $tradeType['13'] - $tradeType['120']); //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利)-投注-回滚
        $data['backwater'] = empty($self['backwater']) ? 0 : $self['backwater']; //返点比例

        ErrorCode::successResponse($data);
    }



    /*
     * 用户团队报表详情  下级用户的下注和投注记录
     * */
    public function groupUserAccountList() {
        //验证token
//        $this->checkAuth();
        $user_id = getParame('user_id',1,'','int');
        $type = getParame('type',0,0,'int');            //   1投注        2充值
        $page = getParame('page',0,1,'int');
        $start_time = getParame('start_time', 0, date('Y-m-d'));
        $end_time = getParame('end_time', 0, date('Y-m-d'));
        $start_time_int = strtotime($start_time);
        $end_time_int = strtotime($end_time . ' 23:59:59');

//        $redis = initCacheRedis();
//        $res = $redis->hMGet('Config:report_count_max_day',['value']);
//        $maxDay = isset($res['value'])?$res['value']:31;
//        deinitCacheRedis($redis);
//        if((($end_time_int - $start_time_int)/84600) > $maxDay) ErrorCode::errorResponse(400, 'The selected date cannot exceed'.$maxDay.' days');
        $where = [
            'userId' => $user_id,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'page' => $page,
            'pageCnt' => 10,
        ];
        switch ($type) {
            case 1:
                $where['type'] = "13";
                break;
            case 2:
                $where['type'] = "10";
                break;
            default:
                $where['type'] = "10,13";
                break;
        }
        $dataList = D('account')->getBills($where);

//        if($type == 1) {
//            $where = ['user_id' => $user_id, 'start_time' => $start_time_int, 'end_time' => $end_time_int];
//            $dataList = D('user')->userBetInfo($where,$page,10);
//        }else {
//            $where = ['userId' => $user_id, 'start_time' => $start_time, 'end_time' => $end_time, 'page' => $page, 'pageCnt' => 10, 'type' => 10];
//            $dataList = D('account')->getBills($where);
//        }

        ErrorCode::successResponse(['data' => $dataList]);
    }

    /**
     * 自身统计
     * @method POST /index.php?m = api&c = user&a = myOneself&token = a7cffd97aedb59e9b25053c1016e445c&start_time = 1478677096&end_time = 1480642077
     * @param token string
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function myOneself()
    {
        //验证token
        $this->checkAuth();

        $id = $this->userId;
        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);

        $start_time = getParame('start_time', 0, date('Y-m-d'));
        $end_time = getParame('end_time', 0, date('Y-m-d'));
        $start_time_int = strtotime($start_time);
        $end_time_int = strtotime($end_time . ' 23:59:59');

        $redis = initCacheRedis();
        $res = $redis->hMGet('Config:report_count_max_day',['value']);
        $maxDay = isset($res['value'])?$res['value']:31;
        deinitCacheRedis($redis);

        if((($end_time_int - $start_time_int)/84600) > $maxDay) ErrorCode::errorResponse(400, 'The selected date cannot exceed'.$maxDay.' days');
        $where = " AND addtime BETWEEN {$start_time_int} and {$end_time_int}";

        //交易类型
        $trade = $this->getTrade();
        $ids = implode($trade['tranTypeIds'], ',');

        //团队会员 查询user表
        #$sql = "SELECT u.id AS uid, u.parent_id FROM un_user AS u WHERE FIND_IN_SET(u.id, getChildLst({$id}))";
        #$res = O('model')->db->getall($sql);
        //优化查询
        //查询自身记录
        $sql = "SELECT id as uid, parent_id FROM un_user WHERE id={$id}";
        $self = O('model')->db->getOne($sql);

        //查询user表下级记录
        $field = "id, id AS uid, parent_id";
        $res = $this->recursive_query($id,$field);
        array_unshift($res,$self);

        $directlyIds = array();//直属会员id
        $teamIds = array();//团队会员id
        foreach ($res as $v) {
            if ($v['parent_id'] == $id) {//直属会员人数
                $directlyIds[] = $v['uid'];
            }
            $teamIds [] = $v['uid']; //团队人数
        }

        //自身交易记录 orders表
        $sql2 = "SELECT user_id, type, SUM(money) AS total_money FROM un_account_log WHERE user_id = {$id} AND type IN({$ids})" . $where . " GROUP BY type";
        $orders = O('model')->db->getall($sql2);

        $type = array();
        $tradeType = array();
        if (!empty($orders)) {
            foreach ($orders as $v) {
                $type[] = $v['type'];
                $tradeType[$v['type']] = $v['total_money'];
            }
        }

        //无记录的返回默认值
        $diff = array_diff($trade['tranTypeIds'], $type);
        if (!empty($diff)) {
            foreach ($diff as $v) {
                $tradeType[$v] = '0.00';
            }
        }

        //自身信息
        $self = $this->model->getUserInfo('username,nickname', array('id' => $id), 1);
        $data = array();
        $data['username'] = $self['username'];
        $data['nickname'] = $self['nickname'];
        $data['recharge'] = $this->convert($tradeType['10']); //充值
        $data['cash'] = $this->convert($tradeType['11']); //提现
        $data['award'] = $this->convert($tradeType['12'] - $tradeType['120']); //中奖-回滚
        $data['betting'] = $this->convert($tradeType['13'] - $tradeType['14']); //投注
        $data['selfBackwater'] = $this->convert($tradeType['19']); //自身返水
        $data['directlyBackwater'] = $this->convert($tradeType['20']); //直属会员返水
        $data['teamBackwater'] = $this->convert($tradeType['21']); //团队返水
        $data['team'] = count($teamIds); //团队人数
        $data['directly'] = count($directlyIds); //直属会员人数
        $data['profit'] = $this->convert(($tradeType['12'] + $tradeType['14'] + $tradeType['19'] + $tradeType['20'] + $tradeType['21'] + $tradeType['18'] + $tradeType['32'] + $tradeType['66']) - $tradeType['13'] - $tradeType['120']); //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利)-投注-回滚
        $data['total_hd_money'] = $this->convert($tradeType['1000'] + $tradeType['999'] + $tradeType['998'] + $tradeType['997'] + $tradeType['995'] + $tradeType['994'] + $tradeType['993'] + $tradeType['992']);      // 活动优惠     （包含：刮刮乐、福袋、转盘奖励 博饼奖励 红包奖励 双旦奖励 平台任务奖励 九宫格奖励）
        $data['total_other_income'] = $this->convert($tradeType['32']);     //其他收入 （包含：会员额度调整）
        ErrorCode::successResponse($data);
    }

    public function agentReportForms() {
        $this->checkAuth();
        $start_time = getParame('start_time', 0, date('Y-m-d'));
        $end_time = getParame('end_time', 0, date('Y-m-d'));
        $start_time_int = strtotime($start_time);
        $end_time_int = strtotime($end_time . ' 23:59:59');

        $redis = initCacheRedis();
        $res = $redis->hMGet('Config:report_count_max_day',['value']);
        $maxDay = isset($res['value'])?$res['value']:31;
        deinitCacheRedis($redis);

        if((($end_time_int - $start_time_int)/84600) > $maxDay) ErrorCode::errorResponse(400, 'The selected date cannot exceed'.$maxDay.' days');

        $user_type = getParame('user_type', 0, 1);
        if($user_type == 1) {           //直属
            $where = "(uut.pids like '%,".$this->userId.",' OR uut.user_id = $this->userId)";
        }else {                     //团队
            $where = "(uut.pids like '%,".$this->userId.",%' OR uut.user_id = $this->userId)";
        }

        //团队总人数
        $teamCountSql = "SELECT COUNT(DISTINCT uut.user_id) as total FROM un_user_tree uut LEFT JOIN un_user uu ON uut.user_id = uu.id where $where";

        //时间范围内注册人数
        $regWhere = " AND uu.regtime >= $start_time_int AND uu.regtime <= $end_time_int";
        $regCountSql = "SELECT COUNT(DISTINCT uut.user_id) as total FROM un_user_tree uut LEFT JOIN un_user uu ON uut.user_id = uu.id where $where $regWhere";

        //时间范围内登录人数
        $loginWhere = " AND uu.addtime >= $start_time_int AND uu.addtime <= $end_time_int";
        $loginCountSql = "SELECT COUNT(DISTINCT uut.user_id) as total FROM un_user_tree uut LEFT JOIN un_user_login_log uu ON uut.user_id = uu.user_id where $where $loginWhere";

        //首存人数  首存额   只计算第一笔
        $firstRechargeWhere = " AND uar.`status` = 1 AND uar.addtime >= $start_time_int AND uar.addtime <= $end_time_int";
        $firstRechargeChildSql = "SELECT uut.user_id,uar.money FROM un_user_tree uut LEFT JOIN un_account_recharge uar ON uut.user_id = uar.user_id where $where $firstRechargeWhere group by uut.user_id";
        $firstRechargeCountSql = "select COUNT(*) AS num,SUM(money) AS money from ($firstRechargeChildSql) infos";

        /*      total_recharge:总充值（入款总额）
        /*      total_withdraw:总提现（出款总额）
        /*      total_win:中奖额
        /*      total_bet:投注额
        /*      total_cd:撤单
        /*      total_back_self:个人返水（代理返水）
        /*      total_back_zs:直属返水
        /*      total_back_td:团队返水
        /*      total_hd_money:活动优惠     （包含：刮刮乐、福袋、转盘奖励 博饼奖励 红包奖励 双旦奖励 平台任务奖励 九宫格奖励）
        /*      total_other_income:其他收入 （包含：会员额度调整）
         * */
        $accountWhere = " AND ud.`classid` = 2 AND ual.addtime >= $start_time_int AND ual.addtime <= $end_time_int";
        $accountFields = "SUM(IF(ual.type = 10, ual.money, 0)) as total_recharge,SUM(IF(ual.type = 11, ual.money, 0)) as total_withdraw,SUM(IF(ual.type = 12, ual.money, 0)) as total_win,SUM(IF(ual.type = 13, ual.money, 0)) as total_bet,SUM(IF(ual.type = 14, ual.money, 0)) as total_cd,SUM(IF(ual.type = 19, ual.money, 0)) as total_back_self,SUM(IF(ual.type = 20, ual.money, 0)) as total_back_zs,SUM(IF(ual.type = 21, ual.money, 0)) as total_back_td,SUM(IF(ual.type in (1000,999,998,997,995,994,993,992),ual.money,0)) as total_hd_money,SUM(IF(ual.type = 32,ual.money,0)) as total_other_income";
        $accountCountSql = "SELECT $accountFields FROM un_user_tree uut LEFT JOIN un_account_log ual ON uut.user_id = ual.user_id LEFT JOIN un_dictionary ud ON ual.type = ud.id where $where $accountWhere";
        $teamCountInfo = O('model')->db->getOne($teamCountSql);
        $regCountInfo = O('model')->db->getOne($regCountSql);
        $loginCountInfo = O('model')->db->getOne($loginCountSql);
        $firstRechargeCountInfo = O('model')->db->getOne($firstRechargeCountSql);
        $accountCountInfo = O('model')->db->getOne($accountCountSql);

        $assData = [
            'regCountUser' => $regCountInfo['total'],
            'logCountUser' => $loginCountInfo['total'],
            'teamCountUser' => $teamCountInfo['total'],
            'firstRechargeCountUser' => $firstRechargeCountInfo['num'],
            'firstRechargeCountAmt' => $this->convert($firstRechargeCountInfo['money']),
            'rechargeCountAmt' => $this->convert($accountCountInfo['total_recharge']),
            'withdrawCountAmt' => $this->convert($accountCountInfo['total_withdraw']),
            'winCountAmt' => $this->convert($accountCountInfo['total_win']),
            'betCountAmt' => $this->convert($accountCountInfo['total_bet'] - $accountCountInfo['total_cd']),
            'hdCountAmt' => $this->convert($accountCountInfo['total_hd_money']),
            'otherCountAmt' => $this->convert($accountCountInfo['total_other_income']),
            'teamBackCountAmt' => $this->convert($accountCountInfo['total_back_self'] + $accountCountInfo['total_back_td']),
            'zsBackCountAmt' => $this->convert($accountCountInfo['total_back_self'] + $accountCountInfo['total_back_zs']),
            'teamProfitCountAmt' => $this->convert($accountCountInfo['total_recharge'] - $accountCountInfo['total_withdraw']),
        ];
        ErrorCode::successResponse($assData);
    }


    /*
     * 获取报表最大查询天数
     * */
    public function getReportMaxDate() {
        $redis = initCacheRedis();
        $res = $redis->hMGet('Config:report_count_max_day',['value']);
        deinitCacheRedis($redis);

        ErrorCode::successResponse(['data' => ['maxDay' => $res['value']]]);
    }

    private function getAvatarFilename($userid, $extname)
    {
        $avatarFileName = uniqid($userid) . ".{$extname}";
        return $avatarFileName;
    }

    private function getAvatarUrl($avatarFileName, $isRand = 1)
    {
        if (empty($avatarFileName)) {
            return '';
        }
        $avatarUrl = S_ROOT . C('upfile_path') . '/avatar/';
        if ($isRand) {
            $avatarUrl .= ('?rand=' . time());
        }
        if (!file_exists($avatarUrl)) {
            @mkdir($avatarUrl, 0777, true);
        }

        return $avatarUrl . $avatarFileName;
    }

    private function getAvatarPath($avatarFileName)
    {
        $dir = S_ROOT;
        $path = $dir . '/' . $avatarFileName;
        return $path;
    }


    /**
     * 验证
     * @param $data mixed 验证字段
     * @param  $code int
     * @return bool
     */
    private function validate($data, $code)
    {
        $vdata = array(
            1 => '/^[0-9]{6,15}$/',
            2 => '/^[0-9]{6}$/',
        );
        if (preg_match($vdata[$code], $data)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * 代理制度 SysMessage 表
     * @return $SMessage array
     */
    /*    protected function getSysMessage(){
            $reids = initCacheRedis();
            $LSM = $reids->lRange('AgentSystemIds', 0, -1);
            $SMessage  = array();
            foreach ($LSM as $v){
                $list = $reids -> hGetAll("AgentSystem:".$v);
                $SMessage[] = $list;

            }
            return $SMessage;
        } */


    /**
     * 多维数组排序
     * @param $array array
     * @param $field string
     * @param $desc bool
     */
    function sortArrByField(&$array, $field, $desc = false)
    {
        $fieldArr = array();
        foreach ($array as $k => $v) {
            $fieldArr[$k] = $v[$field];
        }
        $sort = $desc == false ? SORT_ASC : SORT_DESC;
        array_multisort($fieldArr, $sort, $array);
    }

    /**
     * 交易类型
     * @return json
     */
    protected function getTrade()
    {
        //初始化redis
        $redis = initCacheRedis();
        $LTrade = $redis->lRange('DictionaryIds2', 0, -1);
        $tranType = array();
        foreach ($LTrade as $v) {
            $res = $redis->hMGet("Dictionary2:" . $v, array('id', 'name'));
            $tranType[$res['id']] = $res['name'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return array('tranTypeIds' => $LTrade, 'tranType' => $tranType);
    }


    /**
     * 交易记录
     * @return json
     */
    protected function getTradeLog($uids, $ids, $where = "")
    {
        $sql = "SELECT user_id, type, SUM(money) AS total_money FROM un_account_log WHERE user_id IN({$uids}) AND type IN({$ids}) " . $where . " GROUP BY type";
        //交易记录
        $orders = O('model')->db->getall($sql);
        return $orders;
    }


    /**
     * 登录日志
     * @return json
     */
    protected function loginLog($uid, $flag, $code)
    {
        $data = array(
            'user_id' => $uid,
            'flag' => $flag,
            'code' => $code,
            'ip' => ip(),
            'addtime' => SYS_TIME,
        );
        //登录日志
        $this->model3->add($data);
    }
    
    /**
     * 登录日志(传输ip地址及ip归属地）
     * @return json
     */
    protected function loginLogs($uid, $flag, $code, $ipData)
    {
        $data = array(
            'user_id' => $uid,
            'flag' => $flag,
            'code' => $code,
            'ip' => $ipData['ip'],
            'ip_attribution' => $ipData['attribution'],
            'addtime' => SYS_TIME,
        );
    
        //登录日志
        $this->model3->add($data);
    }

    /**
     * 生成用户名
     * @return string
     */
    protected function getUsername($length,$type)
    {
        switch ($type) {
            case 5:
                $name = 'qq';
                break;
            case 6:
                $name = 'wx';
                break;
            case 7:
                $name = 'wb';
                break;
            case 8:
                //初始化redis
                $redis = initCacheRedis();
                $tourist = $redis->hMGet("Config:tourist", array('value'));
                //关闭redis链接
                deinitCacheRedis($redis);

                $name = $tourist['value'];
                break;
            case 10: //pc28
                $third_pre=C('third_login_pre');
                $name = $third_pre;
                break;
            default:
                $name = 'rbt';
        }
        $min = pow(10 , ($length - 1));
        $max = pow(10, $length) - 1;
        $username = strtolower($name.mt_rand($min, $max));
        if ($this->model->getUserInfo('username', array('username' => $username), '', '', '', true)) {
            $username = self::getUsernameThird($length,$type);
        }
        return $username;
    }

    /**
     * 注册新用户入口，暂供讯彩用户使用
     * @method POST  /index.php?m = api&c = user&a = xc_reg&username = wangrui
     * @param username string 账户
     * @return json
     * 2017-05-09
     */
    public function xc_reg () {

        //讯彩注册类型为10
        $xc_reg_type = 10;

        //讯彩用户的入口类型为5
        $xc_flag = 5;

        //接收参数
        $this->checkInput($_REQUEST, array('username'), 'all');
        $param = array_map('deal_array', $_POST/*$_GET*/);
        $password = $this->_rand_num(6);
        $username = 'xc_' . trim($param['username']) . '_' . $password;
        
        $flag = trim($param['flag']) ? : $xc_flag;
        $regType = trim($param['type']) ? : $xc_reg_type;

        //验证参数
        if ($this->model->getUserInfo('username', array('username' => $username), '', '', '', true)) {
            ErrorCode::errorResponse(ErrorCode::USER_HAS_EXISTS, 'Username already exists');
        }

        //添加用户
        $data = array(
            'username' => strtolower($username),
            'password' => md5($password),
            'regtime' => SYS_TIME,
            'birthday' => SYS_TIME,
            'regip' => ip(),
            'loginip' => ip(),
            'logintime' => SYS_TIME,
            'logintimes' => 1,
            'reg_type' => $regType,
            'entrance' => $flag,
            'layer_id' => $this->model2->getDefaultLayer()
        );

        if (isset($_REQUEST['referrer']) && !empty($_REQUEST['referrer'])) {
            $field = 'id';
        }
        if (!empty($parentId)) {
            $data['parent_id'] = $parentId;
        }

        $userId = $this->model->add($data);

        if (!$userId) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }

        //添加资金账户
        $map = array(
            'user_id' => $userId
        );
        $this->model2->add($map);

        $parent = ',';
        $layer = 1;
        O('model')->db->query("INSERT INTO `un_user_tree` (`user_id`, `pids`, `layer`) VALUES ({$userId}, '".$parent."', {$layer})");

        $data = array(  
            'uid' => $userId,
            'password' => $password,
            // 'token' => $token,
            'username' => $username,
            'nickname' => $username,
            'avatar' => '/up_files/room/avatar.png'
        );
        ErrorCode::successResponse($data);
    }

    /**
     * 获取会员数据
     * 2017-05-09
     * @param string $id 以半角逗号为分割符号的id串
     * @return json
     */
    public function fetch_member_info () {
        //接收参数
        $this->checkInput($_REQUEST, array('id'), 'all');
        $param = array_map('deal_array', $_POST/*$_GET*/);
        $member_id = trim($param['id']);

        //过滤掉非法id值，id只允许正整数值
        $id_arr = array_filter(explode(',', $member_id), 'intval');

        $field = 'id,username';
        $sql = sprintf('SELECT %s FROM un_user WHERE id IN (%s)', $field, implode(',', $id_arr));
        $user_info = O('model')->db->getAll($sql);

        $data = array(
            'user_info' => $user_info,
        );
        ErrorCode::successResponse($data);
    }

    /**
     * 批量注册新用户入口，暂供讯彩用户使用
     * @method POST  /index.php?m = api&c = user&a = xc_reg_batch &user_info_list=讯彩用户列表json字串
     * @param username string 账户
     * @return json
     * 2017-05-10
     */
    public function xc_reg_batch () {

        //讯彩注册类型为10
        $xc_reg_type = 10;

        //讯彩用户的入口类型为5
        $xc_flag = 5;

        //接收参数
        $this->checkInput($_REQUEST, array('user_info_list'), 'all');
        $param = array_map('deal_array', $_POST/*$_GET*/);

        $flag = trim($param['flag']) ? : $xc_flag;
        $regType = trim($param['type']) ? : $xc_reg_type;

        $user_info_list = $param['user_info_list'];

        //去掉反斜杆，并做json解析
        $user_info_list = json_decode(stripslashes($user_info_list), true);

        //如果数据为空，则无需进行后续操作
        if (! $user_info_list) {
            ErrorCode::errorResponse(ErrorCode::DATA_VOID, 'No data');
            return false;
        }

        foreach ($user_info_list as &$user_info) {

            $password = $this->_rand_num(6);
            $username = 'xc_' . trim($user_info['xc_nickname']) . '_' . $password;

            //验证参数
            if ($this->model->getUserInfo('username', array('username' => $username), '', '', '', true)) {
                ErrorCode::errorResponse(ErrorCode::USER_HAS_EXISTS, 'Username already exists');
                return false;
            }
            $user_info['pcsy_username'] = $username;

            //添加用户
            $data = array(
                'username' => $username,
                'password' => md5($password),
                'regtime' => SYS_TIME,
                'birthday' => SYS_TIME,
                'regip' => ip(),
                'loginip' => ip(),
                'logintime' => SYS_TIME,
                'logintimes' => 1,
                'reg_type' => $regType,
                'entrance' => $flag,
                'layer_id' => $this->model2->getDefaultLayer()
            );

            if (isset($_REQUEST['referrer']) && !empty($_REQUEST['referrer'])) {
                $field = 'id';
            }
            if (!empty($parentId)) {
                $data['parent_id'] = $parentId;
            }

            $userId = $this->model->add($data);
            if (!$userId) {
                ErrorCode::errorResponse(ErrorCode::DB_ERROR);
                return false;
            }
            $user_info['new_pcsy_id'] = $userId;

            //添加资金账户
            $map = array(
                'user_id' => $userId
            );
            $this->model2->add($map);

            $parent = ',';
            $layer = 1;
            O('model')->db->query("INSERT INTO `un_user_tree` (`user_id`, `pids`, `layer`) VALUES ({$userId}, '".$parent."', {$layer})");

        }   // end of foreach

        $data = array(
            'user_info_list' => $user_info_list,
        );
        ErrorCode::successResponse($data);
    }


    /**
     * 生成随机值密码
     * 2017-05-09
     * @param integer $num_len 随机值长度
     * @param integer $mixed_type 随机值类别：1为纯数字 2为纯英文 3为混合
     * @return string 生成的随机值
     */
    private function _rand_num ($num_len = 6, $mixed_type = 1) {
        $rand_num_arr = range(0, 9);
        $rand_word_arr = range('a', 'z');

        if ($mixed_type == 1) {
            $rand_arr = $rand_num_arr;
        } elseif ($mixed_type == 2) {
            $rand_arr = $rand_word_arr;
        } else {
            $rand_arr = array_merge($rand_num_arr, $rand_word_arr);
        }

        $tmp_arr = array();
        for ($i = 0; $i < $num_len; $i++) {
            $tmp_arr[] = $rand_arr[array_rand($rand_arr)];
        }
        $rand_str = implode('', $tmp_arr);
        return $rand_str;
    }

    /**
     * 定时任务：删除僵尸数据
     */
    public function timingDel(){
        //@file_put_contents('./caches/log/jiangshi.log', '开始执行清除操作：'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
        $sql='select value from un_config where nid="timingDel"';
        $res=O('model')->db->getOne($sql);
        if($res['value']){
            $data=json_decode($res['value'],JSON_UNESCAPED_UNICODE);
            
            if($data['isopen']==0){
                dump('未开启清除操作');
                @file_put_contents('./caches/log/jiangshi.log', '未开启清除操作：'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
                return false;
            }
            
            if($data['ope_time']==1){
                if(time()-$data['lasttime']<2592000){
                    dump('每月1号零时零分执行清除操作，但两次操作相隔不到一个月');
                    //@file_put_contents('./caches/log/jiangshi.log', '每月1号零时零分执行清除操作，但两次操作相隔不到一个月：'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
                    return false;
                }
//                 if(date('d')!=1){
//                     dump('每月1号零时零分执行清除操作,但还未到1号');
//                     //@file_put_contents('./caches/log/jiangshi.log', '每月1号零时零分执行清除操作,但还未到1号：'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
//                     return false;
//                 }
            }else{
                if(time()-$data['lasttime']<86400){
                    dump('每天零时零分执行清除操作，但两次操作相隔不到一天');
                    //@file_put_contents('./caches/log/jiangshi.log', '每天零时零分执行清除操作，但两次操作相隔不到一天：'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
                    return false;
                }
            }
            
            //符合条件的用户
            $user_arr_login=array();
            $user_arr_chongzhi=array();
            $user_arr_yue=array();
            $user_arr_yinhangka=array();
            //如果是开启的，并且有设置条件
            if($data['isopen']==1&&($data['loginDay']!=0 || $data['chongzhi']!=0 || $data['yue']!=0 || $data['yinhangka']!=0)){
                //如果设置了连续登陆天数
                $user_arr_login_final=array();
                $user_arr_chongzhi_final=array();
                $user_arr_yue_final=array();
                $user_arr_yinhangka_final=array();
                if($data['loginDay']){
                    //得到指定天数未曾登陆过的用户ID
                    if($data['yueshu']==1){
                        $condition=' and unix_timestamp() - regtime>=2592000';
                    }else{
                        $condition=' and unix_timestamp() - regtime>=5184000';
                    }
                    $sql='select id from un_user where reg_type!=9 and unix_timestamp() - logintime>='.(86400*$data['loginDay']).$condition;
                    $user_arr_login=O('model')->db->getall($sql);
                    if(count($user_arr_login)>0){
                        foreach ($user_arr_login as $v){
                            $user_arr_login_final[]=$v['id'];
                        }
                    }
                }
                
                //如果设置了未充值条件
                if($data['chongzhi']){
                    $sql='select id from un_user where reg_type!=9 and id not in(select user_id as id from un_account_recharge where `status`=1 GROUP BY user_id)';
                    $user_arr_chongzhi=O('model')->db->getall($sql);
                    if(count($user_arr_chongzhi)>0){
                        foreach ($user_arr_chongzhi as $v){
                            $user_arr_chongzhi_final[]=$v['id'];
                        }
                    }
                }
                
                //如果设置了余额条件
                if($data['yue']){
                    $sql='select id from un_user where reg_type!=9 and id in (select user_id from un_account where money=0 and money_freeze=0)';
                    $user_arr_yue=O('model')->db->getall($sql);
                    if(count($user_arr_yue)>0){
                        foreach ($user_arr_yue as $v){
                            $user_arr_yue_final[]=$v['id'];
                        }
                    }
                }
                
                //如果设置了绑定银行卡条件
                if($data['yinhangka']){
                    $sql='select id from un_user where reg_type!=9 and id in (select user_id from un_account where money=0 and money_freeze=0)';
                    $user_arr_yinhangka=O('model')->db->getall($sql);
                    if(count($user_arr_yinhangka)>0){
                        foreach ($user_arr_yinhangka as $v){
                            $user_arr_yinhangka_final[]=$v['id'];
                        }
                    }
                }
                $final=array_merge($user_arr_login_final,$user_arr_chongzhi_final,$user_arr_yue_final,$user_arr_yinhangka_final);
                $final=array_unique($final);
                //有下线的用户不能清楚
                $s_uid = implode(',',$final);
                $sql = "SELECT DISTINCT parent_id FROM `un_user` WHERE `parent_id` IN ({$s_uid})";
                $res = $this->db->getall($sql);
                $pids = array();
                if(!empty($res)){
                    foreach ($res as $v){
                        $pids[] = $v['parent_id'];
                    }
                }
                if(count($final)>0){
                    $final_str='';
                    foreach ($final as $k => $v){
                        if(in_array($v,$pids))continue;//有下线的用户不能清楚
                        if($final[$k+1]){
                            $final_str.=$v.',';
                        }else{
                            $final_str.=$v;
                        }
                    }
                }else{
                    dump('无满足条件的用户，不执行清除操作');
                    @file_put_contents('./caches/log/jiangshi.log', '无满足条件的用户，不执行清除操作：'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
                    return false;
                }
                dump($final_str);exit;
                //用户表
                $sql='delete from un_user where id in('.$final_str.')';
                O('model')->db->query($sql);
                //资金表
                $sql='delete from un_account where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //提现表
                $sql='delete from un_account_cash where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //充值表
                $sql='delete from un_account_recharge where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //资金交易明细表
                $sql='delete from un_account_log where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //返水表
                $sql='delete from un_back_log where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //客服聊天记录表
                $sql='delete from un_custom where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //禁言表
                $sql='delete from un_gag where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //站内信表
                $sql='delete from un_message where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //订单表
                $sql='delete from un_orders where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //session表
                $sql='delete from un_session where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //天天返利表
                $sql='delete from un_ttfl_log where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //用户银行卡表
                $sql='delete from un_user_bank where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //用户登录日志表
                $sql='delete from un_user_login_log where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //第三方登录表
                $sql='delete from un_user_third where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //白名单表
                $sql='delete from un_whitelist where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                //系统审核表
                $sql='delete from un_xitongshenghe where user_id in('.$final_str.')';
                O('model')->db->query($sql);
                
                //记录本次删除的时间
                $data['lasttime']=time();
                $data=json_encode($data,JSON_UNESCAPED_UNICODE);
                $sql="update un_config set value='$data' where nid='timingDel'";
                $res=O('model')->db->query($sql);
                
                $final_str.='['.date('Y-m-d H:i:s').']';
                @file_put_contents('./caches/log/jiangshi.log', $final_str.PHP_EOL,FILE_APPEND);
            }else{
                dump('已开启，但未设置条件，不执行清除操作');
                @file_put_contents('./caches/log/jiangshi.log', '已开启，但未设置条件，不执行清除操作：'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
                return false;
            }
            
        }else{
            //如果没配置，则停止
            dump('无配置信息，不执行清除操作');
            @file_put_contents('./caches/log/jiangshi.log', '无配置信息，不执行清除操作：'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
            return false;
        }
    }
    
    /**
     * 定时清除非正常退出的游客信息
     */
    public function timingDelTourist(){
        @file_put_contents('./caches/log/jiangshi.log', '[非正常退出，删除游客-开始]：'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
        //查询游客
        $sql='select user_id from un_session left join un_user on un_user.id=un_session.user_id where un_session.is_admin=0 and un_user.reg_type=8 and unix_timestamp() - lastvisit>=1800';
        $res=O('model')->db->getall($sql);
        if($res){
            $final_str='';
            foreach ($res as $k=>$v){
                if($res[$k+1]){
                    $final_str.=$v['user_id'].',';
                }else{
                    $final_str.=$v['user_id'];
                }
            }
            //用户表
            $sql='delete from un_user where id in('.$final_str.')';
            O('model')->db->query($sql);
            //资金表
            $sql='delete from un_account where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //提现表
            $sql='delete from un_account_cash where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //充值表
            $sql='delete from un_account_recharge where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //资金交易明细表
            $sql='delete from un_account_log where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //返水表
            $sql='delete from un_back_log where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //客服聊天记录表
            $sql='delete from un_custom where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //禁言表
            $sql='delete from un_gag where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //站内信表
            $sql='delete from un_message where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //订单表
            $sql='delete from un_orders where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //session表
            $sql='delete from un_session where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //天天返利表
            $sql='delete from un_ttfl_log where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //用户银行卡表
            $sql='delete from un_user_bank where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //用户登录日志表
            $sql='delete from un_user_login_log where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //第三方登录表
            $sql='delete from un_user_third where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //白名单表
            $sql='delete from un_whitelist where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            //系统审核表
            $sql='delete from un_xitongshenghe where user_id in('.$final_str.')';
            O('model')->db->query($sql);
            
            dump($final_str);
            @file_put_contents('./caches/log/jiangshi.log', '[非正常退出，删除     游客-结束]：'.$final_str.'-'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
        }else{
            @file_put_contents('./caches/log/jiangshi.log', '[非正常退出，删除游客-结束]-没有满足条件的游客：'.date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
        }
    }
    
    /**
     * Nginx黑名单
     */
    public function setNginxBlacklist(){
        echo shell_exec("id -a");
        die;
        $file='/software/nginx/conf/blockip.conf';
        file_put_contents($file,'deny 47.88.156.0;');
        $myfile = file_get_contents($file);
        dump($myfile);
        var_dump(system('/nginx -s reload && echo ok >/tmp/ok97885'));
        echo '111<br>';
        //var_dump(system('/software/nginx/sbin/nginx -s reload'));
    }
    
    /**
     * 绑定修改第三方登录账户秘密
     * @return bool|mixed|void
     */
    public function bindThirdParty()
    {
        //验证token
        $this->checkAuth();

        //接收参数
        $username = trim($_REQUEST['username']);
        $password = trim($_REQUEST['password']);

        if (!preg_match('/^[a-zA-Z0-9]{6,15}$/', $username)) {
            ErrorCode::errorResponse(ErrorCode::USER_FORMAT_WRONG, 'User name is limited to English letters and numbers, 6 to 15 characters');
        }

        //验证该用户是否是第三方登录
        $sql = "SELECT U.id, U.username, U.password, T.type FROM un_user AS U INNER JOIN un_user_third AS T ON U.id = T.user_id WHERE U.id = {$this->userId}";
        $user = O("model")->db->getone($sql);
        if(empty($user) || !empty($user['password'])){
            ErrorCode::errorResponse(ErrorCode::DATA_VOID, 'The user is not eligible for modification!!');
        }

        //验证用户名
        if($user['username'] != $username){
            //验证参数
            if ($this->model->getUserInfo('username', array('username' => $username), '', '', '', true)) {
                ErrorCode::errorResponse(ErrorCode::USER_HAS_EXISTS, 'Username already exists');
            }
        }

        //验证密码
        if (!preg_match('/^[a-zA-Z0-9_]{6,15}$/', $password)) {
            ErrorCode::errorResponse(ErrorCode::PWD_FORMAT_WRONG, 'The password is limited to English letters, numbers and underscores, 6 to 15 characters');
        }

        //修改密码
        $res = $this->model->save(array('password' => md5($password),'username'=>$username), array('id' => $this->userId));
        if (!$res) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }
        $data = array(
            'username' => $username,
            'state' => 2
        );
        ErrorCode::successResponse($data);
    }
    
    /*
     * 获取幸运28当前时间的期号和开奖时间
     */
    public function getQihao(){
        $fileName=$_SERVER['HOST'].'timingSchedule.json';
        $content=file_get_contents($fileName);
        $content=json_decode($content,true);
        
        $nowtime=time();
        
        if($nowtime>=$content['shijian']){
            $temp['qihao']=$content['qihao'];
            $temp['shijian']=$content['shijian'];
            $temp['shijian_geshi']=date('Y-m-d H:i:s');
            dump($temp);
            return;
        }else{
            foreach ($content['qihao_arr'] as $k=>$v){
                if(time()<$v['shijian']){
                    dump($v);
                    break;
                }
            }
        }
    }
    
    //获取荣誉等级
    public function getHonor()
    {
        //验证token
        $this->checkAuth();
        
        $data = $this->model->getHonor($this->userId);
        
        ErrorCode::successResponse($data);
    }
    
    //获取积分记录列表
    public function getHonorRecordList()
    {
        $data = $_REQUEST;
    
        //验证token
        $this->checkAuth();
    
        if (empty($data['type']) || !is_numeric($data['type'])) {
            $data['type'] = 0;
        }
    
        //初始化redis
        $redis = initCacheRedis();
        $page_cfg = $redis->hGetAll("Config:100009"); //获取每页展示多少数据
        $pagesize = isset($page_cfg['value']) ? $page_cfg['value'] : 20;
        //关闭redis
        deinitCacheRedis($redis);
    
        //交易类型列表
        $textType = array(
            0 => '全部',
            1 => '充值', //充值：充值
            2 => '投注', //投注：投注
            3 => '中奖', //中奖：中奖
            4 => '修改'  //赠送：人工后台修改
        );
    
        $data['user_id'] = $this->userId;
    
        $count = $this->model->getHonorCount($data);
        $data['pagestart'] = 1;
        $data['pagesize'] = $pagesize;
    
        $honorScoreList = $this->model->getHonorRecordList($data);
    
        ErrorCode::successResponse($honorScoreList);
    }

    public function getLetter($num){
        $codes = "abcdefghijkmnpqrstuvwxy";
        $code = "";
        for($i=0; $i < $num; $i++) {
            $code .=$codes{rand(0, strlen($codes)-1)};
        }
        return $code;
    }
    
    //检查是否已经关注
    public function checkFollowUser()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'user_id'), 'all');
        //验证token
        $this->checkAuth();
        $user_id = trim($_REQUEST['user_id']);

        $ret = $this->model->checkFollowUser($user_id, $this->userId);
    
        ErrorCode::successResponse($ret);
    }
    
    //关注
    public function addFollowUser()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'user_id'), 'all');
        //验证token
        $this->checkAuth();
        $user_id = trim($_REQUEST['user_id']);

        $userinfo = $this->db->getone('select reg_type from un_user where id = '.$this->userId);
        if($userinfo['reg_type'] == 11) ErrorCode::errorResponse(210300, 'Robot does not have permission to follow');

        $ret = $this->model->addFollowUser($user_id, $this->userId);
        
        if ($ret['code'] == 0) {
            ErrorCode::successResponse(['ret_msg' => 'Follow successfully!']);
        }else {
            ErrorCode::errorResponse(210300, $ret['msg']);
        }
    }
    
    //取消关注
    public function cancelFollowUser()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'user_id'), 'all');
        //验证token
        $this->checkAuth();
        $user_id = trim($_REQUEST['user_id']);
        
        $ret = $this->model->cancelFollowUser($user_id, $this->userId);
        
        if ($ret['code'] == 0) {
            ErrorCode::successResponse(['ret_msg' => 'Unfollow successfully!']);
        }else {
            ErrorCode::errorResponse(210300, $ret['msg']);
        }
    }
    
    //房间内获取关注者最近本房间投注没人5条记录
    public function getRoomFollowUser()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'room_id'), 'all');
        //验证token
        $this->checkAuth();
        $room_id = trim($_REQUEST['room_id']);
        $page = getParame('page', 0, 0, 'int');     //兼容旧版本    为空则不分页

        $ret = $this->model->getRoomFollowUser($room_id, $this->userId, $page);
        
        if ($ret['code'] == 0) {
            ErrorCode::successResponse(['data' => $ret['data']]);
        }else {
            ErrorCode::errorResponse(210300, $ret['msg']);
        }
    }
    
    
    //我的关注页面最近投注没人5条记录
    public function getFollowUserOrderList()
    {
        //验证token
        $this->checkAuth();
        
        $ret = $this->model->getFollowUserOrderList($this->userId);
        
        if ($ret['code'] == 0) {
             ErrorCode::successResponse(['data' => $ret['data']]);
        }else {
            ErrorCode::errorResponse(210300, $ret['msg']);
        }
    }


    //投注排行榜
    public function bet_rank() {
        //验证token
        $this->checkAuth();

        $page = getParame('page',0,1,'int');
     
        $ret = $this->betRankModel->getBetRank($this->userId,$page);

        if ($ret['code'] == 0) {
            ErrorCode::successResponse(['data' => $ret['data']]);
        }else {
            ErrorCode::errorResponse(210300, $ret['msg']);
        }
    }
    
   
    
    
    
    
    
    
    
    
    
    
}
