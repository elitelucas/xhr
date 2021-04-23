<?php  !defined('IN_SNYNI') && die('Access Denied!');?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo  C("app_webname");?> - 管理后台</title>
    <link rel="stylesheet" href="/statics/new_admin/libs/layui/css/layui.css"/>
    <link rel="stylesheet" href="/statics/new_admin/module/admin.css?v=318"/>
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>
<body class="layui-layout-body">
<div class="layui-layout layui-layout-admin">
    <!-- 头部 -->
    <div class="layui-header">
        <div class="layui-logo">
            <img src="/up_files/index_lottery/cb94038a6ddb2ca809a687f7dc551745.png"/>
            <cite> <?php echo  C("app_webname");?></cite>
        </div>
        <ul class="layui-nav layui-layout-left">
            <li class="layui-nav-item" lay-unselect>
                <a ew-event="flexible" title="侧边伸缩"><i class="layui-icon layui-icon-shrink-right"></i></a>
            </li>
            <li class="layui-nav-item" lay-unselect="">
                <a ew-event="refresh" title="刷新"><i class="layui-icon layui-icon-refresh-3"></i></a>
            </li>
        </ul>
        <ul class="layui-nav layui-layout-right">
            <li class="layui-nav-item" lay-unselect>
                <a href="<?php echo url('','default','refresh&action=all&param=all')?>" target="_blank" title="清除缓存">
                    <i class="layui-icon layui-icon-delete"></i>
                </a>
            </li>
            <li class="layui-nav-item layui-hide-xs" lay-unselect>
                <a ew-event="fullScreen" title="全屏"><i class="layui-icon layui-icon-screen-full"></i></a>
            </li>
            <li class="layui-nav-item" lay-unselect>
                <a>
                    <cite><?php echo $user['username']?>  </cite>
                </a>
                <dl class="layui-nav-child">
                    <dd lay-unselect><a ew-event="logout" data-url="?m=admin&c=login&a=logout">退出</a></dd>
                </dl>
            </li>
            <li class="layui-nav-item" lay-unselect>
                <a ew-event="theme" title="主题"><i class="layui-icon layui-icon-more-vertical"></i></a>
            </li>
        </ul>
    </div>

    <!-- 侧边栏 -->
    <div class="layui-side">
        <div class="layui-side-scroll">
            <ul class="layui-nav layui-nav-tree arrow2" lay-filter="admin-side-nav" lay-shrink="_all">
                <li class="layui-nav-item">
                    <a><i class="layui-icon layui-icon-home"></i>&emsp;<cite>常用菜单</cite></a>
                    <dl class="layui-nav-child menu">
                        <?php  if(!empty($stock_menu)) { ?>
                        <?php  foreach($stock_menu as $x){ ?>
                            <?php  if(in_array($x['id'],$auth_list)) { ?>
                                <dd><a lay-href="<?php echo $x['url']?>"><?php echo $x['name']?></a><span class="layui-nav-more-del" data-id="<?php echo $x['id']?>"></span></dd>
                            <?php  } ?>
                        <?php  } ?>
                        <?php  } ?>
                    </dl>
                </li>
                <?php  foreach($menu as $key=>$value){ ?>
                    <?php  if(in_array($key,$auth_list)) { ?>
                        <li class="layui-nav-item">
                            <a><i class="layui-icon <?php echo $value['data']?>"></i>&emsp;<cite><?php echo $value['name']?></cite></a>
                            <dl class="layui-nav-child">
                                <?php  foreach($value["child"] as $k=>$v){ ?>
                                    <?php  if(empty($v['child'])) { ?>
                                        <?php  if(in_array($k,$auth_list)) { ?>
                                            <dd>
                                                <a lay-href="<?php echo $v['url']?>"><?php echo $v['name']?></a>
                                                <?php  if(!in_array($k,$stock_menu_id_arr)) { ?>
                                                    <span class="layui-nav-more-add" data-id="<?php echo $v['id']?>"></span>
                                                <?php  } ?>
                                            </dd>
                                        <?php  } ?>
                                    <?php  } else { ?>
                                        <dd>
                                            <?php  if(in_array($k,$auth_list)) { ?>
                                                <a><?php echo $v['name']?></a>
                                            <?php  } ?>
                                            <?php  foreach($v['child'] as $x){ ?>
                                                <?php  if(in_array($x['id'],$auth_list)) { ?>
                                                    <dl class="layui-nav-child">
                                                        <dd>
                                                            <a lay-href="<?php echo $x['url']?>"><?php echo $x['name']?></a>
                                                            <?php  if(!in_array($x['id'],$stock_menu_id_arr)) { ?>
                                                                <span class="layui-nav-more-add" data-id="<?php echo $x['id']?>"></span>
                                                            <?php  } ?>
                                                        </dd>
                                                    </dl>
                                                <?php  } ?>
                                            <?php  } ?>
                                        </dd>
                                    <?php  } ?>
                                <?php  } ?>
                            </dl>
                        </li>
                    <?php  } ?>
                <?php  };?>
            </ul>
        </div>
    </div>

    <!-- 主体部分 -->
    <div class="layui-body"></div>
    <!-- 底部 -->
    <div class="layui-footer layui-text">
        <a>在线玩家:<p class="cnt">0</p></a> ====
        <a>在线游客:<p class="cnt1">0</p></a> ====
        <a lay-href="?m=admin&c=default&a=main">房间玩家:<p class="cnt2">0</p></a> ====
        <a lay-href="?m=admin&c=user&a=lst&filter=1">今日历史首充:<p class="cnt3">0</p></a> ====
        <a lay-href="?m=admin&c=finance&a=charge">提现:<p class="cash_count">0</p></a> ====
        <a lay-href="">充值:<p class="charge_count">0</p></a>
    </div>
