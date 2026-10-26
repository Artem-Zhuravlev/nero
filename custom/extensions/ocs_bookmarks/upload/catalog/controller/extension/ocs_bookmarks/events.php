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

			$this->session->data['wishlist'] = $wish;
			$this->session->data['compare'] = $compare;
		} catch (\Exception $e) {
			$this->log->write('[ocs_bookmarks] ' . $e->getMessage());
		}
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
