<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/30
 * Time: 11:47
 * desc: 账户资金流水表
 */

class StartPageModel extends CommonModel {
    protected $table = '#@_start_page';

    public function checkValue($data,$id){
        if(!empty($id)){
            $rs = $this->db->getone("select start_time from #@_start_page where id = $id");
            if($rs['start_time'] != $data['start_time']){
                $count = $this->getCount("#@_start_page",['start_time'=>$data['start_time']]);
                if($count > 0){
                    $arr['code'] = -1;
                    $arr['msg'] = "Cannot enter an existing start time";
                    return $arr;
                }
            }
        } else {
            $count = $this->getCount("#@_start_page",['start_time'=>$data['start_time']]);
            if($count > 0){
                $arr['code'] = -1;
                $arr['msg'] = "Cannot enter an existing start time";
                return $arr;
            }
        }
        if(empty($data)){
            $arr['code'] = -1;
            $arr['msg'] = "Missing parameters";
            return $arr;
        }
       
        if ($data['type'] != 3) {
            if($data['start_time'] >= $data['end_time']){
                $arr['code'] = -1;
                $arr['msg'] = "The start time cannot be greater than or equal to the end time";
                return $arr;
            }
        }

        if(empty($data['img_path'])){
            $arr['code'] = -1;
            $arr['msg'] = "Please upload a display image";
            return $arr;
        }
        return true;
    }
}