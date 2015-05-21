String.prototype.strip = function() {
	return this.replace(/^\s+/, '').replace(/\s+$/, '');
};
String.prototype.string = function(len) {
	var s = '', i = 0;
	while (i++ < len)
		s += this;
	
	return s;
};
String.prototype.zf = function(len) {
	return "0".string(len - this.length) + this;
};
Number.prototype.zf = function(len) {
	return this.toString().zf(len);
};

Date.prototype.format = function(f) {
	if (!this.valueOf()) return '';

	var fullWeekName = ["일요일", "월요일", "화요일", "수요일", "목요일", "금요일", "토요일"];
	var weekName = ["일", "월", "화", "수", "목", "금", "토"];
	var d = this;

	return f.replace(/(%Y|%y|%m|%d|%A|%a|%H|%I|%M|%S|%p|%P)/g, function($0, $1) {
		switch ($1) {
			case "%Y": return d.getFullYear();
			case "%y": return (d.getFullYear() % 1000).zf(2);
			case "%m": return (d.getMonth() + 1).zf(2);
			case "%d": return d.getDate().zf(2);
			case "%A": return fullWeekName[d.getDay()];
			case "%a": return weekName[d.getDay()];
			case "%H": return d.getHours().zf(2);
			case "%I": return ((h = d.getHours() % 12) ? h : 12).zf(2);
			case "%M": return d.getMinutes().zf(2);
			case "%S": return d.getSeconds().zf(2);
			case "%p": return d.getHours() < 12 ? "AM" : "PM";
			case "%P": return d.getHours() < 12 ? "am" : "pm";
			default: return $1;
		}
	});
};

