/**
 * Created by HCHT- on 2016/12/2.
 */
var port ="7272";
var url = "wss://"+window.location.hostname+":"+port;
// var url = "wss://47.90.125.237:"+port;
var odds_explain ="赔率说明";
//倒计时
var time;
var order_no;
var music = [];
var check = false;
var check2 = false;
fnSet.countDown=function(msg){
    clearInterval(time);
    var theTime2 = parseInt(msg.time);// 秒
    if(msg.stopOrSell == 1)
    {
        if(theTime2 && theTime2 > 0){
            time =setInterval(settime, 1000);
        }else{
            if(userinfo.lottery_type == 4){
                //幸运飞艇去掉前面4位期号
                $("#issue").text(msg.issue.substr(4));
            }else{
                $("#issue").text(msg.issue);
            }
            $("#issue").attr('data-issue',msg.issue);
            var param = {
                "commandid": "3012",
                "uid": userinfo.userid,
            };
            wsSendMsg(param);
            // $(".roomLi p:last").html('<span class="icoTime">开奖中</span>');
        }
    }else if(msg.stopOrSell == 2){
        $(".roomLi p:first").html(msg.stopMsg.split(" ")[0]);
        $(".roomLi p:last").html(msg.stopMsg.split(" ")[1]);
    }
    //开奖时间计时显示
    function settime() {
        if(theTime2 <= 0 ) {
            // 将定时器停止
            clearInterval(time);
            var param = {
                "commandid": "3012",
                "uid": userinfo.userid,
            };
            wsSendMsg(param);
            // $(".roomLi p:last").html('<span class="icoTime">开奖中</span>');
        }else {
            var tmpTime = theTime2 - msg.sealingTim;
            var theTime1 = parseInt(tmpTime/60);
            var theTime = parseInt(tmpTime%60);
            if(theTime1<10){
                theTime1 ="0"+theTime1;
            }
            if(theTime<10){
                theTime ="0"+theTime;
            }
            if(!check2){

                if(music && music.length > 7 ){
                    if(music[7].state == "1") {
                        $("#room_play").attr("src", music[7].url);
                        check2 = true;
                    }
                }
            }
            if(tmpTime >0 && check === true ){
                if(music && music.length > 8){
                    if(music[8].state == "1"){
                        $("#room_play").attr("src",music[8].url);
                        check = false;
                    }
                }

            }
            if(theTime2 && theTime2 <=msg.sealingTim){
                $(".icoTime").html('已封盘');
                if(check === false && tmpTime <= 0){
                    if(music && music.length > 9){
                        if(music[9].state == "1"){
                            $("#room_play").attr("src",music[9].url);
                            check = true;
                        }
                    }
                }
            }else{
                check = false;
                //$(".icoTime").html('<span class="icoTime">'+theTime1+'分'+theTime+'秒</span>');
                $(".icoTime").html(theTime1+'分'+theTime+'秒');
                // $(".icoTime").text(theTime1+"分"+theTime+"秒");
            }
            theTime2--;
        }
    }
}
fnSet.alert =function(text,sibtne){      //提示框
    var _alert = $(".popupAlert");
    a_sibtne = function() {
        $(this).parents(".popupAlert").remove();
        if (typeof(sibtne) == 'function') {
            sibtne();
        }
    }
    var alert ='<div class="popupAlert"><div class="config"><p>'+text+'</p><div class="button" style="margin-top: 30px;"><button class="confirm">确认</button></div></div></div>'
    // $("body").append(alert);
    if (_alert.length) {
        _alert.show().find(".config p").html(text);
    } else {
        $("body").append(alert);
    }
    $(".confirm").off("click").on("click", a_sibtne);
    // $(".confirm").on("click",function(){
    //     $(this).parents(".popupAlert").remove();
    // })
}
// fnSet.config =function(text,confirm,callOff){
//     var alert ='<div class="popupAlert"><div class="config"><p>'+text+'</p><div class="button" style="margin-top: 30px;"><button class="confirm">确认</button><button class="callOff">取消</button></div></div></div>'
//     $("body").append(alert);
//
//     $(".confirm").on("click",function(){
//         $(this).parents(".popupAlert").remove();
//     })
// }
//玩法赔率
fnSet.oddsUpdate = function(msg){
    var chase_number_dome = $(".sel");
    var panel_1 =msg.data.panel_1;
    var play1 ="";
    var panel_2 =msg.data.panel_2;
    var play2 ="";
    var panel_3 =msg.data.panel_3;
    var play3="";

    var indexT =chase_number_dome.children("option:selected").index();
    // selected
    if(indexT == -1){
        chase_number_dome.empty();
        chase_number_dome.append("<option value=''>请选择</option>");
        if(panel_1.length>0){
            for(var i=0; i<panel_1.length; i++){
                chase_number_dome.append("<option value='"+panel_1[i].title+"'>"+panel_1[i].title+"</option>")
            }
        }
        if(panel_2.length>0){
            for(var i=0; i<panel_2.length; i++){
                chase_number_dome.append("<option value='"+panel_2[i].title+"'>"+panel_2[i].title+"</option>")
            }
        }
        if(panel_3.length>0){
            for(var i=0; i<panel_3.length; i++){
                chase_number_dome.append("<option value='"+panel_3[i].title+"'>"+panel_3[i].title+"</option>")
            }
        }

    }
    // else{
    //     if(indexT!=0 && chase_number_dome.children("option:selected").val() != panel_1[indexT-1].title){
    //         chase_number_dome.empty();
    //         chase_number_dome.append("<option value=''>请选择</option>");
    //         if(panel_1.length>0){
    //             for(var i=0; i<panel_1.length; i++){
    //                 chase_number_dome.append("<option value='"+panel_1[i].title+"'>"+panel_1[i].title+"</option>")
    //             }
    //         }
    //         if(panel_2.length>0){
    //             for(var i=0; i<panel_2.length; i++){
    //                 chase_number_dome.append("<option value='"+panel_2[i].title+"'>"+panel_2[i].title+"</option>")
    //             }
    //         }
    //         if(panel_3.length>0){
    //             for(var i=0; i<panel_3.length; i++){
    //                 chase_number_dome.append("<option value='"+panel_3[i].title+"'>"+panel_3[i].title+"</option>")
    //             }
    //         }
    //     }
    // }

    for(var i=0; i<panel_1.length; i++){
        play1+="<li><div><span>"+panel_1[i].title+"</span><p>1:"+panel_1[i].value+"</p></div></li>";
    }

    $(".room-con-fr-odd-1").children("ul").html(play1);

    for(var i=0; i<panel_2.length; i++){
        play2+="<li><div class='color color"+panel_2[i].title+"' style='color:"+ panel_2[i].color +"'><span>"+panel_2[i].title+"</span><p>"+panel_2[i].value+"</p></div></li>";
        // chase_number_dome.append("<option value='"+panel_2[i].title+"'>"+panel_2[i].title+"</option>")
    }
    $(".room-con-fr-odd-2").children("ul").html(play2);

    
    if (panel_3.length != 0) {
        var colorArr = new Array("red1","lv","lan","huang");
        //var play3 ='<p class="desc">'+ panel_3[0].desc +'</p><ul>';
        for(var i=0; i<panel_3.length; i++){
            play3 += '<li><div  data-desc="'+ panel_3[i].desc +'"><span>'+ panel_3[i].title +'</span><p class="'+ colorArr[i] +'">1:' + panel_3[i].value+ '</p></div></li>';
            // chase_number_dome.append("<option value='"+panel_3[i].title+"'>"+panel_3[i].title+"</option>")
        }
        play3 += '</ul>'
        $(".room-con-fr-odd-3").children("ul").html(play3);
    }else{
        $(".room-con-fr-odd-3").remove();
    }
}

