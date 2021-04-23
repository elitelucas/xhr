<?php
/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
declare(ticks=1);

/**
 * 主逻辑
 * 主要是处理 onMessage onClose
 */

use \GatewayWorker\Lib\Gateway;
use \GatewayWorker\Lib\Db;
use \Workerman\Lib\Timer;
use \Workerman\Autoloader;
use \Workerman\Connection\AsyncTcpConnection;
require_once(__DIR__ . '/../function.php');

date_default_timezone_set('Asia/Shanghai');

class Events
{
    public static function onWorkerStart($businessWorker)
    {
        if ($businessWorker->id === 0) { //定时查在线人数

            Timer::add(3, function () {
                $redis = initCacheRedis();
                $res = $redis->get('header_online_data');
                $redis->del('header_online_data');
                deinitCacheRedis($redis);
                if(!empty($res)){
                    $data = decode($res);
                    var_dump($data);
                    lg('header_online_data',var_export(array(
                        '$data'=>$data,
                    ),1));
                    $msg = json_encode(array('commandid' => 4002, 'content' =>$res),JSON_UNESCAPED_UNICODE);
                    Gateway::sendToAll($msg);
                }
                signa('?m=api&c=workerman&a=onlineUser', '');
            }, array(), true);
        }
    }

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {

    }

