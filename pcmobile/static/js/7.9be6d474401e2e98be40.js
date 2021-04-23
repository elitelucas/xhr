webpackJsonp([7], {
	"+x8j": function (t, e) {},
	"/MOb": function (t, e, r) {
		"use strict";
		Object.defineProperty(e, "__esModule", {
			value: !0
		});
		var a = r("4YfN"),
			n = r.n(a),
			o = r("9rMa"),
			i = r("/dSo"),
			s = r.n(i),
			c = r("3hM3"),
			u = (c.a, n()({}, Object(o.c)(["rechargeData"])), {
				data: function () {
					return {
						amountVal: 0,
						code: "",
						order: "",
						codeUrl: "",
						codeImg: "",
						collectBank: 0,
						returnPopup: !1,
						size: 135,
						codeShow: !1
					}
				},
				components: {
					Qrcode: c.a
				},
				created: function () {
					var t = this;
					this.$nextTick(function () {
						t.rechargeData.payment_name || t.$router.back()
					}), this.rechargeData.prompt = this.rechargeData.prompt, this.code = this.$route.query.code, this.order = this.$route.query.order, this.amountVal = this.$route.query.amountVal, this.codeUrl = this.$route.query.codeUrl, this.codeImg = this.$route.query.codeImg, this.collectBank = this.$route.query.collectBank
				},
				mounted: function () {},
				computed: n()({}, Object(o.c)(["rechargeData"])),
				methods: {
					onConfirm: function () {
						this.$router.back()
					},
					headBack: function () {
						this.returnPopup = !0
					},
					copyText: function () {
						var t = this,
							e = new s.a(".icoCopy");
						e.on("success", function (r) {
							t.$vux.toast.show({
								text: "Copy succeeded"
							}), e.destroy()
						}), e.on("error", function (r) {
							t.$vux.toast.show({
								text: "This browser does not support automatic replication"
							}), e.destroy()
						})
					},
					submitOnLine: function (t) {
						var e = this;
						if ("Payment" == this.$router.currentRoute.name) {
							var r = {
								token: localStorage.getItem("token"),
								order_no: this.order
							};
							"2" != t && window.open("ali" == this.rechargeData.channel_type ? "alipays://" : "wx" == this.rechargeData.channel_type ? "weixin://" : "qq" == this.rechargeData.channel_type ? "mqqapi://" : "", "_self"), clearInterval(this.timer), this.$http.post(this.urlRequest + "?m=api&c=recharge&a=getRechargeInfo", r).then(function (t) {
								0 == t.status && setTimeout(function () {
									0 != t.state ? 1 == t.state && e.$router.replace({
										path: "/recharge/payStatus",
										query: {
											order: e.order,
											code: e.code,
											amountVal: e.amountVal
										}
									}) : e.timer = setInterval(function () {
										e.submitOnLine(2)
									}, 3e3)
								}, 3e3)
							}).catch(function (t) {
								e.timer = setInterval(function () {
									e.submitOnLine()
								}, 3e3), e.$vux.loading.hide(), e.$vux.toast.show({
									text: "Data request timeout"
								})
							})
						} else clearInterval(this.timer)
					},
					submitOffLine: function () {
						var t = this;
						this.$vux.loading.show();
						var e = {
							token: localStorage.getItem("token"),
							order_sn: this.order
						};
						this.$http.post(this.urlRequest + "?m=api&c=recharge&a=setRechargeMusic", e).then(function (e) {
							t.$vux.loading.hide(), 0 == e.status ? t.$router.replace({
								path: "/recharge/payStatus",
								query: {
									order: t.order,
									code: t.code,
									amountVal: t.amountVal
								}
							}) : e.ret_msg && "" != e.ret_msg && t.$vux.toast.show({
								text: e.ret_msg
							})
						}).catch(function (e) {
							t.$vux.loading.hide(), t.$vux.toast.show({
								text: "Data request timeout"
							})
						})
					}
				},
				watch: {},
				deactivated: function () {
					clearInterval(this.timer)
				},
				beforeDestroy: function () {
					clearInterval(this.timer)
				}
			}),
			l = {
				render: function () {
					var t = this,
						e = t.$createElement,
						r = t._self._c || e;
					return r("div", {
						staticClass: "isHeader"
					}, [r("div", {
						staticClass: "headerWrap"
					}, [r("x-header", {
						staticClass: "header",
						attrs: {
							"left-options": {
								preventGoBack: !0
							},
							title: 0 == t.rechargeData.line ? t.rechargeData.payment_name + "Recharge" : "Offline recharge"
						},
						on: {
							"on-click-back": t.headBack
						}
					})], 1), t._v(" "), r("div", {
						staticClass: "page-content"
					}, [r("scroll", [r("div", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: 0 == t.rechargeData.line,
							expression: "rechargeData.line == 0"
						}]
					}, [r("group", {
						staticClass: "top-group"
					}, [r("div", {
						staticClass: "online-info"
					}, [t.codeImg ? [r("img", {
						staticClass: "code",
						attrs: {
							src: t.codeImg
						},
						nativeOn: {
							click: function (e) {
								t.codeShow = !0
							}
						}
					})] : [r("Qrcode", {
						attrs: {
							size: t.size,
							type: "img",
							value: t.codeUrl
						},
						nativeOn: {
							click: function (e) {
								t.codeShow = !0
							}
						}
					})], t._v(" "), r("h4", [t._v("￥" + t._s(t.amountVal))]), t._v(" "), r("p", {
						staticClass: "text-gray"
					}, [t._v("No need to add friends, scan QR code to pay me")])], 2)]), t._v(" "), r("div", {
						staticClass: "submit-btn"
					}, [r("x-button", {
						staticClass: "weui-btn_radius weui-btn_minRadius",
						attrs: {
							type: "warn",
							"action-type": "button"
						},
						nativeOn: {
							click: function (e) {
								return t.submitOnLine(e)
							}
						}
					}, [t._v("Immediate payment")])], 1), t._v(" "), "ali" == t.rechargeData.channel_type || "wx" == t.rechargeData.channel_type || "qq" == t.rechargeData.channel_type ? r("div", {
						staticClass: "tips"
					}, [r("h4", [t._v("reminder")]), t._v(" "), r("p", [t._v("1.You can take a screenshot of the current screen or long press the QR code to save it, or scan and pay with other mobile phones。")]), t._v(" "), r("p", [t._v("\n                        2.Please at\n                        "), "ali" == t.rechargeData.channel_type ? [t._v("Alipay")] : t._e(), t._v(" "), "wx" == t.rechargeData.channel_type ? [t._v("WeChat")] : t._e(), t._v(" "), "qq" == t.rechargeData.channel_type ? [t._v("QQ")] : t._e(), t._v("\n                        Open scan a scan。\n                    ")], 2), t._v(" "), r("p", [t._v("3.In the scan, click album in the upper right corner, and select the screenshot you just saved from the album。")]), t._v(" "), r("p", [t._v("\n                        4.stay\n                        "), "ali" == t.rechargeData.channel_type ? [t._v("Alipay")] : t._e(), t._v(" "), "wx" == t.rechargeData.channel_type ? [t._v("wechat")] : t._e(), t._v(" "), "qq" == t.rechargeData.channel_type ? [t._v("QQ")] : t._e(), t._v("\n                        After completing the payment, return to this page and click I have paid。\n                    ")], 2), t._v(" "), r("p", [t._v("5.If the recharge fails to arrive in time, please contact online customer service。")])]) : t._e()], 1), t._v(" "), r("div", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: 1 == t.rechargeData.line,
							expression: "rechargeData.line == 1"
						}]
					}, [r("group", {
						staticClass: "top-group"
					}, [r("div", {
						staticClass: "offline-info"
					}, [t.rechargeData.code ? [t.rechargeData.code ? r("img", {
						staticClass: "code",
						attrs: {
							src: t.imgRequest + "/" + t.rechargeData.code
						}
					}) : t._e()] : [t.rechargeData.logo ? r("img", {
						staticClass: "code",
						attrs: {
							src: t.imgRequest + t.rechargeData.logo
						}
					}) : t._e()], t._v(" "), r("h4", [t._v(t._s(t.rechargeData.name)), t.rechargeData.code ? r("span", [t._v("Scan code payment")]) : r("span", [t._v("payment")])]), t._v(" "), r("div", {
						staticClass: "pay-info clearfix"
					}, [r("ul", [r("li", [r("div", {
						staticClass: "li-l"
					}, [t._v(t._s(t.rechargeData.name) + "account number：")]), t._v(" "), r("div", {
						staticClass: "li-r"
					}, [t._v(t._s(t.rechargeData.account)), r("em", {
						staticClass: "icoCopy",
						attrs: {
							"data-clipboard-text": t.rechargeData.account
						},
						on: {
							click: t.copyText
						}
					})])]), t._v(" "), r("li", [r("div", {
						staticClass: "li-l"
					}, [t._v("Account name：")]), t._v(" "), r("div", {
						staticClass: "li-r"
					}, [t._v(t._s(t.rechargeData.account_name)), r("em", {
						staticClass: "icoCopy",
						attrs: {
							"data-clipboard-text": t.rechargeData.account_name
						},
						on: {
							click: t.copyText
						}
					})])])])])], 2), t._v(" "), r("cell", {
						staticClass: "prePaid-currency",
						attrs: {
							title: "Recharge amount",
							"value-align": "left"
						}
					}, [r("i", {
						staticClass: "cell-icon cell-icon-currency",
						attrs: {
							slot: "icon"
						},
						slot: "icon"
					}), t._v(" "), r("input", {
						staticClass: "text-red",
						domProps: {
							value: t.amountVal + " USD"
						}
					})]), t._v(" "), r("cell", {
						staticClass: "prePaid-currency",
						attrs: {
							title: "Additional code",
							"value-align": "left"
						}
					}, [r("i", {
						staticClass: "cell-icon cell-icon-code",
						attrs: {
							slot: "icon"
						},
						slot: "icon"
					}), t._v(" "), r("input", {
						staticClass: "text-red",
						domProps: {
							value: t.code
						}
					}), t._v(" "), r("em", {
						staticClass: "icoCopy",
						attrs: {
							"data-clipboard-text": t.code
						},
						on: {
							click: t.copyText
						}
					})])], 1), t._v(" "), r("div", {
						staticClass: "submit-btn"
					}, [r("x-button", {
						staticClass: "weui-btn_radius weui-btn_minRadius",
						attrs: {
							type: "warn",
							"action-type": "button"
						},
						nativeOn: {
							click: function (e) {
								return t.submitOffLine(e)
							}
						}
					}, [t._v("Click confirm to submit")])], 1)], 1)])], 1), t._v(" "), r("x-dialog", {
						staticClass: "public-dialog code-dialog",
						model: {
							value: t.codeShow,
							callback: function (e) {
								t.codeShow = e
							},
							expression: "codeShow"
						}
					}, [r("div", {
						staticClass: "dialog-content"
					}, [t.codeImg ? [r("img", {
						staticClass: "code",
						attrs: {
							src: t.codeImg,
							width: ""
						}
					})] : [r("Qrcode", {
						attrs: {
							size: t.size,
							type: "img",
							value: t.codeUrl
						}
					})]], 2), t._v(" "), r("span", {
						staticClass: "close-icon",
						on: {
							click: function (e) {
								t.codeShow = !1
							}
						}
					})]), t._v(" "), r("div", {
						directives: [{
							name: "transfer-dom",
							rawName: "v-transfer-dom"
						}]
					}, [r("confirm", {
						attrs: {
							title: "Tips"
						},
						on: {
							"on-confirm": t.onConfirm
						},
						model: {
							value: t.returnPopup,
							callback: function (e) {
								t.returnPopup = e
							},
							expression: "returnPopup"
						}
					}, [t._v("\n            The current unfinished order will be cancelled after exiting. Do you want to continue？\n        ")])], 1)], 1)
				},
				staticRenderFns: []
			};
		var h = r("vSla")(u, l, !1, function (t) {
			r("+x8j")
		}, "data-v-8808a34c", null);
		e.default = h.exports
	},
	"31hx": function (t, e, r) {
		var a = r("UgCK");

		function n(t) {
			this.mode = a.MODE_8BIT_BYTE, this.data = t
		}
		n.prototype = {
			getLength: function (t) {
				return this.data.length
			},
			write: function (t) {
				for (var e = 0; e < this.data.length; e++) t.put(this.data.charCodeAt(e), 8)
			}
		}, t.exports = n
	},
	"3hM3": function (t, e, r) {
		"use strict";
		var a = r("MDSg"),
			n = r.n(a),
			o = r("N4bT"),
			i = r.n(o);
		String, Number, String, String, String, String;
		var s = {
			name: "qrcode",
			props: {
				value: String,
				size: {
					type: Number,
					default: 160
				},
				level: {
					type: String,
					default: "L"
				},
				bgColor: {
					type: String,
					default: "#FFFFFF"
				},
				fgColor: {
					type: String,
					default: "#000000"
				},
				type: {
					type: String,
					default: "img"
				}
			},
			mounted: function () {
				var t = this;
				this.$nextTick(function () {
					t.render()
				})
			},
			data: function () {
				return {
					imgData: ""
				}
			},
			watch: {
				value: function () {
					this.render()
				},
				size: function () {
					this.render()
				},
				level: function () {
					this.render()
				},
				bgColor: function () {
					this.render()
				},
				fgColor: function () {
					this.render()
				}
			},
			methods: {
				render: function () {
					var t = this;
					if (void 0 !== this.value) {
						var e = new n.a(-1, i.a[this.level]);
						e.addData(function (t) {
							var e, r, a, n;
							for (e = "", a = t.length, r = 0; r < a; r++)(n = t.charCodeAt(r)) >= 1 && n <= 127 ? e += t.charAt(r) : n > 2047 ? (e += String.fromCharCode(224 | n >> 12 & 15), e += String.fromCharCode(128 | n >> 6 & 63), e += String.fromCharCode(128 | n >> 0 & 63)) : (e += String.fromCharCode(192 | n >> 6 & 31), e += String.fromCharCode(128 | n >> 0 & 63));
							return e
						}(this.value)), e.make();
						var r = this.$refs.canvas,
							a = r.getContext("2d"),
							o = e.modules,
							s = this.size / o.length,
							c = this.size / o.length,
							u = (window.devicePixelRatio || 1) / function (t) {
								return t.webkitBackingStorePixelRatio || t.mozBackingStorePixelRatio || t.msBackingStorePixelRatio || t.oBackingStorePixelRatio || t.backingStorePixelRatio || 1
							}(a);
						r.height = r.width = this.size * u, a.scale(u, u), o.forEach(function (e, r) {
							e.forEach(function (e, n) {
								a.fillStyle = e ? t.fgColor : t.bgColor;
								var o = Math.ceil((n + 1) * s) - Math.floor(n * s),
									i = Math.ceil((r + 1) * c) - Math.floor(r * c);
								a.fillRect(Math.round(n * s), Math.round(r * c), o, i)
							})
						}), "img" === this.type && (this.imgData = r.toDataURL("image/png"))
					}
				}
			}
		};
		var c = {
				render: function () {
					var t = this,
						e = t.$createElement,
						r = t._self._c || e;
					return r("div", [r("canvas", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: "canvas" === t.type,
							expression: "type === 'canvas'"
						}],
						ref: "canvas",
						style: {
							height: t.size + "px",
							width: t.size + "px"
						},
						attrs: {
							height: t.size,
							width: t.size
						}
					}), t._v(" "), "img" === t.type ? r("img", {
						style: {
							height: t.size + "px",
							width: t.size + "px"
						},
						attrs: {
							src: t.imgData
						}
					}) : t._e()])
				},
				staticRenderFns: []
			},
			u = r("vSla")(s, c, !1, null, null, null);
		e.a = u.exports
	},
	INo5: function (t, e, r) {
		var a = r("eq6M");

		function n(t, e) {
			if (void 0 == t.length) throw new Error(t.length + "/" + e);
			for (var r = 0; r < t.length && 0 == t[r];) r++;
			this.num = new Array(t.length - r + e);
			for (var a = 0; a < t.length - r; a++) this.num[a] = t[a + r]
		}
		n.prototype = {
			get: function (t) {
				return this.num[t]
			},
			getLength: function () {
				return this.num.length
			},
			multiply: function (t) {
				for (var e = new Array(this.getLength() + t.getLength() - 1), r = 0; r < this.getLength(); r++)
					for (var o = 0; o < t.getLength(); o++) e[r + o] ^= a.gexp(a.glog(this.get(r)) + a.glog(t.get(o)));
				return new n(e, 0)
			},
			mod: function (t) {
				if (this.getLength() - t.getLength() < 0) return this;
				for (var e = a.glog(this.get(0)) - a.glog(t.get(0)), r = new Array(this.getLength()), o = 0; o < this.getLength(); o++) r[o] = this.get(o);
				for (o = 0; o < t.getLength(); o++) r[o] ^= a.gexp(a.glog(t.get(o)) + e);
				return new n(r, 0).mod(t)
			}
		}, t.exports = n
	},
	MDSg: function (t, e, r) {
		var a = r("31hx"),
			n = r("c5qG"),
			o = r("ap/J"),
			i = r("praC"),
			s = r("INo5");

		function c(t, e) {
			this.typeNumber = t, this.errorCorrectLevel = e, this.modules = null, this.moduleCount = 0, this.dataCache = null, this.dataList = []
		}
		var u = c.prototype;
		u.addData = function (t) {
			var e = new a(t);
			this.dataList.push(e), this.dataCache = null
		}, u.isDark = function (t, e) {
			if (t < 0 || this.moduleCount <= t || e < 0 || this.moduleCount <= e) throw new Error(t + "," + e);
			return this.modules[t][e]
		}, u.getModuleCount = function () {
			return this.moduleCount
		}, u.make = function () {
			if (this.typeNumber < 1) {
				var t = 1;
				for (t = 1; t < 40; t++) {
					for (var e = n.getRSBlocks(t, this.errorCorrectLevel), r = new o, a = 0, s = 0; s < e.length; s++) a += e[s].dataCount;
					for (s = 0; s < this.dataList.length; s++) {
						var c = this.dataList[s];
						r.put(c.mode, 4), r.put(c.getLength(), i.getLengthInBits(c.mode, t)), c.write(r)
					}
					if (r.getLengthInBits() <= 8 * a) break
				}
				this.typeNumber = t
			}
			this.makeImpl(!1, this.getBestMaskPattern())
		}, u.makeImpl = function (t, e) {
			this.moduleCount = 4 * this.typeNumber + 17, this.modules = new Array(this.moduleCount);
			for (var r = 0; r < this.moduleCount; r++) {
				this.modules[r] = new Array(this.moduleCount);
				for (var a = 0; a < this.moduleCount; a++) this.modules[r][a] = null
			}
			this.setupPositionProbePattern(0, 0), this.setupPositionProbePattern(this.moduleCount - 7, 0), this.setupPositionProbePattern(0, this.moduleCount - 7), this.setupPositionAdjustPattern(), this.setupTimingPattern(), this.setupTypeInfo(t, e), this.typeNumber >= 7 && this.setupTypeNumber(t), null == this.dataCache && (this.dataCache = c.createData(this.typeNumber, this.errorCorrectLevel, this.dataList)), this.mapData(this.dataCache, e)
		}, u.setupPositionProbePattern = function (t, e) {
			for (var r = -1; r <= 7; r++)
				if (!(t + r <= -1 || this.moduleCount <= t + r))
					for (var a = -1; a <= 7; a++) e + a <= -1 || this.moduleCount <= e + a || (this.modules[t + r][e + a] = 0 <= r && r <= 6 && (0 == a || 6 == a) || 0 <= a && a <= 6 && (0 == r || 6 == r) || 2 <= r && r <= 4 && 2 <= a && a <= 4)
		}, u.getBestMaskPattern = function () {
			for (var t = 0, e = 0, r = 0; r < 8; r++) {
				this.makeImpl(!0, r);
				var a = i.getLostPoint(this);
				(0 == r || t > a) && (t = a, e = r)
			}
			return e
		}, u.createMovieClip = function (t, e, r) {
			var a = t.createEmptyMovieClip(e, r);
			this.make();
			for (var n = 0; n < this.modules.length; n++)
				for (var o = 1 * n, i = 0; i < this.modules[n].length; i++) {
					var s = 1 * i;
					this.modules[n][i] && (a.beginFill(0, 100), a.moveTo(s, o), a.lineTo(s + 1, o), a.lineTo(s + 1, o + 1), a.lineTo(s, o + 1), a.endFill())
				}
			return a
		}, u.setupTimingPattern = function () {
			for (var t = 8; t < this.moduleCount - 8; t++) null == this.modules[t][6] && (this.modules[t][6] = t % 2 == 0);
			for (var e = 8; e < this.moduleCount - 8; e++) null == this.modules[6][e] && (this.modules[6][e] = e % 2 == 0)
		}, u.setupPositionAdjustPattern = function () {
			for (var t = i.getPatternPosition(this.typeNumber), e = 0; e < t.length; e++)
				for (var r = 0; r < t.length; r++) {
					var a = t[e],
						n = t[r];
					if (null == this.modules[a][n])
						for (var o = -2; o <= 2; o++)
							for (var s = -2; s <= 2; s++) this.modules[a + o][n + s] = -2 == o || 2 == o || -2 == s || 2 == s || 0 == o && 0 == s
				}
		}, u.setupTypeNumber = function (t) {
			for (var e = i.getBCHTypeNumber(this.typeNumber), r = 0; r < 18; r++) {
				var a = !t && 1 == (e >> r & 1);
				this.modules[Math.floor(r / 3)][r % 3 + this.moduleCount - 8 - 3] = a
			}
			for (r = 0; r < 18; r++) {
				a = !t && 1 == (e >> r & 1);
				this.modules[r % 3 + this.moduleCount - 8 - 3][Math.floor(r / 3)] = a
			}
		}, u.setupTypeInfo = function (t, e) {
			for (var r = this.errorCorrectLevel << 3 | e, a = i.getBCHTypeInfo(r), n = 0; n < 15; n++) {
				var o = !t && 1 == (a >> n & 1);
				n < 6 ? this.modules[n][8] = o : n < 8 ? this.modules[n + 1][8] = o : this.modules[this.moduleCount - 15 + n][8] = o
			}
			for (n = 0; n < 15; n++) {
				o = !t && 1 == (a >> n & 1);
				n < 8 ? this.modules[8][this.moduleCount - n - 1] = o : n < 9 ? this.modules[8][15 - n - 1 + 1] = o : this.modules[8][15 - n - 1] = o
			}
			this.modules[this.moduleCount - 8][8] = !t
		}, u.mapData = function (t, e) {
			for (var r = -1, a = this.moduleCount - 1, n = 7, o = 0, s = this.moduleCount - 1; s > 0; s -= 2)
				for (6 == s && s--;;) {
					for (var c = 0; c < 2; c++)
						if (null == this.modules[a][s - c]) {
							var u = !1;
							o < t.length && (u = 1 == (t[o] >>> n & 1)), i.getMask(e, a, s - c) && (u = !u), this.modules[a][s - c] = u, -1 == --n && (o++, n = 7)
						}
					if ((a += r) < 0 || this.moduleCount <= a) {
						a -= r, r = -r;
						break
					}
				}
		}, c.PAD0 = 236, c.PAD1 = 17, c.createData = function (t, e, r) {
			for (var a = n.getRSBlocks(t, e), s = new o, u = 0; u < r.length; u++) {
				var l = r[u];
				s.put(l.mode, 4), s.put(l.getLength(), i.getLengthInBits(l.mode, t)), l.write(s)
			}
			var h = 0;
			for (u = 0; u < a.length; u++) h += a[u].dataCount;
			if (s.getLengthInBits() > 8 * h) throw new Error("code length overflow. (" + s.getLengthInBits() + ">" + 8 * h + ")");
			for (s.getLengthInBits() + 4 <= 8 * h && s.put(0, 4); s.getLengthInBits() % 8 != 0;) s.putBit(!1);
			for (; !(s.getLengthInBits() >= 8 * h || (s.put(c.PAD0, 8), s.getLengthInBits() >= 8 * h));) s.put(c.PAD1, 8);
			return c.createBytes(s, a)
		}, c.createBytes = function (t, e) {
			for (var r = 0, a = 0, n = 0, o = new Array(e.length), c = new Array(e.length), u = 0; u < e.length; u++) {
				var l = e[u].dataCount,
					h = e[u].totalCount - l;
				a = Math.max(a, l), n = Math.max(n, h), o[u] = new Array(l);
				for (var g = 0; g < o[u].length; g++) o[u][g] = 255 & t.buffer[g + r];
				r += l;
				var d = i.getErrorCorrectPolynomial(h),
					f = new s(o[u], d.getLength() - 1).mod(d);
				c[u] = new Array(d.getLength() - 1);
				for (g = 0; g < c[u].length; g++) {
					var v = g + f.getLength() - c[u].length;
					c[u][g] = v >= 0 ? f.get(v) : 0
				}
			}
			var m = 0;
			for (g = 0; g < e.length; g++) m += e[g].totalCount;
			var p = new Array(m),
				_ = 0;
			for (g = 0; g < a; g++)
				for (u = 0; u < e.length; u++) g < o[u].length && (p[_++] = o[u][g]);
			for (g = 0; g < n; g++)
				for (u = 0; u < e.length; u++) g < c[u].length && (p[_++] = c[u][g]);
			return p
		}, t.exports = c
	},
	N4bT: function (t, e) {
		t.exports = {
			L: 1,
			M: 0,
			Q: 3,
			H: 2
		}
	},
	UgCK: function (t, e) {
		t.exports = {
			MODE_NUMBER: 1,
			MODE_ALPHA_NUM: 2,
			MODE_8BIT_BYTE: 4,
			MODE_KANJI: 8
		}
	},
	"ap/J": function (t, e) {
		function r() {
			this.buffer = new Array, this.length = 0
		}
		r.prototype = {
			get: function (t) {
				var e = Math.floor(t / 8);
				return 1 == (this.buffer[e] >>> 7 - t % 8 & 1)
			},
			put: function (t, e) {
				for (var r = 0; r < e; r++) this.putBit(1 == (t >>> e - r - 1 & 1))
			},
			getLengthInBits: function () {
				return this.length
			},
			putBit: function (t) {
				var e = Math.floor(this.length / 8);
				this.buffer.length <= e && this.buffer.push(0), t && (this.buffer[e] |= 128 >>> this.length % 8), this.length++
			}
		}, t.exports = r
	},
	c5qG: function (t, e, r) {
		var a = r("N4bT");

		function n(t, e) {
			this.totalCount = t, this.dataCount = e
		}
		n.RS_BLOCK_TABLE = [
			[1, 26, 19],
			[1, 26, 16],
			[1, 26, 13],
			[1, 26, 9],
			[1, 44, 34],
			[1, 44, 28],
			[1, 44, 22],
			[1, 44, 16],
			[1, 70, 55],
			[1, 70, 44],
			[2, 35, 17],
			[2, 35, 13],
			[1, 100, 80],
			[2, 50, 32],
			[2, 50, 24],
			[4, 25, 9],
			[1, 134, 108],
			[2, 67, 43],
			[2, 33, 15, 2, 34, 16],
			[2, 33, 11, 2, 34, 12],
			[2, 86, 68],
			[4, 43, 27],
			[4, 43, 19],
			[4, 43, 15],
			[2, 98, 78],
			[4, 49, 31],
			[2, 32, 14, 4, 33, 15],
			[4, 39, 13, 1, 40, 14],
			[2, 121, 97],
			[2, 60, 38, 2, 61, 39],
			[4, 40, 18, 2, 41, 19],
			[4, 40, 14, 2, 41, 15],
			[2, 146, 116],
			[3, 58, 36, 2, 59, 37],
			[4, 36, 16, 4, 37, 17],
			[4, 36, 12, 4, 37, 13],
			[2, 86, 68, 2, 87, 69],
			[4, 69, 43, 1, 70, 44],
			[6, 43, 19, 2, 44, 20],
			[6, 43, 15, 2, 44, 16],
			[4, 101, 81],
			[1, 80, 50, 4, 81, 51],
			[4, 50, 22, 4, 51, 23],
			[3, 36, 12, 8, 37, 13],
			[2, 116, 92, 2, 117, 93],
			[6, 58, 36, 2, 59, 37],
			[4, 46, 20, 6, 47, 21],
			[7, 42, 14, 4, 43, 15],
			[4, 133, 107],
			[8, 59, 37, 1, 60, 38],
			[8, 44, 20, 4, 45, 21],
			[12, 33, 11, 4, 34, 12],
			[3, 145, 115, 1, 146, 116],
			[4, 64, 40, 5, 65, 41],
			[11, 36, 16, 5, 37, 17],
			[11, 36, 12, 5, 37, 13],
			[5, 109, 87, 1, 110, 88],
			[5, 65, 41, 5, 66, 42],
			[5, 54, 24, 7, 55, 25],
			[11, 36, 12],
			[5, 122, 98, 1, 123, 99],
			[7, 73, 45, 3, 74, 46],
			[15, 43, 19, 2, 44, 20],
			[3, 45, 15, 13, 46, 16],
			[1, 135, 107, 5, 136, 108],
			[10, 74, 46, 1, 75, 47],
			[1, 50, 22, 15, 51, 23],
			[2, 42, 14, 17, 43, 15],
			[5, 150, 120, 1, 151, 121],
			[9, 69, 43, 4, 70, 44],
			[17, 50, 22, 1, 51, 23],
			[2, 42, 14, 19, 43, 15],
			[3, 141, 113, 4, 142, 114],
			[3, 70, 44, 11, 71, 45],
			[17, 47, 21, 4, 48, 22],
			[9, 39, 13, 16, 40, 14],
			[3, 135, 107, 5, 136, 108],
			[3, 67, 41, 13, 68, 42],
			[15, 54, 24, 5, 55, 25],
			[15, 43, 15, 10, 44, 16],
			[4, 144, 116, 4, 145, 117],
			[17, 68, 42],
			[17, 50, 22, 6, 51, 23],
			[19, 46, 16, 6, 47, 17],
			[2, 139, 111, 7, 140, 112],
			[17, 74, 46],
			[7, 54, 24, 16, 55, 25],
			[34, 37, 13],
			[4, 151, 121, 5, 152, 122],
			[4, 75, 47, 14, 76, 48],
			[11, 54, 24, 14, 55, 25],
			[16, 45, 15, 14, 46, 16],
			[6, 147, 117, 4, 148, 118],
			[6, 73, 45, 14, 74, 46],
			[11, 54, 24, 16, 55, 25],
			[30, 46, 16, 2, 47, 17],
			[8, 132, 106, 4, 133, 107],
			[8, 75, 47, 13, 76, 48],
			[7, 54, 24, 22, 55, 25],
			[22, 45, 15, 13, 46, 16],
			[10, 142, 114, 2, 143, 115],
			[19, 74, 46, 4, 75, 47],
			[28, 50, 22, 6, 51, 23],
			[33, 46, 16, 4, 47, 17],
			[8, 152, 122, 4, 153, 123],
			[22, 73, 45, 3, 74, 46],
			[8, 53, 23, 26, 54, 24],
			[12, 45, 15, 28, 46, 16],
			[3, 147, 117, 10, 148, 118],
			[3, 73, 45, 23, 74, 46],
			[4, 54, 24, 31, 55, 25],
			[11, 45, 15, 31, 46, 16],
			[7, 146, 116, 7, 147, 117],
			[21, 73, 45, 7, 74, 46],
			[1, 53, 23, 37, 54, 24],
			[19, 45, 15, 26, 46, 16],
			[5, 145, 115, 10, 146, 116],
			[19, 75, 47, 10, 76, 48],
			[15, 54, 24, 25, 55, 25],
			[23, 45, 15, 25, 46, 16],
			[13, 145, 115, 3, 146, 116],
			[2, 74, 46, 29, 75, 47],
			[42, 54, 24, 1, 55, 25],
			[23, 45, 15, 28, 46, 16],
			[17, 145, 115],
			[10, 74, 46, 23, 75, 47],
			[10, 54, 24, 35, 55, 25],
			[19, 45, 15, 35, 46, 16],
			[17, 145, 115, 1, 146, 116],
			[14, 74, 46, 21, 75, 47],
			[29, 54, 24, 19, 55, 25],
			[11, 45, 15, 46, 46, 16],
			[13, 145, 115, 6, 146, 116],
			[14, 74, 46, 23, 75, 47],
			[44, 54, 24, 7, 55, 25],
			[59, 46, 16, 1, 47, 17],
			[12, 151, 121, 7, 152, 122],
			[12, 75, 47, 26, 76, 48],
			[39, 54, 24, 14, 55, 25],
			[22, 45, 15, 41, 46, 16],
			[6, 151, 121, 14, 152, 122],
			[6, 75, 47, 34, 76, 48],
			[46, 54, 24, 10, 55, 25],
			[2, 45, 15, 64, 46, 16],
			[17, 152, 122, 4, 153, 123],
			[29, 74, 46, 14, 75, 47],
			[49, 54, 24, 10, 55, 25],
			[24, 45, 15, 46, 46, 16],
			[4, 152, 122, 18, 153, 123],
			[13, 74, 46, 32, 75, 47],
			[48, 54, 24, 14, 55, 25],
			[42, 45, 15, 32, 46, 16],
			[20, 147, 117, 4, 148, 118],
			[40, 75, 47, 7, 76, 48],
			[43, 54, 24, 22, 55, 25],
			[10, 45, 15, 67, 46, 16],
			[19, 148, 118, 6, 149, 119],
			[18, 75, 47, 31, 76, 48],
			[34, 54, 24, 34, 55, 25],
			[20, 45, 15, 61, 46, 16]
		], n.getRSBlocks = function (t, e) {
			var r = n.getRsBlockTable(t, e);
			if (void 0 == r) throw new Error("bad rs block @ typeNumber:" + t + "/errorCorrectLevel:" + e);
			for (var a = r.length / 3, o = new Array, i = 0; i < a; i++)
				for (var s = r[3 * i + 0], c = r[3 * i + 1], u = r[3 * i + 2], l = 0; l < s; l++) o.push(new n(c, u));
			return o
		}, n.getRsBlockTable = function (t, e) {
			switch (e) {
			case a.L:
				return n.RS_BLOCK_TABLE[4 * (t - 1) + 0];
			case a.M:
				return n.RS_BLOCK_TABLE[4 * (t - 1) + 1];
			case a.Q:
				return n.RS_BLOCK_TABLE[4 * (t - 1) + 2];
			case a.H:
				return n.RS_BLOCK_TABLE[4 * (t - 1) + 3];
			default:
				return
			}
		}, t.exports = n
	},
	eq6M: function (t, e) {
		for (var r = {
			glog: function (t) {
				if (t < 1) throw new Error("glog(" + t + ")");
				return r.LOG_TABLE[t]
			},
			gexp: function (t) {
				for (; t < 0;) t += 255;
				for (; t >= 256;) t -= 255;
				return r.EXP_TABLE[t]
			},
			EXP_TABLE: new Array(256),
			LOG_TABLE: new Array(256)
		}, a = 0; a < 8; a++) r.EXP_TABLE[a] = 1 << a;
		for (a = 8; a < 256; a++) r.EXP_TABLE[a] = r.EXP_TABLE[a - 4] ^ r.EXP_TABLE[a - 5] ^ r.EXP_TABLE[a - 6] ^ r.EXP_TABLE[a - 8];
		for (a = 0; a < 255; a++) r.LOG_TABLE[r.EXP_TABLE[a]] = a;
		t.exports = r
	},
	praC: function (t, e, r) {
		var a = r("UgCK"),
			n = r("INo5"),
			o = r("eq6M"),
			i = 0,
			s = 1,
			c = 2,
			u = 3,
			l = 4,
			h = 5,
			g = 6,
			d = 7,
			f = {
				PATTERN_POSITION_TABLE: [
					[],
					[6, 18],
					[6, 22],
					[6, 26],
					[6, 30],
					[6, 34],
					[6, 22, 38],
					[6, 24, 42],
					[6, 26, 46],
					[6, 28, 50],
					[6, 30, 54],
					[6, 32, 58],
					[6, 34, 62],
					[6, 26, 46, 66],
					[6, 26, 48, 70],
					[6, 26, 50, 74],
					[6, 30, 54, 78],
					[6, 30, 56, 82],
					[6, 30, 58, 86],
					[6, 34, 62, 90],
					[6, 28, 50, 72, 94],
					[6, 26, 50, 74, 98],
					[6, 30, 54, 78, 102],
					[6, 28, 54, 80, 106],
					[6, 32, 58, 84, 110],
					[6, 30, 58, 86, 114],
					[6, 34, 62, 90, 118],
					[6, 26, 50, 74, 98, 122],
					[6, 30, 54, 78, 102, 126],
					[6, 26, 52, 78, 104, 130],
					[6, 30, 56, 82, 108, 134],
					[6, 34, 60, 86, 112, 138],
					[6, 30, 58, 86, 114, 142],
					[6, 34, 62, 90, 118, 146],
					[6, 30, 54, 78, 102, 126, 150],
					[6, 24, 50, 76, 102, 128, 154],
					[6, 28, 54, 80, 106, 132, 158],
					[6, 32, 58, 84, 110, 136, 162],
					[6, 26, 54, 82, 110, 138, 166],
					[6, 30, 58, 86, 114, 142, 170]
				],
				G15: 1335,
				G18: 7973,
				G15_MASK: 21522,
				getBCHTypeInfo: function (t) {
					for (var e = t << 10; f.getBCHDigit(e) - f.getBCHDigit(f.G15) >= 0;) e ^= f.G15 << f.getBCHDigit(e) - f.getBCHDigit(f.G15);
					return (t << 10 | e) ^ f.G15_MASK
				},
				getBCHTypeNumber: function (t) {
					for (var e = t << 12; f.getBCHDigit(e) - f.getBCHDigit(f.G18) >= 0;) e ^= f.G18 << f.getBCHDigit(e) - f.getBCHDigit(f.G18);
					return t << 12 | e
				},
				getBCHDigit: function (t) {
					for (var e = 0; 0 != t;) e++, t >>>= 1;
					return e
				},
				getPatternPosition: function (t) {
					return f.PATTERN_POSITION_TABLE[t - 1]
				},
				getMask: function (t, e, r) {
					switch (t) {
					case i:
						return (e + r) % 2 == 0;
					case s:
						return e % 2 == 0;
					case c:
						return r % 3 == 0;
					case u:
						return (e + r) % 3 == 0;
					case l:
						return (Math.floor(e / 2) + Math.floor(r / 3)) % 2 == 0;
					case h:
						return e * r % 2 + e * r % 3 == 0;
					case g:
						return (e * r % 2 + e * r % 3) % 2 == 0;
					case d:
						return (e * r % 3 + (e + r) % 2) % 2 == 0;
					default:
						throw new Error("bad maskPattern:" + t)
					}
				},
				getErrorCorrectPolynomial: function (t) {
					for (var e = new n([1], 0), r = 0; r < t; r++) e = e.multiply(new n([1, o.gexp(r)], 0));
					return e
				},
				getLengthInBits: function (t, e) {
					if (1 <= e && e < 10) switch (t) {
					case a.MODE_NUMBER:
						return 10;
					case a.MODE_ALPHA_NUM:
						return 9;
					case a.MODE_8BIT_BYTE:
					case a.MODE_KANJI:
						return 8;
					default:
						throw new Error("mode:" + t)
					} else if (e < 27) switch (t) {
					case a.MODE_NUMBER:
						return 12;
					case a.MODE_ALPHA_NUM:
						return 11;
					case a.MODE_8BIT_BYTE:
						return 16;
					case a.MODE_KANJI:
						return 10;
					default:
						throw new Error("mode:" + t)
					} else {
						if (!(e < 41)) throw new Error("type:" + e);
						switch (t) {
						case a.MODE_NUMBER:
							return 14;
						case a.MODE_ALPHA_NUM:
							return 13;
						case a.MODE_8BIT_BYTE:
							return 16;
						case a.MODE_KANJI:
							return 12;
						default:
							throw new Error("mode:" + t)
						}
					}
				},
				getLostPoint: function (t) {
					for (var e = t.getModuleCount(), r = 0, a = 0; a < e; a++)
						for (var n = 0; n < e; n++) {
							for (var o = 0, i = t.isDark(a, n), s = -1; s <= 1; s++)
								if (!(a + s < 0 || e <= a + s))
									for (var c = -1; c <= 1; c++) n + c < 0 || e <= n + c || 0 == s && 0 == c || i == t.isDark(a + s, n + c) && o++;
							o > 5 && (r += 3 + o - 5)
						}
					for (a = 0; a < e - 1; a++)
						for (n = 0; n < e - 1; n++) {
							var u = 0;
							t.isDark(a, n) && u++, t.isDark(a + 1, n) && u++, t.isDark(a, n + 1) && u++, t.isDark(a + 1, n + 1) && u++, 0 != u && 4 != u || (r += 3)
						}
					for (a = 0; a < e; a++)
						for (n = 0; n < e - 6; n++) t.isDark(a, n) && !t.isDark(a, n + 1) && t.isDark(a, n + 2) && t.isDark(a, n + 3) && t.isDark(a, n + 4) && !t.isDark(a, n + 5) && t.isDark(a, n + 6) && (r += 40);
					for (n = 0; n < e; n++)
						for (a = 0; a < e - 6; a++) t.isDark(a, n) && !t.isDark(a + 1, n) && t.isDark(a + 2, n) && t.isDark(a + 3, n) && t.isDark(a + 4, n) && !t.isDark(a + 5, n) && t.isDark(a + 6, n) && (r += 40);
					var l = 0;
					for (n = 0; n < e; n++)
						for (a = 0; a < e; a++) t.isDark(a, n) && l++;
					return r += 10 * (Math.abs(100 * l / e / e - 50) / 5)
				}
			};
		t.exports = f
	}
});
//# sourceMappingURL=7.9be6d474401e2e98be40.js.map