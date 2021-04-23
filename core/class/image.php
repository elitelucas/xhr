<?php

/**
 *  image.php 图像处理层
 *
 * @copyright			(C) 2011 snyni.com
 * @lastmodify			2011-09-03   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');

class image {

    var $w_pct = 100;
    var $w_quality = 80;
    var $w_minwidth = 300;
    var $w_minheight = 300;
    var $thumb_enable;
    var $watermark_enable = 1;
    var $interlace = 0;
    var $site_setting = array();

    function __construct() {

    }

    function set($w_img, $w_pos, $w_minwidth = 300, $w_minheight = 300, $w_quality = 80, $w_pct = 100) {
        $this->w_img = $w_img;
        $this->w_pos = $w_pos;
        $this->w_minwidth = $w_minwidth;
        $this->w_minheight = $w_minheight;
        $this->w_quality = $w_quality;
        $this->w_pct = $w_pct;
    }

    /**
     * 获取图片信息
     *
     * @param unknown_type $img
     * @return unknown
     */
    function info($img) {
        $imageinfo = getimagesize($img);
        if ($imageinfo === false)
            return false;
        $imagetype = strtolower(substr(image_type_to_extension($imageinfo[2]), 1));
        $imagesize = @filesize($img);
        $info = array(
            'width' => $imageinfo[0],
            'height' => $imageinfo[1],
            'type' => $imagetype,
            'size' => $imagesize,
            'mime' => $imageinfo['mime']
        );
        return $info;
    }

    /**
     * 缩小图片
     *
     * @param string $filename 原图片地址
     * @param string $filename 新图片地址
     * @param int $new_w 宽
     * @param int $new_h 高
     * @param string $new_h 文件名的后缀
     * @param int $cut 裁剪(0：等比例，1：裁剪缩小，2：裁剪,3:填充)
     * @param int $big 放大(0：不放大，1：放大)
     * @param int $pct jpg图的质量
     * @return 新图片地址
     */
    public function thumb($image, $filename = '', $new_w = 160, $new_h = 120, $suffix = '_t', $cut = 0, $big = 0, $pct = 100) {
        if (!$this->check($image))
            return false;
        $first = empty($filename) ? 1 : 0;
        $info = $this->info($image);
        if (!empty($info['width'])) {
            $old_w = $info['width'];
            $old_h = $info['height'];
            $type = $info['type'];
            unset($info);
            if (empty($filename))
                $filename = substr($image, 0, strrpos($image, '.')) . $suffix . '.' . strtolower(trim(substr(strrchr($image, '.'), 1, 10)));
            if ($old_w <= $new_w && $old_h <= $new_h && !$big) {
                @rename($image, $filename);
                return $filename;
            }
            if ($cut == 0) {
                $scale = min($new_w / $old_w, $new_h / $old_h);
                $width = (int) ($old_w * $scale);
                $height = (int) ($old_h * $scale);
                $start_w = $start_h = 0;
                $end_w = $old_w;
                $end_h = $old_h;
            } elseif ($cut == 1) {
                $scale1 = round($new_w / $new_h, 2);
                $scale2 = round($old_w / $old_h, 2);
                if ($scale1 > $scale2) {
                    $end_h = round($old_w / $scale1, 2);
                    $start_h = ($old_h - $end_h) / 2;
                    $start_w = 0;
                    $end_w = $old_w;
                } else {
                    $end_w = round($old_h * $scale1, 2);
                    $start_w = ($old_w - $end_w) / 2;
                    $start_h = 0;
                    $end_h = $old_h;
                }
                $width = $new_w;
                $height = $new_h;
            } elseif ($cut == 2) {
                $scale1 = round($new_w / $new_h, 2);
                $scale2 = round($old_w / $old_h, 2);
                if ($scale1 > $scale2) {
                    $end_h = round($old_w / $scale1, 2);
                    $end_w = $old_w;
                } else {
                    $end_w = round($old_h * $scale1, 2);
                    $end_h = $old_h;
                }
                $start_w = 0;
                $start_h = 0;
                $width = $new_w;
                $height = $new_h;
            } elseif ($cut == 3) {
                $scale = min($new_w / $old_w, $new_h / $old_h);
                $w = (int) ($old_w * $scale);
                $h = (int) ($old_h * $scale);
                $start_w = $start_h = 0;
                $end_w = $old_w;
                $end_h = $old_h;

                $width = $new_w;
                $height = $new_h;
                $start_w = 0;
                $start_h = 0;

                $ww = ($new_w - $w) / 2;
                $hh = ($new_h - $h) / 2;
            }
            $createFun = 'ImageCreateFrom' . $type;
            $oldimg = $createFun($image);
            if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
                $newimg = imagecreatetruecolor($width, $height);
            } else {
                $newimg = imagecreate($width, $height);
            }
            if ($cut == 3) {
                $bordercolor = ImageColorAllocate($newimg, 0, 0, 0);
                imagefilledrectangle($newimg, 0, 0, $width, $height, $bordercolor); // 填充背景色
                if (function_exists("ImageCopyResampled")) {
                    ImageCopyResampled($newimg, $oldimg, $ww, $hh, $start_w, $start_h, $w, $h, $end_w, $end_h);
                } else {
                    ImageCopyResized($newimg, $oldimg, $ww, $hh, $start_w, $start_h, $w, $h, $end_w, $end_h);
                }
            } else {
                if (function_exists("ImageCopyResampled")) {
                    ImageCopyResampled($newimg, $oldimg, 0, 0, $start_w, $start_h, $width, $height, $end_w, $end_h);
                } else {
                    ImageCopyResized($newimg, $oldimg, 0, 0, $start_w, $start_h, $width, $height, $end_w, $end_h);
                }
            }
            if (!is_dir(dirname($filename))) {
                mkDirs(dirname($filename));
            }
            $type == 'jpeg' && imageinterlace($newimg, 1);
            $imageFun = 'image' . $type;
            if ($type == 'jpeg') {
                $did = @$imageFun($newimg, $filename, $pct);
            } else {
                $did = @$imageFun($newimg, $filename);
            }
            ImageDestroy($newimg);
            ImageDestroy($oldimg);
            if (C('cut_width') && $first && ($old_w > C('cut_width') || $old_h > C('cut_height'))) {
                $this->thumb($image, $image, C('cut_width'), C('cut_height'));
            }
            return $filename;
        }
        return false;
    }

    function watermark($source, $target = '', $w_pos = '', $w_img = '', $w_text = 'ChengHuiTong', $w_font = 8, $w_color = '#ff0000') {
        $w_pos = $w_pos ? $w_pos : $this->w_pos;
        $w_img = $w_img ? $w_img : $this->w_img;
        if (!$this->watermark_enable || !$this->check($source))
            return false;
        if (!$target)
            $target = $source;
        $w_img = S_ROOT . $w_img;
        $source_info = getimagesize($source);
        $source_w = $source_info[0];
        $source_h = $source_info[1];
        if ($source_w < $this->w_minwidth || $source_h < $this->w_minheight)
            return false;
        switch ($source_info[2]) {
            case 1 :
                $source_img = imagecreatefromgif($source);
                break;
            case 2 :
                $source_img = imagecreatefromjpeg($source);
                break;
            case 3 :
                $source_img = imagecreatefrompng($source);
                break;
            default :
                return false;
        }
        if (!empty($w_img) && file_exists($w_img)) {
            $ifwaterimage = 1;
            $water_info = getimagesize($w_img);
            $width = $water_info[0];
            $height = $water_info[1];
            switch ($water_info[2]) {
                case 1 :
                    $water_img = imagecreatefromgif($w_img);
                    break;
                case 2 :
                    $water_img = imagecreatefromjpeg($w_img);
                    break;
                case 3 :
                    $water_img = imagecreatefrompng($w_img);
                    break;
                default :
                    return;
            }
        } else {
            $ifwaterimage = 0;
            $temp = imagettfbbox(ceil($w_font * 2.5), 0, S_CORE . '/tpl/elephant.ttf', $w_text);
            $width = $temp[2] - $temp[6];
            $height = $temp[3] - $temp[7];
            unset($temp);
        }
        switch ($w_pos) {
            case 1:
                $wx = 5;
                $wy = 5;
                break;
            case 2:
                $wx = ($source_w - $width) / 2;
                $wy = 0;
                break;
            case 3:
                $wx = $source_w - $width;
                $wy = 0;
                break;
            case 4:
                $wx = 0;
                $wy = ($source_h - $height) / 2;
                break;
            case 5:
                $wx = ($source_w - $width) / 2;
                $wy = ($source_h - $height) / 2;
                break;
            case 6:
                $wx = $source_w - $width;
                $wy = ($source_h - $height) / 2;
                break;
            case 7:
                $wx = 0;
                $wy = $source_h - $height;
                break;
            case 8:
                $wx = ($source_w - $width) / 2;
                $wy = $source_h - $height;
                break;
            case 9:
                $wx = $source_w - $width;
                $wy = $source_h - $height;
                break;
            case 10:
                $wx = rand(0, ($source_w - $width));
                $wy = rand(0, ($source_h - $height));
                break;
            default:
                $wx = rand(0, ($source_w - $width));
                $wy = rand(0, ($source_h - $height));
                break;
        }
        if ($ifwaterimage) {
            if ($water_info[2] == 3) {
                imagecopy($source_img, $water_img, $wx, $wy, 0, 0, $width, $height);
            } else {
                imagecopymerge($source_img, $water_img, $wx, $wy, 0, 0, $width, $height, $this->w_pct);
            }
        } else {
            if (!empty($w_color) && (strlen($w_color) == 7)) {
                $r = hexdec(substr($w_color, 1, 2));
                $g = hexdec(substr($w_color, 3, 2));
                $b = hexdec(substr($w_color, 5));
            } else {
                return;
            }
            imagestring($source_img, $w_font, $wx, $wy, $w_text, imagecolorallocate($source_img, $r, $g, $b));
        }

        switch ($source_info[2]) {
            case 1 :
                imagegif($source_img, $target);
                break;
            case 2 :
                imagejpeg($source_img, $target, $this->w_quality);
                break;
            case 3 :
                imagepng($source_img, $target);
                break;
            default :
                return;
        }

        if (isset($water_info)) {
            unset($water_info);
        }
        if (isset($water_img)) {
            imagedestroy($water_img);
        }
        unset($source_info);
        imagedestroy($source_img);
        return true;
    }

    function check($image) {
        return extension_loaded('gd') && preg_match("/\.(jpg|jpeg|gif|png)/i", $image, $m) && file_exists($image) && function_exists('imagecreatefrom' . ($m[1] == 'jpg' ? 'jpeg' : $m[1]));
    }

    /**
     * 生成验证码
     *
     * @param 验证码 $vname
     * @param 显示个数 $num
     * @param 字体大小 $size
     * @param 字体格式 $font
     * @param 图片宽度 $width
     * @param 图片高度 $height
     */
    static public function vCode($vname = 'vcode', $num = 4, $size = 14, $font = '../core/tpl/font.ttf', $width = 0, $height = 0) {
        !$width && $width = $num * $size * 4 / 5 + 5;
        !$height && $height = $size + 10;
        $code = self::getsalt($num);
        Session::set($vname, $code);
        $im = imagecreatetruecolor($width, $height);
        $back_color = imagecolorallocate($im, 235, 236, 237);
        $boer_color = imagecolorallocate($im, 118, 151, 199);
        $text_color = imagecolorallocate($im, mt_rand(0, 200), mt_rand(0, 200), mt_rand(0, 200));
        imagefilledrectangle($im, 0, 0, $width, $height, $back_color);
        imagerectangle($im, 0, 0, $width - 1, $height - 1, $boer_color);
        $x = $width / $num;
        for ($i = 0; $i < $num; $i++) {
            imagettftext($im, $size, rand(-30, 30), $x * $i + rand(0, 5), $height / 1.4, $text_color, $font, $code[$i]);
        }
        for ($i = 0; $i < 5; $i++) {
            $font_color = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagearc($im, mt_rand(-$width, $width), mt_rand(-$height, $height), mt_rand(30, $width * 2), mt_rand(20, $height * 2), mt_rand(0, 360), mt_rand(0, 360), $font_color);
        }
        for ($i = 0; $i < 40; $i++) {
            $font_color = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagesetpixel($im, mt_rand(0, $width), mt_rand(0, $height), $font_color);
        }
        header("Cache-Control: max-age=1, s-maxage=1, no-cache, must-revalidate");
        header("Content-type: image/png");
        imagepng($im);
        imagedestroy($im);
    }

