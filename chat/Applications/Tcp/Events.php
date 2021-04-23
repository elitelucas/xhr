<?php
/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

/**
 * 主逻辑
 * 主要是处理 onMessage onClose
 */
require_once  __DIR__.'/funs.php';
use \GatewayWorker\Lib\Gateway;
use \GatewayWorker\Lib\Db;
use \Workerman\Lib\Timer;
use \Workerman\Autoloader;

date_default_timezone_set('Asia/Shanghai');

class Events
{
	public static function onWorkerStart($businessWorker)
	{

//		if ($businessWorker->id === 0) { //指定最后一个进程做计时器给机器人用
//			//改成接口形式--这里直接请求接口
//			Timer::add(1, function () {
//				signa('?m=api&c=workerman&a=robot', ''); //直接请求机器人接口
//			}, array(), true);
//		}
//		if ($businessWorker->id === 1) { //指定最后一个进程做计时器给机器人用
//			//改成接口形式--这里直接请求接口
//			Timer::add(1, function () {
//				signa('?m=api&c=workerman&a=bayWindow', ''); //直接请求机器人接口
//			}, array(), true);
//		}
//
//		//房间发布信息
//		if ($businessWorker->id === 0) { //指定一个进程做计时器
//			//改成接口形式--这里直接请求接口
//			Timer::add(1, function () {
//				signa('?m=api&c=workerman&a=room_sent', '');
//			}, array(), true);
//		}
	}

	/**
	 * 当客户端连接时触发
	 * 如果业务不需此回调可以删除onConnect
	 *
	 * @param int $client_id 连接id
	 */
	public static function onConnect($client_id)
	{
//        lg('chat.log',var_export(array(
//            '$client_id'=>$client_id,
//        ),1));
	}