window.UCAN = window.UCAN || {};
UCAN.Board = {
	Config: {
		Wrapper: {},
		Pager: {},
		Command: {},
		Indicator: {},
		Editor: {
			Post: {
				height: '400px'
			},
			Comment: {
				height: '100px'
			}
		}
	},
	SiteCode: null,
	Router: null,
	Account: {},
	Permissions: {},
	initialize: function($) {
		if (!UCAN.Board.SiteCode ||
			!UCAN.Board.Config.Wrapper.content ||
			!UCAN.Board.Config.requestProxyUrl ||
			!UCAN.Board.Config.Editor.Post.textareaId ||
			!UCAN.Board.Config.Editor.Comment.textareaId ||
			!UCAN.Board.Config.Pager.current ||
			!UCAN.Board.Config.Pager.page ||
			!UCAN.Board.Config.Pager.first ||
			!UCAN.Board.Config.Pager.last ||
			!UCAN.Board.Indicator.initialize ||
			!UCAN.Board.Indicator.hide ||
			!UCAN.Board.Indicator.show
		) {
			window.alert('게시판 설정 오류입니다. 관리자에게 문의해 주세요.');
			return;
		}

		_.templateSettings = {
			interpolate: /<@=([\s\S]+?)@>/g,
			evaluate: /<@([\s\S]+?)@>/g
		};

		$.ajaxSetup({
			beforeSend: function(xhr) {
				UCAN.Board.Indicator.show();
			},
			complete: function(xhr, status) {
				UCAN.Board.Indicator.hide();
			},
			timeout: 10000,
			cache: false
		});

		var _getAjaxErrorMessage = function(response, message) {
			var msg;
			if (response.status == 401) {
				msg = '권한이 없습니다.\n';
			} else {
				msg = response.statusText.toUpperCase();

				if (response.status > 0) {
					msg += ' ' + response.status;
				}
				msg = '[' + msg + '] ' + message;
			}

			return msg;
		};

		var _onAjaxError = function(model, response) {
			if (response.status == 404) {
				window.alert('게시글을 찾을 수 없습니다.');
			} else if (response.status = 401) {
				window.alert('권한이 없습니다.');
			} else {
				window.alert(_getAjaxErrorMessage(response, '게시글을 가져올 수 없습니다.'));
			}

			UCAN.Board.Indicator.hide();
		};

		$(function() {
			var contentWrapper = $(UCAN.Board.Config.Wrapper.content);

			UCAN.Board.Editor = {
				Post: 1,
				Comment: 2,
				disable: function(target) {
					var textareaId = null;
					if (target == UCAN.Board.Editor.Post) {
						textareaId = UCAN.Board.Config.Editor.Post.textareaId;
					} else if (target == UCAN.Board.Editor.Comment) {
						textareaId = UCAN.Board.Config.Editor.Comment.textareaId;
					} else {
						// 인자가 없을 경우 모두 제거
						for (var textareaId in CKEDITOR.instances) {
							try {
								CKEDITOR.instances[textareaId].destroy();
							} catch (e) {}
						}
						return;
					}

					if (!CKEDITOR.instances[textareaId])
						return;

					try {
						CKEDITOR.instances[textareaId].destroy();
					} catch (e) {}
				},
				enable: function(target, options) {
					if (!target) {
						return;
					}

					var textareaId;
					if (target == UCAN.Board.Editor.Post) {
						textareaId = UCAN.Board.Config.Editor.Post.textareaId;

						if (!UCAN.Board.Permissions.postCreate)
							return;
					} else if (target == UCAN.Board.Editor.Comment) {
						textareaId = UCAN.Board.Config.Editor.Comment.textareaId;

						if (!UCAN.Board.Permissions.commentCreate)
							return;
					} else {
						return;
					}

					if (!!CKEDITOR.instances[textareaId])
						return;

					var config = {
						keystrokes: [],
						language: 'ko',
						toolbar: 'Full',
						resize_dir: 'vertical',
						removePlugins: 'about,find,font,forms,scayt,showblocks,flash,bbcode,adobeair,elementspath',
						toolbar_Full: [
							['Bold','Underline','Italic','Strike','Subscript','Superscript','TextColor','BGColor'],
							['RemoveFormat'],
							['NumberedList','BulletedList','-','Outdent','Indent','Blockquote'],
							['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
							['Link','Unlink'],
							['Image','Table','HorizontalRule'],
							['Source']
						]
					}
					for (var key in options) {
						config[key] = options[key];
					}

					CKEDITOR.replace(textareaId, config);
				},
				getData: function(target) {
					if (target == UCAN.Board.Editor.Post && !!CKEDITOR.instances[UCAN.Board.Config.Editor.Post.textareaId]) {
						return CKEDITOR.instances[UCAN.Board.Config.Editor.Post.textareaId].getData();
					} else if (target == UCAN.Board.Editor.Comment && !!CKEDITOR.instances[UCAN.Board.Config.Editor.Comment.textareaId]) {
						return CKEDITOR.instances[UCAN.Board.Config.Editor.Comment.textareaId].getData();
					} else {
						return;
					}
				}
			};

			var Post = Backbone.Model.extend({
				urlRoot: '/posts',
				url: function() {
					var base = this.urlRoot;
					if (!this.isNew()) {
						base = base + (base.charAt(base.length - 1) == '/' ? '' : '/') + encodeURIComponent(this.id);
					}
					return UCAN.Board.Config.requestProxyUrl + '&url=' + encodeURIComponent(base);
				},
				initialize: function() {
					var created_at = this.get('created_at');
					if (!!created_at) {
						this.set('created_at', new Date(Date.parse(created_at)));
					}
				},
				fetch: function(options) {
					options = options ? _.clone(options) : {};
					var model = this;
					var success = options.success;
					options.success = function(resp, status, xhr) {
						if (!model.set(model.parse(resp.post, xhr), options)) return false;

						var comments = [];
						$.each(model.get('comments'), function(i, c) {
							comments.push(new Comment(c));
						});
						model.set('comments', comments);

						if (success) success(model, resp);
						model.read();
					};
					options.error = Backbone.wrapError(options.error, model, options);
					return (this.sync || Backbone.sync).call(this, 'read', this, options);
				},
				validate: function(attrs) {
					if (!!attrs.title && attrs.title.strip().length == 0) {
						var msg = '제목을 입력해 주세요.';
						window.alert(msg);
						return msg;
					}
					if (!!attrs.body && attrs.body.strip().length == 0) {
						var msg = '본문을 입력해 주세요.';
						window.alert(msg);
						return msg;
					}
				},
				read: function() {
					var reads = $.cookie('reads');
					reads = reads == null ? [] : reads.split(',');

					if (!reads)
						return;

					for (var i in reads)
						if (reads[i] == this.id.toString())
							return;

					while (reads.length > 19) {
						reads.shift();
					}
					reads.push(this.id.toString());

					$.cookie('reads', reads, {expires:1, path:'/'});
				}
			});

			var Posts = Backbone.Collection.extend({
				model: Post,
				page: 1,
				per: 10,
				url: function() {
					return UCAN.Board.Config.requestProxyUrl + '&url=' + encodeURIComponent('/posts');
				},
				initialize: function(options) {
				},
				fetch: function(options) {
					options = options ? _.clone(options) : {};
					if (options.parse === undefined) options.parse = true;
					if (options.data === undefined) options.data = {};
					options.data.per = this.per;

					var collection = this;
					var success = options.success;
					options.success = function(resp, status, xhr) {
						collection[options.add ? 'add' : 'reset'](collection.parse(resp.posts, xhr), options);
						collection.totalPosts = resp.total_posts;
						collection.totalPage = Math.ceil(collection.totalPosts / collection.per);
						collection.page = parseInt(options.data.page);

						if (success) success(collection, resp);
					};
					options.error = Backbone.wrapError(options.error, collection, options);
					return (this.sync || Backbone.sync).call(this, 'read', this, options);
				}
			});

			var Comment = Backbone.Model.extend({
				initialize: function(options) {
					if (typeof options == 'object' && !!options.post_id) {
						this.post_id = options.post_id;
					}
					var created_at = this.get('created_at');
					if (!!created_at) {
						this.set('created_at', new Date(Date.parse(created_at)));
					}
				},
				url: function() {
					var base = '';
					if (!this.isNew()) {
						base = '/comments/' + encodeURIComponent(this.id);
					} else {
						if (!this.post_id) {
							base = '/comments';
						} else {
							base = Post.prototype.urlRoot + '/' + this.post_id + '/comments';
						}
					}
					return UCAN.Board.Config.requestProxyUrl + '&url=' + encodeURIComponent(base);
				},
				validate: function(attrs) {
					if (!!attrs.body && attrs.body.strip().length == 0) {
						var msg = '본문을 입력해 주세요.';
						window.alert(msg);
						return msg;
					}
				},
				fetch: function(options) {
					options = options ? _.clone(options) : {};
					var model = this;
					var success = options.success;
					options.success = function(resp, status, xhr) {
						if (!model.set(model.parse(resp.comment, xhr), options)) return false;
						if (success) success(model, resp);
					};
					options.error = Backbone.wrapError(options.error, model, options);
					return (this.sync || Backbone.sync).call(this, 'read', this, options);
				}
			});

			var Comments = Backbone.Collection.extend({
				model: Comment,
				initialize: function(options) {
					if (typeof options == 'object' && !!options.post_id) {
						this.post_id = options.post_id;
					}
				},
				url: function() {
					if (!!this.post_id) {
						var base = Post.prototype.urlRoot + '/' + this.post_id + '/comments';
						return UCAN.Board.Config.requestProxyUrl + '&url=' + encodeURIComponent(base);
					} else {
						throw 'Post id does not set'
					}
				},
				fetch: function(options) {
					options = options ? _.clone(options) : {};
					if (options.parse === undefined) options.parse = true;
					if (options.data === undefined) options.data = {};

					var collection = this;
					var success = options.success;
					options.success = function(resp, status, xhr) {
						collection[options.add ? 'add' : 'reset'](collection.parse(resp.comments, xhr), options);
						if (success) success(collection, resp);
					};
					options.error = Backbone.wrapError(options.error, collection, options);
					return (this.sync || Backbone.sync).call(this, 'read', this, options);
				}
			});

			var CommentDeleteView = Backbone.View.extend({
				initialize: function() {
					var self = this;

					this.model = new Comment();
					this.model.set('id', this.options.id);
					this.model.fetch({
						success: function() {
							self.checkPermission();
						},
						error: _onAjaxError
					});
				},
				checkPermission: function() {
					if (this.model.get('user_id') == UCAN.Board.SiteCode+'.'+UCAN.Board.Account.userId) {
						this.render();
					} else {
						UCAN.Board.Indicator.hide();
						window.alert('권한이 없습니다.');
						window.history.back();
						return;
					}
				},
				render: function() {
					var self = this;

					UCAN.Board.Editor.disable();

					var postUrl = '#post/' + self.model.get('post_id') + '?page=' + self.options.page;
					var _form = _.template($('#template_post_delete_form').html())({
						postUrl: postUrl,
						commandUrl: {
							back: 'javascript:history.back();'
						}
					});
					contentWrapper.html(_form);

					var form = contentWrapper.find('form.post.confirm');
					form.submit(function() {
						form.find('input[type=submit]').attr('disabled', 'disabled');

						self.model.destroy({
							success: function(model) {
								UCAN.Board.Router.navigate(postUrl , {trigger:true});
							},
							error: function(model, response) {
								window.alert('삭제에 실패했습니다.');
								form.find('input[type=submit]').removeAttr('disabled');
							}
						});
						return false;
					});

					return this;
				}
			});

			var CommentEditView = Backbone.View.extend({
				initialize: function() {
					var self = this;

					this.model = new Comment();
					this.model.set('id', this.options.id);
					this.model.fetch({
						success: function() {
							self.checkPermission();
						},
						error: _onAjaxError
					});
					return this;
				},
				checkPermission: function() {
					if (this.model.get('user_id') == UCAN.Board.SiteCode+'.'+UCAN.Board.Account.userId) {
						this.render();
					} else {
						UCAN.Board.Indicator.hide();
						window.alert('권한이 없습니다.');
						window.history.back();
						return;
					}
				},
				render: function() {
					var self = this;

					UCAN.Board.Editor.disable();

					var formHtml = _.template($('#template_comment_edit_form').html())({
					});
					var form = contentWrapper.html(formHtml).find('form.comment');
					form.removeClass('write').addClass('edit');
					form.find('textarea[name=body]').val(this.model.get('body'));
					form.submit(function() {
						var comment = self.model;
						form.find('input[type=submit]').attr('disabled', 'disabled');

						var body = UCAN.Board.Editor.getData(UCAN.Board.Editor.Comment);
						body = body == null ? contentWrapper.find('form.comment.edit textarea[name=body]').val() : body;

						comment.save({body: body}, {
							success: function(model, response) {
								if (response.code < 0) {
									window.alert(response.message);
								} else {
									UCAN.Board.Router.navigate('#post/' + model.get('post_id') + '?page=' + self.options.page , {trigger:true});
								}
								form.find('input[type=submit]').removeAttr('disabled');
							},
							error: function(model, response) {
								window.alert(_getAjaxErrorMessage(response, '저장에 실패했습니다.'));
								form.find('input[type=submit]').removeAttr('disabled');
							}
						});

						return false;
					});

					UCAN.Board.Editor.enable(UCAN.Board.Editor.Comment, {
						height: UCAN.Board.Config.Editor.Comment.height
					});

					return this;
				}
			});

			var PostWriteView = Backbone.View.extend({
				initialize: function() {
					if (!UCAN.Board.Permissions.postCreate) {
						UCAN.Board.Indicator.hide();
						window.alert('권한이 없습니다.');
						window.history.back();
						return;
					} else {
						this.render();
					}

					return this;
				},
				render: function() {
					UCAN.Board.Editor.disable();

					var formHtml = _.template($('#template_post_write_form').html())({
						commandUrl: {
							back: 'javascript:history.back();'
						}
					});
					var form = contentWrapper.html(formHtml).find('form.post');
					form.removeClass('edit').addClass('write');
					form.submit(function() {
						var post = new Post();
						contentWrapper.find('form.post.write input[type=submit]').attr('disabled', 'disabled');

						var title = contentWrapper.find('form.post.write input[type=text][name=title]').val();
						var body = UCAN.Board.Editor.getData(UCAN.Board.Editor.Post);
						body = body == null ? contentWrapper.find('form.post.write textarea[name=body]').val() : body;

						post.save({title: title, body: body}, {
							success: function(model, response) {
								if (response.code < 0) {
									window.alert(response.message);
								} else {
									UCAN.Board.Router.navigate('', {trigger:true});
								}
								form.find('input[type=submit]').removeAttr('disabled');
							},
							error: function(model, response) {
								window.alert(_getAjaxErrorMessage(response, '저장에 실패했습니다.'));
								form.find('input[type=submit]').removeAttr('disabled');
							}
						});
						return false;
					});

					UCAN.Board.Editor.enable(UCAN.Board.Editor.Post, {
						height: UCAN.Board.Config.Editor.Post.height
					});

					return this;
				}
			});

			var PostEditView = Backbone.View.extend({
				initialize: function() {
					var self = this;

					this.model = new Post();
					this.model.set('id', this.options.id);
					this.model.fetch({
						success: function() {
							self.checkPermission();
						},
						error: _onAjaxError
					});
					return this;
				},
				checkPermission: function() {
					if (this.model.get('user_id') == UCAN.Board.SiteCode+'.'+UCAN.Board.Account.userId) {
						this.render();
					} else {
						UCAN.Board.Indicator.hide();
						window.alert('권한이 없습니다.');
						window.history.back();
						return;
					}
				},
				render: function() {
					var self = this;
					UCAN.Board.Editor.disable();

					var _form = _.template($('#template_post_write_form').html())({
						commandUrl: {
							back: 'javascript:history.back();'
						}
					});
					contentWrapper.html(_form);

					var form = contentWrapper.find('form.post');
					form.removeClass('write').addClass('edit');
					form.find('input[name=title]').val(this.model.get('title'));
					form.find('textarea[name=body]').val(this.model.get('body'));
					form.submit(function() {
						var post = self.model;
						form.find('input[type=submit]').attr('disabled', 'disabled');

						var title = contentWrapper.find('form.post.edit input[type=text][name=title]').val();
						var body = UCAN.Board.Editor.getData(UCAN.Board.Editor.Post);
						body = body == null ? contentWrapper.find('form.post.edit textarea[name=body]').val() : body;

						post.save({title: title, body: body}, {
							success: function(model, response) {
								if (response.code < 0) {
									window.alert(response.message);
								} else {
									UCAN.Board.Router.navigate('#post/' + post.get('id') , {trigger:true});
								}
								form.find('input[type=submit]').removeAttr('disabled');
							},
							error: function(model, response) {
								window.alert(_getAjaxErrorMessage(response, '저장에 실패했습니다.'));
								form.find('input[type=submit]').removeAttr('disabled');
							}
						});
						return false;
					});

					UCAN.Board.Editor.enable(UCAN.Board.Editor.Post, {
						height: UCAN.Board.Config.Editor.Post.height
					});
					return this;
				}
			});

			var PostDeleteView = Backbone.View.extend({
				initialize: function() {
					var self = this;

					this.model = new Post();
					this.model.set('id', this.options.id);
					this.model.set('page', this.options.page);
					this.model.fetch({
						success: function() {
							self.checkPermission();
						},
						error: _onAjaxError
					});
				},
				checkPermission: function() {
					if (this.model.get('user_id') == UCAN.Board.SiteCode+'.'+UCAN.Board.Account.userId) {
						this.render();
					} else {
						UCAN.Board.Indicator.hide();
						window.alert('권한이 없습니다.');
						window.history.back();
						return;
					}
				},
				render: function() {
					var self = this;
					UCAN.Board.Editor.disable();

					var postUrl = '#post/' + this.model.get('id') + '?page=' + this.options.page;
					var _form = _.template($('#template_post_delete_form').html())({
						postUrl: postUrl
					});
					contentWrapper.html(_form);

					var form = contentWrapper.find('form.post.confirm');
					form.submit(function() {
						form.find('input[type=submit]').attr('disabled', 'disabled');

						self.model.destroy({
							success: function(model) {
								UCAN.Board.Router.navigate('#page/' + model.get('page') , {trigger:true});
							},
							error: function(model, response) {
								window.alert('삭제에 실패했습니다.');
								form.find('input[type=submit]').removeAttr('disabled');
							}
						});
						return false;
					});

					return this;
				}
			});

			var PostShowView = Backbone.View.extend({
				initialize: function() {
					var self = this;
					this.model = new Post();
					this.model.set('id', this.options.id);
					this.model.fetch({
						success: function() {
							self.model.set('page', self.options.page);
							self.render();
						},
						error: _onAjaxError
					});
				},
				render: function() {
					var self = this;

					UCAN.Board.Editor.disable();

					var comments = $.map(this.model.get('comments'), function(comment, index) {
						var attributes = comment.attributes;
						is_owner = attributes.user_id  == UCAN.Board.SiteCode+'.'+UCAN.Board.Account.userId;
						attributes.can_delete = is_owner;
						attributes.delete_url = '#comment/' + attributes.id + '/delete?page=' + self.options.page;
						attributes.can_edit = is_owner;
						attributes.edit_url = '#comment/' + attributes.id + '/edit?page=' + self.options.page;

						return attributes;
					});

					var html = _.template($('#template_post_show').html())({
						id: this.model.get('id'),
						title: this.model.get('title'),
						body: this.model.get('body'),
						name: this.model.get('name'),
						created_at: new Date(this.model.get('created_at')),
						reads: this.model.get('reads'),
						comments_count: this.model.get('comments_count'),
						ip: this.model.get('ip'),
						comments: comments,
						uri: location.protocol+'//'+location.host+location.pathname + '#post/' + this.model.get('id'),
						commandUrl: {
							list: '#page/' + this.options.page,
							edit: (this.model.get('user_id') == UCAN.Board.SiteCode+'.'+UCAN.Board.Account.userId) ? '#post/' + this.model.get('id') + '/edit?page=' + this.options.page : null,
							delete: (this.model.get('user_id') == UCAN.Board.SiteCode+'.'+UCAN.Board.Account.userId) ? '#post/' + this.model.get('id') + '/delete?page=' + this.options.page : null,
						}
					});

					contentWrapper.html(html);

					var form = contentWrapper.find('form.comment');
					form.removeClass('edit').addClass('write');

					form.submit(function() {
						var comment = new Comment({post_id: self.model.get('id')});
						contentWrapper.find('form.comment.write input[type=submit]').attr('disabled', 'disabled');

						var body = UCAN.Board.Editor.getData(UCAN.Board.Editor.Comment);
						body = body == null ? contentWrapper.find('form.comment.write textarea[name=body]').val() : body;

						comment.save({body: body}, {
							success: function(model, response) {
								if (response.code < 0) {
									window.alert(response.message);
								} else {
									Backbone.history.fragment = null;
									Backbone.history.navigate(document.location.hash, true); 
								}
								contentWrapper.find('form.comment.write input[type=submit]').removeAttr('disabled');
							},
							error: function(model, response) {
								window.alert(_getAjaxErrorMessage(response, '저장에 실패했습니다.'));
								form.find('input[type=submit]').removeAttr('disabled');
							}
						});

						return false;
					});

					UCAN.Board.Editor.enable(UCAN.Board.Editor.Comment, {
						height: UCAN.Board.Config.Editor.Comment.height
					});
					return this;
				}
			});

			var BoardView = Backbone.View.extend({
				initialize: function() {
					var self = this;

					if (!UCAN.Board.CurrentBoard) {
						window.alert('게시판이 지정되지 않았습니다.');
						window.history.back();
						return;
					}

					this.model = new Posts();
					this.model.fetch({
						data: {page: this.options.page},
						success: function() {
							self.render();
						},
						error: _onAjaxError
					});
				},
				render: function() {
					UCAN.Board.Editor.disable();

					var totalPosts = this.model.totalPosts;
					var totalPage = this.model.totalPage;
					var currentPage = this.model.page;
					if (!currentPage) {
						currentPage = 1;
					}

					var differ = this.options.differ || 3;
					var from = (currentPage - differ < 1) ? 1 : currentPage - differ;
					var to = (currentPage + differ > totalPage) ? totalPage : currentPage + differ;

					// render page navigation
					var pageNavigationElement = '';

					var _tokenReplacer = function(tpl) {
						return tpl.replace(/:[a-zA-Z_][a-zA-Z0-9_]+/g, function(match) {
							var attr = match.replace(/^:/, '');
							var value;
							try {
								eval('value = ' + attr);
							} catch (e) {
								value = null;
							}

							return value;
						});
					};

					// first page
					var url = '#page/1';
					pageNavigationElement += _tokenReplacer(UCAN.Board.Config.Pager.first);

					for (var i = from; i <= to; i++) {
						var page = i;
						var url = '#page/' + page;
						var _tpl = page == currentPage ? UCAN.Board.Config.Pager.current : UCAN.Board.Config.Pager.page;
						pageNavigationElement += _tokenReplacer(_tpl);
					}

					// last page
					var url = '#page/' + totalPage;
					pageNavigationElement += _tokenReplacer(UCAN.Board.Config.Pager.last);

					var posts = $.map(this.model.models, function(post, index) {
						attributes = post.attributes;
						attributes.uri = '#post/' + post.id + '?page=' + currentPage;
						return attributes;
					});

					var container = _.template($('#template_post_list').html())({
						posts: posts,
						totalPosts: totalPosts,
						totalPage: totalPage,
						page: currentPage,
						pageNavigation: pageNavigationElement,
						commandUrl: {
							write: UCAN.Board.Permissions.postCreate ? '#write' : null
						}
					});
					contentWrapper.html(container);

					return this;
				}
			});

			var UcanBoardRouter = Backbone.Router.extend({
				routes: {
					'write': 'postWrite',
					'page/:page': 'postList',
					'post/:id': 'postShow',
					'post/:id/edit': 'postEdit',
					'post/:id/delete': 'postDelete',
					'comment/:id/edit': 'commentEdit',
					'comment/:id/delete': 'commentDelete',
					'*path':  'defaultRoute'
				},
				defaultRoute: function() {
					this.postList();
				},
				postList: function(page) {
					new BoardView({page: parseInt(page || 1)});
				},
				postWrite: function() {
					new PostWriteView();
				},
				postShow: function(id, params) {
					new PostShowView({id: id, page: !!params ? parseInt(params.page) : 1});
				},
				postEdit: function(id, params) {
					new PostEditView({id: id, page: !!params ? parseInt(params.page) : 1});
				},
				postDelete: function(id, params) {
					new PostDeleteView({id: id, page: !!params ? parseInt(params.page) : 1});
				},
				commentEdit: function(id, params) {
					new CommentEditView({id: id, page: !!params ? parseInt(params.page) : 1});
				},
				commentDelete: function(id, params) {
					new CommentDeleteView({id: id, page: !!params ? parseInt(params.page) : 1});
				}
			});

			UCAN.Board.Indicator.initialize();

			UCAN.Board.Router = new UcanBoardRouter();
			Backbone.history.start();
		});
	}
};
