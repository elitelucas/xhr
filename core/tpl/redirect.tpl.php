<?php !defined('IN_SNYNI') && die('Forbidden!');?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=7" />
		<title>提示信息</title>
		<style type="text/css">
		*{ padding:0; margin:0; font-size:12px}
		a:link,a:visited{text-decoration:none;color:#0068a6}
		a:hover,a:active{color:#ff6600;text-decoration: underline}

        .showMsg{ height: 172px; width: 450px; position:absolute;top:44%;left:50%;margin:-87px 0 0 -225px;text-align: center; padding: 30px; line-height: 42px; font-size: 16px;}

        .showMsg p{ font-size: 16px; line-height: 34px; padding: 10px;}
		</style>
		<script type="text/javascript" src="/statics/admin/js/jquery.min.js"></script>
		<script type="text/javascript" src="/statics/admin/js/admin_common.js"></script>
	</head>
	<body>
        <div class="showMsg">
            <img src="/statics/admin/images/loading.gif" />
            <p><?php echo $msg;?></p>
            <?php echo $js?>
        </div>
	</body>
</html>