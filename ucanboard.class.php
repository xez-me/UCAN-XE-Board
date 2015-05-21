<?php
require_once(dirname(__FILE__)."/curl.php");

function json_decode2($json) {
	$json = substr($json, strpos($json,'{')+1, strlen($json)); 
	$json = substr($json, 0, strrpos($json,'}')); 
	$json = preg_replace('/(^|,)([\\s\\t]*)([^:]*) (([\\s\\t]*)):(([\\s\\t]*))/s', '$1"$3"$4:', trim($json)); 

	return json_decode('{'.$json.'}', true); 
}

class ucanboard extends ModuleObject {
	var $remote_host = "api.bbs.ucan.or.kr";
	// var $remote_host = "127.0.0.1:3030";

	function moduleInstall() {
		$oModuleController = &getController('module');

		return new Object();
	}

	function checkUpdate() {
		return false;
	}

	function handshake($access_token) {
		$session = curl_init();
		curl_setopt($session, CURLOPT_POST, true);
		curl_setopt($session, CURLOPT_URL, sprintf("http://%s/handshake", $this->remote_host));
		curl_setopt($session, CURLOPT_HEADER, false);
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
