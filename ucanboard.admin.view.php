<?php
class ucanboardAdminView extends ucanboard {
	function init() {
		$module_srl = Context::get('module_srl');
		$oModuleModel = &getModel('module');
		if ($module_srl) {
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if (!!$module_info) {
				ModuleModel::syncModuleToSite($module_info);
				$this->module_info = $module_info;
				Context::set('module_info', $module_info);
			}
		}

		$this->setTemplatePath($this->module_path.'tpl');
	}

	function dispUcanboardAdminList() {
		$args = new stdClass;
		$args->mid = 'ucanboard';
		$output = executeQuery('ucanboard.getBoard', $args);
		$oModuleModel = &getModel('module');

		$config = $oModuleModel->getModuleConfig('ucanboard');

		$ucan = $this->handshake($config->access_token);

		if (isset($ucan['site']['code']) && $ucan['site']['code']) {
			$oModuleController = &getController('module');
			if (!isset($config->sitecode)) {
				$config->sitecode = $ucan['site']['code'];
				$oModuleController->insertModuleConfig('ucanboard', $config);
			} else if ($config->sitecode != $ucan['site']['code']) {
				$config->sitecode = $ucan['site']['code'];
				$oModuleController->updateModuleConfig('ucanboard', $config);
			}
		}

		$modules = array();
		if (gettype($output->data) != 'object') {
			foreach ($output->data as &$module) {
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($module->module_srl);
				$module_info->ucan_originboard_exist = false;
				foreach ($ucan['boards'] as &$board) {
					if ($board['name'] == $module_info->ucan_originboard) {
						$module_info->ucan_originboard_title = $board['title'];
						$module_info->ucan_originboard_exist = true;
						break;
					}
				}
				$modules[] = $module_info;
			}
		} else {
			if (is_array($ucan['boards'])) {
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($output->data->module_srl);
				$module_info->ucan_originboard_exist = false;
				foreach ($ucan['boards'] as &$board) {
					if ($board['name'] == $module_info->ucan_originboard) {
						$module_info->ucan_originboard_title = $board['title'];
						$module_info->ucan_originboard_exist = true;
						break;
					}
				}
				$modules[] = $module_info;
			}
		}

		Context::set('access_token', $config->access_token);
		Context::set('sanitize_html', $config->sanitize_html);
		Context::set('modules', $modules);

		$this->setTemplateFile('adminList');
	}

	function dispUcanboardAdminInfo() {
		$module_srl = Context::get('module_srl');
		$module_info = Context::get('module_info');

		$oBoardModule = &getModule('board');
		$oModuleModel = &getModel('module');
		$skin_list = $oModuleModel->getSkins($oBoardModule->module_path);
		Context::set('skin_list', $skin_list);

		$mskin_list = $oModuleModel->getSkins($oBoardModule->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);

		$oLayoutModel = &getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);
		$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);

		$config = $oModuleModel->getModuleConfig('ucanboard');
		$this->handshake($config->access_token);

		$this->setTemplateFile('adminInfo');
	}

	function dispUcanboardAdminGrantInfo() {
		$oModuleAdminModel = &getAdminModel('module');

		$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
		Context::set('grant_content', $grant_content);

		$this->setTemplateFile('adminGrantInfo');
	}

	function handshake($access_token) {
		$ucan = parent::handshake($access_token);

		$santizier = new ucanboardServerInputSanitize();
		$ucan = $santizier->sanitize($ucan);


		Context::set('boards', $ucan['boards']);
		Context::set('site_error', !$ucan['site']['code'] || !$ucan['site']['name']);
		Context::set('site_code', $ucan['site']['code']);
		Context::set('site_name', $ucan['site']['name']);

		return $ucan;
	}
}
?>
