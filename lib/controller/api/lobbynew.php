<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017-09-27
 * Time: 10:15:21
 * desc: APP 首页接口 私密房间接口 部分数据待存入redis
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class LobbynewAction extends Action{
    /**
     * 数据表
     */
    private $model;
    private $model1;
    private $model2;
    private $model3;
    private $model4;

    public function __construct(){
        parent::__construct();
        $this->model = D('banner');
        $this->model1 = D('user');
        $this->model2 = D('room');
        $this->model3 = D('message');
        $this->model4 = D('account');
    }

    /**
     * 大厅首页接口
     * @method GET/POST  /index.php?m=api&c=lobbynew&a=index[&token='xxxx']
     * @param code string 用户手机物理地址
     * @param token string 用户token
     * @return json
     */
    public function index(){
        //域名跨域问题
        header("Access-Control-Allow-Origin: *");
        // $_REQUEST['json'] = str_replace('\\', '', $_REQUEST['json']);
        // $json_arr = json_decode($_REQUEST['json'], true);
        // $json_data = new_decrypt($json_arr['data'], C('decode_token'));
        // $new_json_data = json_decode($json_data, true);
        // $data_liu = file_get_contents('php://input');
        // lg('new_api3', var_export(['g'=>$_GET,'p'=>$_POST,'re'=>$_REQUEST,'d'=>$data_liu], 1));


        //接收前端app数据并解密
        $new_json_data = $this->handle_post();

        $_lg_ = [
            'request-j'=>$_REQUEST['json'],
            'new-j'=>$new_json_data,
        ];
        lg('index_request_data.txt', var_export($_lg_, true) . "\n\n");


        //验证token
        $resss = $this->model1->isIpBlack($new_json_data['code'],$_REQUEST['m'],$_REQUEST['c'],$_REQUEST['a']);
        if($resss == false) {
            ErrorCode::errorResponse(ErrorCode::DEFAULT_MSG,"Sorry! You don't have enough permissions");
        }
        // if(isset($_REQUEST['token']) && trim($_REQUEST['token'])){
        if(isset($new_json_data['token']) && trim($new_json_data['token'])){

            //兼容老版本代码验证token逻辑，需要重写 $_REQUEST['token'] 值
            $_REQUEST['token'] = $new_json_data['token'];

            $this->checkAuth();
            //用户消息
            $uid = $this->userId;
            // $where[] = 'touser_id = '.$uid;
            // $where[] = 'state = 0';
            $userMes = $this->getMessage($uid);//用户消息
//            $sysMes = $this->getSysMessageList($uid); //系统消息
            $sysMes = $this->getSysMessage($uid); //系统消息
            $msg_num = $userMes['num'] + $sysMes['num'];

            //账户
            $where = array(
                'user_id' => $this->userId
            );

            $res = $this->getOneAccount($where, 'money');
            if (empty($res)) {
                $res = array(
                    'money' => '0.00'
                );
            }
            $money_usable = $this->convert($res['money']);


        }else{
            //系统消息
//            $sysMes = $this->getSysMessageList(0);
            $sysMes = $this->getSysMessage(0);
            $msg_num = $sysMes['num'];
            $money_usable = '0.00';
        }

        //红包活动数据 [2018-04-03 新需求，判断当前红包活动状态，"1"为有活动正在进行，"0"为无活动正在进行]
        $is_redpacket_underway = D('Redpacket')->isRedpacketUnderway();
        $is_redpacket_underway = ($is_redpacket_underway == true) ? '1' : '0';

        //获取配置信息
        $config_nid = array(
            0 => 'Config:100001', //已为用户赚取元宝总数
            1 => 'Config:100002', //回扣返水赚钱率
            2 => 'Config:100003', //注册用户总数
        );
        $config = array();
        $redis = initCacheRedis();
        //app配置信息
        $arrConfig= $redis -> HMGet("Config:appConfig",array('nid','name','value'));
        $appConfig = json_decode($arrConfig['value'], true);
        
        foreach ($config_nid as $v){
            $GameConfig = $redis -> HMGet($v,array('nid','name','value'));
            $config[$GameConfig['nid']] = $GameConfig['value'];
        }

        //banner 轮播图
        //$banner = $this->getListBanner();
        $banner = [];
        $LBanner = $redis->lRange('BannerIds', 0, -1);
        foreach ($LBanner as $v){
            $tmp_banner = $redis->hGetAll("Banner:{$v}");
            if ($tmp_banner['banner_type'] == '1') {
                $banner[] = $tmp_banner;
            }
        }

        $newTime = time();
        foreach ($banner as $key=>$val){
            if($val['start_time'] != 0 && $val['end_time'] != 0){
                if($newTime >= $val['start_time']  && $val['end_time'] >= $newTime){
                    $banner[$key]['go_url'] = $val['replace_url'];
                    $banner[$key]['url'] = $val['replace_path'];
                } else {
                    $banner[$key]['go_url'] = $val['default_url'];
                    $banner[$key]['url'] = $val['default_path'];
                }
            } else {
                $banner[$key]['go_url'] = $val['default_url'];
                $banner[$key]['url'] = $val['default_path'];
            }
        }

        $config_with_key = [
            'user_gain' => $config['100001'],
            'make_money_rate' => $config['100002'],
            'reg_count' => $config['100003'],
        ];

        //后台配置的首页显示彩种信息
        $index_lottery_list = $redis -> HMGet('Config:index_lottery_list',['value']);

        $lottery_list = json_decode($index_lottery_list['value'], true);

        //去掉不显示的彩种，并添加六合彩“今日开奖”角标的标识字段
        $noneShowLottery = [];          //不显示的彩种
        foreach ($lottery_list as $lottery_key => &$lottery_one) {
            if ($lottery_one['is_show'] == '0') {
                $noneShowLottery[] = $lottery_one['lottery_type'];
                unset($lottery_list[$lottery_key]);
            }

            //如果有六合彩，则添加“今日开奖”标识字段
            if ($lottery_one['lottery_type'] == '7') {
                $lhc_qihao_json = $redis->lRange('QiHaoIds7', 0, 0);
                $tmp_award_date = json_decode($lhc_qihao_json[0], true);
                $is_award_today = (date('Y-m-d') == date('Y-m-d', $tmp_award_date['date'])) ? '1' : '0';
                $lottery_one['is_award_today'] = $is_award_today;

                // $lottery_one['ymd_today'] = date('Y-m-d');
                // $lottery_one['ymd_lottery_day'] = date('Y-m-d', $tmp_award_date['date']);
                // $lottery_one['lhc_qihao_json'] = $lhc_qihao_json;
                // $lottery_one['tmp_award_date'] = $tmp_award_date;
            }
            
            //增加各种期号周期数据显示
            $lottery_one['period_info'] = $this->model2->getLotteryPeriodInfo($lottery_one['lottery_type']);
        }

        //热门彩种推荐
        $recommend_list = D('Recommend')->fetchLotteryList();
        if($noneShowLottery) {
            foreach($recommend_list as $k=>$v) {
                if(in_array($v['lottery_type'], $noneShowLottery)) unset($recommend_list[$k]);
            }
            $recommend_list = array_values($recommend_list);
        }

        $tmp_sort_key = [];
        foreach ($lottery_list as $k2 => $v2) {
            $tmp_sort_key[$k2] = $v2['sort'];
        }

        //重新按sort字段升序排列
        array_multisort($tmp_sort_key, SORT_ASC, $lottery_list);

        //投注列表，只取6条数据
        $bettingList = D('lobby')->getBettingList(6);

        //查询出当前设置为已弹窗的消息id
        $popup_msg_id = D('admin/mail')->hasPopupAnnouncement();
        $popup_msg_id = intval($popup_msg_id) . '';

        $json_arr = [
            'banner' => $banner,                    //轮播图
            'app_config' => $appConfig,             //app首页配置信息
            'recommend_list' => $recommend_list,    //热门彩种推荐
            'virtual_data' => $config_with_key,     //虚拟数据
            'lottery_list' => $lottery_list,        //首页彩种
            'bettingList' => $bettingList,          //投注列表
            'msg_num' => $msg_num,                  //消息数量
            'popup_msg_id' => $popup_msg_id,        //弹窗消息id，如果没有，则传字符串'0'
            'money_usable' => $money_usable,        //余额
            'sysMessage' => $sysMes['list'],        //公告
            'is_redpacket_underway' => $is_redpacket_underway,    //红包活动数据
        ];

        $reData = [
            'data' => new_encrypt(json_encode($json_arr, JSON_UNESCAPED_UNICODE)),
            // 'data' => (json_encode($json_arr, JSON_UNESCAPED_UNICODE)),
        ];


        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($reData);
    }


    /**
     * 首页接口额外数据
     * @method GET/POST
     * @param code string 用户手机物理地址
     * @param token string 用户token
     * @return json
     */
    public function indexSimEx(){
        //域名跨域问题
        header("Access-Control-Allow-Origin: *");

        //接收前端app数据并解密
        $new_json_data = $this->handle_post();

        $_lg_ = [
            'request-j'=>$_REQUEST['json'],
            'new-j'=>$new_json_data,
        ];
        lg('index_request_data.txt', var_export($_lg_, true) . "\n\n");


        //验证token
        $resss = $this->model1->isIpBlack($new_json_data['code'],$_REQUEST['m'],$_REQUEST['c'],$_REQUEST['a']);
        if($resss == false) {
            ErrorCode::errorResponse(ErrorCode::DEFAULT_MSG,"Sorry! You don't have enough permissions");
        }
        // if(isset($_REQUEST['token']) && trim($_REQUEST['token'])){
        if(isset($new_json_data['token']) && trim($new_json_data['token'])){

            //兼容老版本代码验证token逻辑，需要重写 $_REQUEST['token'] 值
            $_REQUEST['token'] = $new_json_data['token'];

            $this->checkAuth();
            //用户消息
            $uid = $this->userId;
            // $where[] = 'touser_id = '.$uid;
            // $where[] = 'state = 0';
            $userMes = $this->getMessage($uid);//用户消息
//            $sysMes = $this->getSysMessageList($uid); //系统消息
            $sysMes = $this->getSysMessage($uid); //系统消息
            $msg_num = $userMes['num'] + $sysMes['num'];

            //账户
            $where = array(
                'user_id' => $this->userId
            );

            $res = $this->getOneAccount($where, 'money');
            if (empty($res)) {
                $res = array(
                    'money' => '0.00'
                );
            }
            $money_usable = $this->convert($res['money']);


        }else{
            //系统消息
//            $sysMes = $this->getSysMessageList(0);
            $sysMes = $this->getSysMessage(0);
            $msg_num = $sysMes['num'];
            $money_usable = '0.00';
        }

        //红包活动数据 [2018-04-03 新需求，判断当前红包活动状态，"1"为有活动正在进行，"0"为无活动正在进行]
        $is_redpacket_underway = D('Redpacket')->isRedpacketUnderway();
        $is_redpacket_underway = ($is_redpacket_underway == true) ? '1' : '0';

        //获取配置信息
        $config_nid = array(
            0 => 'Config:100001', //已为用户赚取元宝总数
            1 => 'Config:100002', //回扣返水赚钱率
            2 => 'Config:100003', //注册用户总数
        );
        $config = array();
        $redis = initCacheRedis();
        //app配置信息
        $arrConfig= $redis -> HMGet("Config:appConfig",array('nid','name','value'));
        $appConfig = json_decode($arrConfig['value'], true);

        foreach ($config_nid as $v){
            $GameConfig = $redis -> HMGet($v,array('nid','name','value'));
            $config[$GameConfig['nid']] = $GameConfig['value'];
        }

        //banner 轮播图
        //$banner = $this->getListBanner();
        $banner = [];
        $LBanner = $redis->lRange('BannerIds', 0, -1);
        foreach ($LBanner as $v){
            $tmp_banner = $redis->hGetAll("Banner:{$v}");
            if ($tmp_banner['banner_type'] == '1') {
                $banner[] = $tmp_banner;
            }
        }

        $newTime = time();
        foreach ($banner as $key=>$val){
            if($val['start_time'] != 0 && $val['end_time'] != 0){
                if($newTime >= $val['start_time']  && $val['end_time'] >= $newTime){
                    $banner[$key]['go_url'] = $val['replace_url'];
                    $banner[$key]['url'] = $val['replace_path'];
                } else {
                    $banner[$key]['go_url'] = $val['default_url'];
                    $banner[$key]['url'] = $val['default_path'];
                }
            } else {
                $banner[$key]['go_url'] = $val['default_url'];
                $banner[$key]['url'] = $val['default_path'];
            }
        }

        //热门彩种推荐
        $recommend_list = D('Recommend')->fetchLotteryList();

        $config_with_key = [
            'user_gain' => $config['100001'],
            'make_money_rate' => $config['100002'],
            'reg_count' => $config['100003'],
        ];

        //后台配置的首页显示彩种信息
        $index_lottery_list = $redis -> HMGet('Config:index_lottery_list',['value']);


        //投注列表，只取6条数据
        $bettingList = D('lobby')->getBettingList(6);

        $json_arr = [
            'banner' => $banner,                    //轮播图
            'app_config' => $appConfig,             //app首页配置信息
            'recommend_list' => $recommend_list,    //热门彩种推荐
            'virtual_data' => $config_with_key,     //虚拟数据
            'bettingList' => $bettingList,          //投注列表
            'msg_num' => $msg_num,                  //消息数量
            'money_usable' => $money_usable,        //余额
            'sysMessage' => $sysMes['list'],        //公告
            'is_redpacket_underway' => $is_redpacket_underway,    //红包活动数据
        ];

        $reData = [
            'data' => new_encrypt(json_encode($json_arr, JSON_UNESCAPED_UNICODE)),
            // 'data' => (json_encode($json_arr, JSON_UNESCAPED_UNICODE)),
        ];

        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($reData);
    }

    /**
     * 首页简单接口
     * @method
     * @param code string 用户手机物理地址
     * @param token string 用户token
     * @return json
     */
    public function indexSim(){
        //域名跨域问题
        header("Access-Control-Allow-Origin: *");
        lg('index_sim',var_export(array(
            '$_REQUEST'=>$_REQUEST,
        ),1));

        //接收前端app数据并解密
        $new_json_data = $this->handle_post();

        $_lg_ = [
            'request-j'=>$_REQUEST['json'],
            'new-j'=>$new_json_data,
        ];
        lg('index_request_data.txt', var_export($_lg_, true) . "\n\n");

        //验证token
        $resss = $this->model1->isIpBlack($new_json_data['code'],$_REQUEST['m'],$_REQUEST['c'],$_REQUEST['a']);
        if($resss == false) {
            ErrorCode::errorResponse(ErrorCode::DEFAULT_MSG,"Sorry! You don't have enough permissions");
        }
        $redis = initCacheRedis();

        //后台配置的首页显示彩种信息
        $index_lottery_list = $redis -> HMGet('Config:index_lottery_list',['value']);

        $lottery_list = json_decode($index_lottery_list['value'], true);

        //去掉不显示的彩种，并添加六合彩“今日开奖”角标的标识字段
        foreach ($lottery_list as $lottery_key => &$lottery_one) {
            if ($lottery_one['is_show'] == '0') {
                unset($lottery_list[$lottery_key]);
            }

            //如果有六合彩，则添加“今日开奖”标识字段
            if ($lottery_one['lottery_type'] == '7') {
                $lhc_qihao_json = $redis->lRange('QiHaoIds7', 0, 0);
                $tmp_award_date = json_decode($lhc_qihao_json[0], true);
                $is_award_today = (date('Y-m-d') == date('Y-m-d', $tmp_award_date['date'])) ? '1' : '0';
                $lottery_one['is_award_today'] = $is_award_today;
            }

            //增加各种期号周期数据显示
            $lottery_one['period_info'] = $this->model2->getLotteryPeriodInfo($lottery_one['lottery_type']);
        }

        $tmp_sort_key = [];
        foreach ($lottery_list as $k2 => $v2) {
            $tmp_sort_key[$k2] = $v2['sort'];
        }

        //重新按sort字段升序排列
        array_multisort($tmp_sort_key, SORT_ASC, $lottery_list);

        //查询出当前设置为已弹窗的消息id
        $popup_msg_id = D('admin/mail')->hasPopupAnnouncement();
        $popup_msg_id = intval($popup_msg_id) . '';

        $json_arr = [
            'lottery_list' => $lottery_list,        //首页彩种
            'popup_msg_id' => $popup_msg_id,        //弹窗消息id，如果没有，则传字符串'0'
        ];

        $reData = [
//            'data' => new_encrypt(json_encode($json_arr, JSON_UNESCAPED_UNICODE)),
            'data' => $json_arr,
            // 'data' => (json_encode($json_arr, JSON_UNESCAPED_UNICODE)),
        ];


        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($reData);
    }

    public function test() {
        $popup_msg_id = D('admin/mail')->hasPopupAnnouncement();
        $popup_msg_id = intval($popup_msg_id);
        var_dump($popup_msg_id);
        die;

    }

    /**
     * 根据彩种类别id房间信息
     * @method POST  /index.php?m=api&c=lobbynew&a=room_info[&token='xxxx']
     */
    public function room_info () {
        $isAuthRoom = 0;

        //接收前端app数据并解密
        $new_json_data = $this->handle_post();


        $_lg_ = [
            'request-j'=>$_REQUEST['json'],
            'new-j'=>$new_json_data,
        ];

        //兼容老版本代码验证token逻辑，需要重写 $_REQUEST['token'] 值
        $_REQUEST['token'] = $new_json_data['token'];

        $this->checkAuth();

        //账户
        $where = array(
            'user_id' => $this->userId
        );

        $res = $this->getOneAccount($where, 'money');
        if (empty($res)) {
            $res = array(
                'money' => '0.00'
            );
        }
        $money_usable = $this->convert($res['money']);

        //前端传过来的彩种类别
        $lottery_type = $new_json_data['lottery_type']?:1;

        lg('sjb_room_list_debug',var_export(array('$lottery_type'=>$lottery_type),1));

        $redis = initCacheRedis();

        //彩种标题
        $lottery_title = $redis->hGet("LotteryType:{$lottery_type}",'name');


        //获取实时在线房间人数
        $PublicRoom = [];

        $_lg_tmp_sql = [];


        //房间下限和用户组下限时，最大值
        $user_group_lower= 0 ;
        $group_id =  $this->db->getone("SELECT group_id FROM un_user WHERE id={$this->userId}");
        $group_id =$group_id["group_id"];
        $group_info = $redis->hGetAll('group:'.$group_id);
        if($group_info['limit_state']==1){
            $user_group_lower = $group_info["lower"];
        }else{
            $user_group_lower = 0;
        }

        // $gameInfo = $redis->hGetAll("LotteryType:{$lottery_type}");
        $PRoomIds = $redis->lRange("PublicRoomIds{$lottery_type}", 0, -1);
        $SRoomIds = $redis->lRange("PrivateRoomIds{$lottery_type}", 0, -1);

        //公开房间缓存统计值
        $public_room_count = 0;

        //私密房间缓存统计值
        $private_room_count = 0;

        foreach ($PRoomIds as $key=>$k){
            $PRoomInfo = $redis -> hGetAll("PublicRoom{$lottery_type}:{$k}");
            $PRoomInfo['lottery_title'] = $lottery_title;
            if($PRoomInfo['lower'] <= $user_group_lower){
                $PRoomInfo['lower'] = $user_group_lower;
            }

            $PRoomInfo['lottery_title'] = $lottery_title;
            if($PRoomInfo['passwd'] == ''){
                $PRoomInfo['online'] += $this->getTotal($PRoomInfo['id']);
                //公开房间+1
                $public_room_count++;
            }else{
                foreach ($SRoomIds as $sk){
                    $SRoomInfo = $redis -> hMGet("PrivateRoom{$lottery_type}:{$sk}", ['id','online']);
                    $SRoomInfo['online'] += $this->getTotal($SRoomInfo['id']);
                    $PRoomInfo['online'] += $SRoomInfo['online'];
                    //私密房间+1
                    $private_room_count++;
                }
                
                //限制用户进入房间验证权限
                $authorRoom = checkAuthorRoom($this->userId, $PRoomInfo['lottery_type']);
                if (!empty($authorRoom)) {
                    $isAuthRoom = 1;
                }
            }


            $PRoomInfo['isZhuiHao'] = '1';

            //增加state字段，兼容老版本接口写法
            $PRoomInfo['state'] = $PRoomInfo['status'];

            //获取赛事信息
//            if ($lottery_type == 12) {
//                $match_id = $redis->hGet('allroom:'.$k, 'match_id');
//                if (!empty($match_id)) {
//                    $PRoomInfo['against'] = $this->db->getone("select * from #@_cup_against where match_id = {$match_id}");
//                }
//
//            }

            if ($lottery_type == 12) {
                if ($PRoomInfo['status'] == 1){
                    unset($PRoomInfo[$key]);
                    continue;
                }
                $match_id = $PRoomInfo['match_id'];
                if (!empty($match_id)) {
                    $PRoomInfo['against'] = $this->db->getone("select * from #@_cup_against where match_id = {$match_id}");
                    $st = array(
                        '待开赛','上半场','半场结束','下半场','下半场结束','加时','加时结束','点球','点球结束','全场结束'
                    );
                    $str1 = '/statics/web/images/sjb/dh'.toPY($PRoomInfo['against']['team_1_name']).'.png';
                    $PRoomInfo['dh1'] = is_file(S_ROOT.$str1)?$str1:'/statics/web/images/sjb/dhzg.png';
                    $str2 = '/statics/web/images/sjb/dh'.toPY($PRoomInfo['against']['team_2_name']).'.png';
                    $PRoomInfo['dh2'] = is_file(S_ROOT.$str2)?$str2:'/statics/web/images/sjb/dhzg.png';
                }
            }

            //去掉多余字段
            unset($PRoomInfo['reverse']);
            unset($PRoomInfo['special_way']);
            unset($PRoomInfo['backRate']);
            $PublicRoom[] = $PRoomInfo;
        }
        lg('new_ui_room_info_api', var_export(['sql' => $_lg_tmp_sql, 'PRoomIds' => $PRoomIds, 'SRoomIds' => $SRoomIds, 'res' => $res], true));
        if($lottery_type == 12){
            lg('sjb_room_list_debug', var_export(array('$PRoomInfo'=>$PRoomInfo,'$PublicRoom'=>$PublicRoom), true));
        }



        //判断是否只有一个公开房间
        if ($public_room_count === 1 && $private_room_count === 0) {
            $is_only_one_room = '1';
        } else {
            $is_only_one_room = '0';
        }


        $json_arr = [
            'room' => $PublicRoom,                      //房间信息
            'money_usable' => $money_usable,            //余额
            'lottery_title' => $lottery_title,          //彩种标题
            'isAuthRoom' => $isAuthRoom,                //判断该采种对该用户是否有限制1：有，0：无
            'is_only_one_room' => $is_only_one_room,    //是否只有一个公用房间：1.是 0.否
        ];

        $reData = [
            'data' => new_encrypt(json_encode($json_arr, JSON_UNESCAPED_UNICODE)),
        ];
        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($reData);

    }

    // /**
    //  * 私密房间接口
    //  * @method GET  index.php?m=api&c=lobby&a=privateRoom&token=51d38276de5c19631afc3c467a867148&lottery_type=2&secret_pwd=281002001
    //  * @param token string 用户token
    //  * @param lottery_type string 类型
    //  * @param secret_pwd string 口令
    //  * @return json
    //  */
    // public function privateRoom(){
    //     //验证参数
    //     $this->checkInput($_REQUEST, array('token','lottery_type','secret_pwd'));
    //     $lottery_type = trim($_REQUEST['lottery_type']);
    //     $secret_pwd = trim($_REQUEST['secret_pwd']);

    //     //验证token
    //     $this->checkAuth();

    //     if(empty($lottery_type)){
    //         ErrorCode::errorResponse(100002,'选择要进入的游戏');
    //     }

    //     //初始化redis
    //     $redis = initCacheRedis();

    //     //验证游戏类型
    //     $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);

    //     if(!in_array($lottery_type,$LotteryTypeIds)){
    //         ErrorCode::errorResponse(100012,'该类型游戏不存在');
    //     }

    //     if(empty($secret_pwd)){
    //         ErrorCode::errorResponse(100003,'房间口令不能为空');
    //     }

    //     //验证游戏口令

    //     $room_SPwd = $redis->lRange("PrivateRoomIds".$lottery_type, 0, -1);
    //     if (!in_array($secret_pwd,$room_SPwd)){
    //         ErrorCode::errorResponse(100004,'房间口令不正确,或该房间暂时关闭,请登录其它房间');
    //     }
    //     $current_room = $redis->hGetAll("PrivateRoom".$lottery_type.":".$secret_pwd);
    //     $roomTotal = $this->getTotal($current_room['id']);

    //     if($roomTotal > ($current_room['max_number'] -1)){
    //         ErrorCode::errorResponse(100007,'房间已满请更换房间');
    //     }
    //     $current_room['online'] += $this->getTotal($current_room['id']);

    //     $user_temp=$this->db->getone('select user_id from un_session where sessionid="'.$_REQUEST['token'].'"');
    //     if($user_temp){
    //         $user_id=$user_temp['user_id'];
    //         $isZhuiHao = $this->db->getone("select count(*) as num from un_orders where room_no={$current_room['id']} and lottery_type={$lottery_type} and award_state = 0 and state = 0 and user_id=$user_id and chase_number !=''");
    //         $current_room['isZhuiHao']=$isZhuiHao['num'];
    //     }else{
    //         $current_room['isZhuiHao']=0;
    //     }
        
    //     //关闭redis链接
    //     deinitCacheRedis($redis);
    //     ErrorCode::successResponse(array('data' => $current_room));
    // }

    // /**
    //  * 获取轮播图
    //  * @param $limit int 条数
    //  * @return $res array
    //  */
    // public function getListBanner($limit = null){
    //     $filed = 'id, title, url, is_go, go_url, sort';
    //     $where = array(
    //         'is_show' => 1,
    //     );
    //     $order = 'sort ASC';
    //     //显示几条
    //     if ($limit){
    //         $res = $this->model->getList($filed,$where,$order,$limit);
    //     }else{
    //         $res = $this->model->getList($filed,$where,$order);
    //     }

    //     return $res?$res:null;
    // }

    /**
     * 在线人数
     * @param $room mixed 分组
     * @return $total int
     */
    public function getTotal($room = 'all'){
        //return 0;
        $Gateway = O('Gateway');
        $Gateway::$registerAddress = C('Gateway');
        if($room == 'all'){
            $total =  $Gateway::getAllClientCount();
        }else{
            $total =  $Gateway::getClientCountByGroup($room);
        }
        return $total?$total:0;
    }

    // /**
    //  * 房间publicRoom
    //  * @param $room mixed 分组
    //  * @return $total int
    //  */
    // public function getPublicRoom($where,$limit = null){
    //     $filed = 'id, title, max_number, lower, upper, passwd, sort, lottery_type, status, online';
    //     $order = 'sort ASC';
    //     //显示几条
    //     if ($limit){
    //         $res = $this->model2->getList($filed,$where,$order,$limit);
    //     }else{
    //         $res = $this->model2->getList($filed,$where,$order);
    //     }

    //     return $res;
    // }

    /**
     * 信息message
     * @param $filed string 字段
     * @param $where mixed 条件
     * @param $limit string 条数
     * @return $res array
     */
    public function getMessage($uid){
        $tmpNum = 0;
        $sql = "select * from un_message where touser_id like '%|".$uid."|%' and type = 2 order by addtime desc";
        $ids = $this->db->getall($sql);
        foreach($ids as $key=>$val)
        {
            if(strpos($val['has_read'],"|".$uid."|") !== false)
            {
                $tmpNum ++;
                $ids[$key]['has_read'] = 0;
            }
            else
            {
                $ids[$key]['has_read'] = 1;
            }
        }

        $arr['num'] = count($ids) - $tmpNum;
        $arr['list'] = $ids;
        return $arr;
    }


    //获取系统公告
    protected function getSysMessage($uid) {
        $sql = "select `content` from un_message where touser_id = '0' and type = 1 and recom = 2 order by addtime desc";
        $messagelist = $this->db->getall($sql);            //公告
        $datalist = array_column($messagelist, 'content');

        if($uid) {
            //已读公告消息
            $sql = "select count(*) as read_count from un_message where touser_id = '0' and type = 1 and recom = 2 and has_read like '%|".$uid."|%' order by addtime desc";
            $read_count = $this->db->getone($sql);
            $unread_count = count($datalist) - $read_count['read_count'];
        }else {
            $unread_count = count($datalist);
        }
        foreach($datalist as &$data) {
            $data = strCut($data,30);
            unset($data);
        }
        $res['list'] = $datalist;
        $res['num'] = $unread_count;
        return $res;
    }

    /**
     * 信息系统公告
     * @return $LSM array
     */
    protected function getSysMessageList($uid){
        $tmpNum = 0;
//        $sql = "select * from un_message where touser_id = '0' and type = 1 order by addtime desc";
//        $ids = $this->db->getall($sql);
        $all = D("common")->getMessage();
        $ids=[];
        foreach ($all as $i){
            if($i["touser_id"] == '0' && $i["type"] == 1){
                $ids[]=$i;
            }
        }

        foreach($ids as $key=>$val)
        {
            if(strpos($val['has_read'],"|".$uid."|") !== false)
            {
                $tmpNum ++;
                $ids[$key]['has_read'] = 0;
            }
            else
            {
                $ids[$key]['has_read'] = 1;
            }
            if($val['recom'] == 2)
            {
                $list[] = $val['title'];
            }
        }
        $arr['num'] = count($ids) - $tmpNum;
        $arr['list'] = $list;
        return $arr;
    }

    /**
     * 账户信息
     * @param $filed string 字段
     * @param $where mixed 条件
     * @return $res array
     */
    private function getOneAccount($where, $filed = '*') {
        $res = $this->model4->getOneCoupon($filed, $where);
        return $res;
    }

    /**
     * 定时修改首页数据的配置值（每天定时增加数据）
     * 2018-04-04
     */
    public function updateIndexVirtualData()
    {
        return D('Lobby')->updateIndexVirtualData();
    }
    
    //获取所有采种类型房间列表（私密房只显示私密房）
    public function getRoomList()
    {
        $roomList = [];
    
        $redis = initCacheRedis();
        //后台配置的首页显示彩种信息
        $index_lottery_list = $redis->HMGet('Config:index_lottery_list',['value']);
        $lottery_list = json_decode($index_lottery_list['value'], true);
    
        $lotteryIds = $redis->lRange("LotteryTypeIds", 0, -1);
    
        foreach ($lotteryIds as $k => $v) {
            $roomList[$k]['name'] = $redis->hget('LotteryType:' . $v,'name');
            $roomList[$k]['lotteryId'] = $v;
            $roomList[$k]['times'] = D('workerman')->getQihao($v,time());
            $roomList[$k]['roomList'] = $this->getLotteryRoomLists($v);
            /*
            foreach ($lottery_list as $kl => $vl) {
                if ($vl['lottery_type'] == $v) {
                    $roomList[$k]['pic_url_pc_logo'] = $vl['pic_url_pc_logo'];
                }
            }
            */
            
        }
    
        deinitCacheRedis($redis);
    
        $retData = [
            'room_list' => $roomList,
        ];
    
        ErrorCode::successResponse(['data' => $retData]);
    }
    
    //获取采种类型房间列表
    public function getLotteryRoomLists($lottery_type)
    {
        $redis = initCacheRedis();
    
        $title = $redis->hget('LotteryType:'.$lottery_type,'name'); //给前端显示名字用的
    
        $PRoomIds = $redis->lRange("PublicRoomIds{$lottery_type}", 0, -1);
        $SRoomIds = $redis->lRange("PrivateRoomIds{$lottery_type}", 0, -1);
        foreach ($PRoomIds as $k){
            $PRoomInfo = $redis -> hGetAll("PublicRoom{$lottery_type}:{$k}");
            if($PRoomInfo['passwd'] == ''){
                $PRoomInfo['online'] += $this->getTotal($PRoomInfo['id']);
            }else{
                foreach ($SRoomIds as $sk){
                    $SRoomInfo = $redis -> hMGet("PrivateRoom{$lottery_type}:{$sk}", ['id','online']);
                    $SRoomInfo['online'] += $this->getTotal($SRoomInfo['id']);
                    $PRoomInfo['online'] += $SRoomInfo['online'];
                }
            }
            //去掉多余字段
            unset($PRoomInfo['reverse']);
            unset($PRoomInfo['special_way']);
            unset($PRoomInfo['backRate']);
            $PublicRoom[] = $PRoomInfo;
        }
        deinitCacheRedis($redis);
    
        return $PublicRoom;
    }

    // /**
    //  * 获取对应房间的信息
    //  */
    // public function getRoomInfo() {
    //     //验证参数
    //     $this->checkInput($_REQUEST, array('token','room_id'));
    //     $room_id = trim($_REQUEST['room_id']);

    //     //验证token
    //     $this->checkAuth();

    //     //初始化redis
    //     $redis = initCacheRedis();
    //     $roomInfo = $redis->hGetAll('allroom:'.$room_id);

    //     if(empty($roomInfo)){
    //         ErrorCode::errorResponse(1,'此房间已被关闭!!!');
    //     }

    //     //关闭redis链接
    //     deinitCacheRedis($redis);

    //     //用户可用余额
    //     $res = D('account')->getOneCoupon( 'money', array('user_id' => $this->userId));
    //     $money_usable = $this->convert($res['money']);
    //     $isZhuiHao = $this->db->getone("select count(*) as unm from un_orders where room_no=$room_id and lottery_type={$roomInfo['lottery_type']} and award_state = 0 and state = 0 and user_id='".$this->userId."' and chase_number !=''");
    //     ErrorCode::successResponse(array(
    //         'title' => $roomInfo['title'],
    //         'max_number' => $roomInfo['max_number'],
    //         'max_yb' => $roomInfo['max_yb'],
    //         'low_yb' => $roomInfo['low_yb'],
    //         'online' => $roomInfo['online'],
    //         'lack_tips' => $roomInfo['lack_tips'],
    //         'state' => $roomInfo['status'],
    //         'money_usable' => $money_usable,
    //         'isZhuiHao'=>$isZhuiHao['unm']
    //     ));
    // }

    // public function getMessageNum()
    // {
    //     $this->checkAuth();
    //     $uid = $this->userId;
    //     $userMes = $this->getMessage($uid);//用户消息
    //     $sysMes = $this->getSysMessageList($uid); //系统消息
    //     $msg_num = $userMes['num'] + $sysMes['num'];
    //     $arr['msg_num'] = $msg_num;
    //     $arr['status'] = 0;
    //     $arr['ret_msg'] = "";
    //     ErrorCode::successResponse($arr);
    // }

    // /**
    //  * @return mixed
    //  */
    // public function getRealUserTotal()
    // {
    //     $pass = $_REQUEST['s'];
    //     if ($pass != 'a8fce04d58c1f06f30da6d33c7523abc') return;
    //     $Gateway = O('Gateway');
    //     $Gateway::$registerAddress = C('Gateway');
    //     $total =  $Gateway::getAllClientRealMan(); //改写了一个方法来统计在线真人
    //     echo $total;
    // }

    // /**
    //  * @return mixed
    //  */
    // public function getUserTotal()
    // {
    //     $pass = $_REQUEST['s'];
    //     if ($pass != 'a8fce04d58c1f06f30da6d33c7523abc') return;
    //     echo $this->getTotal();
    // }



    public function download_page() {
        $type = getParame('type',0,0,'int');

        $confKey = 'download_page_set';
        if($type == 1) $confKey = 'app_download_page_info';
        if($type == 2) $confKey = 'pc_download_page_info';
        if($type == 3) $confKey = 'pc_photo_list';
        if(!$confKey) {
            jsonReturn(['status' => 1, 'ret_msg' => 'type error']);
        }

        $redis = initCacheRedis();
        $data = $redis->hMGet('Config:'.$confKey, ['value']);
        $data && $data = unserialize($data['value']);
        jsonReturn(['status' => 0, 'ret_msg' => 'success', 'data' => $data]);
    }
}