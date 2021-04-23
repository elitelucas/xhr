<?php

/**
 * 报表模型
 * Date: 2018-01-20
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

/**
 * 报表数据模型类
 * 2018-01-20
 */
class Shell_ReportingModel extends CommonModel
{

    protected $model;
    protected $model2;

    public function __construct() {
        parent::__construct();
        $this->model = D('user');
        $this->model2 = D('account');
    }

    function shell_team_reporting($date=''){
        //脚本处理的数据时间范围(时间戳),昨天的0点至昨天的23点59分59秒
        $stime = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));
        $etime = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")));

        if(!empty($date)){
            $stime = strtotime($date);
            $etime = strtotime($date.' 23:59:59');
        }

        lg('shell_reporting_team','php进入'.var_export(array('$date'=>$date,'$stime'=>$stime,'$etime'=>$etime),1));

//        $stime = strtotime('2018-03-14');
        $start_date = date('Y-m-d',$stime);
        //$stime = strtotime(date("2018-01-12 00:00:00")); //测试用的，上线要去掉
//        $etime = strtotime('2018-03-14 23:59:59');
        $end_date = date('Y-m-d',$etime);



        //查昨天所有投注用户
//        $sql = "SELECT distinct user_id FROM `un_orders` WHERE `addtime` BETWEEN {$stime} AND {$etime} AND state = 0";
//        $res = $this->db->getall($sql);

        $sql = 'SELECT * FROM `un_user_report`'; //查记录表
        $dbre = $this->db->getall($sql);

        lg('shell_reporting_team','合并前'.var_export(array('$res'=>$res,'$dbre'=>$dbre),1));
//        $res  =  array_merge($res,$dbre);
        $res  =  $dbre;
        lg('shell_reporting_team','合并后'.var_export(array('$res'=>$res,'$dbre'=>$dbre),1));
        $res  =  array_column($res,'user_id');
        lg('shell_reporting_team','转一维后'.var_export(array('$res'=>$res,'$dbre'=>$dbre),1));
        $res  = array_unique($res);
        lg('shell_reporting_team','去重后'.var_export(array('$res'=>$res,'$dbre'=>$dbre),1));

//        return false;