// getsalt
    public function getsalt($l1 = 6) {
        // 去掉了 0 1 O l 等
        $l2 = "23456789abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVW";
        $l3 = '';
        for ($i = 0; $i < $l1; $i++) {
            $l3.= $l2[mt_rand(0, strlen($l2) - 1)];
        }
        return $l3;
    }
	
	static public function vCodenew($vname = 'vcode', $num = 4, $size = 14, $font = '../core/tpl/font.ttf', $width = 0, $height = 0) {
        !$width && $width = $num * $size * 4 / 5 + 5;
        !$height && $height = $size + 10;
        $code = self::getsalt($num);
        //Session::set($vname, $code);
		$_SESSION[$vname] = $code;
        $im = imagecreatetruecolor($width, $height);
        $back_color = imagecolorallocate($im, 235, 236, 237);
        $boer_color = imagecolorallocate($im, 118, 151, 199);
        $text_color = imagecolorallocate($im, mt_rand(0, 200), mt_rand(0, 200), mt_rand(0, 200));
        imagefilledrectangle($im, 0, 0, $width, $height, $back_color);
        imagerectangle($im, 0, 0, $width - 1, $height - 1, $boer_color);
        $x = $width / $num;
        for ($i = 0; $i < $num; $i++) {
            imagettftext($im, $size, rand(-30, 30), $x * $i + rand(0, 5), $height / 1.4, $text_color, $font, $code[$i]);
        }
        for ($i = 0; $i < 5; $i++) {
            $font_color = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagearc($im, mt_rand(-$width, $width), mt_rand(-$height, $height), mt_rand(30, $width * 2), mt_rand(20, $height * 2), mt_rand(0, 360), mt_rand(0, 360), $font_color);
        }
        for ($i = 0; $i < 40; $i++) {
            $font_color = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagesetpixel($im, mt_rand(0, $width), mt_rand(0, $height), $font_color);
        }
        header("Cache-Control: max-age=1, s-maxage=1, no-cache, must-revalidate");
        header("Content-type: image/png");
        imagepng($im);
        imagedestroy($im);
    }

}

?>