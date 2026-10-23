<?php
// Extension: ocs_product_extras. Pure helpers of the product page extras (tested without a database). Owner: SPEC-035.

namespace Ocs;

class ProductExtras {
	/**
	 * Up to $max attributes for the block under the title.
	 *
	 * @param array $groups core attribute groups: each with 'attribute' => [attribute_id, name, text]
	 * @param int[] $wanted attribute ids chosen in the settings, in display order; empty = the first ones of the product
	 * @return array[] each array('name' => ..., 'text' => ...)
	 */
	public static function keyAttributes($groups, $wanted, $max = 4) {
		$all = array();

		foreach ((array)$groups as $group) {
			foreach ((array)(isset($group['attribute']) ? $group['attribute'] : array()) as $a) {
				if (isset($a['name'], $a['text']) && trim((string)$a['text']) !== '') {
					$all[isset($a['attribute_id']) ? (int)$a['attribute_id'] : count($all) + 100000] = array('name' => $a['name'], 'text' => $a['text']);
				}
			}
		}

		$out = array();

		foreach (array_filter(array_map('intval', (array)$wanted)) as $id) {
			if (isset($all[$id])) {
				$out[] = $all[$id];
			}
		}

		if (!$out && !$wanted) {
			$out = array_values($all);
		}

		return array_slice($out, 0, $max);
	}

	/**
	 * Price band of the "similar" block: array(min, max).
	 */
	public static function priceBand($price, $percent = 30) {
		$price = max(0.0, (float)$price);

		return array(round($price * (100 - $percent) / 100, 4), round($price * (100 + $percent) / 100, 4));
	}

	/**
	 * Comma separated ids -> unique positive integers in the given order.
	 */
	public static function ids($text, $max = 50) {
		return array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$text)), function ($v) {
			return $v > 0;
		}))), 0, $max);
	}

	/**
	 * Star distribution of the reviews: rating (5..1) => count -> total, average and one row per rating with its share in percent.
	 */
	public static function stars($counts) {
		$total = 0;
		$sum = 0;

		foreach ((array)$counts as $rating => $n) {
			$total += (int)$n;
			$sum += (int)$rating * (int)$n;
		}

		$rows = array();

		foreach (array(5, 4, 3, 2, 1) as $rating) {
			$n = isset($counts[$rating]) ? (int)$counts[$rating] : 0;

			$rows[] = array('rating' => $rating, 'count' => $n, 'pct' => $total ? (int)round($n * 100 / $total) : 0);
		}

		return array('total' => $total, 'avg' => $total ? round($sum / $total, 1) : 0, 'rows' => $rows);
	}

	/**
	 * Validates a question. Returns array('errors' => field => code, 'clean' => ...).
	 */
	public static function validateQuestion($post) {
		$errors = array();

		$name = isset($post['name']) ? trim(preg_replace('/\s+/u', ' ', (string)$post['name'])) : '';
		$email = isset($post['email']) ? strtolower(trim((string)$post['email'])) : '';
		$text = isset($post['text']) ? trim((string)$post['text']) : '';

		if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 64) {
			$errors['name'] = 'name';
		}

		if ($email !== '' && (strlen($email) > 96 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
			$errors['email'] = 'email';
		}

		if (mb_strlen($text, 'UTF-8') < 5 || mb_strlen($text, 'UTF-8') > 1000) {
			$errors['text'] = 'text';
		}

		// links in a question are the usual spam payload
		if (!$errors && preg_match('~https?://|www\.~i', $text)) {
			$errors['text'] = 'links';
		}

		return array('errors' => $errors, 'clean' => array('name' => $name, 'email' => $email, 'text' => $text));
	}

	/**
	 * JSON-LD of the page: Product (with offer and rating when known) and BreadcrumbList. Safe to print inside <script>.
	 *
	 * @param array $p name, url, image, sku, brand, description, price, currency, in_stock, rating, reviews
	 * @param array $crumbs each array('text' => ..., 'href' => ...)
	 */
	public static function schema($p, $crumbs) {
		$product = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => (string)$p['name'],
			'url'      => (string)$p['url']
		);

		foreach (array('image', 'sku', 'description') as $field) {
			if (!empty($p[$field])) {
				$product[$field] = (string)$p[$field];
			}
		}

		if (!empty($p['brand'])) {
			$product['brand'] = array('@type' => 'Brand', 'name' => (string)$p['brand']);
		}

		if (isset($p['price']) && $p['price'] !== false && (float)$p['price'] > 0) {
			$product['offers'] = array(
				'@type'         => 'Offer',
				'price'         => number_format((float)$p['price'], 2, '.', ''),
				'priceCurrency' => (string)$p['currency'],
				'availability'  => !empty($p['in_stock']) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'           => (string)$p['url']
			);
		}

		if (!empty($p['reviews']) && (float)$p['rating'] > 0) {
			$product['aggregateRating'] = array('@type' => 'AggregateRating', 'ratingValue' => (string)$p['rating'], 'reviewCount' => (int)$p['reviews']);
		}

		$items = array();

		foreach (array_values((array)$crumbs) as $i => $c) {
			$items[] = array('@type' => 'ListItem', 'position' => $i + 1, 'name' => html_entity_decode((string)$c['text'], ENT_QUOTES, 'UTF-8'), 'item' => html_entity_decode((string)$c['href'], ENT_QUOTES, 'UTF-8'));
		}

		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;

		return json_encode(array($product, array('@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items)), $flags);
	}
}