	/**
	 * 有消息时
	 * @param int $client_id
	 * @param mixed $message
	 */
	public static function onMessage($client_id, $message)
	{
//        $message = str_replace('\n','',$message);
//        $message = '{"commandid" : "3002","uid" :710,"roomid" : "1"}';
        var_dump(array(
            '原始数据',
            '$message'=>$message,
        ));
        // 客户端传递的是json数据
		$message_data = json_decode($message, true);
		var_dump(array(
		    '解析后',
		    '$message_data'=>$message_data,
        ));
//		$message_data = $message;
		if (!$message_data) {
			return;
		}
		if ($message_data['commandid'] != 3012) {
			lg('chat.log', "tcp::Events.php-------------->client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} date:" . date("Y-m-d H:i:s") . "  client_id:$client_id session:" . json_encode($_SESSION) . " onMessage:" . $message);
		}

		//查询sessionid
		if (isset($_SESSION['userid']) && !empty($_SESSION['userid'])) {
			$sessionid = Db::instance('db1')->single("select sessionid from un_session WHERE user_id = '{$_SESSION['userid']}'");
			if (!$sessionid) { //踢出去
				Gateway::sendToClient($client_id, json_encode(array('commandid' => 3014, 'content' => 'Since you havent operated for a long time, please log in again!'/*'You have been forced offline!'*/)));
				return;
			}
		} else {
			//var_dump($message_data);
			$sessionid = Db::instance('db1')->single("select sessionid from un_session WHERE user_id = '{$message_data['uid']}'");
			if (!$sessionid) { //踢出去
				Gateway::sendToClient($client_id, json_encode(array('commandid' => 3014, 'content' => 'Since you havent operated for a long time, please log in again!'/*'You have been forced offline! ! !'*/)));
				return;
			}
		}

		// 根据类型执行不同的业务
		switch ($message_data['commandid']) {
			// 客户端回应服务端的心跳
			case '3012':
				if (empty($_SESSION['lottery_type'])) {
					Gateway::sendToClient($client_id, json_encode(array('commandid' => 3020, 'content' => 'Your information has been lost and you are reconnecting! ! !')));
					return;
				}
//                Gateway::sendToClient($client_id, json_encode(self::getCountdown($_SESSION['lottery_type'], time(), $_SESSION['roomid'])));

//                $data = array(
//                    'client_id' => $client_id,
//                    'roomid' => $_SESSION['roomid'],
//                    'lotteryType' => $_SESSION['lottery_type'],
//                );
				//直接调用接口
				//signa('?m=api&c=workerman&a=getQihaoNew', $data);

				/////////////////////////////////////////////6666666666666666666666
				$room = $_SESSION['roomid'];
				$lotteryType = $_SESSION['lottery_type'];


				$info = self::getQihao($lotteryType,$room);

				lg('model_get_qihaovv','tcp::'.var_export(array('$room'=>$room,'$lotteryType'=>$lotteryType,'$info'=>$info,'_sesss'=>$_SESSION),1));


				$data = array(
					'commandid' => 3001,
					'time' => $info['time'],
					'issue' => $info['issue'],
					'sealingTim' => $info['sealingTim'],
					'stopOrSell' => $info['stopOrSell'],
					'stopMsg' => isset($info['msg'])?$info['msg']:'',
					'lotteryType' => $info['lotteryType'],
				);

				Gateway::sendToClient($client_id, encode($data));
				/////////6666666666666
				return;
			//绑定用户
			case '3002':
                lg('chat.log', "tcp::进入3002");
				$message_data['roomid'] = intval($message_data['roomid']);
				$message_data['uid'] = intval($message_data['uid']);

				//判断房间是否已满员
				$roomInfo = redisfuns('get', 'allroom:' . $message_data['roomid'], 1); //获取对应房间的数据信息
				$onlineCount = Gateway::getClientCountByGroup($message_data['roomid']); //在线总人数
				if (($onlineCount + 1) > $roomInfo['max_number']) { //踢出去
					Gateway::sendToClient($client_id, json_encode(array('commandid' => 3014, 'type'=>1, 'content' => 'The room is full, please change to another room!')));
				}

				//用户注册绑定时发送欢迎语
				$userInfo = Db::instance('db1')->row("select username,nickname,reg_type from un_user WHERE id = '{$message_data['uid']}'");
				if (empty($userInfo['nickname'])) { //踢出去
					Gateway::sendToClient($client_id, json_encode(array('commandid' => 3014, 'content' => 'You have not yet set a nickname. Set a nickname!')));
				}
				$username = $userInfo['nickname'] == '' ? $userInfo['username'] : $userInfo['nickname'];

				$honor =  self::get_level_honor($message_data['uid']);
				if ($honor['honor_status'] == 1) {
					Gateway::sendToClient($client_id, json_encode(array('commandid' => 3004, 'honor_status' => $honor['honor_status'], 'sort' => $honor['sort'], 'nickname' => '', 'content' => '欢迎 {#username} 进入房间', 'username'=>$username,'userid' => $message_data['uid'])));
					//控制昵称显示
					$tznickanme = redisfuns('get', "Config:tznickname", 1);
					if ($tznickanme) {
						$strleng = mb_strlen($username) - 1;
						$username = mb_substr($username, 0, 1, 'utf-8') . "***" . mb_substr($username, $strleng, 1, 'utf-8');
					}
					Gateway::sendToGroup($message_data['roomid'], json_encode(array('commandid' => 3004, 'honor_status' => $honor['honor_status'], 'sort' => $honor['sort'], 'nickname' => '', 'content' => '欢迎 {#username} 进入房间', 'username'=>$username)));
				} else {
					Gateway::sendToClient($client_id, json_encode(array('commandid' => 3004, 'nickname' => '', 'honor_status' => $honor['honor_status'], 'sort' => $honor['sort'], 'content' => "欢迎 {#username} 进入房间..", 'username'=>$username,'userid' => $message_data['uid'])));
				}

				if (empty($message_data['roomid']) || empty($message_data['uid'])) {
					$message = array('commandid' => 3004, 'nickname' => '', 'content' => "信息错误。");
					Gateway::sendToClient($client_id, json_encode($message));
					Gateway::closeClient($client_id);
					return;
				}
				Gateway::bindUid($client_id, $message_data['uid']);
				Gateway::joinGroup($client_id, $message_data['roomid']);

                Timer::add(0.01, function () {
			    }, array(), false);
				lg("chat.log",'tcp::'.encode(array(
					'等待取房间对应的彩种ID',
					'$message_data[\'uid\']'=>$message_data['uid'],
					'$client_id'=>$client_id,
					'$message_data[\'roomid\']'=>$message_data['roomid'],
				)));
				$redis = initCacheRedis();
				$lottery_type = $redis->hget("allroom:{$message_data['roomid']}",'lottery_type');
				deinitCacheRedis($redis);
//				$lottery_type = Db::instance('db1')->single("select lottery_type from un_room WHERE id={$message_data['roomid']}");
				lg("chat.log",'tcp::'.encode(array(
					'取到彩种ID',
					'$message_data[\'uid\']'=>$message_data['uid'],
					'$client_id'=>$client_id,
					'$message_data[\'roomid\']'=>$message_data['roomid'],
					'$lottery_type'=>$lottery_type,
				)));
				if ($lottery_type) {
					$_SESSION['userid'] = $message_data['uid'];
					$_SESSION['reg_type'] = $userInfo['reg_type'];
					$_SESSION['roomid'] = $message_data['roomid'];
					$_SESSION['lottery_type'] = $lottery_type;
					$_SESSION['time'] = time();
					Gateway::sendToClient($client_id, json_encode(self::getCountdown($lottery_type, time(), $message_data['roomid'])));
                    lg("chat.log",'tcp::'.encode(array(
                        '调用WM完成',
                        '$message_data[\'uid\']'=>$message_data['uid'],
                        '$client_id'=>$client_id,
                        '$message_data[\'roomid\']'=>$message_data['roomid'],
                        '$lottery_type'=>$lottery_type,
                    )));
					//采用接口方式

//                    $data = array(
//                        'client_id' => $client_id,
//                        'roomid' => $message_data['roomid'],
//                        'lotteryType' => $lottery_type,
//                    );
					//直接调用接口
//                    signa('?m=api&c=workerman&a=getQihaoNew', $data);
//                    $res = json_decode($res, true);
//                    return $res['data'];
				} else {
					Gateway::closeClient($client_id);
				}
				return;
			case '3003':

				//优先判断后台设置的限制 by Joinsen
				$RmbRatio = redisfuns('get', 'Config:rmbratio', 1);
				$config['moneyLessNoSpeak'] = redisfuns('get', 'Config:moneyLessNoSpeak', 1);
				$config['speakWordsNumbers'] = redisfuns('get', 'Config:speakWordsNumbers', 1);

				//判断字数是否满足发言条件
				if ($config['speakWordsNumbers']>0 && mb_strlen($message_data['content'], 'UTF-8') > $config['speakWordsNumbers']) {
					$message = array('commandid' => 3005, 'content' => "Every time you speak, the number of words must not exceed" . $config['speakWordsNumbers'] . "character.");
					Gateway::sendToClient($client_id, json_encode($message));
					return;
				}

				//判断余额是否满足发言条件
//                if ($config['moneyLessNoSpeak']) {
				if ($config['moneyLessNoSpeak'] && $_SESSION['userid']) {
					$ye = Db::instance('db1')->single("select money from un_account where user_id='" . $_SESSION['userid'] . "'");
					if (bccomp($ye, $config['moneyLessNoSpeak'], 2) == -1) {
						$message = array('commandid' => 3005, 'content' => "Account balance is lower than" . ($config['moneyLessNoSpeak'] * $RmbRatio) . "no talking");
						Gateway::sendToClient($client_id, json_encode($message));
						return;
					}
				}

				//禁止游客发言
				if(isset($_SESSION['reg_type'])){
					$regType = $_SESSION['reg_type'];
				}else {
					$regType = Db::instance('db1')->single("select reg_type from un_user where id='" . $_SESSION['userid'] . "'");
					$_SESSION['reg_type'] = $regType;
				};
				$config['visitorLimit'] = redisfuns('get', 'Config:visitorLimit', 1);
				if ($config['visitorLimit']==1 && $regType==8) {
					$message = array('commandid' => 3005, 'content' => "Visitors are not allowed to speak！");
					Gateway::sendToClient($client_id, json_encode($message));
					return;
				}


				$gag = Db::instance('db1')->row("select gag_time,gag_reason,addtime from un_gag WHERE user_id = '" . $_SESSION['userid'] . "' order by id desc limit 1");
				if ($gag && (time() - $gag['addtime'] < $gag['gag_time'] * 60 || $gag['gag_time'] == '0')) {
					$message = array('commandid' => 3005, 'content' => $gag['gag_reason']);
					Gateway::sendToClient($client_id, json_encode($message));
					return;
				}

				$SensitiveWords = redisfuns('get', "Config:SensitiveWords", 1);
				$words = explode(",", $SensitiveWords);
				$count = 0;
				if (!empty($words)) {
					str_replace($words, '', $message_data['content'], $count);
				}
				if (empty($count)) {

					//达到分钟发言限制
					$speakConfig = redisfuns('get', 'Config:banned', 1);
					$speakConfigArr = json_decode($speakConfig, true);
					$limitTime = $speakConfigArr['time'] * 60;

					$speakKey = 'speak:' . $_SESSION['userid']; //key区分不同用户
					$speak = redisfuns('get', $speakKey);
					if ($speak) {
						//判断发言总数
						$speakArr = json_decode($speak, true);
						$speakNum = count($speakArr);
						if (($speakNum >= $speakConfigArr['cnt']) && ($speakArr[$speakNum - 1] - $speakArr[0]) <= $limitTime) { //加入禁用列表
							$sql = self::insert('un_gag', array('user_id' => $_SESSION['userid'], 'gag_time' => 0, 'gag_reason' => "您{$speakConfigArr['time']}分钟内发言超过{$speakConfigArr['cnt']}条限制，已被永久禁言！", 'addtime' => time()));
							Db::instance('db1')->query($sql);
							$message = array('commandid' => 3005, 'content' => "您{$speakConfigArr['time']}分钟内发言超过{$speakConfigArr['cnt']}条限制，已被永久禁言！");
							Gateway::sendToClient($client_id, json_encode($message));
							return;
						} else {
							//获取当前key剩余有效时间
							$yxtime = redisfuns('ttl', $speakKey);
							if ($yxtime > 0) {
								$speakArr[] = time();
								redisfuns('set', array($speakKey => json_encode($speakArr)));
								redisfuns('expire', $speakKey, $yxtime);
							} else {
								$speakArr = array(time());
								redisfuns('set', array($speakKey => json_encode($speakArr)));
								redisfuns('expire', $speakKey, $limitTime);
							}
						}
					} else {//失效重新创建
						$speakArr = array(time());
						redisfuns('set', array($speakKey => json_encode($speakArr)));
						redisfuns('expire', $speakKey, $limitTime);
					}

					//控制昵称显示
					$tznickanme = redisfuns('get', "Config:tznickname", 1);
					if ($tznickanme) {
						$strleng = mb_strlen($message_data['nickname']) - 1;
						$message_data['nickname'] = mb_substr($message_data['nickname'], 0, 1, 'utf-8') . "***" . mb_substr($message_data['nickname'], $strleng, 1, 'utf-8');
					}

					$honor =  self::get_level_honor($message_data['uid']);
					$message = array('commandid' => 3004, 'nickname' => $message_data['nickname'], 'uid' => $_SESSION['userid'], 'honor_status' => $honor['honor_status'], 'sort' => $honor['sort'], 'content' => $message_data['content'], 'avatar' => $message_data['avatar']);


					Gateway::sendToGroup($_SESSION['roomid'], json_encode($message));
				} else {
					$message = array('commandid' => 3005, 'content' => "您的发言涉及到敏感信息，发送失败！");
					Gateway::sendToClient($client_id, json_encode($message));
				}

				redisfuns('close');
				return;
			case '3006':
				if ($_SESSION['userid'] && $message_data['nickname'] && !empty($message_data['way']) && !empty($message_data['money']) && $message_data['avatar']) {
					$jbtme = microtime(1);
					$data = array(
						'userid' => $_SESSION['userid'],
						'lotteryType' => $_SESSION['lottery_type'],
						'roomid' => $_SESSION['roomid'],
						'win_stop' => isset($message_data['win_stop'])?$message_data['win_stop']:'',
						'way' => json_encode($message_data['way'], JSON_UNESCAPED_UNICODE),
						'money' => json_encode($message_data['money']),
						'single_money' => json_encode(isset($message_data['single_money'])?$message_data['single_money']:[]),
						'ext_a' => $message_data['ext_a']
					);

					$lotteryType = $data['lotteryType'];
					$tway = $data['way'];

//                    signa('?m=api&c=workerman&a=betting', $data);
//                    return false;
					///6666666666666666666666666666666666

					//接收参数
					$uid = $_SESSION['userid'];

					$way = json_decode(stripslashes_deep($data['way']),true);
					lg('betting_debug','$way::'.encode($way));
					$money = json_decode(stripslashes_deep($data['money']),true);
					$single_money = !empty($data['single_money'])?decode($data['single_money']):array(); //单注金额
					$roomid = $data['roomid'];
					$win_stop = $data['win_stop'];

					$lotteryType = $data['lotteryType'];

					$hbtime = microtime(1);
					//防止刷单
					lg('bettin_orders_log','接收到的所有参数::'.encode($data).',implode::'.implode(':',$data));
					$redis = initCacheRedis();
					$co_str = 'bet:'.$uid;
					lg('bettin_orders_log','组装key,uid::'.$uid.',彩种::'.$lotteryType.',房间::'.$roomid.',$co_str---->::'.$co_str.',查看是否生效::'.$redis->get($co_str));
					if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去
						lg('bettin_orders_log','进行设置超时时间');
						$redis->expire($co_str,3); //设置它的超时
						lg('bettin_orders_log','超时时间::'.$redis->ttl($co_str));
						deinitCacheRedis($redis);
					}else{
						lg('bettin_orders_log','刷单操作::'.'uid::'.$uid.',彩种::'.$lotteryType.',房间::'.$roomid);
						deinitCacheRedis($redis);
						return false;
					}

					$hetme = microtime(1);
					lg('betting_debug_time',var_export(array(
						'$uid'=>$uid,
						'彩种'=>$data['lotteryType'],
						'玩法'=>$data['way'],
						'并发处理耗时'=>runtime($hetme,$hbtime),
					),1));

					//判断参数
					$paramFlag = false;
					if(empty($uid)) $paramFlag = true;
					if(empty($way)) $paramFlag = true;
					if(empty($money)) $paramFlag = true;
					if(empty($roomid)) $paramFlag = true;
					if(empty($lotteryType)) $paramFlag = true;

					//判断参数
					if($paramFlag){
						lg('betting_log','ERROR 参数验证失败(投注): '.encode($data));
						lg('betting_debug','ERROR 参数验证失败(投注): '.encode($data));
						return;
					}

					$hbtime = microtime(1);
					//验证玩法和金额对应关系
					$lenWay = count($way);
					$lenMoney = count($money);
					$lenSingleMoney = count($single_money);
					lg('betting_log',var_export(array(
						'$uid'=>$uid,
						'$way'=>$way,
						'$money'=>$money,
						'$single_money'=>$single_money,
						'$lenWay'=>$lenWay,
						'$lenMoney'=>$lenMoney,
						'$lenSingleMoney'=>$lenSingleMoney,
					),1));
					if($lenWay != $lenMoney){
						Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "投注数据有误。")));
						return false;
					}
					if(in_array($lotteryType,array(7,8)) && $lenWay != $lenSingleMoney){
						Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "投注数据有误。")));
						return false;
					}

					$hetme = microtime(1);
					lg('betting_debug_time',var_export(array(
						'$uid'=>$uid,
						'彩种'=>$data['lotteryType'],
						'玩法'=>$data['way'],
						'玩法和金额对应验证耗时'=>runtime($hetme,$hbtime),
					),1));


					//判断房间彩种
					$roomInfo = self::getRedisHashValues("allroom:".$roomid,array('lottery_type','special_way'));
					if($roomInfo['lottery_type'] != $lotteryType) $paramFlag = true;

					//特殊玩法判断
					$whereSql = "";
					$special_way = json_decode($roomInfo['special_way'],true);
					if($special_way['status'] != '1'){
						$whereSql = " AND type <> 3";
					}

					if(in_array($lotteryType,array(7,8)) && empty($single_money)){
						lg('lhc_betting_debug','没传single_money字段'.var_export(array('$single_money'=>$single_money),1));
						Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "投注信息不全。")));
						return false;
					}

					//校验投注金额是否有误
					foreach ($money as $mv){
						if(strpos($mv,'.') !== false && !strpos($mv,'.00')){
							lg('betting_log_money',var_export(array(
								'金额有误',
								'$mv'=>$mv,
							),1));
							Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "投注金额有误。")));
							return false;
						}
					}

					if(!empty($single_money)){
						foreach ($single_money as $mv){
							if(strpos($mv,'.') !== false && !strpos($mv,'.00')){
								lg('betting_log_money',var_export(array(
									'投注金额[单注]有误',
									'$mv'=>$mv,
								),1));
								Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "投注金额有误。")));
								return false;
							}
						}
					}

					if(in_array($lotteryType,array(7,8)) && empty($single_money)){
						lg('lhc_betting_debug','没传single_money字段'.var_export(array('$single_money'=>$single_money),1));
						Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "投注信息不全。")));
						return false;
					}

					$hbtime = microtime(1);
					//查询用户信息(昵称,头像,注册类型)
					$sql = "SELECT username,nickname,avatar,group_id,reg_type FROM `un_user` WHERE `id` = '{$uid}'";
					$res = Db::instance('db1')->row($sql);
					if(empty($res) || $paramFlag){
						Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "信息丢失,或已变更。")));
						return;
					};
					$hetme = microtime(1);
					lg('betting_debug_time',var_export(array(
						'$uid'=>$uid,
						'彩种'=>$data['lotteryType'],
						'玩法'=>$data['way'],
						'从数据库取昵称,头像,注册类型，耗时'=>runtime($hetme,$hbtime),
					),1));

					$username = empty($res['nickname'])?$res['username']:$res['nickname'];
					//控制昵称显示
					$username = self::getNickname($username);
					$avatar = empty($res['avatar'])?'/up_files/room/avatar.png':$res['avatar'];
					$regType = $res['reg_type'];
					$groupid = $res['group_id'];

					//查询用户荣誉等级
					//$honor = D('workerman')->getHonorLevel($uid);
					$hbtime = microtime(1);

					$honor = get_level_honor($uid);

					$hetme = microtime(1);
					lg('betting_debug_time',var_export(array(
						'$uid'=>$uid,
						'彩种'=>$data['lotteryType'],
						'玩法'=>$data['way'],
						'获取用户荣誉等级耗时'=>runtime($hetme,$hbtime),
					),1));

					//获取期号
					$info = $res = self::getQihao($lotteryType,$roomid);

					//返回信息
					$message = array(
						'commandid' => 3004,
						'nickname' => '',
						'content' => isset($info['msg'])?$info['msg']:'',
						'avatar' => '',
						'status' => '1'
					);

					$hbtime = microtime(1);
					//六合彩期号验证
					if($lotteryType == 7){
						$redis = initCacheRedis();
						$lhcIssue = $redis->get('lhc_issue');
						if(empty($lhcIssue)){
							$sql_issue = "SELECT issue FROM `un_lhc` WHERE lottery_type=7 ORDER BY issue DESC LIMIT 1";
							$reIssue = Db::instance('db1')->single($sql_issue);
							$redis->set('lhc_issue',$reIssue);
							$lhcIssue = $redis->get('lhc_issue');
						}
						deinitCacheRedis($redis);
						//跨年
						if(strpos($info['issue'],'001')!==false){
							$lhcIssue = date('Y').'000';
						}

						lg('check_lhc_issue',var_export(array(
							'$sql_issue'=>$sql_issue,
							'$reIssue'=>$reIssue,
							'$lhcIssue'=>$lhcIssue,
							'$info[\'issue\']'=>$info['issue'],
						),1));

						if(($lhcIssue+1) != $info['issue']){ //期号有误
							$message['content'] = '当前投注期号有误，请重试';
							$message['is_popup_msg'] = '1';
							Gateway::sendToUid($uid, json_encode($message));
							return false;
						}
					}

					$hetme = microtime(1);
					lg('betting_debug_time',var_export(array(
						'$uid'=>$uid,
						'彩种'=>$data['lotteryType'],
						'玩法'=>$data['way'],
						'六合彩期号验证耗时'=>runtime($hetme,$hbtime),
					),1));

					//如果未获取到期号
					if($info['issue'] == 0){
						Gateway::sendToUid($uid, json_encode($message));
						return;
					}
					$time = time();
					//如果处于封盘
					if($info['sealingTim'] >= ($info['date']- $time)){
						$message['content'] = '当前不在投注时间内，不能投注';
						$message['is_popup_msg'] = '1';
						Gateway::sendToUid($uid, json_encode($message));
						return;
					}

					$hbtime = microtime(1);
					//判断投注的玩法是否是正确的
					$sql = "select way,type from un_odds where lottery_type='{$lotteryType}' and room='{$roomid}'{$whereSql}";
					lg('betting_debug','玩法SQL::'.$sql);

					$res = Db::instance('db1')->query($sql);
					if(empty($res)){
						$message['content'] = '未查询到此彩种相关玩法，禁止投注';
						$message['is_popup_msg'] = '1';
						Gateway::sendToUid($uid, json_encode($message));
						return false;
					}

					$hetme = microtime(1);
					lg('betting_debug_time',var_export(array(
						'$uid'=>$uid,
						'彩种'=>$data['lotteryType'],
						'玩法'=>$data['way'],
						'判断投注的玩法合法性耗时'=>runtime($hetme,$hbtime),
					),1));

					$allway = array();
					$num_way = [];
					foreach ($res as $k=>$v){
						$allway[] = $v['way'];
						if ($v['type'] == 1) {
							$num_way[] = $v['way'];
						}
					}

					$hbtime = microtime(1);
					//车号数量控制
					$num_limit = self::numLimitBetting($lotteryType,$way,$uid,$info['issue']);
					if(!empty($num_limit)){
						$message['content'] = '投注失败，每期'.$num_limit['limitWayStr'].'投注个数不能超过('.$num_limit['max'].'个)';
						$message['is_popup_msg'] = '1';
						Gateway::sendToUid($uid, encode($message));
						return false;
					}

					$hetme = microtime(1);
					lg('betting_debug_time',var_export(array(
						'$uid'=>$uid,
						'彩种'=>$data['lotteryType'],
						'玩法'=>$data['way'],
						'车号数量控制耗时'=>runtime($hetme,$hbtime),
					),1));


					$hbtime = microtime(1);
					//投注本期单点数字个数
					$totalZu  = 0;
					foreach ($way as $k=>$v) {

						$totalZu ++;

						$arr =explode('_',$v); //冠亚两号一起时
						if(count($arr)==3){
							$_1 =(int)$arr[1];
							$_2 =(int)$arr[2];
							if($_1<11 && $_2<11 && $_1>0 && $_2>0){
								$v = $arr[0];
							}
						}

						lg('lhc_betting_log','$arr'.var_export(array('$arr'=>$arr,'$lotteryType'=>$lotteryType,'$arr[0]'=>$arr[0]),1));
						if(in_array($lotteryType,array(7,8))){ //六合彩彩种
							//常规玩法检验
							if(!in_array($arr[0],array('三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中','二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中'))){
								if(!in_array($v, $allway)){
									$message['content'] = '您的投注中有非法投注，禁止投注';
									$message['is_popup_msg'] = '1';
									Gateway::sendToUid($uid, json_encode($message));
									return false;
								}
							}else{ //多注玩法检验
								//验证是否有重复号码 1,2,3 1,1,3
								$tmpArr = explode(',',$arr[1]);

								lg('lhc_betting_log',var_export(array(
									'$tmpArr'=>$tmpArr,
									'count($tmpArr)'=>count($tmpArr),
									'count(array_unique($tmpArr))'=>count(array_unique($tmpArr)),
									'count($tmpArr) != count(array_unique($tmpArr))'=>(count($tmpArr) != count(array_unique($tmpArr))),
								),1));

								if(count($tmpArr) != count(array_unique($tmpArr))){
									$message['content'] = '您的投注中有非法投注，禁止投注';
									$message['is_popup_msg'] = '1';
									Gateway::sendToUid($uid, json_encode($message));
									return false;
								}

								lg('lhc_betting_log', var_export(array('$v' => $v), 1));
								$waysArr = explode(',', $arr[1]); //传进来的玩法
								$len = count($waysArr);
								$check_data = self::lhcCheck($arr[0], $len); //检验个数
								lg('lhc_betting_log', var_export(array('$check_data' => $check_data), 1));
								if (!empty($check_data)) {
									$message['content'] = $check_data['msg'];
									$message['is_popup_msg'] = '1';
									Gateway::sendToUid($uid, json_encode($message));
									return false;
								}

								if (in_array($arr[0], array('三中二', '三全中', '二全中', '二中特', '特串', '二肖连中', '三肖连中', '四肖连中', '二肖连不中', '三肖连不中', '四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中'))) {
									//单注金额，总额和注数关系验证
									$preArr = array(
										'三中二' => 3,
										'三全中' => 3,
										'二全中' => 2,
										'二中特' => 2,
										'特串' => 2,
										'二肖连中' => 2,
										'三肖连中' => 3,
										'四肖连中' => 4,
										'二肖连不中' => 2,
										'三肖连不中' => 3,
										'四肖连不中' => 4,
										'五不中' => 5,
										'六不中' => 6,
										'七不中' => 7,
										'八不中' => 8,
										'九不中' => 9,
										'十不中' => 10,
									);
									$zu = self::zushu($len, $preArr[$arr[0]]);
									$totalZu = $totalZu + $zu - 1;
									if ($single_money[$k] * $zu != $money[$k]) { //总额 单注 注数
										$message['content'] = '金额有误';
										$message['is_popup_msg'] = '1';
										Gateway::sendToUid($uid, json_encode($message));
										return false;
									}

									foreach ($waysArr as $_wv) {
										if (!in_array($arr[0] . '_' . $_wv, $allway)) {
											$message['content'] = '您的投注中有非法投注，禁止投注';
											$message['is_popup_msg'] = '1';
											Gateway::sendToUid($uid, json_encode($message));
											return false;
										}
									}
								}

								if (in_array($arr[0], array('二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中'))) { //六合彩多注
									//单注金额，总额和注数关系验证
									$preArr = array(
										'二尾连中' => 2,
										'三尾连中' => 3,
										'四尾连中' => 4,
										'二尾连不中' => 2,
										'三尾连不中' => 3,
										'四尾连不中' => 4,
									);
									$zu = self::zushu($len, $preArr[$arr[0]]);
									$totalZu = $totalZu + $zu - 1;
									if ($single_money[$k] * $zu != $money[$k]) { //总额 单注 注数
										$message['content'] = '金额有误';
										$message['is_popup_msg'] = '1';
										Gateway::sendToUid($uid, json_encode($message));
										return false;
									}

									foreach ($waysArr as $_wv) {
										if (!in_array($arr[0] . '_' . $_wv . '尾', $allway)) {
											$message['content'] = '您的投注中有非法投注，禁止投注';
											$message['is_popup_msg'] = '1';
											Gateway::sendToUid($uid, json_encode($message));
											return false;
										}
									}
								}
							}
						}else{ //非六合彩彩种
							if(!in_array($v, $allway)){
								$message['content'] = '您的投注中有非法投注，禁止投注';
								$message['is_popup_msg'] = '1';
								Gateway::sendToUid($uid, json_encode($message));
								return false;
							}
						}
					}
					$hetme = microtime(1);
					lg('betting_debug_time',var_export(array(
						'$uid'=>$uid,
						'彩种'=>$data['lotteryType'],
						'玩法'=>$data['way'],
						'本期单点数字个数限制耗时'=>runtime($hetme,$hbtime),
					),1));


					//游戏币比例
					$RmbRatio = self::getConfig("rmbratio",'value');

					$hbtime = microtime(1);
					//投注判断
					$res = self::getReverseBetting($uid,$groupid,$roomid,$info['issue'],$way,$money,$RmbRatio);
					if($res['control']){
						$message['content'] = $res['content'];
						$message['is_popup_msg'] = '1';
						Gateway::sendToUid($uid, json_encode($message));
						return;
					}

					$hetme = microtime(1);
					lg('betting_debug_time',var_export(array(
						'$uid'=>$uid,
						'彩种'=>$data['lotteryType'],
						'玩法'=>$data['way'],
						'逆向投注判断耗时'=>runtime($hetme,$hbtime),
					),1));

					$hbtime = microtime(1);
					//判断账户余额是否小于当前投注总额
					$currentMoney = array_sum($money);
					lg('betting_log',var_export(array(
						'$money'=>$money,
						'$currentMoney'=>$currentMoney,
						'bcdiv($currentMoney,$RmbRatio,2)'=>bcdiv($currentMoney,$RmbRatio,2),
					),1));
					$currentMoney = bcdiv($currentMoney,$RmbRatio,2);
					$sql = "SELECT money FROM `un_account` WHERE `user_id` = '{$uid}'"; //要查主库
					$account = Db::instance('db1')->single($sql);
					if (bccomp($currentMoney, $account, 2) == 1) {
						$message['content'] = "您的余额不足，请充值";
						$message['has_not_money'] = '1';
						$message['is_popup_msg'] = '1';
						Gateway::sendToUid($uid, json_encode($message));
						return;
					}

					$hetme = microtime(1);
					lg('betting_debug_time',var_export(array(
						'$uid'=>$uid,
						'彩种'=>$data['lotteryType'],
						'玩法'=>$data['way'],
						'账户余额是否小于当前投注总额耗时'=>runtime($hetme,$hbtime),
						'备注::这里是查数据库的实时余额',
					),1));

					$rbtime = microtime(1);
					//生成订单
					Db::instance('db1')->beginTrans();
					try {
						$ddsqlarr =[];
						$tempAccount = $account;
						$orderArr = array();
						foreach ($way as $k => $v){
							unset($order_no); //防止重复流水号
							//获取是否跟投标识
							$ext_a = (int)$data['ext_a'] == 1 ? self::getRandomString(6) : '' ;
							//后面补0,防止重复
							$order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);
							lg('betting_log',var_export(array(
								'$order_no'=>$order_no,
								'$orderArr'=>$orderArr,
								'in_array($order_no,$orderArr)'=>in_array($order_no,$orderArr),
							),1));
							if(in_array($order_no,$orderArr)){ //防止重复流水号
								sleep(1);
								$order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);
								lg('betting_log',var_export(array(
									'有重复流水号，延时一秒后执行得到的结果',
									'$order_no'=>$order_no,
									'$orderArr'=>$orderArr,
									'in_array($order_no,$orderArr)'=>in_array($order_no,$orderArr),
								),1));
							}
							$orderArr[] = $order_no;  //新增
							$tzje = bcdiv($money[$k], $RmbRatio, 2); //投注金额
							$dzje = bcdiv($single_money[$k], $RmbRatio, 2); //单注金额
							$tempAccount = bcsub($tempAccount, $tzje, 2); //当前金额
							$ddsqlarr[] = array(
								'lottery_type' => $lotteryType,
								'room_no' => $roomid,
								'order_no' => $order_no,
								'user_id' => $uid,
								'issue' => $info['issue'],
								'addtime' => $time,
								'way' => $v,
								'money' => $tzje,
								'single_money' => $dzje,
								'reg_type' => $regType,
								'win_stop' => $win_stop,
								'ext_b' => $ext_a,

								//生成校验值
								'whats_val' => mixVal($order_no, $tzje, $time, $v),
							);
							$ddsqlarr_audit[] = array(
								'lottery_type' => $lotteryType,
								'room_no' => $roomid,
								'order_no' => $order_no,
								'user_id' => $uid,
								'issue' => $info['issue'],
								'addtime' => $time,
								'way' => $v,
								'money' => $tzje,
							);
							$zjsqlarr[] = array(
								'order_num' => $order_no,
								'user_id' => $uid,
								'type' => 13,
								'addtime' => $time,
								'money' => $tzje,
								'use_money' => $tempAccount,
								'remark'=>"用户投注".$tzje,
								'reg_type' => $regType
							);
						}

						lg('betting_log',var_export(array(
							'$uid'=>$uid,
							'$account::投注前可用余额'=>$account,
							'$currentMoney::投注总额'=>$currentMoney,
							'$tempAccount::投注后可用余额'=>$tempAccount,
							'aa'=>$tempAccount != bcsub($account, $currentMoney, 2),
						),1));

						//$currentMoney 投注总额
						//$account 投注前可用余额
						//$tempAccount 投注后可用余额

						if($tempAccount != bcsub($account, $currentMoney, 2)){
//                throw new Exception('更新失败!3');
							//TODO 调试
							lg('lottery_err_log', 'NOTICE 游戏类型: '.$lotteryType.' 期号：'.$info['issue'].' 用户id：'.$uid.' 投注金额有差异:'.$tempAccount.' | '.bcsub($account, $currentMoney, 2));
						}

						//再查余额
						$sql_acc = "SELECT money-{$currentMoney} AS money FROM un_account WHERE user_id={$uid}";
						$acc_res = Db::instance('db1')->single($sql_acc);
						lg('betting_log',var_export(array(
							'$sql_acc'=>$sql_acc,
							'$acc_res'=>$acc_res,
						),1));
						if($acc_res<0) throw new Exception('更新失败!5');


						$sql = "UPDATE `un_account` SET `money` = money-'{$currentMoney}' WHERE `user_id` = {$uid}"; //出于并发考虑
						$ret = Db::instance('db1')->query($sql);
						if (empty($ret)) throw new Exception('更新失败!1');
						$sql = self::insert('un_orders', $ddsqlarr);
						$inid = Db::instance('db1')->query($sql);

						$sql = self::insert('un_orders_audit', $ddsqlarr_audit);
						$inid_audit = Db::instance('db1')->query($sql);
						if ($inid){
							$sql = self::insert('un_account_log',$zjsqlarr);
							$ret =  Db::instance('db1')->query($sql);
							if (empty($ret)) throw new Exception('更新失败!2');

							$sql = "SELECT MAX(award_state) as state,issue FROM `un_orders` WHERE user_id={$uid} AND room_no={$roomid} AND award_state <> 0 GROUP by issue order by issue desc";
							$sy = Db::instance('db1')->query($sql);
							$sya = array(1 => 0, 2 => 0);
							if ($sy) {
								$a = 0;
								foreach ($sy as $v) {
									if ($a && $v['state'] == $a) {
										$sya[$v['state']]++;
									} elseif ($a && $v['state'] != $a) {
										break;
									} else {
										$sya[$v['state']]++;
										$a = $v['state'];
									}
								}
							}


							$sql = "select count(id) from un_orders WHERE  user_id={$uid} AND room_no={$roomid} AND issue={$info['issue']} AND state=0 AND chase_number = ''";
							$count = Db::instance('db1')->single($sql);
							$message['commandid'] = 3007;
							$message['uid'] = $uid;
							$message['nickname'] = $username;
							$message['avatar'] = $avatar;
							$message['way'] = $way;
							$message['issue'] = $info['issue'];
							// $message['time'] = date("H:i:s",$time);              //旧版时间显示
							$message['time'] = date('Y-m-d H:i', $time);            //新版时间显示
//                $message['money'] = array_sum($money);
							$message['money'] = $money;
							$message['total_money'] = array_sum($money);
//                $message['single_money'] = array_sum($money);
							$message['single_money'] = $single_money;  //六合彩用的单注
							$message['total_zushu'] = $totalZu;
							$message['count'] = $count;
							$message['order_no'] = $orderArr;
							$message['lose'] = $honor['status']?$sya[1]:'';
							$message['won'] = $honor['status']?$sya[2]:'';
							$message['content'] = '';
							$message['sort'] = $honor['sort'];
							$message['honor_status'] = $honor['honor_status'];
							$data=array( //调用双活接口
								'type'=>'betting_group',
								'id'=>$roomid,
								'json'=>encode($message),
							);
							$send = array('commandid' => 3010, 'money' => convert1(bcsub($account, $currentMoney, 2)));
							$data1=array( //调用双活接口
								'type'=>'betting',
								'id'=>$uid,
								'json'=>encode($send),
							);
							var_dump(array(
								'待调用双活'
							));
							lg('double_live','用户类型::'.$regType.',信息1::'.encode($data).',信息2::'.encode($data1));
							$wbtime = microtime(1);
							if(in_array($regType,array(/*11, 暂不含11，过滤掉假人*/9))){ //屏蔽游客和机器人
								Gateway::sendToGroup($roomid, json_encode($data));
								Gateway::sendToUid($uid, json_encode($data1));
							}else{
								Gateway::sendToGroup($roomid, json_encode(decode($data['json'])));
								Gateway::sendToUid($uid, json_encode(decode($data1['json'])));
								//send_home_data($data);
								//send_home_data($data1);
							}
							$wetme = microtime(1);
							lg('betting_debug_time',var_export(array(
								'$uid'=>$uid,
								'彩种'=>$lotteryType,
								'玩法'=>$tway,
								'调用Workerman通知前端耗时'=>runtime($wetme,$wbtime),
							),1));
							var_dump(array(
								'调用双活完成',
								'$data'=>$data,
								'$data1'=>$data1,
							));
						}else{
							throw new Exception('更新失败!3');
						}
						Db::instance('db1')->commitTrans();

