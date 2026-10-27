<?php
// Extension: ocs (theme). schema.org JSON-LD for the storefront: sanitisers, node builders and the safe encoder.
// Pure, no registry access (unit-testable). Every value is sanitised here because Twig autoescape is off and the result sits inside a <script> element.
// A node that misses a property Google requires is dropped, never emitted incomplete. Owner: SPEC-080 (REQ-SD-001..008, REQ-SD-010).

namespace Ocs;

class StructuredData {
	const CONTEXT = 'https://schema.org';

	const MAX_ITEMS = 50;

	const MAX_REVIEWS = 10;

	/** @var string scheme://host of the store, used to make site-relative URLs absolute */
	public static $base = '';

	/** @var string http or https: the scheme given to every URL of the store host (OpenCart builds some links with the other one) */
	public static $scheme = '';

	/** @var string host of the store; page / item / id URLs must belong to it (without a leading www.) */
	public static $host = '';

	private static $availability = array(
		'in'  => 'https://schema.org/InStock',
		'out' => 'https://schema.org/OutOfStock'
	);

	/**
	 * Plain text: HTML entities decoded, tags removed, whitespace collapsed, invalid UTF-8 scrubbed, cut on a character boundary.
	 * The input is the HTML-escaped form that OpenCart passes around, so entities are decoded exactly once on each side of strip_tags.
	 */
	public static function text($value, $max = 0) {
		if (!is_scalar($value)) {
			return '';
		}

		$s = (string)$value;

		if (function_exists('mb_convert_encoding')) {
			$s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
		}

		$s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$s = trim((string)preg_replace('/[\x00-\x1f\x7f\s]+/u', ' ', $s));

		if ($max > 0 && function_exists('mb_substr') && mb_strlen($s, 'UTF-8') > $max) {
			$s = rtrim(mb_substr($s, 0, $max, 'UTF-8'));
		}

		return $s;
	}

