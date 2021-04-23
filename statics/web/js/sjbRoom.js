var url = "wss://"+window.location.hostname+":"+wSport;
// var url = "wss://47.90.125.237:"+port;
var odds_explain ="赔率说明";
//倒计时
var time;
var order_no;
var music = [];
var check = false;
var check2 = false;
fnSet.countDown=function(msg){
}

//调整滚动条
fnSet.scrollTop =function(){
    setTimeout(function() {
        // 将窗口滚动条调整到底部
        var keyHeight =$(".bottomWarp").outerHeight();
        var roomNewsHeight =$('.roomNews').outerHeight();
        // if($(".bettingKey").css("display") =="none"){
        //     keyHeight =0;
        // }
        // var roomHeadHeight =$(".roomHead").outerHeight();//获取头部的高度
        if($(".roomHead").css("display") =="none"){
            $('.betTixing').css("top","44px");
            $('.room').css("top","44px");
        }else{
            if($(".betTixing").css("display") =="none"){
                $('.room').css("top","197px");
                $('.betTixing').css("top","197px");
            }else{
                $('.room').css("top","197px");
                $('.betTixing').css("top","197px");
            }
        }
        $(".customNews").css("bottom",keyHeight);
        //$('.room').css("bottom",keyHeight+roomNewsHeight);
        var pageHeight =$(".roomContent").outerHeight();
        $('.room').animate({scrollTop:pageHeight},500);
    }, 100);
}

//监听高度变化
$("body").on("input",".list-con", function(){
    //$('.room').css("bottom",$(".roomNews").outerHeight());
    fnSet.scrollTop();
})
//发言
$("body").on("click","#speak", function(){
    var content =$(".textArea>div").text();
    if(content !=""){
        var param = {
            "commandid": "3003",
            "nickname": userinfo.nickname,
            "content":content,
            "uid": userinfo.userid,
            "avatar":userinfo.head_url
        };
        //console.log(param);
        wsSendMsg(param);
        $(".textArea>div").text("");
       // var height =$(".roomNews").outerHeight();
        //roomBtu(height)
        fnSet.scrollTop();
    }else{
        fnSet.alert("内容不能为空！")
    }
})
var flagScroll = true;

//上拉加载
$(".quxiao ul").on("scroll",function(){
    var liHeight = $(this).children().outerHeight() * offSet;
    if(offSet < totalZu){
        if(flagScroll){
            var param = {
                "commandid": "3017",
                "uid": userinfo.userid,
                "roomid":userinfo.room_id,
                "lottery_type":userinfo.lottery_type,
                "offSet":offSet
            };
            wsSendMsg(param);
            flagScroll = false;
        }
    }
})

//取消当前投注
var flag = false;
$("body").on("click",".betTixing",function(){
    if(flag){
      $(".betTixing .jt").css("transform","rotate(0deg)");
        flag =false;
    }else{
        $(".betTixing .jt").css("transform","rotate(180deg)");
        flag =true;
    }
    $(".quxiao").toggle();
})
//取消当前投注确认
$("body").on("click","#querenBut",function(){
    var param = {
        "commandid": "3016",
        "uid": userinfo.userid,
        "order_no": order_no,
    };
    wsSendMsg(param);
    $(".popup").hide();
})

//取消指定投注
$(".quxiao ul li .close").live('click', function() {
    order_no = $(this).attr("order-no"); //赋予全局订单号
    var quxiaoT = '<div class="config-confirm"><div class="tit">提示</div><div class="con"><p>取消投注后将返还所有下注金额，是否继续？</p></div><div class="btn"><button id="querenBut" class="confirm">确认</button><button class="cancel">取消</button></div></div>';
    $(".popup").html(quxiaoT);
    $(".popup").show();
});

//取消当期所有投注
$("body").on("click","#quxiaoSy",function(){
    var quxiaoT = '<div class="config-confirm"><div class="tit">提示</div><div class="con"><p>取消投注后将返还所有下注金额，是否继续？</p></div><div class="btn"><button id="qbBut" class="confirm">确认</button><button class="cancel">取消</button></div></div>';
    $(".popup").html(quxiaoT);
    $(".popup").show();
    totalZu=0;
})

//取消当前所有投注确认
$("body").on("click","#qbBut",function(){
    var param = {
        "commandid": "3009",
        "uid": userinfo.userid,
    };
    wsSendMsg(param);
    var issue = $("#issue").attr("data-issue");
    // $(".issue_"+issue).remove();
    $(".popup").hide();
})

//最小投注
$(".bpTop").on("click",".bet-min", function() {
    var limit1  = Number($("#lower").val());
    var limit2  = Number($("#user_group_lower").val());
    if(limit1 > limit2){
        var limit = limit1;
    }else{
        var limit = limit2;
    }
    $("#moneyInput1").val(limit);
    moneyMany=limit;
    $(".moneyMany").html(moneyMany*realZhuMany);
    $(".cmPanel div").each(function(){
        $(this).removeClass("ckedYuanbao");
    })
})

//弹出隐藏投注键盘
$("body").on("click","#bettingBtu", function(event){
    $(".betWarp").show().addClass("betting").removeClass("chasing");
    if($("#lower").val() -  $("#user_group_lower").val() >= 0){
        $(".stake-limit span").text($("#lower").val());
    }else{
        $(".stake-limit span").text($("#user_group_lower").val());
    }
    setTimeout(function () {
        $(".betCon").css("top","197px");
    },20)
    $(".betLeftNav ul li").eq(0).click();
});

//隐藏投注键盘
$(".betWarp").click(function(e){
   if($(this).hasClass('betting')){
       $(this).hide().removeClass("betting");
   }else if($(this).hasClass('chasing')){
    $(this).hide().removeClass("chasing");
   }
   $('.bettPanel').hide();
   $('.cmPanel').hide();
   $('.betRight').css('padding-bottom','10px');
   $('.betLeftNav').css('padding-bottom','0px');
});
$(".betCon").on("click",function (event) {
    event.stopPropagation();    //  阻止事件冒泡
});
$("#moneyInput1").focus(function(){
    $(".betfun .chip span").each(function(){
        $(this).removeClass("active");
    })
})

//追号翻倍
//加的效果
$(".add").click(function () {
    var n = $(this).prev().val();
    var num = parseInt(n) + 1;
    if (num == 0) {
        return;
    }
    $(this).prev().val(num);
});
//减的效果
$(".lessB").click(function () {
    var n = $(this).next().val();
    var num = parseInt(n) - 1;
    if (num == 0) {
        return
    }
    $(this).next().val(num);
});
//撤单
function chedan(number,obj) {
    var icoTime = $(".icoTime").text();
    var type = 0;
    if(icoTime == "已封盘" || icoTime == "开奖中"){
        // type = 1
        //fnSet.alert("不能撤单");
    }
    var param = {
        "commandid": "3016",
        "uid": userinfo.userid,
        "order_no": number
    };
    wsSendMsg(param);
}

//弹出层上面的X
$("body").on("click",".configClose,.cancel,.win-close",function(){
    $(".popup").fadeOut();
})
$(".zhui_details_w .zhui_con").on("click",function(e){
    return false;
    // e.stopPropagation();
})

