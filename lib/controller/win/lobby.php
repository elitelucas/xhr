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

class LobbyAction extends Action
{
    /**
     * 数据表
     */
    private $lobbyModel;
    private $bannerModel;
    private $userModel;
    private $roomModel;
    private $messageModel;
    private $accountModel;
    
    public function __construct(){
        parent::__construct();
        $this->lobbyModel  = D('lobby');
        $this->bannerModel  = D('banner');
        $this->userModel    = D('user');
        $this->roomModel    = D('room');
        $this->messageModel = D('message');
        $this->accountModel = D('account');
    }


    /**
     * 大厅首页接口
     * @method GET/POST  /index.php?m=api&c=lobbynew&a=index[&token='xxxx']
     * @param code string 用户手机物理地址
     * @param token string 用户token
     * @return json
     */
    public function index()
    {
        //系统消息(所有)
        $sysMes = $this->lobbyModel->getSysMessageList();
        $msg_num = $sysMes['num'];
        $money_usable = '0.00';
        $redpacket_info = '';
        $app_version = []; //app二维码
        
        //打开redis
        $redis = initCacheRedis();

        //banner 轮播图
        $banner = [];
        $LBanner = $redis->lRange('BannerIds', 0, -1);
        foreach ($LBanner as $v){
            $tmp_banner = $redis->hGetAll("Banner:{$v}");
            if ($tmp_banner['banner_type'] == '2') {
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

        //后台配置的首页显示彩种信息
        $index_lottery_list = $redis->HMGet('Config:index_lottery_list',['value']);
        $lottery_list = json_decode($index_lottery_list['value'], true);

        $appVersion = $redis->HMGet('Config:appVersion',['value']);
        $app_version = json_decode($appVersion['value'], true);
        
        
        //去掉不显示的彩种
        $noneShowLottery = [];          //不显示的彩种
        foreach ($lottery_list as $lottery_key => &$lottery_one) {
            if ($lottery_one['is_show'] == '0') {
                $noneShowLottery[] = $lottery_one['lottery_type'];
                unset($lottery_list[$lottery_key]);
            } else {
                // $lottery_list[$lottery_key]['lottery_dec'] = 'pc手游是一款时尚好玩的游戏，轻轻松松赚钱，快快乐乐游戏！';
                // $lottery_list[$lottery_key]['star'] = 4;

                //玩法简介
                $lottery_list[$lottery_key]['lottery_dec'] = $lottery_one['play_intro'];

                //星级评价，需转换成整型
                $lottery_list[$lottery_key]['star'] = $lottery_one['star_level'] - 0;
            }

            //如果有六合彩，则添加“今日开奖”标识字段
            if ($lottery_one['lottery_type'] == '7') {
                $lhc_qihao_json = $redis->lRange('QiHaoIds7', 0, 0);
                $tmp_award_date = json_decode($lhc_qihao_json[0], true);
                $is_award_today = (date('Y-m-d') == date('Y-m-d', $tmp_award_date['date'])) ? '1' : '0';
                $lottery_one['is_award_today'] = $is_award_today;
            }

            //增加各种期号周期数据显示
            $lottery_one['period_info'] = $this->roomModel->getLotteryPeriodInfo($lottery_one['lottery_type']);
        }

        //热门彩种推荐
        $recommend_list = D('Recommend')->fetchLotteryList();

        if($noneShowLottery) {
            foreach($recommend_list as $k=>$v) {
                if(in_array($v['lottery_type'], $noneShowLottery)) unset($recommend_list[$k]);
            }
            $recommend_list = array_values($recommend_list);
        }

        //关闭redis链接
        deinitCacheRedis($redis);

        $tmp_sort_key = [];
        foreach ($lottery_list as $k2 => $v2) {
            $tmp_sort_key[$k2] = $v2['sort'];
        }
        //重新按sort字段升序排列
        array_multisort($tmp_sort_key, SORT_ASC, $lottery_list);
        
        $bettingList = $this->lobbyModel->getBettingList();
        
        //查询出当前设置为已弹窗的消息id
        $popup_msg_id = D('admin/mail')->hasPopupAnnouncement();
        $popup_msg_id = intval($popup_msg_id) . '';

        $json_arr = [
            'banner'         => $banner,             //轮播图
            'recommend_list' => $recommend_list,     //热门彩种推荐
            'lottery_list'   => $lottery_list,       //首页彩种
            'msg_num'        => $msg_num,            //公告消息数量
            'popup_msg_id'   => $popup_msg_id,       //弹窗消息id，如果没有，则传字符串'0'
            'money_usable'   => $money_usable,       //余额
            'sysMessage'     => $sysMes['list'],     //公告
            'lastMessage'    => $sysMes['lastDetail'],  //最近一条公告信息
            'bettingList'    => $bettingList,        //最近20条投注记录
            'ad_url'         => '/statics/win/images/index_advert.png',  //广告图片url 
            'app_qrcode'     => $app_version['qrcode_url']
        ];

        ErrorCode::successResponse(['data' => $json_arr]);
    }
    
    
    //获取采种类型房间列表
    public function getLotteryRoomList()
    {
        $isAuthRoom = 0;
        $redis = initCacheRedis();
        $lottery_type = $_REQUEST['type'];

        $this->checkAuth();
    
        $title = $redis->hget('LotteryType:'.$lottery_type,'name'); //给前端显示名字用的

        $group_id =  $this->db->getone("SELECT group_id FROM un_user WHERE id={$this->userId}");
        $group_id =$group_id["group_id"];
        $group_info = $redis->hGetAll('group:'.$group_id);
        if($group_info['limit_state']==1){
            $user_group_lower = $group_info["lower"];
        }else{
            $user_group_lower = 0;
        }
    
        $PRoomIds = $redis->lRange("PublicRoomIds{$lottery_type}", 0, -1);
        $SRoomIds = $redis->lRange("PrivateRoomIds{$lottery_type}", 0, -1);
        foreach ($PRoomIds as $k){
            $PRoomInfo = $redis -> hGetAll("PublicRoom{$lottery_type}:{$k}");
            if($PRoomInfo['lower'] <= $user_group_lower){
                $PRoomInfo['lower'] = $user_group_lower;
            }
            if($PRoomInfo['passwd'] == ''){
                $PRoomInfo['online'] += $this->getTotal($PRoomInfo['id']);
            }else{
                foreach ($SRoomIds as $sk){
                    $SRoomInfo = $redis -> hMGet("PrivateRoom{$lottery_type}:{$sk}", ['id','online','pc_avatar','odds_exp']);
                    $SRoomInfo['online'] += $this->getTotal($SRoomInfo['id']);
                    $PRoomInfo['online'] += $SRoomInfo['online'];
                    $PRoomInfo["idd"] = $SRoomInfo["id"];
                    $SRoomInfo["id"] = $sk;
                }

                $PRoomInfo['pc_avatar'] = $SRoomInfo['pc_avatar'];
                $PRoomInfo['odds_exp'] = $SRoomInfo['odds_exp'];
            }
            //去掉多余字段
            unset($PRoomInfo['reverse']);
            unset($PRoomInfo['special_way']);
            unset($PRoomInfo['backRate']);

            $PublicRoom[] = $PRoomInfo;
        }
        deinitCacheRedis($redis);
        
        //限制用户进入房间验证权限
        $authorRoom = checkAuthorRoom($this->userId, $lottery_type);
        if (!empty($authorRoom)) {
            $isAuthRoom = 1;
        }
        
        $retData = [
            'p_room' => $PublicRoom,
            'isAuthRoom' => $isAuthRoom,
        ];
        
        ErrorCode::successResponse(['data' => $retData]);
    }
    
    public function getHeaderFooter()
    {
        //初始化redis
        $redis = initCacheRedis();
        
        //后台配置的首页显示彩种信息
        $index_lottery_list = $redis->HMGet('Config:index_lottery_list',['value']);
        $lottery_list = json_decode($index_lottery_list['value'], true);
        
        //去掉不显示的彩种
        foreach ($lottery_list as $lottery_key => &$lottery_one) {
            if ($lottery_one['is_show'] == '0') {
                unset($lottery_list[$lottery_key]);
            }
        }
        
        $tmp_sort_key = [];
        foreach ($lottery_list as $k2 => $v2) {
            $tmp_sort_key[$k2] = $v2['sort'];
        }
        
        //重新按sort字段升序排列
        array_multisort($tmp_sort_key, SORT_ASC, $lottery_list);
        
        
        //以为用户赚取元宝数
        $winConfig= $redis->HMGet("Config:100001",array('nid','name','value'));
        $win_num = $winConfig['value'];
        
        //获取后台配置赚钱率
        $earnConfig  = $redis->HMGet("Config:100002",array('nid','name','value'));
        $earnRate = $earnConfig['value'];
        
        //获取后台配置注册人数
        $GameConfig  = $redis->HMGet("Config:100003",array('nid','name','value'));
        $set_reg_num = $GameConfig['value'];
        //获取实际有效的注册用户
        $reg_total    = $this->lobbyModel->regNum();
        $configRegNum = is_numeric($set_reg_num) ? $set_reg_num : 0;
        $reg_total    += $configRegNum;
  
        //获取配置累计提现兑换人次
        $GameConfig= $redis->HMGet("Config:100004",array('nid','name','value'));
        $set_withdraw_num = $GameConfig['value'];
        //获取实际有效提现次数
        $withdraw_total = $this->lobbyModel->withdrawNum();
        $configTotal    = is_numeric($set_withdraw_num) ? $set_withdraw_num : 0;
        $withdraw_total += $configTotal;
        
        //关闭redis链接
        deinitCacheRedis($redis);

        $retData = [
            'withdraw_total' => $withdraw_total,     //累计提现兑换人次
            'fit_total'      => $win_num,            //已赚取元宝数
            'reg_total'      => $reg_total,          //已注册人数
            'earn_rate'      => $earnRate,           //赚钱率
            'lottery_list'   => $lottery_list,       //首页彩种
        ];
        
        ErrorCode::successResponse(['data' => $retData]);
    }

    /**
     * 根据彩种类别id房间信息
     * @method POST  /index.php?m=api&c=lobbynew&a=room_info[&token='xxxx']
     */
    public function room_info () {
        // lg('new_api2', var_export(['g'=>$_GET,'p'=>$_POST,'re'=>$_REQUEST], 1));
        $isAuthRoom = 0;

        //接收前端app数据并解密
        $new_json_data = $this->handle_post();

        //$new_json_data = ['token' => '3s19kvpf18e52t9qad91g1j804', 'lottery_type' => 1];

        $_lg_ = [
            'request-j'=>$_REQUEST['json'],
            'new-j'=>$new_json_data,
        ];
        lg('index_room_info_request_data.txt', var_export($_lg_, true) . "\n\n");

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
        foreach ($PRoomIds as $k){
            $PRoomInfo = $redis -> hGetAll("PublicRoom{$lottery_type}:{$k}");
            $PRoomInfo['lottery_title'] = $lottery_title;
            if($PRoomInfo['lower'] <= $user_group_lower){
                $PRoomInfo['lower'] = $user_group_lower;
            }

            $PRoomInfo['lottery_title'] = $lottery_title;
            if($PRoomInfo['passwd'] == ''){
                $PRoomInfo['online'] += $this->getTotal($PRoomInfo['id']);
            }else{
                foreach ($SRoomIds as $sk){
                    $SRoomInfo = $redis -> hMGet("PrivateRoom{$lottery_type}:{$sk}", ['id','online']);
                    $SRoomInfo['online'] += $this->getTotal($SRoomInfo['id']);
                    $PRoomInfo['online'] += $SRoomInfo['online'];
                }
                
                //限制用户进入房间验证权限
                $authorRoom = checkAuthorRoom($this->userId, $PRoomInfo['lottery_type']);
                if (!empty($authorRoom)) {
                    $isAuthRoom = 1;
                }
            }

            //非私密房间处理
//            if (strpos($k, '_') === false) {
//                //是否追号字段
//                $tmp_sql = "SELECT id FROM un_orders
//                    WHERE room_no = '{$k}'
//                    AND lottery_type = {$lottery_type}
//                    AND award_state = 0
//                    AND state = 0
//                    AND user_id = {$this->userId}
//                    AND chase_number != ''
//                    LIMIT 1";
//                $zhuiHaoInfo = $this->db->getone($tmp_sql);
//
//                $_lg_tmp_sql[$k] = ['sql' => $tmp_sql, 'zhuiHaoInfo' => $zhuiHaoInfo];
//
//                if ($zhuiHaoInfo['id']) {
//                    $PRoomInfo['isZhuiHao'] = '1';
//                } else {
//                    $PRoomInfo['isZhuiHao'] = '0';
//                }
//            }
//            //私密房处理，这里需要注意，虽然有传 isZhuiHao 字段，但是app端不能以这个标识来判断该用户是否有追号，私密房里用户是否有追号，需要到老接口里【/index.php?m=api&c=lobby&a=privateRoom】去判断
//            else {
//                $PRoomInfo['isZhuiHao'] = '0';
//            }
            $PRoomInfo['isZhuiHao'] = '1';

            //增加state字段，兼容老版本接口写法
            $PRoomInfo['state'] = $PRoomInfo['status'];

            //去掉多余字段
            unset($PRoomInfo['reverse']);
            unset($PRoomInfo['special_way']);
            unset($PRoomInfo['backRate']);
            $PublicRoom[] = $PRoomInfo;
        }
        lg('new_ui_room_info_api', var_export(['sql' => $_lg_tmp_sql, 'PRoomIds' => $PRoomIds, 'SRoomIds' => $SRoomIds, 'res' => $res], true));

        $json_arr = [
            'room' => $PublicRoom,                  //房间信息
            'money_usable' => $money_usable,        //余额
            'lottery_title' => $lottery_title,      //彩种标题
            'isAuthRoom' => $isAuthRoom,            //判断该采种对该用户是否有限制1：有，0：无
        ];

        $reData = [
            'data' => new_encrypt(json_encode($json_arr, JSON_UNESCAPED_UNICODE)),
        ];

        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($reData);

    }
    
    //获取所有采种类型房间列表（私密房只显示私密房）
    public function getRoomList()
    {
        $roomList = [];
    
        $redis = initCacheRedis();
        //后台配置的首页显示彩种信息
        $index_lottery_list = $redis->HMGet('Config:index_lottery_list',['value']);
        $lottery_list = json_decode($index_lottery_list['value'], true);



        foreach($lottery_list as $lottery) {
            if($lottery['is_show'] == 0) continue;

            $roomList[] = [
                'name' => $redis->hget('LotteryType:' . $lottery['lottery_type'],'name'),
                'lotteryId' => $lottery['lottery_type'],
                'roomList' => $this->getLotteryRoomLists($lottery['lottery_type']),
                'pic_url_pc_logo' => $lottery['pic_url_pc_logo'],
            ];
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
    
    /*
    //获取所有采种类型房间列表（私密房只显示私密房）
    public function getRoomList()
    {
        $roomList = [];
        
        $redis = initCacheRedis();
        
        $lotteryIds = $redis->lRange("LotteryTypeIds", 0, -1);
        
        foreach ($lotteryIds as $k => $v) {
            $roomList[$k]['name'] = $redis->hget('LotteryType:' . $v,'name');
            $roomList[$k]['lotteryId'] = $v;
            $roomList[$k][roomList] = $this->getLotteryRoomList($v);
        }
        deinitCacheRedis($redis);
    
        $retData = [
            'room_list' => $roomList,
        ];
    
        ErrorCode::successResponse(['data' => $retData]);
    }
    */
    
    /*
    //获取所有采种类型房间列表（私密房只显示私密房）
    public function getRoomList()
    {
        $roomList = [];
    
        $redis = initCacheRedis();
    
        $lotteryIds = $redis->lRange("LotteryTypeIds", 0, -1);
    
        foreach ($lotteryIds as $k => $v) {
            $roomList[$k]['name'] = $redis->hget('LotteryType:' . $v,'name');
            $roomList[$k]['lotteryId'] = $v;
    
            $PRoomIds = $redis->lRange("PublicRoomIds" . $v, 0, -1);
            foreach ($PRoomIds as $kp){
                $PRoomInfo = $redis -> hGetAll("PublicRoom{$v}:{$kp}");
                if (strpos($PRoomInfo['id'], '_')) {
                    $PRoomInfo['id'] = -1;
                }
                $roomList[$k]['room'][] = ['roomName' => $PRoomInfo['title'], 'roomId' => $PRoomInfo['id']];
            }
        }
        deinitCacheRedis($redis);
    
        $retData = [
            'room_list' => $roomList,
        ];
    
        ErrorCode::successResponse(['data' => $retData]);
    }
    */

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
}