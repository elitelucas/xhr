/**
 * Created by a on 2017/7/25.
 */
var apiDomain = T.getApiDomain();
var userId = GetRequest().id;
var h5 = GetRequest().h5;
var attend = $("#data_attend").html();
function GetRequest() {
    var url = decodeURI(location.search); //获取url中"?"符后的字串
    var theRequest = new Object();
    if (url.indexOf("?") != -1) {
        var str = url.substr(1);
        strs = str.split("&");
        for(var i = 0; i < strs.length; i ++) {
            theRequest[strs[i].split("=")[0]]=unescape(strs[i].split("=")[1]);
        }
    }
    return theRequest;
}

var leftTimes = 0; //砸蛋机会次数
var $leftTimes = $('.left-times .num');
var doing = false; //控制只能砸一次
var timer = null;

var actId = getParam('actId');
function getParam (name) {
    var tmp,
        str = window.location.search.replace('?','');
    var arr = str.split("&");
    if(arr.length > 0){
        for(var i = 0,l=arr.length ;i<l;i++){
            try{
                if(/(.*?)=(.*)/.test(arr[i])){
                    if(RegExp.$1==name){
                        tmp=RegExp.$2;
                    }
                }
            }catch(e)
            {}
        }
    }
    return tmp;
}

function bindEvent () {
    //金蛋跳动效果
    clearInterval(timer);
    function random (n) {
        return parseInt(Math.random()*n + 1);
    }
    var count = 0;
    timer = setInterval(function () {
        if (count > 6) {
            setTimeout(function () {
                count = 0;
                $('.egg').removeClass('jump');
            }, 250)
        }
        $('.egg').eq(count).addClass('jump');
        count++;
        //$('.egg').eq(random(7)).addClass('jump');
    }, 600);

    //砸蛋开奖事件
    $('.eggs').off().on('click',function(){
    	leftTimes = parseInt($('.left-times .num').text()); //砸蛋机会次数
    	//leftTimes = 5; //砸蛋机会次数
        if(attend != -3){
            if (leftTimes <= 0) {
                // pageReset();
                // 如果剩余抽奖次数不足，弹窗提示页面重置
                $('.mask').fadeIn(
                    function() {
                        if (location.search.indexOf('&app=0') == -1) {
                            window.T.notimesApp('您的砸蛋次数为0');
                        } else {
                            window.T.notimesH5('您的砸蛋次数为0');
                        }
                    }
                );
                // pageReset();
                doing = false;
            } else {
                clearInterval(timer);
                if (doing) {
                    return;
                }
                doing = true;
                var _this = $(this);
                _this.addClass('taped'); //摇动
                var offsetLeft = _this.offset().left;
                var offsetTop = _this.offset().top;
                var hammerHeight = $('.hammer').height();
                var hammerWidth = $('.hammer').width();
                $('.hammer').css({'top':(offsetTop-hammerHeight),'left':(offsetLeft+hammerWidth*4/5)});
                $('.hammer').removeClass('regular');
                $('.hammer').addClass('swing');
                setTimeout(function(){
                    _this.removeClass('taped');
                    _this.addClass('fired');
                    $('.hammer').hide();
                    setTimeout(function () {
                        //$('.mask').fadeIn(
                        lottery(_this)//抽奖
                        //);
                    },500)
                },500)

            }

        }else{
            $('.mask').fadeIn(
                function(){
                    window.T.errorWin('抱歉，您没有权限参加活动！');
                }
            );
        }

    });

    $('.close-icon, .confirm-btn').off().on('click',function(){
        $(this).parent().fadeOut(
            function(){
                $('.mask').hide()
                pageReset();
            }
        );

    });

    $('.btn-again').off().on('click',function(){
        $('.mask').fadeOut(function(){
            //$(this).parent().parent().hide()
            $('.thanks-wrapper').hide()
            pageReset();
            //页面刷新
            window.location.reload();
        })
    });
}

