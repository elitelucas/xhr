<?php

/**
 * 用户表model
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class MailModel extends CommonModel {

    protected $table = '#@_user';
    protected $table1 = '#@_message';

    //推荐公告
    public function recom($data, $where) {
        return $this->db->update($this->table1, $data, $where);
    }

    /**
     * 获取代理等级
     */
    public function getAgent() {

        $sql = "select * from un_agent_group";

        return $this->db->getall($sql);
    }

    //查询站内信
    public function getMsg($offer, $pagesize) {
        $sql = "select * from un_message where type=1 order by recom desc,addtime desc limit $offer,$pagesize";
        return $this->db->getall($sql);
    }

    public function getMsgCount() {

        $sql = "select * from un_message where type=1 order by addtime desc ";

        return count($this->db->getall($sql));
    }

    /**
     * 查询信息
     */
    public function get_xinxi() {

        $sql = "select * from un_message where type=2 order by addtime desc";

        return $this->db->getall($sql);
    }

    //站内信息搜索

    public function mail_sousuo($data, $offer, $pagesize) {
        $username = $data['username'];
        $type = $data['type'];
        $title = $data['title'];
        $content = $data['content'];
        $sql = "select * from un_message where type={$type} ";

        if ($title != '') {
            $sql .= " and title like '%$title%'";
        }
        if ($content != '') {
            $sql .= " and content like '%$content%'";
        }
        if ($data['min_time'] != '') {
            $time = strtotime($data['min_time']);
            $sql.=" and addtime > $time";
        }
        if ($data['max_time'] != '') {
            $time = strtotime($data['max_time'])+24*60*60;
            $sql.=" and addtime < $time";
        }
        if ($username != '') {
            $sq_user = "select id from un_user where username='$username'";
            $dataUser = $this->db->getone($sq_user);
            $user_id = $dataUser['id'];
            $sql.= " and touser_id like " . "'%|" . $user_id . "|%'";
        }
        $sql.=" ORDER BY id DESC limit $offer,$pagesize";

        return $this->db->getall($sql);
    }

    public function mail_sousuoCount($data) {

        $username = $data['username'];
        $type = $data['type'];
        $title = $data['title'];
        $content = $data['content'];
        $min_time = strtotime($data['min_time']);
        $max_time = strtotime($data['max_time']);
        $sql = "select * from un_message where type={$type}";

        if ($title != '') {

            $and = " and ";
            $sql.="$and title like '%$title%'";
        } else {

            $and = '';
        }

        if ($content != '') {

            $and = "and ";
            $sql.=" $and content like '%$content%'";
        }
        if ($min_time != '' && $max_time == '') {

            $and = " and ";
            $sql.="$and addtime>$min_time";
        }

        if ($min_time == '' && $max_time != '') {

            $and = " and ";
            $sql.=" $and addtime<".($max_time+24*60*60);
        }

        if ($min_time != '' && $max_time != '') {

            $and = " and ";
            $sql.=" $and addtime between $min_time and ".($max_time+24*60*60);
        }

        if ($username != '') {
            $sq_user = "select id from un_user where username='$username'";
            $dataUser = $this->db->getone($sq_user);
            $user_id = $dataUser['id'];

            if ($user_id) {
                $and = " and ";
                $sql.= " $and touser_id like " . "'%|" . $user_id . "|%'";
//                echo $sql;die;
            }
        }

        if ($username == '' && $title == '' && $content == '' && $min_time == '' && $max_time == '') {

            $sq = "select * from un_message where type={$type}";

            return count($this->db->getall($sq));
        } else {

            return count($this->db->getall($sql));
        }
    }

    public function getMessageForId($id)
    {
        $sql = "select * from un_message where id = $id";
        return $this->db->getone($sql);
    }

    /**
     * 获取代理等级
     */
    public function getGroup() {

        $sql = "select * from un_user_group";

        return $this->db->getall($sql);
    }

    //根据用户关系查询

    public function user_search($arr) {

        $link = $arr['link'];
        $username = $arr['username'];
        $sql = "select id from un_user where username='$username'";
        $ids = $this->db->getone($sql);
        if(empty($ids)){
            return array();
        }
        $id = $ids['id'];

        //判断那种关系选择
        if ($link == 1) {

            $sql = "select id,username from un_user where username='$username' ";

            return $this->db->getall($sql);
        } elseif ($link == 2) {

            $sql = "select id,username from un_user where parent_id=$id";
            return $this->db->getall($sql);
        } elseif ($link == 3) {
            $res = $this->teamLists($id);
            $uids = array();
            foreach ($res as $v){
                $uids[] = $v['id'];
            }
            $uids_s = implode(',',$uids);
            $sql = "select id,username from un_user where id IN ($uids_s)";

            return $this->db->getall($sql);
        }
    }

    //发送消息
    public function send_msg($arr) {
        $data = array(
            'user_id'       => $arr['user_id'],
            'touser_id'     => $arr['touser_id'],
            'title'         => $arr['title'],
            'content'       => $arr['content'],
            'type'          => $arr['type'],
            'recom'         => $arr['recom'],
            'is_popup'      => $arr['is_popup'],
            'expired_time'  => $arr['expired_time'],
            'addtime'       => time(),
        );
        $tab = "un_message";
        return $this->db->insert($tab, $data);
    }

    //查看接收人
    public function view($ids) {
        if ($ids != '0') {
            $ids = rtrim($ids, '|');
            $ids = ltrim($ids, '|');
            $ids = explode('|', $ids);
            $ids = implode(',', $ids);
            $sql = "select username from un_user where id in($ids)";
            return $this->db->getall($sql);
        } else {

            $sql = "select username from un_user";
            return $this->db->getall($sql);
        }
    }

    //发送给所有人

    public function send_anyone() {

        $sql = "select id,username from un_user";

        return $this->db->getall($sql);
    }

    //根据用户的管理组及代理等级来查询

    public function agent_search($arr) {

        $group = $arr['group'];
        $agent = $arr['agent'];
        //echo $group,$agent;die;
        $sql = "select id,username from un_user where ";
        if ($group != '') {

            $sql.=" group_id=$group";
            $and = "and";
        } else {

            $and = '';
        }

        if ($agent != '') {

            $sql.=" $and user_type=$agent";
        }
        $sql.=" and reg_type not in(0,9)";
        return $this->db->getall($sql);
    }

    //删除信息

    public function del($id) {

        $where = "id={$id}";
        $table = 'un_message';
        return $this->db->delete($table, $where);
    }

    //检查是否有已设置了弹窗的公告信息
    public function hasPopupAnnouncement()
    {

        $now_time = time();
        $sql = "SELECT id FROM un_message WHERE type = 1 AND is_popup = 1 AND expired_time > {$now_time}";

        $rt = $this->db->getOne($sql);

        if ($rt && $rt['id']) {
            return $rt['id'];
        }
        return false;
    }

    //修改弹窗公告的信息为不弹窗
    public function updateIsPopup($id, $is_popup = 2)
    {
        $where = [
            'id' => $id,
        ];
        $update_data = [
            //修改弹窗标志字段
            'is_popup' => $is_popup,
        ];
        return $this->db->update($this->table1, $update_data, $where);
    }

}
