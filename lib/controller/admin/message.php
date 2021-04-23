<?php

/**
 * @copyright			(C) 2013 CHENGHUITONG.COM
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'pages.php';

class MessageAction extends Action {

    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = D('admin/message');
        $this->return = array(
            'code' => 0,
            'msg' => '',
            'data' => '',
            'pageshow' => ''
        );
    }

    //敏感词列表
    public function words() {
        try {
            $list = $this->model->listWords();
            $list['value'] = explode(",", $list['value']);
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
        include template('list-words');
    }

    //新增敏感词跳转
    public function addWords() {
        include template('add-words');
    }

    //添加敏感词
    public function doAddWords() {
        $value = $_POST['value'];

        if (strlen($value) > 100) {
            echo json_encode(['rt' => -1, 'msg' => '输入的字符数过多']);
            return;
        }

        if (strstr("'", $value)) {
            echo json_encode(['rt' => -1, 'msg' => "输入的字符不能有：'（英文单引号）"]);
            return;
        }

        //$vlue = addslashes($value);

        $rt = $this->model->addWords($value);
        $this->refreshRedis("config", "all");

        echo json_encode(array('rt' => $rt));
    }

    //删除关键字
    public function delWords() {
        $keyWord = $_POST['key'];
        $rt = $this->model->delWords($keyWord);
        if ($rt === false) {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        } else {
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        }
        $this->refreshRedis("config", "all");
        echo json_encode($arr);
    }

    //活动列表
    public function activity() {
        try {
            $list = $this->model->listActivity();
            $value = json_decode($list['value'], true);
            if (!empty($value)) {
                foreach ($value as $k => $v) {
                    list($value[$k]['s_time'], $value[$k]['e_time']) = explode("|", $v['time']);
                    $value[$k]['addtime'] = date('Y-m-d H:i:s', $v['addtime']);
                }
            }
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
        include template('list-activity');
    }

    //活动置顶
    public function topActivity() {
        $i = $_REQUEST['i'];
        $list = $this->model->listActivity();
        $array = json_decode($list['value'], true);

        foreach ($array as $key => $value) {
            if ($key != $i) {
                $array[$key]['status'] = 0;
            } else {
                $array[$key]['status'] = 1;
            }
        }

        $rt = $this->model->upActivity(addslashes(json_encode($array)));
        if ($rt !== false) {
            $this->refreshRedis("config", "all");
        }
        echo json_encode(array("rt" => $rt));
    }

    //新增活动跳转
    public function addActivity() {
        $list = $this->db->getone("select start_time,end_time from un_ttfl_cfg where nid = '100005' and main = 1");
        $list['start_time'] = date("Y-m-d",$list['start_time']);
        $list['end_time'] = date("Y-m-d",$list['end_time']);
        include template('add-activity');
    }

    //新增活动
    public function doAddActivity() {
        $rt = $this->model->addActivity(array(
            'content' => $_POST['contents'],
            'time' => $_POST['s_time'] . "|" . $_POST['e_time'],
            'short' => $_POST['short'],
            'title' => $_POST['title'],
            'status' => $_POST['status'],
            'addtime' => time(),
            'oper' => $this->admin['username']
        ));
        if ($rt !== false) {
            $this->refreshRedis("config", "all");
        }
        echo json_encode(array('rt' => $rt));
    }

    //删除活动
    public function delActivity() {
        $rt = $this->model->delActivity(array(
            'i' => $_POST['i']
        ));
        $this->refreshRedis("config", "all");
        echo json_encode(array('rt' => $rt));
    }

    //房间限额设置
    public function oddsRecord()
    {
        $roomId = $_REQUEST['id'];
        $bettingData = $this->model->roomBettingInfo($roomId);
        include template('general_note');
    }

    //房间限额设置修改
    public function doOddsRecord()
    {
        $data = $_REQUEST;


        $room_id = $data['room_id'];
        unset($data['m']);
        unset($data['c']);
        unset($data['a']);
        unset($data['room_id']);
        $sql = "SELECT upper,lottery_type FROM `un_room` WHERE `id` = '{$room_id}'";
        $res = $this->db->getone($sql);
        if(empty($res['upper'])){
            $res['upper'] = $this->getRoomWayLimit($res['lottery_type']);
        }

        $upper = json_decode($res['upper'], true);

        if ((!empty($_REQUEST['total_amount']) || $_REQUEST['total_amount']==0) && is_numeric($_REQUEST['total_amount'])){
            $upper['total_amount'] = $_REQUEST['total_amount'];
        }else{
            foreach ($data as $k => $v) {
                $arr_key = explode('_', $k);
                if (count($arr_key) < 4 || !is_numeric($v)) {
                    echo json_encode(array("rt" => 0));
                    return;
                }else {
                    $upper['limit'][$arr_key[1]]['data'][$arr_key[3]] = $v;
                }
            }
        }

        $uppers = json_encode($upper,JSON_UNESCAPED_UNICODE);
        $ret = $this->db->update('un_room',array("upper" => $uppers),array("id" => $room_id));

        $this->refreshRedis("room", "all");
        $this->refreshRedis("publicRoom", "all");
        $this->refreshRedis("allroom", "all");

        echo json_encode(array("rt" => $ret));
    }

    /*
     //房间限额设置修改
    public function doOddsRecord() {
        $data = $_REQUEST;
        $sql = "SELECT upper,lottery_type FROM `un_room` WHERE `id` = '{$_REQUEST['id']}'";
        $res = $this->db->getone($sql);
        if(empty($res['upper'])){
            $res['upper'] = $this->getRoomWayLimit($res['lottery_type']);
        }
        $upper = json_decode($res['upper'], true);
        $upper['total_amount'] = $_REQUEST['total_amount'];
        foreach ($_REQUEST['data'] as $k => $v) {
            $upper['limit'][$k]['data'] = $v;
        }
        $uppers = json_encode($upper,JSON_UNESCAPED_UNICODE);
        $this->db->update('un_room',array("upper" => $uppers),array("id" => $_REQUEST['id']));
//        dump($this->db->getLastSql());
//        dump($this->db->_sql());
        $this->refreshRedis("room", "all");
        $this->refreshRedis("publicRoom", "all");
        $this->refreshRedis("allroom", "all");
        echo json_encode(array("rt" => $res));
    }
     */

    //逆向投注开关
    public function reverse() {
        $id = $_REQUEST['id'];
        $rt = $this->model->reverse($id);
        $list = json_decode($rt['reverse'], true);

        $this->refreshRedis("allroom","all");
        include template('list-reverse');
    }

    //逆向投注设置
    public function upReverse() {
        $state = $_REQUEST['state'];
        $type = $_REQUEST['type'];
        $id = $_REQUEST['id'];

        $rt = $this->model->reverse($id);
        if (empty($rt['reverse'])) {
            $rt['reverse'] = $this->getReverse($rt['lottery_type']);
        }
        $json = json_decode($rt['reverse'], true);
        $json[$type]['state'] = $state;
        $res = $this->model->upReverse(array("reverse" => json_encode($json,JSON_UNESCAPED_UNICODE)), array("id" => $id));
        $this->refreshRedis("room", "all");
        $this->refreshRedis("publicRoom", "all");
        $this->refreshRedis("allroom", "all");
        echo json_encode(array('rt' => $res));
    }

    //获取彩种id与彩种名称对应的数据map关联数组
    public function getLotteryArray()
    {
        $lottyArray = array();
        $lottyList = $this->model->lottyList();
        foreach ($lottyList as $value) {
            $lottyArray[$value['id']] = $value['name'];
        }
        return $lottyArray;
    }

    //房间列表
    public function room()
    {
        $lottery_id = $_REQUEST['lottery_id'];
        $room_name  = $_REQUEST['room_name'];
        $lottyArray = array();

        if (empty($lottery_id) || !is_numeric($lottery_id)) {
            $lottery_id = 1;
        }

        $lottyList = $this->model->lottyList();
        foreach ($lottyList as $key => $value) {
            $lottyArray[$value['id']] = $value['name'];
        }

        $where['lottery_id'] = $lottery_id;
        $where['room_name']  = $room_name;
        $list = $this->model->room($where);
        foreach ($list as $key => $value) {
            $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
            $list[$key]['status'] = $value['status'] == 0 ? "开启" : "关闭";
            $list[$key]['lottery_type_text'] = $lottyArray[$value['lottery_type']];
            if ($value['lottery_type'] == 12) {
                $arr = $this->db->getone("select event_name,match_date,team_1_name,team_2_name from #@_cup_against where match_id = {$value['match_id']}");
                $list[$key]['event_name'] = $arr['event_name'];
                $list[$key]['team_name'] = $arr['team_1_name']." VS ".$arr['team_2_name'];
                $list[$key]['time'] = date("Y-m-d H:i:s",$arr['match_date']);
            }

        }
        include template('list-room');
    }

    //新增房间跳转
    public function addRoom() {
        $lottyList = $this->model->lottyList();
        $cup_info = $this->db->getall("select * from #@_cup_against where match_state <> 2");
        foreach ($cup_info as $key=>$val) {
            $cup_info[$key]['match_date'] = date("Y-m-d H:i:s",$val['match_date']);
        }
        include template('add-room');
    }

    //新增私密房间跳转
    public function addRooms() {
        $lottyList = $this->model->lottyList();
        foreach ($lottyList as $key => $val) {
            if ($val['id'] == 12) {
                unset($lottyList[$key]);
            }
        }
        include template('add-rooms');
    }

    //新增or修改房间
    public function doAddRoom()
    {
        $postKeyArr = [
            'title' => '房间名称',
            'max_number' => '满员人数',
            'lower' => '最低投注',
            'low_yb' => '最低元宝',
            'max_yb' => '最高元宝',
            'shove_time' => '限时踢人',
            'closure_time' => '封盘线时间',
            'online' => '虚拟人数',
            'lack_tips' => '余额不足提示',
            'odds_exp' => '赔率说明',
        ];
        $roomuids = '';
        $avatar = '';
        $pc_avatar = '';
        $uids = $_REQUEST['uids'];
        $lottery_id = $_REQUEST['lottery_type'];

        $getLotteryTypeSql = "SELECT id,`name` FROM un_lottery_type";
        $lottery_type_arr = $this->db->getall($getLotteryTypeSql);
        $lottery_type_arr = array_column($lottery_type_arr, 'name', 'id');
        //已经存在的房间会初始化限额BUG
        $log_str = '彩种:'.$lottery_type_arr[$lottery_id].'--';
        if (empty($_POST['id'])) {
            if(empty($_POST['lottery_type'])){
                echo encode(array('rt' => -2));
                return false;
            }
            $upper=$this->getRoomWayLimit($_POST['lottery_type']);

            $log_str .= '新增房间--';
            foreach ($postKeyArr as $k=>$v) {
                if(isset($_REQUEST[$k]) && $_REQUEST[$k])
                    $log_str .= $v.':'.$_REQUEST[$k].'-';
            }
        }else{
            $sql="select title,max_number,lower,low_yb,max_yb,shove_time,closure_time,online,lack_tips,odds_exp,upper,uids from un_room where id={$_POST['id']}";
            $upperArr=$this->db->getone($sql);
            $roomuids = $upperArr['uids'];
            $upper=$upperArr['upper'];

            $log_str .= '更新房间--';
            foreach ($postKeyArr as $k=>$v) {
                if(isset($_REQUEST[$k]) && $_REQUEST[$k] && ($_REQUEST[$k] != $upperArr[$k]))
                    $log_str .= $v.':'.$upperArr[$k].'=>'.$_REQUEST[$k].'-';
            }
        }

        //彩种密码重复判断
        if (!empty($_POST['passwd'])) {
            if (!empty($_POST['id'])) {
                $sql = "select id from un_room where passwd = '{$_POST['passwd']}' and lottery_type = {$_POST['lottery_type']} and id <> {$_POST['id']}";
            }else {
                $sql = "select id from un_room where passwd = '{$_POST['passwd']}' and lottery_type = {$_POST['lottery_type']}";
            }
            $arrRoomId = $this->db->getone($sql);
            if (!empty($arrRoomId)) {
                echo json_encode(array('rt' => 0, 'msg' => '该彩种下私密房间密码重复，请输入其他私密密码！'));
                return;
            }
        }

        if ($_FILES['avatar']['size'] > 600 * 1024) { // 图片大于600KB
            echo json_encode(array('rt' => -3, 'msg' => '移动端房间图片大小超过了600KB，上传失败'));
            return;
        }
        if ($_FILES['pc_avatar']['size'] > 600 * 1024) { // 图片大于600KB
            echo json_encode(array('rt' => -3, 'msg' => '电脑端房间图片大小超过了600KB，上传失败'));
            return;
        }
        if ($_FILES['pc_logo']['size'] > 600 * 1024) { // 图片大于600KB
            echo json_encode(array('rt' => -3, 'msg' => '电脑端（PC）房间Logo大小超过了600KB，上传失败'));
            return;
        }

        //上传房间图片  返回地址
        if ($_FILES['avatar']['name'] != "") $avatar = $this->model->addFiles('avatar');

        if ($_FILES['pc_avatar']['name'] != "") $pc_avatar = $this->model->addFiles('pc_avatar');

        if ($avatar === false || $pc_avatar === false) {
            echo json_encode(array('rt' => -1));
        } else {
            $data = array(
                'title' => $_POST['title'],
                'max_number' => $_POST['max_number'],
                'passwd' => $_POST['passwd'],
                'upper' => $upper,
                'lower' => $_POST['lower'],
                'low_yb' => $_POST['low_yb'],
                'max_yb' => $_POST['max_yb'],
                'lottery_type' => $_POST['lottery_type'],
                'status' => $_POST['status'],
                'online' => $_POST['online'],
                'closure_time' => $_POST['closure_time'],
                'shove_time' => $_POST['shove_time'],
                'odds_exp' => $_POST['odds_exp'],
                'greet'=>$_POST['greet'],
//                'odds_cof' => $_POST['odds_cof'],
                'lack_tips' => $_POST['lack_tips'],
                'reverse' => $this->getReverse($_POST['lottery_type']),
                'backRate' => json_encode(array()),
                'addtime' => time(),
                'match_id' => $_POST['match_id'],
                'video_address' => $_POST['video_address']
            );

            //世界杯房间图片固定不能修改
            if ($_POST['lottery_type'] != 12) {
                if (!empty($avatar)) {
                    $data['avatar'] = $avatar;
                }
                if (!empty($pc_avatar)) {
                    $data['pc_avatar'] = $pc_avatar;
                }
            }
            
            //用户限制房间的修改
            if (!empty($roomuids)) {
                $arruids = explode(',', $roomuids);
                foreach ($arruids as $auid) {
                    if (!empty($uids) && in_array($auid, $uids)) continue;

                    $sql = "SELECT `id`, `uids` FROM `un_room` WHERE `lottery_type` = " . $lottery_id . " AND FIND_IN_SET(" . $auid . ",uids)";
                    $roomIds = $this->db->getall($sql);

                    if (empty($roomIds) || count($roomIds) > 1) continue;

                    $sql = "SELECT `user_id`, `lottery_ids` FROM `un_user_tree` WHERE `user_id` = " . $auid;
                    $treeData = $this->db->getone($sql);

                    if (empty($treeData) || empty($treeData['lottery_ids'])) continue;

                    $treeData['lottery_ids'] = explode(',', $treeData['lottery_ids']);

                    if (in_array($lottery_id, $treeData['lottery_ids'])) {

                        $treeLotterIds = $treeData['lottery_ids'];

                        $tkey = array_search($lottery_id, $treeLotterIds);

                        unset($treeLotterIds[$tkey]);

                        $strTreeLotterIds = '';
                        if (!empty($treeLotterIds)) {
                            $strTreeLotterIds = implode($treeLotterIds, ',');
                        }

                        $sql = "UPDATE un_user_tree SET lottery_ids = '" . $strTreeLotterIds . "' WHERE user_id = " . $auid ;
                        $this->db->exec($sql);
                    }
                }
            }

            if ($data['lottery_type'] == 12) {
                if (empty($data['match_id'])) {
                    echo json_encode(array('rt' => -3, 'msg' => '请选择一场赛事'));
                    return;
                }

                if (empty($_POST['id'])) {
                    $result = $this->db->result("select count(id) as count from #@_room where lottery_type = 12 and status = 0");
                    if ($data['status'] == 0) {
                        if ($result >= 8) {
                            echo json_encode(array('rt' => -3, 'msg' => '当前已开启8个房间，请关闭后重新添加'));
                            return;
                        }
                    }

                } else {
                    $result = $this->db->result("select count(id) as count from #@_room where lottery_type = 12 and status = 0 and id != '{$_POST['id']}'");
                    if ($data['status'] == 0) {
                        if ($result+1 > 8) {
                            echo json_encode(array('rt' => -3, 'msg' => '当前已开启8个房间，请关闭后重新添加'));
                            return;
                        }
                    }


                }

            }

            if (!empty($uids)) {
                $struids = implode($uids, ',');
                $data['uids'] = $struids;
                $sql = "UPDATE un_user_tree SET lottery_ids = CONCAT(lottery_ids,IF(FIND_IN_SET(" . $lottery_id . ",lottery_ids), '', '," . $lottery_id . "')) 
                WHERE user_id in (" . $struids . ")";

                $this->db->exec($sql);
            } else {
                $data['uids'] = '';
            }

            if (empty($_POST['id'])) {
                if ($data['lottery_type'] != 12) {
                    $oddsRt = $this->db->getone("select value from #@_config where nid='specialWay'");
                    $oddsList = json_decode($oddsRt['value'], true);
                    $list = $oddsList[$data['lottery_type']];
                    $data['special_way']=json_encode(['status'=>0,'list'=>$list],JSON_UNESCAPED_UNICODE);
                }
                $rt = $this->model->addRoom($data);
                if($rt){
                    if ($data['lottery_type'] != 12) {
                        $sql = "SELECT lottery_type,way,odds,sort,type FROM `un_odds_copy` WHERE `lottery_type` = {$_POST['lottery_type']}";
                        $res = O("model")->db->query($sql);
                        foreach ($res as $v){
                            $datas = array();
                            $datas = $v;
                            $datas['room'] = $rt;
                            O("model")->db->insert("un_odds",$datas);
                        }
                    } else {
//                        $url = "http://13.113.45.117/index.php?s=Api/Index/receivePushData"; //正式环境
                        $url = "http://61.244.162.83:8080/index.php?s=Api/Index/receivePushData"; //测试环境
                        $post['match_id_list'] = [$data['match_id']];
                        $post['type'] = 1;
                        curl_post_content($url,encode($post));
                    }
                    $config = $this->getMessageConfig($_POST['lottery_type'],$rt);
                    foreach ($config as $v){
                        $this->db->query($v);
                    }
                }
            } else {
                if ($_FILES['avatar']['name'] == "") {
                    unset($data['avatar']);
                }
                //如果是修改房间信息，则不能覆盖原有 backRate 字段的值（返水率）
                unset($data['backRate']);

                $this->model->updateRoom($data, array('id' => $_POST['id']));
//                $sql = "SELECT lottery_type,way,odds,sort,type FROM `un_odds_copy` WHERE `lottery_type` = {$_POST['lottery_type']}";
//                $res = O("model")->db->query($sql);
//                foreach ($res as $v){
//                    $datas = array();
//                    $datas = $v;
//                    $datas['room'] = $_POST['id'];
//                    O("model")->db->insert("un_odds",$datas);
//                }
                $rt = 1;
            }

            admin_operation_log($this->admin['userid'], 80, $log_str);
            //这里刷新redis会导致Nginx重置，改成手工刷新redis
//            $this->refreshRedis("all", "all");
//            $this->refreshRedis("room", "all");
//            $this->refreshRedis("publicRoom", "all");
//            $this->refreshRedis("allroom", "all");
            echo json_encode(array('rt' => $rt));
        }
    }

    //删除房间
    public function delRoom() {

        $getLotteryTypeSql = "SELECT id,`name` FROM un_lottery_type";
        $lottery_type_arr = $this->db->getall($getLotteryTypeSql);
        $lottery_type_arr = array_column($lottery_type_arr, 'name', 'id');

        $sql="select title,lottery_type from un_room where id={$_POST['id']}";
        $roomInfo=$this->db->getone($sql);
        $log_remark = "删除房间--彩种:".$lottery_type_arr[$roomInfo['lottery_type']].'--房间名:'.$roomInfo['title'];

        $rt = $this->model->delRoom(array("id" => $_POST['id']));
        $redis = initCacheRedis();
        $lottery_type = $redis->hGet("allroom:{$_POST['id']}", 'lottery_type');
        deinitCacheRedis($redis);
        //这里刷新redis会导致Nginx重置，改成手工刷新redis by joinsen
//        if($rt){
//            if ($lottery_type != 12) {
//                $rt = O('model')->db->delete("un_odds",array("room" => $_POST['id']));
//                $this->db->delete("#@_message_conf", array('room_id'=>$_POST['id']));
//                $this->refreshRedis("way", "all");
//                $this->refreshRedis("room", "all");
//
//            }else{
//                //PublicRoom12:259
//                $this->refreshRedis("fb_odds", "all");
//                $this->refreshRedis("fb_against", "all");
//            }
//
//            $this->refreshRedis("publicRoom", "all");
//            $this->refreshRedis("allroom", "all");
//        }

        admin_operation_log($this->admin['userid'], 80, $log_remark);
        echo json_encode(array("rt" => $rt));
    }

    //发布信息跳转
    public function issueRecord() {
        $issueList = $this->model->issueList(array("id" => $_REQUEST['id']));
        $roomName = $_REQUEST['room'];
        $roomId = $_REQUEST['id'];
        $lottery_type = $_REQUEST['lottery_type'];
        include template('list-issue');
    }

    //发布信息状态修改
    public function upIssueState() {
        $id = $_REQUEST['id'];
        $state = $_REQUEST['state'];
        $roomId = $_REQUEST['roomId'];

        $this->model->upIssueState(array('state' => $state), array('id' => $id));
        $roomInfo = $this->model->roomInfo(array("id" => $roomId));

        $issueList = $this->model->issueList(array("id" => $roomId));
        //刷新redis缓存 Alan 2017-06-20
        $this->refreshRedis('messageconfig', 'all');
        $roomName = $roomInfo['title'];
        include template('list-issue');
    }

    //发布信息修改跳转
    public function updateIssue() {
        $id = $_REQUEST['id'];
        $roomId = $_REQUEST['roomId'];
        $lottery_type = $_REQUEST['lottery_type'];
        $issueInfo = $this->model->issueInfo(array("id" => $id));
//        $issueInfo['release_time'] = date("Y-m-d H:i:s",$issueInfo['release_time']);
        $roomInfo = $this->model->roomInfo(array("id" => $roomId));
        include template('add-issue');
    }

    //房间排序跳转
    public function sortRoom() {
        $roomList = $this->model->room(array());

        //拼接彩种标题字符串到房间标题前面
        $redis = initCacheRedis();

        foreach ($roomList as &$each_info) {
            $lottery_title = $redis->hGet("LotteryType:{$each_info['lottery_type']}",'name');
            $each_info['title'] = $lottery_title . $each_info['title'];
        }

        //关闭redis链接
        deinitCacheRedis($redis);

        include template('sort-room');
    }

    //房间排序
    public function doSortRoom() {
        $data = $_REQUEST['data'];
        $data = substr($data, 0, strlen($data) - 1);
        $list = explode("|", $data);
        foreach ($list as $value) {
            list($id, $sort) = explode("-", $value);
            $this->model->sortRoom($id, $sort);
        }
        $this->refreshRedis("all", "all");
        echo json_encode(array('rt' => 1));
    }

    //发布信息修改
    public function doAddIssue() {

        //彩种id
        $lottery_type = $_REQUEST['lottery_type'];
        $lottyArray = $this->getLotteryArray();
        $data = [
            'title' => $_POST['title'],
            'release_time' => $lottery_type == 12 ? strtotime($_POST['release_time']) : $_POST['release_time'],
            'content' => addslashes($_POST['contents']),
            'state' => 0,
            'audit_status' => 0
        ];

        $rt = $this->model->addIssue(array('id' => $_REQUEST['id']), $data);

        //审核记录
        $this->model->addIssueAudit(array(
            "shenqingid" => $_REQUEST['id'],
            "sqtime" => time(),
            "leibieid" => 33,
            "neirong" => "{$lottyArray[$lottery_type]} {$_REQUEST['roomname']} {$_POST['title']} 信息修改",
            "faqiren" => $this->admin['username']
        ));

        $this->refreshRedis("messageconfig", "all");
        echo json_encode(array('rt' => $rt));
    }

    //修改房间跳转
    public function updateRoom() {
        $userData = [];

        $lottyList = $this->model->lottyList();
        $roomInfo = $this->model->roomInfo(array("id" => $_REQUEST['id']));
        if ($roomInfo['lottery_type'] == 12) {
            $list = $this->db->getone("select event_name,match_date,team_1_name,team_2_name from #@_cup_against where match_id = {$roomInfo['match_id']}");
            $list['match_date'] = date("Y-m-d H:i:s",$list['match_date']);
        }

        if (!empty($roomInfo['uids'])) {
            $userData = $this->model->getUserInfo($roomInfo['uids']);
        }

        if ($roomInfo['passwd'] != "") {
            include template('add-rooms');
        } else {
            include template('add-room');
        }
    }

    //提示音列表
    public function music() {
        $list = $this->model->music();
        $list = json_decode($list['value'], true);
//        $arr = [
//            [
//                "title" => "提现提示",
//                "url" => "up_files/music/7.mp3",
//                "music" => "我发财了",
//                "remark" => "提现时进行提示",
//                "state" => 1,
//                "id" => 1
//            ],
//            [
//                "title" => "充值提示",
//                "url" => "up_files/music/5.mp3",
//                "music" => "老板查钱",
//                "remark" => "充值时进行提示",
//                "state" => 1,
//                "id" => 2
//            ],
//            [
//                "title" => "手动开奖",
//                "url" => "up_files/music/1.mp3",
//                "music" => "警报1",
//                "remark" => "手动开奖是进行",
//                "state" => 1,
//                "id" => 3
//            ],
//            [
//                "title" => "余额不足",
//                "url" => "up_files/music/3.mp3",
//                "music" => "警报3",
//                "remark" => "余额不足提示音",
//                "state" => 1,
//                "id" => 4
//            ],
//            [
//                "title" => "首充提示",
//                "url" => "up_files/music/6.mp3",
//                "music" => "上分",
//                "remark" => "首充提示",
//                "state" => 1,
//                "id" => 5
//            ],
//            [
//                "title" => "首提提示",
//                "url" => "up_files/music/2.mp3",
//                "music" => "警报2",
//                "remark" => "首提提示",
//                "state" => 1,
//                "id" => 6
//            ],
//            [
//                "title" => "新客服消息提示",
//                "url" => "up_files/music/3.mp3",
//                "music" => "警报3",
//                "remark" => "新客服消息提示",
//                "state" => 1,
//                "id" => 7
//            ],
//            [
//                "title" => "进入房间提示",
//                "url" => "up_files/music/3.mp3",
//                "music" => "警报3",
//                "remark" => "进入房间提示",
//                "state" => 1,
//                "id" => 8
//            ],
//            [
//                "title" => "开始下注提示",
//                "url" => "up_files/music/2.mp3",
//                "music" => "警报3",
//                "remark" => "开始下注提示",
//                "state" => 1,
//                "id" => 9
//            ],[
//                "title" => "停止下注提示",
//                "url" => "up_files/music/1.mp3",
//                "music" => "警报3",
//                "remark" => "停止下注提示",
//                "state" => 1,
//                "id" => 10
//            ]
//        ];
//        echo json_encode($arr);
        include template('list-music');
    }

    //新增提示音跳转
    public function addMusic() {
        include template('add-music');
    }

    //新增提示音跳转
    public function selectMusic() {
        $title = $_REQUEST['title'];
        $sql = "SELECT * FROM `un_config` WHERE `nid` = 'musicTips'";
        $res = O('model')->db->getOne($sql);
        $data = json_decode($res['value'], true);
        $is_exist = false;
        foreach ($data as $v) {
            if ($title == $v['title']) {
                $is_exist = true;
                break;
            }
        }
        if ($is_exist) {
            jsonReturn(array('status' => 0, 'data' => '标题已存在'));
        } else {
            jsonReturn(array('status' => 200012, 'data' => '标题不存在'));
        }
    }

    //新增提示音
    public function doAddMusic() {
        $list = $this->model->music();
        $list = json_decode($list['value'], true);

        list($url, $music) = explode("|", $_REQUEST['music']);
        $data = array(
            "title" => $_REQUEST['title'],
            "url" => "up_files/music/" . $url,
            "music" => $music,
            "remark" => $_REQUEST['remark']
        );
        $list[count($list)] = $data;
        $rt = $this->model->addMusic(array("value" => addslashes(json_encode($list))));
        $this->refreshRedis("all", "all");
        echo json_encode(array("rt" => $rt));
    }

    //删除提示音
    public function delMusic() {
        $list = $this->model->music();
        $list = json_decode($list['value'], true);

        array_splice($list, $_REQUEST['i'], 1);
        $rt = $this->model->addMusic(array("value" => addslashes(json_encode($list))));
        $this->refreshRedis("all", "all");
        echo json_encode(array("rt" => $rt));
    }

    //修改提示音状态
    public function setMusic()
    {
        $list = $this->model->music();
        $list = json_decode($list['value'], true);
        $id = $_POST['id'];
        $state = $_POST['state'];
        foreach($list as $key=>$val)
        {
            if($id == $val['id'])
            {
                $list[$key]['state'] = $state;
            }
        }
        $rt = $this->model->addMusic(array("value" => addslashes(json_encode($list))));
        $this->refreshRedis("config", "all");
        echo json_encode(array("rt" => $rt));
    }

    //修改提示音
    public function editMusic()
    {
        $list = $this->model->music();
        $list = json_decode($list['value'], true);
        $id = $_GET['id'];
        if(in_array($id, array(8,9,10))){
            echo "<script type='text/javascript'>window.history.back();</script>";
        }
        $arr = [];
        foreach($list as $val)
        {
            if($id == $val['id'])
            {
                $arr = $val;
            }
        }

        $ids = $_POST['id'];
        $url = $_POST['url'];
        $music = $_POST['music'];
        $remark = $_POST['remark'];
        $is_pop = $_POST['is_pop'];
        if(!empty($ids) && !empty($url) && !empty($music))
        {
            foreach($list as $key=>$val)
            {
                if(($ids == $val['id'])&&($ids==1||$ids==2)){
                    $tmp = $val;
                    $tmp['url'] = $url;
                    $tmp['music'] = $music;
                    $tmp['remark'] = $remark;
                    $tmp['is_pop'] = $is_pop;
                    $list[$key] = $tmp;
                } elseif($ids == $val['id']) {
                    $tmp = $val;
                    $tmp['url'] = $url;
                    $tmp['music'] = $music;
                    $tmp['remark'] = $remark;
                    $list[$key] = $tmp;
                }
            }

            $rt = $this->model->addMusic(array("value" => addslashes(json_encode($list))));
            if($rt !== false)
            {
                $res['code'] = 0;
                $res['msg'] = "操作成功";
                $this->refreshRedis("all", "all");
            }
            else
            {
                $res['code'] = -1;
                $res['msg'] = "操作失败";
            }
            echo json_encode($res);
            exit;
        }



        include template('editMusic');
    }

    public function uploadFile()
    {
        $file = $_FILES['file'];
        if(!empty($file['tmp_name']))
        {
            $res =  upLodeMp3($file);
            if($res === false)
            {
                $arr['code'] = -1;
                $arr['msg'] = "上传失败";
            }
            else
            {
                $arr['code'] = 0;
                $arr['msg'] = $res;
            }
        }
        else
        {
            $arr['code'] = -1;
            $arr['msg'] = "请选择上传文件";
        }
        echo json_encode($arr);
    }

    //禁言列表
    public function untalk() {
        $username = $_REQUEST['username'];

        $list = $this->model->untalk(array("username" => $username));

        foreach ($list as $key => $value) {
            $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
        }

        include template('list-untalk');
    }

    //新增禁言跳转
    public function addTalk() {
        include template('add-talk');
    }

    //永久禁言跳转
    public function addTalkever() {
        $value = $this->model->talkever();
        include template('add-talkever');
    }

    //新增禁言
    public function doAddTalk() {
        $user_id = $this->model->getIdByName($_REQUEST['username']);
        if (empty($user_id)) {
            echo json_encode(array("rt" => "-1"));
            return;
        }
        $gag = $this->model->getGag($user_id['id']);
        if (!empty($gag)) {
            echo json_encode(array("rt" => "-2"));
            return;
        }

        $data = array(
            "user_id" => $user_id['id'],
            "gag_time" => $_REQUEST['gag_time'],
            "gag_reason" => $_REQUEST['gag_reason'],
            "addtime" => time()
        );
        $rt = $this->model->addTalk($data);
        $this->refreshRedis("all", "all");
        echo json_encode(array("rt" => $rt));
    }

    //久禁言设置
    public function doAddTalkever() {
        $data = array(
            "time" => $_REQUEST['time'],
            "cnt" => $_REQUEST['cnt'],
        );
        $rt = $this->model->addTalkever($data);
        $this->refreshRedis("config", "all");
        echo json_encode(array("rt" => $rt));
    }

    //删除禁言
    public function delTalk() {
        $rt = $this->model->delTalk(array("id" => $_REQUEST['id']));
        $this->refreshRedis("config", "all");
        echo json_encode(array("rt" => $rt));
    }

    //天天返利配置
    public function ttflcfg() {
        $this->model->initTtfl(); //初始天天返利表

        $data = $this->model->ttfl(); //天天返利信息
        $data['data']['start_time'] = date('Y-m-d', $data['data']['start_time']);
        $data['data']['end_time'] = date('Y-m-d', $data['data']['end_time']);
        include template('list-ttflcfg');
    }

    //天天返利主干信息修改
    public function upTTfl() {
        $data = array();
        if ($_REQUEST['start_time'] != "") {
            $data['start_time'] = strtotime($_REQUEST['start_time'].' 00:00:00');
        }
        if ($_REQUEST['end_time'] != "") {
            $data['end_time'] = strtotime($_REQUEST['end_time'].' 23:59:59');
        }
        if ($_REQUEST['low_money'] != "") {
            $data['low_money'] = $_REQUEST['low_money'];
        }
        if ($_REQUEST['max_money'] != "") {
            $data['max_money'] = $_REQUEST['max_money'];
        }

        $rt = $this->model->upTTfl($data);
//        $this->refreshRedis("all", "all");
        echo json_encode(array("rt" => $rt));
    }

    //天天返利详细配置添加
    public function addTTfl() {
        $obj = $this->model->dataTTfl($_REQUEST['cz_cnt']);
        if (!empty($obj)) {
            if ($obj['cz_type'] == 2 || $obj['cz_type'] == 4) {
                echo json_encode(array("rt" => "-998"));
                return;
            }
            if (($obj['cz_type'] == 1 || $obj['cz_type'] == 3) && $obj['fl_type'] == 1) {
                echo json_encode(array("rt" => "-998"));
                return;
            }
            if ((($obj['cz_type'] == 1 || $obj['cz_type'] == 3) && $obj['fl_type'] == 2) && (($_REQUEST['cz_type'] != 1 && $_REQUEST['cz_type'] != 3) || $_REQUEST['fl_type'] == 1)) {
                echo json_encode(array("rt" => "-998"));
                return;
            }
        }

        //次数  or  金额百分比
        if ($_REQUEST['cz_type'] == 2 || $_REQUEST['cz_type'] == 4 || (($_REQUEST['cz_type'] == 1 || $_REQUEST['cz_type'] == 3) && $_REQUEST['fl_type'] == 1)) {
            $data = array(
                "cz_cnt" => $_REQUEST['cz_cnt'],
                "cz_type" => $_REQUEST['cz_type'],
                "cz_money" => $_REQUEST['cz_money'],
                "rt_money" => $_REQUEST['rt_money'],
                "rt_type" => $_REQUEST['rt_type'],
                "fl_type" => $_REQUEST['fl_type'],
                "nid" => "100005"
            );
            $rt = $this->model->addTTfl($data);
        } else {
            $data = array(
                "cz_cnt" => $_REQUEST['cz_cnt'],
                "cz_type" => $_REQUEST['cz_type'],
                "range" => json_encode(array(array("s_money" => $_REQUEST['s_money'], "e_money" => $_REQUEST['e_money'], "rt_money" => $_REQUEST['rt_money'], "rt_type" => $_REQUEST['rt_type']))),
                "fl_type" => $_REQUEST['fl_type'],
                "nid" => "100005"
            );


            if (empty($obj)) {
                $rt = $this->model->addTTfl($data);
            } else {
                //范围数组
                $arr = json_decode($obj['range'], true);

                //判断范围是否重复
                $unique = $this->model->uniqueTTfl($arr, $_REQUEST);
                if ($unique) {
                    echo json_encode(array("rt" => "-999"));
                    return;
                }

                $arr[count($arr)] = array("s_money" => $_REQUEST['s_money'], "e_money" => $_REQUEST['e_money'], "rt_money" => $_REQUEST['rt_money'], "rt_type" => $_REQUEST['rt_type']);
                $rt = $this->model->updateTTfl(array("range" => addslashes(json_encode($arr))), $_REQUEST['cz_cnt']);
            }
        }

//        $this->refreshRedis("all", "all");
        echo json_encode(array("rt" => $rt));
    }

    //删除天天返利记录
    public function delTTfl() {
        $rt = $this->model->delTTfl(array("id" => $_REQUEST['id']));
//        $this->refreshRedis("all", "all");
        echo json_encode(array("rt" => $rt));
    }

    //修改天天返利配置详情记录
    public function upTTfls() {
        $where = array("id" => $_REQUEST['id']);
        $data = array(
            "cz_cnt" => $_REQUEST['cz_cnt'],
            "cz_type" => $_REQUEST['cz_type'],
            "cz_money" => $_REQUEST['cz_money'],
            "rt_money" => $_REQUEST['rt_money'],
            "rt_type" => $_REQUEST['rt_type'],
        );
        $rt = $this->model->upTTfls($data, $where);
//        $this->refreshRedis("all", "all");
        echo json_encode(array("rt" => $rt));
    }

    //代理制度设置页面
    public function agencySystem()
    {
        $rows = $this->db->getone("select * from un_config where nid = 'AgencySystemImg'");
        $result = $this->db->getone("select * from un_config where nid = 'AgencyWebSystemImg'");
        $redis = initCacheRedis();
        $reg_sw = $redis->hGet('Config:AgencyRegSwitch','value');
        deinitCacheRedis($redis);
        $data = decode($reg_sw);

        if (isset($_REQUEST['submit'])) {
            //接收参数
            $list = [];
            $arr = [];
            $index = 0;
            foreach ($_REQUEST as $k=>$i){
                if($k=='low_'.$index) $arr['low'] = $i;
                if($k=='upper_'.$index) $arr['upper'] = $i;
                if($k=='rate_'.$index) {
                    $arr['rate'] = $i;
                    $index++;
                    $list[] = $arr;
                }
            }
            $list =json_encode($list);

            $res = D('config')->save(array('value' => $list), array('nid' => 'cashBack'));

            $this->refreshRedis('config', 'all');
            echo json_encode(array("rt" => $res));
            exit;
        }

        $nid = 'cashBack';
        $list = D('config')->getOneCoupon('value', array('nid' => 'cashBack'));
        @$list = json_decode($list['value'],true);
        if(!is_array($list)){
            $list = [];
            $arr['low'] = 0;
            $arr['upper'] = 0;
            $arr['rate'] = 0;
            $list[] = $arr;
            $res = D('config')->save(array('value' => json_encode($list)), array('nid' => $nid));
            $this->refreshRedis('config', 'all');
        }
        include template('agencySystem');
    }


    //代理制度设置页面
    public function up_agency()
    {
        $status = $_REQUEST['status'];
        $key = $_REQUEST['type'];
        $redis = initCacheRedis();
        $reg_sw = $redis->hGet('Config:AgencyRegSwitch','value');
        deinitCacheRedis($redis);
        $data = decode($reg_sw);
        $data[$key] = $status;
        $val = encode($data);
        $sql = "update un_config set value='{$val}' where nid = 'AgencyRegSwitch'";
        $re = $this->db->query($sql);
        if($re){
            $this->refreshRedis('config', 'all'); //刷新缓存
            $json = array(
                'code'=>1,
                'msg'=>'修改成功',
            );
            echo encode($json);
        }else{
            $json = array(
                'code'=>0,
                'msg'=>'修改失败',
            );
            echo encode($json);
        }
    }

    //代理制度设置操作
    public function addAgencySystemImg()
    {
        $img = $_REQUEST['img'];
        if(empty($img))
        {
            $arr['code'] = -1;
            $arr['msg'] = "非法操作";
        }
        else
        {
            $arr = $this->model->addAgencySystemImg($img);
        }
        echo json_encode($arr);
    }

    //代理制度设置操作
    public function addWebAgencySystemImg()
    {
        $img = $_REQUEST['img'];
        if(empty($img))
        {
            $arr['code'] = -1;
            $arr['msg'] = "非法操作";
        }
        else
        {
            $arr = $this->model->addWebAgencySystemImg($img);
        }
        echo json_encode($arr);
    }

    /*
     * app版本 app请求是http协议是否带s
     */
    public function appVersion()
    {
        $appJson = $this->db->getone("select * from un_config where nid = 'appVersion'");

        $appData = json_decode($appJson['value'], true);

        include template('appVersion');
    }

    /*
     * app版本操作
     */
    public function appVersionAct()
    {

        $data = [
            'qrcode_url' => trim($_REQUEST['qrcode_url']),
            'ios_line' => trim($_REQUEST['ios_line']),
            'android_line' => trim($_REQUEST['android_line']),
            'platform_name' => trim($_REQUEST['platform_name']),
            'pc_logo' => trim($_REQUEST['pc_logo']),
            'app_logo' => trim($_REQUEST['app_logo']),
        ];

        $arr = $this->model->appVersionAct($data);
        if($arr['code'] == 0){
            $this->refreshRedis('config', 'all');
        }
        echo json_encode($arr);
    }

    /**
     * 房间玩法限额设置
     * @param int $lotteryType 游戏类型
     */
    public function getRoomWayLimit($lotteryType) {
        //幸运飞艇, pk10  急速赛车 分分PK10
        if(in_array($lotteryType,array('2','4','9','14'))){
            $data = array();
            $data['total_amount'] = 50000;
            $data['limit'][0]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][0]['contact'] = array('冠军_1','冠军_2','冠军_3','冠军_4','冠军_5','冠军_6','冠军_7','冠军_8','冠军_9','冠军_10');
            $data['limit'][0]['remark'] = '猜冠军';
            $data['limit'][1]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][1]['contact'] = array('亚军_1','亚军_2','亚军_3','亚军_4','亚军_5','亚军_6','亚军_7','亚军_8','亚军_9','亚军_10');
            $data['limit'][1]['remark'] = '猜亚军';
            $data['limit'][2]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][2]['contact'] = array('第三名_1','第三名_2','第三名_3','第三名_4','第三名_5','第三名_6','第三名_7','第三名_8','第三名_9','第三名_10');
            $data['limit'][2]['remark'] = '猜第三名';
            $data['limit'][3]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][3]['contact'] = array('第四名_1','第四名_2','第四名_3','第四名_4','第四名_5','第四名_6','第四名_7','第四名_8','第四名_9','第四名_10');
            $data['limit'][3]['remark'] = '猜第四名';
            $data['limit'][4]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][4]['contact'] = array('第五名_1','第五名_2','第五名_3','第五名_4','第五名_5','第五名_6','第五名_7','第五名_8','第五名_9','第五名_10');
            $data['limit'][4]['remark'] = '猜第五名';
            $data['limit'][5]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][5]['contact'] = array('第六名_1','第六名_2','第六名_3','第六名_4','第六名_5','第六名_6','第六名_7','第六名_8','第六名_9','第六名_10');
            $data['limit'][5]['remark'] = '猜第六名';
            $data['limit'][6]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][6]['contact'] = array('第七名_1','第七名_2','第七名_3','第七名_4','第七名_5','第七名_6','第七名_7','第七名_8','第七名_9','第七名_10');
            $data['limit'][6]['remark'] = '猜第七名';
            $data['limit'][7]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][7]['contact'] = array('第八名_1','第八名_2','第八名_3','第八名_4','第八名_5','第八名_6','第八名_7','第八名_8','第八名_9','第八名_10');
            $data['limit'][7]['remark'] = '猜第八名';
            $data['limit'][8]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][8]['contact'] = array('第九名_1','第九名_2','第九名_3','第九名_4','第九名_5','第九名_6','第九名_7','第九名_8','第九名_9','第九名_10');
            $data['limit'][8]['remark'] = '猜第九名';
            $data['limit'][10]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][10]['contact'] = array('第十名_1','第十名_2','第十名_3','第十名_4','第十名_5','第十名_6','第十名_7','第十名_8','第十名_9','第十名_10');
            $data['limit'][10]['remark'] = '猜第十名';
            $data['limit'][11]['data'] = array(50000);
            $data['limit'][11]['contact'] = array("冠军_大","冠军_小","冠军_单","冠军_双");
            $data['limit'][11]['remark'] = '冠军大小单双';
            $data['limit'][12]['data'] = array(50000);
            $data['limit'][12]['contact'] = array("亚军_大","亚军_小","亚军_单","亚军_双");
            $data['limit'][12]['remark'] = '亚军大小单双';
            $data['limit'][13]['data'] = array(50000);
            $data['limit'][13]['contact'] = array("第三名_大","第三名_小","第三名_单","第三名_双");
            $data['limit'][13]['remark'] = '第三名大小单双';
            $data['limit'][14]['data'] = array(50000);
            $data['limit'][14]['contact'] = array("第四名_大","第四名_小","第四名_单","第四名_双");
            $data['limit'][14]['remark'] = '第四名大小单双';
            $data['limit'][15]['data'] = array(50000);
            $data['limit'][15]['contact'] = array("第五名_大","第五名_小","第五名_单","第五名_双");
            $data['limit'][15]['remark'] = '第五名大小单双';
            $data['limit'][16]['data'] = array(50000);
            $data['limit'][16]['contact'] = array("第六名_大","第六名_小","第六名_单","第六名_双");
            $data['limit'][16]['remark'] = '第六名大小单双';
            $data['limit'][17]['data'] = array(50000);
            $data['limit'][17]['contact'] = array("第七名_大","第七名_小","第七名_单","第七名_双");
            $data['limit'][17]['remark'] = '第七名大小单双';
            $data['limit'][18]['data'] = array(50000);
            $data['limit'][18]['contact'] = array("第八名_大","第八名_小","第八名_单","第八名_双");
            $data['limit'][18]['remark'] = '第八名大小单双';
            $data['limit'][19]['data'] = array(50000);
            $data['limit'][19]['contact'] = array("第九名_大","第九名_小","第九名_单","第九名_双");
            $data['limit'][19]['remark'] = '第九名大小单双';
            $data['limit'][20]['data'] = array(50000);
            $data['limit'][20]['contact'] = array("第十名_大","第十名_小","第十名_单","第十名_双");
            $data['limit'][20]['remark'] = '第十名大小单双';
            $data['limit'][21]['data'] = array(50000);
            $data['limit'][21]['contact'] = array("冠军_小单","冠军_小双","冠军_大单","冠军_大双");
            $data['limit'][21]['remark'] = '冠军组合';
            $data['limit'][22]['data'] = array(50000);
            $data['limit'][22]['contact'] = array("亚军_小单","亚军_小双","亚军_大单","亚军_大双");
            $data['limit'][22]['remark'] = '亚军组合';
            $data['limit'][23]['data'] = array(50000);
            $data['limit'][23]['contact'] = array("第三名_小单","第三名_小双","第三名_大单","第三名_大双");
            $data['limit'][23]['remark'] = '第三名组合';
            $data['limit'][24]['data'] = array(50000);
            $data['limit'][24]['contact'] = array("第四名_小单","第四名_小双","第四名_大单","第四名_大双");
            $data['limit'][24]['remark'] = '第四名组合';
            $data['limit'][25]['data'] = array(50000);
            $data['limit'][25]['contact'] = array("第五名_小单","第五名_小双","第五名_大单","第五名_大双");
            $data['limit'][25]['remark'] = '第五名组合';
            $data['limit'][26]['data'] = array(50000);
            $data['limit'][26]['contact'] = array("第六名_小单","第六名_小双","第六名_大单","第六名_大双");
            $data['limit'][26]['remark'] = '第六名组合';
            $data['limit'][27]['data'] = array(50000);
            $data['limit'][27]['contact'] = array("第七名_小单","第七名_小双","第七名_大单","第七名_大双");
            $data['limit'][27]['remark'] = '第七名组合';
            $data['limit'][28]['data'] = array(50000);
            $data['limit'][28]['contact'] = array("第八名_小单","第八名_小双","第八名_大单","第八名_大双");
            $data['limit'][28]['remark'] = '第八名组合';
            $data['limit'][29]['data'] = array(50000);
            $data['limit'][29]['contact'] = array("第九名_小单","第九名_小双","第九名_大单","第九名_大双");
            $data['limit'][29]['remark'] = '第九名组合';
            $data['limit'][30]['data'] = array(50000);
            $data['limit'][30]['contact'] = array("第十名_小单","第十名_小双","第十名_大单","第十名_大双");
            $data['limit'][30]['remark'] = '第十名组合';
            $data['limit'][31]['data'] = array(50000);
            $data['limit'][31]['contact'] = array("冠军_龙","冠军_虎");
            $data['limit'][31]['remark'] = '冠军龙虎';
            $data['limit'][32]['data'] = array(50000);
            $data['limit'][32]['contact'] = array("亚军_龙","亚军_虎");
            $data['limit'][32]['remark'] = '亚军龙虎';
            $data['limit'][33]['data'] = array(50000);
            $data['limit'][33]['contact'] = array("第三名_龙","第三名_虎");
            $data['limit'][33]['remark'] = '第三名龙虎';
            $data['limit'][34]['data'] = array(50000);
            $data['limit'][34]['contact'] = array("第四名_龙","第四名_虎");
            $data['limit'][34]['remark'] = '第四名龙虎';
            $data['limit'][35]['data'] = array(50000);
            $data['limit'][35]['contact'] = array("第五名_龙","第五名_虎");
            $data['limit'][35]['remark'] = '第五名龙虎';
            $data['limit'][36]['data'] = array(50000);
            $data['limit'][36]['contact'] = array("冠亚和_大","冠亚和_小","冠亚和_单","冠亚和_双");
            $data['limit'][36]['remark'] = '冠亚和大小单双';
            $data['limit'][37]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][37]['contact'] = array('冠亚和_3','冠亚和_4','冠亚和_5','冠亚和_6','冠亚和_7','冠亚和_8','冠亚和_9','冠亚和_10','冠亚和_11','冠亚和_12','冠亚和_13','冠亚和_14','冠亚和_15','冠亚和_16','冠亚和_17','冠亚和_18','冠亚和_19');
            $data['limit'][37]['remark'] = '冠亚和值';
            $data['limit'][38]['data'] = array(50000);
            $data['limit'][38]['contact'] = array("庄","闲");
            $data['limit'][38]['remark'] = '庄闲';
            $data['limit'][39]['data'] = array(50000);
            $data['limit'][39]['contact'] = array("冠亚和_A","冠亚和_B","冠亚和_C");
            $data['limit'][39]['remark'] = '冠亚和区段';
            $data['limit'][40]['data'] = array(50000);
            $data['limit'][40]['contact'] = array("冠亚");
            $data['limit'][40]['remark'] = '冠亚';
            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }


        //幸运28, 加拿大28
        if(in_array($lotteryType,array('1','3'))){
            $data = array();
            $data['total_amount'] = 50000;
            $data['limit'][0]['data'] = array(50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000);
            $data['limit'][0]['contact'] = array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27);
            $data['limit'][0]['remark'] = '单点数字';
            $data['limit'][1]['data'] = array(50000);
            $data['limit'][1]['contact'] = array("大","小","单","双");
            $data['limit'][1]['remark'] = '大小单双';
            $data['limit'][2]['data'] = array(50000);
            $data['limit'][2]['contact'] = array("小单","小双","大单","大双");
            $data['limit'][2]['remark'] = '组合';
            $data['limit'][3]['data'] = array(50000);
            $data['limit'][3]['contact'] = array("极大","极小");
            $data['limit'][3]['remark'] = '极大极小';
            $data['limit'][4]['data'] = array(50000);
            $data['limit'][4]['contact'] = array("红");
            $data['limit'][4]['remark'] = '红';
            $data['limit'][5]['data'] = array(50000);
            $data['limit'][5]['contact'] = array("绿");
            $data['limit'][5]['remark'] = '绿';
            $data['limit'][6]['data'] = array(50000);
            $data['limit'][6]['contact'] = array("蓝");
            $data['limit'][6]['remark'] = '蓝';
            $data['limit'][7]['data'] = array(50000);
            $data['limit'][7]['contact'] = array("豹子");
            $data['limit'][7]['remark'] = '豹子';
            $data['limit'][8]['data'] = array(50000);
            $data['limit'][8]['contact'] = array("正顺");
            $data['limit'][8]['remark'] = '正顺';
            $data['limit'][9]['data'] = array(50000);
            $data['limit'][9]['contact'] = array("倒顺");
            $data['limit'][9]['remark'] = '倒顺';
            $data['limit'][10]['data'] = array(50000);
            $data['limit'][10]['contact'] = array("半顺");
            $data['limit'][10]['remark'] = '半顺';
            $data['limit'][11]['data'] = array(50000);
            $data['limit'][11]['contact'] = array("乱顺");
            $data['limit'][11]['remark'] = '乱顺';
            $data['limit'][12]['data'] = array(50000);
            $data['limit'][12]['contact'] = array("对子");
            $data['limit'][12]['remark'] = '对子';
            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }

        //重庆时时彩 三分彩 分分彩
        if(in_array($lotteryType,array('5','6','11'))){
            $data = array();
            $data['total_amount'] = 50000;

            //猜总和
            $data['limit'][] = [
                'data' => [50000,],
                'contact' => ['总和_大', '总和_小', '总和_单', '总和_双',],
                'remark' => '猜总和',
            ];

            //猜双面
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => ['第一球_大', '第一球_小', '第一球_单', '第一球_双',],
                'remark' => '猜双面第一球',
            ];
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => ['第二球_大', '第二球_小', '第二球_单', '第二球_双',],
                'remark' => '猜双面第二球',
            ];
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => ['第三球_大', '第三球_小', '第三球_单', '第三球_双',],
                'remark' => '猜双面第三球',
            ];
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => ['第四球_大', '第四球_小', '第四球_单', '第四球_双',],
                'remark' => '猜双面第四球',
            ];
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => ['第五球_大', '第五球_小', '第五球_单', '第五球_双',],
                'remark' => '猜双面第五球',
            ];

            //猜数字
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => [
                    '第一球_0', '第一球_1', '第一球_2', '第一球_3', '第一球_4',
                    '第一球_5', '第一球_6', '第一球_7', '第一球_8', '第一球_9',
                ],
                'remark' => '猜数字第一球',
            ];
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => [
                    '第二球_0', '第二球_1', '第二球_2', '第二球_3', '第二球_4',
                    '第二球_5', '第二球_6', '第二球_7', '第二球_8', '第二球_9',
                ],
                'remark' => '猜数字第二球',
            ];
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => [
                    '第三球_0', '第三球_1', '第三球_2', '第三球_3', '第三球_4',
                    '第三球_5', '第三球_6', '第三球_7', '第三球_8', '第三球_9',
                ],
                'remark' => '猜数字第三球',
            ];
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => [
                    '第四球_0', '第四球_1', '第四球_2', '第四球_3', '第四球_4',
                    '第四球_5', '第四球_6', '第四球_7', '第四球_8', '第四球_9',
                ],
                'remark' => '猜数字第四球',
            ];
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => [
                    '第五球_0', '第五球_1', '第五球_2', '第五球_3', '第五球_4',
                    '第五球_5', '第五球_6', '第五球_7', '第五球_8', '第五球_9',
                ],
                'remark' => '猜数字第五球',
            ];

            //猜龙虎合
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, ],
                'contact' => ['龙', '虎', '和', ],
                'remark' => '猜龙虎和',
            ];

            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }

        //六合彩 急速六合彩
        if(in_array($lotteryType,['7','8'])){
            $data = array();
            $data['total_amount'] = 50000;

            $num = [];
            $con1 = [];
            $con2 = [];
            $con3 = [];
            $con4 = [];
            $zt1 = [];
            $zt2 = [];
            $zt3 = [];
            $zt4 = [];
            $zt5 = [];
            $zt6 = [];
            $lm1 = [];
            $lm2 = [];
            $lm3 = [];
            $lm4 = [];
            $lm5 = [];
            for ($a=1;$a<=49;$a++) {
                $num[] = 50000;
                $con1[] = "特码A_".$a;
                $con2[] = "特码B_".$a;
                $con3[] = "正码A_".$a;
                $con4[] = "正码B_".$a;
                $zt1[] = "正1特_".$a;
                $zt2[] = "正2特_".$a;
                $zt3[] = "正3特_".$a;
                $zt4[] = "正4特_".$a;
                $zt5[] = "正5特_".$a;
                $zt6[] = "正6特_".$a;
                $lm1[] = "连码_三中二".$a;
                $lm2[] = "连码_三全中".$a;
                $lm3[] = "连码_二全中".$a;
                $lm4[] = "连码_二中特".$a;
                $lm5[] = "连码_特串".$a;

            }
            /*特码 投注限额开始*/
            $data['limit'][] = [
                'data' => $num,
                'contact' => $con1,
                'remark' => '特码A值',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码A_大','特码A_小','特码A_单','特码A_双'],
                'remark' => '特码A大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码A_红波','特码A_蓝波','特码A_绿波'],
                'remark' => '特码A红蓝绿波',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码A_尾大','特码A_尾小'],
                'remark' => '特码A尾大尾小',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码A_合大','特码A_合小','特码A_合单','特码A_合双'],
                'remark' => '特码A合大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码A_家禽','特码A_野兽'],
                'remark' => '特码A家禽野兽',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码A_区段A','特码A_区段B','特码A_区段C','特码A_区段D','特码A_区段E'],
                'remark' => '特码A区段ABCDE',
            ];

            $data['limit'][] = [
                'data' => $num,
                'contact' => $con2,
                'remark' => '特码B值',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码B_大','特码B_小','特码B_单','特码B_双'],
                'remark' => '特码B大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码B_红波','特码B_蓝波','特码B_绿波'],
                'remark' => '特码B红蓝绿波',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码B_尾大','特码B_尾小'],
                'remark' => '特码B尾大尾小',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码B_合大','特码B_合小','特码B_合单','特码B_合双'],
                'remark' => '特码B合大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码B_家禽','特码B_野兽'],
                'remark' => '特码B家禽野兽',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特码B_区段A','特码B_区段B','特码B_区段C','特码B_区段D','特码B_区段E'],
                'remark' => '特码B区段ABCDE',
            ];
            /*特码 投注限额结束*/

            /*正码 投注限额开始*/
            $data['limit'][] = [
                'data' => $num,
                'contact' => $con3,
                'remark' => '正码A值',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['正码A_总和大','正码A_总和小','正码A_总和单','正码A_总和双'],
                'remark' => '正码A总和大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['正码A_总尾大','正码A_总尾小'],
                'remark' => '正码A总尾大小',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['正码A_龙','正码A_虎'],
                'remark' => '正码A龙虎',
            ];

            $data['limit'][] = [
                'data' => $num,
                'contact' => $con4,
                'remark' => '正码B值',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['正码B_总和大','正码B_总和小','正码B_总和单','正码B_总和双'],
                'remark' => '正码B总和大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['正码B_总尾大','正码B_总尾小'],
                'remark' => '正码B总尾大小',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['正码B_龙','正码B_虎'],
                'remark' => '正码B龙虎',
            ];
            /*正码 投注限额结束*/

            /*正特 投注限额开始*/
            for ($a=1;$a<=6;$a++) {
                $xxx = "zt".$a;
                $data['limit'][] = [
                    'data' => $num,
                    'contact' => $$xxx,
                    'remark' => '正'.$a.'特值',
                ];
                $data['limit'][] = [
                    'data' => [50000],
                    'contact' => ['正'.$a.'特_尾大','正'.$a.'特_尾小'],
                    'remark' => '正'.$a.'特尾数大小',
                ];
                $data['limit'][] = [
                    'data' => [50000],
                    'contact' => ['正'.$a.'特_大','正'.$a.'特_小','正'.$a.'特_单','正'.$a.'特_双'],
                    'remark' => '正'.$a.'特大小单双',
                ];
                $data['limit'][] = [
                    'data' => [50000],
                    'contact' => ['正'.$a.'特_合单','正'.$a.'特_合双','正'.$a.'特_合大','正'.$a.'特_合小'],
                    'remark' => '正'.$a.'特合单双大小',
                ];
                $data['limit'][] = [
                    'data' => [50000],
                    'contact' => ['正'.$a.'特_红波','正'.$a.'特_绿波','正'.$a.'特_蓝波'],
                    'remark' => '正'.$a.'特红蓝绿波',
                ];
            }
            /*正特 投注限额结束*/

            /*连码 投注限额开始*/
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['三中二'],
                'remark' => '三中二',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['三全中'],
                'remark' => '三全中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['二全中'],
                'remark' => '二全中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['二中特'],
                'remark' => '二中特',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['特串'],
                'remark' => '特串',
            ];
            /*连码 投注限额结束*/

            /*半波 投注限额开始*/
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['半波_红大','半波_红小','半波_红单','半波_红双'],
                'remark' => '半波红大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['半波_红合单','半波_红合双'],
                'remark' => '半波红合单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['半波_绿大','半波_绿小','半波_绿单','半波_绿双'],
                'remark' => '半波绿大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['半波_绿合单','半波_绿合双'],
                'remark' => '半波绿合单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['半波_蓝大','半波_蓝小','半波_蓝单','半波_蓝双'],
                'remark' => '半波蓝大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['半波_蓝合单','半波_蓝合双'],
                'remark' => '半波蓝合单双',
            ];
            /*半波 投注限额结束*/

            /* 尾数限额开始 */
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => [
                    '尾数_0尾', '尾数_1尾', '尾数_2尾', '尾数_3尾', '尾数_4尾',
                    '尾数_5尾', '尾数_6尾', '尾数_7尾', '尾数_8尾', '尾数_9尾',
                ],
                'remark' => '尾数限额',
            ];
            /* 尾数限额结束 */

            /* 连尾限额开始 */
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['二尾连中'],
                'remark' => '二尾连中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['三尾连中'],
                'remark' => '三尾连中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['四尾连中'],
                'remark' => '四尾连中',
            ];

            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['二尾连不中'],
                'remark' => '二尾连不中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['三尾连不中'],
                'remark' => '三尾连不中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['四尾连不中'],
                'remark' => '四尾连不中',
            ];
            /* 连尾限额结束 */

            /* 一肖限额开始 */
            $data['limit'][] = [
                'data' => [50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, 50000, ],
                'contact' => [
                    '一肖_鼠', '一肖_牛', '一肖_虎', '一肖_兔', '一肖_龙', '一肖_蛇',
                    '一肖_马', '一肖_羊', '一肖_猴', '一肖_鸡', '一肖_狗', '一肖_猪',
                ],
                'remark' => '一肖限额',
            ];
            /* 一肖限额结束 */

            /* 连肖限额开始 */
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['二肖连中'],
                'remark' => '二肖连中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['三肖连中'],
                'remark' => '三肖连中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['四肖连中'],
                'remark' => '四肖连中',
            ];

            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['二肖连不中'],
                'remark' => '二肖连不中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['三肖连不中'],
                'remark' => '三肖连不中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['四肖连不中'],
                'remark' => '四肖连不中',
            ];
            /* 连肖限额结束 */

            /*特肖 投注限额开始*/
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['特肖_鼠','特肖_牛','特肖_虎','特肖_兔','特肖_龙','特肖_蛇','特肖_马','特肖_羊','特肖_猴','特肖_鸡','特肖_狗','特肖_猪'],
                'remark' => '特码生肖',
            ];
            /*特肖 投注限额结束*/

            /*不中 投注限额开始*/
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['五不中'],
                'remark' => '五不中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['六不中'],
                'remark' => '六不中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['七不中'],
                'remark' => '七不中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['八不中'],
                'remark' => '八不中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['九不中'],
                'remark' => '九不中',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['十不中'],
                'remark' => '十不中',
            ];
            /*不中 投注限额结束*/

            /*正码1-6 投注限额开始*/
            for ($a=1;$a<=6;$a++) {
                $data['limit'][] = [
                    'data' => [50000],
                    'contact' => ['正码'.$a.'_大','正码'.$a.'_小','正码'.$a.'_单','正码'.$a.'_双'],
                    'remark' => '正码'.$a.'大小单双',
                ];
                $data['limit'][] = [
                    'data' => [50000],
                    'contact' => ['正码'.$a.'_尾大','正码'.$a.'_尾小'],
                    'remark' => '正码'.$a.'尾大小',
                ];
                $data['limit'][] = [
                    'data' => [50000],
                    'contact' => ['正码'.$a.'_合大','正码'.$a.'_合小','正码'.$a.'_合单','正码'.$a.'_合双'],
                    'remark' => '正码'.$a.'合大小单双',
                ];
                $data['limit'][] = [
                    'data' => [50000],
                    'contact' => ['正码'.$a.'_红波','正码'.$a.'_绿波','正码'.$a.'_蓝波'],
                    'remark' => '正码'.$a.'红蓝绿波',
                ];
            }
            /*正码1-6 投注限额结束*/

            /*正1-6龙虎 投注限额开始*/
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['1-2球_龙','1-2球_虎'],
                'remark' => '1-2球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['1-3球_龙','1-3球_虎'],
                'remark' => '1-3球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['1-4球_龙','1-4球_虎'],
                'remark' => '1-4球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['1-5球_龙','1-5球_虎'],
                'remark' => '1-5球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['1-6球_龙','1-6球_虎'],
                'remark' => '1-6球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['2-3球_龙','2-3球_虎'],
                'remark' => '2-3球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['2-4球_龙','2-4球_虎'],
                'remark' => '2-4球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['2-5球_龙','2-5球_虎'],
                'remark' => '2-5球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['2-6球_龙','2-6球_虎'],
                'remark' => '2-6球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['3-4球_龙','3-4球_虎'],
                'remark' => '3-4球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['3-5球_龙','3-5球_虎'],
                'remark' => '3-5球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['3-6球_龙','3-6球_虎'],
                'remark' => '3-6球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['4-5球_龙','4-5球_虎'],
                'remark' => '4-5球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['4-6球_龙','4-6球_虎'],
                'remark' => '4-6球龙虎',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['5-6球_龙','5-6球_虎'],
                'remark' => '5-6球龙虎',
            ];
            /*正1-6龙虎 投注限额结束*/
            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }

        //牛牛
        if (in_array($lotteryType,['10'])) {
            $data['total_amount'] = 50000;
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['无牛','牛一','牛二','牛三','牛四','牛五','牛六','牛七','牛八','牛九','牛牛','花色牛'],
                'remark' => '猜牛',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['第一张_A','第一张_2','第一张_3','第一张_4','第一张_5','第一张_6','第一张_7','第一张_8','第一张_9','第一张_10','第一张_J','第一张_Q','第一张_K'],
                'remark' => '猜第一张',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['第二张_A','第二张_2','第二张_3','第二张_4','第二张_5','第二张_6','第二张_7','第二张_8','第二张_9','第二张_10','第二张_J','第二张_Q','第二张_K'],
                'remark' => '猜第二张',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['第三张_A','第三张_2','第三张_3','第三张_4','第三张_5','第三张_6','第三张_7','第三张_8','第三张_9','第三张_10','第三张_J','第三张_Q','第三张_K'],
                'remark' => '猜第三张',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['第四张_A','第四张_2','第四张_3','第四张_4','第四张_5','第四张_6','第四张_7','第四张_8','第四张_9','第四张_10','第四张_J','第四张_Q','第四张_K'],
                'remark' => '猜第四张',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['第五张_A','第五张_2','第五张_3','第五张_4','第五张_5','第五张_6','第五张_7','第五张_8','第五张_9','第五张_10','第五张_J','第五张_Q','第五张_K'],
                'remark' => '猜第五张',
            ];

            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['第一张_大','第一张_小','第一张_单','第一张_双'],
                'remark' => '第一张大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['第二张_大','第二张_小','第二张_单','第二张_双'],
                'remark' => '第二张大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['第三张_大','第三张_小','第三张_单','第三张_双'],
                'remark' => '第三张大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['第四张_大','第四张_小','第四张_单','第四张_双'],
                'remark' => '第四张大小单双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['第五张_大','第五张_小','第五张_单','第五张_双'],
                'remark' => '第五张大小单双',
            ];

            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['第一张_大单','第一张_大双','第一张_小单','第一张_小双'],
                'remark' => '第一张大单大双小单小双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['第二张_大单','第二张_大双','第二张_小单','第二张_小双'],
                'remark' => '第二张大单大双小单小双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['第三张_大单','第三张_大双','第三张_小单','第三张_小双'],
                'remark' => '第三张大单大双小单小双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['第四张_大单','第四张_大双','第四张_小单','第四张_小双'],
                'remark' => '第四张大单大双小单小双',
            ];
            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['第五张_大单','第五张_大双','第五张_小单','第五张_小双'],
                'remark' => '第五张大单大双小单小双',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['第一张_黑桃','第一张_梅花','第一张_红心','第一张_方块'],
                'remark' => '第一张花色',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['第二张_黑桃','第二张_梅花','第二张_红心','第二张_方块'],
                'remark' => '第二张花色',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['第三张_黑桃','第三张_梅花','第三张_红心','第三张_方块'],
                'remark' => '第三张花色',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['第四张_黑桃','第四张_梅花','第四张_红心','第四张_方块'],
                'remark' => '第四张花色',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['第五张_黑桃','第五张_梅花','第五张_红心','第五张_方块'],
                'remark' => '第五张花色',
            ];

            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['龙','虎'],
                'remark' => '猜龙虎',
            ];

            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['有公牌','无公牌'],
                'remark' => '猜公牌',
            ];

            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['大','小','单','双'],
                'remark' => '猜总和大小单双',
            ];

            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['大单','大双','小单','小双'],
                'remark' => '猜总和大单大双小单小双',
            ];

            $data['limit'][] = [
                'data' => [50000],
                'contact' => ['红方胜','蓝方胜'],
                'remark' => '猜胜负',
            ];
            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }

        //足球
        if (in_array($lotteryType,['12'])) {
            $data['total_amount'] = 50000;

            $data['limit'][] = [
                'data' => [50000,50000],
                'contact' => ['全场单双_单','全场单双_双'],
                'remark' => '单双',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['半场让球_A','半场让球_B','半场大小_大','半场大小_小'],
                'remark' => '半场',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['全场让球_A','全场让球_B','全场大小_大','全场大小_小'],
                'remark' => '全场',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['加时让球_A','加时让球_B','加时大小_大','加时大小_小'],
                'remark' => '加时',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['点球让球_A','点球让球_B','点球大小_大','点球大小_小'],
                'remark' => '点球',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000],
                'contact' => ['半场_A胜','半场_平局','半场_B胜','全场_A胜','全场_平局','全场_B胜'],
                'remark' => '独赢盘',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['半场入球_0','半场入球_1','半场入球_2','半场入球_3或以上','全场入球_0~1','全场入球_2~3','全场入球_4~6','全场入球_7或以上'],
                'remark' => '总入球',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['半/全场_主主','半/全场_主和','半/全场_主客','半/全场_和主','半/全场_和和','半/全场_和客','半/全场_客主','半/全场_客和','半/全场_客客'],
                'remark' => '半场/全场',
            ];

            $data['limit'][] = [
                'data' => [
                    50000,50000,50000,50000,50000,50000,50000,50000,50000,50000, 50000, 50000,50000,
                    50000,50000,50000,50000,50000,50000,50000, 50000,50000,50000,50000,50000,50000
                ],
                'contact' => [
                    '全场比分_1-0','全场比分_0-1','全场比分_2-0','全场比分_0-2','全场比分_2-1','全场比分_1-2','全场比分_3-0','全场比分_0-3','全场比分_3-1','全场比分_1-3',
                    '全场比分_3-2','全场比分_2-3','全场比分_4-0','全场比分_0-4','全场比分_4-1','全场比分_1-4','全场比分_4-2','全场比分_2-4','全场比分_4-3','全场比分_3-4',
                    '全场比分_0-0','全场比分_1-1','全场比分_2-2','全场比分_3-3','全场比分_4-4','全场比分_其他'
                ],
                'remark' => '波胆',
            ];


            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }

        if (in_array($lotteryType,['13'])) {
            $data['total_amount'] = 50000;

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000],
                'contact' => ['第一骰_1','第一骰_2','第一骰_3','第一骰_4','第一骰_5','第一骰_6'],
                'remark' => '猜数字第一骰',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['第一骰_大','第一骰_小','第一骰_单','第一骰_双'],
                'remark' => '猜双面第一骰',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000],
                'contact' => ['第二骰_1','第二骰_2','第二骰_3','第二骰_4','第二骰_5','第二骰_6'],
                'remark' => '猜数字第二骰',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['第二骰_大','第二骰_小','第二骰_单','第二骰_双'],
                'remark' => '猜双面第二骰',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000],
                'contact' => ['第三骰_1','第三骰_2','第三骰_3','第三骰_4','第三骰_5','第三骰_6'],
                'remark' => '猜数字第三骰',
            ];
            $data['limit'][] = [
                'data' => [50000,50000,50000,50000],
                'contact' => ['第三骰_大','第三骰_小','第三骰_单','第三骰_双'],
                'remark' => '猜双面第三骰',
            ];


            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => [
                    '总和_大','总和_小','总和_单','总和_双','总和_4','总和_5','总和_6', '总和_7','总和_8','总和_9',
                    '总和_10','总和_11','总和_12','总和_13','总和_14','总和_15','总和_16','总和_17'
                ],
                'remark' => '猜总和大小单双',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000],
                'contact' => ['对子_1','对子_2','对子_3','对子_4','对子_5','对子_6'],
                'remark' => '猜对子',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['豹子_1','豹子_2','豹子_3','豹子_4','豹子_5','豹子_6','豹子_1-6'],
                'remark' => '猜围骰和全骰',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000],
                'contact' => ['单骰_1','单骰_2','单骰_3','单骰_4','单骰_5','单骰_6'],
                'remark' => '猜单骰',
            ];

            $data['limit'][] = [
                'data' => [50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000,50000],
                'contact' => ['双骰_1-2','双骰_1-3','双骰_1-4','双骰_1-5','双骰_1-6','双骰_2-3','双骰_2-4','双骰_2-5','双骰_2-6','双骰_3-4','双骰_3-5','双骰_3-6','双骰_4-5','双骰_4-6','双骰_5-6'],
                'remark' => '猜双骰',
            ];
            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }
    }

    /*
     *
     */

    /**
     * 获取逆向投注
     * @return bool|mixed|void
     */
    public function getReverse($lotteryType)
    {
        //幸运28, 加拿大28
        if(in_array($lotteryType,array(1,3))){
            $data = array();
            $data[0]['name'] = '大小';
            $data[0]['data'] = '大,小';
            $data[0]['state'] = "1";
            $data[1]['name'] = '单双';
            $data[1]['data'] = '单,双';
            $data[1]['state'] = "1";
            $data[2]['name'] = '大单&小单&大双&小双';
            $data[2]['data'] = '大单,小单,大双,小双';
            $data[2]['state'] = "1";
            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }

        //幸运飞艇, pk10
        if(in_array($lotteryType,array(2,4))){
            $data = array();
            $data[0]['name'] = '冠军大小';
            $data[0]['data'] = '冠军_大,冠军_小';
            $data[0]['state'] = "1";
            $data[1]['name'] = '亚军大小';
            $data[1]['data'] = '亚军_大,亚军_小';
            $data[1]['state'] = "1";
            $data[2]['name'] = '第三名大小';
            $data[2]['data'] = '第三名_大,第三名_小';
            $data[2]['state'] = "1";
            $data[3]['name'] = '第四名大小';
            $data[3]['data'] = '第四名_大,第四名_小';
            $data[3]['state'] = "1";
            $data[4]['name'] = '第五名大小';
            $data[4]['data'] = '第五名_大,第五名_小';
            $data[4]['state'] = "1";
            $data[5]['name'] = '第六名大小';
            $data[5]['data'] = '第六名_大,第六名_小';
            $data[5]['state'] = "1";
            $data[6]['name'] = '第七名大小';
            $data[6]['data'] = '第七名_大,第七名_小';
            $data[6]['state'] = "1";
            $data[7]['name'] = '第八名大小';
            $data[7]['data'] = '第八名_大,第八名_小';
            $data[7]['state'] = "1";
            $data[8]['name'] = '第九名大小';
            $data[8]['data'] = '第九名_大,第八名_小';
            $data[8]['state'] = "1";
            $data[9]['name'] = '第十名大小';
            $data[9]['data'] = '第十名_大,第十名_小';
            $data[9]['state'] = "1";
            $data[10]['name'] = '冠军单双';
            $data[10]['data'] = '冠军_单,冠军_双';
            $data[10]['state'] = "1";
            $data[11]['name'] = '亚军单双';
            $data[11]['data'] = '亚军_单,亚军_双';
            $data[11]['state'] = "1";
            $data[12]['name'] = '第三名单双';
            $data[12]['data'] = '第三名_单,第三名_双';
            $data[12]['state'] = "1";
            $data[13]['name'] = '第四名单双';
            $data[13]['data'] = '第四名_单,第四名_双';
            $data[13]['state'] = "1";
            $data[14]['name'] = '第五名单双';
            $data[14]['data'] = '第五名_单,第五名_双';
            $data[14]['state'] = "1";
            $data[16]['name'] = '第六名单双';
            $data[16]['data'] = '第六名_单,第六名_双';
            $data[16]['state'] = "1";
            $data[17]['name'] = '第七名单双';
            $data[17]['data'] = '第七名_单,第七名_双';
            $data[17]['state'] = "1";
            $data[18]['name'] = '第八名单双';
            $data[18]['data'] = '第八名_单,第八名_双';
            $data[18]['state'] = "1";
            $data[19]['name'] = '第九名单双';
            $data[19]['data'] = '第九名_单,第九名_双';
            $data[19]['state'] = "1";
            $data[20]['name'] = '第十名单双';
            $data[20]['data'] = '第十名_单,第十名_双';
            $data[20]['state'] = "1";
            $data[21]['name'] = '冠军龙虎';
            $data[21]['data'] = '冠军_龙,冠军_虎';
            $data[21]['state'] = "1";
            $data[22]['name'] = '亚军龙虎';
            $data[22]['data'] = '亚军_龙,亚军_虎';
            $data[22]['state'] = "1";
            $data[23]['name'] = '第三名龙虎';
            $data[23]['data'] = '第三名_龙,第三名_虎';
            $data[23]['state'] = "1";
            $data[24]['name'] = '第四名龙虎';
            $data[24]['data'] = '第四名_龙,第四名_虎';
            $data[24]['state'] = "1";
            $data[25]['name'] = '第五名龙虎';
            $data[25]['data'] = '第五名_龙,第五名_虎';
            $data[25]['state'] = "1";
            $data[26]['name'] = '冠军大单&小单&大双&小双';
            $data[26]['data'] = '冠军_大单,冠军_小单,冠军_大双,冠军_小双';
            $data[26]['state'] = "1";
            $data[27]['name'] = '亚军大单&小单&大双&小双';
            $data[27]['data'] = '亚军_大单,亚军_小单,亚军_大双,亚军_小双';
            $data[27]['state'] = "1";
            $data[28]['name'] = '第三名大单&小单&大双&小双';
            $data[28]['data'] = '第三名_大单,第三名_小单,第三名_大双,第三名_小双';
            $data[28]['state'] = "1";
            $data[29]['name'] = '第四名大单&小单&大双&小双';
            $data[29]['data'] = '第四名_大单,第四名_小单,第四名_大双,第四名_小双';
            $data[29]['state'] = "1";
            $data[30]['name'] = '第五名大单&小单&大双&小双';
            $data[30]['data'] = '第五名_大单,第五名_小单,第五名_大双,第五名_小双';
            $data[30]['state'] = "1";
            $data[31]['name'] = '第六名大单&小单&大双&小双';
            $data[31]['data'] = '第六名_大单,第六名_小单,第六名_大双,第六名_小双';
            $data[31]['state'] = "1";
            $data[32]['name'] = '第七名大单&小单&大双&小双';
            $data[32]['data'] = '第七名_大单,第七名_小单,第七名_大双,第七名_小双';
            $data[32]['state'] = "1";
            $data[33]['name'] = '第八名大单&小单&大双&小双';
            $data[33]['data'] = '第八名_大单,第八名_小单,第八名_大双,第八名_小双';
            $data[33]['state'] = "1";
            $data[34]['name'] = '第九名大单&小单&大双&小双';
            $data[34]['data'] = '第九名_大单,第九名_小单,第九名_大双,第九名_小双';
            $data[34]['state'] = "1";
            $data[35]['name'] = '第十名大单&小单&大双&小双';
            $data[35]['data'] = '第十名_大单,第十名_小单,第十名_大双,第十名_小双';
            $data[35]['state'] = "1";
            $data[36]['name'] = '冠亚小&冠亚大&冠亚单&冠亚双';
            $data[36]['data'] = '冠亚小,冠亚大,冠亚单,冠亚双';
            $data[36]['state'] = "1";
            $data[36]['name'] = '庄闲';
            $data[36]['data'] = '庄,闲';
            $data[36]['state'] = "1";
            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }

    }

    /**
     * 获取封盘信息
     * @return bool|mixed|void
     */
    public function getMessageConfig($lotteryType,$roomid)
    {
        if ($lotteryType == 12) {
            $time = time();
        } else {
            $time = 20;
        }
        $list = $this->db->getone("select id from un_message_conf where room_id = {$roomid}");
        if (!empty($list)){
            $this->db->delete("#@_message_conf", array('room_id'=>$roomid));
        }
        $sql = array(
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '封盘提示', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '封盘线', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '下注核对', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '开奖前信息', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '封盘后广告1', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '封盘后广告2', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '封盘后广告3', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '封盘后广告4', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '封盘后广告5', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '封盘后广告6', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '封盘后广告7', '{$time}','','0', '2')",
            "INSERT INTO `un_message_conf` ( `lottery_type`, `room_id`, `title`, `release_time`, `content`, `state`, `audit_status`) VALUES ( '{$lotteryType}', '{$roomid}', '账单', '{$time}','','0', '2')",
        );

        return $sql;
    }

    /**
     * 获取设置个人最多投注数字个数页面
     *
     */
    public function getLimit()
    {
        $type = trim($_REQUEST['type']);
        $pkData = [];
        $getData = $this->model->getLimitLottyer($type);
        if (empty($getData)) {
            $pkData['status'] = 0;
            $pkData['max'] = 0;
        }

        $pkData = json_decode($getData['value'], true);

        include template('message-set-limit');
    }

    /**
     * bjpk10个人最多投注数字个数设置
     *
     */
    public function setLimit()
    {
        $type = trim($_REQUEST['type']);
        if (!in_array($type,["2","4","5","6","7","8","9","10","11","13","14"])) {
            echo json_encode(['code' => -1, 'msg' => '缺少必要参数:type']);
            exit;
        }

        if (in_array($type,["7","8","10"])){

            $data = $_REQUEST['data'];

            foreach ($data as $val) {

                if (!is_numeric($val['status']) || !isset($val['status'])) {
                    echo json_encode(['code' => -1, 'msg' => '缺少必要参数:status']);
                    exit;
                }
                if (!is_numeric($val['max']) || !isset($val['max'])) {
                    echo json_encode(['code' => -1, 'msg' => '缺少必要参数:max']);
                    exit;
                }

                if (!in_array($val['name'],['特肖','一肖','尾数','正6特','正5特','正4特','正3特','正2特','正1特','正码B','正码A','特码A','特码B','猜牛','猜牌面'])) {
                    echo json_encode(['code' => -1, 'msg' => '缺少必要参数:name']);
                    exit;
                }

                if ($val['name'] =='尾数') {
                    if ($val['max'] < 0 || $val['max'] > 9) {
                        echo json_encode(['code' => -1, 'msg' => '修改失败，请输入0~9的整数']);
                        exit;
                    }
                } elseif (in_array($val['name'],['一肖','特肖','猜牛'])) {
                    if ($val['max'] < 0 || $val['max'] > 12) {
                        echo json_encode(['code' => -1, 'msg' => '修改失败，请输入0~12的整数']);
                        exit;
                    }
                } elseif($val['name'] =='猜牌面') {
                    if ($val['max'] < 0 || $val['max'] > 13) {
                        echo json_encode(['code' => -1, 'msg' => '修改失败，请输入0~13的整数']);
                        exit;
                    }
                } else {
                    if ($val['max'] < 0 || $val['max'] > 49) {
                        echo json_encode(['code' => -1, 'msg' => '修改失败，请输入0~49的整数']);
                        exit;
                    }
                }
            }

        } else {

            $data['status'] = trim($_REQUEST['status']);
            $data['max'] = trim($_REQUEST['limitNum']);
            if (!is_numeric($data['status']) || !is_numeric($data['max']) || floor($data['max']) != $data['max']) {
                echo json_encode(['code' => -1, 'msg' => '输入非法，修改失败']);
                exit;
            }

            if ($data['max'] < 0 || $data['max'] > 10) {
                echo json_encode(['code' => -1, 'msg' => '修改失败，请输入0~10的整数']);
                exit;
            }
        }

        $getData = $this->model->setLimitLottyer($data, $type);

        if ($getData !== false || $getData > 0) {
            $this->refreshRedis('config', 'all');
            echo json_encode(['code' => 0, 'msg' => '操作成功']);
            exit;
        } else {
            echo json_encode(['code' => -1, 'msg' => '操作失败']);
            exit;
        }
    }


    /**
     * 首页彩种设置
     * 2017-09-30 update
     */
    public function index_lottery () {

        $redis = initCacheRedis();
        $index_lottery_list = $redis -> HMGet('Config:index_lottery_list',['value']);

        $list = json_decode($index_lottery_list['value'], true);

        //关闭redis链接
        deinitCacheRedis($redis);
        include template('list-index-lottery');
    }

    /**
     * 首页热门彩种推荐设置
     * 2018-05-15
     */
    public function recommend_lottery()
    {

        $redis = initCacheRedis();
        $recommend_lottery_list = $redis -> HMGet('Config:recommend_lottery_list',['value']);

        $list = json_decode($recommend_lottery_list['value'], true);

        //关闭redis链接
        deinitCacheRedis($redis);
        include template('list-recommend-lottery');
    }


    /**
     * 编辑首页彩种
     * 2017-09-30 update
     */
    public function edit_lottery () {

        $lottery_type = intval($_REQUEST['lottery_type']);

        $redis = initCacheRedis();
        $index_lottery_list = $redis -> HMGet('Config:index_lottery_list',['value']);

        $list = json_decode($index_lottery_list['value'], true);

        $lottery_info = [];
        foreach ($list as $k => $v) {
            if ($v['lottery_type'] == $lottery_type) {
                $lottery_info = $v;
                break;
            }
        }
        //关闭redis链接
        deinitCacheRedis($redis);

        $lottery_article = $this->db->getall("SELECT `id`, `title` FROM `un_article` WHERE `type` = 6");

        //星级评价
        $star_level_arr = [
            1 => '一星',
            2 => '二星',
            3 => '三星',
            4 => '四星',
            5 => '五星',
        ];

        include template('edit-index-lottery');
    }

    /**
     * 编辑首页热门推荐彩种
     * 2018-05-15
     */
    public function edit_recommend_lottery()
    {

        $lottery_type = intval($_REQUEST['lottery_type']);

        $redis = initCacheRedis();
        $recommend_lottery_list = $redis -> HMGet('Config:recommend_lottery_list',['value']);

        $list = json_decode($recommend_lottery_list['value'], true);

        $lottery_info = [];
        foreach ($list as $k => $v) {
            if ($v['lottery_type'] == $lottery_type) {
                $lottery_info = $v;
                break;
            }
        }
        //关闭redis链接
        deinitCacheRedis($redis);

        include template('edit-recommend-lottery');
    }

    /**
     * 保存首页彩种信息
     * 2017-09-30 update
     */
    public function save_lottery () {

        $lottery_type = intval($_POST['lottery_type']);

        $redis = initCacheRedis();
        $index_lottery_list = $redis -> HMGet('Config:index_lottery_list',['value']);

        $list = json_decode($index_lottery_list['value'], true);

        $update_key = null;
        $sort_arr = [];
        foreach ($list as $k => $v) {
            //定位需要更新的数据对象的下标索引
            if ($v['lottery_type'] == $lottery_type) {
                $update_key = $k;
            }
            //将其他的排序值，存入缓存数组 $sort_arr ，用于判断是否有重复排序值存在
            else {
                $sort_arr[] = $v['sort'];
            }
        }

        $sort = intval($_POST['sort']) . '';
        if (in_array($sort, $sort_arr)) {
            echo json_encode(['rt' => 770001, 'msg' => '已存在相同排序值，请重新输入']);
            exit;
        }

        $log_remark = $this->admin['username'] . "--" . date('Y-m-d H:i:s').'--首页彩种设置';
        $logM = false;
        if($list[$update_key]['pic_url'] != $_POST['hi_pic_url']) {
            $list[$update_key]['pic_url'] = $_POST['hi_pic_url'];
            $log_remark .= '--修改移动端彩种图片';
            $logM = true;
        }
        if($list[$update_key]['pic_url_pc'] != $_POST['hi_pic_url_pc']) {
            $list[$update_key]['pic_url_pc'] = $_POST['hi_pic_url_pc'];
            $log_remark .= '--修改PC端彩种图片';
            $logM = true;
        }
        if($list[$update_key]['pic_url_pc_logo'] != $_POST['hi_pic_url_pc_logo']) {
            $list[$update_key]['pic_url_pc_logo'] = $_POST['hi_pic_url_pc_logo'];
            $log_remark .= '--修改PC端彩种LOGO';
            $logM = true;
        }
        $logM && admin_operation_log($this->admin['userid'], 110, $log_remark);

        $list[$update_key]['article_id'] = $_POST['article_id'];
        $list[$update_key]['pic_type'] = $_POST['pic_type'];
        $list[$update_key]['is_show'] = $_POST['is_show'];
        $list[$update_key]['sort'] = $_POST['sort'];

        //玩法简介
        $list[$update_key]['play_intro'] = $_POST['play_intro'];

        //星级评价
        $list[$update_key]['star_level'] = $_POST['star_level'];

        $new_list = json_encode($list, JSON_UNESCAPED_UNICODE);

        $this->db->update('un_config', ['value' => $new_list], ['nid' => 'index_lottery_list']);

        //保存后更新redis缓存
        $this->refreshRedis('config', 'all');

        //关闭redis链接
        deinitCacheRedis($redis);

        echo json_encode(['rt' => 0]);
    }

    /**
     * 保存首页热门推荐彩种信息
     * 2018-05-15
     */
    public function save_recommend_lottery()
    {

        $lottery_type = intval($_POST['lottery_type']);

        $redis = initCacheRedis();
        $recommend_lottery_list = $redis -> HMGet('Config:recommend_lottery_list',['value']);

        $list = json_decode($recommend_lottery_list['value'], true);

        $update_key = null;
        $sort_arr = [];
        foreach ($list as $k => $v) {
            //定位需要更新的数据对象的下标索引
            if ($v['lottery_type'] == $lottery_type) {
                $update_key = $k;
            }
            //将其他的排序值，存入缓存数组 $sort_arr ，用于判断是否有重复排序值存在
            else {
                $sort_arr[] = $v['sort'];
            }
        }

        $sort = intval($_POST['sort']) . '';
        if (in_array($sort, $sort_arr)) {
            echo json_encode(['rt' => 880001, 'msg' => '已存在相同排序值，请重新输入']);
            exit;
        }

        $log_remark = $this->admin['username'] . "--" . date('Y-m-d H:i:s').'--热门彩种设置';
        $logM = false;
        if($list[$update_key]['h5_pic'] != $_POST['hi_h5_pic']) {
            $list[$update_key]['h5_pic'] = $_POST['hi_h5_pic'];
            $log_remark .= '--修改移动端彩种图片';
            $logM = true;
        }
        if($list[$update_key]['pc_pic'] != $_POST['hi_pc_pic']) {
            $list[$update_key]['pc_pic'] = $_POST['hi_pc_pic'];
            $log_remark .= '--修改PC端彩种图片';
            $logM = true;
        }
        $logM && admin_operation_log($this->admin['userid'], 110, $log_remark);
        $list[$update_key]['sort'] = $_POST['sort'];
        $list[$update_key]['is_recommend'] = $_POST['is_recommend'];
        $list[$update_key]['lottery_type'] = $_POST['lottery_type'];

        //取出是否推荐一列，统计个数
        $is_recommend_arr = array_column($list, 'is_recommend');
        $is_recommend_arr = array_filter($is_recommend_arr);

        $is_recommend_count = count($is_recommend_arr);
        if ($is_recommend_count > 5) {
            echo json_encode(['rt' => 880002, 'msg' => '热门彩种推荐个数不能超过5个，请重新设置']);
            exit;
        }

        $new_list = json_encode($list, JSON_UNESCAPED_UNICODE);

        $this->db->update('un_config', ['value' => $new_list], ['nid' => 'recommend_lottery_list']);

        //保存后更新redis缓存
        $this->refreshRedis('config', 'all');

        //关闭redis链接
        deinitCacheRedis($redis);

        echo json_encode(['rt' => 0]);
    }

    /**
     * 首页虚拟数据配置
     * 2017-09-30
     */
    public function index_data_setting () {


        //获取配置信息
        $config_nid = array(
            0 => 'Config:100001', //已为用户赚取元宝总数
            1 => 'Config:100002', //回扣返水赚钱率
            2 => 'Config:100003', //注册用户总数
        );
        $config = array();
        $redis = initCacheRedis();
        foreach ($config_nid as $v){
            $GameConfig = $redis -> HMGet($v,array('nid','name','value'));
            $config[$GameConfig['nid']] = $GameConfig['value'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        include template('index-data-setting');
    }


    /**
     * 首页虚拟数据保存
     * 2017-09-30
     */
    public function save_index_data () {

        $conf_100001 = floatval($_POST['conf_100001']);
        $conf_100002 = floatval($_POST['conf_100002']);
        $conf_100003 = floatval($_POST['conf_100003']);

        //FIX-ME 语句待优化
        $this->db->update('un_config', ['value' => $conf_100001], ['nid' => '100001']);
        $this->db->update('un_config', ['value' => $conf_100002], ['nid' => '100002']);
        $this->db->update('un_config', ['value' => $conf_100003], ['nid' => '100003']);

        $redis = initCacheRedis();
        //保存后更新redis缓存
        $this->refreshRedis('config', 'all');

        //关闭redis链接
        deinitCacheRedis($redis);

        echo json_encode(array('rt' => 1));
    }


    /**
     * 上传图片
     * @method GET
     * @return json
     */
    public function uploadImg() {
        $error = [];
        $file = [];

        if(isset($_FILES['file'])) $name = 'file';
        if(isset($_FILES['file_1'])) $name = 'file_1';
        $pathKey = [
            'pc_logo' => 'pc_logo.png',
            'pc_home_pic' => 'shouji.png',
            'qrcode_pic' => 'app_download.png',
            'pc_two_home_pic' => 'zhanshi-you.png',
            'pc_three_home_pic' => 'Phone2.png',
            'pc_focus_qrcode' => 'weixing-ma.jpg',

            'app_logo' => 'top_wap.png',
            'app_two_home_pic' => 'sj1.png',
            'app_three_home_pic' => 'sj2.png',
            'app_four_home_pic' => 'sj3.png',
            'app_five_home_pic' => 'sj4.png',
            'app_six_home_pic' => 'sj5.png',
            'app_seven_home_pic' => 'sj6.png',
            'app_install_tips_a' => 'q1.png',
            'app_install_tips_b' => 'q2.png',
            'app_install_tips_c' => 'q3.png',
            'app_install_tips_d' => 'q4.png',
            'app_install_tips_e' => 'q5.png',
            'app_install_tips_f' => 'q6.png',
        ];
        if(isset($_FILES['pc_logo'])) $name = 'pc_logo';
        if(isset($_FILES['pc_home_pic'])) $name = 'pc_home_pic';
        if(isset($_FILES['qrcode_pic'])) $name = 'qrcode_pic';
        if(isset($_FILES['pc_two_home_pic'])) $name = 'pc_two_home_pic';
        if(isset($_FILES['pc_three_home_pic'])) $name = 'pc_three_home_pic';
        if(isset($_FILES['pc_focus_qrcode'])) $name = 'pc_focus_qrcode';
        if(isset($_FILES['app_logo'])) $name = 'app_logo';
        if(isset($_FILES['app_two_home_pic'])) $name = 'app_two_home_pic';
        if(isset($_FILES['app_three_home_pic'])) $name = 'app_three_home_pic';
        if(isset($_FILES['app_four_home_pic'])) $name = 'app_four_home_pic';
        if(isset($_FILES['app_five_home_pic'])) $name = 'app_five_home_pic';
        if(isset($_FILES['app_six_home_pic'])) $name = 'app_six_home_pic';
        if(isset($_FILES['app_seven_home_pic'])) $name = 'app_seven_home_pic';
        if(isset($_FILES['app_install_tips_a'])) $name = 'app_install_tips_a';
        if(isset($_FILES['app_install_tips_b'])) $name = 'app_install_tips_b';
        if(isset($_FILES['app_install_tips_c'])) $name = 'app_install_tips_c';
        if(isset($_FILES['app_install_tips_d'])) $name = 'app_install_tips_d';
        if(isset($_FILES['app_install_tips_e'])) $name = 'app_install_tips_e';
        if(isset($_FILES['app_install_tips_f'])) $name = 'app_install_tips_f';

        $file = $_FILES[$name];
        $FileName = '';
        if(array_key_exists($name, $pathKey)) $FileName = $pathKey[$name];

        if ($file['error'] > 0) {
            jsonReturn(array('status' => 200000, 'data' => '图片上传失败'));
        } else {
            if ($file['size'] > 600 * 1024) { // 图片大于600KB
                jsonReturn(array('status' => 200001, 'data' => '图片大小超过了600KB，上传失败'));
            } else {
                $suffix = '';
                switch ($file['type']) {
                    case 'image/gif':
                        $suffix = 'gif';
                        break;
                    case 'image/jpeg':
                    case 'image/pjpeg':
                        $suffix = 'jpg';
                        break;
                    case 'image/bmp':
                        $suffix = 'bmp';
                        break;
                    case 'image/png':
                    case 'image/x-png':
                        $suffix = 'png';
                        break;
                    default:
                        jsonReturn(array('status' => 200001, 'data' => '图片格式不正确'));
                }

                !$FileName && $FileName = md5(time()) . "." . $suffix;

                $path = $this->getAvatarUrl($FileName, 0);
                if (!move_uploaded_file($file['tmp_name'], $path)) {
                    jsonReturn(array('status' => 200001, 'data' => '图片上传失败'));
                }
//                $data = $r_path?$r_path:"/". C('upfile_path') . '/index_lottery/' . $FileName;
//                jsonReturn(array('status' => 0, 'data' => $data));
                jsonReturn(array('status' => 0, 'data' => "/" . C('upfile_path') . '/index_lottery/' . $FileName));
            }
        }

    }

    private function getAvatarUrl($avatarFileName, $isRand = 1) {
        if (empty($avatarFileName)) {
            return '';
        }
        $avatarUrl = S_ROOT . C('upfile_path') . '/index_lottery/';
        if ($isRand) {
            $avatarUrl .= ('?rand=' . time());
        }
        if (!file_exists($avatarUrl)) {
            @mkdir($avatarUrl, 0777, true);
        }

        return $avatarUrl . $avatarFileName;
    }

    public  function quickBetSet(){
        $data = $_REQUEST;
        unset($data['m'],$data['c'],$data['a']);
        if($data['do']==1){
            lg('quick_bet_set','操作人::'.encode($this->admin).',接收到的数据::'.encode($data['data']));
            foreach ($data['data'] as $k=>$v){
                if(!preg_match("/^\d*$/",$v)){
                    $msg =array(
                        'code'=>1,
                        'msg'=>'请输入合法数据!',
                    );
                    echo encode($msg);
                    return false;
                    break;
                }

                //转化成整数
                $data['data'][$k]=(int)$v;

                if($v<1 || $v > 9999){
                    $msg =array(
                        'code'=>1,
                        'msg'=>'请输入1~9999值!',
                    );
                    echo encode($msg);
                    return false;
                    break;
                }

                if($v>10) {
                    if ($v < 100) {
                        if ($v % 10 != 0) {
                            $msg = array(
                                'code' => 1,
                                'msg' => '大于10，小于100，请输入10的倍数!',
                            );
                            echo encode($msg);
                            return false;
                            break;
                        }
                    }
                }

                if($v>99 && $v<1000){
                    if($v%100!=0){
                        $msg =array(
                            'code'=>1,
                            'msg'=>'大于100，小于1000，请输入100的倍数!',
                        );
                        echo encode($msg);
                        return false;
                        break;
                    }
                }

                if($v>999 && $v<10000){
                    if($v%1000!=0){
                        $msg =array(
                            'code'=>1,
                            'msg'=>'大于1000，请输入1000的倍数!',
                        );
                        echo encode($msg);
                        return false;
                        break;
                    }
                }
            }
            $len = count($data['data']);
            if($len<1){
                $msg =array(
                    'code'=>1,
                    'msg'=>'至少要一个值!',
                );
                echo encode($msg);
                return false;
            }

            if($len>5){
                $msg =array(
                    'code'=>1,
                    'msg'=>'最多只能设置5个值!',
                );
                echo encode($msg);
                return false;
            }

            //判断是否有重复值
            $ndata = array_unique($data['data']);
            if(count($ndata) != $len){
                $msg =array(
                    'code'=>1,
                    'msg'=>'不允许有重复值!',
                );
                echo encode($msg);
                return false;
            }

            sort($data['data']);
            $sql = "UPDATE `un_config` SET `value`='".encode($data['data'])."' WHERE nid='quick_bet_set'";
            $this->db->query($sql);
            $this->refreshRedis('config', 'all'); //刷新缓存
            $msg =array(
                'code'=>0,
                'msg'=>'更新成功!',
            );
            echo encode($msg);
            return true;
        }else{
            $redis = initCacheRedis();
//            $bettingIds = $redis->lRange("DictionaryIds11", 0, -1);
//            $betting = array();
//            foreach ($bettingIds as $v){
//                $res = $redis->hMGet("Dictionary11:".$v,array('value'));
//                $betting[] = $res['value'];
//            }
//            deinitCacheRedis($redis);
//            sort($betting);
//            $list = $betting;
            $re = $redis->hget('Config:quick_bet_set','value');
            $list = decode($re);
            sort($list);
            include template('quick_bet_set');
        }
    }

    /**
     * 文章栏目列表（类别）
     */
    public function listArticleColumn()
    {
        $articeColumnList = [];

        $articeColumnJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'article_column'");
        if (!empty($articeColumnJson)) {
            $articeColumnList = json_decode($articeColumnJson['value'],true);
        }else {
            $insert = [
                'nid'   => 'article_column',
                'value' => '',
                'name'  => '文章栏目',
                'desc'  => '文章栏目分类,newType:新增栏目类型（新增时使用），status：状态，name：栏目名称，type：栏目类型',
                'is_file' => ''
            ];

            $value['status']  = 1;
            $value['newType'] = 7;
            $value['column']  = [
                ['name' => '关于我们', 'status' => 1, 'type' => 1],
                ['name' => '联系我们', 'status' => 1, 'type' => 2],
                ['name' => '取款帮助', 'status' => 1, 'type' => 3],
                ['name' => '存款帮助', 'status' => 1, 'type' => 4],
                ['name' => '常见问题', 'status' => 1, 'type' => 5],
                ['name' => '彩种玩法', 'status' => 1, 'type' => 6],
                ['name' => '防劫持教程', 'status' => 1, 'type' => 7],
            ];

            $insert['value'] = json_encode($value, JSON_UNESCAPED_UNICODE);
            $ret = $this->db->insert('un_config', $insert);
            if ($ret) {
                $articeColumnList = $value;
            }
        }

        include template('list-article-column');
    }

    /**
     *展示添加文章栏目（类别）
     */
    public function addArticleColumn()
    {
        include template('add-article-column');
    }

    /**
     *删除文章栏目（类别）
     */
    public function deleteArticleColumn()
    {
        $value = '';
        $type = $_POST['type'];

        if (!is_numeric($type) || $type <= 7) {
            echo json_encode(['code' => 0, 'msg' => '栏目类型错误！']);
            return;
        }
        $articeList = $this->db->getone("SELECT `id` FROM `un_article` WHERE `type` = " . $type);
        if (!empty($articeList)) {
            echo json_encode(['code' => 0, 'msg' => '该栏目类型下还有文章存在!']);
            return;
        }

        $articeColumnJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'article_column'");
        if (empty($articeColumnJson)) {
            echo json_encode(['code' => 0, 'msg' => '删除失败！']);
            return;
        }

        $articeColumnList = json_decode($articeColumnJson['value'],true);
        foreach ($articeColumnList['column'] as $k => $v) {
            if ($v['type'] == $type) {
                unset($articeColumnList['column'][$k]);
                break;
            }
        }

        $value = json_encode($articeColumnList, JSON_UNESCAPED_UNICODE);
        $ret = $this->db->update('un_config', ['value' => $value], ['nid' => 'article_column']);

        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '删除成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '删除失败！']);
        }

        return;
    }

    /**
     *修改文章栏目（类别）状态
     */
    public function setArticleColumnStatus()
    {
        $value = '';
        $type = $_POST['type'];
        $status = $_POST['status'];

        if (!is_numeric($type)) {
            echo json_encode(['code' => 0, 'msg' => '栏目类型错误！']);
            return;
        }
        if ($status != 1) {
            $status = 0;
        }

        $articeColumnJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'article_column'");
        if (empty($articeColumnJson)) {
            echo json_encode(['code' => 0, 'msg' => '修改失败！']);
            return;
        }

        $articeColumnList = json_decode($articeColumnJson['value'],true);
        foreach ($articeColumnList['column'] as $k => $v) {
            if ($v['type'] == $type) {
                $articeColumnList['column'][$k]['status'] = $status;
                break;
            }
        }

        $value = json_encode($articeColumnList, JSON_UNESCAPED_UNICODE);
        $ret = $this->db->update('un_config', ['value' => $value], ['nid' => 'article_column']);

        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '修改成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '修改失败！']);
        }

        return;
    }

    /**
     * 添加文章栏目（类别）
     */
    public function doAddArticleColumn()
    {
        $value = [];
        $name = $_POST['name'];
        $status = $_POST['status'];

        if ($status != 1) {
            $status = 0;
        }

        if (empty($name)) {
            echo json_encode(['code' => 0, 'msg' => '栏目名称不能为空']);
            return;
        }

        $articeColumnJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'article_column'");
        $articeColumnList = json_decode($articeColumnJson['value'],true);
        $articeColumnList['column'][] = [
            'name' => $name,
            'status' => $status,
            'type' => $articeColumnList['newType']
        ];
        $articeColumnList['newType'] += 1;

        $value = json_encode($articeColumnList, JSON_UNESCAPED_UNICODE);
        $ret = $this->db->update('un_config', ['value' => $value], ['nid' => 'article_column']);

        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '栏目添加成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '栏目添加失败！']);
        }

        return;
    }

    /**
     * 文章列表
     */
    public function listArticle()
    {
        $articeList = [];
        $articeColumnList = [];

        $articeColumnJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'article_column'");
        if (!empty($articeColumnJson)) {
            $articeColumnList = json_decode($articeColumnJson['value'],true);
        }

        $articeList = $this->db->getall("SELECT ar.*, ad.`username` FROM `un_article` ar LEFT JOIN `un_admin` ad ON ar.`role_id` = ad.`userid`");
        if (!empty($articeList)) {
            foreach ($articeList as $k => $v) {
                foreach ($articeColumnList['column'] as $ka => $va) {
                    if ($va['type'] == $v['type']) {
                        //启用是否有效
                        if ($va['type'] < 6) {
                            $articeList[$k]['show'] = 0;
                        }else {
                            $articeList[$k]['show'] = 1;
                        }
                        $articeList[$k]['type'] = $va['name'];
                        break;
                    }
                }
                $articeList[$k]['edit_time']   = date('Y-m-d H:i:s',$articeList[$k]['edit_time']);
                $articeList[$k]['create_time'] = date('Y-m-d H:i:s',$articeList[$k]['create_time']);
            }
        }

        include template('list-article');
    }

    /**
     * 添加文章
     */
    public function doAddArticle()
    {
        $type = $_POST['type'];
        $status = $_POST['status'];
        $title = $_POST['title'];
        $content = $_POST['content'];

        if ($status != 1) {
            $status = 0;
        }

        if (empty($title) || empty($content) || empty($type)) {
            echo json_encode(['code' => 0, 'msg' => '添加数据为空']);
            return;
        }

        $insert = [
            'status' => $status,
            'type'  => $type,
            'title' => $title,
            'content' => $content,
            'role_id' => $this->admin['userid'],
            'edit_time' => time(),
            'create_time' => time()
        ];

        $ret = $this->db->insert('un_article', $insert);

        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '添加成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '添加失败！']);
        }

        return;
    }


    /**
     *展示添加文章栏目（类别）
     */
    public function addArticle()
    {
        $articeColumnList = [];

        $articeColumnJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'article_column'");
        if (!empty($articeColumnJson)) {
            $articeColumnList = json_decode($articeColumnJson['value'],true);
        }

        include template('add-article');
    }

    /**
     *删除文章
     */
    public function deleteArticle()
    {
        $id = $_POST['id'];

        if (!is_numeric($id)) {
            echo json_encode(['code' => 0, 'msg' => '文章ID错误！']);
            return;
        }

        $artice = $this->db->getone("SELECT `id` FROM `un_article` WHERE `id` = " . $id);
        if (empty($artice)) {
            echo json_encode(['code' => 0, 'msg' => '文章不存在，删除失败！']);
            return;
        }

        $ret = $this->db->exec("DELETE FROM `un_article` WHERE `id` = " . $id);

        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '删除成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '删除失败！']);
        }

        return;
    }

    /**
     *修改文章状态
     */
    public function setArticleStatus()
    {
        $value = '';
        $id = $_POST['id'];
        $status = $_POST['status'];

        if (!is_numeric($id)) {
            echo json_encode(['code' => 0, 'msg' => '文章ID错误！']);
            return;
        }

        if ($status != 1) {
            $status = 0;
        }

        $artice = $this->db->getone("SELECT `id` FROM `un_article` WHERE `id` = " . $id);
        if (empty($artice)) {
            echo json_encode(['code' => 0, 'msg' => '文章不存在，删除失败！']);
            return;
        }

        $ret = $this->db->update('un_article', ['status' => $status], ['id' => $id]);

        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '修改成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '修改失败！']);
        }

        return;
    }

    /**
     *展示添加文章栏目（类别）
     */
    public function editArticle()
    {
        $id = $_REQUEST['id'];
        $articeColumnList = [];

        $article = $this->db->getone("SELECT * FROM `un_article` WHERE `id` = " . $id);

        $articeColumnJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'article_column'");
        if (!empty($articeColumnJson)) {
            $articeColumnList = json_decode($articeColumnJson['value'],true);
        }

        include template('edit-article');
    }

    /**
     *浏览添加文章
     */
    public function browseArticle()
    {
        $id = $_REQUEST['id'];
        $articeColumnList = [];

        $article = $this->db->getone("SELECT * FROM `un_article` WHERE `id` = " . $id);
        if (!empty($article)) {
            $article['content'] =  htmlspecialchars_decode($article['content']);
        }

        $articeColumnJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'article_column'");
        if (!empty($articeColumnJson)) {
            $articeColumnList = json_decode($articeColumnJson['value'],true);
        }

        include template('browse-article');
    }

    /**
     * 添加文章
     */
    public function doEditArticle()
    {
        $id = $_POST['id'];
        $type = $_POST['type'];
        $status = $_POST['status'];
        $title = $_POST['title'];
        $content = $_POST['content'];


        if ($status != 1) {
            $status = 0;
        }

        if (empty($title) || empty($content) || empty($type)) {
            echo json_encode(['code' => 0, 'msg' => '修改数据不能为空']);
            return;
        }

        $artice = $this->db->getone("SELECT `id` FROM `un_article` WHERE `id` = " . $id);
        if (empty($artice)) {
            echo json_encode(['code' => 0, 'msg' => '文章不存在，修改失败！']);
            return;
        }

        $updateData = [
            'status' => $status,
            'type'  => $type,
            'title' => $title,
            'content' => $content,
            'role_id' => $this->admin['userid'],
            'edit_time' => time(),
        ];

        $ret = $this->db->update('un_article', $updateData, ['id' => $id]);

        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '修改成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '修改失败！']);
        }

        return;
    }

    /**
     * app帮助文章列表
     */
    public function listHelper()
    {
        $where = $_REQUEST; //搜索条件
        unset($where['m']);
        unset($where['c']);
        unset($where['a']);
        $helperList = [];
        $pagesize = 20;
        
        $helperCnt = $this->db->getone("SELECT count(id) as cnt FROM `un_article_helper`");
        $url = '?m=admin&c=message&a=listHelper';
        $page = new pages($helperCnt['cnt'], $pagesize, $url,$where);
        $show = $page->show();

        $page_start = $page->offer;
        $helperList = $this->db->getall("SELECT ah.*, ad.`username` FROM `un_article_helper` ah LEFT JOIN `un_admin` ad ON ah.`role_id` = ad.`userid` LIMIT {$page_start},{$pagesize}");
        if (!empty($helperList)) {
            foreach ($helperList as $k => $v) {
                $helperList[$k]['edit_time']   = date('Y-m-d H:i:s',$v['edit_time']);
                $helperList[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
            }
        }
    
        include template('list-helper');
    }
    
    /**
     *展示添加文章
     */
    public function addHelper()
    {
        include template('add-helper');
    }
    
    /**
     * 添加帮助文章
     */
    public function doAddHelper()
    {
        $status = $_POST['status'];
        $title = $_POST['title'];
        $content = $_POST['content'];
    
        if ($status != 1) {
            $status = 0;
        }
    
        if (empty($title) || empty($content)) {
            echo json_encode(['code' => 0, 'msg' => '添加数据为空']);
            return;
        }
    
        $insert = [
            'status' => $status,
            'title' => $title,
            'content' => $content,
            'role_id' => $this->admin['userid'],
            'edit_time' => time(),
            'create_time' => time()
        ];
    
        $ret = $this->db->insert('un_article_helper', $insert);
    
        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '添加成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '添加失败！']);
        }
    
        return;
    }
    
    /**
     *修改帮助文本
     */
    public function editHelper()
    {
        $id = $_REQUEST['id'];
    
        $helperData = $this->db->getone("SELECT * FROM `un_article_helper` WHERE `id` = " . $id);
    
        include template('edit-helper');
    }
    
    /**
     * 保存修改文章
     */
    public function doEditHelper()
    {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $title = $_POST['title'];
        $content = $_POST['content'];
    
        if ($status != 1) {
            $status = 0;
        }
    
        if (empty($title) || empty($content)) {
            echo json_encode(['code' => 0, 'msg' => '修改数据不能为空']);
            return;
        }
    
        $helperData = $this->db->getone("SELECT `id` FROM `un_article_helper` WHERE `id` = " . $id);
        if (empty($helperData)) {
            echo json_encode(['code' => 0, 'msg' => '文章不存在，修改失败！']);
            return;
        }
    
        $updateData = [
            'status' => $status,
            'title' => $title,
            'content' => $content,
            'role_id' => $this->admin['userid'],
            'edit_time' => time(),
        ];
    
        $ret = $this->db->update('un_article_helper', $updateData, ['id' => $id]);
    
        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '修改成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '修改失败！']);
        }
    
        return;
    }
    
    /**
     *删除帮助文章
     */
    public function deleteHelper()
    {
        $id = $_POST['id'];
    
        if (!is_numeric($id)) {
            echo json_encode(['code' => 0, 'msg' => '文章ID错误！']);
            return;
        }
    
        $helperData = $this->db->getone("SELECT `id` FROM `un_article_helper` WHERE `id` = " . $id);
        if (empty($helperData)) {
            echo json_encode(['code' => 0, 'msg' => '文章不存在，删除失败！']);
            return;
        }
    
        $ret = $this->db->exec("DELETE FROM `un_article_helper` WHERE `id` = " . $id);
    
        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '删除成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '删除失败！']);
        }
    
        return;
    }
    
    /**
     *修改帮助文章状态
     */
    public function setHelperStatus()
    {
        $value = '';
        $id = $_POST['id'];
        $status = $_POST['status'];
    
        if (!is_numeric($id)) {
            echo json_encode(['code' => 0, 'msg' => '文章ID错误！']);
            return;
        }
    
        if ($status != 1) {
            $status = 0;
        }
    
        $helperData = $this->db->getone("SELECT `id` FROM `un_article_helper` WHERE `id` = " . $id);
        if (empty($helperData)) {
            echo json_encode(['code' => 0, 'msg' => '文章不存在，删除失败！']);
            return;
        }
    
        $ret = $this->db->update('un_article_helper', ['status' => $status], ['id' => $id]);
    
        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '修改成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '修改失败！']);
        }
    
        return;
    }
    
    /**
     * 用户反馈信息列表
     */
    public function listFeedback()
    {
        $where = $_REQUEST; //搜索条件
        unset($where['m']);
        unset($where['c']);
        unset($where['a']);
        $helperList = [];
        $pagesize = 20;

        $helperCnt = $this->db->getone("SELECT count(id) as cnt FROM `un_feedback`");
        $url = '?m=admin&c=message&a=listFeedback';
        $page = new pages($helperCnt['cnt'], $pagesize, $url,$where);
        $show = $page->show();
    
        $page_start = $page->offer;
        $feedbackList = $this->db->getall("SELECT f.*, u.username, ad.`username` as admin_name FROM `un_feedback` f 
                                        LEFT JOIN `un_user` u ON f.user_id = u.id
                                        LEFT JOIN `un_admin` ad ON f.`role_id` = ad.`userid` order by f.id desc LIMIT {$page_start},{$pagesize}");
        if (!empty($feedbackList)) {
            foreach ($feedbackList as $k => $v) {
                $feedbackList[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
                if ($v['type'] == 2) {
                    $feedbackList[$k]['type'] = '投诉';
                }else {
                    $feedbackList[$k]['type'] = '建议';
                }
                
                $feedbackList[$k]['abstract'] = mb_substr($v['content'], 0, 10) . '...';
            }
        }
    
        include template('list-feedback');
    }
    
    /**
     *查看反馈信息
     */
    public function scanFeedback()
    {
        $id = $_REQUEST['id'];
    
        $feedbackData = $this->db->getone("SELECT f.id, f.type,f.content, f.cellphone,f.image_url,f.status,u.username FROM `un_feedback` f 
                                            LEFT JOIN `un_user` u ON f.user_id = u.id
                                            WHERE f.`id` = " . $id);
    
        if (!empty($feedbackData['image_url'])) {
            $feedbackData['image_url'] = json_decode($feedbackData['image_url'], true);
        }

        include template('scan-feedback');
    }
    
    /**
     *删除用户反馈信息
     */
    public function deleteFeedback()
    {
        $id = $_REQUEST['id'];
    
        if (!is_numeric($id)) {
            echo json_encode(['code' => 0, 'msg' => 'ID错误！']);
            return;
        }
    
        $helperData = $this->db->getone("SELECT `id`,image_url FROM `un_feedback` WHERE `id` = " . $id);
        if (empty($helperData)) {
            echo json_encode(['code' => 0, 'msg' => '反馈信息不存在，删除失败！']);
            return;
        }
    
        $ret = $this->db->exec("DELETE FROM `un_feedback` WHERE `id` = " . $id);
    
        if ($ret) {
            if (!empty($helperData['image_url'])) {
                $arrUrl = json_decode($helperData['image_url'], true);
                foreach ($arrUrl as $ka => $va) {
                    unlink(S_ROOT . ltrim($va,'/'));
                }
            }
            echo json_encode(['code' => 1, 'msg' => '删除成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '删除失败！']);
        }
    
        return;
    }
    
    /**
     *标记为已处理用户信息
     */
    public function setFeedbackStatus()
    {
        $value = '';
        $id = $_POST['id'];
        $status = $_POST['status'];
    
        if (!is_numeric($id)) {
            echo json_encode(['code' => 0, 'msg' => '反馈信息ID错误！']);
            return;
        }
        
        $ret = $this->db->update('un_feedback', ['status' => 1, 'role_id' => $this->admin['userid'], 'edit_time' => time()], ['id' => $id]);
    
        if ($ret) {
            echo json_encode(['code' => 1, 'msg' => '标记成功！']);
        }else {
            echo json_encode(['code' => 0, 'msg' => '标记失败！']);
        }

        return;
    }
    
    /**
     * app配置信息设置
     */
    public function appConfig()
    {
        $appJson = $this->db->getone("select * from un_config where nid = 'appConfig'");
        $appData = json_decode($appJson['value'], true);
        
        include template('app-config');
    }
    
    public function setAppConfig()
    {
        $data = $_REQUEST;

        $ret = $this->model->setAppConfig($data);
        
        if($ret['code'] == 1){
            $this->refreshRedis('config', 'all');
        }
        
        echo json_encode($ret);
        return;
    }
    public function PCPhotos()
    {
        $data = $_REQUEST;

        $ret = $this->model->PCPhotos($data);

        if($ret['code'] == 1){
            $this->refreshRedis('config', 'all');
        }

        echo json_encode($ret);
        return;
    }


    public function download_page_set_new() {
        $confKey = 'download_page_set';
        $isPost = getParame('ispost',0,0,'int');
        if($isPost) {
            $parameArr = ['platform_title', 'logo', 'website_org', 'pc_pic', 'mobile_pic', 'download_qrcode', 'pc_home_link', 'h5_home_link', 'download_link', 'desc_a1', 'desc_a2', 'desc_a3', 'desc_a4', 'show_pic1', 'show_desc1', 'show_pic2', 'show_desc2', 'show_pic3', 'show_desc3', 'desc_b', 'ico_pic1', 'ico_title1', 'ico_desc1', 'ico_pic2', 'ico_title2', 'ico_desc2', 'ico_pic3', 'ico_title3', 'ico_desc3', 'ico_pic4', 'ico_title4', 'ico_desc4', 'setup_pic1', 'setup_desc1', 'setup_pic2', 'setup_desc2', 'setup_pic3', 'setup_desc3', 'setup_pic4', 'setup_desc4', 'setup_pic5', 'setup_desc5', 'setup_pic6', 'setup_desc6', 'service_link', 'qq1', 'qq2', 'wx1', 'wx2', 'focus_qrcode', 'website_records'];
            foreach($parameArr as $v) {
                if(isset($_REQUEST[$v]))
                    $data[$v] = getParame($v,0);
            }

            $infos = $this->db->getone("select * from un_config where nid = '".$confKey."'");

            if($infos) {
                $infos['value'] = unserialize($infos['value']);
                if(!$infos['value']) $infos['value'] = [];
                $infos['value'] = serialize(array_merge($infos['value'], $data));
                $rows = $this->db->update("un_config", ['value'=>$infos['value']], ['id'=>$infos['id']]);
            }else {
                $confName = '下载页信息设置';
                $infos = [
                    'nid' => $confKey,
                    'value' => serialize($data),
                    'name' => $confName,
                ];
                $rows = $this->db->insert("un_config",$infos);
                $infos['id'] = $rows;
            }

            if($rows) {
                $redis = initCacheRedis();
                $redis->del('Config:'.$confKey);
                $redis->hMset('Config:'.$confKey, $infos);
                deinitCacheRedis($redis);
                $res['status'] = 0;
                $res['ret_msg'] = 'success';
            }else {
                $res['status'] = 1;
                $res['ret_msg'] = 'fail';
            }
            jsonReturn($res);
        }
        $redis = initCacheRedis();
        $infos = $redis->hMGet('Config:'.$confKey, ['value']);
        deinitCacheRedis($redis);
        $infos && $data = unserialize($infos['value']);
        include template('download_page_set_new');
    }

    public function download_page_set() {
        $type = getParame('type',1,'','int');
        if($type == 1) $confKey = 'app_download_page_info';
        if($type == 2) $confKey = 'pc_download_page_info';
        if($type == 3) $confKey = 'pc_photo_list';

        $isPost = getParame('ispost',0,0,'int');
        if($isPost) {
            $parameArr_1 = ['page_title', 'logo', 'qrcode_pic', 'download_link', 'home_link', 'two_home_pic', 'three_home_pic', 'four_home_pic', 'five_home_pic', 'six_home_pic', 'seven_home_pic', 'qq', 'wx', 'address', 'website_records','install_tips_a','install_tips_b','install_tips_c','install_tips_d','install_tips_e','install_tips_f'];
            $parameArr_2 = ['page_title','logo', 'qrcode_pic', 'home_link', 'home_pic', 'one_desc', 'two_home_pic', 'download_link','three_home_pic', 'three_desc', 'four_desc_title', 'four_desc_site', 'four_desc_banner', 'online_service_link','qq', 'wx', 'address', 'focus_qrcode','website_records'];
            $parameArr_3 = ['pc_photo_a', 'pc_photo_b', 'pc_photo_c', 'pc_photo_d'];

            if($type == 1) $parameArr = $parameArr_1;
            if($type == 2) $parameArr = $parameArr_2;
            if($type == 3) $parameArr = $parameArr_3;

            foreach ($parameArr as $v) {
                $data[$v] = getParame($v,0);

//                if($v == 'qq' || $v == 'wx') {
//                    $data[$v] = explode(',', $data[$v]);
//                }
            }

            $infos = $this->db->getone("select * from un_config where nid = '".$confKey."'");
            if($infos) {
                $infos['value'] = serialize($data);
                $rows = $this->db->update("un_config", ['value'=>$infos['value']], ['id'=>$infos['id']]);
            }else {
                $confName = $type == 1?'app下载首页信息设置':'pc下载首页信息设置';
                $infos = [
                    'nid' => $confKey,
                    'value' => serialize($data),
                    'name' => $confName,
                ];
                $rows = $this->db->insert("un_config",$infos);
                $infos['id'] = $rows;
            }

            if($rows) {
                $redis = initCacheRedis();
                $redis->del('Config:'.$confKey);
                $redis->hMset('Config:'.$confKey, $infos);
                deinitCacheRedis($redis);
                $res['status'] = 0;
                $res['ret_msg'] = 'success';
            }else {
                $res['status'] = 1;
                $res['ret_msg'] = 'fail';
            }
            jsonReturn($res);
        }


        $redis = initCacheRedis();
        $infos = $redis->hMGet('Config:'.$confKey, ['value']);
        deinitCacheRedis($redis);
        $infos && $data = unserialize($infos['value']);
//        is_array($data['qq']) && $data['qq'] = implode(',',$data['qq']);
//        is_array($data['wx']) && $data['wx'] = implode(',',$data['wx']);
        include template($confKey);
    }
}
