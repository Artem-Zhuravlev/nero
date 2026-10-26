<?php
// Extension: ocs (theme). Event handlers of the theme. Owner: SPEC-006 (REQ-THM-022), SPEC-007.

class ControllerExtensionOcsEvents extends Controller {
	/**
	 * catalog/view/common/header/before: injects asset_version, a short build id used as ?v= on theme assets.
	 */
	public function beforeHeader(&$route, &$data) {
		if (!is_array($data)) {
			return;
		}

		$data['asset_version'] = $this->assetVersion();
	}

	/**
	 * Every storefront view, last in order: applies the stock / article number switches of the theme settings to every storefront view (SPEC-044 REQ-VIS-051).
	 */
	public function applyDisplay(&$route, &$data) {
		// the return forms ask the customer for the model themselves: not a display of the catalogue field
		if (!is_array($data) || $this->config->get('config_theme') != 'ocs' || strpos($route, 'account/return') === 0) {
			return;
		}

		// the home page has no visible title: one visually hidden h1 with the store title (SPEC-045 REQ-SEO-001)
		if ($route === 'common/home') {
			$data['ocs_h1'] = $this->config->get('config_meta_title');
		}

		try {
			$data = \Ocs\Display::apply($data, \Ocs\Display::on($this->config->get('theme_ocs_show_stock')), \Ocs\Display::on($this->config->get('theme_ocs_show_sku')));
		} catch (\Exception $e) {
			$this->log->write('[ocs] ' . $e->getMessage());
		}
	}

	private function assetVersion() {
		$info = dirname(dirname(rtrim(DIR_APPLICATION, '/\\'))) . '/BUILD_INFO.json';

		if (is_file($info)) {
			$json = json_decode((string)file_get_contents($info), true);

			if (is_array($json)) {
				if (!empty($json['gitCommit'])) {
					return substr(preg_replace('/[^a-f0-9]/i', '', (string)$json['gitCommit']), 0, 8);
				}

				if (!empty($json['treeHash'])) {
					return substr(preg_replace('/[^a-f0-9]/i', '', (string)$json['treeHash']), 0, 8);
				}
			}
		}

		$css = DIR_APPLICATION . 'view/theme/ocs/stylesheet/stylesheet.css';

		return is_file($css) ? dechex((int)filemtime($css)) : '0';
	}
}