</div>

<!-- 加载动画 -->
<div class="page-loading">
    <div class="ball-loader">
        <span></span><span></span><span></span><span></span>
    </div>
</div>

<!-- js部分 --><script type="text/javascript" src="/statics/new_admin/libs/layui/layui.js"></script><script type="text/javascript" src="/statics/new_admin/js/common.js?v=318"></script><script type="text/javascript">
    layui.use(['notice','index','jquery','element'],function(){
        var $ = layui.jquery;
        var notice = layui.notice;
        var index = layui.index;
        var element = layui.element;
        var hostname = window.location.hostname;

        function wsConncet() {
            var ws = new WebSocket("wss://"+hostname+':9501');
            var timer = {};
            ws.addEventListener('open', function(res){
                timer = setInterval(function () {
                    ws.send('{"commandid":4000,"user_id":"<?php echo $userid?>"}');
                },3000);
            });
            ws.addEventListener('message', function(res){
                var data = JSON.parse(res.data);
                if(data.commandid==4002){
                    var onlineData = JSON.parse(data.content);
                    $(".cnt").html(onlineData.cnt);
                    $(".cnt1").html(onlineData.cnt1);
                    $(".cnt2").html(onlineData.cnt2);
                    $(".cnt3").html(onlineData.cnt3);
                    $(".cash_count").html(onlineData.cash_count);
                    $(".charge_count").html(onlineData.charge_count);
                }
                if(data.commandid==4001){
                    var data = data.list;
                    for (var a = 0; a < data.length; a++) {
                        data = data[a];
                        notice.info({
                            className:'info',
                            title: data['title'] ,
                            message: data['msg'] + ' ' +  data['time'],
                            timeout: false,
                            position: 'bottomRight',
                            pauseOnHover: false,
                            resetOnHover: false,
                            progressBar: false,
                            animateInside :true,
                            maxWidth :300,
                            audio:data['music'],
                            buttons: [['<button>点击处理</button>', function () {
                                index.openTab({
                                    title: data['title'] ,
                                    url: data['url']
                                });
                                notice.hide({}, document.querySelector('.info'));
                            }]]
                        });
                    }
                }
            });
            ws.addEventListener('close', function(res){
                clearInterval(timer);
                wsConncet()
            })
        }

        wsConncet()

        // 默认加载主页
        index.loadHome({
            menuPath: '?m=admin&c=default&a=main',
            menuName: '<i class="layui-icon layui-icon-home"></i>'
        });

        $(".cnt").parent("a").click(function (){
            index.openTab({title: '在线玩家',url: '?m=admin&c=user&a=lst&rg_type=0&online=1'});
        });
        $(".cnt1").parent("a").click(function (){
            index.openTab({title: '在线游客',url: '?m=admin&c=user&a=lst&rg_type=8&online=1'});
        });
        $(".cnt2").parent("a").click(function (){
            index.openTab({title: '房间人数',url: '?m=admin&c=default&a=main'});
        });
        $(".cnt3").parent("a").click(function (){
            index.openTab({title: '今日历史首充',url: '?m=admin&c=user&a=lst&filter=1'});
        });
        $(".cash_count").parent("a").click(function (){
            index.openTab({title: '提现管理',url: '?m=admin&c=finance&a=drawal&status=0'});
        });
        $(".charge_count").parent("a").click(function (){
            index.openTab({title: '线下充值',url: '?m=admin&c=finance&a=charge&status=0'});
        });

        $("ul").on("click touchstart",".layui-nav-more-del",function(){
            var menu_id = $(this).data('id');
            var layui_nav_more_del = $(this);
            var layui_nav_more_del_html =layui_nav_more_del.prev("a").html();
            parent.layui.admin.confirm('确定要移出常用菜单？', function (index) {
                $.ajax({
                    url: '?m=admin&c=default&a=saveStockMenu',
                    data: {menu_id:menu_id, type:2},
                    dataType: 'json',
                    type: 'post',
                    error: function () {
                        notice.msg('参数或网络错误', {icon: 2});
                    },
                    success: function (result) {
                        if (result.code == 200) {
                            $('.layui-nav-tree .layui-nav-child dd a').each(function(index){
                                if($(this).html() == layui_nav_more_del_html){
                                    if($(this).next("span").length == 0){
                                        layui_nav_more_del.removeClass('layui-nav-more-del').addClass('layui-nav-more-add');
                                        layui_nav_more_del.clone(true).appendTo($(this).parent());
                                        layui_nav_more_del.parent().remove();
                                    }
                                }
                            });
                            notice.msg('操作成功', {icon: 1});
                        } else {
                            notice.msg(result.msg, {icon: 2});
                        }
                    }
                });
                parent.layer.close(index);
            });
        });

        $("ul").on("click touchstart",".layui-nav-more-add",function(){
            var menu_id = $(this).data('id');
            var layui_nav_more_add = $(this);
            parent.layui.admin.confirm('确定要添加到常用菜单？', function (index) {
                $.ajax({
                    url: '?m=admin&c=default&a=saveStockMenu',
                    data: {menu_id:menu_id, type:1},
                    dataType: 'json',
                    type: 'post',
                    error: function () {
                        notice.msg('参数或网络错误', {icon: 2});
                    },
                    success: function (result) {
                        if (result.code == 200) {

                            notice.msg('操作成功', {icon: 1});
                            layui_nav_more_add.removeClass('layui-nav-more-add').addClass('layui-nav-more-del');
                            layui_nav_more_add.parent().clone(true).appendTo(".menu");
                            layui_nav_more_add.remove();
                        } else {
                            notice.msg(result.msg, {icon: 2});
                        }
                    }
                });
                parent.layer.close(index);
            });
        });
    });
</script>
</body>
</html>