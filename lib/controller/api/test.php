<?php
///**
// *  action.php
// *
// */
//ini_set('max_execution_time', '0');
//ini_set(‘memory_limit’,’2048M’);
//class TestAction {
//
//    public $db;
//    public $userId = 0;
//
//    public function __construct()
//    {
//        $this->db = getconn();
//    }
//
////    //访问权限控制
////    public function betting_money()
////    {
////        if($_REQUEST['pass'] != 'e10adc3949ba59abbe56e057f20f883e'){
////            ErrorCode::errorResponse(200003, '口令错误无权访问');
////            return false;
////        }
////
////        //起始时间
////        $start_date = trim($_REQUEST['start_time']);
////        //结束时间
////        $end_date = trim($_REQUEST['end_time']);
////
////        if(!empty($start_date) && !empty($end_date)){
////            $start_time = strtotime($start_date);
////            $end_time = strtotime($end_date." 23:59:59");
////            $where = " AND l.addtime BETWEEN {$start_time} and {$end_time}";
////            $msgTime = '时间范围: '.$start_date.' 到 '.$end_date." 23:59:59";
////        }elseif(!empty($start_date)){
////            $start_time = strtotime($start_date);
////            $where = " AND l.addtime >= {$start_time}";
////            $msgTime = '时间大于等于: '.$start_date;
////        }elseif(!empty($end_date)){
////            $end_time = strtotime($end_date." 23:59:59");
////            $where = " AND l.addtime <= {$end_time}";
////            $msgTime = '时间小于等于: '.$end_date." 23:59:59";
////        }else{
////            $where = "";
////            $msgTime = '全部数据: ';
////        }
////        //交易金额
////        $sql = "SELECT type, NULLIF(SUM(money),0) AS total_money FROM un_account_log AS l WHERE reg_type NOT IN (0,8,9,11) " . $where . " AND l.type IN(12,13,14) GROUP BY l.type";
////        $tradeLog = O('model')->db->getAll($sql);
////
////        $tranType = array(12,13,14);
////        $trades = array();
////        foreach ($tranType as $v) {
////            $trades[$v] = '0';
////        }
////        $type = array();
////        foreach ($tradeLog as $v) {
////            $trades[$v['type']] = $v['total_money'];
////        }
////
////        $arr = array(
////            'betting_money' => $trades['13'] - $trades['14'],//投注总额
////            'award_money' => $trades['12'],//中奖总额
////            'betting_profit_money' => $trades['13'] - $trades['14']-$trades['12'],//投注盈利总额
////        );
////        echo "<div style='margin: 30px; font-size: 30px'>";
////        echo $msgTime;
////        echo "<br>";
////        echo '<span style="color: #4dcd70">投注总额:</span> '.$arr['betting_money'].' 元';
////        echo "<br>";
////        echo '<span style="color: #4dcd70">中奖总额:</span> '.$arr['award_money'].' 元';
////        echo "<br>";
////        echo '<span style="color: #4dcd70">投注盈利总额:</span> '.$arr['betting_profit_money'].' 元';
////        echo "</div><br>";
////    }
//}