// 页面重置
function pageReset () {
    $('.eggs-wrapper .eggs').remove();
    $('.eggs-wrapper .hammer').remove();
    $('.eggs-wrapper').append('<div class="eggs one"><div class="egg"></div><div class="fire-work"></div><div class="egg-bottom"></div></div><div class="eggs two"><div class="egg"></div><div class="fire-work"></div><div class="egg-bottom"></div></div><div class="eggs three"><div class="egg"></div><div class="fire-work"></div><div class="egg-bottom"></div></div><div class="hammer regular"></div>');
    bindEvent();
}

function animationInit () {

}

//app是否是ios
function isIos() {
    var ua = navigator.userAgent.toLowerCase();
    if (ua.match(/iPhone\sOS/i) == "iphone os") {
        return true;
    } else {
        return false;
    }
}
// ios添加说明
function iosNotice () {
    if(isIos()){
        var _bodyHeight = $('body').height();
        if($('.main').length){
            var mainWinH = $('.main').height();
            if(mainWinH<_bodyHeight){
                $('.main').append('<div class="notice fixed">*兑换项与活动和设备生产商Apple lnc.公司无关</div>');
            }else{
                $('.main').append('<div class="notice">*兑换项与活动和设备生产商Apple lnc.公司无关</div>');
            }
        }else{
            $('body').append('<div class="notice">*兑换项与活动和设备生产商Apple lnc.公司无关</div>');
        }
    }
}


function getDevice() {
    var ua = navigator.userAgent.toLowerCase();
    if(/iphone|ipad|ipod/.test(ua)) {
        return 'IOS';
    } else if(/android/.test(ua)) {
        return 'android';
    }
}

// 抽奖
function lottery (obj) {
    $.ajax({
        url: "?m=web&c=activity&a=christmasLucDraw",
        type: "post",
        data: {id: userId, type: 2},
        dataType: "json",
        success: function (newData) {
            var _this = obj;
            ajaxSuccess(newData, _this)
        },
        error: function (msg) {
            doing = false;
            console.log(msg)
            window.T.errorWin('网络拥堵,稍后再试');
            $('.mask').show();
            pageReset();
        }
    })
    //$.ajax({
    //    url: '?m=web&c=activity&a=christmasLucDraw',
    //    data: {
    //        //device: getDevice(),
    //        //act_id: actId,
    //        //adzone_click_id: logId
    //        id: userId, //userId
    //        type: 2
    //    },
    //    timeout: 10000,
    //    dataType: 'json',
    //    xhrFields: {
    //        withCredentials: true
    //    },
    //    success: ajaxSuccess,
    //    error: function (msg) {
    //        doing = false;
    //        console.log(msg)
    //        window.T.errorWin('网络拥堵,稍后再试');
    //        $('.mask').show();
    //        pageReset();
    //    }
    //});

    function thanks () {
        $('.mask').fadeIn(function () {
            $('.thanks-wrapper').show();
        });
    }
    function again () {
        $('.mask').fadeIn(function () {
            $('.again-wrapper').show();
        });
    }
    function ajaxSuccess (ret, obj) {
        var _this = obj;
        doing = false;
        if (ret.status == 0) {
            _this.removeClass('fired');
            _this.addClass('end');
            var data = ret.result;
            console.log(data);
            //isGet = true;
            outcome = parseInt(data.prize_id);
            //outcome = 2;
            prize_name = data.prize_name;
            prize_project = data.prize_project;
            prize_img = data.prize_img;

            leftTimes = data.num; //博饼次数
            $leftTimes.html(leftTimes);

            $(".img-word-has .prize-name").html(prize_project);
            $(".tit-has").html(prize_name);
            if(prize_img != ''){
                $(".img-has").css('background','url('+prize_img+') no-repeat center');
            }

            //6种结果全部进行判断
            //gameShow(outcome)
            switch (outcome) {
                case 1: //谢谢参与
                    thanks();
                    break;
                case 2: //一等奖
                    again();
                    break;
                case 3: //二等奖
                    again();
                    break;
                case 4: //三等奖
                    again();
                    break;
                case 5: //四等奖
                    again();
                    break;
                case 6: //五等奖
                    again();
                    break;
            }
            //localStorage.setItem('lastResult', JSON.stringify(saibao));

        } else {
            console.log(ret.ret_msg);
            window.T.errorWin(ret.ret_msg || '网络拥堵,稍后再试');
            $('.mask').show();
            pageReset();
        }

    }
}

