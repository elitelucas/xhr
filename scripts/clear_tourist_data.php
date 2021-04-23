<?php
/**
 *
 * 清除昨天的游客数据
 */

//引用系统的功能
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set('memory_limit','1024M');
//获取DB连接
$db = getconn();

$sql  = "select id from un_user where regtime < ".strtotime('today')." and reg_type=8 order by id desc";
lg('clear_tourist_data_log','查询昨天的记录::SQL::'.$sql);
$list = $db->getall($sql);
lg('clear_tourist_data_log','总数据::'.count($list));
if(!empty($list)){
    foreach ($list as $k => $v){
        lg('clear_tourist_data_log','游客ID::'.$v['id']);
        //用户表
        $sql_1='delete from un_user where id ='.$v['id'];
        $db->query($sql_1);
        //资金表
        $sql='delete from un_account where user_id ='.$v['id'];
        $db->query($sql);
        //提现表
        $sql='delete from un_account_cash where user_id ='.$v['id'];
        $db->query($sql);
        //充值表
        $sql='delete from un_account_recharge where user_id ='.$v['id'];
        $db->query($sql);
        //资金交易明细表
        $sql='delete from un_account_log where user_id ='.$v['id'];
        $db->query($sql);
        //返水表
        $sql='delete from un_back_log where user_id ='.$v['id'];
        $db->query($sql);
        //客服聊天记录表
        $sql='delete from un_custom where user_id ='.$v['id'];
        $db->query($sql);
        //禁言表
        $sql='delete from un_gag where user_id ='.$v['id'];
        $db->query($sql);
        //站内信表
        $sql='delete from un_message where user_id ='.$v['id'];
        $db->query($sql);
        //订单表
        $sql='delete from un_orders where user_id ='.$v['id'];
        $db->query($sql);
        //session表
        $sql='delete from un_session where user_id ='.$v['id'];
        $db->query($sql);
        //天天返利表
        $sql='delete from un_ttfl_log where user_id ='.$v['id'];
        $db->query($sql);
        //用户银行卡表
        $sql='delete from un_user_bank where user_id ='.$v['id'];
        $db->query($sql);
        //用户登录日志表
        $sql='delete from un_user_login_log where user_id ='.$v['id'];
        $db->query($sql);
        //第三方登录表
        $sql='delete from un_user_third where user_id ='.$v['id'];
        $db->query($sql);
        //白名单表
        $sql='delete from un_whitelist where user_id ='.$v['id'];
        $db->query($sql);
        //系统审核表
        $sql='delete from un_xitongshenghe where user_id ='.$v['id'];
        $db->query($sql);
        //系统审核表
        $sql='delete from un_xitongshenghe where user_id ='.$v['id'];
        $db->query($sql);
        //清除转盘中奖表记录
        $sql='DELETE FROM `un_turntable_award_log` WHERE `user_id` = '.$v['id'];
        $db->query($sql);
        //清除转盘参与表记录
        $sql='DELETE FROM `un_turntable_join_log` WHERE `user_id` = '.$v['id'];
        $db->query($sql);
    }
}
lg('clear_tourist_data_log',"执行完成.\n\n");