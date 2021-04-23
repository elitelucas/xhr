<?php

class upload {

    var $contentid;
    var $module;
    var $catid;
    var $attachments;
    var $field;
    var $imageexts = array('gif', 'jpg', 'jpeg', 'png', 'bmp');
    var $uploadedfiles = array();
    var $downloadedfiles = array();
    var $error;
    var $upload_root;
    var $site = array();

    function __construct($module = '', $upload_dir = '') {
        $this->module = $module ? $module : 'content';
        include_cache(S_CORE . 'common/dir.func.php');
        $this->upload_root = S_ROOT . C('upfile_path') . DS;
        $this->upload_func = 'copy';
        $this->upload_dir = $this->module . "/" . $upload_dir;
    }

    /**
     * 附件上传方法
     * @param $field 上传字段
     * @param $alowexts 允许上传类型
     * @param $maxsize 最大上传大小
     * @param $overwrite 是否覆盖原有文件
     * @param $thumb_setting 缩略图设置
     * @param $watermark_enable  是否添加水印
     */
    function upload($field, $alowexts = '', $maxsize = 0, $overwrite = 0, $thumb_setting = array(), $watermark_enable = 1) {
        if (!isset($_FILES[$field])) {
            $this->error = UPLOAD_ERR_OK;
            return false;
        }
        if (empty($alowexts) || $alowexts == '') {
            $alowexts = str_replace(",", "|", C('upfile_exts'));
        } else {
            $exts = explode(",", C('upfile_exts'));
            $alowext = explode("|", $alowexts);
            foreach ($alowext as $v) {
                if (!in_array($v, $exts)) {
                    $this->error = '14';
                    return false;
                    break;
                }
            }
        }
        $fn = $_GET['CKEditorFuncNum'] ? $_GET['CKEditorFuncNum'] : '1';

        $this->field = $field;
        $this->savepath = $this->upload_root . $this->upload_dir . date('Y/md/');
        $this->alowexts = $alowexts;
        $this->maxsize = empty($maxsize) ? C('upload_maxsize') * 1024 : $maxsize;
        $this->overwrite = $overwrite;
        $uploadfiles = array();
        $description = isset($GLOBALS[$field . '_description']) ? $GLOBALS[$field . '_description'] : array();
        if (is_array($_FILES[$field]['error'])) {
            $this->uploads = count($_FILES[$field]['error']);
            foreach ($_FILES[$field]['error'] as $key => $error) {
                if ($error === UPLOAD_ERR_NO_FILE)
                    continue;
                if ($error !== UPLOAD_ERR_OK) {
                    $this->error = $error;
                    return false;
                }
                $uploadfiles[$key] = array('tmp_name' => $_FILES[$field]['tmp_name'][$key], 'name' => $_FILES[$field]['name'][$key], 'type' => $_FILES[$field]['type'][$key], 'size' => $_FILES[$field]['size'][$key], 'error' => $_FILES[$field]['error'][$key], 'description' => $description[$key], 'fn' => $fn);
            }
        } else {
            $this->uploads = 1;
            if (!$description)
                $description = '';
            $uploadfiles[0] = array('tmp_name' => $_FILES[$field]['tmp_name'], 'name' => $_FILES[$field]['name'], 'type' => $_FILES[$field]['type'], 'size' => $_FILES[$field]['size'], 'error' => $_FILES[$field]['error'], 'description' => $description, 'fn' => $fn);
        }

        if (!dir_create($this->savepath)) {
            $this->error = '8';
            return false;
        }
        if (!is_dir($this->savepath)) {
            $this->error = '8';
            return false;
        }
        @chmod($this->savepath, 0777);

        if (!is_writeable($this->savepath)) {
            $this->error = '9';
            return false;
        }
        $aids = array();
        foreach ($uploadfiles as $k => $file) {
            $fileext = fileext($file['name']);
            if ($file['error'] != 0) {
                $this->error = $file['error'];
                return false;
            }
            if (!preg_match("/^(" . $this->alowexts . ")$/", $fileext)) {
                $this->error = '10';
                return false;
            }
            if ($this->maxsize && $file['size'] > $this->maxsize) {
                $this->error = '11';
                return false;
            }
            if (!$this->isuploadedfile($file['tmp_name'])) {
                $this->error = '12';
                return false;
            }
            $temp_filename = $this->getname($fileext);
            $savefile = $this->savepath . $temp_filename;
            $savefile = preg_replace("/(php|phtml|php3|php4|jsp|exe|dll|asp|cer|asa|shtml|shtm|aspx|asax|cgi|fcgi|pl)(\.|$)/i", "_\\1\\2", $savefile);
            $filepath = preg_replace(addslashes_deep("|^" . $this->upload_root . "|"), "", $savefile);
            if (!$this->overwrite && file_exists($savefile))
                continue;
            $upload_func = $this->upload_func;
            if (@$upload_func($file['tmp_name'], $savefile)) {
                $this->uploadeds++;
                @chmod($savefile, 0644);
                @unlink($file['tmp_name']);
                $file['name'] = safe_replace($file['name']);
                $uploadedfile = array('filename' => $file['name'], 'filepath' => $filepath, 'filesize' => $file['size'], 'fileext' => $fileext);
                if (in_array($fileext, $this->imageexts)) {
                    $image = O('image');
                    $image->set(C('watermark'), 10, 500, 500, C('water_pct'), 30);
                    $thumb_enable = is_array($thumb_setting) && ($thumb_setting[0] > 0 || $thumb_setting[1] > 0 ) ? 1 : 0;
                    if ($thumb_enable) {
                        $thumb = $image->thumb($savefile, '', $thumb_setting[0], $thumb_setting[1], '_t', 1);
                        $uploadedfile['isthumb'] = $thumb ? 1 : 0;
                    }
                    if ($watermark_enable) {
                        $image->watermark($savefile, $savefile, 10);
                    }
                }
                $aids[] = $this->add($uploadedfile);
            }
        }
        return $aids;
    }

