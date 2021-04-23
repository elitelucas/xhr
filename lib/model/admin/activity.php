<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/18
 * Time: 18:09
 * desc; 博饼活动表
 */
!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'model' . DS . 'common.php');

class ActivityModel extends CommonModel {

    protected $table = "#@_config";
    public $actType = [
        1 => '博饼活动',
        2 => '双旦活动',
        3 => '九宫格活动',
        4 => '福袋活动',
        5 => '刮刮乐活动',
    ];
    public $stateArr = [
        '1' => '开启',
        '2' => '停止',
    ];
    public $admin_id = 0;

    public function editBoBinConf($data){
        foreach ($data as $key=>$val){
            if(empty($val)){
                $data[$key] = 1;
            }
        }
        $list = $this->db->getone("select value,id from $this->table where nid = 'bo_bin'");
        if(empty($list)){
            $data['max_event_num'] = 1;
            $post_data = [
                'nid'=>'bo_bin',
                'value'=>json_encode($data),
                'name'=>'博饼活动通用配置',
                'desc'=>'1:表示显示，2表示隐藏'
            ];
            $row = $this->db->insert($this->table, $post_data);
        } else {
            $list['value'] = json_decode($list['value'],true);
            $list['value']['isUserName'] = $data['isUserName'];
            $list['value']['isTime'] = $data['isTime'];
            $list['value']['max_event_num'] = $data['event_num'] + 1;
            $row= $this->db->update($this->table, ['value'=>json_encode($list['value'])], ['id'=>$list['id']]);
        }
        $this->refreshRedis("config", "all");
        if($row > 0 || $row){
            $arr['code'] = "0";
            $arr['msg'] = "操作成功";
        } else {
            $arr['code'] = "-1";
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

    //添加博饼活动
    public function addBoBinConf($data){
        unset($data['m'],$data['c'],$data['a']);
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        $data['value'] = json_encode($data['value'],JSON_UNESCAPED_UNICODE);
        $data['rules_play'] = json_encode($data['rules_play'],JSON_UNESCAPED_UNICODE);
        $data['level_limit'] = json_encode($data['level_limit'],JSON_UNESCAPED_UNICODE);
        $data['add_time'] = time();
        if($data['start_time'] > $data['end_time']){
            $arr['code'] = "-1";
            $arr['msg'] = "开始时间必须大于结束时间";
            return $arr;
        }
        $this->db->query('BEGIN');//开启事务
        if(empty($data['id'])){
            if($data['state'] == 1){
                $state = $this->db->getone("select id from un_activity where activity_type = {$data['activity_type']} and state = 1");
                if (!empty($state)){
                    $arr['code'] = "-1";
                    $arr['msg'] = "已经有开启的活动，无法创建新的活动";
                    return $arr;
                }
            }

            $log_remark = '新增'.$this->actType[$data['activity_type']].'--活动名称:'.$data['title'].'--状态:'.$this->stateArr[$data['state']].'--期数:'.$data['event_num'];
            admin_operation_log($data['add_admin_id'], 120, $log_remark);

            $rows = $this->db->insert("#@_activity", $data);
        } else {
            $data['update_time'] = time();
            $rows = $this->db->update("#@_activity", $data, ['id'=>$data['id']]);
        }

        $list = $this->db->getone("select value,id from #@_config where nid = 'bo_bin'");
        if(empty($list)){
            $post_data = [
                'nid'=>'bo_bin',
                'value'=>json_encode(['isUserName'=>1,'isTime'=>1,'max_event_num'=> $data['event_num'] + 1]),
                'name'=>'博饼活动通用配置',
                'desc'=>'1:表示显示，2表示隐藏'
            ];
            $row1 = $this->db->insert("#@_config", $post_data);
        } else {
            $list['value'] = json_decode($list['value'],true);
            $list['value']['max_event_num'] = $data['event_num'] + 1;
            $row1 = $this->db->update("#@_config", ['value'=>json_encode($list['value'])], ['id'=>$list['id']]);
        }

        if(($rows > 0 || $rows !== false) || ($row1 > 0 || $row1 !== false)){
            $this->refreshRedis("config", "all");
            $arr['code'] = "0";
            $arr['msg'] = "操作成功";
            $this->db->query('COMMIT');//提交事务

        } else {
            $arr['code'] = "-1";
            $arr['msg'] = "操作失败";
            $this->db->query('ROLLBACK');//事务回滚
        }
        return $arr;
    }

    /**
     * 开启关闭活动
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-26 19:21
     * @param int $id 活动ID
     * @param int $state 状态
     * @param int $activity_type 活动类型
     * @return array
     *
     */
    public function stopOrStart($id, $state, $activity_type){
        $actInfo = $this->db->getone("select * from un_activity where id = $id");
        if(!$actInfo) {
            $arr['code'] = "-1";
            $arr['msg'] = "活动不存在";
            return $arr;
        }

        if($state == 2){
            $states = $this->db->getone("select id from un_activity where activity_type = $activity_type and state = 1");
            if (!empty($states)){
                $arr['code'] = "-1";
                $arr['msg'] = "已经有开启的活动，无法开启活动";
                return $arr;
            }
        }

        if($state == 1){
            $state = 2;
        } else {
            $state = 1;
        }
        $rows = $this->db->update("un_activity", ['state'=>$state], ['id'=>$id]);
        if($rows === false){
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        } else {
            $log_remark = $this->stateArr[$state].$this->actType[$activity_type].'--活动名称:'.$actInfo['title'].'--期数:'.$actInfo['event_num'];
            admin_operation_log($this->admin_id, 120, $log_remark);

            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        }
        return $arr;

    }

    //获取活动配置
    public function getActivityConfig($activity_type){
        $field = '*';
        $where = ['state'=>1,'activity_type'=>$activity_type];
        $list = $this->getOneCouponNew($field, $where, '', "un_activity");

        if(empty($list) || !($list['start_time'] < time() && $list['end_time'] > time())){
            return "";
        }
        $list['rules_play'] = json_decode($list['rules_play'],true);
        $list['level_limit'] = json_decode($list['level_limit'],true);
        $list['value'] = json_decode($list['value'],true);
        return $list;
    }

    //验证活动是否结束
    public function checkActivityState($where){
        $rs = false;
        $row = $this->getOneCouponNew($field = 'end_time,id', $where, $order = '', "un_activity");
        if(!empty($row)){
            if($row['end_time'] < time()){
                $rs = $this->db->update("#@_activity", ['state'=>2], ['id'=>$row['id']]);
                if ($where['activity_type'] == 1) {
                    //3 => 博饼活动
                    $act_type = 3;
                } elseif($where['activity_type'] == 2){
                    //4 => 双旦活动
                    $act_type = 4;
                } elseif($where['activity_type'] == 3){
                    //5 => 九宫格活动
                    $act_type = 5;
                } elseif($where['activity_type'] == 4){
                    //6 => 福袋格活动
                    $act_type = 6;
                } elseif($where['activity_type'] == 5){
                    //7 => 福袋格活动
                    $act_type = 7;
                }
                D('Actcenter')->updateActIsUnderway($act_type);
            }
        }
        return $rs;
    }


    //博饼活动自动派奖
    public function boBinAutoSendPrize(){
        $adminInfo = session::get("admin");
        $newTime = time();
        $rs = $this->db->getall("select id,value,event_num from un_activity where state = 2 and end_time < $newTime and is_send_prize = 2 and activity_type = 1");
        if(!empty($rs)){
            $this->db->query('BEGIN');//开启事务
            foreach ($rs as $val){
                $check = true;
                $config = json_decode($val['value'],true);
                $reward_config = $config['reward_config'];
                $sql = "select SUM(prize_value) as integral,log.user_id,log.activity_id,log.event_num,u.username,u.reg_type from un_activity_log as log LEFT JOIN un_user as u on log.user_id = u.id where log.event_num = '{$val['event_num']}' and log.activity_id = '{$val['id']}' and log.is_winning = 1 GROUP BY log.user_id ORDER BY integral DESC limit 0,100";
                $list = $this->db->getall($sql);
                foreach ($list as $key=>$vv){
                    $ranking = $key+1;
                    if($ranking == 1){
                        $prize_id = 1;
                    } else if($ranking >=2 && $ranking <=3){
                        $prize_id = 2;
                    } else if($ranking >=4 && $ranking <=10){
                        $prize_id = 3;
                    } else if($ranking >=11 && $ranking <=50){
                        $prize_id = 4;
                    } else if($ranking >=51 && $ranking <=100){
                        $prize_id = 5;
                    }
                    $data['user_id'] = $vv['user_id'];
                    $data['activity_id'] = $vv['activity_id'];
                    $data['username'] = $vv['username'];
                    $data['activity_type'] = 1;
                    $data['event_num'] = $vv['event_num'];
                    $data['add_time'] = time();
                    $data['ranking'] = $ranking;
                    $data['integral'] = $vv['integral'];
                    $data['use_num'] = $this->db->getone("select count(id) as count from un_activity_log where user_id = '{$vv['user_id']}' and event_num = '{$vv['event_num']}' and activity_id = '{$vv['activity_id']}'")['count'];
                    foreach ($reward_config as $v){

                        if($prize_id == $v['prize_id']){
                            $order_num = 'BB' . date("YmdHis") . rand(100, 999);
                            if($v['prize_type'] == 2){//非实物
                                $data['prize_type'] = 1;
                                $data['prize_name'] = "元宝 ".$v['prize_money'];
                                $data['giving_status'] = 1;
                                $data['remark'] = "用户：".$vv['username']." 在第 ".$vv['event_num']." 期,博饼活动中夺得第 ".$ranking." 名，奖励元宝：".$v['prize_money'];
                                $data['order_num'] = $order_num;
                                $data['prize_money'] = $v['prize_money'];
                                $data['last_updatetime'] = time();
                                $data['send_people_id'] = $adminInfo['userid'];
                                $data['send_people_name'] = $adminInfo['username'];

                                $insert_money_data = [
                                    'user_id' => $vv['user_id'],
                                    'order_num' => $order_num,
                                    'type' => 999,     //博饼类别为999
                                    'money' => $v['prize_money'],
                                    'use_money' => $v['prize_money'],
                                    'remark' => $data['remark'],
                                    'verify' => 0,
                                    'addtime' => time(),
                                    'addip' => ip(),
                                    'admin_money' => 0,
                                    'reg_type' => $vv['reg_type'],
                                ];

                                //添加中奖表
                                $res1 = $this->db->insert("un_activity_prize", $data);
                                //如果奖品为彩金，添加资金明细表
                                $res2 = $this->db->insert('un_account_log', $insert_money_data);
                                //添加彩金到用户账户
                                $res3 = $this->db->query("UPDATE un_account SET `money` = `money` + '{$insert_money_data['money']}' WHERE user_id = '{$vv['user_id']}'");


                                if($res1 < 0 || $res2 < 0 || $res3 === false){
                                    $check = false;
                                    file_put_contents(S_CACHE . 'log/bobing.log', date("m-d H:i:s"). '--Order number--' . $order_num . '--Send the prize fail' ."\n", FILE_APPEND);
                                }
                            } else {
                                $data['prize_money'] = "0";
                                $data['prize_type'] = 2;
                                $data['prize_name'] = $v['prize_name'];
                                $data['giving_status'] = 2;
                                $data['remark'] = "用户：".$vv['username']." 在第 ".$vv['event_num']." 期,博饼活动中夺得第 ".$ranking." 名，奖励：".$v['prize_name'];
                                //添加中奖表
                                $res1 = $this->db->insert("un_activity_prize", $data);
                                if($res1 < 0){
                                    $check = false;
                                    file_put_contents(S_CACHE . 'log/bobing.log', date("m-d H:i:s"). '--Order number--' . $order_num . '--Send the prize fail' ."\n", FILE_APPEND);
                                }
                            }
                        }
                    }
                }
                if($check == true){
                    $res4 = $this->db->update("un_activity", ['is_send_prize'=>1], ['id'=>$val['id']]);
                    if($res4 > 0){
                        $this->db->query('COMMIT');//提交事务
                    } else {
                        $this->db->query('ROLLBACK');//事务回滚
                    }
                }
            }
        }
    }

    //博饼活动添加中奖名单
    public function addBoBinWinList(){
        $newTime = time();
        $rs = $this->db->getall("select id,value,event_num from un_activity where (state = 2 or end_time < $newTime) and is_send_prize = 2 and activity_type = 1");
        if(!empty($rs)){
            $this->db->query('BEGIN');//开启事务
            foreach ($rs as $val){
                $check = true;
                $config = json_decode($val['value'],true);
                $reward_config = $config['reward_config'];
                $reward_config_arr = [];
                foreach($reward_config as $reward) {
                    $reward_config_arr[$reward['prize_id']] = $reward;
                }

                $sql = "select SUM(prize_value) as integral,log.user_id,log.activity_id,log.event_num,u.username,u.reg_type from un_activity_log as log LEFT JOIN un_user as u on log.user_id = u.id where log.event_num = '{$val['event_num']}' and log.activity_id = '{$val['id']}' and log.is_winning = 1 GROUP BY log.user_id ORDER BY integral DESC limit 0,100";
                $list = $this->db->getall($sql);
                foreach ($list as $key=>$vv){
                    $prizeInfo = [];
                    $ranking = $key+1;
                    if($ranking == 1){
                        $prize_id = 1;
                    } else if($ranking >=2 && $ranking <=3){
                        $prize_id = 2;
                    } else if($ranking >=4 && $ranking <=10){
                        $prize_id = 3;
                    } else if($ranking >=11 && $ranking <=50){
                        $prize_id = 4;
                    } else if($ranking >=51 && $ranking <=100){
                        $prize_id = 5;
                    }
                    $prizeInfo['user_id'] = $vv['user_id'];
                    $prizeInfo['activity_id'] = $vv['activity_id'];
                    $prizeInfo['username'] = $vv['username'];
                    $prizeInfo['activity_type'] = 1;
                    $prizeInfo['event_num'] = $vv['event_num'];
                    $prizeInfo['add_time'] = time();
                    $prizeInfo['ranking'] = $ranking;
                    $prizeInfo['integral'] = $vv['integral'];
                    $prizeInfo['use_num'] = $this->db->getone("select count(id) as count from un_activity_log where user_id = '{$vv['user_id']}' and event_num = '{$vv['event_num']}' and activity_id = '{$vv['activity_id']}'")['count'];

                    $order_num = 'BB' . date("YmdHis") . rand(100, 999);
                    if($reward_config_arr[$prize_id]['prize_type'] == 2) {
                        $prizeInfo['prize_type'] = 2;
                        $prizeInfo['order_num'] = $order_num;
                        $prizeInfo['prize_money'] = $reward_config_arr[$prize_id]['prize_money'];
                        $prizeInfo['prize_name'] = "元宝 ".$prizeInfo['prize_money'];
                        $prizeInfo['remark'] = "用户：".$vv['username']." 在第 ".$vv['event_num']." 期,博饼活动中夺得第 ".$ranking." 名，奖励元宝：".$prizeInfo['prize_money'];
                    }else {
                        $prizeInfo['prize_type'] = 1;
                        $prizeInfo['prize_name'] = $reward_config_arr[$prize_id]['prize_name'];
                        $prizeInfo['remark'] = "用户：".$vv['username']." 在第 ".$vv['event_num']." 期,博饼活动中夺得第 ".$ranking." 名，奖励：".$prizeInfo['prize_name'];
                        $prizeInfo['prize_money'] = 0;
                    }
                    $res1 = $this->db->insert("un_activity_prize", $prizeInfo);
                    if($res1 < 0){
                        $check = false;
                        //添加中奖名单失败
                        file_put_contents(S_CACHE . 'log/bobing.log', date("m-d H:i:s"). '--Order number--' . $order_num . '--Failed to add a list of winners' ."\n", FILE_APPEND);
                    }
                }
                if($check == true){
                    $res4 = $this->db->update("un_activity", ['is_send_prize'=>1], ['id'=>$val['id']]);
                    if($res4 > 0){
                        $this->db->query('COMMIT');//提交事务
                    } else {
                        $this->db->query('ROLLBACK');//事务回滚
                    }
                }
            }
        }
    }

    //博饼活动手动派奖
    public function sendPrize($id){
        $adminInfo = session::get("admin");
        $list = $this->db->getone("select * from un_activity_prize where id = '{$id}' and giving_status = 2");
        if(!empty($list)){
            if($list['prize_type'] == 2){

                $this->db->query('BEGIN');//开启事务

                //当前余额
//                $sql = "SELECT money FROM `un_account` WHERE user_id={$list['user_id']}";
                if(!empty(C('db_port'))){ //使用mycat时 查主库数据
                    $sql="/*#mycat:db_type=master*/ SELECT money FROM `un_account` WHERE user_id={$list['user_id']} FOR UPDATE";
                }else{
                    $sql="SELECT money FROM `un_account` WHERE user_id={$list['user_id']} FOR UPDATE";
                }
                $use_money = $this->db->result($sql);

                $insert_money_data = [
                    'user_id' => $list['user_id'],
                    'money' => $list['prize_money'],
                    'use_money' => bcadd($use_money,$list['prize_money'],2),
                    'remark' => $list['remark'],
                    'verify' => 0,
                    'addtime' => time(),
                    'addip' => ip(),
                    'admin_money' => 0,
                    'reg_type' => $this->db->getone("select reg_type from un_user where id = '{$list['user_id']}'")['reg_type'],
                ];
                if($list['activity_type'] == 1){
                    $insert_money_data['type'] = 999;    //博饼类别为999
                    $insert_money_data['order_num'] = 'BB' . date("YmdHis") . rand(100, 999);
                } elseif($list['activity_type'] == 2){
                    $insert_money_data['type'] = 998;    //圣诞类别为998
                    $insert_money_data['order_num'] = 'SD' . date("YmdHis") . rand(100, 999);
                } elseif($list['activity_type'] == 3){
                    $insert_money_data['type'] = 995;    //九宫格类别为995
                    $insert_money_data['order_num'] = 'JGG' . date("YmdHis") . rand(100, 999);
                } elseif($list['activity_type'] == 4){
                    $insert_money_data['type'] = 993;    //福袋类别为993
                    $insert_money_data['order_num'] = 'FD' . date("YmdHis") . rand(100, 999);
                } elseif($list['activity_type'] == 5){
                    $insert_money_data['type'] = 992;    //刮刮乐类别为992
                    $insert_money_data['order_num'] = 'GGL' . date("YmdHis") . rand(100, 999);
                }

                //修改中奖列表的派奖状态
                $this->db->update("un_activity_prize", ['giving_status'=>1,'last_updatetime'=>time(),'send_people_id'=>$adminInfo['userid'],'send_people_name'=>$adminInfo['username'],'order_num'=>$insert_money_data['order_num']],['id'=>$id]);
                //如果奖品为彩金，添加资金明细表
                $res2 = $this->db->insert('un_account_log', $insert_money_data);
                //添加彩金到用户账户
                $res3 = $this->db->query("UPDATE un_account SET `money` = `money` + '{$insert_money_data['money']}' WHERE user_id = '{$list['user_id']}'");

                if($res2 < 0 || $res3 === false){
                    $arr['code'] = -1;
                    $arr['msg'] = "派奖失败";
                    $this->db->query('ROLLBACK');//事务回滚
                } else {
                    $arr['code'] = 0;
                    $arr['msg'] = "派奖成功";
                    $this->db->query('COMMIT');//提交事务
                }
            } else {
                //修改中奖列表的派奖状态
                $this->db->update("un_activity_prize", ['giving_status'=>1,'last_updatetime'=>time(),'send_people_id'=>$adminInfo['userid'],'send_people_name'=>$adminInfo['username']],['id'=>$id]);
                $arr['code'] = 0;
                $arr['msg'] = "派奖成功";
            }
        } else {
            $arr['code'] = -1;
            $arr['msg'] = "该奖项已派发";
        }
        return $arr;
    }

    //获取派奖总金额
    public function countAwardSum ($where) {
        $this->refreshRedis("config", "all");
        $redis = initCacheRedis();
        $config = $redis->hGet("Config:"."list_total_conf","value");
        deinitCacheRedis($redis);
        if(is_array($where)){
            $where_str = '';
            foreach ($where as $key=>$val){
                $where_str .= $key." = '".$val."' and ";
            }
            $where_str = rtrim($where_str,"and ");
        } else {
            $where_str = $where;
        }
        $sql = "SELECT SUM(prize_money) AS count_sum FROM un_activity_prize  as a left join un_user as u on a.user_id = u.id where {$where_str}";
        $sum_all = $this->db->result($sql);
        $sum_all = empty($sum_all) ? 0 : $sum_all;
        $adminInfo = session::get('admin');
        $roleInfo = explode(",",$config);
        if(in_array($adminInfo['roleid'],$roleInfo)){
            return $sum_all;
        } else {
            return;
        }
    }


    //获取某个用户在谋其博饼活动中的抽奖次数情况
    public function getNumByUid($uid, $event_num, $activity_id, $activity_type){
        $sql = "select variable_num,recharge_num,betting_num,lose_num,free_num,used_num,id,event_num from #@_activity_num where user_id = '{$uid}' and event_num = '{$event_num}' and activity_type = '{$activity_type}' and activity_id = '{$activity_id}'";
        $list = $this->db->getone($sql);
        return $list;
    }

    /**
     * 获取某个用户在某期活动中当天的已抽奖次数
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-25 15:48
     * @param int $uid 用户ID
     * @param int $event_num 期号
     * @param int $activity_id 活动ID
     * @param int $activity_type 活动类型
     * @return int
     */
    public function getNumByToday($uid, $event_num, $activity_id, $activity_type){
        $startTime = strtotime(date("Y-m-d"));
        $endTime = strtotime(date("Y-m-d 23:59:59"));
        $sql = "select count(id) as count from #@_activity_log where user_id = '{$uid}' and event_num = '{$event_num}' and activity_type = '{$activity_type}' and activity_id = '{$activity_id}' and add_time between {$startTime} and {$endTime}";
        $count = $this->db->getone($sql)['count'];
        return $count;
    }

    /**
     * 添加/修改圣诞活动方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-26 19:21
     * @param array $data
     * @return array
     */
    public function addChristmasEdit($data){
        unset($data['m'],$data['c'],$data['a']);
        $data['value']['details'] = str_replace("\n","<br />",trim($data['value']['details']));
        $data['value']['statement'] = str_replace("\n","<br />",trim($data['value']['statement']));
        $data['value'] = json_encode($data['value'],JSON_UNESCAPED_UNICODE);
        $data['rules_play'] = json_encode($data['rules_play'],JSON_UNESCAPED_UNICODE);
        $data['level_limit'] = json_encode($data['level_limit'],JSON_UNESCAPED_UNICODE);
        $data['add_time'] = time();
        $data['is_send_prize'] = 3;
        $this->db->query('BEGIN');//开启事务
        if(empty($data['id'])){
            if($data['state'] == 1){
                $state = $this->db->getone("select id from un_activity where activity_type = {$data['activity_type']} and state = 1");
                if (!empty($state)){
                    $arr['code'] = "-1";
                    $arr['msg'] = "已经有开启的活动，无法创建新的活动";
                    return $arr;
                }
            }
            $rows = $this->db->insert("#@_activity", $data);
            if($rows) {
                $log_remark = '新增'.$this->actType[$data['activity_type']].'--活动名称:'.$data['title'].'--状态:'.$this->stateArr[$data['state']].'--期数:'.$data['event_num'];
                admin_operation_log($data['add_admin_id'], 120, $log_remark);
            }
        } else {
            $data['update_time'] = time();
            $rows = $this->db->update("#@_activity", $data, ['id'=>$data['id']]);
        }

        $list = $this->db->getone("select value,id from #@_config where nid = 'christmas_max'");
        if(empty($list)){
            $post_data = [
                'nid'=>'christmas_max',
                'value'=>$data['event_num'] + 1,
                'name'=>'圣诞活动最大期数',
            ];
            $row1 = $this->db->insert("#@_config", $post_data);
        } else {
            $row1 = $this->db->update("#@_config", ['value'=>$data['event_num'] + 1], ['id'=>$list['id']]);
        }

        if(($rows > 0 || $rows !== false) || ($row1 > 0 || $row1 !== false)){

            $this->refreshRedis("config", "all");
            $arr['code'] = "0";
            $arr['msg'] = "操作成功";
            $this->db->query('COMMIT');//提交事务

        } else {
            $arr['code'] = "-1";
            $arr['msg'] = "操作失败";
            $this->db->query('ROLLBACK');//事务回滚
        }
        return $arr;
    }

    /**
     * 添加手动调整抽奖次数方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-26 19:21
     * @param array $data
     * @return array
     */
    public function addNumLog($data){
        unset($data['m'],$data['c'],$data['a']);
        $userId = $this->db->getone("select id from #@_user where username = '{$data['username']}' and reg_type not in(8,9)")['id'];
        $data['user_id'] = $userId;
        if(empty($userId)) {
            $arr['code'] = "-1";
            $arr['msg'] = "该会员不存在";
            return $arr;
        } else {
            //获取活动配置
            $config = $this->getActivityConfig($data['activity_type']);
            if (!empty($config)) {
                if($config['state'] == 1){

                    //获取活动期间充值投注总额
                    $sum = $this->getRechargeAndBettingSum($userId, $config['start_time'], $config['end_time']);
                    lg('christmas', "用户ID：{$userId} 活动期间投注总额：{$sum['recharge_count']} 充值总额：{$sum['betting_count']}\n");
                    //获取已用抽奖次数
                    $count  = $this->getUsedNum($userId, $config['event_num'], $config['id'], $data['activity_type']);
                    $used_num = $count['free_num'] + $count['paid_num'];


                    $recharge_num = 0;
                    $betting_num = 0;
                    if ($data['activity_type'] == 1){//博饼送次数规则
                        if($sum['recharge_count'] > 0 && $config['rules_play']['recharge_money'] > 0){
                            $recharge_num = floor($sum['recharge_count'] / $config['rules_play']['recharge_money']) * $config['rules_play']['recharge_num'];
                        }
                        if($sum['betting_count'] > 0 && $config['rules_play']['betting_money'] > 0){
                            $betting_num = floor($sum['betting_count'] / $config['rules_play']['betting_money']) * $config['rules_play']['betting_num'];
                        }
                    } elseif ($data['activity_type'] == 2 || $data['activity_type'] == 3 || $data['activity_type'] == 4 || $data['activity_type'] == 5) {//圣诞，九宫格，福袋，刮刮乐送次数规则
                        if($sum['recharge_count'] > 0 && $config['rules_play']['every_topup'] > 0){
                            $recharge_num = floor($sum['recharge_count'] / $config['rules_play']['every_topup']) * $config['rules_play']['every_topup_val'];
                        }
                        if($sum['betting_count'] > 0 && $config['rules_play']['every_bet'] > 0){
                            $betting_num = floor($sum['betting_count'] / $config['rules_play']['every_bet']) * $config['rules_play']['every_bet_val'];
                        }
                    }


                    //查询用户是否参加过当前活动
                    $sql = "select * from #@_activity_num where user_id = '{$userId}' and activity_id = '{$config['id']}' and activity_type = '{$data['activity_type']}' and event_num = '{$config['event_num']}'";
                    $list = $this->db->getone($sql);
                    if(empty($list)){
                        $this->db->query('BEGIN');//开启事务
                        $check = true;
                        $check1 = true;
                        $check2 = true;
                        $post = [
                            'user_id' => $userId,
                            'activity_id' => $config['id'],
                            'activity_type' => $data['activity_type'],
                            'event_num' => $config['event_num'],
                            'recharge_num' => $recharge_num,
                            'betting_num' => $betting_num,
                            'free_num' => !empty($config['value']['free_num']) ? $config['value']['free_num'] : 0 ,
                            'used_num' => $used_num,
                        ];
                        $rows = $this->db->insert("#@_activity_num", $post);
                        $rows_sql = "新用户参加抽奖活动，添加活动次数数据\n".$this->db->_sql();
                        lg('christmas', $rows_sql."\n");

                        if($recharge_num > 0){
                            $post1 = [
                                'user_id' => $userId,
                                'activity_id' => $config['id'],
                                'activity_type' => $data['activity_type'],
                                'event_num' => $config['event_num'],
                                'available_num' => $recharge_num,
                                'num' => $recharge_num,
                                'type' => 1,
                                'add_type' => 2,
                                'add_time' => time(),
                                'remarks' => "会员充值，获得{$recharge_num}次抽奖机会"
                            ];
                            $rows1 = $this->db->insert("#@_activity_num_log", $post1);
                            $rows1_sql = "添加充值获得次数日志\n".$this->db->_sql();
                            lg('christmas', $rows1_sql."\n");
                            if($rows1 < 0){
                                $check = false;
                            }
                        }

                        if($betting_num > 0){
                            $post2 = [
                                'user_id' => $userId,
                                'activity_id' => $config['id'],
                                'activity_type' => $data['activity_type'],
                                'event_num' => $config['event_num'],
                                'available_num' => $recharge_num + $betting_num,
                                'num' => $betting_num,
                                'type' => 1,
                                'add_type' => 1,
                                'add_time' => time(),
                                'remarks' => "会员投注，获得{$betting_num}次抽奖机会"
                            ];
                            $rows2 = $this->db->insert("#@_activity_num_log", $post2);
                            $rows2_sql = "添加投注获得次数日志\n".$this->db->_sql();
                            lg('christmas', $rows2_sql."\n");
                            if($rows2 < 0){
                                $check1 = false;
                            }
                        }

                        if($config['value']['free_num'] > 0){
                            $post5 = [
                                'user_id' => $userId,
                                'activity_id' => $config['id'],
                                'activity_type' => $data['activity_type'],
                                'event_num' => $config['event_num'],
                                'available_num' => $recharge_num + $betting_num + $config['value']['free_num'],
                                'num' => $config['value']['free_num'],
                                'type' => 1,
                                'add_type' => 2,
                                'add_time' => time(),
                                'remarks' => "会员免费，获得{$config['value']['free_num']}次抽奖机会"
                            ];
                            $rows5 = $this->db->insert("#@_activity_num_log", $post5);
                            $rows5_sql = "添加免费获得次数日志\n".$this->db->_sql();
                            lg('christmas', $rows5_sql."\n");
                            if($rows5 < 0){
                                $check2 = false;
                            }
                        }


                        $count_num = $recharge_num + $betting_num;
                        if($data['type'] == 1){
                            $data['available_num'] = $count_num - $used_num + $data['num'] + $config['value']['free_num'];
                        } elseif($data['type'] == 2) {
                            $data['available_num'] = $count_num - $used_num - $data['num'] + $config['value']['free_num'];
                        }

                        if($data['available_num'] < 0){
                            $x = $count_num - $used_num;
                            $arr['code'] = "-1";
                            $arr['msg'] = "会员 ".$data['username']." <br />当前可用抽奖次数：{$x}"."<br />调整后的抽奖次数不能小于0<br />";
                            return $arr;
                        }
                        unset($data['username']);
                        $data['event_num'] = $config['event_num'];
                        if($data['type'] == 1){
                            $data['remarks'] = "后台增加次数".$data['num']."次";
                        } else {
                            $data['remarks'] = "后台减少次数".$data['num']."次";
                        }
                        $rows3 = $this->db->insert("#@_activity_num_log", $data);
                        $rows3_sql = "添加管理员手动调整抽奖次数日志\n".$this->db->_sql();
                        lg('christmas', $rows3_sql."\n");

                        $total = $this->getVariableNum($userId, $config['event_num'], $config['id'], $config['activity_type']);
                        $variable_num = $total['increase'] - $total['reduce'];
                        $rows4 = $this->db->update("#@_activity_num", ['variable_num' => $variable_num], ['id'=>$rows]);
                        $rows4_sql = "更新管理员修改次数字段(variable_num)\n".$this->db->_sql();
                        lg('christmas', $rows4_sql."\n");

                        $a = [$rows,$check,$check1,$rows3,$rows4,$check2];
                        lg('christmas', var_export($a,true)."\n");
                        if($rows > 0 && $check && $check1 && $rows3 > 0 && $rows4 && $check2){
                            $this->db->query('COMMIT');//提交事务
                            $arr['code'] = "0";
                            $arr['msg'] = "操作成功";
                        } else {
                            $this->db->query('ROLLBACK');//事务回滚
                            $arr['code'] = "-1";
                            $arr['msg'] = "操作失败";
                        }
                        return $arr;

                    } else {

                        $check = true;
                        $check1 = true;
                        $num = $recharge_num - $list['recharge_num'];
                        $num1 = $betting_num - $list['betting_num'];
                        $this->db->query('BEGIN');//开启事务
                        if($recharge_num > $list['recharge_num']){
                            $rows = $this->db->update("#@_activity_num", ['recharge_num' => $recharge_num], ['id'=>$list['id']]);
                            $rows_sql = "更新充值获得的次数字段(recharge_num)\n".$this->db->_sql();
                            lg('christmas', $rows_sql."\n");

                            $post1 = [
                                'user_id' => $list['user_id'],
                                'activity_id' => $list['id'],
                                'activity_type' => $list['activity_type'],
                                'event_num' => $list['event_num'],
                                'available_num' => $num + $list['recharge_num'] + $list['betting_num'] + $list['lose_num'] + $list['variable_num'] - $list['used_num'],
                                'num' => $num,
                                'type' => 1,
                                'add_type' => 2,
                                'add_time' => time(),
                                'remarks' => "会员充值，获得{$num}次抽奖机会"
                            ];
                            $rows1 = $this->db->insert("#@_activity_num_log", $post1);
                            $rows1_sql = "添加充值获得次数日志\n".$this->db->_sql();
                            lg('christmas', $rows1_sql."\n");
                            if(!$rows && $rows1 < 0){
                                $check = false;
                            }
                        }

                        if($betting_num > $list['betting_num']){
                            $rows3 = $this->db->update("#@_activity_num", ['betting_num' => $betting_num], ['id'=>$list['id']]);
                            $rows3_sql = "更新投注获得的次数字段(betting_num)\n".$this->db->_sql();
                            lg('christmas', $rows3_sql."\n");

                            $post2 = [
                                'user_id' => $list['user_id'],
                                'activity_id' => $list['id'],
                                'activity_type' => $list['activity_type'],
                                'event_num' => $list['event_num'],
                                'available_num' => $num1 + $list['recharge_num'] + $list['betting_num'] + $list['lose_num'] + $list['variable_num'] - $list['used_num'],
                                'num' => $num1,
                                'type' => 1,
                                'add_type' => 1,
                                'add_time' => time(),
                                'remarks' => "会员投注，获得{$num1}次抽奖机会"
                            ];
                            $rows4 = $this->db->insert("#@_activity_num_log", $post2);
                            $rows4_sql = "添加投注获得次数日志\n".$this->db->_sql();
                            lg('christmas', $rows4_sql."\n");
                            if(!$rows3 && $rows4 < 0){
                                $check1 = false;
                            }
                        }

                        $count_num = $num1 + $num + $list['recharge_num'] + $list['betting_num'] + $list['lose_num'] + $list['variable_num'];
                        if($data['type'] == 1){
                            $data['available_num'] = $count_num - $used_num + $data['num'];
                        } elseif($data['type'] == 2) {
                            $data['available_num'] = $count_num - $used_num - $data['num'];
                        }
                        if($data['available_num'] < 0){
                            $x = $count_num - $used_num;
                            $arr['code'] = "-1";
                            $arr['msg'] = "会员 ".$data['username']." <br />当前可用抽奖次数：{$x}"."<br />调整后的抽奖次数不能小于0<br />";
                            return $arr;
                        }
                        unset($data['username']);
                        $data['event_num'] = $list['event_num'];
                        if($data['type'] == 1){
                            $data['remarks'] = "后台增加次数".$data['num']."次";
                        } else {
                            $data['remarks'] = "后台减少次数".$data['num']."次";
                        }
                        $rows5 = $this->db->insert("#@_activity_num_log", $data);
                        $rows5_sql = "添加管理员手动调整抽奖次数日志\n".$this->db->_sql();
                        lg('christmas', $rows5_sql."\n");

                        $total = $this->getVariableNum($userId, $list['event_num'], $list['activity_id'], $list['activity_type']);
                        $variable_num = $total['increase'] - $total['reduce'];
                        $rows6 = $this->db->update("#@_activity_num", ['variable_num' => $variable_num], ['id'=>$list['id']]);
                        $rows6_sql = "更新管理员修改次数字段(variable_num)\n".$this->db->_sql();
                        lg('christmas', $rows6_sql."\n");

                        $a = [$check,$check1,$rows5,$rows6];
                        lg('christmas', var_export($a,true)."\n");
                        if($check && $check1 && $rows5 > 0 && $rows6){
                            $this->db->query('COMMIT');//提交事务
                            $arr['code'] = "0";
                            $arr['msg'] = "操作成功";
                        } else {
                            $this->db->query('ROLLBACK');//事务回滚
                            $arr['code'] = "-1";
                            $arr['msg'] = "操作失败";
                        }
                        return $arr;
                    }
                } else {
                    $arr['code'] = "-1";
                    $arr['msg'] = "活动已结束";
                    return $arr;
                }
            } else {
                $arr['code'] = "-1";
                $arr['msg'] = "活动已结束";
                return $arr;
            }

        }
    }

    /**
     * 获取某个用户在某时间段的有效充值和有效投注总额
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-26 19:21
     * @param int $uid 用户ID
     * @param int $start 开始时间戳
     * @param int $end 结束时间戳
     * @return array
     */
    public function getRechargeAndBettingSum($uid, $start, $end){
        $arr = [];
        $sql1 = "select SUM(money) as count_num 
                from un_account_recharge 
                where addtime between '{$start}' and '{$end}' and status = 1 and user_id = '{$uid}'";
        $count1 = $this->db->getone($sql1)['count_num'];
        $arr['recharge_count'] = $count1 > 0 ? $count1 : 0 ;
        $sql2 = "select SUM(money) as count_num 
                 from un_orders 
                 where addtime between '{$start}' and '{$end}' and state = 0 and user_id = '{$uid}' and award_state <> 0";
        $count1 = $this->db->getone($sql2)['count_num'];
        $arr['betting_count'] = $count1 > 0 ? $count1 : 0 ;
        return $arr;
    }

    /**
     * 计算某个用户某个活动已经抽过多少次
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-26 19:21
     * @param int $uid 用户ID
     * @param int $event_num 期数
     * @param int $activity_id 活动ID
     * @param int $activity_type 活动类型
     * @return array
     */
    public function getUsedNum($uid, $event_num, $activity_id, $activity_type){
        $sql = "select 
                count( case when user_id = '{$uid}' and activity_type = '{$activity_type}' and is_free = 1 and event_num = '{$event_num}' and activity_id = '{$activity_id}' then id end) as 'free_num',
                count( case when user_id = '{$uid}' and activity_type = '{$activity_type}' and is_free = 2 and event_num = '{$event_num}' and activity_id = '{$activity_id}' then id end) as 'paid_num'
                from un_activity_log";
        $total = $this->db->getone($sql);
        return $total;
    }

    /**
     * 计算某个用户某个活动调整次数
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-01 17:59
     * @param int $uid 用户ID
     * @param int $event_num 期数
     * @param int $activity_id 活动ID
     * @param int $activity_type 活动类型
     * @return int
     */
    public function getVariableNum($uid, $event_num, $activity_id, $activity_type){
        $sql = "select 
                sum( case when user_id = $uid and activity_type = $activity_type and type = 1 and event_num = $event_num and activity_id = $activity_id and add_type = 4 then num end) as 'increase',
                sum( case when user_id = $uid and activity_type = $activity_type and type = 2 and event_num = $event_num and activity_id = $activity_id and add_type = 4 then num end) as 'reduce'
                from un_activity_num_log";
        $total = $this->db->getone($sql);
        return $total;
    }

    /**
     * 更新用户可用抽奖次数表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-01 17:59
     * @param int $uid 用户ID
     * @param int $activity_type 活动类型  1：博饼活动   2：圣诞活动   3：九宫格
     */
    public function updateNum($uid, $activity_type){
        $config = $this->getActivityConfig($activity_type);

        if($config['state'] == 1 && !empty($uid) && !empty($config)){

            //获取活动期间充值投注总额
            $sum = $this->getRechargeAndBettingSum($uid, $config['start_time'], $config['end_time']);
            lg('christmas', "用户ID：{$uid} 活动期间投注总额：{$sum['recharge_count']} 充值总额：{$sum['betting_count']}\n");
            //获取已用抽奖次数
            $count  = $this->getUsedNum($uid, $config['event_num'], $config['id'], $config['activity_type']);
            $used_num = $count['free_num'] + $count['paid_num'];

            $recharge_num = 0;
            $betting_num = 0;
            if(in_array($config['activity_type'],['1'])){//博饼活动

                if($sum['recharge_count'] > 0 && $config['rules_play']['recharge_money'] > 0){
                    $recharge_num = floor($sum['recharge_count'] / $config['rules_play']['recharge_money']) * $config['rules_play']['recharge_num'];
                }
                if($sum['betting_count'] > 0 && $config['rules_play']['betting_money'] > 0){
                    $betting_num = floor($sum['betting_count'] / $config['rules_play']['betting_money']) * $config['rules_play']['betting_num'];
                }

            } elseif(in_array($config['activity_type'],['2','3','4','5'])){//圣诞,九宫格,福袋,刮刮乐

                if($sum['recharge_count'] > 0 && $config['rules_play']['every_topup'] > 0){
                    $recharge_num = floor($sum['recharge_count'] / $config['rules_play']['every_topup']) * $config['rules_play']['every_topup_val'];
                }
                if($sum['betting_count'] > 0 && $config['rules_play']['every_bet'] > 0){
                    $betting_num = floor($sum['betting_count'] / $config['rules_play']['every_bet']) * $config['rules_play']['every_bet_val'];
                }

            }


            //查询用户是否参加过当前活动
            $sql = "select * from #@_activity_num where user_id = '{$uid}' and activity_id = '{$config['id']}' and activity_type = '{$config['activity_type']}' and event_num = '{$config['event_num']}'";
            $list = $this->db->getone($sql);
            if(empty($list)){

                $this->db->query('BEGIN');//开启事务
                $check = true;
                $check1 = true;
                $check2 = true;

                $post = [
                    'user_id' => $uid,
                    'activity_id' => $config['id'],
                    'activity_type' => $config['activity_type'],
                    'event_num' => $config['event_num'],
                    'recharge_num' => $recharge_num,
                    'betting_num' => $betting_num,
                    'free_num' => !empty($config['value']['free_num']) ? $config['value']['free_num'] : 0 ,
                    'used_num' => $used_num,
                ];
                $rows = $this->db->insert("#@_activity_num", $post);
                $rows_sql = "新用户参加抽奖活动，添加活动次数数据\n".$this->db->_sql();
                lg('christmas', $rows_sql."\n");

                if($recharge_num > 0){
                    $post1 = [
                        'user_id' => $uid,
                        'activity_id' => $config['id'],
                        'activity_type' => $config['activity_type'],
                        'event_num' => $config['event_num'],
                        'available_num' => $recharge_num,
                        'num' => $recharge_num,
                        'type' => 1,
                        'add_type' => 2,
                        'add_time' => time(),
                        'remarks' => "会员充值，获得{$recharge_num}次抽奖机会"
                    ];
                    $rows1 = $this->db->insert("#@_activity_num_log", $post1);
                    $rows1_sql = "添加充值获得次数日志\n".$this->db->_sql();
                    lg('christmas', $rows1_sql."\n");
                    if($rows1 < 0){
                        $check = false;
                    }
                }

                if($betting_num > 0){
                    $post2 = [
                        'user_id' => $uid,
                        'activity_id' => $config['id'],
                        'activity_type' => $config['activity_type'],
                        'event_num' => $config['event_num'],
                        'available_num' => $recharge_num + $betting_num,
                        'num' => $betting_num,
                        'type' => 1,
                        'add_type' => 1,
                        'add_time' => time(),
                        'remarks' => "会员投注，获得{$betting_num}次抽奖机会"
                    ];
                    $rows2 = $this->db->insert("#@_activity_num_log", $post2);
                    $rows2_sql = "添加投注获得次数日志\n".$this->db->_sql();
                    lg('christmas', $rows2_sql."\n");
                    if($rows2 < 0){
                        $check1 = false;
                    }
                }

                if($config['value']['free_num'] > 0){
                    $post3 = [
                        'user_id' => $uid,
                        'activity_id' => $config['id'],
                        'activity_type' => $config['activity_type'],
                        'event_num' => $config['event_num'],
                        'available_num' => $recharge_num + $betting_num + $config['value']['free_num'],
                        'num' => $config['value']['free_num'],
                        'type' => 1,
                        'add_type' => 2,
                        'add_time' => time(),
                        'remarks' => "会员免费，获得{$config['value']['free_num']}次抽奖机会"
                    ];
                    $rows3 = $this->db->insert("#@_activity_num_log", $post3);
                    $rows3_sql = "添加免费获得次数日志\n".$this->db->_sql();
                    lg('christmas', $rows3_sql."\n");
                    if($rows3 < 0){
                        $check2 = false;
                    }
                }
                $a = [$rows,$check,$check1,$check2];
                lg('christmas', var_export($a,true)."\n");
                if($rows > 0 && $check && $check1 && $check2){
                    $this->db->query('COMMIT');//提交事务

                } else {
                    $this->db->query('ROLLBACK');//事务回滚
                }

            } else {

                $check = true;
                $check1 = true;
                $check2 = true;
                $num = $recharge_num - $list['recharge_num'];
                $num1 = $betting_num - $list['betting_num'];
                $this->db->query('BEGIN');//开启事务
                if($recharge_num > $list['recharge_num']){
                    $rows = $this->db->update("#@_activity_num", ['recharge_num' => $recharge_num], ['id'=>$list['id']]);
                    $rows_sql = "更新充值获得的次数字段(recharge_num)\n".$this->db->_sql();
                    lg('christmas', $rows_sql."\n");
                    $post = [
                        'user_id' => $list['user_id'],
                        'activity_id' => $list['activity_id'],
                        'activity_type' => $list['activity_type'],
                        'event_num' => $list['event_num'],
                        'available_num' => $num + $list['recharge_num'] + $list['betting_num'] + $list['lose_num'] + $list['variable_num'] + $list['free_num'] - $list['used_num'],
                        'num' => $num,
                        'type' => 1,
                        'add_type' => 2,
                        'add_time' => time(),
                        'remarks' => "会员充值，获得{$num}次抽奖机会"
                    ];
                    $rows1 = $this->db->insert("#@_activity_num_log", $post);
                    $rows1_sql = "添加充值获得次数日志\n".$this->db->_sql();
                    lg('christmas', $rows1_sql."\n");
                    if(!$rows && $rows1 < 0){
                        $check = false;
                    }
                }
                if($betting_num > $list['betting_num']){
                    $rows2 = $this->db->update("#@_activity_num", ['betting_num' => $betting_num], ['id'=>$list['id']]);
                    $rows2_sql = "更新投注获得的次数字段(betting_num)\n".$this->db->_sql();
                    lg('christmas', $rows2_sql."\n");
                    $post1 = [
                        'user_id' => $list['user_id'],
                        'activity_id' => $list['activity_id'],
                        'activity_type' => $list['activity_type'],
                        'event_num' => $list['event_num'],
                        'available_num' => $num1 + $list['recharge_num'] + $list['betting_num'] + $list['lose_num'] + $list['variable_num'] + $list['free_num'] - $list['used_num'],
                        'num' => $num1,
                        'type' => 1,
                        'add_type' => 1,
                        'add_time' => time(),
                        'remarks' => "会员投注，获得{$num1}次抽奖机会"
                    ];
                    $rows3 = $this->db->insert("#@_activity_num_log", $post1);
                    $rows3_sql = "添加投注获得次数日志\n".$this->db->_sql();
                    lg('christmas', $rows3_sql."\n");
                    if(!$rows2 && $rows3 < 0){
                        $check1 = false;
                    }
                }

                if($used_num > $list['used_num']){
                    $rows4 = $this->db->update("#@_activity_num", ['used_num' => $used_num], ['id'=>$list['id']]);
                    $rows4_sql = "更新已使用抽奖次数字段(used_num)\n".$this->db->_sql();
                    lg('christmas', $rows4_sql."\n");
                    if(!$rows4){
                        $check2 = false;
                    }
                }

		        $a = [$check,$check1,$check2];
                lg('christmas', var_export($a,true)."\n");
                if($check && $check1 && $check2){
                    $this->db->query('COMMIT');//提交事务
                } else {
                    $this->db->query('ROLLBACK');//事务回滚
                }

            }
        } else {
            lg('christmas', "当前活动处于结束中，活动ID：{$config['id']}\n");
        }
    }

    /**
     * 获取用户是否在白名单之内，返回必中次数
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:55
     * @param int $username 用户名
     * @param int $white 白名单
     * @return int 返回白名单中奖次数
     */
    public function isInWhiteList($username,$white){
        $a = explode(";",$white);
        foreach($a as $v) {
            $b = explode("-",$v);
            if($b[0] == $username) {
                return $b[1];
            } else {
                return 0;
            }
        }
    }

    /**
     * 获取某个活动中某个奖品的中奖次数
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:55
     * @param int $gift_id 奖品ID
     * @param int $uid 用户ID
     * @param int $activity_type 活动类型
     * @param int $activity_id 活动ID
     * @return int 中奖次数
     */
    public function getWinNum($gift_id, $uid, $activity_type, $activity_id){
        $sql = "select count(id) as sum from #@_activity_log where gift_id = $gift_id and user_id = $uid and activity_type = $activity_type and activity_id = $activity_id";
        $sum = $this->db->getone($sql)['sum'];
        return $sum;
    }

    /**
     * 获取预留的奖品数量
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:55
     * @param int $gift_id 奖品ID
     * @param int $white_list 白名单
     * @param int $activity_type 活动类型
     * @param int $activity_id 活动ID
     * @return array
     */
    public function getReserveNum($white_list, $gift_id, $activity_type, $activity_id){
        $arr = [];
        $a = explode(";",$white_list);
        foreach($a as $v) {
            $b = explode("-",$v);
            $sql = "select id from #@_user where username = '{$b[0]}'";
            $uid = $this->db->getone($sql)['id'];
            if(!empty($uid)){
                $arr[$uid] = [
                    'num'=> $b[1],//白名单总次数
                    'usd_num'=>$this->getWinNum($gift_id, ''.$uid.'', $activity_type, $activity_id),//已中奖次数
                ];
            }
        }
        return $arr;
    }

    /**
     * 添加中奖飘窗方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:55
     * @param array $data 数据组
     * @return array
     */
    public function barrageSetAct($data) {
        $list = $this->getOneCouponNew("id", ['nid' => $data['nid']], '', "#@_config");
        if (empty($list)) {
            $rows = $this->db->insert("#@_config", $data);
        } else {
            $rows = $this->db->update("#@_config", ['value' => $data['value']], ['id'=>$list['id']]);
        }
        if ($rows > 0 || $rows !== false) {
            $arr['code'] = "0";
            $arr['msg'] = "操作成功";
            $this->refreshRedis('config', 'all'); //刷新缓存
        } else {
            $arr['code'] = "-1";
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

    /**
     * 添加自动飘窗配置方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:55
     * @param array $data 数据组
     * @return array
     */
    public function barrageConfigAct($data){
        $check = true;
        $this->db->query('BEGIN');//开启事务
        $conf = [
            'value' => json_encode($data['value'],JSON_UNESCAPED_UNICODE),
            'state' => 0,
            'type' => 2
        ];
        if(empty($data['id'])) {
            $rows = $this->db->insert("un_person_config",$conf);
        } else {
            $rows = $this->db->update("un_person_config",$conf,['id'=>$data['id']]);
            $this->db->delete("un_role",['conf_id'=>$data['id']]);
            $this->db->delete("un_barrage_auto",['conf_id'=>$data['id']]);
        }
        if ($rows > 0 || $rows !== false) {
            foreach($data['value']['user_info'] as $val) {
                $role['user_id'] = $val['user_id'];
                if(empty($data['id'])) {
                    $role['conf_id'] = $rows;
                } else {
                    $role['conf_id'] = $data['id'];
                }
                $rows1 = $this->db->insert("un_role",$role);
                if($rows1 < 0) {
                    $check = false;
                }
            }
            if($check === false) {
                $this->db->query('ROLLBACK');//事务回滚
                $arr['code'] = -1;
                $arr['msg'] = "操作失败";
            } else {
                $this->db->query('COMMIT');//提交事务
                $arr['code'] = 0;
                $arr['msg'] = "操作成功";
            }
        } else {
            $this->db->query('ROLLBACK');//事务回滚
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

    /**
     * 添加/修改九宫格活动方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-26 19:21
     * @param array $data
     * @return array
     */
    public function addNineEdit($data){
        unset($data['m'],$data['c'],$data['a']);
        $data['value']['details'] = str_replace("\n","<br />",trim($data['value']['details']));
        $data['value']['statement'] = str_replace("\n","<br />",trim($data['value']['statement']));
        $data['value'] = json_encode($data['value'],JSON_UNESCAPED_UNICODE);
        $data['rules_play'] = json_encode($data['rules_play'],JSON_UNESCAPED_UNICODE);
        $data['level_limit'] = json_encode($data['level_limit'],JSON_UNESCAPED_UNICODE);
        $data['add_time'] = time();
        $data['is_send_prize'] = 3;
        $this->db->query('BEGIN');//开启事务
        if(empty($data['id'])){
            if($data['state'] == 1){
                $state = $this->db->getone("select id from un_activity where activity_type = {$data['activity_type']} and state = 1");
                if (!empty($state)){
                    $arr['code'] = "-1";
                    $arr['msg'] = "已经有开启的活动，无法创建新的活动";
                    return $arr;
                }
            }
            $rows = $this->db->insert("#@_activity", $data);
            if($rows) {
                $log_remark = '新增'.$this->actType[$data['activity_type']].'--活动名称:'.$data['title'].'--状态:'.$this->stateArr[$data['state']].'--期数:'.$data['event_num'];
                admin_operation_log($data['add_admin_id'], 120, $log_remark);
            }
        } else {
            $data['update_time'] = time();
            $rows = $this->db->update("#@_activity", $data, ['id'=>$data['id']]);
        }

        $list = $this->db->getone("select value,id from #@_config where nid = 'nine_gong_max'");
        if(empty($list)){
            $post_data = [
                'nid'=>'nine_gong_max',
                'value'=> $data['event_num'] + 1,
                'name'=>'九宫格活动最大期数',
            ];
            $row1 = $this->db->insert("#@_config", $post_data);
        } else {
            $row1 = $this->db->update("#@_config", ['value'=> $data['event_num'] + 1], ['id'=>$list['id']]);
        }

        if(($rows > 0 || $rows !== false) || ($row1 > 0 || $row1 !== false)){
            $this->refreshRedis("config", "all");
            $arr['code'] = "0";
            $arr['msg'] = "操作成功";
            $this->db->query('COMMIT');//提交事务

        } else {
            $arr['code'] = "-1";
            $arr['msg'] = "操作失败";
            $this->db->query('ROLLBACK');//事务回滚
        }
        return $arr;
    }

    /**
     * @name 修改任务配置方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-17 15:32:46
     * @param array $data 数据组
     * @return array
     */
    public function taskConfAct($data) {
        foreach ($data['config'] as $key => $val) {
            $data['config'][$key]['explain'] = str_replace("\n","<br />",trim($val['explain']));
        }
        $list = $this->db->getone("select value,id from #@_config where nid = 'task_config'");
        if (empty($list)) {
            $post_data[$data['type']] = $data;
            $rows = $this->db->insert("#@_config", ['nid' => "task_config", 'value' => encode($post_data), 'name' => '平台任务配置']);
        } else {
            $value = decode($list['value']);
            $value[$data['type']] = $data;
            $rows = $this->db->update("#@_config", ['value'=> encode($value)], ['id'=>$list['id']]);
        }
        if($rows > 0 || $rows !== false){
            $this->refreshRedis("config", "all");
            $arr['code'] = "0";
            $arr['msg'] = "操作成功";
        } else {
            $arr['code'] = "-1";
            $arr['msg'] = "操作失败";
        }
        return $arr;

    }


    /*
     * 获取平台任务 new
     * */
    public function taskIndexN($config,$user_id) {
        $start_time = strtotime(date("Y-m-d"));
        $end_time = strtotime(date("Y-m-d")) + 86399;
        $monday = this_monday();
        $sunday = this_sunday() + 86399;

        $oneceTask = $config[1];            //一次性任务      只能做一次
        $everydayTask = $config[2];         //每日任务      每天重复
        $everydayAddTask = $config[3];      //每日达标任务   每日完成指定任务数量即可

        $taskSuccessData = [];      //已达标任务

        $userInfo = $this->db->getone("select * from un_user where id = $user_id");            //用户信息

        $check_status = $this->doTaskAuth($user_id);

        if($oneceTask && $oneceTask['config']) {
            if($check_status === true) {
                $taskTypeArr = [];
                foreach($oneceTask['config'] as $k=>$value) {
                    if($value['state'] == 1) {
                        unset($oneceTask['config'][$k]);         //未开启的任务
                        continue;
                    }

                    $taskTypeArr[] = $value['id'];
                }

                if($taskTypeArr) {
                    $taskTypeStr = "(".implode(',',$taskTypeArr).")";
                    $sql = "select id,state,type from #@_task_prize where user_id = {$user_id} and type in $taskTypeStr";
                    $taskInfo = $this->db->getall($sql);

                    $taskStatusArr = array_column($taskInfo, 'state', 'type');
                    $taskPrizeIdArr = array_column($taskInfo, 'id', 'type');
                    foreach($oneceTask['config'] as &$value) {
                        if(!array_key_exists($value['id'], $taskStatusArr)) {
                            $value['task_state'] = 2;       //任务未完成
                        }else {
                            $value['task_state'] = $taskStatusArr[$value['id']] == 1?1:3;   //任务状态  1未领取  3已领取
                            $value['task_prize_id'] = $taskPrizeIdArr[$value['id']];   //任务状态  1未领取  3已领取
                        }
                        unset($value);
                    }
                }
                $tmp["name"] = "有奖任务";
                $tmp['data'] = array_values($oneceTask['config']);
                $task_1[] = $tmp;
            }else {
                $tmp["name"] = "有奖任务";
                $tmp['data'] = array($check_status);
                $task_1[] = $tmp;
            }
        }

        if($everydayTask && $everydayTask['config']) {
            $taskTypeArr = [];
            foreach ($everydayTask['config'] as $k=>$value) {
                if($check_status !== true && $value['id'] == 7) {
                    unset($everydayTask['config'][$k]);
                    continue;
                }

                if($value['state'] == 1) {
                    unset($everydayTask['config'][$k]);         //未开启的任务
                    continue;
                }

                $taskTypeArr[] = $value['id'];
            }

            if($taskTypeArr) {
                $taskTypeStr = "(".implode(',',$taskTypeArr).")";
                $sql = "select id,state,type from #@_task_prize where user_id = {$user_id} and type in $taskTypeStr and complete_time between {$start_time} and {$end_time}";
                $taskInfo = $this->db->getall($sql);
                $taskStatusArr = array_column($taskInfo, 'state', 'type');
                $taskPrizeIdArr = array_column($taskInfo, 'id', 'type');
                foreach($everydayTask['config'] as &$value) {
                    if($value['id'] == 7) {
                        $value['is_sign'] = 0;
                        $value['count'] = 0;
                        $sql = "select id,complete_time from #@_task_prize where user_id = {$user_id} and type = {$value['id']} and complete_time between {$monday} and {$sunday}";
                        $axxx = $this->db->getall($sql);
                        foreach ($axxx as $axxx_val) {
                            $value['count']++;
                            if ($axxx_val['complete_time'] >= $start_time && $axxx_val['complete_time'] <= $end_time) {
                                $value['is_sign'] = 1;
                            }
                        }
                    }
                    if(in_array($value['id'], [8,9])) {
                        if($value['id'] == 8) {
                            $fields = 'money';
                            $order = 'order by money desc';
                        }
                        if($value['id'] == 9) {
                            $fields = 'sum(money) as money';
                            $order = '';
                        }

                        $sql = "select $fields from #@_account_recharge where user_id = {$user_id} and addtime between {$start_time} and {$end_time} and status = 1 $order";
                        $info = $this->db->getone($sql);

                        $value['recharge'] = isset($info['money'])?(int) $info['money']:0;
                    }

                    if(array_key_exists($value['id'], $taskStatusArr)) {
                        $value['task_state'] = $taskStatusArr[$value['id']] == 1?1:3;   //任务状态  1未领取  3已领取
                        $value['task_prize_id'] = $taskPrizeIdArr[$value['id']];   //任务状态  1未领取  3已领取
                    }else {
                        $value['task_state'] = 2;       //任务未完成

                        if($value['id'] == 7) continue;         //每日签到

                        if($value['id'] == 8 || $value['id'] == 9) {     //每日单次充值           累计充值
                            if(!$info || $info['money'] < $value['money_bal']) continue;     //未充值或金额未达标
                        }

                        if(in_array($value['id'],[10,11,12,13,14,15,16,17,18,19,24,25,26])) {
                            //任务类型对应彩种类型
                            $taskTypeForLotteryType = ['10' => 1, '11' => 3, '12' => 2, '13' => 4, '14' => 9, '15' => 5, '16' => 6, '17' => 7, '18' => 8, '19' => 10, '24' => 11,'25' => 14,'26' => 13];
                            $sql = "select sum(money) as money from un_orders where user_id = {$user_id} and addtime between {$start_time} and {$end_time} and state = 0 and is_legal = 1 and lottery_type = {$taskTypeForLotteryType[$value['id']]} and award_state != 0";
                            $info = $this->db->getone($sql);

                            $value['betting'] = isset($info['money'])?$info['money']:0;
                            if(!$info || $info['money'] < $value['money_bal']) continue;            //未投注或投注金额未达标
                        }

                        $value['task_state'] = 1;
                        $taskSuccessData[] = [
                            'user_id' => $user_id,
                            'username' => $userInfo['username'],
                            'type' => $value['id'],
                            'name' => $value['name'],
                            'money' => $value['money'],
                            'remark' => "【{$value['name']}】任务完成，达到领取条件，奖励{$value['money']}元宝",
                            'complete_time' => time(),          //$info['addtime']
                            'invalid_time' => strtotime(date("Y-m-d")) + 86399,
                            'state' => 1,
                            'class' => 2,
                        ];
                    }
                }
                unset($value);
            }

            $tmp["name"] = "今日任务";
            $tmp['data'] = array_values($everydayTask['config']);
            $task_2[] = $tmp;
        }

        if($everydayAddTask && $everydayAddTask['config']) {

            $taskTypeArr = [];

            foreach ($everydayAddTask['config'] as $k=>$value) {

                if($value['state'] == 1) {
                    unset($everydayAddTask['config'][$k]);         //未开启的任务
                    continue;
                }
                $taskTypeArr[] = $value['id'];
            }

            if($taskTypeArr) {
                $sql = "select count(id) as count from un_task_prize where user_id = {$user_id} and complete_time between {$start_time} and {$end_time} and class = 2";
                $count = $this->db->result($sql);           //当日任务累计完成数量
                $n = count($taskSuccessData);
                $count += $n;

                $taskTypeStr = "(".implode(',',$taskTypeArr).")";
                $sql = "select id,state,type from #@_task_prize where user_id = {$user_id} and type in $taskTypeStr and complete_time between {$start_time} and {$end_time}";
                $taskInfo = $this->db->getall($sql);
                $taskStatusArr = array_column($taskInfo, 'state', 'type');
                $taskPrizeIdArr = array_column($taskInfo, 'id', 'type');

                foreach($everydayAddTask['config'] as $k=>&$value) {
                    if($value['id'] == 20) $value['total_task'] = 3;         //完成3个日常任务
                    if($value['id'] == 21) $value['total_task'] = 7;
                    if($value['id'] == 22) $value['total_task'] = 13;
                    $value['complete_task'] = $count;

                    if(array_key_exists($value['id'], $taskStatusArr)) {
                        $value['task_state'] = $taskStatusArr[$value['id']] == 1?1:3;   //任务状态  1未领取  3已领取
                        $value['task_prize_id'] = $taskPrizeIdArr[$value['id']];   //任务状态  1未领取  3已领取
                    }else {
                        $value['task_state'] = 2;       //任务未完成

                        if($count >= $value['total_task']) {
                            $taskSuccessData[] = [
                                'user_id' => $user_id,
                                'username' => $userInfo['username'],
                                'type' => $value['id'],
                                'name' => $value['name'],
                                'money' => $value['money'],
                                'remark' => "【{$value['name']}】任务完成，达到领取条件，奖励{$value['money']}元宝",
                                'complete_time' => time(),          //$info['addtime']
                                'invalid_time' => strtotime(date("Y-m-d")) + 86399,
                                'state' => 1,
                                'class' => 3,
                            ];
                            $value['task_state'] = 1;
                        }
                        unset($value);
                    }
                }
            }

            $tmp["name"] = "任务达成";
            $tmp['data'] = $everydayAddTask['config'];
            $task_3[] = $tmp;
        }

        if($taskSuccessData) {
            $this->db->insert('un_task_prize',$taskSuccessData);
        }

        $taskInfo = array_merge($task_1,$task_2,$task_3);
        return $taskInfo;
    }


    /**
     * 获取平台任务列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-19 14:22:05
     * @param $config Array 平台任务配置信息
     * @param $user_id Int 当前登录的用户ID
     * @return Array
     */
    public function taskIndex($config,$user_id){
        $task_1 = [];
        $task_2 = [];
        $task_3 = [];
        $start_time = strtotime(date("Y-m-d"));
        $end_time = strtotime(date("Y-m-d")) + 86399;
        $monday = this_monday();
        $sunday = this_sunday() + 86399;
        foreach ($config as $key=>$value) {
            if (empty($value)) {
                continue;
            }
            foreach ($value['config'] as $k => $v) {
                if ($v['state'] == 1) {
                    unset($value['config'][$k]);
                } else {

                    if (!in_array($v['id'],[1,2,3,4,5,6])) {
                        $sql = "select id,state from #@_task_prize where user_id = {$user_id} and type = {$v['id']} and complete_time between {$start_time} and {$end_time}";
                    } else {
                        $sql = "select id,state from #@_task_prize where user_id = {$user_id} and type = {$v['id']}";
                    }
                    $list = $this->db->getone($sql);
                    if (empty($list)) {
                        $value['config'][$k]['task_state'] = 2;//未完成
                    } else {
                        $value['config'][$k]['task_state'] = $list['state'] == 1 ? 1 : 3;  //1未领取  3已领取
                        $value['config'][$k]['task_prize_id'] = $list['id'];
                    }

                    if ($v['id'] == 7) {
                        $value['config'][$k]['is_sign'] = 0;
                        $value['config'][$k]['count'] = 0;
                        $sql = "select id,complete_time  from #@_task_prize where user_id = {$user_id} and type = {$v['id']} and complete_time between {$monday} and {$sunday}";
                        $axxx = $this->db->getall($sql);
                        foreach ($axxx as $axxx_val) {
                            $value['config'][$k]['count']++;
                            if ($axxx_val['complete_time'] >= $start_time && $axxx_val['complete_time'] <= $end_time) {
                                $value['config'][$k]['is_sign'] = 1;
                            }
                        }

                    } elseif (in_array($v['id'],[8,9])) {

                        $money_info = $this->db->getall("select money from #@_account_recharge where user_id = {$user_id} and addtime between {$start_time} and {$end_time} and status = 1");
                        $check = false;
                        $total_money = 0;
                        if (!empty($money_info)) {
                            foreach ($money_info as $money_one) {
                                $total_money += $money_one['money'];
                                if ($money_one['money'] >= $v['money_bal']) {
                                    $check = true;
                                }
                            }
                        }


                        if ($v['id'] == 8) {

                            if ($check) {
                                $arr = $this->taskSuccess($v['id'], $user_id);
                                if (is_array($arr)) {
                                    $value['config'][$k]['task_state'] = $arr['state'] == 1 ? 1 : 3;  //1未领取  3已领取
                                } else {
                                    $value['config'][$k]['task_state'] = 1;
                                }
                            } else {
                                $value['config'][$k]['task_state'] = 2;
                            }

                        } elseif($v['id'] == 9) {

                            if ($total_money >= $v['money_bal']) {

                                $arr = $this->taskSuccess($v['id'], $user_id);
                                if (is_array($arr)) {
                                    $value['config'][$k]['task_state'] = $arr['state'] == 1 ? 1 : 3;  //1未领取  3已领取;
                                } else {
                                    $value['config'][$k]['task_state'] = 1;
                                }

                            } else {
                                $value['config'][$k]['task_state'] = 2;
                            }
                            $value['config'][$k]['recharge'] = intval($total_money);

                        }

                    } elseif (in_array($v['id'],[10,11,12,13,14,15,16,17,18,19,24])) {
                        $lottery_type = 0;
                        switch ($v['id']) {
                            case 10: //投注幸运28
                                $lottery_type = 1;
                                break;
                            case 11://投注加拿大28
                                $lottery_type = 3;
                                break;
                            case 12://投注北京PK10
                                $lottery_type = 2;
                                break;
                            case 13://投注幸运飞艇
                                $lottery_type = 4;
                                break;
                            case 14://注急速PK10
                                $lottery_type = 9;
                                break;
                            case 15://投注重庆时时彩
                                $lottery_type = 5;
                                break;
                            case 16://投注三分彩
                                $lottery_type = 6;
                                break;
                            case 17://投注香港六合彩
                                $lottery_type = 7;
                                break;
                            case 18://投注急速六合彩
                                $lottery_type = 8;
                                break;
                            case 19://投注百人牛牛
                                $lottery_type = 10;
                                break;
                            case 24://投注分分彩
                                $lottery_type = 11;
                                break;
                        }
                        $sql = "select sum(money) as money from #@_orders where user_id = {$user_id} and addtime between {$start_time} and {$end_time} and state = 0 and is_legal = 1 and lottery_type = {$lottery_type} and award_state != 0";
                        $money = $this->db->getone($sql)['money'];
                        if($money >= $v['money_bal'] ) {
                            $arr = $this->taskSuccess($v['id'], $user_id);
                            if (is_array($arr)) {
                                $value['config'][$k]['task_state'] = $arr['state'] == 1 ? 1 : 3;  //1未领取  3已领取;
                            } else {
                                $value['config'][$k]['task_state'] = 1;
                                $value['config'][$k]['task_prize_id'] = $arr;
                            }

                        } else {
                            $value['config'][$k]['task_state'] = 2;
                        }

                        $value['config'][$k]['betting'] = intval($money);

                    } elseif(in_array($v['id'],[20,21,22])) {

                        $sql = "select count(id) as count from #@_task_prize where user_id = {$user_id} and complete_time between {$start_time} and {$end_time} and class = 2";
                        $count = $this->db->getone($sql)['count'];
                        $value['config'][$k]['complete_task'] = $count;
                        switch ($v['id']) {
                            case 20: //完成3个日常任务
                                $value['config'][$k]['total_task'] = 3;
                                if($count >= 3) {
                                    $arr = $this->taskSuccess($v['id'], $user_id);
                                    if (is_array($arr)) {
                                        $value['config'][$k]['task_state'] = $arr['state'] == 1 ? 1 : 3;  //1未领取  3已领取;
                                    } else {
                                        $value['config'][$k]['task_state'] = 1;
                                        $value['config'][$k]['task_prize_id'] = $arr;
                                    }

                                } else {
                                    $value['config'][$k]['task_state'] = 2;
                                }
                                break;
                            case 21://完成7个日常任务
                                $value['config'][$k]['total_task'] = 7;
                                if($count >= 7) {
                                    $arr = $this->taskSuccess($v['id'], $user_id);
                                    if (is_array($arr)) {
                                        $value['config'][$k]['task_state'] = $arr['state'] == 1 ? 1 : 3;  //1未领取  3已领取;
                                    } else {
                                        $value['config'][$k]['task_state'] = 1;
                                        $value['config'][$k]['task_prize_id'] = $arr;
                                    }
                                } else {
                                    $value['config'][$k]['task_state'] = 2;
                                }
                                break;
                            case 22://完成13个日常任务
                                $value['config'][$k]['total_task'] = 13;
                                if($count == 13 ) {
                                    $arr = $this->taskSuccess($v['id'], $user_id);
                                    if (is_array($arr)) {
                                        $value['config'][$k]['task_state'] = $arr['state'] == 1 ? 1 : 3;  //1未领取  3已领取;
                                    } else {
                                        $value['config'][$k]['task_state'] = 1;
                                        $value['config'][$k]['task_prize_id'] = $arr;
                                    }
                                } else {
                                    $value['config'][$k]['task_state'] = 2;
                                }
                                break;
                        }

                    }
                }
            }

            $check_status = $this->doTaskAuth($user_id);
            $value['config'] = array_values($value['config']);
            if ($key == 1) {
                if ($check_status === true) {
                    $tmp["name"] = "有奖任务";
                    $tmp['data'] = $value['config'];
                    $task_1[] = $tmp;
                } else {
                    $tmp["name"] = "有奖任务";
                    $tmp['data'] = array($check_status);
                    $task_1[] = $tmp;
                }
            } elseif($key == 2) {
                foreach ($value['config'] as $key=>$val) {
                    if ($val['id'] == 7) {
                        if ($check_status !== true) {
                            unset($value['config'][$key]);
                        }
                    }
                }
                $value['config'] = array_values($value['config']);
                $tmp["name"] = "今日任务";
                $tmp['data'] = $value['config'];
                $task_2[] = $tmp;
            } else {
                $tmp["name"] = "任务达成";
                $tmp['data'] = $value['config'];
                $task_3[] = $tmp;
            }
        }

        $taskInfo = array_merge($task_1,$task_2,$task_3);
        return $taskInfo;
    }

    /**
     * 完成平台任务操作
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-19 18:22:23
     * @param $type int 平台任务类型
     * @param $user_id int 用户id
     * @return Array
     */
    public function taskSuccess($type, $user_id){

        $config = $this->getTaskConfig();
        if (empty($config)) {
            return true;
        }

        $arr = [];
        foreach ($config as $key=>$val) {
            foreach ($val['config'] as $k=>$v) {
                $v['class'] = $key;
                if ($key == 2 || $key == 3) {
                    $v['invalid_time'] = strtotime(date("Y-m-d")) + 86399;
                }
                $v['reward_money'] = $v['money'];
                if (in_array($v['id'],[1,2,3,4,5,6,7,20,21,22])) {
                    if ($v['id'] == 7){
                        $tmp = [
                            'money' => $v['money_bal'],
                            'class' => 2,
                            'name' => "连续签到7次",
                            'remark' => "【连续签到7次】任务完成，达到领取条件，奖励{$v['money_bal']}元宝",
                            'reward_money' => $v['money_bal'],
                            'id' => 23,
                            'state' => $v['state'],
                            'invalid_time' => strtotime(date("Y-m-d")) + 86399
                        ];
                        $arr[] = $tmp;
                    }
                    $v['remark'] = "【{$v['name']}】任务完成，达到领取条件，奖励{$v['money']}元宝";
                } else {
                    if (in_array($v['id'],[8,9,10,11,12,13,14,15,16,17,18,19,24])) {
                        $v['remark'] = "【{$v['name']}】达【{$v['money_bal']}元宝】任务完成，达到领取条件，奖励{$v['money']}元宝";
                    }
                }
                $arr[] = $v;
            }
        }
        $start_time = strtotime(date("Y-m-d"));
        $end_time = strtotime(date("Y-m-d")) + 86399;
        $monday = this_monday();
        $sunday = this_sunday() + 86399;

        foreach ($arr as $val) {
            if ($type == $val['id'] && $val['state'] == 2) {
                if (!in_array($val['id'],[1,2,3,4,5,6])) {
                    if ($val['id'] == 23) {
                        $sql = "select id,state from #@_task_prize where user_id = {$user_id} and type = {$val['id']} and complete_time between {$monday} and {$sunday}";
                    } else {
                        $sql = "select id,state from #@_task_prize where user_id = {$user_id} and type = {$val['id']} and complete_time between {$start_time} and {$end_time}";
                    }
                } else {
                    $sql = "select id,state from #@_task_prize where user_id = {$user_id} and type = {$val['id']}";
                }
                $list = $this->db->getone($sql);
                if (!empty($list)) {
                    lg("platform_task","查询结果::".print_r($list,true)."->当前任务已完成执行sql语句::".$this->db->_sql());
                    return $list;
                }
                $data = [
                    'user_id'=> $user_id,
                    'username' => $this->db->getone("select username from #@_user where id = {$user_id}")['username'],
                    'type' => $type,
                    'name' => $val['name'],
                    'remark' => $val['remark'],
                    'money' => $val['money'],
                    'complete_time' => time(),
                    'invalid_time' => isset($val['invalid_time']) ? $val['invalid_time'] : 0 ,
                    'state' => 1,
                    'class' => $val['class']
                ];
                $rows = $this->db->insert("#@_task_prize", $data);
                if ($rows > 0) {
                    return $rows;
                } else {
                    lg("platform_task","插入数据结果::".print_r($rows,true)."->执行sql语句::".$this->db->_sql());
                    return false;
                }

            }
        }
        return true;
    }

    /**
     * 定时任务，更改已经失效的奖励
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-20 10:21:50
     */
    public function autoTaskState(){
        $sql = "UPDATE #@_task_prize SET state = 3 WHERE class in(2,3) and state = 1 AND invalid_time > 0 AND invalid_time < ".strtotime(date("Y-m-d"));
        $this->db->exec($sql);
        $res = $this->db->affected_rows();
        //lg("platform_task","自动执行结果::".print_r($res,true)."->执行sql语句::".$sql);
    }

    /**
     * 统计任务列表数据
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-20 11:28:21
     * @return Array
     */
    public function taskTotal($where){
        $sql1 = "select 
          sum( case when state = '1' then money end) as 'complete_money',
          sum( case when state = '2' then money end) as 'receive_money',
          sum( case when state = '3' then money end) as 'invalid_money'
          from #@_task_prize $where";
        $total = $this->db->getone($sql1);
        $total['complete_money'] = $total['complete_money'] == 0 ? 0 : $total['complete_money'];
        $total['receive_money'] = $total['receive_money'] == 0 ? 0 : $total['receive_money'];
        $total['invalid_money'] = $total['invalid_money'] == 0 ? 0 : $total['invalid_money'];
        return $total;
    }

    /**
     * 领取奖励接口
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-20 11:40:09
     * @param $user_id int 用户ID
     * @param $id int un_task_prize表的主键ID
     * @return Boolean
     */
    public function receiveTaskReward($user_id, $id){
        $sql = "select * from #@_task_prize where id = $id LIMIT 1 for update";
        $list = $this->db->getone($sql);
        $this->db->query('BEGIN');//开启事务
        
        if (empty($list) ) {
            return "无效ID";
        }
        if ($list['state'] == 3 || $list['state'] == 2) {
            return "奖励已失效或已被领取";
        }


        $config = $this->getTaskConfig();

        $total = $config[$list['class']]['total'];
        $start_time = strtotime(date("Y-m-d"));
        $end_time = strtotime(date("Y-m-d")) + 86399;
        if ($list['class'] != 1) {
            $sql = "select SUM(money) as money from #@_task_prize where complete_time between '{$start_time}' and '{$end_time}' and state = 2 and class = {$list['class']}";
        } else {
            $sql = "select SUM(money) as money from #@_task_prize where state = 2 and class = {$list['class']}";
        }
        $total_money = $this->db->getone($sql)['money'];

        if (bccomp(bcadd($total_money,$list['money']),$total) > 0 && $total > 0) {
            return "领取限额已达上限";
        }

        //当前余额
//        $sql = "SELECT money FROM `un_account` WHERE user_id={$user_id}";
        if(!empty(C('db_port'))){ //使用mycat时 查主库数据
            $sql="/*#mycat:db_type=master*/ SELECT money FROM `un_account` WHERE user_id={$user_id} FOR UPDATE";
        }else{
            $sql="SELECT money FROM `un_account` WHERE user_id={$user_id} FOR UPDATE";
        }

        $use_money = $this->db->result($sql);
        $this->db->query('BEGIN');//开启事务
        $order_num = 'TASK' . date("YmdHis") . rand(100, 999);
        $insert_money_data = [
            'user_id' => $list['user_id'],
            'money' => $list['money'],
            'use_money' => bcadd($use_money,$list['money'],2),
            'remark' => $list['remark'],
            'verify' => 0,
            'addtime' => time(),
            'addip' => ip(),
            'admin_money' => 0,
            'reg_type' => $this->db->getone("select reg_type from un_user where id = '{$list['user_id']}'")['reg_type'],
            'type' => 994,
            'order_num' => $order_num
        ];

        //修改任务列表的领取状态
        $res1 = $this->db->update("#@_task_prize", ['state'=> 2,'order_num'=>$order_num,'receive_time'=>time()], ['id'=> $id]);
        lg("platform_task","res1执行结果::".print_r($res1,true)."->执行sql语句::".$this->db->_sql());

        //如果奖品为彩金，添加资金明细表
        $res2 = $this->db->insert('un_account_log', $insert_money_data);
        lg("platform_task","res2执行结果::".print_r($res2,true)."->执行sql语句::".$this->db->_sql());

        //添加彩金到用户账户
        $res3 = $this->db->exec("UPDATE un_account SET `money` = `money` + '{$insert_money_data['money']}' WHERE user_id = '{$list['user_id']}'");
        lg("platform_task","res2执行结果::".print_r($res3,true)."->执行sql语句::".$this->db->_sql());

        if ($res1 !== false && $res2 > 0 && $res3 !== false) {
            $this->db->query('COMMIT');//提交事务
            return true;
        } else {
            $this->db->query('ROLLBACK');//事务回滚
            return false;
        }
    }

    /**
     * 获取平台任务配置
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-24 19:37:52
     * @return Array
     */
    public function getTaskConfig(){
        $config = [];
        $redis = initCacheRedis();
        $configJson = $redis->hGet('Config:task_config', 'value');
        $config = json_decode($configJson,true);
        deinitCacheRedis($redis);
        return $config;
    }

    /**
     * 活动背景设置方法
     * @copyright gpgao
     * @date 2018-06-06 17:49:05
     * @param activity_type int 1:大转盘 2:九宫格
     * @param back_type int 1:默认模板 2:端午节模板 3:世界杯模板
     * @return void
     */
    public function backConfAct($data){
        $redis = initCacheRedis();
        $configJson = $redis->hGet('Config:back_ground_config', 'value');
        $config = json_decode($configJson,true);
        deinitCacheRedis($redis);

        if (empty($config)) {

            $post_data = [
                'nid' => "back_ground_config",
                'value' => encode([['activity_type'=>$data['activity_type'],'back_type'=>$data['back_type']]]),
                'name' => "活动背景图设置",
                'desc' => "activity_type(1:大转盘 2:九宫格)  back_type(1:默认模板 2:端午模板 3:世界杯模板)"
            ];
            $res = $this->db->insert('#@_config', $post_data);

        } else {
            $check = true;
            foreach ($config as &$val) {
                if ($val['activity_type'] == $data['activity_type']) {
                    $val['back_type'] = $data['back_type'];
                    $check = false;
                }
            }
            unset($val);
            if ($check) {
                $config[] = ['activity_type'=>$data['activity_type'],'back_type'=>$data['back_type']];
            }
            $res = $this->db->update("#@_config", ['value'=> encode($config)], ['nid'=> "back_ground_config"]);
        }
        $this->refreshRedis("config", "all");
        return $res;
    }

    /**
     * 判断用户是否有权限参加活动
     * @copyright gpgao
     * @date 2018-06-22 18:01:02
     */
    public function doTaskAuth($user_id){
//        $start_time = date("Y-m-d 00:00:00",strtotime("-30 day"));
//        $end_time = date("Y-m-d 00:00:00",time());

        $start_time = strtotime(date("Y-m-d"));
        $end_time = strtotime(date("Y-m-d 23:59:59"));

        $arr = [];
        $sql = "select sum(money) as betting from #@_account_log where type = '13' and user_id = {$user_id} and addtime BETWEEN '{$start_time}' and '{$end_time}'";
        $count_money_info['betting'] = $this->db->result($sql);
        $count_money_info['betting'] = empty($count_money_info['betting']) ? 0 : $count_money_info['betting'];
        $sql = "select sum(money) as recharge from #@_account_log where type = '10' and user_id = {$user_id} and addtime BETWEEN '{$start_time}' and '{$end_time}'";
        $count_money_info['recharge'] = $this->db->result($sql);
        $count_money_info['recharge'] = empty($count_money_info['recharge']) ? 0 : $count_money_info['recharge'];

        $redis = initCacheRedis();
        $configJson = $redis->hGet('Config:task_limit', 'value');
        $task_limit = json_decode($configJson,true);
        deinitCacheRedis($redis);
        if (empty($task_limit)) {
            $task_limit['betting'] = 0;
            $task_limit['recharge'] = 0;
        }
        if (!($count_money_info['betting'] >= $task_limit['betting'] || $count_money_info['recharge'] >= $task_limit['recharge'])) {
            $arr['betting_need_money'] = $task_limit['betting'];
            $arr['recharge_need_money'] = $task_limit['recharge'];
            $arr['betting_money'] = $count_money_info['betting'];
            $arr['recharge_money'] = $count_money_info['recharge'];
            return $arr;
        }
        return true;
    }


    /**
     * 添加/修改福袋活动方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-26 19:21
     * @param array $data
     * @return array
     */
    public function addLuckyBagEdit($data){
        unset($data['m'],$data['c'],$data['a']);
        $data['value']['details'] = str_replace("\n","<br />",trim($data['value']['details']));
        $data['value']['statement'] = str_replace("\n","<br />",trim($data['value']['statement']));
        $data['value'] = json_encode($data['value'],JSON_UNESCAPED_UNICODE);
        $data['rules_play'] = json_encode($data['rules_play'],JSON_UNESCAPED_UNICODE);
        $data['level_limit'] = json_encode($data['level_limit'],JSON_UNESCAPED_UNICODE);
        $data['add_time'] = time();
        $data['is_send_prize'] = 3;
        $this->db->query('BEGIN');//开启事务
        if(empty($data['id'])){
            if($data['state'] == 1){
                $state = $this->db->getone("select id from un_activity where activity_type = {$data['activity_type']} and state = 1");
                if (!empty($state)){
                    $arr['code'] = "-1";
                    $arr['msg'] = "已经有开启的活动，无法创建新的活动";
                    return $arr;
                }
            }
            $rows = $this->db->insert("#@_activity", $data);
            if($rows) {
                $log_remark = '新增'.$this->actType[$data['activity_type']].'--活动名称:'.$data['title'].'--状态:'.$this->stateArr[$data['state']].'--期数:'.$data['event_num'];
                admin_operation_log($data['add_admin_id'], 120, $log_remark);
            }
        } else {
            $data['update_time'] = time();
            $rows = $this->db->update("#@_activity", $data, ['id'=>$data['id']]);
        }

        $list = $this->db->getone("select value,id from #@_config where nid = 'lucky_bag_max'");
        if(empty($list)){
            $post_data = [
                'nid'=>'lucky_bag_max',
                'value'=> $data['event_num'] + 1,
                'name'=>'福袋活动最大期数',
            ];
            $row1 = $this->db->insert("#@_config", $post_data);
        } else {
            $row1 = $this->db->update("#@_config", ['value'=> $data['event_num'] + 1], ['id'=>$list['id']]);
        }

        if(($rows > 0 || $rows !== false) || ($row1 > 0 || $row1 !== false)){
            $this->refreshRedis("config", "all");
            $arr['code'] = "0";
            $arr['msg'] = "操作成功";
            $this->db->query('COMMIT');//提交事务

        } else {
            $arr['code'] = "-1";
            $arr['msg'] = "操作失败";
            $this->db->query('ROLLBACK');//事务回滚
        }
        return $arr;
    }

    /**
     * 添加/修改刮刮乐活动方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-26 19:21
     * @param array $data
     * @return array
     */
    public function addScratchEdit($data){
        unset($data['m'],$data['c'],$data['a']);
        $data['value']['details'] = str_replace("\n","<br />",trim($data['value']['details']));
        $data['value']['statement'] = str_replace("\n","<br />",trim($data['value']['statement']));
        $data['value'] = json_encode($data['value'],JSON_UNESCAPED_UNICODE);
        $data['rules_play'] = json_encode($data['rules_play'],JSON_UNESCAPED_UNICODE);
        $data['level_limit'] = json_encode($data['level_limit'],JSON_UNESCAPED_UNICODE);
        $data['add_time'] = time();
        $data['is_send_prize'] = 3;
        $this->db->query('BEGIN');//开启事务
        if(empty($data['id'])){
            if($data['state'] == 1){
                $state = $this->db->getone("select id from un_activity where activity_type = {$data['activity_type']} and state = 1");
                if (!empty($state)){
                    $arr['code'] = "-1";
                    $arr['msg'] = "已经有开启的活动，无法创建新的活动";
                    return $arr;
                }
            }
            $rows = $this->db->insert("#@_activity", $data);
            if($rows) {
                $log_remark = '新增'.$this->actType[$data['activity_type']].'--活动名称:'.$data['title'].'--状态:'.$this->stateArr[$data['state']].'--期数:'.$data['event_num'];
                admin_operation_log($data['add_admin_id'], 120, $log_remark);
            }
        } else {
            $data['update_time'] = time();
            $rows = $this->db->update("#@_activity", $data, ['id'=>$data['id']]);
        }

        $list = $this->db->getone("select value,id from #@_config where nid = 'scratch_max'");
        if(empty($list)){
            $post_data = [
                'nid'=>'scratch_max',
                'value'=> $data['event_num'] + 1,
                'name'=>'刮刮乐活动最大期数',
            ];
            $row1 = $this->db->insert("#@_config", $post_data);
        } else {
            $row1 = $this->db->update("#@_config", ['value'=> $data['event_num'] + 1], ['id'=>$list['id']]);
        }

        if(($rows > 0 || $rows !== false) || ($row1 > 0 || $row1 !== false)){
            $this->refreshRedis("config", "all");
            $arr['code'] = "0";
            $arr['msg'] = "操作成功";
            $this->db->query('COMMIT');//提交事务

        } else {
            $arr['code'] = "-1";
            $arr['msg'] = "操作失败";
            $this->db->query('ROLLBACK');//事务回滚
        }
        return $arr;
    }



}
