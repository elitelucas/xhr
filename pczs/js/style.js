/**
 * Created by HCHT- on 2017/6/9.
 */
function infoLogin() {
    var infoLogin = {};
    if (localStorage.getItem("infoLogin") && localStorage.getItem("infoLogin") != null) {
        infoLogin = JSON.parse(localStorage.getItem("infoLogin"));
    }
    return infoLogin;
};

function serverTime() {
    var time = null;
    $.ajax({
        async: false,
        type: "POST", //get 方式猎豹有问题
        success: function(result, status, xhr) {
            time = new Date(xhr.getResponseHeader("Date"));

        }
    });
    return time;
}

//为jq 添加方法
jQuery.ask = function(content) { //提示框（2秒自动隐藏）
    var tis = '<div class="box-ask"><div class="box-ask-con">' + content + '</div></div>';
    var ask = $(".box-ask").length;
    if (ask) {
        $(".box-ask").show().find(".box-ask-con").html(content);
    } else {
        $("body").append(tis);
    }
    setTimeout(function() {
        $(".box-ask").hide();
    }, 2000);
}
jQuery.alert = function(content, sibtne) { //弹出框 一个确认按钮
    var _alert = $("#box-alert");
    a_sibtne = function() {
        $(this).parents("#box-alert").hide();
        if (typeof(sibtne) == 'function') {
            sibtne();
        }
    }
    var html = '<div class="bomb_box" id="box-alert">' +
        '<div class="box-alert">' +
        '<div class="box-alert-con">' +
        content +
        '</div>' +
        '<div class="box-alert-but-warp">' +
        '<button class="confirm-btn box-alert-but" id="box-alert-but">确认</button>' +
        '</div>';
    if (_alert.length) {
        _alert.show().find(".box-alert-con").html(content);
    } else {
        $("body").append(html);
    }
    $("#box-alert-but").off("click").on("click", a_sibtne);
}

jQuery.confirm = function(content, sibtne, cile) { //询问框  确认和取消按钮
    a_sibtne = function() {
        $(this).parents("#box-confirm").hide();
        if (typeof(sibtne) == 'function') {
            sibtne();
        }
    }
    b_sibtne = function() {
        $(this).parents("#box-confirm").hide();
        if (typeof(cile) == 'function') {
            cile();
        }
    }
    var html = '<div class="bomb_box" id="box-confirm">' +
        '<div class="box-confirm">' +
        '<h2>提示</h2>' +
        '<div class="box-confirm-con">' +
        content +
        '</div>' +
        '<div class="box-confirm-but-warp">' +
        '<button class="cancel-btn  fl" id="box-confirm-cancel">取消</button>' +
        '<button class="confirm-btn fr" id="box-confirm-submit">确认</button>' +
        '</div></div></div>';
    var confirm = $("#box-confirm");
    if (confirm.length) {
        confirm.show();
        confirm.find(".box-confirm-con").html(content);
    } else {
        $("body").append(html);
    }
    $("#box-confirm-submit").off("click").on("click", a_sibtne);
    $("#box-confirm-cancel").off("click").on("click", b_sibtne);
}
//倒计时
jQuery.countdown = function(obj, i) {
    $(obj).flipcountdown({
        size: 'sm',
        tick: function() {
            i--;
            return sealingPlateTime(i);
        },
    });
}
jQuery.myAjax = function(url, data, fun,loading) {
    var _thisType_ = false;
    //请求ajax接口方法  url: 地址  data:数据  fun: 成功之后的调用  loading :是否加loading (false不加loading)
    if(data._thisType_){
        delete data._thisType_;
        _thisType_ = true;
    }
    var dataNew = encryption(JSON.stringify(data));
    if (typeof loading == "undefined") {
        load = true;
    } else {
        load = false;
    }
    $.ajax({
        url: url,
        type: 'POST',
        dataType: 'json',
        data: dataNew,
        global: load,
        success: function(res) {
            var newData = JSON.parse(decrypt(res.data));
            if (newData.code == 0) {
                fun(newData);
            } else if (newData.code == 6) {
                localStorage.removeItem('infoLogin');
                localStorage.removeItem('popupAnnouncement');
                $.ask(newData.msg);
                window.location.href = "/Home/Index/index.html";
                return;
            }else if (newData.code == 2203 || newData.code == 2202) {  //投注已封盘（不在投注时间内）、期号异常
                var txt = $(".main-title").find("span").text()||$("#tbQH").text();
                var obj = $(".main-title").find("span").parents(".mask");
                $.alert(txt+"The period has been closed, please re-bet",function(){
                   // history.go(0);
                   fun(newData,true);
                });
            }else {
                if(_thisType_){
                    $.ask(newData.msg);
                    fun(newData,true);
                    return false;
                }
                if (newData.code != 1808) {
                    if(newData.msg =='该请求已超时，请重新发送请求'){
                        console.log('url:',url,new Date())
                    }

                    $.ask(newData.msg);
                }
            }
        },
        error: function(res) {
            $.ask("Request failed");
        }
    })
}

