webpackJsonp([15], {
	IZGE: function (t, e, s) {
		"use strict";
		Object.defineProperty(e, "__esModule", {
			value: !0
		});
		var i = s("4YfN"),
			a = s.n(i),
			n = s("j108"),
			o = s("inDh"),
			r = s("3cXf"),
			l = s.n(r),
			c = s("AA3o"),
			u = s.n(c),
			h = s("xSur"),
			d = s.n(h),
			g = s("+Up5"),
			v = s.n(g),
			_ = function (t) {
				return Array.prototype.slice.call(t)
			},
			p = function () {
				function t(e) {
					if (u()(this, t), this._default = {
						container: ".vux-swiper",
						item: ".vux-swiper-item",
						direction: "vertical",
						activeClass: "active",
						threshold: 50,
						duration: 300,
						auto: !1,
						loop: !1,
						interval: 3e3,
						height: "auto",
						minMovingDistance: 0
					}, this._options = v()(this._default, e), this._options.height = this._options.height.replace("px", ""), this._start = {}, this._move = {}, this._end = {}, this._eventHandlers = {}, this._prev = this._current = this._goto = 0, this._width = this._height = this._distance = 0, this._offset = [], this.$box = this._options.container, this.$container = this._options.container.querySelector(".vux-swiper"), this.$items = this.$container.querySelectorAll(this._options.item), this.count = this.$items.length, this.realCount = this.$items.length, this._position = [], this._firstItemIndex = 0, this._isMoved = !1, this.count) return this._init(), this._auto(), this._bind(), this._onResize(), this
				}
				return d()(t, [{
					key: "_auto",
					value: function () {
						var t = this;
						t.stop(), t._options.auto && (t.timer = setTimeout(function () {
							t.next()
						}, t._options.interval))
					}
				}, {
					key: "updateItemWidth",
					value: function () {
						this._width = this.$box.offsetWidth || document.documentElement.offsetWidth, this._distance = "horizontal" === this._options.direction ? this._width : this._height
					}
				}, {
					key: "stop",
					value: function () {
						this.timer && clearTimeout(this.timer)
					}
				}, {
					key: "_loop",
					value: function () {
						return this._options.loop && this.realCount >= 3
					}
				}, {
					key: "_onResize",
					value: function () {
						var t = this;
						this.resizeHandler = function () {
							setTimeout(function () {
								t.updateItemWidth(), t._setOffset(), t._setTransform()
							}, 100)
						}, window.addEventListener("orientationchange", this.resizeHandler, !1)
					}
				}, {
					key: "_init",
					value: function () {
						this._height = "auto" === this._options.height ? "auto" : this._options.height - 0, this.updateItemWidth(), this._initPosition(), this._activate(this._current), this._setOffset(), this._setTransform(), this._loop() && this._loopRender()
					}
				}, {
					key: "_initPosition",
					value: function () {
						for (var t = 0; t < this.realCount; t++) this._position.push(t)
					}
				}, {
					key: "_movePosition",
					value: function (t) {
						if (t > 0) {
							var e = this._position.splice(0, 1);
							this._position.push(e[0])
						} else if (t < 0) {
							var s = this._position.pop();
							this._position.unshift(s)
						}
					}
				}, {
					key: "_setOffset",
					value: function () {
						var t = this,
							e = t._position.indexOf(t._current);
						t._offset = [], _(t.$items).forEach(function (s, i) {
							t._offset.push((i - e) * t._distance)
						})
					}
				}, {
					key: "_setTransition",
					value: function (t) {
						var e = "none" === (t = t || this._options.duration || "none") ? "none" : t + "ms";
						_(this.$items).forEach(function (t, s) {
							t.style.webkitTransition = e, t.style.transition = e
						})
					}
				}, {
					key: "_setTransform",
					value: function (t) {
						var e = this;
						t = t || 0, _(e.$items).forEach(function (s, i) {
							var a = e._offset[i] + t,
								n = "translate3d(" + a + "px, 0, 0)";
							"vertical" === e._options.direction && (n = "translate3d(0, " + a + "px, 0)"), s.style.webkitTransform = n, s.style.transform = n, e._isMoved = !0
						})
					}
				}, {
					key: "_bind",
					value: function () {
						var t = this,
							e = this;
						e.touchstartHandler = function (t) {
							e.stop(), e._start.x = t.changedTouches[0].pageX, e._start.y = t.changedTouches[0].pageY, e._setTransition("none"), e._isMoved = !1
						}, e.touchmoveHandler = function (s) {
							if (1 !== e.count) {
								e._move.x = s.changedTouches[0].pageX, e._move.y = s.changedTouches[0].pageY;
								var i = e._move.x - e._start.x,
									a = e._move.y - e._start.y,
									n = a,
									o = Math.abs(i) > Math.abs(a);
								"horizontal" === e._options.direction && o && (n = i), t._options.loop || t._current !== t.count - 1 && 0 !== t._current || (n /= 3), ((e._options.minMovingDistance && Math.abs(n) >= e._options.minMovingDistance || !e._options.minMovingDistance) && o || e._isMoved) && e._setTransform(n), o && s.preventDefault()
							}
						}, e.touchendHandler = function (t) {
							if (1 !== e.count) {
								e._end.x = t.changedTouches[0].pageX, e._end.y = t.changedTouches[0].pageY;
								var s = e._end.y - e._start.y;
								"horizontal" === e._options.direction && (s = e._end.x - e._start.x), 0 !== (s = e.getDistance(s)) && e._options.minMovingDistance && Math.abs(s) < e._options.minMovingDistance && !e._isMoved || (s > e._options.threshold ? e.move(-1) : s < -e._options.threshold ? e.move(1) : e.move(0), e._loopRender())
							}
						}, e.transitionEndHandler = function (t) {
							e._activate(e._current);
							var s = e._eventHandlers.swiped;
							s && s.apply(e, [e._prev % e.count, e._current % e.count]), e._auto(), e._loopRender(), t.preventDefault()
						}, e.$container.addEventListener("touchstart", e.touchstartHandler, !1), e.$container.addEventListener("touchmove", e.touchmoveHandler, !1), e.$container.addEventListener("touchend", e.touchendHandler, !1), e.$items[1] && e.$items[1].addEventListener("webkitTransitionEnd", e.transitionEndHandler, !1)
					}
				}, {
					key: "_loopRender",
					value: function () {
						var t = this;
						t._loop() && (0 === t._offset[t._offset.length - 1] ? (t.$container.appendChild(t.$items[0]), t._loopEvent(1)) : 0 === t._offset[0] && (t.$container.insertBefore(t.$items[t.$items.length - 1], t.$container.firstChild), t._loopEvent(-1)))
					}
				}, {
					key: "_loopEvent",
					value: function (t) {
						var e = this;
						e._itemDestoy(), e.$items = e.$container.querySelectorAll(e._options.item), e.$items[1] && e.$items[1].addEventListener("webkitTransitionEnd", e.transitionEndHandler, !1), e._movePosition(t), e._setOffset(), e._setTransform()
					}
				}, {
					key: "getDistance",
					value: function (t) {
						return this._loop() ? t : t > 0 && 0 === this._current ? 0 : t < 0 && this._current === this.realCount - 1 ? 0 : t
					}
				}, {
					key: "_moveIndex",
					value: function (t) {
						0 !== t && (this._prev = this._current, this._current += this.realCount, this._current += t, this._current %= this.realCount)
					}
				}, {
					key: "_activate",
					value: function (t) {
						var e = this._options.activeClass;
						Array.prototype.forEach.call(this.$items, function (s, i) {
							s.classList.remove(e), t === Number(s.dataset.index) && s.classList.add(e)
						})
					}
				}, {
					key: "go",
					value: function (t) {
						var e = this;
						return e.stop(), t = t || 0, t += this.realCount, t %= this.realCount, t = this._position.indexOf(t) - this._position.indexOf(this._current), e._moveIndex(t), e._setOffset(), e._setTransition(), e._setTransform(), e._auto(), this
					}
				}, {
					key: "next",
					value: function () {
						return this.move(1), this
					}
				}, {
					key: "move",
					value: function (t) {
						return this.go(this._current + t), this
					}
				}, {
					key: "on",
					value: function (t, e) {
						return this._eventHandlers[t] && console.error("[swiper] event " + t + " is already register"), "function" != typeof e && console.error("[swiper] parameter callback must be a function"), this._eventHandlers[t] = e, this
					}
				}, {
					key: "_itemDestoy",
					value: function () {
						var t = this;
						this.$items.length && _(this.$items).forEach(function (e) {
							e.removeEventListener("webkitTransitionEnd", t.transitionEndHandler, !1)
						})
					}
				}, {
					key: "destroy",
					value: function () {
						if (this.stop(), this._current = 0, this._setTransform(0), window.removeEventListener("orientationchange", this.resizeHandler, !1), this.$container.removeEventListener("touchstart", this.touchstartHandler, !1), this.$container.removeEventListener("touchmove", this.touchmoveHandler, !1), this.$container.removeEventListener("touchend", this.touchendHandler, !1), this._itemDestoy(), this._options.loop && 2 === this.count) {
							var t = this.$container.querySelector(this._options.item + "-clone");
							t && this.$container.removeChild(t), (t = this.$container.querySelector(this._options.item + "-clone")) && this.$container.removeChild(t)
						}
					}
				}]), t
			}(),
			f = s("7+S+"),
			m = (Array, String, Boolean, Boolean, String, String, Boolean, Boolean, Number, Number, Number, String, Number, Number, Number, {
				name: "swiper",
				created: function () {
					this.index = this.value || 0, this.index && (this.current = this.index)
				},
				mounted: function () {
					var t = this;
					this.hasTwoLoopItem(), this.$nextTick(function () {
						t.list && 0 === t.list.length || t.render(t.index), t.xheight = t.getHeight(), t.$emit("on-get-height", t.xheight)
					})
				},
				methods: {
					hasTwoLoopItem: function () {
						2 === this.list.length && this.loop ? this.listTwoLoopItem = this.list : this.listTwoLoopItem = []
					},
					clickListItem: function (t) {
						Object(f.a)(t.url, this.$router), this.$emit("on-click-list-item", JSON.parse(l()(t)))
					},
					buildBackgroundUrl: function (t) {
						return t.fallbackImg ? "url(" + t.img + "), url(" + t.fallbackImg + ")" : "url(" + t.img + ")"
					},
					render: function () {
						var t = this,
							e = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : 0;
						this.swiper && this.swiper.destroy(), this.swiper = new p({
							container: this.$el,
							direction: this.direction,
							auto: this.auto,
							loop: this.loop,
							interval: this.interval,
							threshold: this.threshold,
							duration: this.duration,
							height: this.height || this._height,
							minMovingDistance: this.minMovingDistance,
							imgList: this.imgList
						}).on("swiped", function (e, s) {
							t.current = s % t.length, t.index = s % t.length
						}), e > 0 && this.swiper.go(e)
					},
					rerender: function () {
						var t = this;
						this.$el && !this.hasRender && (this.hasRender = !0, this.hasTwoLoopItem(), this.$nextTick(function () {
							t.index = t.value || 0, t.current = t.value || 0, t.length = t.list.length || t.$children.length, t.destroy(), t.render(t.value)
						}))
					},
					destroy: function () {
						this.hasRender = !1, this.swiper && this.swiper.destroy()
					},
					getHeight: function () {
						var t = parseInt(this.height, 10);
						return t ? this.height : t ? void 0 : this.aspectRatio ? this.$el.offsetWidth * this.aspectRatio + "px" : "180px"
					}
				},
				props: {
					list: {
						type: Array,
						default: function () {
							return []
						}
					},
					direction: {
						type: String,
						default: "horizontal"
					},
					showDots: {
						type: Boolean,
						default: !0
					},
					showDescMask: {
						type: Boolean,
						default: !0
					},
					dotsPosition: {
						type: String,
						default: "right"
					},
					dotsClass: String,
					auto: Boolean,
					loop: Boolean,
					interval: {
						type: Number,
						default: 3e3
					},
					threshold: {
						type: Number,
						default: 50
					},
					duration: {
						type: Number,
						default: 300
					},
					height: {
						type: String,
						default: "auto"
					},
					aspectRatio: Number,
					minMovingDistance: {
						type: Number,
						default: 0
					},
					value: {
						type: Number,
						default: 0
					}
				},
				data: function () {
					return {
						hasRender: !1,
						current: this.index || 0,
						xheight: "auto",
						length: this.list.length,
						index: 0,
						listTwoLoopItem: []
					}
				},
				watch: {
					auto: function (t) {
						t ? this.swiper && this.swiper._auto() : this.swiper && this.swiper.stop()
					},
					list: function (t, e) {
						l()(t) !== l()(e) && this.rerender()
					},
					current: function (t) {
						this.index = t, this.$emit("on-index-change", t)
					},
					index: function (t) {
						var e = this;
						t !== this.current && this.$nextTick(function () {
							e.swiper && e.swiper.go(t)
						}), this.$emit("input", t)
					},
					value: function (t) {
						this.index = t
					}
				},
				beforeDestroy: function () {
					this.destroy()
				}
			}),
			k = {
				render: function () {
					var t = this,
						e = t.$createElement,
						s = t._self._c || e;
					return s("div", {
						staticClass: "vux-slider"
					}, [s("div", {
						staticClass: "vux-swiper",
						style: {
							height: t.xheight
						}
					}, [t._t("default"), t._v(" "), t._l(t.list, function (e, i) {
						return s("div", {
							staticClass: "vux-swiper-item",
							attrs: {
								"data-index": i
							},
							on: {
								click: function (s) {
									t.clickListItem(e)
								}
							}
						}, [s("a", {
							attrs: {
								href: "javascript:"
							}
						}, [s("div", {
							staticClass: "vux-img",
							style: {
								backgroundImage: t.buildBackgroundUrl(e)
							}
						}), t._v(" "), t.showDescMask ? s("p", {
							staticClass: "vux-swiper-desc"
						}, [t._v(t._s(e.title))]) : t._e()])])
					}), t._v(" "), t._l(t.listTwoLoopItem, function (e, i) {
						return t.listTwoLoopItem.length > 0 ? s("div", {
							staticClass: "vux-swiper-item vux-swiper-item-clone",
							attrs: {
								"data-index": i
							},
							on: {
								click: function (s) {
									t.clickListItem(e)
								}
							}
						}, [s("a", {
							attrs: {
								href: "javascript:"
							}
						}, [s("div", {
							staticClass: "vux-img",
							style: {
								backgroundImage: t.buildBackgroundUrl(e)
							}
						}), t._v(" "), t.showDescMask ? s("p", {
							staticClass: "vux-swiper-desc"
						}, [t._v(t._s(e.title))]) : t._e()])]) : t._e()
					})], 2), t._v(" "), s("div", {
						directives: [{
							name: "show",
							rawName: "v-show",
							value: t.showDots,
							expression: "showDots"
						}],
						class: [t.dotsClass, "vux-indicator", "vux-indicator-" + t.dotsPosition]
					}, t._l(t.length, function (e) {
						return s("a", {
							attrs: {
								href: "javascript:"
							}
						}, [s("i", {
							staticClass: "vux-icon-dot",
							class: {
								active: e - 1 === t.current
							}
						})])
					}))])
				},
				staticRenderFns: []
			};
		var x = s("vSla")(m, k, !1, function (t) {
				s("uQuL")
			}, null, null).exports,
			b = {
				render: function () {
					var t = this.$createElement;
					return (this._self._c || t)("div", {
						staticClass: "vux-swiper-item"
					}, [this._t("default")], 2)
				},
				staticRenderFns: []
			},
			y = s("vSla")({
				name: "swiper-item",
				mounted: function () {
					var t = this;
					this.$nextTick(function () {
						t.$parent.rerender()
					})
				},
				beforeDestroy: function () {
					var t = this.$parent;
					this.$nextTick(function () {
						t.rerender()
					})
				}
			}, b, !1, null, null, null).exports,
			C = s("9rMa"),
			w = (n.a, o.a, a()({}, Object(C.c)(["accountData", "getSignObj"])), a()({}, Object(C.b)(["SET_ACCOUNT_DATA"]), {
				toggleRule: function (t) {
					var e = this,
						s = {};
					(s = this.taskData[this.curIndex]).data[t].seen = !s.data[t].seen, this.$set(this.taskData, this.curIndex, s), this.$nextTick(function () {}), setTimeout(function () {
						e.$refs.scroll[e.curIndex].refresh()
					}, 500)
				},
				_getTaskData: function (t) {
					var e = this;
					if (!JSON.parse(localStorage.getItem("isUser"))) return this.taskData = [], void this.$vux.toast.show({
						text: "Visitors have no permission, please register first"
					});
					var s = {
						token: localStorage.getItem("token")
					};
					t && this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=app&a=taskIndex", s).then(function (s) {
						if (t ? e.$vux.loading.hide() : e.$refs.scroll[e.curIndex].forceUpdate(!0), 0 == s.status) {
							var i = [],
								a = [],
								n = e;
							n.taskData = s.data, s.data[0].data[0].betting_need_money && (a = s.data[0].data);
							for (var o = 0; o < n.taskData.length; o++)
								for (var r = 0; r < n.taskData[o].data.length; r++) n.taskData[o].data[r].seen = !1;
							for (r = 0; r < n.taskData.length; r++) {
								var l = n.taskData[r];
								l.data = n.taskSort(l.data), i.push(l)
							}
							a.length && a[0].betting_need_money && (i[0].data = a), n.taskData = i
						}
					}).catch(function (t) {
						e.$refs.scroll[e.curIndex].forceUpdate(!1), e.$vux.loading.hide(), console.log(t)
					})
				},
				taskSort: function (t) {
					for (var e = [], s = 0, i = 0; i < t.length; i++) 1 == t[i].task_state && (e.splice(0, 0, t[i]), s++), 2 == t[i].task_state && (e.splice(s, 0, t[i]), s++), 3 == t[i].task_state && e.splice(e.length, 0, t[i]);
					return e
				},
				getAward: function (t, e, s) {
					var i = this;
					if (t) {
						var a = {
							token: localStorage.getItem("token"),
							id: t
						};
						this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=app&a=receiveTaskReward", a).then(function (t) {
							if (i.$vux.loading.hide(), 0 == t.status) {
								var a = i;
								i.$vux.alert.show({
									content: '<p style="color:#000">Congratulations on your acquisition<span class="text-red" style="margin:0 4px">' + s + '</span> coins<i class="icon-wallet-gold" style="margin-left:4px"></i></p>',
									onHide: function () {
										var t = {};
										(t = a.taskData[a.curIndex]).data[e].task_state = 3, t.data = a.taskSort(t.data), a.$set(a.taskData, a.curIndex, t), a.taskSort(a.taskData[a.curIndex].data);
										var i = a.accountData;
										i.money_usable = Number(i.money_usable), i.money_usable += Number(s), a.SET_ACCOUNT_DATA({
											Obj: i
										})
									}
								})
							} else t.ret_msg && "" != t.ret_msg && i.$vux.toast.show({
								text: t.ret_msg
							})
						})
					} else this.$vux.toast.show({
						text: "Failed to claim. Please refresh and try again"
					})
				},
				pullingDown: function () {
					this._getTaskData(0)
				},
				hasFinish: function (t) {
					for (var e = 0; e < this.taskData.length; e++)
						for (var s = 0; s < this.taskData[e].data.length; s++)
							if (1 == this.taskData[e].data[s].task_state && e == t || 7 == this.taskData[e].data[s].id && 2 == this.taskData[e].data[s].task_state && e == t) return !0;
					return !1
				},
				goHome: function () {
					this.$router.push({
						path: "/home"
					})
				},
				signInClose: function () {
					this.signInFlag = !1
				},
				_getSignCont: function (t) {
					var e = this;
					this.signFlag = t;
					var s = {
						token: localStorage.getItem("token")
					};
					this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=app&a=getSignCont", s).then(function (t) {
						if (e.$vux.loading.hide(), e.signStatus = t, -1 != t.status && 0 == t.status) {
							if (1 == t.data.is_sign) return;
							var s = e.getSignObj;
							if (s.signCurrent = t.data.count, s.signState = t.data.is_sign, e.signMoneyBal = t.data.money_bal, e.signMoney = t.data.money, e.$store.commit("setSignObj", s), 0 == s.signState) {
								var i = e;
								setTimeout(function () {
									i.signInFlag = !0
								}, 20)
							}
						}
					}).catch(function (t) {
						e.$vux.loading.hide(), console.log(t)
					})
				},
				taskSign: function (t) {
					var e = this;
					if (0 != this.getSignObj.signState) return !1;
					if (t && t - this.getSignObj.signCurrent != 1) return !1;
					var s = {
						token: localStorage.getItem("token"),
						type: 7
					};
					this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=app&a=taskSign", s).then(function (t) {
						if (e.$vux.loading.hide(), 0 == t.status) {
							var s = e.getSignObj;
							s.signState = 1, s.signCurrent += 1, e.$store.commit("setSignObj", s);
							var i = e.accountData;
							i.money_usable = Number(i.money_usable), i.money_usable += Number(e.getSignObj.signCurrent < 7 ? e.signMoney : e.signMoneyBal), console.log(i.money_usable), e.SET_ACCOUNT_DATA({
								Obj: i
							}), e.signInOKFlag = !0;
							var a = e,
								n = {};
							(n = a.taskData[a.curIndex]).data[e.signFlag].task_state = 3, n.data = a.taskSort(n.data), a.$set(a.taskData, a.curIndex, n), a.taskSort(a.taskData[a.curIndex].data);
							var o = e;
							setTimeout(function () {
								o.signInFlag = !1, o.signInOKFlag = !1
							}, 3e3)
						} else t.ret_msg && "" != t.ret_msg && e.$vux.toast.show({
							text: t.ret_msg
						})
					}).catch(function (t) {
						e.$vux.loading.hide(), console.log(t)
					})
				}
			}), {
				components: {
					Tab: n.a,
					TabItem: o.a,
					Swiper: x,
					SwiperItem: y
				},
				data: function () {
					return {
						taskData: [],
						taskList: [],
						curIndex: 0,
						pullDownRefresh: !0,
						signStatus: [],
						signInFlag: !1,
						signInOKFlag: !1,
						signMoneyBal: 0,
						signMoney: 0
					}
				},
				created: function () {},
				mounted: function () {
					this._getTaskData(1)
				},
				computed: a()({}, Object(C.c)(["accountData", "getSignObj"])),
				methods: a()({}, Object(C.b)(["SET_ACCOUNT_DATA"]), {
					toggleRule: function (t) {
						var e = this,
							s = {};
						(s = this.taskData[this.curIndex]).data[t].seen = !s.data[t].seen, this.$set(this.taskData, this.curIndex, s), this.$nextTick(function () {}), setTimeout(function () {
							e.$refs.scroll[e.curIndex].refresh()
						}, 500)
					},
					_getTaskData: function (t) {
						var e = this;
						if (!JSON.parse(localStorage.getItem("isUser"))) return this.taskData = [], void this.$vux.toast.show({
							text: "Visitors have no permission, please register first"
						});
						var s = {
							token: localStorage.getItem("token")
						};
						t && this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=app&a=taskIndex", s).then(function (s) {
							if (t ? e.$vux.loading.hide() : e.$refs.scroll[e.curIndex].forceUpdate(!0), 0 == s.status) {
								var i = [],
									a = [],
									n = e;
								n.taskData = s.data, s.data[0].data[0].betting_need_money && (a = s.data[0].data);
								for (var o = 0; o < n.taskData.length; o++)
									for (var r = 0; r < n.taskData[o].data.length; r++) n.taskData[o].data[r].seen = !1;
								for (r = 0; r < n.taskData.length; r++) {
									var l = n.taskData[r];
									l.data = n.taskSort(l.data), i.push(l)
								}
								a.length && a[0].betting_need_money && (i[0].data = a), n.taskData = i
							}
						}).catch(function (t) {
							e.$refs.scroll[e.curIndex].forceUpdate(!1), e.$vux.loading.hide(), console.log(t)
						})
					},
					taskSort: function (t) {
						for (var e = [], s = 0, i = 0; i < t.length; i++) 1 == t[i].task_state && (e.splice(0, 0, t[i]), s++), 2 == t[i].task_state && (e.splice(s, 0, t[i]), s++), 3 == t[i].task_state && e.splice(e.length, 0, t[i]);
						return e
					},
					getAward: function (t, e, s) {
						var i = this;
						if (t) {
							var a = {
								token: localStorage.getItem("token"),
								id: t
							};
							this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=app&a=receiveTaskReward", a).then(function (t) {
								if (i.$vux.loading.hide(), 0 == t.status) {
									var a = i;
									i.$vux.alert.show({
										content: '<p style="color:#000">Congratulations on your acquisition<span class="text-red" style="margin:0 4px">' + s + '</span> coins<i class="icon-wallet-gold" style="margin-left:4px"></i></p>',
										onHide: function () {
											var t = {};
											(t = a.taskData[a.curIndex]).data[e].task_state = 3, t.data = a.taskSort(t.data), a.$set(a.taskData, a.curIndex, t), a.taskSort(a.taskData[a.curIndex].data);
											var i = a.accountData;
											i.money_usable = Number(i.money_usable), i.money_usable += Number(s), a.SET_ACCOUNT_DATA({
												Obj: i
											})
										}
									})
								} else t.ret_msg && "" != t.ret_msg && i.$vux.toast.show({
									text: t.ret_msg
								})
							})
						} else this.$vux.toast.show({
							text: "Failed to claim. Please refresh and try again"
						})
					},
					pullingDown: function () {
						this._getTaskData(0)
					},
					hasFinish: function (t) {
						for (var e = 0; e < this.taskData.length; e++)
							for (var s = 0; s < this.taskData[e].data.length; s++)
								if (1 == this.taskData[e].data[s].task_state && e == t || 7 == this.taskData[e].data[s].id && 2 == this.taskData[e].data[s].task_state && e == t) return !0;
						return !1
					},
					goHome: function () {
						this.$router.push({
							path: "/home"
						})
					},
					signInClose: function () {
						this.signInFlag = !1
					},
					_getSignCont: function (t) {
						var e = this;
						this.signFlag = t;
						var s = {
							token: localStorage.getItem("token")
						};
						this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=app&a=getSignCont", s).then(function (t) {
							if (e.$vux.loading.hide(), e.signStatus = t, -1 != t.status && 0 == t.status) {
								if (1 == t.data.is_sign) return;
								var s = e.getSignObj;
								if (s.signCurrent = t.data.count, s.signState = t.data.is_sign, e.signMoneyBal = t.data.money_bal, e.signMoney = t.data.money, e.$store.commit("setSignObj", s), 0 == s.signState) {
									var i = e;
									setTimeout(function () {
										i.signInFlag = !0
									}, 20)
								}
							}
						}).catch(function (t) {
							e.$vux.loading.hide(), console.log(t)
						})
					},
					taskSign: function (t) {
						var e = this;
						if (0 != this.getSignObj.signState) return !1;
						if (t && t - this.getSignObj.signCurrent != 1) return !1;
						var s = {
							token: localStorage.getItem("token"),
							type: 7
						};
						this.$vux.loading.show(), this.$http.post(this.urlRequest + "?m=api&c=app&a=taskSign", s).then(function (t) {
							if (e.$vux.loading.hide(), 0 == t.status) {
								var s = e.getSignObj;
								s.signState = 1, s.signCurrent += 1, e.$store.commit("setSignObj", s);
								var i = e.accountData;
								i.money_usable = Number(i.money_usable), i.money_usable += Number(e.getSignObj.signCurrent < 7 ? e.signMoney : e.signMoneyBal), console.log(i.money_usable), e.SET_ACCOUNT_DATA({
									Obj: i
								}), e.signInOKFlag = !0;
								var a = e,
									n = {};
								(n = a.taskData[a.curIndex]).data[e.signFlag].task_state = 3, n.data = a.taskSort(n.data), a.$set(a.taskData, a.curIndex, n), a.taskSort(a.taskData[a.curIndex].data);
								var o = e;
								setTimeout(function () {
									o.signInFlag = !1, o.signInOKFlag = !1
								}, 3e3)
							} else t.ret_msg && "" != t.ret_msg && e.$vux.toast.show({
								text: t.ret_msg
							})
						}).catch(function (t) {
							e.$vux.loading.hide(), console.log(t)
						})
					}
				}),
				watch: {}
			}),
			S = {
				render: function () {
					var t = this,
						e = t.$createElement,
						i = t._self._c || e;
					return i("div", [i("div", {
						staticClass: "headerWrap"
					}, [i("x-header", {
						staticClass: "header"
					}, [t._v("Mission Center")])], 1), t._v(" "), i("div", {
						staticClass: "h5-tab-wrap task-wrap"
					}, [i("div", {
						staticClass: "tab-public tab-fixed"
					}, [i("tab", {
						staticClass: "top-tabNav",
						attrs: {
							"line-width": 2
						},
						model: {
							value: t.curIndex,
							callback: function (e) {
								t.curIndex = e
							},
							expression: "curIndex"
						}
					}, t._l(t.taskData, function (e, s) {
						return i("tab-item", {
							key: s,
							attrs: {
								selected: e === s
							}
						}, [i("span", [t._v(t._s(e.name == "有奖任务" ? "Rewarding" : e.name == "今日任务" ? "Today tasks" : "Accomplished")), t.hasFinish(s) ? i("i") : t._e()])])
					}))], 1), t._v(" "), i("div", {
						staticClass: "swiper-wrap"
					}, [i("swiper", {
						attrs: {
							"show-dots": !1
						},
						model: {
							value: t.curIndex,
							callback: function (e) {
								t.curIndex = e
							},
							expression: "curIndex"
						}
					}, t._l(t.taskData, function (e, a) {
						return i("swiper-item", {
							key: a
						}, [e.data.length ? i("scroll", {
							ref: "scroll",
							refInFor: !0,
							staticClass: "task-scroll",
							attrs: {
								pullDownRefresh: t.pullDownRefresh
							},
							on: {
								pullingDown: t.pullingDown
							}
						}, [i("div", {
							staticClass: "tab-swiper taskList"
						}, [0 == a && e.data[0].betting_need_money ? i("div", {
							staticClass: "actRuleCon"
						}, [i("h1", {
							staticClass: "dialog-tit"
						}, [t._v("Description of activity rules")]), t._v(" "), i("p", [t._v("Complete any of the following tasks to participate in the platform routine tasks and daily check-in activities：")]), t._v(" "), i("p", {
							staticClass: "text-gray"
						}, [t._v("1. Accumulated deposit of"), i("span", {
							staticClass: "text-red"
						}, [t._v(t._s(e.data[0].recharge_need_money))]), t._v("$, the current cumulative deposit"), i("span", {
							staticClass: "text-red"
						}, [t._v(t._s(e.data[0].recharge_money))]), t._v("$, not enough"), i("span", {
							staticClass: "text-red"
						}, [t._v(t._s(e.data[0].recharge_need_money - e.data[0].recharge_money))]), t._v("$")]), t._v(" "), i("p", {
							staticClass: "text-gray"
						}, [t._v("2. Cumulative amount of bets on the day"), i("span", {
							staticClass: "text-red"
						}, [t._v(t._s(e.data[0].betting_need_money))]), t._v("$, the current cumulative bet"), i("span", {
							staticClass: "text-red"
						}, [t._v(t._s(e.data[0].betting_money))]), t._v("$, not enough"), i("span", {
							staticClass: "text-red"
						}, [t._v(t._s(e.data[0].betting_need_money - e.data[0].betting_money))]), t._v("$")])]) : t._l(e.data, function (e, a) {
							return i("dl", {
								key: a
							}, [i("dt", {
								class: [1 == e.id ? "No1" : 2 == e.id ? "No2" : 3 == e.id ? "No3" : 4 == e.id ? "No4" : 5 == e.id ? "No5" : 6 == e.id ? "No6" : 7 == e.id ? "No7" : 8 == e.id ? "No8" : 9 == e.id ? "No9" : e.id > 9 && e.id < 20 ? "No10" : 24 == e.id ? "No10" : 20 == e.id ? "No11" : 21 == e.id ? "No12" : 22 == e.id ? "No13" : ""]
							}, [i("img", {
								attrs: {
									src: s("S9x0"),
									alt: ""
								}
							})]), t._v(" "), i("dd", [i("h5", [t._v(t._s(e.name))]), t._v(" "), i("p", {
								staticClass: "money text-red"
							}, [t._v(t._s(e.money) + " coins")]), t._v(" "), i("div", {
								staticClass: "ruleToggle",
								class: [e.seen ? "ruleActive" : ""],
								on: {
									click: function (e) {
										t.toggleRule(a)
									}
								}
							}, [i("span", {
								staticClass: "ruleTit"
							}, [t._v("Click to view the rules"), i("i", {
								staticClass: "arrow"
							})]), t._v(" "), i("p", {
								staticClass: "ruleCon",
								domProps: {
									innerHTML: t._s(e.explain)
								}
							})]), t._v(" "), i("div", {
								staticClass: "taskRight"
							}, [i("div", {
								staticClass: "taskState"
							}, [3 != e.task_state ? [7 == e.id && 0 == t.getSignObj.signState ? i("p", [i("em", {
								staticClass: "text-red"
							}, [t._v(t._s(e.count))]), t._v("/7")]) : t._e()] : t._e(), t._v(" "), 2 == e.task_state ? [8 == e.id && 2 == e.task_state ? i("p", [i("em", {
								staticClass: "text-red"
							}, [t._v("0")]), t._v("/1")]) : 8 == e.id && 1 == e.task_state ? i("p", [i("em", {
								staticClass: "text-red"
							}, [t._v("1")]), t._v("/1")]) : 9 == e.id ? i("p", [i("em", {
								staticClass: "text-red"
							}, [t._v(t._s(e.recharge))]), t._v("/" + t._s(e.money_bal)), i("i", {
								staticClass: "icon-ingot"
							})]) : 10 == e.id || 11 == e.id || 12 == e.id || 13 == e.id || 14 == e.id || 15 == e.id || 16 == e.id || 17 == e.id || 18 == e.id || 19 == e.id || 24 == e.id || 25 == e.id || 26 == e.id || 27 == e.id || 28 == e.id || 29 == e.id || 30 == e.id || 31 == e.id || 32 == e.id ? i("p", [i("em", {
								staticClass: "text-red"
							}, [t._v(t._s(e.betting))]), t._v("/" + t._s(e.money_bal)), i("i", {
								staticClass: "icon-ingot"
							})]) : 20 == e.id || 21 == e.id || 22 == e.id || 23 == e.id ? i("p", [i("em", {
								staticClass: "text-red"
							}, [t._v(t._s(e.complete_task))]), t._v("/" + t._s(e.total_task))]) : t._e()] : t._e()], 2), t._v(" "), 1 == e.task_state ? i("a", {
								staticClass: "taskBtn taskBtn-red",
								attrs: {
									href: "javascript:void(0);"
								},
								on: {
									click: function (s) {
										t.getAward(e.task_prize_id, a, e.money)
									}
								}
							}, [t._v("receive")]) : 2 == e.task_state && e.id < 7 ? i("router-link", {
								staticClass: "taskBtn taskBtn-yellow",
								attrs: {
									to: {
										path: 1 == e.id ? "/bank" : 2 == e.id ? "/personal/revisePage?type=qq&qq=" : 3 == e.id ? "/personal/revisePage?type=weixin&weixin=" : 4 == e.id ? "/personal/revisePage?type=email&email=" : 5 == e.id ? "/personal/revisePage?type=nickname&nickname=" : 6 == e.id ? "/personal" : ""
									}
								}
							}, [5 == e.id || 6 == e.id ? i("span", [t._v("To modify")]) : i("span", [t._v("Debinding")])]) : 2 != e.task_state || 20 != e.id && 21 != e.id && 22 != e.id ? 2 == e.task_state && 7 == e.id && 0 == t.getSignObj.signState ? i("a", {
								staticClass: "taskBtn taskBtn-red",
								attrs: {
									href: "javascript:void(0);"
								},
								on: {
									click: function (e) {
										t._getSignCont(a)
									}
								}
							}, [t._v("Sign in")]) : 2 == e.task_state && 8 == e.id || 2 == e.task_state && 9 == e.id ? i("router-link", {
								staticClass: "taskBtn taskBtn-yellow",
								attrs: {
									to: {
										path: "/recharge"
									}
								}
							}, [t._v("Deposit")]) : 2 == e.task_state && e.id > 9 && e.id < 20 || 2 == e.task_state && e.id > 23 ? i("a", {
								staticClass: "taskBtn taskBtn-yellow",
								attrs: {
									href: "javascript:void(0);"
								},
								on: {
									click: t.goHome
								}
							}, [t._v("Bet")]) : 3 == e.task_state || 7 == e.id && 1 == t.getSignObj.signState ? i("a", {
								staticClass: "taskBtn taslBtn-complete",
								attrs: {
									href: "javascript:void(0);"
								}
							}, [t._v("Completed")]) : t._e() : i("a", {
								staticClass: "taskBtn taskBtn-gray",
								attrs: {
									href: "javascript:void(0);"
								}
							}, [t._v("receive")])], 1)])])
						}), t._v(" "), 2 == a ? i("p", {
							staticClass: "tips"
						}, [t._v("\n                            Note: the total number of tasks completed is calculated according to the number of tasks completed today, and will be reset the next day. All the unclaimed rewards are invalid. The final interpretation right of this activity belongs to the company\n                        ")]) : t._e()], 2)]) : [i("img", {
							staticClass: "noDataImg",
							attrs: {
								src: s("w+73"),
								alt: ""
							}
						})]], 2)
					}))], 1)]), t._v(" "), i("x-dialog", {
						staticClass: "global-dialog signIn-dialog",
						model: {
							value: t.signInFlag,
							callback: function (e) {
								t.signInFlag = e
							},
							expression: "signInFlag"
						}
					}, [i("div", {
						staticClass: "dialog-content"
					}, [i("div", {
						on: {
							click: t.signInClose
						}
					}, [i("span", {
						staticClass: "vux-close"
					})]), t._v(" "), i("h1", {
						staticClass: "dialog-tit"
					}), t._v(" "), i("div", {
						staticClass: "dialog-con signIn"
					}, [i("ul", t._l(7, function (e, s) {
						return t.signStatus.data ? i("li", {
							key: s,
							class: [t.getSignObj.signCurrent - (s + 1) >= 0 ? "signed" : t.signStatus.data.count == s ? "signCur" : ""],
							on: {
								click: function (e) {
									t.taskSign(s + 1)
								}
							}
						}, [i("span", [t._v("The first" + t._s(s + 1) + "day")]), t._v(" "), i("p", {
							class: [6 == s ? "gift" : "ingot"]
						}), t._v(" "), i("span", [t._v(t._s(t.getSignObj.signCurrent - (s + 1) >= 0 ? "Received" : 6 == s ? "Mysterious gift" : t.signMoney + " coins"))])]) : t._e()
					})), t._v(" "), i("x-button", {
						staticClass: "weui-btn_minRadius",
						attrs: {
							type: "warn"
						},
						nativeOn: {
							click: function (e) {
								t.taskSign()
							}
						}
					}, [t._v("Receive rewards")])], 1)])]), t._v(" "), i("x-dialog", {
						staticClass: "global-dialog signInOK-dialog",
						model: {
							value: t.signInOKFlag,
							callback: function (e) {
								t.signInOKFlag = e
							},
							expression: "signInOKFlag"
						}
					}, [i("p", {
						class: [t.getSignObj.signCurrent < 7 ? "ingot" : "gift"]
					}), t._v(" "), i("span", [t._v("+ " + t._s(t.getSignObj.signCurrent < 7 ? t.signMoney : t.signMoneyBal) + " coins")])])], 1)
				},
				staticRenderFns: []
			};
		var A = s("vSla")(w, S, !1, function (t) {
			s("afH5")
		}, "data-v-7dcdf55e", null);
		e.default = A.exports
	},
	S9x0: function (t, e) {
		t.exports = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAFYAAABWCAYAAABVVmH3AAAE60lEQVR4AezBAQ0AAAjAIO0f+uZwAwYAAAAAAOCHao99cwCWJIfjcI2x02Ocbdu2WVjbtm3btm3btm1b4/ulKl0vD92zew/zcpdUfYvMv3uTbzPxA+3BNaCWQmAW8AlriZMmEon8Er+/1Jc8J9SpSKVii6U1F4vFjiG/P35fmMFn0/CcVsjNIN2+ffsROOoajUa7QdQCVhyEruzcubMbYWbCjRs3yqQRe5g8R0Dsj8Im01JDodCbcYV07NixXxBjY4HEAxnFIr/Z/6blor4vgUagaVpOnDhhISJu3br1lpLYvXv3/oAYO5Ao9nA4vF1FrJZ2KX9m9G+ChuAZ7lsjKnFUSRrEuhGju3r16ttKMXfv3p316quvumSpu3fv/ob0ABnFQnhz8j6gheRhceV0hOeWraFiFdOePXs8iNGfOXPmXbU4CNt07dq1lnfu3BmCPvW2UlwwGGyB9xmAXlUsklw+HqVqgU6tcsuXL/chxoiW+148CxLEt8T7TMCArmB4ArE6HmcTGlpwvVrlpk2bFkCM+cCBAx9khVj01a3o7MGEAXFEArF6WkYNb61VD4xqlRs/fvwDiLGOHDnykXPnzpU7depUBbTeijLk7ySfcOHChbIEtbgdO3Z8RN4HLOgWRiYQawR6udXyJtakVrlBgwY9REXkAXbgBG7gobhpnp3BAVxp4lw03washHsQa+JVrAGY1SqHyo8HIwnoH0dhoTCacPPmzTEE+e8sSjHkefldBAxehxOINQMDb2J1tNCWeC5NpGy0jDrexBo5EGsUYoXY5IrFNGspFhF3hNislbrio48+8q9du/ZLDF7nhNgsSBB5rE2bNs/IU7AhQ4a8gZZ7U4jNRILAWwsXLvxUlkrAoqGOaLGZTPv27SvELChcu3bt+h3ZYSE2E+nixYutmdWXc/78+aQLuCIGr8xtukyTJMlLpdrbtWv3EPrafWK6lbkZwPYCBQo8Ikt9++23Hdi3nSvmsSmj+QXV/lDhGTLqs5sv2C/oJhYIKYIuDRgw4O2VK1f+hj9fvNfGumHDhp/oQOUAEo52SouVF+N18+bNfyPGTxg8ePBb+HrvSiQE+69VmO1E6ejRo18hOyjEptwXOJI/f/7HEeOl+IoWLfoEBiTFfhItsz/iPLLUJUuWvIAZwHmxV5D+kHAXThHeoC2Q4AkEAv5Lly51y+A8a/nTTz/tlwerpk2b+tF9bBObMMqrpss48/qVtkIXFew9dOhQOXwWpK37cMOGDZ+WpXq9Xgn/KVPE7lbiFMHXvFba45nVq1f/AKkH58yZ8yE7A0B30TrxK4VY9qhmRL58+bzsudYzzzzjY6RKWGkVuk+HQiz92m/Asfgz8sEhxQ5suG70MUJuAyH2X+5WnT148OCXRCYlz7x5855E/snMKxVL2iD63bLkHVWrVnWiJa/PvE4hlt0T6APG/AfOvMRhohArxAqxQqwQK8RGcqHXCPdisa7vSfZec5HUKCkTz2INtPAScAEP8AF/kvAxp7kSn7cNmfuxwAYczEVhb5LwMMc5Nlo2A7c3uoEVSPKOVZJx0LJYebzRnbY7MNOK2Gil7ElCYq7Sm3nrBtK2WlmuiVbGkmTMtCwGnn8ciZWrBwaKMUkYKHpWKgWJT7kyuiSjpahL5VBybuGf9uBAAAAAAECQv/UEG1QAAAAAAMAJdZ9AK5IfueQAAAAASUVORK5CYII="
	},
	afH5: function (t, e) {},
	uQuL: function (t, e) {}
});
//# sourceMappingURL=15.19755e9361b4e4a2b9fb.js.map