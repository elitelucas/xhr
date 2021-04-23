<?php

/**
 * 用户表model
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class UserModel extends CommonModel {

    protected $table = '#@_user';

    //根据id获取用户表的一条记录
    public function userDB($id) {
        return $this->db->getone("select * from " . $this->table . " where id = {$id}");
    }

    //根据id获取团队总数
    public function teamCnt($id) {
        $rt = $this->db->getall("SELECT count(u.id) as cnt FROM un_user AS u WHERE FIND_IN_SET(u.id, getChildLst({$id}))");
        return $rt[0]['cnt'];
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo() {
        $sql = "select a.*,b.account,c.money,c.money_freeze,d.name from un_user a left join (select * from un_user_bank where state<>2) b on a.id=b.user_id left join un_account c on a.id=c.user_id left join un_user_group d on a.group_id = d.id";

        return count($this->db->getall($sql));
    }

    //分页数据
    public function search($offset, $pagesize) {

        $sql = "select a.*,b.account,c.money,c.money_freeze,d.name from un_user a left join (select * from un_user_bank where state<>2) b on a.id=b.user_id left join un_account c on a.id=c.user_id left join un_user_group d on a.group_id = d.id order by a.regtime desc limit $offset,$pagesize";

        $data = $this->db->getall($sql);

        return $data;
    }

    //用户详情信息
    public function getInfoOne($id) {
        $now = time();
        $sql = "select a.*,b.id as agent_id,b.backwater,c.name as group_name from un_user a left join un_agent_group b on a.user_type=b.id left join un_user_group c on a.group_id=c.id  where a.id={$id}";

        $data = $this->db->getone($sql);

        $sql_f = "select h.account,i.name from un_user_bank h left join un_dictionary i on h.bank=i.id where h.user_id={$id} and h.state = 1";
        $account = $this->db->getone($sql_f);
        //银行信息
        if (!empty($account)) {

            $data['account'] = $account['account'];
            $data['account_name'] = $account['name'];
        }
        //用户是否在线
//        $sq = "select * from un_session where user_id={$id} and {$now} - lastvisit < 600 ";
        $sq = "select * from un_session where user_id={$id}";
        $arr = $this->db->getone($sq);

        if (!empty($arr)) {

            $is_online = 1;
        } else {

            $is_online = 0;
        }

        $data['is_online'] = $is_online;

        //团对人数
//        $team = $this->recursive_query($id);
//        $data['team_sum'] = count($team) + 1;
        //YH
        $team = $this->teamLists($id);

        $uids = array();
        if(!empty($team)){
            foreach ($team as $v){
                $uids[] = $v['id'];
            }
        }
        $uids_s = implode(',',$uids);
        $sql .= " and user.id in ({$uids_s}) order by user.id != {$team}";
        $teamSql = "SELECT user.`id` FROM un_user user WHERE user.reg_type NOT IN(8,9,11) AND user.id IN (" . $uids_s . ")";
        $user_count = $this->db->getall($teamSql);

        $data['team_sum'] = count($user_count);

        //获取用户的账户余额
        $sql_money = "select money from un_account where user_id={$id}";
        $data_ac = $this->db->getone($sql_money);

        $data['ac_money'] = $data_ac['money'];

        //充值，提现，投注，中奖
        //当天的时间
        $start = strtotime(date('Y-m-d' . ' 00:00:00'));
        $end = strtotime(date('Y-m-d' . ' 23:59:59'));
        //今日充值(成功)的总金额
        $sql_cz = "select sum(money) as money from un_account_recharge where user_id={$id} and addtime between $start and $end and status = 1";
        $data_cz = $this->db->getone($sql_cz);

        $data['cz'] = round($data_cz['money'], 2);

        //提现
        $sql_tx = "select sum(money) as all_cash,sum(if(addtime >= $start and addtime <= $end,money,0)) as t_cash from un_account_cash where user_id={$id} and (status = 1  or status = 4)";
        $data_tx = $this->db->getone($sql_tx);
        $data['tx'] = round($data_tx['t_cash'], 2);     //今日提现的总金额
        $data['ztx'] = round($data_tx['all_cash'], 2);     //用户历史提现的总金额（包括今日提现）

        //今日提现的总金额
//        $sql_tx = "select sum(money) as money from un_account_cash where user_id={$id} and addtime between $start and $end and (status = 1 or status = 4)";
//        $data_tx = $this->db->getone($sql_tx);
//        $data['tx'] = round($data_tx['money'], 2);
        
        //用户历史提现的总金额（包括今日提现）
//        $sql_ztx = "select sum(money) as money from un_account_cash where user_id={$id} and (status = 1  or status = 4)";
//        $data_ztx = $this->db->getone($sql_ztx);
//        $data['ztx'] = round($data_ztx['money'],2);


        $sql_order = "select sum(award) as award,sum(money) as money from un_orders where user_id={$id} and addtime between $start and $end and state = 0";
        $data_order = $this->db->getone($sql_order);
        $data['zj'] = round($data_order['award'],2);            //今日中奖的总金额
        $data['tz'] = round($data_order['money'],2);            //今日投注的总金额

        //今日中奖的总金额
//        $sql_zj = "select sum(award) as money from un_orders where user_id={$id} and addtime between $start and $end and state = 0";
//        $data_zj = $this->db->getone($sql_zj);
//        $data['zj'] = round($data_zj['money'],2);

        //今日投注的总金额
//        $sql_tz = "select sum(money) as money from un_orders where user_id={$id} and addtime between $start and $end and state = 0";
//        $data_tz = $this->db->getone($sql_tz);
//        $data['tz'] = round($data_tz['money'], 2);

        //今日撤单
        $sql_cd = "select sum(money) as money from un_orders where user_id={$id} and addtime between $start and $end and state = 1";
        $data_cd = $this->db->getone($sql_cd);

        $data['cd'] = round($data_cd['money'], 2);

        //查询某个用户的元宝余额
        $sql_yb = "select value from un_config where id=15";
        $da_ratio = $this->db->getone($sql_yb);

        $ratio = $da_ratio['value'];
        $data['yuanbao'] = round($data['ac_money'] * $ratio, 2);

        //注册方式
        $sql_rp = "select name from un_dictionary where classid=4 and value = (select entrance from un_user where id = {$id})";
        $data_rp = $this->db->getone($sql_rp);

        $data['regtype'] = $data_rp['name'];

        //直属上级
        $rt = $this->db->getone("select username from un_user where id = (select parent_id from un_user where id = {$id})");

        $data['parent'] = $rt['username'];

        //历史充值总额
        $typestr = "(10,13,120,14,12,19)";
        $sql = "SELECT type,SUM(money) as money FROM `un_account_log` WHERE `user_id` = {$id} AND `type` in $typestr group by type";
        $res = $this->db->getall($sql);
        if($res) {
            $re = array_column($res, 'money', 'type');
            $data['cntRecharge'] = isset($re['10'])?round($re['10'],2):0;
            $data['cntBack'] = isset($re['19'])?round($re['19'],2):0;
            $bet = isset($re['13'])?round($re['13'],2):0;               //投注
            $cb = isset($re['120'])?round($re['120'],2):0;      //回滚
            $revoke = isset($re['14'])?round($re['14'],2):0;    //撤单
            $Winning = isset($re['12'])?round($re['12'],2):0;  //中奖
        }else {
            $data['cntBack'] = $data['cntRecharge'] = $bet = $cb = $revoke = $Winning = 0;
        }

        lg('cnt_log',var_export(array(
            '$cb'=>$cb,
            '$bet'=>$bet,
            '$revoke'=>$revoke,
            '$Winning'=>$Winning,
        ),1));

        $data['cntBetProfit'] = round($Winning + $revoke - $cb - $bet, 2);

        //返水 cntBack 改为历史记录的所有反水
//        $fs_date = date('Y-m-d', strtotime("-1 day"));
//        $sql_fs = "select sum(selfBack) as cntBack from un_back_log where user_id={$id} and state = 2";
//        $sql_fs = "SELECT SUM(money) as cntBack FROM `un_account_log` WHERE `user_id` = {$id} AND `type` = 19";
//        $data_fs = $this->db->getone($sql_fs);

//        $data['cntBack'] = round($data_fs['cntBack'], 2);

//        $sql = "SELECT SUM(money) FROM `un_account_log` WHERE `user_id` = {$id} AND `type` = 10";
//        $data['cntRecharge'] = round($this->db->result($sql), 2);

        //额度调整加款总额
//        $sql = "SELECT SUM(money) FROM `un_account_log` WHERE `user_id` = {$id} AND `type` = 32 AND `money` > 0";
//        $cntPlus = $this->db->result($sql);

        //额度调整扣款总额
//        $sql = "SELECT SUM(money) FROM `un_account_log` WHERE `user_id` = {$id} AND `type` = 32 AND `money` < 0";
//        $reduce = $this->db->result($sql);

//        $data['cntRecharge'] = round($data['cntRecharge'] + $cntPlus, 2);

//        $data['ztx'] =  round($data['ztx'] + abs($reduce), 2);

        //历史总盈亏（投注-中奖）
        //投注,回滚
//        $sql = "SELECT SUM(money) FROM `un_account_log` WHERE `user_id` = {$id} AND `type` = 13";
//        $bet = $this->db->result($sql);

//        $sql = "SELECT SUM(money) FROM `un_account_log` WHERE `user_id` = {$id} AND `type` = 120";
//        $cb = $this->db->result($sql);

        //撤单
//        $sql = "SELECT SUM(money) FROM `un_account_log` WHERE `user_id` = {$id} AND `type` = 14";
//        $revoke = $this->db->result($sql);

        //中奖
//        $sql = "SELECT SUM(money) FROM `un_account_log` WHERE `user_id` = {$id} AND `type` =12";
//        $Winning = $this->db->result($sql);

        
        //验证提现限制
        $nRt = abs($this->db->result("select value from un_config where nid = 'cashLimit'"));
        if (!empty($nRt)) { //倍率已设置  并且大于0 才做判断
            //验证提现限制
            $time = $this->db->result("select addtime from un_account_cash where user_id = {$id} and (status = 1 or status = 4) order by id desc");
            if (empty($time)) {
                $sql = "SELECT regtime FROM `un_user` WHERE `id` = '{$id}'";
                $time = $this->db->result($sql);
            }

            $sql  = "select ifnull(sum(money),0) as money from un_account_recharge where user_id = {$id} and status = 1 and addtime > {$time}";
            $regRt = $this->db->result($sql);
            /* //查最后一次充值时间
            $sql = "SELECT `addtime`,money FROM `un_account_recharge` WHERE user_id = {$id} AND STATUS = 1 ORDER BY `addtime` DESC LIMIT 1";
            $re = $this->db->getone($sql); //最后一次时间,没有记录就设置为当前值
            $last_time = isset($re['addtime']) ? $re['addtime'] : time();
            $last_money = isset($re['money']) ? $re['money'] : 0; */
        
            //最后充值到当前时间的有效投注
            //$sql = "select ifnull(sum(money),0) as money from un_orders where user_id = {$this->userId} AND award_state>0 AND state=0 AND addtime > {$last_time}";
            //$lbetRt = $this->db->result($sql);
            $sql = "select ifnull(sum(money),0) as money from un_orders where user_id = {$id} AND award_state>0 AND state=0 AND addtime > {$time}";
            $betRt = $this->db->result($sql);
            $sql = "select bet_amount from un_user where id={$id}";
            $bet_amount = $this->db->result($sql);
            $data['dml'] = bcadd(bcsub(bcmul($nRt,$regRt,2),$betRt,2),$bet_amount,2);
            if($data['dml']<0||$nRt==0) $data['dml'] = 0;
        }else {
            $data['dml'] = 0.00;
        }

        return $data;
    }

    //用户详情信息冻结
    public function update_detail($id, $state) {
        if ($state == 0 || $state == 1) {

            $table = 'un_user';
            $where = "id =$id";
            $data = "state=2";
        } else {

            $table = 'un_user';
            $where = "id =$id";
            $data = "state=0";
        }
        $res = $this->db->update($table, $data, $where, $add = '');
        return $res;
    }

    //修改用户的信息
    public function update_user($data) {
        $table = "un_user";
        $id = $data['id'];
        $where = "id =$id";
        $res = $this->db->update($table, $data, $where, $add = '');
        return $res;
    }

    //修改用户银行的信息
    public function user_bank_up($data) {
        $this->db->update("un_user_bank", array("state" => 2,'last_mod_name'=>$data['last_mod_name']), array("user_id" => $data['user_id']));
        $res = $this->db->insert("un_user_bank", $data);
        return $res;
    }

    //新增用户银行的信息
    public function user_bank_add($data) {
        $table = "un_user_bank";
        $res = $this->db->insert($table, $data);
        return $res;
    }

    //用户详情信息冻结
    public function update_detail_fx($id, $state) {
        if ($state == 0 || $state == 2) {

            $table = 'un_user';
            $where = "id =$id";
            $data = "state=1";
        } else {

            $table = 'un_user';
            $where = "id =$id";
            $data = "state=0";
        }
        $res = $this->db->update($table, $data, $where, $add = '');
        return $res;
    }

    //解除冻结
    public function undongjie($id, $state) {
        $table = 'un_user';
        return $this->db->update($table, array("state" => $state), array("id" => $id));
    }

    //会员组管理
    public function man_group() {
        //$sql = "select name from un_payment_config where id in(1,2)";
        //return $arr = $this->db->getall($sql);
        $sql = "select * from un_user_group";
        $data = $this->db->getall($sql);

        foreach ($data as $k => $v) {
            $a = array();
            $online = [];
            //线下充值
            $powers = $v['powers'];
            if (empty($powers)) {
                $data[$k]['offline'] = '';
            }else {
                $sq = "select name from un_dictionary where id in($powers)";
                $arr = $this->db->getall($sq);
                foreach ($arr as $k1 => $v1) {
                
                    $a[] = $v1['name'];
                }
                $reyment = implode(',', $a);
                $data[$k]['offline'] = $reyment;
            }
            
            
            //线上充值
            $online_type = $v['online_type'];
            if (empty($online_type)) {
                $data[$k]['online'] = '';
            }else {
                $sq = "select name from un_dictionary where id in($online_type)";
                $arr_online = $this->db->getall($sq);
                foreach ($arr_online as $ka => $va) {
                
                    $online[] = $va['name'];
                }
                $str_online = implode(',', $online);
                $data[$k]['online'] = $str_online;
            }
        }
        return $data;
    }

    //获取字典表的支付
    public function getReypment()
    {
        $paymentType = [];

        //线下充值方式
        $paymentType = $this->db->getall("select `id`, `name` from un_dictionary where classid=10");
        
        return $paymentType;
    } 
    
    //获取字典表的线上支付类型
    public function getOnlinePayment()
    {
        $paymentType = [];
    
        //线下充值方式
        $paymentType = $this->db->getall("select `id`, `name` from un_dictionary where classid=13");
    
        return $paymentType;
    }
    
    //获取字典表的支付
    public function getPaymentType()
    {
        $paymentType = [];
    
        //线下充值方式
        $paymentType['offline'] = $this->db->getall("select `id`, `name` from un_dictionary where classid=10");
        //线上充值方式
        $paymentType['online'] = $this->db->getall("select `id`, `name` from un_dictionary where classid=13");
    
        return $paymentType;
    }
    
    //获取会员组可使用的支付方式
    public function getUserGroupPaymentType($id)
    {
        $sql = "select id, name, powers, online_type, remark from un_user_group where id = {$id}";
        $data = $this->db->getone($sql);
       
        return $data;
    }

    //风险会员监控
    public function fx_watch($param) {
        $sql = " select * from un_user where 1=1 ";

        if ($param['rg_type'] != 0) {
            $sql .= " and reg_type = ".$param['rg_type'];
        }else{
            $sql .= " and reg_type NOT IN (8,9,11)";
        }
        if ($param['state'] == 0) {
            $sql .= " and state = 0";
        } elseif ($param['state'] == 1) {
            $sql .= " and state = 1";
        } else {
            $sql .= " and state = 2";
        }
        if ($param['username'] != "") {
            $sql .= " and username = '{$param['username']}'";
        }
        if ($param['weixin'] != "") {
            $sql .= " and weixin = '{$param['weixin']}'";
        }
        if ($param['regip'] != "") {
            $sql .= " and regip = '{$param['regip']}'";
        }

        $sql .= " order by regtime desc limit {$param['pagestart']},{$param['pagesize']}";
        return $this->db->getall($sql);
    }

    //风险会员监控总数
    public function fx_watch_cnt($param) {
        $sql = " select count(*) as cnt from un_user where 1=1 ";

        if ($param['rg_type'] != 0) {
            $sql .= " and reg_type = ".$param['rg_type'];
        }else{
            $sql .= " and reg_type NOT IN (8,9,11)";
        }

        if ($param['state'] == 0) {
            $sql .= " and state = 0";
        } elseif ($param['state'] == 1) {
            $sql .= " and state = 1";
        } else {
            $sql .= " and state = 2";
        }

        if ($param['username'] != "") {
            $sql .= " and username = '{$param['username']}'";
        }
        if ($param['weixin'] != "") {
            $sql .= " and weixin = '{$param['weixin']}'";
        }
        if ($param['regip'] != "") {
            $sql .= " and regip = '{$param['regip']}'";
        }
        $rt = $this->db->getone($sql);
        return $rt['cnt'];
    }

    //风险监控列表删除
    public function del_watch($id) {

        $where = "id={$id}";
        $table = 'un_user';
        return$this->db->delete($table, $where);
    }

    //风险监控列表冻结
    public function update_watch($id) {

        $table = 'un_user';
        $where = "id =$id";
        $data = "state=2";
        return $this->db->update($table, $data, $where, $add = '');
    }

    //黑名单列表
    public function blacklist($param) {
        $sql = " select * from un_user where state=2";
        if ($param['username'] != "") {
            $sql .= " and username = '{$param['username']}'";
        }
        if ($param['realname'] != "") {
            $sql .= " and realname = '{$param['realname']}'";
        }
        if ($param['regip'] != "") {
            $sql .= " and regip = '{$param['regip']}'";
        }
        return $this->db->getall($sql);
    }

    //黑名单列表搜索
    public function search_black($data) {

        $username = $data['username'];
        $weixin = $data['weixin'];
        $regip = $data['regip'];
        $sql = " select * from un_user where state=2";
        if ($username != '') {

            $and = ' and';

            $sql.=$and . " username like '%$username%'";
        } else {

            $and = '';
        }

        if ($weixin != '') {

            $and = ' and';
            $sql.=$and . " weixin='$weixin'";
        }

        if ($regip != '') {

            $and = ' and';
            $sql.=$and . " regip='$regip'";
        }

        if ($username == '' && $weixin == '' && $regip == '') {

            $sq = " select * from un_user where state=2";

            return $this->db->getall($sq);
        } else {

            return $this->db->getall($sql);
        }
    }

    //代理等级管理
    public function agent_manage($lottery_type,$back_type,$son_team) {
        $sql = "select * from un_agent_group where back_type={$back_type} and lottery_type={$lottery_type} and son_team={$son_team} order by id";
        $res = $this->db->getall($sql);
        $redis = initCacheRedis();
        foreach ($res as $k=>$v) {
            $res[$k]['back_type'] = $v['back_type']==1?'有效投注额':'输分';
            $res[$k]['lottery_type'] = $v['lottery_type']==0?'--':$v['lottery_type'];
            $res[$k]['lottery_name'] = $v['lottery_type']==0?'--':$redis->hget("LotteryType:{$lottery_type}",'name');
            $res[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);
            $sql = "select username from un_admin WHERE userid={$v['uid']}";
            $re = $this->db->result($sql);
            $res[$k]['uid'] = $v['uid']==0?'--':$re;
        }
        deinitCacheRedis($redis);
        return $res;
    }

    //代理等级管理-删除
    public function del_agent($id) {

        $where = "id={$id}";
        $table = 'un_agent_group';
        return$this->db->delete($table, $where);
    }

    //代理等级管理-添加
    public function add_agent($data) {
        $table = 'un_agent_group';
        $sql = "select id from {$table} where lottery_type = {$data['lottery_type']} and back_type={$data['back_type']} and lower={$data['lower']} and upper={$data['upper']}  and son_team={$data['son_team']}";
        $rt = $this->db->getone($sql);
//        dump($sql);
        if (empty($rt)) {
            return $this->db->insert($table, $data);
        } else {
//            return $this->db->replace($table, $data);
            return -1; //id重复
        }
    }

    //代理等级管理-添加
    public function add_group($data) {
        $table = 'un_user_group';
        return $this->db->insert($table, $data);
    }

    //会员组管理-删除
    public function del_group($id) {

        $where = "id={$id}";
        $table = 'un_user_group';
        return $this->db->delete($table, $where);
    }

    //会员组修改
    public function update_user_group($id) {
        $sql = "select * from un_user_group where id={$id}";
        $data = $this->db->getone($sql);
        $powers = $data['powers'];
        $sq = "select id from un_dictionary where id in($powers)";
        $arr = $this->db->getall($sq);
        $payment = array();
        foreach ($arr as $k => $v) {
            $payment[$k] = $v['id'];
        }

        $data['payment'] = $payment;
        return $data;
    }

    public function update_user_group_ok($data) {

        $table = 'un_user_group';
        $id = $data['id'];
        $where = "id =$id";
        unset($data['id']);
        return $this->db->update($table, $data, $where, $add = '');
    }

    //修改
    public function set_back_type($data) {
        return $this->db->update('un_agent_group', $data,'');
    }

    //代理等级管理-修改
    public function update_agent($data, $where) {
        return $this->db->update('un_agent_group', $data, $where);
    }

    //获取一条记录
    public function get_agent_one($id) {
        $sql = " select * from un_agent_group where id={$id}";
        return $this->db->getone($sql);
    }

    //获取用户组的信息
    public function get_group() {
        $sql = " select * from un_user_group";
        return $this->db->getall($sql);
    }

    //获取发卡银行
    public function get_bank() {
        $sql = " select * from un_dictionary where classid=1";
        return $this->db->getall($sql);
    }
    
    //获取所有提款方式（银行,QQ钱包，微信，支付宝）
    public function get_banks()
    {
        //隐藏QQ钱包
        //$sql = " select * from un_dictionary where classid = 1 OR `id` IN (1, 2, 124)";
        $sql = " select * from un_dictionary where classid = 1 OR `id` IN (1, 2)";

        return $this->db->getall($sql);
    }

    //获取银行的信息
    public function get_bank_one($id) {
        $sql = " select * from un_user_bank where user_id={$id} ORDER BY id DESC";
        return $this->db->getall($sql);
    }

    //更新登录信息
    public function updateLoginInfo($userId) {
        $userInfo = $this->getUserInfo('loginip,logintime', array('id' => $userId), 1);
        if (empty($userInfo)) {
            return false;
        }

        $data = array(
            'lastloginip' => $userInfo['loginip'],
            'lastlogintime' => $userInfo['logintime'],
            'loginip' => ip(),
            'logintime' => SYS_TIME,
            'logintimes' => '+=1',
            'is_online' => '1',
        );

        return $this->db->update($this->table, $data, array('id' => $userId));
    }

    //更新用户信息
    public function save($data, $where) {
        if (empty($data) || empty($where)) {
            return false;
        }

        return $this->db->update($this->table, $data, $where);
    }

    //统计当前用户数量
    public function reg_num() {
        $sql = 'SELECT count(*) as num FROM ' . $this->table;
        $res = $this->db->getone($sql);
        return $res['num'];
    }

    //用户列表搜索   un_user  un_user_bank  un_account  un_user_group
    //$type = 0,表示一般搜索，type = 1，表示搜索直属会员
    public function sousuo($data, $offer, $pagesize, $type = 0) {
        $sort = $data['sort'];
        $today = strtotime(date('Y-m-d'));
        $left = '';
        $fields_s = '';
        $loginip = $data['loginip'];
        if($loginip)
            $left .= " left join un_user_login_log ull on u.id = ull.user_id";

        $count_filter = $data['filter'];
        if($count_filter == 1) {
            $fields_s .= ',SUM(IF(ur_a.id, 1, 0)) AS cnt';
            $left .= " LEFT JOIN un_account_recharge as ur_a ON u.id = ur_a.user_id AND ur_a.`status` = 1 AND ur_a.addtime < $today";
        }         //历史首冲

        if($sort == 4){
            $userTabField = 'u.id,u.username,u.realname,u.parent_id,u.state,u.regtime,u.logintime,u.loginip,u.login_ip_attribution'.$fields_s;
            $sql = "select (select count(id) from `un_account_recharge` where un_account_recharge.`user_id` = u.`id` ) count,$userTabField,bank.account,bank.id as bid,account.money,account.money_freeze,groups.name from un_user as u "
                . " left join (select * from un_user_bank where state = 1 group by user_id) as bank on u.id = bank.user_id "
                . " left join un_account as account on u.id = account.user_id "
                . " left join un_user_group as groups on u.group_id = groups.id"
                . " left join un_account_recharge as ur on u.id = ur.user_id";
        }else{
            $userTabField = 'u.id,u.username,u.realname,u.parent_id,u.state,u.regtime,u.logintime,u.loginip,u.login_ip_attribution'.$fields_s;
            $sql = "select $userTabField,bank.account,bank.id as bid,account.money,account.money_freeze,groups.name from un_user as u "
                . " left join (select * from un_user_bank where state = 1 group by user_id) as bank on u.id = bank.user_id "
                . " left join un_account as account on u.id = account.user_id "
                . " left join un_user_group as groups on u.group_id = groups.id"
                . " left join un_account_recharge as ur on u.id = ur.user_id";
        }

        $sql .= $left;

        $sql .= " where 1=1";
        $username = $data['username'];
        $state = $data['state'];
        $group_id = $data['group_id'];
        $layer_id = $data['layer_id'];
        $regtime = $data['regtime'];
        $lastlogintime = $data['lastlogintime'];
        $reg_type = $data['rg_type'];
        $online = $data['online'];
        $realname = $data['realname'];
        $nickname = $data['nickname'];
        $team = $data['team'];
        $weixin = $data['weixin'];
        $mobile = $data['mobile'];
        $bankname = $data['bankname'];
        $bankaccount = $data['bankaccount'];
//        $is_realname = $data['is_realname'];
        //添加最后登录域名条件
        $last_login_source = $data['last_login_source'];

        if ($reg_type != 0) {
            $sql .= " and u.reg_type in({$reg_type})";
        } else {
            $sql .= " and u.reg_type not in(8,9,11)";
        }
        if ($username != '') {
            $sql .= " and u.username like '%{$username}%'";
        }
        if ($realname != '') {
            $sql .= " and u.realname like '%{$realname}%'";
        }
        if ($nickname != '') {
            $sql .= " and u.nickname like '%{$nickname}%'";
        }
        if ($last_login_source != '') {
            $sql .= " and u.last_login_source like '%{$last_login_source}%'";
        }        

        if ($online != '') {
            $now = time();
            if ($online == 1) { //在线
                $sql .= " and u.id in (select user_id from un_session where is_admin=0)";
            }
            if ($online == 2) { //离线
                $sql .= " and u.id not in (select user_id from un_session where is_admin=0)";
            }
        }
        if ($loginip != '') {
            $sql .= " and ull.ip like '%{$loginip}%'";
        }
        if ($state != '') {
            $sql .= " and u.state = '{$state}'";
        }
//        if ($is_realname != '') {
//            $sql .= " and user.is_realname = '{$is_realname}'";
//        }

        if ($weixin != '') {
            $sql .= " and u.weixin = '{$weixin}'";
        }
        if ($mobile != '') {
            $sql .= " and u.mobile = '{$mobile}'";
        }
        if ($bankname != '') {
            $sql .= " and bank.name = '{$bankname}'";
        }
        if ($bankaccount != '') {
            $sql .= " and bank.account = '{$bankaccount}'";
        }

        if ($group_id != '') {
            $sql .= " and groups.id = '{$group_id}'";
        }
        if ($layer_id != '') {
            $sql .= " and u.layer_id = '{$layer_id}'";
        }
        if ($regtime != '') {
            $s = strtotime($regtime . " 00:00:00");
            $e = strtotime($regtime . " 23:59:59");
            $sql .= " and u.regtime >= $s and u.regtime <= $e";
        }
        if ($lastlogintime != '') {
            $time = time() - $lastlogintime;
            $sql .= " and u.logintime >= '{$time}'";
        }
        if($count_filter) {
            if($count_filter == 1 || $count_filter == 2) {
                $today = strtotime(date('Y-m-d'));
                $sql .= "AND ur.addtime > $today AND ur.`status` = 1";
            }
        }
        if ($team != '') {
            //查询user表下级记录
//            $field = "id";
//            $res = $this->recursive_query($team,$field);
//            $uids = array(0=>$team);
            //YH
            if ($type == 1) {
                $res = $this->leaguer($team);
            }else {
                $res = $this->teamLists($team);
            }
            $uids = array();
            if(!empty($res)){
                foreach ($res as $v){
                    $uids[] = $v['id'];
                }
            }
            $uids_s = implode(',',$uids);
            //$sql .= " and user.id in ({$uids_s}) order by user.id != {$team}";
            $sql .= " and u.id in ({$uids_s})";
        }

        $sql .= ' GROUP BY u.id ';
        switch ($sort){
            case 1:
                $sql .= " order by u.regtime desc ";
                break;
            case 2:
                $sql .= " order by u.logintime desc ";
                break;
            case 3:
                $sql .= " order by account.money desc ";
                break;
            case 4:
                $sql .= " order by count desc ";
                break;
            default:
                $sql .= " order by u.id desc ";
        }

        if($count_filter == 1) {
            $sql = "select * from ($sql) infos where cnt = 0";
        }
        $sql.=" limit $offer,$pagesize";
        return $this->db->getall($sql);
    }

    public function getUserInfoTJ($data)
    {
        $today = strtotime(date('Y-m-d'));
        $left = '';
        $fields_s = '';

        $loginip = $data['loginip'];
        if($loginip)
            $left .= " left join un_user_login_log ull on u.id = ull.user_id";

        $count_filter = $data['filter'];
        if($count_filter == 1) {
            $fields_s .= ',SUM(IF(ur_a.id, 1, 0)) AS cnt';
            $left .= " LEFT JOIN un_account_recharge as ur_a ON u.id = ur_a.user_id AND ur_a.`status` = 1 AND ur_a.addtime < $today";
        }         //历史首冲

        $fields = 'account.money as countMoney, account.money_freeze as countMoneyFreeze'.$fields_s;
        $sql = "select $fields from un_user as u "
            . " left join (select * from un_user_bank where state = 1 group by user_id) as bank on u.id = bank.user_id "
            . " left join un_account as account on u.id = account.user_id "
            . " left join un_user_group as groups on u.group_id = groups.id"
            . " left join un_account_recharge as ur on u.id = ur.user_id";

        $sql .= $left;

        $sql .= " where 1=1";

        $username = $data['username'];
        $state = $data['state'];
        $type = $data['type'];
        $group_id = $data['group_id'];
        $layer_id = $data['layer_id'];
        $regtime = $data['regtime'];
        $lastlogintime = $data['lastlogintime'];
        $reg_type = $data['rg_type'];
        $online = $data['online'];
        $nickname = $data['nickname'];
        $team = $data['team'];
        $weixin = $data['weixin'];
        $mobile = $data['mobile'];
        $bankname = $data['bankname'];
        $bankaccount = $data['bankaccount'];
        $sort = $data['sort'];
//        $is_realname = $data['is_realname'];
        //添加最后登录域名条件
        $last_login_source = $data['last_login_source'];

        if ($reg_type != 0) {
            $sql .= " and u.reg_type in({$reg_type})";
        } else {
            $sql .= " and u.reg_type not in(8,9,11)";
        }
        if ($username != '') {
            $sql .= " and u.username like '%{$username}%'";
        }
        if ($nickname != '') {
            $sql .= " and u.nickname like '%{$nickname}%'";
        }
        if ($last_login_source != '') {
            $sql .= " and u.last_login_source like '%{$last_login_source}%'";
        }
        
        if ($online != '') {
            $now = time();
            if ($online == 1) { //在线
                $sql .= " and u.id in (select user_id from un_session where is_admin=0)";
            }
            if ($online == 2) { //离线
                $sql .= " and u.id not in (select user_id from un_session where is_admin=0)";
            }
        }
        if ($loginip != '') {
            $sql .= " and ull.ip like '%{$loginip}%'";
        }
        if ($state != '') {
            $sql .= " and u.state = '{$state}'";
        }
//        if ($is_realname != '') {
//            $sql .= " and user.is_realname = '{$is_realname}'";
//        }

        if ($weixin != '') {
            $sql .= " and u.weixin = '{$weixin}'";
        }
        if ($mobile != '') {
            $sql .= " and u.mobile = '{$mobile}'";
        }
        if ($bankname != '') {
            $sql .= " and bank.name = '{$bankname}'";
        }
        if ($bankaccount != '') {
            $sql .= " and bank.account = '{$bankaccount}'";
        }

        if ($group_id != '') {
            $sql .= " and groups.id = '{$group_id}'";
        }
        if ($layer_id != '') {
            $sql .= " and u.layer_id = '{$layer_id}'";
        }
        if ($regtime != '') {
            $s = strtotime($regtime . " 00:00:00");
            $e = strtotime($regtime . " 23:59:59");
            $sql .= " and u.regtime >= $s and u.regtime <= $e";
        }
        if ($lastlogintime != '') {
            $time = time() - $lastlogintime;
            $sql .= " and u.logintime >= '{$time}'";
        }

        if($count_filter) {
            if($count_filter == 1 || $count_filter == 2) {
                $sql .= "AND ur.addtime > $today AND ur.`status` = 1";
            }
        }

        if ($team != '') {
            //查询user表下级记录
//            $field = "id";
//            $res = $this->recursive_query($team,$field);
//            $uids = array(0=>$team);
            //YH
            //$res = $this->teamLists($team);
            if ($type == 1) {
                $res = $this->leaguer($team);
            }else {
                $res = $this->teamLists($team);
            }
            $uids = array();
            if(!empty($res)){
                foreach ($res as $v){
                    $uids[] = $v['id'];
                }
            }
            $uids_s = implode(',',$uids);
            $sql .= " and u.id in ({$uids_s}) order by u.id != {$team}";
        } else {
            $sql .= " GROUP BY u.id";
            switch ($sort){
                case 0:
                    $sql .= " order by u.regtime desc ";
                    break;
                case 1:
                    $sql .= " order by u.logintime desc ";
                    break;
                case 2:
                    $sql .= " order by account.money desc ";
                    break;
                default:
                    $sql .= " order by u.id desc ";
            }
        }
        $sql = "select ifnull(sum(countMoney),0) as countMoney,ifnull(sum(countMoneyFreeze),0) as countMoneyFreeze from ($sql) as infos";
        if($count_filter == 1) $sql .= ' where cnt = 0';
        return $this->db->getall($sql);
    }

    public function getSearchCount($data, $type = '') {
        $today = strtotime(date('Y-m-d'));
        $left = '';
        $fields_s = '';

        $loginip = $data['loginip'];
        if($loginip)
            $left .= " left join un_user_login_log ull on u.id = ull.user_id";

        $count_filter = $data['filter'];
        if($count_filter == 1) {
            $fields_s .= ',SUM(IF(ur_a.id, 1, 0)) AS cnt';
            $left .= " LEFT JOIN un_account_recharge as ur_a ON u.id = ur_a.user_id AND ur_a.`status` = 1 AND ur_a.addtime < $today";
        }         //历史首冲

        $fields = 'u.id'.$fields_s;
        $sql = "select $fields from un_user as u "
            . " left join (select * from un_user_bank where state = 1 group by user_id) as bank on u.id = bank.user_id "
            . " left join un_account as account on u.id = account.user_id "
            . " left join un_user_group as groups on u.group_id = groups.id"
            . " left join un_account_recharge as ur on u.id = ur.user_id";

        $sql .= $left;

        $sql .= " where 1=1";

        $username = $data['username'];
        $state = $data['state'];
        $group_id = $data['group_id'];
        $layer_id = $data['layer_id'];
        $regtime = $data['regtime'];
        $lastlogintime = $data['lastlogintime'];
        $reg_type = $data['rg_type'];
        $online = $data['online'];
        $team = $data['team'];
        $nickname = $data['nickname'];
        $nickname = $data['nickname'];
        //添加最后登录域名条件
        $last_login_source = $data['last_login_source'];

        if ($reg_type != 0) {
            $sql .= " and u.reg_type in ({$reg_type})";
        } else {
            $sql .= " and u.reg_type not in(8,9,11)";
        }
        if ($online != '') {
            $now = time();
            if ($online == 1) { //在线
                $sql .= " and u.id in (select user_id from un_session where is_admin=0)";
            }
            if ($online == 2) { //离线
                $sql .= " and u.id not in (select user_id from un_session where is_admin=0)";
            }
        }
        if ($username != '') {
            $sql .= " and u.username like '%{$username}%'";
        }
        if ($nickname != '') {
            $sql .= " and u.nickname like '%{$nickname}%'";
        }
        if ($last_login_source != '') {
            $sql .= " and u.last_login_source like '%{$last_login_source}%'";
        }        

        if ($loginip != '') {
            $sql .= " and ull.ip like '%{$loginip}%'";
        }
        if ($state != '') {
            $sql .= " and u.state = '{$state}'";
        }
        if ($group_id != '') {
            $sql .= " and groups.id = '{$group_id}'";
        }
        if ($layer_id != '') {
            $sql .= " and u.layer_id = '{$layer_id}'";
        }
        if ($regtime != '') {
            $s = strtotime($regtime . " 00:00:00");
            $e = strtotime($regtime . " 23:59:59");
            $sql .= " and u.regtime >= $s and u.regtime <= $e";
        }
        if ($lastlogintime != '') {
            $time = time() - $lastlogintime;
            $sql .= " and u.logintime >= '{$time}'";
        }
        if($count_filter) {
            if($count_filter == 1 || $count_filter == 2) {
                $sql .= "AND ur.addtime > $today AND ur.`status` = 1";
            }
        }
        if ($team != '') {
            //查询user表下级记录
            //            $field = "id";
            //            $res = $this->recursive_query($team,$field);
            //            $uids = array(0=>$team);
            if ($type == 1) {
                $res = $this->leaguer($team);
            }else {
                $res = $this->teamLists($team);
            }
            $uids = array();
            if(!empty($res)){
                foreach ($res as $v){
                    $uids[] = $v['id'];
                }
            }
            $uids_s = implode(',',$uids);
            $sql .= " and u.id in ({$uids_s})";
        }
        $sort = $data['sort'];
        if($sort==4){
            $sql .= " AND (SELECT COUNT(id) FROM `un_account_recharge` WHERE un_account_recharge.`user_id` = u.`id`)>0";
        }
        $sql .= " GROUP BY u.id";

        $where = '';
        if($count_filter == 1) $where = ' where cnt = 0';
        $sql = "select count(*) cnt from ($sql) infos $where";
        $rt = $this->db->getone($sql);
        lg("get_search_count",var_export(array(
            '$sql'=>$sql,
            '$rt'=>$rt,
        ),1));
        return $rt['cnt'];
    }

    //用户累计金额统计
    public function money_tj($param) {
        //会员类型  游客or会员
        if ($param['rg_type'] != 0) {
            $user_type = "u.`reg_type` = ".$param['rg_type'];
        }else{
            $user_type = "u.`reg_type` NOT IN (8,9,11)";
        }
        $sql = "SELECT * FROM 
                (SELECT u.regtime,u.username,u.id AS user_id,u.weixin,u.group_id,IFNULL(m.reg_money,0) AS reg_money,IFNULL(tz.tz_money,0) AS tz_money,IFNULL(zj.zj_money,0) AS zj_money FROM un_user AS u 
                LEFT JOIN (SELECT user_id,SUM(money) AS reg_money FROM `un_account_recharge` WHERE un_account_recharge.`status` = 1 GROUP BY user_id) AS m ON u.`id` = m.user_id
                LEFT JOIN (SELECT user_id,SUM(money) AS tz_money FROM `un_orders` WHERE un_orders.`state` = 0 GROUP BY user_id) AS tz ON u.`id` = tz.user_id
                LEFT JOIN (SELECT user_id,SUM(award) AS zj_money FROM `un_orders` WHERE un_orders.`state` = 0 GROUP BY user_id) AS zj ON u.`id` = zj.user_id
                WHERE {$user_type}) AS rt where 1=1 ";

        if ($param['username'] != "") {
            $sql .= " and rt.username = '{$param['username']}'";
        }
        if ($param['weixin'] != "") {
            $sql .= " and rt.weixin = '{$param['weixin']}'";
        }
        if ($param['group_id'] != "") {
            $sql .= " and rt.group_id = {$param['group_id']}";
        }
        if ($param['sreg_money'] != "") {
            $sql .= " and rt.reg_money >= {$param['sreg_money']}";
        }
        if ($param['ereg_money'] != "") {
            $sql .= " and rt.reg_money <= {$param['ereg_money']}";
        }
        if ($param['stz_money'] != "") {
            $sql .= " and rt.tz_money >= {$param['stz_money']}";
        }
        if ($param['etz_money'] != "") {
            $sql .= " and rt.tz_money <= {$param['etz_money']}";
        }
        $sql .= " order by rt.regtime desc limit {$param['pagestart']},{$param['pagesize']}";
        $rt = $this->db->getall($sql);
        return $rt;
    }

    //用户累计金额统计
    public function getMoney($param) {
        //会员类型  游客or会员
        if ($param['rg_type'] != 0) {
            $user_type = "u.`reg_type` = ".$param['rg_type'];
        }else{
            $user_type = "u.`reg_type` NOT IN (8,9,11)";
        }
        $sql = "SELECT count(*) as cnt FROM 
                (SELECT u.regtime,u.username,u.id AS user_id,u.weixin,u.group_id,IFNULL(m.reg_money,0) AS reg_money,IFNULL(tz.tz_money,0) AS tz_money,IFNULL(zj.zj_money,0) AS zj_money FROM un_user AS u 
                LEFT JOIN (SELECT user_id,SUM(money) AS reg_money FROM `un_account_recharge` GROUP BY user_id) AS m ON u.`id` = m.user_id
                LEFT JOIN (SELECT user_id,SUM(money) AS tz_money FROM `un_orders` WHERE un_orders.`state` = 0 GROUP BY user_id) AS tz ON u.`id` = tz.user_id
                LEFT JOIN (SELECT user_id,SUM(award) AS zj_money FROM `un_orders` WHERE un_orders.`state` = 0 GROUP BY user_id) AS zj ON u.`id` = zj.user_id
                WHERE {$user_type}) AS rt where 1=1 ";
        if ($param['username'] != "") {
            $sql .= " and rt.username = '{$param['username']}'";
        }
        if ($param['weixin'] != "") {
            $sql .= " and rt.weixin = '{$param['weixin']}'";
        }
        if ($param['group_id'] != "") {
            $sql .= " and rt.group_id = {$param['group_id']}";
        }
        if ($param['sreg_money'] != "") {
            $sql .= " and rt.reg_money >= {$param['sreg_money']}";
        }
        if ($param['ereg_money'] != "") {
            $sql .= " and rt.reg_money <= {$param['ereg_money']}";
        }
        if ($param['stz_money'] != "") {
            $sql .= " and rt.tz_money >= {$param['stz_money']}";
        }
        if ($param['etz_money'] != "") {
            $sql .= " and rt.tz_money <= {$param['etz_money']}";
        }

        $rt = $this->db->getone($sql);
        return $rt['cnt'];
    }

    public function getMoneyEmp() {

        $sql = "select a.type,a.money,a.user_id,b.*,c.name from (select user_id,type,sum(money) as money  from un_account_log GROUP BY user_id,type) a right join un_user b on a.user_id = b.id LEFT JOIN un_user_group c on b.group_id=c.id";
        $sq = "select a.type,a.money,a.user_id,b.*,c.name from (select user_id,type,sum(money) as money  from un_account_log GROUP BY user_id,type) a right join un_user b on a.user_id = b.id LEFT JOIN un_user_group c on b.group_id=c.id GROUP BY a.user_id";
        $arr = $this->db->getall($sq);
        $data = $this->db->getall($sql);
        foreach ($arr as $k => $v) {

            foreach ($data as $a => $b) {

                if ($v['user_id'] == $b['user_id']) {
                    if ($b['type'] == 10) {
                        $arr[$k]['sort']['cz'] = $b['money'];
                    }
                    if ($b['type'] == 13) {
                        $arr[$k]['sort']['tz'] = $b['money'];
                    }
                    if ($b['type'] == 12) {
                        $arr[$k]['sort']['zj'] = $b['money'];
                    }
                }
            }
        }

        return $arr;
    }

    //搜索----累计金额统计
    public function mount_sousuo($data) {

        $sql = "select a.type,a.money,a.user_id,b.*,c.name from (select user_id,type,sum(money) as money  from un_account_log GROUP BY user_id,type) a right join un_user b on a.user_id = b.id LEFT JOIN un_user_group c on b.group_id=c.id";
        $sq = "select a.type,a.money,a.user_id,b.*,c.name from (select user_id,type,sum(money) as money  from un_account_log GROUP BY user_id,type) a right join un_user b on a.user_id = b.id LEFT JOIN un_user_group c on b.group_id=c.id GROUP BY a.user_id";

        $username = $data['username'];
        $weixin = $data['weixin'];
        $group = $data['group'];
        $cz_min = $data['cz_min'];
        $cz_max = $data['cz_max'];
        $tz_min = $data['tz_min'];
        $tz_max = $data['tz_max'];

        //$sql_s ="select * from ($sq) m where ";
        $sql_s = "select * from ($sql) n where ";
        $sq_f = "select * from ($sq) m where ";
        if ($username != '') {

            $sql_s.="n.username like '%$username%'";
            $sq_f.="m.username like '%$username%'";
            $and = "and ";
        } else {

            $and = "";
            $sq_f = $sq;
        }

        if ($weixin != '') {

            $sql_s.="$and n.weixin ='$weixin'";
            $and = "$and ";
        }

        if ($group != '') {

            $sql_s.="$and n.group_id=$group";
            $and = "$and ";
        }

        if ($cz_min != '' && $cz_max != '') {

            $sql_s.=" n.money between $cz_min and $cz_max and n.type=10";
            $and = "and ";
        } else {

            $and = '';
        }

        if ($tz_min != '' && $tz_max != '' && $cz_min != '' && $cz_max != '') {

            $sql_s.=" or n.type=13 and n.money between $tz_min and $tz_max ";
            $and = "and ";
        }

        if ($tz_min != '' && $tz_max != '' && $cz_min == '' && $cz_max == '') {

            $sql_s.="$and n.money between $tz_min and $tz_max and n.type=13";
        }

        //如果条件都为空
        if ($username == '' && $weixin == '' && $group == '' && $cz_min == '' && $cz_max == '' && $tz_min == '' && $tz_max == '') {

            return $this->getMoneyEmp();
        } else {

            $data1 = $this->db->getall($sql_s);
            $arr = $this->db->getall($sq_f);
        }

        foreach ($arr as $k => $v) {

            foreach ($data1 as $a => $b) {

                if ($v['user_id'] == $b['user_id']) {
                    if ($b['type'] == 10) {
                        $arr[$k]['sort']['cz'] = $b['money'];
                    }
                    if ($b['type'] == 13) {
                        $arr[$k]['sort']['tz'] = $b['money'];
                    }
                    if ($b['type'] == 12) {
                        $arr[$k]['sort']['zj'] = $b['money'];
                    }
                }
            }
        }



        return $arr;
    }

    //设置用户在那个组
    public function set_user_group($user_id) {

        $sql = "select * from un_user where id={$user_id}";
        $row = $this->db->getone($sql);
        return $row;
    }

    public function set_user_group_ok($data) {
        $table = 'un_user';
        $id = $data['id'];
        $where = "id =$id";
        return $this->db->update($table, $data, $where, $add = '');
    }

    //查询某个用户的金额
    public function get_account($user_id) {


        $sql = "select money from un_account where user_id={$user_id}";

        $row = $this->db->getone($sql);

        return $row['money'];
    }

    //查询某个用户的金额
    public function getBetAmount($user_id) {
        if(!empty($user_id)) {
            $sql = "select bet_amount from un_user where id={$user_id}";
            $row = $this->db->getone($sql);
        }else{
            $row['bet_amount'] = 0;
        }


        return $row['bet_amount'];
    }

    public function adjust_ok($arr) {        //开启事物
        O('model')->db->query('BEGIN');
        if(empty($arr['old_bet_amount'])) $arr['old_bet_amount'] = 0;
        try {
            $username = $arr['username'];
            $rt = $this->db->getone("select un_account.* from un_user left join un_account on un_user.id = un_account.user_id where un_user.username = '".$username."' LIMIT 1 for update");
            if(empty($rt))
            {
                return false;
            }else{
                $arr['account'] = $rt['money'];
            }
            //订单号
            $num = $this->get_random($length = 3);
            $order_num = 'ED' . $num;

            //日志数据
            $flag = $arr['flag'] == 1 ? "+" : "-";
            $money = $arr['money'];
            if($arr['flag'] != 1){

                if($arr['account']<$arr['money']){
                    return false;
                }
            }

            if($money>0) {
                $money = $arr['flag'] == 1 ? ($arr['account'] + $arr['money']) : ($arr['account'] - $arr['money']);
                $data = array(
                    'user_id' => $arr['id'],
                    'order_num' => $order_num,
                    'type' => 32,
                    'money' => $flag.$arr['money'],
                    'use_money' => $money,
                    'remark' => "用户:{$arr['username']} 现金账户调整:{$flag}{$arr['money']} ;调整前余额为{$arr['account']}; 操作人:{$arr['oper']}",
                    'verify' => $arr['operid'],
                    'addip' => $_SERVER["SERVER_ADDR"],
                    'addtime' => time()
                );
                //把资金调整写入日志
                $rt1 = $this->aadAccountLog($data);
                if (!$rt1) {
                    throw new Exception();
                }
                //修改用户的总金额
                $rt2 = $this->db->update('un_account', array("money" => $money), array("user_id" => $arr['id']));
                if (!$rt2) {
                    throw new Exception();
                }
            }

            $flag = $arr['bet_state'] == 1 ? "+" : "-";
            if($arr['bet_amount']!=0){
                $num = $this->get_random($length = 3);
                $order_num = 'ED' . $num;
                $bet_amount = $arr['bet_amount'];
                $money = $arr['flag'] == 1 ? ($arr['account'] + $arr['money']) : ($arr['account'] - $arr['money']);
                $arr['bet_amount'] = $arr['bet_state'] == 1?$arr['old_bet_amount']+$arr['bet_amount']:$arr['old_bet_amount']-$arr['bet_amount'];
                $data = array(
                    'user_id' => $arr['id'],
                    'order_num' => $order_num,
                    'type' => 10650,
                    'money' => 0,
                    'use_money' => $money,
                    'remark' => "用户:{$arr['username']} 打码补偿量调整:{$flag}".abs($bet_amount)." ;调整前打码补偿量为{$arr['old_bet_amount']}; 操作人:{$arr['oper']}",
                    'verify' => $arr['operid'],
                    'addip' => $_SERVER["SERVER_ADDR"],
                    'addtime' => time()
                );

                $rt2 = $this->aadAccountLog($data);
                //修改用户的打码补偿量
                $rt2 = $this->db->update('un_user', array("bet_amount" => floatval($arr['bet_amount'])), array("id" => $arr['id']));

                if (!$rt2) {
                    throw new Exception();
                }
            }

            O('model')->db->query('COMMIT');

            $res['code'] = 0;
            $res['msg'] = "操作成功";
            return $res;
        } catch (Exception $ex) {
            die(var_dump($ex));
            O('model')->db->query('ROLLBACK');
            return false;
        }
    }

    //标记风险会员
    public function biaoji($id, $state) {
        $table = 'un_user';
        $this->db->delete("un_session",['user_id'=>$id]);
        return $this->db->update($table, array("state" => $state), array("id" => $id));
    }

    //冻结账户
    public function getdongjie($id) {
        $sql = "select * from un_user where id=$id";
        return $this->db->getone($sql);
    }

    //更新状态+备注
    public function up_remark($id, $state, $remark) {
        $table = 'un_user';
        return $this->db->update($table, array('state' => $state, 'remark' => $remark), array("id" => $id));
    }

    //算法
    public function get_random($length = 3) {
        $min = pow(10, ($length - 1));
        $max = pow(10, $length) - 1;
        return date('YmdHis', time()) . mt_rand($min, $max);  //当前时间加上3位随机数
    }

    /**
     * 数据查询 团队人数  不包含自身
     * @return mixed sql
     */
    public function recursive_query($id, $field = '*', $where = '') {
        $sql = "SELECT {$field} FROM un_user WHERE parent_id = {$id} {$where}";
        $res = O('model')->db->getAll($sql);
        if ($res) {
            foreach ($res as $v) {
                $res_c = $this->recursive_query($v['id'], $field, $where);
                $res = array_merge($res, $res_c);
            }
        }
        return $res;
    }
    /*
     * 获取假人$username,$password,$money
     * @return $list
     */
    public function getDummyListByRoomId($roomId)
    {

        $roomList = $this->db->getall("select id,title,low_yb,max_yb from un_room where passwd = ''");
        foreach($roomList as $value)
        {
            if($value['id'] == $roomId)
            {
                $roomInfo = $value;
            }
        }
        $list = $this->db->getall("select un_user.id,un_user.username,un_user.nickname,un_account.money,un_user.avatar from un_user LEFT JOIN un_account on un_account.user_id = un_user.id where un_user.reg_type = 9");
        $lists = $this->db->getall("select user_id from un_role");
        if(!empty($list))
        {
            foreach($list as $key=>$val)
            {
                if(!empty($lists))
                {
                    foreach($lists as $v)
                    {
                        if($val['id'] == $v['user_id'])
                        {
                            unset($list[$key]);
                        }
                    }
                }
                if($val['money'] <= $roomInfo['low_yb'] || $val['money'] >= $roomInfo['max_yb'] )
                {
                    unset($list[$key]);
                }
            }
            $list = array_merge($list);
            $arr['code'] = 0;
            $arr['msg'] = "获取成功";
            $arr['list'] = $list;
        }
        else
        {
            $arr['code'] = 0;
            $arr['msg'] = "获取成功";
            $arr['list'] = $list;
        }
        return $arr;
    }


    /*
     * 添加假人$username,$password,$money
     * @return Boolean
     */
    public function addDummy($username,$nickname,$money,$avatar)
    {
        $rt = $this->db->getone("select un_account.* from un_user left join un_account on un_user.id = un_account.user_id where un_user.username = '".$username."'");
        if(!empty($rt))
        {
            $arr['code'] = -1;
            $arr['msg'] = "用户名已存在";
            return $arr;
        }
        $data['username'] = $username;
        $data['nickname'] = $nickname;
        $data['avatar'] = $avatar;
        $data['regtime'] = time();
        $data['regip'] = ip();
        $data['is_realname'] = 1;
        $data['reg_type'] = 9;
        $row = $this->db->insert("un_user", $data);
        if($row < 0)
        {
            $arr['code'] = -1;
            $arr['msg'] = "添加用户失败";
            return $arr;
        }
        $data2['user_id'] = $row;
        $data2['money'] = $money;
        $rowss = $this->db->insert("un_account", $data2);
        if($rowss < 0)
        {
            $arr['code'] = -1;
            $arr['msg'] = "充值失败";
            return $arr;
        }
        $arr['code'] = 0;
        $arr['msg'] = "操作成功";
        return $arr;
    }

    /*
    * 更新假人$id,$type,$money
    * @return Boolean
    */
    public function updateDummy($id,$type,$nickname,$money=0,$avatar)
    {
        $rs = $this->db->getone("select b.money from un_user a left join un_account b on a.id = b.user_id where a.id = $id");
        if(empty($rs))
        {
            $arr['code'] = -1;
            $arr['msg'] = "非法请求";
        }
        else
        {
            if($type == "update")
            {
                $rows = $this->db->update("un_account",['money'=>$rs['money']+$money], ['user_id'=>$id]);
                $this->db->update("un_user",['avatar'=>$avatar,'nickname'=>$nickname], ['id'=>$id]);
            }
            elseif($type == "del")
            {
                $rows = $this->db->delete("un_user", ['id'=>$id]);
            }
            if($rows !== false || $rows > 0)
            {
                $arr['code'] = 0;
                $arr['msg'] = "操作成功";
            }
            else
            {
                $arr['code'] = -1;
                $arr['msg'] = "服务器错误！！";
            }
        }
        return $arr;
    }

    public function delAllDummy($userInfo)
    {
        $this->db->query('BEGIN');//开启事务
        $userInfo = implode(",",$userInfo);
        $row1 = $this->db->query("delete from un_user where id in($userInfo)");
        $row2 = $this->db->query("delete from un_account where user_id in($userInfo)");
        if($row1 > 0 && $row2 > 0) {
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
            $this->db->query('COMMIT');//提交事务
        } else {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
            $this->db->query('ROLLBACK');//事务回滚
        }
        return $arr;
    }

    //强制踢线
    public function forcedKick($uid)
    {
        $rows1 = $this->db->getone("select * from un_session where user_id = $uid");
        if(empty($rows1))
        {
            $arr['code'] = -1;
            $arr['msg'] = "该用户不在线";
        }
        else
        {
            $rows2 = $this->db->delete("un_session",['user_id'=>$uid]);
            if($rows2 > 0)
            {
                $arr['code'] = 0;
                $arr['msg'] = "操作成功";
            }
            else
            {
                $arr['code'] = -1;
                $arr['msg'] = "操作失败";
            }
        }
        return $arr;

    }

    /*
     * 机器人批量加款
     */
    public function dummyBatchChargeMoney($userInfo,$money){

        $userIdStr = implode(",",$userInfo);
        $row = $this->db->query("update un_account set money = money+".$money." where user_id in(".$userIdStr.")");
        if($row > 0){
            $resources['code'] = 0;
            $resources['msg'] = "操作成功";
        } else {
            $resources['code'] = -1;
            $resources['msg'] = "操作失败";
        }
        return $resources;
    }


    /**
     * 会员累计金额统计-生成用户数据
     * @author king
     * @date 2017/09/18
     * @return array
     */
    public function create_user_money_count(){
        $list=array();
        //充值金额统计sql;
        $recharge_sql="SELECT SUM(money)AS recharge_money,un_user.id,un_user.group_id,un_user.reg_type FROM `un_account_recharge` LEFT JOIN un_user ON un_user.id=un_account_recharge.user_id WHERE status=1 GROUP BY un_user.id";
        $recharge_result=$this->db->getall($recharge_sql);
        //投注金额统计sql
        $bet_sql="SELECT SUM(un_orders.money)AS bet_money,un_user.id,un_user.group_id,un_user.reg_type as reg_type FROM `un_orders` LEFT JOIN un_user ON un_user.id=un_orders.user_id 
WHERE un_orders.state=0 GROUP BY un_user.id";
        $bet_result=$this->db->getall($bet_sql);
        //中奖金额统计sql
        $award_sql="SELECT SUM(un_orders.award)AS award_money,un_user.id,un_user.group_id,un_user.reg_type as reg_type FROM `un_orders` left JOIN un_user ON un_user.id=un_orders.user_id 
WHERE un_orders.award_state=2 AND un_orders.state=0 GROUP BY un_user.id";
        $award_result=$this->db->getall($award_sql);
		$data=array();		
        if(count($bet_result)) {
            foreach ($bet_result as $value) {
                $data[$value['id']]['user_id']  = empty($value['id']) ? 0 : $value['id'];
                $data[$value['id']]['group_id'] = empty($value['group_id']) ? 0 : $value['group_id'];
                $data[$value['id']]['reg_type'] = empty($value['reg_type']) ? 0 : $value['reg_type'];
                $data[$value['id']]['recharge_amount'] = !empty($data[$value['id']]['recharge_amount']) ? $data[$value['id']]['recharge_amount'] : 0;
                $data[$value['id']]['betting_amount'] = $value['bet_money'];
                $data[$value['id']]['winning_amount'] = 0;
            }
        }
		if(count($recharge_result)) {
            foreach ($recharge_result as $value) {
                $data[$value['id']]['user_id']  = empty($value['id']) ? 0 : $value['id'];
                $data[$value['id']]['group_id'] = empty($value['group_id']) ? 0 : $value['group_id'];
                $data[$value['id']]['reg_type'] = empty($value['reg_type']) ? 0 : $value['reg_type'];
                $data[$value['id']]['recharge_amount'] = $value['recharge_money'];
                $data[$value['id']]['winning_amount'] = 0;
            }
        }
        if(count($award_result)) {
            foreach ($award_result as $value) {
                $data[$value['id']]['user_id']  = empty($value['id']) ? 0 : $value['id'];
                $data[$value['id']]['group_id'] = empty($value['group_id']) ? 0 : $value['group_id'];
                $data[$value['id']]['reg_type'] = empty($value['reg_type']) ? 0 : $value['reg_type'];
                $data[$value['id']]['recharge_amount'] = !empty($data[$value['id']]['recharge_amount']) ? $data[$value['id']]['recharge_amount'] : 0;
                $data[$value['id']]['winning_amount'] = $value['award_money'];
            }
        }

		if(count($data)>0){
            foreach ($data as $k=>$val){
                //$val['id']=$k;
                $val['create_time']=time();
                //$this->db->replace(" un_user_amount_total",$val);
                $this->updateTotal($val);
            }
        }
        return $data;
    }
    
    public function updateTotal($data)
    {
        $sql = "select `user_id` from `un_user_amount_total` where `user_id` = " . $data['user_id'];
        $arr_id = $this->db->getone($sql);
        if (!empty($arr_id)) {
            $this->db->update('un_user_amount_total', $data, ['user_id' => $data['user_id']]);
        } else {
            $this->db->insert('un_user_amount_total', $data);
        }
    }

    /**
     * 会员资金信息统计数量
     * @author king
     * @date 2017/09/18
     * @param $where
     * @return $string
     */
    public function user_amount_count($where){
        $sql="SELECT count(a.user_id) FROM un_user_amount_total a LEFT JOIN un_user b ON a.user_id=b.id WHERE $where LIMIT 1";
        $result=$this->db->result($sql);
        return $result;
    }

    //风险会员监控
    public function get_user_amount_count_list($where,$param) {
        $sql="SELECT a.*,b.username,b.weixin,b.group_id,c.name FROM un_user_amount_total a LEFT JOIN un_user b ON a.user_id=b.id LEFT JOIN un_user_group c ON b.group_id=c.id WHERE $where ORDER BY betting_amount DESC  LIMIT {$param['pagestart']},{$param['pagesize']}";
        return $this->db->getall($sql);
    }
    
    //风险会员监控
    public function get_user_amount_count_list_time() 
    {
        $sql="SELECT create_time FROM un_user_amount_total";
        $time = $this->db->getone($sql);
        
        return empty($time['creat_time']) ? time() : $time['creat_time'];
    }

    public function checkUsername($username) {
        $user_info = $this->db->getone("select id from #@_user where username = '{$username}'");
        if (empty($user_info)) {
            return false;
        } else {
            return $user_info['id'];
        }
    }

}