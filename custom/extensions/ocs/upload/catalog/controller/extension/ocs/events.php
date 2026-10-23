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
