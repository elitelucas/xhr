<?php
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

/**
 * 用户体系
 *
 */
class UserAction extends Action
{

    private $model;
    private $model2;
    private $model3;

    public function __construct()
    {
      
        parent::__construct();
        $this->model = D('user');
        $this->model2 = D('account');
        $this->model3 = D('userlog');
      
    }

    public function validatecode(){
        require_once S_ROOT."/core/class/validatecode.php";
        session_start();
        $img = new validatecode();
        $img->outimg();
        $this->setCheckCode($img->getcode());
        $_SESSION["register_code"] = $img->getcode();
    }

    private function setCheckCode($check_code){
        @$dif_ip = $_SERVER['REMOTE_ADDR'];
        $check_code = md5(strtoupper($check_code));
        $time = time();
        $where = "dif_ip = '$dif_ip'";
     
        $this->db->query("DElETE FROM `un_outside_check` WHERE $where");
        $this->db->query("INSERT INTO `un_outside_check` SET `dif_ip`='$dif_ip',`check_code`='$check_code',`time`='$time'");
        $current_time = time() - 120;
        $where = "time < $current_time";
        $this->db->query("DElETE FROM `un_outside_check` WHERE $where");
    }


    /**
     * 用户注册界面
     */
    public function register()
    {
        $type = 1;
        if((session::is_set('type') && !empty(session::get('type'))) && (session::is_set('pid') && !empty(session::get('pid')))){
            $type = session::get('type');
            $field = 'id,username';
            $pid = $this->model->getUserInfo($field, array('id' => session::get('pid')), 1);
        }elseif (isset($_REQUEST['pid']) && !empty($_REQUEST['pid'])) {
            $field = 'id,username';
            $pid = $this->model->getUserInfo($field, array('id' => trim($_REQUEST['pid'])), 1);
			if (isset($_REQUEST['type']) && !empty($_REQUEST['type'])) {
	            $type = trim($_REQUEST['type']);
	        }else{
	            $type = 1;
	        }
        }
        unset($_SESSION["register_code"]);
        $rand = rand(10,1001111);
        //注册项配置
        $registerText = ['weixin' => '微信号', 'qq' => 'QQ', 'mobile' => '电话号码', 'email' => '邮箱',"register"=>"验证码"];
//        $dataType = ['weixin' => '/^[a-zA-Z0-9_-]{3,30}$/', 'qq' => '/^[1-9]{1}[0-9]{3,}$/', 'mobile' => '/^1[34578]{1}[0-9]{9}$/', 'email' => 'e',"register"=>"/^[0-9]/"];  //Validform验证使用
        $dataType = ['weixin' => '', 'qq' => '', 'mobile' => '', 'email' => '',"register"=>""];  //Validform验证使用
        $registerJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'set_register_info'");
        $register = json_decode($registerJson['value'],true);
        $registerData = $register['register'];


        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'reg'));
        $reg = $this->URL(array('c' => 'user', 'a' => 'register'));
        $selectName = $this->URL(array('c' => 'user', 'a' => 'selectName'));
        include template('my/register');
    }

    /**
     * 用户注册查询账户是否可注册
     */
    public function selectName()
    {
        if (isset($_REQUEST['param']) && !empty($_REQUEST['param'])) {
            $username = strtolower(trim($_REQUEST['param']));
            if ($this->model->getUserInfo('username', array('username' => $username), '', '', '', true) || preg_match('/.*(script).*/', $username)) {
                $this->ajaxReturn(100009, '账号已存在!', 'n');
            } else {
                $this->ajaxReturn(200002, '账号可以用!', 'y');
            }

        }
        $this->ajaxReturn(100008, '账号不能为空!', 'n');
    }

    /**
     * 用户注册
     * @param username string 账户
     * @param password string 密码
     * @param password2 string 确认密码
     * @param referrer string 推荐人账户
     * @param flag string 入口标示
     * @param code string 机身码
     */
    public function reg()
    {	
        session_start();
        $this->checkInput($_REQUEST, array('username', 'password', 'password2', 'flag','type'), 'all');
        $param = array_map('deal_array', $_POST);
        $reg_type  = empty($param['reg_type']) ? false : trim($param['reg_type']);
        $username  = trim($param['username']);
        $password  = trim($param['password']);
        $password2 = trim($param['password2']);
        $flag      = trim($_REQUEST['flag']);
        $code      = trim($_SERVER['HTTP_USER_AGENT']);
        $regType   = trim($_REQUEST['type']);
        $weixin    = isset($param['weixin']) ? trim($param['weixin']) : '';
        $qq        = isset($param['qq']) ? trim($param['qq']) : '';
        $mobile    = isset($param['mobile']) ? trim($param['mobile']) : '';
        $email     = isset($param['email']) ? trim($param['email']) : '';
        $register     = isset($param['register']) ? strtoupper(trim($param['register'])): '';
        $registerData = array('username', 'password', 'password2', 'flag','type');
        $limit = [];
        $limit["register_limit"] = 100;
        $limit["register_times"] = 100;
        @$session_code = strtoupper($_SESSION["register_code"]);
        if(isset($register)&&$register!=""&&$register!=$session_code) {
            ErrorCode::errorResponse(ErrorCode::PWD_DIFFERENT, 'Incorrect verification code');
        }
        
        if (!$reg_type) {
            //注册项配置
    //        $registerJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'set_register_info'");
    //        $register = json_decode($registerJson['value'],true);
            $redis = initCacheRedis();
            $register = json_decode($redis->hGet("Config:set_register_info","value"),true);
            deinitCacheRedis($redis);
            $registerSetData = $register['register'];
            $limit = $register["limit"];
            foreach ($registerSetData as $kr => $vr) {
                if ($vr == 1) {
                    $registerData[] = $kr;
                    if ($register['status'] == 1) {
                        if (empty($param[$kr])) {
                            $registerText = ['weixin' => 'WeChat number', 'qq' => 'QQ', 'mobile' => 'Mobile number', 'email' => 'Email'];
                            ErrorCode::errorResponse(1720, $registerText[$kr] . 'cannot be empty');
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
                            ErrorCode::errorResponse(ErrorCode::USER_FORMAT_WRONG, 'Username is limited to English letters and numbers, 6 to 15 characters');
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
//                   case 'weixin':
//                        if (!empty($weixin) && !preg_match('/^[a-zA-Z]{1}[a-zA-Z0-9_-]{3,30}$/', $weixin)) {
//                            ErrorCode::errorResponse(ErrorCode::USER_WEIXIN, '微信号格式错误');
//                        }
//                        break;
//                    case 'qq':
//                        if (!empty($qq) && !preg_match('/^[1-9]{1}[0-9]{4,14}$/', $qq)) {
//                            ErrorCode::errorResponse(ErrorCode::USER_QQ, 'QQ号格式错误');
//                        }
//                        break;
//                    case 'mobile':
//                        if (!empty($mobile) && !preg_match('/^[0-9]{11}$/', $mobile)) {
//                            ErrorCode::errorResponse(ErrorCode::USER_MOBILE, '手机号码错误');
//                        }
//                        break;
//                    case 'email':
//                        if (!empty($email) && !preg_match('/^([_a-z0-9-]+)(\.[_a-z0-9-]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', $email)) {
//                            ErrorCode::errorResponse(ErrorCode::USER_EMAIL, '邮箱格式错误');
//                        }
//                        break;
                    default:
                }
            }
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
            $parent = $res['pids'].$data['parent_id'].',';
            $layer = $res['layer']+1;
        }else{
            $parent = ',';
            $layer = 1;
        }



        O('model')->db->query("INSERT INTO `un_user_tree` (`user_id`, `pids`, `follow_user_id`, `layer`) VALUES ({$userId}, '".$parent."', ',', {$layer})");

        //设置登录信息
        if($regType != 4){ //4线下开户
            $this->loginLogs($userId, $flag, $code, $ipData);
            $token = $this->setToken($userId);
            session::set('token', $token);
            session::set('uid', $userId);
            session::set('username', $username);
            session::set('nickname', $data['nickname']);
            //随机一个默认头像，不用写死
            // $avatar = '/up_files/room/avatar.png';
            session::set('avatar', $avatar);
            cookie::set('name',$username);
            cookie::set('pwd',$password);
        }
        @$_SESSION["reg_ip"] = $_SERVER['REMOTE_ADDR'];


        ErrorCode::successResponse(array('JumpUrl' => url('web', 'lobby', 'index')));
    }

    /**
     * 用户注册
     * @param token string 用户token
     * @param username string 账户
     * @param password string 密码
     * @param password2 string 确认密码
     * @param referrer string 推荐人账户
     * @param flag string 入口标示
     * @param code string 机身码
     */
    /*
    public function reg()
    {
        //接收参数
        $this->checkInput($_REQUEST, array('username', 'password', 'password2', 'flag','type'), 'all');
        $param = array_map('deal_array', $_POST);
        $username = trim($param['username']);
        //$username = strtolower(trim($param['username']));
        $password = trim($param['password']);
        $password2 = trim($param['password2']);
        $flag = trim($_REQUEST['flag']);
        $code = trim($_SERVER['HTTP_USER_AGENT']);
        $regType = trim($_REQUEST['type']);
        */
        //验证参数
        //if ($this->model->getUserInfo('username', array('username' => $username), '', '', '', true) || preg_match('/.*(script).*/', $username)) {
        /*
            ErrorCode::errorResponse(ErrorCode::USER_HAS_EXISTS, 'Username already exists');
        }
    
        if (!preg_match('/^[a-zA-Z0-9]{6,15}$/', $username)) {
            ErrorCode::errorResponse(ErrorCode::USER_FORMAT_WRONG, 'Username is limited to English letters and numbers, 6 to 15 characters');
        }
        if (!preg_match('/^[a-zA-Z0-9_]{6,15}$/', $password)) {
            ErrorCode::errorResponse(ErrorCode::PWD_FORMAT_WRONG, 'The password is limited to English letters, numbers and underscores, 6 to 15 characters');
        }
        if ($password != $password2) {
            ErrorCode::errorResponse(ErrorCode::PWD_DIFFERENT, 'Two password entries are inconsistent');
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
    
        //设置登录信息
        if($regType != 4){ //4线下开户
            $this->loginLogs($userId, $flag, $code, $ipData);
            $token = $this->setToken($userId);
            session::set('token', $token);
            session::set('uid', $userId);
            session::set('username', $username);
            session::set('nickname', $data['nickname']);
            //随机一个默认头像，不用写死
            // $avatar = '/up_files/room/avatar.png';
            session::set('avatar', $avatar);
            cookie::set('name',$username);
            cookie::set('pwd',$password);
        }
        ErrorCode::successResponse(array('JumpUrl' => url('web', 'lobby', 'index')));
    }
    */
    
    /**
     * 用户登录界面
     */
    public function login()
    {
        $redis = initCacheRedis();
        //客服配置
        $val = $redis->hget('Config:kefu_set','value');
        $kefu = decode($val);
        deinitCacheRedis($redis);
        //提交数据地址
        $login = $this->URL(array('c' => 'user', 'a' => 'loginValid'));
        //注册数账号地址
        $reg = $this->URL(array('c' => 'user', 'a' => 'register'));
        //忘记密码地址
        $forgetPsd = $this->URL(array('c' => 'user', 'a' => 'register'));
        //游客登录地址
        $ykLogin = $this->URL(array('c' => 'user', 'a' => 'registerMachine'));

        include template('my/login');
    }

    /**
     * 用户登入
     * @param username string 账户
     * @param password string 密码
     * @param flag string 入口标示
     * @param code string 机身码
     */
    public function loginValid()
    {
        //接收参数
        $this->checkInput($_REQUEST, array('username', 'password', 'flag','type'), 'all');
        //$username = strtolower(trim($_REQUEST['username']));
        $username = trim($_REQUEST['username']);
        $password = trim($_REQUEST['password']);
        $flag = trim($_REQUEST['flag']);
        $code = trim($_SERVER['HTTP_USER_AGENT']);
        $logType = trim($_REQUEST['type']);
        $rember = trim($_REQUEST['rember']);

        //获取IP地址及ip归属地
        $ipData = getIp();

        //第三方登录
        if (in_array($logType, array(5, 6, 7))) {

        }else{
            //验证账号密码
            $sql = "SELECT id,nickname,avatar,reg_type FROM un_user WHERE username = '" . $username . "' AND password = '" . md5($password) . "' AND state IN(0,1)";
            $userInfo = O('model')->db->getOne($sql);
            if (empty($userInfo)) {
                ErrorCode::errorResponse(ErrorCode::PHONE_OR_PWD_INVALID);
            }

            $userId = $userInfo['id'];

            $reg_type = $userInfo['reg_type'];
            session::set('reg_type', $reg_type);

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

                //将讯彩端的游戏积分带入PC手游端，写入日志
                $xc_point = $ret_data['xc_point'];
                $remark = 'User login, bring in XC terminal points';
                //$ip = ip();
                $sys_time = SYS_TIME;
                $insert_sql = "INSERT INTO `un_xc_account_log` (`user_id`, `money`, `use_money`, `remark`, `addtime`, `addip`, `addip_attribution`) VALUES ('{$userId}', '{$xc_point}', '{$xc_point}', '{$remark}', '{$sys_time}', '{$ipData['ip']}', '{$ipData['attribution']}')";
                O('model')->db->query($insert_sql);

                //更新PC手游端的积分数据，在原有积分上，加上讯彩端带过来的积分
                $update_sql = "UPDATE `un_account` SET `money` = `money` + '{$xc_point}' WHERE `user_id` = '{$userId}' ";
                O('model')->db->query($update_sql);
            }

            //扣除积分
            loseScore($userId);

            //更新登录信息
            //$this->model->updateLoginInfos($userId, $ipData);
        }

        //更新登录信息
        $this->model->updateLoginInfos($userId, $ipData);

        //去掉更新设备，这里更新的设备字段，为注册设备，最后登录设备已记录在 un_user_login_log 表
        // $this->model->save(array('entrance' => $flag), array('id' => $userId)); //更新用户设备登录类型

        //设置登录信息
        $token = $this->setToken($userId, $ipData);
        $this->loginLogs($userId, $flag, $code, $ipData);
        session::set('token', $token);
        session::set('uid', $userId);
        session::set('username', $username);
        $nickname = empty($userInfo['nickname']) ? "" : $userInfo['nickname'];
        session::set('nickname', $nickname);
        $avatar = empty($userInfo['avatar']) ? '/up_files/room/avatar.png' : $userInfo['avatar'];
        session::set('avatar', $avatar);
        if($rember==2){
            session::set('rember', 2);
            cookie::set('name',$username);
            cookie::set('pwd',$password);
        }else{
            session::set('rember', 1);
        }

        ErrorCode::successResponse(array('JumpUrl' => url('web', 'lobby', 'index')));
    }

    /**
     * 游客登录
     * @method POST  /index.php?m = api&c = user&a = registerMachine
     * @param flag string 入口标示
     * @param code string 机身码
     * @return json
     */
    public function registerMachine (){
        //注册时不同域名跨域问题
        header("Access-Control-Allow-Origin: *");
        $username = $this->getUsername(6,8);
        $flag = 3;
        $code = trim($_SERVER['HTTP_USER_AGENT']);
        $prefix = $this->db->getone("select value from un_config where nid = 'tourist'");
        
        //获取IP地址及ip归属地
        $ipData = getIp();

        //添加用户
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
            'entrance' => $flag
        );

        $userId = $this->model->add($data);
        lg('register_machine',var_export(array(
            '$userId'=>$userId,
            '$this->db'=>$this->db,
            '$this->db->_sql()'=>$this->db->_sql(),
        ),1));

        if (!$userId) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }

        if(!empty(C('db_port'))) {
            sleep(1); //兼容Mycat
        }

   //添加资金账户
        $map = array(
            'user_id' => $userId,
            'money' => 2000
        );
        $this->model2->add($map);
        $this->loginLogs($userId, $flag, $code, $ipData);
        //设置登录信息
        if (!empty($parentId)) {
            ErrorCode::successResponse();
        }
        //设置登录信息
        $token = $this->setToken($userId);
        if($_REQUEST['rember'] == 2){
            session::set('rember', 2);
        }else{
            session::set('rember', 1);
        }
        session::set('token', $token);
        session::set('uid', $userId);
        session::set('username', $username);
        session::set('nickname', $data['nickname']);
        // $avatar = '/up_files/room/avatar.png';
        //随机一个默认头像
        $avatar = D('Avatar')->fetchRandomPic();
        session::set('avatar', $avatar);
        ErrorCode::successResponse(array('JumpUrl' => url('web', 'lobby', 'index')));
    }

    /**
     * 用户登出
     */
    public function logout()
    {
        //验证token
        $this->checkAuth();
        
        //如果reg_type为10（讯彩用户），则将退出状态设置到讯彩的数据中间站服务器
        $reg_type=O('model')->db->getOne('select reg_type from un_user where id='.$this->userId)['reg_type'];
        if ($reg_type == 10) {
            
            //获取IP地址及ip归属地
            $ipData = getIp();

            //查询该讯彩用户积分，并传到中间站
            $fetch_sql = "SELECT `money` FROM `un_account` WHERE `user_id` = '{$this->userId}'";
            $res = O('model')->db->getOne($fetch_sql);

            //整数部分的积分
            $tmp_integer_part = floor($res['money']);
            $post_data = array(
                'pcsy_online_status' => 0,
                'pcsy_id' => $this->userId,
                'pcsy_point' => $tmp_integer_part,
            );

            //TODO: 同login方法，按需，建立访问API失败后的重试机制
            $ret_data = curl_post_content(C('transfer_site_set_status'), $post_data);

            //将PC手游端的游戏积分清零，写入日志
            $remark = 'Users log out and clear the integral part of the points on the PC mobile game terminal';
             //$ip = ip();
            $sys_time = SYS_TIME;
            //小数部分的积分，即总分减去整数部分的积分
            $tmp_decimal_part = $res['money'] - $tmp_integer_part;
            $insert_sql = "INSERT INTO `un_xc_account_log` (`user_id`, `money`, `use_money`, `remark`, `addtime`, `addip`, `addip_attribution`) VALUES ('{$this->userId}', '{$tmp_integer_part}', '{$tmp_decimal_part}', '{$remark}', '{$sys_time}', '{$ipData['ip']}', '{$ipData['attribution']}')";
            O('model')->db->query($insert_sql);

            //退出登录时，清零该讯彩用户积分
            $update_sql = "UPDATE `un_account` SET `money` = '{$tmp_decimal_part}' WHERE `user_id` = '{$this->userId}'";
            O('model')->db->query($update_sql);
        }else if($reg_type==8){

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

        //修改密码
        $res = $this->model->save(array('password' => md5($newpsd)), array('id' => $this->userId));
        if (!$res) {
            ErrorCode::errorResponse(ErrorCode::DB_ERROR);
        }

        //记录操作日志
        $this->opLog(2);

        ErrorCode::successResponse();
    }

    //上传用户头像
    public function saveAvatar()
    {
        //验证token
        $this->checkAuth();
        $userid = $this->userId;
        $avatarFileName = $this->getAvatarFilename($userid, 'jpg');
        $avatarUrl = $this->getAvatarUrl($avatarFileName, 0);

        $res = $this->model->getUserInfo('avatar', array('id' => $userid), 1);
        if ($res['avatar']) {
            $oldPath = $this->getAvatarPath($res['avatar']);
            @unlink($oldPath);
        }
        $res = file_put_contents($avatarUrl, base64_decode(str_replace('data:image/jpeg;base64,', '', $_REQUEST['avatar'])));
        if (!$res) {
            ErrorCode::errorResponse(100023, 'Avatar upload failed');
        }
        $data = array('avatar' => '/' . C('upfile_path') . '/avatar/' . $avatarFileName);

        $res = $this->model->save($data, array('id' => $userid));
        if (!$res) {
            ErrorCode::errorResponse(100023, 'Avatar upload failed');
        }
        session::set('avatar', $data['avatar']);

        //完成平台任务
        $arr = D('admin/activity')->taskSuccess(6, $this->userId);
        if (!$arr) {
            ErrorCode::errorResponse('100019','Platform task failed to complete');
        }

        //记录操作日志
        $this->opLog(1);

        ErrorCode::successResponse($data);
    }

    /**
     * 个人中心
     * @method GET /index.php?m = web&c = user&a = my
     */
    public function my()
    {
        //验证token
        $this->checkAuth();
        //查询用户信息
        $userInfo = array();
        $userInfo['avatar'] = session::get('avatar');
        $username = session::get('username');
        $nickname = session::get('nickname');
        $uid = session::get('uid');
        $reg_type = $this->db->getone("select reg_type from #@_user where id = {$uid}")['reg_type'];

        $redis = initCacheRedis();
        $bo_bin = json_decode($redis->hGet("Config:"."bo_bin","value"),true);//redis里面博饼入口
        $christmas = json_decode($redis->hGet("Config:"."christmas","value"),true);//redis里面圣诞入口
        $christmas['state'] = 0;
        $bo_bin['state'] = 0;
        $activity_config = $this->db->getall("select id,activity_type from #@_activity where state = 1");
        if(!empty($activity_config)){
            foreach ($activity_config as $val) {
                if($val['activity_type'] == 1){
                    $bo_bin['state'] = 1;
                }
                if ($val['activity_type'] == 2) {
                    $christmas['state'] = 1;
                }
            }
        }

        /*取redis中的大转盘入口开启状态 开始*/
        $turntable_value = json_decode($redis->hGet('Config:'.'turntable_setting', 'value'), true);
        $is_show_in_profile = $turntable_value['is_show_in_profile'];
        deinitCacheRedis($redis);
        /*取redis中的大转盘入口开启状态 开始*/

        $token = $_SESSION['SN_']['token'];
        $userInfo['nickname'] = empty($nickname) ? "" : $nickname;
        /*
        $honor = get_honor_level($this->userId);
        if(($honor['status1'] && $honor['status']) || ($honor['status'] && $honor['score']==0)){
            $userInfo['honor'] = $honor['name'];
            $userInfo['icon'] = $honor['icon'];
        }
        */
        
        $honor = get_honor_info($this->userId);

        $rtArr = $this->model->getOneCoupon('reg_type', array('id' => $this->userId));
        $JumpUrl = $this->getUrl();
        include template('my/my');
    }
    
    /**
     * 获取荣誉升级弹出框状态
     * @method POST
     * @param token string 用户token
     * @return json
     */
    public function getHonorBox()
    {
        //验证token
        $this->checkAuth();
    
        $ret = $this->model->getHonorBox($this->userId);
        
        echo json_encode($ret);
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
     * 荣誉等级详细信息
     * @method GET /index.php?m = web&c = user&a = honorDetails
     */
    public function honorDetails()
    {
        //验证token
        $this->checkAuth();

        //url
        $JumpUrl = $this->getUrl();
        include template('my/honorDetails');
    }

    /**
     * 用户详细信息
     * @method GET /index.php?m = web&c = user&a = userInfo
     */
    public function userInfo()
    {
        //验证token
        $this->checkAuth();

        //查询用户信息
        $userInfo = array();
        $fields = 'id,mobile,qq,email,avatar,nickname,username,realname,birthday,weixin,sex,signature,reg_type'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
        $honor = get_honor_info($this->userId);
        $userInfo['weixin']=decrypt($userInfo['weixin']);
        $userInfo['email']=decrypt($userInfo['email']);
        $userInfo['mobile']=decrypt($userInfo['mobile']);
        //url
        $JumpUrl = $this->getUrl();
        include template('my/personalInformation');
    }

    /**
     * 用户默认头像设置
     * @method GET /index.php?m = web&c = user&a = setIcon
     */
    public function setIcon()
    {
        //验证token
        $this->checkAuth();
        $token = $this->db->result("SELECT sessionid FROM un_session WHERE user_id = {$this->userId}");

        include template('my/setIcon');
    }

    /**
     * 修改设置昵称
     * @method GET /index.php?m = web&c = user&a = setNickname
     */
    public function setNickname()
    {
        //验证token
        $this->checkAuth();
        //查询用户信息
        $userInfo = array();
        $userInfo['nickname'] = session::get('nickname');
        $JumpUrl = $this->getUrl();
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'setInfo'));
        include template('my/modifyNickname');
    }

    /**
     * 修改设置性别
     * @method GET /index.php?m = web&c = user&a = setSex
     */
    public function setSex()
    {
        //验证token
        $this->checkAuth();
        //查询用户信息
        $fields = 'id,sex'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
        $JumpUrl = $this->getUrl();
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'setInfo'));
        include template('my/modifyGender');
    }

    /**
     * 修改设置名字
     * @method GET /index.php?m = web&c = user&a = setRealname
     */
    public function setRealname()
    {
        //验证token
        $this->checkAuth();
        //查询用户信息
        $fields = 'id,realname'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
        $JumpUrl = $this->getUrl();
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'setInfo'));
        include template('my/modifyChineseName');
    }

    /**
     * 修改设置生日
     * @method GET
     */
    public function setBirthday()
    {
        //验证token
        $this->checkAuth();
        //查询用户信息
        $fields = 'id,birthday'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
        $JumpUrl = $this->getUrl();
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'setInfo'));
        include template('my/modifyBirth');
    }

    /**
     * 修改设置微信号
     * @method GET
     */
    public function setWeixin()
    {
        //验证token
        $this->checkAuth();
        //查询用户信息
        $fields = 'id,weixin'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
        $userInfo['weixin']=decrypt($userInfo['weixin']);
        $JumpUrl = $this->getUrl();
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'setInfo'));
        include template('my/modifyWeixin');
    }
    
    /**
     * 修改设置QQ号
     * @method GET
     */
    public function setQQ()
    {
        //验证token
        $this->checkAuth();
        //查询用户信息
        $fields = 'id,qq'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
        $userInfo['qq']=decrypt($userInfo['qq']);
        $JumpUrl = $this->getUrl();
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'setInfo'));
        include template('my/modifyQQ');
    }

    /**
     * 修改设置邮箱
     * @method GET /index.php?m = web&c = user&a = setBirthday
     */
    public function setEmail()
    {
        //验证token
        $this->checkAuth();
        //查询用户信息
        $fields = 'id,email'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
        $userInfo['email']=decrypt($userInfo['email']);
        $JumpUrl = $this->getUrl();
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'setInfo'));
        include template('my/modifyEmail');
    }

    /**
     * 修改设置手机号
     * @method GET
     */
    public function setMobile()
    {
        //验证token
        $this->checkAuth();
        //查询用户信息
        $fields = 'id,mobile'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
        $JumpUrl = $this->getUrl();
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'setInfo'));
        include template('my/modifyPhone');
    }

    /**
     * 设置用户个人资料
     */
    public function setInfo()
    {
        //验证token
        $this->checkAuth();
        $param = array_map('deal_array', $_POST);
        $param_keys = array_keys($param);
        isset($param['birthday']) ? $param['birthday'] = strtotime($param['birthday']) : '';
        $filed = array('nickname', 'sex', 'signature', 'email', 'birthday', 'weixin', 'mobile', 'qq');
        $encode_filed = array('email', 'weixin','mobile', 'qq');
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
        if (!res) {
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

        $JumpUrl = url("web",'user',  'userInfo');
        //修改session 值
        isset($param['nickname']) ? session::set('nickname', $param['nickname']) : '';
        isset($param['avatar']) ? session::set('avatar', $param['avatar']) : '';
        //$this->ajaxReturn();

        //记录操作日志
        $this->opLog(1);

        ErrorCode::successResponse(array('JumpUrl' => $JumpUrl));
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
        //验证token
        $this->checkAuth();

        $param = array_map('deal_array', $_POST);

        $psd = trim($param['password']);
        $res = $this->validate($psd, 2);
        if (!$res) {
            ErrorCode::errorResponse(100014, 'The password format is incorrect, please fill in a 6-digit password');
        }
        $psd2 = trim($param['password2']);
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

        //记录操作日志
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
        //验证token
        $this->checkAuth();

        $param = array_map('deal_array', $_POST);
        $old_psd = trim($param['old_psd']);
        $res = $this->validate($old_psd, 1);
        if (!$res) {
            ErrorCode::errorResponse(100013, 'The format of the old password is incorrect, please fill in a 6-digit password');
        }
        $psd = trim($param['new_psd']);
        $res = $this->validate($psd, 1);
        if (!$res) {
            ErrorCode::errorResponse(100014, 'The password format is incorrect, please fill in a 6-digit password');
        }
        $psd2 = trim($param['new_psd2']);

        $fields = 'id,paypassword,regtime'; //需要的字段
        $userInfo = $this->model->getUserInfo($fields, array('id' => $this->userId), 1);
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

        //记录操作日志
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
        include template('my/agencySystem');
    }

    /**
     * 代理分享
     * @method get /index.php?m = api&c = user&a = agentSharing&token = b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return  json
     */
    public function agentSharing()
    {
        //验证token
        $this->checkAuth();
        $redis = initCacheRedis();
        $reg_sw = $redis->hGet('Config:AgencyRegSwitch','value');
        deinitCacheRedis($redis);
        $data = decode($reg_sw);

        $url = $this->URL(array('c' => 'user', 'a' => 'register')) . "&pid=" . $this->userId."&type=2";
        $url2 = $this->URL(array('c' => 'user', 'a' => 'register')) . "&pid=" . $this->userId."&type=3";
        include template('my/agentSharing');
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
        $value = $this->URL(array('c' => 'user', 'a' => 'register')) . "&pid=" . $this->userId."&type=".$type;
        $errorCorrectionLevel = "L"; // 纠错级别：L、M、Q、H
        $matrixPointSize = "6"; // 点的大小：1到10
        Qrcode::png($value, false, $errorCorrectionLevel, $matrixPointSize);
    }

    /**
     * 团队管理
     * @method POST /index.php?m = api&c = user&a = openAccount&username = wangrui2354&password = aa112233&password2 = aa112233&token = 45e3845fe6fc08283c1d7ef300dfc6ff
     * @param token string
     * @return  json
     */
    public function workTeam()
    {
        //验证token
        $this->checkAuth();
        $JumpUrl = $this->getUrl();
        include template('teamManage/teamManage');
    }

    /**
     * 下线开户
     * @method POST /index.php?m = api&c = user&a = openAccount&username = wangrui2354&password = aa112233&password2 = aa112233&token = 45e3845fe6fc08283c1d7ef300dfc6ff
     * @param token string
     * @return  json
     */
    public function openAccount()
    {
        //验证token
        $this->checkAuth();
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'reg'));
        $username = session::get('username');
        $selectName = $this->URL(array('c' => 'user', 'a' => 'selectName'));
        include template('teamManage/open');
    }

    /**
     * 下线开户成功
     * @method GEt /index.php?m = api&c = user&a = openAccountOk
     * @return  json
     */
    public function openAccountOk()
    {
        //验证token
        $this->checkAuth();
        include template('teamManage/openComplete');
    }

    /**
     * 会员报表
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function myMemberWeb()
    {
        //验证token
        $this->checkAuth();
        $token = D('token')->getTokenByUserid($this->userId);
        $user_value = trim($_REQUEST['user_value']);
        
        if (is_numeric($_REQUEST['type']) && $_REQUEST['type'] < 3 && $_REQUEST['type'] > 0) {
            $type = $_REQUEST['type'];
        } else {
            $type = 1;
        }

        $start_time = getParame('start_time', 0, date('Y-m-d'));
        $end_time = getParame('end_time', 0, date('Y-m-d'));

        $backUrl = url('','user','workTeam');
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'myMember'));
        include template('teamManage/vipReport');
    }

    /**
     * 会员报表
     * @method POST /index.php?m = api&c = user&a = myMember&token = 45e3845fe6fc08283c1d7ef300dfc6ff
     * @param token string
     * @return  html
     */
    public function myMemberBack()
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
        include template('teamManage/vipReportContent');
    }
    //优化后
    public function myMember()
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
            $where = " AND regtime BETWEEN {$start_time} and {$end_time}";
        } elseif (!empty($start_date)) {
            $start_time = strtotime($start_date);
            $where = " AND regtime >= {$start_time}";
        } else {
            $where = "";
        }

        //会员数据
        $myMemberList = array();

        $id = $this->userId;
        //查询user表下级记录
        $field = "id, id AS uid, username, nickname, parent_id, regtime";
        $c_user = $this->recursive_query($id,$field,$where);
        if(empty($c_user)){
            include template('teamManage/vipReportContent');
            return;
        }

        //1会员账号, 2会员昵称
        $user_type = trim($_REQUEST['user_type']);
        $user_value = trim($_REQUEST['user_value']);
        if($user_type == 1 ){
            $field = "username";
        }else{
            $field = "nickname";
        }

        //查询account
        $uid = array();//查询条件
        $pids = array();//直属会员id
        foreach ($c_user as $k=>$v){
            if(!empty($user_value)){
                if($v[$field] != $user_value){
                    unset($c_user[$k]);
                    continue;
                }
            }
            $uid[] = $v['uid'];
            if($v['parent_id'] == $id){
                $pids[] = $v['id'];
            }
        }
        if(empty($c_user)){
            include template('teamManage/vipReportContent');
            return;
        }
        $suid = implode($uid,',');
        $sql = "SELECT user_id, money FROM un_account WHERE user_id IN({$suid})";
        $res = O('model')->db->getAll($sql);

        $account = array();
        foreach ($res as $v){
            $account[$v['user_id']] = $v['money'];
        }
        //查询orders
        $today = strtotime(date('Y-m-d 00:00:00', SYS_TIME));
        $sql = "SELECT user_id, SUM(money) AS inputs_money FROM un_orders WHERE user_id IN({$suid}) AND state = 0 AND addtime >= {$today}  GROUP BY user_id";

        $orders = O('model')->db->getAll($sql);
        if (!empty($orders)) {
            $inputsMoney = array();
            foreach ($orders as $v) {
                $inputsMoney[$v['user_id']] = $v['inputs_money'];
            }
        }

        //初始化redis
        $redis = initCacheRedis();
        $stage = $redis->hMGet("Config:stage", array('value'));
        //关闭redis链接
        deinitCacheRedis($redis);

        foreach ($c_user as $v) {
            //$v['nickname'] = empty($v['nickname']) ? $v['username'] : $v['nickname'];
            $v['nickname'] = ($stage['value'] == 1 && !in_array($v['uid'],$pids)) ? subtext($v['username'],1,0)."****".subtext($v['username'],1,-1) : $v['username'];
            $v['money'] = empty($account[$v['uid']]) ? '0.00' : $this->convert($account[$v['uid']]);
            $v['inputsMoney'] = isset($inputsMoney[$v['uid']]) ? $this->convert($inputsMoney[$v['uid']]) : '0.00';
            $myMemberList[] = $v;
        }
        include template('teamManage/vipReportContent');
    }

    /**
     * 会员报表详情
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function memberDetailWeb()
    {
        //验证token
        $this->checkAuth();
        
        $user_value = trim($_REQUEST['user_value']);
        
        if (is_numeric($_REQUEST['type']) && $_REQUEST['type'] < 3 && $_REQUEST['type'] > 0) {
            $type = $_REQUEST['type'];
        } else {
            $type = 1;
        }
        
        if (trim($_REQUEST['start_time'])) {
            $start_time = trim($_REQUEST['start_time']);
        } else {
            $start_time = '';
        }
        
        if (trim($_REQUEST['end_time'])) {
            $end_time = trim($_REQUEST['end_time']);
        } else {
            $end_time = '';
        }
        
        /*$id = trim($_REQUEST['id']);
        $ValidUrl = $this->URL(array('c'=>'user', 'a'=>'memberDetail','param'=>'&id='.$id));*/
        $backUrl = url('','user','myMemberWeb') . "&start_time=" . $start_time . "&end_time=" . $end_time . "&type=" . $type . "&user_value=" . $user_value;
        $data = $this->memberDetail();
        include template('teamManage/details');
    }

    /**
     * 会员报表详情
     * @method POST
     * @param token string
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function memberDetail()
    {
        //验证token
        $this->checkAuth();
        $id = trim($_REQUEST['id']);
        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);

        $start_time = getParame('start_time', 0, date('Y-m-d'));
        $end_time = getParame('end_time', 0, date('Y-m-d'));
        $start_time_int = strtotime($start_time);
        $end_time_int = strtotime($end_time . ' 23:59:59');
        $where = " AND addtime BETWEEN {$start_time_int} and {$end_time_int}";

        $sql = "SELECT distinct uu.id,uu.id as uid,uu.parent_id FROM un_user_tree uut LEFT JOIN un_user uu ON uut.user_id = uu.id WHERE uut.pids LIKE '%,".$id.",%' AND uu.id > 0";
        $res = O('model')->db->getAll($sql);

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

        if(!empty($teamIds)) {
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
        }else {
            foreach ($trade['tranTypeIds'] as $v) {
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
        //$data['nickname'] = empty($self['nickname']) ? $self['username'] : $self['nickname'];
        $data['username'] = $self['username'];
        if($self['parent_id'] == $this->userId && !$direct['value']) {
            $data['username'] = interceptChinese($self['username']);
        }
        if($self['parent_id'] != $this->userId && !$stage['value']) {
            $data['username'] = interceptChinese($self['username']);
        }

        $data['recharge'] = $this->convert($tradeType['10']); //充值
        $data['cash'] = $this->convert($tradeType['11']); //提现
        $data['award'] = $this->convert($tradeType['12'] - $tradeType['120']); //中奖-回滚
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
        return $data;
        //ErrorCode::successResponse($data);
    }

    /**
     * 团队报表
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function myGroupWeb()
    {
        //验证token
        $this->checkAuth();
        $token = D('token')->getTokenByUserid($this->userId);
        $user_value = trim($_REQUEST['user_value']);
        
        if (is_numeric($_REQUEST['type']) && $_REQUEST['type'] < 3 && $_REQUEST['type'] > 0) {
            $type = $_REQUEST['type'];
        } else {
            $type = 1;
        }
        
        if (is_numeric($_REQUEST['online']) && $_REQUEST['online'] < 3 && $_REQUEST['online'] >= 0) {
            $online = $_REQUEST['online'];
        } else {
            $online = 0;
        }
        
        if (trim($_REQUEST['start_time'])) {
            $start_time = trim($_REQUEST['start_time']);
        } else {
            $start_time = '';
        }
        
        if (trim($_REQUEST['end_time'])) {
            $end_time = trim($_REQUEST['end_time']);
        } else {
            $end_time = '';
        }
        
        $backUrl = url('','user','workTeam');
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'myGroup'));//优化后的方法
        include template('teamManage/teamReport');
    }

    /**
     * 团队报表
     * @method POST
     * @param token string
     * @param online mixed 在线状态
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function myGroup()
    {
        //验证token
        $this->checkAuth();
        $id = $this->userId;

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
            $nowTime = SYS_TIME;
            $start_date = date('Y-m-d',$nowTime);
            $start_time = strtotime($start_date);
            $where = " AND addtime >= {$start_time}";
        }

        //会员数据
        $myMemberList = array();

        //交易类型
        /*$trade = $this->getTrade();*/
        $trade['tranTypeIds'] = array(12, 13, 14, 18, 19, 20, 21, 32, 66,120);
        $ids = implode($trade['tranTypeIds'], ',');

        //查询自身记录
        #$sql = "SELECT id, username, nickname, parent_id FROM un_user WHERE id={$id}";
        #$res = O('model')->db->getOne($sql);

        //查询user表下级记录
        $field = "id, username, nickname, parent_id";
        $c_user = $this->recursive_query($id,$field);
        #array_unshift($c_user,$res);
        if(empty($c_user)){
            include template('teamManage/teamReportContent');
            return;
        }
        //1会员账号, 2会员昵称
        $user_type = trim($_REQUEST['user_type']);
        $user_value = trim($_REQUEST['user_value']);
        if($user_type == 1 ){
            $field = "username";
        }else{
            $field = "nickname";
        }

        //查询在线人数
        $uid = array();//查询条件
        foreach ($c_user as $k=>$v){
            if(!empty($user_value)){
                if($v[$field] != $user_value){
                    unset($c_user[$k]);
                    continue;
                }
            }
            $uid[] = $v['id'];
        }
        if(empty($c_user)){
            include template('teamManage/teamReportContent');
            return;
        }
        $suid = implode($uid,',');
        $sql = "SELECT user_id FROM un_session WHERE user_id IN({$suid})";
        $res = O('model')->db->getAll($sql);

        $onlineUid = array();//当前在线
        foreach ($res as $v){
            $onlineUid[] = $v['user_id'];
        }

        $offlineUid = array();//当前离线
        $user = array();//用户信息
        $pids = array();//直属会员id
        foreach ($c_user as $v){
            if(in_array($v['id'],$onlineUid)){
                $v['online'] = 1;
            }else{
                $v['online'] = 0;
                $offlineUid[] = $v['id'];
            }
            if($v['parent_id'] == $id){
                $pids[] = $v['id'];
            }
            $user[] = $v;
        }

        //查询相关流水记录
        $online = trim($_REQUEST['online']);
        if ($online == 1) {//在线
            $suid = implode($onlineUid,',');
        } elseif ($online == 2) {//离线
            $suid = implode($offlineUid,',');
        }
        if(empty($suid)){
            include template('teamManage/teamReportContent');
            return;
        }
        $sql = "SELECT user_id, type, SUM(money) AS total_money FROM un_account_log WHERE user_id IN($suid) AND type IN({$ids})" . $where . " GROUP BY user_id, type";
        $res = O('model')->db->getAll($sql);
        $TradeLog = array();//流水记录
        $type = array();//记录类型
        foreach ($res as $v) {
            if ($v['type']) {
                $TradeLog[$v['user_id']][$v['type']] = $v['total_money'];
            }
            $type[$v['user_id']][] = $v['type'];
        }

        //无记录的返回默认值
        foreach ($type as $k => $v) {
            $diff = array_diff($trade['tranTypeIds'], $v);
            if (!empty($diff)) {
                foreach ($diff as $v) {
                    $TradeLog[$k][$v] = '0.00';
                }
            }
        }

        //初始化redis
        $redis = initCacheRedis();
        //用户名展示或隐藏开关
        $stage = $redis->hMGet("Config:stage", array('value'));
        //关闭redis链接
        deinitCacheRedis($redis);

        foreach ($user as $v) {
            if(!in_array($v['id'],explode(',',$suid))){
                continue;
            }
            $m['uid'] = $v['id'];
            //$m['nickname'] = empty($v['nickname']) ? $v['username'] : $v['nickname'];
            $m['nickname'] = ($stage['value'] == 1 && !in_array($v['id'],$pids)) ? subtext($v['username'],1,0)."****".subtext($v['username'],1,-1) : $v['username'];
            $m['online'] = $v['online'];
            $m['inputsMoney'] = $this->convert($TradeLog[$v['id']]['13'] - $TradeLog[$v['id']]['14']);
            $m['profit'] = $this->convert(($TradeLog[$v['id']]['12'] + $TradeLog[$v['id']]['14'] + $TradeLog[$v['id']]['19'] + $TradeLog[$v['id']]['20'] + $TradeLog[$v['id']]['21'] + $TradeLog[$v['id']]['18'] + $TradeLog[$v['id']]['32'] + $TradeLog[$v['id']]['66']) - $TradeLog[$v['id']]['13'] - $TradeLog[$v['id']]['120']); //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利)-投注-回滚
            $myMemberList[] = $m;
        }

        include template('teamManage/teamReportContent');
    }

    /**
     * 团队报表详情
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function myGroupDetailWeb()
    {
        //验证token
        $this->checkAuth();
        $id = trim($_REQUEST['id']);
        $user_value = trim($_REQUEST['user_value']);
        
        if (is_numeric($_REQUEST['type']) && $_REQUEST['type'] < 3 && $_REQUEST['type'] > 0) {
            $type = $_REQUEST['type'];
        } else {
            $type = 1;
        }
        if (is_numeric($_REQUEST['online']) && $_REQUEST['online'] < 3 && $_REQUEST['online'] >= 0) {
            $online = $_REQUEST['online'];
        } else {
            $online = 0;
        }
        
        if (trim($_REQUEST['start_time'])) {
            $start_time = trim($_REQUEST['start_time']);
        } else {
            $start_time = '';
        }
        
        if (trim($_REQUEST['end_time'])) {
            $end_time = trim($_REQUEST['end_time']);
        } else {
            $end_time = '';
        }
        $backUrl = url('','user','myGroupWeb') . "&start_time=" . $start_time . "&end_time=" . $end_time . "&type=" . $type . "&user_value=" . $user_value . '&online=' . $online;
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'myGroupDetail', 'param' => '&id=' . $id));
        include template('teamManage/teamReportDetails');
    }


    public function myGroupDetail() {
        //验证token
        $this->checkAuth();
        $id = getParame('id', 1, '', 'int');
        $start_time = getParame('start_time', 0, date('Y-m-d'));
        $end_time = getParame('end_time', 0, date('Y-m-d'));
        $sort_type = getParame('sort_type', 0, 1, 'int');       //排序1离线时间 2注册时间 3会员盈亏
        $start_time_int = strtotime($start_time);
        $end_time_int = strtotime($end_time . ' 23:59:59');
        if((($end_time_int - $start_time_int)/84600) > 32) ErrorCode::errorResponse(400, 'The selected date cannot exceed 31 days');
		$timewhere = " AND addtime BETWEEN {$start_time_int} and {$end_time_int}";
		$backwhere = " AND ubl.addtime BETWEEN {$start_time_int} and {$end_time_int}";

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
		
        $redis = initCacheRedis();
        $stage = $redis->hMGet("Config:stage", array('value'));
        $direct = $redis->hMGet("Config:direct", array('value'));
        deinitCacheRedis($redis);
        //自身信息
        $sql3 = "SELECT u.id AS uid, u.username, u.nickname, u.parent_id, u.user_type, a.backwater FROM un_user AS u LEFT JOIN un_agent_group AS a ON u.user_type = a.id WHERE u.id = {$id}";
        $self = O('model')->db->getOne($sql3);

        $data['nickname'] = $self['username'];
        if($self['parent_id'] == $this->userId && !$direct['value']) {
            $data['nickname'] = interceptChinese($self['username']);
        }
        if($self['parent_id'] != $this->userId && !$stage['value']) {
            $data['nickname'] = interceptChinese($self['username']);
        }
        $data['backwater'] = empty($self['backwater']) ? 0 : $self['backwater']; //返点比例

        $backfileds = "SUM(ubl.selfBack) as selfBack,SUM(IF(ubl.user_id = $id,ubl.sonBack,0)) as sonBack";
        $backSql = "SELECT $backfileds FROM `un_user_tree` uut LEFT JOIN un_back_log ubl ON uut.user_id = ubl.user_id WHERE pids LIKE '%,".$id.",%' OR uut.user_id = $id $backwhere";
        $res = $this->db->getone($backSql);
        $data['back'] = $this->convert($res['selfBack'] + $res['sonBack']);


        ErrorCode::successResponse($data);

        //TODO:团队总反水   团队用户个人反水+当前用户直属会员反水    un_back_log  selfBack    sonBack
    }

    /**
     * 团队报表详情
     * @method POST
     * @param token string
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function myGroupDetailBak()
    {


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
        //$sql = "SELECT u.id AS uid, u.parent_id FROM un_user AS u WHERE FIND_IN_SET(u.id, getChildLst({$id}))";
        //$res = O('model')->db->getall($sql);
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
                $tradeType[$v] = '0';
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
        $data['team_award'] = $this->convert($teamTradeType['12']) - $this->convert($teamTradeType['120']); //团队会员中奖 - 回滚
        $data['profit'] = $this->convert(($tradeType['12'] + $tradeType['14'] + $tradeType['19'] + $tradeType['20'] + $tradeType['21'] + $tradeType['18'] + $tradeType['32'] + $tradeType['66']) - $tradeType['13'] - $tradeType['120']); //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利)-投注-回滚
        $data['backwater'] = empty($self['backwater']) ? 0 : $self['backwater']; //返点比例

        ErrorCode::successResponse($data);
    }

    /**
     * 自身统计
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function myOneselfWeb()
    {
        //验证token
        $this->checkAuth();
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'myOneself'));
        include template('teamManage/statisticsDetails');
    }

    /**
     * 自身统计
     * @method POST
     * @param token string
     * @param online mixed 在线状态
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
        $data['award'] = $this->convert($tradeType['12']) - $this->convert($tradeType['120']); //中奖-回滚
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


    /*
     * 代理报表
     * */
    public function agentReportForms() {
        //验证token
        $this->checkAuth();
        $token = D('token')->getTokenByUserid($this->userId);
        $backUrl = url('','user','workTeam');
        $ValidUrl = $this->URL(array('c' => 'user', 'a' => 'agentReportForms'));
        include template('teamManage/agentReportForms');
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
    protected function getSysMessage()
    {
        $reids = initCacheRedis();
        $LSM = $reids->lRange('AgentSystemIds', 0, -1);
        $SMessage = array();
        foreach ($LSM as $v) {
            $list = $reids->hGetAll("AgentSystem:" . $v);
            $SMessage[] = $list;

        }
        return $SMessage;
    }


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
     * 设置支付密码web
     * @return web
     */
    public function setPayWeb()
    {
        //验证token
        $this->checkAuth();

        //核对是否设置资金密码
        $where = array(
            'id' => $this->userId
        );
        $field = 'paypassword';
        $userInfo = $this->model->getOneCoupon($field, $where);
        if ($userInfo['paypassword'] != '') {//设置过，则加载支付安全界面
            include template('wallet/paymentSecurity');
            exit;
        }

        include template('wallet/firstSetPas');
    }

    /**
     * 修改支付密码web
     */
    public function uPsdWeb()
    {
        //验证token
        $this->checkAuth();

        include template('wallet/modifyPas');
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
     * 设置web
     */
    public function setup()
    {
        //验证token
        $this->checkAuth();
        $rtArr = $this->model->getOneCoupon('reg_type', array('id' => $this->userId));

        $version = C("version");

        include template('my/setUp');
    }

    /**
     * 关于我们
     */
    public function aboutUs()
    {
        //验证token
        $this->checkAuth();

        $version = C("version");
        $name= C("app_webname");

        include template('my/aboutUs');
    }

    /**
     * 修改登陆密码web
     */
    public function uLoginPsdWeb()
    {
        //验证token
        $this->checkAuth();

        include template('my/modifyPas');
    }

    /**
     * 登录日志
     * @return json
     */
    protected function loginLog($uid, $flag, $code)
    {
        
        
        $ip = ip();
        $ip_attribution = '';
        //$urlIp = 'http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=' . $ip;
        /*
        $urlIp = 'http://ip.taobao.com/service/getIpInfo.php?ip=' . $ip;
        if (!empty($ip)) {
            $curlData = curl_get_content($urlIp);
            $data = json_decode($curlData, true);
            if ($data['code'] == 0) {
                $ip_attribution = $data['data']['country'] . $data['data']['region'] . $data['data']['city'] . '|' . $data['data']['isp'];
            }
        }
        */
        
        $data = array(
            'user_id' => $uid,
            'flag' => $flag,
            'code' => $code,
            'ip' => ip(),
            'ip_attribution' => $ip_attribution,
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
    
    function http_get_data($data,$gzip=false)
    {
        $url = 'http://www.cip.cc/'.$data;
        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,  CURLOPT_TIMEOUT, 1);//连接超时间

        $response = curl_exec($ch);
        $country =  explode('URL',explode('数据二',$response)[1])[0];
        $country = str_replace(':','',$country);
        $country = str_replace(' ','',$country);
        $country = str_replace('市','',$country);
        $country = str_replace('中国','',$country);
        $country = str_replace('省','',$country);
        return $country;
    }

    public function getLetter($num){
        $codes = "abcdefghijkmnpqrstuvwxy";
        $code = "";
        for($i=0; $i < $num; $i++) {
            $code .=$codes{rand(0, strlen($codes)-1)};
        }
        return $code;
    }
    
    //获取荣誉等级信息
    public function getHonor()
    {
        //验证token
        $this->checkAuth();
    
        $data = $this->model->getHonor($this->userId);
    
        echo json_encode($data);
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
            4 => '修改' //赠送：人工后台修改
        );
        
        $data['user_id'] = $this->userId;

        $count = $this->model->getHonorCount($data);
        $data['pagestart'] = 1;
        $data['pagesize'] = $pagesize;

        $honorScoreList = $this->model->getHonorRecordList($data);
        
        var_dump($honorScoreList);
    }


    //记录操作日志
    public  function opLog($type){
        $ip = ip();
        $sql  = "INSERT INTO `un_user_change_log` (`user_id`, `record_id`, `type`, `tab`, `change_name`, `ip`, `addtime`) VALUES ('{$this->userId}', '{$this->userId}', $type,'un_user', '本人', '{$ip}', '".time()."')";
        $this->db->query($sql);
    }

    //用户活动中心
    public function activityCenter()
    {
        //验证token
        $this->checkAuth();
        $uid = $this->userId;
        $token = session_id();        
        include template('my/activityCenter');
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
        $strId = '';
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'user_id'), all);
        //验证token
        $this->checkAuth();
        $user_id = trim($_REQUEST['user_id']);
    
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
        $this->checkInput($_REQUEST, array('token', 'user_id'), all);
        //验证token
        $this->checkAuth();
        $user_id = trim($_REQUEST['user_id']);
    
        $ret = $this->model->cancelFollowUser($user_id, $this->userId);
    
        if ($ret['code'] == 0) {
            ErrorCode::successResponse(['ret_msg' => 'Unfollow successfully']);
        }else {
            ErrorCode::errorResponse(210300, $ret['msg']);
        }
    }
    
    //房间内获取关注者最近本房间投注没人5条记录
    public function getRoomFollowUser()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'room_id'), all);
        //验证token
        $this->checkAuth();
        $room_id = trim($_REQUEST['room_id']);
    
        $ret = $this->model->getRoomFollowUser($room_id, $this->userId);
    
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
    
}
