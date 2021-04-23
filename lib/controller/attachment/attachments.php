<?php

/**
 * @copyright			(C) 2013 CHENGHUITONG.COM
 */
!defined('IN_SNYNI') && die('Access Denied!');
require S_CORE . 'common/att.func.php';
class AttachmentsAction {

    function __construct() {
        O('session', '', 0);
        O('cookie', '', 0);
        $this->upload_path = C('upfile_path');
        $this->upload_url = APP_PATH . C('upfile_path') . "/";
        $this->imgext = explode(",", C('upfile_exts'));
        $this->isadmin = isset($_REQUEST['isadmin']) && $_REQUEST['isadmin'] ? 1 : 0;
        if ($this->isadmin) {
            Session::start();
            $this->userid = Session::get('userid');
        } else {
            $this->userid = cookie::get('userid');
        }
    }

    /**
     * 常规上传
     */
    public function upload() {
        O('upload', '', 0);
        $attachment = new upload($_GET['module']);
        $attachment->set_userid($this->userid);
        $a = $attachment->upload('upload');
        if ($a) {
            $filepath = $attachment->uploadedfiles[0]['filepath'];
            $fn = intval($_GET['CKEditorFuncNum']);
            $this->upload_json($a[0], $filepath, $attachment->uploadedfiles[0]['filename']);
            $attachment->mkhtml($fn, $this->upload_url . $filepath, '');
        }
    }

	public function upload_img() {
        O('upload', '', 0);
        $attachment = new upload($_GET['module']);
        $attachment->set_userid($this->userid);
        $a = $attachment->upload('upfile','',0,0,array(100,100));
		

        if ($a) {
            $filepath = $attachment->uploadedfiles[0]['filepath'];
			$thumbpath = str_replace(".", "_t.", $filepath);
			$img_id = '|'.$a[0];
			$imgurl = '|'.APP_PATH .C('upfile_path').'/'.$filepath;

            echo '<body onload="showPic()"><script type="text/javascript">function showPic(){parent.set_img("fsUploadProgress", "'.C('upfile_path').'/'.$thumbpath.'");parent.set_hidden_attr("'.$img_id.'","'.$imgurl.'")}</script></body>';
        }
    }

    /**
     * swfupload上传附件
     */
    public function swfupload() {
        if (isset($_POST['dosubmit'])) {
            //判断是否登录
			
            if (empty($this->userid)) {
                $arr_uid_time = explode(',',dencrypt($_POST['userid']));
				$this->userid = $arr_uid_time[0];
				$stime = $arr_uid_time[1];
				if(time()-$stime>1800){
					exit('0,非法用户');
				}
            }
            if ($_POST['swf_auth_key'] != md5(C('auth_key') . $_POST['SWFUPLOADSESSID']))
                exit("0,非法上传");
            if (!in_array($_POST['module'],array('avatar','cardpic','material','article','borrow','carefund'))) {
            	exit("0,非法模块上传");
            }
            O('upload', '', 0);
            $attachment = new upload($_POST['module']);
            $attachment->set_userid($_POST['userid']);
            $aids = $attachment->upload('Filedata', $_POST['filetype_post'], '', '', array($_POST['thumb_width'], $_POST['thumb_height']), $_POST['watermark_enable']);
            if ($aids[0]) {
                $filename = $attachment->uploadedfiles[0]['filename'];
                if ($attachment->uploadedfiles[0]['isimage']) {
                    echo $aids[0] . ',' . $this->upload_url . $attachment->uploadedfiles[0]['filepath'] . ',' . $attachment->uploadedfiles[0]['isimage'] . ',' . $filename;
                } else {
                    $fileext = $attachment->uploadedfiles[0]['fileext'];
                    if ($fileext == 'zip' || $fileext == 'rar')
                        $fileext = 'rar';
                    elseif ($fileext == 'doc' || $fileext == 'docx')
                        $fileext = 'doc';
                    elseif ($fileext == 'xls' || $fileext == 'xlsx')
                        $fileext = 'xls';
                    elseif ($fileext == 'ppt' || $fileext == 'pptx')
                        $fileext = 'ppt';
                    elseif ($fileext == 'flv' || $fileext == 'swf' || $fileext == 'rm' || $fileext == 'rmvb')
                        $fileext = 'flv';
                    else
                        $fileext = 'do';
                    echo $aids[0] . ',' . $this->upload_url . $attachment->uploadedfiles[0]['filepath'] . ',' . $fileext . ',' . $filename;
                }
                exit;
            } else {
                echo '0,' . $attachment->error();
                exit;
            }
        } else {
            $args = $_GET['args'];
            $authkey = $_GET['authkey'];
            if (upload_key($args) != $authkey)
                alert("参数传递错误");
            extract(getswfinit($_GET['args']));
            $file_size_limit = sizecount(C('upload_maxsize') * 1024);

            $att_not_used = cookie::get('att_json');
            //if(empty($att_not_used) || !isset($att_not_used)) $tab_status = ' class="on"';
            //if(!empty($att_not_used)) $div_status = ' hidden';
            //获取临时未处理文件列表
            //$att = $this->att_not_used();

            include template('swfupload');
        }
    }

