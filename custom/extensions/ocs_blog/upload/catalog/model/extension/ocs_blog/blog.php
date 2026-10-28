<?php
// Extension: ocs_blog. Storefront model: authors, article dates, reaction counters and votes. Owner: SPEC-084.
// Every read is one query for a whole list of ids (REQ-BLG-062).

class ModelExtensionOcsBlogBlog extends Model {
	/**
	 * Authors of several articles in the current language, one JOIN.
	 *
	 * @param int[] $ids article ids
	 * @return array article_id => author row (author_id, name, position, description, image, social[])
	 */
	public function getAuthors(array $ids) {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if (!$ids) {
			return array();
		}

		$language_id = (int)$this->config->get('config_language_id');

		$rows = $this->db->query("SELECT oa.article_id, a.author_id, a.image, a.social, ad.name, ad.position, ad.description FROM `" . DB_PREFIX . "ocs_blog_article` oa INNER JOIN `" . DB_PREFIX . "ocs_blog_author` a ON (a.author_id = oa.author_id AND a.status = '1') LEFT JOIN `" . DB_PREFIX . "ocs_blog_author_description` ad ON (ad.author_id = a.author_id AND ad.language_id = '" . $language_id . "') WHERE oa.article_id IN (" . implode(',', $ids) . ")")->rows;

		$out = array();

		foreach ($rows as $row) {
			if (trim((string)$row['name']) === '') {
				continue;
			}

			$row['social'] = \Ocs\Blog::decodeSocial($row['social']);
			$out[(int)$row['article_id']] = $row;
		}

		return $out;
	}

	/**
	 * Publication date and reaction counters of several articles.
	 *
	 * @return array article_id => array(date, likes, dislikes)
	 */
	public function getArticleMeta(array $ids) {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if (!$ids) {
			return array();
		}

		$out = array();

		foreach ($this->db->query("SELECT a.article_id, a.date_available, a.date_added, COALESCE(oa.likes, 0) AS likes, COALESCE(oa.dislikes, 0) AS dislikes FROM `" . DB_PREFIX . "article` a LEFT JOIN `" . DB_PREFIX . "ocs_blog_article` oa ON (oa.article_id = a.article_id) WHERE a.article_id IN (" . implode(',', $ids) . ")")->rows as $row) {
			$out[(int)$row['article_id']] = array(
				'date'     => \Ocs\Blog::articleDate($row['date_available'], $row['date_added']),
				'likes'    => (int)$row['likes'],
				'dislikes' => (int)$row['dislikes']
			);
		}

		return $out;
	}

	/** @return array review_article_id => array(likes, dislikes) */
	public function getCommentCounts(array $ids) {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if (!$ids) {
			return array();
		}

		$out = array();

		foreach ($this->db->query("SELECT review_article_id, likes, dislikes FROM `" . DB_PREFIX . "ocs_blog_comment` WHERE review_article_id IN (" . implode(',', $ids) . ")")->rows as $row) {
			$out[(int)$row['review_article_id']] = array('likes' => (int)$row['likes'], 'dislikes' => (int)$row['dislikes']);
		}

		return $out;
	}

	/** Votes of one visitor: target_id => -1|1. */
	public function getMyVotes($type, array $ids, $voter_key) {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if (!$ids) {
			return array();
		}

		$out = array();

		foreach ($this->db->query("SELECT target_id, value FROM `" . DB_PREFIX . "ocs_blog_vote` WHERE target_type = '" . $this->db->escape($type) . "' AND voter_key = '" . $this->db->escape($voter_key) . "' AND target_id IN (" . implode(',', $ids) . ")")->rows as $row) {
			$out[(int)$row['target_id']] = (int)$row['value'];
		}

		return $out;
	}

	/** Voter key of the current visitor: customer id or a salted hash of IP and user agent (REQ-BLG-033). */
	public function voterKey() {
		$server = $this->request->server;

		return \Ocs\Blog::voterKey($this->customer->isLogged() ? $this->customer->getId() : 0, isset($server['REMOTE_ADDR']) ? $server['REMOTE_ADDR'] : '', isset($server['HTTP_USER_AGENT']) ? $server['HTTP_USER_AGENT'] : '', DB_DATABASE);
	}

	public function targetExists($type, $id) {
		if ($type === 'article') {
			return (bool)$this->db->query("SELECT article_id FROM `" . DB_PREFIX . "article` WHERE article_id = '" . (int)$id . "' AND status = '1'")->num_rows;
		}

		return (bool)$this->db->query("SELECT review_article_id FROM `" . DB_PREFIX . "review_article` WHERE review_article_id = '" . (int)$id . "' AND status = '1'")->num_rows;
	}

	public function recentVotes($voter_key) {
		$row = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ocs_blog_vote` WHERE voter_key = '" . $this->db->escape($voter_key) . "' AND date_added > DATE_SUB(NOW(), INTERVAL 1 HOUR)")->row;

		return (int)$row['total'];
	}

	/**
	 * Applies a click: same value removes the vote, another value replaces it (REQ-BLG-033). Counters are recomputed from the vote rows,
	 * so they cannot drift.
	 *
	 * @return array likes, dislikes, mine
	 */
	public function vote($type, $id, $value, $voter_key) {
		$type = $type === 'comment' ? 'comment' : 'article';
		$id = (int)$id;

		$old = $this->getMyVotes($type, array($id), $voter_key);
		$old = isset($old[$id]) ? $old[$id] : 0;
		$new = \Ocs\Blog::nextVote($old, $value);

		if ($new === 0) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_blog_vote` WHERE target_type = '" . $type . "' AND target_id = '" . $id . "' AND voter_key = '" . $this->db->escape($voter_key) . "'");
		} else {
			$this->db->query("REPLACE INTO `" . DB_PREFIX . "ocs_blog_vote` SET target_type = '" . $type . "', target_id = '" . $id . "', voter_key = '" . $this->db->escape($voter_key) . "', value = '" . (int)$new . "', date_added = NOW()");
		}

		$sums = $this->db->query("SELECT SUM(value = 1) AS likes, SUM(value = -1) AS dislikes FROM `" . DB_PREFIX . "ocs_blog_vote` WHERE target_type = '" . $type . "' AND target_id = '" . $id . "'")->row;
		$likes = (int)$sums['likes'];
		$dislikes = (int)$sums['dislikes'];

		if ($type === 'article') {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_blog_article` SET article_id = '" . $id . "', likes = '" . $likes . "', dislikes = '" . $dislikes . "' ON DUPLICATE KEY UPDATE likes = '" . $likes . "', dislikes = '" . $dislikes . "'");
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_blog_comment` SET review_article_id = '" . $id . "', likes = '" . $likes . "', dislikes = '" . $dislikes . "' ON DUPLICATE KEY UPDATE likes = '" . $likes . "', dislikes = '" . $dislikes . "'");
		}

		return array('likes' => $likes, 'dislikes' => $dislikes, 'mine' => $new);
	}
}
