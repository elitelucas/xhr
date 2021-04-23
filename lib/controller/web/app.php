<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 13:34
 * desc: 天天反利 玩法介绍
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class AppAction extends Action{
    /**
     * 数据表
     */
//    private $model;

    public function __construct(){
        parent::__construct();
//        $this->model = D('');
    }

    /**
     * 天天返利
     * @method get /index.php?m=api&c=app&a=rebate&token=b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return mixed
     */
    public function rebate(){
        //验证token
        $this->checkAuth();
        //初始化redis
        $redis = initCacheRedis();

        //获取配置参数
        $configJson = $redis -> HMGet("Config:activity",array('value'));
        $infoRt = json_decode($configJson['value'], true);
        $infoArr = array();
        foreach ($infoRt as  $value) {
            if($value['status'] == 1){
                $infoArr[] = $value;
                break;
            }
        }
        //关闭redis链接
        deinitCacheRedis($redis);

        include template('rebate');
    }

    /**
     * 玩法介绍
     * @method get /index.php?m=api&c=app&a=gameList&token=b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return mixed
     */
    public function gameList(){
        //验证token
        $this->checkAuth();
        //初始化redis
        $redis = initCacheRedis();
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        $gameInfo = array();
        foreach ($LotteryTypeIds as $v){
            $res = $redis->hGetAll("LotteryType:".$v);
            $gameInfo[$res['id']] = $res;
        }
        ksort($gameInfo);
        //关闭redis链接
        deinitCacheRedis($redis);
        include template('wfjs');
    }

    /**
     * 玩法介绍
     * @method get /index.php?m=api&c=app&a=gameplay&token=b5062b58d2433d1983a5cea888597eb6&lottery_type=1
     * @param token string
     * @param lottery_type int
     * @return mixed
     */
    public function gameplay()
    {
        //验证参数
        $lottery_type = trim($_REQUEST['lottery_type']);
        
        //验证token
        //$this->checkAuth();
        switch($lottery_type){
            case 1:
                $jpg_name = 'way28.png';
                // include template('way28');
                break;
            case 2:
                $jpg_name = 'way-pk10.jpg';
                // include template('way-pk10');
                break;
            case 3:
                $jpg_name = 'way-Canada28.png';
                // include template('way-Canada28');
                break;
            case 4:
                $jpg_name = 'way-xyft.jpg';
                // include template('way-xyft');
                break;
            case 5:
                $jpg_name = 'way-cqssc.jpg';
                break;
            case 6:
                $jpg_name = 'way-sfc.jpg';
                break;
            case 7:
                $jpg_name = 'way-xglhc.jpg';
                break;
            case 8:
                $jpg_name = 'way-jslhc.jpg';
                break;
            case 9:
                $jpg_name = 'way-jssc.jpg';
                break;
            case 10:
                $jpg_name = 'way-nn.jpg';
                break;
            case 11:
                $jpg_name = 'way-ffc.jpg';
                break;
        }
        include template('way-otherAll');
    }

    /**
     * 支付安全修改成功提示界面
     * @return web
     */
    public function tsSuccess() {
        //验证token
        $this->checkAuth();

        $title = trim($_REQUEST['title']);
        $msg = trim($_REQUEST['msg']);

        include template('wallet/pasSuccess');
    }


    /**
     * 在线客服
     */
    public function customService() {

        //获取用户昵称
        $nicknameArr = D('user')->getUserInfo('username,nickname', array('id' => $this->userId), 1);

        if ($_REQUEST['type'] != 1) {
            //验证token
            $this->checkAuth();
        } else {
            $nicknameArr['nickname'] = '您';
        }

        $userInfo = array(
            'userid' => $this->userId,
            'nickname' => $nicknameArr['nickname'] == '' ? session::get('nickname') : $nicknameArr['nickname'],
            'head_url' => empty(session::get('avatar')) ? '/up_files/room/avatar.png' : session::get('avatar'),
        );
        $token = $_SESSION['SN_']['token'];
        $userInfo = json_encode($userInfo);

        $JumpUrl = $this->getUrl();
        include template('customService/index');
    }

    /**
     * web默认界面
     */
    public function index() {
        $res = $this->cp();
        if($res == 2){
            $data = array('msg'=>'System under maintenance!');
            include template('systemMaintenance');
            exit;
        }
        if((isset($_REQUEST['type']) && !empty($_REQUEST['type']))&&(isset($_REQUEST['pid']) && !empty($_REQUEST['pid']))){
            session::set('type', $_REQUEST['type']);
            session::set('pid', $_REQUEST['pid']);
        }

        $redis = initCacheRedis();
        //客服配置
        $val = $redis->hget('Config:kefu_set','value');
        $kefu = decode($val);
        deinitCacheRedis($redis);

        if('ap.'.$_SERVER['SERVER_NAME'] == $_SERVER['HTTP_HOST']){
//            $lobby = $this->URL(array('c'=>'lobby','a'=>'index'));
//            header("Location:$lobby");
            header('Location: '.C("app_home").'?m=web&c=lobby&a=index');
        }


        $sql1 = "select `addtime`,url from un_version where `type`=1 order by addtime desc ";
        $re1 = $this->db->getone($sql1);
        $sql2 = "select `addtime`,url  from un_version where `type`=2 order by addtime desc ";
        $re2 = $this->db->getone($sql2);

        include template('jinruyemian');
    }

    /**
     * 推广中心
     */
    public function tarec(){
        include template('auto/Tarec');
    }


    /**
     * 帮助中心
     */
    public function help(){
        include template('auto/Help');
    }

    /**
     * 关于我们
     */
    public function about(){
        include template('auto/About');
    }

    /**
     * app下载
     */
    public function appDown() {
        $db = getconn();
        $sql = "SELECT url FROM un_version WHERE type = 1 ORDER BY id DESC"; //ios
        $sql2 = "SELECT url FROM un_version WHERE type = 2 ORDER BY id DESC"; //Android
        $res = $db->getone($sql);
        $res2 = $db->getone($sql2);

        include template('app-down');
    }

    /**
     * app下载二位码
     * @param url string
     * @param token string
     * @return  json
     */
    public function QRcode() {
        O('phpQRcode');
        $value = $this->URL(array('c' => 'app', 'a' => 'appDown'));
        $errorCorrectionLevel = "L"; // 纠错级别：L、M、Q、H
        $matrixPointSize = "6"; // 点的大小：1到10
        Qrcode::png($value, false, $errorCorrectionLevel, $matrixPointSize);
    }

    /**
     * 重置session有效时间
     */
    public function resetTime() {
        //验证token
        $this->checkAuth();
        echo 'success';
    }
}