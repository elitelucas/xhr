webpackJsonp([28], {
	qBAp: function (t, e, i) {
		"use strict";
		Object.defineProperty(e, "__esModule", {
			value: !0
		});
		var s = i("Hpey"),
			a = i("J8qA"),
			n = i("xQdF"),
			r = (a.a, {
				components: {
					dataTimeFilter: a.a
				},
				data: function () {
					return {
						showList: [],
						nowId: "",
						recordList: [],
						lotteryList: [{
							id: 0,
							name: "All lottery"
						}],
						lottery: "",
						lotteryType: "",
						status: "",
						statusId: "",
						type: "",
						english: [{
							"急速赛车": "Fast 3 cars",
							"分分PK10": "Fast 1 car",
							"三分彩": "Sharp 3 colors",
							"百人牛牛": "Bullfight",
							"分分彩": "Sharp 1 color",
							"欢乐骰宝": "Dice",
							"待开奖": "Not yet",
							"未中奖": "Lose",
							"已中奖": "Win",
							"撤单": "Cancel order",
							"和局": "Award",
							"初级房": "Junior",
							"中级房": "Regular",
							"高级房": "Expert",
							"Vip房": "VIP",
							"投注": "Bet",
							"追号": "Chase number",
                            "大": "Big",
                            "小": "Small",
                            "单": "Odd",
                            "双": "Even",
                            "组合": "combination",
                            "极值": "Limit",
                            "极大": "Biggest",
                            "极小": "Smallest",
                            "号码分布": "Distrubution",
                            "大小": "Size",
                            "单双": "Odd/Even",
                            "充提记录": "Transaction record",
                            "额度转换": "Quota conversion",
                            "目前额度": "Current quota",
                            "现金余额": "Cash balance",
                            "转出": "Roll out",
                            "转入": "Roll in",
                            "数额": "Amount",
                            "提交订单": "Submit orders",
                            "一键转入": "One-click transfer",
                            "总余额": "Total balance",
                            "刷新": "Refresh",
                            "现金余额不足": "Cash balance is not enough",
                            "请输入正确的转账金额": "Please enter the correct transfer amount",
                            "线上支付": "Online payment",
                            "线下支付": "Offline payment",
                            "充值方式": "Deposit method",
                            "返现比例": "Cash back ratio",
                            "充值银行": "Deposit bank",
                            "充值金额": "Deposit amount",
                            "充值限额": "Deposit limit",
                            "下限": "Lower limit",
                            "其他": "Other",
                            "下一步": "Next step",
                            "提交中": "Submitting",
                            "该方式额度已达充值限额": "This method has reached the top-up limit",
                            "建议通过银行卡转账入款": "We recommend depositing by bank card transfer",
                            "发卡银行": "Bank",
                            "扫一扫二维码支付": "Scan the QR code to pay",
                            "我已完成支付": "Completed the payment",
                            "收款人": "Receiver",
                            "收款账号": "Receiver Account",
                            "附加码": "Additional code",
                            "扫码支付": "scan code to pay",
                            "支付": "pay",
                            "点击确认提交": "Click confirm to submit",
                            "支付信息提交成功": "Payment information submitted successfully",
                            "线上": "Online",
                            "线下": "Offline",
                            "转账": "transfer",
                            "充值异常": "Deposit error",
                            "重新充值": "Deposit again",
                            "支付成功": "Pay successfully",
                            "未支付": "Not pay",
                            "请根据支付情况点击下方按钮请不要重复支付": "Please click the button below according to the payment situation, please do not repeat payment",
                            "获取银行列表失败": "Failed to get bank list",
                            "切换后将取消当前未完成订单": "After switching, the current outstanding order will be cancelled",
                            "是否继续": "would you continue",
                            "请输入充值金额": "Please input deposit amount",
                            "输入的资金格式有误": "The format of the entered funds is incorrect",
                            "请选择银行": "Please select the bank",
                            "充值金额不能小于": "The deposit amount cannot be less than",
                            "正向你跳转第三方支付": "Redirecting third-party payment to you",
                            "去支付": "Pay",
                            "该浏览器不支持自动复制": "This browser does not support automatic copy",
                            "已完成支付": "Payment completed",
                            "确认关闭": "would you close",
                            "填写提现金额": "Input the withdraw amount",
                            "等待审核": "Waiting for review",
                            "交易完成": "Transaction complete",
                            "您还没有设置支付密码": "You have not set a payment password",
                            "立即设置": "Set up now",
                            "银行卡信息": "Bank card information",
                            "限额": "Limit",
                            "提现金额": "Withdrawal amount",
                            "请输入提现金额": "Please input the withdraw amount",
                            "您还需要": "You still need",
                            "打码量才可提现": "code to withdraw",
                            "提现密码": "Withdrawal password",
                            "请联系客服": "please contact service center",
                            "您今天提现次数已经超出系统设置的每天免费提现次数限制": "Your number of withdrawals today has exceeded the daily free withdrawal limit set by the system",
                            "继续提现需要额外手续费": "and additional handling fees will be required for continued withdrawals",
                            "账号充值": "Deposit",
                            "立即提现": "Withdraw",
                            "在线客服": "Online service",
                            "请输入房间密码": "Please input the room password",
                            "该彩种已停售": "This lottery has been discontinued",
                            "视频直播": "Live video",
                            "彩票介绍": "Lottery introduction",
                            "投注截止": "Bet deadline",
                            "已封盘": "Closed",
                            "开奖中": "Awarding",
                            "已停售": "Closed",
                            "停售时间": "Discontinued time",
                            "已停售, 售彩时间": "Has been discontinued, play time",
                            "蓝": "Blue",
                            "红": "Red",
                            "对子": "Pair",
                            "豹子": "Triple",
                            "单骰": "Single dice",
                            "双骰": "Double dice",
                            "收起视频": "Collapse video",
                            "关闭": "Close",
                            "已有结果": "There are results",
                            "请刮图层": "please scratch the coating",
                            "此处为最佳刮奖区": "Here is the best scratch area",
                            "选择玩法": "Choose",
                            "追号": "Chase number",
                            "赔率说明": "Odds description",
                            "猜数字": "Guess the number",
                            "已选": "seleted",
                            "最小投注": "Min bet",
                            "请输入下注金额": "Please enter the bet amount",
                            "梭哈": "All in",
                            "确认下注": "Confirm bet",
                            "追": "chasing",
                            "翻": "tip",
                            "倍": "times",
                            "生成追号": "Generate",
                            "确认投注": "Confirm bet",
                            "追号清单": "Chase number list",
                            "中奖即停": "Stop winning",
                            "猜双面": "Guess the size",
                            "猜车号": "Guess the car",
                            "猜龙虎": "Guess the winner",
                            "猜庄闲": "Guess the dealer",
                            "猜冠亚": "Guess the first",
                            "冠亚和": "First and second",
                            "翻倍": "Multiple",
                            "冠军": "Champian",
                            "亚军": "Runner-up",
                            "第三名": "Third",
                            "第四名": "Forth",
                            "第五名": "Fifth",
                            "第六名": "Sixth",
                            "第七名": "Seventh",
                            "第八名": "Eighth",
                            "第九名": "Ninth",
                            "第十名": "Tenth",
                            "猜区段": "Guess block",
                            "庄闲": "Dealer",
                            "冠亚": "First",
                            "和": "Second",
                            "区段": "Block",
                            "猜号码": "Guess the number",
                            "请选择玩法": "Please choose how to play",
                            "最多追加100期": "100 additional issues at most",
                            "长龙投注": "Dragon bet",
                            "删除": "Delete",
                            "暂无数据": "No Data",
                            "猜总和": "Guess the sum",
                            "第一球": "First ball",
                            "第二球": "Second ball",
                            "第三球": "Third ball",
                            "第四球": "Forth ball",
                            "第五球": "Fifth ball",
                            "本期追号投注": "This Chase number bet",
                            "第一名": "First",
                            "第二名": "Second",
                            "三军": "Three armies",
                            "双面": "Two sides",
                            "长牌": "Long card",
                            "短牌": "Short card",
                            "点数": "Points",
                            "特码-特A": "Champian",
                            "特码": "Champian",
                            "正码": "Top",
                            "正特": "Special",
                            "连码": "High",
                            "半波": "Semi-high",
                            "尾数": "Regular",
                            "一肖": "Semi-regular",
                            "特肖": "Low",
                            "连肖": "Semi-low",
                            "连尾": "Bottom",
                            "不中": "Not in",
                            "正码6": "Top1-6",
                            "正6龙虎": "Top1-6 winner",
                            "特A": "ChampianA",
                            "特B": "ChampianB",
                            "正A": "TopA",
                            "正B": "TopB",
                            "正1特": "Special1",
                            "正2特": "Special2",
                            "正3特": "Special3",
                            "正4特": "Special4",
                            "正5特": "Special5",
                            "正6特": "Special6",
                            "三中二": "Two in three",
                            "三全中": "In three",
                            "二全中": "In two",
                            "二中特": "Special in two",
                            "特串": "Specialstring",
                            "二肖连中": "In half two",
                            "三肖连中": "In half three",
                            "四肖连中": "In half four",
                            "二肖连不中": "Not in half two",
                            "三肖连不中": "Not in half three",
                            "四肖连不中": "Not in half four",
                            "二尾连中": "In end two",
                            "三尾连中": "In end three",
                            "四尾连中": "In end four",
                            "二尾连不中": "Not in end two",
                            "三尾连不中": "Not in end three",
                            "四尾连不中": "Not in end four",
                            "五不中": "Not in five",
                            "六不中": "Not in six",
                            "七不中": "Not in seven",
                            "八不中": "Not in eight",
                            "九不中": "Not in nine",
                            "十不中": "Not in ten",
                            "追号只能选择一注": "Only one bet can be seleted for chase",
                            "最多选择10个号码": "Choose 10 numbers at most",
                            "最多选择8个号码": "Choose 8 numbers at most",
                            "最多选择9个号码": "Choose 9 numbers at most",
                            "最多选择11个号码": "Choose 11 numbers at most",
                            "最多选择12个号码": "Choose 12 numbers at most",
                            "最多选择13个号码": "Choose 13 numbers at most",
                            "猜胜负": "Guess the winner",
                            "猜牛牛": "Guess the bull",
                            "猜牌面": "Guess the face",
                            "猜花色": "Guess the flower",
                            "猜公牌": "Guess the card",
                            "第一张": "First card",
                            "第二张": "Second card",
                            "第三张": "Third card",
                            "第四张": "Forth card",
                            "第五张": "Fifth card",
                            "猜对子": "Guess pair",
                            "猜围骰": "Guess triple",
                            "猜单骰": "Guess single dice",
                            "猜双骰": "Guess two dices",
                            "第一骰": "First dice",
                            "第二骰": "Second dice",
                            "第三骰": "Third dice",
                            "大小单双": "Odd and even",
                            "特殊玩法": "Special play",
                            "快速转账": "Quick transfer",
                            "开奖历史": "Award History",
                            "基本走势": "Basic Trend",
                            "号码统计": "Statistics",
                            "日期": "Date",
                            "查询": "Search",
                            "查看单号分布": "View traking number distribution",
                            "还原": "Reduction",
                            "大小单双分布": "Distribution",
                            "序号": "Serial number",
                            "佰": "Hundred",
                            "拾": "Collection",
                            "个": "Piece",
                            "出现总次数": "Total number of occurrences",
                            "平均遗漏值": "Average missing value",
                            "最大遗漏值": "Max missing value",
                            "最大连出值": "Max continuous value",
                            "龙虎": "Red/Blue",
                            "后二": "Last two",
                            "后三": "Last three",
                            "龙": "Blue",
                            "虎": "Red",
                            "奇偶和": "Parity sum",
                            "上中下": "Top/Middle/Bottom",
                            "偶": "Even",
                            "奇": "Odd",
                            "上": "Top",
                            "中": "Middle",
                            "下": "Bottom",
                            "彩球": "Colored ball",
                            "总开": "Always open",
                            "最大连开": "Max continuous opening",
                            "最大遗漏": "Biggest omission",
                            "平均遗漏": "Average omission",
                            "当前遗漏": "Currently missing",
                            "总和尾": "Sum of tail",
                            "尾大": "Oda",
                            "尾小": "Oko",
                            "开牌结果": "Open result",
                            "胜负": "Win/Lose",
                            "牛牛": "Bull",
                            "公牌": "Public license",
                            "总和大": "Sum of big",
                            "总和小": "Sum of small",
                            "牌": "Card",
                            "万位": "Ten thousand",
                            "千位": "Thousand",
                            "百位": "Hundred",
                            "十位": "Ten",
                            "个位": "One",
                            "开奖点数": "Points",
                            "彩球号": "Ball number",
                            "总号": "Total number",
                            "特码合数": "Special code number",
                            "特码生肖": "Special code zodiac",
                            "色波": "Color wave",
                            "总数": "Total",
                            "合单双": "Odd and Even",
                            "合大小": "Big and Small",
                            "黄铜": "Brass",
                            "白银": "Silver",
                            "黄金": "Gold",
                            "铂金": "Steel",
                            "钻石": "Diamond",
                            "大师": "VVVIP",
                            "王者": "VVIP",
                            "至尊": "VIP",
                            "初级房": "Junior",
                            "中级房": "Regular",
                            "高级房": "Expert",
                            "Vip房": "VIP",
                            "5分钟一期": "Every 5 mins",
                            "20分钟一期": "Every 20 mins",
                            "3分半一期": "Every 3.5 mins",
                            "3分钟一期": "Every 3 mins",
                            "每周开奖3期": "3 times per week",
                            "1分钟一期": "Every minute",
                            "最快最准最全": "Quick",
                            "质": "Quality",
                            "合": "Sum",
                            "红方": "Red",
                            "蓝方": "Blue",
                            "红方胜": "Red Win",
                            "蓝方胜": "Blue Win",
                            "大单": "Big Odd",
                            "大双": "Big Even",
                            "小单": "Small Odd",
                            "小双": "Small Even",
                            "庄": "Banker",
                            "闲": "Dealer",
                            "无牛": "No bull",
                            "牛一": "First",
                            "牛二": "Second",
                            "牛三": "Third",
                            "牛四": "Forth",
                            "牛五": "Fifth",
                            "牛六": "Sixth",
                            "牛七": "Seventh",
                            "牛八": "Eighth",
                            "牛九": "Ninth",
                            "花色牛": "Color bull",
                            "黑桃": "Spade",
                            "梅花": "Club",
                            "红心": "Heart",
                            "方块": "Diamond",
                            "A": "A",
                            "J": "J",
                            "Q": "Q",
                            "K": "K",
                            "0": "0",
                            "1": "1",
                            "2": "2",
                            "3": "3",
                            "4": "4",
                            "5": "5",
                            "6": "6",
                            "7": "7",
                            "8": "8",
                            "9": "9",
                            "10": "10",
                            "11": "11",
                            "12": "12",
                            "13": "13",
                            "14": "14",
                            "15": "15",
                            "16": "16",
                            "17": "17",
                            "18": "18",
                            "19": "19",
                            "有公牌": "Public Cards",
                            "无公牌": "No public Cards",
                            "1-2": "1-2",
                            "1-3": "1-3",
                            "1-4": "1-4",
                            "1-5": "1-5",
                            "1-6": "1-6",
                            "2-3": "2-3",
                            "2-4": "2-4",
                            "2-5": "2-5",
                            "2-6": "2-6",
                            "3-4": "3-4",
                            "3-5": "3-5",
                            "3-6": "3-6",
                            "4-5": "4-5",
                            "4-6": "4-6",
                            "5-6": "5-6"
						}],
						statusList: [{
							id: 0,
							name: "All States"
						}],
						pikerShow: !1,
						pickerTitle: "lottery type",
						datePicker: !1,
						beginTime: Object(s.a)(new Date, "YYYY-MM-DD"),
						endTime: Object(s.a)(new Date, "YYYY-MM-DD"),
						page: 1,
						award: "",
						money: "",
						total: "",
						fromPath: ""
					}
				},
				created: function () {
					this.resetAll(1)
				},
				mounted: function () {
					this.lotteryType = this.$route.query.lottery_type
				},
				methods: {
					activeCopy: function (t) {
						Object(n.a)(t.target, "activeScroll"), document.addEventListener("touchmove", function () {
							Object(n.c)(t.target, "activeScroll")
						}), document.addEventListener("touchend", function () {
							Object(n.c)(t.target, "activeScroll")
						})
					},
					pullingDown: function () {
						this.page = 1, this.getLottery("down")
					},
					pullingUp: function () {
						this.flag ? this.$refs.scroll.forceUpdate(!1) : (this.page += 1, this.getLottery("up"))
					},
					resetAll: function (t) {
						var e = this;
						e.status = e.statusList[0], e.lottery = e.lotteryList[0], t && e.$route.query.lottery_type ? e.lotteryType = e.$route.query.lottery_type : e.lotteryType = e.lotteryList[0].id, e.statusId = e.statusList[0].id, e.value1 = Object(s.a)(new Date, "YYYY-MM-DD"), e.value2 = Object(s.a)(new Date, "YYYY-MM-DD"), e.beginTime = Object(s.a)(new Date, "YYYY-MM-DD"), e.endTime = Object(s.a)(new Date, "YYYY-MM-DD"), e.getLottery()
					},
					showPicker: function (t, e) {
						this.type = t, 0 == t ? (this.showList = this.lotteryList, this.pickerTitle = "lottery type") : 1 == t && (this.showList = this.statusList, this.pickerTitle = "Transaction status"), this.pikerShow = !0, this.nowId = e
					},
					getLottery: function (t) {
						var e = this,
							i = {};
						i.token = localStorage.getItem("token"), i.start_time = e.beginTime, i.end_time = e.endTime, i.status = e.statusId, i.type = e.lotteryType, i.page = e.page, t || e.$vux.loading.show(), e.$http.post(e.urlRequest + "?m=api&c=order&a=betList", i).then(function (t) {
							0 == t.status && (1 == e.lotteryList.length && (e.lotteryList = [{
								id: 0,
								name: "All lottery"
							}], e.lotteryList = e.lotteryList.concat(e.filterLottery(t.gameInfo)), e.statusList = [{
								id: 0,
								name: "All States"
							}], e.statusList = e.statusList.concat(e.filterStatus(t.trantype)), e.lottery = e.lotteryList[0], e.status = e.statusList[0], e.lotteryType = e.lotteryList[0].id, e.statusId = e.statusList[0].id), 1 == e.page ? e.recordList = t.list : e.recordList = e.recordList.concat(t.list), t.list.length < 20 && (e.flag = !0), e.money = t.total.money, e.award = t.total.award, e.total = (+t.total.award - +t.total.money).toFixed(2)), e.$vux.loading.hide()
						}).catch(function (t) {
							e.$vux.loading.hide(), console.log(t)
						})
					},
					filterLottery: function (t) {
						var a = [];
						for (var r = 0; r < t.length; r++) {
							if (t[r]["id"] == 6 || t[r]["id"] >= 9) {
								t[r]['name'] = this.english[0][t[r]['name']];
								a.push(t[r]);
							}
						}
						return a;
					},
					filterStatus: function (t) {
						var a = [];
						for (var r = 0; r < t.length; r++) {
							t[r]['name'] = this.english[0][t[r]['name']];
							a.push(t[r]);
						}
						return a;
					},
					pickHide: function (t) {
						this.datePicker = t
					},
					getTime: function (t) {
						new Date(t[0]), new Date(t[1]);
						this.endTime = t[1], this.beginTime = t[0], this.datePicker = !1, this.page = 1, this.getLottery()
					},
					picking: function (t) {
						var e = this;
						0 == this.type ? (this.lotteryType = t, this.lottery = this.lotteryList.find(function (e) {
							return e.id == t
						})) : 1 == this.type && (this.statusId = t, this.status = this.statusList.find(function (e) {
							return e.id == t
						})), this.nowId = t, this.getLottery();
						setTimeout(function () {
							e.pikerShow = !1
						}, 200)
					}
				},
				activated: function () {},
				deactivated: function () {},
				watch: {
					$route: function (t, e) {
						"TouZhuDetail" != e.name && "BettingRecord" == t.name && (this.recordList = [], this.lotteryList = [{
							id: 0,
							name: "All States"
						}], this.resetAll(1))
					},
					lotteryList: function (t) {
						if (t.length)
							for (var e = 0; e < t.length; e++) t[e].id == this.$route.query.lottery_type && (this.lottery = t[e], this.lotteryType = t[e].id)
					}
				}
			}),
			o = {
				render: function () {
					var t = this,
						e = t.$createElement,
						s = t._self._c || e;
					return s("div", [s("div", {
						staticClass: "headerWrap"
					}, [s("x-header", {
						staticClass: "header"
					}, [t._v("Betting record "), s("a", {
						attrs: {
							slot: "right"
						},
						on: {
							click: function (e) {
								t.resetAll(0)
							}
						},
						slot: "right"
					}, [t._v("Reset")])])], 1), t._v(" "), s("flexbox", {
						staticClass: "headFilter",
						attrs: {
							gutter: 0
						}
					}, [s("flexbox-item", {
						staticClass: "item-type",
						class: {
							active: t.pikerShow && 0 == t.type
						},
						attrs: {
							span: 4
						},
						nativeOn: {
							click: function (e) {
								t.showPicker(0, t.lottery.id)
							}
						}
					}, [s("span", {
						staticClass: "item-title"
					}, [t._v(t._s(t.lottery.name))]), t._v(" "), s("i", {
						staticClass: "item-icon triangle",
						staticStyle: {
							"pointer-events": "none"
						}
					}, [s("img", {
						staticClass: "down",
						attrs: {
							src: i("sUte"),
							alt: ""
						}
					}), t._v(" "), s("img", {
						staticClass: "up",
						attrs: {
							src: i("mrzh"),
							alt: ""
						}
					})])]), t._v(" "), s("flexbox-item", {
						staticClass: "item-type",
						class: {
							active: t.pikerShow && 1 == t.type
						},
						attrs: {
							span: 4
						},
						nativeOn: {
							click: function (e) {
								t.showPicker(1, t.status.id)
							}
						}
					}, [s("span", {
						staticClass: "item-title"
					}, [t._v(t._s(t.status.name))]), t._v(" "), s("i", {
						staticClass: "item-icon triangle",
						staticStyle: {
							"pointer-events": "none"
						}
					}, [s("img", {
						staticClass: "down",
						attrs: {
							src: i("sUte"),
							alt: ""
						}
					}), t._v(" "), s("img", {
						staticClass: "up",
						attrs: {
							src: i("mrzh"),
							alt: ""
						}
					})])]), t._v(" "), s("flexbox-item", {
						staticClass: "item-type",
						attrs: {
							span: 4
						},
						nativeOn: {
							click: function (e) {
								t.datePicker = !0
							}
						}
					}, [s("span", {
						staticClass: "item-title"
					}, [t._v("Time filtering")]), t._v(" "), s("i", {
						staticClass: "item-icon"
					}, [s("img", {
						attrs: {
							src: i("4/iK"),
							alt: ""
						}
					})])])], 1), t._v(" "), s("div", {
						staticClass: "trading-hour"
					}, [s("span", [t._v("Betting time：")]), t._v(" "), "" == t.beginTime && "" == t.endTime ? [s("span", [t._v("All")])] : [s("span", [t._v(t._s(t.beginTime))]), t._v(" "), s("span", [t._v("to")]), t._v(" "), s("span", [t._v(t._s(t.endTime))])]], 2), t._v(" "), s("div", {
						staticClass: "item-list-wrapper"
					}, [t.recordList.length > 0 ? [s("scroll", {
						ref: "scroll",
						attrs: {
							pullDownRefresh: !0,
							pullUpLoad: !0,
							data: t.recordList
						},
						on: {
							pullingDown: t.pullingDown,
							pullingUp: t.pullingUp
						}
					}, [s("div", t._l(t.recordList, function (e, i) {
						return s("flexbox", {
							key: i,
							staticClass: "item-list activeBox",
							attrs: {
								gutter: 0
							},
							nativeOn: {
								click: function (i) {
									t.$router.push("/touZhuDetail?id=" + e.id)
								}
							}
						}, [s("flexbox-item", {
							attrs: {
								span: 7
							}
						}, [s("h3", [t._v(t._s(t.english[0][e.name])), s("span", [t._v(t._s(t.english[0][e.room_name]))])]), t._v(" "), s("p", [t._v("issue：" + t._s(e.issue))]), t._v(" "), s("p", [t._v("Bet content：" + t._s(e.way.split("_").length > 1 ? t.english[0][e.way.split("_")[0]] + "_" + t.english[0][e.way.split("_")[1]] : t.english[0][e.way]))]), t._v(" "), s("p", [t._v(t._s(t.english[0][e.state]) + " time：" + t._s(e.addtime))])]), t._v(" "), s("flexbox-item", {
							staticClass: "text-right",
							attrs: {
								span: 5
							}
						}, [s("h3", {
							class: "已撤单" == e.status ? "text-red" : "text-green"
						}, [t._v(t._s("已撤单" == e.status ? "+" : "-") + t._s(e.money) + "(" + t._s(t.english[0][e.state]) + ")")]), t._v(" "), s("h3", {
							directives: [{
								name: "show",
								rawName: "v-show",
								value: e.award > 0,
								expression: "item.award > 0"
							}],
							staticClass: "text-red"
						}, [t._v("+" + t._s(e.award) + "(Win)")]), t._v(" "), s("p", [t._v("state：" + t._s(t.english[0][e.status]))])])], 1)
					}))])] : [s("img", {
						staticClass: "noDataImg",
						attrs: {
							src: i("w+73"),
							alt: ""
						}
					})]], 2), t._v(" "), s("flexbox", {
						staticClass: "footerTotal",
						attrs: {
							gutter: 0
						}
					}, [s("flexbox-item", [s("p", [t._v("Betting")]), t._v(" "), s("h3", {
						staticClass: "text-green"
					}, [t._v("-" + t._s(0 === t.money ? "0.00" : t.money))])]), t._v(" "), s("flexbox-item", [s("p", [t._v("Win")]), t._v(" "), s("h3", {
						staticClass: "text-red"
					}, [t._v(t._s(t.award >= 0 ? "+" : "-") + t._s(0 === t.award ? "0.00" : t.award))])]), t._v(" "), s("flexbox-item", [s("p", [t._v("profit")]), t._v(" "), s("h3", {
						class: t.total > 0 ? "text-red" : "text-green"
					}, [t._v(t._s(t.total > 0 ? "+" : " ") + t._s(0 === t.total ? "0.00" : t.total))])])], 1), t._v(" "), s("transition", {
						attrs: {
							name: "picker"
						}
					}, [t.pikerShow ? s("div", {
						staticClass: "picker",
						on: {
							click: function (e) {
								if (e.target !== e.currentTarget) return null;
								t.pikerShow = !1
							}
						}
					}, [t.pikerShow ? s("div", {
						staticClass: "innerBox"
					}, [s("h4", {
						staticClass: "vux-1px-b"
					}, [t._v("Select " + t._s(t.pickerTitle))]), t._v(" "), s("div", {
						staticClass: "picker-list clearfix"
					}, t._l(t.showList, function (e, i) {
						return s("div", {
							key: i
						}, [s("button", {
							class: {
								cur: e.id == t.nowId
							},
							attrs: {
								"data-id": e.id
							},
							on: {
								click: function (i) {
									t.picking(e.id)
								}
							}
						}, [t._v(t._s(e.name))])])
					})), t._v(" "), s("a", {
						attrs: {
							href: "javascript:void(0)"
						},
						on: {
							click: function (e) {
								t.pikerShow = !1
							}
						}
					}, [t._v("cancel")])]) : t._e()]) : t._e()]), t._v(" "), s("dataTimeFilter", {
						attrs: {
							endTime: t.endTime,
							beginTime: t.beginTime,
							datePicker: t.datePicker
						},
						on: {
							getTime: t.getTime,
							pickHide: t.pickHide
						}
					})], 1)
				},
				staticRenderFns: []
			};
		var l = i("vSla")(r, o, !1, function (t) {
			i("vkSy")
		}, "data-v-d4ff6038", null);
		e.default = l.exports
	},
	vkSy: function (t, e) {}
});