/* 互动推活动公共方法 */
(function () {
    window.T = {
        data: {
            falseInfo: {}
        },

        // 代理proxy_getJSON请求，容错1次，支持jsonp
        // url         请求的地址
        // callback    回调函数
        // condition   查询参数（可选）
        // num         失败重查次数（可选，默认为1）
        // jsonpCallback    存在时使用jsonp
        // async       是否为异步，默认异步，传false为同步
        // code      传true表示不判断code200，否则判断code为200
        proxy_getJSON: function (url, callback, condition, num, jsonpCallback, async, code) {
            var count = 0;
            var _task = null;
            condition = condition || {};
            num = num || 1;
            _task = function () {
                var done = false;
                var opts = {
                    url : url,
                    data : condition,
                    dataType : "json",
                    xhrFields: {withCredentials: true},
                    success : function (ret) {
                        if (ret.code === 200 || code === true) {
                            callback(ret);
                            clearTimeout(_task);
                        } else {
                            if (count < num) {
                                setTimeout(_task, 100);
                                count++;
                            } else {
                                callback(ret);
                                clearTimeout(_task);
                            }
                        }
                    },
                    complete : function (XMLHttpRequest, textStatus) {
                        if (textStatus !== "success") {
                            if (count < num) {
                                setTimeout(_task, 100);
                                count++;
                            } else {
                                if (textStatus === "timeout") {
                                    callback({code : 101, message : "请求超时"});
                                } else if (textStatus === "error") {
                                    callback({code : 101, message : "请求异常"});
                                }
                                clearTimeout(_task);
                            }
                        }
                    }
                };
                if (jsonpCallback) {
                    opts.dataType = "jsonp";
                    opts.jsonpCallback = jsonpCallback || "sucess";
                }
                if (async === false) {
                    opts.async = false;
                }
                $.ajax(opts);
            };
            _task();
        },
        getApiDomain: function () {
            var arr = window.location.host.split('.');
            arr[0] = 'apidisplay';
            return '//' + arr.join('.');
        },
        getDevice: function () {
            var ua = navigator.userAgent.toLowerCase();
            if (/iphone|ipad|ipod/.test(ua)) {
                return 'IOS';
            } else if(/android/.test(ua)) {
                return 'android';
            } else {
                return '';
            }
        },
        //ajax取不到值时弹窗
        errorWin: function (msg) {
            msg = msg || '网络拥堵,稍后再试';
            var ht = (
                '<div class="common-error-pop-mask common-mask">' +
                '    <div class="common-notimes-pop">' +
                '        <div class="close-icon"></div>' +
                '        <div class="popup-con">' +
                '            <div class="msg-txt">' + msg + '</div>' +
                '        </div>' +

                '    </div>' +
                '</div>'
            );
            var p = $(ht).appendTo($('body'));
            $(".common-notimes-pop").find('.close-icon').on('click', function () {
                $(this).parent().fadeOut(
                    function(){
                        $('.mask').hide()
                    }
                );
                pageReset();
            });

        },
        //抽奖机会用完H5提示
        notimesH5: function (msg) {
            if(!/Android|webOS|iPhone|iPod|BlackBerry/i.test(navigator.userAgent)) {
                var urlLink1 = '/pcweb/index.html#/topUpCenter/recharge'
                var urlLink2 = '/pcweb/index.html'
            } else {
                var urlLink1 = '/pcmobile/index.html#/recharge'
                var urlLink2 = '/pcmobile/index.html'
            }
            msg = msg || '您的砸蛋次数已用完！';
            var ht = (
                '<div class="common-error-pop-mask common-mask">' +
                '    <div class="common-notimes-pop">' +
                '        <div class="close-icon"></div>' +
                '        <div class="popup-con">' +
                '            <div class="msg">' + msg + '</div>' +
                '            <div class="btn-recharge"><a href=' + urlLink1 + '>马上充值</a></div>' +
                '            <div class="btn-gobet"><a href=' + urlLink2 + '>马上投注</a></div>' +
                '        </div>' +

                '    </div>' +
                '</div>'
            );
            var p = $(ht).appendTo($('body'));
            $(".common-notimes-pop").find('.close-icon').on('click', function () {
                $(this).parent().fadeOut(
                    function(){
                        $('.mask').hide()
                        window.location.reload();
                        
                        
                        
                    }
                );
                pageReset();
            });
            //$(".common-error-pop").find('.close-icon').on('click', function () {
            //    p.remove();
            //});
        },
        //抽奖机会用完app提示
        notimesApp: function (msg) {
            msg = msg || '您的砸蛋次数已用完！';
            var ht = (
                '<div class="common-error-pop-mask common-mask">' +
                '    <div class="common-notimes-pop">' +
                '        <div class="close-icon"></div>' +
                '        <div class="popup-con">' +
                '            <div class="msg-txt">' + msg + '</div>' +
                '        </div>' +
                '    </div>' +
                '</div>'
            );
            var p = $(ht).appendTo($('body'));
            $(".common-notimes-pop").find('.close-icon').on('click', function () {
                $(this).parent().fadeOut(
                    function(){
                        $('.mask').hide()
                        
                    }
                );
                pageReset();
            });
            //$(".common-error-pop").find('.close-icon').on('click', function () {
            //    p.remove();
            //});
        },
        //没抽到
        commonPopupThanks: function (opts) {
            var ht = (
                '<div class="common-mask">' +
                '    <div class="common-thanks-wrapper">' +
                '        <div class="close-icon">×</div>' +
                '        <div class="main-title">谢谢参与</div>' +
                '        <div class="confirm-btn">继续抽奖</div>' +
                '    </div>' +
                '</div>'
            );
            var p = $(ht).appendTo($('body'));
            //p.find('.close-icon,.confirm-btn').off().on('click', function () {
            //    p.remove();
            //});
        },


        doLottery: function (params, cb) {
            var t = this;
            params = params || {};
            params.act_id = params.act_id || t.getParam('actId');
            params.adzone_click_id = params.adzone_click_id || t.getParam('logId');
            params.device = t.getDevice();
            t.proxy_getJSON(window.location.protocol + t.getApiDomain() + '/lottery.htm', function (ret) {
                if (ret.code === 200) {
                    cb(ret);
                } else {
                    t.errorWin(ret.message || ret.data);
                    cb(ret);
                }
            }, params);
        },

        iosNotice: function (container) {
            var t = this;
            container = container || $('.main') || $('body');
            if (t.getDevice() === 'IOS') {
                var _bodyHeight = $('body').height();
                var mainWinH = $('.main').height();
                if (mainWinH < _bodyHeight) {
                    container.append('<div class="ios-notice fixed">*兑换项与活动和设备生产商Apple lnc.公司无关</div>');
                } else {
                    container.append('<div class="ios-notice">*兑换项与活动和设备生产商Apple lnc.公司无关</div>');
                }
            }
        },

        correctUrl: function (url) {
            // 如果是http资源，当前是https，则做替换
            url = url.replace(/^https?:\/\//g, window.location.protocol + "//");
            // 如果资源是后台配置的display.adhudong.com资源，自动替换为当前域名
            url = url.replace(/^https?:\/\/display\.adhudong\.com/g, window.location.origin);
            return url;
        },
        getRedBox:function(actId,adzoneId,mediaId){//右下角添加红包app的下载入口
        	var pos = "adwID_"+adzoneId+"_actionID_"+actId;
        	var chn = "mediaID_"+mediaId;
			//var _url = "https://union.egou.com/to?site=23&term=3&page=hudongtui&pos="+pos+"&chn="+chn+"&url=http%3A%2F%2Fm.bigou.cn%2Fdownload%2Fhongbao-1.0.0.apk";
        	var _url = "http://m.bigou.cn/download/hongbao-1.0.0.apk";
        	if(adzoneId == 295 && actId == 17 && this.getDevice()=='android'){
        		$('body').append("<a href="+_url+" class='common-getred'><span></span></a>")
        	}
        },
    };
})();