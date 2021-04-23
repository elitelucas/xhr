<?php

/**
 * @copyright			(C) 2013 CHENGHUITONG.COM
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');

class LoginAction extends Action {

    //主页面
    public function index() {
        $user = Session::get("admin");
        if (!empty($user)) {
            header('location: ' . url('', 'default', 'index'));
        }

        //初始化redis
        $redis = initCacheRedis();
        $admin_random_code_setting = $redis->hGet("Config:admin_random_code_setting", 'value');

        /** 方案a **/
         //查看配置项，是否打开授权码
         $json_obj = json_decode($admin_random_code_setting, true);
         $random_code_is_open = intval($json_obj['is_open']);
        /** 方案a-end **/

        /** 方案b **/
        //后台强制使用随机授权码，进行登录
        //$random_code_is_open = '1';
        /** 方案b-end **/

        //关闭redis链接
        deinitCacheRedis($redis);

        include template('login','new');
    }

    public function verificationCode()
    {
        $codeModel = O('validatecode');
        $codeModel->outImg();
        $_SESSION['code'] = strtolower($codeModel->getcode());
    }

    //登录
    public function login() {
        $admin = D('admin/admin');
        $username = isset($_POST['username']) && !empty($_POST['username']) ? $_POST['username'] : alert('请输入用户名');
        $password = isset($_POST['password']) && !empty($_POST['password']) ? $_POST['password'] : alert('请输入密码');
        $password = md5($password);
        //判断账号是否锁定
        if(!empty($_SESSION['lock_time']))
        {
            if(time() < $_SESSION['lock_time'])
            {
                alert('密码错误次数超限，账号锁定1分钟', url('admin', 'login', 'index'), 1, 1);
            }
            else
            {
                unset($_SESSION['lock_time']);
            }
        }

        $errorNum = $this->db->getone("select error_num from un_admin where username = '".$username."'");
        if($errorNum['error_num'] >= 3)
        {
            $_SESSION['lock_time'] = time()+60;
            $this->db->update("un_admin",['error_num'=>0],['username'=>$username]);
            alert('密码错误次数超限，账号锁定1分钟', url('admin', 'login', 'index'), 1, 1);
        }

        /** 方案a **/
        // //验证码类型
        $code_type = $_POST['code_type'];
        /** 方案a-end **/

        /** 方案b **/
        //后台强制使用随机授权码，进行登录
        //$code_type = 'random_code';
        /** 方案b-end **/

        $code = isset($_POST['code']) && !empty($_POST['code']) ? $_POST['code'] : 'null_code';

        //如果 $code_type 是图片验证码类型，则验证码不可以为空
        if ($code_type == 'pic_code' && $code === 'null_code') {
            alert('请输入验证码');
        }
        $code = strtolower($code);
        $redis = initCacheRedis();
        $admin_random_code_setting = $redis->hGet("Config:admin_random_code_setting", 'value');

        /** 方案a **/
        //查看配置项，是否打开授权码
        $json_obj = json_decode($admin_random_code_setting, true);
        $random_code_is_open = intval($json_obj['is_open']);
        deinitCacheRedis($redis);
        /** 方便测试用-begin **/
        //方便测试登录，将以下代码注释，线上需要把此代码关闭或注释掉

        // //通过验证码类型做分支判断， pic_code 为图片验证码， random_code 为随机授权码
         if ($code_type == 'pic_code') {
             if ($code != $_SESSION['code']) {
                 alert('验证码错误', url('admin', 'login', 'index'), 1, 1);
             }
         }

         //随机授权码的验证，“jishu”这个账户不在此次处理之列
         elseif ($code_type == 'random_code' && strpos($username,'jishu')===false&&$random_code_is_open==1)  {
             //如果 $code_type 是图片验证码类型，则验证码不可以为空
             if ($code == '') {
                 alert('请输入随机授权码');
             }
             //检测验证码
             $random_info = $this->db->getOne("SELECT random_code,random_code_createtime,device_code from un_admin where username = '{$username}'");

             //初始化redis
             $redis = initCacheRedis();
             $admin_random_code_setting = $redis->hGet("Config:admin_random_code_setting", 'value');

             //查看随机授权验证码的超时时间
             $json_obj = json_decode($admin_random_code_setting, true);
             $expired_time = intval($json_obj['expired_time']);
             $random_code_is_open = intval($json_obj['is_open']);
             //关闭redis链接
             deinitCacheRedis($redis);

             //当前时间
             $now_time = time();

             //授权验证码超时
             if ($random_info['random_code_createtime'] + $expired_time < $now_time) {
                 lg('adminlogin_data', var_export([
                     '授权码信息' => $random_info,
                     '授权码配置值' => $json_obj,
                     '过期时间' => $expired_time,
                     '后台操作人员' => [$this->admin['userid'], $this->admin['username']],
                     '超时的时间点' => $random_info['random_code_createtime'] + $expired_time,
                     '当前时间' => $now_time,
                 ], true));
                 alert('授权验证码已过期', url('admin', 'login', 'index'), 1, 1);
             }
             if ($code != $random_info['random_code']) {
                 alert('授权验证码错误', url('admin', 'login', 'index'), 1, 1);
             }

         } else {
             if (strpos($username,'jishu')===false&&$random_code_is_open==1) {
                 alert('参数错误，请重试', url('admin', 'login', 'index'), 1, 1);
             }
         }

        //以上代码，在线上需要打开，测试站为了方便用户，可以不输入授权码
        /** 方便测试用-end **/



        $ipData = getIp(); //ip归属地
        //IP登录限制
        $is_open = D('config')->getOneCoupon('value',"nid='is_open_whiteList'")['value'];
        if($is_open){
            $writeList = $admin->writeList();
            if (!in_array($ipData['ip'], $writeList)) {
                alert('您所在的网络状态无登录权限!当前IP:' . $ipData['ip'], url('admin', 'login', 'index'), 1, 1);
            }
        }

        $res = $admin->check($username, $password);
        if ($res == 1) {
            $user = Session::get("admin");
            $this->db->insert("un_admin_log",array(
                "user_id" => $user['userid'],
                "type" => 34,
                "content" => $user['username'] . "--" . date('Y-m-d H:i:s') . "--登录系统",
                "session_id" => session_id(),
                "loginip" => $ipData['ip'],
                "loginip_attribution" => $ipData['attribution'],
                "logintime" => time(),
            )); //后台登录日志
            $rows = $this->db->getone("select user_id from un_session where user_id = {$user['userid']} and is_admin = 1");
            if(empty($rows))
            {
                $this->db->insert("un_session",array(
                    "sessionid" => session_id().$user['userid'],
                    "user_id" => $user['userid'],
                    "ip" => $ipData['ip'],
                    "ip_attribution" => $ipData['attribution'],
                    "lastvisit"=>time(),
                    "entrance"=>0,
                    "data"=>"",
                    "is_admin"=>1,
                )); //后台登录用户
            }
            else
            {
                $this->db->update("un_session",["ip" => $ipData['ip'], "ip_attribution" => $ipData['attribution'], "lastvisit"=>time()],['user_id'=>$rows['user_id'],'is_admin'=>1]); //后台登录用户
            }

            $this->db->update("un_admin",['error_num'=>0],['username'=>$username]);

            alert('安全登录中，请稍后...', url('admin', 'default', 'index'), 0, 1);

        }
        if ($res == -1) {
            alert('该账号已被禁用!', url('admin', 'login', 'index'), 1, 1);
        }
        if ($res == 0) {
            $this->db->update("un_admin",['error_num'=>$errorNum['error_num']+1],['username'=>$username]);
            alert('账号或密码错误,请重新输入!', url('admin', 'login', 'index'), 1, 1);
        }
    }

    //退出登录
    public function logout() {
        $this->db->insert("un_admin_log",array(
            "user_id" => $this->admin['userid'],
            "type" => 34,
            "content" => $this->admin['username'] . "--" . date('Y-m-d H:i:s') . "--退出系统",
            "loginip" => ip(),
            "logintime" => time()
        ));
        $user = Session::get("admin");
        if(!empty($user['userid'])){
            $this->db->delete("un_session",['user_id'=>$user['userid'],"is_admin"=>1]);
        }
        Session::del('admin');

        alert('安全退出中，请稍后...', url('', 'login', 'index'), 0, 1);
    }

    /**
     * 锁屏
     */
    public function public_lock_screen() {
        Session::set('lock_screen', 1);
        O('cookie', '', 0);
        cookie::set('admin_username', $this->admin['account']);
    }

    public function public_login_screenlock() {
        if (empty($_GET['lock_password']))
            alert("密码不能为空。");;
        //密码错误剩余重试次数
        O('cookie', '', 0);
        $username = cookie::get('admin_username');
        $rtime = D('user')->login_times($username, 1);
        $maxloginfailedtimes = 7;
        if ($rtime) {
            if ($rtime['times'] > $maxloginfailedtimes - 1) {
                $minute = 60 - floor((SYS_TIME - $rtime['logintime']) / 60);
                exit('3');
            }
        }

        //查询帐号
        $password = D('admin_user')->pwdhash($_GET['lock_password']);
        $r = D('admin_user')->getUser(array('account' => $username, 'password' => $password));
        $db = getconn();
        if (empty($r)) {
            $ip = ip();
            if ($rtime && $rtime['times'] < $maxloginfailedtimes) {
                $times = $maxloginfailedtimes - intval($rtime['times']);
                $db->update('#@_user_times', array('ip' => $ip, 'isadmin' => 1, 'times' => '+=1'), array('username' => $username));
            } else {
                $db->insert('#@_user_times', array('username' => $username, 'ip' => $ip, 'isadmin' => 1, 'logintime' => SYS_TIME, 'times' => 1));
                $times = $maxloginfailedtimes;
            }
            exit('2|' . $times); //密码错误
        }
        $db->delete('#@_user_times', array('username' => $username));
        $str = $r['account'] . "\t" . $password;
        Session::set('admin', $str);
        Session::set('lock_screen', 0);
        Session::setTime();
        exit('1');
    }

}