//						$lotteryType = $data['lotteryType'];
//						$tway = $data['way'];

						$hretme = microtime(1);
						lg('betting_debug_time',var_export(array(
							'$uid'=>$uid,
							'彩种'=>$lotteryType,
							'玩法'=>$tway,
							'订单入库耗时'=>runtime($hretme,$rbtime),
						),1));

						$jetme = microtime(1);
						lg('betting_debug_time',var_export(array(
							'$uid'=>$uid,
							'彩种'=>$lotteryType,
							'玩法'=>$tway,
							'总耗时'=>runtime($jetme,$jbtme),
						),1));

						//删除刷单间隔标识
						$redis = initCacheRedis();
						lg('bettin_orders_log','删除刷单间隔标识::'.$co_str);
						$redis->del($co_str);
						deinitCacheRedis($redis);
						var_dump(array(
							'完成',
							'$co_str'=>$co_str,
						));
						return;
					} catch (Exception $err) {
						Db::instance('db1')->rollBackTrans();
						$send = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "系统错误，请联系客服人员。");
						Gateway::sendToUid($uid, json_encode($send));
						return;
					}
					/// 666666666666666666666666666666666666
//                    //signa('?m=api&c=workerman&a=betting', $data);
//                    return;
				}
				$message = array('commandid' => 3004, 'nickname' => '', 'content' => "信息错误。");
				Gateway::sendToClient($client_id, json_encode($message));
				return;

			case '3009':
				if ($_SESSION['userid'] && $_SESSION['userid'] == $message_data['uid']) {
					$data = array(
						'lottery_type' => $_SESSION['lottery_type'],
						'room_id' => $_SESSION['roomid'],
						'api_id' => '3009',
						'uid' => $_SESSION['userid'],
					);
					signa('?m=api&c=workerman&a=cancal_orders', $data); //直接用接口撤单

				}
				return;
			case '3016':
				if ($_SESSION['userid'] && $_SESSION['userid'] == $message_data['uid'] && $message_data['order_no']) {
					$data = array(
						'lottery_type' => $_SESSION['lottery_type'],
						'api_id' => '3016',
						'room_id' => $_SESSION['roomid'],
						'uid' => $_SESSION['userid'],
						'order_no' => $message_data['order_no'],
					);
					$ret = signa('?m=api&c=workerman&a=cancal_orders', $data); //直接用接口撤单

					Gateway::sendToClient($client_id, $ret);
				}
				break;
			case '3017':
			    lg('chat.log','tcp::进入3017');
				if ($message_data['uid'] && $message_data['roomid'] && $message_data['lottery_type']) {
					$data = array(
						'type' => 'tcp',
						'userid' => $message_data['uid'],
						'lotteryType' => $message_data['lottery_type'],
						'roomid' => $message_data['roomid'],
						'offSet' => isset($message_data['offSet'])?$message_data['offSet']:'', //订单过多，设置偏移量
					);
					signa('?m=api&c=workerman&a=getBettingData', $data);
					//signa('?m=api&c=workerman&a=getOnceAgain', $data);//获取再博一次参数
					return;
				}
				break;
			case '3019':
				//追号
				if ($_SESSION['userid'] && $message_data['nickname'] && !empty($message_data['data']) && $message_data['avatar']) {
					$data = array(
						'userid' => $_SESSION['userid'],
						'lotteryType' => $_SESSION['lottery_type'],
						'roomid' => $_SESSION['roomid'],
						'win_stop' => $message_data['win_stop'],
						'data' => json_encode($message_data['data'], JSON_UNESCAPED_UNICODE),
						'type' => 0,
					);
					signa('?m=api&c=workerman&a=chase', $data);
					return;
				}
				break;
			case '3021':
				//追号生成判断
				if ($_SESSION['userid'] && $message_data['nickname'] && !empty($message_data['data']) && $message_data['avatar']) {
					$data = array(
						'userid' => $_SESSION['userid'],
						'lotteryType' => $_SESSION['lottery_type'],
						'roomid' => $_SESSION['roomid'],
						'data' => json_encode($message_data['data'], JSON_UNESCAPED_UNICODE),
						'type' => 1,
					);
					signa('?m=api&c=workerman&a=chase', $data);
					return;
				}
				break;
		}
	}

	/**
	 * 当客户端断开连接时
	 * @param integer $client_id 客户端id
	 */
	public static function onClose($client_id)
	{
	    lg('chat.log','退出操作::'.json_encode($_SESSION));
		if ($_SESSION['userid']) {
			unset($_SESSION['userid']);
			unset($_SESSION['reg_type']);
			unset($_SESSION['roomid']);
			unset($_SESSION['lottery_type']);
			unset($_SESSION['time']);
		}
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
		$info = self::getQihao($lottery_type, $roomid);

		$data = array(
			'commandid' => 3001,
			'time' => $info['time'],
			'issue' => $info['issue'],
			'sealingTim' => $info['sealingTim'],
			'stopOrSell' => $info['stopOrSell'],
			'stopMsg' => isset($info['msg'])?$info['msg']:'',
			'lotteryType' => $info['lotteryType'],
		);
		return $data;
	}

	/**
	 * @param int $lottery_type
	 * @param int $roomid 房间ID
	 * @return mixed
	 *
	 */
	public static function getQihao($lottery_type, $roomid = 0)
	{
		$nowtime = time();
		//停售时间段
		$stopStartTime = '23:59:59';//停售开始时间
		$stopEndTime = '00:00';//停售结束时间
		$stopTime = '0';//当天时间段停售 0 第二天停售 86400
		$stopConfig = null;
		//开奖间隔 停售配置 停售时间段
		switch ($lottery_type){
			case 1:
                $space = 300;
                $stopConfig = 'xy28_stop_or_sell';
                $stopStartTime = '23:55';
                $stopEndTime = '09:00';
                $stopTime = 86400;
                break;
            case 2:
                $space = 1200;
                $stopConfig = 'bjpk10_stop_or_sell';
                $stopStartTime = '23:55';
                $stopEndTime = '09:00';
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
		$first = $redis->get("QiHaoFirst".$lottery_type);
		$last = $redis->get("QiHaoLast".$lottery_type);
		//返回信息
		$data = array(
			'issue' => 0,
			'date' => 0,
			'time' => 0,
			'QiHaoFirst' => json_decode($first,true),
			'QiHaoLast' => json_decode($last,true),
		);


		//如果不在售彩时间段
		$lottery = self::getRedisHashValues('LotteryType:'.$lottery_type,'config');
		$lottery_config=json_decode($lottery,true);

		$start_time = strtotime($lottery_config['start_time']);
		$end_time = strtotime($lottery_config['end_time']);
		$stop_start_time = strtotime($stopStartTime);
		$stop_end_time = strtotime($stopEndTime) + $stopTime;

		//停售时间不在当天时间段的特殊处理
		if($end_time <= $start_time){
			$specialTime = 86400;
			$start_time -= $specialTime;
			$end_time += $specialTime;
		}


		if($nowtime < $start_time || $nowtime > $end_time || ($nowtime > $stop_start_time && $nowtime < $stop_end_time)){
			if($lottery_type==3){
				$data['msg'] = '停售时间： '.$lottery_config['end_time'].'-'.$lottery_config['start_time'];
			}else{
				$data['msg'] = '已停售，售彩时间： '.$lottery_config['start_time'].'-'.$lottery_config['end_time'];
			}
            $tip = Db::instance('db1')->single("select tip from un_lottery_type WHERE id = $lottery_type");
            if($tip!="") $data['msg'] = $tip;
			$data['sealingTim'] = 0;
			$data['lotteryType'] = $lottery_type;
			$data['stopOrSell'] = 2;
			if($lottery_type==3){
				lg('model_get_qihao',var_export(array('不在时间内','$lottery_config'=>$lottery_config,'$data'=>$data,'$nowtime'=>$nowtime,'$start_time'=>$start_time,'$end_time'=>$end_time,'$stop_start_time'=>$stop_start_time,'$stop_end_time'=>$stop_end_time,'($nowtime < $start_time || $nowtime > $end_time || ($nowtime > $stop_start_time && $nowtime < $stop_end_time))'=>($nowtime < $start_time || $nowtime > $end_time || ($nowtime > $stop_start_time && $nowtime < $stop_end_time))),1));
			}
			return $data;
		}

		//如果后台设置停止售彩
		$config_res=self::getConfig($stopConfig,array('value'));
		$config_config=json_decode($config_res['value'],true);
//        if($lotteryType==3){
//            lg('getQiaHao_stopOrSell','前'.var_export(array('$config_config'=>$config_config),1));
//        }
		if($config_config['status']==2){

			$data['msg'] = $config_config['title'];
			$data['sealingTim'] = 0;
			$data['lotteryType'] = $lottery_type;
			$data['stopOrSell'] = 2;
			if($lottery_type==3){
				lg('model_get_qihao',var_export(array('后台停售','$data'=>$data,'$config_config'=>$config_config),1));
			}
			return $data;
		}

		//todo
		//房间停售 封盘时间
		$closure_time = 0;
		if($roomid){
			//TODO: 后续完善,暂无房间停售
			//封盘时间
			$closure_time = self::getRedisHashValues('allroom:'.$roomid,'closure_time');
			$closure_time = $closure_time?$closure_time:0;
		}

		$QiHao = $redis->lRange("QiHaoIds".$lottery_type, 0, -1);

		foreach ($QiHao as $v){
			$res = json_decode($v,true);
			if($res['date'] <= $nowtime){
				//将对应的键删除
				$redis->Lrem("QiHaoIds".$lottery_type, $v);
			}else{
				if($lottery_type==7){ //六合彩单独处理
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
			$data = self::setqihao($lottery_type,$nowtime);
		}
		$data['sealingTim'] = $closure_time;
		$data['lotteryType'] = $lottery_type;
		$data['stopOrSell'] = 1;
		if($nowtime < $start_time || $nowtime > $end_time || ($nowtime > $stop_start_time && $nowtime < $stop_end_time)){
			$data['stopOrSell'] = 2;
		}else{
			if($data['issue']==0){
				if($lottery_type==3){ //update 20180621
					$data['msg'] = '停售时间： '.$lottery_config['end_time'].'-'.$lottery_config['start_time'];
				}else{
					$data['msg'] = '已停售，售彩时间： '.$lottery_config['start_time'].'-'.$lottery_config['end_time'];
				}
                $tip = Db::instance('db1')->single("select tip from un_lottery_type WHERE id = $lottery_type");
                if($tip!="") $data['msg'] = $tip;
				$data['sealingTim'] = 0;
				$data['lotteryType'] = $lottery_type;
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

//        if($lotteryType==100) return false;


		//开奖间隔 数据源
		switch ($lotteryType){
			case 1:
				$space = 300;
				$data = @file_get_contents('xy28_qihao.json'); //获取数据
				break;
			case 2:
				$space = 300;
				$data = @file_get_contents('bjpk10_qihao.json'); //获取数据
				break;
			case 3:
				$space = 210;
				$data = @file_get_contents('jnd28_qihao.json'); //获取数据
				break;
			case 4:
				$space = 300;
				$data = @file_get_contents('xyft_qihao.json'); //获取数据
				break;
			case 5:
				if(date("H")>21){ //夜场
					$space = 300;
				}else{
					$space = 600;
				}
				$data = @file_get_contents('cqssc_qihao.json'); //获取数据
				break;
			case 6:
				$space = 180;
				$data = @file_get_contents('sfc_qihao.json'); //获取数据
				break;
			case 7:
				$space = 180;
				$data = @file_get_contents('lhc_qihao.json'); //获取数据
				break;
			case 8:
				$space = 300;
				$data = @file_get_contents('jslhc_qihao.json'); //获取数据
				break;
			case 9:
				$space = 180;
				$data = @file_get_contents('jssc_qihao.json'); //获取数据
				break;
			case 10:
				$space = 300;
				$data = @file_get_contents('nn_qihao.json'); //获取数据
				break;
			case 11:
				$space = 60;
				$data = @file_get_contents('ffc_qihao.json'); //获取数据
				break;
			case 21:
                $space = 300;
                $data = @file_get_contents('hlft_qihao.json'); //获取数据
                break;
			case 13:
				$space = 300;
				$data = @file_get_contents('sb_qihao.json'); //获取数据
				break;
			case 14:
				$space = 60;
				$data = @file_get_contents('ffpk10_qihao.json'); //获取数据
				break;
			case 15:
                $space = 180;
                $data = @file_get_contents('xjp28_qihao.json'); //获取数据
                break;
			case 16:
                $space = 60;
                $data = @file_get_contents('rd28_qihao.json'); //获取数据
                break;
			case 17:
                $space = 300;
                $data = @file_get_contents('cq28_qihao.json'); //获取数据
                break;
			case 18:
                $space = 60;
                $data = @file_get_contents('tx28_qihao.json'); //获取数据
                break;
			case 19:
                $space = 60;
                $data = @file_get_contents('txffc_qihao.json'); //获取数据
                break;
			case 20:
                $space = 60;
                $data = @file_get_contents('qqffc_qihao.json'); //获取数据
                break;
			case 21:
                $space = 300;
                $data = @file_get_contents('hlft_qihao.json'); //获取数据
                break;
			case 22:
                $space = 300;
                $data = @file_get_contents('jsk3_qihao.json'); //获取数据
                break;
			case 23:
                $space = 300;
                $data = @file_get_contents('gxk3_qihao.json'); //获取数据
                break;
            case 22:
                $space = 60;
                $data = @file_get_contents('hn1fc_qihao.json'); //获取数据
                break;
			case 23:
                $space = 300;
                $data = @file_get_contents('hn5fc_qihao.json'); //获取数据
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
	 * 控制昵称显示
	 * @param string $username 昵称
	 */
	public static function getNickname($username)
	{
		if(empty($username)){
			$username = time();
		}
		//初始化redis
		$redis = initCacheRedis();
		$tznickanme = $redis->hGetAll("Config:"."tznickname");
		//关闭redis链接
		deinitCacheRedis($redis);

		if($tznickanme){
			$strleng = mb_strlen($username)-1;
			$username = mb_substr($username,0,1,'utf-8')."***".mb_substr($username,$strleng,1,'utf-8');
		}
		return $username;
	}

	/**
	 * @param $way 多注玩法前缀
	 * @param $len 所投号码个数
	 * @return array|bool
	 *
	 */
	public static function lhcCheck($way,$len){
		$data = array(
			'三中二'=>[3,10],
			'三全中'=>[3,10],
			'二全中'=>[2,10],
			'二中特'=>[2,10],
			'特串'=>[2,10],
			'二肖连中'=>[2,8],
			'三肖连中'=>[3,8],
			'四肖连中'=>[4,8],
			'二肖连不中'=>[2,8],
			'三肖连不中'=>[3,8],
			'四肖连不中'=>[4,8],
			'五不中'=>[5,8],
			'六不中'=>[6,9],
			'七不中'=>[7,10],
			'八不中'=>[8,11],
			'九不中'=>[9,12],
			'十不中'=>[10,13],
			'二尾连中'=>[2,8],
			'三尾连中'=>[3,8],
			'四尾连中'=>[4,8],
			'二尾连不中'=>[2,8],
			'三尾连不中'=>[3,8],
			'四尾连不中'=>[4,8],
		);

		lg('lhc_betting_log',var_export(array('$data[$way]'=>$data[$way]),1));

		if(in_array($way,array_keys($data))){
			if($len<$data[$way][0] || $len>$data[$way][1]){
				return array('code'=>1,'msg'=>$way.'投注中含有非法投注，禁止投注');
			}
		}
		return false;
	}

	/**
	 * 投注控制
	 * @param $uid int 用户id
	 * @param $groupid int 用户组id
	 * @param $roomid int 房间id
	 * @param $issue int 期号
	 * @param $way array 玩法
	 * @param $money array 投注金额
	 * @param $RmbRatio int 金额比率
	 */
	public static function getReverseBetting($uid,$groupid,$roomid,$issue,$way,$money,$RmbRatio){
		//初始化redis
		$redis = initCacheRedis();
		$re = $redis->hget('Config:reverse_set','value');
		$data = decode($re);

		$lottery_type = $redis->hget("allroom:".$roomid,'lottery_type');
		deinitCacheRedis($redis);
//        lg('reverse_betting_log',"从redis中获取到的数据".var_export(array(
//                '$lottery_type'=>$lottery_type,
//                '$re'=>$re,
//            ),1));

		//逆向投注过滤（先查找在此彩种此期下注的订单）
		$sql = "SELECT way, money,room_no FROM un_orders WHERE user_id='{$uid}' AND lottery_type={$lottery_type} AND issue = '{$issue}' AND state = 0";
		lg('reverse_betting_log','查本期所有投注订单::SQL::'.$sql);
		$orders = Db::instance('db1')->query($sql);

		//总注
		$totalMoney = 0;
		//赋值给临时数组
		$temp = array();
		$tempRoom = array();
		$tempRoomCurrent = array();
		$totalTmp = array();

		$except_way = [
			"冠亚", "三中二", "三全中", "二全中", "二中特", "特串", "五不中", "六不中", "七不中", "八不中",
			"九不中", "十不中", "二尾连中", "三尾连中", "四尾连中", "二尾连不中", "三尾连不中", "四尾连不中", "二肖连中",
			"三肖连中", "四肖连中", "二肖连不中", "三肖连不中", "四肖连不中"
		];
		//以前投的
		foreach ($orders as $v) {
			$arr = explode('_',$v['way']); //多个号码一起时
			if (in_array($arr[0],$except_way)) {
				$v['way'] = $arr[0];
			}
			$temp[$v['way']] += $v['money'];
			if($v['room_no'] == $roomid){ //当前房间的
				$tempRoom[$v['way']] += $v['money'];
			}
			$totalMoney = bcadd($totalMoney,$v['money'],2);
		}

		//当前投注
		foreach ($way as $k => $v) {
			$arr = explode('_',$v); //多个号码一起时
			if (in_array($arr[0],$except_way)) {
				$v = $arr[0];
			}
			$temp[$v] += $money[$k];
			$tempRoomCurrent[$v] += $money[$k]; //当前投注金额
			$totalMoney = bcadd($totalMoney,$money[$k],2);
		}

		//合并投注
		foreach ($tempRoomCurrent as $ck=>$cv){
			$totalTmp[$ck] = bcadd($tempRoom[$ck],$cv,2);
		}

		if(!empty($data)){
			$wayKeys = array_keys($temp);
			$zeroKey = array_search(0,$wayKeys,true); //0 特殊处理 请查看in_array int 0
			if($zeroKey !== false) $wayKeys[$zeroKey] = '0';
			foreach ($data[$lottery_type] as $k=>$v){
				if($v['state'] == 1){
					$controlWayArr = explode(',',$v['data']);
					$num = count($controlWayArr);
					$jArr = array_intersect($wayKeys,$controlWayArr);
					if(!empty($jArr) && count($jArr)==$num){
						return array('control' => true, 'content' => "您的投注内容不符合本期投注规则，不能同时投注[".implode(',',$controlWayArr)."]");
					}
				}
			}
		}else{
			return array('control' => true, 'content' => "无法取到数据");
		}

		//获取房间投注控制
		$room = self::getRedisHashValues("allroom:".$roomid, array('upper','lower')); //逆向投注已改到上面，这里就不用取这个值'reverse'

		//投注总额下限限额控制
		if (!empty($room['lower'])) {
			$lower = bcmul($room['lower'],$RmbRatio,2);
			if (bccomp($lower,$totalMoney, 2) == 1) return array('control' => true, 'content' => "您的投注金额小于总注限额： ".$lower."元宝");
			foreach ($way as $k => $v) {
				if (bccomp($lower,$money[$k], 2) == 1) return array('control' => true, 'content' => "您的投注[{$v}]金额[{$money[$k]}]小于房间限额： ".$lower."元宝");
			}
		}

		//投注总额上限限额控制-投注玩法限额控制
		if(!empty($room['upper'])){
			$upper = json_decode($room['upper'],true);
			//投注总额上限限额控制
			if(!empty($upper['total_amount'])){
				$total_amount = bcmul($upper['total_amount'],$RmbRatio,2);
				if (bccomp($totalMoney, $total_amount, 2) == 1) return array('control' => true, 'content' => "您的投注金额大于总注限额： ".$total_amount."元宝");
			}
			//玩法限额
			if(!empty($upper['limit'])){
				foreach ($upper['limit'] as $v){
					$groupWayMoney = 0;
					foreach ($v['contact'] as $k => $c){
						//当前玩法
						foreach ($tempRoomCurrent as $tk=>$tv){
							if($c===$tk){
								$groupWayMoney = $tv;
								$oneSet = 0; //是否只有单条记录

								if (count($v['data']) == 1 && count($v['contact']) >= 1) {
									$limitMoney = bcmul($v['data'][0],$RmbRatio,2);
									$oneSet = 1;
								}else {
									$limitMoney = bcmul($v['data'][$k],$RmbRatio,2);
								}

//                              $liArrKeys = array_keys($v['data']);
								$liArrKeys = $v['contact'];
								lg('roomupper.log',print_r($v,true));
								lg('roomupper.log',print_r($v['data'],true));
								lg('roomupper.log',print_r($liArrKeys,true));
								if($uid== 1386) {
									lg('room_upper.log',var_export(array('$liArrKeys'=>$liArrKeys, 'k' => $k,'tk' => $tk, '$c'=>$c, '$v'=>$v, 'rmb' => $RmbRatio),true));
								}
								$liArrKeys = array_map(function ($p){
									return (string)$p;
								},$liArrKeys);

								lg('get_reverse_betting',var_export(array(
									'$v[\'data\']'=>$v['data'],
									'$v[\'contact\']'=>$v['contact'],
									'(in_array($tk,$liArrKeys) && bccomp($groupWayMoney, $limitMoney, 2) == 1 && $limitMoney != 0)'=>(in_array($tk,$liArrKeys) && bccomp($groupWayMoney, $limitMoney, 2) == 1 && $limitMoney != 0),
									'$oneSet'=>$oneSet,
								),1));

								if (in_array($tk,$liArrKeys) && bccomp($groupWayMoney, $limitMoney, 2) == 1 && $limitMoney != 0){
									if($oneSet==1){
										return array('control' => true, 'content' => "您的投注金额大于({$v['remark']}) ".$limitMoney."元宝");
									}else{
										return array('control' => true, 'content' => "您的投注金额大于({$v['remark']}:{$c}) ".$limitMoney."元宝");
									}
								}
							}
						}

						$groupWayMoney = 0;
						$limitMoney = 0;
						//合并后的玩法
						foreach ($totalTmp as $tk=>$tv){

							if($c===$tk){
								$groupWayMoney = $tv;
								$oneSet = 0; //是否只有单条记录

								if (count($v['data']) == 1 && count($v['contact']) > 1) {

									$oneSet = 1;

									$limitMoney = bcmul($v['data'][0],$RmbRatio,2);
									//这里写逻辑
									$jtmpWay = $v['contact'];
									$re = array_search($c,$jtmpWay);
									if($re !== false){
										unset($jtmpWay[$re]);
									}
									$jjson = '';
									foreach ($jtmpWay as $jk=>$jv){
										$jjson .= ",'".$jv."'";
									}
									$jjson = trim($jjson,',');
									$sql = "SELECT sum(money) as total FROM un_orders WHERE user_id='{$uid}' AND lottery_type={$lottery_type} AND issue = '{$issue}' AND state = 0 and way in ({$jjson})";
//                                    if($uid==3915) {
//                                        lg('lhc_wubuzhong_debug',var_export(array('$limitMoney'=>$limitMoney,'$re'=>$re,'$jjson'=>$jjson,'$sql'=>$sql),1));
//                                    }
									$wmoney = Db::instance('db1')->single($sql);
									$groupWayMoney = bcadd($groupWayMoney,$wmoney,2);

								}else {
									if (count($v['data']) == 1 && count($v['contact']) == 1) {
										$oneSet = 1;
									}
									$limitMoney = bcmul($v['data'][$k],$RmbRatio,2);
								}
								$liArrKeys = $v['contact'];
								$liArrKeys = array_map(function ($p){
									return (string)$p;
								},$liArrKeys);

//                                if($uid==3915) {
//                                    lg('lhc_wubuzhong_debug','合前后限制'.var_export(array('line'=>3887,'$c'=>$c,'$tk'=>$tk,'$tv'=>$tv,'$v[\'data\']'=>$v['data'],'$groupWayMoney'=>$groupWayMoney,'$limitMoney'=>$limitMoney,'$k'=>$k,'$v'=>$v,'$liArrKeys'=>$liArrKeys),1));
//                                }
								lg('get_reverse_betting',var_export(array(
									'合并后',
									'$v[\'data\']'=>$v['data'],
									'$v[\'contact\']'=>$v['contact'],
									'(in_array($tk,$liArrKeys) && bccomp($groupWayMoney, $limitMoney, 2) == 1 && $limitMoney != 0)'=>(in_array($tk,$liArrKeys) && bccomp($groupWayMoney, $limitMoney, 2) == 1 && $limitMoney != 0),
									'$oneSet'=>$oneSet,
								),1));
								if (in_array($tk,$liArrKeys) && bccomp($groupWayMoney, $limitMoney, 2) == 1 && $limitMoney != 0){
									if($oneSet==1){
										return array('control' => true, 'content' => "您的投注金额大于({$v['remark']}) ".$limitMoney."元宝");
									}else{
										return array('control' => true, 'content' => "您的投注金额大于({$v['remark']}:{$c}) ".$limitMoney."元宝");
									}
								}
							}
						}
					}
				}
			}
		}

		//获取会员组投注控制
		$group = self::getRedisHashValues("group:".$groupid, array('upper','lower','name','limit_state'));

		lg('bet_limit_log','是否设置限制::$group[\'limit_state\']::'.$group['limit_state']);
		if($group['limit_state'] == 1){
			//投注总额下限限额控制
			if (!empty($group['lower'])) {
				$lower = bcmul($group['lower'],$RmbRatio,2);
				if (bccomp($lower,$totalMoney, 2) == 1) return array('control' => true, 'content' => "您属于会员组({$group['name']}),您的投注金额小于该会员组限额： {$lower}元宝");
				foreach ($way as $k => $v) {
					if (bccomp($lower,$money[$k], 2) == 1) return array('control' => true, 'content' => "您属于会员组({$group['name']}),您的投注[{$v}]金额[{$money[$k]}]小于该会员组限额： ".$lower."元宝");
				}
			}

			//投注总额上限限额控制
			if (!empty($group['upper'])) {
				$upper = bcmul($group['upper'],$RmbRatio,2);
				if (bccomp($totalMoney, $upper, 2) == 1) return array('control' => true, 'content' => "您属于会员组({$group['name']}),您的投注金额超过该会员组限额： {$upper}元宝");
			}
		}
	}

	/**
	 * @param $a
	 * @param $b
	 * @return float|int
	 *
	 */
	public static function zushu($a, $b) {
		$topNum = 1;
		for($i=$a;$i>$a-$b;$i--){
			$topNum = $topNum*$i;
		}
		$botNum = 1;
		for($j = 1; $j <= $b; $j++){
			$botNum = $botNum*$j;
		}
		$dataSum =  $topNum / $botNum;
		return $dataSum;
	}

	public static function insertSql($table,$arr){
		$sql ="";
		$param_str = "";
		$first = true;
		foreach ($arr as $k=>$i){
			if($first) {
				$param_str .= "`$k`='$i'";
				$first = false;
			}else{
				$param_str .= ",`$k`='$i'";
			}
		}

		$sql = "INSERT INTO $table SET $param_str";
		return $sql;
	}

	/**
	 * @param $lotteryType 彩种
	 * @param $way 当前玩法
	 * @param $uid 用户ID
	 * @param $issue 期号
	 * @return array|bool 受限有值 ，不受限false
	 *
	 */
	public static function numLimitBetting($lotteryType,$way,$uid,$issue){
		$redis = initCacheRedis();

		$way = array_unique($way); //去重处理

		//车号限制
		if(in_array($lotteryType,array(7,8))){

			$nid = ($lotteryType==7)?"lhc_set_bet":"jslhc_set_bet";
			$configBetData = decode($redis->hget('Config:'.$nid,'value'));
			deinitCacheRedis($redis);

			//当前投注的玩法
			sort($way);
			$eArr = array(
				'特码A',
				'特码B',
				'正码A',
				'正码B',
				'正1特',
				'正2特',
				'正3特',
				'正4特',
				'正5特',
				'正6特',
				'尾数',
				'一肖',
				'特肖',
			);

			foreach ($configBetData as $config){
				if($config['status'] ==1 && $config['max']>0){
					$iArr = array();
					foreach ($way as $ck=>$cv) {
						$wayArr = explode('_', $cv);
						if(in_array($wayArr[0],array('尾数','一肖','特肖'))){ //'尾数','一肖','特肖'要单独处理
							if ($wayArr[0] == $config['name']) {
								$iArr[$wayArr[0]]++;
								lg('lhc_num_limit','当前玩法'.var_export(array('$cv'=>$cv,'$iArr[$wayArr[0]]'=>$iArr[$wayArr[0]],'$config[\'max\']'=>$config['max'],'$wayArr[0]'=>$wayArr[0]),1));
								if($iArr[$wayArr[0]] > $config['max']){ //超出值
									return array('max'=>$config['max'],'limitWayStr'=>$wayArr[0]);
									break;
								}
							}
						}else{
							if(in_array($wayArr[1],range(1,49))){
								if ($wayArr[0] == $config['name'] && in_array($wayArr[0], $eArr)) {
									$iArr[$wayArr[0]]++;
									lg('lhc_num_limit','当前玩法'.var_export(array('$cv'=>$cv,'$iArr[$wayArr[0]]'=>$iArr[$wayArr[0]],'$config[\'max\']'=>$config['max'],'$wayArr[0]'=>$wayArr[0]),1));
									if($iArr[$wayArr[0]] > $config['max']){ //超出值
										return array('max'=>$config['max'],'limitWayStr'=>$wayArr[0]);
										break;
									}
								}
							}
						}

					}
				}
			}

			//统计当前玩法个数
			$iArr=array();
			$full_arr=array();
			foreach ($way as $ck=>$cv) {
				$wayArr = explode('_', $cv);
				if(in_array($wayArr[0],array('尾数','一肖','特肖'))) { //'尾数','一肖','特肖'要单独处理
					$iArr[$wayArr[0]]['num']++;
					$iArr[$wayArr[0]]['way'] = $cv;
					lg('lhc_num_limit','统计当前玩法'.var_export(array('$cv'=>$cv,'$iArr[$wayArr[0]]'=>$iArr[$wayArr[0]],'$wayArr[0]'=>$wayArr[0]),1));
				}else{
					if(in_array($wayArr[1],range(1,49))){
						if (in_array($wayArr[0], $eArr)) {
							$iArr[$wayArr[0]]['num']++;
							$iArr[$wayArr[0]]['way'] = $cv;
							lg('lhc_num_limit','统计当前玩法'.var_export(array('$cv'=>$cv,'$iArr[$wayArr[0]]'=>$iArr[$wayArr[0]],'$wayArr[0]'=>$wayArr[0]),1));
						}
					}
				}
			}

			//库里存在的记录
			$sql_full = "select distinct way from un_orders where user_id=$uid and issue = {$issue} and state = 0 AND lottery_type = {$lotteryType} GROUP BY way";
			$re_full  = Db::instance('db1')->query($sql_full);
			foreach ($re_full as $fv){
				$full_arr[] = $fv['way'];
			}
			lg('lhc_num_limit','库里存在的记录'.var_export(array('$sql_full'=>$sql_full,'$re_full'=>$re_full,'$full_arr'=>$full_arr),1));

			//合并当前玩法和历史玩法
			foreach ($iArr as $ik=>$iv){
				$sql = "select way from un_orders where user_id=$uid and issue = {$issue} and state = 0 and way REGEXP '^{$ik}_' AND  lottery_type = {$lotteryType} GROUP BY way";
				$re  =  Db::instance('db1')->query($sql);
				if(!empty($re)){
					$len = count($re);
					$iArr[$ik]['num'] += $len;
					lg('lhc_num_limit','合并当前玩法和历史玩法'.var_export(array('$re'=>$re,'$sql'=>$sql,'$way'=>$way,'$len'=>$len,'$iArr[$ik]'=>$iArr[$ik]),1));
				}
			}

			foreach ($configBetData as $config){
				if($config['status'] ==1 && $config['max']>0){
					foreach ($iArr as $ik=>$iv){
						lg('lhc_num_limit',var_export(array('$ik'=>$ik,'$iv'=>$iv,'$config[\'name\']'=>$config['name'],'$way'=>$way,'$config[\'max\']'=>$config['max']),1));
						if(($ik==$config['name'] && $iv['num']>$config['max'] && !in_array($iv['way'],$full_arr))){
							return array('max'=>$config['max'],'limitWayStr'=>$ik);
							break;
						}
					}
				}
			}
		}

		if(in_array($lotteryType,array(5,6,11,19,20,24,25))){
			if($lotteryType==5){
				$nid = 'qcssc_set_bet';
			}

			if($lotteryType==6){
				$nid = 'sfc_set_bet';
			}

			if($lotteryType==11){
				$nid = 'ffc_set_bet';
			}
			
			if($lotteryType==19){
				$nid = 'txffc_set_bet';
			}
			
			if($lotteryType==20){
				$nid = 'qqffc_set_bet';
			}
          
            if($lotteryType==24){
				$nid = 'hn1fc_set_bet';
			}
			
			if($lotteryType==25){
				$nid = 'hn5fc_set_bet';
			}
//            $nid = ($lotteryType==5)?"qcssc_set_bet":"sfc_set_bet";
			$configBetData = decode($redis->hget('Config:'.$nid,'value'));
			deinitCacheRedis($redis);
			lg('num_limit',var_export(array('$configBetData[\'status\']'=>$configBetData['status'],'$configBetData[\'max\']'=>$configBetData['max']),1));
			if($configBetData['status'] ==1 && $configBetData['max']>0){ //开启这个功能 并且数值有限制
				//当前投注的玩法
				sort($way);
				$iArr = array();
				foreach ($way as $ck=>$cv) {
					if(preg_match('/^第.{3}球_\d$/',$cv)){
						$cv = preg_replace('/_\d$/','',$cv);
						$iArr[$cv]++;
						lg('num_limit','当前玩法'.var_export(array('$cv'=>$cv,'$iArr[$cv]'=>$iArr[$cv],'$configBetData[\'max\']'=>$configBetData['max']),1));
						if($iArr[$cv] > $configBetData['max']){ //超出值
							return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);
//                            return $cv;
							break;
						}
					}
				}

				//合并当前玩法和历史玩法
				$sql = "select way from un_orders where user_id=$uid and issue = {$issue} and state = 0 and way REGEXP '第.{3}球_[0-9]' AND  lottery_type = {$lotteryType} GROUP BY way";
				$re  =  Db::instance('db1')->query($sql);
				lg('num_limit',var_export(array('$re'=>$re,'$sql'=>$sql,'$way'=>$way),1));
				foreach ($re as $ok=>$ov){
					if(!in_array($ov['way'],$way)){
						$way[$ov['way']] = $ov['way'];
					}
				}
				$iArr = array();
				foreach ($way as $ck=>$cv) {
					if(preg_match('/^第.{3}球_\d$/',$cv)){
						$cv = preg_replace('/_\d$/','',$cv);
						$iArr[$cv]++;
						lg('num_limit',var_export(array('$iArr[$cv]'=>$iArr[$cv],'$cv'=>$cv,'$configBetData[\'max\']'=>$configBetData['max']),1));
						if($iArr[$cv] > $configBetData['max']){ //超出值
							return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);
//                            return $cv;
							break;
						}
					}
				}
			}
		}


		if(in_array($lotteryType,array(2,4,9,13,14,21))){
            $nid='';
            if($lotteryType==2){
                $nid = 'bjpk10_set_bet';
            }

            if($lotteryType==4){
                $nid = 'xyft_set_bet';
            }

            if($lotteryType==9){
                $nid = 'jssc_set_bet';
            }


            if($lotteryType==13){
                $nid = 'hlsb_set_bet';
            }

            if($lotteryType==14){
                $nid = 'ffpk10_set_bet';
            }
          
            if($lotteryType==21){
                $nid = 'hlft_set_bet';
            }

			$configBetData = decode($redis->hget('Config:'.$nid,'value'));
			deinitCacheRedis($redis);
			lg('num_limit_js',var_export(array('$configBetData[\'status\']'=>$configBetData['status'],'$configBetData[\'max\']'=>$configBetData['max']),1));
			if($configBetData['status'] ==1 && $configBetData['max']>0){ //开启这个功能 并且数值有限制
				//当前投注的玩法
				sort($way);
				$iArr = array();
				foreach ($way as $ck=>$cv) {
					if(preg_match('/^(冠军|亚军|第.{3}名|第.{3}骰)_([1-9]|10)$/',$cv)){
						$arrTmp = explode('_',$cv);
						$cv = $arrTmp[0];
						$iArr[$cv]++;
						lg('num_limit_js','当前玩法'.var_export(array('$cv'=>$cv,'$iArr[$cv]'=>$iArr[$cv],'$configBetData[\'max\']'=>$configBetData['max']),1));
						if($iArr[$cv] > $configBetData['max']){ //超出值
							return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);
							break;
						}
					}
				}

				//合并当前玩法和历史玩法
				$sql = "select way from un_orders where user_id=$uid and issue = {$issue} and state = 0 and way REGEXP '^(冠军|亚军|第.{3}名|第.{3}骰)_([1-9]|10)' AND  lottery_type = {$lotteryType} GROUP BY way";
				$re  =  Db::instance('db1')->query($sql);
				lg('num_limit_js',var_export(array('$re'=>$re,'$sql'=>$sql,'$way'=>$way),1));
				foreach ($re as $ok=>$ov){
					if(!in_array($ov['way'],$way)){
						$way[$ov['way']] = $ov['way'];
					}
				}
				$iArr = array();
				foreach ($way as $ck=>$cv) {
					if(preg_match('/^(冠军|亚军|第.{3}名|第.{3}骰)_([1-9]|10)/',$cv)){
						$arrTmp = explode('_',$cv);
						$cv = $arrTmp[0];
						$iArr[$cv]++;
						lg('num_limit_js',var_export(array('$iArr[$cv]'=>$iArr[$cv],'$cv'=>$cv,'$configBetData[\'max\']'=>$configBetData['max']),1));
						if($iArr[$cv] > $configBetData['max']){ //超出值
							return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);
							break;
						}
					}
				}
			}
		}

		if(in_array($lotteryType,array(10))){
			$nid = ($lotteryType==10)?"nn_set_bet":"";
			$configBetDataArr = decode($redis->hget('Config:'.$nid,'value'));
			deinitCacheRedis($redis);
			lg('num_limit_nn',var_export(array('$nid'=>$nid,'$configBetDataArr'=>$configBetDataArr),1));
			foreach ($configBetDataArr as $configBetData){
				if($configBetData['status'] ==1 && $configBetData['max']>0){ //开启这个功能 并且数值有限制
					//当前投注的玩法
					sort($way);
					$iArr = array();
					foreach ($way as $ck=>$cv) {
						//牛九
						if($configBetData['name']=='猜牛' && preg_match('/牛/',$cv)){
							$limitWayStr = $configBetData['name'];
							$iArr[$limitWayStr]++;
							lg('num_limit_nn','当前玩法'.var_export(array('$limitWayStr'=>$limitWayStr,'$cv'=>$cv,'$iArr[$limitWayStr]'=>$iArr[$limitWayStr],'$configBetData[\'max\']'=>$configBetData['max']),1));
							if($iArr[$limitWayStr] > $configBetData['max']){ //超出值
								return array('max'=>$configBetData['max'],'limitWayStr'=>$limitWayStr);
								break;
							}
						}

						//第一张_双
						if($configBetData['name']=='猜牌面' && preg_match('/^第.{3}张_[2-9]|10|A|J|Q|K$/',$cv)){
							//                        $cv = preg_replace('/_[2-9]|10|A|J|Q|K$/','',$cv);
							$arrTmp = explode('_',$cv);
							$cv = $arrTmp[0];
							$iArr[$cv]++;
							lg('num_limit_nn','当前玩法'.var_export(array('$cv'=>$cv,'$iArr[$cv]'=>$iArr[$cv],'$configBetData[\'max\']'=>$configBetData['max']),1));
							if($iArr[$cv] > $configBetData['max']){ //超出值
								return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);
								break;
							}
						}
					}

					//合并当前玩法和历史玩法
					$sql = "select way from un_orders where user_id=$uid and issue = {$issue} and state = 0 and way REGEXP '牛|(第.{3}张_[2-9]|10|A|J|Q|K)' AND  lottery_type = {$lotteryType} GROUP BY way";
					$re  =  Db::instance('db1')->query($sql);
					lg('num_limit_nn',var_export(array('$re'=>$re,'$sql'=>$sql,'$way'=>$way),1));
					foreach ($re as $ok=>$ov){
						if(!in_array($ov['way'],$way)){
							$way[$ov['way']] = $ov['way'];
						}
					}
					$iArr = array();
					foreach ($way as $ck=>$cv) {
						//牛九
						if($configBetData['name']=='猜牛' && preg_match('/牛/',$cv)){
							$limitWayStr = $configBetData['name'];
							$iArr[$limitWayStr]++;
							lg('num_limit_nn','当前玩法'.var_export(array('$limitWayStr'=>$limitWayStr,'$cv'=>$cv,'$iArr[$limitWayStr]'=>$iArr[$limitWayStr],'$configBetData[\'max\']'=>$configBetData['max']),1));
							if($iArr[$limitWayStr] > $configBetData['max']){ //超出值
								return array('max'=>$configBetData['max'],'limitWayStr'=>$limitWayStr);
								break;
							}
						}

						if($configBetData['name']=='猜牌面' && preg_match('/^第.{3}张_[2-9]|10|A|J|Q|K$/',$cv)){
							$arrTmp = explode('_',$cv);
							$cv = $arrTmp[0];
							$iArr[$cv]++;
							lg('num_limit_nn',var_export(array('$configBetData[\'name\']'=>$configBetData['name'],'$iArr[$cv]'=>$iArr[$cv],'$cv'=>$cv,'$configBetData[\'max\']'=>$configBetData['max']),1));
							if($iArr[$cv] > $configBetData['max']){ //超出值
								return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);
								break;
							}
						}
					}
				}
			}
		}
		return false;
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

/**
 * 验证签名 公钥加密
 * @param $url string 请求地址
 * @param $data array 传入参数
 */
function signa($url, $data)
{
	static $host, $signa, $config;
	if (empty($config)) {
		!defined('IN_SNYNI') && define("IN_SNYNI", 1);
		$config = require("../caches/config.php");
	}
	if (empty($host)) {
		$host = $config['api_host'];
	}
	if (empty($signa)) {
		$signa = $config['signa'];
	}
	//签名数据
	$key = $signa['key'];
	$secret_key = $signa['secret_key'];
	$param['timestamp'] = time();//时间戳
	$param['signature'] = md5(md5($param['timestamp']) . $secret_key);//签名
	$param['key'] = $key;//key
	$param['source'] = 0;//接口来源:1 ios;  2 安卓; 3 H5; 4 PC ; 0 服务器本身
	$param['project'] = 0;//项目来源(0:pc手游, 1:讯彩; 2其它;  [待存字典表])
	$param['method'] = "POST";
	$params = base64_encode(json_encode($param));

	//业务数据
	$encrypted = "";
	if (!empty($data)) {
		$datas = json_encode($data);
		$encrypted = dencrypt(base64_encode($datas), "ENCODE", $param['signature']);
	}
	//请求接口
	$res = curl_post($host . $url, array('param' => $params, 'data' => $encrypted));
	return $res;
}

/**
 * 加密解密处理
 * @param unknown_type $string 密文
 * @param unknown_type $operation 加密 或 解密
 * @param unknown_type $key 密匙
 * @return unknown
 */
function dencrypt($string, $operation = 'DECODE', $key = '')
{
	if (empty($string)) {
		return false;
	}
	$operation != 'ENCODE' && $string = base64_decode(substr($string, 16));  //如果是解密就截16位以后的字符 并base64解密
	$code = '';
	$key = md5($key); //md5密匙
	$keya = strtoupper(substr($key, 0, 16));      //截取新密匙的前16位并大写
	$keylen = strlen($key);                      //计算密匙长度
	$strlen = strlen($string);
	$rndkey = [];
	for ($i = 0; $i < 128; $i++) {
		$rndkey[$i] = ord($key[$i % $keylen]);  //生成128个加密因子  （按密匙中单个字符的ASCII 值）

	}
	for ($i = 0; $i < $strlen; $i++) {
		$code .= chr(ord($string[$i]) ^ $rndkey[$i * $strlen % 128]);  //用字条串的每个字符ASCII值和加密因子里的（当前循环次数*字符串长度 求于 128） 按位异或  最后 ASCII 值返回字符
	}
	return ($operation != 'DECODE' ? $keya . str_replace('=', '', base64_encode($code)) : $code);  // 如果是加密就截取新密匙的前16位并加上base64加密码生成的密文
}

/**
 * curl post
 * @param $url string 请求地址
 * @param $data array 传入参数
 * @param $header array 返回Header
 * @param $nobody array 返回body
 * @return mixed
 */
function curl_post($url, $data = [], $header = false, $nobody = false)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, $header);//返回Header
	curl_setopt($ch, CURLOPT_NOBODY, $nobody);//不需要内容
	curl_setopt($ch, CURLOPT_POST, true);//POST
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}


