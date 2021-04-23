webpackJsonp([55], {
	gD6N: function (t, e, s) {
		"use strict";
		Object.defineProperty(e, "__esModule", {
			value: !0
		});
		var i = {
				data: function () {
					return {
						lotteryList: [],
                        english: [{
                            "急速赛车": "Fast 3 cars",
                            "分分PK10": "Fast 1 car",
                            "三分彩": "Sharp 3 colors",
                            "百人牛牛": "Bullfight",
                            "分分彩": "Sharp 1 color",
                            "欢乐骰宝": "Dice",
                        }]
					}
				},
				created: function () {
					this._getLotteryList()
				},
				methods: {
					_getLotteryList0: function () {
						var t = this;
						this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=win&c=lobby&a=getHeaderFooter").then(function (e) {
							t.$vux.loading.hide(), 0 == e.status ? t.lotteryList = e.data.lottery_list : e.ret_msg && "" != e.ret_msg && t.$vux.toast.show({
								text: e.ret_msg
							})
						})
					},
					_getLotteryList: function () {
						var t = this;
						this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=win&c=lobby&a=getHeaderFooter").then(function (e) {
							t.$vux.loading.hide(), 0 == e.status && (t.lotteryList = e.data.lottery_list)
						}).catch(function (e) {
							t.$vux.loading.hide(), console.log(e)
						})
					}
				}
			},
			a = {
				render: function () {
					var t = this,
						e = t.$createElement,
						i = t._self._c || e;
					return i("div", {
						staticClass: "isHeader"
					}, [i("div", {
						staticClass: "headerWrap"
					}, [i("x-header", {
						staticClass: "header",
						attrs: {
							"left-options": {
								showBack: !0
							}
						}
					}, [t._v("\n            How to play\n        ")])], 1), t._v(" "), i("div", {
						staticClass: "content"
					}, [this.lotteryList ? [i("scroll", [i("group", {
						staticClass: "top-group"
					}, t._l(t.lotteryList, function (e, s) {
						return i("cell", {
							key: s,
							attrs: {
								title: t.english[0][e.lottery_name]
							},
							nativeOn: {
								click: function (s) {
									t.$router.push("/introDetail?article_id=" + e.article_id)
								}
							}
						})
					}))], 1)] : [i("img", {
						staticClass: "noDataImg",
						attrs: {
							src: s("w+73"),
							alt: ""
						}
					})]], 2)])
				},
				staticRenderFns: []
			};
		var o = s("vSla")(i, a, !1, function (t) {
			s("iR7k")
		}, "data-v-34c323d0", null);
		e.default = o.exports
	},
	iR7k: function (t, e) {}
});
//# sourceMappingURL=55.2214a20ce091c73179c7.js.map