	/**
	 * @param bool $own true: the URL must belong to the store host (page, item and id URLs); false: any http(s) host (images, profiles)
	 * @return string absolute http(s) URL or '' when invalid
	 */
	public static function url($value, $own = false) {
		if (!is_scalar($value)) {
			return '';
		}

		$url = html_entity_decode(trim((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x20\x7f"<>\\\\^`{|}]/', $url)) {
			return '';
		}

		if ($url[0] === '/' && (!isset($url[1]) || $url[1] !== '/') && self::$base !== '') {
			$url = rtrim(self::$base, '/') . $url;
		}

		$parts = parse_url($url);

		if (!$parts || empty($parts['host']) || !isset($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
			return '';
		}

		$ours = self::$host !== '' && self::bare($parts['host']) === self::bare(self::$host);

		if ($own && self::$host !== '' && !$ours) {
			return '';
		}

		if ($ours && self::$scheme !== '') {
			$url = self::$scheme . substr($url, strlen($parts['scheme']));
		}

		return $url;
	}

	private static function bare($host) {
		return preg_replace('/^www\./', '', strtolower((string)$host));
	}

	/** @return string price with a dot and two decimals, '' when not a positive number */
	public static function price($value) {
		if (!is_numeric($value) || (float)$value <= 0 || (float)$value > 1.0E+12) {
			return '';
		}

		return number_format((float)$value, 2, '.', '');
	}

	/** @return string ISO 4217 code or '' */
	public static function currency($value) {
		$value = strtoupper(trim((string)$value));

		return preg_match('/^[A-Z]{3}$/', $value) ? $value : '';
	}

	/**
	 * @param bool $day true: Y-m-d only
	 * @return string ISO 8601 or '' (empty / zero dates included)
	 */
	public static function date($value, $day = false) {
		$value = trim((string)$value);

		if ($value === '' || strpos($value, '0000-00-00') === 0) {
			return '';
		}

		$time = strtotime($value);

		if ($time === false || $time <= 0) {
			return '';
		}

		return date($day ? 'Y-m-d' : DATE_ATOM, $time);
	}

	/** @return string BCP 47 tag from an OpenCart language code ("ru-ru" -> "ru-RU"), '' when not recognisable */
	public static function lang($code) {
		if (!preg_match('/^([a-z]{2,3})(?:[-_]([a-z]{2}))?$/i', trim((string)$code), $m)) {
			return '';
		}

		return strtolower($m[1]) . (isset($m[2]) ? '-' . strtoupper($m[2]) : '');
	}

	/** @return string the first candidate that is a GTIN (8, 12, 13 or 14 digits), '' otherwise */
	public static function gtin(array $candidates) {
		foreach ($candidates as $c) {
			$c = preg_replace('/[\s-]+/', '', (string)$c);

			if (preg_match('/^\d+$/', $c) && in_array(strlen($c), array(8, 12, 13, 14), true)) {
				return $c;
			}
		}

		return '';
	}

	/** @return string phone for schema.org: digits with an optional leading plus, '' when shorter than 5 digits */
	public static function phone($value) {
		$value = preg_replace('/^tel:/i', '', trim((string)$value));
		$digits = preg_replace('/(?!^\+)[^0-9]/', '', $value);

		return strlen(preg_replace('/\D/', '', $digits)) >= 5 ? $digits : '';
	}

	/** @return float|null average rating inside 1..5 */
	public static function rating($value) {
		if (!is_numeric($value) || (float)$value < 1 || (float)$value > 5) {
			return null;
		}

		return round((float)$value, 1);
	}

	/**
	 * Organization node. Used inline (publisher, seller, author) with only @id, name and url, and as the full top-level node.
	 *
	 * @param array $o id, name, url, logo, same_as[], phones[], address
	 */
	public static function organization(array $o, $full = true) {
		$name = self::text(isset($o['name']) ? $o['name'] : '', 200);
		$url = self::url(isset($o['url']) ? $o['url'] : '', true);
		$id = self::url(isset($o['id']) ? $o['id'] : '', true);

		if ($name === '' || $url === '' || $id === '') {
			return null;
		}

		$node = array('@type' => 'Organization', '@id' => $id, 'name' => $name, 'url' => $url);

		if (!$full) {
			return $node;
		}

		$logo = self::url(isset($o['logo']) ? $o['logo'] : '');

		if ($logo !== '') {
			$node['logo'] = array('@type' => 'ImageObject', 'url' => $logo);
		}

		$same = array();

		foreach (isset($o['same_as']) ? (array)$o['same_as'] : array() as $link) {
			$link = self::url($link);

			if ($link !== '' && !in_array($link, $same, true)) {
				$same[] = $link;
			}
		}

		if ($same) {
			$node['sameAs'] = array_slice($same, 0, 20);
		}

		$points = array();

		foreach (isset($o['phones']) ? (array)$o['phones'] : array() as $phone) {
			$phone = self::phone($phone);

			if ($phone !== '') {
				$points[] = array('@type' => 'ContactPoint', 'telephone' => $phone, 'contactType' => 'customer service');
			}
		}

		if ($points) {
			$node['contactPoint'] = array_slice($points, 0, 10);
		}

		$address = self::text(isset($o['address']) ? $o['address'] : '', 300);

		if ($address !== '') {
			$node['address'] = array('@type' => 'PostalAddress', 'streetAddress' => $address);
		}

		return $node;
	}

	public static function website(array $w) {
		$name = self::text(isset($w['name']) ? $w['name'] : '', 200);
		$url = self::url(isset($w['url']) ? $w['url'] : '', true);
		$id = self::url(isset($w['id']) ? $w['id'] : '', true);

		if ($name === '' || $url === '' || $id === '') {
			return null;
		}

		$node = array('@type' => 'WebSite', '@id' => $id, 'name' => $name, 'url' => $url);

		$lang = self::lang(isset($w['lang']) ? $w['lang'] : '');

		if ($lang !== '') {
			$node['inLanguage'] = $lang;
		}

		$publisher = self::url(isset($w['publisher']) ? $w['publisher'] : '', true);

		if ($publisher !== '') {
			$node['publisher'] = array('@id' => $publisher);
		}

		return $node;
	}

	/**
	 * @param array $p type (WebPage, CollectionPage, SearchResultsPage, ContactPage, Blog), url, name, lang, website (id), breadcrumb (id), main (id), description
	 */
	public static function page(array $p) {
		$types = array('WebPage', 'CollectionPage', 'SearchResultsPage', 'ContactPage', 'Blog');
		$type = isset($p['type']) && in_array($p['type'], $types, true) ? $p['type'] : 'WebPage';
		$url = self::url(isset($p['url']) ? $p['url'] : '', true);
		$name = self::text(isset($p['name']) ? $p['name'] : '', 300);

		if ($url === '' || $name === '') {
			return null;
		}

		$node = array('@type' => $type, '@id' => $url . '#webpage', 'url' => $url, 'name' => $name);

		$description = self::text(isset($p['description']) ? $p['description'] : '', 500);

		if ($description !== '') {
			$node['description'] = $description;
		}

		$lang = self::lang(isset($p['lang']) ? $p['lang'] : '');

		if ($lang !== '') {
			$node['inLanguage'] = $lang;
		}

		foreach (array('website' => 'isPartOf', 'breadcrumb' => 'breadcrumb', 'main' => 'mainEntity') as $key => $property) {
			$ref = self::url(isset($p[$key]) ? $p[$key] : '', true);

			if ($ref !== '') {
				$node[$property] = array('@id' => $ref);
			}
		}

		return $node;
	}

	/**
	 * @param array $crumbs each [text, href]; the last one is the current page and gets no item
	 */
	public static function breadcrumbs($id, array $crumbs) {
		$id = self::url($id, true);
		$items = array();
		$crumbs = array_values($crumbs);

		foreach ($crumbs as $i => $crumb) {
			$name = self::text(isset($crumb['text']) ? $crumb['text'] : '', 300);

			if ($name === '') {
				continue;
			}

			$item = array('@type' => 'ListItem', 'name' => $name);

			if ($i < count($crumbs) - 1) {
				$href = self::url(isset($crumb['href']) ? $crumb['href'] : '', true);

				if ($href === '') {
					continue;
				}

				$item['item'] = $href;
			}

			$items[] = $item;
		}

		if ($id === '' || !$items) {
			return null;
		}

		foreach ($items as $i => $item) {
			// position first, as in Google's examples
			$items[$i] = array('@type' => 'ListItem', 'position' => $i + 1) + array_diff_key($item, array('@type' => 1));
		}

		return array('@type' => 'BreadcrumbList', '@id' => $id, 'itemListElement' => $items);
	}

	/**
	 * @param array $items each [href] (the URL of the item page); at most 50 are kept
	 * @param int $offset items before this page (positions continue across pagination)
	 */
	public static function itemList($id, $name, array $items, $offset = 0) {
		$id = self::url($id, true);
		$list = array();

		foreach (array_values($items) as $item) {
			$href = self::url(isset($item['href']) ? $item['href'] : '', true);

			if ($href === '') {
				continue;
			}

			$list[] = array('@type' => 'ListItem', 'position' => max(0, (int)$offset) + count($list) + 1, 'url' => $href);

			if (count($list) >= self::MAX_ITEMS) {
				break;
			}
		}

		if ($id === '' || !$list) {
			return null;
		}

		$node = array('@type' => 'ItemList', '@id' => $id);

		$name = self::text($name, 200);

		if ($name !== '') {
			$node['name'] = $name;
		}

		$node['numberOfItems'] = count($list);
		$node['itemListElement'] = $list;

		return $node;
	}

	/**
	 * @param array $r author, rating (1..5), date, text
	 */
	public static function review(array $r) {
		$author = self::text(isset($r['author']) ? $r['author'] : '', 100);
		$rating = isset($r['rating']) ? (int)$r['rating'] : 0;

		if ($author === '' || $rating < 1 || $rating > 5) {
			return null;
		}

		$node = array(
			'@type'        => 'Review',
			'author'       => array('@type' => 'Person', 'name' => $author),
			'reviewRating' => array('@type' => 'Rating', 'ratingValue' => $rating, 'bestRating' => 5, 'worstRating' => 1)
		);

		$date = self::date(isset($r['date']) ? $r['date'] : '');

		if ($date !== '') {
			$node['datePublished'] = $date;
		}

		$body = self::text(isset($r['text']) ? $r['text'] : '', 2000);

		if ($body !== '') {
			$node['reviewBody'] = $body;
		}

		return $node;
	}

	/**
	 * Product with its offer and reviews; dropped when it has neither an offer nor reviews (Google requires one of them).
	 *
	 * @param array $p id, name, url, images[], description, sku, mpn, gtin[], brand, price, currency, in_stock, valid_until, seller (organization array),
	 *                 rating (average), review_total, reviews[] (author, rating, date, text)
	 */
	public static function product(array $p) {
		$name = self::text(isset($p['name']) ? $p['name'] : '', 300);
		$url = self::url(isset($p['url']) ? $p['url'] : '', true);

		if ($name === '' || $url === '') {
			return null;
		}

		$node = array('@type' => 'Product', '@id' => $url . '#product', 'name' => $name, 'url' => $url);

		$images = array();

		foreach (isset($p['images']) ? (array)$p['images'] : array() as $image) {
			$image = self::url($image);

			if ($image !== '' && !in_array($image, $images, true)) {
				$images[] = $image;
			}
		}

		if ($images) {
			$node['image'] = array_slice($images, 0, 10);
		}

		$description = self::text(isset($p['description']) ? $p['description'] : '', 5000);

		if ($description !== '') {
			$node['description'] = $description;
		}

		foreach (array('sku' => 'sku', 'mpn' => 'mpn') as $key => $property) {
			$v = self::text(isset($p[$key]) ? $p[$key] : '', 100);

			if ($v !== '') {
				$node[$property] = $v;
			}
		}

		$gtin = self::gtin(isset($p['gtin']) ? (array)$p['gtin'] : array());

		if ($gtin !== '') {
			$node['gtin'] = $gtin;
		}

		$brand = self::text(isset($p['brand']) ? $p['brand'] : '', 200);

		if ($brand !== '') {
			$node['brand'] = array('@type' => 'Brand', 'name' => $brand);
		}

		$price = self::price(isset($p['price']) ? $p['price'] : 0);
		$currency = self::currency(isset($p['currency']) ? $p['currency'] : '');

		if ($price !== '' && $currency !== '') {
			$offer = array(
				'@type'         => 'Offer',
				'url'           => $url,
				'price'         => $price,
				'priceCurrency' => $currency,
				'availability'  => !empty($p['in_stock']) ? self::$availability['in'] : self::$availability['out'],
				'itemCondition' => 'https://schema.org/NewCondition'
			);

			$until = self::date(isset($p['valid_until']) ? $p['valid_until'] : '', true);

			if ($until !== '') {
				$offer['priceValidUntil'] = $until;
			}

			$seller = isset($p['seller']) && is_array($p['seller']) ? self::organization($p['seller'], false) : null;

			if ($seller) {
				$offer['seller'] = $seller;
			}

			$node['offers'] = $offer;
		}

		$reviews = array();

		foreach (isset($p['reviews']) ? (array)$p['reviews'] : array() as $r) {
			$review = is_array($r) ? self::review($r) : null;

			if ($review) {
				$reviews[] = $review;
			}

			if (count($reviews) >= self::MAX_REVIEWS) {
				break;
			}
		}

		$average = self::rating(isset($p['rating']) ? $p['rating'] : null);
		$total = isset($p['review_total']) ? (int)$p['review_total'] : 0;

		if ($average !== null && $total >= 1) {
			$node['aggregateRating'] = array('@type' => 'AggregateRating', 'ratingValue' => $average, 'bestRating' => 5, 'worstRating' => 1, 'reviewCount' => $total);
		}

		if ($reviews) {
			$node['review'] = $reviews;
		}

		if (!isset($node['offers']) && !isset($node['aggregateRating']) && !isset($node['review'])) {
			return null;
		}

		return $node;
	}

	/**
	 * @param array $a url, headline, image, description, published, modified, publisher (organization array)
	 */
	public static function article(array $a) {
		$headline = self::text(isset($a['headline']) ? $a['headline'] : '', 110);
		$url = self::url(isset($a['url']) ? $a['url'] : '', true);
		$published = self::date(isset($a['published']) ? $a['published'] : '');

		if ($headline === '' || $url === '' || $published === '') {
			return null;
		}

		$node = array('@type' => 'BlogPosting', '@id' => $url . '#article', 'headline' => $headline, 'mainEntityOfPage' => array('@id' => $url . '#webpage'), 'url' => $url);

		$image = self::url(isset($a['image']) ? $a['image'] : '');

		if ($image !== '') {
			$node['image'] = array($image);
		}

		$description = self::text(isset($a['description']) ? $a['description'] : '', 300);

		if ($description !== '') {
			$node['description'] = $description;
		}

		$node['datePublished'] = $published;

		$modified = self::date(isset($a['modified']) ? $a['modified'] : '');
		$node['dateModified'] = $modified !== '' ? $modified : $published;

		$publisher = isset($a['publisher']) && is_array($a['publisher']) ? self::organization($a['publisher'], false) : null;

		if ($publisher) {
			$node['author'] = $publisher;
			$node['publisher'] = $publisher;
		}

		return $node;
	}

	/**
	 * Encodes the nodes as one JSON-LD document. Nulls and a second node with an already used @id are dropped.
	 * Safe inside <script>: <, >, &, ' and " are written as \uXXXX, so no input can close the element or start a comment.
	 *
	 * @return string JSON or '' when there is nothing to print or encoding failed
	 */
	public static function graph(array $nodes) {
		$graph = array();
		$ids = array();

		foreach ($nodes as $node) {
			if (!is_array($node) || !isset($node['@type'])) {
				continue;
			}

			if (isset($node['@id'])) {
				if (isset($ids[$node['@id']])) {
					continue;
				}

				$ids[$node['@id']] = true;
			}

			$graph[] = $node;
		}

		if (!$graph) {
			return '';
		}

		$json = json_encode(array('@context' => self::CONTEXT, '@graph' => $graph), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return is_string($json) ? $json : '';
	}

	/** @return string the output with the script put before the first </head>; unchanged when there is no head or no JSON */
	public static function inject($html, $json) {
		if ($json === '') {
			return $html;
		}

		$pos = strpos($html, '</head>');

		if ($pos === false) {
			return $html;
		}

		return substr($html, 0, $pos) . '<script type="application/ld+json">' . $json . '</script>' . "\n" . substr($html, $pos);
	}
}
