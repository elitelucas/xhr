<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/11
 * Time: 9:43
 * desc: APP 首页接口 私密房间接口 部分数据待存入redis
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

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
        //验证token
        $resss = $this->model1->isIpBlack($_REQUEST['code'],$_REQUEST['m'],$_REQUEST['c'],$_REQUEST['a']);
        if($resss == false) {
            ErrorCode::errorResponse(ErrorCode::DEFAULT_MSG,"Sorry! You don't have enough permissions");
        }
        if(isset($_REQUEST['token'])&& trim($_REQUEST['token'])){
            $this->checkAuth();
            //用户消息
            $uid = $this->userId;
            $where[] = 'touser_id = '.$uid;
            $where[] = 'state = 0';
            $userMes = $this->getMessage($uid);//用户消息
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
        }else{
            //系统消息
            $sysMes = $this->getSysMessageList(0);
            $msg_num = $sysMes['num'];
            $money_usable = '0.00';
        }

        //获取配置信息
        $config_nid = array(
            0 => '100001', //已为用户赚取元宝总数
            1 => '100002', //回扣返水赚钱率
            2 => '100003', //注册用户总数
            3 => '100004', //在线用户总数 
        );
        $config = array();
        $redis = initCacheRedis();
        
        //app配置信息
        $arrConfig= $redis -> HMGet("Config:appConfig",array('nid','name','value'));
        $appConfig = json_decode($arrConfig['value'], true);
        
        foreach ($config_nid as $v){
            $GameConfig= $redis -> HMGet("Config:".$v,array('nid','name','value'));
            $config[$GameConfig['nid']] = $GameConfig['value'];
        }

        //banner 轮播图
        //$banner = $this->getListBanner();
        $LBanner = $redis->lRange('BannerIds', 0, -1);
        foreach ($LBanner as $v){
            $banner[] = $redis -> hGetAll("Banner:".$v);
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
            'sysMessage' => $sysMes['list'],//系统公告
            'banner' => $banner,//轮播图
            'app_config' => $appConfig,
            'list' => array(
                'total' => $total,//在线总人数
                'msg_num' => $msg_num,//新消息
                'reg_num' => $reg_num,//已注册人数
                'profit' => $profit,//收益
                'rate' => $rate,//收益
            ),
            'rlist' => $proom,
            'money_usable' => $money_usable
        );

        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($reData);
    }

    /**
     *彩种类型
     **/

    public function lotteryType(){
        $redis = initCacheRedis();
        $lotteryType = $redis ->lRange("LotteryTypeIds",0,-1);
        $lotteryList = [];
//        foreach ($lotteryType as $k => $v) {
//            $lotteryList[] =  ['id'=>$v,'name'=>$redis->hGetAll("LotteryType:".$v)['name']];
//        }

        $index_lottery_list = $redis -> HMGet('Config:index_lottery_list',['value']);

        $list = json_decode($index_lottery_list['value'], true);
        foreach ($list as $k => $v) {
            if ($v['is_show'] == 1 && $v['lottery_type'] != 12) {
                $lotteryList[] = ['id'=> $v['lottery_type'],'name'=> $v['lottery_name']];
            }
            unset($list[$k]);
        }

        deinitCacheRedis($redis);
        ErrorCode::successResponse(['data'=>$lotteryList]);
    }

    public function reidis() {
        $config_nid = array(
            0 => 'Config:100001', //已为用户赚取元宝总数
            1 => 'Config:100002', //回扣返水赚钱率
            2 => 'Config:100003', //注册用户总数
        );
        $redis = initCacheRedis();
        foreach ($config_nid as $v) {
            $redisData = $redis->HMGet($v,array('nid','name','value'));
            $oldData[$redisData['nid']] = $redisData;
            unset($redisData);
        }
        $scale = rand(92,99);
        $yb = rand(4000,20000);
        $regNum = rand(5,20);
        $oldData['100001']['value'] = $oldData['100001']['value']+$yb;
        $oldData['100002']['value'] = $scale;
        $oldData['100003']['value'] = $oldData['100003']['value']+$regNum;
        dump($oldData);
        foreach ($oldData as $k => $v) {
            $sql = "update un_config set value = {$v['value']} where nid = {$v['nid']} ";
            $a = $this->db->query($sql);
            $redis->hMset("Config:".$v['nid'],$v);
        }
        dump($redis->HMGet('Config:100001',array('nid','name','value')));
        deinitCacheRedis($redis);

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
        //验证参数
        $this->checkInput($_REQUEST, array('token','lottery_type','secret_pwd'));
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

//        $user_temp=$this->db->getone('select user_id from un_session where sessionid="'.$_REQUEST['token'].'"');
//        if($user_temp){
//            $user_id=$user_temp['user_id'];
//            $isZhuiHao = $this->db->getone("select count(*) as num from un_orders where room_no={$current_room['id']} and lottery_type={$lottery_type} and award_state = 0 and state = 0 and user_id=$user_id and chase_number !=''");
//            $current_room['isZhuiHao']=$isZhuiHao['num'];
//        }else{
//            $current_room['isZhuiHao']=0;
//        }
        $current_room['isZhuiHao'] = 1;

        //彩种标题
        $lottery_title = $redis->hGet("LotteryType:{$lottery_type}",'name');
        $current_room['lottery_title'] = $lottery_title;

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

        //$sql = "select * from un_message where touser_id = '0' and type = 1 order by addtime desc";
        //$ids = $this->db->getall($sql);
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
     * 获取对应房间的信息
     */
    public function getRoomInfo() {
        //验证参数
        $this->checkInput($_REQUEST, array('token','room_id'));
        $room_id = trim($_REQUEST['room_id']);

        //验证token
        $this->checkAuth();

        //初始化redis
        $redis = initCacheRedis();
        $roomInfo = $redis->hGetAll('allroom:'.$room_id);

        if(empty($roomInfo)){
            ErrorCode::errorResponse(1,'This room has been closed!!!');
        }

        //关闭redis链接
        deinitCacheRedis($redis);

        //用户可用余额
        $res = D('account')->getOneCoupon( 'money', array('user_id' => $this->userId));
        $money_usable = $this->convert($res['money']);
        //$isZhuiHao = $this->db->getone("select count(*) as unm from un_orders where room_no=$room_id and lottery_type={$roomInfo['lottery_type']} and award_state = 0 and state = 0 and user_id='".$this->userId."' and chase_number !=''");
        ErrorCode::successResponse(array(
            'title' => $roomInfo['title'],
            'max_number' => $roomInfo['max_number'],
            'max_yb' => $roomInfo['max_yb'],
            'low_yb' => $roomInfo['low_yb'],
            'online' => $roomInfo['online'],
            'lack_tips' => $roomInfo['lack_tips'],
            'state' => $roomInfo['status'],
            'money_usable' => $money_usable,
            'isZhuiHao'=> 1
        ));
    }

    public function getMessageNum()
    {
        $this->checkAuth();
        $uid = $this->userId;
        $userMes = $this->getMessage($uid);//用户消息
        $sysMes = $this->getSysMessageList($uid); //系统消息
        $msg_num = $userMes['num'] + $sysMes['num'];
        $arr['msg_num'] = $msg_num;
        $arr['status'] = 0;
        $arr['ret_msg'] = "";
        ErrorCode::successResponse($arr);
    }

    /**
     * @return mixed
     */
    public function getRealUserTotal()
    {
        $pass = $_REQUEST['s'];
        if ($pass != 'a8fce04d58c1f06f30da6d33c7523abc') return false;
        O('Gateway');
        Gateway::$registerAddress = C('Gateway');
        $total =  Gateway::getAllClientRealMan(); //改写了一个方法来统计在线真人
        echo $total;
        return 1;
    }

    /**
     * @return mixed
     */
    public function getUserTotal()
    {
        $pass = $_REQUEST['s'];
        if ($pass != 'a8fce04d58c1f06f30da6d33c7523abc') return;
        echo $this->getTotal();
    }
}