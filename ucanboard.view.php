<?php

class ucanboardView extends ucanboard {
	function init() {
		$this->setTemplatePath($this->module_path.'tpl');
	}

	function dispUcanboardList() {
		$logged_info = Context::get('logged_info');

		$oLayoutModel = &getModel('layout');
		$layoutList = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layoutList);

		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('ucanboard');
		Context::set('sitecode', $config->sitecode);

		$args = Context::getRequestVars();
		$module_srl = $oModuleModel->getModuleSrlByMid($args->mid);
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		Context::set('board_name', $module_info->ucan_originboard);

		unset($args);
		$args = new stdClass;
		$args->module = 'ucanboard';
		$_module_list = $oModuleModel->getMidList($args, array('mid', 'browser_title'));
		$boards = array();
		foreach ($_module_list as $_module) {
			$_board = new stdClass;
			$_board->name = $_module->mid;
			$_board->title = $_module->browser_title;
			$_board->url = Context::getUrl(2, array('mid', $_module->mid), null, false);
			$boards[] = $_board;
		}

		Context::set('mid', $module_info->mid);

		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument(0);
		$document_srl = Context::get('document_srl');
		if ($document_srl) {
			$url = sprintf("/posts/%s", $document_srl);
			$json = $this->getJSON($url, $config, $logged_info);
			if ($json->post) {
				$oDocument = new ucanDummyDocumentItem($config->sitecode, $json->post, $logged_info, $config->sanitize_html == 1);
				$reads = $_COOKIE['ucnb_reads'] ? explode(',', $_COOKIE['ucnb_reads']) : array();
				if (!in_array($document_srl, $reads)) {
					$reads[] = $document_srl;
					if (count($reads) > 10) {
						$reads = array_slice($reads, count($reads) - 5);
					}
					setcookie('ucnb_reads', implode(',', $reads), $_SERVER['REQUEST_TIME'] + 3600 * 24 * 1000, '/');
				}
			}
		}
		$oDocument->add('module_srl', $this->module_srl);
		Context::set('oDocument', $oDocument);

		// list config
		$list_config = $this->getListConfig($this->module_srl);
		Context::set('list_config', $list_config);

		$this->grant->manager = false;
		Context::set('grant', $this->grant);

		$module_info->list_count = (int) $module_info->list_count;

		$cur_page = (int) Context::get('page');
		$cur_page = $cur_page ? $cur_page : 1;
		$page_navigation = new PageHandler(0, 1, (int) Context::get('page'), (int) $module_info->page_count);
		$page_navigation->first_page = 1;
		$page_navigation->last_page = 1;

		$url = sprintf("/posts?page=%s&per=%s", $page_navigation->cur_page, $module_info->list_count);
		$json = $this->getJSON($url, $config, $logged_info);

		if ($json->posts) {
			$post_list = array();
			$notice_list = array();
			foreach ($json->posts as $post) {
				$doc = new ucanDummyDocumentItem($config->sitecode, $post, $logged_info, $config->sanitize_html == 1);
				if ($post->is_notice) {
					$notice_list[$post->id] = $doc;
				} else {
					$post_list[$post->id] = $doc;
				}
			}
			Context::set('document_list', $post_list);
			Context::set('notice_list', $notice_list);
			$page_navigation->total_page = (int)floor($json->total_posts / $module_info->list_count);
			$page_navigation->last_page = $page_navigation->total_page;

			$page_navigation->first_page = $page_navigation->cur_page - ($page_navigation->page_count/2);
			if ($page_navigation->first_page < 1) {
				$page_navigation->first_page = 1;
			}
			if ($page_navigation->last_page - $page_navigation->first_page < $page_navigation->page_count) {
				$page_navigation->first_page = $page_navigation->last_page - $page_navigation->page_count + 1;
			}

			$page_navigation->total_count = $json->total_posts;
		}
			// var_dump($page_navigation);exit;
		Context::set('total_count', $page_navigation->total_count);
		Context::set('total_page', $page_navigation->total_page);
		Context::set('page', $page_navigation->cur_page);
		Context::set('page_navigation', $page_navigation);

		$module_info->votes = 'N';
		Context::set('module_info', $module_info);

		$oBoardModule = &getModule('board');
		Context::addJsFile($oBoardModule->module_path.'tpl/js/board.js');
		Context::addJsFilter($oBoardModule->module_path.'tpl/filter', 'insert_comment.xml');