/**
 * 日志系统
 * @param $f 文件名
 * @param $s 数据
 */
function lg($f,$s) {
	$dirname=__DIR__.'/../../../log';
//    $dirname='/tmp';
	$fp = fopen($dirname.'/'.date('Y_m_d').'_'.$f,"a");
	fwrite($fp, date('Y-m-d H:i:s').'--------->'.$s."\n\n");
	fclose($fp);
}

// 清除转义($arr/$string)
function stripslashes_deep($l1) {
	$l1 = is_array($l1) ? array_map('stripslashes_deep', $l1) : stripslashes($l1);

	return $l1;
}

/**
 * 获取用户荣誉信息
 * @param $userId   用户ID
 * @return array
 */
function get_level_honor($userId)
{
	$score = Db::instance('db1')->single("select honor_score from un_user where id=" . $userId);
	if (empty($score)) {
		$score = 0;
	}
	$score = $score < 0 ? 0 : $score;

	$honor = Db::instance('db1')->row("select name, icon, sort, score, grade from un_honor where status = 1 and score <= $score order by score desc");

	$conf = Db::instance('db1')->single("select value from un_config where nid='level_honor'");
	$config = json_decode($conf,true);

	$honor['honor_status'] = $config['status'];
	$honor['user_score'] = $score;
	//注意等级和排序号的关系
	$honor['sort']  = $honor['grade'];

	return $honor;

}