    /**
     * 附件下载
     * Enter description here ...
     * @param $field 预留字段
     * @param $value 传入下载内容
     * @param $watermark 是否加入水印
     * @param $ext 下载扩展名
     * @param $absurl 绝对路径
     * @param $basehref
     */
    function download($field, $value, $watermark = '0', $ext = 'gif|jpg|jpeg|bmp|png', $absurl = '', $basehref = '') {
        global $image_d;
        $this->att_db = pc_base::load_model('attachment_model');
        $upload_url = pc_base::load_config('system', 'upload_url');
        $this->field = $field;
        $dir = date('Y/md/');
        $uploadpath = $upload_url . $dir;
        $uploaddir = $this->upload_root . $dir;
        $string = new_stripslashes($value);
        if (!preg_match_all("/(href|src)=([\"|']?)([^ \"'>]+\.($ext))\\2/i", $string, $matches))
            return $value;
        $remotefileurls = array();
        foreach ($matches[3] as $matche) {
            if (strpos($matche, '://') === false)
                continue;
            dir_create($uploaddir);
            $remotefileurls[$matche] = $this->fillurl($matche, $absurl, $basehref);
        }
        unset($matches, $string);
        $remotefileurls = array_unique($remotefileurls);
        $oldpath = $newpath = array();
        foreach ($remotefileurls as $k => $file) {
            if (strpos($file, '://') === false || strpos($file, $upload_url) !== false)
                continue;
            $filename = fileext($file);
            $file_name = basename($file);
            $filename = $this->getname($filename);

            $newfile = $uploaddir . $filename;
            $upload_func = $this->upload_func;
            if ($upload_func($file, $newfile)) {
                $oldpath[] = $k;
                $GLOBALS['downloadfiles'][] = $newpath[] = $uploadpath . $filename;
                @chmod($newfile, 0777);
                $fileext = fileext($filename);
                if ($watermark) {
                    watermark($newfile, $newfile, $this->siteid);
                }
                $filepath = $dir . $filename;
                $downloadedfile = array('filename' => $filename, 'filepath' => $filepath, 'filesize' => filesize($newfile), 'fileext' => $fileext);
                $aid = $this->add($downloadedfile);
                $this->downloadedfiles[$aid] = $filepath;
            }
        }
        return str_replace($oldpath, $newpath, $value);
    }