    public function crop_upload() {
        if (isset($GLOBALS["HTTP_RAW_POST_DATA"])) {
            $pic = $GLOBALS["HTTP_RAW_POST_DATA"];
            if (isset($_GET['width']) && !empty($_GET['width'])) {
                $width = intval($_GET['width']);
            }
            if (isset($_GET['height']) && !empty($_GET['height'])) {
                $height = intval($_GET['height']);
            }
            if (isset($_GET['file']) && !empty($_GET['file'])) {
                $_GET['file'] = str_ireplace(array(';', 'php'), '', $_GET['file']);
                if (is_image($_GET['file']) == false || stripos($_GET['file'], '.php') !== false)
                    exit();
                if (strpos($_GET['file'], pc_base::load_config('system', 'upload_url')) !== false) {
                    $file = $_GET['file'];
                    $basename = basename($file);
                    if (strpos($basename, 'thumb_') !== false) {
                        $file_arr = explode('_', $basename);
                        $basename = array_pop($file_arr);
                    }
                    $fileext = strtolower(fileext($basename));
                    if (!in_array($fileext, array('jpg', 'gif', 'jpeg', 'png', 'bmp')))
                        exit();
                    $new_file = 'thumb_' . $width . '_' . $height . '_' . $basename;
                } else {
                    pc_base::load_sys_class('attachment', '', 0);
                    $module = trim($_GET['module']);
                    $catid = intval($_GET['catid']);
                    $siteid = $this->get_siteid();
                    $attachment = new attachment($module, $catid, $siteid);
                    $uploadedfile['filename'] = basename($_GET['file']);
                    $uploadedfile['fileext'] = strtolower(fileext($_GET['file']));
                    if (in_array($uploadedfile['fileext'], array('jpg', 'gif', 'jpeg', 'png', 'bmp'))) {
                        $uploadedfile['isimage'] = 1;
                    }
                    $file_path = $this->upload_path . date('Y/md/');
                    pc_base::load_sys_func('dir');
                    dir_create($file_path);
                    $new_file = date('Ymdhis') . rand(100, 999) . '.' . $uploadedfile['fileext'];
                    $uploadedfile['filepath'] = date('Y/md/') . $new_file;
                    $aid = $attachment->add($uploadedfile);
                }
                $filepath = date('Y/md/');
                file_put_contents($this->upload_path . $filepath . $new_file, $pic);
            } else {
                return false;
            }
            echo pc_base::load_config('system', 'upload_url') . $filepath . $new_file;
            exit;
        }
    }

    /**
     * 删除附件
     */
    public function swfdelete() {
        $attachment = pc_base::load_sys_class('attachment');
        $att_del_arr = explode('|', $_GET['data']);
        foreach ($att_del_arr as $n => $att) {
            if ($att)
                $attachment->delete(array('aid' => $att, 'userid' => $this->userid, 'uploadip' => ip()));
        }
    }

    /**
     * 设置upload上传的json格式cookie
     */
    private function upload_json($aid, $src, $filename) {
        $arr['aid'] = intval($aid);
        $arr['src'] = trim($src);
        $arr['filename'] = urlencode($filename);
        $json_str = json_encode($arr);
        $att_arr_exist = cookie::get('att_json');
        $att_arr_exist_tmp = explode('||', $att_arr_exist);
        if (is_array($att_arr_exist_tmp) && in_array($json_str, $att_arr_exist_tmp)) {
            return true;
        } else {
            $json_str = $att_arr_exist ? $att_arr_exist . '||' . $json_str : $json_str;
            cookie::set('att_json', $json_str);
            return true;
        }
    }