function mixVal($order_no = '', $money = '', $addtime = '', $way = '')
{

	$md5_val = md5(($addtime - $money) . substr($order_no, 10) . $way);

	return $md5_val;
}


function convert1($money) {
	if (substr($money, -2) == '00') {
		$money = substr($money, 0, -3);
	} elseif (substr($money, -2, 1) != '0' && substr($money, -1) == '0') {
		$money = substr($money, 0, -1);
	}
	return $money;
}


/**
 *
 * 把发送数据和接收数据放到公共函数
 * 发送数据给前台,这里的前台一般有多个，并且是跑wokerman的
 * 特别注意:后台的配置文件要把home_arr这个加上去
 * 双活用的，请不要动
 */
function send_home_data($data=array()){
	$key='DCCdPke3boPWr2Wp2Qb4yWF9MuiYq@9f';
	$time=time();
	$sign=md5($key.$time);
	$data['sign']=$sign;
	$data['timestamp']=$time;
	lg('send_home_data','发送给前台的数据::'.encode($data));
	$res  = require __DIR__.'/../../../caches/config.php';

	foreach ($res['home_arr'] as $v){
		$url  =  $v."/index.php?m=api&c=workerman&a=get_admin_data";
		lg('send_home_data','url'.$url);
		http_post_json($url,json_encode($data,JSON_UNESCAPED_UNICODE),1);
	}
}
/**
 *
 * 双活用的，请不要动
 */
function http_post_json($url, $jsonStr='[]',$timeout=false)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //这个是重点
	if($timeout){
		curl_setopt($ch, CURLOPT_TIMEOUT,3); //防止超时卡顿
	}
	curl_setopt($ch, CURLOPT_POST, true); //POST
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('CLIENT-IP:58.68.44.61','X-FORWARDED-FOR:58.68.44.61'));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}


// 程序运行时间
function runtime($et,$bt) {
	return  round(($et-$bt),3);
//	return round(microtime(true) - $GLOBALS['_SN']['starttime'], 3);
}