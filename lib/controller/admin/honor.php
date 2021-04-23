<?php
/**
 * @author: Aho
 * @Date  : 2017/5/25 18:30
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'page.php';

class HonorAction extends Action
{
    //显示荣誉等级列表
    public function index()
    {
        $data = D('honor')->getList();

        $listCount = D('honor')->getListCount();

        $conf = D('config')->getOneCoupon("value", "nid='level_honor'");
        $config = json_decode($conf['value'], true);

        include template('honor_level_list');
    }
    
    public function test(){
        include template('test');
    }
    
    /*
     * 初始化用户积分
     */
    public function initializeUser()
    {
        $ret = D('honor')->initializeUser();
    
        echo json_encode(['msg' => $ret]);
    }
    
    /*
     * 检查用户名不区分大小写时，是否相同
     */
    public function checkUsername()
    {
        $ret = D('honor')->checkUsername();
    
        echo json_encode($ret);
    }
    
    /*
     * 检查用户名不区分大小写时，是否相同
     */
    public function same_username_list()
    {
        $listData = D('honor')->sameUsernameList();
    
        include template('honor_user_same_list');
    }
    
    //获取对所有机器人的加减分设置页面
    public function honor_robot_score_conf()
    {
        include template('honor_robot_score_edit');
    }
    
    //对所有机器人的加减积分
    public function honor_robot_score_edit()
    {
        $data = $_REQUEST;
    
        $ret = D('honor')->honorRobotScoreEdit($data, $this->admin);
    
        echo $ret;
    }
    
    //获取单个用户积分
    public function honor_user_score_conf()
    {
        $id = $_GET['id'];
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];  

        $userData = O('model')->db->getone("select id, username, weixin, honor_score from un_user where id=" . $id);

        include template('honor_user_score_edit');
    }
    
    //修改单个用户积分
    public function honor_user_score_edit()
    {
        $data = $_REQUEST;
    
        $ret = D('honor')->honorUserScoreEdit($data, $this->admin);
    
        echo $ret;
    }
    
    //用户积分列表
    public function honor_score_list()
    {
        $data = $_REQUEST;
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];
        
        if (empty($data['type']) || !is_numeric($data['type'])) {
            $data['type'] = 0;
        }
        
        if (empty($data['rg_type']) || !is_numeric($data['rg_type'])) {
            $data['rg_type'] = 0;
        }

        $count = D('honor')->getHonorCount($data);
        $pagesize = 10;
        $url = '?m=admin&c=honor&a=honor_score_list';
        $page = new page($count, $pagesize, $url, $data);
        $show = $page->show();
        $data['pagestart'] = $page->offer;
        $data['pagesize'] = $pagesize;
        $creatime=0;
        $honorScoreList = D('honor')->honorScoreList($data);

        $honorList = D('honor')->getSortList();

        include template('honor_user_score');
    }
    
    //积分变更记录列表
    public function score_record()
    {
        $typeData = [' ', '充值', '投注', '中奖', '后台修改', '充值回滚', '投注回滚', '中奖回滚'];
        $data = $_REQUEST;
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];

        $where = D('honor')->scoreRecordSeachWhere($data);
 
        $count = D('honor')->scoreRecordCount($where);
        $pagesize = 10;
        $url = '?m=admin&c=honor&a=score_record';
        $page = new page($count, $pagesize, $url, $data);
        $show = $page->show();
        $data['pagestart'] = $page->offer;
        $data['pagesize'] = $pagesize;
        $creatime=0;
        $recordList = D('honor')->scoreRecordList($data, $where);

        include template('honor_score_record');
    }
    
    //添加等级
    public function level_add()
    {
        $conf = D('config')->getOneCoupon("value", "nid='level_honor'");
        $config = json_decode($conf['value'], true);

        include template('honor_level_info');
    }
    
    //编辑等级
    public function level_edit(){
        $id = intval($_GET['id']);
        
        $conf = D('config')->getOneCoupon("value", "nid='level_honor'");
        $config = json_decode($conf['value'], true);
        
        $data = D('honor')->getOneCoupon('','id='.$id);

        include template('honor_level_info');
    }
    
    //等级操作  编辑、添加
    public function level_ok()
    {
        $data = $_POST;
    
        if (empty($data['id'])) {
            $ret = D('honor')->addHonor($data);
        } else {
            $ret = D('honor')->editHonor($data);
        }
    
        echo $ret;
    }
    
    //删除
    public function level_del()
    {
        $id = $_POST['id'];
        
        $ret = D('honor')->deleteLevel($id);

        echo $ret;
    }
    
    //是否启用
   public function use_level()
    {
        $id = $_POST['id'];
        
        $ret = D('honor')->useLevel($id);
    
        echo $ret;
    }
    
    //（加分）充值、投注、中奖送积分设置页面
    public function score_conf()
    {
        $config = json_decode(D('config')->getOneCoupon('value',"nid='level_honor'")['value'],true);

        include template('honor_score_conf');
    }
    
    
    //（加分）充值、投注、中奖送积分配置操作
    public function set_conf()
    {
        $data = $_POST;
        
        $ret = D('honor')->setConfig($data);
        
        echo $ret;
        /*
        $res = D('config')->save($data,'nid="honor_upgrade"');
        $msg = '操作';
        if($res){
            exit(json_encode(['code'=>1,'msg'=>$msg.'成功~']));
        }else{
            exit(json_encode(['code'=>0,'msg'=>$msg.'失败~']));
        }
        */
    }
    //荣誉前端是否显示
    public function show_honor()
    {
        $data = $_POST;
        $ret = D('honor')->showHonor($data['status']);
        
        echo $ret;
    }
    
    //是否启用兑换加分
    public function start_plus()
    {
        $data = $_POST;
        $ret = D('honor')->startPlus($data['status']);
    
        echo $ret;
    }
    //扣分配置操作
    public function downgrade_ok(){
        $data['value'] = json_encode($_POST,true);
        $res = D('config')->save($data,'nid="honor_downgrade"');
        $msg = '操作';
        if($res){
            exit(json_encode(['code'=>1,'msg'=>$msg.'成功~']));
        }else{
            exit(json_encode(['code'=>0,'msg'=>$msg.'失败~']));
        }
    }
    //积分设置页面
    public function score_confs(){
        //dump(D('accountrecharge')->getOneCoupon('addtime',"user_id=57 and status=1",'id desc'));
        //dump(D('user')->db->result("select honor_score from un_user where id=57"));
        //dump(get_honor_level(57,$type=1));
        //dump(D('accountrecharge')->db->result("select sum(money) from un_account_recharge where user_id=57 and status=0"));
        $data3 = D('honor')->getList();
        //dump($data3);
        $config = json_decode(D('config')->getOneCoupon('value',"nid='level_honor'")['value'],true);
        //dump($data1);
        include template('honor_score_conf');
    }


    //上传图标
    public function uploadFile()
    {
        $file = $_FILES['file'];
        $res = upLodeImg($file);
        echo json_encode($res);
    }


}