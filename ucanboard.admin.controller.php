<?php
class ucanboardAdminController extends ucanboard {
	function init()
    {
	}

	function procUcanboardAdminUpdate() {
		$oModuleController = &getController('module');
		$oModuleModel = &getModel('module');
		$args = Context::getRequestVars();
		$args->module = 'ucanboard';
		$args->mid = $args->page_name;
		unset($args->page_name);

		if ($args->module_srl) {
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
			if ($module_info->module_srl != $args->module_srl)
				unset($args->module_srl);
		}

		if (!$args->module_srl) {
			$output = $oModuleController->insertModule($args);
		} else {
			$output = $oModuleController->updateModule($args);
		}

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'module_srl', $output->get('module_srl'), 'act', 'dispUcanboardAdminInfo');

        Context::close();
		header('location:'.$returnUrl, 302, true);
        exit();
	}

	function procUcanboardAdminDelete() {
		$oModuleController = &getController('module');
		$module_srl = Context::get('module_srl');
		$output = $oModuleController->deleteModule($module_srl);

		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispUcanboardAdminList');
        Context::close();
        header('location:'.$returnUrl, 302, true);
        exit();
	}

	function procUcanboardAdminUpdateConfig() {
		$oModuleController = getController('module');
		$args = new stdclass;
		$args->access_token = Context::get('access_token');
		$args->sanitize_html = !Context::get('sanitize_html') ? 0 : 1;
		$oModuleController->updateModuleConfig('ucanboard', $args);

		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispUcanboardAdminList');
        Context::close();
        header('location:'.$returnUrl, 302, true);
        exit();
	}
}
?>
