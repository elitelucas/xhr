<?php !defined('IN_SNYNI') && die('Forbidden!'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php include '/../../template/default/header.html'; ?>
<style type="text/css">
    .showMsg{text-align:center;border:1px #ccc solid; padding:40px;margin: 0 auto;width: 920px}
    .showMsg a{color:#ff6600;}
    .showMsg a:hover,.showMsg a:active{color:#ff6600;text-decoration: underline}
    .showMsg .content{ padding:46px 12px 10px 45px; font-size:14px;width: 260px; height:44px;margin: 0 auto; text-align:left}
    .showMsg .bottom{ background:#e4ecf7; margin: 0 1px 1px 1px;line-height:26px; *line-height:30px; height:26px; text-align:center;font-size: 12px;}
    .showMsg .ok,.showMsg .guery{background: url(./statics/admin/images/msg_img/msg_bg.png) no-repeat 0px -560px;}
    .showMsg .guery{background-position: left -460px;}

</style>
<div id="syindex">
    <div class="showMsg">
        <?php if (empty($status)) { ?>
            <div class="content guery">很抱歉,<?php echo $msg; ?></div>
        <?php } else { ?>
            <div class="content ok"  >恭喜您,<?php echo $msg; ?></div>
        <?php } ?>
        <div class="bottom">
            等待3秒后,如果您的浏览器没有自动跳转，请<a href="<?php echo empty($jumpUrl) ? $_SERVER["HTTP_REFERER"] : $jumpUrl ?>">点击这里</a>
            <?php echo $js ?>
        </div>
    </div>
</div>
<?php include '/../../template/default/footer.html'; ?>