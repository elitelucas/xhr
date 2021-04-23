<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/14
 * Time: 14:03
 * desc: 初始化redis缓存数据
 */

!defined('IN_SNYNI') && die('Access Denied!');
ini_set('max_execution_time','0');

class InitCacheAction{
    public $db;
    protected $model;
    protected $model1;
    protected $model2;
    protected $model3;
    protected $model4;
    protected $model5;
    protected $model6;
    protected $model7;
    protected $model8;
    protected $model9;
    public $cache_redis;

    public function __construct()
    {
        $this->db = getconn();
        $this->model = D('config');
        $this->model1 = D('room');
        $this->model2 = D('banner');
        $this->model3 = D('sysMessage');
        $this->model4 = D('lotteryType');
        $this->model5 = D('publicRoom');
        $this->model6 = D('paymentConfig');
        $this->model7 = D('bankConfig');
        $this->model8 = D('dictionaryClass');
        $this->model9 = D('dictionary');
        $this->model10 = D('gameWay');
    }

    /**
     * @method get /index.php?m=api&c=initCache&a=index&data=un123456&data=all&param=all 更新所有
     */
    public function index(){
        $param = $_REQUEST;
        //验证用户 预留

        //验证口令
        if (!(isset($param['pass']) && $param['pass'] == C('pass'))) {
            ErrorCode::errorResponse(100001,'Refresh configuration password error');
        }

        //验证参数
        if (!(isset($param['action']) && !empty($param['action']))) {
            ErrorCode::errorResponse(100005,'Please enter the name of the data interface to be initialized');
        }
        if (!(isset($param['param']) && !empty($param['param']))) {
            ErrorCode::errorResponse(100006,'Please enter the data parameters to be initialized');
        }

        //初始化所有redis缓存记录
        $this->cache_redis = initCacheRedis();
        $flag = false;
        if ($param['action'] === 'all' && $param['param'] === 'all') {
            $flag = true;
        }

        //初始化配置
        if ($flag || $param['action'] === 'config') {
            $data = $this->getListConfig();
            $key = array(
                'hk' => 'Config:',
                'lk' => 'ConfigIds',
            );
            $this->load($data,'nid',$key,'LoadConfig');
        }

        //初始化游戏类型
        if ($flag || $param['action'] === 'lotteryType') {
            $data = $this->getLotteryType();
            $key = array(
                'hk' => 'LotteryType:',
                'lk' => 'LotteryTypeIds',
            );
            $this->load($data,'id',$key,'LoadLotteryType');
        }

        //初始化字典类型
        if ($flag || $param['action'] === 'dictionaryClass') {
            $data = $this->getDictionaryClass();
            $key = array(
                'hk' => 'DictionaryClass:',
                'lk' => 'DictionaryClassIds',
            );
            $this->load($data,'id',$key,'LoadDictionaryClass');
        }

        //初始化字典表
        if ($flag || $param['action'] === 'dictionary') {
            $TypeIds = $this->cache_redis->lRange("DictionaryClassIds", 0, -1);
            if(in_array($param['param'],$TypeIds)){
                $data = $this->getListDictionary($param['param']);
                $key = array(
                    'hk' => 'Dictionary'.$param['param'].':',
                    'lk' => 'DictionaryIds'.$param['param'],
                );
                $this->load($data,'id',$key,'LoadDictionary'.$param['param']);
            }elseif($param['param'] === 'all'){
                $res = $this->loadDictionary();
                foreach ($res as $k => $v){
                    $key = array(
                        'hk' => 'Dictionary'.$k .':',
                        'lk' => 'DictionaryIds'.$k ,
                    );
                    $this->load($v,'id',$key,'LoadDictionary'.$k );
                }
            }else{
                ErrorCode::errorResponse(100010,'The data to be initialized does not exist');
            }
        }

        //初始化游戏玩法
        if ($flag || $param['action'] === 'way') {
            $res = $this->loadGameWay();
            foreach ($res as $k => $v){
                $key = array(
                    'hk' => 'way'.$k .':',
                    'lk' => 'wayIds'.$k ,
                );
                $this->load($v,'nid',$key,'Loadway'.$k );
            }
        }

        //初始化公共房间
        if ($flag || $param['action'] === 'publicRoom') {
            $LotteryTypeIds = $this->cache_redis->lRange("LotteryTypeIds", 0, -1);
            if(in_array($param['param'],$LotteryTypeIds)){
                $data = $this->getListPRoom($param['param']);
                $key = array(
                    'hk' => 'PublicRoom'.$param['param'].':',
                    'lk' => 'PublicRoomIds'.$param['param'],
                );
                $this->load($data,'id',$key,'LoadPublicRoom'.$param['param']);
            }elseif($param['param'] === 'all'){
                $res = $this->loadPublicRoom();
                foreach ($res as $k => $v){
                    $key = array(
                        'hk' => 'PublicRoom'.$k .':',
                        'lk' => 'PublicRoomIds'.$k ,
                    );
                    $this->load($v,'id',$key,'LoadPublicRoom'.$k );
                }
            }else{
                ErrorCode::errorResponse(100010,'The data to be initialized does not exist');
            }
        }

        //初始化私密房间
        if ($flag || $param['action'] === 'room') {
            $LotteryTypeIds = $this->cache_redis->lRange("LotteryTypeIds", 0, -1);
            if(in_array($param['param'],$LotteryTypeIds)){
                $data = $this->getListSRoom($param['param']);
                $key = array(
                    'hk' => 'PrivateRoom'.$param['param'].':',
                    'lk' => 'PrivateRoomIds'.$param['param'],
                );
                $this->load($data,'passwd',$key,'LoadPrivateRoom'.$param['param']);
            }elseif($param['param'] === 'all'){
                $res = $this->loadPrivateRoom();
                foreach ($res as $k => $v){
                    $key = array(
                        'hk' => 'PrivateRoom'.$k .':',
                        'lk' => 'PrivateRoomIds'.$k ,
                    );
                    $this->load($v,'passwd',$key,'LoadPrivateRoom'.$k );
                }
            }else{
                ErrorCode::errorResponse(100010,'The data to be initialized does not exist');
            }
        }

        //初始化轮播图
        if ($flag || $param['action'] === 'banner') {
            $data = $this->getListBanner();
            $key = array(
                'hk' => 'Banner:',
                'lk' => 'BannerIds',
            );
            $this->load($data,'id',$key,'LoadBanner');
        }

        //初始公告
        if ($flag || $param['action'] === 'sysMessage') {
            $data = $this->getListSysMessage(1);
            $key = array(
                'hk' => 'SysMessage:',
                'lk' => 'SysMessageIds',
            );
            $this->load($data,'id',$key,'LoadSysMessage');
        }


        //初始公告
        if ($flag || $param['action'] === 'agentSystem') {
            $data = $this->getListSysMessage(2);
            $key = array(
                'hk' => 'AgentSystem:',
                'lk' => 'AgentSystemIds',
            );
            $this->load($data,'id',$key,'LoadAgentSystem');
        }

        //初始第三方支付信息
        if ($flag || $param['action'] === 'paymentConfig') {
            $data = $this->getListPaymentConfig();
            $key = array(
                'hk' => 'paymentConfig:',
                'lk' => 'paymentConfigIds',
            );
            $this->load($data,'nid',$key,'LoadpaymentConfig');
        }

        //初始收款账号信息
        if ($flag || $param['action'] === 'BankConfig') {
            $data = $this->getListBankConfig();
            $key = array(
                'hk' => 'BankConfig:',
                'lk' => 'BankConfigIds',
            );
            $this->load($data,'id',$key,'LoadBankConfig');
        }

        //关闭redis
        deinitCacheRedis($this->cache_redis);
    }

