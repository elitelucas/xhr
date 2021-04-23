<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'page.php';

/**
 * 活动中心控制器
 * 2017-12-12
 */
class ActcenterAction extends Action
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 活动管理
     * 2017-12-12
     */
    public function actList()
    {

        //分页带上的条件
        $params = $_REQUEST;
        unset($params['m']);
        unset($params['c']);
        unset($params['a']);

        $sql = 'SELECT COUNT(*) AS rows_cnt FROM un_act_center AS ac
                LEFT JOIN un_admin AS admin ON admin.userid = ac.admin_id';

        $countInfo = $this->db->getOne($sql);
        $listCnt = $countInfo['rows_cnt'];
        $pagesize = 10;
        $url = '?m=admin&c=actcenter&a=actList';
        $page = new page($listCnt, $pagesize, $url, $params);
        $show = $page->show();

        $page_start = $page->offer;
        $page_size = $pagesize;

        $limit = "limit {$page_start},{$page_size}";

        //查询字段
        $field = 'ac.id, ac.act_sort, ac.act_title, ac.act_type, ac.act_start_time, ac.act_end_time, ac.act_url, ac.act_banner_pic, ac.last_updatetime, ac.admin_id, ac.is_underway, ac.is_putaway, admin.username AS admin_user';

        //查询活动中心记录列表
        $sql2 = "SELECT {$field} FROM un_act_center AS ac
            LEFT JOIN un_admin AS admin ON admin.userid = ac.admin_id
            ORDER BY ac.act_sort ASC {$limit}";
        $actList = $this->db->getAll($sql2);

        include template('actcenter-actList');
    }

    /**
     * 删除活动
     * 2017-12-13 update
     */
    public function actDel()
    {
        $id = intval($_POST['id']);
        $where = [
            'id' => $id,
        ];
        $flag = $this->db->delete('un_act_center', $where);
        echo json_encode(['rt' => $flag]);
    }

    /**
     * 活动中心详情编辑
     * 2017-12-13 update
     */
    public function actEdit()
    {
        //活动id和编辑方式
        $id = intval($_GET['id']);
        $save_type = $_GET['save_type'];
        $now_time = time();

        //获取活动类型列表数据
        $act_type_list = D('Actcenter')->fetchActTypeList();

        //新增逻辑
        if ($save_type == 'add') {

            //活动中心--[新增时的默认数据]
            $act_info = [
                'is_putaway' => 1,
                'is_underway' => 1,
            ];

        } else {

            $field = 'id, act_sort, act_title, act_type, act_start_time, act_end_time, act_url, act_banner_pic, act_banner_pic_pc, last_updatetime, admin_id, is_underway, is_putaway';
            $act_info = $this->db->getOne("SELECT {$field} FROM un_act_center WHERE id = {$id}");

            //活动开始时间
            $s_date = date('Y-m-d H:i:s', $act_info['act_start_time']);
            $e_date = date('Y-m-d H:i:s', $act_info['act_end_time']);
        }

        include template('actcenter-actEdit');
    }

    /**
     * 上传图片（新）
     * @method GET
     * @return json
     */
    public function uploadImg()
    {
        $dirPath = '/actcenter/';
        return $this->newUploadImg($dirPath);
    }

    /**
     * 保存活动中心详情
     * 2017-12-13
     */
    public function actSave()
    {
        $post_data = $_POST;

        $save_data = [];
        $save_data['id'] = intval($post_data['id']);

        //排序
        $save_data['act_sort'] = intval($post_data['act_sort']);

        //判断是新增还是编辑
        if (! $save_data['id']) {
            $save_type = 'add';
            $save_data['id'] = null;
            $save_data['admin_id'] = $this->admin['userid'];
            $sql = "select id from un_act_center where act_sort = {$post_data['act_sort']}";
            $rows = $this->db->getone($sql);
            if (!empty($rows)) {
                echo json_encode(['code' => 1, 'rt' => '', 'msg' => '排序值已存在，请重新设定排序值']);
                exit;
            }
        } else {
            $save_type = 'update';

            //检查排序值是否存在
            $fetch_where = "WHERE act_sort = {$save_data['act_sort']} AND id <> {$save_data['id']}";
            $act_info_check_one = D('Actcenter')->fetchActOne($fetch_where, 'id');

            if ($act_info_check_one) {
                echo json_encode(['code' => 1, 'rt' => '', 'msg' => '排序值已存在，请重新设定排序值']);
                exit;
            }
        }

        //标题
        $save_data['act_title'] = $post_data['act_title'];

        //开始、结束时间
        $save_data['act_start_time'] = strtotime($post_data['act_start_time']);
        $save_data['act_end_time'] = strtotime($post_data['act_end_time']);

        //活动链接以及banner图（含PC端banner图）
        $save_data['act_url'] = trim($post_data['act_url']);
        $save_data['act_banner_pic'] = trim($post_data['act_banner_pic']);
        $save_data['act_banner_pic_pc'] = trim($post_data['act_banner_pic_pc']);

        //更新时间
        $save_data['last_updatetime'] = time();

        //是否上架
        $save_data['is_putaway'] = intval($post_data['is_putaway']) ? : 0;
        $save_data['is_underway'] = intval($post_data['is_underway']) ? : 0;

        //活动类别
        $save_data['act_type'] = intval($post_data['act_type']) ? : 0;

        if ($save_type == 'add') {
            $flag = $this->db->insert('un_act_center', $save_data);
        } else {
            $where = [
                'id' => $save_data['id'],
            ];
            $flag = $this->db->update('un_act_center', $save_data, $where);
        }
        echo json_encode(['code' => 0, 'rt' => $flag]);
        exit;
    }

    /**
     * 获取各个种类的活动信息
     * 2017-12-14 update
     */
    public function fetchActInfo()
    {

        $act_type = intval($_POST['act_type']);

        $data_info = D('Actcenter')->fetchActInfo($act_type);

        if ($data_info === false) {
            $rt_data = [
                'code' => 600403,
                'msg' => '没有活动记录，请先添加活动！',
                'data' => [],
            ];
            echo json_encode($rt_data);
            exit;
        }
        
        $rt_data = [
            'code' => 0,
            'msg' => '',
            'data' => $data_info,
        ];
        echo json_encode($rt_data);
        exit;
    }






}