    /**
     * 设置swfupload上传的json格式cookie
     */
    public function swfupload_json() {
        $arr['aid'] = intval($_GET['aid']);
        $arr['src'] = safe_replace(trim($_GET['src']));
        $arr['filename'] = urlencode(safe_replace($_GET['filename']));
        $json_str = json_encode($arr);
        $att_arr_exist = cookie::get('att_json');
        $att_arr_exist_tmp = explode('||', $att_arr_exist);
        if (is_array($att_arr_exist_tmp) && in_array($json_str, $att_arr_exist_tmp)) {
            return true;
        } else {
            $json_str = $att_arr_exist ? $att_arr_exist . '||' . $json_str : $json_str;
            cookie::set('att_json', $json_str);
            return true;
        }
    }

    /**
     * 删除swfupload上传的json格式cookie
     */
    public function swfupload_json_del() {
        $arr['aid'] = intval($_GET['aid']);
        $arr['src'] = trim($_GET['src']);
        $arr['filename'] = urlencode($_GET['filename']);
        $json_str = json_encode($arr);
        $att_arr_exist = cookie::get('att_json');
        $att_arr_exist = str_replace(array($json_str, '||||'), array('', '||'), $att_arr_exist);
        $att_arr_exist = preg_replace('/^\|\|||\|\|$/i', '', $att_arr_exist);
        cookie::set('att_json', $att_arr_exist);
    }

    private function att_not_used() {
        $this->att_db = pc_base::load_model('attachment_model');
        //获取临时未处理文件列表
        if ($att_json = param::get_cookie('att_json')) {
            if ($att_json)
                $att_cookie_arr = explode('||', $att_json);
            foreach ($att_cookie_arr as $_att_c)
                $att[] = json_decode($_att_c, true);
            if (is_array($att) && !empty($att)) {
                foreach ($att as $n => $v) {
                    $ext = fileext($v['src']);
                    if (in_array($ext, $this->imgext)) {
                        $att[$n]['fileimg'] = $v['src'];
                        $att[$n]['width'] = '80';
                        $att[$n]['filename'] = urldecode($v['filename']);
                    } else {
                        $att[$n]['fileimg'] = file_icon($v['src']);
                        $att[$n]['width'] = '64';
                        $att[$n]['filename'] = urldecode($v['filename']);
                    }
                    $this->cookie_att .= '|' . $v['src'];
                }
            }
        }
        return $att;
    }

    /**
     * swfupload上传附件
     */
    public function ajax_upload() {
        $data = Parameters(array('args', 'authkey', 'module'));
            $args = $data['args'];
            $authkey = $data['authkey'];
            $module = $data['module'];
            if (upload_key($args) != $authkey)
                alert("参数传递错误");
            if (empty($this->userid)) {
                $arr_uid_time = explode(',',dencrypt($_POST['userid']));
				$this->userid = $arr_uid_time[0];
				$stime = $arr_uid_time[1];
				if(time()-$stime>1800){
					exit('0,非法用户');
				}
            }
            if (!in_array($module,array('avatar','cardpic','material','article','borrow'))) {
            	exit("0,非法模块上传");
            }
            O('upload', '', 0);
            $attachment = new upload($module);
            $attachment->set_userid($this->userid);
            $filetype = "jpg|jpeg|gif|png";
            $file_size_limit = "2097152";
            $overwrite = "";
            $thumb_setting = array(100,100);
            $watermark_enable = "1";
            $aids = $attachment->upload('upfile', $filetype, $file_size_limit, $overwrite, $thumb_setting, $watermark_enable);
            if ($aids[0]) {
                $filename = $attachment->uploadedfiles[0]['filename'];
                if ($attachment->uploadedfiles[0]['isimage']) {
                    //echo $aids[0] . ',' . $this->upload_url . $attachment->uploadedfiles[0]['filepath'] . ',' . $attachment->uploadedfiles[0]['isimage'] . ',' . $filename;
                    $json = json_encode(array('id'=>$aids[0],'url'=>$this->upload_url . $attachment->uploadedfiles[0]['filepath'],'ext'=>$attachment->uploadedfiles[0]['fileext'],'filename'=>$filename));
                    echo $json;
                } else {
                    $fileext = $attachment->uploadedfiles[0]['fileext'];
                    if ($fileext == 'zip' || $fileext == 'rar')
                        $fileext = 'rar';
                    elseif ($fileext == 'doc' || $fileext == 'docx')
                        $fileext = 'doc';
                    elseif ($fileext == 'xls' || $fileext == 'xlsx')
                        $fileext = 'xls';
                    elseif ($fileext == 'ppt' || $fileext == 'pptx')
                        $fileext = 'ppt';
                    elseif ($fileext == 'flv' || $fileext == 'swf' || $fileext == 'rm' || $fileext == 'rmvb')
                        $fileext = 'flv';
                    else
                        $fileext = 'do';
                    //echo $aids[0] . ',' . $this->upload_url . $attachment->uploadedfiles[0]['filepath'] . ',' . $fileext . ',' . $filename;
                    $json = json_encode(array('status'=>$attachment->uploadedfiles[0]['status'],'id'=>$aids[0],'url'=>$this->upload_url . $attachment->uploadedfiles[0]['filepath'],'ext'=>$attachment->uploadedfiles[0]['fileext'],'filename'=>$filename));
                    echo $json;
                }
                exit;
            } else {
            	$json = json_encode(array('status'=>'x','msg'=>$attachment->error()));
            	echo $json;
                exit;
            }
    }
    