//截取url/
function GetRequest() {
    var url = decodeURI(location.search); //获取url中"?"符后的字串
    var theRequest = new Object();
    if (url.indexOf("?") != -1) {
        var str = url.substr(1);
        strs = str.split("&");
        for (var i = 0; i < strs.length; i++) {
            theRequest[strs[i].split("=")[0]] = unescape(strs[i].split("=")[1]);
        }
    }
    return theRequest;
}

//修改当前url的指定参数 url 目标url arg 需要替换的参数名称  arg_val 替换后的参数的值  return url 参数替换后的url 
function changeURLArg(arg, arg_val) {
    var url = this.location.href.toString();
    var pattern = arg + '=([^&]*)';
    var replaceText = arg + '=' + arg_val;
    if (url.match(pattern)) {
        var tmp = '/(' + arg + '=)([^&]*)/gi';
        tmp = url.replace(eval(tmp), replaceText);
        return tmp;
    } else {
        if (url.match('[\?]')) {
            return url + '&' + replaceText;
        } else {
            return url + '?' + replaceText;
        }
    }
    return url + '\n' + arg + '\n' + arg_val;
}
//从数组中删除指定的元素  arr为数组 val为指定值
function removeByValue(arr, val) {
    for (var i = 0; i < arr.length; i++) {
        if (arr[i] == val) {
            arr.splice(i, 1);
            break;
        }
    }
}
//定义排序方法
function sortNumber(a, b) {
    return a - b
}
// //加密解密
function base64_decode(input) {
    var rv;
    rv = window.atob(input);
    rv = escape(rv);
    rv = decodeURIComponent(rv);
    return rv;
}

function base64_encode(input) {
    var rv;
    rv = encodeURIComponent(input);
    rv = unescape(rv);
    rv = window.btoa(rv);
    return rv;
}

function decrypt(str) { //解密
    var token = 'hcht_2016_kylin';
    var token = hex_md5(token);

    var str = JSON.parse(base64_decode(str));

    var str2 = new Array();
    var index = '';
    for (var i = 0; i < str.length; i++) {
        if (i > token.length - 1) {
            index = token.length - 1;
        } else {
            index = i;
        }
        str2[i] = String.fromCharCode(str[i] - token[index].charCodeAt());
    }
    return base64_decode(str2.join(""));
}

function encryption(str) { //加密
    var token = hex_md5('hcht_2016_kylin');

    var str = base64_encode(str);

    var len = str.length;

    var data = new Array();
    var index = 0;
    for (var i = 0; i < len; i++) {
        if (i > token.length - 1) {
            index = token.length - 1;
        } else {
            index = i;
        }
        data[i] = str[i].charCodeAt() + token[index].charCodeAt();
    }
    data = JSON.stringify(data);
    data = base64_encode(data);

    var str2 = '{"data":"' + data + '"}';
    return str2;
}

