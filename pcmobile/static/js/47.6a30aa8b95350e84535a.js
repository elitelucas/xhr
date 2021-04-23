webpackJsonp([47], {
	Rd2K: function (e, a, t) {
		"use strict";
		Object.defineProperty(a, "__esModule", {
			value: !0
		});
		var r = t("4YfN"),
			n = t.n(r),
			s = t("9rMa"),
			o = t("s4gL"),
			i = t("uYm6"),
			c = t("o+C2"),
			h = (Array, o.a, i.a, c.a, n()({}, Object(s.c)(["userInfo", "accountData", "rechargeData"])), {
				props: {
					quickBtn: {
						type: Array,
						default: function () {
							return []
						}
					}
				},
				components: {
					Checker: o.a,
					CheckerItem: i.a,
					PopupPicker: c.a
				},
				data: function () {
					return {
						checkerIndex: this.quickBtn[0],
						amountVal: null,
						prePaidData: {},
						gtbank: !1,
						bankList: [],
						bankListValue: [],
						confirmPay: !1,
						showSelect: !0,						
						payerName: "",
						payerEmail: "",
						payerPhone: "",
						busiCode: "",
						bankAccount: "",
						bankCode: "",
					}
				},
				created: function () {
					this._getBankList()
				},
				mounted: function () {
					var e = this;
					this.$nextTick(function () {
						e.rechargeData.payment_name || e.$router.back()
					})
				},
				computed: n()({}, Object(s.c)(["userInfo", "accountData", "rechargeData"])),
				methods: {
					headBack: function () {
						this.$router.push({
							path: "/recharge"
						})
					},
					payCancel: function () {
						this.$router.replace({
							path: "/recharge/payStatusErr",
							query: {
								amountVal: this.amountVal
							}
						})
					},
					payConfirm: function () {
						this.$router.replace({
							path: "/recharge/payStatus",
							query: {
								amountVal: this.amountVal
							}
						})
					},
					removeImg: function () {
						this.userInfo.avatar = ""
					},
					onChange: function (e) {
						console.log("val change", e)
					},
					_getBankList: function () {
						var e = this;
						if (2 == this.rechargeData.type_mode) {
							var a = {
								token: localStorage.getItem("token"),
								payment_id: this.rechargeData.payment_id
							};
							this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=recharge&a=getRechargeBankList", a).then(function (a) {
								if (e.$vux.loading.hide(), 0 == a.status) {
									e.newData = a.bankList;
									var t = [];
									for (var r in e.newData) t.push(e.newData[r].name);
									e.bankList.push(t), console.log(a.bankList), console.log(e.bankList)
								} else a.ret_msg && "" != a.ret_msg && e.$vux.toast.show({
									text: a.ret_msg
								})
							}).catch(function (a) {
								e.$vux.loading.hide(), e.$vux.toast.show({
									text: "Failed to get bank list"
								})
							})
						}
					},
					changeBusiCode: function(t) {
						if (this.$refs.scroll)
							for (var e = ['Thailand Online Banking','Thailand Scan Code','Thailand TrueMoney'], s = 0; s < e.length; s++)
								if (e[s] == t[0]) {
									var i = document.getElementById("pmTitle" + s);
									this.$refs.scroll.scrollTo(0, -i.offsetTop, 500)
								}
						this.busiCode = t[0]
					},
					changeBankCode: function(t) {
						if (this.$refs.scroll)
							for (var e = ['Bank of Ayudhya','Krung Thai Bank','BANGKOK BANK','Kasikornbank'], s = 0; s < e.length; s++)
								if (e[s] == t[0]) {
									var i = document.getElementById("pmTitle" + s);
									this.$refs.scroll.scrollTo(0, -i.offsetTop, 500)
								}
						this.bankCode = t[0]
					},
					payNow: function () {
						var e = this;
						this.amountVal = this.$refs.amountValRef.value;
						var a = Number(this.amountVal);
						if (console.log("rechargeData", this.rechargeData), console.log("amountVal：", this.amountVal), "" == this.amountVal || 0 == this.amountVal) return this.$refs.amountValRef.focus(), void this.$vux.toast.show({
							text: "Please enter the recharge amount"
						});
						if (!/(^[1-9]([0-9]+)?(\.[0-9]{1,2})?$)|(^(0){1}$)|(^[0-9]\.[0-9]([0-9])?$)/.test(this.amountVal)) return this.$refs.amountValRef.focus(), void this.$vux.toast.show({
							text: "The format of the entered funds is incorrect"
						});
						if (0 == this.rechargeData.line) {
							if (2 == this.rechargeData.type_mode && "" == this.bankListValue) return void this.$vux.toast.show({
								text: "Please select a bank"
							});
							var t = Number(this.rechargeData.lower_limit);
							if (a < t) return void this.$vux.toast.show({
								text: "The recharge amount cannot be less than " + t + "$"
							});
							var busiCodeList = {
								'Thailand Online Banking':'100201',
								'Thailand Scan Code':'100202',
								'Thailand TrueMoney':'100203'
							}
							var bankCodeList = {
								'Bank of Ayudhya':'BAY',
								'Krung Thai Bank':'KTB',
								'BANGKOK BANK':'BBL',
								'Kasikornbank':'KBANK'
							}
							var r = {
								pname: this.payerName,
								pemail: this.payerEmail,
								phone: this.payerPhone,
								order_amount : this.amountVal ? this.amountVal: "",
								busi_code: busiCodeList[this.busiCode],
								accNo: this.bankAccount,
								bankCode: bankCodeList[this.bankCode]
							};
							"Global" == this.rechargeData.type_mode ? (this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=recharge&a=globalPay", r).then(function (a) {
								e.$vux.loading.hide(), 'SUCCESS' == a.status ? (window.location.href=a.order_data, e.order_no = a.order_no, a.code_img ? (e.codeImg = a.code_img, e.$router.push({
									path: "/recharge/payment",
									query: {
										order: e.order_no,
										codeImg: e.codeImg,
										collectBank: e.collectBank,
										amountVal: e.amountVal
									}
								})) : (e.codeUrl = a.code_url, e.$router.push({
									path: "/recharge/payment",
									query: {
										order: e.order_no,
										codeUrl: e.codeUrl,
										collectBank: e.collectBank,
										amountVal: e.amountVal
									}
								}))) : (e.$router.replace({
									path: "/recharge/payStatusErr",
									query: {
										order: e.order,
										code: e.code,
										amountVal: e.amountVal
									}
								}), a.err_msg && "" != a.err_msg && e.$vux.toast.show({
									text: a.err_msg
								}))
							}).catch(function (a) {
								e.$vux.loading.hide(), e.$vux.toast.show({
									text: "Data request timeout"
								})
							})) : 1 == this.rechargeData.type_mode ? (this.paymentLink = this.urlRequest + "?m=api&c=recharge&a=rechargeOnline&token=" + localStorage.getItem("token") + "&channel_type=" + this.rechargeData.channel_type + "&type=" + this.rechargeData.type + "&pay_type=" + this.rechargeData.payment_name + "&money=" + this.amountVal, this.confirmPay = !0, window.open(this.paymentLink)) : 2 == this.rechargeData.type_mode && (this.paymentLink = this.urlRequest + "?m=api&c=recharge&a=rechargeOnline&token=" + localStorage.getItem("token") + "&channel_type=" + this.rechargeData.channel_type + "&type=" + this.rechargeData.type + "&pay_type=" + this.rechargeData.payment_name + "&money=" + this.amountVal + "&bank_code=" + this.bankListVal, this.confirmPay = !0, window.open(this.paymentLink))
						} else {
							var n = Number(this.rechargeData.min_recharge),
								s = Number(this.rechargeData.max_recharge);
							if (a < n) return void this.$vux.toast.show({
								text: "Recharge amount cannot be less than" + n + "USD"
							});
							if (a > s) return void this.$vux.toast.show({
								text: "Recharge amount cannot be greater than" + s + "USD"
							});
							r = {
								token: localStorage.getItem("token"),
								id: this.rechargeData.payment_id,
								money: this.amountVal ? this.amountVal : ""
							};
							this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=recharge&a=rechargeOffline", r).then(function (a) {
								e.$vux.loading.hide(), 0 == a.status ? e.$router.push({
									path: "/recharge/payment",
									query: {
										order: a.order_sn,
										code: a.code,
										amountVal: e.amountVal
									}
								}) : a.ret_msg && "" != a.ret_msg && e.$vux.toast.show({
									text: a.ret_msg
								})
							}).catch(function (a) {
								e.$vux.loading.hide(), e.$vux.toast.show({
									text: "Data request timeout"
								})
							})
						}
					}
				},
				watch: {
					bankListValue: function (e) {
						for (var a in this.newData) this.newData[a].name == e && (this.bankListVal = this.newData[a])
					}
				}
			}),
			l = {
				render: function () {
					var e = this,
						a = e.$createElement,
						r = e._self._c || a;
					return r("div", {
						staticClass: "subPage isHeader"
					}, [r("div", {
						staticClass: "headerWrap"
					}, [r("x-header", {
						staticClass: "header",
						attrs: {
							"left-options": {
								preventGoBack: !0
							}
						},
						on: {
							"on-click-back": e.headBack
						}
					}, [e._v(e._s(e.rechargeData.payment_name))])], 1), e._v(" "), r("div", {
						staticClass: "page-content"
					}, [r("scroll", [r("div", {
						staticClass: "prePaid-user"
					}, [r("div", {
						staticClass: "avatar-wrap"
					}, [e.userInfo.avatar ? r("img", {
						attrs: {
							src: e.imgRequest + e.userInfo.avatar
						},
						on: {
							error: e.removeImg
						}
					}) : r("img", {
						attrs: {
							src: t("cuiQ")
						}
					})]), e._v(" "), r("p", {
						staticClass: "clearfix"
					}, [r("span", [e._v("Member account：" + e._s(e.userInfo.username))]), e._v(" "), r("span", [e._v("Account balance：" + e._s(e.accountData ? e.accountData.money_usable : 0))])])]), e._v(" "), e.rechargeData.line==0?e.rechargeData.type_mode=='Global'?r("group", {
						staticClass: "group-min-top"
					}, [r("cell", {
						attrs: {
							title: "Payer name",
							"value-align": "left"
						}
					}, [r("i", {
						staticClass: "cell-icon cell-icon-bank",
						attrs: {
							slot: "icon"
						},
						slot: "icon"
					}), e._v(" "),r("input", {
						directives: [{
							name: "model",
							rawName: "v-model",
						}],
						ref: "payerName",
						staticClass: "pr-0",
						staticStyle: {"margin-left": "10px"},
						attrs: {
							placeholder: "Please enter the payer name",
							type: "text"
						},
						domProps: {
							value: e.payerName
						},
						on: {
							input: function (a) {
								a.target.composing || (e.payerName = a.target.value)
							}
						}
					}), e._v(" ")]), e._v(" "),r("cell", {
						attrs: {
							title: "Payer email",
							"value-align": "left"
						}
					}, [r("i", {
						staticClass: "cell-icon cell-icon-bank",
						attrs: {
							slot: "icon"
						},
						slot: "icon"
					}), e._v(" "),r("input", {
						directives: [{
							name: "model",
							rawName: "v-model",
						}],
						ref: "payerEmail",
						staticClass: "pr-0",
						staticStyle: {"margin-left": "10px"},
						attrs: {
							placeholder: "Please enter the payer email",
							type: "text"
						},
						domProps: {
							value: e.payerEmail
						},
						on: {
							input: function (a) {
								a.target.composing || (e.payerEmail = a.target.value)
							}
						}
					}), e._v(" ")]), e._v(" "),r("cell", {
						attrs: {
							title: "Payer phone",
							"value-align": "left"
						}
					}, [r("i", {
						staticClass: "cell-icon cell-icon-bank",
						attrs: {
							slot: "icon"
						},
						slot: "icon"
					}), e._v(" "),r("input", {
						directives: [{
							name: "model",
							rawName: "v-model",
						}],
						ref: "payerPhone",
						staticClass: "pr-0",
						staticStyle: {"margin-left": "10px"},
						attrs: {
							placeholder: "Please enter the payer phone",
							type: "text"
						},
						domProps: {
							value: e.payerPhone
						},
						on: {
							input: function (a) {
								a.target.composing || (e.payerPhone = a.target.value)
							}
						}
					}), e._v(" ")]), e._v(" "),r("cell", {
						attrs: {
							title: "Payment Type",
							"value-align": "left"
						}
					}, [r("i", {
						staticClass: "cell-icon cell-icon-bank",
						attrs: {
							slot: "icon"
						},
						slot: "icon"
					}), e._v(" "),r("group", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: e.showSelect,
							expression: "showSelect"
						}],
						staticClass: "weui-cells-mt"
					}, [r("popup-picker", {
						staticClass: "oddsPanel vux-1px-l vux-1px-r",
						staticStyle: {"margin-left": "10px"},
						attrs: {
							placeholder: e.busiCode!=""?e.busiCode:"Please select",
							data: [['Thailand Online Banking','Thailand Scan Code','Thailand TrueMoney']]
						},
						on: {
							"on-change": e.changeBusiCode
						}
					})], 1), e._v(" ")]), e._v(" "),e.busiCode=="Thailand Online Banking"?r("cell", {
						attrs: {
							title: "Bank Account",
							"value-align": "left"
						}
					}, [r("i", {
						staticClass: "cell-icon cell-icon-bank",
						attrs: {
							slot: "icon"
						},
						slot: "icon"
					}), e._v(" "),r("input", {
						directives: [{
							name: "model",
							rawName: "v-model",
						}],
						ref: "bankAccount",
						staticClass: "pr-0",
						staticStyle: {"margin-left": "10px"},
						attrs: {
							placeholder: "Please enter the bank account",
							type: "text"
						},
						domProps: {
							value: e.bankAccount
						},
						on: {
							input: function (a) {
								a.target.composing || (e.bankAccount = a.target.value)
							}
						}
					}), e._v(" ")]):e._e(), e._v(" "),e.busiCode=="Thailand Online Banking"?r("cell", {
						attrs: {
							title: "Bank Code",
							"value-align": "left"
						}
					}, [r("i", {
						staticClass: "cell-icon cell-icon-bank",
						attrs: {
							slot: "icon"
						},
						slot: "icon"
					}), e._v(" "),r("group", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: e.showSelect,
							expression: "showSelect"
						}],
						staticClass: "weui-cells-mt"
					}, [r("popup-picker", {
						staticClass: "oddsPanel vux-1px-l vux-1px-r",
						staticStyle: {"margin-left": "10px"},
						attrs: {
							placeholder: e.bankCode!=""?e.bankCode:"Please select",
							data: [['Bank of Ayudhya','Krung Thai Bank','BANGKOK BANK','Kasikornbank']]
						},
						on: {
							"on-change": e.changeBankCode
						}
					})], 1), e._v(" ")]):e._e(), e._v(" "),r("cell", {
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
					}), e._v(" "),r("input", {
						directives: [{
							name: "model",
							rawName: "v-model",
							value: e.checkerIndex,
							expression: "checkerIndex"
						}],
						ref: "amountValRef",
						staticClass: "amount-input",
						attrs: {
							placeholder: "Please enter the recharge amount",
							type: "number"
						},
						domProps: {
							value: e.checkerIndex
						},
						on: {
							input: function (a) {
								a.target.composing || (e.checkerIndex = a.target.value)
							}
						}
					}), e._v(" "), r("span", {
						staticClass: "unit text-gray"
					}, [e._v("(USD)")])]), e._v(" "), r("div", {
						staticClass: "amount-wrap"
					}, [r("div", {
						staticClass: "amount-tab-list"
					}, [r("checker", {
						attrs: {
							"default-item-class": "",
							"selected-item-class": "active"
						},
						model: {
							value: e.checkerIndex,
							callback: function (a) {
								e.checkerIndex = a
							},
							expression: "checkerIndex"
						}
					}, e._l(e.quickBtn, function (a, t) {
						return r("checker-item", {
							key: t,
							attrs: {
								value: a
							}
						}, [r("span", [e._v(e._s(a))])])
					}))], 1), e._v(" ")]), e._v(" "), r("div", {
						staticClass: "submit-btn"
					}, [r("x-button", {
						staticClass: "weui-btn_radius weui-btn_minRadius",
						attrs: {
							type: "warn",
							"action-type": "button"
						},
						nativeOn: {
							click: function (a) {
								return e.payNow(a)
							}
						}
					}, [e._v("Immediate payment")])], 1)], 1):r("group", {
						staticClass: "group-min-top"
					}, e._l(e.rechargeData['addresses'], function (a, t) {
						return"bitcoin"==t||"ethereum"==t||"usdc"==t?r("cell",{							
							key: t,
							nativeOn: {
								click: function () {
									window.location.href=e.rechargeData['hosted_url']
								}
							}},[r("div",{
								staticStyle:{width:"15%",float:"left","margin-left":"2%"}
							},["bitcoin"==t?r("img",{
								attrs:{
									src:e.imgRequest+"/pcmobile/static/img/bitcoin.png",
									alt:"",
									width:"100%"
								}
							}):e._e(),"ethereum"==t?r("img",{
								attrs:{
									src:e.imgRequest+"/pcmobile/static/img/ethereum.png",
									alt:"",
									width:"100%"
								}
							}):e._e(),"usdc"==t?r("img",{
								attrs:{
									src:e.imgRequest+"/pcmobile/static/img/usdc.png",
									alt:"",
									width:"100%"
								}
							}):e._e()]),r("div",{
								staticStyle:{"margin-left":"5%",width:"70%",display:"inline-block"}
							},[r("h4",{staticStyle:{"word-break": "break-all"}},[
								e._v(e._s(t[0].toUpperCase()+t.substring(1))+": "+e._s(a))
							]),"bitcoin"==t?r("p",[e._v("BTC-USD: "+e._s(e.rechargeData["exchange_rates"]["BTC-USD"]))]):e._e(),"ethereum"==t?r("p",[e._v("ETH-USD: "+e._s(e.rechargeData["exchange_rates"]["ETH-USD"]))]):e._e(),"usdc"==t?r("p",[e._v("USDC-USD: "+e._s(e.rechargeData["exchange_rates"]["USDC-USD"]))]):e._e()])]
						):e._v(" ")
					})):r("group", {
						staticClass: "group-min-top"
					}, [r("cell", {
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
					}), e._v(" "), r("input", {
						directives: [{
							name: "model",
							rawName: "v-model",
							value: e.checkerIndex,
							expression: "checkerIndex"
						}],
						ref: "amountValRef",
						staticClass: "amount-input",
						attrs: {
							placeholder: "Please enter the recharge amount",
							type: "number"
						},
						domProps: {
							value: e.checkerIndex
						},
						on: {
							input: function (a) {
								a.target.composing || (e.checkerIndex = a.target.value)
							}
						}
					}), e._v(" "), r("span", {
						staticClass: "unit text-gray"
					}, [e._v("(USD)")])]), e._v(" "), r("div", {
						staticClass: "amount-wrap"
					}, [r("div", {
						staticClass: "amount-tab-list"
					}, [r("checker", {
						attrs: {
							"default-item-class": "",
							"selected-item-class": "active"
						},
						model: {
							value: e.checkerIndex,
							callback: function (a) {
								e.checkerIndex = a
							},
							expression: "checkerIndex"
						}
					}, e._l(e.quickBtn, function (a, t) {
						return r("checker-item", {
							key: t,
							attrs: {
								value: a
							}
						}, [r("span", [e._v(e._s(a))])])
					}))], 1), e._v(" "), r("p", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: e.rechargeData.lower_limit,
							expression: "rechargeData.lower_limit"
						}],
						staticClass: "recharge-tips text-gray"
					}, [r("em", {
						staticClass: "text-red"
					}, [e._v("*")]), e._v("Recharge limit：lower limit" + e._s(e.rechargeData.lower_limit) + "USD")]), e._v(" "), r("p", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: e.rechargeData.min_recharge && e.rechargeData.max_recharge,
							expression: "rechargeData.min_recharge && rechargeData.max_recharge"
						}],
						staticClass: "recharge-tips text-gray"
					}, [r("em", {
						staticClass: "text-red"
					}, [e._v("*")]), e._v("Recharge limit：" + e._s(e.rechargeData.min_recharge) + " ~ " + e._s(e.rechargeData.max_recharge) + "，It is suggested to transfer money through bank card")])])], 1), e._v(" "), r("group", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: 2 == e.rechargeData.type_mode,
							expression: "rechargeData.type_mode == 2"
						}]
					}, [r("popup-picker", {
						attrs: {
							title: "Bank card transfer",
							placeholder: "Please select a bank",
							data: e.bankList
						},
						on: {
							"on-change": e.onChange
						},
						model: {
							value: e.bankListValue,
							callback: function (a) {
								e.bankListValue = a
							},
							expression: "bankListValue"
						}
					})], 1), e._v(" "), e.rechargeData.line!=0?r("div", {
						staticClass: "submit-btn"
					}, [r("x-button", {
						staticClass: "weui-btn_radius weui-btn_minRadius",
						attrs: {
							type: "warn",
							"action-type": "button"
						},
						nativeOn: {
							click: function (a) {
								return e.payNow(a)
							}
						}
					}, [e._v("Immediate payment")])], 1):e._e(), e._v(" "), e.rechargeData.prompt && "" != e.rechargeData.prompt ? r("div", {
						staticClass: "tips"
					}, [r("h4", [e._v("reminder")]), e._v(" "), 0 == e.rechargeData.line ? r("p", {
						domProps: {
							innerHTML: e._s(e.rechargeData.prompt[0].replace(/\r?\n|\r|&crarr;|&#8629;/g, "<br>"))
						}
					}) : e._e(), e._v(" "), 1 == e.rechargeData.line ? r("p", {
						domProps: {
							innerHTML: e._s(e.rechargeData.prompt.replace(/\\n/g, "</br>"))
						}
					}) : e._e()]) : e._e()], 1)], 1), e._v(" "), r("div", {
						directives: [{
							name: "transfer-dom",
							rawName: "v-transfer-dom"
						}]
					}, [r("confirm", {
						staticClass: "confirmPay",
						attrs: {
							title: "reminder",
							"cancel-text": "Unpaid",
							"confirm-text": "Payment successful"
						},
						on: {
							"on-cancel": e.payCancel,
							"on-confirm": e.payConfirm
						},
						model: {
							value: e.confirmPay,
							callback: function (a) {
								e.confirmPay = a
							},
							expression: "confirmPay"
						}
					}, [e._v("\n            Please click the button below according to the payment situation. Please do not repeat payment。\n        ")])], 1)])
				},
				staticRenderFns: []
			};
		var u = t("vSla")(h, l, !1, function (e) {
			t("e0Ev")
		}, "data-v-644f1bc6", null);
		a.default = u.exports
	},
	e0Ev: function (e, a) {}
});
//# sourceMappingURL=47.6a30aa8b95350e84535a.js.map