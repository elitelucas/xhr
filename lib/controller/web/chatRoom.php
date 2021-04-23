<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/2
 * Time: 11:58
 * desc: 聊天室
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class ChatRoomAction extends Action{

    /**
     * 房间主页
     */
    public function index() {
        //验证token
        $this->checkAuth();
        $room_id = trim($_REQUEST['room_id']);
        $_SESSION['alan_roomid']=$room_id;
        //获取用户昵称
//       $nicknameArr = D('user')->getUserInfo('nickname', array('id' => $this->userId), 1);

        $userInfo = array(
            'userid' => $this->userId,
            'nickname' => empty(session::get('nickname')) ? session::get('username') : session::get('nickname'),
            'head_url' => session::get('avatar'),
            'room_id'  => $room_id,
            'token'  =>session_id()
        );

        //获取房间名称
        $sql = "SELECT title, passwd, lottery_type,low_yb, max_yb, uids FROM #@_room WHERE id = '{$room_id}' LIMIT 1";
        $room_name = $this->db->getone($sql);
        lg('football_room_log',var_export(array('$sql'=>$sql,'$room_name'=>$room_name),1));
        if($room_name['passwd'] !=''){
            if(session::get('room_passwd')!=$room_name['passwd'] || session::get('room_lottery_type')!=$room_name['lottery_type']){
                header('Location:'.$this->URL(array('c'=>'lobby','a'=>'index')));
            }
        }

        //限制用户进入房间验证权限
        $authorRoom = checkAuthorRoom($this->userId, $room_name['lottery_type']);
        if ($authorRoom) {
            $room_uids = explode(',', $room_name['uids']);
            $arr = array_intersect($room_uids, $authorRoom);
            if (empty($arr)) {
                $this->prompt_box('抱歉，您未开启当前房间权限');
                return;
            }
        }
        

        session::set('room_id',$room_id);
//        session::set('lottery_type',$room_name['lottery_type']);

        $userInfo['lottery_type'] = $room_name['lottery_type'];
        $userInfo = encode($userInfo);

        lg('football_room_log',var_export(array('$userInfo'=>$userInfo),1));

        //获取最近10期的开奖结果
        $lottery_type = $room_name['lottery_type'];
        if ($lottery_type == 12) {
            $redis = initCacheRedis();
            $match_id = $redis->hGet('allroom:'.$room_id, 'match_id');
            $video_address = $redis->hGet('allroom:'.$room_id, 'video_address')?:'';
            deinitCacheRedis($redis);
            $fb_info = $this->db->getone("select * from #@_cup_against where match_id = {$match_id}");
//            dump($fb_info);
            $fb_info['team_1_logo'] = toPY($fb_info['team_1_name']);
            $fb_info['team_2_logo'] = toPY($fb_info['team_2_name']);

            $str1 = '/statics/web/images/sjb/dh'.toPY($fb_info['team_1_name']).'.png';
            $fb_info['team_1_logo'] = is_file(S_ROOT.$str1)?$str1:'/statics/web/images/sjb/dhzg.png';
            $str2 = '/statics/web/images/sjb/dh'.toPY($fb_info['team_2_name']).'.png';
            $fb_info['team_2_logo'] = is_file(S_ROOT.$str2)?$str2:'/statics/web/images/sjb/dhzg.png';

//            $fb_info['title'] = $fb_info['team_1_name'].'VS'.$fb_info['team_2_name'];
            $st = array(
                '待开赛','上半场','半场结束','下半场','下半场结束','加时','加时结束','点球','点球结束','全场结束'
            );
            $fb_info['status'] = $st[$fb_info['match_state']];
        }

        //增加对北京pk10的判断 Alan 2017-6-21
        switch ($lottery_type){
            case 1:
                $list = $this->getOpenAward($lottery_type);
                $result = explode("+",$list[0]['spare_1']);
                break;
            case 2:
            case 14:
                $list = $this->getXyftPk10($lottery_type);
                break;
            case 3:
                $list = $this->getOpenAward($lottery_type);
                $result = explode("+",$list[0]['spare_1']);
                break;
            case 4:
                $list = $this->getXyftPk10($lottery_type);
                break;
            case 5:
                $list = $this->ssc($lottery_type);
                break;
            case 6:
                $list = $this->ssc($lottery_type);
                break;
            case 7:
                $list = $this->lhc($lottery_type);
                break;
            case 8:
                $list = $this->lhc($lottery_type);
                break;
            case 9:
                $list = $this->getXyftPk10($lottery_type);
                break;
            case 10:
                $list = $this->getNN($lottery_type);
                break;
            case 11:
                $list = $this->ssc($lottery_type);
                break;
            case 12:
                $list[] = array('issue'=>1);
                break;
            case 13:
                $list = $this->sb($lottery_type);
                break;
            default:
                return;
        }


        lg('football_room_log',var_export(array('$userInfo'=>$userInfo,'118'=>118),1));

        $redis = initCacheRedis();
        $lottery_title = $redis->hGet("LotteryType:{$lottery_type}",'name');
        lg('football_room_log',var_export(array('$userInfo'=>$userInfo,'$lottery_title'=>$lottery_title),1));

        //关闭redis链接
        deinitCacheRedis($redis);

        //获取用户可用余额
        $moneyArr = D('account')->getOneCoupon('money',array('user_id' => $this->userId));

        $ybMoney = convert($moneyArr['money']);
        
        if ($ybMoney < $room_name['low_yb'] || $ybMoney >= $room_name['max_yb']) {
            //return '您所拥有的元宝数不符合房间的元宝限额' . $room_name['low_yb'] . '~' . $room_name['max_yb'];
            header('Location:'.$this->URL(array('c'=>'lobby','a'=>'index')));
        }

        $JumpUrl = $this->getUrl();
        if($room_name['lottery_type'] == 1)
        {
            $sql = "select value from un_config where nid = 'xy28_stop_or_sell'";
        }
        elseif($room_name['lottery_type'] == 3)
        {
            $sql = "select value from un_config where nid = 'jnd28_stop_or_sell'";
        }
        $res = $this->db->getone($sql);
        if(!empty($res))
        {
            $config = (Array)json_decode($res['value']);
        }

        //取端口号
        $port = getWsPort();

        $redis = initCacheRedis();
        //客服配置
        $val = $redis->hget('Config:kefu_set','value');
        $kefu = decode($val);
        $kefu = $kefu['kefu'];
        deinitCacheRedis($redis);

        //$isZhuiHao = $this->db->getone("select count(*) as unm from un_orders where room_no=$room_id and lottery_type={$room_name['lottery_type']} and award_state = 0 and state = 0 and user_id='".$this->userId."' and chase_number !=''");
        if(in_array($lottery_type,array(2,4,9,14))){
            include template("bjpk10_room");
            return;
        }

        if(in_array($lottery_type,array(5,6,11))){
            include template("ssc_room");
            return;
        }

        if(in_array($lottery_type,array(7,8))){
            include template("lhc_room");
            return;
        }

        if(in_array($lottery_type,array(10))){
            include template("nn_room");
            return;
        }


        if(in_array($lottery_type,array(12))){
            include template("football_room"); //足彩房间
            return;
        }

        if(in_array($lottery_type,array(13))){
            include template("sb_room");
            return;
        }

        if(in_array($lottery_type,array(1,3))){

            //28类彩种，将开奖结果字符串（如：05+06+07）分割成一个数组，由三个开奖基数组成（如['05','06','07']）
            foreach ($list as $list_key => &$list_val) {
                $list_val['num_list'] = explode('+', $list_val['spare_1']);
            }

            include template("room");
            return;
        }
    }

    /**
     * 时时彩的开奖结果 目前只有重庆时时彩
     */
    public function sb($lottery_type){
        switch ($lottery_type){
            case 13;
                $table = "un_sb";
                break;
            default:
                return;
        }
        $list=$this->db->getall('select issue,`lottery_time`,lottery_result from '.$table.' where lottery_type='.$lottery_type.' order by issue desc limit 10');
        foreach ($list as $k=>$v){
            $temp_str='';
            $temp_he = 0;
            $temp_zanshi=explode(',',$v['lottery_result']);
            foreach ($temp_zanshi as $t_k=>$t_v){
                $temp_he += $t_v;
                $temp_str.="<i class='ssc_jieguo'>{$t_v}</i>";
            }
            $v['lottery_he'] = $temp_he;
            $v['lottery_dx'] = $temp_he >= 23 ? '大': '小';
            $v['lottery_ds'] = $temp_he%2 == 0 ? '双': '单';
            $v['lottery_result']=$temp_str;
            $v['lottery_time']=date('H:i:s',$v['lottery_time']);
            $list[$k]=$v;
        }
        return $list;
    }


    /**
     * 获取幸运28,加拿大28的开奖结果
     */
    public function getOpenAward($lottery_type){
        $where = array('lottery_type' => $lottery_type);
        $where[] = "`state` IN (0,1)";
        $order = 'issue DESC, open_time DESC';
        $list = D('openAward')->getList('issue, open_time, open_result, spare_1, spare_2, lottery_type', $where, $order, '0,10');
        //标记大小单双
        foreach ($list as $k=>$v) {
            $temp = mb_substr($v['spare_2'], 0, 1, 'utf-8');
            $temp2 = mb_substr($v['spare_2'], 1, 1, 'utf-8');
            $list[$k]['open_result'] = strlen($v['open_result']) === 1 ? '0' . $v['open_result'] : $v['open_result'];
            $list[$k]['spare_2'] = $temp.$temp2;
            $list[$k]['open_time'] = date('m-d H:i:s', $v['open_time']);
        }
        return $list;
    }



    /**
     * 时时彩的开奖结果 目前只有重庆时时彩
     */
    public function getNN($lottery_type){
        switch ($lottery_type){
            case 10;
                $table = "un_nn";
                break;
        }
        $sql = "SELECT id, issue,lottery_result AS open_result,FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM `{$table}` WHERE `status` IN (0,1) AND lottery_type={$lottery_type} ORDER BY `issue` DESC LIMIT 0,5";
        $res = $this->db->getall($sql);
        $list = array();
        foreach ($res as $k => $v) {
            $list[$k]['issue'] = $v['issue'];
            $spare_2 = getShengNiuNiu($v['open_result'],1);
//            dump($spare_2);
            $map =array(
                '黑桃A'  => 'poker1_1',
                '黑桃2'  => 'poker2_1',
                '黑桃3'  => 'poker3_1',
                '黑桃4'  => 'poker4_1',
                '黑桃5'  => 'poker5_1',
                '黑桃6'  => 'poker6_1',
                '黑桃7'  => 'poker7_1',
                '黑桃8'  => 'poker8_1',
                '黑桃9'  => 'poker9_1',
                '黑桃10' => 'poker10_1',
                '黑桃J'  => 'poker11_1',
                '黑桃Q'  => 'poker12_1',
                '黑桃K'  => 'poker13_1',
                '红心A'  => 'poker1_2',
                '红心2'  => 'poker2_2',
                '红心3'  => 'poker3_2',
                '红心4'  => 'poker4_2',
                '红心5'  => 'poker5_2',
                '红心6'  => 'poker6_2',
                '红心7'  => 'poker7_2',
                '红心8'  => 'poker8_2',
                '红心9'  => 'poker9_2',
                '红心10' => 'poker10_2',
                '红心J'  => 'poker11_2',
                '红心Q'  => 'poker12_2',
                '红心K'  => 'poker13_2',
                '梅花A'  => 'poker1_3',
                '梅花2'  => 'poker2_3',
                '梅花3'  => 'poker3_3',
                '梅花4'  => 'poker4_3',
                '梅花5'  => 'poker5_3',
                '梅花6'  => 'poker6_3',
                '梅花7'  => 'poker7_3',
                '梅花8'  => 'poker8_3',
                '梅花9'  => 'poker9_3',
                '梅花10' => 'poker10_3',
                '梅花J'  => 'poker11_3',
                '梅花Q'  => 'poker12_3',
                '梅花K'  => 'poker13_3',
                '方块A'  => 'poker1_4',
                '方块2'  => 'poker2_4',
                '方块3'  => 'poker3_4',
                '方块4'  => 'poker4_4',
                '方块5'  => 'poker5_4',
                '方块6'  => 'poker6_4',
                '方块7'  => 'poker7_4',
                '方块8'  => 'poker8_4',
                '方块9'  => 'poker9_4',
                '方块10' => 'poker10_4',
                '方块J'  => 'poker11_4',
                '方块Q'  => 'poker12_4',
                '方块K'  => 'poker13_4',
            );
            $purl_blue=$purl_red=$pai_red=array();
            $pai_red = explode(',',$spare_2['red']['lottery_pai']);
            foreach ($pai_red as $pv){
                $purl_red[] = $map[$pv];
            }

            $pai_blue = explode(',',$spare_2['blue']['lottery_pai']);
            foreach ($pai_blue as $pv){
                $purl_blue[] = $map[$pv];
            }

            $list[$k]['vs']= array(
                'red'=>array(
                    'pai'=>$pai_red,
                    'niu'=>$spare_2['red']['lottery_niu'],
                    'pai_url' => $purl_red,
                ),
                'blue'=>array(
                    'pai'=>$pai_blue,
                    'niu'=>$spare_2['blue']['lottery_niu'],
                    'pai_url'=>$purl_blue
                ),
            );
            if($spare_2['sheng']=='蓝方胜'){
                $list[$k]['pai_url'] = $purl_blue;
            }else{
                $list[$k]['pai_url'] = $purl_red;
            }
//            $list[$k]['open_result'] = '('.str_replace('胜','',$spare_2['sheng']).','.($spare_2['sheng']=='红方胜'?str_replace('胜','',$spare_2['red']['lottery_niu']):str_replace('胜','',$spare_2['blue']['lottery_niu'])).')';
            $list[$k]['open_result'] = '('.$spare_2['sheng'].','.($spare_2['sheng']=='红方胜'?$spare_2['red']['lottery_niu']:$spare_2['blue']['lottery_niu']).')';
            $list[$k]['open_time'] = $v['open_time'];
            $list[$k]['sheng'] = $spare_2['sheng'];
            $list[$k]['red_niu'] = $spare_2['red']['lottery_niu'];
            $list[$k]['blue_niu'] = $spare_2['blue']['lottery_niu'];
        }
//        dump($list);
//
        return $list;
    }

    /**
     * 时时彩的开奖结果 目前只有重庆时时彩
     */
    public function ssc($lottery_type){
        switch ($lottery_type){
            case 5;
                $table = "un_ssc";
                break;
            case 6;
                $table = "un_ssc";
                break;
            case 11;
                $table = "un_ssc";
                break;
            default:
                return;
        }
        $list=$this->db->getall('select issue,`lottery_time`,lottery_result from '.$table.' where lottery_type='.$lottery_type.' order by issue desc limit 10');
        foreach ($list as $k=>$v){
            $temp_str='';
            $temp_he = 0;
            $temp_zanshi=explode(',',$v['lottery_result']);
            foreach ($temp_zanshi as $t_k=>$t_v){
                $temp_he += $t_v;
                $temp_str.="<i class='ssc_jieguo'>{$t_v}</i>";
            }
            $v['lottery_he'] = $temp_he;
            $v['lottery_dx'] = $temp_he >= 23 ? '大': '小';
            $v['lottery_ds'] = $temp_he%2 == 0 ? '双': '单';
            $v['lottery_result']=$temp_str;
            $v['lottery_time']=date('H:i:s',$v['lottery_time']);
            $list[$k]=$v;
        }
        return $list;
    }


    /**
     * 时时彩的开奖结果 目前只有重庆时时彩
     */
    public function lhc($lottery_type){
        switch ($lottery_type){
            case 7;
                $table = "un_lhc";
                break;
            case 8;
                $table = "un_lhc";
                break;
            default:
                return;
        }
        $red = array(1,2,7,8,12,13,18,19,23,24,29,30,34,35,40,45,46);
        $blue = array(3,4,9,10,14,15,20,25,26,31,36,37,41,42,47,48);
        $green = array(5,6,11,16,17,21,22,27,28,32,33,38,39,43,44,49);
        $list=$this->db->getall('select issue,`lottery_time`,lottery_result from '.$table.' where lottery_type='.$lottery_type.' order by issue desc limit 6');
        foreach ($list as $k=>$v){
           
            $temp_str='';
            $temp_zanshi=explode(',',$v['lottery_result']);
            $arr = array();
            $arrColor = array();
            foreach ($temp_zanshi as $t_k=>$t_v){
                $arr[$t_k] = getLhcShengxiao($t_v,$v['lottery_time']);
                if(in_array($t_v,$red)){
                    $arrColor[$t_k] = 'redBox';
                }elseif(in_array($t_v,$blue)){
                    $arrColor[$t_k] = 'blueBox';
                }else{
                    $arrColor[$t_k] = 'greenBox';
                }
            }
            $v['lottery_no']=$temp_zanshi;
            $v['lottery_color'] = $arrColor;
            $v['lottery_result'] = $arr;
            $v['lottery_time']=date('H:i:s',$v['lottery_time']);
            $list[$k]=$v;
        }
        return $list;
    }

    /**
     * 获取幸运飞艇,北京pk10的开奖结果
     */
    public function getXyftPk10($lottery_type){
        switch ($lottery_type){
            case 2:
                $list=$this->db->getall("SELECT qihao,kaijiangshijian,kaijianghaoma FROM un_bjpk10 WHERE lottery_type = {$lottery_type} ORDER BY qihao DESC LIMIT 10");
                break;
            case 9:
                $list=$this->db->getall("SELECT qihao,kaijiangshijian,kaijianghaoma FROM un_bjpk10 WHERE lottery_type = {$lottery_type} ORDER BY qihao DESC LIMIT 10");
                break;
            case 4:
                $list=$this->db->getall("SELECT qihao,kaijiangshijian,kaijianghaoma FROM un_xyft ORDER BY qihao DESC LIMIT 10");
                break;
            case 14:
                $list=$this->db->getall("SELECT issue as qihao,FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS kaijiangshijian,lottery_result as kaijianghaoma FROM un_ffpk10 where lottery_type={$lottery_type} ORDER BY issue DESC LIMIT 10");
                break;
            default:
                return;
        }
        foreach ($list as $k=>$v){
            $temp_str='';
            $temp_zanshi=explode(',',$v['kaijianghaoma']);
            foreach ($temp_zanshi as $t_k=>$t_v){
                $temp_str.="<i class='colorjieguo color{$t_v}'>{$t_v}</i>";
            }
            $v['kaijianghaoma']=$temp_str;
            $v['kaijiangshijian']=substr($v['kaijiangshijian'],5);
            $list[$k]=$v;
        }
        return $list;
    }
}
