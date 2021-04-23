<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/11
 * Time: 11:55
 * banner 轮播图
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class BannerModel extends CommonModel {
    protected $table = '#@_banner';
    public function checkValue($data){
        if(empty($data['title'])){
            $arr['code'] = -1;
            $arr['msg'] = "标题不能为空";
            return $arr;
        }
        if(empty($data['sort'])){
            $arr['code'] = -1;
            $arr['msg'] = "排序不能为空";
            return $arr;
        }
        if (!preg_match("/^[1-9]*$/",$data['sort'])) {
            $arr['code'] = -1;
            $arr['msg'] = "排序只能输入正整数";
            return $arr;
        }
        if(empty($data['default_path'])){
            $arr['code'] = -1;
            $arr['msg'] = "默认图片不能为空";
            return $arr;
        }
        if(empty($data['default_url'])){
            $arr['code'] = -1;
            $arr['msg'] = "默认图片跳转地址不能为空";
            return $arr;
        }
        if($data['start_time'] > $data['end_time']){
            $arr['code'] = -1;
            $arr['msg'] = "开始时间不能大于结束时间";
            return $arr;
        }
        return true;
    }

    //更新的时候如果上传了新的图片将原来的图片删除
    public function delOldImg($id,$default_path,$replace_path){
        $list = $this->db->getone("select default_path,id,replace_path from un_banner where id = '{$id}'");
        if(!empty($list)){
            if($default_path != $list['default_path']){
                @unlink($_SERVER['DOCUMENT_ROOT'].$list['default_path']);
            }
            if($replace_path != $list['replace_path']){
                @unlink($_SERVER['DOCUMENT_ROOT'].$list['default_path']);
            }
        }
    }

    //添加或者编辑banner图方法
    public function editBannerAct($id,$data){
        if(!empty($id)){
            $this->delOldImg($id,$data['default_path'],$data['replace_path']);
            $rows = $this->db->update($this->table,$data,['id'=>$id]);
        } else {
            $rows = $this->db->insert($this->table,$data);
        }
        if($rows > 0 || $rows !== false){
            $this->refreshRedis('banner', 'all');
            $arr["code"] = 0;
            $arr['msg'] = "操作成功";
        } else {
            $arr["code"] = -1;
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

}