    /**
     * 头像上传处理方法
     */
    public function avatar_upload() {
            $module = "avatar";
            $args = "1,jpg|jpeg|gif|bmp|png,1,100,100,0,0";
            $authkey = upload_key($args);
            if (upload_key($args) != $authkey)
                alert("参数传递错误");
            if (empty($this->userid)) {
                $arr_uid_time = explode(',',dencrypt($_POST['userid']));
				$this->userid = $arr_uid_time[0];
				$stime = $arr_uid_time[1];
				if(time()-$stime>1800){
					exit('0,非法用户');
				}
            }
            if (!in_array($module,array('avatar','cardpic','material','article','borrow'))) {
            	exit("0,非法模块上传");
            }
            O('upload', '', 0);
            $attachment = new upload($module);
            $attachment->set_userid($this->userid);
            $filetype = "jpg|jpeg|gif|png";
            $file_size_limit = "2097152";
            $overwrite = "";
            $thumb_setting = array(100,100);
            $watermark_enable = "1";
            $aids = $attachment->upload('upfile', $filetype, $file_size_limit, $overwrite, $thumb_setting, $watermark_enable);
            if ($aids[0]) {
                $filename = $attachment->uploadedfiles[0]['filename'];
                if ($attachment->uploadedfiles[0]['isimage']) {
                    $json = json_encode(array('id'=>$aids[0],'url'=>$this->upload_url . $attachment->uploadedfiles[0]['filepath'],'ext'=>$attachment->uploadedfiles[0]['fileext'],'filename'=>$filename));
                    //update user avatar
                    $avatar = str_replace(".", '_t.', $attachment->uploadedfiles[0]['filepath']);
                    D('User')->userSave(array('avatar' => $avatar), array('id' => $this->userid));
                    header("location:".url('member','main','index'));
                } 
//                else {
//                    $fileext = $attachment->uploadedfiles[0]['fileext'];
//                    if ($fileext == 'zip' || $fileext == 'rar')
//                        $fileext = 'rar';
//                    elseif ($fileext == 'doc' || $fileext == 'docx')
//                        $fileext = 'doc';
//                    elseif ($fileext == 'xls' || $fileext == 'xlsx')
//                        $fileext = 'xls';
//                    elseif ($fileext == 'ppt' || $fileext == 'pptx')
//                        $fileext = 'ppt';
//                    elseif ($fileext == 'flv' || $fileext == 'swf' || $fileext == 'rm' || $fileext == 'rmvb')
//                        $fileext = 'flv';
//                    else
//                        $fileext = 'do';
//                    //echo $aids[0] . ',' . $this->upload_url . $attachment->uploadedfiles[0]['filepath'] . ',' . $fileext . ',' . $filename;
//                    $json = json_encode(array('status'=>$attachment->uploadedfiles[0]['status'],'id'=>$aids[0],'url'=>$this->upload_url . $attachment->uploadedfiles[0]['filepath'],'ext'=>$attachment->uploadedfiles[0]['fileext'],'filename'=>$filename));
//                    echo $json;
//                }
            }
    }

}

?>