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
include_cache(S_PAGE . 'model' . DS . 'admin' . DS. 'mima.php');
include_cache(S_PAGE . 'model' . DS . 'admin' . DS. 'withdrawfactory.php');

class FinanceModel extends CommonModel {

    protected $table = '#@_dictionary';
    protected $table1 = '#@_user_bank';
    protected $table2 = '#@_account_recharge';
    protected $table3 = '#@_payment_config';
    protected $table4 = '#@_user';
    protected $table5 = '#@_account_cash';
    protected $table6 = '#@_account';
    protected $tablePaymentGroup = '#@_payment_group';
    protected $tableDictionary = '#@_dictionary';
    protected $tableDictionaryClass = '#@_dictionary_class';
    protected $tableUserGroup = '#@_user_group';
    protected $tableTTflCfg = '#@_ttfl_cfg';
    protected $tableTTflLog = '#@_ttfl_log';
    protected $tableAccountLog = '#@_account_log';
    private $withdrawStatus = array(0 => '未完成', 1 => '完成', 2 => '同意', 3 => '客服同意');

    //线下充值统计 all
    public function offlineTJ($where, $payIds = [])
    {
        $query = "";
        if ($where['order_sn'] != "") {
            $query .= " and a.order_sn = '{$where['order_sn']}'";
        }
        if ($where['username'] != "") {
            $uRt = $this->db->getone("select id from un_user where username = '{$where['username']}'");
            $uId = empty($uRt) ? 0 : $uRt['id'];
            $query .= " and a.user_id = {$uId}";
        }
        if ($where['status'] != "") {
            $query .= " and a.status = {$where['status']}";
        }else {
            $query_sql .= " and a.status <> 3";
        }
        
        if ($where['remark'] != '') {
            $query .= " and a.remark='{$where['remark']}' ";
        }
        
        if ($where['reg_type'] != 0) {
            $query .= " and b.reg_type='{$where['reg_type']}' ";
        }else{
            $query .= " and b.reg_type not in(8,9,11) ";
        }

        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query .= " and a.addtime > $time ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query .= " and a.addtime < $time ";
        }  
        
        /* if(!empty($payIds)){
            $paymentIdStr = implode(',', $payIds);
            $query .= " and a.payment_id in ({$paymentIdStr}) ";
        } */
        
        if(!empty($payIds)){
            $paymentIdStr = implode(',', $payIds);
            $query .= " and (pc.type in ({$paymentIdStr}) or a.pay_type in ({$paymentIdStr}))";
        }
        
        if (!empty($where['bank_id'])) {
            $query .= " and (pc.id = " . $where['bank_id'] . " or a.bank_id = " . $where['bank_id'] . ") ";

//            left join un_dictionary as d on pc.bank_id = d.id or a.bank_id = d.id
            $sql1 = "select ifnull(sum(money),0) as cnt from un_account_recharge as a 
                left join un_user as b on a.user_id = b.id
                left join un_payment_config as pc on a.payment_id = pc.id
                where a.status = 0".$query;
            $sql2 = "select ifnull(sum(money),0) as cnt from un_account_recharge as a 
                left join un_user as b on a.user_id = b.id  
                left join un_payment_config as pc on a.payment_id = pc.id
                where a.status = 1".$query;
            $sql3 = "select ifnull(sum(money),0) as cnt from un_account_recharge as a 
                left join un_user as b on a.user_id = b.id  
                left join un_payment_config as pc on a.payment_id = pc.id
                where a.status = 2".$query;
        } else {
            $sql1 = "select ifnull(sum(money),0) as cnt from un_account_recharge as a 
                left join un_user as b on a.user_id = b.id
                left join un_payment_config as pc on a.payment_id = pc.id
                where a.status = 0".$query;
            $sql2 = "select ifnull(sum(money),0) as cnt from un_account_recharge as a
                left join un_user as b on a.user_id = b.id
                left join un_payment_config as pc on a.payment_id = pc.id
                where a.status = 1".$query;
            $sql3 = "select ifnull(sum(money),0) as cnt from un_account_recharge as a
                left join un_user as b on a.user_id = b.id
                left join un_payment_config as pc on a.payment_id = pc.id
                where a.status = 2".$query;
        }

        $rt1 = $this->db->getone($sql1);
        $rt2 = $this->db->getone($sql2);
        $rt3 = $this->db->getone($sql3);
        return array("succMoney" => $rt2['cnt'], "dealMoney" => $rt1['cnt'], "cancMoney" => $rt3['cnt']);
    }

    //银行列表
    public function listBank($where) {
        $sql = "select * from " . $this->table . " where classid = {$where['classid']}"; //1-银行信息

        $sql .= " limit {$where['page_start']},{$where['page_size']}";
        $rt = $this->db->getall($sql);
        return ($rt);
    }

    public function listAllBank($classid) {
        $sql = "select id,name,classid from " . $this->table . " where classid IN({$classid}) ORDER BY classid"; //1-银行信息
        $rt = $this->db->getall($sql);
        return ($rt);
    }
    
    //获取所有银行，外加QQ钱包、微信、支付宝
    public function listAllBanks() {
        //$sql = "select id,name,classid from " . $this->table . " where classid IN({$classid})"; //1-银行信息
        $sql = " select id,name,classid from un_dictionary where classid = 1 OR `id` IN (1, 2, 124, 201, 211, 213)";
        $rt = $this->db->getall($sql);
        return ($rt);
    }
    
    //获取单一支付方式下所有充值类型
    public function listAllBanksType($classid) {
        $sql = " select id,name,classid from un_dictionary where classid = " . $classid;
        $rt = $this->db->getall($sql);
        return ($rt);
    }

    //银行总数
    public function cntBank($where) {
        $sql = "select count(*) as cnt from " . $this->table . " where classid = {$where['classid']}"; //1-银行信息
        $rt = $this->db->getone($sql);
        return ($rt['cnt']);
    }

    //添加银行信息
    public function addBank($data) {
        $rt = $this->db->insert($this->table, $data);
        return $rt;
    }
    
    /**
     * 删除银行卡信息
     * @param int $bankId
     * @return array
     */
    public function deleteBankCard($paymentId)
    {
        $time = strtotime("-2 day");

        $bankInfo = $this->db->getone('select `group_id` from ' . $this->table3 . ' where `id` = ' . $paymentId);
        if (empty($bankInfo)) {
            return ['code' => 0, 'msg' => '该银行卡账号不存在!'];
        }
        if (!empty($bankInfo['group_id'])) {
            return ['code' => 2, 'msg' => '删除失败，该银行卡账号已绑定银行卡组，删除银行卡信息前，请先解绑银行卡组!'];
        }
        
        $orderInfo = $this->db->getone('select `id` from ' . $this->table2 . ' where `status` = 0 and `payment_id` = ' . $paymentId . ' and addtime > ' . $time);
        if (!empty($orderInfo)) {
            return ['code' => 2, 'msg' => '删除失败，该银行卡账号下还有未处理完的充值订单信息，删除银行卡信息前，请先处理完相关的充值信息!'];
        }

        $ret = $this->db->delete($this->table3, array('id' => $paymentId));
        if ($ret > 0) {
            return ['code' => 1, 'msg' => '银行卡删除成功!'];
        } else {
            return ['code' => 0, 'msg' => '银行卡删除失败!'];
        }
    }

    public function deleteBank($data,$classid) 
    {
        $sql = "select id from " . $this->table3 . " where bank_id = " . $data; //1-银行信息
        $payConfig = $this->db->getone($sql);
        
        if (!empty($payConfig)) return -1;
        
        $rt = $this->db->delete($this->table, array('id' => $data, 'classid' => $classid));
        
        if ($rt > 0) {
            $this->db->update($this->table3, array('bank_id' => 0), array('bank_id' => $data));
        }
        
        return $rt;
    }

    //获取bank的value  最后一条信息 + 1
    public function lastBankValue() {
        $sql = "select value from " . $this->table . " where classid = 1 order by value desc "; //1-银行信息
        $rt = $this->db->getone($sql);
        $value = $rt['value'];
        $num = substr($value, 1, strlen($value) - 1);
        $str = "b" . ($num + 1);
        return $str;
    }

    public function getDictionary($dictionaryId) {
        $sql = "select * from " . $this->table . " where id = " . $dictionaryId; //1-银行信息
        $rt = $this->db->getone($sql);
        return $rt;
    }

    //银行卡列表
    public function listBankcard($where)
    {
        $sql = "select pc.id, pc.type, pc.nid, pc.logo, pc.config, pc.fee, pc.balance, pc.min_recharge, pc.max_recharge, pc.lower_limit,pc.upper_limit, pc.group_id, pc.remark, pc.sort, d.name, pg.purpose, pc.canuse from {$this->table3} as pc "
                . " left join {$this->table} as d on pc.bank_id = d.id "
                . " left join un_payment_group as pg on pc.id = pg.payment_id"//银行卡信息
                . " where  type in (35,36,37, 125, 202, 211, 213)";
        $sql .= " limit {$where['page_start']},{$where['page_size']}";
        $rt = $this->db->getall($sql);
        
        return ($rt);
    }
    
    //银行卡列表
    public function listBankcardType($where)
    {
        $sql = "select DISTINCT(pc.id), pc.type, pc.nid, pc.logo, pc.config, pc.fee, pc.balance, pc.min_recharge, pc.max_recharge, pc.lower_limit,pc.upper_limit, pc.group_id, pc.remark, pc.sort, d.name, pg.purpose, pc.canuse,pc.handsel from {$this->table3} as pc "
        . " left join {$this->table} as d on pc.bank_id = d.id "
        . " left join un_payment_group as pg on pc.id = pg.payment_id"//银行卡信息
        . " where  type = " . $where['type'];
        $sql .= " limit {$where['page_start']},{$where['page_size']}";
        $rt = $this->db->getall($sql);
    
        return ($rt);
    }

    //银行卡总数
    public function cntBankcard($where='') {
        $sql = "select count(*) as cnt from " . $this->table3.$where; //1-银行信息
        $rt = $this->db->getone($sql);
        return ($rt['cnt']);
    }

    //添加银行卡信息
    public function addBankcard($data) {
        $rt = $this->db->insert($this->table3, $data);
        return $rt;
    }

    //修改银行卡信息
    public function upBankcard($data, $where) {
        $rt = $this->db->update($this->table3, $data, $where);
        return $rt;
    }

    
    //充值列表
    public function listCharge($where,$payIds=array()) {
        $sql = "select r.bank_name, r.pay_type, r.bank_id, r.id,r.order_sn,u.id as uid,u.username,u.realname,r.payment_id,r.money,r.addtime,r.status,r.remark,r.verify_userid,pc.bank_id as pcbank_id from " . $this->table2 . " as r"
            . " left join " . $this->table4 . " as u on r.user_id = u.id "
            . " left join " . $this->table3 . " as pc on pc.id = r.payment_id";
        $query_sql = " where 1=1 ";
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and r.addtime >= $time ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query_sql .= " and r.addtime <= $time ";
        }

        if(!empty($payIds)){
            $paymentIdStr = implode(',', $payIds);
            $query_sql .= " and (pc.type in ({$paymentIdStr}) or r.pay_type in ({$paymentIdStr}))";
        }
        
        if ($where['username'] != '') {
            $query_sql .= " and u.username='{$where['username']}' ";
        }
        
        if ($where['realname'] != '') {
            $query_sql .= " and u.realname like '%{$where['realname']}%' ";
        }
        
        if ($where['reg_type'] != 0) {
            $query_sql .= " and u.reg_type='{$where['reg_type']}' ";
        }else{
            $query_sql .= " and u.reg_type not in(8,9,11) ";
        }
        if ($where['order_sn'] != '') {
            $query_sql .= " and r.order_sn='{$where['order_sn']}' ";
        }
        if ($where['status'] != '') {
            $query_sql .= " and r.status='{$where['status']}' ";
        }else {
            $query_sql .= " and r.status <> 3";
        }
        
        //所属银行判断
        if ($where['bank_id'] != '' && is_numeric($where['bank_id'])) {
            $query_sql .= " and (pc.bank_id = {$where['bank_id']} or r.bank_id = {$where['bank_id']})";
        }
