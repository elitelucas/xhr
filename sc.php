<?php     //源码来源258源码 www.258yuanma.cn 站长QQ194998587
header('Content-type: text/json; charset=utf-8');
define('S_ROOT', __DIR__ . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
//258源码www.258yuanma.cn 最新期号生成
$name = $_GET['name'];
//今天的日期
//$nowDate = date('Y-m-d',time());
$nowDate = date($_GET['nowDate'],time());
//第一期时间
$nowDate = date('Y-m-d').' '.$_GET['nowDate1'];
//每日多少期
//$qihao = '41';
$qihao = $_GET['qihao'];
//每期多少分
$fz = $_GET['fz'];
//期数格式
$qsgs = $_GET['qsgs'];
//期数计算
$save_list = array();
$list = array();
for($i=1;$i<=$qihao;$i++){
if($i<10){
 $qs = "00".$i;//控制00
}elseif($i<100){
 $qs = "0".$i;//控制0
}else{
 $qs = $i;
}
//期数信息
//欢乐飞艇，欢乐赛车
if($name =='jssc'){
$save['issue'] = date($qsgs).'02'.$qs;
//欢乐时时彩
}elseif($name =='sfc'){
$save['issue'] = date($qsgs).'01'.$qs;
//极速赛车
}elseif($name =='ffpk10'){
$save['issue'] = date($qsgs).'07'.$qs;
//极速时时彩
}elseif($name =='ffc'){
$save['issue'] = date($qsgs).'05'.$qs;
//百人牛牛
}elseif($name =='nn'){
$save['issue'] = date($qsgs).'04'.$qs;
//欢乐骰宝
}elseif($name =='sb'){
$save['issue'] = date($qsgs).'06'.$qs;
//极速六合彩
}elseif($name =='jslhc'){
$save['issue'] = date($qsgs).'03'.$qs;
//河内1分彩
}elseif($name =='jjsc'){
$save['issue'] = date($qsgs).'02'.$qs;
//QQ分分彩，腾讯分分彩，腾讯28
}elseif($name =='qqffc'|| $name =='txffc' || $name =='tx28'){
//大于10
if($qs < 10){
 $qs = "000".$i;
//大于100
}elseif($qs < 100){
 $qs = "00".$i;
//大于1000
}elseif($qs > 1000){
 $qs = $i;
}else{
 $qs = "0".$i;
}
$save['issue'] = date($qsgs).$qs;
}else{
$save['issue'] = date($qsgs).$qs;
}
  
$save['date'] = strtotime($nowDate);
$save['__ymd__'] = $nowDate;
$save_list[] = $save;
unset($save);
$nowDate = date('Y-m-d H:i:s',strtotime("+".$fz." minute",strtotime($nowDate)));//控制多少分钟一期
}
//期数组装
$tmp['list'] = $save_list;
$tmp['visit_time'] = time();
$tmp['length'] = count($save_list);
$json = array('txt'=>encode($tmp));
//期数输出
$shuju = encode($json);
echo encode($json);

$dumulu = S_ROOT . "/{$name}_qihao.json";
file_put_contents($dumulu,$shuju);

?>