    /**
     * 配置
     */
    public function getListConfig(){
        $res = $this->model->getList();
        return $res;
    }

    /**
     * 游戏类型
     */
    public function getLotteryType(){
        $res = $this->model4->getList('*','');
        $date=array();
        foreach ($res as $v){
            $conf = json_decode($v['config'],true);
            unset($v['config'],$v['id']);
            $date[$v['id']] = array_merge($v,$conf);
        }
        return $date;
    }

    /**
     * 字典类型
     */
    public function getDictionaryClass(){
        $res = $this->model8->getList();
        return $res;
    }

    /**
     * 字典
     */
    private function loadDictionary(){
        $res = $this->getListDictionary();
        $TypeIds = $this->cache_redis->lRange("DictionaryClassIds", 0, -1);
        $room = array();
        foreach ($res as $v) {
            foreach ($TypeIds as $vk){
                if($vk == $v['classid']){
                    $room[$vk][] = $v;
                }
            }
        }
        return $room;
    }

    /**
     * 字典
     */
    public function getListDictionary($type = 'null'){
        if($type !== 'null'){
            $where[] = "classid = ".$type;
        }
        $where[] = "is_sys = 1";
        $res = $this->model9->getList('',$where);
        return $res;
    }


    /**
     * 游戏玩法
     */
    private function loadGameWay(){
        $res = $this->model10->getList('','','sort');
        $data = array();
        $i = $y = $h = $j = 0;
        foreach ($res as $v) {
            if ($v['sort'] > 27) {
                if ($i != 0 && $i % 5 == 0) {
                    $j++;
                }
                $data[$v['lottery_type']]['panel_1'][$j][] = array('title' => $v['way'], 'value' => $v['odds']);
                $i++;
            } else {
                if ($y != 0 && $y % 7 == 0) {
                    $h++;
                }
                $y++;
                $data[$v['lottery_type']]['panel_2'][$h][] = array('title' => $v['way'], 'value' => $v['odds']);
            }
        }
        return $data;
    }