//获取中奖通告内容
function scrollTxt(){
    var controls={},
        values={},
        t1=1000, /*播放动画的时间*/
        t2=1000, /*播放时间间隔*/
        si;
    controls.rollWrap=$("#winningScroll");
    controls.rollWrapUl=controls.rollWrap.children();
    controls.rollWrapLIs=controls.rollWrapUl.children();
    values.liNums=controls.rollWrapLIs.length;
    values.liHeight=controls.rollWrapLIs.eq(0).height();
    values.ulHeight=controls.rollWrap.height();
    this.init=function(){
        autoPlay();
        pausePlay();
    }
    /*滚动*/
    function play(){
        controls.rollWrapUl.animate({"margin-top" : "-"+values.liHeight}, t1, function(){
            $(this).css("margin-top" , "0").children().eq(0).appendTo($(this));
        });
    }
    /*自动滚动*/
    function autoPlay(){
        /*如果所有li标签的高度和大于.roll-wrap的高度则滚动*/
        if(values.liHeight*values.liNums > values.ulHeight){
            si=setInterval(function(){
                play();
            },t2);
        }
    }
    /*鼠标经过ul时暂停滚动*/
    function pausePlay(){
        controls.rollWrapUl.on({
            "mouseenter":function(){
                clearInterval(si);
            },
            "mouseleave":function(){
                autoPlay();
            }
        });
    }
}

//中奖记录跑马灯
function scrollAjax (){
    var dataNew = {};
    //$.post("/Home/Index/winSite", dataNew, function(res) {
        // console.log(res);
        var list = $("#winningScroll ul").find("li");
        //for(var i=0;i<res.data.length;i++){
        for(var i=0; i<list.length; i++){
            //var str1 = res.data[i].substr(0,res.data[i].indexOf("赢"));
            //var str2 = res.data[i].substr(res.data[i].indexOf("赢"),res.data[i].length);
            //$("#winningScroll ul").append('<li>'+str1+'<em>'+str2+'</em></li>');
            $("#winningScroll ul").append(list[i]);
        }

        new scrollTxt().init();
    //}, "json");
}