function timestamp() {
    var date = new Date();
    var dataD = date.getTime(); //获取当前时间转化成秒
    var timestamp = parseInt(dataD / 1000) + ""; //时间戳
    var myDate = null; //获取当前日期，给input[type=date]
    var seperator1 = "-";
    var month = date.getMonth() + 1;
    var strDate = date.getDate();
    if (month >= 1 && month <= 9) {
        month = "0" + month;
    }
    if (strDate >= 0 && strDate <= 9) {
        strDate = "0" + strDate;
    }
    var value = {
        dataD: dataD,
        timestamp: timestamp,
        myDate: date.getFullYear() + seperator1 + month + seperator1 + strDate,
    }
    return value;
}

function sealingPlateTime(time) {
    var day = 0,
        hour = 0,
        minute = 0,
        second = 0; //默认值
    if (time > 0) {
        // day = Math.floor(time / (60 * 60 * 24));
        // hour = Math.floor(time / (60 * 60)) - (day * 24);
        // minute = Math.floor(time / 60) - (day * 24 * 60) - (hour * 60);
        // second = Math.floor(time) - (day * 24 * 60 * 60) - (hour * 60 * 60) - (minute * 60);
        hour = Math.floor(time / (60*60));
        minute = Math.floor(time / 60) - (hour * 60);
        second = Math.floor(time) - (hour * 60 * 60) - (minute * 60);
    }
    hour <= 9 ? hour = '0' + hour : hour;
    minute <= 9 ? minute = '0' + minute : minute;
    second <= 9 ? second = '0' + second : second;
    if (day > 0) {
        return day + ":" + hour + ":" + minute + ":" + second;
    } else {
        return hour + ":" + minute + ":" + second;
    }
    // else{
    //     return minute+":"+second;
    // }

}

function sealingPlateTime2(time) {
    var minute = 0,
        second = 0; //默认值
    if (time > 0) {
        minute = Math.floor(time / 60);
        second = Math.floor(time) - (minute * 60);
    }
    minute <= 9 ? minute = '0' + minute : minute;
    second <= 9 ? second = '0' + second : second;

    return minute + ":" + second;
}
//获取hre参数值 ?name=a  传递name参数 返回a;
function GetQueryString(name) {
    var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)");
    var r = window.location.search.substr(1).match(reg);
    if (r != null) return unescape(r[2]);
    return null;
}
//头部和导航公用的hover效果
function downMenu() {
    var oDrop = $('.nav_lottery');
    var timer = null;
    oDrop.on({
        mouseenter: function() {
            var This = $(this);
            timer = setTimeout(
                function() {
                    This.children("a").css("background", "#2e2e2e");
                    This.children(".small_warp").fadeIn();
                    This.find('.small_warp>div').addClass('cssAll');
                    This.addClass("cssAll-hover");
                }, 300);
        },
        mouseleave: function() {
            clearTimeout(timer);
            $(this).children("a").css("background", "none");
            $(this).children(".small_warp").fadeOut();
            $(this).find('.small_warp>div').removeClass('cssAll');
            $(this).removeClass("cssAll-hover");
        }
    })
}


// iFrame 自适应高度
function iFrameHeight() {
    var ifm= document.getElementById("iframeId");
    var subWeb = document.frames ? document.frames["iframeId"].document : ifm.contentDocument;
    if(ifm != null && subWeb != null) {
        // alert(subWeb.body.scrollHeight);
        document.getElementById("iframeWrap").style.height=subWeb.body.scrollHeight+"px";
        document.getElementById("iframeWrap").style.width=subWeb.body.scrollWidth+"px";
        //ifm.height = subWeb.body.scrollHeight;
        //ifm.width = subWeb.body.scrollWidth;
    }
}