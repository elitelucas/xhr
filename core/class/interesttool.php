<?php

/**
 *  Interesttool.php 利息计算类
 *
 * @copyright			(C) 2013 CHENGHUITONG.COM
 */
!defined('IN_SNYNI') && die('Access Denied!');

class interesttool {
	

    /**
     * 等额本息法按月还款
     * 贷款本金×月利率×（1+月利率）还款月数/[（1+月利率）还款月数-1]
     * a*[i*(1+i)^n]/[(1+I)^n-1]
     * （a×i－b）×（1＋i）
     *
     * @param unknown_type $data  array('account'=>金额，'apr'=>年利率,time_limit=>月数,borrow_time=>开始计算时间，不传为当前时间)
     * @param unknown_type $istotal  是否返回汇总
     * @return unknown
     */
    public function equalMonth($data = array(), $istotal = 0) {
        //借款金额
        if (!(isset($data['account']) && bccomp($data['account'], 0.00, 2)) || !(isset($data['apr']) && bccomp($data['apr'], 0.0000, 4)) || !(isset($data['time_limit']) && $data['time_limit'] > 0))
            return false;

        $account = $data['account'];
        $month_apr = round($data['apr'] / 12, 8);
        $month_times = $data['time_limit'];
        $borrow_time = (isset($data['borrow_time']) && $data['borrow_time'] > 0) ? $data['borrow_time'] : SYS_TIME;

        //还款金额
        $repayment = round($data['account'] * ($month_apr * pow((1 + $month_apr), $month_times)) / (pow((1 + $month_apr), $month_times) - 1), 2);
        $_result = array();
        $interest_t = 0;
        for ($i = 0; $i < $month_times; $i++) {
            $interest = $i == 0 ? round($account * $month_apr, 2) : round(($account * $month_apr - $repayment) * pow((1 + $month_apr), $i) + $repayment, 2);
            $interest_t += $interest;
            $_result[$i]['will_money'] = round($repayment, 2);                                                  //月还款本息
            $_result[$i]['will_interest'] = round($interest, 2);                                                //利息
            $_result[$i]['will_time'] = strtotime(date('Y-m-d', self::get_times($borrow_time,$i + 1)));      //还款时间
            $_result[$i]['will_capital'] = $repayment - $interest;                                             //月还款本金
            $_result[$i]['periods'] = $i + 1;                  //期数 periods
        }
        //汇总
        if ($istotal) {
            $_result['repayment_account'] = $repayment * $month_times;  //还款总额
            $_result['monthly_repayment'] = $repayment;                 //月还款
            $_result['month_apr'] = round($month_apr * 100, 2);         //月利率
            $_result['interest'] = round($interest_t, 2);         //总利息
        }
        return $_result;
    }

    /**
     * 到期还本，按月付息
     *
     * @param unknown_type $data  array('account'=>金额，'apr'=>年利率,time_limit=>月数,borrow_time=>开始计算时间，不传为当前时间)
     * @param unknown_type $istotal  是否返回汇总
     * @return unknown
     */
    public function equalEndmonth($data = array(), $istotal = 0) {
        //借款金额
        if (!(isset($data['account']) && bccomp($data['account'], 0.00, 2)) || !(isset($data['apr']) && bccomp($data['apr'], 0.000000, 6)) || !(isset($data['time_limit']) && $data['time_limit'] > 0))
            return false;

        $account = $data['account'];
        $month_apr = round($data['apr'] / 12, 8);
        $month_times = $data['time_limit'];
        $borrow_time = (isset($data['borrow_time']) && $data['borrow_time'] > 0) ? $data['borrow_time'] : SYS_TIME;

        $_result = array();
        $interest = round($account * $month_apr, 2);                            //利息等于应还金额乘月利率
        for ($i = 0; $i < $month_times; $i++) {
            $capital = $i + 1 == $month_times ? $account : 0.00;                //本金只在第三个月还，本金等于借款金额除季度
            $_result[$i]['will_money'] = $interest + $capital;
            $_result[$i]['will_time'] = strtotime(date('Y-m-d', self::get_times($borrow_time,$i + 1)));
            $_result[$i]['will_interest'] = $interest;
            $_result[$i]['will_capital'] = $capital;
            $_result[$i]['periods'] = $i + 1;                 //期数 periods
        }
        //汇总
        if ($istotal) {
            $_result['repayment_account'] = $account + $interest * $month_times;      //还款总额
            $_result['monthly_repayment'] = $interest;                              //月还款
            $_result['month_apr'] = round($month_apr * 100, 2);                     //月利率
            $_result['interest'] = round($interest * $month_times, 2);         //总利息
        }
        return $_result;
    }

    /**
     * 天标利息计算
     *
     * @param unknown_type $data array('account'=>金额，'apr'=>年利率,time_limit=>天数,borrow_time=>开始计算时间，不传为当前时间)
     * @return unknown
     */
    public function endday($data = array()) {
        if (!(isset($data['account']) && bccomp($data['account'], 0.00, 2)) || !(isset($data['apr']) && bccomp($data['apr'], 0.0000, 4)) || !(isset($data['time_limit']) && $data['time_limit'] > 0))
            return false;
        $day_apr = round($data['apr'] / 365, 8);
        $borrow_time = (isset($data['borrow_time']) && $data['borrow_time'] > 0) ? $data['borrow_time'] : SYS_TIME;

        $_result = array();
        $interest = round($day_apr * $data['time_limit'] * $data['account'], 2);
        $_result['will_money'] = $data['account'] + $interest;      //还款总额
        $_result['will_interest'] = $interest;                     //月利息
        $_result['will_time'] = strtotime(date('Y-m-d', strtotime($borrow_time . ' days', $data['time_limit'])));
        $_result['will_capital'] = $data['account'];
        $_result['periods'] = 1;                  //期数 periods
        return $_result;
    }

    /**
     * 逾期利息计算
     *
     * @param unknown_type $data account 金额 repayment_time 还款时间 con_late_rate 天利率
     * @return unknown
     */
    public function LateInterest($data) {
        $late_rate = isset($data['con_late_rate']) ? $data['con_late_rate'] : 0.003;
        $data['repayment_time'] = strtotime(date("Y-m-d 00:00:00",$data['repayment_time']))+86400;
        if (SYS_TIME<$data['repayment_time']) {
        	return false;
        }
        $late_days = ceil((SYS_TIME - $data['repayment_time']) / 86400);
        if ($late_days>0) {
        	$late_interest = round($data['account'] * $late_rate * $late_days, 2);
	        if ($late_days == 0)
	            $late_interest = 0;
	        return array("late_days" => $late_days, "late_interest" => $late_interest);
        }else {
        	return false;
        }
    }
    
    //获得时间
    public function get_times($time,$num){
    	$day = (int)date("d",$time);
		$_result = strtotime("$num month",$time);
		$_day = (int)date("d",$_result);
		if ($day!=$_day){
			$_result = strtotime(date('Y-m-t',strtotime("-1 month",$_result)));
		}
		return $_result;
    }
}

?>