//后台返回3004 前端推送数据
fnSet.update34 =function(msg){
    var roomGroup;
    var float="left";
    var honor ="";
    var icon ="";
    msg.content =msg.content.replace(/\\r|\\n/g, "\n");
    if(msg.nickname ==""){
        msg.nickname ="机器人";
        msg.avatar ="/up_files/room/avatar.png";
    }
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
    if(msg.honor){
        honor ="<em>"+msg.honor+"</em>";
    }
    if(msg.icon){
        icon = "<span><img src='"+msg.icon+"' /> </span>";
    }
    if(msg.uid == userinfo.userid){
        float="right";
        roomGroup ='<div class="userBetting"><ul><li class="fr">'+
            '<div class="head-image" data-href="'+ tz_record +'"><img src="'+msg.avatar+'"></div>'+
            '<div class="news-fl"><h3>'+honor+icon+userinfo.nickname+'</h3>'+'<div class="news-con"><pre>'+msg.content+'</pre></div></div>'+
            '</li></ul></div>'
    }else{
        //roomGroup ='<div class="userBetting"><h3 style="text-align: '+float+'">'+msg.nickname+honor+icon+'</h3><ul class="'+float+'"><li><img src="'+msg.avatar+'"></li><li style="font-size: 14px;"><pre>'+msg.content+'</pre></li></ul></div>'
        roomGroup ='<div class="userBetting"><ul><li class="fl">'+
            '<div class="head-image"><img src="'+msg.avatar+'"></div>'+
            '<div class="news-fl"><h3>'+msg.nickname+honor+icon+'</h3>'+'<div class="news-con"><pre>'+msg.content+'</pre></div></div>'+
            '</li></ul></div>'
    }
    if(msg.status){
        fag1 =true;
    }
    $(".room-chat-con").append(roomGroup);
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
    if(userinfo.lottery_type ==4){
        msg.issue = msg.issue.substr(4);
    }
    if(msg.honor){
        honor ="<em>"+msg.honor+"</em>";
    }
    if(msg.icon){
        icon = "<span><img src='"+msg.icon+"' /> </span>";
    }
    if(msg.uid == userinfo.userid){
        //$(".keyboard").click();
        float ="right";
        //$(".room").css("padding-top","110px");
        $(".room-con-fr-top em").html(msg.count);
        //$(".betTixing").show();
        $(".betting ul").html("");
        $(".textArea").html("");
        for(var i=0; i<msg.way.length; i++){
            var limit_exist = false;
            if($(".issue_"+msg.issue).html()!=undefined) {
                $(".issue_"+msg.issue).each(function () {
                    if($(this).attr("name") == msg.way[i]) {
                        limit_exist = true;
                        var limit_money = parseFloat(msg.money[i])+parseFloat($(this).val());
                        $(this).val(limit_money.toFixed(2));
                    }
                });
            }
            if(!limit_exist) $("body").eq(0).prepend("<input type='hidden' name='"+msg.way[i]+"' class='issue_"+msg.issue+"' value='"+msg.money[i]+"'>");
        }
        fag1 =true;
        aWanf=[];
        aJine=[];
        //roomGroup ='<div class="userBetting" ><h3 style="text-align: '+float+'">'+honor+icon+'<b>'+userinfo.nickname+'</b></h3><dl class="'+float+'" data-issue="'+msg.issue+'"><dd>';
        roomGroup ='<div class="userBetting"><ul><li class="fr" data-issue="'+msg.issue+'">'+ '<div class="head-image" data-href="'+tz_record+'"><img src="'+msg.avatar+'"></div>'+
            '<div class="news-fl"><h3>'+honor+icon+userinfo.nickname+'</h3>'+'<div class="betting-con"><ul>';
    } else {
        //roomGroup ='<div class="userBetting" ><h3 style="text-align: '+float+'"><b>'+msg.nickname+'</b>'+honor+icon+'</h3><dl class="'+float+'" data-issue="'+msg.issue+'"><dt><img src="'+msg.avatar+'"></dt><dd>';
        roomGroup ='<div class="userBetting"><ul><li class="fl" data-issue="'+msg.issue+'">'+ '<div class="head-image"><img src="'+msg.avatar+'"></div>'+
            '<div class="news-fl"><h3>'+msg.nickname+honor+icon+'</h3>'+'<div class="betting-con"><ul>';
    }

    for(var i=0; i<msg.way.length; i++){
        //tzRecord += '<li><label>投注类型：' + msg.way[i] + '</label> <label>金额：' + msg.money[i] + '<i class="icoAcer"></i></label><em class="close" order-no="' + msg.order_no[i] + '"></em></li>'; //添加投注记录
        tzRecord += '<li><span class="wanf2">' + msg.way[i] + '</span><span class="money2">' + msg.money[i] + '</span><span class="delete2"><i class="ico ico-delete2"  order-no="' + msg.order_no[i] + '"></i></span></li>'; //添加投注记录
        //roomGroup += '<p order-no="' + msg.order_no[i] + '"><label>投注类型：</label> <span>' + msg.way[i] + '</span><label style="color: #dc5d55;">金额：</label><em>' + msg.money[i] + '</em><u class="icoAcer"></u></p>';
        roomGroup += '<li order-no="' + msg.order_no[i] + '"><label>投注类型：<em>' + msg.way[i] + '</em></label><span><i class="ico1 ico1-money"></i>金额：<em>' + msg.money[i] + '</em></span></li>';
    }
    //roomGroup +='<div><span style="float: left">'+msg.time+'</span><em></em><span style="float: right">第'+msg.issue+'期</span></div><i></i></dd></dl></div>';
    roomGroup +='</ul><p><span>'+msg.time+'</span><span>第<em>'+msg.issue+'</em>期</span></p></div></div><i class="wipe"></i></li></ul></div>';

    $(".room-chat-con").append(roomGroup);

    if(msg.uid == userinfo.userid) {
        //生成投注记录
        $(".room-con-fr-mid-con ul").append(tzRecord);
    }

    fnSet.scrollTop();
}

fnSet.update318 =function(msg){
    var roomGroup;
    var float ="left";
    var tzRecord = "";
    var honor ="";
    var icon="";
    for(var t=0; t<msg.data.length; t++){
        if(msg.data[t].nickname ==""){
            msg.data[t].nickname =msg.data[t].username;
        }
        if(msg.data[t].avatar==""){
            msg.data[t].avatar ="/up_files/room/avatar.png";
        }
        if(msg.data[t].honor){
            honor ="<em>"+msg.data[t].honor+"</em>";
        }
        if(msg.data[t].icon){
            icon = "<span><img src='"+msg.data[t].icon+"' /> </span>";
        }
        if(msg.data[t].uid == userinfo.userid){
            //$(".keyboard").click();
            float ="right";
            //$(".room").css("padding-top","110px");
            //$(".betTixing span em").html('本期已下'+msg.count+'注');
            $(".room-con-fr-top em").html(msg.count);
            // $(".betTixing").show();
            //$(".textArea >div").html("");
            $(".betting ul").html("");//投注选择结果显示栏清空
            $(".textArea").html("");//发言输入栏清空
            // fag1 =true;
            aWanf=[];
            aJine=[];

            //roomGroup ='<div class="userBetting" ><h3  style="text-align: '+float+'">'+honor+icon+'<b>'+userinfo.nickname+'</b></h3><dl class="'+float+'" data-issue="'+msg.data[t].issue+'"><dt data-href="'+ tz_record +'"><img src="'+msg.data[t].avatar+'"></dt><dd>';
            roomGroup ='<div class="userBetting"><ul><li class="fr" data-issue="'+msg.data[t].issue+'">'+ '<div class="head-image" data-href="'+tz_record+'"><img src="'+msg.data[t].avatar+'"></div>'+
                '<div class="news-fl"><h3>'+honor+icon+userinfo.nickname+'</h3>'+'<div class="betting-con"><ul>';
        } else {
            //float ="left";
            //roomGroup ='<div class="userBetting"><h3  style="text-align: '+float+'"><b>'+msg.data[t].nickname+'</b>'+honor+icon+'</h3><dl class="'+float+'" data-issue="'+msg.data[t].issue+'"><dt><img src="'+msg.data[t].avatar+'"></dt><dd>';
            roomGroup ='<div class="userBetting"><ul><li class="fl" data-issue="'+msg.data[t].issue+'">'+ '<div class="head-image"><img src="'+msg.data[t].avatar+'"></div>'+
                '<div class="news-fl"><h3>'+msg.data[t].nickname+honor+icon+'</h3>'+'<div class="betting-con"><ul>';
        }


        //tzRecord += '<li><label>投注类型：'+ msg.data[t].way +'</label> <label>金额：'+ msg.data[t].money +'<i class="icoAcer"></i></label><em class="close" order-no="'+ msg.data[t].order_no +'"></em></li>'; //添加投注记录
        tzRecord += '<li><span class="wanf2">' + msg.data[t].way + '</span><span class="money2">' + msg.data[t].money + '</span><span class="delete2"><i class="ico ico-delete2" order-no="'+ msg.data[t].order_no +'"></i></span></li>';//添加投注记录

        //roomGroup+='<p order-no="'+ msg.data[t].order_no +'"><label>投注类型：</label> <span>'+msg.data[t].way+'</span><label style="color: #dc5d55;">金额：</label><em>'+msg.data[t].money+'</em><u class="icoAcer"></u></p>';
        roomGroup += '<li order-no="' + msg.data[t].order_no + '"><label>投注类型：<em>' + msg.data[t].way + '</em></label><span><i class="ico1 ico1-money"></i>金额：<em>' + msg.data[t].money + '</em></span></li>';

        //roomGroup +='<div><span style="float: left">'+msg.data[t].addtime+'</span><em></em><span style="float: right">第'+msg.data[t].issue+'期</span></div><i></i></dd></dl></div>';
        roomGroup +='</ul><p><span>'+msg.data[t].addtime+'</span><span>第<em>'+msg.data[t].issue+'</em>期</span></p></div></div><i class="wipe"></i></li></ul></div>';

        $(".room-chat-con").append(roomGroup);

        if(msg.nickname == userinfo.nickname){
            //$(".quxiao ul").append(tzRecord);
            //生成投注记录
            $(".room-con-fr-mid-con ul").append(tzRecord);
        }
    }
    //追号
    if($('#zhuiHao').css("display") == "block"){
        $("#chaseNumber").click();
    }
    fnSet.scrollTop();
}