    /**
     * 公共房间
     */
    private function loadPublicRoom(){
        //查询公共房间
        $res = $this->getListPRoom();
        $LotteryTypeIds = $this->cache_redis->lRange("LotteryTypeIds", 0, -1);
        $room = array();
        foreach ($res as $v) {
            foreach ($LotteryTypeIds as $vk){
                if($vk == $v['lottery_type']){
                    $room[$vk][] = $v;
                }
            }
        }
        return $room;
    }

    /**
     * 公共房间
     */
    public function getListPRoom($type = 'null'){
        if($type !== 'null'){
            $where[] = "lottery_type = ".$type;
        }
        $where[] = "status = 0";
        $order = 'sort ASC';
        $res = $this->model5->getList('',$where,$order);
        return $res;
    }

    /**
     * 私密房间
     */
    private function loadPrivateRoom(){
        //查询游私密房间
        $res = $this->getListSRoom();
        $LotteryTypeIds = $this->cache_redis->lRange("LotteryTypeIds", 0, -1);
        $room = array();
        foreach ($res as $v) {
            foreach ($LotteryTypeIds as $vk){
                if($vk == $v['lottery_type']){
                    $room[$vk][] = $v;
                }
            }
        }
        return $room;
    }

    /**
     * 私密房间
     */
    public function getListSRoom($type = 'null'){
        if($type !== 'null'){
            $where[] = "lottery_type = ".$type;
        }
        $where[] = "status = 0";
        $order = 'sort ASC';
        $res = $this->model1->getList('',$where,$order);
        return $res;
    }

    /**
     * 获取轮播图
     * @return $res array
     */
    public function getListBanner(){
        $where = array(
            'is_show' => 1,
        );
        $order = 'sort ASC';
        $res = $this->model2->getList("*",$where,$order);
        return $res;
    }

    /**
     * 获取公告
     * @return $res array
     */
    public function getListSysMessage($type=1){
        $where = array(
            'type' => $type,
            'status' => 0
        );
        $order = 'sort ASC, addtime DESC';
        $res = $this->model3->getList("*",$where,$order);
        return $res;
    }

    /**
     * 获取收款账号信息
     * @return $res array
     */
    public function getListpaymentConfig(){
        $where = array(
            'status' => 0,
        );
        $res = $this->model6->getList("*",$where);
        return $res;
    }

    /**
     * 获取收款账号信息
     * @return $res array
     */
    public function getListBankConfig(){
        $where = array(
            'state' => 1,
        );
        $res = $this->model7->getList("*",$where);
        return $res;
    }

    /**
     * 初始化数据
     */
    private function load($data,$index,$key,$name) {
        //清除缓存
        $this->clear(array("{$key['hk']}*", $key['lk']));
        //将查询数据写入redis
        foreach ($data as $v) {
            $id = $v[$index];
            //将对应的键存入队列中
            $this->cache_redis->rPush($key['lk'], $id);
            //将数据写入redis哈希表
            $this->cache_redis->hMset($key['hk'].$id, $v);
        }

        //将更新后的数据显示页面
        $hkeys = count($this->cache_redis->keys("{$key['hk']}*"));
        $listkeys = count($this->cache_redis->lRange($key['lk'], 0, -1));

        echo "{$name} {HK: {$hkeys}, LK: {$listkeys}} data success...<br>";
    }

    /**
     * 清楚redis 缓存
     */
    private  function clear($regex) {
        foreach ($regex as $re) {
            $keys = $this->cache_redis->keys($re);
            foreach ($keys as $key) {
                $this->cache_redis->del($key);
            }
        }
    }
}