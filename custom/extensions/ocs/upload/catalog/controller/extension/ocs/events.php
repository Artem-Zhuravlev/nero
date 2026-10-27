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
	 * catalog/model/tool/image/resize/before: core passes unset theme size settings (null) to Image::resize(int, int), a fatal error;
	 * replaces a missing size by a default (migration 0009).
	 */
	public function beforeImageResize(&$route, &$args) {
		if (!is_array($args)) {
			return;
		}

		foreach (array(1 => 228, 2 => 228) as $i => $default) {
			if (!isset($args[$i]) || (int)$args[$i] <= 0) {
				$args[$i] = $default;
			}
		}
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

		// regular prices of the cart lines that are reduced by a special or a quantity discount (SPEC-050 REQ-CRT-002)
		if ($route === 'checkout/cart') {
			$this->cartOldPrices($data);
		}

		// top menu of the header (SPEC-050): information pages from the settings, contacts, blog, sale
		if ($route === 'common/header') {
			$data['ocs_top'] = $this->topMenu();
		}

		// default footer groups without the hidden links, payment logos switch (SPEC-072)
		if ($route === 'common/footer') {
			$data['ocs_foot'] = $this->footer($data);
		}

		// store-wide info blocks of the product page (SPEC-048 REQ-INF-002)
		if ($route === 'product/product') {
			$this->load->model('setting/setting');

		// config_language is the visitor's language at run time; the store default is read from the settings table (fallback for untranslated contacts)
		$default = (string)$this->model_setting_setting->getSettingValue('config_language', (int)$this->config->get('config_store_id'));

			$data['ocs_info_blocks'] = \Ocs\InfoBlocks::view($this->config->get('theme_ocs_info_blocks'), $this->config->get('config_language'), $default);
		}

		try {
			$data = \Ocs\Display::apply($data, \Ocs\Display::on($this->config->get('theme_ocs_show_stock')), \Ocs\Display::on($this->config->get('theme_ocs_show_sku')));
		} catch (\Exception $e) {
			$this->log->write('[ocs] ' . $e->getMessage());
		}
	}

	/**
	 * Display only: the cart totals stay the core's. The old unit price is the regular product price plus the same option surcharge, never below the current price.
	 */
	private function cartOldPrices(&$data) {
		if (empty($data['products']) || !is_array($data['products'])) {
			return;
		}

		try {
			$this->load->model('catalog/product');

			$lines = array();

			foreach ($this->cart->getProducts() as $line) {
				$lines[(string)$line['cart_id']] = $line;
			}

			$currency = $this->session->data['currency'];

			foreach ($data['products'] as $i => $row) {
				if (!isset($row['cart_id'], $lines[(string)$row['cart_id']])) {
					continue;
				}

				$line = $lines[(string)$row['cart_id']];
				$info = $this->model_catalog_product->getProduct((int)$line['product_id']);

				if (!$info) {
					continue;
				}

				$surcharge = 0.0;

				foreach ($line['option'] as $option) {
					$surcharge += ($option['price_prefix'] === '-' ? -1 : 1) * (float)$option['price'];
				}

				$current = (float)$line['price'];
				$old = (float)$info['price'] + $surcharge;

				if ($old > $current + 0.004) {
					$data['products'][$i]['old_price'] = $this->currency->format($this->tax->calculate($old, $line['tax_class_id'], $this->config->get('config_tax')), $currency);
					$data['products'][$i]['old_total'] = $this->currency->format($this->tax->calculate($old, $line['tax_class_id'], $this->config->get('config_tax')) * (int)$line['quantity'], $currency);
				}
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs] ' . $e->getMessage());
		}
	}

	private function footer($data) {
		try {
			$this->load->model('catalog/information');

			$pages = array();

			foreach ($this->model_catalog_information->getInformations() as $row) {
				if ($row['bottom']) {
					$pages[] = array('id' => (int)$row['information_id'], 'title' => $row['title'], 'href' => $this->url->link('information/information', 'information_id=' . (int)$row['information_id']));
				}
			}

			return \Ocs\Footer::view($this->config->get('theme_ocs_footer'), $pages, $data);
		} catch (\Exception $e) {
			$this->log->write('[ocs] ' . $e->getMessage());
		}

		return null;
	}

	private function topMenu() {
		try {
			$cfg = $this->config->get('theme_ocs_top_menu');

			if (!is_array($cfg) || empty($cfg['enabled'])) {
				return null;
			}

			$strings = $this->load->language('extension/ocs/top');
			$strings = is_array($strings) ? $strings : array();
			$str = function ($k) use ($strings) {
				return isset($strings[$k]) ? $strings[$k] : '';
			};

			$this->load->model('catalog/information');
			$this->load->model('setting/setting');

			$titles = array();
			$pages = array();

			foreach ($this->model_catalog_information->getInformations() as $row) {
				$titles[(int)$row['information_id']] = $row['title'];
				$pages[(int)$row['information_id']] = $this->url->link('information/information', 'information_id=' . (int)$row['information_id']);
			}

			$links = array(
				'contacts' => array('href' => $this->url->link('information/contact'), 'title' => $str('text_contacts')),
				'sale'     => array('href' => $this->url->link('product/special'), 'title' => $str('text_sale')),
				'pages'    => $pages
			);

			// the blog module of ocStore: its home page exists when the module is on and the controller is installed
			if ($this->config->get('configblog_blog_menu') && is_file(DIR_APPLICATION . 'controller/blog/latest.php')) {
				$links['blog'] = array('href' => $this->url->link('blog/latest'), 'title' => $str('text_blog'));
			}

			$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : 'common/home';
			$active = '';

			if ($route === 'information/information' && isset($this->request->get['information_id'])) {
				$active = 'information:' . (int)$this->request->get['information_id'];
			} elseif ($route === 'information/contact') {
				$active = 'contacts';
			} elseif ($route === 'product/special') {
				$active = 'sale';
			} elseif (strpos($route, 'blog/') === 0) {
				$active = 'blog';
			}

			$default = (string)$this->model_setting_setting->getSettingValue('config_language', (int)$this->config->get('config_store_id'));

			return \Ocs\TopMenu::view($cfg, $this->config->get('config_language'), $default, $str('text_customers'), $titles, $links, $active);
		} catch (\Exception $e) {
			$this->log->write('[ocs] ' . $e->getMessage());
		}

		return null;
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
