<?php
// Extension: ocs_bookmarks. Wishlist page that works without login: the list comes from the ocs_wish cookie (merged with the account list
// for a logged-in customer by the hydrate event). Owner: SPEC-044 (REQ-BKM-001..003).

class ControllerExtensionOcsBookmarksWishlist extends Controller {
	public function index() {
		$this->load->language('account/wishlist');
		$this->load->language('extension/ocs_bookmarks/bookmarks');

		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		// no-JS fallback of the remove link: ?remove=ID
		if (isset($this->request->get['remove'])) {
			$this->forget((int)$this->request->get['remove']);

			$this->response->redirect($this->url->link('extension/ocs_bookmarks/wishlist', '', true));
		}

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_empty'] = $this->language->get('text_empty');
		$data['success'] = '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/ocs_bookmarks/wishlist', '', true)
		);

		$ids = isset($this->session->data['wishlist']) && is_array($this->session->data['wishlist']) ? $this->session->data['wishlist'] : \Ocs\Bookmarks::parse(isset($this->request->cookie[\Ocs\Bookmarks::WISH]) ? $this->request->cookie[\Ocs\Bookmarks::WISH] : '', \Ocs\Bookmarks::WISH_MAX);
		$data['products'] = array();

		foreach (array_reverse($ids) as $id) {
			$product_info = $this->model_catalog_product->getProduct((int)$id);

			// a disabled / deleted product is dropped silently
			if (!$product_info) {
				continue;
			}

			$image = $product_info['image'] ? $this->model_tool_image->resize($product_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_height')) : false;

			if ($product_info['quantity'] <= 0) {
				$stock = $product_info['stock_status'];
			} elseif ($this->config->get('config_stock_display')) {
				$stock = $product_info['quantity'];
			} else {
				$stock = $this->language->get('text_instock');
			}

			$show_price = $this->customer->isLogged() || !$this->config->get('config_customer_price');

			$data['products'][] = array(
				'product_id' => $product_info['product_id'],
				'thumb'      => $image,
				'name'       => $product_info['name'],
				'model'      => $product_info['model'],
				'stock'      => $stock,
				'price'      => $show_price ? $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']) : false,
				'special'    => $show_price && (float)$product_info['special'] ? $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']) : false,
				'minimum'    => $product_info['minimum'] > 0 ? $product_info['minimum'] : 1,
				'href'       => $this->url->link('product/product', 'product_id=' . $product_info['product_id']),
				'remove'     => $this->url->link('extension/ocs_bookmarks/wishlist', 'remove=' . $product_info['product_id'], true)
			);
		}

		$data['continue'] = $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/wishlist', $data));
	}

	private function forget($id) {
		$ids = isset($this->session->data['wishlist']) && is_array($this->session->data['wishlist']) ? $this->session->data['wishlist'] : array();
		$ids = \Ocs\Bookmarks::change($ids, 'remove', $id, \Ocs\Bookmarks::WISH_MAX);

		$this->session->data['wishlist'] = $ids;

		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');
			$this->model_account_wishlist->deleteWishlist($id);
		}

		if (!headers_sent()) {
			setcookie(\Ocs\Bookmarks::WISH, \Ocs\Bookmarks::serialize($ids), array(
				'expires'  => time() + 86400 * \Ocs\Bookmarks::DAYS,
				'path'     => '/',
				'secure'   => !empty($this->request->server['HTTPS']),
				'httponly' => false,
				'samesite' => 'Lax'
			));
		}
	}
}