//        if ($where['payment_id'] != '') {
//            $query_sql .= " and p.type='{$where['payment_id']}' ";
//        }
        if ($where['remark'] != '') {
            $query_sql .= " and r.remark='{$where['remark']}' ";
        }

        $sql .= $query_sql;

        $sql .= " order by addtime desc limit {$where['page_start']},{$where['page_size']}";

        $rt = $this->db->getall($sql);
        return ($rt);
    }
    

    //提现卡,充值卡排序列表
    public function listPayGroup($where) {
        $sql = "select r.id, r.payment_id,r.sort, t.name as pay_type,u.name as user_group,v.name as entrance,w.name as bank,config,balance,lower_limit,upper_limit from " . $this->tablePaymentGroup . " as r"
                . " left join " . $this->table3 . " as s on r.payment_id = s.id"
                . " left join " . $this->tableDictionary . " as t on r.pay_type = t.id "
                . " left join " . $this->tableUserGroup . " as u on r.user_group = u.id"
                . " left join " . $this->tableDictionary . " as v on r.entrance = v.value and v.classid=4"
                . " left join " . $this->tableDictionary . " as w on s.bank_id = w.id";
        $sql .= " where 1=1 and purpose=" . $where['purpose'];
        $sql .= " order by r.entrance asc, sort asc limit {$where['page_start']},{$where['page_size']}";
        $rt = $this->db->getall($sql);
        return ($rt);
    }
    
    //提现卡,充值卡排序列表(一个充值方式多个前段查询）
    public function listPayGroups($where) {
        $sql = "select r.id, r.payment_id,r.sort, t.name as pay_type,r.user_group as user_group,v.name as entrance,w.name as bank,config,balance,lower_limit,upper_limit from " . $this->tablePaymentGroup . " as r"
                . " left join " . $this->table3 . " as s on r.payment_id = s.id"
                . " left join " . $this->tableDictionary . " as t on r.pay_type = t.id "
                . " left join " . $this->tableDictionary . " as v on r.entrance = v.value and v.classid=4"
                . " left join " . $this->tableDictionary . " as w on s.bank_id = w.id";
        $sql .= " where 1=1 and entrance_group = 0 AND purpose=" . $where['purpose'];
        $sql .= " order by r.entrance asc, sort asc limit {$where['page_start']},{$where['page_size']}";
        payLog('a.txt',$sql);
        $rt = $this->db->getall($sql);
        
        return ($rt);
    }
    
    //统计提现卡,充值卡一个充值方式多个前段查询）
    public function getPayEntrance($id)
    {
        $strEntrance = '';
        $sql = "select  gp.entrance, gp.entrance_group, v.name from " . $this->tablePaymentGroup . " as gp 
               left join " . $this->tableDictionary . " as v on gp.entrance = v.value and v.classid=4
               where entrance_group = " . $id;
        $payData = $this->db->getall($sql);
        if (!empty($payData)) {
            foreach ($payData as $kp => $vp) {
                $strEntrance .= '/' . $vp['name'];
            }
        }
        
        return $strEntrance;
    }

    public function getPayGroupInfo($payGroupId) {
        $sql = "select r.id,r.payment_id, r.sort, t.name as pay_type,r.user_group as user_group,v.name as entrance,w.name as bank,config,balance,lower_limit,upper_limit from " . $this->tablePaymentGroup . " as r"
                . " left join " . $this->table3 . " as s on r.payment_id = s.id"
                . " left join " . $this->tableDictionary . " as t on r.pay_type = t.id"
                . " left join " . $this->tableUserGroup . " as u on r.user_group = u.id"
                . " left join " . $this->tableDictionary . " as v on r.entrance = v.value and v.classid=4"
                . " left join " . $this->tableDictionary . " as w on s.bank_id = w.id";
        $query_sql = " where 1=1 and r.id=" . $payGroupId;
        $sql .= $query_sql;
        $rt = $this->db->getone($sql);
        return ($rt);
    }

    public function getPayGroup($payGroupId) {
        $sql = "select * from " . $this->tablePaymentGroup;
        $query_sql = " where id=" . $payGroupId;
        $sql .= $query_sql;
        $rt = $this->db->getone($sql);
        return ($rt);
    }

    
    public function getUnbindPayConfigByPayType($payType) {
        $sql = "select s.id, s.type,s.config,s.fee,s.lower_limit,s.upper_limit, s.balance,r.name as bank from " . $this->table3 . ' as s left join ' . $this->tableDictionary . ' as r on s.bank_id = r.id';
        $query_sql = " where s.type=" . $payType . ' and group_id=0 and canuse = 1';
        $sql .= $query_sql;
        $rt = $this->db->getall($sql);
        return ($rt);
    }
    
    public function cntPayGroup($purpose) {
        
        $sql = "select count(*) as cnt from " . $this->tablePaymentGroup;
        $query_sql = " where 1=1 and purpose=" . $purpose;
        $sql .= $query_sql;
        $rt = $this->db->getone($sql);
        return ($rt['cnt']);
    }
    
    //统计提现卡,充值卡(一个充值方式多个前段查询）
    public function cntPayGroups($purpose)
    {
        /*
        $sql_1 = "select count(*) as cnt from " . $this->tablePaymentGroup . " where entrance_group > 0 AND purpose=" . $purpose . " group by entrance_group";
        $count_1 = $this->db->getone($sql_1);

        $sql_2 = "select count(*) as cnt from " . $this->tablePaymentGroup . " where entrance_group = 0 AND purpose=" . $purpose;
        $count_2 = $this->db->getone($sql_2);
*/
        $sql = "select count(*) as cnt from " . $this->tablePaymentGroup . " where entrance_group = 0 AND purpose = " . $purpose;
        $rt = $this->db->getone($sql);
        return ($rt['cnt']);
    }

    //充值总数
    public function cntCharge($where,$payIds=array())
    {
        $sql = "select count(*) as cnt from " . $this->table2 . " as r"
            . " inner join " . $this->table4 . " as u on r.user_id = u.id "
            . " left join " . $this->table3 . " as pc on pc.id = r.payment_id";
        $query_sql = " where 1=1 ";
        //所属银行判断
        if ($where['bank_id'] != '' && is_numeric($where['bank_id'])) {
            $query_sql .= " and (pc.bank_id = {$where['bank_id']} or r.bank_id = {$where['bank_id']})";
        }

        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and r.addtime > $time ";

        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query_sql .= " and r.addtime < $time ";
        }

        if(!empty($payIds)){
            $paymentIdStr = implode(',', $payIds);
            $query_sql .= " and (pc.type in ({$paymentIdStr}) or r.pay_type in ({$paymentIdStr}))";
        }
        if ($where['username'] != '') {
            $query_sql .= " and u.username='{$where['username']}' ";
        }
        
        if ($where['realname'] != '') {
            $query_sql .= " and u.realname like '%{$where['realname']}%' ";
        }
        
        if ($where['reg_type'] != 0) {
            $query_sql .= " and u.reg_type='{$where['reg_type']}' ";
        }else{
            $query_sql .= " and u.reg_type not in(8,9,11) ";
        }
        if ($where['order_sn'] != '') {
            $query_sql .= " and r.order_sn='{$where['order_sn']}' ";
        }

        if ($where['status'] != '') {
            $query_sql .= " and r.status='{$where['status']}' ";
        }else {
            $query_sql .= " and r.status <> 3";
        }
        
        if ($where['remark'] != '') {
            $query_sql .= " and r.remark='{$where['remark']}' ";
        }
        $sql .= $query_sql;
       // echo $sql;
        $rt = $this->db->getone($sql);
        return ($rt['cnt']);
    }

    //确认充值
    public function submitCharge($id) {
        if (!is_numeric($id)) {
            throw new Exception('input error');
        }
        //增加余额
        //增加用户账户金额
        $money = $this->db->getone('select money from ' . $this->table2 . ' where id =' . $id);

        //$this->db->update($this);

        return $this->db->update($this->table2, array('status' => 1), array('id' => $id));
    }


    //提现列表
    public function listDrawal($where) {

        $w_nid = '';
        if($where['w_type'] == 4) $w_nid = 'quan_yin_withdraw';         //全银代付
        if($where['w_type'] == 5) $w_nid = 'mi_man_withdraw';           //米曼代付
        if($where['w_type'] == 6) $w_nid = 'jia_yi_withdraw';           //嘉亿

        $sql = "select user.group_id,user.id as uid,cash.id,cash.order_sn,cash.verifyremark,cash.verify_user_id,bank.name as realname,cash.addtime,cash.status,cash.money,user.username,bank.branch,bank.account,cash.payment_id,bank.bank"
                . " from " . $this->table5 . " as cash"
                . " left join " . $this->table1 . " as bank on cash.bank_id = bank.id"
                . " left join " . $this->table4 . " as user on cash.user_id = user.id"
                . " left join " . $this->table3 . " as upc on cash.payment_id = upc.id";

        $query_sql = " where 1=1 ";
        if ($where['username'] != '') {
            $query_sql .= " and user.username='{$where['username']}' ";
        }
        
        if ($where['realname'] != '') {
            $query_sql .= " and user.realname='{$where['realname']}' ";
        }
        
        if ($where['reg_type'] != 0) {
            $query_sql .= " and user.reg_type='{$where['reg_type']}' ";
        }else{
            $query_sql .= " and user.reg_type not in(8,9,11) ";
        }
        if ($where['order_sn'] != '') {
            $query_sql .= " and cash.order_sn='{$where['order_sn']}' ";
        }
        if ($where['status'] != '') {// 0 1 2 3 只有1是成功
            if ($where['status'] == 1) {
                $query_sql .= " and cash.status = 1 ";
            } else if ($where['status'] == 2) {
                $query_sql .= " and cash.status =2 ";
            } else if (is_array($where['status'])) {
                $count = count($where['status']) -1;
                $connect = $where['status'][$count];
                unset($where['status'][$count]);
                $query_sql .= " and (";
                foreach ($where['status'] as $k => $v) {
                    $query_sql .= " cash.status " . $v[0].$v[1] ;
                    if ($k+1 < $count) {
                        $query_sql .= " ".$connect;
                    }
                }
                $query_sql .= ")";
            } else {
                $query_sql .= " and cash.status =0 ";
            }
        }
        if ($where['account'] != '') {
            $query_sql .= " and bank.account='{$where['account']}' ";
        }
        
        //银行类型
        if ($where['type'] != '') {
            if (!in_array($where['type'], [1, 2, 124])) {
                $query_sql .= " and bank.bank not in (1, 2, 124, 201, 210, 212) ";
            } else {
                $query_sql .= " and bank.bank = '{$where['type']}' ";
            }
        }
        if($where['w_type']) {
            if($w_nid) {        //代付
                $query_sql .= " and upc.type = 302 and upc.nid = '".$w_nid."'";
            }else {         //微信  支付宝  银联
                $query_sql .= " and upc.type = ".$where['w_type'];
            }
        }
        
        if ($where['verify_user_id'] != '') {

            $verify_user_id = $this->db->getone('select userid from un_admin where username="' . $where['verify_user_id'] . '"')['userid'];


            $query_sql .= " and cash.verify_user_id='{$verify_user_id}' ";
        }
        if ($where['s_money'] != '') {
            $query_sql .= " and cash.money >= {$where['s_money']} ";
        }
        if ($where['e_money'] != '') {
            $query_sql .= " and cash.money <= {$where['e_money']} ";
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and cash.addtime >= $time ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query_sql .= " and cash.addtime <= $time ";
        }
        $sql .= $query_sql;

//        $sql .= " group by cash.id order by addtime desc limit {$where['page_start']},{$where['page_size']}";
        $sql .= " order by addtime desc limit {$where['page_start']},{$where['page_size']}";

        $rt = $this->db->getall($sql);

        for ($i = 0; $i < count($rt); $i++) {
//            un_account_log on cash.order_sn = un_account_log.order_num"

            $money_usable = $this->db->getone('select use_money from un_account_log  where order_num="' . $rt[$i]['order_sn'] . '" order by id desc');
            if ($money_usable) {
                $rt[$i]['money_usable'] = $money_usable['use_money'];
            }
        }

        for ($i = 0; $i < count($rt); $i++) {
            $remark = json_decode($rt[$i]['verifyremark'], true);
            $rt[$i]['detailStatus'] = $remark['status'];
            $adminName = '';
            for ($j = 0; $j < count($remark['remark']); $j++) {
                $adminName = $adminName . $remark['remark'][$j]['admin'] . ',';
            }
            $rt[$i]['admin_name'] = substr($adminName, 0, -1);
        }
        return ($rt);
    }

    //提现总数
    public function cntDrawal($where) {
        $w_nid = '';
        if($where['w_type'] == 4) $w_nid = 'quan_yin_withdraw';         //全银代付
        if($where['w_type'] == 5) $w_nid = 'mi_man_withdraw';           //米曼代付
        if($where['w_type'] == 6) $w_nid = 'jia_yi_withdraw';           //嘉亿

        $sql = "select count(*) as cnt from " . $this->table5 . " as cash"
                . " left join " . $this->table1 . " as bank on cash.bank_id = bank.id"
                . " left join " . $this->table4 . " as user on cash.user_id = user.id"
                . " left join " . $this->table3 . " as upc on cash.payment_id = upc.id";
        $query_sql = " where 1=1 ";
        if ($where['username'] != '') {
            $query_sql .= " and user.username='{$where['username']}' ";
        }
        
        if ($where['realname'] != '') {
            $query_sql .= " and user.realname='{$where['realname']}' ";
        }
        
        if ($where['reg_type'] != 0) {
            $query_sql .= " and user.reg_type='{$where['reg_type']}' ";
        }else{
            $query_sql .= " and user.reg_type not in(8,9,11) ";
        }
        if ($where['order_sn'] != '') {
            $query_sql .= " and cash.order_sn='{$where['order_sn']}' ";
        }
        if ($where['status'] != '') {// 0 1 2 3 只有1是成功
            if ($where['status'] == 1) {
                $query_sql .= " and cash.status = 1 ";
            } else if ($where['status'] == 0) {
//                $query_sql .= " and cash.status in (0,2,3) ";
                $query_sql .= " and cash.status = 0 ";
            } else if (is_array($where['status'])) {
                $count = count($where['status']) -1;
                $connect = $where['status'][$count];
                unset($where['status'][$count]);
                $query_sql .= " and (";
                foreach ($where['status'] as $k => $v) {
                    $query_sql .= " cash.status " . $v[0].$v[1] ;
                    if ($k+1 < $count) {
                        $query_sql .= " ".$connect;
                    }
                }
                $query_sql .= ")";
            } else {
//                $query_sql .= " and cash.status in (0,2,3) ";
                $query_sql .= " and cash.status = 2 ";
            }
        }
        if ($where['account'] != '') {
            $query_sql .= " and bank.account='{$where['account']}' ";
        }
        
        //银行类型
        if ($where['type'] != '') {
            if (!in_array($where['type'], [1, 2, 124])) {
                $query_sql .= " and bank.bank not in (1, 2, 124, 201, 210, 212) ";
            } else {
                $query_sql .= " and bank.bank = '{$where['type']}' ";
            }
        }
        if($where['w_type']) {
            if($w_nid) {        //代付
                $query_sql .= " and upc.type = 302 and upc.nid = '".$w_nid."'";
            }else {         //微信  支付宝  银联
                $query_sql .= " and upc.type = ".$where['w_type'];
            }
        }
        
        if ($where['verify_user_id'] != '') {
            $verify_user_id = $this->db->getone('select userid from un_admin where username="' . $where['verify_user_id'] . '"')['userid'];
            $query_sql .= " and cash.verify_user_id='{$verify_user_id}' ";
        }
        if ($where['s_money'] != '') {
            $query_sql .= " and cash.money >= {$where['s_money']} ";
        }
        if ($where['e_money'] != '') {
            $query_sql .= " and cash.money <= {$where['e_money']} ";
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and cash.addtime >= $time ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query_sql .= " and cash.addtime <= $time ";
        }
        $sql .= $query_sql;
        $rt = $this->db->getone($sql);
//        $table_change = array('count(*) as cnt' => 'SUM(cash.money) as money');
//        $table_change += array('1=1' => 'cash.status=1 ');
//        $sql2 = strtr($sql, $table_change);
        //$res = $this->db->getone($sql2);
        if (empty($res['money'])) {
            $res['money'] = 0;
        }
        return array('cnt' => $rt['cnt'], 'total' => $res['money']);
    }

    //提现总数
    public function drawal_num($where) {
        $sql = "select count(*) as cnt from " . $this->table5 . " as cash"
            . " left join " . $this->table1 . " as bank on cash.bank_id = bank.id"
            . " left join " . $this->table4 . " as user on cash.user_id = user.id";
        $query_sql = " where 1=1 ";
        if ($where['username'] != '') {
            $query_sql .= " and user.username='{$where['username']}' ";
        }

        if ($where['realname'] != '') {
            $query_sql .= " and user.realname='{$where['realname']}' ";
        }

        if ($where['reg_type'] != 0) {
            $query_sql .= " and user.reg_type='{$where['reg_type']}' ";
        }else{
            $query_sql .= " and user.reg_type not in(8,9,11) ";
        }
        if ($where['order_sn'] != '') {
            $query_sql .= " and cash.order_sn='{$where['order_sn']}' ";
        }
        if ($where['status'] != '') {// 0 1 2 3 只有1是成功
            if ($where['status'] == 1) {
                $query_sql .= " and cash.status = 1 ";
            } else if ($where['status'] == 0) {
//                $query_sql .= " and cash.status in (0,2,3) ";
                $query_sql .= " and cash.status = 0 ";
            } else if (is_array($where['status'])) {
                $count = count($where['status']) -1;
                $connect = $where['status'][$count];
                unset($where['status'][$count]);
                $query_sql .= " and (";
                foreach ($where['status'] as $k => $v) {
                    $query_sql .= " cash.status " . $v[0].$v[1] ;
                    if ($k+1 < $count) {
                        $query_sql .= " ".$connect;
                    }
                }
                $query_sql .= ")";
            } else {
//                $query_sql .= " and cash.status in (0,2,3) ";
                $query_sql .= " and cash.status = 2 ";
            }
        }
        if ($where['account'] != '') {
            $query_sql .= " and bank.account='{$where['account']}' ";
        }

        //银行类型
        if ($where['type'] != '') {
            if (!in_array($where['type'], [1, 2, 124])) {
                $query_sql .= " and bank.bank not in (1, 2, 124, 201, 210, 212) ";
            } else {
                $query_sql .= " and bank.bank = '{$where['type']}' ";
            }
        }

        if ($where['verify_user_id'] != '') {
            $verify_user_id = $this->db->getone('select userid from un_admin where username="' . $where['verify_user_id'] . '"')['userid'];
            $query_sql .= " and cash.verify_user_id='{$verify_user_id}' ";
        }
        if ($where['s_money'] != '') {
            $query_sql .= " and cash.money >= {$where['s_money']} ";
        }
        if ($where['e_money'] != '') {
            $query_sql .= " and cash.money <= {$where['e_money']} ";
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and cash.addtime >= $time ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query_sql .= " and cash.addtime <= $time ";
        }
        $sql .= $query_sql;
        $table_change = array('count(*) as cnt' => 'SUM(cash.money) as money');
        $table_change += array('1=1' => '(cash.status=1 or cash.status = 4) ');
        $sql2 = strtr($sql, $table_change);
        $res = $this->db->getone($sql2);
        if (empty($res['money'])) {
            $res['money'] = 0;
        }
        return array('total' => $res['money']);
    }

    public function addPayGroup($data, $entrance) {
        $rt = $this->db->insert($this->tablePaymentGroup, $data);
        if ($rt && !empty($entrance)) {
            $data['entrance_group'] = $rt;
            foreach ($entrance as $ke => $ve) {
                $data['entrance'] = $ve;
                $this->db->insert($this->tablePaymentGroup, $data);
            }
            //$this->db->update($this->tablePaymentGroup, ['entrance_group' => $rt], ['id' => $rt]);
        }

        return ['code' => $rt, 'msg' => '添加成功！'];
    }

    /**
     * 将线下充值方式绑定到线下充值卡组
     * @param int $payGroupId 卡组ID
     * @param int $paymentId   卡ID
     * @param number $sort     排序号
     */
    public function bindPayment($payGroupId, $paymentId, $sort = 0)
    {
        $retval = $this->validateBindPayment($payGroupId, $paymentId, $sort);
        if ($retval !== true) {
            return $retval;
        }
        
        $this->db->query('BEGIN');
        
        try {
            $ret1 = $this->db->exec("UPDATE " . $this->tablePaymentGroup . " SET " . " payment_id = " . $paymentId . ', sort = ' . $sort
                . " WHERE id = " . $payGroupId . " OR entrance_group = " . $payGroupId);
            $ret2 = $this->db->update($this->table3, array('group_id' => $payGroupId), array('id' => $paymentId));
            //$this->db->update($this->tablePaymentGroup, array('payment_id' => $paymentId, 'sort' => $sort), array('id' => $payGroupId));
            
            if ($ret1 && $ret2) {
                $this->db->query('COMMIT');

                return ['code' => 1, 'msg' => '绑定成功！'];
            } else {
                $this->db->query('ROLLBACK');

                return ['code' => 0, 'msg' => '绑定失败！'];
            }
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            
            return ['code' => 0, 'msg' => '绑定失败！'];
        }
    }
  
    /**
     * 解绑卡与卡组
     * @param number $payGroupId 卡组ID
     * @return array
     */
    public function unBindPayConfig($payGroupId) {
        $payGroup = $this->db->getone('select * from ' . $this->tablePaymentGroup . ' where payment_id > 0 AND id = ' . $payGroupId);
        if (empty($payGroup)) {
            return ['code' => 0, 'msg' => '卡组已解绑或不存在，解绑失败！'];
        }
        
        $this->db->query('BEGIN');
        
        try {
            $sqlpg = "UPDATE " . $this->tablePaymentGroup . " SET " . " payment_id = 0, sort = 0"
                . " WHERE id = " . $payGroupId . " OR entrance_group = " . $payGroupId;
            $ret1 = $this->db->query($sqlpg);

            $sql = "UPDATE `un_payment_config` SET `group_id` = '0' WHERE `id` = {$payGroup['payment_id']}";
//            $ret2 = $this->db->update($this->table3, array('group_id' => 0), array('id' => $payGroup['payment_id']));
            $ret2 = $this->db->query($sql);

            lg('un_bind_pay_config',var_export(array(
                '$sqlpg'=>$sqlpg,
                '$ret1'=>$ret1,
                '$sql'=>$sql,
                '$ret2'=>$ret2,
            ),1));
            //$ret2 = $this->db->update($this->tablePaymentGroup, array('payment_id' => 0, 'sort' => 0), array('id' => $payGroupId));
        
            if ($ret1 && $ret2) {
                $this->db->query('COMMIT');
            
                return ['code' => 1, 'msg' => '解绑成功！'];
            } else {

                $this->db->query('ROLLBACK');
            
                return ['code' => 0, 'msg' => '解绑失败！'];
            }
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
        
            return ['code' => 0, 'msg' => '解绑失败！'];
        }
    }

    public function getDictionaryClass($classId) {
        return $this->db->getall('select * from ' . $this->tableDictionary . ' where classid=' . $classId);
    }

    /**
     * 线下绑定银行卡参数验证
     * @param int $payGroupId  用户组ID
     * @param int $paymentId   支付类型ID
     * @param number $sort     支付排序号
     * @return bool|array
     */
    public function validateBindPayment($payGroupId, $paymentId, $sort = 0) {
        $payGroup = $this->db->getone('select * from ' . $this->tablePaymentGroup . ' where payment_id= ' . $paymentId);
        if ($payGroup) {
            //throw new Exception('该支付账号已绑定');
            return ['code' => 0, 'msg' => '绑定失败，该支付账号已绑定'];
        } else {
            $payment = $this->db->getone('select * from ' . $this->table3 . ' where id= ' . $paymentId);
            if (!$payment) {
                //throw new Exception('该支付账号不存在');
                return ['code' => 0, 'msg' => '绑定失败，该支付账号不存在'];
            }

            $payGroup = $this->db->getone('select * from ' . $this->tablePaymentGroup . ' where id= ' . $payGroupId);
            if ($payment['type'] != $payGroup['pay_type']) {
                //throw new Exception('该支付账号类型不符合');
                return ['code' => 0, 'msg' => '绑定失败，该支付账号类型不符合'];
            }
            
            if ($payGroup['purpose'] == 0) {
                if ($sort < 0 || (int)$sort != $sort) {
                    return ['code' => 0, 'msg' => '绑定失败，排序号错误！'];
                }
                
                if ($sort == 0) {
                    return ['code' => 0, 'msg' => '绑定失败，排序号不能为零！'];
                }
            }
            
            //判断排序好是否被使用
            if ($sort > 0 && $sort != $payGroup['sort']) {
                $sort_id = $this->db->getone('select id from ' . $this->tablePaymentGroup . ' where entrance = ' . $payGroup['entrance'] . ' and sort = ' . $sort);
                if (!empty($sort_id)) {
                    return ['code' => 0, 'msg' => '绑定失败，排序号已被使用！'];
                }
            }
        }
        
        return true;
    }

    public function unBindPayment($payGroupId) {

        $rt = $this->db->update($this->table1, array('payment_id' => null), array('id' => $payGroupId));
        return $rt;
    }

    public function getUserGroups() {
        //return $this->db->getall('select * from 'un_user_group  . $this->tableUserGroup);
        return $this->db->getall('select * from ' . $this->tableUserGroup);
    }

    public function refuseRecharge($logId, $remark, $admin) {
        lg("run_time_log","后台用户开始驳回充值订单");
        if ($remark == null || trim($remark) == '') {
            return array('status'=>0,'msg'=>"请填写驳回原因");
        }

        //更改提示音状态
        $start_time = microtime(true);
        $sql = "UPDATE `un_music_tips` SET STATUS=1 WHERE record_id={$logId} AND TYPE IN (2,5);";
        $this->db->query($sql);
        $end_time = microtime(true);
        lg("run_time_log","1.更新提示音信息(un_music_tips)执行时间：".getRunTime($end_time,$start_time));

        lg('music_tips_debug','充值驳回$sql::'.$sql);

        $start_time = microtime(true);
        $rechargeLog = $this->getModel($this->table2, $logId);
        $end_time = microtime(true);
        lg("run_time_log","2.获取充值订单信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

        if ($rechargeLog['status'] != 0) {
            return array('status'=>0,'msg'=>"订单已处理");
        }
        $verify_remark = json_decode($rechargeLog['verify_remark'], true);
        $verify_remark['status'] = 2;
        $adminName = $admin['username'] ? $admin['username'] : 'unknown';
        if ($verify_remark['remark'] == null) {

            $verify_remark['remark'] = array(array('admin' => $adminName, 'remark' => $remark));
        } else {
            array_push($verify_remark['remark'], array('admin' => $adminName, 'remark' => $remark));
        }

        $start_time = microtime(true);
        $res = $this->db->update($this->table2, array('status' => 2,
                    'verify_userid' => $admin['userid'] ? $admin['userid'] : 0, '
            verify_time' => time(),
                    'verify_remark' => json_encode($verify_remark, JSON_UNESCAPED_UNICODE)), array('id' => $logId));
        $end_time = microtime(true);
        lg("run_time_log","3.更新充值订单信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

        $msg = "操作失败!";
        if($res){
            $msg = "操作成功!";
        }
        return array('status'=>$res,'msg'=>$msg);
    }


    public function drawalFollowUp($data) {
        $factory = new withdrawfactory();
        $withdraw = $factory->getInterface($data['nid']);
        $curlData = $withdraw->queryOrder($data);
        $account = $this->db->getone("select money,money_freeze from un_account where user_id = ".$data['user_id'].' LIMIT 1 for update');

        if($curlData['code'] == 10) $remarksT = '自动提现成功';
        if($curlData['code'] == 1) $remarksT = '自动提现失败';
        $verifyremark = json_decode($data['verifyremark'],true);
        if ($verifyremark['remark']) {
            array_push($verifyremark['remark'],['admin'=>$curlData['drawal_name'],'remark'=>$remarksT]);
        } else {
            $verifyremark['remark'] = ['admin'=>$curlData['drawal_name'],'remark'=>$remarksT];
        }

        if ($curlData['code'] == 10) {
            $verifyremark['status'] = 4;
            $insertData = [
                'verifytime' => time(),
                'status' => 4,
                'verifyremark' => json_encode($verifyremark,JSON_UNESCAPED_UNICODE)
            ];
            O('model')->db->query('BEGIN');
            $return1 = $this->db->update('#@_account_cash',$insertData,['id'=>$data['id']]);
            $return2 = $this->db->update("#@_account", array('money_freeze' => $account['money_freeze'] - $curlData['amount']), array('user_id' => $data['user_id']));
            if($return1 && $return2) {
                O('model')->db->query('COMMIT');
                return ['code'=>1,'msg'=>$curlData['msg']];         //提现成功
            }else {
                O('model')->db->query('ROLLBACK');
                payLog('autodrawal.txt',"提现成功,数据更新失败,回滚---".json_encode([$data['order_no'],$return1,$return2]));
                return ['code'=>0,'msg'=>'系统错误,请重试'];
            }
        }elseif($curlData['code'] == 1) {
            $sql = "select money from un_account_log where type = 154 and user_id = " .$data['user_id'] . " and order_num = '" . $data['order_no'] ."'";
            $accountLog = $this->db->getone($sql);
            O('model')->db->query('BEGIN');
            $return2 = $result = 1;
            if($accountLog['money']) { //如有则加入account_cash中
                $result = $this->db->update('#@_account_cash',['extra_fee'=>$accountLog['money']],['id'=>$data['id']]);
                $sql = "select money_freeze from un_account where user_id = " . $data['user_id'];
                $account = $this->db->getone($sql);
                $return2 = $this->db->update("#@_account",['money_freeze'=> $account['money_freeze']+$accountLog['money']],array('user_id' => $data['user_id']));
                lg("autodrawal.txt","line==139==订单号".$data['order_no']."失败额外手续费为".$accountLog['money']."更改金额表结果".$return2);
            }
            $delAccLog = $this->db->delete('#@_account_log',['user_id'=>$data['user_id'],'order_num'=>$data['order_no']]); //删除记录

            $verifyremark['status'] = 0;
            $insertData = [
                'verifytime' => time(),
                'status' => 0,
                'verifyremark' => json_encode($verifyremark,JSON_UNESCAPED_UNICODE),
                'payment_id' => 0,
            ];
            $return1 = $this->db->update('#@_account_cash',$insertData,['id'=>$data['id']]);
            if($result && $return2 && $delAccLog && $return1) {
                O('model')->db->query('COMMIT');
                return ['code'=>2,'msg'=>'自动提现失败!'.$curlData['msg']];         //失败
            }else {
                O('model')->db->query('ROLLBACK');
                payLog('autodrawal.txt',"提现失败,数据更新失败,回滚---".json_encode([$data['order_no'],$result,$return2,$delAccLog,$return1]));
                return ['code'=>0,'msg'=>'系统错误,请重试'];
            }
        }else {
            return ['code'=>3,'msg'=>'提现处理中,请等待['.$curlData['msg'].']'];          //处理中
        }
    }

    public function autoDrawal($data,$admin,$verifyremark) {

        O('model')->db->query('BEGIN');
        $isFirst = D("accountCash")->getIsFirstCash($data['user_id']);
        if(!$isFirst){
            $firstDrawal = "该用户为首次提现 ";
        }else{
            $firstDrawal = "";
        }
        $withdrawSet = $this->getWithdrawSet($data['user_id']);

        $account = $this->db->getone("select money,money_freeze from un_account where user_id = ".$data['user_id'].' LIMIT 1 for update');

        if ($withdrawSet['cont'] > 0 && $data['extra_fee'] > 0) {
            $start_time = microtime(true);
            $this->db->update($this->table5, array('extra_fee' => 0), array('id' => $data['accountCashId']));
            $end_time = microtime(true);
            lg("run_time_log","9.修改提现表中该提现记录里的额外提现手续费归零信息(un_account_cash)执行时间：".getRunTime($end_time,$start_time));

            //冻结的多余额外提现手续费返回用户可用金额
            $start_time = microtime(true);
            $this->db->update($this->table6, array('money' => $account['money'] + $data['extra_fee'],'money_freeze' => $account['money_freeze'] - $data['extra_fee']), array('user_id' => $data['user_id']));
            $end_time = microtime(true);
            lg("run_time_log","10.冻结的多余额外提现手续费返回用户可用金额信息(un_account)执行时间：".getRunTime($end_time,$start_time));
            $data['money'] = $data['money'] - $data['extra_fee'];
            $data['extra_fee'] = 0;
        }

        $this->withdrawLog($data, $account,  0, 0, 0,$firstDrawal);
        $factory = new withdrawfactory();
        $withdraw = $factory->getInterface($data['nid']);
        $result =  $withdraw->doWithdraw($data);
        $id = $result['account_cash_id'];
        payLog('autodrawal.txt',"line 1027 orderNO ".$data['order_sn'] ."代付结果".print_r($result,true));
        if ($result['code'] == 1) { //1失败，2 处理中 10 成功
            $status = 0;
            $remark = "自动提现失败";
            $payment_id = 0;
            O('model')->db->query('ROLLBACK');
        } elseif ($result['code'] == 2) {
            $status = 8; //自动提现处理中
            $remark = "自动提现处理中";
            $payment_id = $result['payment_id'];
        } elseif ($result['code'] == 3) {
            O('model')->db->query('ROLLBACK');
            return ['code'=>0,'msg'=>$result['msg']];
        } elseif ($result['code'] == 10) {
            $remark = "自动提现成功";
        }
//        payLog('cc.txt',print_r($result['code'],true). "====1043+++++".$status);
        $adminName = '客服:' . ($admin['username'] ? $admin['username'] : 'unkown');
        $remarkarray = json_decode($verifyremark,true)['remark'];
        if ($remarkarray) {
            array_push($remarkarray,['admin'=>$result['drawal_name'],'remark'=>$remark],
                ['admin'=>$adminName,'remark'=>'同意']);
        } else {
            $remarkarray = [['admin'=>$result['drawal_name'],'remark'=>$remark],
                ['admin'=>$adminName,'remark'=>'同意']];
        }
//        $remark =
        if ($result['code']== 1 || $result['code'] == 2) {
            $insertData = [
                'verifytime' => time(),
                'status' => $status,
                'payment_id' => $payment_id,
                'verifyremark' => json_encode(array('status'=>$status,'remark'=>$remarkarray),JSON_UNESCAPED_UNICODE)
            ];

           $aaa = D('accountCash')->save($insertData,['id'=>$id]);
//           $bbb = $this->db->update($this->table6, array('money_freeze' => $account['money_freeze'] - $result['money']), array('user_id' => $result['user_id']));
            O('model')->db->query('COMMIT');
            $this->db->update("un_user",['bet_amount'=>0],["id"=>$data['user_id']]);
            return ['code'=>0,'msg'=>$result['msg']];
        } elseif ($result['code']== 10) {

            $insertData = [
                'status' => 4,
                'verifytime' => time(),
                'payment_id' => $data['payment_id'],
                'verifyremark' => json_encode(array('status'=>4,'remark'=>$remarkarray),JSON_UNESCAPED_UNICODE)

            ];

            $changeCash = D('accountCash')->save($insertData,['id'=>$id]);
            payLog('autodrawal.txt',"==1076== update accountCash data:".json_encode($insertData));
            $newfreeze = $account['money_freeze'] - $result['amount'];
            payLog('autodrawal.txt','====1079=== 用户金额'.print_r($account,true).$newfreeze);
            $sql = "update un_account set money_freeze = " . $newfreeze . " where user_id = " . $result['user_id'];
            $return = $this->db->query($sql);
           payLog('autodrawal.txt',"==1081== update money_freeze sql === ".$sql . "excute result ===".print_r($return,true));
            $start_time = microtime(true);
            O('model')->db->query('COMMIT');
            return ['code'=>1,'msg'=>$result['msg']];
        }

    }

    public function dealAccount($data)
    {
        $isFirst = D("accountCash")->getIsFirstCash($data['user_id']);
        if(!$isFirst){
            $firstDrawal = "该用户为首次提现 ";
        }else{
            $firstDrawal = "";
        }
        $withdrawSet = $this->getWithdrawSet($data['user_id']);

        $account = D('account')->getOneCoupon('money,money_freeze',['user_id'=>$data['user_id']]);
        if ($withdrawSet['cnt'] > 0 && $data['extra_fee'] > 0) {
            $start_time = microtime(true);
            $this->db->update($this->table5, array('extra_fee' => 0), array('id' => $data['accountCashId']));
            $end_time = microtime(true);
            lg("run_time_log","9.修改提现表中该提现记录里的额外提现手续费归零信息(un_account_cash)执行时间：".getRunTime($end_time,$start_time));

            //冻结的多余额外提现手续费返回用户可用金额
            $start_time = microtime(true);
            $this->db->update($this->table6, array('money' => $account['money'] + $data['extra_fee'],'money_freeze' => $account['money_freeze'] - $data['extra_fee']), array('user_id' => $data['user_id']));
            $end_time = microtime(true);
            lg("run_time_log","10.冻结的多余额外提现手续费返回用户可用金额信息(un_account)执行时间：".getRunTime($end_time,$start_time));
            $data['money'] = $data['money'] - $data['extra_fee'];
            $data['extra_fee'] = 0;
        }

        $this->withdrawLog($data, $account,  0, 0, 0,$firstDrawal);
        $factory = new withdrawfactory();
        $withdraw = $factory->getInterface($data['nid']);
        $result =  $withdraw->doWithdraw($data);
    }

    public function agreeRecharge($logId, $remark, $admin)
    {
        O('model')->db->query('BEGIN');

        try {
            $start_time = microtime(true);
            $recharge = $this->getRechargeLog($logId);
            $end_time = microtime(true);
            lg("run_time_log","1.获取用户充值订单信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

            if ($recharge['status'] != 0) {
                O('model')->db->query('ROLLBACK');
                return false;
            }
            $chergeMoney = $recharge['money'];

            $start_time = microtime(true);
            $account = $this->getAccount($recharge['user_id']); //行锁
            $end_time = microtime(true);
            lg("run_time_log","2.获取用户资金信息(un_account)执行时间：".getRunTime($end_time,$start_time));

            $finalMoney = $account['money'] + $chergeMoney;

            $start_time = microtime(true);
            $payConfig = $this->getPayConfig($recharge['payment_id']);
            $end_time = microtime(true);
            lg("run_time_log","3.获取充值方式信息(un_payment_config)执行时间：".getRunTime($end_time,$start_time));

            if($payConfig['upper_limit'] - $payConfig['balance'] < $recharge['money']) {
                O('model')->db->query('ROLLBACK');
            
                return -1;
            }

            $start_time = microtime(true);
            $user = $this->m('user', $recharge['user_id']);
            $end_time = microtime(true);
            lg("run_time_log","4.获取用户信息(un_user)执行时间：".getRunTime($end_time,$start_time));

            if ($user['share_id'] != 0) {

                $start_time = microtime(true);
                $rechargeOld = $this->db->getall('select * from un_account_recharge where status=1 and user_id=' . $recharge['user_id']);
                $end_time = microtime(true);
                lg("run_time_log","5.获取分享用户信息(un_user)执行时间：".getRunTime($end_time,$start_time));

                if ($rechargeOld == null || count($rechargeOld) == 0) {

                    $start_time = microtime(true);
                    $shareMoneyConf = $this->m('config', 28)['value'];
                    if(is_numeric($shareMoneyConf)) {
                        $shareMoney = $shareMoneyConf;
                    }else {
                        $shareMoneyData = json_decode($shareMoneyConf, true);
                        $sharerate = 0;         //返现比例
                        foreach ($shareMoneyData as $shareData) {
                            if($shareData['low'] <= $chergeMoney && $shareData['upper'] >= $chergeMoney) {
                                $sharerate = $shareData['rate'];           //返现比列
                                break;
                            }
                            $sharerate = $shareData['rate'];           //返现比列
                        }
                        $shareMoney = ($sharerate * $chergeMoney) / 100;
                    }


                    $end_time = microtime(true);
                    lg("run_time_log","6.获取分享配置信息(un_config)执行时间：".getRunTime($end_time,$start_time));

                    $start_time = microtime(true);
                    $shareAccount = $this->getAccount($user['share_id']);
                    $end_time = microtime(true);
                    lg("run_time_log","7.获取分享用户资金信息(un_account)执行时间：".getRunTime($end_time,$start_time));

                    $start_time = microtime(true);
                    $this->rechargeShareLog($user['share_id'], $shareMoney, $admin['userid'] ? $admin['userid'] : 0, $shareAccount);
                    $end_time = microtime(true);
                    lg("run_time_log","8.添加分享用户资金明细信息(un_account_log)执行时间：".getRunTime($end_time,$start_time));

                    $start_time = microtime(true);

                    $this->db->update($this->table6, array('money' => '+=' . $shareMoney), array('user_id' => $user['share_id']));
                    $end_time = microtime(true);
                    lg("run_time_log","9.更新分享用户资金信息(un_account)执行时间：".getRunTime($end_time,$start_time));
                    //rechargeShareLog($user_id,$money,$adminId,$account){
                }
            }

            //判断是否是首充
            $start_time = microtime(true);
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($recharge['user_id']);
            $end_time = microtime(true);
            lg("run_time_log","10.获取用户是否首充信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

            if(!$isFirstRecharge){
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $start_time = microtime(true);
            $this->rechargeLog($recharge, $admin['userid'] ? $admin['userid'] : 0, $account, $payConfig['balance'] + $chergeMoney,$firstRecharge);
            $end_time = microtime(true);
            lg("run_time_log","11.添加用户资金明细信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

            $start_time = microtime(true);
            $this->db->update($this->table6, array('money' => '+=' . $chergeMoney), array('user_id' => $account['user_id']));
            $end_time = microtime(true);
            lg("run_time_log","12.更新用户资金信息(un_account)执行时间：".getRunTime($end_time,$start_time));
            //        $this->db->update($this->table2, array('status' => 1, 'verify_time' => time()), array('id' => $logId));
        
            //更改提示音状态
            $start_time = microtime(true);
            $sql = "UPDATE `un_music_tips` SET STATUS=1 WHERE record_id={$logId} AND TYPE IN (2,5);";
            $this->db->query($sql);
            $end_time = microtime(true);
            lg("run_time_log","13.更改提示音状态信息(un_music_tips)执行时间：".getRunTime($end_time,$start_time));
        
            lg('music_tips_debug','充值同意::$sql::'.$sql);
        
            if ($remark == null || trim($remark) == '') {
                O('model')->db->query('ROLLBACK');

                throw new Exception('请填写同意原因');
            }
        
            $verify_remark = json_decode($recharge['verify_remark'], true);
            $verify_remark['status'] = 1;
            $adminName = $admin['username'] ? $admin['username'] : 'unknown';
            if ($verify_remark['remark'] == null) {
        
                $verify_remark['remark'] = array(array('admin' => $adminName, 'remark' => $remark));
            } else {
                array_push($verify_remark['remark'], array('admin' => $adminName, 'remark' => $remark));
            }
        
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
            }

            $start_time = microtime(true);
            $this->db->update($this->table2, array('status' => 1,
                'verify_userid' => $admin['userid'] ? $admin['userid'] : 0, '
            verify_time' => time(),
                'verify_remark' => json_encode($verify_remark, JSON_UNESCAPED_UNICODE)), array('id' => $logId));
            $end_time = microtime(true);
            lg("run_time_log","14.更新用户充值订单状态信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

            $start_time = microtime(true);
            $this->ttfl($logId); //天天返利赠送
            $end_time = microtime(true);
            lg("run_time_log","15.天天返利信息(un_account_recharge, un_ttfl_cfg, un_ttfl_log)执行时间：".getRunTime($end_time,$start_time));

            $start_time = microtime(true);
            $ret = $this->db->update($this->table3, array('balance' => $payConfig['balance'] + $chergeMoney), array('id' => $payConfig['id']));
            $end_time = microtime(true);
            lg("run_time_log","16.更新支付配置信息(un_payment_config)执行时间：".getRunTime($end_time,$start_time));

            //线上充值送彩金
            $percent = $this->db->result("select handsel from un_payment_config where id = '".$payConfig['id']."'");

            if($percent>0) {
                $username = $this->db->result("select username from un_user where id ='".$recharge['user_id']."'");
                $handsel = bcdiv(bcmul($recharge['money'],$percent,2),100,2);
                $order_handsel=[];
                $order_handsel["user_id"] = $recharge['user_id'];
                $order_handsel["username"] = $username;
                $order_handsel["order_id"] = $recharge['order_sn'];
                $order_handsel["type"] = $recharge["pay_type"];
                $order_handsel["way"] = $recharge["bank_id"];
                $order_handsel["percent"] = $percent;
                $order_handsel["money"] = $recharge['money'];
                $order_handsel["handsel"] = $handsel;
                $order_handsel["create_time"] = time();

                $auto_offline_handsel = $this->db->result("select value from un_config where nid = 'auto_offline_handsel'");
                if($auto_offline_handsel == 1) {
                    $order_handsel["status"] = 1;
                    D('account')->save(array('money' => '+=' . $handsel), array('user_id' => $recharge['user_id']));
                    $data['user_id'] = $recharge['user_id'];
                    $data['order_num'] = $recharge['order_sn'];
                    $data['type'] = 1071;
                    $data['money'] = $handsel;
                    $data['use_money'] = $finalMoney + $handsel;
                    $data['remark'] = '用户id为:' . $recharge['user_id'] . ' 充值送彩金:' . $handsel . '成功';
                    $data['verify'] = $admin["userid"];
                    $data['addtime'] = time();
                    $data['addip'] = ip();
                    $rt = $this->aadAccountLog($data);
                }else $order_handsel["status"] = 0;
                $this->db->insert('un_offline_handsel',$order_handsel);
            }




            O('model')->db->query('COMMIT');
            //充值加荣誉积分
            $start_time = microtime(true);
            //set_honor_score($recharge['user_id']);
            exchangeIntegral($recharge['money'], $recharge['user_id'], 1);
            $end_time = microtime(true);
            lg("run_time_log","17.更新荣誉积分信息(un_user_amount_total)执行时间：".getRunTime($end_time,$start_time));

            return $ret;
        } catch (Exception $e) {
            O('model')->db->query('ROLLBACK');
        
            throw $e;
        }
    }

    private function getRechargeLog($logId) {
        return $this->db->getone('select * from ' . $this->table2 . ' where id = ' . $logId);
    }

    private function getPayConfig($payConfigId) {
        return $this->db->getone('select * from ' . $this->table3 . ' where id = ' . $payConfigId);
    }

    public function getModel($table, $id) {
        return $this->db->getone('select * from ' . $table . ' where id = ' . $id);
    }

    public function m($table, $id) {
        return $this->db->getone('select * from un_' . $table . ' where id = ' . $id);
    }

    private function getAccount($uid) {
        if(!empty(C('db_port'))) { //使用mycat时 查主库数据
            $sql = '/*#mycat:db_type=master*/ select * from ' . $this->table6 . ' where user_id = ' . $uid.' LIMIT 1 for update';
        }else{
            $sql = 'select * from ' . $this->table6 . ' where user_id = ' . $uid.' LIMIT 1 for update';
        }
        return $this->db->getone($sql);
    }

    public function qall($sql) {
        return $this->db->getall($sql);
    }

    public function q($sql) {
        return $this->db->getone($sql);
    }

    public function refuseWithDrawl($logId, $remark, $admin,$paymentId) {
        O('model')->db->query('BEGIN');
        try {
            $now_admin=$this->db->getone('select userid from un_admin where disabled=0 and userid='.$admin['userid']);
            if(!$now_admin){
                throw new Exception('您的账号异常，无法进行审核操作');
            }

            if ($remark == null || trim($remark) == '') {
                throw new Exception('请填写驳回原因');
            }
            $cashLog = $this->getModel($this->table5, $logId);

            //更改提示音状态
            $sql = "UPDATE `un_music_tips` SET STATUS=1 WHERE record_id={$logId} AND TYPE IN (1,6);";
            $this->db->query($sql);
            lg('music_tips_debug','提现驳回::$sql::'.$sql);

            if ($remark == null || trim($remark) == '') {
                throw new Exception(-1, '请填写同意原因');
            }

            $oldchar=array(" ","　","\t","\n","\r");
            $newchar=array("","","","","");
            $remark = str_replace($oldchar,$newchar,$remark);           //去除空格

            $adminName = '';
            $verify_remark = json_decode($cashLog['verifyremark'], true);
            //2017-5-31修改此处   alan
            if ($verify_remark['status'] == 0 || $verify_remark['status'] == 3 || $verify_remark['status'] == 6 || $verify_remark['status'] == 5) {
                $adminName = '客服:' . ($admin['username'] ? $admin['username'] : 'unknown');
                $verify_remark['status'] = 2;
                if ($verify_remark['remark'] == null) {
                    $verify_remark['remark'] = array(array('admin' => $adminName, 'remark' => $remark));
                } else {
                    array_push($verify_remark['remark'], array('admin' => $adminName, 'remark' => $remark));
                }

                $account = $this->getAccount($cashLog['user_id']);
                $this->refuseWithdrawLog($cashLog, $admin['userid'] ? $admin['userid'] : 0, $account);
                $this->db->update($this->table6, array('money' => $account['money'] + $cashLog['money'] + $cashLog['extra_fee'], 'money_freeze' => $account['money_freeze'] - $cashLog['money'] - $cashLog['extra_fee']), array('user_id' => $cashLog['user_id']));
                
                //资金不足驳回时，$paymentId=NUll,说明当前后台绑定的提现卡资金余额不足本次提现操作
                if (!empty($paymentId)) {
                    $payment = $this->getModel($this->table3, $paymentId);
                    $this->db->update($this->table3, array('balance' => $payment['balance'] + $cashLog['money'], 'money_freeze' => $payment['money_freeze'] - $cashLog['money']), array('id' => $paymentId));
                }
                $ret = $this->db->update($this->table5, array(
                    'status' => 2,
                    'payment_id' => null,
                    'verify_user_id' => $admin['userid'] ? $admin['userid'] : 0,
                    'verifytime' => time(),
                    'verifyremark' => json_encode($verify_remark, JSON_UNESCAPED_UNICODE)
                ), array('id' => $logId));
                O('model')->db->query('COMMIT');
                return $ret;
            }else{
                O('model')->db->query('ROLLBACK');
                return -1;
            }
//             else if ($verify_remark['status'] == 3) {
//                 $payment = $this->m('payment_config', $cashLog['payment_id']);
//                 $this->db->update($this->table3, array('balance' => $payment['balance'] + $cashLog['money'], 'money_freeze' => $payment['money_freeze'] - $cashLog['money']), array('id' => $cashLog['payment_id']));
//                 $adminName = '出纳:' . ($admin['username'] ? $admin['username'] : 'unknown');
//             }

            
        } catch (Exception $e) {
            O('model')->db->query('ROLLBACK');
            throw $e;
        }
    }

    public function agreeWithdraw($logId, $paymentId, $remark, $fee, $admin) {
        lg("run_time_log","提现审核开始");
        O('model')->db->query('BEGIN');
        try {
            $now_admin=$this->db->getone('select userid from un_admin where disabled=0 and userid='.$admin['userid']);
            if(!$now_admin){
                return ['code' => 0, 'msg' => '您的账号异常，无法进行审核操作'];
            }

            $start_time = microtime(true);
            $cashLog = $this->getModel($this->table5, $logId);
            $end_time = microtime(true);
            lg("run_time_log","1.获取提现订单信息(un_account_cash)执行时间：".getRunTime($end_time,$start_time));

            if ($cashLog['status'] != 0) {
                return ['code' => 0, 'msg' => '订单已处理'];
            }
            $verify_remark = json_decode($cashLog['verifyremark'], true);
            if ($verify_remark['status'] == 3) {
                $paymentId = $cashLog['payment_id'];
            }

            $start_time = microtime(true);
            $payment = $this->getModel($this->table3, $paymentId);
            $end_time = microtime(true);
            lg("run_time_log","2.获取提现银行信息(_payment_config)执行时间：".getRunTime($end_time,$start_time));

            $start_time = microtime(true);
            $account = $this->getAccount($cashLog['user_id']);
            $end_time = microtime(true);
            lg("run_time_log","3.获取用户资金信息(un_account)执行时间：".getRunTime($end_time,$start_time));

//            if ($account['money'] < $cashLog['money']) {
//                throw new Exception('用户余额不足');
//            }
            if ($verify_remark['status'] == 0 && $payment['balance'] < $cashLog['money']) {
                return ['code' => 0, 'msg' => '银行卡余额不足'];
            }
            if ($remark == null || $remark == '') {
                $remark = '同意';
            }

            //更改提示音状态
            $start_time = microtime(true);
            $sql = "UPDATE `un_music_tips` SET STATUS=1 WHERE record_id={$logId} AND TYPE IN (1,6)";
            $this->db->query($sql);
            $end_time = microtime(true);
            lg("run_time_log","4.更新提示音信息(un_music_tips)执行时间：".getRunTime($end_time,$start_time));

            lg('music_tips_debug','提现同意::$sql::'.$sql);
            $verify_remark['status']!=0?:$verify_remark['status']=3;
            switch ($verify_remark['status']) {
                case 0:
                    $verify_remark['status'] = 3;
                    $adminName = '客服:' . ($admin['username'] ? $admin['username'] : 'unkown');
                    if ($verify_remark['remark'] == null) {
                        $verify_remark['remark'] = array(array('admin' => $adminName, 'remark' => $remark));
                    } else {
                        array_push($verify_remark['remark'], array('admin' => $adminName, 'remark' => $remark));
                    }
                    $start_time = microtime(true);
                    $this->db->update($this->table3, array('balance' => $payment['balance'] - $cashLog['money'], 'money_freeze' => $cashLog['money']), array('id' => $paymentId));
                    $end_time = microtime(true);
                    lg("run_time_log","5.更新支付方式信息(un_payment_config)执行时间：".getRunTime($end_time,$start_time));

                    $start_time = microtime(true);
                    $ret = $this->db->update($this->table5, array(
                        'payment_id' => $paymentId,
                        'verify_user_id' => $admin['userid'] ? $admin['userid'] : 0,
                        'verifytime' => time(),
                        'verifyremark' => json_encode($verify_remark, JSON_UNESCAPED_UNICODE)
                            ), array('id' => $logId));
                    $end_time = microtime(true);
                    lg("run_time_log","6.更新提现订单信息信息(un_account_cash)执行时间：".getRunTime($end_time,$start_time));

                    O('model')->db->query('COMMIT');
                    
                    return ['code' => $ret, 'msg' => '处理成功'];
                case 1:
                    return ['code' => 0, 'msg' => '处理失败，请稍后再试！'];
                case 2:
                    return ['code' => 0, 'msg' => '处理失败，请稍后再试！'];
                case 3:
                    $verify_remark['status'] = 1;
                    $adminName = '客服:' . ($admin['username'] ? $admin['username'] : 'unknown');
                    if ($verify_remark['remark'] == null) {
                        $verify_remark['remark'] = array(array('admin' => $adminName, 'remark' => $remark));
                    } else {
                        array_push($verify_remark['remark'], array('admin' => $adminName, 'remark' => $remark));
                    }

                    $start_time = microtime(true);
                    $isFirstCash = D("accountCash")->getIsFirstCash($cashLog['user_id']);
                    $end_time = microtime(true);
                    lg("run_time_log","7.获取用户是否为首提信息(un_account_cash)执行时间：".getRunTime($end_time,$start_time));

                    if(!$isFirstCash){
                        $verify_remark['FirstCash'] = 1;
                        $firstCash = "该用户为首次提现 ";
                    }else{
                        $firstCash = "";
                    }
                    
                    //获取提现次数和额外提现手续费
                    $start_time = microtime(true);
                    $withdrawSet = $this->getWithdrawSet($cashLog['user_id']);
                    $end_time = microtime(true);
                    lg("run_time_log","8.获取提现次数和额外提现手续费信息(un_account_cash)执行时间：".getRunTime($end_time,$start_time));

                    if ($withdrawSet['cont'] > 0) {
                        //如果处理订单时，原本需要额外手续费提现，现在（之前有取消提现订单）少于免费提现次数了，需要处理额外手续费的归还操作
                        if ($cashLog['extra_fee'] > 0) {
                            //修改提现表中该提现记录里的额外提现手续费归零
                            $start_time = microtime(true);
                            $this->db->update($this->table5, array('extra_fee' => 0), array('id' => $logId));
                            $end_time = microtime(true);
                            lg("run_time_log","9.修改提现表中该提现记录里的额外提现手续费归零信息(un_account_cash)执行时间：".getRunTime($end_time,$start_time));

                            //冻结的多余额外提现手续费返回用户可用金额
                            $start_time = microtime(true);
                            $this->db->update($this->table6, array('money' => $account['money'] + $cashLog['extra_fee'],'money_freeze' => $account['money_freeze'] - $cashLog['extra_fee']), array('user_id' => $cashLog['user_id']));
                            $end_time = microtime(true);
                            lg("run_time_log","10.冻结的多余额外提现手续费返回用户可用金额信息(un_account)执行时间：".getRunTime($end_time,$start_time));

                        }
                        $cashLog['extra_fee'] = 0;
                    }
                    $start_time = microtime(true);
                    $this->withdrawLog($cashLog, $account, $admin['userid'] ? $admin['userid'] : 0, $fee, $payment['balance'],$firstCash);
                    $end_time = microtime(true);
                    lg("run_time_log","11.添加资金明细信息(un_account_log)执行时间：".getRunTime($end_time,$start_time));

                    $start_time = microtime(true);
                    $this->db->update($this->table5, array('fee' => $fee, 'status' => 1,
                        'verify_user_id' => $admin['userid'] ? $admin['userid'] : 0,
                        'verifytime' => time(),
                        'verifyremark' => json_encode($verify_remark, JSON_UNESCAPED_UNICODE)
                            ), array('id' => $logId));
                    $end_time = microtime(true);
                    lg("run_time_log","12.更新提现订单信息(un_account_cash)执行时间：".getRunTime($end_time,$start_time));

                    $start_time = microtime(true);
                    $this->db->update($this->table6, array('money_freeze' => $account['money_freeze'] - $cashLog['money'] - $cashLog['extra_fee']), array('user_id' => $cashLog['user_id']));
                    $end_time = microtime(true);
                    lg("run_time_log","13.更新用户资金信息(un_account)执行时间：".getRunTime($end_time,$start_time));

                    $start_time = microtime(true);
                    $ret = $this->db->update($this->table3, array('money_freeze' => $payment['money_freeze'] - $cashLog['money']), array('id' => $paymentId));
                    $end_time = microtime(true);
                    lg("run_time_log","14.更新支付配置信息(un_payment_config)执行时间：".getRunTime($end_time,$start_time));

                    $this->db->update("un_user",['bet_amount'=>0],["id"=>$cashLog['user_id']]);
                    O('model')->db->query('COMMIT');
                    return ['code' => $ret, 'msg' => '处理成功'];
            }
        } catch (Exception $e) {
            O('model')->db->query('ROLLBACK');
            //throw $e;
            return ['code' => 0, 'msg' => '处理失败，请稍后再试！'];
        }
    }

    public function getCanUseRechargePayConfig($logId) {
        $record = $this->db->getone('select * from ');
    }

    public function getCanUseCashPayConfig($logId) {
        $y = date("Y");
        $m = date("m");
        $d = date("d");
        $day_start = mktime(0, 0, 0, $m, $d, $y);
        $day_end = mktime(23, 59, 59, $m, $d, $y);


//        select a.id,b.money,c.money from un_payment_config as a left join (select payment_id, sum(money) as money from un_account_cash where verifytime between 1 and 1480760581 group by payment_id) as b on a.id = b.payment_id left join (select payment_id, sum(money) as money from un_account_recharge where verify_time between 1 and 1481209508697+100000 group by payment_id) as c on a.id = c.payment_id where (if(isnull(c.money),0,c.money)-if(isnull(b.money),0,b.money))+100 between a.lower_limit and a.upper_limit;

        $cash = $this->getModel($this->table5, $logId);
        $cashMoney = $cash['money'];
        $userGroupId = $this->db->getone('select un_user_group.id from un_user left join un_user_group on un_user.group_id = un_user_group.id where un_user.id=' . $cash['user_id'])['id'];

        // $cashMoney=10;
        $charge = $this->db->getall('select a.id,a.name,a.config,a.lower_limit,a.upper_limit,a.balance,a.fee,b.money as withdraw,c.money as recharge,un_dictionary.name as bankname from un_payment_config as a 
                                     inner join un_payment_group as d on a.group_id = d.id and d.purpose=1 and find_in_set(' . $userGroupId . ',d.user_group)
                                     left join un_dictionary on a.bank_id = un_dictionary.id
                                     left join (select payment_id, sum(money) as money from un_account_cash 
                                     where status=1 and verifytime between ' . $day_start . ' and ' . $day_end . ' group by payment_id) 
                                     as b on a.id = b.payment_id left join 
                                     (select payment_id, sum(money) as money from un_account_recharge
                                      where verify_time between ' . $day_start . ' and ' . $day_end . ' group by payment_id) as c on a.id = c.payment_id 
                                      where a.balance>=' . $cashMoney . ' and (if(isnull(b.money),0,b.money)+' . $cashMoney . '-if(isnull(c.money),0,c.money)) <= a.upper_limit');


//        $charge = $this->db->getall('
//                                     select a.id,a.name,a.config,a.lower_limit,a.upper_limit,a.balance,b.money as withdraw,c.money as recharge,un_dictionary.name as bankname from un_payment_config as a
//                                     inner join un_payment_group as d on a.group_id = d.id and d.purpose=1 and d.user_group='.$userGroupId.'
//                                     left join un_dictionary on a.bank_id = un_dictionary.id
//                                     left join (select payment_id, sum(money) as money from un_account_cash
//                                     where status=1 and verifytime between ' . $day_start . ' and ' . $day_end . ' group by payment_id)
//                                     as b on a.id = b.payment_id left join
//                                     (select payment_id, sum(money) as money from un_account_recharge
//                                      where verify_time between ' . $day_start . ' and ' . $day_end . ' group by payment_id) as c on a.id = c.payment_id
//                                      where a.balance>=' . $cashMoney . ' and (if(isnull(b.money),0,b.money)+' . $cashMoney . '-if(isnull(c.money),0,c.money)) <= a.upper_limit');
//where a.balance>=' . $cashMoney . ' and (if(isnull(b.money),0,b.money)+' . $cashMoney . '-if(isnull(c.money),0,c.money)) between a.lower_limit and a.upper_limit
        //where (if(isnull(b.money),0,b.money)+'.$cashMoney.'-if(isnull(c.money),0,c.money)) between a.lower_limit and a.upper_limit

        return $charge;

//        $charge = $this->db->getall('select sum(money) as money from ' . $this->table2 . ' where verifytime between ' . $day_start . ' and ' . $day_end)['money'];
    }

    public function getCashPayConfig($paymentId) {

        // $cashMoney=10;
        $charge = $this->db->getone('
                                     select a.id,a.name,a.config,a.lower_limit,a.upper_limit,a.fee,a.balance ,un_dictionary.name as bankname from un_payment_config as a
                                     inner join un_payment_group as b on a.group_id = b.id 
                                     left join un_dictionary on a.bank_id = un_dictionary.id
                                     where a.id=' . $paymentId);
        return $charge;

//        $charge = $this->db->getall('select sum(money) as money from ' . $this->table2 . ' where verifytime between ' . $day_start . ' and ' . $day_end)['money'];
    }

    public function getRechargeStatusStr($status) {
        switch ($status) {
            case 0:
                return '未处理';
            case 1:
                return '完成';
//            case 2:
//                return '未通过';
//            case 3:
//                return '客服不通过';
//            case 4:
//                return '财务经理不通过';
            default:
                return '未通过';
        }
    }

    public function getWithdrawStatusStr($status) {

        //0未处理，1完成，2未通过，3客服不通过，4财务经理不通过，5出纳不通过
        switch ($status) {
            case 0:
                return '待客服处理';
            case 1:
                return '完成';
            case 2:
                return '未通过';
            case 4:
                return '代付免审核（成功）';
            case 8:
                return '代付处理中';
            default:
                return '待出纳处理';
        }
    }

    //第0次返利赠送金额
    public function zeroTTfl($id)
    {
        $rt = $this->db->getone("select * from un_ttfl_cfg where main = 0 and cz_cnt = 0");
        if (empty($rt)) { //没有设置0次返利金额
            return;
        }
        $now = time();
        $rts = $this->db->getone("select * from un_ttfl_cfg where main = 1 and start_time < {$now} and end_time > {$now}");
        if (empty($rts)) { //当前充值时间不在天天返利活动日期内
            return;
        }

        $info = $this->db->getone("select * from " . $this->table2 . " where id = {$id}"); //充值表的一条记录(ID)
        $mainCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 1"); //天天返利活动主条件
        $max_money = $mainCfg['max_money']; //返利上限--需要用累计的
        $low_money = $mainCfg['low_money']; //返利下限

        $stime = $mainCfg['start_time'];
        $etime = $mainCfg['end_time'];

        $branchCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 0 and cz_cnt = 0"); //天天返利配置条件
        if (empty($branchCfg)) {
            log_to_mysql('未配置天天返利条件--sql:'.$this->db->getLastSql(), 'finance_zeroTTfl_user_id_'.$info['user_id']);
            return;
        }

        //返利金额
        $rtMoney = 0;
        $t1 = date('Y-m-d H:i:s', $stime);
        $t2 = date('Y-m-d H:i:s', $etime);
        $rtNote = "天天返利活动时间：{$t1}-{$t2}  金额上限：{$max_money}  金额下限：{$low_money};<br>"; //返利备注
        $rtNote .= "满足第0充返利条件  会员本次充值金额{$info['money']}元;<br/>";

        log_to_mysql($rtNote, 'finance_zeroTTfl_user_id_'.$info['user_id'].'_$rtNote_1');

        //充值返利
        if ($branchCfg['cz_type'] == 1) {
            $rtNote .= "按充值返利->";
            //①按比例返利
            if ($branchCfg['fl_type'] == 1) {
                $time = floor($info['money'] / $branchCfg['cz_money']);
                $rtNote .= "比例返利(每充值{$branchCfg['cz_money']}返利{$branchCfg['rt_money']}元);<br>";
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $time * $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $branchCfg['cz_money'] * ($branchCfg['rt_money'] / 100 ) * $time;
                }
            }

            //②按范围返利
            if ($branchCfg['fl_type'] == 2) {
                $range = json_decode($branchCfg['range'], true);
                foreach ($range as $value) {
                    //满足范围条件
                    if ($value['s_money'] < $info['money'] && $value['e_money'] >= $info['money']) {
                        $rtNote .= "范围返利(充值{$value['s_money']}<X<={$value['e_money']}，返利{$value['rt_money']}元);<br>";
                        if ($value['rt_type'] == 1) { //直接返利
                            $rtMoney = $value['rt_money'];
                        } else { //百分比返利
                            $rtMoney = $info['money'] * $value['rt_money'] / 100;
                        }
                        break;
                    }
                }
            }
        }

        //充值次数返利
        elseif ($branchCfg['cz_type'] == 2) {
            $historyReg = $this->ttflHistoryReg($info['user_id']); //历史成功充值次数
            $rtNote .= "按充值次数返利->历史累计充值{$historyReg}次，返利{$branchCfg['rt_money']}元;<br/>";
            if ($historyReg >= $branchCfg['cz_money']) { //满足条件
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $info['money'] * ($branchCfg['rt_money'] / 100 );
                }
            }
        }

        //直属充值返利
        elseif ($branchCfg['cz_type'] == 3) {
            $sonMoney = $this->ttflSumSon($info['user_id'], $stime, $etime);
            $rtNote .= "按直属充值返利->直属充值{$sonMoney}元;<br/>";

            //①按比例返利
            if ($branchCfg['fl_type'] == 1) {
                $time = floor($sonMoney / $branchCfg['cz_money']);
                $rtNote .= "->比例返利(每充值{$branchCfg['cz_money']}返利{$branchCfg['rt_money']}元);<br>";
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $time * $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $branchCfg['cz_money'] * ($branchCfg['rt_money'] / 100 ) * $time;
                }
            }

            //②按范围返利
            if ($branchCfg['fl_type'] == 2) {
                $range = json_decode($branchCfg['range'], true);

                foreach ($range as $value) {
                    //满足范围条件
                    if ($value['s_money'] < $sonMoney && $value['e_money'] >= $sonMoney) {
                        $rtNote .= "->范围返利(充值{$value['s_money']}<X<={$value['e_money']}，返利{$value['rt_money']}元);<br>";
                        if ($value['rt_type'] == 1) { //直接返利
                            $rtMoney = $value['rt_money'];
                        } else { //百分比返利
                            $rtMoney = $sonMoney * $value['rt_money'] / 100;
                        }
                        break;
                    }
                }
            }
        }

        //直属充值次数返利
        elseif ($branchCfg['cz_type'] == 4) {
            $historyReg = $this->ttflCntSon($info['user_id']); //历史成功充值次数
            $rtNote .= "按直属充值次数返利->直属历史累计充值{$historyReg}次，返利{$branchCfg['rt_money']}元;<br/>";
            if ($historyReg >= $branchCfg['cz_money']) { //满足条件
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $info['money'] * ($branchCfg['rt_money'] / 100 );
                }
            }
        } else {
            return;
        }

        if ($rtMoney < $low_money) {//不能低于最小返回金额
            $rtNote .= "返利金额{$rtMoney}元小于最小返利金额{$low_money}元，调整返利为{$low_money}元;<br/>";
            $rtMoney = $low_money;
        }
        $flSum = $this->flSum($info['user_id'], $stime, $etime); //会员已经返利的金额
        $rtNote .= "会员活动期间历史返利金额{$flSum}元;";
        if ($rtMoney + $flSum > $max_money) { //超过最大时候  == 取差值
            $rtMoney = $max_money - $flSum;
            $rtNote .= "会员返利金额将超出返利设置最大金额条件{$max_money}元，调整返利为{$rtMoney}元;<br/>";
        }

        log_to_mysql($rtNote, 'finance_zeroTTfl_user_id_'.$info['user_id'].'_$rtNote_2');

        if ($rtMoney == 0) { //不返利  退出
            log_to_mysql('未达到返利条件或返利金额为0!$rtNote:'.$rtNote, 'finance_zeroTTfl_user_id_'.$info['user_id']);
            return;
        } else {
            $rtNote .= "会员最终返利金额{$rtMoney}元";
        }
        $rtId = $info['user_id'];
        $addtime = time();

        $ttflLog = array(
            "cz_money" => $info['money'],
            "get_money" => $rtMoney,
            "order_sum" => $info['order_sn'],
            "user_id" => $rtId,
            "remark" => $rtNote,
            "addtime" => $addtime
        );
        $this->db->insert($this->tableTTflLog, $ttflLog);
    }

    //天天返利赠送金额
    public function ttfl($id) {

        $this->zeroTTfl($id); //存在第0次天天返利的情况
        
        $info = $this->db->getone("select * from " . $this->table2 . " where id = $id"); //充值表的一条记录(ID)
        $mainCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 1"); //天天返利活动主条件
        $max_money = $mainCfg['max_money']; //返利上限--需要用累计的
        $low_money = $mainCfg['low_money']; //返利下限
        
        $stime = $mainCfg['start_time'];
        $etime = $mainCfg['end_time'];
        if($etime < time() || $stime > time()){
            return;
        }
        
        $chargeCntObj = $this->db->getall("select count(*) as cnt,sum(money) as sums from " . $this->table2 . " where user_id = {$info['user_id']} and addtime > $stime and addtime < $etime and status = 1");
        
        $chargeCnt = $chargeCntObj[0]['cnt']; //用户在天天返利条件内的充值次数
        $chargeSum = $chargeCntObj[0]['sums']; //用户在天天返利条件内的充值金额
        $branchCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 0 and cz_cnt = {$chargeCnt}"); //天天返利配置条件
        if (empty($branchCfg)) {
            log_to_mysql('未配置天天返利条件--sql:'.$this->db->getLastSql(), 'finance_ttfl_user_'.$info['user_id']);
            return;
        }
        log_to_mysql($branchCfg, 'finance_ttfl_user_'.$info['user_id'].'_$branchCfg');
        
        //返利金额
        $rtMoney = 0;
        $t1 = date('Y-m-d H:i:s', $stime);
        $t2 = date('Y-m-d H:i:s', $etime);
        $rtNote = "天天返利活动时间：{$t1}-{$t2}  金额上限：{$max_money}  金额下限：{$low_money};<br>"; //返利备注
        $rtNote .= "满足第" . $branchCfg['cz_cnt'] . "充返利条件  会员本次充值金额{$info['money']}元;<br/>";

        log_to_mysql($rtNote, 'finance_ttfl_user_'.$info['user_id'].'_$rtNote_1');

        //充值返利
        if ($branchCfg['cz_type'] == 1) {
            $rtNote .= "按充值返利->";
            //①按比例返利
            if ($branchCfg['fl_type'] == 1) {
                $time = floor($info['money'] / $branchCfg['cz_money']);
                $rtNote .= "比例返利(每充值{$branchCfg['cz_money']}返利{$branchCfg['rt_money']}元);<br>";
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $time * $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $branchCfg['cz_money'] * ($branchCfg['rt_money'] / 100 ) * $time;
                }
            }
        
            //②按范围返利
            if ($branchCfg['fl_type'] == 2) {
                $range = json_decode($branchCfg['range'], true);
                foreach ($range as $value) {
                    //满足范围条件
                    if ($value['s_money'] < $info['money'] && $value['e_money'] >= $info['money']) {
                        $rtNote .= "范围返利(充值{$value['s_money']}<X<={$value['e_money']}，返利{$value['rt_money']}元);<br>";
                        if ($value['rt_type'] == 1) { //直接返利
                            $rtMoney = $value['rt_money'];
                        } else { //百分比返利
                            $rtMoney = $info['money'] * $value['rt_money'] / 100;
                        }
                        break;
                    }
                }
            }
        }
        
        //充值次数返利
        if ($branchCfg['cz_type'] == 2) {
            $historyReg = $this->ttflHistoryReg($info['user_id']); //历史成功充值次数
            $rtNote .= "按充值次数返利->历史累计充值{$historyReg}次，返利{$branchCfg['rt_money']}元;<br/>";
            if ($historyReg >= $branchCfg['cz_money']) { //满足条件
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $info['money'] * ($branchCfg['rt_money'] / 100 );
                }
            }
        }
        
        //直属充值返利
        if ($branchCfg['cz_type'] == 3) {
            $sonMoney = $this->ttflSumSon($info['user_id'], $stime, $etime);
            $rtNote .= "按直属充值返利->直属充值{$sonMoney}元;<br/>";
        
            //①按比例返利
            if ($branchCfg['fl_type'] == 1) {
                $time = floor($sonMoney / $branchCfg['cz_money']);
                $rtNote .= "->比例返利(每充值{$branchCfg['cz_money']}返利{$branchCfg['rt_money']}元);<br>";
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $time * $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $branchCfg['cz_money'] * ($branchCfg['rt_money'] / 100 ) * $time;
                }
            }
        
            //②按范围返利
            if ($branchCfg['fl_type'] == 2) {
                $range = json_decode($branchCfg['range'], true);
        
                foreach ($range as $value) {
                    //满足范围条件
                    if ($value['s_money'] < $sonMoney && $value['e_money'] >= $sonMoney) {
                        $rtNote .= "->范围返利(充值{$value['s_money']}<X<={$value['e_money']}，返利{$value['rt_money']}元);<br>";
                        if ($value['rt_type'] == 1) { //直接返利
                            $rtMoney = $value['rt_money'];
                        } else { //百分比返利
                            $rtMoney = $sonMoney * $value['rt_money'] / 100;
                        }
                        break;
                    }
                }
            }
        }
        
        //直属充值次数返利
        if ($branchCfg['cz_type'] == 4) {
        
            $historyReg = $this->ttflCntSon($info['user_id']); //历史成功充值次数
        
            $rtNote .= "按直属充值次数返利->直属历史累计充值{$historyReg}次，返利{$branchCfg['rt_money']}元;<br/>";
            if ($historyReg >= $branchCfg['cz_money']) { //满足条件
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $info['money'] * ($branchCfg['rt_money'] / 100 );
                }
            }
        }
        
        if ($rtMoney < $low_money) {//不能低于最小返回金额
            $rtNote .= "返利金额{$rtMoney}元小于最小返利金额{$low_money}元，调整返利为{$low_money}元;<br/>";
            $rtMoney = $low_money;
        }
        $flSum = $this->flSum($info['user_id'], $stime, $etime); //会员已经返利的金额
        $rtNote .= "会员活动期间历史返利金额{$flSum}元;";
        if ($rtMoney + $flSum > $max_money) { //超过最大时候  == 取差值
            $rtMoney = $max_money - $flSum;
            $rtNote .= "会员返利金额将超出返利设置最大金额条件{$max_money}元，调整返利为{$rtMoney}元;<br/>";
        }

        log_to_mysql($rtNote, 'finance_ttfl_user_'.$info['user_id'].'_$rtNote_2');

        if ($rtMoney == 0) { //不返利  退出
            log_to_mysql('未达到返利条件或返利金额为0!$rtNote:'.$rtNote, 'finance_ttfl_user_'.$info['user_id']);
            return;
        } else {
            $rtNote .= "会员最终返利金额{$rtMoney}元";
        }
        $rtId = $info['user_id'];
        $addtime = time();
        
        $ttflLog = array(
            "cz_money" => $info['money'],
            "get_money" => $rtMoney,
            "order_sum" => $info['order_sn'],
            "user_id" => $rtId,
            "remark" => $rtNote,
            "addtime" => $addtime
        );
        
        $this->db->insert($this->tableTTflLog, $ttflLog);
    }

    //会员历史累计充值次数
    public function ttflHistoryReg($user_id) {
        $rt = $this->db->getone("select count(*) as cnt from un_account_recharge where user_id = {$user_id} and status = 1");
        return $rt['cnt'];
    }

    //会员已经返利金额
    public function flSum($uId, $stime, $etime) {
        $rt = $this->db->getone("select sum(get_money) as sum from un_ttfl_log where user_id = {$uId} and addtime >= {$stime} and addtime <= {$etime}");
        return empty($rt['sum']) ? 0 : $rt['sum'];
    }

    //直属充值金额
    public function ttflSumSon($uId, $stime, $etime) {
        $rt = $this->db->getone("select sum(money) as sum from un_account_recharge where addtime > $stime and addtime < $etime and status = 1 and user_id in (select id from un_user where parent_id = $uId)");
        return $rt['sum'];
    }

    //直属充值次数
    public function ttflCntSon($uId) {
        $rt = $this->db->getone("select count(id) as cnt from un_account_recharge where status = 1 and user_id in (select id from un_user where parent_id = $uId)");
        return $rt['cnt'];
    }

    private function rechargeLog($charge, $adminId, $account, $bankCardFinalBalance,$firstRecharge)
    {
        //判断订单记录是否已经存在
        $sql = "SELECT `id` FROM `un_account_log` WHERE `order_num` = '{$charge['order_sn']}'";
        $orderId = $this->db->result($sql);
        if (!empty($orderId)) {
            return false;
        }

        $data['user_id'] = $charge['user_id'];
        $data['order_num'] = $charge['order_sn'];
        $data['type'] = 10;
        $data['money'] = $charge['money'];
        $data['use_money'] = $account['money'] + $charge['money'];
        //用户id为: 17 申请提现: 500 到绑定银行id为: 12
        $data['remark'] = $firstRecharge.'用户id为:' . $charge['user_id'] . ' 申请充值:' . $charge['money'] . '到绑定客服银行id:' . $charge['payment_id'];
        $data['verify'] = $adminId;
        $data['addtime'] = time();
        $data['addip'] = ip();
        $data['admin_money'] = $bankCardFinalBalance;
//        $rt = $this->db->insert($this->tableAccountLog, $data);
        $rt = $this->aadAccountLog($data);
        return $rt;
    }

    private function rechargeShareLog($user_id, $money, $adminId, $account) {
        $data['user_id'] = $user_id;
        $data['order_num'] = "JL" . date("YmdHis") . rand(100, 999);
        $data['type'] = 66;
        $data['money'] = $money;
        $data['use_money'] = $account['money'] + $money;
        //用户id为: 17 申请提现: 500 到绑定银行id为: 12
        $data['remark'] = '用户id为:' . $user_id . ' 分享奖励:' . $money;
        $data['verify'] = $adminId;
        $data['addtime'] = time();
        $data['addip'] = ip();
        $rt = $this->aadAccountLog($data);
        return $rt;
    }

    private function refuseWithdrawLog($withdraw, $adminId, $account) {
        $data['user_id'] = $withdraw['user_id'];
        $data['order_num'] = $withdraw['order_sn'];
        $data['type'] = 51;
        $data['money'] = $withdraw['money'];
        $data['use_money'] = $account['money'] + $withdraw['money'] + $withdraw['extra_fee'];       //$withdraw['extra_fee'] 超出免费提现次数的提现手续费
        //用户id为: 17 申请提现: 500 到绑定银行id为: 12
        $data['remark'] = '用户id为:' . $withdraw['user_id'] . ' 申请提现:' . $withdraw['money'] . '失败';
        $data['verify'] = $adminId;
        $data['addtime'] = time();
        $data['addip'] = ip();
        $rt = $this->aadAccountLog($data);
        return $rt;
    }

    private function withdrawLog($withdraw, $account, $adminId, $fee, $bankCardFinalBalance,$firstCash) {
        $data['user_id'] = $withdraw['user_id'];
        $data['order_num'] = $withdraw['order_sn'];
        $data['type'] = 11;
        $data['money'] = $withdraw['money'];
        $data['use_money'] = $account['money'];
        //用户id为: 17 申请提现: 500 到绑定银行id为: 12
        $data['admin_money'] = $bankCardFinalBalance;
        $data['remark'] = $firstCash.'用户id为:' . $withdraw['user_id'] . ' 申请提现:' . $withdraw['money'] . '到绑定用户银行id:' . $withdraw['bank_id'];
        $data['verify'] = $adminId;
        $data['addtime'] = time();
        $data['addip'] = ip();
        $rt = $this->aadAccountLog($data);
        //银行卡手续费
        if ($fee != 0) {
            $data['user_id'] = $withdraw['user_id'];
            $data['order_num'] = $withdraw['order_sn'];
            $data['type'] = 48;
            $data['money'] = $fee;
            $data['use_money'] = $account['money'];
            //用户id为: 17 申请提现: 500 到绑定银行id为: 12
            $data['remark'] = '用户id为:' . $withdraw['user_id'] . ' 申请提现:' . $withdraw['money'] . '到绑定用户银行id:' . $withdraw['bank_id'] . '的银行手续费:' . $fee . '元';
            $data['verify'] = $adminId;
            $data['addtime'] = time();
            $data['addip'] = ip();
//            $rt = $this->db->insert($this->tableAccountLog, $data);
            $rt = $this->aadAccountLog($data);
        }
        
        //超出免费提现次数的提现手续费
        if ($withdraw['extra_fee'] > 0) {
            $logData = array(
                'user_id'   => $withdraw['user_id'],
                'order_num' => $withdraw['order_sn'],
                'type'      => 154,
                'money'     => $withdraw['extra_fee'],
                'use_money' => $account['money'],
                'remark'    => '用户id为:' . $withdraw['user_id'] . ' 申请提现:' . $withdraw['money'] . '到绑定用户银行id:' . $withdraw['bank_id'] . '的提现手续费:' . $withdraw['extra_fee'] . '元',
                'addtime'   => time(),
                'addip'     => ip(),
                'verify'    => $adminId
            );
            $rt = $this->aadAccountLog($logData);
//            dump($logData);dump($rt);
        }
        return $rt;
    }

    public function delPaymentGroupById($id)
    {
        $ret = $this->db->exec("DElETE FROM " . $this->tablePaymentGroup . " WHERE id = " . $id . " OR entrance_group = " . $id);
        
        return $ret;
        //return $this->db->delete($this->tablePaymentGroup, array('id' => $id));
    }

    public function quotaAdjustment($arr)
    {
        $remark = $arr["remark"];
        O('model')->db->query('BEGIN');
        if(empty($arr['old_bet_amount'])) $arr['old_bet_amount'] = 0;
        try {
            $username = $arr['username'];
            $rt = $this->db->getone("select un_account.* from un_user left join un_account on un_user.id = un_account.user_id where un_user.username = '".$username."'");
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
                    'remark' => "用户:{$arr['username']} 现金账户调整:{$flag}{$arr['money']} ;调整前余额为{$arr['account']}; 操作人:{$arr['oper']},备注:$remark",
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
                    'remark' => "用户:{$arr['username']} 打码补偿量调整:{$flag}".abs($bet_amount)." ;调整前打码补偿量为{$arr['old_bet_amount']}; 操作人:{$arr['oper']},备注:$remark",
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
    
    //额度调整列表总数
    public function cntQuota($where){
         $sql = "select count(*) as cnt,sum(log.money) as money from un_account_log as log"
                . " inner join un_user as user on log.user_id = user.id"
                . " inner join un_account as account on log.user_id = account.user_id";
        $query_sql = " where 1=1";
        if($where['type'] != 0){
            $query_sql .= " and log.type = '{$where['type']}' ";
        } else {
            $query_sql .= " and log.type = 32";
        }
        if ($where['username'] != '') {
            if($where['stype'] == 2)//直属查询
            {
                $ids = '';
                $rt = $this->db->getone("select id from un_user where username = '".$where['username']."'");
                if(!empty($rt))
                {
                    $idInfo = $this->teamLists($rt['id']);
                    if(!empty($idInfo))
                    {
                        foreach($idInfo as $val)
                        {
                            $ids .= $val['id'].",";
                        }
                        $query_sql .= " and user.id in(".trim($ids,",").") ";
                    }else{
                        return 0;
                    }
                }else{
                    return 0;
                }
            }else{
                $query_sql .= " and user.username like '%{$where['username']}%' ";
            }
        }
        if ($where['order_num'] != '') {
            $query_sql .= " and log.order_num='{$where['order_num']}' ";
        }
        if ($where['rg_type'] != 0) {
            $query_sql .= " and user.reg_type = {$where['rg_type']} ";
        } else {
            $query_sql .= " and user.reg_type not in(8,9,11) ";
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and log.addtime > {$time} ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query_sql .= " and log.addtime < {$time} ";
        }
        $sql .= $query_sql;
        $rt = $this->db->getone($sql);
        if (empty($rt['money'])) {
            $rt['money'] = 0;
        }
        return($rt);
    }

    //算法
    public function get_random($length = 3) {
        $min = pow(10, ($length - 1));
        $max = pow(10, $length) - 1;
        return date('YmdHis', time()) . mt_rand($min, $max);  //当前时间加上3位随机数
    }

    //团队集合ID  包含自身
    public function teamLists($userId) {
        $sql = "SELECT user_id as id FROM `un_user_tree` WHERE pids LIKE '%{$userId}%'";
        $userTeams = $this->db->getall($sql);
        if (empty($userTeams)) {
            $userTeams[0]['id'] = $userId;
        } else {
            array_unshift($userTeams, array("id" => $userId));
        }
        return $userTeams;
    }
    
    //额度调整列表
    public function listQuota($where){
        $sql = "select log.id,log.order_num,user.username,log.addtime,log.money,log.use_money as money_usable,log.remark from un_account_log as log"
                . " inner join un_user as user on log.user_id = user.id"
                . " inner join un_account as account on log.user_id = account.user_id";
        $query_sql = " where 1=1";
        if($where['type'] != 0){
            $query_sql .= " and log.type = '{$where['type']}' ";
        } else {
            $query_sql .= " and log.type = 32";
        }
        if ($where['username'] != '') {
            if($where['stype'] == 2)//直属查询{
            {
                $ids = '';
                $rt = $this->db->getone("select id from un_user where username = '".$where['username']."'");
                if(!empty($rt))
                {
                    $idInfo = $this->teamLists($rt['id']);
                    if(!empty($idInfo))
                    {
                        foreach($idInfo as $val)
                        {
                            $ids .= $val['id'].",";
                        }
                        $query_sql .= " and user.id in(".trim($ids,",").") ";
                    }else{
                        return 0;
                    }
                }else{
                    return 0;
                }
            }else{
                $query_sql .= " and user.username like '%{$where['username']}%' ";
            }
        }
        if ($where['order_num'] != '') {
            $query_sql .= " and log.order_num='{$where['order_num']}' ";
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and log.addtime > {$time} ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query_sql .= " and log.addtime < {$time} ";
        }        
        if ($where['rg_type'] != 0) {
            $query_sql .= " and user.reg_type = {$where['rg_type']} ";
        } else {
            $query_sql .= " and user.reg_type not in(8,9,11) ";
        }
        $sql .= $query_sql;
        $sql .= " order by log.addtime desc,log.id desc limit {$where['page_start']},{$where['page_size']}";
        $rt = $this->db->getall($sql);
        return($rt);
    }
    
    /**
     * 获取用户提现的限制，系统次数限制，系统额外手续费
     * @param int $user_id 用户ID
     * @return array
     */
    public function getWithdrawSet($user_id)
    {
        $retData = [];
    
        //初始化redis
        $redis = initCacheRedis();
        //充值下限
        $Config = $redis -> HMGet("Config:cash",array('value'));
        $cash = json_decode($Config['value'],true);
    
        //获取每天提现次数限制
        $freeCont = $redis -> HMGet("Config:daily_free_withdraw_count",array('value'));
        if (!isset($freeCont['value']) || $freeCont['value'] == '' || !is_numeric($freeCont['value'])) {
            $this->refreshRedis2("config", "all");
            $freeCont = $redis -> HMGet("Config:daily_free_withdraw_count",array('value'));
            if (!isset($freeCont['value']) || $freeCont['value'] == '' || !is_numeric($freeCont['value'])) {
                $freeCont['value'] = 0;
            }
        }
    
        //获取每天提现额外手续费
        $withdrwlFee = $redis -> HMGet("Config:daily_withdraw_fee",array('value'));
        if (!isset($withdrwlFee['value']) || $withdrwlFee['value'] == '' || !is_numeric($withdrwlFee['value'])) {
            $this->refreshRedis2("config", "all");
            $withdrwlFee = $redis -> HMGet("Config:daily_withdraw_fee",array('value'));
            if (!isset($withdrwlFee['value']) || $withdrwlFee['value'] == '' || !is_numeric($withdrwlFee['value'])) {
                $withdrwlFee['value'] = 0;
            }
        }
    
        //关闭redis链接
        deinitCacheRedis($redis);
    
        //获取今日免费提现次数
        if ($freeCont['value'] > 0) {
            $start_time = strtotime(date('Y-m-d 00:00:00'));
            $end_time   = strtotime(date('Y-m-d 23:59:59'));
            $sql = 'select id from un_account_cash where user_id = ' . $user_id . ' and (status = 1 or status = 4) and addtime between ' . $start_time . ' and ' . $end_time . ' limit ' . $freeCont['value'];
            $timeCont = $this->db->getall($sql);
            $cont = empty($timeCont) ? 0 : count($timeCont);
            $retData['cont'] = $freeCont['value'] - $cont;
        } else {
            $retData['cont'] = 1;
        }


        $retData['freeCont']     = $freeCont['value'];
        $retData['withdrwlFee']  = $withdrwlFee['value'];
        $retData['withdrwlCont'] = $cont;
        
    
        return $retData;
    }
    
    /**
     * 强制解绑银行卡
     * @param number $paymentId 银行卡ID
     * @return array
     */
    public function setForceRelieveBank($paymentId)
    {
        $ret1 = '';
        $ret2 = '';
    
        if (empty($paymentId) || !is_numeric($paymentId)) {
            return ['code' => 0, 'msg' => '银行卡编号错误！'];
        }
        $bankData = $this->db->getone('select * from ' . $this->table3 . ' where group_id > 0 and id = ' . $paymentId);
        if (empty($bankData)) {
            return ['code' => 0, 'msg' => '该编号的银行卡不存在或已经解绑！'];
        }
        //打log日志
        lg('unbindbankgroup.log', 'payment_bankData_' . $paymentId . '：' . print_r($bankData, true));
    
        $this->db->query('BEGIN');
        try {
            $ret1 = $this->db->update($this->table3, array('group_id' => 0), array('id' => $paymentId));
    
            $groupData = $this->db->getone('select * from ' . $this->tablePaymentGroup . ' where  payment_id = ' . $paymentId);
            if (!empty($groupData)) {
                $ret2 = $this->db->update($this->tablePaymentGroup, array('payment_id' => 0, 'sort' => 0), array('payment_id' => $paymentId));
                
                //打log日志
                lg('unbindbankgroup.log', 'payment_groupData' . $paymentId . '：' . print_r($groupData, true));
            }

            if ($ret1 > 0) {
                if ($ret2 === false) {
                    $this->db->query('ROLLBACK');
    
                    return ['code' => 0, 'msg' => '强制解绑失败，请稍后重试！'];
                } else {
                    $this->db->query('COMMIT');
    
                    return ['code' => 1, 'msg' => '强制解绑成功！'];
                }
            } else {
                $this->db->query('ROLLBACK');
    
                return ['code' => 0, 'msg' => '强制解绑失败，请稍后重试！'];
            }
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
    
            return ['code' => 0, 'msg' => '强制解绑失败，请稍后重试！'];
        }
    }
    
    /**
     * 强制解绑卡组
     * @param number $groupId 卡组ID
     * @return array
     */
    public function setForceRelieveGroup($groupId, $purpose)
    {
        $ret1 = '';
        $ret2 = '';
        $type = '';
    
        $purpose = empty($purpose) ? 0 : 1;
        if (empty($groupId) || !is_numeric($groupId)) {
            return ['code' => 0, 'msg' => '卡组编号错误！'];
        }
        $groupData = $this->db->getone('select * from ' . $this->tablePaymentGroup . ' where  payment_id > 0 and id = ' . $groupId . ' and purpose = ' . $purpose);
        if (empty($groupData)) {
            if ($purpose == 1) {
                $type = '提现';
            } else {
                $type = '充值';
            }
            return ['code' => 0, 'msg' => '该编号的' . $type . '卡组不存在或已经被解绑！'];
        }
        
        //打log日志
        lg('unbindbankgroup.log', 'group_groupData' . $groupId . '：' . print_r($groupData, true));
    
        $this->db->query('BEGIN');
        try {
            $ret1 = $this->db->update($this->tablePaymentGroup, array('payment_id' => 0, 'sort' => 0), array('id' => $groupId, 'purpose' => $purpose));
    
            $bankData = $this->db->getone('select * from ' . $this->table3 . ' where  group_id = ' . $groupId);
            if (!empty($bankData)) {
                $ret2 = $this->db->update($this->table3, array('group_id' => 0), array('group_id' => $groupId));
                
                //打log日志
                lg('unbindbankgroup.log', 'group_bankData' . $groupId . '：' . print_r($bankData, true));
            }
    
            if ($ret1 > 0) {
                if ($ret2 === false) {
                    $this->db->query('ROLLBACK');
    
                    return ['code' => 0, 'msg' => '强制解绑失败，请稍后重试！'];
                } else {
                    $this->db->query('COMMIT');
    
                    return ['code' => 1, 'msg' => '强制解绑成功！'];
                }
            } else {
                $this->db->query('ROLLBACK');
    
                return ['code' => 0, 'msg' => '强制解绑失败，请稍后重试！'];
            }
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
    
            return ['code' => 0, 'msg' => '强制解绑失败，请稍后重试！'];
        }
    }
}