    /**
     * 有消息时
     * @param int $client_id
     * @param mixed $message
     */
    public static function onMessage($client_id, $message){
//        return false;
        // 客户端传递的是json数据
        $message_data = json_decode($message, true);
        if (!$message_data) {
            return;
        }
        @$user_id = $message_data['user_id'];
        if(empty($user_id)){
            return false;
        }

        // 根据类型执行不同的业务
        switch ($message_data['commandid']) {
            case 4000:
                $stime  = time();
                $nowtime = time();
                $LotteryTypeIds = array();
                $redis= initCacheRedis();
                $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
                $lottery=array();
                foreach ($LotteryTypeIds as $v){
                    $lottery[] = $redis->hGetAll("LotteryType:".$v);
                }

                $time_out_stime = time();

                foreach ($lottery as $v){
                    $url = '';
                    $msg='';
                    $info = self::getQihao($v['id'],$nowtime);

                    if($info['stopOrSell']==2 && $v['id']==3) continue; //停售不走后面逻辑
                    if(date("H")>21 && 5==$v['id']) $v['every_time'] = 300; //重庆时时彩夜场 提示手动开奖

                    if ($info['issue'] == 0){
                        switch ($v['id']){
                            case 1:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND `issue` = '{$info['QiHaoLast']['issue']}'";
                                }else{
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=LuckyList';
                                $msg='幸运28:';
                                break;
                            case 2:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT qihao AS issue, status FROM `un_bjpk10` WHERE `lottery_type` = {$v['id']} AND `qihao` = '{$info['QiHaoLast']['issue']}'";
                                }else{
                                    $sql = "SELECT qihao AS issue, status FROM `un_bjpk10`  WHERE `lottery_type` = {$v['id']} ORDER BY `qihao` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=bjpk10List';
                                $msg='北京赛车(PK10):';
                                break;
                            case 3:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND `issue` = '{$info['QiHaoLast']['issue']}'";
                                }else{
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=jndList';
                                $msg='加拿大28:';
                                break;
                            case 4:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT qihao AS issue, status FROM `un_xyft` WHERE `qihao` = '{$info['QiHaoLast']['issue']}'";
                                }else{
                                    $sql = "SELECT qihao AS issue, status FROM `un_xyft` ORDER BY `qihao` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=xyftList';
                                $msg='幸运飞艇:';
                                break;
                            case 5:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=sscList';
                                $msg='重庆时时彩:';
                                break;
                            case 6:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=sfcList';
                                $msg='欢乐时时彩:';
                                break;
                            case 7:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_lhc` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_lhc` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=lhcList';
                                $msg='香港六合彩:';
                                break;
                            case 8:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_lhc` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_lhc` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=jslhcList';
                                $msg='极速六合彩:';
                                break;
                            case 9:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT qihao AS issue, status FROM `un_bjpk10` WHERE `lottery_type` = {$v['id']} AND `qihao` = '{$info['QiHaoLast']['issue']}'";
                                }else{
                                    $sql = "SELECT qihao AS issue, status FROM `un_bjpk10`  WHERE `lottery_type` = {$v['id']} ORDER BY `qihao` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=jsscList';
                                $msg='欢乐赛车:';
                                break;
                            case 10:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_nn` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_nn` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=nnList';
                                $msg='百人牛牛:';
                                break;
                            case 11:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=ffcList';
                                $msg='极速时时彩:';
                                break;
                            case 13:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_sb` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_sb` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=sbList';
                                $msg='欢乐骰宝:';
                                break;
                            case 14:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_ffpk10` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_ffpk10` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=ffpk10List';
                                $msg='极速赛车:';
                                break;
							case 15:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND `issue` = '{$info['QiHaoLast']['issue']}'";
                                }else{
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=XjpList';
                                $msg='新加坡28:';
                                break;
							case 16:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND `issue` = '{$info['QiHaoLast']['issue']}'";
                                }else{
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=RdList';
                                $msg='福彩28:';
                                break;
							case 17:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND `issue` = '{$info['QiHaoLast']['issue']}'";
                                }else{
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=CqList';
                                $msg='重庆28:';
                                break;
							case 18:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND `issue` = '{$info['QiHaoLast']['issue']}'";
                                }else{
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=TxList';
                                $msg='腾讯28:';
                                break;
							case 19:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=txffcList';
                                $msg='腾讯分分彩:';
                                break;
							case 20:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=qqffcList';
                                $msg='QQ分分彩:';
                                break;
							case 21:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT qihao AS issue, status FROM `un_hlft` WHERE `qihao` = '{$info['QiHaoLast']['issue']}'";
                                }else{
                                    $sql = "SELECT qihao AS issue, status FROM `un_hlft` ORDER BY `qihao` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=hlftList';
                                $msg='欢乐飞艇:';
                                break;
							case 22:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_sb` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_sb` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=jsk3List';
                                $msg='江苏快三:';
                                break;
							case 23:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_sb` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_sb` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=gxk3List';
                                $msg='广西快三:';
                                break;
                            case 24:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=hn1fcList';
                                $msg='河内1分彩:';
                                break;
                            case 25:
                                //判断是否是最后一期;
                                if($nowtime > $info['QiHaoLast']['date'] && !empty($info['QiHaoLast']['issue'])){
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$info['QiHaoLast']['issue']}' AND lottery_type={$v['id']}";
                                }else{
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE lottery_type={$v['id']} ORDER BY `issue` DESC";
                                }
                                $url ='?m=admin&c=openAward&a=hn5fcList';
                                $msg='河内5分彩:';
                                break;
                            
                        }

                        unset($stime);
                        unset($etime);
                        $stime = time();
                        $res = Db::instance('db1')->row($sql);
                        $etime = time();
                        lg('music_b_debug',"停售时间内执行查操作::".($etime-$stime).",SQL::".$sql);

                        if(in_array($v['id'],array(4,5,6,8,9,10,11,13,14)) && preg_match('/000$/',$info['QiHaoLast']['issue'])){ //防止000期出现
                            return false;
                        }

                        if(empty($res)){
                            $res['status'] = 2;
                            $res['issue'] = $info['QiHaoLast']['issue'];
                        }

                        $data=array(
                            'record_id'=>$v['id'].'_'.$res['issue'],
                            'type'=>3,
                            'tip'=>'手工开奖',
                            'url'=>$url,
                            'time'=>time(),
                            'uids'=>'',
                            'msg'=>$msg.$res['issue'].'需要手动开奖!',
                            'remark'=>date('Y-m-d H:i:s').'提示'.$msg.$res['issue'],
                        );
                        //判断是否需要手动开奖
                        $open_award[$v['id']] = self::setHandDraw($data,$res['status']);

                    }else{
                        if ($v['auto_open']<($v['every_time']-$info['time'])){
                            $issue = $info['issue']-1;
                            switch ($v['id']){
                                case 1:
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND issue = '{$issue}'";
                                    $url ='?m=admin&c=openAward&a=LuckyList';
                                    $msg='幸运28:';
                                    break;
                                case 2:
                                    $sql = "SELECT qihao AS issue, status FROM `un_bjpk10` WHERE `qihao` = '{$issue}' AND  lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=bjpk10List';
                                    $msg='北京赛车(PK10):';
                                    break;
                                case 3:
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND issue = '{$issue}'";
                                    $url ='?m=admin&c=openAward&a=jndList';
                                    $msg='加拿大28:';
                                    break;
                                case 4:
                                    $sql = "SELECT qihao AS issue, status FROM `un_xyft` WHERE `qihao` = '{$issue}'";
                                    $url ='?m=admin&c=openAward&a=xyftList';
                                    $msg='幸运飞艇:';
                                    break;
                                case 5:
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=sscList';
                                    $msg='重庆时时彩:';
                                    break;
                                case 6:
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=sfcList';
                                    $msg='欢乐时时彩:';
                                    break;
                                case 7:
                                    $sql = "SELECT issue, status FROM `un_lhc` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=lhcList';
                                    $msg='香港六合彩:';
                                    break;
                                case 8:
                                    $sql = "SELECT issue, status FROM `un_lhc` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=jslhcList';
                                    $msg='极速六合彩:';
                                    break;
                                case 9:
                                    $sql = "SELECT qihao AS issue, status FROM `un_bjpk10` WHERE `qihao` = '{$issue}' AND  lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=jsscList';
                                    $msg='欢乐赛车:';
                                    break;
                                case 10:
                                    $sql = "SELECT issue, status FROM `un_nn` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=nnList';
                                    $msg='百人牛牛:';
                                    break;
                                case 11:
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=ffcList';
                                    $msg='极速时时彩:';
                                    break;
                                case 13:
                                    $sql = "SELECT issue, status FROM `un_sb` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=sbList';
                                    $msg='欢乐骰宝:';
                                    break;
                                case 14:
                                    $sql = "SELECT issue, status FROM `un_ffpk10` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=ffpk10List';
                                    $msg='极速赛车:';
                                    break;
								case 15:
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND issue = '{$issue}'";
                                    $url ='?m=admin&c=openAward&a=XjpList';
                                    $msg='新加坡28:';
                                    break;
								case 16:
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND issue = '{$issue}'";
                                    $url ='?m=admin&c=openAward&a=RdList';
                                    $msg='福彩28:';
                                    break;
								case 17:
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND issue = '{$issue}'";
                                    $url ='?m=admin&c=openAward&a=CqList';
                                    $msg='重庆28:';
                                    break;
								case 18:
                                    $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$v['id']} AND issue = '{$issue}'";
                                    $url ='?m=admin&c=openAward&a=TxList';
                                    $msg='腾讯28:';
                                    break;
								case 19:
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=txffcList';
                                    $msg='腾讯分分彩:';
                                    break;
								case 20:
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=qqffcList';
                                    $msg='QQ分分彩:';
                                    break;
								case 21:
                                    $sql = "SELECT qihao AS issue, status FROM `un_xyft` WHERE `qihao` = '{$issue}'";
                                    $url ='?m=admin&c=openAward&a=hlftList';
                                    $msg='欢乐飞艇:';
                                    break;
								case 22:
                                    $sql = "SELECT issue, status FROM `un_sb` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=jsk3List';
                                    $msg='江苏快三:';
                                    break;
								case 23:
                                    $sql = "SELECT issue, status FROM `un_sb` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=gxk3List';
                                    $msg='广西快三:';
                                    break;
                                case 24:
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=hn1ffcList';
                                    $msg='河内1分彩:';
                                    break;
                                case 25:
                                    $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$issue}' AND lottery_type={$v['id']}";
                                    $url ='?m=admin&c=openAward&a=hn5ffcList';
                                    $msg='河内5分彩:';
                                    break;
								
                            }

                            unset($stime);
                            unset($etime);
                            $stime = time();
                            $res = Db::instance('db1')->row($sql);
                            $etime = time();
                            lg('music_b_debug',"售奖时间内执行查操作::".($etime-$stime).",SQL::".$sql);

                            if(in_array($v['id'],array(4,5,6,8,9,10,11,13,14)) && preg_match('/000$/',$issue)){ //防止000期出现
                                return false;
                            }

                            if(empty($res)){
                                $res['status'] = 2;
                                $res['issue'] = $issue;
                            }
                            $data=array(
                                'record_id'=>$v['id'].'_'.$res['issue'],
                                'type'=>3,
                                'tip'=>'手工开奖',
                                'url'=>$url,
                                'time'=>time(),
                                'uids'=>'',
                                'msg'=>$msg.$res['issue'].'需要手动开奖!',
                                'remark'=>date('Y-m-d H:i:s').'提示'.$msg.$res['issue'],
                            );

                            //判断是否需要手动开奖
                            $open_award[$v['id']] = self::setHandDraw($data,$res['status']);
                        }
                    }
                }
                $datas=[];
                $datas["list"]=[];
                $time_out_etime = time();
                lg('music_b_debug',"开奖超时查询耗时::".($time_out_etime-$time_out_stime));

                $configJson = $redis->HMGet("Config:musicTips", array('value')); //获取提示音配置参数
                lg('get_music_ips','redis取出来的提示音配置参数'.encode($configJson));
                $infoArr = json_decode($configJson['value'], true);        //格式化提示音配置参数
                deinitCacheRedis($redis); //关闭redis链接

                $db = Db::instance('db1');
                //匹配对应的音乐
                foreach ($infoArr as $v) {
                    if ($v['state'] == 1 && ($v['id']<8 || $v['id']==11) && ($v['is_pop']==1|| !isset($v['is_pop']))) {
                        $res_recharge = self::getMusicTips($v['id'], $user_id);
                        if (!empty($res_recharge)) {
                            foreach ($res_recharge as $rv) {
                                lg('music_tips_debugss',var_export(array(
                                    '$rv'=>$rv,
                                ),1));
                                //判断是否风险会员
                                $str = '';
                                switch ($rv['type']) {
                                    case 2: //线上充值
                                    case 5: //线下充值
                                        $sql = "SELECT u.`state`,u.`username` FROM `un_account_recharge` AS r,un_user AS u WHERE r.`user_id`=u.id AND r.id={$rv['record_id']}";
                                        $rrs = $db->row($sql);
                                        if($rrs['state'] ==1){
                                            $str = "风险会员[{$rrs['username']}]";
                                        }
                                        break;
                                    case 1: //提现
                                    case 6: //提现
                                        $sql = "SELECT u.`state`,u.`username` FROM `un_account_cash` AS c, un_user AS u WHERE c.`user_id`=u.id AND c.id={$rv['record_id']}";
                                        $rrs = $db->row($sql);
                                        if($rrs['state'] ==1){
                                            $str = "风险会员[{$rrs['username']}]";
                                        }
                                        break;
                                }
                                lg('music_tips_debugss',var_export(array(
                                    '$sql'=>$sql,
                                    '$rrs'=>$rrs,
                                ),1));
                                isset($rv['id'])?$tmp['id'] = $rv['id']:$tmp['id'] = "";
                                isset($rv['url'])?$tmp['url'] = $rv['url']:$tmp['url'] = "";
                                isset($rv['msg'])?$tmp['msg'] = $str.$rv['msg']:$tmp['msg'] = "";
                                isset($rv['tip'])?$tmp['title'] = $rv['tip']:$tmp['title'] = "";
                                isset($v['url'])?$tmp['music'] = $v['url']:$tmp['music'] = "";
                                $tmp['time'] = date("Y-m-d H:i:s", time());
                                $datas["list"][] = $tmp;
                                $where = [];
                                @$where['id'] = $rv['id'];
                                $data = array();
                                $data['uids'] = empty($rv['uids']) ? $user_id: $rv['uids'] . "," . $user_id;
                                if(isset($where['id'])&&$where['id']!=""&&$where['id']!=false) self::setMusicTips($data, $where);
                            }
                        }
                    }
                }

                $datas["commandid"] = 4001;

                Gateway::sendToClient($client_id, json_encode($datas));
                break;
        }
        return true;
    }

    /**
     *
     * 把发送数据和接收数据放到公共函数
     * 发送数据给前台,这里的前台一般有多个，并且是跑wokerman的
     * 特别注意:后台的配置文件要把home_arr这个加上去
     * 双活用的，请不要动
     */
    public static function send_home_data($data=array()){
        $key='DCCdPke3boPWr2Wp2Qb4yWF9MuiYq@9f';
        $time=time();
        $sign=md5($key.$time);
        $data['sign']=$sign;
        $data['timestamp']=$time;
        lg('send_home_data','发送给前台的数据::'.encode($data));
        foreach (C('home_arr') as $v){
            $url  =  $v."/index.php?m=api&c=workerman&a=get_admin_data";
            lg('send_home_data','url'.$url);
            http_post_json($url,json_encode($data,JSON_UNESCAPED_UNICODE));
        }
    }

    public static function getQihao($lotteryType,$nowtime,$room = 0){
        //停售时间段
        $stopStartTime = '23:59:59';//停售开始时间
        $stopEndTime = '00:00';//停售结束时间
        $stopTime = '0';//当天时间段停售 0 第二天停售 86400
        //开奖间隔 停售配置 停售时间段
        switch ($lotteryType){
            case 1:
                $space = 300;
                $stopConfig = 'xy28_stop_or_sell';
                $stopStartTime = '23:55';
                $stopEndTime = '09:00';
                $stopTime = 86400;
                break;
            case 2:
                $space = 300;
                $stopConfig = 'bjpk10_stop_or_sell';
                $stopStartTime = '23:55';
                $stopEndTime = '09:00';
                $stopTime = 86400;
                break;
            case 3:
                $space = 210;
                $stopConfig = 'jnd28_stop_or_sell';
                $stopStartTime = '19:00';
                $stopEndTime = '19:10';
                break;
            case 4:
                $space = 300;
                $stopConfig = 'xyft_stop_or_sell';
                $stopStartTime = '04:04';
                $stopEndTime = '13:00';
                break;
            case 5:
                if(date("H")>21){ //夜场
                    $space = 300;
                }else{
                    $space = 600;
                }
                $space = 1200;
                $stopConfig = 'cqssc_stop_or_sell';
				if(date("H")>21){ //夜场
                    $stopStartTime = '03:10';
                }else{
                    $stopStartTime = '23:50';
                }
				if(date("H")>23){ //夜场
                    $stopEndTime = '00:30';
                }else{
                    $stopEndTime = '07:30';
                }
                break;
            case 6:
                $space = 180;
                $stopConfig = 'sfc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 7:
                $space = 180;
                $stopConfig = 'lhc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 8:
                $space = 300;
                $stopConfig = 'jslhc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 9:
                $space = 180;
                $stopConfig = 'jssc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 10:
                $space = 300;
                $stopConfig = 'nn_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 11:
                $space = 60;
                $stopConfig = 'ffc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 13:
                $space = 300;
                $stopConfig = 'tb_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 14:
                $space = 60;
                $stopConfig = 'ffpk10_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
			case 15:
                $space = 300;
                $stopConfig = 'xjp28_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
			case 16:
                $space = 300;
                $stopConfig = 'rd28_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
			case 17:
                if(date("H")>21){ //夜场
                    $space = 300;
                }else{
                    $space = 600;
                }
                $space = 1200;
                $stopConfig = 'cq28_stop_or_sell';
                if(date("H")>21){ //夜场
                    $stopStartTime = '03:10';
                }else{
                    $stopStartTime = '23:50';
                }
				if(date("H")>23){ //夜场
                    $stopEndTime = '00:30';
                }else{
                    $stopEndTime = '07:30';
                }
                break;
			case 18:
                $space = 30;
                $stopConfig = 'tx28_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
			case 19:
                $space = 90;
                $stopConfig = 'txffc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
			case 20:
                $space = 90;
                $stopConfig = 'qqffc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
			case 21:
                $space = 180;
                $stopConfig = 'hlft_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 22:
                $space = 1200;
                $stopConfig = 'jsk3_stop_or_sell';
                $stopStartTime = '22:10';
                $stopEndTime = '08:50';
                break;
            case 23:
                $space = 1200;
                $stopConfig = 'gxk3_stop_or_sell';
                $stopStartTime = '22:30';
                $stopEndTime = '09:30';
                break;
            case 24:
                $space = 60;
                $stopConfig = 'hn1fc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 25:
                $space = 300;
                $stopConfig = 'hn5fc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            default:
                $space = 0;
        }

        //连接redis
        $redis = initCacheRedis();
        $first = $redis->get("QiHaoFirst".$lotteryType);
        $last = $redis->get("QiHaoLast".$lotteryType);
        //返回信息
        $data = array(
            'issue' => 0,
            'date' => 0,
            'time' => 0,
            'QiHaoFirst' => json_decode($first,true),
            'QiHaoLast' => json_decode($last,true),
        );

        if($lotteryType==0){ //六合彩单独处理

        }else{
            //如果不在售彩时间段
            $lottery = self::getRedisHashValues('LotteryType:'.$lotteryType,'config');

            $lottery_config=json_decode($lottery,true);

            var_dump(array(
                '$lotteryType'=>$lotteryType,
                '$lottery'=>$lottery,
                '$lottery_config'=>$lottery_config,
            ));
            if(!isset($lottery_config['start_time'])) $lottery_config['start_time'] = 0;
            if(!isset($lottery_config['end_time'])) $lottery_config['end_time'] = 0;
            @$start_time = strtotime($lottery_config['start_time']);
            @$end_time = strtotime($lottery_config['end_time']);
            $stop_start_time = strtotime($stopStartTime);
            $stop_end_time = strtotime($stopEndTime) + $stopTime;

            //停售时间不在当天时间段的特殊处理
            if($end_time <= $start_time){
                $specialTime = 86400;
                $start_time -= $specialTime;
                $end_time += $specialTime;
            }
        }

        if($nowtime < $start_time || $nowtime > $end_time || ($nowtime > $stop_start_time && $nowtime < $stop_end_time)){
            if($lotteryType==3){
                $data['msg'] = '停售时间： '.$lottery_config['end_time'].'-'.$lottery_config['start_time'];
            }else{
                $data['msg'] = '已停售，售彩时间： '.$lottery_config['start_time'].'-'.$lottery_config['end_time'];
            }
            $tip = Db::instance('db1')->single("select tip from un_lottery_type WHERE id = $lotteryType");
            if($tip!="") $data['msg'] = $tip;
            $data['sealingTim'] = 0;
            $data['lotteryType'] = $lotteryType;
            $data['stopOrSell'] = 2;
            if($lotteryType==3){
                lg('model_get_qihao',var_export(array('不在时间内','$lottery_config'=>$lottery_config,'$data'=>$data,'$nowtime'=>$nowtime,'$start_time'=>$start_time,'$end_time'=>$end_time,'$stop_start_time'=>$stop_start_time,'$stop_end_time'=>$stop_end_time,'($nowtime < $start_time || $nowtime > $end_time || ($nowtime > $stop_start_time && $nowtime < $stop_end_time))'=>($nowtime < $start_time || $nowtime > $end_time || ($nowtime > $stop_start_time && $nowtime < $stop_end_time))),1));
            }
            return $data;
        }

        //如果后台设置停止售彩
        $config_res= self::getConfig($stopConfig,array('value'));
        $config_config=json_decode($config_res['value'],true);
//        if($lotteryType==3){
//            lg('getQiaHao_stopOrSell','前'.var_export(array('$config_config'=>$config_config),1));
//        }
        if($config_config['status']==2){

            $data['msg'] = $config_config['title'];
            $data['sealingTim'] = 0;
            $data['lotteryType'] = $lotteryType;
            $data['stopOrSell'] = 2;
            if($lotteryType==3){
                lg('model_get_qihao',var_export(array('后台停售','$data'=>$data,'$config_config'=>$config_config),1));
            }
            return $data;
        }

        //todo
        //房间停售 封盘时间
        $closure_time = 0;
        if($room){
            //TODO: 后续完善,暂无房间停售
            //封盘时间
            $closure_time = self::getRedisHashValues('allroom:'.$room,'closure_time');
            $closure_time = $closure_time?$closure_time:0;
        }

        $QiHao = $redis->lRange("QiHaoIds".$lotteryType, 0, -1);

        foreach ($QiHao as $v){
            $res = json_decode($v,true);
            if($res['date'] <= $nowtime){
                //将对应的键删除
                $redis->Lrem("QiHaoIds".$lotteryType, $v);
            }else{
                if($lotteryType==7){ //六合彩单独处理
                    $data = $res;
                }else{
                    if($res['date']-$nowtime <= $space){
                        $data = $res;
                    }
                }
                break;
            }
        }
        deinitCacheRedis($redis);
        if ($data['issue'] == 0){
            $data = self::setqihao($lotteryType,$nowtime);
        }
        $data['sealingTim'] = $closure_time;
        $data['lotteryType'] = $lotteryType;
        $data['stopOrSell'] = 1;
        if($nowtime < $start_time || $nowtime > $end_time || ($nowtime > $stop_start_time && $nowtime < $stop_end_time)){
            $data['stopOrSell'] = 2;
        }else{
            if($data['issue']==0){
                if($lotteryType==3){ //update 20180621
                    $data['msg'] = '停售时间： '.$lottery_config['end_time'].'-'.$lottery_config['start_time'];
                }else{
                    $data['msg'] = '已停售，售彩时间： '.$lottery_config['start_time'].'-'.$lottery_config['end_time'];
                }
                $tip = Db::instance('db1')->single("select tip from un_lottery_type WHERE id = $lotteryType");
                if($tip!="") $data['msg'] = $tip;
                $data['sealingTim'] = 0;
                $data['lotteryType'] = $lotteryType;
                $data['stopOrSell'] = 2;
                return $data;
            }
        }
        $data['stopOrSell'] = ($data['issue']==0)?2:1;
        $data['time'] = $data['date']-time();
        $data['QiHaoFirst'] = json_decode($first,true);
        $data['QiHaoLast'] = json_decode($last,true);
        //幸运飞艇期号前台需要截取掉前面4位，后台必须把期号转成字符串类型，前台才不会报错，才能进行切割（WTF）...
        $data['issue'] = (string)$data['issue'];
//        if($lotteryType==3){
//            lg('model_get_qihao',var_export(array('最后数据','$data'=>$data),1));
//        }

        return $data;
    }

    /**
     * 配置参数
     * @param $k
     * @return $config array
     */
    public static function getConfig($k,$value = ''){
        //初始化redis
        $redis = initCacheRedis();
        if(empty($value)){
            $config = $redis->hGetAll("Config:".$k);
        }else{
            if(is_array($value)){
                $config = $redis->hMGet("Config:".$k,$value);
            }else{
                $config = $redis->hGet("Config:".$k,$value);
            }
        }

        //关闭redis链接
        deinitCacheRedis($redis);
        return $config;
    }

    /**
     * 重获取期号
     * @param int $lotteryType 开奖采种
     * @param int $nowtime 当前时间
     */
    public static function setqihao($lotteryType,$time){
        if($lotteryType==12) return false;
        //开奖间隔 数据源
        switch ($lotteryType){
            case 1:
                $space = 300;
                $data = @file_get_contents(__DIR__.'/../../../xy28_qihao.json'); //获取数据
                break;
            case 2:
                $space = 1200;
                $data = @file_get_contents(__DIR__.'/../../../bjpk10_qihao.json'); //获取数据
                break;
            case 3:
                $space = 210;
                $data = @file_get_contents(__DIR__.'/../../../jnd28_qihao.json'); //获取数据
                break;
            case 4:
                $space = 300;
                $data = @file_get_contents(__DIR__.'/../../../xyft_qihao.json'); //获取数据
                break;
            case 5:
                if(date("H")>21){ //夜场
                    $space = 300;
                }else{
                    $space = 600;
                }
                    $space = 1200;
                $data = @file_get_contents(__DIR__.'/../../../cqssc_qihao.json'); //获取数据
                break;
            case 6:
                $space = 180;
                $data = @file_get_contents(__DIR__.'/../../../sfc_qihao.json'); //获取数据
                break;
            case 7:
                $space = 180;
                $data = @file_get_contents(__DIR__.'/../../../lhc_qihao.json'); //获取数据
                break;
            case 8:
                $space = 300;
                $data = @file_get_contents(__DIR__.'/../../../jslhc_qihao.json'); //获取数据
                break;
            case 9:
                $space = 180;
                $data = @file_get_contents(__DIR__.'/../../../jssc_qihao.json'); //获取数据
                break;
            case 10:
                $space = 300;
                $data = @file_get_contents(__DIR__.'/../../../nn_qihao.json'); //获取数据
                break;
            case 11:
                $space = 60;
                $data = @file_get_contents(__DIR__.'/../../../ffc_qihao.json'); //获取数据
                break;
            case 13:
                $space = 300;
                $data = @file_get_contents(__DIR__.'/../../../sb_qihao.json'); //获取数据
                break;
            case 14:
                $space = 60;
                $data = @file_get_contents(__DIR__.'/../../../ffpk10_qihao.json'); //获取数据
                break;
			case 15:
                $space = 180;
                $data = @file_get_contents(__DIR__.'/../../../xjp28_qihao.json'); //获取数据
                break;
			case 16:
                $space = 60;
                $data = @file_get_contents(__DIR__.'/../../../rd28_qihao.json'); //获取数据
                break;
			case 17:
                $space = 300;
                $data = @file_get_contents(__DIR__.'/../../../cq28_qihao.json'); //获取数据
                break;
			case 18:
                $space = 60;
                $data = @file_get_contents(__DIR__.'/../../../tx28_qihao.json'); //获取数据
                break;
			case 19:
                $space = 60;
                $data = @file_get_contents(__DIR__.'/../../../txffc_qihao.json'); //获取数据
                break;
			case 20:
                $space = 60;
                $data = @file_get_contents(__DIR__.'/../../../qqffc_qihao.json'); //获取数据
                break;
			case 21:
                $space = 300;
                $data = @file_get_contents(__DIR__.'/../../../hlft_qihao.json'); //获取数据
                break;
			case 22:
                $space = 1200;
                $data = @file_get_contents(__DIR__.'/../../../jsk3_qihao.json'); //获取数据
                break;
			case 23:
                $space = 1200;
                $data = @file_get_contents(__DIR__.'/../../../gxk3_qihao.json'); //获取数据
                break;
            case 24:
                $space = 60;
                $data = @file_get_contents(__DIR__.'/../../../hn1fc_qihao.json'); //获取数据
                break;
			case 25:
                $space = 300;
                $data = @file_get_contents(__DIR__.'/../../../hn5fc_qihao.json'); //获取数据
                break;
            default:
                $space = 0;
        }
        $data = json_decode($data,true);
        $list = json_decode($data['txt'],true);

        //连接redis
        $redis = initCacheRedis();
        $redis -> del("QiHaoFirst".$lotteryType);
        $redis -> del("QiHaoLast".$lotteryType);
        $redis -> del("QiHaoIds".$lotteryType); //删除之前的缓存
        //最后一期
//        dump($list['list']);exit;
        $last = end($list['list']);
        $redis -> set("QiHaoLast".$lotteryType,json_encode($last));
        //第一期
        $first = reset($list['list']);
        $redis -> set("QiHaoFirst".$lotteryType,json_encode($first));
        //一天的期号
        foreach ($list['list'] as $v){
            $key = json_encode($v);
            //将对应的键存入队列中
            $redis -> RPUSH("QiHaoIds".$lotteryType, $key);
        }
        $QiHao = $redis->lRange("QiHaoIds".$lotteryType, 0, -1);

        //返回信息
        $data = array(
            'issue' => 0,
            'date' => 0,
            'QiHaoFirst' => $first,
            'QiHaoLast' => $last,
            'msg' => "未到售彩时间,暂时停售",
        );
        foreach ($QiHao as $v){
            $res = json_decode($v,true);
            if($res['date'] <= $time){
                //将对应的键删除
                $redis -> Lrem("QiHaoIds".$lotteryType, $v);
            }else{
                if($res['date']-$time <= $space){
                    $data = $res;
                    break;
                }
            }
        }
        $redis -> close();
        return $data;
    }
    /**
     * 获取缓存信息
     */
    public static function getRedisHashValues($key, $value='')
    {
        //初始化redis
        $redis = initCacheRedis();
        if(empty($value)){
            $res = $redis->hGetAll($key);
        }else{
            if(is_array($value)){
                $res = $redis->hMGet($key,$value);
            }else{
                $res = $redis->hGet($key,$value);
            }
        }
        //关闭redis链接
        deinitCacheRedis($redis);

        return $res;
    }

    /**
     * @return array
     */
    public static function getMusicTips($type, $uid)
    {
        $userInfo = self::getSoundReceiveUid($uid);

        lg('music_tip_13',var_export(array('$userInfo'=>$userInfo),1));
        if(!$userInfo) {
            return false;
        }
        //获取接收提示音的管理员uid   手动开奖提示音
        $sql = "SELECT id, record_id, `type`, tip, url, click_uid, click_status, click_time, `status`, `time`, uids, msg FROM un_music_tips WHERE `status` = 0 AND click_uid = 0 AND type = '{$type}' AND not find_in_set({$uid},uids)";
        //    lg('get_music_ips','提示音:SQL'.$sql);
        return Db::instance('db1')->query($sql);
    }

    /*
     * 获取能接收提示音的管理员uid
     */
    public static function getSoundReceiveUid($id = 1)
    {
        //$admin = $this->admin;
        $redis = initCacheRedis();
        $promptAuth = $redis->hget('Config:tonePermissions','value');
        lg('music_tip_13',var_export(array('$promptAuth'=>$promptAuth),1));
        deinitCacheRedis($redis);
        if(!empty($promptAuth)){
            $promptAuth = json_decode($promptAuth,true);
            if(!empty($promptAuth)) {
                $tonePermissions = implode(",",$promptAuth);

                $sql = "select userid from un_admin where userid=$id AND roleid in(".$tonePermissions.")";
                $uid = Db::instance('db1')->row($sql);

                lg('music_tip_13',var_export(array(
                    '$sql'=>$sql,
                    '$tonePermissions'=>$tonePermissions,
                ),1));

                if(!empty($uid)){
                    return $uid;
                }
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public static function setMusicTips($data,$where)
    {
        $where_str = "";
        $first = true;
        foreach ($where as $k=>$i){
            if($first) {
                $where_str .= "`$k`='$i'";
                $first = false;
            }else{
                $where_str .= ",`$k`='$i'";
            }
        }

        $param_str = "";
        $first = true;
        foreach ($data as $k=>$i){
            if($first) {
                $param_str .= "`$k`='$i'";
                $first = false;
            }else{
                $param_str .= ",`$k`='$i'";
            }
        }

        $sql = "UPDATE `un_music_tips` SET $param_str WHERE $where_str";
        var_dump(
            array(
                '$sql'=>$sql,
            )
        );
        return Db::instance('db1')->query($sql);
    }

    /**
     * 刷新缓存
     * @method POST
     * @param $action string 方法名 刷新全部 all
     * @param $param string 参数  刷新全部 all
     * @return array
     */
    public static function refreshRedis($action, $param) {
        if (empty($action) || empty($param)) {
            return array('status' => 100002, 'data' => " 缺少刷新参数");
        }

        $arr=array();
        $param = array(
            'pass' => C('pass'),
            'action' => $action,
            'param' => $param
        );
        //组装URL
        foreach (C('home_arr') as $k=>$v){
            $url  =  $v."/index.php?m=api&c=initCache&a=index";
            lg('do_init_cache','url'.$url);
            $arr[$k]=http_post_json($url,$param);
        }
        return $arr;
    }

    /**
     * 设置手动开奖信息
     * @return string
     */
    public static function setHandDraw($data,$status=2){
        $manual_open = array();
        if(!in_array($status,array(0,1))){
            //获取接收提示音的管理员uid   手动开奖提示音
            $uidInfo = self::getSoundReceiveUid();
            lg('getSoundReceiveUid',var_export(array('$data'=>$data),1));

            //防止并发
            lg('fb_input_time','接收到的所有参数::'.encode($data).',implode::'.implode(':',$data));
            $redis = initCacheRedis();
            $co_str = 'man_open:'. $data['record_id'];
            lg('fb_input_time','组装key,$co_str---->::'.$co_str.',查看是否生效::'.$redis->get($co_str));
            if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去
                lg('fb_input_time','进行设置超时时间');
                $redis->expire($co_str,20); //设置它的超时
                lg('fb_input_time','超时时间::'.$redis->ttl($co_str));
                deinitCacheRedis($redis);
            }else{
                lg('fb_input_time','并发操作::,期数::'. $data['record_id']);
                deinitCacheRedis($redis);
                return false;
            }

//            lg('fb_input_time',var_export(array('$uidInfo'=>$uidInfo),1));

            if(!empty($uidInfo)) {
                $sql = 'select uids from un_music_tips WHERE `record_id`=\'' . $data['record_id'] . '\' and type=\'' . $data['type'] . '\' and status=0';
                $res = Db::instance('db1')->row($sql);
                if (empty($res)) {
                    $param_str = "";
                    $first = true;
                    foreach ($data as $k=>$i){
                        if($first) {
                            $param_str .= "`$k`='$i'";
                            $first = false;
                        }else{
                            $param_str .= ",`$k`='$i'";
                        }
                    }

                    $sql="INSERT INTO `un_music_tips` SET $param_str";

                    $inid = Db::instance('db1')->row($sql);
                    if ($inid) {
                        $manual_open['msg'] = $data['msg'];
                        $manual_open['time'] = $data['time'];
                    }
                }
            }
        }
        return $manual_open;
    }
    /**
     * 当客户端断开连接时
     * @param integer $client_id 客户端id
     */
    public static function onClose($client_id)
    {
//        if ($_SESSION['userid']) {
//            unset($_SESSION['userid']);
//            unset($_SESSION['reg_type']);
//            unset($_SESSION['roomid']);
//            unset($_SESSION['lottery_type']);
//            unset($_SESSION['time']);
//        }
    }

    /**
     * 获取倒计时
     * @param $lottery_type
     * @param int $nowtime
     * @param int $istimer
     * @return array
     */
    public static function getCountdown($lottery_type, $nowtime = 0, $roomid = 0)
    {
        //$nowtime = empty($nowtime) ? time() :$nowtime;
        $info = self::getQihao($lottery_type, $roomid);
        $data = array(
            'commandid' => 3001,
            'time' => $info['time'],
            'issue' => $info['issue'],
            'sealingTim' => $info['sealingTim'],
            'stopOrSell' => $info['stopOrSell'],
            'stopMsg' => $info['msg'],
            'lotteryType' => $info['lotteryType'],
        );
        return $data;
    }


    /**
     * 定时任务每秒
     * @param $client_id
     */
    public static function send()
    {
        $nowtime = time();
        //获取配置信息
        $list = json_decode(redisfuns('get', 'messageconfig'), 1);
        $lotteryType = redisfuns('getLIds', 'LotteryTypeIds');
        foreach ($lotteryType as $v){
            $info[$v] = self::getQihao($v);
        }
        foreach ($list as $v) {
            self::sendMessage($v, $info[$v['lottery_type']] );
        }
        unset($list);
        $room = redisfuns('getall', 'allroomIds');
        $list = Gateway::getAllClientSessions();
        if ($list) {
            foreach ($list as $k => $v) {
                if (empty($v)) {
                    continue;
                }
                if ($v['time'] && $nowtime - $v['time'] > $room[$v['roomid']]['shove_time'] * 60) {
                    Gateway::sendToClient($k, json_encode(array('commandid' => 3014, 'content' => '由于您长时间未操作，请重新登录。')));
                    Gateway::closeClient($k);
                }
            }
        }
    }

    /**
     * 定时任务每秒
     * @param $client_id
     */
    public static function sendMessage($v, $info)
    {
        if ($info['time'] == $v['release_time']) {
            $qihao = $info['issue'];
            if (strpos($v['content'], '{期号}') !== false) {
                $v['content'] = str_replace("{期号}", $qihao, $v['content']);
            }
            if (strpos($v['content'], '{下注核对}') !== false) {
                $ret = Db::instance('db1')->query("select U.nickname,D.way,D.money,U.id from un_orders D LEFT JOIN  un_user U ON D.user_id=U.id where D.issue='" . $qihao . "' AND D.room_id=" . $v['room_id']);
                $str = '';
                if ($ret) {
                    $RmbRatio = redisfuns('get', "Config:rmbratio", 1);
                    $xianshigeshi = redisfuns('get', "Config:dandianshuzi", 1);
                    if ($xianshigeshi == 'space') $xianshigeshi = " ";
                    $userlist = array();
                    foreach ($ret as $val) {
                        $ljstr = is_numeric($val['way']) ? $xianshigeshi : '';
                        $val['money'] = $val['money'] * $RmbRatio;
                        if (isset($userlist[$val['id']])) {
                            $userlist[$val['id']] .= "  " . $val['way'] . $ljstr . $val['money'];
                        } else {
                            $userlist[$val['id']] = $val['nickname'] . "[" . $val['way'] . $ljstr . $val['money'];
                        }
                    }
                    if (!empty($userlist)) {
                        $str = implode("]\n", $userlist) . "]";
                    }
                }
                $v['content'] = str_replace("{下注核对}", $str, $v['content']);
            }
            Gateway::sendToGroup($v['room_id'], json_encode(array('commandid' => 3004, 'nickname' => '', 'content' => $v['content'])));
        }

    }


    /**
     * 获取机器人的投注数据
     */
    public static function send_person()
    {
        $nowtime = time();
        $sql = "SELECT a.user_id,a.lottery_type,a.username,a.room_id,a.way,a.bet_money,c.avatar,c.nickname FROM un_bet_list a left join un_person_config b on a.conf_id = b.id left join un_user c on c.id = a.user_id where a.bet_time = $nowtime";
        $list = Db::instance('db1')->query($sql);

        if (!empty($list)) {
            foreach ($list as $val) {
                $msgData['userid'] = $val['user_id'];
                $msgData['lottery_type'] = $val['lottery_type'];
                $msgData['username'] = $val['username'];
                $msgData['roomid'] = $val['room_id'];
                $msgData['way'] = [$val['way']];
                $msgData['money'] = [$val['bet_money']];
                $msgData['avatar'] = $val['avatar'];
                $msgData['nickname'] = $val['nickname'];
                self::person($msgData);
            }
        }
    }


    /**
     * 获取随机追号标识
     */
    public static function getRandomString($len, $chars = null)
    {
        if (is_null($chars)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        }
        mt_srand(10000000 * (double)microtime());
        for ($i = 0, $str = '', $lc = strlen($chars) - 1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }

    /**
     * 获取用户荣誉信息
     * author: Aho
     *
     * @param $userId   用户ID
     * @param int $type 返回类型 1：json 0：array
     *
     * @return bool|string
     */
    public static function get_honor_level($userId)
    {
        $status = Db::instance('db1')->single("select value from un_config where nid='is_show_honor'");
        $score = Db::instance('db1')->single("select honor_score-lose_score from un_user where id = $userId");
        $score = $score < 0 ? 0 : $score;
        $honor = Db::instance('db1')->row("select name,icon,status,score,num from un_honor where score <= $score order by score desc limit 1");
        $honor['status1'] = $status;
        return $honor;

    }

    /**
     * 获取用户荣誉信息
     * @param $userId   用户ID
     * @return array
     */
    public static function get_level_honor($userId)
    {
        $score = Db::instance('db1')->single("select honor_score from un_user where id = " . $userId);
        if (empty($score)) {
            $score = 0;
        }
        $score = $score < 0 ? 0 : $score;
        $honor = Db::instance('db1')->row("select name, icon, sort, score, grade from un_honor where status = 1 and score <= $score order by score desc limit 1");

        $conf = Db::instance('db1')->single("select value from un_config where nid = 'level_honor'");
        $config = json_decode($conf,true);

        $honor['honor_status'] = $config['status'];
        $honor['user_score'] = $score;
        $honor['sort']  = $honor['grade'];

        return $honor;
    }

    //插入数据
    private static function insert($table, $data = array())
    {
        $cols = array();
        $vals = array();
        $one = reset($data);
        if (is_array($one)) {
            $cols = self::deal_field(array_keys($one));
            foreach ($data as $val) {
                $vals[] = '(' . implode(',', self::deal_value($val)) . ')';
            }
            $vals = implode(',', $vals);
        } else {
            $cols = self::deal_field(array_keys($data));
            $vals = '(' . implode(',', self::deal_value($data)) . ')';
        }
        $sql = "INSERT INTO " . self::deal_field($table) . " ( {$cols} ) VALUES {$vals}";
        return $sql;
    }

    //私有处理表名
    private static function deal_field($str = '')
    {
        if (is_array($str)) {
            $str = array_map(array(__class__, __method__), $str);
            $str = implode(',', $str);
            return $str;
        }
        if (strpos($str, ',') !== false && strpos($str, '`') === false) {
            $arr = explode(',', $str);
            $str = array_map(array(__class__, __method__), $arr);
            $str = implode(',', $str);
            return $str;
        }
        if ($str && $str != '*' && strpos($str, 'COUNT') === false && strpos($str, 'SUM') === false && strpos($str, 'AS') === false)
            $str = "`" . trim($str) . "`";
        return $str;
    }

    //私有处理数据值
    public static function deal_value($str = '')
    {
        if (is_array($str)) {
            $str = array_map(array(__class__, __method__), $str);
            return $str;
        }
        $str = "'{$str}'";
        return $str;
    }
}

//$data = json_decode(redisfuns('get', 'way' . $_SESSION['roomid']), 1);
function redisfuns($funs, $key = '', $type = 0)
{
    static $cache_redis, $config;
    if (empty($config)) {
        !defined('IN_SNYNI') && define("IN_SNYNI", 1);
        $config = require("../caches/config.php");
    }
    if (empty($cache_redis)) {
        $cache_redis = new redis();
        $cache_redis->connect($config['redis_config']['host'], $config['redis_config']['port']);
        $cache_redis->auth($config['redis_config']['pass']);
    }
    switch ($funs) {
        case 'set':
            foreach ($key as $k => $v) {
                $cache_redis->set($k, $v);
            }
        case 'getall':
            $Ids = $cache_redis->lRange($key, 0, -1);
            $key_str = str_replace("Ids", ':', $key);
            $info = array();
            foreach ($Ids as $v) {
                $info[$v] = $cache_redis->hGetAll($key_str . $v);
            }
            return $info;
        case 'getLIds':
            $Ids = $cache_redis->lRange($key, 0, -1);
            return $Ids;
        case 'get':
            if ($type) {
                $data = $cache_redis->hGetAll($key);
                if (substr($key, 0, 7) == 'Config:') {
                    return $data['value'];
                }
                return $cache_redis->hGetAll($key);
            } else {
                return $cache_redis->get($key);
            }
        case 'del':
            return $cache_redis->del($key);
        case 'expire':
            return $cache_redis->expire($key, $type);
        case 'ttl':
            return $cache_redis->ttl($key);
        case 'close':
            $cache_redis->close();
            $cache_redis = false;
    }
}

function getRecord(){
    $cash_count = Db::instance('db1')->single("select count(id) from un_account_cash where status = 0");
    $charge_count = Db::instance('db1')->single("select count(id) from un_account_recharge where status = 0");
    return array('commandid' => 4003, 'cash_count' =>$cash_count,'$charge_count'=>$charge_count);
}