//跟投
$("body").on("click",".userBetting dd .bet-con-self, .userBetting dd .bet-con",function(){
    var nameMz =$(this).parent().siblings("h3").find("b").text();
    var timeTz =$(this).children("div").children("span").eq(0).text()
    var gentou ='<div class="config-confirm"><h3>跟投</h3><div class="gentouCon">';
    var genZhi = $(this).find("p.leftTbFlex");
    var player = $(this).siblings("h3").children("b").html();
    var sgMoney = $(this).attr('data-sgMoney');    
    gentou+='<div class="gentou_player"><label>玩家:</label><span>'+player+'</span></div><div class="con"><div class="con-l">类别:</div><div class="con-r">';
    for(var i=0; i<genZhi.length; i++){
        gentou+='<p><span style="color: #d22727">'+genZhi.eq(i).find("span.oddWayTitle").text()+'</span> <label class="configAcer" style="text-align: right;"><em>'+genZhi.eq(i).find("em").text()+'</em></label></p>'
    }
    gentou+='</div></div></div><div class="cigBtn"><button id="genTou" data-sgMoney="'+sgMoney+'">确定</button><button class="cancel">取消</button></div>';
    $(".popup").html(gentou);
    $(".popup").show();
});

$("body").on("click","#genTou",function(){
    var genWanf =new Array();
    var genJine =new Array();
    var genZhi = $('.con-r').find("p");
    for(var i=0; i<genZhi.length; i++){
        genWanf.push($(genZhi[i]).children('span').eq(0).html());
        genJine.push($(genZhi[i]).find('em').html());
    }
    var sgMoney = $(this).attr('data-sgMoney').split(',');
    var param = {
        "commandid": "3006",
        "nickname": userinfo.nickname,
        "way":genWanf,
        "money":genJine,
        "avatar":userinfo.head_url,
        "ext_a":1        
    };
    wsSendMsg(param);
    // genWanf=[];
    // genJine=[];
    $(".popup").hide();
})

