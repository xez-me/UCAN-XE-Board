<?php
require_once(dirname(__FILE__)."/curl.php");

class ucanboardController extends ucanboard {
	function init() {
		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('ucanboard');
		$this->access_token = $config->access_token;
		$this->sitecode = $config->sitecode;
	}

	function request($method, $url, $params, $request_body) {
		$url = sprintf("http://%s%s", self::REMOTE_HOST, $url);

		$request_header = array('Accept-Language: en');

		$logged_info = Context::get('logged_info');
		$userId = sprintf('%s.%s', $this->sitecode, $logged_info->member_srl);
		$request_header[] = sprintf('X-UCAN-UserId: %s', $userId);
		$userName = $logged_info->nick_name;

		$request_header[] = sprintf('X-UCAN-UserName: %s', $userName);
		$request_header[] = sprintf('X-UCAN-Origin-Addr: %s', $_SERVER['REMOTE_ADDR']);

		$oModuleModel = &getModel('module');
		$mid = Context::get('mid');
		$module_srl = $oModuleModel->getModuleSrlByMid($mid);
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		$request_header[] = sprintf('X-UCAN-BoardName: %s', $module_info->ucan_originboard);

		$grant = $oModuleModel->getGrant($module_info, $logged_info);
		$disallowed_permissions = array(); 
		if (!$grant->access)
			$disallowed_permissions[] = 'post.index';
		if (!$grant->view)
			$disallowed_permissions[] = 'post.show';
		if (!$grant->write_document)
			$disallowed_permissions[] = 'post.create';
		if (!$grant->write_comment)
			$disallowed_permissions[] = 'comment.create';
		$request_header[] = sprintf('X-UCAN-Disallowed-Permission: %s', implode($disallowed_permissions, ','));
		$request_header[] = sprintf('X-UCAN-AccessToken: %s', $this->access_token);
		$request_header[] = 'X-UCAN-Version: 0.4.5';

		$session = curl_init();
		if ($method == 'POST') {
			@curl_setopt($session, CURLOPT_POST, true);
			curl_setopt($session, CURLOPT_POSTFIELDS, $request_body);
		} else if ($method == 'PUT') {
			@curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'PUT');
			$request_header[] = sprintf('Content-Length: %s', strlen($request_body));
			curl_setopt($session, CURLOPT_POSTFIELDS, $request_body);
		} else {
			if ($method == 'DELETE') {
				@curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'DELETE');
			} else {
				@curl_setopt($session, CURLOPT_GET, true);
			}

			if ($params != '') {
				$url = sprintf("%s?%s", $url, $params);
			}
		}

		curl_setopt($session, CURLOPT_URL, $url);
		curl_setopt($session, CURLOPT_HEADER, false);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($session, CURLOPT_HTTPHEADER, $request_header);
		curl_setopt($session, CURLOPT_TIMEOUT_MS, 1000);

		$response_body = curl_exec($session);
		$response_info = curl_getinfo($session);
		curl_close($session);

		return array('info' => $response_info, 'body' => $response_body);
	}

	function procBoardInsertDocument() {
		if (!$this->checkCSRFToken()) {
			return new Object(-1, 'msg_invalid_request');
		}

		$mid = Context::get('mid');

		$title = Context::get('title');
		$content = Context::get('content');

		$json = json_encode(array('title' => $title, 'body' => $content));

		$document_srl = Context::get('document_srl');
		if ($document_srl) {
			$response = $this->request('PUT', sprintf('/posts/%d.json', $document_srl), '', $json);
		} else {
			$response = $this->request('POST', '/posts.json', '', $json);
		}

		$response_info = $response['info'];
		$result = $response['body'];

		$this->add('mid', Context::get('mid'));

		if (!$result || $response_info['http_code'] != 200) {
			return new Object(-1, 'fail_to_registed');
		}

		$result = json_decode($result);
		if (!$result || !$result->post) {
			return new Object(-1, 'fail_to_registed');
		}

		$post = $result->post;
		if ($post->id) {
			$msg_code = 'success_registed';
			$this->add('document_srl', $post->id);
		}

		return $this->setMessage('fail_to_registed');
	}

	function procBoardDeleteDocument() {
		if (!$this->checkCSRFToken()) {
			return new Object(-1, 'msg_invalid_request');
		}

		$document_srl = Context::get('document_srl');
		$response = $this->request('DELETE', sprintf('/posts/%d.json', $document_srl), '', NULL);
		$response_info = $response['info'];
		if ($response_info['http_code'] != 200) {
			return new Object(-1, 'msg_protect_content');
		}

		$this->add('mid', Context::get('mid'));
		$this->setMessage('success_deleted');
	}

	function procBoardInsertComment() {
		if (!$this->checkCSRFToken()) {
			return new Object(-1, 'msg_invalid_request');
		}

		$document_srl = Context::get('document_srl');
		$content = Context::get('content');

		$json = json_encode(array('body' => $content));
		$comment_srl = Context::get('comment_srl');
		if ($comment_srl) {
			$response = $this->request('PUT', sprintf('/comments/%d.json', $comment_srl), '', $json);
		} else {
			$response = $this->request('POST', sprintf('/posts/%d/comments.json', $document_srl), '', $json);
		}

		$response_info = $response['info'];
		$result = $response['body'];

		$this->add('mid', Context::get('mid'));
		$this->add('document_srl', $document_srl);

		$result = json_decode($result);
		if (!$result || !$result->comment) {
			return $this->setMessage('fail_to_registed');
		}

		$this->setMessage('success_registed');
	}

	function procBoardDeleteComment() {
		if (!$this->checkCSRFToken()) {
			return new Object(-1, 'msg_invalid_request');
		}

		$comment_srl = Context::get('comment_srl');

		$response = $this->request('DELETE', sprintf('/comments/%d.json', $comment_srl), '', NULL);

		$response_info = $response['info'];
		$result = $response['body'];

		if ($response_info['http_code'] != 200 || !$result) {
			return new Object(-1, 'msg_protect_content');
		}

		$this->add('mid', Context::get('mid'));
		$this->add('document_srl', Context::get('document_srl'));

		$this->setMessage('success_deleted');
	}

	function checkCSRFToken() {
		if (!checkCSRF()) {
			return false;
		}


		$stored_token = $_SESSION[self::SESSION_KEY][self::CSRF_VALUE_SESSION_KEY];
		$request_token = $_SERVER['HTTP_X_XE_UCAN_CSRFTOKEN'];

		if (!$stored_token || !$request_token) {
			return false;
		}


		if ($stored_token != $request_token) {
			return false;
		}

		unset($_SESSION[self::SESSION_KEY]);

		return true;
	}
}
?>
