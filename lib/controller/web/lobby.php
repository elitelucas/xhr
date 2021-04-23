<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/11
 * Time: 9:43
 * desc: APP 首页接口 私密房间接口 部分数据待存入redis
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class LobbyAction extends Action{
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
     * @method GET  /index.php?m=api&c=lobby&a=index[&token='xxxx']
     * @param token string 用户token
     * @return json
     */
    public function index(){
        $res = $this->cp();
        if($res == 2){
            $data = array('msg'=>'System under maintenance!');
            include template('systemMaintenance');
            exit;
        }
        //检测用户是否登录
        $token = session_id();

        $sql = "SELECT user_id FROM #@_session WHERE sessionid = '{$token}' LIMIT 1";
        $userId = $this->db->result($sql);


        $redis = initCacheRedis();


        //已登陆则获取用户相关数据
        if($userId){
            $this->checkAuth();
            //用户消息
            $uid = $this->userId;
            $where[] = "touser_id = $uid";
            $where[] = 'state = 0';
            $userMes = $this->getMessage($uid); //用户消息
            $sysMes = $this->getSysMessageList($uid); //系统消息
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

            //登录状态下的红包数据
            $redpacket_info = D('Redpacket')->checkRedpacketByUser($this->userId);
            if ($redpacket_info === false) {
                $redpacket_info = '';
            }
        }else{
            //系统消息
            $sysMes = $this->getSysMessageList(0);
            $msg_num = $sysMes['num'];
            $money_usable = '0.00';

            //未登录状态下，红包数据默认为空
            $redpacket_info = '';
        }

        //获取配置信息
        $config_nid = array(
            0 => '100001', //已为用户赚取元宝总数
            1 => '100002', //回扣返水赚钱率
            2 => '100003', //注册用户总数
            3 => '100004', //在线用户总数
        );
        $config = array();
        foreach ($config_nid as $v){
            $GameConfig= $redis -> HMGet("Config:".$v,array('nid','name','value'));
            $config[$GameConfig['nid']] = $GameConfig['value'];
        }

        //banner 轮播图
        //$banner = $this->getListBanner();
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
        // 房间信息 获取实时在线房间人数后期调整 ???
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        $PublicRoom = array();
        foreach ($LotteryTypeIds as $v){
            $gameInfo = $redis->hGetAll("LotteryType:".$v);
            $PRoomIds = $redis->lRange("PublicRoomIds".$v, 0, -1);
            $SRoomIds = $redis->lRange("PrivateRoomIds".$v, 0, -1);
            foreach ($PRoomIds as $k){
                $PRoomInfo = $redis -> hGetAll("PublicRoom".$v.":".$k);
                if($PRoomInfo['passwd'] == ''){
                    $PRoomInfo['online'] += $this->getTotal($PRoomInfo['id']);
                }else{
                    foreach ($SRoomIds as $sk){
                        $SRoomInfo = $redis -> hMGet("PrivateRoom".$v.":".$sk,array('id','online'));
                        $SRoomInfo['online'] += $this->getTotal($SRoomInfo['id']);
                        $PRoomInfo['online'] += $SRoomInfo['online'];
                    }
                }
                $PublicRoom[$v]['room'][] = $PRoomInfo;
            }
            $PublicRoom[$v]['gameInfo'][] = $gameInfo;
        }

        $proom = array();
        foreach ($PublicRoom as $v){
            $proom[] =$v;
        }
        //在线总人数
        $total = $this->getTotal();
        # 获取后台配置参数
        $configTotal = isset($config['100004'])?$config['100004']:0;
        $total += $configTotal;

        //已注册人数
        $reg_num = $this->model1->reg_num();
        # 获取后台配置参数
        $configRegNum = isset($config['100003'])?$config['100003']:0;
        $reg_num += $configRegNum;

        //赚钱率
        $rate = isset($config['100002'])?$config['100002']:'59';

        //为用户赚钱
        $profit = isset($config['100001'])?$config['100001']:'10000.00';
        $reData = array(
            'sysMessage' => $sysMes['list'], //系统公告
            'banner' => $banner, //轮播图
            'list' => array(
                'total' => $total, //在线总人数
                'msg_num' => $msg_num, //新消息
                'reg_num' => $reg_num, //已注册人数
                'profit' => $profit, //收益
                'rate' => $rate, //收益
            ),
            'rlist' => $proom,
            'money_usable' => $money_usable,
        );

        //红包数据
        $reData['redpacket_info'] = json_encode($redpacket_info, JSON_UNESCAPED_UNICODE);

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
        array_multisort($tmp_sort_key, SORT_ASC, $lottery_list);//重新按sort字段升序排列

        $titleName = C('app_webname');
        //关闭redis链接
        deinitCacheRedis($redis);
        $nickName = session::get('nickname');
        $JumpUrl = $this->getUrl();

        $sql1 = "select `addtime`,url from un_version where `type`=1 order by addtime desc ";
        $re1 = $this->db->getone($sql1);
        $sql2 = "select `addtime`,url  from un_version where `type`=2 order by addtime desc ";
        $re2 = $this->db->getone($sql2);

        include template('index');
    }

    //幸运28
    public function indexSubNav()
    {
        $isAuthRoom = 0;
        //验证token
        $this->checkAuth();
        lg("debug_log","用户用户登录信息->".var_export($this->userId,true));
        if(!empty($this->userId)){
            $res = $this->getOneAccount(['user_id'=>$this->userId], 'money');
            if (empty($res)) {
                $res = array(
                    'money' => '0.00'
                );
            }
            $money = $this->convert($res['money']);
        }
        $redis = initCacheRedis();
        $lottery_type = $_REQUEST['type'];

        $title = $redis->hget('LotteryType:'.$lottery_type,'name'); //给前端显示名字用的

        $PRoomIds = $redis->lRange("PublicRoomIds{$lottery_type}", 0, -1);
        $SRoomIds = $redis->lRange("PrivateRoomIds{$lottery_type}", 0, -1);

        if($lottery_type == 12){
            lg('sjb_room_list_debug_web', var_export(array('$PRoomIds'=>$PRoomIds,'$SRoomIds'=>$SRoomIds), true));
        }

        //公开房间缓存统计值
        $public_room_count = 0;

        //私密房间缓存统计值
        $private_room_count = 0;

        foreach ($PRoomIds as $key=>$k){
            $PRoomInfo = $redis -> hGetAll("PublicRoom{$lottery_type}:{$k}");
            if ($lottery_type == 12) {
                if ($PRoomInfo['status'] == 1){
                    unset($PRoomInfo[$key]);
                    continue;
                }
                $match_id = $PRoomInfo['match_id'];
                if(!empty($match_id)){
                    $PRoomInfo['against'] = $this->db->getone("select * from #@_cup_against where match_id = {$match_id}");

                    $st = array(
                        '待开赛','上半场','半场结束','下半场','下半场结束','加时','加时结束','点球','点球结束','全场结束'
                    );
                    $str1 = '/statics/web/images/sjb/dh'.toPY($PRoomInfo['against']['team_1_name']).'.png';
                    $PRoomInfo['dh1'] = is_file(S_ROOT.$str1)?$str1:'/statics/web/images/sjb/dhzg.png';
//                dump(array('$str1'=>$str1,'is_file(S_ROOT.$str1)'=>is_file(S_ROOT.$str1)));
                    $str2 = '/statics/web/images/sjb/dh'.toPY($PRoomInfo['against']['team_2_name']).'.png';
                    $PRoomInfo['dh2'] = is_file(S_ROOT.$str2)?$str2:'/statics/web/images/sjb/dhzg.png';
                }
            }
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
            //去掉多余字段
            unset($PRoomInfo['reverse']);
            unset($PRoomInfo['special_way']);
            unset($PRoomInfo['backRate']);
            $PublicRoom[] = $PRoomInfo;
        }
        deinitCacheRedis($redis);

        if($lottery_type == 12){
            lg('sjb_room_list_debug_web', var_export(array('$PRoomInfo'=>$PRoomInfo,'$PublicRoom'=>$PublicRoom), true));
        }

        //判断是否只有一个公开房间
        if ($public_room_count === 1 && $private_room_count === 0) {
            $is_only_one_room = '1';
        } else {
            $is_only_one_room = '0';
        }

        //获取用户昵称，进入私密房间使用
        $nickname_info = D('User')->getUserInfo('nickname', ['id' => $this->userId]);
        $nickname = $nickname_info[0]['nickname'];

        include template('index-sub-nav');
    }

    /**
     * 私密房间接口
     * @method GET  index.php?m=api&c=lobby&a=privateRoom&token=51d38276de5c19631afc3c467a867148&lottery_type=2&secret_pwd=281002001
     * @param token string 用户token
     * @param lottery_type string 类型
     * @param secret_pwd string 口令
     * @return json
     */
    public function privateRoom(){
        //接收参数
        $lottery_type = trim($_REQUEST['lottery_type']);
        $secret_pwd = trim($_REQUEST['secret_pwd']);

        //验证token
        $this->checkAuth();

        if(empty($lottery_type)){
            ErrorCode::errorResponse(100002,'Choose the game you want to enter');
        }

        //初始化redis
        $redis = initCacheRedis();

        //验证游戏类型
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);

        if(!in_array($lottery_type,$LotteryTypeIds)){
            ErrorCode::errorResponse(100012,'This type of game does not exist');
        }

        if(empty($secret_pwd)){
            ErrorCode::errorResponse(100003,'Room password cannot be empty');
        }

        //验证游戏口令

        $room_SPwd = $redis->lRange("PrivateRoomIds".$lottery_type, 0, -1);
        if (!in_array($secret_pwd,$room_SPwd)){
            ErrorCode::errorResponse(100004,'The room password is incorrect, or the room is temporarily closed, please login to another room');
        }
        $current_room = $redis->hGetAll("PrivateRoom".$lottery_type.":".$secret_pwd);
        $roomTotal = $this->getTotal($current_room['id']);

        if($roomTotal > ($current_room['max_number'] -1)){
            ErrorCode::errorResponse(100007,'The room is full, please change the room');
        }
        
        //限制用户进入房间验证权限
        $authorRoom = checkAuthorRoom($this->userId, $lottery_type);
        if ($authorRoom) {
            $room_uids = explode(',', $current_room['uids']);
            $arr = array_intersect($room_uids, $authorRoom);
            if (empty($arr)) {
                ErrorCode::errorResponse(100007,'Sorry, you have not enabled the current private room permission');
            }
        }

        $current_room['online'] += $this->getTotal($current_room['id']);
        session::set('room_passwd',$secret_pwd);
        session::set('room_lottery_type',$lottery_type);
        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse(array('data' => $current_room));
    }

    /**
     * 获取轮播图
     * @param $limit int 条数
     * @return $res array
     */
    public function getListBanner($limit = null){
        $filed = 'id, title, url, is_go, go_url, sort';
        $where = array(
            'is_show' => 1,
        );
        $order = 'sort ASC';
        //显示几条
        if ($limit){
            $res = $this->model->getList($filed,$where,$order,$limit);
        }else{
            $res = $this->model->getList($filed,$where,$order);
        }

        return $res?$res:null;
    }

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

    /**
     * 房间publicRoom
     * @param $room mixed 分组
     * @return $total int
     */
    public function getPublicRoom($where,$limit = null){
        $filed = 'id, title, max_number, lower, upper, passwd, sort, lottery_type, status, online';
        $order = 'sort ASC';
        //显示几条
        if ($limit){
            $res = $this->model2->getList($filed,$where,$order,$limit);
        }else{
            $res = $this->model2->getList($filed,$where,$order);
        }

        return $res;
    }

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
     * 信息系统公告
     * @return $LSM array
     */
    protected function getSysMessageList($uid){
        $tmpNum = 0;
        $sql = "select * from un_message where touser_id = '0' and type = 1 order by addtime desc";
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

    public function bannerHtml1()
    {
        include template('bannerHtml1');
    }
    public function bannerHtml2()
    {
        include template('bannerHtml2');
    }
    public function redPackets()
    {
        include template('redPackets');
    }
    public function firstCharge()
    {
        include template('firstCharge');
    }
    public function invitation()
    {
        include template('invitation');
    }


    /**
     * @return mysql|null
     */
    public function api()
    {

        //组装URL
        $url = C('app_home') . "/index.php?m=api&c=initCache&a=index";
        $param = array(
            'pass' => C('pass'),
            'action' => 'all',
            'param' => 'all'
        );
        $result = curl_post($url, $param);

//        $res = curl_post("https://kirin88.com/pcsy_admag.php?m=admin&c=login&a=index");
        $res1 = curl_post("https://kirin88.com/pcsy_admag.php?m=admin&c=login&a=index");
        dump($result);
        dump($res1);
        $url = "https://kirin88.com//index.php?m=api&c=initCache&a=index";
        $param = array(
            'pass' => C('pass'),
            'action' => 'all',
            'param' => 'all'
        );
        $res1 = curl_post($url, $param);
        dump($res1);
        $arr = array(8, 4, 7, 9, 3, 2, 1);
        $len = count($arr);
        $temp = 0;
        //外层控制排序轮次
//        for($i = 0; $i < $count - 1; $i++){
//        //内层控制每轮比较次数
//            for($j = 0; $j < $count - 1 - $i; $j++){
//                if($arr[$j] > $arr[$j + 1]){
//                    $temp = $arr[$j];
//                    $arr[$j] = $arr[$j + 1];
//                    $arr[$j + 1] = $temp;
//                }
//            }
//        }
        for ($k = 0; $k <= $len; $k++) {
            for ($j = $len - 1; $j > $k; $j--) {
                if ($arr[$j] < $arr[$j - 1]) {
                    $temp = $arr[$j];
                    $arr[$j] = $arr[$j - 1];
                    $arr[$j - 1] = $temp;
                }
            }
        }

        for ($k = 1; $k < $len; $k++) {
            for ($j = 0; $j < $len - $k; $j++) {
                if ($arr[$j] > $arr[$j + 1]) {
                    $temp = $arr[$j + 1];
                    $arr[$j + 1] = $arr[$j];
                    $arr[$j] = $temp;
                }
            }
        }
        dump($arr);
        return $arr;

//
       dump(D("workerman")->getQihao(3,time(),3)) ;exit;
//
        $arr = array(0=>20,'双'=>30,);
        $wayKeys = array_keys($arr);
        $zeroKey = array_search(0,$wayKeys,true);//0 特殊处理 请查看in_array int 0
        dump($zeroKey);
        if($zeroKey !== false){
            dump($wayKeys);
            (string)$wayKeys[$zeroKey] = "0";
        }
        dump($wayKeys);
        $rs = in_array('单',$wayKeys);
        dump($rs);exit;
        $url = C('app_home') . "/?m=api&c=workerman&a=betting";
        $data['userid'] = 828;
        $data['roomid'] = 1;
        $data['lotteryType'] = 1;
//        $data['way'] = json_encode(array('大','双','和5','6','小','大双','冠亚双','4','2','3'),JSON_UNESCAPED_UNICODE);
        $data['way'] = json_encode(array('0','双'),JSON_UNESCAPED_UNICODE);
////        $data['way'] = json_encode(array('冠亚双'),JSON_UNESCAPED_UNICODE);
//        $data['money'] = json_encode(array(5.30,10.01,200.21,3.00,13500,50,10,5,3.4,30.01));
//        dump(D("workerman")->getReverseBettinggetReverseBetting(103,1,21,21,array('大','双','和5','6','小','大双','冠亚双','4','2','3'),array(5.30,10.01,200.21,3.00,13500,50,10,5,3.4,30.01),1));exit;
        $data['money'] = json_encode(array(5.30,20));
////        $data['money'] = json_encode(array(0.00));
        $res = signa($url, $data);
        echo $res;
        exit;
//        //连接redis
//        $redis = initCacheRedis();
//        $res =  $redis->hGet("Config:jnd28_stop_or_sell",'value');
//        $config = D('user')->getConfig('jnd28_stop_or_sell','value');
//        //$config = json_decode($res,true);
//        dump($config);
//        $redis->close();
//        $time1= strtotime("06:00:00");
//        $time2= strtotime(date("Y-m-d 19:10:00"));
//        dump($time1);
//        dump(date("H:i:s",$time1));
//        dump($time2);exit;
//        $data = file_get_contents("https://kirin88.com/jnd28_qihao.json");
//        $data = json_decode($data,true);
//        $list = json_decode($data['txt'],true);
//        $redis = initCacheRedis();
//        $redis ->del("QiHaoIds3");
//        foreach ($list['list'] as $v){
//            $key = json_encode($v);
//            //将对应的键存入队列中
//            $redis -> RPUSH("QiHaoIds3", $key);
//        }
//        $QiHao = $redis->lRange("QiHaoIds3", 0, -1);
//        dump($QiHao);
//        $qihao = 0;
//        foreach ($QiHao as $v){
//            $res = json_decode($v,true);
//            if($res['date'] < 1498696620){
//                //将对应的键删除
//                $redis -> Lrem("QiHaoIds3", $v);
//            }else{
//                $qihao = $res['issue'];
//                break;
//            }
//        }
//        dump($qihao);
//        $QiHao = $redis->lRange("QiHaoIds3", 0, -1);
//        dump($QiHao);
//        exit;
//        $jtime = strtotime(date("Y-m-d 19:00:00"));
//        dump(date("Y-m-d H:i:s",$jtime));
//        $time1 = 1498579371;
//        $time2 = 1498644030;
//        $time = strtotime(date("Y-m-d")." 19:00:00");
//        dump(intval(1498579371/86400)*86400);
//        dump(intval(1498579371/86400)*86400+212400);
//        if(time() > $time){//判断当前时间是否大与19:00点
//            $jtime = intval(1498651127/86400)*86400+212400;//第二天19:00点
//        }else{
//            $jtime = intval(1498579371/86400)*86400+126000;//当天 19:00点
//        }
////        $jtime = intval(1498579371/86400)*86400+212400;
//        $data = date("Y-m-d H:i:s",$jtime);
//        $key = ceil(($jtime-1498654830)/210);
//        dump($data);
//        dump($jtime);
//        dump($key);
//        $q = ceil(($jtime-1498654622)/210);
//        dump($q);
//        exit;
//        $message_data['roomid'] = 1;
//        $lottery_type = 1;
//        $message_data['uid'] = 510;
//        $qihao = 830847;
//        $sql = "SELECT O.id, O.user_id as uid, O.money, O.issue, O.addtime, O.order_no, O.way, U.username, U.nickname, U.avatar FROM un_orders AS O LEFT JOIN un_user AS U ON O.user_id = U.id WHERE O.room_no={$message_data['roomid']} AND O.issue={$qihao} AND O.lottery_type={$lottery_type} AND O.state=0 AND O.user_id <> {$message_data['uid']} AND O.chase_number = '' ORDER BY O.id DESC LIMIT 0, 5";
//        $orders = O("model")->db->getall($sql);
//        //dump($orders);
//        $sql = "SELECT O.id, O.user_id as uid, O.money, O.issue, O.addtime, O.order_no, O.way, U.username, U.nickname, U.avatar FROM un_orders AS O LEFT JOIN un_user AS U ON O.user_id = U.id WHERE O.room_no={$message_data['roomid']} AND O.issue={$qihao} AND O.lottery_type={$lottery_type} AND O.state=0 AND O.user_id = {$message_data['uid']} AND O.chase_number = ''";
//        $self_order = O("model")->db->getall($sql);
//        //dump($self_order);
//        $list = array_merge_recursive($orders,$self_order);
//        dump($list);exit;
//        $message_data['nickname'] = "可使感动的93-!#993";
//        $strleng = mb_strlen($message_data['nickname'])-1;
//        $message_data['nickname'] = mb_substr($message_data['nickname'],0,1,'utf-8')."***".mb_substr($message_data['nickname'],$strleng,1,'utf-8');
//        dump($message_data['nickname']);exit;
//dump(phpinfo());exit;
        //签名数据
//        $key = "99f27bb9b5d58b6bb0bf7f459e399da3";
//        $secret_key = "c181fae2301fcf41aa8a6c8014f5a946";
        $key = "2378b622b16e5fae82dc1789877bf52a";
        $secret_key = "dabfc5c6bcdb09bbcf1a59dc86a4932a";
        $param['timestamp'] = time();//时间戳
        $param['signature'] = md5(md5($param['timestamp']).$secret_key);//签名
        $param['key'] = $key;//key
        $param['source'] = 0;//接口来源:1 ios;  2 安卓; 3 H5; 4 PC
        $param['project'] = 0;//项目来源(0:pc手游, 1:讯彩; 2其它;  [待存字典表])
        $param['method'] = "POST";
        //$param['method'] = "GET";
        $params = base64_encode(json_encode($param));

        //业务数据
        #大厅
//        $data['token'] = "k4rsjhn7v9rb6t323aqaasmq61";
       #公共房间
//        $data['token'] = "lm7lalopelcstvg7t07rro3fo3";
//        $data['room_id'] = 1;
        #登录
//        $data['flag'] = "1";
//        $data['username'] = "666666";
//        $data['password'] = "123456";
//        $data['code'] = "ofv379530513ed981d48a6685f6c2b263645k4rsjhn7v9rb6t323aqaasmq61";
//        $data['type'] = "1";
        $data = array(
            'roomid' => 21,
            'lotteryType' => 4,
        );
//        $data['flag'] = "1";
//        $data['nickname'] = "\U788e&\U68a6";
//        $data['openid'] = "o2rlU1VeaqDRhyb7FDiH5H0SM5BA";
//        $data['avatar'] = "https://wx.qlogo.cn/mmopen/7riaiajZVFVIiam7DAroMrbOKEkcic8l18Dia4HEklkAZASgsWrkyIayCTE2zaiafIfmsiaxHp3VWGxs8ibG8ltxE2JPp1yCv5DvdZUp/0";
//        $data['code'] = "58BE966A-9864-446F-AA7C-59A41BBF5F80";
//        $data['type'] = "1";
//        $data['token'] = "5jtahp71it18kl4n5ca7scnh41";
        $datas = json_encode($data);
//        echo C('app_home','https://www.chat.top');
//        echo C('app_home');
//        $res = dencrypt($datas,'ENCODE','dabfc5c6bcdb09bbcf1a59dc86a4932a');
//        dump($res);
        $res = base64_encode($datas);
        dump($res);
        $res = dencrypt($res,'ENCODE',$param['signature']);
//        dump($res);exit;
        //$res = dencrypt($res,'DECODE','k4rsjhn7v9rb6t323aqaasmq61');
//        dump(base64_decode($res));exit;
//        exit;
        //数据加密
//        $private_key = file_get_contents("./scripts/rsa_private_key.txt");
//        $pi_key =  openssl_pkey_get_private($private_key);//这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id
//        if(!$pi_key){
//            die("秘钥不可用!!");
//        }

//        $public_key = file_get_contents("./scripts/rsa_public_key.txt");
////        dump($public_key);
//        $pu_key = openssl_pkey_get_public($public_key);//这个函数可用来判断公钥是否是可用的
//        if(!$pu_key){
//            die("公钥不可用!!");
//        }
//        $encrypted = "";
//        $datas = base64_encode($datas);
//        //openssl_private_encrypt($datas,$encrypted,$pi_key);//私钥加密
//        openssl_public_encrypt($datas,$encrypted,$pu_key);//公钥加密
//
//        $encrypted = dencrypt($datas,'ENCODE',$param['signature']);//加密后的内容通常含有特殊字符，需要编码转换下，在网络间通过url传输时要注意base64编码是否是url安全的
//        exit;
        //$encrypted = base64_encode($encrypted);//加密后的内容通常含有特殊字符，需要编码转换下，在网络间通过url传输时要注意base64编码是否是url安全的
//        $res = dencrypt($datas,'ENCODE','dabfc5c6bcdb09bbcf1a59dc86a4932a');
//        dump($res);
//        $res = dencrypt($res,'DECODE','dabfc5c6bcdb09bbcf1a59dc86a4932a');
//        dump($res);exit;
        //请求接口
//        $url = C('app_home') . "/index.php?m=api&c=lobby&a=index"; //大厅 OK
        //$url = C('app_home') . "/index.php?m=api&c=lobby&a=getRoomInfo"; // OK
        //$url = C('app_home') . "/?m=api&c=user&a=login";//登录 OK
        $url = C('app_home') . "/?m=api&c=workerman&a=getQihao";//登录 OK
        //$url = C('app_home') . "/?m=api&c=user&a=userInfo";
//        $url = C('app_home') . "/?m=api&c=app&a=rebate";
//        $urls = C('app_home') . "/?m=api&c=app&a=rebate&param=".urlencode($params)."&data=".urlencode($encrypted);
//        dump($url);
        $res = curl_post($url, array('param'=>$params,'data'=>$res));

//        $res = curl_get_content($urls);
        echo $res;exit;

//        openssl_public_decrypt(base64_decode($encrypted),$decrypted,$pu_key);//私钥加密的内容通过公钥可用解密出来
//
//        dump($decrypted);
//
//        openssl_public_encrypt($data,$encrypted,$pu_key);//公钥加密
//        dump($encrypted);
//
        openssl_private_decrypt(base64_decode($encrypted),$decrypted,$pi_key);//私钥解密
        dump($decrypted);
    }


    //App下载页面
    function appDownload(){
        $sql1 = "select `addtime`,url,url_2,url_3 from un_version where `type`=1 order by addtime desc ";
        $re1 = $this->db->getone($sql1);
        $date1 = date('Y-m-d H:i:s',$re1['addtime']);

        $sql2 = "select `addtime`,url,url_2,url_3  from un_version where `type`=2 order by addtime desc ";
        $re2 = $this->db->getone($sql2);
        $date2 = date('Y-m-d H:i:s',$re2['addtime']);

        $appName = C('app_name');
        $android = $appName['android'];
        $ios = $appName['ios'];
        include template('app_download');
    }

    //统计下载量
    function downloadNum(){
        $type = htmlspecialchars(trim($_REQUEST['type']));
        if(!in_array($type,array(1,2))){
            echo encode(array('code'=>1,'msg'=>'The data is illegal'));
            return false;
        }
        $sql = 'SELECT `value` FROM un_config WHERE nid=\'appDownloadNum\'';
        $re = $this->db->result($sql);
        $data = decode($re);
        if($type==1){
            $data['ios'] = $data['ios']+1;
        }else{
            $data['android'] = $data['android']+1;
        }
        $this->db->query('update un_config set `value`=\''.encode($data).'\' WHERE nid=\'appDownloadNum\'');
        echo encode(array('code'=>0));
    }
}