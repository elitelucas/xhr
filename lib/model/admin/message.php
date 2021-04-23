<?php

/**
 * Created by PhpStorm.
 * User: wangrui
 * Date: 2016/11/18
 * Time: 22:27
 * desc: 用户邦定银行信息
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class MessageModel extends CommonModel {

    protected $table = '#@_config';
    protected $table1 = '#@_room';
    protected $table2 = '#@_lottery_type';
    protected $table3 = '#@_message_conf';
    protected $table4 = '#@_gag';
    protected $table5 = '#@_user';
    protected $table6 = '#@_ttfl_cfg';
    protected $table7 = '#@_xitongshenghe';

    //敏感词列表
    public function listWords() {
        $sql = "select `nid`,`desc`,`value` from " . $this->table . " where nid = 'SensitiveWords'"; //  敏感词

        $rt = $this->db->getone($sql);
        return $rt;
    }

    //添加敏感词
    public function addWords($word) {
        $sql = "select `value` from " . $this->table . " where nid = 'SensitiveWords'"; //  敏感词

        $rt = $this->db->getone($sql);
        $value = explode(",", $rt['value']);
        if (in_array($word, $value)) {
            return -1;
        }
        return $this->db->update($this->table, array("value" => $rt['value'] . "," . $word), array("nid" => "SensitiveWords"));
    }

    public function delWords($keyWord) {
        $sql = "select `value` from " . $this->table . " where nid = 'SensitiveWords'"; //  敏感词
        $rt = $this->db->getone($sql);
        $keyList = explode(",", $rt['value']);
        foreach ($keyList as $key => $value) {
            if ($value == $keyWord) {
                unset($keyList[$key]);
            }
        }
        return $this->db->update($this->table, array("value" => implode(",", $keyList)), array("nid" => "SensitiveWords"));
    }

    //活动列表
    public function listActivity() {
        $sql = "select `value` from " . $this->table . " where nid = 'activity'"; //  活动

        $rt = $this->db->getone($sql);
        return $rt;
    }

    //添加活动
    public function addActivity($data) {
        $sql = "select `value` from " . $this->table . " where nid = 'activity'"; //  敏感词

        $rt = $this->db->getone($sql);

        $value = $rt['value'];
        $array = json_decode($value, true);


        if ($data['status'] == 1) {
            foreach ($array as $val) {
                if ($val['status'] == 1) {
                    return false;
                }
            }
        }
        $array[count($array)] = $data;
        $last = addslashes(json_encode($array));
        $sql = "update " . $this->table . " set value='{$last}' where nid = 'activity'";
        return $this->db->query($sql);
    }

    //修改活动
    public function upActivity($value) {
        $sql = "update " . $this->table . " set value='{$value}' where nid = 'activity'";
        return $this->db->query($sql);
    }

    //删除活动
    public function delActivity($data) {
        $sql = "select `value` from " . $this->table . " where nid = 'activity'"; //  敏感词

        $rt = $this->db->getone($sql);
        $value = $rt['value'];
        $array = json_decode($value, true);
        array_splice($array, $data['i'], 1);

        $last = addslashes(json_encode($array));
        $sql = "update " . $this->table . " set value='{$last}' where nid = 'activity'";
        return $this->db->query($sql);
    }

     //根据房间名和彩种查询房间列表
    public function room($data) {
        $room_name = $data['room_name'];
        $sql = "select * from " . $this->table1 . " where 1=1 ";
        if ($room_name != '') {
            $sql .= " and title like '%{$room_name}%'";
        }
        
        if (!empty($data['lottery_id']) && is_numeric($data['lottery_id'])) {
            $sql .= " and lottery_type = {$data['lottery_id']}";
        }
        
        $sql .= " order by status";

        return $this->db->getall($sql);
    }
    
    //房间图片上传
    public function addFile() {
        $file_name = date("YmdHis") . rand(100, 999);
        $file_all = "up_files/room/" . $file_name . ".png";
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $file_all)) {
            return "/" . $file_all;
        } else {
            return false;
        }
    }
    
    //房间图片上传
    public function addFiles($name) {
        $file_name = date("YmdHis") . rand(100, 999);
        $file_all = "up_files/room/" . $file_name . ".png";
        if (move_uploaded_file($_FILES[$name]["tmp_name"], $file_all)) {
            return "/" . $file_all;
        } else {
            return false;
        }
    }

    //房间信息上传
    public function addRoom($data) {
        return $this->db->insert($this->table1, $data);
    }

    //房间信息修改
    public function updateRoom($data, $where) {
        return $this->db->update($this->table1, $data, $where);
    }

    //房间删除
    public function delRoom($data) 
    {
        $sql="select * from un_room where id = {$data['id']}";
        $roomData = $this->db->getone($sql);

        if (!empty($roomData['uids'])) {
            $arruids = explode(',', $roomData['uids']);
            foreach ($arruids as $auid) {
                $sql = "SELECT `id`, `uids` FROM `un_room` WHERE `lottery_type` = " . $roomData['lottery_type'] . " AND FIND_IN_SET(" . $auid . ",uids)";
                $roomIds = $this->db->getall($sql);
                if (empty($roomIds) || count($roomIds) > 1) continue;
            
                $sql = "SELECT `user_id`, `lottery_ids` FROM `un_user_tree` WHERE `user_id` = " . $auid;
                $treeData = $this->db->getone($sql);
                if (empty($treeData) || empty($treeData['lottery_ids'])) continue;
            
                $treeData['lottery_ids'] = explode(',', $treeData['lottery_ids']);

                if (in_array($roomData['lottery_type'], $treeData['lottery_ids'])) {
                    $treeLotterIds = $treeData['lottery_ids'];
                    $tkey = array_search($roomData['lottery_type'], $treeLotterIds);
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
        
        return $this->db->delete($this->table1, $data);
    }

    //根据ID获取房间发布信息
    public function issueList($where) {
        return $this->db->getall("select * from " . $this->table3 . " where room_id={$where['id']}");
    }

    //根据ID获取房间信息
    public function roomInfo($where)
    {
        return $this->db->getone("select * from " . $this->table1 . " where id={$where['id']}");
    }
    
    //根据ID获取房间投资金额数据
    public function roomBettingInfo($roomId)
    {
        $arrData = [];
    
        $roomData = $this->db->getone("select * from " . $this->table1 . " where id={$roomId}");
        $roomData['upper'] = json_decode($roomData['upper'], true);

        /*
        if ($roomData['lottery_type'] == 1 || $roomData['lottery_type'] == 3) {
            $arrData['总注数'][] = ['data' => [$arrSetData['total_amount']], 'contact' => ['总注书'], 'remark' => '总注数'];
            foreach ($arrSetData['limit'] as $ka => $va) {
                if ($va['remark'] == '单点数字') {
                    $arrData['单点数字'][] = $va;
                }elseif (in_array($va['remark'], ['', '大小单双', '组合', '极大极小', ''])) {
                    $arrData['猜双面'][] = $va;
                }elseif(in_array($va['remark'], ['红', '绿', '蓝', '豹子', '对子', '正顺', '倒顺', '半顺', '乱顺'])) {
                    $arrData['特殊玩法'][] = $va;
                }
            }
        }
        */
    
        return $roomData;
        //return $this->db->getone("select * from " . $this->table1 . " where id={$where['id']}");
    }
    
    //获取私密房限制用户的id或username信息
    public function getUserInfo($struids) {
        return $this->db->getall("select `id`, `username` from " . $this->table5 . " where id in ({$struids})");
    }

    //房间限额修改
    public function oddsRecord($arr) {
        $sql = "select * from " . $this->table1 . " where id={$arr['id']}";
        $rt = $this->db->getone($sql);
        $value = json_decode($rt['upper'], true);

        $value['general_note'] = $arr['general_note'];
        $value['single_digit'] = $arr['single_digit'];
        $value['size_ds'] = $arr['size_ds'];
        $value['parts'] = $arr['parts'];
        $value['minimax'] = $arr['minimax'];
        $value['red'] = $arr['red'];
        $value['green'] = $arr['green'];
        $value['blue'] = $arr['blue'];
        $value['leo'] = $arr['leo'];
        $value['zhengshun'] = $arr['zhengshun'];
        $value['daoshun'] = $arr['daoshun'];
        $value['banshun'] = $arr['banshun'];
        $value['luanshun'] = $arr['luanshun'];
        $value['pair'] = $arr['pair'];

        return $this->db->update($this->table1, array("upper" => addslashes(json_encode($value))), array("id" => $rt['id']));
    }

    //逆向投注开关
    public function reverse($id) {
        return $this->db->getone("select reverse,lottery_type from " . $this->table1 . " where id = {$id}");
    }

    //逆向投注开关设置
    public function upReverse($data, $where) {
        return $this->db->update($this->table1, $data, $where);
    }

    //房间排序
    public function sortRoom($id, $sort) {
        return $this->db->update($this->table1, array("sort" => $sort), array("id" => $id));
    }

    //根据ID获取发布信息
    public function issueInfo($where) {
        return $this->db->getone("select * from " . $this->table3 . " where id={$where['id']}");
    }

    //发布信息内容修改
    public function addIssue($where, $data) {
        return $this->db->update($this->table3, $data, $where);
    }

    //审核记录
    public function addIssueAudit($data) {
        return $this->db->insert($this->table7, $data);
    }

    //发布信息状态修改
    public function upIssueState($data, $where) {
        return $this->db->update($this->table3, $data, $where);
    }

    //彩种列表
    public function lottyList() {
        $sql = "select id,name from " . $this->table2;
        return $this->db->getall($sql);
    }

    //提示音列表
    public function music() {
        $sql = "select `value` from " . $this->table . " where nid = 'musicTips'"; //  提示音

        $rt = $this->db->getone($sql);
        return $rt;
    }

    //新增提示音
    public function addMusic($data) {
        return $this->db->update($this->table, $data, array("nid" => "musicTips"));
    }

    //永久禁言设置
    public function talkever() {
        $rt = $this->db->getone("select value from " . $this->table . " where nid = 'banned'");
        return json_decode($rt['value'], true);
    }

    //禁言列表
    public function untalk($where) {
        $sql = "select g.id,g.gag_time,g.gag_reason,g.addtime,u.username from " . $this->table4 . " as g inner join " . $this->table5 . " as u on g.user_id = u.id"; //  禁言列表
        if ($where['username'] != "") {
            $sql .= " where u.username='{$where['username']}'";
        }

        $rt = $this->db->getall($sql);
        return $rt;
    }

    //新增禁言
    public function addTalk($data) {
        return $this->db->insert($this->table4, $data);
    }

    //永久禁言设置
    public function addTalkever($data) {
        return $this->db->update($this->table, array("value" => json_encode($data)), array("nid" => "banned"));
    }

    //删除禁言
    public function delTalk($where) {
        return $this->db->delete($this->table4, $where);
    }

    //获取用户ID
    public function getIdByName($name) {
        return $this->db->getone("select id from " . $this->table5 . " where username='{$name}'");
    }

    //禁言表查询用户
    public function getGag($id) {
        return $this->db->getone("select * from " . $this->table4 . " where user_id = $id");
    }

    //天天返利配置表初始化
    public function initTtfl() {
        $rt = $this->db->getone("select * from " . $this->table6 . " where nid = '100005'"); //天天返利
        if (empty($rt)) {
            $data = array(
                'start_time' => time(),
                'end_time' => time(),
                'addtime' => time(),
                'nid' => '100005'
            );
            $this->db->insert($this->table6, $data);
        }
    }

    //天天返利信息
    public function ttfl() {
        $main = $this->db->getone("select * from " . $this->table6 . " where nid = '100005' and main = 1");
        $list = $this->db->getall("select * from " . $this->table6 . " where nid = '100005' and main = 0 order by cz_cnt");
        foreach ($list as $key => $value) {
            if (!empty($value['range'])) {
                $arr = json_decode($value['range'], true);
                $e_money = array();
                foreach ($arr as $a) {
                    $e_money[] = $a['e_money'];
                }
                array_multisort($e_money, SORT_ASC, $arr);
                $list[$key]['range'] = $arr;
            }
        }
        $info['data'] = $main;
        $info['list'] = $list;
        return $info;
    }

    //天天返利修改
    public function upTTfl($data) {
        return $this->db->update($this->table6, $data, array("nid" => "100005", "main" => 1));
    }

    //天天返利添加
    public function addTTfl($data) {
        return $this->db->insert($this->table6, $data);
    }

    //返利配置一条记录
    public function dataTTfl($cnt) {
        return $this->db->getone("select * from un_ttfl_cfg where cz_cnt = {$cnt} and main = 0");
    }

    //返利配置范围是否重复
    public function uniqueTTfl($data, $post) {
        $isUnique = true;
        //按顺序排列之前的范围数组
        $e_money = array();
        foreach ($data as $a) {
            $e_money[] = $a['e_money'];
        }
        array_multisort($e_money, SORT_ASC, $data);

        $array = array();
        foreach ($data as $d) {
            $array[] = $d['s_money'];
            $array[] = $d['e_money'];
        }

        if ($post['e_money'] <= $array[0] || $post['s_money'] >= $array[count($array) - 1]) {
            $isUnique = false;
        }
        foreach ($array as $k => $v) {
            if ($k % 2 == 1) {
                if ($post['s_money'] >= $v && $post['e_money'] <= $array[$k + 1]) {
                    $isUnique = false;
                    break;
                }
            }
        }

        return $isUnique;
    }

    //返利配置更新一条记录
    public function updateTTfl($data, $cz_cnt) {
        return $this->db->update("un_ttfl_cfg", $data, array("cz_cnt" => $cz_cnt));
    }

    //天天返利删除
    public function delTTfl($where) {
        return $this->db->delete($this->table6, $where);
    }

    //天天返利修改
    public function upTTfls($data, $where) {
        return $this->db->update($this->table6, $data, $where);
    }

    public function addAgencySystemImg($img)
    {
        $rt = $this->db->getone("select * from " . $this->table . " where nid = 'AgencySystemImg'");
        if(empty($rt))
        {
            $data['nid'] = "AgencySystemImg";
            $data['value'] = $img;
            $data['name'] = "代理制度图片";
            $rows = $this->db->insert($this->table, $data);
        }
        else
        {
            $data['value'] = $img;
            $rows = $this->db->update($this->table, $data, ['nid'=>"AgencySystemImg"]);
        }

        if($rows > 0 || $rows !== false)
        {
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        }
        else
        {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

    public function addWebAgencySystemImg($img)
    {
        $rt = $this->db->getone("select * from " . $this->table . " where nid = 'AgencyWebSystemImg'");
        if(empty($rt))
        {
            $data['nid'] = "AgencyWebSystemImg";
            $data['value'] = $img;
            $data['name'] = "代理制度图片";
            $rows = $this->db->insert($this->table, $data);
        }
        else
        {
            $data['value'] = $img;
            $rows = $this->db->update($this->table, $data, ['nid'=>"AgencyWebSystemImg"]);
        }

        if($rows > 0 || $rows !== false)
        {
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        }
        else
        {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

    public function appVersionAct($post_data){

        $data['value'] = encode($post_data);
        $rt = $this->db->getone("select * from " . $this->table . " where nid = 'appVersion'");
        if(empty($rt)){
            $data['nid'] = "appVersion";
            $data['name'] = "App版本";
            $rows = $this->db->insert($this->table, $data);
        }else {
            $rows = $this->db->update($this->table, $data, ['nid'=>"appVersion"]);
        }

        if($rows > 0 || $rows !== false)
        {
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        }
        else
        {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }

        return $arr;
    }
    
    /**
     * 个人最多投注数字个数配置获取
     *
     */
    public function getLimitLottyer($type)
    {
        if (!in_array($type,['2','4','5','6','7','8','9','10','11','13','14'])){
            return false;
        }
        switch ($type) {
            case 2:
                $nid = "bjpk10_set_bet";
                break;
            case 4:
                $nid = "xyft_set_bet";
                break;
            case 5:
                $nid = "qcssc_set_bet";
                break;
            case 6:
                $nid = "sfc_set_bet";
                break;
            case 7:
                $nid = "lhc_set_bet";
                break;
            case 8:
                $nid = "jslhc_set_bet";
                break;
            case 9:
                $nid = "jssc_set_bet";
                break;
            case 10:
                $nid = "nn_set_bet";
                break;
            case 11:
                $nid = "ffc_set_bet";
                break;
            case 13:
                $nid = "hlsb_set_bet";
                break;
            case 14:
                $nid = "ffpk10_set_bet";
                break;
            default:
                break;
        }
//        $getSetData = $this->db->getone("select * from " . $this->table . " where `nid` = '{$nid}'");
        $redis = initCacheRedis();
        $getSetData = $redis->hgetall('Config:'.$nid);
        deinitCacheRedis($redis);
        if (empty($getSetData)) {
            return false;
        }
        
        return $getSetData;
    }
    
    /**
     * 个人最多投注数字个数设置
     *
     */
    public function setLimitLottyer($data, $type)
    {
        $nid = "";
        switch ($type) {
            case 2:
                $nid = 'bjpk10_set_bet';
                $name = 'pk10投注限制';
                break;
            case 4:
                $nid = 'xyft_set_bet';
                $name = '幸运飞艇投注限制';
                break;
            case 5:
                $nid = 'qcssc_set_bet';
                $name = '重庆时时彩投注限制';
                break;
            case 6:
                $nid = 'sfc_set_bet';
                $name = '三分彩投注限制';
                break;
            case 7:
                $nid = "lhc_set_bet";
                $name = '六合彩投注限制';
                break;
            case 8:
                $nid = "jslhc_set_bet";
                $name = '急速六合彩投注限制';
                break;
            case 9:
                $nid = "jssc_set_bet";
                $name = '急速赛车投注限制';
                break;
            case 10:
                $nid = "nn_set_bet";
                $name = '牛牛投注限制';
                break;
            case 11:
                $nid = 'ffc_set_bet';
                $name = '分分彩投注限制';
                break;
            case 13:
                $nid = 'hlsb_set_bet';
                $name = '欢乐骰宝投注限制';
                break;
            case 14:
                $nid = 'ffpk10_set_bet';
                $name = '分分PK10投注限制';
                break;
            default:
                break;
        }
        $myData['name'] = $name;
        if (in_array($type,['7','8','10'])) {
            $myData['value'] = json_encode($data,JSON_UNESCAPED_UNICODE);
        } else {
            $arrData = [
                'status' => $data['status'],
                'max'    => $data['max']
            ];
            $myData['value'] = json_encode($arrData);
        }
        $rs = $this->getLimitLottyer($type);
        if ($rs === false) {
            $myData['nid'] = $nid;
            $myData['desc'] = "status 0:关闭 1:开启";
            return $this->db->insert($this->table, $myData);
        } else {
            $where['nid'] = $nid;
            return $this->db->update($this->table, $myData, $where);
        }

    }
    
    /**
     * 文章列表
     * @param data array 文章信息，为空时获取全部文章列表
     */
    public function getContentList($data)
    {
        $ret = [];
        
        $sql = "SELECT `id`, `title`, `type`, `role`, `edit_time`, `create_time` FROM `un_content`";
        $ret = $this->db->getall($sql);
        
        return $ret;
    }
    
    //app配置信息
    public function setAppConfig($post_data)
    {
        $appConfig = [];
        if (empty($post_data)) {
            return ['code' => 0, 'msg' => '数据不能为空！'];
        }
        
        $config = $this->db->getone("select * from " . $this->table . " where nid = 'appConfig'");
        if(empty($config)){
            $data['nid'] = "appConfig";
            $data['name'] = "app端配置信息";
            $data['value'] = json_encode(['status' => 1]);
            $this->db->insert($this->table, $data);
            $appConfig['status'] = 1;
        }else {
            $appConfig = json_decode($config['value'], true);
        }

        $appConfig['sign_icon'] = $post_data['url_sign_icon'];
        $appConfig['task_icon'] = $post_data['url_task_icon'];
        $appConfig['bet_icon']  = $post_data['url_bet_icon'];
        $appConfig['recharge_icon'] = $post_data['url_recharge_icon'];
        
        $data['value'] = json_encode($appConfig, JSON_UNESCAPED_UNICODE);
        
        $rows = $this->db->update($this->table, $data, ['nid'=>"appConfig"]);
       
        if($rows){
            $arr['code'] = 1;
            $arr['msg'] = "操作成功";
        }else{
            $arr['code'] = 0;
            $arr['msg'] = "操作失败";
        }
    
        return $arr;
    }
}
