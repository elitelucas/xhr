<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/14
 * Time: 11:49
 * desc: 配置
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class HonorModel extends CommonModel
{
    protected $table = '#@_honor';

    //根据id获取一条记录
    public function getOne($nid) {
        return $this->db->getone("select * from " . $this->table . " where nid = '{$nid}'");
    }
    
    //获取等级最高的sort
    public function getListCount() {
        return $this->db->getone("select max(sort) as maxs from " . $this->table);
    }
    
    //获取积分列表
    public function getSortList() {
        return $this->db->getall("select name, sort from " . $this->table . ' where status = 1');
    }

    //修改极光推送配置
    public function editJPush($value){
        $table = 'un_config';
        return $this->db->update($table, array("value" => $value), array("nid" => 'JPush_config'));
    }
    
    //初始化用户积分
    public function initializeUser()
    {
        $model = O('model');
        $userData = $model->db->query("select id, honor_status from un_user where honor_status = 0");
        
        if (empty($userData)) {
            return '没有可初始化积分的用户';
        }
        
        foreach ($userData as $k => $v) {
            //充值金额统计sql;
            $recharge_sql = "SELECT SUM(ar.money) AS recharge_money FROM `un_account_recharge` as ar
                            WHERE ar.status = 1 AND ar.user_id = " . $v['id'];
            $recharge_result = $model->db->getone($recharge_sql);
            //投注金额统计sql
            $bet_sql = "SELECT SUM(o.money) AS bet_money FROM `un_orders` as o WHERE o.state = 0 AND o.user_id = " . $v['id'];
            $bet_result = $model->db->getone($bet_sql);
            //中奖金额统计sql
            $award_sql = "SELECT SUM(o.award) AS award_money FROM `un_orders` as o WHERE o.award_state = 2 AND o.state = 0  AND o.user_id = " . $v['id'];
            $award_result = $model->db->getall($award_sql);
            
            
            $model->db->query('BEGIN');
            
            try {
                if (!empty($recharge_result['recharge_money'])) {
                    $this->exchangeIntegral($recharge_result['recharge_money'], $v['id'], 1); //充值积分兑换
                }

                if (!empty($bet_result['bet_money'])) {
                    $this->exchangeIntegral($bet_result['bet_money'], $v['id'], 2);           //投注积分兑换
                }
                
                if (!empty($award_result['award_money'])) {
                    $this->exchangeIntegral($award_result['award_money'], $v['id'], 3);       //中奖积分兑换
                }

                D('user')->db->update('un_user', ['honor_status' => 1], 'id = ' . $v['id']); //修改标志是否已经初始化

                $model->db->query('COMMIT');

            } catch (Exception $e) {
                $model->db->query('ROLLBACK');

                return '初始数据出错，部分积分数据未初始化，请重新点击"初始化用户积分"按钮';
            }
        }
        
        return '初始化成功！';
        
    }
    
    public function sameUsernameList()
    {
        $listData = [];
        $model = O('model');

        $sql = "select * from `un_user_same`";
        $listData = $model->db->getall($sql);
    
        return $listData;
    }
    /*
    public function sameUsernameList()
    {
        $listData = [];
        $model = O('model');
    
        $sql = "select `id`, `same_str`, `user_id_str` from `un_user_same`";
        $same_id = $model->db->getall($sql);
    
        foreach ($same_id as $k => $v) {
            $sql = "SELECT `id`,`username` FROM `un_user` WHERE `id` in (" . $v['user_id_str'] . ")";
            $userData = $model->db->getall($sql);
            $listData[$v['same_str']] = $userData;
        }
    
        return $listData;
    }
    */

    //检查用户名（不区分大小写时）是否相等
    public function checkUsername()
    {
        $arrId = [];
        $model = O('model');
        $userId = $model->db->getone("select count(`id`) as ids from un_user_copy");
    
        //$model->db->query("DELETE FROM `un_user_same`");

        $sql = "SELECT `name` FROM `un_bank_info` WHERE `id` = 100";
        $count_id = $model->db->getone($sql);
        
        $is = $count_id['name'];
        $ret_id = $userId['ids'] - $count_id['name'];

        try {
            for ($i = $is; $i <= $ret_id; $i++) {

                $sql = "SELECT `username` FROM `un_user_copy` WHERE `id` = " . $i;
                $arr_name = $model->db->getone($sql);
                if (empty($arr_name)) {
                    $sql = 'update `un_bank_info` set name = ' . $i . ' WHERE `id` = 100';
                    $model->db->exec($sql);
                    continue;
                }

                $sql = "SELECT `id` FROM `un_user_copy` WHERE UPPER(`username`) = UPPER('" . $arr_name['username'] . "')";
                $same_id = $model->db->getall($sql);
                if (count($same_id) < 2) {
                    //修改用户积分
                    $sql = 'update `un_bank_info` set name = ' . $i . ' WHERE `id` = 100';
                    $model->db->exec($sql);
                    continue;
                }
                
                $aid = array_column($same_id, 'id');
                $arrId = array_merge($arrId, $aid);
                $idStr = implode(',',$aid);

                $csname = strtolower($arr_name['username']);
                $m = 0;
                foreach ($same_id as $key => $vid) {
                    $sql = "SELECT `username` FROM `un_user_copy` WHERE `id` = " .$vid['id'];
                    $sname = $model->db->getone($sql);
                    if ($m > 0) {
                        $csname .= '0';
                        $sql = "update `un_user_copy` set username = '" . $csname ."' WHERE `id` = " . $vid['id'];
                        $model->db->exec($sql);
                    }
                    $sql = 'INSERT INTO `un_user_same` (`same_str`,`user_id_str`, `user_id`, `b_username`, `a_username`) VALUES ("' . strtoupper($arr_name['username']) . '","' . $idStr . '",' . $vid['id'] . ',"' . $sname['username'] . '","' . $csname . '")';
                    $model->db->query($sql);
                    
                    $m++;
                }
            }

            return ['code' => 1, 'msg' => 'The same username has been modified successfully. Modify the rule: all lowercase for the same username, and then add "0" repeatedly at the end of the username. If you cannot login, please continue to add "0"! !'];
        }catch(Exception $e) {
           //$model->db->query("DELETE FROM `un_user_same`");
            return ['code' => 2, 'msg' => 'Find the error, please search again!'];
        }
    
        return '初始化成功！';
    }
    /*
    public function checkUsername()
    {
        $arrId = [];
        $model = O('model');
        $arrUsername = $model->db->query("select `id`, `username` from un_user");
    
        if (empty($arrUsername)) {
            return ['code' => 2, 'msg' => '没有用户名'];
        }
    
        $model->db->query("DELETE FROM `un_user_same`");
    
        try {
            foreach ($arrUsername as $k => $v) {
    
                if (in_array($v['id'], $arrId)) continue;
    
                $sql = "SELECT `id` FROM `un_user` WHERE UPPER(`username`) = UPPER('" . $v['username'] . "')";
                $same_id = $model->db->getall($sql);
                if (count($same_id) < 2) {
                    continue;
                }
    
                $aid = array_column($same_id, 'id');
                $arrId = array_merge($arrId, $aid);
                $idStr = implode(',',$aid);
    
                $sql = 'INSERT INTO `un_user_same` (`same_str`, `user_id_str`) VALUES ("' . strtoupper($v['username']) . '","' . $idStr . '")';
                $model->db->query($sql);
            }
            if (empty($arrId)) {
                return ['code' => 2, 'msg' => '很幸运，数据库中没有用户名相同的用户（不区分大小写）！'];
            }else {
                return ['code' => 1, 'msg' => '数据库存在用户名相同的用户（不区分大小写）！'];
            }
        }catch(Exception $e) {
            $model->db->query("DELETE FROM `un_user_same`");
            return ['code' => 2, 'msg' => '查找错误,请重新查找！'];
        }
    
        return '初始化成功！';
    }
    */
    
    /**
     * 积分兑换
     * @param float $money 金额
     * @param int $user_id 用户ID
     * @param string $type 类型，1：充值兑换，2：投注兑换，3：中奖兑换
     * @return boolean
     */
    public function exchangeIntegral($money, $user_id, $type = 0)
    {
        $flag = 0;
        $amont_data = [];
        $log_data = [];
        $textType = ['', '充值', '投注', '中奖'];
        $strName = ['', 'recharge', 'betting', 'winning'];
        $model = O('model');
    
        $conf = D('config')->getOneCoupon("value", "nid='level_honor'");
        $config = json_decode($conf['value'], true);
    
        $sql = 'select u.honor_score, u.group_id as groupid, u.reg_type as regtype, uat.* from un_user as u LEFT JOIN un_user_amount_total as uat ON uat.user_id = u.id where u.id = ' . $user_id;
        $userData = $model->db->getone($sql);
        if (empty($userData)) {
            payLog('exchange.log', 'ID为：' . $user_id . '的用户不存在，充值金额' . $money . '添加到累计兑换积分失败！');
    
            return false;
        }
        //标志un_user_amount_total表没有用户记录
        if (empty($userData['id'])) {
            $flag = 1;
        }

        $exchangeMoney = $money + $userData[$strName[$type] . '_exchange'];
        $exMoney = (($exchangeMoney * 100) % ($config['plus'][$strName[$type]]['money'] * 100)) / 100;
        $logMoney = $exchangeMoney - $exMoney;

        $amont_data[$strName[$type] . '_exchange'] = $exchangeMoney;

        if ($config['plus']['status'] == 1 && $logMoney > 0) {
            $logScore = ($logMoney / $config['plus'][$strName[$type]]['money']) * $config['plus'][$strName[$type]]['score'];

            //修改用户积分
            $sql = 'update un_user set honor_score = honor_score + ' . $logScore . ' where id = ' . $user_id;
            $model->db->exec($sql);

            //添加积分记录
            $log_data = [
                'user_id' => $user_id,
                'money'   => $logMoney,
                'score'   => $logScore,
                'use_money' => $exMoney,
                'honor_score' => $userData['honor_score'],
                'type'      => $type,
                'create_time' => time(),
                'remarks' => 'Current cumulative ' . $textType[$type] . ' points redemption setting is: accumulative' . $textType[$type] . '金额每满：' . $config['plus'][$strName[$type]]['money'] . ',兑换积分：' . $config['plus'][$strName[$type]]['score']
            ];
            $model->db->insert('un_integral_log', $log_data);

            //修改未兑换积分的金额
            $amont_data[$strName[$type] . '_exchange'] = $exMoney;
        }

        if ($flag == 0) {
            $model->db->update('un_user_amount_total', $amont_data, 'user_id = ' . $user_id);
        } else {
            //添加用户统计记录
            $user_data = [
                'user_id' => $user_id,
                'group_id'   => $userData['groupid'],
                'reg_type'   => $userData['regtype'],
                'recharge_exchange' => $amont_data[$strName[$type] . '_exchange'],
                'create_time' => time()
            ];
            $model->db->insert('un_user_amount_total', $user_data);
        }
    }
    
    
    /**
     * 加减用户积分操作
     * @param array $data 数据
     * @param array $admin 管理员数据
     * @return string json
     */
    public function honorUserScoreEdit($data, $admin)
    {
        $text = 0;
        $model = O('model');
        
        if (empty($data['plus_score'])) {
            $data['plus_score'] = 0;
        }

        if ($data['plus_score'] != (int)$data['plus_score']) {
            return json_encode(['code' => 0, 'msg' => 'Integral modification failed, the number entered is wrong!']);
        }
        
        if ($data['plus_score'] <= 0) {
            return json_encode(['code' => 0, 'msg' => 'The modified points must be greater than 0!']);
        }

        if ($data['plus_type'] != (int)$data['plus_type'] || !in_array($data['plus_type'],[1,2])) {
            return json_encode(['code' => 0, 'msg' => 'Integral modification failed, operation type is wrong!']);
        }
        
        if (empty($data['id']) || $data['id'] != (int)$data['id'] || $data['id'] <= 0) {
            return json_encode(['code' => 0, 'msg' => 'Failed to modify the points, the user does not exist!']);
        }
    
        $sql="SELECT id,username,honor_score,honor_upgrade FROM un_user WHERE id = " . $data['id'];
        $userData = $this->db->getone($sql);
        if (empty($userData)) {
            return json_encode(['code' => 0, 'msg' => 'Failed to modify the points, the user does not exist!']);
        }
    
        if ($data['plus_type'] == 1) {
            $score = $userData['honor_score'] + $data['plus_score'];
        }else {
            $score = $userData['honor_score'] - $data['plus_score'];
        }
        if ($score < 0) {
            return json_encode(['code' => 0, 'msg' => 'Failed to modify points, user points are not enough!']);
        }
        
        if ($userData['honor_score'] > $score) {
            $text = '减少';
        } elseif ($userData['honor_score'] == $score) {
            return json_encode(['code' => 0, 'msg' => 'Integral modification failed, there is no change before and after modification!']);
        } else {
            $text = '增加';
        }
        
        //判断减分后等级是否有所降低
        $userLevel = $userData['honor_upgrade'];
        $level = $this->db->getone("select sort, score, grade from un_honor where status = 1 and score <= " . $score . ' order by score desc');
        if ($level['sort'] < $userData['honor_upgrade']) {
            $userLevel = $level['sort'];
        }
        
        $model->db->query('BEGIN');
        
        try {
            
            //添加积分修改记录
            $log_data = [
                'user_id' => $data['id'],
                'money'   => 0,
                'score'   => $score - $userData['honor_score'],
                'use_money' => 0,
                'honor_score' => $score,
                'type'      => 4,
                'create_time' => time(),
                'remarks' => '【Back-office administrator:' . ($admin['username'] ? $admin['username'] : 'unkown') . ' through the background, operating users: ' . $userData['username'] . '，' . $text . ': ' . abs($score - $userData['honor_score'] ) . ' Points】' . $data['remark']
            ];
            $model->db->insert('un_integral_log', $log_data);
            
            $model->db->update('un_user', ['honor_score' => $score, 'honor_upgrade' => $userLevel], 'id = ' . $data['id']);
            
            $model->db->query('COMMIT');
            
            return json_encode(['code' => 1, 'msg' => 'Successfully modify user points!']);
        } catch (Exception $e) {
            $model->db->query('ROLLBACK');
        
            return json_encode(['code' => 0, 'msg' => 'Failed to modify user points!']);
        }
    }
    
    /**
     * 加减假人（机器人）积分操作
     * @param array $data 数据
     * @param array $admin 管理员数据
     * @return string json
     */
    public function honorRobotScoreEdit($data, $admin)
    {
        $text = 0;
        $model = O('model');
    
        if (empty($data['plus_score'])) {
            $data['plus_score'] = 0;
        }
    
        if (empty($data['reduce_score'])) {
            $data['reduce_score'] = 0;
        }
    
        if ($data['plus_score'] != (int)$data['plus_score'] || $data['puls_score'] < 0) {
            return json_encode(['code' => 0, 'msg' => 'Failed to modify the points, the number of bonus points entered is wrong!']);
        }
    
        if ($data['reduce_score'] != (int)$data['reduce_score'] || $data['reduce_score'] < 0) {
            return json_encode(['code' => 0, 'msg' => 'Failed to modify the points, the input deduction number is wrong!']);
        }
        
        $score = $data['plus_score'] - $data['reduce_score'];
    
        if ($score < 0) {
            $text = '减少';
        } elseif ($score == 0) {
            return json_encode(['code' => 0, 'msg' => 'Integral modification failed, there is no change before and after modification!']);
        } else {
            $text = '增加';
        }
        
        $sql="SELECT id,username,honor_score FROM un_user WHERE reg_type = 9";
        $userData = $this->db->getall($sql);
    
        $model->db->query('BEGIN');
    
        try {
            foreach ($userData as $k => $v) {
                $model->db->update('un_user', ['honor_score' => (($v['honor_score'] + $score) < 0 ? 0 : ($v['honor_score'] + $score))], 'reg_type = 9');
            }
            
            //添加积分修改记录
            $log_data = [
                'user_id' => $admin['id'],
                'money'   => 0,
                'score'   => $score,
                'use_money' => 0,
                'honor_score' => 0,
                'type'      => 4,
                'create_time' => time(),
                'remarks' => '【Back-office administrator:' . ($admin['username'] ? $admin['username'] : 'unkown') . ' through the background, operate all robots: ' . $text . ': ' . abs($score) . ' points】' . $data['remark']
            ];
            $model->db->insert('un_integral_log', $log_data);

            $model->db->query('COMMIT');
    
            return json_encode(['code' => 1, 'msg' => 'Successfully modify the points of the dummy (robot)!']);
        } catch (Exception $e) {
            $model->db->query('ROLLBACK');
    
            return json_encode(['code' => 0, 'msg' => 'Failed to modify the points of the dummy (robot)!']);
        }
    }
    
    /**
     * 积分变更记录列表
     * @param array $data 查询数据
     * @param string $where  查询条件
     * @return array
     */
    public function scoreRecordList($data, $where)
    {
        $sql="SELECT u.username, u.weixin, il.honor_score, il.id, il.money, il.log_money, il.exchange_money, il.score, il.use_money, il.type, il.status, il.remarks, il.create_time FROM un_integral_log as il
                LEFT JOIN un_user as u ON il.user_id = u.id
                WHERE $where ORDER BY il.id DESC LIMIT {$data['pagestart']},{$data['pagesize']}";
        $recordList = $this->db->getall($sql);
       
       return $recordList;
    }
    
    /**
     * 用户积分列表
     * @param 查询数据 $data
     * @return array
     */
    public function honorScoreList($data)
    {
        $where = $this->userHonorSeachWhere($data);
        $sql="SELECT u.id, u.username, u.weixin, u.honor_score FROM un_user as u WHERE {$where} ORDER BY u.id ASC  LIMIT {$data['pagestart']},{$data['pagesize']}";
        $recordList = $this->db->getall($sql);
        if (empty($recordList)) {
            return false;
        }

        $sql="SELECT * FROM un_honor WHERE status = 1 ORDER BY sort DESC";
        $honorLevelList = $this->db->getall($sql);

        foreach ($recordList as $k => $v) {
            foreach ($honorLevelList as $key => $value) {
                if ($v['honor_score'] >= $value['score']) {
                    $recordList[$k]['icon'] = $value['icon'];
                    break;
                }
            }
        }
         
        return $recordList;
    }
    
    
    /**
     * 用户积分
     * @param array $data
     */
    public function getHonorCount($data)
    {
        $where = $this->userHonorSeachWhere($data);

        $sql="SELECT count(u.id) as count FROM un_user as u WHERE " . $where;
    
        $sum = $this->db->getone($sql);
    
        return $sum['count'];
    }
    
    /**
     * 会员累计金额统计-组合搜索条件
     * @author king
     * @date 2017/09/18
     * @param $param
     * @return string
     */
    public function userHonorSeachWhere($param)
    {
        $where=" 1=1 ";

        if (!empty($param['username'])) {
            $where .= " AND u.username LIKE '%" . $param['username'] . "%' ";
        }
    
        if (!empty($param['weixin'])) {
            $where .=" AND u.weixin LIKE '%" . $param['weixin'] . "%'";
        }
        
        if (!empty($param['rg_type'])) {
            if ($param['rg_type'] == 7) {
                $where .=" AND u.reg_type < 8 ";
            } elseif($param['rg_type'] == 8) {
                $where .=" AND u.reg_type = 8 ";
            } elseif($param['rg_type'] == 9) {
                $where .=" AND u.reg_type = 9 ";
            } elseif($param['rg_type'] == 11) {
                $where .=" AND u.reg_type = 11 ";
            }
        }
        
        if (!empty($param['type'])) {
            $level_score = $this->getOneCoupon('score,status','sort = ' . $param['type']);
            $next_level_score = $this->db->getone("select score from " . $this->table . " where status = 1 AND sort > " . $param['type'] . " ORDER BY sort ASC");
            
            if (!empty($level_score) && $level_score['status'] == 0) {
                    $level_score = $this->getOneCoupon('score,status','status = 1 AND sort = ' . $param['type']);
            }

            if (!empty($level_score)) {
                if (empty($next_level_score)) {
                    $where .= " AND u.honor_score >= " . $level_score['score'];
                } else {
                    $where .= " AND u.honor_score BETWEEN " . $level_score['score'] . ' AND ' . ($next_level_score['score'] - 1);
                }
            }
        }
    
        return $where;
    }
    
    /**
     * 获取记录条数
     * @param unknown $data
     */
    public function scoreRecordCount($where)
    {
        $sql="SELECT count(il.id) as count FROM un_integral_log as il
                LEFT JOIN un_user as u ON il.user_id = u.id
                WHERE " . $where;
        
        $sum = $this->db->getone($sql);
        
        return $sum['count'];
    }
    
    /**
     * 会员累计金额统计-组合搜索条件
     * @author king
     * @date 2017/09/18
     * @param $param
     * @return string
     */
    public function scoreRecordSeachWhere($param) {
        $username=$param['username'];
        $weixin=$param['weixin'];
        $type = $param['type'];
        $where=" 1=1 ";
        if (!empty($username)) {
            $where.= " AND u.username = '" . $username . "' ";
        }
    
        if (!empty($weixin)) {
            $where.=" AND u.weixin = '" . $weixin . "'";
        }

        if (!empty($type) && $type > 0) {
            $where.=" AND il.type = " . $type;
        }
        
        $where .= " AND reg_type not in (8,9,11) "; //排出机器人、游客和假人
    
        return $where;
    }
    
    /**
     * （加分）充值、投注、中奖送积分配置操作
     * @param array $data //配置数据
     * @return string
     */
    public function setConfig($data)
    {
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $data['remoney']) || ($data['remoney'] == 0 || $data['remoney'] == '0.0' || $data['remoney'] == '0.00')) {
            return json_encode(['code' => 0, 'msg' => 'Failed to configure the points, please enter the legal recharge amount for each cumulative!']);
        }
        if ($data['rescore'] != (int)$data['rescore'] || $data['rescore'] <= 0) {
            return json_encode(['code' => 0, 'msg' => 'Points configuration failed, please enter a valid number of points!']);
        }
        
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $data['bemoney']) || ($data['bemoney'] == 0 || $data['bemoney'] == '0.0' || $data['bemoney'] == '0.00')) {
            return json_encode(['code' => 0, 'msg' => 'Failed to configure the points, please enter the legal amount of each cumulative bet!']);
        }
        if ($data['bescore'] != (int)$data['bescore'] || $data['bescore'] <= 0) {
            return json_encode(['code' => 0, 'msg' => 'Points configuration failed, please enter a valid number of points!']);
        }
        
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $data['wimoney'] || ($data['wimoney'] == 0 || $data['wimoney'] == '0.0' || $data['wimoney'] == '0.00'))) {
            return json_encode(['code' => 0, 'msg' => 'Failed to configure the points, please enter the legal total winning amount!']);
        }
        if ($data['wiscore'] != (int)$data['wiscore'] || $data['wiscore'] <= 0) {
            return json_encode(['code' => 0, 'msg' => 'Points configuration failed, please enter a valid number of points!']);
        }
        
        $conf = D('config')->getOneCoupon("value", "nid='level_honor'");
        $config = json_decode($conf['value'], true);
        
        $config['plus']['recharge']['money'] = $data['remoney'];
        $config['plus']['recharge']['score'] = $data['rescore'];
        $config['plus']['betting']['money']  = $data['bemoney'];
        $config['plus']['betting']['score']  = $data['bescore'];
        $config['plus']['winning']['money']  = $data['wimoney'];
        $config['plus']['winning']['score']  = $data['wiscore'];
        
        $configJson['value'] = json_encode($config);
        $ret = D('config')->save($configJson, "nid = 'level_honor'");

        if($ret){
            return json_encode(['code' => 1, 'msg' => 'The points configuration is successful!']);
        }else{
            return json_encode(['code' => 0, 'msg' => 'Points configuration failed!']);
        }
    }
    
    /**
     * 前段是否显示荣誉等级
     * @param ind $id
     * @retrun string
     */
    public function showHonor($status)
    {
        if ($status < 0 || $status > 1 || $status != (int)$status) {
            return json_encode(['code' => 0, 'msg' => 'Operation failed!']);
        }
        
        $conf = D('config')->getOneCoupon("value", "nid='level_honor'");
        $config = json_decode($conf['value'], true);

        if ($config['status'] == 1) {
            $config['status'] = 0;
        } else {
            $config['status'] = 1;
        }
        
        $configJson['value'] = json_encode($config);
        $ret = D('config')->save($configJson, "nid = 'level_honor'");

        if ($ret) {
            return json_encode(['code' => 1, 'msg' => 'Operation succeeded!']);
        } else {
            return json_encode(['code' => 0, 'msg' => 'Operation failed!']);
        }
    }
    
    /**
     * 是否开启兑换加分
     * @param ind $id
     * @retrun string
     */
    public function startPlus($status)
    {
        if ($status < 0 || $status > 1 || $status != (int)$status) {
            return json_encode(['code' => 0, 'msg' => 'Operation failed!']);
        }
    
        $conf = D('config')->getOneCoupon("value", "nid='level_honor'");
        $config = json_decode($conf['value'], true);
    
        if ($config['plus']['status'] == 1) {
            $config['plus']['status'] = 0;
        } else {
            $config['plus']['status'] = 1;
        }
    
        $configJson['value'] = json_encode($config);
        $ret = D('config')->save($configJson, "nid = 'level_honor'");
    
        if ($ret) {
            return json_encode(['code' => 1, 'msg' => 'Operation succeeded!']);
        } else {
            return json_encode(['code' => 0, 'msg' => 'Operation failed!']);
        }
    }
    
    //删除
    public function deleteLevel($id)
    {
        $id = $_POST['id'];
    
        if ($id != (int)$id) {
            return json_encode(['code' => 0, 'msg' => 'Failed to delete!']);
        }
    
        $levelData = D('honor')->getOneCoupon('','id='.$id);
        if (empty($levelData)) {
            return json_encode(['code' => 0, 'msg' => 'The deletion failed, the level does not exist!']);
        }
    
        if ($levelData['sort'] < 6) {
            return json_encode(['code' => 0, 'msg' => 'Delete failed, illegal operation!']);
        }
    
        $ret = D('honor')->db->query("delete from un_honor where id = $id");
    
        if ($ret) {
            //修改等级排序号
            $levelSort = $this->db->getone("select max(sort) as maxsort from " . $this->table);
            $sort = $levelData['sort'];
            while($sort < $levelSort['maxsort']) {
                $this->save(['sort' => $sort], 'sort = ' . ($sort + 1));
                $sort++;
            }
            
            return json_encode(['code' => 1, 'msg' => 'Successfully deleted!']);
        } else {
            return json_encode(['code' => 0, 'msg' => 'Failed to delete!']);
        }
    }
    
    //是否启用
    public function useLevel($id)
    {
        $status = 0;
    
        if (empty($id) || $id != (int)$id) {
            return json_encode(['code' => 0, 'msg' => 'Operation failed!']);
        }
    
        $levelData = $this->getOneCoupon('',' id = '.$id);
    
        if (empty($levelData)) {
            return json_encode(['code' => 0, 'msg' => 'Operation failed!']);
        }
    
        if ($levelData['status'] == 1) {
            $status = 0;
        } else {
            $status = 1;
        }
    
        $ret = $this->save(['status' => $status], 'id = ' . $id);
    
        if ($ret) {
            return json_encode(['code' => 1, 'msg' => 'Operation succeeded!']);
        } else {
            return json_encode(['code' => 0, 'msg' => 'Operation failed!']);
        }
    }
    
    /**
     * 编辑荣誉等级
     * @param array $data 修改数据
     * @return string
     */
    public function editHonor($data)
    {
        $edit = [];

        if(!$data['id'] || $data['id'] != (int)$data['id']) {
            return json_encode(['code' => 0, 'msg' => 'Edit failed!']);
        }

        if(!$data['num'] || $data['num'] != (int)$data['num']) {
            return json_encode(['code' => 0, 'msg' => 'Edit failed!']);
        }

        $levelData = $this->getOneCoupon('','id = ' . $data['id']);

        if (empty($levelData)) {
            return json_encode(['code'=>0,'msg'=>'Editing failed, the level does not exist!']);
        }

        if(!$data['name'] || mb_strlen($data['name'],"UTF8") < 2 || mb_strlen($data['name'],"UTF8") > 4) {
            return json_encode(['code'=>1,'msg'=>'Editing failed, the level name should be 2 ~ 4 characters!']);
        }

        //$pre_level_score = $this->getOneCoupon('score','status = 1 AND sort = ' . ($levelData['sort'] - 1));
        //$next_level_score = $this->getOneCoupon('score','status = 1 AND sort = ' . ($levelData['sort'] + 1));
        
        $pre_level_score  = $this->db->getone("select score from " . $this->table . " where status = 1 AND sort < " . $levelData['sort'] . " ORDER BY sort DESC");
        $next_level_score = $this->db->getone("select score from " . $this->table . " where status = 1 AND sort > " . $levelData['sort'] . " ORDER BY sort ASC");

        if($data['score'] < 0 || !is_numeric($data['score'])){
            return json_encode(['code'=>0,'msg'=>'Editing failed, level score is non-numeric']);
        }

        if ($levelData['sort'] == 1) {
            return json_encode(['code'=>0,'msg'=>'Editing failed, the lowest honor level cannot be modified!']);
        } else {
            if($data['score'] <= $pre_level_score['score']) {
                if (!empty($next_level_score)) {
                    return json_encode(['code'=>0,'msg'=>'Editing failed, level score range:' . $pre_level_score['score'] . ' ~ ' . $next_level_score['score']]);
                } else {
                    return json_encode(['code'=>0,'msg'=>'Editing failed, the level score must be greater than:' . $pre_level_score['score']]);
                }
            }
        }

        if (!empty($next_level_score) && $data['score'] >= $next_level_score['score']) {
            return json_encode(['code'=>0,'msg'=>'Editing failed, level score range:' . $pre_level_score['score'] . ' ~ ' . $next_level_score['score']]);
        }
        
        if($data['status'] < 0 || $data['status'] > 1 || (int)$data['status'] != $data['status']) {
            return json_encode(['code'=>0,'msg'=>'Editing failed, illegal operation!']);
        }
        
        if ($levelData['sort'] > 5) {
            if (empty($data['icon'])) {
                return json_encode(['code' => 0, 'msg' => 'The modification failed, please select the level icon!']);
            }
            
            $levelId = $this->db->getone("select id from " . $this->table . " where sort <> " . $levelData['sort'] . " AND icon = '" . $data['icon'] . "'");

            if (!empty($levelId)) {
                return json_encode(['code' => 0, 'msg' => 'The modification failed, the image of this level has been used!']);
            } elseif (!is_file(S_ROOT . $data['icon'])) {
                return json_encode(['code' => 0, 'msg' => 'The modification failed, the image of this level does not exist!']);
            } else {
                $edit['icon'] = $data['icon'];
                $edit['grade']   = $data['num'];
            }
        }

        $edit['name']   = $data['name'];
        $edit['score']  = $data['score'];
        $edit['status'] = $data['status'];

        $ret = $this->save($edit, 'id = ' . $data['id']);

        if($ret){
            return json_encode(['code' => 1, 'msg' => 'Edit successfully!']);
        }else{
            return json_encode(['code' => 0, 'msg' => 'Edit failed!']);
        }
    }
    
    /**
     * 添加荣誉等级
     * @param array $data 数据
     * @return string
     */

    public function addHonor($data)
    {
        $addData = [];
    
        $maxSort = $this->db->getone("select max(sort) as maxSort from " . $this->table);
        $levelData = $this->db->getone("select sort, score from " . $this->table . " where sort = " . $maxSort['maxSort']);
        if (empty($levelData)) {
            $levelData['sort'] = 0;
            $levelData['score'] = 0;
        }

        $conf = D('config')->getOneCoupon("value", "nid='level_honor'");
        $config = json_decode($conf['value'], true);
        if ($levelData['sort'] >= $config['level']) {
            return json_encode(['code' =>0 ,'msg' => 'The honor level is limited to ' . $config['level'] . ' levels!']);
        }

        $addData['sort'] = $levelData['sort'] + 1;

        if(!$data['name'] || mb_strlen($data['name'],"UTF8") < 2 || mb_strlen($data['name'],"UTF8") > 4) {
            return json_encode(['code'=>1,'msg'=>'Failed to add, the level name should be 2 ~ 4 characters or characters!']);
        }
    
        //$pre_level_score = D('honor')->getOneCoupon('score','id = ' . ($data['id'] -1));
        if(!is_numeric($data['score']) || $data['score'] <= $levelData['score']) {
            return json_encode(['code'=>0,'msg'=>'Adding failed, the level score must be greater than:' . $levelData['score']]);
        }
    
        if($data['status'] < 0 || $data['status'] > 1 || (int)$data['status'] != $data['status']) {
            return json_encode(['code'=>0,'msg'=>'Adding failed, illegal operation!']);
        }
        
        if(empty($data['icon'])) {
            return json_encode(['code'=>0,'msg'=>'Failed to add, the url of level image cannot be empty!']);
        }

        $levelId = $this->db->getone("select id from " . $this->table . " where icon = '" . $data['icon'] . "'");
        if (!empty($levelId)) {
            return json_encode(['code' => 0, 'msg' => 'Failed to add, the image of this level has been used!']);
        } elseif (!is_file(S_ROOT . $data['icon'])) {
            return json_encode(['code' => 0, 'msg' => 'Failed to add, the image of this level does not exist!']);
        }
    
        $addData['name']   = $data['name'];
        $addData['score']  = $data['score'];
        $addData['status'] = $data['status'];
        $addData['icon']   = $data['icon'];
        $addData['grade']  = $data['num'];

        $ret = D('honor')->add($addData);

        if($ret){
            return json_encode(['code' => 1, 'msg' => 'Added successfully!']);
        }else{
            return json_encode(['code' => 0, 'msg' => 'Failed in adding!']);
        }
    }
}