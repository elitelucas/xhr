webpackJsonp([13], {
	D9i6: function (e, a, n) {
		"use strict";
		Object.defineProperty(a, "__esModule", {
			value: !0
		});
		var t = n("3cXf"),
			r = n.n(t),
			u = n("4YfN"),
			l = n.n(u),
			m = n("9rMa"),
			v = n("o+C2"),
			p = n("JRhT"),
			i = n.n(p),
			s = n("qZvt"),
			o = n.n(s),
			h = {
				"北京市": "110100",
				"天津市": "120100",
				"上海市": "310100",
				"重庆市": "500100"
			},
			c = function (e, a) {
				var n = i()(e, function (n, t) {
					var r = "";
					return 2 === t ? (r = o()(a, function (a) {
						return a.name === e[1]
					}) || {
						value: "__"
					}, h[e[0]] && (r = {
						value: h[e[0]]
					}), o()(a, function (e) {
						return e.name === n && e.parent === r.value
					})) : 1 === t && h[e[0]] ? {
						value: h[e[0]]
					} : o()(a, function (e) {
						return e.name === n
					})
				});
				return i()(n, function (e) {
					return e ? e.value : "__"
				}).join(" ")
			},
			d = n("CZ5u"),
			f = (v.a, String, Array, Boolean, Array, String, String, String, Boolean, String, String, String, Function, Object, String, Boolean, Boolean, {
				name: "x-address",
				components: {
					PopupPicker: v.a
				},
				props: {
					title: {
						type: String,
						required: !0
					},
					value: {
						type: Array,
						default: function () {
							return []
						}
					},
					rawValue: Boolean,
					list: {
						type: Array,
						required: !0
					},
					labelWidth: String,
					inlineDesc: String,
					placeholder: String,
					hideDistrict: Boolean,
					valueTextAlign: String,
					confirmText: String,
					cancelText: String,
					displayFormat: {
						type: Function,
						default: function (e, a) {
							return a
						}
					},
					popupStyle: Object,
					popupTitle: String,
					show: Boolean,
					disabled: Boolean
				},
				created: function () {
					if (this.currentValue.length && this.rawValue) {
						var e = c(this.currentValue, this.list);
						/__/.test(e) ? (console.error("[VUX] Wrong address value", this.currentValue), this.currentValue = []) : this.currentValue = e.split(" ")
					}
					this.show && (this.showValue = !0)
				},
				methods: {
					emitHide: function (e) {
						this.$emit("on-hide", e)
					},
					getAddressName: function () {
						return Object(d.a)(this.value, this.list)
					},
					onShadowChange: function (e, a) {
						this.$emit("on-shadow-change", e, a)
					}
				},
				data: function () {
					return {
						currentValue: this.value,
						showValue: !1
					}
				},
				computed: {
					nameValue: function () {
						return Object(d.a)(this.currentValue, this.list)
					},
					labelClass: function () {
						return {
							"vux-cell-justify": "justify" === this.$parent.labelAlign || "justify" === this.$parent.$parent.labelAlign
						}
					}
				},
				watch: {
					currentValue: function (e) {
						this.$emit("input", e)
					},
					value: function (e) {
						if (e.length && !/\d+/.test(e[0])) {
							var a = c(e, this.list).split(" ");
							if ("__" !== a[0] && "__" !== a[1]) return void(this.currentValue = a)
						}
						this.currentValue = e
					},
					show: function (e) {
						this.showValue = e
					},
					showValue: function (e) {
						this.$emit("update:show", e)
					}
				}
			}),
			x = {
				render: function () {
					var e = this,
						a = e.$createElement,
						n = e._self._c || a;
					return n("popup-picker", {
						attrs: {
							"fixed-columns": e.hideDistrict ? 2 : 0,
							columns: 3,
							data: e.list,
							title: e.title,
							"show-name": "",
							"inline-desc": e.inlineDesc,
							placeholder: e.placeholder,
							"value-text-align": e.valueTextAlign,
							"confirm-text": e.confirmText,
							"cancel-text": e.cancelText,
							"display-format": e.displayFormat,
							"popup-style": e.popupStyle,
							"popup-title": e.popupTitle,
							show: e.showValue,
							disabled: e.disabled
						},
						on: {
							"update:show": function (a) {
								e.showValue = a
							},
							"on-shadow-change": e.onShadowChange,
							"on-hide": e.emitHide,
							"on-show": function (a) {
								e.$emit("on-show")
							}
						},
						scopedSlots: e._u([{
							key: "title",
							fn: function (a) {
								return [e._t("title", [a.labelTitle ? n("label", {
									class: [a.labelClass, e.labelClass],
									style: a.labelStyle,
									domProps: {
										innerHTML: e._s(a.labelTitle)
									}
								}) : e._e()], {
									labelClass: a.labelClass,
									labelStyle: a.labelStyles,
									labelTitle: a.title
								})]
							}
						}]),
						model: {
							value: e.currentValue,
							callback: function (a) {
								e.currentValue = a
							},
							expression: "currentValue"
						}
					})
				},
				staticRenderFns: []
			},
			b = n("vSla")(f, x, !1, null, null, null).exports,
			w = n("G022"),
			k = n.n(w),
			_ = (v.a, l()({}, Object(m.c)(["userInfo", "bankedData", "accountData"])), l()({}, Object(m.b)(["SET_USER_INFO_DATA", "SET_BANKED_DATA", "SET_ACCOUNT_DATA"]), {
				onConfirm: function () {
					return 1 == this.bank_id ? (this.bankAjax(), !1) : 2 == this.bank_id ? (this.alipayAjax(), !1) : 3 == this.bank_id ? (this.weChatAjax(), !1) : void 0
				},
				_getissuingBankList: function () {
					var e = this;
					this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=bank&a=getConfigBanks").then(function (a) {
						e.$vux.loading.hide(), e.bankListData = a.list;
						var n = [];
						for (var t in e.bankListData) n.push(e.bankListData[t].name);
						e.issuingBankList.push(n)
					}).catch(function (a) {
						e.$vux.loading.hide(), e.$vux.toast.show({
							text: "Data request timeout"
						})
					})
				},
				getUserIsBindCard: function () {
					var e = this,
						a = {
							token: localStorage.getItem("token")
						},
						n = this;
					n.$vux.loading.show(), n.$http.post(n.urlRequest + "?m=api&c=bank&a=getUserBank", a).then(function (e) {
						n.$vux.loading.hide(), n.userBankedData = e, e.name && (n.UnionName = e.name, n.AliUserName = e.name, n.wxUserName = e.name), localStorage.setItem("bankedData", r()(e)), n.SET_BANKED_DATA({
							obj: e
						})
					}).catch(function (a) {
						e.$vux.loading.hide(), e.$vux.toast.show({
							text: "Data request timeout"
						})
					})
				},
				bindUnion: function () {
					var e = this;
					return console.log("this.UnionCardNo", this.UnionCardNo), this.UnionName ? this.UnionCardNo ? /^([1-9]{1})(\d+)$/.test(this.UnionCardNo) ? "" == this.issuingBankValue ? (this.$vux.toast.show({
						text: "Issuing bank cannot be blank"
					}), !1) : "" == this.UnionSubBranch ? (this.$vux.toast.show({
						text: "Branch bank cannot be empty"
					}), !1) : (this.bankListData.forEach(function (a) {
						var n = e;
						a.name == n.issuingBankValue && (n.issuingBankVal = a.id)
					}), this.UnionSubBranch ? this.UnionMobile && !/^[0-9]+$/.test(this.UnionMobile) ? (this.$refs.UnionMobile.focus(), this.$vux.toast.show({
						text: "Wrong format of mobile phone number input！"
					}), !1) : (this.bankParams = {
						token: localStorage.getItem("token"),
						name: this.UnionName,
						account: this.UnionCardNo,
						bank: this.issuingBankVal,
						branch: this.UnionSubBranch,
						mobile: this.UnionMobile
					}, console.log(this.bankParams), void("" == this.userInfo.realname ? this.bankPopup = !0 : this.bankAjax())) : (this.$refs.UnionSubBranch.focus(), this.$vux.toast.show({
						text: "Detailed branch name cannot be blank"
					}), !1)) : (this.$refs.UnionCardNoRef.focus(), this.$vux.toast.show({
						text: "Please input 16-19 digit card number"
					}), !1) : (this.$refs.UnionCardNoRef.focus(), this.$vux.toast.show({
						text: "Bank card number cannot be empty"
					}), !1) : (this.$refs.UnionNameRef.focus(), this.$vux.toast.show({
						text: "Cardholder cannot be empty"
					}), !1)
				},
				bankAjax: function () {
					var e = this;
					this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=bank&a=bank", this.bankParams).then(function (a) {
						e.$vux.loading.hide(), 0 == a.status ? (e.userAccounData.is_banded_bank = "1", e.newUserInfo.realname && (e.newUserInfo.realname = e.UnionName, e.SET_USER_INFO_DATA({
							Obj: e.newUserInfo
						})), e.SET_ACCOUNT_DATA({
							Obj: e.userAccounData
						}), e.getUserIsBindCard(), e.$vux.toast.show({
							text: "Bank card bound successfully！"
						}), e.$router.push({
							path: "/bank/bindOK",
							query: {
								bank_id: 1,
								bank: e.issuingBankValue,
								account: e.UnionCardNo
							}
						})) : a.ret_msg && "" != a.ret_msg && e.$vux.toast.show({
							text: a.ret_msg
						})
					}).catch(function (a) {
						e.$vux.loading.hide(), e.$vux.toast.show({
							text: "Data request timeout"
						})
					})
				},
				bindAlipay: function () {
					return this.AliAcc ? this.AliNickName ? this.AliUserName ? this.AliMobile ? /^[0-9]+$/.test(this.AliMobile) ? (this.alipayParams = {
						token: localStorage.getItem("token"),
						account: this.AliAcc,
						nickname: this.AliNickName,
						name: this.AliUserName,
						mobile: this.AliMobile,
						flag_b: 2
					}, console.log(this.alipayParams), void("" == this.userInfo.realname ? this.bankPopup = !0 : this.alipayAjax())) : (this.$refs.AliMobile.focus(), this.$vux.toast.show({
						text: "Wrong format of mobile phone number input！"
					}), !1) : (this.$refs.AliMobile.focus(), this.$vux.toast.show({
						text: "Mobile phone cannot be empty"
					}), !1) : (this.$refs.AliUserName.focus(), this.$vux.toast.show({
						text: "Real name cannot be empty"
					}), !1) : (this.$refs.AliNickName.focus(), this.$vux.toast.show({
						text: "Nickname cannot be empty"
					}), !1) : (this.$refs.AliAcc.focus(), this.$vux.toast.show({
						text: "Alipay cannot be empty"
					}), !1)
				},
				alipayAjax: function () {
					var e = this;
					this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=bank&a=bindWeChatAndAlipay", this.alipayParams).then(function (a) {
						e.$vux.loading.hide(), 0 == a.status ? (e.userAccounData.is_banded_bank = "1", e.newUserInfo.realname && (e.newUserInfo.realname = e.UnionName, e.SET_USER_INFO_DATA({
							Obj: e.newUserInfo
						})), e.SET_ACCOUNT_DATA({
							Obj: e.userAccounData
						}), e.getUserIsBindCard(), e.$vux.toast.show({
							text: "Successfully bind Alipay!"
						}), e.$router.push({
							path: "/bank/bindOK"
						})) : a.ret_msg && "" != a.ret_msg && e.$vux.toast.show({
							text: a.ret_msg
						})
					}).catch(function (a) {
						e.$vux.loading.hide(), e.$vux.toast.show({
							text: "Data request timeout"
						})
					})
				},
				bindWeChat: function () {
					return this.wxAcc ? this.wxNickName ? this.wxUserName ? this.wxMobile ? /^[0-9]+$/.test(this.wxMobile) ? (this.weChatparams = {
						token: localStorage.getItem("token"),
						account: this.wxAcc,
						nickname: this.wxNickName,
						name: this.wxUserName,
						mobile: this.wxMobile,
						flag_b: 1
					}, console.log(this.weChatparams), void("" == this.userInfo.realname ? this.bankPopup = !0 : this.weChatAjax())) : (this.$refs.wxMobile.focus(), this.$vux.toast.show({
						text: "Wrong format of mobile phone number input！"
					}), !1) : (this.$refs.wxMobile.focus(), this.$vux.toast.show({
						text: "Mobile phone cannot be empty"
					}), !1) : (this.$refs.wxUserName.focus(), this.$vux.toast.show({
						text: "Real name cannot be empty"
					}), !1) : (this.$refs.wxNickName.focus(), this.$vux.toast.show({
						text: "Nickname cannot be empty"
					}), !1) : (this.$refs.wxAcc.focus(), this.$vux.toast.show({
						text: "WeChat cannot be empty"
					}), !1)
				},
				weChatAjax: function () {
					var e = this;
					this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=bank&a=bindWeChatAndAlipay", this.weChatparams).then(function (a) {
						e.$vux.loading.hide(), 0 == a.status ? (e.userAccounData.is_banded_bank = "1", e.newUserInfo.realname && (e.newUserInfo.realname = e.UnionName, e.SET_USER_INFO_DATA({
							Obj: e.newUserInfo
						})), e.SET_ACCOUNT_DATA({
							Obj: e.userAccounData
						}), e.getUserIsBindCard(), e.$vux.toast.show({
							text: "Successfully bind WeChat!"
						}), e.$router.push({
							path: "/bank/bindOK"
						})) : a.ret_msg && "" != a.ret_msg && e.$vux.toast.show({
							text: a.ret_msg
						})
					}).catch(function (a) {
						e.$vux.loading.hide(), e.$vux.toast.show({
							text: "Data request timeout"
						})
					})
				}
			}), {
				components: {
					PopupPicker: v.a,
					XAddress: b
				},
				data: function () {
					return {
						bankPopup: !1,
						bank_id: null,
						customBankName: name,
						UnionPayShow: !1,
						AlipayShow: !1,
						WeChatShow: !1,
						userBankedData: {},
						UnionName: "",
						UnionCardNo: "",
						UnionSubBranch: "",
						UnionMobile: "",
						verifyUnionCardNo: function (e) {
							return {
								valid: /^([1-9]{1})(\d+)$/.test(e),
								msg: "Please enter digits for card number"
							}
						},
						verifyUnionMobile: function (e) {
							return {
								valid: /^[0-9]+$/.test(e),
								msg: "Wrong format of mobile phone number input！"
							}
						},
						AliAcc: "",
						AliNickName: "",
						AliUserName: "",
						AliMobile: "",
						verifyAliAcc: function (e) {
							return {
								valid: /^[a-zA-Z0-9_-]{3,30}$/.test(e),
								msg: "The input Alipay format is wrong!"
							}
						},
						verifyAliMobile: function (e) {
							return {
								valid: /^[0-9]+$/.test(e),
								msg: "Wrong format of mobile phone number input！"
							}
						},
						wxAcc: "",
						wxNickName: "",
						wxUserName: "",
						wxMobile: "",
						verifywxAcc: function (e) {
							return {
								valid: /^[a-zA-Z0-9_-]{3,30}$/.test(e),
								msg: "The input WeChat format is wrong!"
							}
						},
						verifywxMobile: function (e) {
							return {
								valid: /^[0-9]+$/.test(e),
								msg: "Wrong format of mobile phone number input！"
							}
						},
						issuingBankValue: [],
						issuingBankList: [],
						addressData: k.a,
						addressValue: [],
						bankListData: []
					}
				},
				created: function () {
					this.getUserIsBindCard(), this.userAccounData = this.accountData, this.newUserInfo = this.userInfo, this._getissuingBankList()
				},
				mounted: function () {
					return this.bank_id = this.$route.query.bank_id, 1 == this.bank_id ? (this.UnionPayShow = !0, this.customBankName = "银行卡", !1) : 2 == this.bank_id ? (this.AlipayShow = !0, this.customBankName = "支付宝", !1) : 3 == this.bank_id ? (this.WeChatShow = !0, this.customBankName = "微信", !1) : void 0
				},
				computed: l()({}, Object(m.c)(["userInfo", "bankedData", "accountData"])),
				methods: l()({}, Object(m.b)(["SET_USER_INFO_DATA", "SET_BANKED_DATA", "SET_ACCOUNT_DATA"]), {
					onConfirm: function () {
						return 1 == this.bank_id ? (this.bankAjax(), !1) : 2 == this.bank_id ? (this.alipayAjax(), !1) : 3 == this.bank_id ? (this.weChatAjax(), !1) : void 0
					},
					_getissuingBankList: function () {
						var e = this;
						this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=bank&a=getConfigBanks").then(function (a) {
							e.$vux.loading.hide(), e.bankListData = a.list;
							var n = [];
							for (var t in e.bankListData) n.push(e.bankListData[t].name);
							e.issuingBankList.push(n)
						}).catch(function (a) {
							e.$vux.loading.hide(), e.$vux.toast.show({
								text: "Data request timeout"
							})
						})
					},
					getUserIsBindCard: function () {
						var e = this,
							a = {
								token: localStorage.getItem("token")
							},
							n = this;
						n.$vux.loading.show(), n.$http.post(n.urlRequest + "?m=api&c=bank&a=getUserBank", a).then(function (e) {
							n.$vux.loading.hide(), n.userBankedData = e, e.name && (n.UnionName = e.name, n.AliUserName = e.name, n.wxUserName = e.name), localStorage.setItem("bankedData", r()(e)), n.SET_BANKED_DATA({
								obj: e
							})
						}).catch(function (a) {
							e.$vux.loading.hide(), e.$vux.toast.show({
								text: "Data request timeout"
							})
						})
					},
					bindUnion: function () {
						var e = this;
						return console.log("this.UnionCardNo", this.UnionCardNo), this.UnionName ? this.UnionCardNo ? /^([1-9]{1})(\d+)$/.test(this.UnionCardNo) ? "" == this.issuingBankValue ? (this.$vux.toast.show({
							text: "Issuing bank cannot be blank"
						}), !1) : "" == this.UnionSubBranch ? (this.$vux.toast.show({
							text: "Branch bank cannot be empty"
						}), !1) : (this.bankListData.forEach(function (a) {
							var n = e;
							a.name == n.issuingBankValue && (n.issuingBankVal = a.id)
						}), this.UnionSubBranch ? this.UnionMobile && !/^[0-9]+$/.test(this.UnionMobile) ? (this.$refs.UnionMobile.focus(), this.$vux.toast.show({
							text: "Wrong format of mobile phone number input！"
						}), !1) : (this.bankParams = {
							token: localStorage.getItem("token"),
							name: this.UnionName,
							account: this.UnionCardNo,
							bank: this.issuingBankVal,
							branch: this.UnionSubBranch,
							mobile: this.UnionMobile
						}, console.log(this.bankParams), void("" == this.userInfo.realname ? this.bankPopup = !0 : this.bankAjax())) : (this.$refs.UnionSubBranch.focus(), this.$vux.toast.show({
							text: "Bank branch name cannot be blank"
						}), !1)) : (this.$refs.UnionCardNoRef.focus(), this.$vux.toast.show({
							text: "Please input 16-19 digit card number"
						}), !1) : (this.$refs.UnionCardNoRef.focus(), this.$vux.toast.show({
							text: "Bank card number cannot be empty"
						}), !1) : (this.$refs.UnionNameRef.focus(), this.$vux.toast.show({
							text: "Cardholder cannot be empty"
						}), !1)
					},
					bankAjax: function () {
						var e = this;
						this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=bank&a=bank", this.bankParams).then(function (a) {
							e.$vux.loading.hide(), 0 == a.status ? (e.userAccounData.is_banded_bank = "1", e.newUserInfo.realname && (e.newUserInfo.realname = e.UnionName, e.SET_USER_INFO_DATA({
								Obj: e.newUserInfo
							})), e.SET_ACCOUNT_DATA({
								Obj: e.userAccounData
							}), e.getUserIsBindCard(), e.$vux.toast.show({
								text: "Bank card bound successfully！"
							}), e.$router.push({
								path: "/bank/bindOK",
								query: {
									bank_id: 1,
									bank: e.issuingBankValue,
									account: e.UnionCardNo
								}
							})) : a.ret_msg && "" != a.ret_msg && e.$vux.toast.show({
								text: a.ret_msg
							})
						}).catch(function (a) {
							e.$vux.loading.hide(), e.$vux.toast.show({
								text: "Data request timeout"
							})
						})
					},
					bindAlipay: function () {
						return this.AliAcc ? this.AliNickName ? this.AliUserName ? this.AliMobile ? /^[0-9]+$/.test(this.AliMobile) ? (this.alipayParams = {
							token: localStorage.getItem("token"),
							account: this.AliAcc,
							nickname: this.AliNickName,
							name: this.AliUserName,
							mobile: this.AliMobile,
							flag_b: 2
						}, console.log(this.alipayParams), void("" == this.userInfo.realname ? this.bankPopup = !0 : this.alipayAjax())) : (this.$refs.AliMobile.focus(), this.$vux.toast.show({
							text: "Wrong format of mobile phone number input！"
						}), !1) : (this.$refs.AliMobile.focus(), this.$vux.toast.show({
							text: "Mobile phone cannot be empty"
						}), !1) : (this.$refs.AliUserName.focus(), this.$vux.toast.show({
							text: "Real name cannot be empty"
						}), !1) : (this.$refs.AliNickName.focus(), this.$vux.toast.show({
							text: "Nickname cannot be empty"
						}), !1) : (this.$refs.AliAcc.focus(), this.$vux.toast.show({
							text: "Alipay cannot be empty"
						}), !1)
					},
					alipayAjax: function () {
						var e = this;
						this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=bank&a=bindWeChatAndAlipay", this.alipayParams).then(function (a) {
							e.$vux.loading.hide(), 0 == a.status ? (e.userAccounData.is_banded_bank = "1", e.newUserInfo.realname && (e.newUserInfo.realname = e.UnionName, e.SET_USER_INFO_DATA({
								Obj: e.newUserInfo
							})), e.SET_ACCOUNT_DATA({
								Obj: e.userAccounData
							}), e.getUserIsBindCard(), e.$vux.toast.show({
								text: "Successfully bind Alipay!"
							}), e.$router.push({
								path: "/bank/bindOK"
							})) : a.ret_msg && "" != a.ret_msg && e.$vux.toast.show({
								text: a.ret_msg
							})
						}).catch(function (a) {
							e.$vux.loading.hide(), e.$vux.toast.show({
								text: "Data request timeout"
							})
						})
					},
					bindWeChat: function () {
						return this.wxAcc ? this.wxNickName ? this.wxUserName ? this.wxMobile ? /^[0-9]+$/.test(this.wxMobile) ? (this.weChatparams = {
							token: localStorage.getItem("token"),
							account: this.wxAcc,
							nickname: this.wxNickName,
							name: this.wxUserName,
							mobile: this.wxMobile,
							flag_b: 1
						}, console.log(this.weChatparams), void("" == this.userInfo.realname ? this.bankPopup = !0 : this.weChatAjax())) : (this.$refs.wxMobile.focus(), this.$vux.toast.show({
							text: "Wrong format of mobile phone number input！"
						}), !1) : (this.$refs.wxMobile.focus(), this.$vux.toast.show({
							text: "Mobile phone cannot be empty"
						}), !1) : (this.$refs.wxUserName.focus(), this.$vux.toast.show({
							text: "Real name cannot be empty"
						}), !1) : (this.$refs.wxNickName.focus(), this.$vux.toast.show({
							text: "Nickname cannot be empty"
						}), !1) : (this.$refs.wxAcc.focus(), this.$vux.toast.show({
							text: "WeChat cannot be empty"
						}), !1)
					},
					weChatAjax: function () {
						var e = this;
						this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=bank&a=bindWeChatAndAlipay", this.weChatparams).then(function (a) {
							e.$vux.loading.hide(), 0 == a.status ? (e.userAccounData.is_banded_bank = "1", e.newUserInfo.realname && (e.newUserInfo.realname = e.UnionName, e.SET_USER_INFO_DATA({
								Obj: e.newUserInfo
							})), e.SET_ACCOUNT_DATA({
								Obj: e.userAccounData
							}), e.getUserIsBindCard(), e.$vux.toast.show({
								text: "Successfully bind WeChat"
							}), e.$router.push({
								path: "/bank/bindOK"
							})) : a.ret_msg && "" != a.ret_msg && e.$vux.toast.show({
								text: a.ret_msg
							})
						}).catch(function (a) {
							e.$vux.loading.hide(), e.$vux.toast.show({
								text: "Data request timeout"
							})
						})
					}
				}),
				watch: {
					userInfo: function (e) {
						e.realname && (this.UnionName = e.realname, this.newUserInfo = e)
					},
					accountData: function (e) {
						e.user_id && (this.userAccounData = e)
					},
					issuingBankValue: function (e) {
						for (var a in this.bankListData) this.bankListData[a].name == e && (this.issuingBankVal = this.bankListData[a])
					}
				}
			}),
			A = {
				render: function () {
					var e = this,
						a = e.$createElement,
						n = e._self._c || a;
					return n("div", [n("div", {
						staticClass: "headerWrap"
					}, [n("x-header", {
						staticClass: "header",
						attrs: {
							title: "Binding bank card"
						}
					})], 1), e._v(" "), n("div", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: this.UnionPayShow,
							expression: "this.UnionPayShow"
						}],
						staticClass: "weui-cell-hd-four"
					}, [n("group", [n("x-input", {
						ref: "UnionNameRef",
						attrs: {
							title: "Cardholder",
							placeholder: "Please enter the name of the cardholder",
							required: "",
							disabled: !!e.userBankedData.name
						},
						model: {
							value: e.UnionName,
							callback: function (a) {
								e.UnionName = a
							},
							expression: "UnionName"
						}
					}), e._v(" "), n("x-input", {
						ref: "UnionCardNoRef",
						attrs: {
							title: "Bank card number",
							placeholder: "Please input bank card number",
							required: "",
							max: 19,
							"is-type": e.verifyUnionCardNo
						},
						model: {
							value: e.UnionCardNo,
							callback: function (a) {
								e.UnionCardNo = a
							},
							expression: "UnionCardNo"
						}
					})], 1), e._v(" "), e._m(0), e._v(" "), n("group", {
						staticClass: "weui-cells-mt"
					}, [n("x-input", {
						ref: "issuingBankValue",
						attrs: {
							title: "Issuing bank",
							placeholder: "Please enter bank name",
							required: ""
						},
						model: {
							value: e.issuingBankValue,
							callback: function (a) {
								e.issuingBankValue = a
							},
							expression: "issuingBankValue"
						}
					})], 1), e._v(" "), e._m(1), e._v(" "), n("group", {
						staticClass: "weui-cells-mt"
					}, [n("x-input", {
						ref: "UnionSubBranch",
						attrs: {
							title: "bank branch",
							placeholder: "Please enter detailed branch name",
							required: ""
						},
						model: {
							value: e.UnionSubBranch,
							callback: function (a) {
								e.UnionSubBranch = a
							},
							expression: "UnionSubBranch"
						}
					})], 1), e._v(" "), e._m(2), e._v(" "), n("group", {
						staticClass: "weui-cells-mt"
					}, [n("x-input", {
						ref: "UnionMobile",
						attrs: {
							title: "phone number",
							placeholder: "Please enter your mobile phone number",
							max: 15,
							"is-type": e.verifyUnionMobile
						},
						model: {
							value: e.UnionMobile,
							callback: function (a) {
								e.UnionMobile = a
							},
							expression: "UnionMobile"
						}
					})], 1), e._v(" "), n("p", {
						staticClass: "tips"
					}, [e._v("Information encryption processing, only for bank verification")]), e._v(" "), n("div", {
						staticClass: "submit-btn"
					}, [n("x-button", {
						staticClass: "weui-btn_radius weui-btn_minRadius",
						attrs: {
							type: "warn",
							"action-type": "button"
						},
						nativeOn: {
							click: function (a) {
								return e.bindUnion(a)
							}
						}
					}, [e._v("ok")])], 1)], 1), e._v(" "), n("div", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: this.AlipayShow,
							expression: "this.AlipayShow"
						}]
					}, [e._m(3), e._v(" "), e._m(4), e._v(" "), n("group", {
						staticClass: "weui-cells-mt"
					}, [n("x-input", {
						ref: "AliAcc",
						attrs: {
							title: "支付宝账号",
							placeholder: "请输入支付宝账号",
							required: ""
						},
						model: {
							value: e.AliAcc,
							callback: function (a) {
								e.AliAcc = a
							},
							expression: "AliAcc"
						}
					}), e._v(" "), n("x-input", {
						ref: "AliNickName",
						attrs: {
							title: "用户昵称",
							placeholder: "请输入用户昵称",
							required: ""
						},
						model: {
							value: e.AliNickName,
							callback: function (a) {
								e.AliNickName = a
							},
							expression: "AliNickName"
						}
					}), e._v(" "), n("x-input", {
						ref: "AliUserName",
						attrs: {
							title: "真实姓名",
							placeholder: "请输入真实姓名",
							required: "",
							disabled: !!e.userBankedData.name
						},
						model: {
							value: e.AliUserName,
							callback: function (a) {
								e.AliUserName = a
							},
							expression: "AliUserName"
						}
					}), e._v(" "), n("x-input", {
						ref: "AliMobile",
						attrs: {
							title: "phone number",
							placeholder: "Please enter your mobile phone number",
							required: "",
							max: 15,
							"is-type": e.verifyAliMobile
						},
						model: {
							value: e.AliMobile,
							callback: function (a) {
								e.AliMobile = a
							},
							expression: "AliMobile"
						}
					})], 1), e._v(" "), n("div", {
						staticClass: "submit-btn"
					}, [n("x-button", {
						staticClass: "weui-btn_radius weui-btn_minRadius",
						attrs: {
							type: "warn",
							"action-type": "button"
						},
						nativeOn: {
							click: function (a) {
								return e.bindAlipay(a)
							}
						}
					}, [e._v("ok")])], 1)], 1), e._v(" "), n("div", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: this.WeChatShow,
							expression: "this.WeChatShow"
						}]
					}, [e._m(5), e._v(" "), e._m(6), e._v(" "), n("group", {
						staticClass: "weui-cells-mt"
					}, [n("x-input", {
						ref: "wxAcc",
						attrs: {
							title: "微信账号",
							placeholder: "请输入微信账号",
							required: ""
						},
						model: {
							value: e.wxAcc,
							callback: function (a) {
								e.wxAcc = a
							},
							expression: "wxAcc"
						}
					}), e._v(" "), n("x-input", {
						ref: "wxNickName",
						attrs: {
							title: "用户昵称",
							placeholder: "请输入用户昵称",
							required: ""
						},
						model: {
							value: e.wxNickName,
							callback: function (a) {
								e.wxNickName = a
							},
							expression: "wxNickName"
						}
					}), e._v(" "), n("x-input", {
						ref: "wxUserName",
						attrs: {
							title: "真实姓名",
							placeholder: "请输入真实姓名",
							required: "",
							disabled: !!e.userBankedData.name
						},
						model: {
							value: e.wxUserName,
							callback: function (a) {
								e.wxUserName = a
							},
							expression: "wxUserName"
						}
					}), e._v(" "), n("x-input", {
						ref: "wxMobile",
						attrs: {
							title: "phone number",
							placeholder: "Please enter your mobile phone number",
							required: "",
							max: 15,
							"is-type": e.verifywxMobile
						},
						model: {
							value: e.wxMobile,
							callback: function (a) {
								e.wxMobile = a
							},
							expression: "wxMobile"
						}
					})], 1), e._v(" "), n("div", {
						staticClass: "submit-btn"
					}, [n("x-button", {
						staticClass: "weui-btn_radius weui-btn_minRadius",
						attrs: {
							type: "warn",
							"action-type": "button"
						},
						nativeOn: {
							click: function (a) {
								return e.bindWeChat(a)
							}
						}
					}, [e._v("ok")])], 1)], 1), e._v(" "), n("div", {
						directives: [{
							name: "transfer-dom",
							rawName: "v-transfer-dom"
						}]
					}, [n("confirm", {
						attrs: {
							title: "Tips"
						},
						on: {
							"on-confirm": e.onConfirm
						},
						model: {
							value: e.bankPopup,
							callback: function (a) {
								e.bankPopup = a
							},
							expression: "bankPopup"
						}
					}, [e._v("\n            After successful binding, the name of the cardholder cannot be modified, but other bank cards of the cardholder can be re bound\n        ")])], 1)])
				},
				staticRenderFns: [
					function () {
						var e = this.$createElement,
							a = this._self._c || e;
						return a("div", {
							staticClass: "bank-title"
						}, [this._v("Please input the issuing bank"), a("span", {
							staticClass: "text-red"
						}, [this._v("*")])])
					},
					function () {
						var e = this.$createElement,
							a = this._self._c || e;
						return a("div", {
							staticClass: "bank-title"
						}, [this._v("Please fill in the branch information"), a("span", {
							staticClass: "text-red"
						}, [this._v("*")])])
					},
					function () {
						var e = this.$createElement,
							a = this._self._c || e;
						return a("div", {
							staticClass: "bank-title"
						}, [this._v("Please fill in the bank reservation information"), a("span", {
							staticClass: "text-gray"
						}, [this._v("(Not required)")])])
					},
					function () {
						var e = this.$createElement,
							a = this._self._c || e;
						return a("div", {
							staticClass: "bankCard"
						}, [a("img", {
							attrs: {
								src: n("yVM2")
							}
						})])
					},
					function () {
						var e = this.$createElement,
							a = this._self._c || e;
						return a("div", {
							staticClass: "bank-title"
						}, [this._v("请输入支付宝信息"), a("span", {
							staticClass: "text-red"
						}, [this._v("*")])])
					},
					function () {
						var e = this.$createElement,
							a = this._self._c || e;
						return a("div", {
							staticClass: "bankCard"
						}, [a("img", {
							attrs: {
								src: n("xmR0")
							}
						})])
					},
					function () {
						var e = this.$createElement,
							a = this._self._c || e;
						return a("div", {
							staticClass: "bank-title"
						}, [this._v("请输入微信信息"), a("span", {
							staticClass: "text-red"
						}, [this._v("*")])])
					}
				]
			};
		var g = n("vSla")(_, A, !1, function (e) {
			n("enXK")
		}, "data-v-1f40e61a", null);
		a.default = g.exports
	},
	G022: function (e, a) {
		e.exports = [{
			name: "default",
			value: "110000"
		}, {
			name: "default",
			value: "110100",
			parent: "110000"
		}]
	},
	enXK: function (e, a) {},
	xmR0: function (e, a, n) {
		e.exports = n.p + "static/img/weixin.3d55e90.png"
	},
	yVM2: function (e, a, n) {
		e.exports = n.p + "static/img/zhifubao.0193005.png"
	}
});
//# sourceMappingURL=13.36d2a1645eddf81c2a02.js.map