//        lg('shell_reporting_team',var_export(array('$res'=>$res),1));
//        var_dump(array('$res'=>$res,'$sql'=>$sql));
//        return false;
        if(!empty($res)){
            foreach ($res as $vv) {
                $uid = $vv;
                $sql = "SELECT * FROM `un_user_tree` WHERE pids LIKE '%{$uid}%' OR user_id={$uid}"; //查团队 包含自身
                $re = $this->db->getall($sql);
                $len = count($re);
                lg('shell_reporting_team','团队人数'.var_export(array('$len'=>$len),1));
                if(!empty($re)) {
                    $len = count($re);
                    if ($len >= 500) { //超过500人的团队
                        if(!in_array($uid,array(76526/*,37484,87513,4078*/))){ ////37484  87513
                            continue;
                        }
                        $v['uid'] = $vv;
                        $teamIds = $this->teamLists2($v['uid']);  //团队会员ID
                        $leaguers = $this->leaguer2($v['uid']);    //直属会员ID（包括自己）
//                        var_dump(array('$teamIds'=>$teamIds,'$leaguers'=>$leaguers));
                        $arrTeamIds = array_merge($leaguers, $teamIds);
                        $teamIds = array_column($arrTeamIds, 'user_id');
//                        var_dump(array('$teamIds'=>$teamIds,'$arrTeamIds'=>$arrTeamIds,'$leaguers'=>$leaguers));
                        $uids = implode(',', $teamIds);
//                        var_dump(array('$uids'=>$uids));
                        $arrUserId[] = ['uid' => $v['uid'], 'team_id' => $uids];
                        $userIds = $this->getGroupIds($arrUserId, $start_date, $end_date);
//                        var_dump(array('$arrUserId'=>$arrUserId,'$userIds'=>$userIds));
                        $userCount = $userIds['ucount'];  //满足条件数量

//                        var_dump($userIds);
//                        die;
//                        $url = '?m=admin&c=reporting&a=groupDetail';
//                        $pages = new pages($userCount,$pagesize,$url,$_REQUEST);
//                        $show = $pages->show();
//                        $page_start = $pages->offer;
//                        $page_size = $pagesize;

                        //排序后该页显示的用户Id（排序完成后）
                        $list = [];
                        if (!empty($userIds['user_ids'])) {
                            foreach ($userIds['user_ids'] as $ks => $vs) {
                                $resData = array();
                                $listTeamId = $this->teamLists2($vs);  //团队会员ID
                                lg('shell_reporting_team',var_export(array('$listTeamId'=>$listTeamId),1));
                                $listLeaguers = $this->leaguer2($vs);    //直属会员ID（包括自己）
                                $arrUser = ['uid' => $vs, 'team_id' => $listTeamId, 'own_id' => $listLeaguers];

//                                $resData = $this->getUserDetail($arrUser, $start_date, $end_date);

                                $resData['uid'] = $vs; //id
                                //自身信息
                                $sql3 = "SELECT u.id AS uid, u.username, u.weixin, u.regtime, u.logintime FROM un_user AS u WHERE u.id = {$vs}";
                                $self = O('model')->db->getOne($sql3);
                                $resData['username'] = $self['username']; //账户
                                $resData['weixin'] = $self['weixin']; //账户
                                $resData['online'] = 0; //活跃人数
                                $resData['reg'] = 0; //新注册人数
                                $resData['team'] = 0; //团队人数
                                $resData['directly'] = 0; //直属会员人数
                                $resData['selfBackwater'] = 0;
                                $resData['directlyBackwater'] = 0;
                                $resData['teamBackwater'] = 0;


                                $ids = array_column($listTeamId,'user_id');
                                $idsStr = implode(',',$ids);
                                //团队投注 中奖
                                 $sql = "SELECT sum(money) as money,sum(award) as award FROM un_orders WHERE addtime >= {$stime} and addtime <= {$etime}  AND reg_type != 9 AND state = 0 and user_id in({$idsStr})";
                                 $res = $this ->db->getone($sql);
                                $resData['team_Betting'] = $res['money'];
                                $resData['team_award'] = $res['award'];

                                $resData['profit'] = bcsub($resData['team_award'],$resData['team_Betting'],2);
                                $resData['profit_2'] = 0;

//                                $resData['selfBackwater'] = $tradeType['19']; //自身返水
//                                $resData['directlyBackwater'] = $tradeType['20']; //直属会员返水
//                                $resData['teamBackwater'] = $tradeType['21']; //团队返水
//                                $resData['team_Betting'] = $teamTradeType['13'] - $teamTradeType['14']; //团队会员投注-测单
//                                $resData['team_award'] = $teamTradeType['12'] - $teamTradeType['120'];  //团队会员中奖-回滚
//                                $resData['profit'] = ($teamTradeType['12'] + $teamTradeType['14'] + $teamTradeType['19'] + $teamTradeType['20'] + $teamTradeType['21'] + $teamTradeType['18'] + $teamTradeType['66'] + $teamTradeType['1000'] + $teamTradeType['999'] + $teamTradeType['998'] + $teamTradeType['997'] + $teamTradeType['995']) - $teamTradeType['13'] - $teamTradeType['120']; //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995)-投注-回滚
//                                $resDatata['profit'] = ($tradeType['12'] + $tradeType['14'] + $tradeType['19'] + $tradeType['20'] + $tradeType['21'] + $tradeType['18'] + $tradeType['32'] + $tradeType['66'] + $tradeType['1000'] + $tradeType['999'] + $tradeType['998'] + $tradeType['997']) - $tradeType['13'] - $tradeType['120']; //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997)-投注-回滚
//                                $resData['profit_2'] = $teamTradeType['12'] + $teamTradeType['14'] - $teamTradeType['13'] - $teamTradeType['120']; //投注盈利: 中奖+撤单-投注-回滚






                                $resData['selfBackwater'] = bcadd($resData['selfBackwater'], 0, 2);
                                $resData['directlyBackwater'] = bcadd($resData['directlyBackwater'], 0, 2);
//                                $resData['teamBackwater'] = bcadd($resData['teamBackwater'], 0, 2);
                                $resData['teamBackwater'] = bcadd($resData['teamBackwater'], 0, 2);
                                $resData['team_Betting'] = bcadd($resData['team_Betting'], 0, 2);
                                $resData['team_award'] = bcadd($resData['team_award'], 0, 2);
                                $resData['profit'] = bcadd($resData['profit'], 0, 2);
                                $resData['profit_2'] = bcadd($resData['profit_2'], 0, 2);
                                $resData['date'] = $start_date; //统计时间
                                $resData['create_time']=time();
//                                var_dump($resData);

//                                $list[] = $resData;

//                                $total['online'] += $resData['online'];//活跃人数
//                                $total['reg'] += $resData['reg'];//注册人数
//                                $total['team'] += $resData['team']; //团队人数
//
//                                $total['selfBackwater'] += $resData['selfBackwater']; //自身返水
//                                $total['directlyBackwater'] += $resData['directlyBackwater']; //直属会员返水
//                                $total['teamBackwater'] += $resData['teamBackwater']; //团队返水
//                                $total['team_Betting'] += $resData['team_Betting']; //团队会员投注
//                                $total['team_award'] += $resData['team_award']; //团队会员中奖
//                                $total['profit'] += $resData['profit'];//盈利
//                                $total['profit_2'] += $resData['profit_2'];//投注盈利
//                                if(!empty($resData)) {
//                                    lg('shell_reporting_team', var_export(array('$resData' => $resData), 1));
//                                    $this->db->replace('un_user_team_report', $resData);
//                                }
                                if(!empty($resData)) {
                                    lg('shell_reporting_team', var_export(array('$resData' => $resData), 1));
                                    $this->db->replace('un_user_team_report', $resData);
                                }
                            }
                        }
                    }
//                    if(!empty($resData)){
//                    }
                    $this->getGroupInfo($uid,1,$start_date,$end_date); //直属数据
//                    $this->getGroupInfo($uid,2,$start_date,$end_date); //团队数据  ==  直属加自身 变成团队
                }
//                var_dump($resData);
            }
        }
    }

    /**
     * 获取团队会员信息 / 获取直属会员信息
     * @method ajax
     * @param uid int 用户id
     * @param type int 1:获取直属会员信息; 2:获取团队会员信息
     * @return html
     */
    public function getGroupInfo($id,$type,$start_date,$end_date){
//        $id = $_REQUEST['uid'];
//        $type = $_REQUEST['type'];
//        $start_date = $_REQUEST['start_time'];
//        $end_date = $_REQUEST['end_time'];

        if($type==2){
            //查询user表下级记录
            $res = $this->model2->teamLists($id);
        }elseif ($type ==1){
            $sql = "SELECT u.id FROM un_user AS u WHERE u.parent_id = {$id}";
            $res = O('model')->db->getAll($sql);
        }

        if(!empty($res)){
            $resData = array();
            foreach ($res as $v){
                $re = $this->getGroupDetail($v['id'],$start_date,$end_date);
                $re['date'] = $start_date;
                $re['type'] = $type;
                $re['parent_id'] = $id;
                lg('shell_reporting_team','下线数据'.var_export(array('$type'=>$type,'uid'=>$id,'$re'=>$re),1));
                $this->db->replace('un_user_team_report',$re);
//                $resData[]  = $re;
            }
//            include template('reporting-groupList');
        }
    }



    /**
     * 团队报表
     * @method POST
     * @param token string
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function getGroupDetail($id,$start_date,$end_date)
    {
        $threeTime = SYS_TIME - (86400*3); //24小时内登录的，计算活跃人数，新注册用户

        //交易类型
        $trade = $trade = $this->model2->getTrade();
        $ids = implode($trade['tranTypeIds'],',');

        //团队会员 查询user表
        //查询自身记录
        $sql = "SELECT id, id AS uid, regtime, logintime, parent_id FROM un_user WHERE id={$id}";
        $c_user = O('model')->db->getOne($sql);

        //查询user表下级记录
        $field = "id, id AS uid, regtime, logintime, parent_id";
        $res = $this->recursive_query($id,$field);
        array_unshift($res,$c_user);

        $directlyIds = array();//直属会员id
        $teamIds = array();//团队会员id
        $online = 0;
        $reg = 0;
        foreach ($res as $v){
            if($v['logintime'] > $threeTime){
                $online++;   //活跃人数
            }
            if($v['regtime'] > $threeTime){
                $reg++;      //新注册人数
            }
            if($v['parent_id'] == $id){
                $directlyIds[] = $v['uid'];  //直属会员人数
            }
            $teamIds [] = $v['uid']; //团队人数
        }

        //团队交易记录
        $STeamIds = implode($teamIds,',');
        $teamTradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$STeamIds);
        //自身交易记录 orders表
        $tradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$id);
        //自身信息
        $sql3 = "SELECT u.id AS uid, u.username, u.weixin, u.regtime, u.logintime FROM un_user AS u WHERE u.id = {$id}";
        $self = O('model')->db->getOne($sql3);
        $data = array();
//        $data['id'] = $id; //id
        $data['uid'] = $id;//id
        $data['username'] = $self['username'];//账户
        $data['weixin'] = $self['weixin'];//账户
        $data['online'] = $online;//活跃人数
        $data['reg'] = $reg;//注册人数
        $data['team'] = count($teamIds); //团队人数
        $data['directly'] = count($directlyIds); //直属会员人数
        $data['selfBackwater'] = $tradeType['19']; //自身返水
        $data['directlyBackwater'] = $tradeType['20']; //直属会员返水
        $data['teamBackwater'] = $tradeType['21']; //团队返水
        $data['team_Betting'] = $teamTradeType['13'] - $teamTradeType['14']; //团队会员投注
        $data['team_award'] = $teamTradeType['12'] - $teamTradeType['120']; //团队会员中奖-回滚
        $data['profit'] = ($tradeType['12'] + $tradeType['14'] + $tradeType['19'] + $tradeType['20'] + $tradeType['21'] + $tradeType['18'] + $tradeType['32'] + $tradeType['66'] + $tradeType['1000'] + $tradeType['999'] + $tradeType['998'] + $tradeType['997'] + $tradeType['995'] + $tradeType['994'] + $tradeType['993'] + $tradeType['992']) - $tradeType['13'] - $tradeType['120']; //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995+平台任务994+福袋993+刮刮乐992)-投注-回滚
        $data['create_time']=time();
        D("user")->ReplaceTeamReport($data);

        $data['profit_2'] = $tradeType['12'] + $tradeType['14'] - $tradeType['13'] - $tradeType['120']; //投注盈利: 中奖+撤单-投注-回滚
        return $data;
    }



    /**
     * 数据查询
     * @return mixed sql
     */
    public function recursive_query($id, $field = '*', $where = '') {
        $sql = "SELECT {$field} FROM un_user WHERE parent_id = {$id} {$where}";
        $res = O('model')->db->getAll($sql);
        if ($res) {
            foreach ($res as $v) {
                $res_c = $this->recursive_query($v['id'], $field, $where);
                $res = array_merge($res, $res_c);
            }
        }
        return $res;
    }

    //管理员操作日志   目前记录日志 额度调整 线下充值 线上充值 提现管理
    public function operLog($userid, $type, $content) {
        $dictID = $this->db->getone("select id from un_dictionary where classid = 14 and value='{$type}'");
        $dictID = $dictID['id'];

        $time = date('Y-m-d H:i:s');
        $data = array(
            "user_id" => $userid,
            "type" => $dictID,
            "content" => $content . "Operating time:{$time};",
            "loginip" => ip(),
            "logintime" => time()
        );
        $this->db->insert("un_admin_log",$data);
    }

    /**
     * 团队id 包含自身
     * @return json
     */
    protected function teamLists2($userId)
    {
        $sql = "SELECT user_id FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},%' ";
        $res = O('model')->db->getAll($sql);
        $self = array('user_id' => $userId);
        if (empty($res)) {
            return array($self);
        } else {
            array_push($res, $self);
            return $res;
        }
    }

    //直属会员    包括自己
    public function leaguer2($userId)
    {
        $sql = "SELECT user_id  FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},'";
        $res = O('model')->db->getAll($sql);
        $self = array('user_id' => $userId);
        if (empty($res)) {

            return array($self);
        } else {
            array_push($res, $self);

            return $res;
        }
    }



    /**
     * ID排序
     * @param array $arrUserId 用户ID数据
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @param int $pageSize 每页显示条数
     * @param int $type 1，按盈亏倒叙（大-小）3,按投资金额倒叙（大小）
     * @return  array
     */
    public function getGroupIds(&$arrUserId, $start_date, $end_date,/* $page = 1, $pageSize = 10,*/ $flag = 0)
    {
        $start_time = strtotime($start_date. ' 00:00:00');
        $end_time = strtotime($end_date. ' 23:59:59');
        $start_date = $start_date . ' 00:00:00';
        $end_date = $end_date . ' 23:59:59';

        $useData = [];
        $count = 0;
        $useIds = [];
        $tradeStr = '12,14,18,19,20,21,66,997,998,999,995,994,993,992,1000';  //交易类型
        $userList = [];

        $sql = "SELECT SUM(IF(`type` = 13,`money`,0)) as betting_money, SUM(IF(`type` = 14,`money`,0)) as ubetting_money, SUM(IF(`type` = 120,`money`,0)) as back_money, SUM(IF(find_in_set(`type`, '{$tradeStr}') > 0,`money`,0)) as profit_money FROM `un_daily_flow` WHERE `addtime` BETWEEN '{$start_date}' and '{$end_date}'";

        foreach ($arrUserId as $k => $vdatas) {
            $sqls =  $sql;
            $sqls .=  " AND `user_id` IN ({$vdatas['team_id']})";
            $tradeLog = O('model')->db->getone($sqls);
            $userList[$vdatas['uid']] = $tradeLog;
        }

        $profit = [];
        $betting = [];
        $sortId = [];
        foreach ($userList as $ky => $va) {
            $useData[] = ['id' => $ky, 'betting_money' => $va['betting_money'], 'profit_money' => ($va['profit_money'] - $va['betting_money'])];
            $profit[] = $va['profit_money'] - $va['betting_money'] - $va['back_money'];
            $betting[] = $va['betting_money'] - $va['ubetting_money'];
            $sortId[] = $ky;
        }
        //多维数组排序方法
        if ($flag == 0 || $flag == 1) {
            array_multisort($profit,SORT_ASC,$betting,SORT_ASC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 2) {
            array_multisort($profit,SORT_ASC,$betting,SORT_DESC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 3) {
            array_multisort($profit,SORT_DESC,$betting,SORT_ASC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 4) {
            array_multisort($profit,SORT_DESC,$betting,SORT_DESC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 5) {
            array_multisort($betting,SORT_ASC,$profit,SORT_ASC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 6) {
            array_multisort($betting,SORT_ASC,$profit,SORT_DESC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 7) {
            array_multisort($betting,SORT_DESC,$profit,SORT_ASC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 8) {
            array_multisort($betting,SORT_DESC,$profit,SORT_DESC,$sortId,SORT_ASC,$useData);
        } else {
            array_multisort($profit,SORT_ASC,$betting,SORT_ASC, $sortId,SORT_ASC,$useData);
        }
//
        foreach ($useData as $uv){
            $userIds[]  = $uv['id'];
        }

        return ['user_ids' => $userIds, 'ucount' => 0];
    }


    /**
     * 单用户总报表统计
     * @param array $arrUserId 用户ID数据
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @param int $pageSize 每页显示条数
     * @param int $type 1，按盈亏倒叙（大-小）3,按投资金额倒叙（大小）
     * @return  array
     */
    public function getUserDetail(&$arrUserId, $start_date, $end_date)
    {
        $activeTime = SYS_TIME - (86400*3); //24小时内登录的，计算活跃人数，新注册用户

        $arrTeamIds = array_merge($arrUserId['team_id'], $arrUserId['own_id']);

        $TeamIds = array_column($arrTeamIds, 'user_id');
        $TeamIds = array_unique($TeamIds);  //去重处理

        $teamId = implode(',',$TeamIds);
        lg('shell_reporting_team',var_export(array('$arrTeamIds'=>$arrTeamIds,'$TeamIds'=>$TeamIds,'$teamId'=>$teamId),1));

        //团队登录注册活跃度
        $sql = "SELECT SUM(IF(`logintime` > {$activeTime},1,0)) as online,SUM(IF(`regtime` > {$activeTime},1,0)) as reg FROM `un_user` WHERE `id` IN ({$teamId})";
        $teamUser = O('model')->db->getOne($sql);

        //交易类型
        $trade = $trade = $this->model2->getTrade();
        $ids = implode($trade['tranTypeIds'],',');

        //团队交易记录
        $teamTradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$teamId);
        //自身交易记录 orders表
        $tradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'], $arrUserId['uid']);
        //自身信息
        $sql3 = "SELECT u.id AS uid, u.username, u.weixin, u.regtime, u.logintime FROM un_user AS u WHERE u.id = {$arrUserId['uid']}";
        $self = O('model')->db->getOne($sql3);
        $data = array();
        $data['uid'] = $arrUserId['uid'];//id
        $data['username'] = $self['username'];//账户
        $data['weixin'] = $self['weixin'];//账户
        $data['online'] = $teamUser['online'];//活跃人数
        $data['reg'] = $teamUser['reg'];    //新注册人数
        $data['team'] = count($TeamIds);    //团队人数
        $data['directly'] = count($arrUserId['own_id']) - 1; //直属会员人数
        $data['selfBackwater'] = $tradeType['19']; //自身返水
        $data['directlyBackwater'] = $tradeType['20']; //直属会员返水
        $data['teamBackwater'] = $tradeType['21']; //团队返水
        $data['team_Betting'] = $teamTradeType['13'] - $teamTradeType['14']; //团队会员投注-测单
        $data['team_award'] = $teamTradeType['12'] - $teamTradeType['120'];  //团队会员中奖-回滚
        $data['profit'] = ($teamTradeType['12'] + $teamTradeType['14'] + $teamTradeType['19'] + $teamTradeType['20'] + $teamTradeType['21'] + $teamTradeType['18'] + $teamTradeType['66'] + $teamTradeType['1000'] + $teamTradeType['999'] + $teamTradeType['998'] + $teamTradeType['997'] + $teamTradeType['995'] + $teamTradeType['994'] + $teamTradeType['993'] + $teamTradeType['992']) - $teamTradeType['13'] - $teamTradeType['120']; //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995+平台任务994+福袋993+刮刮乐992)-投注-回滚
        //$data['profit'] = ($tradeType['12'] + $tradeType['14'] + $tradeType['19'] + $tradeType['20'] + $tradeType['21'] + $tradeType['18'] + $tradeType['32'] + $tradeType['66'] + $tradeType['1000'] + $tradeType['999'] + $tradeType['998'] + $tradeType['997']) - $tradeType['13'] - $tradeType['120']; //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997)-投注-回滚
        $data['profit_2'] = $teamTradeType['12'] + $teamTradeType['14'] - $teamTradeType['13'] - $teamTradeType['120']; //投注盈利: 中奖+撤单-投注-回滚
        $data['create_time']=time();

        return $data;
    }
}