webpackJsonp([26], {
	"0rJy": function(t, o) {},
	"9TUZ": function(t, o) {
		t.exports =
			"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAeCAMAAAB61OwbAAAAh1BMVEX///8AAAD///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////9T1RfSAAAALXRSTlPmANgF4t7WeyAas3QvDZHHuEY3EgfRzKObg21hT0oo28O9raCXZllViIddPReGXnBeAAABHUlEQVQoz4XR6XKCMBSG4e+QhV0BUZAKrrXr/V9fk1AgoTB9/sRx3pkcTkCuML4K7OuWBrPgVaCXBfMg1P98YCQ7JwjvECW9wHJIrSCROG9o58HmT8Fuj1r1MRwiHYMjKqaODK52CMoz1xMyzPhDUOBGyjdmNkNwwrv5kvWg/8n42hUJrqRFcOVDwCQKs2c4ZDkE5GO7U0e6h62ZFpVuccgZUQ5LxqxVBwdAPsl+jEvoPNaJAxVNhb7TCpIIPPIDCjYcY/Ecr2A++DFUZyNgy7o+YA/IQk96x4xoTRBDJHoZN/zBcxWEHG/9ky8QASFGbebEoooQIdHBA8s6eB4pJccyH7jooMCKCLEZ8RMrJMhosIqM+L/gS3q/OBzRDyRPC846W6yxAAAAAElFTkSuQmCC"
	},
	XkAF: function(t, o, s) {
		"use strict";
		Object.defineProperty(o, "__esModule", {
			value: !0
		});
		var e = s("4YfN"),
			a = s.n(e),
			i = s("3cXf"),
			r = s.n(i),
			n = s("//TE"),
			c = s("9rMa"),
			l = (s("Y0Uy"), a()({}, Object(c.c)(["accountData", "roomData"])), {
				data: function() {
					return {
						english: [{
							"急速赛车": "Fast 3 cars",
							"分分PK10": "Fast 1 car",
							"三分彩": "Sharp 3 colors",
							"百人牛牛": "Bullfight",
							"分分彩": "Sharp 1 color",
							"欢乐骰宝": "Dice",
						}],
						roomList: {},
						showPwdDlg: !1,
						lottery_type: 0,
						tip: "",
						showTip: !1
					}
				},
				mounted: function() {
					var t = this,
						o = {
							token: localStorage.getItem("token"),
							lottery_type: this.$route.query.lottery_type
						};
					this.$http.post(this.urlRequest + "?m=api&c=lobbynew&a=room_info", Object(n.c)(r()(o))).then(function(o) {
						0 == o.status && (t.roomList = JSON.parse(Object(n.a)(o.data)), console.log(t.roomList)), t.$vux.loading.hide()
					})
				},
				created: function() {
					this.$vux.loading.show()
				},
				computed: a()({}, Object(c.c)(["accountData", "roomData"])),
				methods: {
					headBack: function() {
						this.$router.push({
							path: "/home"
						})
					},
					showPlDlg: function(t, o) {
						if ("私密房间" == this.roomList.room[o].max_number) return this.tip = "Please enter the room and check it", void(this.showTip = !0);
						t = t.replace(/\r?\n|\r|&crarr;|&#8629;/g, "<br>"), console.log(t), this.tip = t || "No explanation of the odds", this.showTip = !
							0
					},
					goPwdRoom: function(t) {
						var o = this;
						this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=lobby&a=privateRoom", {
							lottery_type: this.lottery_type,
							secret_pwd: t
						}).then(function(s) {
							if (0 == s.status)
								if (s.data.passwd == t) {
									localStorage.setItem("MdStory", "私密房间");
									var e = o.roomData;
									e.plTipText = s.data.odds_exp, o.$store.commit("setRoomData", e), o.$router.push({
										path: "/room/" + s.data.id,
										query: {
											lottery_type: o.lottery_type,
											id: s.data.id
										}
									})
								} else o.$vux.toast.show({
									text: "Room password error"
								});
							else o.$vux.toast.show({
								text: "Room password error"
							});
							o.$vux.loading.hide()
						})
					},
					goRoom: function(t) {
						var o = t.lottery_type;
						this.lottery_type = t.lottery_type;
						var s = t.id,
							e = t.passwd,
							a = parseInt(t.max_yb),
							i = parseInt(t.low_yb),
							r = t.lack_tips,
							n = parseInt(this.accountData.money);
						if ("" === e) {
							if (a < n || n < i) return void this.$vux.toast.show({
								text: r || "Failed to enter. Please try again later"
							});
							if (0 != this.roomList.isAuthRoom) return void this.$vux.alert.show({
								content: "Sorry, you have not opened the current room permission！"
							});
							if (1 == t.status) return void this.$vux.toast.show({
								text: "The room is closed"
							});
							var c = this.roomData;
							c.plTipText = t.odds_exp, this.$store.commit("setRoomData", c), this.$router.push({
								path: "/room/" + s,
								query: {
									lottery_type: o,
									id: s
								}
							})
						} else this.showPwdDlg = !0
					}
				}
			}),
			h = {
				render: function() {
					var t = this,
						o = t.$createElement,
						e = t._self._c || o;
						if(location.href.indexOf("#reloaded")==-1){
						        location.href=location.href+"#reloaded";
						        location.reload();
						    }
					return e("div", {
						staticClass: "isHeader"
					}, [e("div", {
						staticClass: "headerWrap"
					}, [e("x-header", {
						staticClass: "header",
						attrs: {
							title: t.english[0][t.roomList.lottery_title],
							"left-options": {
								preventGoBack: !0
							}
						},
						on: {
							"on-click-back": t.headBack
						}
					})], 1), t._v(" "), t._l(t.roomList.room, function(o, a) {
						return e("div", {
							key: a,
							staticClass: "roomPanel",
							class: {
								"no-img": !o.avatar
							}
						}, [e("div", {
							staticClass: "shade",
							on: {
								click: function(s) {
									if (s.target !== s.currentTarget) return null;
									t.goRoom(o)
								}
							}
						}), t._v(" "), o.avatar ? e("img", {
							staticClass: "data-img",
							attrs: {
								src: t.imgRequest + o.avatar
							}
						}) : e("img", {
							staticClass: "default-img",
							attrs: {
								src: s("cCrS")
							}
						}), t._v(" "), e("div", {
							staticClass: "plTip",
							on: {
								click: function(s) {
									s.stopPropagation(), t.showPlDlg(o.odds_exp, a)
								}
							}
						}, [t._v("Odds statement")]), t._v(" "), e("div", {
							staticClass: "rsBox"
						}, [e("img", {
							attrs: {
								src: "",
								alt: ""
							}
						}), t._v("")])])
					}), t._v(" "), e("br"), t._v(" "), e("confirm", {
						attrs: {
							"show-input": "",
							title: "Input password",
							"input-attrs": {
								type: "password"
							},
							"close-on-confirm": !1
						},
						on: {
							"on-cancel": function(o) {
								t.showPwdDlg = !t.showPwdDlg
							},
							"on-confirm": t.goPwdRoom
						},
						model: {
							value: t.showPwdDlg,
							callback: function(o) {
								t.showPwdDlg = o
							},
							expression: "showPwdDlg"
						}
					}), t._v(" "), e("alert", {
						attrs: {
							title: "Odds statement"
						},
						model: {
							value: t.showTip,
							callback: function(o) {
								t.showTip = o
							},
							expression: "showTip"
						}
					}, [e("pre", {
						staticClass: "tip-text",
						domProps: {
							innerHTML: t._s(t.tip)
						}
					})])], 2)
				},
				staticRenderFns: []
			};
		var u = s("vSla")(l, h, !1, function(t) {
			s("0rJy")
		}, "data-v-05bdda1c", null);
		o.default = u.exports
	}
});
//# sourceMappingURL=26.f817ea538164e5678713.js.map
