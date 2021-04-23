webpackJsonp([40], {
    "/R+J": function(t, a) {},
    "9y5l": function(t, a, s) {
        "use strict";
        Object.defineProperty(a, "__esModule", {
            value: !0
        });
        var i = s("4YfN"),
            e = s.n(i),
            n = s("9rMa"),
            o = (e()({}, Object(n.c)(["accountData"])), {
                data: function() {
                    return {
                        activityList: [],
                        page: 1,
                        isTouristFlag: !1
                    }
                },
                created: function() {
                    this.getList(1), this.accountData.is_tourist && 1 === this.accountData.is_tourist && (this.isTouristFlag = !0)
                },
                computed: e()({}, Object(n.c)(["accountData"])),
                methods: {
                    goContent: function(t, a) {
                        /*window.location.href = "https://www.1255233.com/"+t*/
                        var _that = this;
                        this.accountData.is_tourist && 1 === this.accountData.is_tourist ? this.isTouristFlag = !0 : 0 != a ? 2 != a ? (this.$http.get(t).then(function(res){
                            //console.log(res);
                            _that.$vux.alert.show({
                            title: '详情',
                            content: res,
                            buttonText: '关闭',
                            hideOnBlur: true,
                         })})) : this.$vux.toast.text("The event is over", "bottom") : this.$vux.toast.text("Activity not started", "bottom")
                    },
                    pullingDown: function() {
                        this.page = 1, this.getList()
                    },
                    pullingUp: function() {
                        this.pullUpLoadFlag ? this.$refs.scroll.forceUpdate(!1) : (this.page += 1, this.getList())
                    },
                    getList: function(t) {
                        var a = this,
                            s = this,
                            i = {};
                        i.token = localStorage.getItem("token"), i.page = s.page, t && s.$vux.loading.show(), s.pullUpLoadFlag = !1, s.$http.post(s.urlRequest + "?m=api&c=actcenter&a=fetchActList", i).then(function(i) {
                            t && s.$vux.loading.hide(), 0 == i.status ? (1 == s.page ? s.activityList = i.data : s.activityList = s.activityList.concat(i.data), i.data.length < 10 && (s.pullUpLoadFlag = !0)) : i.ret_msg && "" != i.ret_msg && a.$vux.toast.show({
                                text: i.ret_msg
                            })
                        }).catch(function(t) {
                            s.$refs.scroll.forceUpdate(!1), s.pullUpLoad = !1, s.$vux.loading.hide(), s.$vux.toast.show({
                                text: "Data request timed out"
                            }), console.log(t)
                        })
                    }
                },
                watch: {
                    accountData: function(t) {
                        t.is_tourist && 1 === t.is_tourist && (this.isTouristFlag = !0)
                    }
                }
            }),
            c = {
                render: function() {
                    var t = this,
                        a = t.$createElement,
                        i = t._self._c || a;
                    return i("div", [i("div", {
                        staticClass: "headerWrap"
                    }, [i("x-header", {
                        staticClass: "header",
                        attrs: {
                            title: "Activity Center"
                        }
                    })], 1), t._v(" "), t.activityList.length > 0 ? i("div", {
                        staticClass: "act-list"
                    }, [i("scroll", {
                        ref: "scroll",
                        attrs: {
                            pullDownRefresh: !0,
                            pullUpLoad: !0,
                            data: t.activityList
                        },
                        on: {
                            pullingDown: t.pullingDown,
                            pullingUp: t.pullingUp
                        }
                    }, [i("ul", {
                        staticClass: "li-wrapper"
                    }, t._l(t.activityList, function(a, s) {
                        return i("li", {
                            key: s,
                            on: {
                                click: function(s) {
                                    t.goContent(a.act_url, a.is_underway)
                                }
                            }
                        }, [i("div", {
                            staticClass: "act-banner-wrap"
                        }, [i("div", {
                            staticClass: "act-banner"
                        }, [2 == a.is_underway ? i("div", {
                            staticClass: "mask"
                        }, [t._v("Expired")]) : t._e(), t._v(" "), i("img", {
                            attrs: {
                                src: t.imgRequest + a.act_banner_pic
                            }
                        })])]), t._v(" "), i("div", {
                            staticClass: "act-info"
                        }, [i("h5", [t._v(t._s(a.act_title))]), t._v(" "), 2 == a.is_underway ? i("span", {
                            staticClass: "text-gray"
                        }, [t._v("Finished")]) : i("span", {
                            staticClass: "text-gray"
                        }, [t._v(t._s(a.act_end_date) + " Finished")])])])
                    }))])], 1) : [i("img", {
                        staticClass: "noDataImg",
                        attrs: {
                            src: s("w+73"),
                            alt: ""
                        }
                    })], t._v(" "), i("alert", {
                        attrs: {
                            title: "reminder",
                            "button-text": "got it"
                        },
                        model: {
                            value: t.isTouristFlag,
                            callback: function(a) {
                                t.isTouristFlag = a
                            },
                            expression: "isTouristFlag"
                        }
                    }, [i("pre", {
                        staticClass: "tip-text",
                        domProps: {
                            textContent: t._s("Tourists can't participate in the activities, please register members first！")
                        }
                    })])], 2)
                },
                staticRenderFns: []
            };
        var l = s("vSla")(o, c, !1, function(t) {
            s("/R+J")
        }, "data-v-6f7b6bae", null);
        a.default = l.exports
    }
});
//# sourceMappingURL=40.6ec9674bdeb70d577132.js.map