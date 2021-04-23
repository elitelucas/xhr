<?php
/**
 *
 * 期号表定时脚本操作
 *
 */

//引用系统的功能
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set('memory_limit','1024M');

$id  = $argv[1];
if(empty($id)){
    die();
}

$this_year = date('Y');
$year_month_day = date('Y_m_d');

switch ($id){
    case 1 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/xy28/'. $year_month_day .'@xy28_qihao.json';
        $dest = S_ROOT.'xy28_qihao.json';
        break;
    case 2 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/bjpk10/'. $year_month_day .'@bjpk10_qihao.json';
        $dest = S_ROOT.'bjpk10_qihao.json';
        break;
    case 3 :
	$source = S_ROOT.'json_files/issue/'. $this_year .'/jnd28/'. $year_month_day .'@jnd28_qihao.json';
        $dest = S_ROOT.'jnd28_qihao.json';
	break;
    case 4 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/xyft/'. $year_month_day .'@xyft_qihao.json';
        $dest = S_ROOT.'xyft_qihao.json';
        break;
    case 5 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/cqssc/'. $year_month_day .'@cqssc_qihao.json';
        $dest = S_ROOT.'cqssc_qihao.json';
        break;
    case 6 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/sfc/'. $year_month_day .'@sfc_qihao.json';
        $dest = S_ROOT.'sfc_qihao.json';
        break;
    case 8 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/jslhc/'. $year_month_day .'@jslhc_qihao.json';
        $dest = S_ROOT.'jslhc_qihao.json';
        break;
    case 9 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/jssc/'. $year_month_day .'@jssc_qihao.json';
        $dest = S_ROOT.'jssc_qihao.json';
        break;
    case 10 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/nn/'. $year_month_day .'@nn_qihao.json';
        $dest = S_ROOT.'nn_qihao.json';
        break;
    case 11 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/ffc/'. $year_month_day .'@ffc_qihao.json';
        $dest = S_ROOT.'ffc_qihao.json';
        break;
    case 13 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/sb/'. $year_month_day .'@sb_qihao.json';
        $dest = S_ROOT.'sb_qihao.json';
        break;
    case 14 :
        $source = S_ROOT.'json_files/issue/'. $this_year .'/ffpk10/'. $year_month_day .'@ffpk10_qihao.json';
        $dest = S_ROOT.'ffpk10_qihao.json';
        break;
}

if(copy($source,$dest)){
    sleep(2);
    $redis = initCacheRedis();
    $redis->del('QiHaoIds'.$id);
    deinitCacheRedis($redis);
}

