<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/25
 * Time: 10:00
 * desc: 网站首页model
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class LobbyModel extends CommonModel
{
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
     * 信息系统公告
     * @param $uid int 用户ID, 0:表示公告所有人
     * @return array
     */
    public function getSysMessageList($uid = 0)
    {
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
                $list[] = ['id' => $val['id'], 'title' => $val['title'], 'content' => $val['content']];
            }
        }
        $arr['num'] = count($ids) - $tmpNum;
        $arr['list'] = $list;
        $arr['lastDetail'] = empty($ids) ? '' : ['id' => $ids[0]['id'], 'title' => $ids[0]['title'], 'content' => $ids[0]['content']];
        return $arr;
    }
    
    /**
     * 信息系统公告
     * @param $uid int 用户ID, 0:表示公告所有人
     * @return array
     */
    public function getUserMessageList($uid = 0)
    {
        $user = "|".$uid."|";
        $tmpNum = 0;
        $sql = "select * from un_message where touser_id like '%|{$uid}|%' and type = 2 order by addtime desc";
        $ids = $this->db->getall($sql);
        foreach($ids as $key=>$val)
        {
            if(strpos($val['has_read'],"|".$uid."|") !== false)
            {
                $tmpNum ++;
                $ids[$key]['has_read'] = 0;
            }else {
                $ids[$key]['has_read'] = 1;
            }
            
            if($val['recom'] == 2)
            {
                $list[] = ['id' => $val['id'], 'title' => $val['title']];
            }
        }

        $arr['num'] = count($ids) - $tmpNum;
        $arr['list'] = $list;
        $arr['lastDetail'] = empty($ids) ? '' : ['id' => $ids[0]['id'], 'title' => $ids[0]['title'], 'content' => $ids[0]['content']];
        return $arr;
    }
    
    /**
     * 获取最近投注的20条投注信息
     * @return array
     */
    public function getBettingList($num = 20)
    {
        $retLst = [];
        $now = time();
        $dummyListSql = "SELECT user_id,lottery_type,bet_money money,bet_time addtime,way FROM un_bet_list WHERE bet_time < $now ORDER BY bet_time DESC LIMIT $num";
        $orderListSql = "SELECT user_id,lottery_type,money,addtime,way FROM un_orders WHERE is_legal = 1 AND lottery_type != 12 ORDER BY id DESC LIMIT $num";
        $sql = "SELECT infos.*,u.nickname,1 as s_type FROM (($dummyListSql) UNION ALL ($orderListSql)) infos LEFT JOIN un_user u ON u.id = infos.user_id ORDER BY addtime desc LIMIT 4";     //4条投注动态
        $orderInfo = $this->db->getall($sql);

        //TODO 8条中奖记录  按金额排序
        $sql = "SELECT uu.nickname,uu.id,uo.award,uo.addtime,lottery_type,2 as s_type FROM `un_orders` uo left join un_user uu on uo.user_id = uu.id WHERE uo.state = 0 and award_state = 2 and is_legal = 1 ORDER BY uo.award DESC LIMIT 8";
        $winInfo = $this->db->getall($sql);

        //TODO 8条提现记录  按金额排序
        $sql = "SELECT uu.nickname,uu.id,ual.money,ual.addtime,3 as s_type FROM `un_account_log` ual left join un_user uu on ual.user_id = uu.id WHERE ual.type = 11 ORDER BY ual.money DESC LIMIT 8";
        $cashInfo = $this->db->getall($sql);

        $resData = array_merge($orderInfo, $winInfo, $cashInfo);
        $sortKey = array_column($resData, 'addtime');
        array_multisort($sortKey, SORT_DESC, $resData);
//        $resData = quickSort($resData, 'addtime', false);
        $redis = initCacheRedis();
        foreach($resData as &$res) {
            $res['username'] = interceptChinese($res['nickname']);
            if($res['s_type'] == 1) $res['way'] = is_numeric($res['way']) ? '数字' . $res['way'] : $res['way'];
            if($res['s_type'] != 3) $res['lottery_name'] = $lottery_title = $redis->hGet("LotteryType:{$res['lottery_type']}",'name');
            $res['addtime'] = date('m-d H:i', $res['addtime']);
            unset($res);
        }
        deinitCacheRedis($redis);
        return $resData;

//        $sql = 'SELECT o.id, o.addtime, u.nickname, u.realname, lt.name, o.way, o.money FROM `un_orders` o
//            lEFT JOIN `un_user` u ON o.`user_id` = u.`id`
//            LEFT JOIN `un_lottery_type` lt ON o.lottery_type = lt.`id`
//            WHERE o.`is_legal` = 1 AND o.lottery_type != 12 ORDER BY o.`id` DESC LIMIT ' . $num;


    }
    
    /**
     * 统计当前所有有效注册用户数量
     * @return int
     */
    public function regNum()
    {
        $sql = 'SELECT count(id) as num FROM `un_user` WHERE `reg_type` NOT IN (8,9,11)';
        $res = $this->db->getone($sql);
        return $res['num'];
    }
    
    /**
     * 统计当前所有用户提现次数
     * @return int
     */
    public function withdrawNum()
    {
        $sql = 'SELECT count(id) as num FROM `un_account_cash` WHERE `status` = 1';
        $res = $this->db->getone($sql);
        return $res['num'];
    }

    /**
     * 定时修改首页数据的配置值（每天定时增加数据）
     * 2018-04-04
     */
    public function updateIndexVirtualData()
    {
        //随机范围 已赚元宝总数：1000~10000，注册人数：1~100
        $rand_value_a = mt_rand(1000, 10000);
        $rand_value_b = mt_rand(1, 100);

        //修改虚拟数据语句
        $update_sql = "UPDATE `un_config` 
            SET `value` = `value` + (CASE nid WHEN '100001' THEN {$rand_value_a} WHEN '100003' THEN {$rand_value_b} END)
            WHERE nid IN ('100001','100003')";

        try {
            $exec_result = $this->db->query($update_sql);

            //保存后更新redis缓存
            $this->refreshRedis('config', 'all');

            lg('update_index_virtual_data', var_export([
                '修改语句 update_sql' => $update_sql,
                '已赚元宝总数-随机值 rand_value_a' => $rand_value_a,
                '注册人数-随机值 rand_value_b' => $rand_value_b,
                '执行结果 exec_result' => $exec_result,
            ], true));

            echo 'ok';
            return true;
        } catch (Exception $e) {
            lg('update_index_virtual_data', '更新首页虚拟数据错误，相关值：' . var_export([
                '修改语句 update_sql' => $update_sql,
                '异常信息-$e' => $e,
            ], true));

            echo 'failure';
            return false;
        }
    }
}