$('.popup').on('click','.cancel',function(){
    $('.popup').hide();
});
//后台返回3004 前端推送数据
fnSet.update34 =function(msg){
    var roomGroup;
    var float="left";
    var honor ="";
    var icon ="";
    msg.content =msg.content.replace(/\\r|\\n/g, "\n");

    var check_code_limit ="\u3010\u8ffd\u53f7\u3011\u6295\u6ce8\u6210\u529f";
    var limit_data_list = msg.content.split(" ");
    if(limit_data_list[3]===check_code_limit) {
        var limit_exist = false;
        $(".issue_"+limit_data_list[0]).each(function () {
            if($(this).attr("name") == limit_data_list[1]) {
                limit_exist = true;
                var limit_money = parseFloat(limit_data_list[2])+parseFloat($(this).val());
                $(this).val(limit_money.toFixed(2));
            }
        });
        if(!limit_exist) $("body").eq(0).prepend("<input type='hidden' name='"+limit_data_list[1]+"' class='issue_"+limit_data_list[0]+"' value='" +limit_data_list[2]+ "'>");
    }

    if(Number(msg.honor_status) == 1){
        honor ='<i class="level-'+Number(msg.sort)+'"></i>';
    }
    if(msg.icon){
        icon = "<span><img src='"+msg.icon+"' /> </span>";
    }

    if(msg.nickname ==""||msg.is_popup_msg==1){
        //系统消息
        if(msg.content.indexOf("欢迎")!= -1){
            if(msg.username == userinfo.nickname){
                //刚进房间时设置投注区荣誉等级的图片
                $(".stake-icon").eq(0).css("background","url(/statics/web/images/honor/mbadge0"+Number(msg.sort)+"_default@2x.png) no-repeat #4d4c4b center");
                $(".stake-icon").eq(1).css("background","url(/statics/web/images/honor/mbadge0"+Number(msg.sort)+"@2x.png) no-repeat #4d4c4b center");
            }
            if(Number(msg.honor_status) == 1){
                msg.content = msg.content.replace(/{#username}/g, '<i class="level-'+Number(msg.sort)+'"></i>' + msg.username);
            }else{
                msg.content = msg.content.replace(/{#username}/g, msg.username);
            }

            roomGroup ='<div class="userBetting2"><ul class="welcome"><li style="font-size: 12px;"><pre>'+msg.content+'</pre></li></ul></div>'
        }else{
            roomGroup ='<div class="userBetting2"><ul class="system"><li style="font-size: 12px;"><div class="content_l"><pre>'+msg.content+'</pre></div></li></ul></div>'; //系统提示
        }

    }else{
        if(msg.uid == userinfo.userid){
            float="right";
            roomGroup ='<div class="userBetting"><ul class="'+float+'"><li data-href="'+ tz_record +'"><img src="'+msg.avatar+'"></li><li style="font-size: 14px;"><h3 style="text-align: '+float+'"><b>'+userinfo.nickname+'</b>'+honor+icon+'</h3><div style="text-align: right;"><div class="content_r"><pre>'+msg.content+'</pre></div></div>'
        }else{
            roomGroup ='<div class="userBetting"><ul class="'+float+'"><li data-href="'+ tz_record +'"><img src="'+msg.avatar+'"></li><li style="font-size: 14px;"><h3 style="text-align: '+float+'"><b>'+msg.nickname+'</b>'+honor+icon+'</h3><div style="text-align: left;"><div class="content_l"><pre>'+msg.content+'</pre></div></div>';
        }

        var oTime = timeHandle();
        roomGroup +='<span class="timeRecord">'+oTime+'</span></li></ul></div>';
    }

    if(msg.status){
        fag1 =true;
    }
    $(".room .roomContent").append(roomGroup);
    fnSet.scrollTop();
}

//后台返回3005 前端推送数据
fnSet.update35 =function(msg){
    fnSet.alert(msg.content);
}
//后台返回3007 前端推送数据
fnSet.update37 =function(msg){
    var roomGroup;
    var float ="left";
    var tzRecord = "";
    var honor ="";
    var icon="";
    var issue ="";
    issue = msg.issue;
    if(Number(msg.honor_status) == 1){
        honor ='<i class="level-'+Number(msg.sort)+'"></i>';
    }
    if(msg.icon){
        icon = "<span><img src='"+msg.icon+"' /> </span>";
    }
    if(msg.uid == userinfo.userid){
        $(".keyboard").click();
        float ="right";
        //$(".room").css("padding-top","110px")
        $(".betTixing span em").html('本期已下'+parseInt(msg.total_zushu+totalZu)+'注');
        offSet += msg.way.length;                
        totalZu=msg.total_zushu+totalZu;        
        $(".betTixing").show();

        $(".textArea >div").html("");
        $(".textArea").html('<div contenteditable="true" data-content="true"></div>');
        for(var i=0; i<msg.way.length; i++){
            var limit_exist = false;
            if($(".issue_"+issue).html()!=undefined) {
                $(".issue_"+issue).each(function () {
                    if($(this).attr("name") == msg.way[i]) {
                        limit_exist = true;
                        var limit_money = parseFloat(msg.money[i])+parseFloat($(this).val());
                        $(this).val(limit_money.toFixed(2));
                    }
                });
            }
            if(!limit_exist) $("body").eq(0).prepend("<input type='hidden' name='"+msg.way[i]+"' class='issue_"+issue+"' value='"+msg.money[i]+"'>");
        }
        fag1 =true;
        aWanf=[];
        aJine=[];
        roomGroup ='<div class="userBetting" ><dl class="'+float+'" data-issue="'+msg.issue+'"><dt data-href="'+ tz_record +'&fromRoom=1"><img src="'+msg.avatar+'"></dt><dd><h3 style="text-align: '+float+'"><b>'+userinfo.nickname+'</b>'+honor+icon+'</h3><div class="bet-con-self" data-sgMoney="'+msg.single_money+'"><div class="issueCon"><em></em><span>'+msg.time+'</span></div><div class="tbTitle"><span>投注类型</span><em>投注金额</em></div>';
    } else {  //除了游客和注册用户的其他
        roomGroup ='<div class="userBetting" ><dl class="'+float+'" data-issue="'+msg.issue+'"><dt><img src="'+msg.avatar+'"></dt><dd><h3 style="text-align: '+float+'"><b>'+msg.nickname+'</b>'+honor+icon+'</h3><div class="bet-con" data-sgMoney="'+msg.single_money+'"><div class="issueCon"><em></em><span>'+msg.time+'</span></div><div class="tbTitle" style="color:#6e6e6e !important;"><span>投注类型</span><em>投注金额</em></div>';
    }

    for(var i=0; i<msg.way.length; i++){

        tzRecord += '<li><font>'+msg.time+'</font><label>' + msg.way[i] +'<font style="margin-left:5px">'+msg.pan_kou[i]+ '</font></label> <span>' + msg.money[i] + '<i class="icoAcer"></i></span></li>'; //添加投注记录
        roomGroup+='<p class="leftTbFlex" order-no="'+msg.order_no[i]+'"><span class="oddWayTitle">'+msg.way[i]+'</span>'+msg.pan_kou[i]+'<font class="r"><em>'+msg.money[i]+'</em><i class="iconMoney"></i></font></p>'

    }

    var oTime = timeHandle();
    roomGroup +='<i class="mask"></i></div><span class="timeRecord">'+oTime+'</span></dd></dl></div>';
    $(".room .roomContent").append(roomGroup);

    if(msg.uid == userinfo.userid) {
        $(".quxiao ul").append(tzRecord);
    }

    fnSet.scrollTop();
}
var is324 = false
fnSet.update324 = function(){
    //更新赔率
    getOdds()
    is324=true
    var activeIndex = $('.active')
    if(endState==0){
        if(activeIndex.attr('data-title')=='独赢盘'){
            fnSet.showOdds(0,0);
        }else if(activeIndex.attr('data-title')=='全场'){
            fnSet.showOdds(1,0);        
        }else if(activeIndex.attr('data-title')=='半场'){
            fnSet.showOdds(2,0);
        }else if(activeIndex.attr('data-title')=='单双'){
            fnSet.showOdds(3,0,true);
        }else if(activeIndex.attr('data-title')=='总入球'){
            fnSet.showOdds(4,0,true);        
        }else if(activeIndex.attr('data-title')=='半/全场'){
            fnSet.showOdds(5,0,true);
        }else if(activeIndex.attr('data-title')=='波胆'){
            fnSet.showOdds(6,0,true);
        }else if(activeIndex.attr('data-title')=='加时'){
            fnSet.showOdds(7,0);
        }else if(activeIndex.attr('data-title')=='点球'){
            fnSet.showOdds(8,0);
        }
    }else{
        if(activeIndex.attr('data-title')=='独赢盘'){
            fnSet.showOdds(0,0);
        }else if(activeIndex.attr('data-title')=='全场'){
            fnSet.showOdds(1,0);        
        }else if(activeIndex.attr('data-title')=='半场'){
            fnSet.showOdds(2,0);
        }else if(activeIndex.attr('data-title')=='单双'){
            fnSet.showOdds(3,0);
        }else if(activeIndex.attr('data-title')=='总入球'){
            fnSet.showOdds(4,0);        
        }else if(activeIndex.attr('data-title')=='半/全场'){
            fnSet.showOdds(5,0);
        }else if(activeIndex.attr('data-title')=='波胆'){
            fnSet.showOdds(6,0);
        }else if(activeIndex.attr('data-title')=='加时'){
            fnSet.showOdds(7,0);
        }else if(activeIndex.attr('data-title')=='点球'){
            fnSet.showOdds(8,0);
        }
    }
    
    // zhuMany=0;//清空已选
    // oddsWay=[];
    // moneyArr=[];
    // lastDataTitle=[];
    // $('.bettPanel').hide();
    // $('.cmPanel').hide();
}
var totalZu;
fnSet.update318 =function(msg){
    var roomGroup;
    var float ="left";
    var tzRecord = "";
    var honor ="";
    var icon="";
    if(msg.data.length){
        $(".betTixing span em").html('本期已下'+msg.totalZu+'注');
        totalZu =msg.totalZu;
    }else{
        $(".quxiao ul").html("");
        $(".betTixing").hide();
        totalZu=0
    }
    for(var t=0; t<msg.data.length; t++){
        if(msg.data[t].nickname ==""){
            msg.data[t].nickname =msg.data[t].username;
        }
        if(msg.data[t].avatar==""){
            msg.data[t].avatar ="/up_files/room/avatar.png";
        }
        if(Number(msg.data[t].honor_status)){
            honor ='<i class="level-'+Number(msg.data[t].sort)+'"></i>';
        }

        if(msg.data[t].icon){
            icon = "<span><img src='"+msg.data[t].icon+"' /> </span>";
        }
        var dataIssue = msg.data[t].issue.substr(4);
        if(msg.data[t].uid == userinfo.userid){
            offSet++;
            $(".keyboard").click();
            float ="right";
            $(".betTixing").show();
            $(".textArea >div").html("");
            fag1 =true;
            aWanf=[];
            aJine=[];

            roomGroup ='<div class="userBetting" ><dl class="'+float+'"><dt data-href="'+ tz_record +'"><img src="'+msg.data[t].avatar+'"></dt><dd><h3  style="text-align: '+float+'"><b>'+userinfo.nickname+'</b>'+honor+icon+'</h3><div class="bet-con-self"><div class="issueCon" "><em></em><span>'+msg.data[t].addtime+'</span></div><div class="tbTitle"><span>投注类型</span><em>投注金额</em></div>';
        } else {
            float ="left";
            roomGroup ='<div class="userBetting" ><dl class="'+float+'"><dt><img src="'+msg.data[t].avatar+'"></dt><dd><h3  style="text-align: '+float+'"><b>'+msg.data[t].nickname+'</b>'+honor+icon+'</h3><div class="bet-con"><div class="issueCon" "><em></em><span>'+msg.data[t].addtime+'</span></div><div class="tbTitle" style="color: #6e6e6e !important"><span>投注类型</span><em>投注金额</em></div>';
        }

        tzRecord = '<li><font>'+msg.data[t].addtime+'</font><label>'+ msg.data[t].way +'<font style="margin-left:5px">'+msg.data[t].pan_kou+'</font></label> <span>'+ msg.data[t].money +'<i class="icoAcer"></i></span></li>';  //添加投注记录

        
        roomGroup+='<p class="leftTbFlex" order-no="'+ msg.data[t].order_no +'"><span class="oddWayTitle">'+msg.data[t].way+'</span>'+msg.data[t].pan_kou+'<font class="r"><em>'+msg.data[t].money+'</em><i class="iconMoney"></i></font></p>'

        var oTime = timeHandle();
        roomGroup +='<i class="mask"></i></div><span class="timeRecord">'+oTime+'</span></dd></dl></div>';
        $(".room .roomContent").append(roomGroup);

        if(msg.data[t].uid == userinfo.userid){
            $(".quxiao ul").append(tzRecord);
        }
    }
    if($('#zhuiHao').css("display") == "block"){
        $("#chaseNumber").click();
    }
    fnSet.scrollTop();
    flagScroll = true;
    
}
fnSet.update319 =function(msg){
    var roomGroup;
    var float ="right";
    var tzRecord = "";
    var honor ="";
    var msg = msg.data;
    if(Number(msg.honor_status) == 1){
        honor ='<i class="level-'+Number(msg.sort)+'"></i>';
    }

    $(".keyboard").click();
    //$(".room").css("padding-top","110px")
    $(".textArea >div").html("");
    $(".textArea").html('<div contenteditable="true" data-content="true"></div>');

    fag1 =true;
    aWanf=[];
    aJine=[];
    var begin_issue = '';
    var end_issue = '';
    begin_issue = msg.begin_issue;
    end_issue = msg.end_issue;
    begin_issue = msg.begin_issue.substr(4);
    end_issue = msg.end_issue.substr(4);
    roomGroup ='<div class="userBetting" ><dl class="'+float+'" data-issue="'+issue+'"><dt data-href="'+ tz_record +'"><img src="'+ userinfo.head_url+'"></dt><dd><h3 style="text-align: '+float+'">'+honor+'<b>'+userinfo.nickname+'</b></h3><div class="chase-con" data-sgMoney="'+msg.single_money+'"><div class="issueCon"><em></em>追号: <span>'+begin_issue+'-'+end_issue+'期</span></div><p class="chase-tit"><span>投注类型</span><span>金额</span><span>翻倍</span></p>';

    for(var i=0; i<msg.bet_array.length; i++){
        roomGroup += '<p><span>'+msg.bet_array[i].way+'</span><span>'+msg.bet_array[i].money+'</span><span>'+msg.bet_array[i].multiple+' 倍</span></p>'; //聊天室投注消息
    }
    roomGroup += '<div class="chase-static"><span>共 <em>'+msg.count+'</em> 注</span><span>总计: <em>'+msg.total_amount+'</em>元宝</span></div><i class="mask"></i>';
    var oTime = timeHandle();
    roomGroup +='</div><span class="timeRecord">'+oTime+'</span></dd></dl></div>';
    $(".room .roomContent").append(roomGroup);
    fnSet.scrollTop();
}

//调整滚动条
fnSet.scrollTop =function(){
    setTimeout(function() {
        // 将窗口滚动条调整到底部
        var keyHeight =$(".bottomWarp").outerHeight();
        var roomNewsHeight =$('.roomNews').outerHeight();
        // if($(".bettingKey").css("display") =="none"){
        //     keyHeight =0;
        // }
        // var roomHeadHeight =$(".roomHead").outerHeight();//获取头部的高度
        if($(".roomHead").css("display") =="none"){
            $('.betTixing').css("top","44px");
            $('.room').css("top","44px");
        }else{
            if($(".betTixing").css("display") =="none"){
                $('.room').css("top","197px");
                $('.betTixing').css("top","197px");
            }else{
                $('.room').css("top","197px");
                $('.betTixing').css("top","197px");
            }
        }
        $(".customNews").css("bottom",keyHeight);
        //$('.room').css("bottom",keyHeight+roomNewsHeight);
        var pageHeight =$(".roomContent").outerHeight();
        $('.room').animate({scrollTop:pageHeight},500);
    }, 100);
}
fnSet.button = function(oddsWay, moneyArr,zdOddsTiltleList,isLm){
    if (oddsWay.length) {
        var html = '';
        for (var i = 0; i < oddsWay.length; i++) {
            html += '<p><em class="issue">' + zdOddsTiltleList[i] + '</em><label>' + oddsWay[i] + '</label> <span>' + moneyArr[i] + '</span><em class="close" data-isLm="'+isLm+'"></em></p>'
        }
        $(".betList").children(".list-con").html(html);
    }
    if( $(".betList .list-con p").length >3){
        var pheight = $(".betList .list-con p").height();
        $(".betList").scrollTop(($(".betList .list-con p").length -3)*pheight)
    }
}

var oddsData = [];

function commShowOdds(data,dataIndex,oddsClass,flag){
    // var shit = false;
    // if(){
    //     shit = true;
    // }
    if(is324){
        for(var j=0;j<data.length;j++){
            var thisVal = data[j];
            var way = thisVal.way.split("_")[1]
            var that = $('.checkPanel1>div').eq(j)
            that.attr('data-title',thisVal.way)
            if(flag){
                if(that.hasClass('lockedOdds')){
                    that.addClass('ckOdds1')
                    that.removeClass('lockedOdds')
                }
                that.html('<span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span>')
            }else{
                if(that.hasClass('ckOdds1')){
                    that.addClass('lockedOdds')
                    that.removeClass('ckOdds1')
                }
                that.html('<span>'+way+'</span><span class="ysdText">已锁定</span>')                    
            }
        }
        return
    }
    if(oddsClass=='ckOdds1'){
        var html = '';
        html+='<div class="checkPanel1">';
        for(var j=0;j<data.length;j++){//单双
            var thisVal = data[j];
            var way = thisVal.way.split("_")[1]
            if(flag&&thisVal.odds!=""&&thisVal.odds!=null){
                html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
            }else{
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }
        }
        html+='</div>';
        $('.data-con').html(html);
        $('.data-con').append('<p class="xsTip">注：单双玩法在开赛后截止投注</p>')
        return;
    }
    if(oddsClass=='ckOdds2'){
        //半场
        var html = '<h4>半场让球</h4>';
        html+='<div class="checkPanel1">';
        for(var j=0;j<data["半场让球"].length;j++){
            var thisVal = data["半场让球"][j];
            var way = thisVal.way.split("_")[1];
            if(thisVal.handicap==''||thisVal.odds==''){
                //锁定不能选
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }else{
                if(thisVal.handicap.substr(0,1)!='-'){
                    //正数不展示盘口
                    html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
                }else{
                    
                    html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap.substr(1)+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
                }
            }
        }
        html+='</div><h4>半场大小</h4><div class="checkPanel1">';
        for(var j=0;j<data["半场大小"].length;j++){
            var thisVal = data["半场大小"][j];
            var way = thisVal.way.split("_")[1];
            if(thisVal.handicap==''||thisVal.odds==''){
                //锁定不能选
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }else{
                html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
            }
        }
        html+='</div>';
        $('.data-con').html(html);
        return;
    }
    if(oddsClass=='ckOdds3'){
        //全场
        var html = '<h4>全场让球</h4>';
        html+='<div class="checkPanel1">';
        for(var j=0;j<data["全场让球"].length;j++){
            var thisVal = data["全场让球"][j];
            var way = thisVal.way.split("_")[1];
            if(thisVal.handicap==''||thisVal.odds==''){
                //锁定不能选
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }else{
                if(thisVal.handicap.substr(0,1)!='-'){
                    //正数不展示盘口
                    html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
                }else{
                    
                    html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap.substr(1)+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
                }
            }
        }
        html+='</div><h4>全场大小</h4><div class="checkPanel1">';
        for(var j=0;j<data["全场大小"].length;j++){
            var thisVal = data["全场大小"][j];
            var way = thisVal.way.split("_")[1];
            if(thisVal.handicap==''||thisVal.odds==''){
                //锁定不能选
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }else{
                html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
            }
        }
        html+='</div>';
        $('.data-con').html(html);
        return;
    }
    if(oddsClass=='ckOdds4'){
        //加时
        var html = '<h4>加时让球</h4>';
        html+='<div class="checkPanel1">';
        for(var j=0;j<data["加时让球"].length;j++){
            var thisVal = data["加时让球"][j];
            var way = thisVal.way.split("_")[1];
            if(thisVal.handicap==''||thisVal.odds==''){
                //锁定不能选
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }else{
                if(thisVal.handicap.substr(0,1)!='-'){
                    //正数不展示盘口
                    html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
                }else{
                    
                    html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap.substr(1)+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
                }
            }
        }
        html+='</div><h4>加时大小</h4><div class="checkPanel1">';
        for(var j=0;j<data["加时大小"].length;j++){
            var thisVal = data["加时大小"][j];
            var way = thisVal.way.split("_")[1];
            if(thisVal.handicap==''||thisVal.odds==''){
                //锁定不能选
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }else{
                html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
            }
        }
        html+='</div>';
        $('.data-con').html(html);
        $('.data-con').append('<p class="xsTip">注：加时赛在全场结束之后才能投注</p>')        
        return;
    }
    if(oddsClass=='ckOdds5'){
        // asddsadas
        //点球aaaaaa
        var html = '<h4>点球让球</h4>';
        html+='<div class="checkPanel1">';
        for(var j=0;j<data["点球让球"].length;j++){
            var thisVal = data["点球让球"][j];
            var way = thisVal.way.split("_")[1];
            if(thisVal.handicap==''||thisVal.odds==''){
                //锁定不能选
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }else{
                if(thisVal.handicap.substr(0,1)!='-'){
                    //正数不展示盘口
                    html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
                }else{
                    
                    html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap.substr(1)+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
                }
            }
        }
        html+='</div><h4>点球大小</h4><div class="checkPanel1">';
        for(var j=0;j<data["点球大小"].length;j++){
            var thisVal = data["点球大小"][j];
            var way = thisVal.way.split("_")[1];
            if(thisVal.handicap==''||thisVal.odds==''){
                //锁定不能选
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }else{
                html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
            }
        }
        html+='</div>';
        $('.data-con').html(html);
        $('.data-con').append('<p class="xsTip">注：点球在加时赛结束之后，点球开始之前才能投注</p>')        
        
        return;
    }
    if(oddsClass=='ckOdds6'){
        //总入球
        var html = '<h4>半场总入球数</h4>';
        html+='<div class="checkPanel1">';
        for(var j=0;j<data["半场入球"].length;j++){
            var thisVal = data["半场入球"][j];
            var way = thisVal.way.split("_")[1];
            if(flag&&thisVal.odds!=""&&thisVal.odds!=null){
                html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
            }else{
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }
        }
        html+='</div><h4>全场总入球数</h4><div class="checkPanel1">';
        for(var j=0;j<data["全场入球"].length;j++){
            var thisVal = data["全场入球"][j];
            var way = thisVal.way.split("_")[1];
            if(flag&&thisVal.odds!=""&&thisVal.odds!=null){
                html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
            }else{
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }
        }
        html+='</div>';
        $('.data-con').html(html);
        $('.data-con').append('<p class="xsTip">注：总入球玩法在开赛后截止投注</p>')
        return;
    }
    if(oddsClass=='ckOdds7'||oddsClass=='ckOdds8'){
        var html = '';
        html+='<div class="checkPanel1">';
        for(var j=0;j<data.length;j++){
            var thisVal = data[j];
            var way = thisVal.way.split("_")[1]
            if(flag&&thisVal.odds!=""&&thisVal.odds!=null){
                html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
            }else{
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }
        }
        html+='</div>';
        $('.data-con').html(html);
        if(oddsClass=='ckOdds7'){
            $('.data-con').append('<p class="xsTip">注：半/全场赛果玩法在开赛后截止投注</p>')
        }else{
            $('.data-con').append('<p class="xsTip">注：波胆赛果玩法在开赛后截止投注</p>')            
        }
        return;
    }
    if(oddsClass=='ckOdds9'){
        var html = '<h4>半场赛果</h4>';
        html+='<div class="checkPanel1">';
        for(var j=0;j<data["半场赛果"].length;j++){
            var thisVal = data["半场赛果"][j];
            var way = thisVal.way.split("_")[1];
            if(thisVal.odds==''){
                //锁定不能选
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }else{
                html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
            }
        }
        html+='</div><h4>全场赛果</h4><div class="checkPanel1">';
        for(var j=0;j<data["全场赛果"].length;j++){
            var thisVal = data["全场赛果"][j];
            var way = thisVal.way.split("_")[1];
            if(thisVal.odds==''){
                //锁定不能选
                html+='<div data-title="'+thisVal.way+'" class="lockedOdds"><span>'+way+'</span><span class="ysdText">已锁定</span></div>';
            }else{
                html+='<div data-title="'+thisVal.way+'" class="ckOdds1"><span>'+way+'</span><span>'+thisVal.handicap+'</span><span class="peilv1">'+thisVal.odds+'</span></div>';
            }
        }
        html+='</div>';
        $('.data-con').html(html);    
        
        return;
    }
}

//赔率信息拼接
fnSet.showOdds=function(dataIndex,elseIndex,flag){
    if(dataIndex==4){
        //总入球
        $('#betTitle').html('');
        var data = oddsData.panel_6;
        commShowOdds(data,dataIndex,'ckOdds6',flag);
    }else if(dataIndex==5){
        //半场、全场
        $('#betTitle').html('半/全场赛果');
        var data = oddsData.panel_7;
        commShowOdds(data,dataIndex,'ckOdds7',flag);
    }else if(dataIndex==6){
        //波胆
        $('#betTitle').html('波胆全场');
        var data = oddsData.panel_8;
        commShowOdds(data,dataIndex,'ckOdds8',flag);
                
    }else if(dataIndex==0){
        //独赢盘
        $('#betTitle').html('');
        var data = oddsData.panel_9;
        commShowOdds(data,dataIndex,'ckOdds9');
                
    }else if(dataIndex==3){
        //单双
        $('#betTitle').html('全场单双');
        var data = oddsData.panel_1;
        commShowOdds(data,dataIndex,'ckOdds1',flag);
    }else if(dataIndex==2){
        //半场
        $('#betTitle').html('');
        var data = oddsData.panel_2;
        commShowOdds(data,dataIndex,'ckOdds2');
    }else if(dataIndex==1){
        //全场
        $('#betTitle').html('');
        var data = oddsData.panel_3;
                
        commShowOdds(data,dataIndex,'ckOdds3');
                
    }else if(dataIndex==7){
        //加时
        $('#betTitle').html('');
        var data = oddsData.panel_4;        
                
        commShowOdds(data,dataIndex,'ckOdds4');
                
    }else if(dataIndex==8){
        //点球
        $('#betTitle').html('');
        var data = oddsData.panel_5;
        commShowOdds(data,dataIndex,'ckOdds5');
    }
}

var activeIndex = 0;//当前选中玩法
//玩法切换
$(".betLeftNav ul").on("click","li",function () {
    is324=false
    $('.desc').html("");
    activeIndex=$(this).index();
    var title = $(this).attr("data-title");
    $(this).addClass("active").siblings().removeClass("active");
    $('.betRight').scrollTop(0);
    $('.zhuMany').html(zhuMany);
    $('.moneyMany').html(0);
    if(endState==0){
        if($(this).attr('data-title')=='独赢盘'){
            fnSet.showOdds(0,0);
        }else if($(this).attr('data-title')=='全场'){
            fnSet.showOdds(1,0);        
        }else if($(this).attr('data-title')=='半场'){
            fnSet.showOdds(2,0);
        }else if($(this).attr('data-title')=='单双'){
            fnSet.showOdds(3,0,true);
        }else if($(this).attr('data-title')=='总入球'){
            fnSet.showOdds(4,0,true);        
        }else if($(this).attr('data-title')=='半/全场'){
            fnSet.showOdds(5,0,true);
        }else if($(this).attr('data-title')=='波胆'){
            fnSet.showOdds(6,0,true);
        }else if($(this).attr('data-title')=='加时'){
            fnSet.showOdds(7,0);
        }else if($(this).attr('data-title')=='点球'){
            fnSet.showOdds(8,0);
        }
    }else{
        if($(this).attr('data-title')=='独赢盘'){
            fnSet.showOdds(0,0);
        }else if($(this).attr('data-title')=='全场'){
            fnSet.showOdds(1,0);        
        }else if($(this).attr('data-title')=='半场'){
            fnSet.showOdds(2,0);
        }else if($(this).attr('data-title')=='单双'){
            fnSet.showOdds(3,0);
        }else if($(this).attr('data-title')=='总入球'){
            fnSet.showOdds(4,0);        
        }else if($(this).attr('data-title')=='半/全场'){
            fnSet.showOdds(5,0);
        }else if($(this).attr('data-title')=='波胆'){
            fnSet.showOdds(6,0);
        }else if($(this).attr('data-title')=='加时'){
            fnSet.showOdds(7,0);
        }else if($(this).attr('data-title')=='点球'){
            fnSet.showOdds(8,0);
        }
    }
    
    zhuMany=0;//清空已选
    oddsWay=[];
    moneyArr=[];
    lastDataTitle=[];
    $('.bettPanel').hide();
    $('.cmPanel').hide();
});
//赔率切换
$('.data-con').on('change','#selectPanel1',function(){//特码赔率切换
    var selectIndex=0;
    selectIndex=$(this).children('option:selected').index();
    fnSet.showOdds(activeIndex,selectIndex);
    zhuMany=0;//清空已选
    $('.zhuMany').html(zhuMany);
    $('.moneyMany').html(0);
    oddsWay=[];
    dataTitleList=[];
    moneyArr=[];//test
});

//红蓝绿波
var redBox = [1,2,7,8,12,13,18,19,23,24,29,30,34,35,40,45,46];
var blueBox = [3,4,9,10,14,15,20,25,26,31,36,37,41,42,47,48];
var greenBox = [5,6,11,16,17,21,22,27,28,32,33,38,39,43,44,49];

//已选注数和元宝总和等
var zhuMany = 0;
var realZhuMany = 0;
var moneyMany = 0;
var bet_min_money = 0;//最小投注额
var oddsWay=[];//投注数组
var moneyArr=[];//金额数组
var dataTitleList = [];//连码数组
var lastDataTitle = [];//连码最后显示结果
var lmOddsWay=[];//连码投注数组
//获取选中颜色
function getColor(that){
    for(var i=0;i<redBox.length;i++){
        if(that==redBox[i]){
            return '#D22727';
        }
    }
    for(var k=0;k<blueBox.length;k++){
        if(that==blueBox[k]){
            return '#1E87D9';
        }
    }
    for(var j=0;j<greenBox.length;j++){
        if(that==greenBox[j]){
            return '#4CA80F';
        }
    }
}

//公共选中操作
function commCkedOdds(){
    $('.bettPanel').show();
    $('.cmPanel').show();  
    $('.betLeftNav').css('padding-bottom','40px');
    $('.betRight').css('padding-bottom','70px');
}

//连选算法
function getRealZhu(a, b) {
    var topNum = 1;
    for(var i=a;i>a-b;i--){
      topNum = topNum*i;
    }
    var botNum = 1;
    for(var  j = 1; j <= b; j++){
      botNum = botNum*j;
    }
    let dataSum =  topNum / botNum;
    return dataSum
};

//打开追号面板
function opChasing(title){
    $('.cmPanel').hide();
    $(".stakeWarp").hide();
    $(".bettPanel").hide();
    $(".chase-wanf").html(title);
    $(".zhuiHaoList").hide();
    $(".chaseWarp").show();
    $(".zhuiHao").slideDown();
    $('.money-text').focus();
}

//注数选择
$('.data-con').on('click','.ckOdds1,.ckOdds2,.ckOdds3,.ckOdds4,.ckOdds5',function(){
    commCkedOdds();
    if($(".betWarp").hasClass("chasing")){
        //追号
        $('.bettPanel').hide();
        $('.cmPanel').hide();
        $(this).addClass('ckedOdds1');
        var title = $(this).attr("data-title");
        opChasing(title);
        return;
    };
    if($(this).hasClass('ckOdds1')||$(this).hasClass('ckOdds2')){
        //数字选择
        if($(this).hasClass('ckedOdds1')){
            for(var i=0;i<oddsWay.length;i++){
                if(oddsWay[i]==$(this).attr('data-title')){
                    //去掉操作
                    oddsWay.splice(i,1);
                    break;
                }
            }
            $(this).removeClass('ckedOdds1');
            if(zhuMany>=1){
                zhuMany--;
                realZhuMany=zhuMany;
                $('.zhuMany').html(zhuMany);
            }
            $('.moneyMany').html(realZhuMany*moneyMany);
            return;
        }
        zhuMany++;
        realZhuMany=zhuMany;
        oddsWay.push($(this).attr('data-title'));
        $('.zhuMany').html(zhuMany);    
        $(this).addClass('ckedOdds1');
        $('.moneyMany').html(realZhuMany*moneyMany);
    }else if($(this).hasClass('ckOdds3')){
        if($(this).hasClass('ckedOdds1')){
            for(var i=0;i<oddsWay.length;i++){
                if(oddsWay[i]==$(this).attr('data-title')){
                    //去掉操作
                    oddsWay.splice(i,1);
                    break;
                }
            }
            $(this).removeClass('ckedOdds1');
            if(zhuMany>=1){
                zhuMany=0;
                realZhuMany=zhuMany;
                $('.zhuMany').html(zhuMany);
            }
            $('.moneyMany').html(realZhuMany*moneyMany);
            return;
        }
        zhuMany=1;
        realZhuMany=zhuMany;
        var desc = $(this).attr('data-desc');
        $('.desc').html(desc);
        oddsWay[0]=($(this).attr('data-title'));
        $('.zhuMany').html(zhuMany);    
        $(this).addClass('ckedOdds1');
        $(this).siblings('div').removeClass('ckedOdds1');
        $('.moneyMany').html(realZhuMany*moneyMany);
    }else if($(this).hasClass('ckOdds4')){
        if($(this).hasClass('ckedOdds1')){
            for(var i=0;i<oddsWay.length;i++){
                if(oddsWay[i]==$(this).attr('data-title')){
                    //去掉操作
                    oddsWay.splice(i,1);
                    break;
                }
            }
            $(this).removeClass('ckedOdds1');
            if(zhuMany>=1){
                zhuMany=0;
                realZhuMany=zhuMany;                
                $('.zhuMany').html(zhuMany);
            }
            $('.moneyMany').html(realZhuMany*moneyMany);
            return;
        }
        zhuMany=1;
        realZhuMany=zhuMany;        
        var desc = $(this).attr('data-desc');
        $('.desc').html(desc);
        oddsWay[0]=($(this).attr('data-title'));
        $('.zhuMany').html(zhuMany);
        $(this).addClass('ckedOdds1');
        $(this).siblings('div').removeClass('ckedOdds1');        
        $('.moneyMany').html(realZhuMany*moneyMany);
    }else if($(this).hasClass('ckOdds5')){
        //尾数
        if($(this).hasClass('ckedOdds1')){
            for(var i=0;i<oddsWay.length;i++){
                if(oddsWay[i]==$(this).attr('data-title')){
                    //去掉操作
                    oddsWay.splice(i,1);
                    break;
                }
            }
            $(this).removeClass('ckedOdds1');        
            if(zhuMany>=1){
                zhuMany=1;
                realZhuMany=zhuMany;                
                $('.zhuMany').html(zhuMany);
            }
            $('.moneyMany').html(realZhuMany*moneyMany);
            return;
        }
        zhuMany=1;
        realZhuMany=zhuMany;        
        var desc = $(this).attr('data-desc');
        $('.desc').html(desc);
        oddsWay[0]=($(this).attr('data-title'));
        $('.zhuMany').html(zhuMany);    
        $(this).addClass('ckedOdds1');
        $(this).siblings('div').removeClass('ckedOdds1');
        $('.moneyMany').html(realZhuMany*moneyMany);
    }
});

//金额选择
$('.cmPanel').on('click','div',function(){
    $(this).addClass('ckedYuanbao');
    $(this).siblings('div').removeClass('ckedYuanbao');
    if($(this).attr('data-val')){
        var ckedMoney = parseInt($(this).attr('data-val'));
        moneyMany=ckedMoney;
        $('#moneyInput1').val(moneyMany);
        $('.moneyMany').html(realZhuMany*moneyMany);
        return;
    }
    var ckedMoney = parseInt($(this).attr('data-money'));
    moneyMany=ckedMoney;
    $('#moneyInput1').val(moneyMany);
    $('.moneyMany').html(realZhuMany*moneyMany);
});

//输入金额
$('#moneyInput1').bind('input propertychange',function(){
    moneyMany=$(this).val();
    $('.moneyMany').html(moneyMany*realZhuMany);
});
$('#moneyInput1').focus(function(){
    $('.ckedYuanbao').removeClass('ckedYuanbao');
});

//获取赔率信息
function getOdds() {
    $.ajax({
        url:"?m=web&c=odds&a=getOdds",
        type:'post',
        dataType:'json',
        data:{"room_id":userinfo.room_id},
        success:function(data) {
            if(data.code==0) {
                oddsData = data.data;
                var i=0;
                
                if(oddsData['panel_1']==undefined){
                    $(".betLeftNav ul li").each(function(){
                        if($(this).attr('data-title')=='单双'){
                            $(this).remove();
                        }
                    })
                }
                if(oddsData['panel_2']==undefined){
                    $(".betLeftNav ul li").each(function(){
                        if($(this).attr('data-title')=='半场'){
                            $(this).remove();
                        }
                    })
                }if(oddsData['panel_3']==undefined){
                    $(".betLeftNav ul li").each(function(){
                        if($(this).attr('data-title')=='全场'){
                            $(this).remove();
                        }
                    })
                }if(oddsData['panel_4']==undefined){
                    $(".betLeftNav ul li").each(function(){
                        if($(this).attr('data-title')=='加时'){
                            $(this).remove();
                        }
                    })
                }if(oddsData['panel_5']==undefined){
                    $(".betLeftNav ul li").each(function(){
                        if($(this).attr('data-title')=='点球'){
                            $(this).remove();
                        }
                    })
                }if(oddsData['panel_6']==undefined){
                    $(".betLeftNav ul li").each(function(){
                        if($(this).attr('data-title')=='总入球'){
                            $(this).remove();
                        }
                    })
                }if(oddsData['panel_7']==undefined){
                    $(".betLeftNav ul li").each(function(){
                        if($(this).attr('data-title')=='半场/全场'){
                            $(this).remove();
                        }
                    })
                }if(oddsData['panel_8']==undefined){
                    $(".betLeftNav ul li").each(function(){
                        if($(this).attr('data-title')=='波胆'){
                            $(this).remove();
                        }
                    })
                }if(oddsData['panel_9']==undefined){
                    $(".betLeftNav ul li").each(function(){
                        if($(this).attr('data-title')=='独赢盘'){
                            $(this).remove();
                        }
                    })
                }
            }
        }
    });

}

//投注
$('.bpBtnPanel').on('click','#bettingBtn1',function(){
    if(realZhuMany<1){
        lastDataTitle=[];
        layer.open({
            content: '请至少选择一注',
            skin: 'msg',
            time: 2 //2秒后自动关闭
        });
        return;
    }else if(moneyMany<=0){
        layer.open({
            content: '请输入有效金额',
            skin: 'msg',
            time: 2 //2秒后自动关闭
        });
        return;
    }
    var sgMoneyArr=[];
    for(var i=0;i<oddsWay.length;i++){
        moneyArr.push(moneyMany);
        sgMoneyArr.push(moneyMany);
    }
    
    var param = {
        "commandid": "3006",
        "nickname": userinfo.nickname,
        "way":oddsWay,
        "money":moneyArr,
        "avatar":userinfo.head_url
    };
    wsSendMsg(param);
    dataTitleList=[];//清空连码数据
    lastDataTitle=[];
    lmOddsWay=[];
    $('.blueBox1').removeClass('blueBox1');
    $('.greenBox1').removeClass('greenBox1');
    $('.redBox1').removeClass('redBox1');
    $('.yuanIndex').css('background','white').css('color','#333');    
    $('.ckedOdds1').removeClass('ckedOdds1');
    $('.cmPanel').hide();
    $('.bettPanel').hide();
    $('.betWarp').hide().removeClass('betting');
    zhuMany=[];
    realZhuMany=[];
});

var zdMany=0;
var zdMoney=0;
var zdOddsWay=[];
var zdmoneyArr=[];
var zdManyArr=[];
var zdsgMoneyArr=[];
var zdOddsTiltleList=[];
//加入注单
$(".bpBtnPanel").on('click','#butJp',function(){
    if(realZhuMany<1){
        lastDataTitle=[];
        layer.open({
            content: '请选择玩法',
            skin: 'msg',
            time: 2 //2秒后自动关闭
        });
        return;
    }else if(moneyMany<=0){
        layer.open({
            content: '请输入有效金额',
            skin: 'msg',
            time: 2 //2秒后自动关闭
        });
        return;
    }
    //动画效果
    setTimeout(ballAnimation(),800);
    var oddTitle = $('.active').html();
    var isLm = false;
    for(var i=0;i<oddsWay.length;i++){
        zdOddsTiltleList.push(oddTitle);
        moneyArr.push(moneyMany);
        zdsgMoneyArr.push(moneyMany);
    }
    zdOddsWay = zdOddsWay.concat(oddsWay);
    zdmoneyArr = zdmoneyArr.concat(moneyArr);
    $('.blueBox1').removeClass('blueBox1');
    $('.greenBox1').removeClass('greenBox1');
    $('.redBox1').removeClass('redBox1');
    $('.yuanIndex').css('background','white').css('color','#333');
    $('.ckedOdds1').removeClass('ckedOdds1');    
    zdMany+=realZhuMany;
    zdManyArr.push(realZhuMany);
    zdMoney+=realZhuMany*moneyMany;
    fnSet.button(zdOddsWay, zdmoneyArr,zdOddsTiltleList,isLm);
    fnSet.scrollTop();
    dataTitleList=[];
    lastDataTitle=[];
    lmOddsWay=[];
    zhuMany=[];
    oddsWay=[];
    $(".bettPanel").hide();
    $(".cmPanel").hide();
    $(".stakeWarp").show();
    $(".hasStake").show();
    $('.stake-num').html(zdMany);
    $('.stake-num-txt span').html(zdMany);
    $('.stake-money').html(zdMoney);
    moneyArr=[];
    realZhuMany=[];
    $(".noStake").hide();
    $('.betLeftNav').css('padding-bottom','20px');
    $('.betRight').css('padding-bottom','20px');
});

//加入注单动画
function ballAnimation() {
    var $pointDiv = $('<div id="pointDivs">').appendTo('body');
    for(var i = 0; i < 5; i++) {
        $('<div class="point-outer point-pre"><div class="point-inner"/></div>').appendTo($pointDiv);
    }
    var startOffset = $(".ckedOdds1").offset();
    var endTop = window.screen.height - 50,
        endLeft = 15,
        left = startOffset.left,
        top = startOffset.top;
    var outer = $('#pointDivs .point-pre').first().removeClass("point-pre").css({
        left: left + 'px',
        top: top + 'px'			});
    var inner = outer.find(".point-inner");

    setTimeout(function() {
        outer[0].style.webkitTransform = 'translate3d(0,' + (endTop - top) + 'px,0)';
        inner[0].style.webkitTransform = 'translate3d(' + (endLeft - left) + 'px,0,0)';
        setTimeout(function() {
            outer.removeAttr("style").addClass("point-pre");
            inner.removeAttr("style");
        }, 500);
        //这里的延迟值和小球的运动时间相关
    }, 1);
    //小球运动坐标
}

//去下注
$('.hasStake').on('click','.stake-go',function(){
    $.confirm("本次投注：共"+zdMany+'注 '+zdMoney+"元宝",function(){
        var param = {
            "commandid": "3006",
            "nickname": userinfo.nickname,
            "way":zdOddsWay,
            "money":zdmoneyArr,
            "avatar":userinfo.head_url
        };
        wsSendMsg(param);
        dataTitleList=[];//清空连码数据
        lastDataTitle=[];
        lmOddsWay=[];
        $('.blueBox1').removeClass('blueBox1');
        $('.greenBox1').removeClass('greenBox1');
        $('.redBox1').removeClass('redBox1');
        $('.yuanIndex').css('background','white').css('color','#333');    
        $('.ckedOdds1').removeClass('ckedOdds1');
        $('.cmPanel').hide();
        $('.bettPanel').hide();
        $('.betWarp').hide().removeClass('betting');
        $('.noStake').show();
        $('.hasStake').hide();
        $('.betListWarp').hide();
        $('.list-con').html('');
        stakeflag=false;
        zhuMany=[];
        realZhuMany=[];
        zdMany=0;
        zdMoney=0;
        zdOddsWay=[];
        zdmoneyArr=[];
        zdsgMoneyArr=[];
        zdOddsTiltleList=[];
    });
});

//pkft打开注单
var stakeflag = false;
$(".stake-icon").on("click",function(){
    if(zdOddsWay.length>0){
        if(stakeflag){
            $(".betList").css("top","100%");
            setTimeout(function () {
                $(".betListWarp").hide();
            },500);
            stakeflag =false;
        }else{
            $(".betListWarp").show();

            $(".betList").css("top",$("body").height()-235);


            stakeflag =true;
        }
        //todo
        // $(".betLeftNav").css("height","392px");
        // $(".betRight").css("height","392px");
    }
})
$(".betListWarp").on("click",function () {
    stakeflag =false;
    $(".betList").css("top","100%");
    setTimeout(function () {
        $(".betListWarp").hide();
    },500);
})
$(".betList").on("click",function (event) {
    event.stopPropagation();
})

$('.list-tit').on("click touch",".list-empty",function(){
    //清除注单
    $('.list-con').html('');
    $('.betListWarp').hide();
    $(".betList").css("top","100%");
    $(".hasStake").hide();
    $(".noStake").show();
    stakeflag=false;
    zdMany=0;
    zdMoney=0;
    zdOddsWay=[];
    zdmoneyArr=[];
    oddsWay=[];
});

//清除单注注单
$('.list-con').on('click','.close',function(){
    console.log($(this).parent('p').index());
    for(var i=0;i<zdOddsWay.length;i++){
        if(i==$(this).parent('p').index()){
            var isLm = $(this).attr('data-isLm');
            var many = 1;
            if(isLm=='true'){
                many=zdManyArr[i];
            }
            zdMoney-=zdmoneyArr[i];
            zdMany-=many;
            $('.stake-num').html(zdMany);
            $('.stake-num-txt span').html(zdMany);
            $('.stake-money').html(zdMoney);
            zdOddsWay.splice(i,1);
            zdmoneyArr.splice(i,1);
            zdsgMoneyArr.splice(i,1);
            zdManyArr.splice(i,1);
        }
    }
    if(zdOddsWay.length<1){
        $('.list-con').html('');
        $('.betListWarp').hide();
        $(".betList").css("top","100%");
        $(".hasStake").hide();
        $(".noStake").show();
        stakeflag=false;
        zdMany=0;
        zdMoney=0;
        zdOddsWay=[];
        zdmoneyArr=[];
        zdsgMoneyArr=[];
        oddsWay=[];
        zdManyArr=[];
    }
    $(this).parent('p').remove();
    
});
//梭哈
$('.ybBg5').on('click',function(){
    $(this).attr('data-val',parseInt($('.icoAcer').html()));
});
//关闭金额面板
$('.bettCancel').on('click',function(){
    $('.bettPanel').hide();
    $('.cmPanel').hide();
});
//获取投注金额
$.ajax({
    url: "/index.php?m=web&c=openAward&a=getBetting",
    //data:{},
    type: "POST",
    dataType:'json',
    success:function(msg){
        if(msg.status == "0"){
            var list="";
            var chipNum="";
            for(var i=0; i<msg.list.length; i++){
                if(Number(msg.list[i]) >= 1000){
                    chipNum = Number(msg.list[i])/1000 + 'K'
                }else{
                    chipNum = msg.list[i];
                }

                list +='<div class="ybBg'+i+'" data-money='+msg.list[i]+'>'+ chipNum +'</div>';
            }
            $(".cmPanel").prepend(list);
            music = msg.music;
            bet_min_money = msg.list[0];
        }
    },
    error:function(er){
    }
});

//初始化信息
function limitFun(){
    $.ajax({
        url:"?m=api&c=dataCenter&a=getRoomLimite&id="+userinfo.room_id,
        type:'post',
        dataType:'json',
        success:function(data) {
            if(data.status.code==10000) {
                for (name in data.data) {
                    $("body").eq(0).prepend("<input type='hidden' name='" + name + "' value='" + data.data[name] + "' id='"+name+"'>");
                }
            }
        },
        error:function () {
            $.ajax({
                url:"?m=api&c=dataCenter&a=getRoomLimite&id="+userinfo.room_id,
                type:'post',
                dataType:'json',
                success:function(data) {
                    if(data.status.code==10000) {
                        for (name in data.data) {
                            $("body").eq(0).prepend("<input type='hidden' name='" + name + "' value='" + data.data[name] + "' id='"+name+"'>");
                        }
                    }
                }
            });
        }
    });

    $.ajax({
        url:"?m=api&c=dataCenter&a=getAllBet&user_id="+userinfo.userid+"&room_id="+userinfo.room_id,
        type:'post',
        dataType:'json',
        success:function(data) {
            if(data.status.code==10000) {
                for (name in data.data) {
                    //如果有当期有投注记录
                    $("body").eq(0).prepend("<input type='hidden' name='"+data.data[name].way+"' class='issue_"+data.data[name].issue+"' value='" + data.data[name].money + "'>");
                }
            }
        },
        error:function () {
            $.ajax({
                url:"?m=api&c=dataCenter&a=getAllBet&user_id="+userinfo.userid+"&room_id="+userinfo.room_id,
                type:'post',
                dataType:'json',
                success:function(data) {
                    if(data.status.code==10000) {
                        for (name in data.data) {
                            $("body").eq(0).prepend("<input type='hidden' name='"+data.data[name].way+"' class='issue_"+data.data[name].issue+"' value='" + data.data[name].money + "'>");
                        }
                    }
                }
            });
        }
    });

    $.ajax({
        url:"?m=api&c=dataCenter&a=getReverse",
        type:'post',
        dataType:'json',
        success:function(data) {
            if(data.status.code==10000) {
                for (name in data.data) {
                    $("body").eq(0).prepend("<input type='hidden' name='"+data.data[name].name+"' id='reverse_"+name+"' value='" + data.data[name].state + "'>");
                }
            }
        },
        error:function () {
            $.ajax({
                url:"?m=api&c=dataCenter&a=getReverse",
                type:'post',
                dataType:'json',
                success:function(data) {
                    if(data.status.code==10000) {
                        for (name in data.data) {
                            $("body").eq(0).prepend("<input type='hidden' name='"+data.data[name].name+"' id='reverse_"+name+"' value='" + data.data[name].state + "'>");
                        }
                    }
                }
            });
        }
    });
}

$(function(){
    getOdds();
    limitFun();
    $(".rstIssue span").text($(".rstIssue span").text().substr(4));
    var lottery =$(".lottery dl dd ul");
    for(var i=0; i < lottery.length; i++){
        lottery.eq(i).children("li").find("span").text(lottery.eq(i).children("li").find("span").text().substr(4));
    }
})