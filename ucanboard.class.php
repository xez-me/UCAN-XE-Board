<?php
require_once(_XE_PATH_."modules/UCAN-XE-Board/ucanboard.function.php");


class ucanboard extends ModuleObject {
	const REMOTE_HOST = "api.bbs.ucan.or.kr";

	const SESSION_KEY = 'XE_UCANBOARD';
	const CSRF_VALUE_SESSION_KEY = 'csrf_token';
	const CSRF_EXPIRE_SESSION_KEY = 'csrf_expires';
	const CSRF_EXPIRE_SECOND = 600;

	function moduleInstall() {
		$oModuleController = &getController('module');

		return new Object();
	}

	function checkUpdate()
    {
		return false;
	}

	function handshake($access_token) {
		$session = curl_init();
		curl_setopt($session, CURLOPT_POST, true);
		curl_setopt($session, CURLOPT_URL, sprintf("http://%s/handshake", self::REMOTE_HOST));
		curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session,CURLOPT_ENCODING , "gzip");
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($session, CURLOPT_HTTPHEADER, array(
			'Content-type: application/json; charset=utf-8',
			"X-UCAN-AccessToken: ${access_token}"
		));
		curl_setopt($session, CURLOPT_POSTFIELDS, "{}");
		$response_body = curl_exec($session);
		$response_info = curl_getinfo($session);
		curl_close($session);

		$json = json_decode2($response_body);

		return array('site' => $json['site'], 'boards' => $json['boards']);
	}
}
?>
