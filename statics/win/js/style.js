/**
 * Created by win10 on 2017/8/17.
 */
var fnSet=function(){};

//fnSet.alert =function(text,sibtne){                //提示框
//    var _alert = $(".popupAlert");
//    a_sibtne = function() {
//        $(this).parents(".popupAlert").remove();
//        if (typeof(sibtne) == 'function') {
//            sibtne();
//        }
//    }
//    var alert ='<div class="popupAlert"><div class="config"><p>'+text+'</p><div class="button" style="margin-top: 30px;"><button class="confirm">确认</button></div></div></div>'
//    // $("body").append(alert);
//     if (_alert.length) {
//         _alert.show().find(".config p").html(text);
//     } else {
//     $("body").append(alert);
//     }
//     $(".confirm").off("click").on("click", a_sibtne);
//    // $(".confirm").on("click",function(){
//    //     $(this).parents(".popupAlert").remove();
//    // })
//}

jQuery.confirm = function(content,sibtne,cile) {  //询问框  确认和取消按钮
    a_sibtne = function(){
        $(this).parents("#box-confirm").hide();
        if (typeof(sibtne) == 'function') {
            sibtne();
        }
    }
    b_sibtne = function(){
        $(this).parents("#box-confirm").hide();
        if (typeof(cile) == 'function') {
            cile();
        }
    }

    var html= '<div class="popupAlert" id="box-confirm"><div class="popupAlert">'+
        '<div class="config"><p class="box-confirm-con">'+
        content +
        '</p><div class="button confirmWap" style="margin-top: 30px;">'+
        '<button class="confirm  left" id="box-confirm-cancel">取消</button>'+
        '<button class="confirm right" id="box-confirm-submit">确认</button>'+
        '</div></div></div>';

    // var html= '<div class="cenWarp"><div class="cenCon"><div class="cenConText">'+content+'</div><button id="sibtne">确定</button><button id="cile">取消</button></div>';
    var confirm =$("#box-confirm");
    if(confirm.length){
        confirm.show();
        confirm.find(".box-confirm-con").html(content);
    }else{
        $("body").append(html);
    }
    $("#box-confirm-submit").off("click").on("click",a_sibtne);
    $("#box-confirm-cancel").off("click").on("click",b_sibtne);
}

