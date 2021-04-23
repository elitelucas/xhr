<?php

/**
 *  default.php 前台默认控制类
 *
 * @copyright			(C) 2013 CHENGHUITONG.COM
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'content' . DS . 'action.php');

class defaultAction extends Action {

    /** 主页面 */
    public function index() {
        O('cookie', '', 0);
        $threeyearcookie = cookie::get('threeyearcookie');
        if(empty($threeyearcookie)){
            cookie::set('threeyearcookie', 1, strtotime('tomorrow') - SYS_TIME);
        }

        //邀请码来源
        if (!empty($_GET['yqcode'])) {
            Session::set('yqcode', $_GET['yqcode']);
        }

        if (empty($_GET['web'])) {
            $web = cookie::get('webcookie');
            if (empty($web) && $this->isMobile()) {
                header('location:' . url('mobile', 'borrow', 'index'));
                exit;
            }
        } else {
            cookie::set('webcookie', 1);
        }

        //注册来源
        if(!empty($_GET['xsource'])){
            Session::set('xsource', $_GET['xsource']);
            //获取访问的网络IP
            if ($HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"]) {
                $ip = $HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"];
            } elseif ($HTTP_SERVER_VARS["HTTP_CLIENT_IP"]) {
                $ip = $HTTP_SERVER_VARS["HTTP_CLIENT_IP"];
            } elseif ($HTTP_SERVER_VARS["REMOTE_ADDR"]) {
                $ip = $HTTP_SERVER_VARS["REMOTE_ADDR"];
            } elseif (getenv("HTTP_X_FORWARDED_FOR")) {
                $ip = getenv("HTTP_X_FORWARDED_FOR");
            } elseif (getenv("HTTP_CLIENT_IP")) {
                $ip = getenv("HTTP_CLIENT_IP");
            } elseif (getenv("REMOTE_ADDR")) {
                $ip = getenv("REMOTE_ADDR");
            } else {
                $ip = "";
            }
            if(!empty($ip)){
                $source = $_GET['xsource'];
                D('Accesslog')->accesslogAdd(array('ip'=>$ip,'source'=>$source,'addtime'=>time()));
            }
        }

//        //获取文章 广告banner
//        $Banner_Ariticle = D('Article')->getArticlelist('A.id,A.banner_url', array('A.status' => 1, 'A.isbanner' => 1), '', '0,5');

        //网站公告
        $this->getnotice();

        //媒体报道
        $Ariticle = D('Article')->getArticlelist('A.id,A.img_url,A.title,A.addtime,A.cateid', 'A.status=1 AND A.cateid=5', '', 5);

        //行业新闻
        $Ariticle_same=D('Article')->getArticlelist('A.id,A.img_url,A.title,A.addtime,A.cateid', 'A.status=1 AND A.cateid=2', '', 5);

        //最近30天投标排行
        $where = 'BT.status=1 AND BT.addtime>=' . strtotime('-30 day 00:00:00') . ' AND BT.addtime<=' . strtotime('23:59:59') . ' AND U.isvest=0';
        $Borrowtender_Rank = D('Borrowtender')->getBorrowtender('SUM(BT.account_act) AS money,U.username', $where, 'money DESC', 5, 'BT.user_id');

        //月待收
        // $Account_Rank = D('Account')->getRank(3,"0,20");
        // $sql = "SELECT sum(J.account_act) as rankact,U.username,J.name FROM jl_juanzhu J inner JOIN jl_user U ON J.user_id=U.id  group by J.user_id ORDER BY sum(J.account_act) desc LIMIT 0,20 ";
        // $rank_list = D('carefund')->rank($sql);//公益榜

//        //感恩会员
//        $Borrowschedule_Rank = D('Borrowschedule')->getRank(0);

        //获取最新标
        $BorrowlistXin = D('Borrow')->getBorrow('B.*,U.username', array('0' => 'B.status IN (2,3,5,6,7)', 'borrow_type'=>1), 'B.status ASC,B.verify_time DESC', 4);
        $BorrowlistDi = D('Borrow')->getBorrow('B.*,U.username', array('0' => 'B.status IN (2,3,5,6,7)', 'borrow_type'=>2), 'B.status ASC,B.verify_time DESC', 4);
        //$Borrowlist = D('Borrow')->getBorrow('B.*,U.username', array('0' => 'B.status IN (2,3,5,6,7)'), 'B.status ASC,B.verify_time DESC', 4);

        //标状态
        $Borrowstatus = D('Borrow')->getBorrowstatus();
        $borrow_style = D('Glossary')->getGlossaryCache(21, 1); //还款方式
        //发标公告
        $Borrownotice =D('Borrownotice')->getNotice('pre_date,addtime,content', array('if_display'=>'1'));

        //投标统计
        $stat_file = S_ROOT."history.sum.json";
        if (file_exists($stat_file)){
	        $sum = file_get_contents($stat_file);
	        $sum=json_decode($sum);
	        $sum = (array)$sum;
        }
        $fundlist = D('carefund')->getfundlist('','status > 0','id desc',1);//慈善
        include template('index');
    }


      //检测是否是手机登陆

    public function isMobile() {
        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
        if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
            return true;
        }
        // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
        if (isset($_SERVER['HTTP_VIA'])) {
            // 找不到为flase,否则为true
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }
        // 脑残法，判断手机发送的客户端标志,兼容性有待提高
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $clientkeywords = array('nokia',
                'sony',
                'ericsson',
                'mot',
                'samsung',
                'htc',
                'sgh',
                'lg',
                'sharp',
                'sie-',
                'philips',
                'panasonic',
                'alcatel',
                'lenovo',
                'iphone',
                'ipod',
                'blackberry',
                'meizu',
                'android',
                'netfront',
                'symbian',
                'ucweb',
                'windowsce',
                'palm',
                'operamini',
                'operamobi',
                'openwave',
                'nexusone',
                'cldc',
                'midp',
                'wap',
                'mobile'
            );
            // 从HTTP_USER_AGENT中查找手机浏览器的关键字
            if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
                return true;
            }
        }
        // 协议法，因为有可能不准确，放到最后判断
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            // 如果只支持wml并且不支持html那一定是移动设备
            // 如果支持wml和html但是wml在html之前则是移动设备
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
                return true;
            }
        }
        return false;
    }

    //金额格式化
    protected function money_format($money) {
        $str = '';
        $y = bcdiv($money, 100000000, 0);
        if ($y > 0) {
            $str .= '<span>'. $y . '</span>亿';
        }
        $w = bcdiv(bcmod($money, 100000000), 10000, 0);
        if ($w > 0) {
            $str .= '<span>' . $w . '</span>万';
        }
        $q = bcmod($money, 10000);
        if ($q > 0) {
            $str .= '<span>' . $q . '</span>';
        }
        $str .= '元';
        return $str;
    }

    //运营数据
    public function operational_data() {
        $zcCount = D('user')->getUsercount();
        //投标统计
        $stat_file = S_ROOT."history.sum.json";
        if (file_exists($stat_file)){
	        $sum = file_get_contents($stat_file);
	        $sum=json_decode($sum);
	        $sum = (array)$sum;
        }
        //最近30天投标排行
        $where = 'BT.status=1 AND BT.addtime>=' . strtotime('-30 day 00:00:00') . ' AND BT.addtime<=' . strtotime('23:59:59') . ' AND U.isvest=0';
        $Borrowtender_Rank = D('Borrowtender')->getBorrowtender('SUM(BT.account_act) AS money,U.username', $where, 'money DESC', 10, 'BT.user_id');
        //最近12个月每月成交量
        $startMonth = strtotime('-11 month', strtotime(date('Y-m-01')));
        $list = D('borrow')->getBorrow("FROM_UNIXTIME(`review_time`,'%Y-%m') AS category,SUM(account) AS account", "review_time>=$startMonth AND status IN (2,3,5,6,7,9)", 'category', '', 'category');
        //12个月之前成交总量
        $oldSum = D('borrow')->getBorrow('SUM(account)', "review_time<$startMonth AND status IN (2,3,5,6,7,9)");
        foreach ($list as $k => $v) {
            $oldSum = bcadd($v['account'], $oldSum, 2);
            $list[$k]['value'] = bcdiv($oldSum, 100000000, 1); //得到每月的历史累计成交量（亿）
            $list[$k]['account'] = bcdiv($v['account'], 10000, 0); //当月成交量（万）
        }
        //借款周期占比
        $list2 = D('borrow')->getBorrow('time_limit AS target,COUNT(1) AS value', 'status IN (2,3,5,6,7,9)', 'target', '', 'target');
        $month = array(1 => '一', 2 => '二', 3 => '三', 4 => '四', 5 => '五', 6 => '六', 7 => '七', 8 => '八', 9 => '九', 10 => '十', 11 => '十一', '12' => '十二');
        foreach ($list2 as $k => $v) {
            $list2[$k]['target'] = $month[$v['target']] . '月标';
        }
        //投资金额占比
        $list3 = D('borrowtender')->getBorrowtender('SUM(IF(account_act<10000,account_act,0))/10000 AS a1,
                SUM(IF(account_act>=10000 AND account_act<100000,account_act,0))/10000 AS a2,
                SUM(IF(account_act>=100000 AND account_act<500000,account_act,0))/10000 AS a3,
                SUM(IF(account_act>=500000 AND account_act<1000000,account_act,0))/10000 AS a4,
                SUM(IF(account_act>=1000000,account_act,0))/10000 AS a5', 'status=1', '', 1);

        include template('operational-data');
    }

    //英雄投资榜
    public function hero_list() {
        //最近30天投标排行
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page = max($page, 1);
        $param = $_GET;
        unset($param['a'], $param['b'], $param['c'], $param['p']);
        $pages = pagelist(50, $page, $param, 10);
        $where = 'BT.status=1 AND BT.addtime>=' . strtotime('-29 day 00:00:00') . ' AND BT.addtime<=' . strtotime('23:59:59') . ' AND U.isvest=0';
        $Borrowtender_Rank = D('Borrowtender')->getBorrowtender('SUM(BT.account_act) AS money,U.username', $where, 'money DESC', $pages['limit'], 'BT.user_id');
        //最近7天投标排行
        $where = 'BT.status=1 AND BT.addtime>=' . strtotime('-6 day 00:00:00') . ' AND BT.addtime<=' . strtotime('23:59:59') . ' AND U.isvest=0';
        $Borrowtender_Rank2 = D('Borrowtender')->getBorrowtender('SUM(BT.account_act) AS money,U.username', $where, 'money DESC', 10, 'BT.user_id');
        include template('hero-list');
    }

}

?>