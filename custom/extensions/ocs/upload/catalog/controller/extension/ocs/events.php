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

		// font chosen in the theme settings (SPEC-121); any problem leaves the template on its built-in Montserrat
		try {
			$font = \Ocs\Fonts::render($this->config->get('theme_ocs_font'), DIR_APPLICATION . 'view/theme/ocs/fonts/');

			$data['font_css'] = $font['css'];
			$data['font_preload'] = $font['preload'];
		} catch (\Throwable $e) {
		}
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

		// brand search texts of the brands page (SPEC-074)
		if ($route === 'product/manufacturer_list') {
			$strings = $this->load->language('extension/ocs/top');
			$data['ocs_brand_search'] = isset($strings['text_brand_search']) ? $strings['text_brand_search'] : '';
			$data['ocs_brand_none'] = isset($strings['text_brand_none']) ? $strings['text_brand_none'] : '';
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

			// quantity stepper limits: step = product minimum, max = stock when the cart refuses more (SPEC-101 REQ-QTY-002, REQ-QTY-004)
			$step = max(1, isset($data['minimum']) ? (int)$data['minimum'] : 1);
			$max = 0;

			if (!empty($this->request->get['product_id'])) {
				$this->load->model('catalog/product');

				$stock_info = $this->model_catalog_product->getProduct((int)$this->request->get['product_id']);

				if ($stock_info && !empty($stock_info['subtract']) && !$this->config->get('config_stock_checkout')) {
					$max = (int)floor(max(0, (int)$stock_info['quantity']) / $step) * $step;
				}
			}

			$data['ocs_qty'] = array('min' => $step, 'max' => $max);

			// countdown to the end of the special applied now (SPEC-086 REQ-TMR-001); the core ends a special at 00:00 of date_end
			$data['ocs_special_timer'] = null;

			if (!empty($data['special']) && !empty($data['price']) && !empty($this->request->get['product_id'])) {
				$end = $this->specialEnd((int)$this->request->get['product_id']);

				if ($end && (int)$end['seconds'] > 0) {
					$strings = $this->load->language('extension/ocs/top');
					$data['ocs_special_timer'] = array(
						'seconds' => (int)$end['seconds'],
						'label'   => isset($strings['text_special_timer']) ? $strings['text_special_timer'] : '',
						'days'    => isset($strings['text_special_days']) ? $strings['text_special_days'] : ''
					);
				}
			}

			// how much the customer saves with the special, in the visitor's currency (SPEC-091 REQ-SAV-002)
			$data['ocs_savings'] = '';

			if (!empty($data['special']) && !empty($data['price']) && !empty($this->request->get['product_id'])) {
				$this->load->model('catalog/product');

				$info = $this->model_catalog_product->getProduct((int)$this->request->get['product_id']);

				if ($info && !is_null($info['special'])) {
					$tax = $this->config->get('config_tax');
					$currency = $this->session->data['currency'];
					$saved = $this->tax->calculate($info['price'], $info['tax_class_id'], $tax) - $this->tax->calculate($info['special'], $info['tax_class_id'], $tax);

					if ($this->currency->format($saved, $currency, '', false) > 0) {
						$data['ocs_savings'] = $this->currency->format($saved, $currency);
						$strings = $this->load->language('extension/ocs/top');
						$data['ocs_savings_label'] = isset($strings['text_savings']) ? $strings['text_savings'] : '';
					}
				}
			}

			$data['ocs_info_blocks'] = \Ocs\InfoBlocks::view($this->config->get('theme_ocs_info_blocks'), $this->config->get('config_language'), $default);
		}

		try {
			$data = \Ocs\Display::apply($data, \Ocs\Display::on($this->config->get('theme_ocs_show_stock')), \Ocs\Display::on($this->config->get('theme_ocs_show_sku')));
		} catch (\Exception $e) {
			$this->log->write('[ocs] ' . $e->getMessage());
		}
	}

	/**
	 * The special the core applies now (same filter and order as the product model): its end date and the seconds left, or null when it is open-ended or there is none (SPEC-086).
	 */
	private function specialEnd($product_id) {
		$row = $this->db->query("SELECT date_end, TIMESTAMPDIFF(SECOND, NOW(), date_end) AS seconds FROM `" . DB_PREFIX . "product_special` WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND (date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW()) ORDER BY priority ASC, price ASC LIMIT 1")->row;

		if (!$row || $row['date_end'] === '0000-00-00') {
			return null;
		}

		return $row;
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

			// core's event/language merges the strings into the view data at order 999, after this event (99): read them here
			foreach ($this->language->all() as $key => $value) {
				if (!isset($data[$key])) {
					$data[$key] = $value;
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

	/** @var bool the page already got its JSON-LD */
	private static $ldDone = false;

	/** @var array product carousels of the modules rendered so far: [name, items => [href]] */
	private static $ldLists = array();

	/**
	 * catalog/view/ * /after: SPEC-080. Product carousel modules are remembered while they render; the first full page (output with a head) gets one
	 * JSON-LD script before </head>. Partials, AJAX answers, non-GET requests and error pages are left alone. Never throws to the customer.
	 */
	public function structuredData(&$route, &$data, &$output) {
		if (!is_array($data) || !is_string($output) || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		if (strpos($route, 'extension/module/') === 0) {
			if (!empty($data['products']) && is_array($data['products']) && count(self::$ldLists) < 10) {
				self::$ldLists[] = array(isset($data['heading_title']) ? $data['heading_title'] : '', $data['products']);
			}

			return;
		}

		if (self::$ldDone || strpos($output, '</head>') === false || in_array($route, array('common/header', 'common/footer', 'common/maintenance'), true) || strpos($route, 'error/') === 0) {
			return;
		}

		$status = $this->config->get('theme_ocs_schema_status');

		if (($status !== null && !$status) || !isset($this->request->server['REQUEST_METHOD']) || $this->request->server['REQUEST_METHOD'] !== 'GET') {
			return;
		}

		try {
			$output = \Ocs\StructuredData::inject($output, $this->ldDocument($route, $data));
			self::$ldDone = true;
		} catch (\Throwable $e) {
			$this->log->write('[ocs] structured data: ' . $e->getMessage());
		}
	}

	/**
	 * @return string JSON-LD document of the page (REQ-SD-002..008)
	 */
	private function ldDocument($route, $data) {
		$home = html_entity_decode($this->url->link('common/home'), ENT_QUOTES, 'UTF-8');
		$parts = parse_url($home);

		if (!$parts || empty($parts['host'])) {
			return '';
		}

		$https = !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off';

		\Ocs\StructuredData::$scheme = $https ? 'https' : 'http';
		\Ocs\StructuredData::$host = $parts['host'];
		\Ocs\StructuredData::$base = \Ocs\StructuredData::$scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

		$home = \Ocs\StructuredData::url($home);
		$server = $https ? $this->config->get('config_ssl') : $this->config->get('config_url');
		$code = (string)$this->config->get('config_language');
		$lang = \Ocs\StructuredData::lang($code);

		// the page itself: the canonical link of the controller, else the requested URL
		$page_url = '';

		foreach ($this->document->getLinks() as $link) {
			if ($link['rel'] === 'canonical') {
				$page_url = $link['href'];
			}
		}

		if ($page_url === '' && isset($this->request->server['REQUEST_URI'])) {
			$page_url = \Ocs\StructuredData::$base . strtok($this->request->server['REQUEST_URI'], '#');
		}

		$page_url = \Ocs\StructuredData::url($page_url, true);

		if ($page_url === '') {
			return '';
		}

		$org_id = $home . '#organization';
		$site_id = $home . '#website';
		$org = $this->ldOrganization($org_id, $home, $server, $code);

		$nodes = array(\Ocs\StructuredData::organization($org), \Ocs\StructuredData::website(array('id' => $site_id, 'name' => $org['name'], 'url' => $home, 'lang' => $lang, 'publisher' => $org_id)));

		$crumbs = isset($data['breadcrumbs']) && is_array($data['breadcrumbs']) ? $data['breadcrumbs'] : array();
		$crumbs_id = $page_url . '#breadcrumb';
		$crumb_node = \Ocs\StructuredData::breadcrumbs($crumbs_id, $crumbs);

		$type = 'WebPage';
		$name = isset($data['heading_title']) ? $data['heading_title'] : '';
		$main = '';
		$page = max(1, isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1);
		$limit = !empty($data['limit']) ? (int)$data['limit'] : 0;

		switch ($route) {
			case 'common/home':
				$name = $this->config->get('config_meta_title');

				foreach (self::$ldLists as $i => $list) {
					$nodes[] = \Ocs\StructuredData::itemList($page_url . '#carousel-' . ($i + 1), $list[0], $list[1]);
				}

				break;

			case 'product/category':
			case 'product/manufacturer_info':
			case 'product/special':
			case 'product/search':
				$type = $route === 'product/search' ? 'SearchResultsPage' : 'CollectionPage';
				$products = isset($data['products']) && is_array($data['products']) ? $data['products'] : array();
				$list = \Ocs\StructuredData::itemList($page_url . '#list', $name, $products, ($page - 1) * ($limit ?: count($products)));

				if ($list) {
					$nodes[] = $list;
					$main = $list['@id'];
				}

				break;

			case 'product/manufacturer_list':
				$type = 'CollectionPage';
				$brands = array();

				foreach (isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : array() as $group) {
					foreach (isset($group['manufacturer']) ? (array)$group['manufacturer'] : array() as $brand) {
						$brands[] = $brand;
					}
				}

				$list = \Ocs\StructuredData::itemList($page_url . '#list', $name, $brands);

				if ($list) {
					$nodes[] = $list;
					$main = $list['@id'];
				}

				break;

			case 'product/product':
				$product = $this->ldProduct($data, $page_url, $org);

				if ($product) {
					$nodes[] = $product;
					$main = $product['@id'];
				}

				foreach (array('accessories' => 'text_accessories', 'similar' => 'text_similar') as $key => $title) {
					if (!empty($data['ocs_px'][$key])) {
						$nodes[] = \Ocs\StructuredData::itemList($page_url . '#' . $key, isset($data['ocs_px'][$title]) ? $data['ocs_px'][$title] : '', $data['ocs_px'][$key]);
					}
				}

				break;

			case 'blog/article':
				$article = $this->ldArticle($data, $page_url, $org);

				if ($article) {
					$nodes[] = $article;
					$main = $article['@id'];
				}

				break;

			case 'blog/category':
			case 'blog/latest':
				$type = $route === 'blog/latest' ? 'Blog' : 'CollectionPage';
				$articles = isset($data['articles']) && is_array($data['articles']) ? $data['articles'] : array();
				$list = \Ocs\StructuredData::itemList($page_url . '#list', $name, $articles, ($page - 1) * ($limit ?: count($articles)));

				if ($list) {
					$nodes[] = $list;
					$main = $list['@id'];
				}

				break;

			case 'information/contact':
				$type = 'ContactPage';

				break;
		}

		$nodes[] = \Ocs\StructuredData::page(array('type' => $type, 'url' => $page_url, 'name' => $name, 'lang' => $lang, 'website' => $site_id, 'breadcrumb' => $crumb_node ? $crumbs_id : '', 'main' => $main));
		$nodes[] = $crumb_node;

		return \Ocs\StructuredData::graph($nodes);
	}

	/**
	 * Organization of the store: name and logo from the schema settings (store name / logo by default), profiles, phones and address from the contacts of the theme (SPEC-047).
	 */
	private function ldOrganization($id, $home, $server, $code) {
		$this->load->model('setting/setting');

		$default = (string)$this->model_setting_setting->getSettingValue('config_language', (int)$this->config->get('config_store_id'));
		$contacts = \Ocs\Contacts::view($this->config->get('theme_ocs_contact'), $this->config->get('theme_ocs_contact_links'), $code, $default);

		$same = array();

		foreach ($contacts['links'] as $link) {
			if ($link['kind'] === 'social' && !in_array($link['key'], array('email', 'phone', 'map'), true)) {
				$same[] = $link['href'];
			}
		}

		$name = trim((string)$this->config->get('theme_ocs_schema_org_name'));
		$logo = trim((string)$this->config->get('theme_ocs_schema_org_logo'));
		$logo = $logo !== '' ? $logo : (string)$this->config->get('config_logo');

		return array(
			'id'      => $id,
			'name'    => $name !== '' ? $name : $this->config->get('config_name'),
			'url'     => $home,
			'logo'    => $logo !== '' && is_file(DIR_IMAGE . $logo) ? $server . 'image/' . $logo : '',
			'same_as' => $same,
			'phones'  => array_column($contacts['phones'], 'href'),
			'address' => $contacts['address']
		);
	}

	/**
	 * Product node (REQ-SD-004, REQ-SD-005): the facts come from the product row and the prices as shown on the page (same tax and currency).
	 */
	private function ldProduct($data, $page_url, $org) {
		if (empty($data['product_id'])) {
			return null;
		}

		$this->load->model('catalog/product');

		$info = $this->model_catalog_product->getProduct((int)$data['product_id']);

		if (!$info) {
			return null;
		}

		$currency = $this->session->data['currency'];
		$price = false;

		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			$amount = $this->tax->calculate(!is_null($info['special']) ? $info['special'] : $info['price'], $info['tax_class_id'], $this->config->get('config_tax'));
			$price = $this->currency->format($amount, $currency, '', false);
		}

		$until = '';

		if ($price !== false && !is_null($info['special'])) {
			$row = $this->specialEnd((int)$info['product_id']);
			$until = $row ? $row['date_end'] : '';
		}

		$images = array();

		if (!empty($data['popup'])) {
			$images[] = $data['popup'];
		}

		foreach (isset($data['images']) && is_array($data['images']) ? $data['images'] : array() as $image) {
			$images[] = isset($image['popup']) ? $image['popup'] : '';
		}

		$reviews = array();
		$total = 0;

		if ($this->config->get('config_review_status') && (int)$info['reviews'] > 0) {
			$this->load->model('catalog/review');

			$total = (int)$info['reviews'];

			foreach ($this->model_catalog_review->getReviewsByProductId((int)$info['product_id'], 0, \Ocs\StructuredData::MAX_REVIEWS) as $row) {
				$reviews[] = array('author' => $row['author'], 'rating' => $row['rating'], 'date' => $row['date_added'], 'text' => $row['text']);
			}
		}

		return \Ocs\StructuredData::product(array(
			'name'         => isset($data['heading_title']) ? $data['heading_title'] : $info['name'],
			'url'          => $page_url,
			'images'       => $images,
			'description'  => isset($data['description']) ? $data['description'] : '',
			'sku'          => !empty($info['sku']) ? $info['sku'] : $info['model'],
			'mpn'          => !empty($info['mpn']) ? $info['mpn'] : $info['model'],
			'gtin'         => array(isset($info['ean']) ? $info['ean'] : '', isset($info['upc']) ? $info['upc'] : '', isset($info['jan']) ? $info['jan'] : ''),
			'brand'        => $info['manufacturer'],
			'price'        => $price,
			'currency'     => $currency,
			'in_stock'     => (int)$info['quantity'] > 0,
			'valid_until'  => $until,
			'seller'       => $org,
			'rating'       => $info['rating'],
			'review_total' => $total,
			'reviews'      => $reviews
		));
	}

	/**
	 * BlogPosting node (REQ-SD-007).
	 */
	private function ldArticle($data, $page_url, $org) {
		if (empty($data['article_id'])) {
			return null;
		}

		$this->load->model('blog/article');

		$info = $this->model_blog_article->getArticle((int)$data['article_id']);

		if (!$info) {
			return null;
		}

		$server = !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off' ? $this->config->get('config_ssl') : $this->config->get('config_url');

		$published = $info['date_added'];
		$person = null;

		// SPEC-084: publication date and named author from the ocs_blog extension, when it is installed.
		if (class_exists('\Ocs\Blog')) {
			try {
				$this->load->model('extension/ocs_blog/blog');

				$meta = $this->model_extension_ocs_blog_blog->getArticleMeta(array((int)$data['article_id']));
				$authors = $this->model_extension_ocs_blog_blog->getAuthors(array((int)$data['article_id']));

				if (isset($meta[(int)$data['article_id']]) && $meta[(int)$data['article_id']]['date']) {
					$published = $meta[(int)$data['article_id']]['date'];
				}

				if (isset($authors[(int)$data['article_id']])) {
					$author = $authors[(int)$data['article_id']];
					$author['image_url'] = !empty($author['image']) && is_file(DIR_IMAGE . $author['image']) ? $server . 'image/' . $author['image'] : '';
					$author['social'] = $author['social'];

					$person = \Ocs\Blog::person($author);

					if ($person && !empty($author['social'])) {
						$person['sameAs'] = array_values($author['social']);
					}
				}
			} catch (\Exception $e) {
				$this->log->write('[ocs] blog author: ' . $e->getMessage());
			}
		}

		return \Ocs\StructuredData::article(array(
			'url'         => $page_url,
			'headline'    => isset($data['heading_title']) ? $data['heading_title'] : $info['name'],
			'image'       => !empty($info['image']) && is_file(DIR_IMAGE . $info['image']) ? $server . 'image/' . $info['image'] : '',
			'description' => !empty($info['meta_description']) ? $info['meta_description'] : (isset($data['description']) ? $data['description'] : ''),
			'published'   => $published,
			'modified'    => $info['date_modified'],
			'publisher'   => $org,
			'person'      => $person
		));
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
