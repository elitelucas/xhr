<?php
/**
 *  action.php 前台控制基类
 *
 * @copyright			(C) 2013 CHENGHUITONG.COM
 * @lastmodify			2013-08-21   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');

class Action {

    /** 初始化 */
    public $_SN;    //系统配制
    public $user;    //user
    protected $Banner_Notice;
    protected $kefu;
    protected $dj = array(1627, 4645, 5458, 8323, 11776, 23689, 45122, 98291); //普通账户冻结提现
    protected $hfdj = array(1627, 4645, 5458, 6141, 8323, 13925, 23689); //汇付账户冻结提现
    protected $ffdj = array(98291); //丰付账户冻结提现

    public function __construct() {
        O('session', '', 0);
        Session::start();
        O('cookie', '', 0);

        //加载系统配制
        $this->_SN = & $GLOBALS['_SN'];
        $this->_SN['Sysconfig'] = D('Sysconfig')->Sysconfig_cache();
        if (ROUTE_C != 'login' && !cookie::is_set('login_vcodecount')) {
            cookie::set('login_vcodecount', '1');
        }
        $this->checklogin();
        $this->kefu = F('kefu');
        if (empty($this->kefu)) {
            $this->kefu = D("admin_user")->getUserlist(array('role_id' => 2, 'status' => 0), '', '', 'id');
            F('kefu', $this->kefu);
        }
        shuffle($this->kefu);//将数组随机排序
    }

    /** 检测登陆 */
    public function checklogin() {
        $userstr = dencrypt(Cookie::get('user'));
        // if (ROUTE_C == 'login' || empty($userstr)) {
        if (empty($userstr)) {
            return true;
        } else {
            list($id, $pwd, $openid) = explode("|", $userstr); //最后一个是微信id
            $user = D('User')->getUser('id,username,email,password,paypassword,realname,hfid,ffid,weinxin_id', array('id' => $id), '', 1);
             //密码不对，或者绑定的微信有变（如换个手机在别人的微信里登录，那么旧手机登录的账号应该自动退出）
            if ($user['password'] != $pwd || (!empty($openid) && $user['weinxin_id'] != $openid)) {
                Cookie::del('user');
                Cookie::del('userid');
                return false;
            } elseif ($user) {
                $user['noreadmsg'] = D('Message')->getMessage("COUNT(*)", array('status' => 0, 'to_user' => $user['id']));
                $this->user = $user;
                return true;
            } else {
                return false;
            }
        }
    }

    /** 检测交易密码* */
    public function check_paypassword() {
        if (empty($this->user['paypassword'])) {
            $this->jump_alert("您还没有设置交易密码，请先设置", url('member', 'userinfo', 'index', array('paypass' => 1)));
        }
    }

    /**
     * 验证登录
     *
     */
    public function getlogin() {
        if (empty($this->user)) {
            $this->jump_alert("You have not logged in, please log in first", url('content', 'login', 'index'));
        }
    }

    /**
     * 验证用户认证信息
     *
     * @param unknown_type $real_status
     * @param unknown_type $email_status
     * @param unknown_type $phone_status
     * @param unknown_type $vip_status
     */
    public function checkatte($real_status = 0, $email_status = 0, $phone_status = 0, $vip_status = 0) {
        $att = D('userattestation')->attestation($this->user['id']);
        if ($real_status && $att['real_status'] != 2) {
            $this->jump_alert("您还没有实名认证，请先实名认证！", url('member', 'userinfo', 'index', array('showreal' => 1)));
        }
        if ($email_status && $att['email_status'] != 2) {
            $this->jump_alert("您还没有邮箱认证，请先认证邮箱！", url('member', 'userinfo', 'index'));
        }
        if ($phone_status && $att['phone_status'] != 2) {
            $this->jump_alert("您还没有手机认证，请先认证手机！", url('member', 'userinfo', 'index'));
        }
        if ($vip_status && ($att['vip_status'] != 2 || SYS_TIME > $att['vip_verifytime'])) {
            $this->jump_alert("您还没有申请VIP，请先申请VIP！", url('member', 'verify', 'vip'));
        }
    }

    /**
     * 获取公告
     *
     */
    public function getnotice() {
        $this->Banner_Notice = F('Banner_Notice');
        if (empty($this->Banner_Notice)) {
            $this->Banner_Notice = D('Article')->getArticlelist('A.id,A.title,A.addtime', array('A.status' => 1, 'A.cateid' => 4), '', 5);
            F('Banner_Notice', $this->Banner_Notice, 300);
        }
    }

    /**
     * 提示信息
     *
     * @param unknown_type $msg  错误信息
     * @param unknown_type $jumpUrl  跳转地址 0为返回上一页  1为刷新当前页
     * @param unknown_type $status  0为错误提示  1为正确提示  2自定义提示
     * @param unknown_type $time  跳转间隔时间  0为直接跳转
     */
    public function jump_alert($msg = 'error!', $jumpUrl = 0, $status = 0, $time = 3) {
        if (empty($jumpUrl)) { // 返回上一页
            $jumpUrl = 'javascript:history.back();';
        } elseif ($jumpUrl == 1) { // 刷新当前页
            $jumpUrl = 'javascript:location.reload();';
        }
        $js = "<script language='javascript' type='text/javascript'>window.setTimeout(function () { document.location.href='$jumpUrl';}, '" . ($time * 1000) . "');</script>";

        if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_REQUEST['inajax'])) {
            echo $msg;
        } elseif (!headers_sent()) {
            // redirect
            header('Content-Type:text/html; charset=utf-8');
            if ($time == 0) {
                header("Location:{$jumpUrl}");
            } else {
                include template('error');
            }
        } else {
            $l4 = "<meta http-equiv='refresh' content='$time'; url='$jumpUrl' />";
            if ($time != 0) {
                include template('error');
            }
        }
        exit;
    }

    /**
     * 获取个人信息
     *
     */
    public function getUserInfo() {
        $userinfo = array();
        if (empty($this->user['id'])) {
            return false;
        } else {
            /* 读取会员基本信息 */
            $userinfo['user'] = D('User')->getUserinfo($this->user['id']);
            $userinfo['tender'] = D('Account')->getAccounttotal($this->user['id']);
            $userinfo['key_str'] = "1,jpg|jpeg|gif|bmp|png,1,100,100,0,0";
            $userinfo['authkey'] = upload_key($key_str);
            /* 没有投标成功纪录不能申请VIP */
            $userinfo['tenderCount'] = D('Borrowtender')->getBorrowtendercount('U.id =' . $this->user['id']);
            $sql = "SELECT sum(money) FROM `jl_account_recharge` A inner join jl_payment_config P on A.payment_id = P.id and P.type = 1 and A.status = 1 and P.status = 0 and A.user_id =" . $this->user['id'];
            $userinfo['czmoney'] = D('Account')->getSumDate($sql);
            //头像
            if ($userinfo['user']['avatar']) {
                $userinfo['destination'] = APP_PATH . "up_files/" . $userinfo['user']['avatar'];
            } else {
                $userinfo['destination'] = APP_PATH . '/statics/red/images/account/user-header-img.jpg';
            }
            return $userinfo;
        }
    }

}
