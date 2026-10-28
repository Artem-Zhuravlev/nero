<?php
// Extension: ocs_latest_reviews. One query for the latest approved reviews of visible products. Owner: SPEC-081 (REQ-LRV-003, REQ-LRV-010).

class ModelExtensionOcsLatestReviewsReviews extends Model {
	const TTL = 300;

	public function latest($limit, $min_rating, $category_id = 0) {
		$limit = max(1, min(20, (int)$limit));
		$min_rating = max(1, min(5, (int)$min_rating));
		$category_id = (int)$category_id;

		$key = 'ocs_latest_reviews.' . (int)$this->config->get('config_store_id') . '.' . (int)$this->config->get('config_language_id') . '.' . $limit . '.' . $min_rating . '.' . $category_id;
		$cached = $this->cache->get($key);

		if (is_array($cached) && isset($cached['t'], $cached['rows']) && $cached['t'] > time() - self::TTL) {
			return $cached['rows'];
		}

		$sql = "SELECT r.review_id, r.author, r.rating, r.text, r.date_added, p.product_id, p.image, pd.name FROM " . DB_PREFIX . "review r"
			. " INNER JOIN " . DB_PREFIX . "product p ON (r.product_id = p.product_id)"
			. " INNER JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')"
			. " INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "')";

		if ($category_id) {
			$sql .= " INNER JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id AND p2c.category_id = '" . $category_id . "')";
		}

		$sql .= " WHERE r.status = '1' AND p.status = '1' AND p.date_available <= NOW() AND r.rating >= '" . $min_rating . "' ORDER BY r.date_added DESC, r.review_id DESC LIMIT " . $limit;

		$rows = $this->db->query($sql)->rows;

		$this->cache->set($key, array('t' => time(), 'rows' => $rows));

		return $rows;
	}
}
