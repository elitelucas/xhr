<?php
$now_tiem = time();

//返水记录入库脚本
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set('memory_limit','4096M');

//$lotteryType = $_REQUEST['lotteryType'];
//$issue = $_REQUEST['issue'];
//dump($lotteryType );
//dump($issue);

$startDate = $argv[1];
$long = $argv[2];  //天数
//$issue = $argv[2];
//$date
//$status = $argv[3];
//$uid = $argv[4];
////var_dump($argv);
var_dump($startDate);
var_dump($long);
////var_dump($issue);
////exit;
//$db = getconn();
//
if(!empty($startDate)){
    $model = D('shell_reporting');
    for ($i=0;$i<$long;$i++){
        $date = date('Y-m-d',(strtotime($startDate)+(24*3600*$i)));
        lg('shell_reporting_team','shell端代码'.var_export(array('$startDate'=>$startDate,'$long'=>$long,'$date'=>$date),1));
        $res = $model->shell_team_reporting($date);
    }
}else{
    $model = D('shell_reporting');
    $res = $model->shell_team_reporting();
}

//$db->close();