		$this->setSkin($oBoardModule);
		$this->setTemplateFile('list');
		$this->addCSRFToken();
	}

	function dispBoardWrite() {
		$logged_info = Context::get('logged_info');

		$oBoardModule = &getModule('board');
		$this->setSkin($oBoardModule);

		if (!$this->grant->write_document) {
			return $this->dispBoardMessage('msg_not_permitted');
		}

		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('ucanboard');

		$args = Context::getRequestVars();
		$module_srl = $oModuleModel->getModuleSrlByMid($args->mid);
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		Context::set('module_info', $module_info);

		$document_srl = Context::get('document_srl');
		if ($document_srl) {
			$oDocument = $this->getDocument($document_srl, $config, $logged_info);
		}
		if ($oDocument == null) {
			$oDocument = new ucanDummyDocumentItem($config->sitecode, new stdClass, $logged_info);
		}
		$oDocument->module_srl = $module_info->module_srl;
		Context::set('oDocument', $oDocument);

		$this->grant->manager = false;
		Context::set('grant', $this->grant);

		Context::addJsFile($oBoardModule->module_path.'tpl/js/board.js');
		Context::addJsFilter($oBoardModule->module_path.'tpl/filter', 'insert.xml');

		$this->setTemplateFile('write_form');
		$this->addCSRFToken();
	}

	function dispBoardDelete() {
		$logged_info = Context::get('logged_info');

		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('ucanboard');

		$oBoardModule = &getModule('board');
		Context::addJsFile($oBoardModule->module_path.'tpl/js/board.js');
		Context::addJsFilter($oBoardModule->module_path.'tpl/filter', 'delete_document.xml');

		$document_srl = Context::get('document_srl');
		if ($document_srl) {
			$oDocument = $this->getDocument($document_srl, $config, $logged_info);
		}

		if ($oDocument) {
			if ($oDocument->get('user_id') != sprintf("%s.%s", $config->sitecode, $logged_info->member_srl)) {
				return $this->dispUcanboardList();
			}
			Context::set('oDocument',$oDocument);
		} else {
			return $this->dispUcanboardList();
		}

		$this->setSkin($oBoardModule);
		$this->setTemplateFile('delete_form');
		$this->addCSRFToken();
	}

	function dispBoardModifyComment() {
		$oBoardModule = &getModule('board');
		$this->setSkin($oBoardModule);

		if (!$this->grant->write_comment) {
			return $this->dispBoardMessage('msg_not_permitted');
		}

		$logged_info = Context::get('logged_info');

		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('ucanboard');

		$comment_srl = Context::get('comment_srl');

		$json = $this->getJSON(sprintf('/comments/%d.json', $comment_srl), $config, $logged_info);
		if ($json->comment) {
			$oComment = new ucanDummyCommentItem($config->sitecode, $json->comment);
			Context::set('oComment', $oComment);
		} else {
			$this->alertMessage('msg_not_founded');
			return;
		}

		$oCommentModel = getModel('comment');
		Context::set('oSourceComment', $oCommentModel->getComment());

		Context::addJsFile($oBoardModule->module_path.'tpl/js/board.js');
		Context::addJsFilter($oBoardModule->module_path.'tpl/filter', 'insert_comment.xml');

		$this->setTemplateFile('comment_form');
		$this->addCSRFToken();
	}

	function dispBoardDeleteComment() {
		$oBoardModule = &getModule('board');
		$this->setSkin($oBoardModule);

		if(!$this->grant->write_comment) {
			return $this->dispBoardMessage('msg_not_permitted');
		}

		$logged_info = Context::get('logged_info');

		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('ucanboard');

		$comment_srl = Context::get('comment_srl');

		$json = $this->getJSON(sprintf('/comments/%d.json', $comment_srl), $config, $logged_info);
		if ($json->comment) {
			$oComment = new ucanDummyCommentItem($config->sitecode, $json->comment);
			Context::set('oComment', $oComment);
		} else {
			$this->alertMessage('msg_not_founded');
			return;
		}

		Context::addJsFile($oBoardModule->module_path.'tpl/js/board.js');
		Context::addJsFilter($oBoardModule->module_path.'tpl/filter', 'delete_comment.xml');
		$this->setTemplateFile('delete_comment_form');
		$this->addCSRFToken();
	}

	function getDocument($document_srl, &$config, &$logged_info) {
		$url = sprintf("/posts/%s", $document_srl);
		$json = $this->getJSON($url, $config, $logged_info);
		if ($json && $json->post) {
			return new ucanDummyDocumentItem($config->sitecode, $json->post, $logged_info, $config->sanitize_html == 1);
		}

		return null;
	}

	function getJSON($url, $config, $logged_info) {
		$disallowed_permissions = array(); 
		if (!$this->grant->is_site_admin && !$this->grant->is_admin) {
			if (!$this->grant->access)
				$disallowed_permissions[] = 'post.index';
			if (!$this->grant->view)
				$disallowed_permissions[] = 'post.show';
			if (!$this->grant->write_document)
				$disallowed_permissions[] = 'post.create';
			if (!$this->grant->write_comment)
				$disallowed_permissions[] = 'comment.create';
		}

		$request_header = array();
		$request_header[] = sprintf('X-UCAN-UserId: %s', sprintf('%s.%s', $config->sitecode, $logged_info->member_srl));
		$request_header[] = sprintf('X-UCAN-UserName: %s', $logged_info->nick_name);
		$request_header[] = sprintf('X-UCAN-Origin-Addr: %s', $_SERVER['REMOTE_ADDR']);
		$request_header[] = sprintf('X-UCAN-BoardName: %s', $this->module_info->ucan_originboard);
		$request_header[] = sprintf('X-UCAN-Disallowed-Permission: %s', implode($disallowed_permissions, ','));
		$request_header[] = sprintf('X-UCAN-AccessToken: %s', $config->access_token);
		$request_header[] = 'X-UCAN-Version: 0.4.5';
		$reads = $_COOKIE['ucnb_reads'];
		if ($reads) {
			$request_header[] = sprintf('Cookie: reads=%s', urlencode($reads));
		}

		$session = curl_init();
		@curl_setopt($session, CURLOPT_GET, true);
		curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session,CURLOPT_ENCODING , "gzip");
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($session, CURLOPT_HTTPHEADER, $request_header);
		$url = sprintf("http://%s%s", self::REMOTE_HOST, $url);
		curl_setopt($session, CURLOPT_URL, $url);
		curl_setopt($session, CURLOPT_TIMEOUT_MS, 1000);

		$response_body = curl_exec($session);
		$json = json_decode($response_body);
		return $json;
	}

	function getListConfig($module_srl) {
		$oModuleModel = getModel('module');
		$oDocumentModel = getModel('document');

		// get the list config value, if it is not exitsted then setup the default value
		$list_config = $oModuleModel->getModulePartConfig('board', $module_srl);
		if(!$list_config || count($list_config) <= 0)
		{
			$list_config = array('no', 'title', 'user_name','regdate','readed_count');
		}

		foreach($list_config as $key)
		{
			$output[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
		}
		return $output;
	}

	function setSkin(&$oBoardModule) {
		$template_path = sprintf("%sskins/%s/",$oBoardModule->module_path, $this->module_info->skin);
		if(!is_dir($template_path) || !$this->module_info->skin) {
			$this->module_info->skin = 'default';
			$template_path = sprintf("%sskins/%s/", $oBoardModule->module_path, $this->module_info->skin);
		}
		$this->setTemplatePath($template_path);
	}

	function dispBoardMessage($msg_code)
	{
		$msg = Context::getLang($msg_code);
		if(!$msg) $msg = $msg_code;
		Context::set('message', $msg);
		$this->setTemplateFile('message');
	}

	function alertMessage($message)
	{
		$script = sprintf('<script> jQuery(function(){ alert("%s"); } );</script>', Context::getLang($message));
		Context::addHtmlFooter( $script );
	}

	function addCSRFToken() {
		Context::addMetaTag('UCANBoard-CSRFToken', $this->generateCSRFToken());
		Context::addJsFile($this->module_path.'js/csrf.js');
	}

	function generateCSRFToken() {
		$hash = md5(sprintf('%s %s', rand(), microtime()));
		$_SESSION[self::SESSION_KEY][self::CSRF_VALUE_SESSION_KEY] = $hash;
		$_SESSION[self::SESSION_KEY][self::CSRF_EXPIRE_SESSION_KEY] = time() + self::CSRF_EXPIRE_SECOND;
		return $hash;
	}
}
?>
