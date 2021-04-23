<?php

/**
 * 用户表model
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class BetRankModel extends CommonModel {


    private function getPrevWeekTime() {
        $s_time = mktime(0,0,0,date('m'),date('d')-date('w')+1-7,date('Y'));    //上周开始时间
        $e_time = mktime(23,59,59,date('m'),date('d')-date('w')+7-7,date('Y'));     //上周结束时间
        return [$s_time, $e_time];
    }

    //当天前N天 开始时间至当天前1天结束时间
    private function getBeforeTime($days) {
        $s_time = strtotime(date('Y-m-d',strtotime("-$days days")));
        $e_time = strtotime(date('Y-m-d')) - 1;
        return [$s_time, $e_time];
    }


    //今日投注排行榜
    public function toDayBetRank() {
        $rankNum = 50;      //排行榜数量
        $time = $this->getBeforeTime(7);
        $timeWhere = ' and addtime >= '.$time[0].' and addtime <= '.$time[1];

        $cache_key = "bet_rank_".date('Y-m-d').'_maxN_'.$rankNum;
        $redis = initCacheRedis();
        $rankLisit = $redis->get($cache_key);
        if(!$rankLisit) {
            $dummy_rank_sql = "SELECT user_id,bet_money FROM `un_bet_rank` ORDER BY bet_money DESC LIMIT $rankNum";         //后台设置假人榜

            //真实用户投注前50名
            $bet_log_rank_sql = "SELECT	user_id,SUM(money) AS bet_money	FROM un_account_log LEFT JOIN un_user ON un_account_log.user_id = un_user.id WHERE type = 13 AND un_user.reg_type NOT in (0,8,9,11) $timeWhere GROUP BY user_id ORDER BY bet_money DESC LIMIT $rankNum";

            $fetSql = "SELECT infos.*,uu.nickname,uu.username,uu.avatar,uu.reg_type FROM (($dummy_rank_sql) UNION ALL ($bet_log_rank_sql)) infos LEFT JOIN un_user uu ON infos.user_id = uu.id ORDER BY bet_money DESC LIMIT $rankNum";

            $rankLisit = $this->db->getall($fetSql);
            $redis->set($cache_key, json_encode($rankLisit), 86400);
        }else{
            $rankLisit = json_decode($rankLisit, true);
        }
        deinitCacheRedis($redis);

        return $rankLisit;
    }

    //获取投注英雄榜
    public function getBetRank($user_id, $page = 1) {
        $pagesize = 20;
        $rankLisit = $this->toDayBetRank();
        $offS = ($page - 1) * $pagesize;
        $offE = $page * $pagesize;
        $res = D('user')->getUserFollowUserList($user_id);

        if($res['code']) return $res;

        $resData = [];
        $follow = $res['data'];

        foreach($rankLisit as $k=>$rank) {
            if($k < $offS) continue;
            if($k >= $offE) break;

            $rank['nickname'] = D('workerman')->getNickname($rank['nickname']);

            if(in_array($rank['user_id'], $follow)) {
                $rank['is_follow'] = 1;
            }else {
                $rank['is_follow'] = 0;
            }
            $rank['level'] = get_honor_info($rank['user_id']);
            $resData[] = $rank;
            unset($rank);
        }

        return ['code' => 0, 'msg' => '', 'data' => $resData];
    }

    //获取单条记录
    public function getRankInfo($where) {
        $where = ' WHERE '.$where;
        $fetchSql = "SELECT * FROM un_bet_rank $where";
        $res = $this->db->getone($fetchSql);
        return $res;
    }

    public function getDummyBetRankCount($where = '') {
        if($where)
            $where = ' WHERE '.$where;
        $fetchSql = "SELECT count(*) as total from un_bet_rank $where";
        $res = $this->db->getone($fetchSql);
        return $res['total'];
    }


    //假人投注排行榜
    public function dummyBetRankList($page) {
        $offset = ($page - 1) * 20;
        $fetSql = "SELECT ur.user_id,ur.bet_money,uu.username,ur.id FROM un_bet_rank ur LEFT JOIN un_user uu ON ur.user_id = uu.id ORDER BY ur.bet_money DESC LIMIT $offset,20";
        $rankLisit = $this->db->getall($fetSql);
        return ['code' => 0, 'msg' => '', 'data' => $rankLisit];
    }
}
