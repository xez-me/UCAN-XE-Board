<?php
require_once(dirname(__FILE__)."/htmlpurifier-4.6.0-standalone/HTMLPurifier.standalone.php");

function sanitize_user_html($html) {
	global $purifier;

	if ($purifier == NULL) {
		$config = HTMLPurifier_Config::createDefault();
		$config->set('Cache.SerializerPath', dirname(__FILE__).'/../../files/cache');
		$purifier = new HTMLPurifier($config);
	}

	return $purifier->purify($html);
}
?>
