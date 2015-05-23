<?php
@require_once(dirname(__FILE__)."/../../classes/security/Purifier.class.php");

if (!class_exists('Purifier')) {
	function sanitize_user_html($html) {
		global $purifier;

		if ($purifier == NULL) {
			$purifier = new Purifier;
		}

		return $purifier->purify($html);
	}
} else {
	// if Purifier does not exists, use bundled
	require_once(dirname(__FILE__)."/htmlpurifier-4.6.0-standalone/HTMLPurifier.standalone.php");

	function sanitize_user_html($html) {
		global $purifier;

		if ($purifier == NULL) {
			$cacheDir = _XE_PATH_ . 'files/cache/htmlpurifier';
			if (!is_dir($cacheDir)) {
				FileHandler::makeDir($cacheDir);
			}

			$config = HTMLPurifier_Config::createDefault();
			$config->set('Cache.SerializerPath', $cacheDir);
			$config->set('HTML.TidyLevel', 'light');
			$config->set('Output.FlashCompat', TRUE);
			$config->set('HTML.SafeObject', TRUE);
			$config->set('HTML.SafeEmbed', TRUE);
			$config->set('HTML.SafeIframe', TRUE);
			$config->set('URI.SafeIframeRegexp', '%^http://(www\.youtube\.com/|player\.vimeo\.com/)%');
			$config->set('Attr.AllowedFrameTargets', array('_blank'));

			$purifier = new HTMLPurifier($config);
		}

		return $purifier->purify($html);
	}
}
?>
