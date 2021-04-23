<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/11
 * Time: 9:43
 * desc: 用户信息
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'app' . DS . 'action.php');

class UserAction extends Action
{
    public function __construct()
    {
        parent::__construct();

        $redis = initCacheRedis();//初始化redis
        $redis->set("mac",$_REQUEST['code']);
        $mac = $redis->get("mac");
        $resss = $this->model->isIpBlack($mac,$_REQUEST['m'],$_REQUEST['c'],$_REQUEST['a']);
        if($resss == false) {
            ErrorCode::errorResponse(ErrorCode::DEFAULT_MSG,"Sorry! You don't have enough permissions");
        }
        deinitCacheRedis($redis);//关闭redis
    }

    /**
     * 游客登录
     * @method POST  /index.php?m = api&c = user&a = registerMachine
     * @param flag string 入口标示
     * @param code string 机身码
     * @return json
     */
    public function registerMachine (){
        $this->checkInput($_REQUEST, array('flag', 'code'), 'all');
        $username = $this->getUsername(6,8);
        $flag = trim($_REQUEST['flag']);
        $code = trim($_REQUEST['code']);
        $prefix = $this->db->getone("select value from un_config where nid = 'tourist'");
        //添加用户
        
        //获取IP地址及ip归属地
        $ipData = getIp();

        //生成随机头像
        $random_avatar = D('Avatar')->fetchRandomPic();

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
        $this->checkInput($_REQUEST, array('openid','nickname','type','flag', 'code'), 'all');
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
        
        $sql = "SELECT user_id FROM `un_user_third` WHERE `openid` = '{$openid}' AND `type` = '{$type}'";
        $res = O('model')->db->getOne($sql);
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
            if (empty($userInfo)) {
                ErrorCode::errorResponse(ErrorCode::PHONE_OR_PWD_INVALID);
            }
            //更新登录信息
            $this->model->updateLoginInfo($userId);

            //去掉更新设备，这里更新的设备字段，为注册设备，最后登录设备已记录在 un_user_login_log 表
            // $this->model->save(array('entrance' => $flag), array('id' => $userId)); //更新用户设备登录类型

            //设置登录信息
            $token = $this->setToken($userId,$code);
            $this->loginLog($userId, $flag, $code);
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
        $sql = "SELECT id,nickname,avatar,reg_type FROM un_user WHERE username = '" . $username . "' AND password = '" . md5($password) . "' AND state IN(0,1)";
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
    public function reg($parentId = '')
    {
        show_log(var_export(['r'=>$_REQUEST,'g'=>$_GET,'p'=>$_POST], true));

        //var_dump($_POST);
        //var_dump(json_decode($_POST, ture));
    
        //接收参数
        $this->checkInput($_REQUEST, array('username', 'password', 'password2', 'flag', 'code', 'type'), 'all');
        $param = array_map('deal_array', $_POST);
        // var_dump($param);
        $username = trim($param['username']);
        //$username = strtolower(trim($param['username']));
        $password = trim($param['password']);
        $password2 = trim($param['password2']);
        $flag = trim($_REQUEST['flag']);
        $code = trim($_REQUEST['code']);
        $regType = trim($_REQUEST['type']);
        //        $domain = trim($_REQUEST['domain']);
    
        //验证参数
        if ($this->model->getUserInfo('username', array('username' => $username), '', '', '', true) || preg_match('/.*(script).*/', $username)) {
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

        //获取IP地址及ip归属地
        $ipData = getIp();

        //如果reg_type为10（讯彩用户），则将退出状态设置到讯彩的数据中间站服务器
        $reg_type=O('model')->db->getOne('select reg_type from un_user where id='.$this->userId)['reg_type'];
        if ($reg_type == 10) {

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
            $insert_sql = "INSERT INTO `un_xc_account_log` (`user_id`, `money`, `use_money`, `remark`, `addtime`, `addip`, `addip_attribution`) VALUES ('{$this->userId}', '{$tmp_integer_part}', '{$tmp_decimal_part}', '{$remark}', '{$sys_time}', '{$$ipData['ip']}', '{$ipData['attribution']}')";
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

}