//调整滚动条
fnSet.scrollTop =function(){
    setTimeout(function() {
        // 将窗口滚动条调整到底部
        //var keyHeight =$(".bottomWarp").outerHeight();
        //var roomNewsHeight =$('.roomNews').outerHeight();
        // if($(".bettingKey").css("display") =="none"){
        //     keyHeight =0;
        // }
        // var roomHeadHeight =$(".roomHead").outerHeight();//获取头部的高度

        //if($(".roomHead").css("display") =="none"){
        //    $('.betTixing').css("top","44px");
        //    $('.room').css("top","44px");
        //}else{
        //    $('.room').css("top","158px");
        //    $('.betTixing').css("top","158px");
        //}
        //$(".customNews").css("bottom",keyHeight);
        //$('.room').css("bottom",keyHeight+roomNewsHeight);
        var pageHeight =$(".room-chat-con").outerHeight();
        var scrollB = pageHeight - $(".room-chat-warp").scrollTop();
        //console.log($(".room-chat-con").height());
        //console.log($(".room-chat-warp").scrollTop());
        //console.log(scrollB);
        if(scrollB < 2000){
            $('.room-chat-warp').scrollTop(pageHeight);
        }
    }, 100);
}
fnSet.scrollTop_speak =function(){
    setTimeout(function() {
        // 将窗口滚动条调整到底部
        var pageHeight =$(".room-chat-con").outerHeight();
        var scrollB = pageHeight - $(".room-chat-warp").scrollTop();
        $('.room-chat-warp').scrollTop(pageHeight);
    }, 100);
}

//投注栏显示投注选择
fnSet.button = function(wanf,jine){
    if(jine == -1){
        jine ="梭哈";
    }

    $(".betting ul").append('<li><span class="wanf">'+wanf+'</span><span class="money"><em>'+jine+'</em>元</span><i class="ico ico-delete"></i></li>');
    $(".cenMoneyWarp").hide();
    $("#inputNumber").val("");
    if( $(".betting ul li").length >3){
        var pheight = $(".betting ul li").height();
        $(".textArea").scrollTop(($(".betting ul li").length -3)*pheight);
    }
}