    /**
     * 附件删除方法
     * @param $where 删除sql语句
     */
    function delete($where) {
        $db = getconn();
        $result = $db->getall($db->c_sql($where, 'filepath', '#@_upfiles'));
        foreach ($result as $r) {
            $image = $this->upload_root . $r['filepath'];
            @unlink($image);
            $arr = explode(".", $image);
            $thumbs = glob($arr[0] . '*.' . $arr[1]);
            if ($thumbs)
                foreach ($thumbs as $thumb)
                    @unlink($thumb);
        }
        return $db->delete('#@_upfiles', $where);
    }

    /**
     * 附件添加如数据库
     * @param $uploadedfile 附件信息
     */
    function add($uploadedfile) {
        $db = getconn();
        $uploadedfile['module'] = $this->module;
        $uploadedfile['userid'] = $this->userid;
        $uploadedfile['uploadtime'] = SYS_TIME;
        $uploadedfile['uploadip'] = ip();
        $uploadedfile['status'] = 0;
        $uploadedfile['filename'] = strlen($uploadedfile['filename']) > 49 ? $this->getname($uploadedfile['fileext']) : $uploadedfile['filename'];
        $uploadedfile['isimage'] = in_array($uploadedfile['fileext'], $this->imageexts) ? 1 : 0;
        $aid = $db->insert("#@_upfiles", $uploadedfile);
        $this->uploadedfiles[] = $uploadedfile;
        return $aid;
    }

    function set_userid($userid) {
        $this->userid = $userid;
    }

    /**
     * 获取缩略图地址..
     * @param $image 图片路径
     */
    function get_thumb($image) {
        return str_replace('.', '_thumb.', $image);
    }

    /**
     * 获取附件名称
     * @param $fileext 附件扩展名
     */
    function getname($fileext) {
        return date('Ymdhis') . rand(100, 999) . '.' . $fileext;
    }

    /**
     * 返回附件大小
     * @param $filesize 图片大小
     */
    function size($filesize) {
        if ($filesize >= 1073741824) {
            $filesize = round($filesize / 1073741824 * 100) / 100 . ' GB';
        } elseif ($filesize >= 1048576) {
            $filesize = round($filesize / 1048576 * 100) / 100 . ' MB';
        } elseif ($filesize >= 1024) {
            $filesize = round($filesize / 1024 * 100) / 100 . ' KB';
        } else {
            $filesize = $filesize . ' Bytes';
        }
        return $filesize;
    }

    /**
     * 判断文件是否是通过 HTTP POST 上传的
     *
     * @param	string	$file	文件地址
     * @return	bool	所给出的文件是通过 HTTP POST 上传的则返回 TRUE
     */
    function isuploadedfile($file) {
        return is_uploaded_file($file) || is_uploaded_file(str_replace('\\\\', '\\', $file));
    }