// 页面入口
$(function () {
    document.body.addEventListener('touchstart', function () { });
    if(attend == -3){
        $(".left-times").html("你没有权限参加活动！");
    }

    //点击活动详情
    $(".go-to-rules").click(function () {
        $('.mask').fadeIn(function(){
            $(".activity-detail").removeClass("scaleNone");
            $(".activity-detail").addClass("scaleBlock");
        })
        $(".main").css("overflow","hidden");
    });
    $(".activity-detail .close").click(function () {
        $(".activity-detail").removeClass("scaleBlock");
        $(".activity-detail").addClass("scaleNone");
        $('.mask').fadeOut()
        $(".main").css("overflow","auto");
    })

    //选项卡
    $(".detail-nav ul li").click(function() {
        $(this).addClass("active").siblings().removeClass("active");
        var index = $(this).index()
        $(".detail-con").eq(index).show().siblings(".detail-con").hide();
    });

    //iosNotice(); // 苹果设备增加说明
    //loadActInfo(); // 加载活动信息
   // userLotteryLeftTimes(); // 查询用户剩余抽奖次数
    //animationInit(); // 初始化动效
    bindEvent(); // 事件绑定
    //bindOne(); //新客0元享
    scrollAjax(); //中奖信息滚动
    setInterval(scrollAjax, 5000);

    var page = 1;
    var flag = true;
    var timers = null; //定时器(滚动加载方法 2 中用的)
    var LoadingDataFn = function () {
        if(flag){
            $.ajax({
                type: 'GET',
                url: '?m=web&c=activity&a=christmasPrizeList',
                data: { id:userId, type: 2, page: page},
                dataType: 'json',
                success: function(data){
                    console.log(data);
                    if (data.status == 0) {
                        var ht='';
                        var list = data.result;
                        var arrLen = list.length;
                        if(arrLen == 0) {
                            if(page == 1){
                                $('.detail-con2').html('<div class="no-data">暂无数据</div>');
                            }else{
                                //page = page - 1;
                                $('#next').html('已无更新数据');
                                flag = false;
                            }

                        }else if(arrLen > 0) {
                            for (var i = 0; i < arrLen; i++) {
                                ht += '<tr><td>' + list[i].add_time + '</td><td>'+ list[i].prize_project +'</td><td>'+ list[i].prize_name +'</td></tr>'
                            }
                            // 插入数据到页面，放到最后面
                            $('.detail-con2 tbody').append(ht);
                            $('#next').html('');
                        }

                    } else {
                        window.T.errorWin(data.ret_msg);
                        $('.mask').show();
                    }
                },
                error: function(xhr, type){
                    window.T.errorWin('网络拥堵,稍后再试');
                    $('.mask').show();
                }
            });
        }else{
            $('#next').html('已无更新数据');
        }

    }

    LoadingDataFn();

    $('.detail-con2').scroll(function() {
        //当时滚动条离底部60px时开始加载下一页的内容
        if (($(this)[0].scrollTop + $(this).height() + 60) >= $(this)[0].scrollHeight) {
            $('#next').html('加载中...');
            clearTimeout(timers);
            //这里还可以用 [ 延时执行 ] 来控制是否加载 （这样就解决了 当上页的条件满足时，一下子加载多次的问题啦）
            timers = setTimeout(function() {
                page++;
                //console.log("第" + page + "页");
                LoadingDataFn(); //调用执行上面的加载方法

            }, 800);
        }
    });

    // 每页展示5个
    //var size = 5;
    //$('.detail-con2 tbody').dropload({
    //    scrollArea : window,
    //    domDown : {
    //        domClass   : 'dropload-down',
    //        // 滑动到底部显示内容
    //        domRefresh : '<div class="dropload-refresh">↑上拉加载更多</div>',
    //        // 内容加载过程中显示内容
    //        domLoad    : '<div class="dropload-load"><span class="loading"></span>加载中...</div>',
    //        // 没有更多内容-显示提示
    //        domNoData  : '<div class="dropload-noData">没有更多内容</div>'
    //    },
    //    loadDownFn : function(me){
    //        page++;
    //        var ht='';
    //        $.ajax({
    //            type: 'GET',
    //            url: '?m=web&c=activity&a=christmasPrizeList',
    //            data: { id:userId, type: 2, page: page},
    //            dataType: 'json',
    //            success: function(data){
    //                console.log(data);
    //                if (data.status == 0) {
    //                    var list = data.result;
    //                    var arrLen = list.length;
    //
    //                    if(arrLen > 0) {
    //                        for (var i = 0; i < arrLen; i++) {
    //                            ht += '<tr><td>' + list[i].add_time + '</td><td>'+ list[i].prize_project +'</td><td>'+ list[i].prize_name +'</td></tr>'
    //                        }
    //                    }else{
    //                        me.lock(); // 锁定
    //                        me.noData(); // 无数据
    //                    }
    //
    //                    // 为了测试，延迟1秒加载
    //                    setTimeout(function(){
    //                        // 插入数据到页面，放到最后面
    //                        $('.detail-con2 tbody').prepend(ht);
    //                        // 每次数据插入，必须重置
    //                        me.resetload();
    //
    //                    },800);
    //
    //                } else {
    //                    window.T.errorWin(data.ret_msg);
    //                    $('.mask').show();
    //                }
    //            },
    //            error: function(xhr, type){
    //                window.T.errorWin('网络拥堵,稍后再试');
    //                $('.mask').show();
    //
    //                //alert('Ajax error!');
    //                // 即使加载出错，也得重置
    //                me.resetload();
    //            }
    //        });
    //    }
    //});

});