$(function(){
    fnSet.scrollTop();
    //var height ="";
    var fag =true;
    // var genWanf =new Array();
    // var genJine =new Array();

    //投注金额
    $.ajax({
        url: "/index.php?m=web&c=openAward&a=getBetting",
        //data:{},
        type: "POST",
        dataType:'json',
        success:function(msg){
            if(msg.status == "0"){
                var list="";
                for(var i=0; i<msg.list.length; i++){
                    list +='<li>'+msg.list[i]+'</li>'
                }
                $(".cenMoney ul.text-ul").prepend(list);
                music = msg.music;

            }
        },
        error:function(er){
        }
    });



    //弹出隐藏投注键盘====
    $("body").on("click","#bettingBtu", function(){
        $("#speak").hide();
        $(".keyboard").show();
        $(this).hide();
        $('#bettingBtu1').show();
        // var keyHeight =$(".bettingKey").outerHeight();
        // $('.customNews').css("bottom",keyHeight);
        $(".bettingKey").show();
        $(".zhuiHao").hide();
        $(".textArea div").attr("contenteditable","false");
        $(".roomHead").hide();
        $("#zhuiHList").empty();
        if($(".lottery").css("display")=="block"){
        	$(".lottery").css("display","none");
        }
        if($(".subtotal").length>0){
            $(".subtotal").remove();
        }
        fnSet.scrollTop();
    })

    //点击玩法号码投注
    $(".room-con-fr-odd").on("click","ul li", function(){
        if(aJine !=""){
            var jineLength =aJine.length;
            if(aJine[jineLength-1] =="-1"){
                fnSet.alert("您已经梭哈");
                return false;
            }
        }

        //新增特殊玩法描述
        //$(".play3 .desc").text($(this).children("div").attr("data-desc"));
        $(".place-order").show();
        $("#touzhuzhi").html($(this).find("span").text());
        $("#touzhuOdd").html($(this).find("p").text());
        //var height =$(".cenMoney").outerHeight();
        //$(".cenMoney").css("margin-top",-(height/2))
        event.stopPropagation();

        $(".textArea").hide();
        $(".betting").show();
        $("#speak").hide();
        $("#bettingBtu1").show();
    })

    //金额值
    $(".text-ul").on("click","a", function(){
        var val = $(this).text();
        var zMoney = $(".icoAcer").html();
        if(val == "梭哈"){
            $(".textArea div p").each(function(){
                zMoney -= $(this).children("span").html();
            })
            $("#touzhuzhi").text();
            var wanf =$("#touzhuzhi").text();
            aWanf.push(wanf);
            aJine.push(Math.floor(zMoney));
            fnSet.button(wanf,Math.floor(zMoney));
            fnSet.scrollTop();
        }else{
            $("#inputNumber").val($(this).text());
        }
    });

    //下单详情确认下单按钮
    $("#butJp").on("click","button",function(){
        var text =$(this).text();
        var wanf =$("#touzhuzhi").text();
        var winStop =$("#if_win_stop").attr('checked')?1:0;
        console.log(winStop);
        var jine =window.parseInt($("#inputNumber").val());
        if(text == "确认下单"){
            if(jine =="" || isNaN(jine)){
                fnSet.alert("请输入金额！");
                return false;
            }
            aWanf.push(wanf);
            aJine.push(jine);
            fnSet.button(wanf,jine);
            fnSet.scrollTop();
        }else{
            $(".cenMoneyWarp").hide();
            $("#inputNumber").val("");
        }
    });
    //下单详情中取消
    $("body").on("click",".keyboard", function(){
        //$('.customNews').css("bottom","0");
        //$(".bettingKey").hide();
        $(".zhuiHao").hide();
        // var height =$(".roomNews").outerHeight();
        //$(".room").css("bottom",height);
        $("#speak").show();
        $(".textArea").show();

        $("#bettingBtu1").hide();
        $(".betting").hide();
        //$(".place-order").hide();

        $("#bettingBtu").show();
        //$(".roomHead").show();
        //$("#bettingBtu").text("赔率");

        //roomBtu(height);
        aWanf=[];
        aJine=[];
        $("#zhuiHList").empty();
        fnSet.scrollTop();
        //fag= true;
    });

        // var money = $(".roomHead1 .icoAcer").text();
        // if(!fag1){
        //     setTimeout(function(){
        //         fag1 =true;
        //     },1000);
        //     return false;
        // }
        // //if(fag){
        //     $('.customNews').css("bottom","492px");
        //     $(".bettingKey").show();
        //     //var height =$(".roomNews").outerHeight()+$(".bettingKey").outerHeight();
        //     //$(".room").css("bottom",height);
        //    // roomBtu(height);
        //     $("#speak").hide();
        //     $(".keyboard").show();
        //
        //     $(this).hide();
        //     $('#bettingBtu1').show();
        //
        //     // $(this).text("投注");
        //     $(".textArea").html('<div data-content="false"></div>');
        //     //投注金额键盘
        //     $(".play >ul").on("click","li", function(){
        //         if(aJine !=""){
        //             var jineLength =aJine.length;
        //             if(aJine[jineLength-1] =="-1"){
        //                 fnSet.alert("您已经梭哈");
        //                 return false;
        //             }
        //         }
        //
        //         //新增特殊玩法描述
        //         $(".play3 .desc").text($(this).attr("data-desc"));
        //
        //         $(".cenMoneyWarp").show();
        //         $("#touzhuzhi").html($(this).children("span").text());
        //         var height =$(".cenMoney").outerHeight();
        //         $(".cenMoney").css("margin-top",-(height/2))
        //         event.stopPropagation();
        //     })
        //     fag =false;
        //}else{
        //     var zMoney = 0;
        //     for(var i=0; i<aJine.length;i++){
        //         zMoney =aJine[i]+zMoney;
        //     }
           // if(fag1){
           //      if(aWanf ==""){
           //          fnSet.alert("请投注！");
           //      }else if(money<zMoney){
           //          fnSet.alert("余额不足");
           //      }else{
           //          if(fag1){
           //
           //              var param = {
           //                  "commandid": "3006",
           //                  "nickname": userinfo.nickname,
           //                  "way":aWanf,
           //                  "money":aJine,
           //                  "avatar":userinfo.head_url
           //              };
           //
           //              wsSendMsg(param);
           //          }


                   // }
                   // fag1 =false;
               // }

           // }
        //}

    //投注按钮
    $("body").on("click","#bettingBtu1", function(){
        var money = $(".icoAcer").text();
        var zMoney = 0;
        var issue = $("#issue").data("data-issue");
        for(var i=0; i<aJine.length;i++){
            zMoney =aJine[i]+zMoney;
        }
        var list = [];
        for(i in aWanf) {
            // var reverse_check = checkReverse(issue,aWanf[i],aWanf);
            // if(!reverse_check) return;
            switch (aWanf[i]) {
                case '大':
                case '小':
                case '单':
                case '双':
                    if(!list["blsd"]) list["blsd"] = {money:aJine[i]};
                    else list["blsd"].money += aJine[i];
                    break;
                case '极大':
                case '极小':
                    if(!list["mimx"]) list["mimx"] = {money:aJine[i]};
                    else list["mimx"].money += aJine[i];
                    break;
                case '大单':
                case '小单':
                case '大双':
                case '小双':
                    if(!list["mix"]) list["mix"] = {money:aJine[i]};
                    else list["mix"].money += aJine[i];
                    break;
                case '红':
                    if(!list["red"]) list["red"] = {money:aJine[i]};
                    else list["red"].money += aJine[i];
                    break;
                case '绿':
                    if(!list["green"]) list["green"] = {money:aJine[i]};
                    else list["green"].money += aJine[i];
                    break;
                case '蓝':
                    if(!list["blue"]) list["blue"] = {money:aJine[i]};
                    else list["blue"].money += aJine[i];
                    break;
                case '豹子':
                    if(!list["leo"]) list["leo"] = {money:aJine[i]};
                    else list["leo"].money += aJine[i];
                    break;
                case '正顺':
                    if(!list["zhengshun"]) list["zhengshun"] = {money:aJine[i]};
                    else list["zhengshun"].money += aJine[i];
                    break;
                case '倒顺':
                    if(!list["daoshun"]) list["daoshun"] = {money:aJine[i]};
                    else list["daoshun"].money += aJine[i];
                    break;
                case '半顺':
                    if(!list["banshun"]) list["banshun"] = {money:aJine[i]};
                    else list["banshun"].money += aJine[i];
                    break;
                case '乱顺':
                    if(!list["luanshun"]) list["luanshun"] = {money:aJine[i]};
                    else list["luanshun"].money += aJine[i];
                    break;
                case '对子':
                    if(!list["pair"]) list["pair"] = {money:aJine[i]};
                    else list["pair"].money += aJine[i];
                    break;
                default:
                    if(!list[aWanf[i]]) list[aWanf[i]] = {money:aJine[i]};
                    else list[aWanf[i]].money += aJine[i];
                    break;
            }
        }

        for(i in list) {
            var new_way = "";
            var new_name = "";
            switch (i) {
                case 'blsd':
                    new_way = "大";
                    new_name = "大小单双";
                    break;
                case 'mimx':
                    new_way = "极大";
                    new_name = "极大极小";
                    break;
                case 'mix':
                    new_way = "大单";
                    new_name = "组合";
                    break;
                case 'red':
                    new_way = "红";
                    new_name = "红";
                    break;
                case 'green':
                    new_way = "绿";
                    new_name = "绿";
                    break;
                case 'blue':
                    new_way = "蓝";
                    new_name = "蓝";
                    break;
                case 'leo':
                    new_way = "豹子";
                    new_name = "豹子";
                    break;
                case 'zhengshun':
                    new_way = "正顺";
                    new_name = "正顺";
                    break;
                case 'daoshun':
                    new_way = "倒顺";
                    new_name = "倒顺";
                    break;
                case 'banshun':
                    new_way = "半顺";
                    new_name = "半顺";
                    break;
                case 'luanshun':
                    new_way = "乱顺";
                    new_name = "乱顺";
                    break;
                case 'pair':
                    new_way = "对子";
                    new_name = "对子";
                    break;
                default:
                    new_way = i;
                    new_name = i;
                    break;
            }
            var bet_limit_check = checkMoney(issue,new_way,list[i].money);
            if(!bet_limit_check) {
                return;
            }
        }
        var group_check = checkGroup(issue,zMoney);
        if(group_check>0){
            var group_name = "";
            if($("#user_group_name").val()!=undefined) group_name = $("#user_group_name").val();
            if(group_check==1) {
                var group_upper = 1000000;
                if($("#user_group_upper").val()!=undefined) group_upper = parseFloat($("#user_group_upper").val());
                fnSet.alert("您的投注额累计超过，您所属会员组("+group_name+")上限:"+group_upper);
                return;
            }else if(group_check==2) {
                var group_lower = 0;
                if($("#user_group_lower").val()!=undefined) group_lower = parseFloat($("#user_group_lower").val());
                fnSet.alert("您的投注额低于，您所属会员组("+group_name+")下限:"+group_lower);
                return;
            }
        }

        if(aWanf ==""){
            fnSet.alert("请投注！");
        }else if(money<zMoney){
            fnSet.alert("余额不足");
        } else{
            if(fag1){
                fag1 =false;
                var param = {
                    "commandid": "3006",
                    "nickname": userinfo.nickname,
                    "way":aWanf,
                    "money":aJine,
                    "avatar":userinfo.head_url
                };
                //console.log(param);
                wsSendMsg(param);
            }
            // //投注后清空
            // aWanf=[];
            // aJine=[];
            fnSet.scrollTop();
        }

        $("#speak").show();
        $(".textArea").show();
        $("#bettingBtu1").hide();
        $(".betting").hide();
    })

    //追号确认投注
    $("body").on("click","#querenTz", function(){
        var all = 0;
        var count = 0;
        var winStop=$('#if_win_stop').attr('checked')?1:0;
        if($("#subtotal")!=undefined) all = $("#subtotal").attr("all");
        if($("#subtotal")!=undefined) count = $("#subtotal").attr("count");
        if(count>0){
            $.confirm("确认投注吗？<br/>共追号:"+count+"期,总金额:"+all,function () {
                var bodyDome = $("#zhuiHList").find("tr");
                var money = $(".icoAcer").text();
                var zMoney = 0;
                var betData = [];
                for(var a=0;a<bodyDome.length;a++) {
                    var issue = $(bodyDome[a]).find("td").eq(0).find("em").attr("data-issue"),
                        bet_money = $(bodyDome[a]).find("td").eq(1).find("em").html(),
                        way = $(".sel option:selected").val();
                    var obj = {
                        'qihao':issue,
                        'money':bet_money,
                        'way':way,
                        'multiple':$(bodyDome[a]).find("td").eq(2).find("em").html(),
                    }
                    zMoney +=parseInt(obj.money);
                    betData.push(obj);
                }
                for(i in betData) {
                    if(betData[i].way==""||betData[i].way==undefined) {
                        fnSet.alert("请选择玩法");
                        return;
                    }
                }
                if(betData.length <= 0 || zMoney == 0){
                    fnSet.alert("请生成投注信息");
                }else if(money<zMoney){
                    fnSet.alert("余额不足");
                }else{
                    if($(".subtotal").length>0){
                        $(".subtotal").remove();
                    }
                    // if(fag1){
                        var param = {
                            "commandid": "3019",
                            "nickname": userinfo.nickname,
                            "data":betData,
                            "win_stop":winStop,
                            "avatar":userinfo.head_url
                        };
                        //console.log(param);
                        wsSendMsg(param);
                    // }
                    //投注后清空
                    fnSet.scrollTop();
                    $(".zhuiHao").hide();
                }
            },function () {});
        }else{
            fnSet.alert("请选择投注内容")
        }

    });
    //删除投注栏投注
    $('.betting').on("click touch",".ico-delete",function(){
        var index =$(this).parent("li").index();
        $(this).parent("li").remove();
        aJine.splice(index,1);
        aWanf.splice(index,1);
        fnSet.scrollTop();
    })
    //监听高度变化=====
    $("body").on("input",".textArea div", function(){
        $('.room').css("bottom",$(".roomNews").outerHeight());
        fnSet.scrollTop();
    })
    //发言
    $("body").on("click","#speak", function(){
        var content =$(".textArea").text();
        if(content !=""){
            var param = {
                "commandid": "3003",
                "nickname": userinfo.nickname,
                "content":content,
                "avatar":userinfo.head_url
            };
            //console.log(param);
            wsSendMsg(param);
            $(".textArea").text("");
           // var height =$(".roomNews").outerHeight();
            //roomBtu(height)
            fnSet.scrollTop_speak();
        }else{
            fnSet.alert("内容不能为空！")
        }

    })

    //取消当前投注---
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


    //第一次注册修改昵称---
    $("body").on("click","#keepName",function(){
        var nickname =$("input[name=nickname]").val();
        if(nickname ==""){
            fnSet.alert("请输入昵称");
        }else if(nickname.length >10){

        }else{
            $.ajax({
                url: btn_url,
                data:{"nickname":nickname},
                type: "POST",
                dataType:'json',
                success:function(msg){
                    if(msg.status == "0"){
                        userinfo.nickname =nickname;
                        initWebSocket();
                        $('.popup').hide();
                    }
                },
                error:function(er){
                }
            });
            // $.ajax({ url: btn_url,{"nickname": nickname},type:POST, success: function(msg){
            //     var msg =JSON.parse(msg);
            //     if(msg.status == "0"){
            //         userinfo.nickname =nickname;
            //         initWebSocket();
            //         $('.popup').hide();
            //     }
            // }});
        }
    })
    //修改昵称取消---
    $("body").on("click","#ncquxiao",function(){
        window.location.href ="/?m=web&c=lobby&a=index";
    })

    //跟投---
    $("body").on("click",".userBetting dd",function(){
        var nameMz =$(this).parent().siblings("h3").find("b").text();
        var timeTz =$(this).children("div").children("span").eq(0).text()
        var gentou ='<div class="config1"><i class="configClose"></i><p style="font-size: 12px; text-align: left;"><em>'+nameMz+'</em>&nbsp;&nbsp;&nbsp;&nbsp;'+timeTz+'&nbsp;&nbsp;&nbsp;&nbsp;第'+$('#issue').text()+'期</p><div class="configContent">';
        var genZhi =$(this).children("p");
        for(var i=0; i<genZhi.length; i++){
            gentou+='<p style="font-size: 14px; text-align: left;">投注类型：<span>'+genZhi.eq(i).children("span").text()+'</span> <label class="configAcer">金额：<em>'+genZhi.eq(i).children("em").text()+'</em></label></p>'
        }
        gentou+='</div><div class="button" style="margin-top: 30px;"><button id="genTou">跟他投</button></div></div>';
        $(".popup").html(gentou);
        $(".popup").show();
    })
    //跟投功能======
    $("body").on("click","#genTou",function(){
        var genWanf =new Array();
        var genJine =new Array();
        var genZhi =$(this).parent().siblings(".configContent").children("p");
        var sub_total = 0;
        for(var i=0; i<genZhi.length; i++){
            genWanf.push(genZhi.eq(i).children("span").text());
            genJine.push(genZhi.eq(i).find("em").text());
            sub_total += parseFloat(genZhi.eq(i).find("em").text());
        }
        //提前限额判断
        var issue = $("#issue").attr("data-issue");
        var list = [];
        for(i in genWanf) {
            //逆向投注检测
            // var reverse_check = checkReverse(issue,genWanf[i],genWanf);
            // if(!reverse_check) return;

            switch (genWanf[i]) {
                case '大':
                case '小':
                case '单':
                case '双':
                    if(!list["blsd"]) list["blsd"] = {money:parseFloat(genJine[i])};
                    else list["blsd"].money += parseFloat(genJine[i]);
                    break;
                case '极大':
                case '极小':
                    if(!list["mimx"]) list["mimx"] = {money:parseFloat(genJine[i])};
                    else list["mimx"].money += parseFloat(genJine[i]);
                    break;
                case '大单':
                case '小单':
                case '大双':
                case '小双':
                    if(!list["mix"]) list["mix"] = {money:parseFloat(genJine[i])};
                    else list["mix"].money += parseFloat(genJine[i]);
                    break;
                case '红':
                    if(!list["red"]) list["red"] = {money:parseFloat(genJine[i])};
                    else list["red"].money += parseFloat(genJine[i]);
                    break;
                case '绿':
                    if(!list["green"]) list["green"] = {money:parseFloat(genJine[i])};
                    else list["green"].money += parseFloat(genJine[i]);
                    break;
                case '蓝':
                    if(!list["blue"]) list["blue"] = {money:parseFloat(genJine[i])};
                    else list["blue"].money += parseFloat(genJine[i]);
                    break;
                case '豹子':
                    if(!list["leo"]) list["leo"] = {money:parseFloat(genJine[i])};
                    else list["leo"].money += parseFloat(genJine[i]);
                    break;
                case '正顺':
                    if(!list["zhengshun"]) list["zhengshun"] = {money:parseFloat(genJine[i])};
                    else list["zhengshun"].money += parseFloat(genJine[i]);
                    break;
                case '倒顺':
                    if(!list["daoshun"]) list["daoshun"] = {money:parseFloat(genJine[i])};
                    else list["daoshun"].money += parseFloat(genJine[i]);
                    break;
                case '半顺':
                    if(!list["daoshun"]) list["daoshun"] = {money:parseFloat(genJine[i])};
                    else list["daoshun"].money += parseFloat(genJine[i]);
                    break;
                case '乱顺':
                    if(!list["luanshun"]) list["luanshun"] = {money:parseFloat(genJine[i])};
                    else list["luanshun"].money += parseFloat(genJine[i]);
                    break;
                case '对子':
                    if(!list["pair"]) list["pair"] = {money:parseFloat(genJine[i])};
                    else list["pair"].money += parseFloat(genJine[i]);
                    break;
                default:
                    if(!list[genWanf[i]]) list[genWanf[i]] = {money:parseFloat(genJine[i])};
                    else list[genWanf[i]].money += parseFloat(genJine[i]);
                    break;
            }
        }

        for(i in list) {
            var new_way = "";
            var new_name = "";
            switch (i) {
                case 'blsd':
                    new_way = "大";
                    new_name = "大小单双";
                    break;
                case 'mimx':
                    new_way = "极大";
                    new_name = "极大极小";
                    break;
                case 'mix':
                    new_way = "大单";
                    new_name = "组合";
                    break;
                case 'red':
                    new_way = "红";
                    new_name = "红";
                    break;
                case 'green':
                    new_way = "绿";
                    new_name = "绿";
                    break;
                case 'blue':
                    new_way = "蓝";
                    new_name = "蓝";
                    break;
                case 'leo':
                    new_way = "豹子";
                    new_name = "豹子";
                    break;
                case 'zhengshun':
                    new_way = "正顺";
                    new_name = "正顺";
                    break;
                case 'daoshun':
                    new_way = "倒顺";
                    new_name = "倒顺";
                    break;
                case 'banshun':
                    new_way = "半顺";
                    new_name = "半顺";
                    break;
                case 'luanshun':
                    new_way = "乱顺";
                    new_name = "乱顺";
                    break;
                case 'pair':
                    new_way = "对子";
                    new_name = "对子";
                    break;
                default:
                    new_way = i;
                    new_name = i;
                    break;
            }
            var bet_limit_check = checkMoney(issue,new_way,list[i].money);

            if(!bet_limit_check) {
                return;
            }
        }
        var group_check = checkGroup(issue,sub_total);
        if(group_check>0){
            var group_name = "";
            if($("#user_group_name").val()!=undefined) group_name = $("#user_group_name").val();
            if(group_check==1) {
                var group_upper = 1000000;
                if($("#user_group_upper").val()!=undefined) group_upper = parseFloat($("#user_group_upper").val());
                fnSet.alert("您的投注额累计超过，您所属会员组("+group_name+")上限:"+group_upper);
                return;
            }else if(group_check==2) {
                var group_lower = 0;
                if($("#user_group_lower").val()!=undefined) group_lower = parseFloat($("#user_group_lower").val());
                fnSet.alert("您的投注额低于，您所属会员组("+group_name+")下限:"+group_lower);
                return;
            }
        }

        var param = {
            "commandid": "3006",
            "nickname": userinfo.nickname,
            "way":genWanf,
            "money":genJine,
            "avatar":userinfo.head_url
        };
        wsSendMsg(param);
        // genWanf=[];
        // genJine=[];
        $(".popup").hide();
    })

    $("body").on("input",'#inputNumber',function(){
        var text =$(this).val();

        if(text.indexOf("0")=="0"){
            $(this).val("");
        }

    })
    //赔率说明
    $(".room-con-fr-odd ").on("click",'.odds-explain',function(){
        var explain_content =  '<div class="config" style="padding-top: 30px;"><i class="configClose"></i>'
                + '<p style="color: #5e97fe;">赔率说明</p>'
                + '<p style="color: #000;font-size: 14px;line-height: 25px;height: 180px;overflow: auto;text-align:left">'+ odds_explain +'</p></div>';
        $(".popup").html(explain_content);
        $(".popup").show();
    });

    //弹出层上面的X
    $("body").on("click",".configClose,.cancel",function(){
        $(".popup").hide();
    })
    //下单详情弹出层上面的X和取消
    $("body").on("click",".ico-close,.cancel",function(){
        $(".place-order").hide();
    });

    //回车发送消息
    $("body").on("keyup",".textArea",function(e){
        e = e? e : (window.event ? window.event : null);
        if(e.keyCode==13)//Enter
        {
            document.getElementById("speak").click();
        }
    })
    
    //取消指定投注
    $(".room-con-fr-mid-con .ico-delete2").live('click', function() {
        order_no = $(this).attr("order-no"); //赋予全局订单号
        var quxiaoT = '<div class="config" style="height: 225px;"><p style="color: #ff4f4f;">您真的要取消下注吗？</p><p style="color: #000;">点击确认继续</p><div class="cigBtn"><button class="cancel">取消</button><button id="querenBut" class="confirm">确认</button></div></div>';
        $(".popup").html(quxiaoT);
        $(".popup").show();
    });

    //取消指定投注确认
    $("body").on("click","#querenBut",function(){
        var param = {
            "commandid": "3016",
            "uid": userinfo.userid,
            "order_no": order_no,
        };
        wsSendMsg(param);
        $(".popup").hide();
    })
    
    //取消当期所有投注
    $("body").on("click","#quxiaoSy",function(){
        var num = $(".room-con-fr-mid-con ul li").length;
        if(num > 0){
            var quxiaoT = '<div class="config" style="height: 225px;"><p style="color: #ff4f4f;">您真的要取消所有下注吗？</p><p style="color: #000;">点击确认继续</p><div class="cigBtn"><button class="cancel">取消</button><button id="qbBut" class="confirm">确认</button></div></div>';
            $(".popup").html(quxiaoT);
            $(".popup").show();
        }else{
            fnSet.alert("当前无投注记录！");
        }
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

    //客服图标 移动
    //var bodyWidth = $("body").width();
    //var bodyHeight = $("body").height();
    //var dragWidth =$("#drag").width();
    //var move = false;//移动标记
    //var seup =false;
    //var _x, _y;//鼠标离控件左上角的相对位置
    //drag.addEventListener("touchstart",function () {
    //    move = true;
    //    seup =true;
    //    _x = event.targetTouches[0].pageX - parseInt($("#drag").css("left"));
    //    _y = event.targetTouches[0].pageY - parseInt($("#drag").css("top"));
    //})
    //drag.addEventListener("touchmove",function (){
    //    if (event.targetTouches.length == 1) {
    //        event.preventDefault();// 阻止浏览器默认事件，重要
    //        if (move) {
    //            var x = event.targetTouches[0].pageX - _x;//控件左上角到屏幕左上角的相对位置
    //            var y = event.targetTouches[0].pageY - _y;
    //            $("#drag").css({"top": y, "left": x});
    //        }
    //    }
    //
    //})
    //drag.addEventListener("touchend",function (){
    //    if(seup){
    //        var left = $("#drag").offset().left;
    //        var top = $("#drag").offset().top;
    //        if(top<86){
    //            $("#drag").css({"top": '44px'});
    //        }
    //        // if(top > 44 || top > bodyHeight -102){
    //            if((bodyWidth/2) < left){
    //                $("#drag").css({"left": bodyWidth-53+"px"});
    //            }else{
    //                $("#drag").css({"left": "15px"});
    //            }
    //        // }
    //        if(top>bodyHeight-76){
    //            $("#drag").css({"top":bodyHeight- 38});
    //        }
    //    }
    //    move = false;
    //    seup =false;
    //})


    //追号详情浮动图标拖动操作
    //var dZh = $("#dZh");
    var dZh = document.getElementById("dZh");
    dZh.addEventListener("onmouseup",function () {
        move = true;
        seup =true;
        _x = event.targetTouches[0].pageX - parseInt($("#dZh").css("left"));
        _y = event.targetTouches[0].pageY - parseInt($("#dZh").css("top"));
    })
    dZh.addEventListener("onmousemove",function (){
        if (event.targetTouches.length == 1) {
            event.preventDefault();// 阻止浏览器默认事件，重要
            if (move) {
                var x = event.targetTouches[0].pageX - _x;//控件左上角到屏幕左上角的相对位置
                var y = event.targetTouches[0].pageY - _y;
                $("#dZh").css({"top": y, "left": x});
            }
        }

    })
    dZh.addEventListener("onmousedown",function (){
        if(seup){
            var left = $("#dZh").offset().left;
            var top = $("#dZh").offset().top;
            if(top<86){
                $("#dZh").css({"top": '44px'});
            }
            // if(top > 44 || top > bodyHeight -102){
                if((bodyWidth/2) < left){
                    $("#dZh").css({"left": bodyWidth-53+"px"});
                }else{
                    $("#dZh").css({"left": "15px"});
                }
            // }
            if(top>bodyHeight-76){
                $("#dZh").css({"top":bodyHeight- 38});
            }

        }
        move = false;
        seup =false;
    })

    //视频
    var video = document.getElementById("video");
    video.addEventListener("touchstart",function () {
        move = true;
        seup =true;
        _x = event.targetTouches[0].pageX - parseInt($("#video").css("left"));
        _y = event.targetTouches[0].pageY - parseInt($("#video").css("top"));
    })
    video.addEventListener("touchmove",function (){
        if (event.targetTouches.length == 1) {
            event.preventDefault();// 阻止浏览器默认事件，重要
            if (move) {
                var x = event.targetTouches[0].pageX - _x;//控件左上角到屏幕左上角的相对位置
                var y = event.targetTouches[0].pageY - _y;
                $("#video").css({"top": y, "left": x});
            }
        }

    })
    video.addEventListener("touchend",function (){
        if(seup){
            var left = $("#video").offset().left;
            var top = $("#video").offset().top;
            if(top<86){
                $("#video").css({"top": '44px'});
            }
            // if(top > 44 || top > bodyHeight -102){
                if((bodyWidth/2) < left){
                    $("#video").css({"left": bodyWidth-45+"px"});
                }else{
                    $("#video").css({"left": "15px"});
                }
            // }
            if(top>bodyHeight-76){
                $("#video").css({"top":bodyHeight- 30});
            }

        }
        move = false;
        seup =false;
    })

    //走势图标
    $("body").on("click",".ico-trend",function(){
        $(".layer").show();
    })
    //关闭窗口
    $("body").on("click",".ico-roomClose",function(){
        $(".layer").hide();
        return false;
    });


    //追号按钮
    $("body").on("click","#chaseNumber", function(){
        //$("#bettingBtu").show();
        //$(".keyboard").hide();
        $(".textArea").text("");
        //subtotal不知道是什么
        if($(".subtotal").length>0){
            $(".subtotal").remove();
        }
        if($(".zhuiHao").css("display") =="none"){
            //$(".textArea").attr("contenteditable","false");
            //$(".bettingKey").hide();
            $(".zhuiHao").show();
            //var zhuihaoHtml = $(".zhuiHao").html();
            //var zhuihao_content =  '<div class="config" style="padding-top: 30px;"><i class="configClose"></i>'
            //    + '<p style="color: #5e97fe;">追号工具</p>'
            //    + zhuihaoHtml +'</div>';
            //$(".popup").html(zhuihao_content);
            //$(".popup").show()
            //$(".zhuiHao").remove();
            //$(".roomHead").hide();
        }else{
            $(".zhuiHao").hide();
            //$(".roomHead").show();
            $(".textArea").attr("contenteditable","true");
        }
        $(".lottery").hide();
        fnSet.scrollTop();
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


    //点击追号详情浮动图标
    $("body").on("click",".ioc_zhuihao",function(){
        var data = {
            'token':userinfo.token,
            'room_id':userinfo.room_id
        }
        $.ajax({
            url:"?m=api&c=order&a=getZhuiHaoInfo",
            type:'post',
            dataType:'json',
            data:data,
            success:function(data) {
                var zhui_details_con = $(".zhui_details_con");
                zhui_details_con.empty();
                var html = '<dl>';
                if(data.code == 0){
                    for(var x=0;x<data.list.length;x++) {
                        html += '<dt><em>玩法：</em><em style="color: #dc5d55">'+data.list[x]['way']+'</em><em>'+data.list[x]['time']+'</em> <button class="right chedan" onclick="chedan(\''+data.list[x]['number']+'\',this)">撤单</button></dt>';
                        for(var b=0;b<data.list[x]['data'].length;b++){
                            var color = "";
                            if(data.list[x]['data'][b]['award_state'] == 0){
                                var award_state = "待开奖";
                            }else if(data.list[x]['data'][b]['award_state'] == 1){
                                var award_state = "未中奖";
                                color = "#EE6A50";
                            }else if(data.list[x]['data'][b]['award_state'] == 2){
                                var award_state = "中奖";
                                color = "#EE6A50";
                            }
                            html += '<dd><span>第'+data.list[x]['data'][b]['issue']+'期</span><span>'+data.list[x]['data'][b]['money']+'元</span><span>'+data.list[x]['data'][b]['multiple']+'倍</span><span style="color:'+ color+';">'+award_state+'</span></dd>';
                        }
                    }
                } else {
                    html += "<dd>"+data.msg+"</dd>";
                }
                html += '</dl>';
                zhui_details_con.append(html);
                $(".zhui_details").show();
            }
        })

    });
    //追号详情
    $("body").on("click",".zhui_details",function(){
        $(this).hide();
        return false;
    })
    //追号详情上面的X
    //$("body").on("click",".configClose2",function(){
    //    $(this).parents(".zhui_details").hide();
    //    return false;
    //});
    $(".zhui_details_w div").on("click",function(e){
        return false;
        // e.stopPropagation();
    })

    //追号生成
    $(".chase-form-right").on("click",".generate",function(){
        var money =$(".money-text").val();
        var sel = $(".sel option:selected").val();
        if(sel ==""){
            fnSet.alert("请选择玩法");
            return false;
        }
        if(money ==""){
            fnSet.alert("请填写金额");
            return false;
        }
        if(!/^[1-9]\d*$/.test(money)){
            fnSet.alert("金额必须为大于0的整数");
            return false;
        }
        var zhuiQs =$("#zhuiQs").val();
        var zhuiBs =$("#zhuiBs").val();
        var dqQH2 =$("#issue").attr("data-issue");
        var dqQH =$("#issue").text();
        var morenBs =1;
        var tabTr="";
        if(parseInt(zhuiQs) > 100 ) {
            fnSet.alert("追号一次最多100期");
            return false;
        }
        var subtotal = 0;
        var bet_count = 0;
        for(var i=0; i<zhuiQs; i++){
            if(i==0){
                var room_check  = checkMoney(dqQH,sel,money);
                var group_check = checkGroup(dqQH,money);
                // var reverse_check = checkReverse(dqQH,sel,[]);
                if(group_check>0) {
                    var group_name = "";
                    if($("#user_group_name").val()!=undefined) group_name = $("#user_group_name").val();
                    if(group_check==1) {
                        var group_upper = 1000000;
                        if($("#user_group_upper").val()!=undefined) group_upper = parseFloat($("#user_group_upper").val());
                        fnSet.alert("您的投注额累计超过，您所属会员组("+group_name+")上限:"+group_upper);
                        return;
                    }else if(group_check==2) {
                        var group_lower = 0;
                        if($("#user_group_lower").val()!=undefined) group_lower = parseFloat($("#user_group_lower").val());
                        fnSet.alert("您的投注额低于，您所属会员组("+group_name+")下限:"+group_lower);
                        return;
                    }
                } else if(!room_check) {
                    return;
                // }else if(!reverse_check){
                //     return;
                } else{
                    tabTr += '<tr><td width="34%">第<em data-issue="'+dqQH2+'">' + dqQH + '</em>期</td><td width="33%"><em>' + money + '</em>元</td><td width="33%"><em>1</em>倍</td></tr>';
                    subtotal += parseFloat(money);
                    bet_count++;

                }
            } else{
                morenBs = morenBs*zhuiBs;
                var room_check  = checkMoney(dqQH,sel,money * morenBs);
                var group_check = checkGroup(dqQH,money * morenBs);
                // var reverse_check = checkReverse(dqQH,sel,[]);
                if(group_check>0) {
                    var group_name = "";
                    if($("#user_group_name").val()!=undefined) group_name = $("#user_group_name").val();
                    if(group_check==1) {
                        var group_upper = 1000000;
                        if($("#user_group_upper").val()!=undefined) group_upper = parseFloat($("#user_group_upper").val());
                        fnSet.alert("您的投注额累计超过，您所属会员组("+group_name+")上限:"+group_upper);
                        return;
                    }else if(group_check==2) {
                        var group_lower = 0;
                        if($("#user_group_lower").val()!=undefined) group_lower = parseFloat($("#user_group_lower").val());
                        fnSet.alert("您的投注额低于，您所属会员组("+group_name+")下限:"+group_lower);
                        return;
                    }
                } else if(!room_check) {
                    return;
                // }else if(!reverse_check){
                //     return;
                } else {
                    if(dqQH.toString().length<7){
                        dqQH ="0"+dqQH;
                    }
                    tabTr += '<tr><td >第<em data-issue="'+dqQH2+'">' + dqQH + '</em>期</td><td><em>' + money * morenBs + '</em>元</td><td><em>' + morenBs + '</em>倍</td></tr>';
                    subtotal += parseFloat(money * morenBs);
                    bet_count++;
                }
            }
            dqQH++;
            dqQH2++;
        }
        if($(".subtotal").length>0){
            $(".subtotal").remove();
        }
        $(".listQs").eq(0).append("<div class='subtotal' style='text-align: center;background-color: beige;' id='subtotal' all='"+subtotal+"' count='"+bet_count+"' >总金额:"+subtotal+"元</div>");
        $("#zhuiHList").html(tabTr);
        fnSet.scrollTop();
    })

    function checkMoney(issue,sel,money) {
        var limit = 1000000000000;
        var bet_total = 0;
        var bet_all = 0;
        $(".issue_" + issue).each(function () {
            switch (sel) {
                case '大':
                case '小':
                case '单':
                case '双':
                    if ($(this).attr("name") == "大" || $(this).attr("name") == "小" || $(this).attr("name") == "单" || $(this).attr("name") == "双") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '极大':
                case '极小':
                    if ($(this).attr("name") == "极大" || $(this).attr("name") == "极小") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '大单':
                case '小单':
                case '大双':
                case '小双':
                    if ($(this).attr("name") == "大单" || $(this).attr("name") == "小单" || $(this).attr("name") == "大双" || $(this).attr("name") == "小双") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '红':
                    if ($(this).attr("name") == "红") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '绿':
                    if ($(this).attr("name") == "绿") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '蓝':
                    if ($(this).attr("name") == "蓝") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '豹子':
                    if ($(this).attr("name") == "豹子") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '正顺':
                    if ($(this).attr("name") == "正顺") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '倒顺':
                    if ($(this).attr("name") == "倒顺") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '半顺':
                    if ($(this).attr("name") == "半顺") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '乱顺':
                    if ($(this).attr("name") == "乱顺") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                case '对子':
                    if ($(this).attr("name") == "对子") {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
                default:
                    if ($(this).attr("name") == sel) {
                        if ($(this).val() != undefined && $(this).val() != "") bet_total += parseFloat($(this).val());
                    }
                    break;
            }
            bet_all += parseFloat($(this).val());
        });
        bet_all += parseFloat(money);
        money = bet_total + parseFloat(money);
        if (isNaN(sel)) {
            switch (sel) {
                case '大':
                    if ($("#size_ds").val() != undefined && $("#size_ds").val() != "0" && $("#size_ds").val() != "") limit = parseFloat($("#size_ds").val());
                    break;
                case '小':
                    if ($("#size_ds").val() != undefined && $("#size_ds").val() != "0" && $("#size_ds").val() != "") limit = parseFloat($("#size_ds").val());
                    break;
                case '单':
                    if ($("#size_ds").val() != undefined && $("#size_ds").val() != "0" && $("#size_ds").val() != "") limit = parseFloat($("#size_ds").val());
                    break;
                case '双':
                    if ($("#size_ds").val() != undefined && $("#size_ds").val() != "0" && $("#size_ds").val() != "") limit = parseFloat($("#size_ds").val());
                    break;
                case '极大':
                    if ($("#minimax").val() != undefined && $("#minimax").val() != "0" && $("#minimax").val() != "") limit = parseFloat($("#minimax").val());
                    break;
                case '大单':
                    if ($("#parts").val() != undefined && $("#parts").val() != "0" && $("#parts").val() != "") limit = parseFloat($("#parts").val());
                    break;
                case '小单':
                    if ($("#parts").val() != undefined && $("#parts").val() != "0" && $("#parts").val() != "") limit = parseFloat($("#parts").val());
                    break;
                case '大双':
                    if ($("#parts").val() != undefined && $("#parts").val() != "0" && $("#parts").val() != "") limit = parseFloat($("#parts").val());
                    break;
                case '小双':
                    if ($("#parts").val() != undefined && $("#parts").val() != "0" && $("#parts").val() != "") limit = parseFloat($("#parts").val());
                    break;
                case '极小':
                    if ($("#minimax").val() != undefined && $("#minimax").val() != "0" && $("#minimax").val() != "") limit = parseFloat($("#minimax").val());
                    break;
                case '红':
                    if ($("#red").val() != undefined && $("#red").val() != "0" && $("#red").val() != "") limit = parseFloat($("#red").val());
                    break;
                case '绿':
                    if ($("#green").val() != undefined && $("#green").val() != "0" && $("#green").val() != "") limit = parseFloat($("#green").val());
                    break;
                case '蓝':
                    if ($("#blue").val() != undefined && $("#blue").val() != "0" && $("#blue").val() != "") limit = parseFloat($("#blue").val());
                    break;
                case '豹子':
                    if ($("#leo").val() != undefined && $("#leo").val() != "0" && $("#leo").val() != "") limit = parseFloat($("#leo").val());
                    break;
                case '正顺':
                    if ($("#zhengshun").val() != undefined && $("#zhengshun").val() != "0" && $("#zhengshun").val() != "") limit = parseFloat($("#zhengshun").val());
                    break;
                case '倒顺':
                    if ($("#daoshun").val() != undefined && $("#daoshun").val() != "0" && $("#daoshun").val() != "") limit = parseFloat($("#daoshun").val());
                    break;
                case '半顺':
                    if ($("#banshun").val() != undefined && $("#banshun").val() != "0" && $("#banshun").val() != "") limit = parseFloat($("#banshun").val());
                    break;
                case '乱顺':
                    if ($("#luanshun").val() != undefined && $("#luanshun").val() != "0" && $("#luanshun").val() != "") limit = parseFloat($("#luanshun").val());
                    break;
                case '对子':
                    if ($("#pair").val() != undefined && $("#pair").val() != "0" && $("#pair").val() != "") limit = parseFloat($("#pair").val());
                    break;
            }
        } else {
            var arr = null;
            if ($("#single_digit").val() != undefined && $("#single_digit").val() != "0" && $("#single_digit").val() != "") {
                arr = $("#single_digit").val().split(",");
                if(arr[sel]!="0"&&arr.length>10) limit = arr[sel];
                else if(arr[parseFloat(sel)-1]!="0"&&arr.length==10) limit = arr[parseFloat(sel)-1];
            }
        }
        var all = 100000000;
        var all_limit = true;
        if ($("#general_note").val() != undefined && $("#general_note").val() != "0" && $("#general_note").val() != "") {
            all = parseFloat($("#general_note").val());
        }else{
            all_limit = false;
        }
        var lower = 0;
        var lower_limit = true;
        if ($("#lower").val() != undefined && $("#lower").val() != "0" && $("#lower").val() != "") {
            lower = parseFloat($("#lower").val());
        }else {
            lower_limit = false;
        }
        if (money > limit) {
            switch (sel) {
                case '大':
                case '小':
                case '单':
                case '双':
                    fnSet.alert("超过了房间玩法:(大小单双)的限额:"+limit);
                    break;
                case '极大':
                case '极小':
                    fnSet.alert("超过了房间玩法:(极值)的限额:"+limit);
                    break;
                case '大单':
                case '小单':
                case '大双':
                case '小双':
                    fnSet.alert("超过了房间玩法:(组合)的限额:"+limit);
                    break;
                case '红':
                case '绿':
                case '蓝':
                case '豹子':
                case '正顺':
                case '倒顺':
                case '半顺':
                case '乱顺':
                case '对子':
                default:
                    fnSet.alert("超过了房间玩法:("+sel+")的限额:"+limit);
                    break;
            }
            return false;
        }
        else if(bet_all>all) {
            if(all_limit) {
                fnSet.alert("超过了房间本期总投注限额:"+all);
                return false;
            }
        }
        else if(lower>bet_all) {
            if(lower_limit) {
                fnSet.alert("低于房间最低投注限额:" + lower);
                return false;
            }else {
                return true;
            }
        }
        else return true;
    }
    
    function checkGroup(issue,money) {
        var bet_total = 0;
        $(".issue_"+issue).each(function () {
            if($(this).val()!=undefined&&$(this).val()!="") bet_total+=parseFloat($(this).val());
        });
        bet_total += parseFloat(money);
        var group_upper = 10000000000000;
        var group_lower = 0;
        var up_limite =true;
        var low_limite =true;
        if($("#user_group_upper").val()!=undefined && $("#user_group_upper").val() != "0" && $("#user_group_upper").val() != "") group_upper = parseFloat($("#user_group_upper").val());
        else up_limite = false;
        if($("#user_group_lower").val()!=undefined && $("#user_group_lower").val() != "0" && $("#user_group_lower").val() != "") group_lower = parseFloat($("#user_group_lower").val());
        else low_limite = false;

        if( bet_total>group_upper) {
            if(up_limite) return 1;
            else return 0;
        }else if( bet_total < group_lower){
            if(low_limite) return 2;
            else return 0;
        }else return 0;
    }
    
    // function checkReverse(issue,sel,arr) {
    //     var way_list = [];
    //     $(".issue_"+issue).each(function () {
    //         if($(this).attr("name") != undefined) {
    //             way_list.push($(this).attr("name"));
    //         }
    //     });
    //     way_list.push(sel);
    //     if(arr.length>0) way_list = way_list.concat(arr);
    //     if( $("#reverse_2").val()=="1" && way_list.indexOf("大双")>=0 && way_list.indexOf("小双")>=0 && way_list.indexOf("大单")>=0 && way_list.indexOf("小单")>=0) {
    //         fnSet.alert("第"+issue+"期，不能投注房间玩法:(组合)的所有玩法");
    //         return false;
    //     }
    //
    //     if( $("#reverse_1").val()=="1" && way_list.indexOf("单")>=0 && way_list.indexOf("双")>=0) {
    //         fnSet.alert("第"+issue+"期，不能投注房间玩法:(单双)的所有玩法");
    //         return false;
    //     }
    //
    //     if( $("#reverse_0").val()=="1" && way_list.indexOf("大")>=0 && way_list.indexOf("小")>=0) {
    //         fnSet.alert("第"+issue+"期，不能投注房间玩法:(大小)的所有玩法");
    //         return false;
    //     }
    //
    //     return true;
    // }
})

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

    // var data = {
    //     'number': number,
    //     'token':userinfo.token,
    //     'room_id':userinfo.room_id,
    //     'type':type
    // }
    // $.ajax({
    //     url:"?m=api&c=order&a=ceDanZhuiHao",
    //     type:'post',
    //     dataType:'json',
    //     data:data,
    //     beforeSend:function(){
    //         $(obj).attr("disabled",true)
    //         $(obj).html("撤单中..")
    //     },
    //     success:function(data) {
    //         if(data.code == 0){
    //             $(".zhui_details").hide();
    //             $(".roomHead1 li span.icoAcer").text(data.money);
    //             var issue = $("#issue").text();
    //             $(".issue_"+issue).remove();
    //             fnSet.alert("撤单成功");
    //         }else{
    //             fnSet.alert("撤单失败");
    //         }
    //     },
    //     complete:function(){
    //         $(obj).attr("disabled",false)
    //         $(obj).html("撤单")
    //     }
    // })
}
/*
 * 修改人: CLoud
 * 获取该房间所有限额
 * 用途:限制生成追号
 * params id int 房间号
 */
$(function(){
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

    //处理幸运飞艇的期号前4位
    if(userinfo.lottery_type == 4){
        $("#issue2").text($("#issue2").text().substr(4));
        var lottery =$(".lottery dl dd ul");
        for(var i=0; i < lottery.length; i++){
            lottery.eq(i).children("li").eq(0).text(lottery.eq(i).children("li").eq(0).text().substr(4));
        }
    }



});