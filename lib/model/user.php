<?php

/**
 * 用户表model
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class UserModel extends CommonModel {

    protected  $table = '#@_user';
    
    
    /**
     * 获取用户信息
     */
    public function getUserInfo($field = '*',$where = '' , $limit = '', $order = '', $group = '',$rowsFlag = false)
    {
        $sql = $this->db->c_sql($where,$field,$this->table,$limit,$order,$group);
        
        if($rowsFlag)
        {
            $res = $this->db->query($sql);
            
            return $this->db->num_rows($res);
        }
        else 
        {
            return $limit == 1 ? $this->db->getone($sql) : $this->db->getall($sql);
        }   
    }

    //更新登录信息
    public function updateLoginInfo($userId)
    {
        $userInfo = $this->getUserInfo('loginip,logintime',array('id' => $userId),1);
        if(empty($userInfo))
        {
            return false;
        }
        $ip = D('userlog')->getMaxlistIp($userId);
        
        $data = array(
                'lastloginip' => $userInfo['loginip'],
                'lastlogintime' => $userInfo['logintime'],
                'loginip' => $ip ? $ip : ip(),
                'logintime' => SYS_TIME,
                'logintimes' => '+=1',
        );
        
        return $this->db->update($this->table, $data, array('id' => $userId));
    }
    
    //更新登录信息,添加ip归属地记录
    public function updateLoginInfos($userId, $ipData = [])
    {
        $userInfo = $this->getUserInfo('loginip,logintime,login_ip_attribution',array('id' => $userId),1);
        if(empty($userInfo))
        {
            return false;
        }
        if (empty($ipData)) {
            $ipData['ip'] = '';
            $ipData['attribution'] = '';
        }
        //$ipData = D('userlog')->getMaxlistIps($userId);
        //if ($ipData['ip'] === false) {
        //    $ipData = $ip;
        //}
        $data = array(
            'lastloginip' => $userInfo['loginip'],
            'lastlogintime' => $userInfo['logintime'],
            'last_login_ip_attribution' => $userInfo['login_ip_attribution'],
            'loginip' => $ipData['ip'],
            'login_ip_attribution' => $ipData['attribution'],
            'logintime' => SYS_TIME,
            'logintimes' => '+=1',
            //记录最后登录域名，统一用HTTP_HOST，不用SERVER_NAME
            'last_login_source' => $_SERVER['HTTP_HOST'],
        );
    
        return $this->db->update($this->table, $data, array('id' => $userId));
    }
    
    //更新用户信息
    public function save($data,$where)
    {
        if(empty($data) || empty($where))
        {
            return false;
        }
        
        return $this->db->update($this->table, $data, $where);
    }

    /**
     * 统计当前用户数量
     * @return int
     */
    public function reg_num (){
        $sql = 'SELECT count(id) as num FROM '.$this->table;
        $res = $this->db->getone($sql);
        return $res['num'];
    }

    /*
     * 判断用户是否在IP黑名单之内
     */
    public function isIpBlack($mac,$m,$c,$a)
    {
        $ipBlackInfo = $this->db->getone("select status,url_content from un_ipBlacklist where mac = '".$mac."'");
        if(!empty($ipBlackInfo)){
            if($ipBlackInfo['status'] == 0) {
                if($ipBlackInfo['url_content'] == "*") {
                    return false;
                } else {
                    $ipBlackInfo['url_content'] = explode(",",$ipBlackInfo['url_content']);

                    foreach ($ipBlackInfo['url_content'] as $val) {
                        $url = explode("/",$val);
                        if($url[0] == $m && $url['1'] == $c && $url[2] == $a) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }

    /*
     * 获取能接收提示音的管理员uid
     */
    public function getSoundReceiveUid()
    {
        $uidInfo = [];
        $promptAuth = $this->db->getone("select value from un_config where nid = 'tonePermissions'");
        if(!empty($promptAuth)){
            $promptAuth = json_decode($promptAuth['value']);
            if(!empty($promptAuth)) {
                $tonePermissions = implode(",",$promptAuth);
                $sql = "select userid from un_admin where roleid in(".$tonePermissions.")";
                $row = $this->db->getall($sql);
                if(!empty($row)){
                    $uidInfo = $row;
                }
            }
        }
        return $uidInfo;

    }

    /**
     * 替换团队报表中的所有数据
     */
    public function ReplaceTeamReport($data){

        $this->db->replace("un_team_report",$data);

    }
    
    /**
     * 获取荣誉升级弹出框状态
     * @param $userId 用户ID
     * @return json
     */
    public function getHonorBox($userId)
    {
        $ret = '';
        //查询用户信息
        $user_honor = $this->getUserInfo('honor_score,honor_upgrade', array('id' => $userId), 1);
        if (empty($user_honor['honor_score'])) {
            $user_honor['honor_score'] = 0;
        }
        $score = $user_honor['honor_score'] < 0 ? 0 : $user_honor['honor_score'];
    
        $honor = D('honor')->db->getone("select name, icon, sort, score, grade from un_honor where status = 1 and score <= $score order by score desc");
    
        //暂时没有考虑减分机制，添加是再来调试
        if ($user_honor['honor_upgrade'] < $honor['sort']) {
            $ret = ['code' => 1, 'grade' => $honor['sort']];
        } else {
            $ret = ['code' => 0, 'grade' => ''];
        }
    
        return $ret;
    }

    /**
     * 关闭荣誉升级弹出框
     * @param $userId 用户ID
     * @return json
     */
    public function setHonorBox($userId)
    {
        $ret = '';
        //查询用户信息
        $user_honor = $this->getUserInfo('honor_score,honor_upgrade', array('id' => $userId), 1);
        if (empty($user_honor['honor_score'])) {
            $user_honor['honor_score'] = 0;
        }
        $score = $user_honor['honor_score'] < 0 ? 0 : $user_honor['honor_score'];
    
        $honor = D('honor')->db->getone("select name, icon, sort, score, grade from un_honor where status = 1 and score <= $score order by score desc");
    
        //暂时没有考虑减分机制，添加是再来调试
        if ($user_honor['honor_upgrade'] < $honor['sort']) {
            $ret = $this->save(['honor_upgrade' => $honor['sort']], 'id = ' . $userId);
        }
    
        return $ret;
    }

    /**
     * 获取荣誉等级信息
     * @param $userId 用户ID
     * @return json
     */
    public function getHonor($userId)
    {
        $honorInfo = [];

        $user_honor = D('user')->db->getone("select honor_score from un_user where id = " . $userId);
        if (empty($user_honor['honor_score'])) {
            $user_honor['honor_score'] = 0;
        }
        $score = $user_honor['honor_score'] < 0 ? 0 : $user_honor['honor_score'];
        
        $honor = D('honor')->db->getone("select name, score, sort from un_honor where status = 1 and score <= $score order by score desc");
        
        //判断是否达到最高级 0否，1是
        $honorInfo['next_status'] = 0;
        
        //判断下一级等级
        $nextLevel = D('honor')->db->getone("select name, score, sort from un_honor where status = 1 and sort > " . $honor['sort'] . ' order by score asc');
        if (empty($nextLevel)) {
            //判断下一级等级
            $nextLevel = D('honor')->db->getone("select name, icon, sort, score, grade from un_honor where status = 1 and score < " . $honor['score'] . ' order by score desc');
            $honorInfo['next_status'] = 1;
        }
        
        $honorData = D('honor')->db->getall("select name, sort, score, grade from un_honor where status = 1");
        
        $conf = D('config')->db->result("select value from un_config where nid='level_honor'");
        $config = json_decode($conf,true);
        
        $honorInfo['user_name']  = $honor['name'];
        $honorInfo['user_score'] = $user_honor['honor_score'];
        $honorInfo['user_sort']  = $honor['sort'];
        $honorInfo['next_name']  = $nextLevel['name'];
        $honorInfo['next_score'] = $nextLevel['score'];
        $honorInfo['next_sort']  = $nextLevel['sort'];
        $honorInfo['plus_score'] = $config['plus'];
        $honorInfo['honor']      = $honorData;
        
        return $honorInfo;
    }
    
    //获取积分记录列表
    public function getHonorRecordList($data)
    {
        $where = ' status = 1 AND user_id = ' . $data['user_id'];

        if ($data['type'] > 0) {
            $where .= ' AND type = ' . $data['type'];
        }
    
        $sql="SELECT uil.id, uil.money, uil.score, uil.type FROM un_integral_log as uil 
              WHERE " . $where . " ORDER BY uil.id DESC LIMIT " . $data['pagestart'] . "," . $data['pagesize'];
    
        $scoreList = $this->db->getall($sql);
        
        return $scoreList;
    }
    
    /**
     * 用户积分记录条数
     * @param array $data
     * @return number
     */
    public function getHonorCount($data)
    {
        $where = ' status = 1 AND user_id = ' . $data['user_id'];

        if ($data['type'] > 0) {
            $where .= ' AND type = ' . $data['type'];
        }
    
        $sql="SELECT count(uil.id) as count FROM un_integral_log as uil WHERE " . $where;
    
        $sum = $this->db->getone($sql);
    
        return $sum['count'];
    }
    
    /**
     * 检查是否已经关注
     * @param int $user_id  被关注者ID
     * @param int $myid  关注者ID
     * @return array
     */
    public function checkFollowUser($user_id, $myid)
    {
        $sql = "SELECT id,reg_type FROM un_user WHERE id = {$user_id}";
        $followData = $this->db->getone($sql);
       /*  if (empty($followData)) {
            return['code' => 0, 'msg' => '用户不存在，关注失败！'];
        }

        if ($followData['reg_type'] == 8) {
            return['code' => 0, 'msg' => '关注失败，禁止关注游客！'];
        } */
    
        $sql = "SELECT user_id,follow_user_id FROM un_user_tree WHERE user_id = {$myid}";
        $userData = $this->db->getone($sql);
        /* if (empty($userData)) {
            return['code' => 0, 'msg' => '关注失败，系统错误！'];
        } */
    
        $followUser = trim($userData['follow_user_id'], ',');
        if (!empty($followUser)) {
            $arrFollow = explode(',', $followUser);
            if (in_array($followData['id'], $arrFollow)) {
                return['code' => 0, 'msg' => 'Followed!'];
            }else {
                return['code' => 1, 'msg' => 'You can follow!'];
            }
        }else {
            return['code' => 1, 'msg' => 'You can follow!'];
        }
    }
    
    /**
     * 关注
     * @param int $user_id  被关注者ID
     * @param int $myid  关注者ID
     * @return array
     */
    public function addFollowUser($user_id, $myid)
    {
        $strId = '';
    
        $sql = "SELECT id,reg_type,follow_status FROM un_user WHERE id = {$user_id}";
        $followData = $this->db->getone($sql);
        if (empty($followData)) {
            return['code' => 1, 'msg' => 'The user does not exist, failed in focus!'];
        }
    
        if ($followData['reg_type'] == 8) {
            return['code' => 1, 'msg' => 'Failed to follow, it is forbidden to follow tourists!'];
        }

        if($followData['reg_type'] == 9 || $followData['follow_status'] == 2) {
            return['code' => 1, 'msg' => 'This member is set to prohibit following!'];
        }
    
        $sql = "SELECT user_id,follow_user_id FROM un_user_tree WHERE user_id = {$myid}";
        $userData = $this->db->getone($sql);
        if (empty($userData)) {
            return['code' => 1, 'msg' => 'Follow failed, system error!'];
        }

        $followUser = trim($userData['follow_user_id'], ',');
        if (!empty($followUser)) {
            $arrFollow = array_filter(explode(',', $followUser));
            if (count($arrFollow) >= 100) {
                return['code' => 1, 'msg' => 'Following failed, only 100 users can be followed at most!'];
            }
    
            if (in_array($followData['id'], $arrFollow)) {
                return['code' => 1, 'msg' => 'Please do not follow again!'];
            }
            $strId = $userData['follow_user_id'] . $followData['id'] . ',';
        }else {
            $strId = ',' . $followData['id'] . ',';
        }
    
        $ret = $this->db->update('un_user_tree', ['follow_user_id' => $strId], ['user_id' => $myid]);
    
        if ($ret) {
            return['code' => 0, 'msg' => 'Follow successfully!'];
        }else {
            return['code' => 1, 'msg' => 'Follow failed!'];
        }
    }
    
    /**
     * 取消关注
     * @param int $user_id  被关注者ID
     * @param int $myid  关注者ID
     * @return array
     */
    public function cancelFollowUser($user_id, $myid)
    {
        $sql = "SELECT user_id,follow_user_id FROM un_user_tree WHERE user_id = {$myid}";
        $userData = $this->db->getone($sql);
        if (empty($userData)) {
            return['code' => 1, 'msg' => 'Follow failed, system error!'];
        }

        $followUser = trim($userData['follow_user_id'], ',');
        if (!empty($followUser)) {
            $arrFollow = explode(',', $followUser);
            $flag = 0;
            foreach ($arrFollow as $ka => $va) {
                if ($va == $user_id) {
                    unset($arrFollow[$ka]);
                    $flag = 1;
                    break;
                }
            }
    
            if ($flag) {
                if (!empty($arrFollow)) {
                    $strId = implode(',', $arrFollow);
                    $ret = $this->db->update('un_user_tree', ['follow_user_id' => ',' . $strId . ','], ['user_id' => $myid]);
                }else {
                    $ret = $this->db->update('un_user_tree', ['follow_user_id' => ','], ['user_id' => $myid]);
                }
    
                if ($ret) {
                    return['code' => 0, 'msg' => 'Unfollow successfully'];
                }else {
                    return['code' => 1, 'msg' => 'Unfollow failed!'];
                }
            }else {
                return['code' => 1, 'msg' => 'Not following this user yet!'];
            }
        }else {
            return['code' => 1, 'msg' => 'Not following any users yet!'];
        }
    }

    //获取用户关注的用户列表
    public function getUserFollowUserList($user_id) {
        $sql = "SELECT user_id,follow_user_id FROM un_user_tree WHERE user_id = {$user_id}";
        $userData = $this->db->getone($sql);
        if (empty($userData)) {
            return ['code' => 0, 'msg' => '', 'data' => []];
//            return['code' => 1, 'msg' => '系统错误！'];
        }
        $followUser = trim($userData['follow_user_id'], ',');

        $arrFollow = explode(',', $followUser);
        return ['code' => 0, 'msg' => '', 'data' => $arrFollow];
    }

    /**
     * 房间内获取关注者最近本房间投注没人5条记录
     * @param int $room_id  当前房间ID
     * @param int $myid  用户ID
     * @return array
     */
    public function getRoomFollowUser($room_id, $myid, $page = 0)
    {
        $pagesize = 20;
        $strId = '';
        $userList = [];
    
        $sql = "SELECT user_id,follow_user_id FROM un_user_tree WHERE user_id = {$myid}";
        $userData = $this->db->getone($sql);
        if (empty($userData)) {
            return['code' => 1, 'msg' => 'System error!'];
        }
    
        $followUser = trim($userData['follow_user_id'], ',');
        if (!empty($followUser)) {
            $arrFollow = explode(',', $followUser);
            $start = 0;
            $end = count($arrFollow);
            if($page) {
                $start = ($page-1) * $pagesize;
                $end = $page * $pagesize;
            }

            $betRankData = D('betrank')->toDayBetRank();
            if($betRankData)
                $betRank = array_flip(array_column($betRankData, 'user_id'));

            foreach ($arrFollow as $ka => $va) {
                if($ka < $start) continue;
                if($ka >= $end) break;
                $sql = "SELECT id, nickname, avatar, honor_upgrade FROM un_user WHERE id = {$va}";
                $userData = $this->db->getone($sql);
                $userData['nickname'] = D('workerman')->getNickname($userData['nickname']);
                $sql = "SELECT o.id, o.user_id, o.room_no, o.issue, o.way, o.money, o.single_money, r.title as room_name, lt.name as lottery_name FROM un_orders o
                LEFT JOIN un_room r ON o.room_no = r.id
                LEFT JOIN un_lottery_type lt ON o.lottery_type = lt.id
                WHERE o.user_id = {$va} AND o.room_no = {$room_id} ORDER BY o.addtime DESC LIMIT 5";
                $orderList = $this->db->getall($sql);

                $betSort = isset($betRank[$userData['id']])?($betRank[$userData['id']] + 1):0;
                $userData['betSort'] = $betSort;
    
                $userList[] = ['user' => $userData, 'orderList' => $orderList];
    
            }
        }

        return ['code' => 0, 'msg' => '', 'data' => $userList];
    }
    
    /**
     * 我的关注页面最近投注没人5条记录
     * @param int $user_id  用户ID
     * @return array
     */
    public function getFollowUserOrderList($user_id)
    {
        $sql = "SELECT user_id,follow_user_id FROM un_user_tree WHERE user_id = {$user_id}";
        $userData = $this->db->getone($sql);
        if (empty($userData)) {
            return['code' => 1, 'msg' => 'System error!'];
        }
    
        $followUser = trim($userData['follow_user_id'], ',');
        if (!empty($followUser)) {
            $arrFollow = explode(',', $followUser);
            foreach ($arrFollow as $ka => $va) {
                $sql = "SELECT id, nickname, avatar, honor_upgrade FROM un_user WHERE id = {$va}";
                $userData = $this->db->getone($sql);
                $userData['nickname'] = D('workerman')->getNickname($userData['nickname']);
                $sql = "SELECT o.id, o.user_id, o.room_no, o.issue, o.way, o.money, o.single_money, r.title as room_name, lt.name as lottery_name FROM un_orders o
                LEFT JOIN un_room r ON o.room_no = r.id
                LEFT JOIN un_lottery_type lt ON o.lottery_type = lt.id
                WHERE o.user_id = {$va} ORDER BY o.addtime DESC LIMIT 5";
                $orderList = $this->db->getall($sql);
    
                $userList[] = ['user' => $userData, 'orderList' => $orderList];
    
            }
        }
    
        return ['code' => 0, 'msg' => '', 'data' => $userList];
    }


    public function followUserList($user_id, $page = 1) {
        $res = $this->getUserFollowUserList($user_id);
        if($res['code']) return $res;

        $userList = $res['data'];
        $pagesize = 10;
        if($page) {
            $start = ($page-1) * $pagesize;
            $userList && $userList = array_slice($userList,$start,$pagesize);
        }


        $betRankData = D('betrank')->toDayBetRank();
        if($betRankData)
            $betRank = array_flip(array_column($betRankData, 'user_id'));

        $resData = [];
        foreach($userList as $uid) {

            if(!$uid) continue;

            $sql = "SELECT id, nickname, avatar, honor_upgrade FROM un_user WHERE id = {$uid}";
            $userData = $this->db->getone($sql);
//            if(!$userData) continue;
            $userData['nickname'] = D('workerman')->getNickname($userData['nickname']);

            $betSort = isset($betRank[$userData['id']])?($betRank[$userData['id']] + 1):0;
            $userData['betSort'] = $betSort;
            $resData[] = $userData;
        }

        return ['code' => 0, 'msg' => '', 'data' => $resData];
    }



    /*
     * 用户投注记录
     * */
    public function userBetInfo($condArr = [], $page = 1, $pagesize = 10) {
        $offset = ($page - 1) * $pagesize;

        $where = '1=1 and o.state = 0 and o.is_legal = 1';
        if(isset($condArr['user_id']) && $condArr['user_id']) $where .= ' and o.user_id = '.$condArr['user_id'];
        if(isset($condArr['room_id']) && $condArr['room_id']) $where .= ' and o.room_no = '.$condArr['room_id'];
        if(isset($condArr['lottery_type']) && $condArr['lottery_type']) $where .= ' and o.lottery_type = '.$condArr['lottery_type'];
        if(isset($condArr['start_time']) && $condArr['start_time']) $where .= ' and o.addtime >= '.$condArr['start_time'];
        if(isset($condArr['end_time']) && $condArr['end_time']) $where .= ' and o.addtime <= '.$condArr['end_time'];

        $sql = "SELECT o.id, o.user_id, o.room_no, o.issue, o.way, o.money, o.single_money, r.title as room_name, lt.name as lottery_name FROM un_orders o
                LEFT JOIN un_room r ON o.room_no = r.id
                LEFT JOIN un_lottery_type lt ON o.lottery_type = lt.id
                WHERE $where ORDER BY o.addtime DESC LIMIT $offset,$pagesize";
        $betList = $this->db->getall($sql);
        return $betList;
    }

}