$(function(){


    //首页房间选项卡切换
    $(".content-nav").on("click","li",function(){
        $(this).addClass("active").siblings().removeClass("active");
        var index = $(this).index();
        $(".content-room .tabC").eq(index).show().siblings().hide();
    })

    //$(".indexTab ul li").click(function() {
    //    $(this).addClass("active").siblings().removeClass("active");
    //    var index = $(this).index()
    //    $(".tabContent .tabC").eq(index).show().siblings().hide();
    //});

    $(".my-fl > ul >li").on("click",function(){
        $(this).children("a").addClass("active");
        $(this).siblings().children("a").removeClass("active");
        $(".my-fl > ul >li").find(".jqShow").removeClass("ico-bot2").addClass("ico-bot1");
        $(".my-fl > ul >li").children("ul").hide();
        if($(this).children("ul").css("display") =="none"){
            $(this).children("ul").show()
            $(this).find(".jqShow").removeClass("ico-bot1").addClass("ico-bot2");

        }else{
            $(this).children("ul").hide();
            $(this).find(".jqShow").removeClass("ico-bot2").addClass("ico-bot1");
        }

    })




    //房间开奖结果显示隐藏/
    var fag =false;
    $(".room-issue").click(function(){
        if(fag){
            $(this).children("i").css("transform","rotate(0deg)");
            fag =false;
        }else{
            $(this).children("i").css("transform","rotate(180deg)");
            fag =true;
        }
        $(".lottery").toggle();
    })


    if($(".headerRight li.icoNews").attr("data-new") > 0){
        $(".headerRight li.icoNews").addClass("oAfter")
    }

    /*连接href*/
    $("*").on("click","[data-href]",function(){
        //alert($(this).attr("data-href"));
        var href = $(this).attr("data-href");
        window.location.href= href;
    });

    //*********************密码可见********************************/
    $(".see").click(function(){
        var type = $(this).siblings().children("input").attr("type");
        if(type =="text"){
            $(this).removeClass("see1");
            $(this).siblings().children("input")[0].type = "password";
        }else{
            $(this).addClass("see1");
            $(this).siblings().children("input")[0].type = "text";
        }

    })
    //*********************select 下拉列表********************************/
    $(".select").change(function(){
        $(this).siblings(".inputW").text($(this).children('option:selected').text())
//        $(".inputW").text($(this).children('option:selected').val());
    })
    //*********************input 首次设置资金密码********************************/
    $(".inputPas").on("focus",function(){
        var index =$(this).val().length;
        // //把光标移到input值后面
        // var v =$(this).val();
        // $(this).val("");
        if(index ==6){
            $(this).siblings("ul").children("li").eq(index-1).addClass("guangbiao1");
        }else{
            $(this).siblings("ul").children("li").eq(index).addClass("guangbiao");
        }

    })
    $(".inputPas").on("blur",function(){
        $(this).siblings("ul").children("li").removeClass("guangbiao");
        $(this).siblings("ul").children("li").removeClass("guangbiao1");
    })

    $(".inputPas").on("input change",function(){
        $(this).val($(this).val().replace(/[^\d]/g,''));
        var index =$(this).val().length;
        var val =$(this).val();
        if(index > 6){
            val=val.substring(0,6);
            $(this).val(val);
            // fnSet.alert("密码最多6位数！");
            return false;
        }
        var oLi =$(this).siblings("ul").children("li");
        if(index){
            oLi.eq(index).prevAll().text("*");
            oLi.eq(index-1).nextAll().text("");
            oLi.removeClass("guangbiao").eq(index).addClass("guangbiao");
            if(index ==5){
                oLi.removeClass("guangbiao1");
            }
            if(index ==6){
                oLi.eq(index-1).text("*");
                oLi.removeClass("guangbiao").eq(index-1).addClass("guangbiao1");
            }

        }
        if(!index){
            oLi.eq(0).text("");
            oLi.removeClass("guangbiao").eq(0).addClass("guangbiao");
        }

    })

    //房间开奖结果显示隐藏/
    //var fag =false;
    //$(".roomHead2").click(function(){
    //    if(fag){
    //        $(this).children("em").css("transform","rotate(0deg)");
    //        fag =false;
    //    }else{
    //        $(this).children("em").css("transform","rotate(180deg)");
    //        fag =true;
    //    }
    //    $(".lottery").toggle();
    //})

    //title上的加号
    //$(".icoAdd").click(function(){
    //    $(".menu").toggle();
    //})
    //$(".menu li").click(function(){
    //    $(".menu").hide();
    //})

    //点击X关闭弹窗
    $(".configClose").click(function() {
        $(".popup").css("display","none");
        $("input[name=secret_pwd]").val('');
    });

    //查询报表
    $(function(){
        $("#teamTime").on("click",function(){
            $(".teamSearchTime").show();
        })
        // $("#date1").on("input",function(){
        //     var val = $(this).val();
        //     $("#startTime").val(val.replace(/-/g,"/"));
        // })
        // $("#date2").on("input",function(){
        //     var val = $(this).val();
        //     $("#endTime").val(val.replace(/-/g,"/"));
        // })
        $(".cancelX").on("click",function(){
            $(".teamSearchTime").hide();
        })
        $(".chaX").on("click",function(){
            var starTime =$("#startTime").val();
            var endTime = $("#endTime").val();

            if (starTime == '' && endTime == '') {
                $("#teamTime span").html("交易时间：全部");
            }else if(starTime.substring(0,4)-endTime.substring(0,4) ==0){
                $("#teamTime span").html(starTime.substring(5,10)+'-'+endTime.substring(5,10));
            }else{
                $("#teamTime span").html(starTime+'-'+endTime);
            }

        })
        $('.cleanUp').on("click",function(){
            $("#startTime").val('');
            $("#endTime").val('');
            $("#teamTime span").html("交易时间：今天")

        })

        //判断时间轴
        var date1 ,
            date2;
        $("#date1").on("input", function(){
            date1 = $(this).val();
            if(date2){
                if(date1>date2){
                    alert("开始时间不能大于结束时间")
                }else{
                    var val = $(this).val();
                    $("#startTime").val(val.replace(/-/g,"/"));
                }
            }
        })
        $("#date2").on("input", function(){
            date2 = $(this).val();
            if(date1){
                if(date1>date2){
                    alert("结束时间不能少于开始时间")
                }else{
                    var val = $(this).val();
                    $("#endTime").val(val.replace(/-/g,"/"));
                }
            }
        })

        //刷新
        $(".icoRefresh").click(function() {
            window.location.reload();
        });
    })

    //首页选项卡
    //$(".indexTab ul li").click(function() {
    //    $(this).addClass("active").siblings().removeClass("active");
    //    var index = $(this).index()
    //    $(".tabContent .tabC").eq(index).show().siblings().hide();
    //});

    //选择时间
    $("input[type=date]").on("change",function(){
        $(this).siblings(".inputDate").val($(this).val());
    })
    //下拉菜单
    $("select").on("change",function(){
        $(this).siblings(".sel").val($(this).find("option:selected").text());
    })


    $("body").on("focus","input",function(){
        // var viewTop = $(window).scrollTop(),            // 可视区域顶部
        //     viewBottom = viewTop + window.innerHeight;  // 可视区域底部
        // var elementTop = $(this).offset().top, // $element是保存的input
        //     elementBottom = elementTop + $(this).height();
        // $(window).scrollTop(value);


    })

})