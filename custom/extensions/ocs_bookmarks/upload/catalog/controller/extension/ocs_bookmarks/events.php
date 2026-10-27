<?php
// Extension: ocs_bookmarks. Wishlist and compare live in cookies (ocs_wish, ocs_cmp); these events copy them into the session for the core code
// and hand the card state (cart ids, labels, endpoints) to the theme. Owner: SPEC-044 (REQ-BKM-001..003, REQ-CAR-002, 003).
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsBookmarksEvents extends Controller {
	private static $done = false;

	/**
	 * catalog/controller/common/header/before and catalog/controller/product/compare/before: cookies -> session (once per request).
	 * A logged-in customer's wishlist is merged with the account wishlist both ways.
	 */
	public function hydrate(&$route, &$args) {
		if (self::$done || !$this->enabled()) {
			return;
		}

		self::$done = true;

		try {
			$wish = \Ocs\Bookmarks::parse(isset($this->request->cookie[\Ocs\Bookmarks::WISH]) ? $this->request->cookie[\Ocs\Bookmarks::WISH] : '', \Ocs\Bookmarks::WISH_MAX);
			$compare = \Ocs\Bookmarks::parse(isset($this->request->cookie[\Ocs\Bookmarks::COMPARE]) ? $this->request->cookie[\Ocs\Bookmarks::COMPARE] : '', \Ocs\Bookmarks::COMPARE_MAX);

			if ($this->customer->isLogged()) {
				$this->load->model('account/wishlist');

				$account = array();

				foreach ($this->model_account_wishlist->getWishlist() as $row) {
					$account[(int)$row['product_id']] = (int)$row['product_id'];
				}

				foreach ($wish as $id) {
					if (!isset($account[$id])) {
						$this->model_account_wishlist->addWishlist($id);
						$account[$id] = $id;
					}
				}

				$merged = array_values(array_unique(array_merge($wish, array_values($account))));
				$merged = count($merged) > \Ocs\Bookmarks::WISH_MAX ? array_slice($merged, -\Ocs\Bookmarks::WISH_MAX) : $merged;

				if ($merged != $wish) {
					$wish = $merged;
					$this->setCookie(\Ocs\Bookmarks::WISH, $wish);
				}
			}

			// SPEC-057 REQ-CMP-006: a longer-than-allowed list can only exist in a category-heavy cookie, so the lookup runs only then
			if (count($compare) > \Ocs\Bookmarks::COMPARE_PER_CATEGORY) {
				$this->load->model('extension/ocs_bookmarks/compare');

				$trimmed = \Ocs\Bookmarks::trim($compare, $this->model_extension_ocs_bookmarks_compare->categories($compare), \Ocs\Bookmarks::COMPARE_MAX, \Ocs\Bookmarks::COMPARE_PER_CATEGORY);

				if ($trimmed != $compare) {
					$compare = $trimmed;
					$this->setCookie(\Ocs\Bookmarks::COMPARE, $compare);
				}
			}

			$this->session->data['wishlist'] = $wish;
			$this->session->data['compare'] = $compare;
		} catch (\Exception $e) {
			$this->log->write('[ocs_bookmarks] ' . $e->getMessage());
		}
	}

	/**
	 * catalog/controller/product/compare/before (after hydrate): the page shows one compare category (SPEC-057 REQ-CMP-002).
	 * session['compare'] is narrowed to the active group, so the core controller builds only those products; the cookie is not touched.
	 * A removal is done here too, to come back to the same tab.
	 */
	public function compareFilter(&$route, &$args) {
		if (!$this->enabled() || empty($this->session->data['compare']) || !is_array($this->session->data['compare'])) {
			return;
		}

		try {
			$this->load->model('extension/ocs_bookmarks/compare');

			$ids = array_values($this->session->data['compare']);
			$of = $this->model_extension_ocs_bookmarks_compare->categories($ids);
			$groups = \Ocs\Bookmarks::groups($ids, $of);
			$wanted = isset($this->request->get['category']) ? (int)$this->request->get['category'] : -1;

			if (isset($this->request->get['remove'])) {
				$gone = (int)$this->request->get['remove'];

				$this->session->data['compare'] = array_values(array_diff($ids, array($gone)));
				$this->load->language('product/compare');
				$this->session->data['success'] = $this->language->get('text_remove');

				$remaining = \Ocs\Bookmarks::groups($this->session->data['compare'], $of);

				$this->response->redirect($this->url->link('product/compare', isset($remaining[$wanted]) ? 'category=' . $wanted : ''));
			}

			if (!$groups) {
				return;
			}

			$active = isset($groups[$wanted]) ? $wanted : key($groups);

			$this->load->language('extension/ocs_bookmarks/bookmarks');
			$names = $this->model_extension_ocs_bookmarks_compare->names(array_keys($groups));
			$tabs = array();

			foreach ($groups as $category => $members) {
				$tabs[] = array(
					'category_id' => $category,
					'name'        => $category && isset($names[$category]) ? $names[$category] : $this->language->get('text_cmp_other'),
					'count'       => count($members),
					'href'        => $this->url->link('product/compare', 'category=' . $category),
					'active'      => $category === $active
				);
			}

			self::$view = array('tabs' => $tabs, 'active' => $active);
			$this->session->data['compare'] = array_slice($groups[$active], -\Ocs\Bookmarks::COMPARE_PER_CATEGORY);
		} catch (\Exception $e) {
			$this->log->write('[ocs_bookmarks] ' . $e->getMessage());
		}
	}

	/**
	 * catalog/view/product/compare/before: tabs, characteristic groups with rows and a "same" flag per row (SPEC-057 REQ-CMP-003, 005, 007).
	 * Without the filter data (disabled, failed) the template renders the flat core table.
	 */
	public function compareView(&$route, &$data) {
		if (!self::$view || !is_array($data) || empty($data['products'])) {
			return;
		}

		try {
			$products = $data['products'];
			$groups = array();

			$general = array();
			$general[] = $this->row('model', $this->language->get('text_model'), $products, function ($p) {
				return isset($p['model']) ? $p['model'] : '';
			});
			$general[] = $this->row('manufacturer', $this->language->get('text_manufacturer'), $products, function ($p) {
				return isset($p['manufacturer']) ? $p['manufacturer'] : '';
			});
			$general[] = $this->row('description', $this->language->get('text_summary'), $products, function ($p) {
				return isset($p['description']) ? $p['description'] : '';
			});
			$general[] = $this->row('weight', $this->language->get('text_weight'), $products, function ($p) {
				return isset($p['weight']) ? $p['weight'] : '';
			});
			$general[] = $this->row('dimension', $this->language->get('text_dimension'), $products, function ($p) {
				$parts = array(isset($p['length']) ? $p['length'] : '', isset($p['width']) ? $p['width'] : '', isset($p['height']) ? $p['height'] : '');

				return trim(implode('', $parts)) === '' ? '' : implode(' x ', $parts);
			});

			$groups[] = array('name' => $this->language->get('text_cmp_general'), 'rows' => $this->visible($general));

			if (!empty($data['attribute_groups'])) {
				foreach ($data['attribute_groups'] as $group) {
					$rows = array();

					foreach ($group['attribute'] as $attribute_id => $attribute) {
						$rows[] = $this->row('attr', $attribute['name'], $products, function ($p) use ($attribute_id) {
							return isset($p['attribute'][$attribute_id]) ? $p['attribute'][$attribute_id] : '';
						});
					}

					$groups[] = array('name' => $group['name'], 'rows' => $this->visible($rows));
				}
			}

			$groups = array_values(array_filter($groups, function ($g) {
				return !empty($g['rows']);
			}));

			foreach ($data['products'] as $id => $product) {
				$data['products'][$id]['remove'] = $this->url->link('product/compare', 'remove=' . (int)$id . '&category=' . self::$view['active']);
			}

			$category = self::$view['active'] > 0 ? $this->url->link('product/category', 'path=' . self::$view['active']) : $this->url->link('common/home');

			$data['ocs_cmp'] = array(
				'tabs'     => self::$view['tabs'],
				'groups'   => $groups,
				'single'   => count($products) < 2,
				'category' => $category,
				't'        => array(
					'only_diff' => $this->language->get('text_cmp_only_diff'),
					'same'      => $this->language->get('text_cmp_same'),
					'more'      => $this->language->get('text_cmp_more'),
					'category'  => $this->language->get('text_cmp_category'),
					'groups'    => $this->language->get('text_cmp_group')
				)
			);
		} catch (\Exception $e) {
			$this->log->write('[ocs_bookmarks] ' . $e->getMessage());
		}
	}

	private static $view = null;

	private function row($key, $name, array $products, $cell) {
		$cells = array();
		$flat = array();
		$empty = true;

		foreach ($products as $id => $product) {
			$text = (string)$cell($product);
			$cells[] = $text;
			$flat[] = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8')));
			$empty = $empty && end($flat) === '';
		}

		return array('key' => $key, 'name' => $name, 'cells' => $cells, 'empty' => $empty, 'same' => count(array_unique($flat)) === 1);
	}

	private function visible(array $rows) {
		return array_values(array_filter($rows, function ($r) {
			return !$r['empty'];
		}));
	}

	/**
	 * catalog/view/*\/before: every wishlist link points to the cookie-based page; the header carries the card state for theme.js.
	 */
	public function beforeView(&$route, &$data) {
		if (!is_array($data) || !$this->enabled()) {
			return;
		}

		try {
			if (isset($data['wishlist']) && is_string($data['wishlist'])) {
				$data['wishlist'] = $this->url->link('extension/ocs_bookmarks/wishlist', '', true);
			}

			if ($route !== 'common/header') {
				return;
			}

			$strings = $this->load->language('extension/ocs_bookmarks/bookmarks');
			$strings = is_array($strings) ? $strings : array();
			$ids = array();

			foreach ($this->cart->getProducts() as $line) {
				$ids[(int)$line['product_id']] = (int)$line['product_id'];
			}

			$data['ocs_bk'] = array(
				'sync'    => $this->url->link('extension/ocs_bookmarks/sync', '', true),
				'cart'    => $this->url->link('checkout/cart', '', true),
				'cart_ids' => implode(',', array_values($ids)),
				'days'    => \Ocs\Bookmarks::DAYS,
				'strings' => htmlspecialchars(json_encode(array(
					'in_cart'  => isset($strings['text_in_cart']) ? $strings['text_in_cart'] : '',
					'wish_on'  => isset($strings['text_wish_on']) ? $strings['text_wish_on'] : '',
					'wish_off' => isset($strings['text_wish_off']) ? $strings['text_wish_off'] : '',
					'cmp_on'   => isset($strings['text_cmp_on']) ? $strings['text_cmp_on'] : '',
					'cmp_off'  => isset($strings['text_cmp_off']) ? $strings['text_cmp_off'] : '',
					'wish_label' => sprintf(isset($strings['text_wish_total']) ? $strings['text_wish_total'] : '%s', '{n}'),
					'cmp_label'  => sprintf(isset($strings['text_cmp_total']) ? $strings['text_cmp_total'] : '%s', '{n}')
				)), ENT_QUOTES, 'UTF-8')
			);
		} catch (\Exception $e) {
			$this->log->write('[ocs_bookmarks] ' . $e->getMessage());
		}
	}

	private function setCookie($name, array $ids) {
		if (!headers_sent()) {
			setcookie($name, \Ocs\Bookmarks::serialize($ids), array(
				'expires'  => time() + 86400 * \Ocs\Bookmarks::DAYS,
				'path'     => '/',
				'secure'   => !empty($this->request->server['HTTPS']),
				'httponly' => false,
				'samesite' => 'Lax'
			));
		}
	}

	private function enabled() {
		return $this->config->get('config_theme') == 'ocs';
	}
}
