<?php
// Extension: ocs (theme). Admin settings form: image sizes and list limits. Owner: SPEC-006 (REQ-THM-001, 030, 031).

class ControllerExtensionThemeOcs extends Controller {
	private $error = array();

	private $roles = array('thumb', 'popup', 'category', 'manufacturer', 'product', 'additional', 'related', 'compare', 'wishlist', 'cart', 'location');

	public function index() {
		$this->load->language('extension/theme/ocs');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$store_id = isset($this->request->get['store_id']) ? (int)$this->request->get['store_id'] : 0;
		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('theme_ocs', $this->request->post, $store_id);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=theme', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_product_limit'] = isset($this->error['product_limit']) ? $this->error['product_limit'] : '';
		$data['error_product_description_length'] = isset($this->error['product_description_length']) ? $this->error['product_description_length'] : '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $token, true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', $token . '&type=theme', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/theme/ocs', $token . '&store_id=' . $store_id, true)
		);

		$data['action'] = $this->url->link('extension/theme/ocs', $token . '&store_id=' . $store_id, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=theme', true);

		$setting_info = array();

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$setting_info = $this->model_setting_setting->getSetting('theme_ocs', $store_id);
		}

		$defaults = array('status' => 0, 'directory' => 'ocs', 'product_limit' => 15, 'product_description_length' => 100, 'show_stock' => 1, 'show_sku' => 1);

		foreach ($this->roles as $role) {
			$data['error_image_' . $role] = isset($this->error['image_' . $role]) ? $this->error['image_' . $role] : '';
			$defaults['image_' . $role . '_width'] = 80;
			$defaults['image_' . $role . '_height'] = 80;
		}

		foreach ($defaults as $field => $default) {
			$key = 'theme_ocs_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif (isset($setting_info[$key])) {
				$data[$key] = $setting_info[$key];
			} else {
				$data[$key] = $default;
			}
		}

		// contacts of the footer / header (SPEC-047): per language texts and one list of links
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['contact_icons'] = array_keys(\Ocs\Contacts::$icons);

		$contact = isset($this->request->post['theme_ocs_contact']) ? $this->request->post['theme_ocs_contact'] : (isset($setting_info['theme_ocs_contact']) ? $setting_info['theme_ocs_contact'] : array());
		$links = isset($this->request->post['theme_ocs_contact_links']) ? $this->request->post['theme_ocs_contact_links'] : (isset($setting_info['theme_ocs_contact_links']) ? $setting_info['theme_ocs_contact_links'] : array());

		$data['contact'] = array();

		foreach ($data['languages'] as $language) {
			foreach (array('address', 'hours', 'phones') as $field) {
				$data['contact'][$language['code']][$field] = isset($contact[$language['code']][$field]) ? $contact[$language['code']][$field] : '';
			}
		}

		$data['contact_links'] = is_array($links) ? array_values($links) : array();

		// store-wide info blocks of the product page (SPEC-048)
		$blocks = isset($this->request->post['theme_ocs_info_blocks']) ? $this->request->post['theme_ocs_info_blocks'] : (isset($setting_info['theme_ocs_info_blocks']) ? $setting_info['theme_ocs_info_blocks'] : array());

		$data['info_blocks'] = is_array($blocks) ? array_values($blocks) : array();
		$data['info_icons'] = array_keys(\Ocs\InfoBlocks::$icons);

		// top menu of the header (SPEC-050)
		$this->load->model('catalog/information');

		$data['top_pages'] = array();

		foreach ($this->model_catalog_information->getInformations() as $row) {
			$data['top_pages'][] = array('id' => (int)$row['information_id'], 'title' => $row['title']);
		}

		$top = isset($this->request->post['theme_ocs_top_menu']) ? $this->request->post['theme_ocs_top_menu'] : (isset($setting_info['theme_ocs_top_menu']) ? $setting_info['theme_ocs_top_menu'] : array());
		$top = is_array($top) ? $top : array();

		$data['top'] = array(
			'enabled'  => !empty($top['enabled']),
			'contacts' => !empty($top['contacts']),
			'blog'     => !empty($top['blog']),
			'sale'     => !empty($top['sale']),
			'title'    => isset($top['title']) && is_array($top['title']) ? $top['title'] : array(),
			'pages'    => array()
		);

		foreach (isset($top['pages']) && is_array($top['pages']) ? $top['pages'] : array() as $key => $value) {
			// saved form: a list of ids in order; posted form: id => [on, sort]
			if (is_array($value)) {
				$data['top']['pages'][(int)$key] = array('on' => !empty($value['on']), 'sort' => isset($value['sort']) ? (int)$value['sort'] : 0);
			} else {
				$data['top']['pages'][(int)$value] = array('on' => true, 'sort' => ((int)$key + 1) * 10);
			}
		}
		$data['roles'] = $this->roles;
		$data['version'] = '1.0.0';

		$data['setup'] = $this->url->link('extension/ocs/setup', $token, true);
		$data['setup_pending'] = !$this->config->get('ocs_package_version') && is_file(DIR_SYSTEM . 'library/ocs/setup/autoload.php');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/theme/ocs', $data));
	}

	// Called by extension/extension/theme/install (SPEC-040): core checks only the first two route parts (extension/ocs) for permission.
	public function install() {
		$this->load->model('user/user_group');

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/ocs');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/ocs');
	}

	// Called by extension/extension/theme/uninstall: core drops theme_ocs_* settings, so the storefront must not keep pointing at the theme.
	public function uninstall() {
		$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = 'default' WHERE `key` = 'config_theme' AND `value` = 'ocs'");
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/theme/ocs')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['theme_ocs_product_limit'])) {
			$this->error['product_limit'] = $this->language->get('error_limit');
		}

		if (empty($this->request->post['theme_ocs_product_description_length'])) {
			$this->error['product_description_length'] = $this->language->get('error_limit');
		}

		foreach ($this->roles as $role) {
			$w = 'theme_ocs_image_' . $role . '_width';
			$h = 'theme_ocs_image_' . $role . '_height';

			if (empty($this->request->post[$w]) || empty($this->request->post[$h])) {
				$this->error['image_' . $role] = $this->language->get('error_image');
			}
		}

		if (!$this->error) {
			$codes = array();

			foreach ($this->model_localisation_language->getLanguages() as $language) {
				$codes[] = $language['code'];
			}

			// only whitelisted icons and valid URLs reach the settings; texts are trimmed and cut (SPEC-047 REQ-SET-002)
			$this->request->post['theme_ocs_contact_links'] = \Ocs\Contacts::sanitize(isset($this->request->post['theme_ocs_contact_links']) ? $this->request->post['theme_ocs_contact_links'] : array(), $codes);

			$contact = array();

			foreach ($codes as $code) {
				foreach (array('address' => 255, 'hours' => 500, 'phones' => 500) as $field => $max) {
					$value = isset($this->request->post['theme_ocs_contact'][$code][$field]) ? trim((string)$this->request->post['theme_ocs_contact'][$code][$field]) : '';
					$contact[$code][$field] = function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
				}
			}

			$this->request->post['theme_ocs_contact'] = $contact;

			$this->load->model('catalog/information');

			$valid = array();

			foreach ($this->model_catalog_information->getInformations() as $row) {
				$valid[] = (int)$row['information_id'];
			}

			$this->request->post['theme_ocs_top_menu'] = \Ocs\TopMenu::sanitize(isset($this->request->post['theme_ocs_top_menu']) ? $this->request->post['theme_ocs_top_menu'] : array(), $codes, $valid);

			$this->request->post['theme_ocs_info_blocks'] = \Ocs\InfoBlocks::sanitize(isset($this->request->post['theme_ocs_info_blocks']) ? $this->request->post['theme_ocs_info_blocks'] : array(), $codes);
		}

		return !$this->error;
	}
}