    /**
     * 补全网址
     *
     * @param	string	$surl		源地址
     * @param	string	$absurl		相对地址
     * @param	string	$basehref	网址
     * @return	string	网址
     */
    function fillurl($surl, $absurl, $basehref = '') {
        if ($basehref != '') {
            $preurl = strtolower(substr($surl, 0, 6));
            if ($preurl == 'http://' || $preurl == 'ftp://' || $preurl == 'mms://' || $preurl == 'rtsp://' || $preurl == 'thunde' || $preurl == 'emule://' || $preurl == 'ed2k://')
                return $surl;
            else
                return $basehref . '/' . $surl;
        }
        $i = 0;
        $dstr = '';
        $pstr = '';
        $okurl = '';
        $pathStep = 0;
        $surl = trim($surl);
        if ($surl == '')
            return '';
        $urls = @parse_url(SITE_URL);
        $HomeUrl = $urls['host'];
        $BaseUrlPath = $HomeUrl . $urls['path'];
        $BaseUrlPath = preg_replace("/\/([^\/]*)\.(.*)$/", '/', $BaseUrlPath);
        $BaseUrlPath = preg_replace("/\/$/", '', $BaseUrlPath);
        $pos = strpos($surl, '#');
        if ($pos > 0)
            $surl = substr($surl, 0, $pos);
        if ($surl[0] == '/') {
            $okurl = 'http://' . $HomeUrl . '/' . $surl;
        } elseif ($surl[0] == '.') {
            if (strlen($surl) <= 2)
                return '';
            elseif ($surl[0] == '/') {
                $okurl = 'http://' . $BaseUrlPath . '/' . substr($surl, 2, strlen($surl) - 2);
            } else {
                $urls = explode('/', $surl);
                foreach ($urls as $u) {
                    if ($u == "..")
                        $pathStep++;
                    else if ($i < count($urls) - 1)
                        $dstr .= $urls[$i] . '/';
                    else
                        $dstr .= $urls[$i];
                    $i++;
                }
                $urls = explode('/', $BaseUrlPath);
                if (count($urls) <= $pathStep)
                    return '';
                else {
                    $pstr = 'http://';
                    for ($i = 0; $i < count($urls) - $pathStep; $i++) {
                        $pstr .= $urls[$i] . '/';
                    }
                    $okurl = $pstr . $dstr;
                }
            }
        } else {
            $preurl = strtolower(substr($surl, 0, 6));
            if (strlen($surl) < 7)
                $okurl = 'http://' . $BaseUrlPath . '/' . $surl;
            elseif ($preurl == "http:/" || $preurl == 'ftp://' || $preurl == 'mms://' || $preurl == "rtsp://" || $preurl == 'thunde' || $preurl == 'emule:' || $preurl == 'ed2k:/')
                $okurl = $surl;
            else
                $okurl = 'http://' . $BaseUrlPath . '/' . $surl;
        }
        $preurl = strtolower(substr($okurl, 0, 6));
        if ($preurl == 'ftp://' || $preurl == 'mms://' || $preurl == 'rtsp://' || $preurl == 'thunde' || $preurl == 'emule:' || $preurl == 'ed2k:/') {
            return $okurl;
        } else {
            $okurl = preg_replace('/^(http:\/\/)/i', '', $okurl);
            $okurl = preg_replace('/\/{1,}/i', '/', $okurl);
            return 'http://' . $okurl;
        }
    }

    /**
     * 返回错误信息
     */
    function error() {
        $UPLOAD_ERROR = array(
            0 => "文件上传成功",
            1 => "上传的文件超过了环境配置中选项限制的值",
            2 => "上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值",
            3 => "文件只有部分被上传",
            4 => "没有文件被上传",
            5 => '',
            6 => '找不到临时文件夹。',
            7 => '文件写入临时文件夹失败',
            8 => '附件目录创建不成功',
            9 => '附件目录没有写入权限',
            10 => '不允许上传该类型文件',
            11 => '文件超过了管理员限定的大小',
            12 => '非法上传文件',
            14 => "文件超过了管理员限定文件类型",
        );

        return $UPLOAD_ERROR[$this->error];
    }

    /**
     * ck编辑器返回
     * @param $fn
     * @param $fileurl 路径
     * @param $message 显示信息
     */
    function mkhtml($fn, $fileurl, $message) {
        $str = '<script type="text/javascript">window.parent.CKEDITOR.tools.callFunction(' . $fn . ', \'' . $fileurl . '\', \'' . $message . '\');</script>';
        exit($str);
    }

    /**
     * flash上传调试方法
     * @param $id
     */
    function uploaderror($id = 0) {
        file_put_contents(PHPCMS_PATH . 'xxx.txt', $id);
    }

}

?>