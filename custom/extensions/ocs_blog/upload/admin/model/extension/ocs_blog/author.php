<?php
// Extension: ocs_blog. Admin model of blog authors and the article extras. Owner: SPEC-084.

class ModelExtensionOcsBlogAuthor extends Model {
	public function addAuthor($data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_blog_author` SET image = '" . $this->db->escape(isset($data['image']) ? $data['image'] : '') . "', social = '" . $this->db->escape(json_encode(\Ocs\Blog::cleanSocial(isset($data['social']) ? $data['social'] : array()), JSON_UNESCAPED_SLASHES)) . "', sort_order = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "', status = '" . (int)!empty($data['status']) . "'");

		$author_id = $this->db->getLastId();

		$this->saveDescriptions($author_id, isset($data['description']) ? $data['description'] : array());

		return $author_id;
	}

	public function editAuthor($author_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "ocs_blog_author` SET image = '" . $this->db->escape(isset($data['image']) ? $data['image'] : '') . "', social = '" . $this->db->escape(json_encode(\Ocs\Blog::cleanSocial(isset($data['social']) ? $data['social'] : array()), JSON_UNESCAPED_SLASHES)) . "', sort_order = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "', status = '" . (int)!empty($data['status']) . "' WHERE author_id = '" . (int)$author_id . "'");

		$this->saveDescriptions($author_id, isset($data['description']) ? $data['description'] : array());
	}

	private function saveDescriptions($author_id, $descriptions) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_blog_author_description` WHERE author_id = '" . (int)$author_id . "'");

		foreach ((array)$descriptions as $language_id => $value) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_blog_author_description` SET author_id = '" . (int)$author_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape(isset($value['name']) ? $value['name'] : '') . "', position = '" . $this->db->escape(isset($value['position']) ? $value['position'] : '') . "', description = '" . $this->db->escape(isset($value['description']) ? $value['description'] : '') . "'");
		}
	}

	/** REQ-BLG-006: articles keep existing, only the link is cleared. */
	public function deleteAuthor($author_id) {
		$this->db->query("UPDATE `" . DB_PREFIX . "ocs_blog_article` SET author_id = 0 WHERE author_id = '" . (int)$author_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_blog_author_description` WHERE author_id = '" . (int)$author_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_blog_author` WHERE author_id = '" . (int)$author_id . "'");
	}

	public function getAuthor($author_id) {
		$row = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_blog_author` WHERE author_id = '" . (int)$author_id . "'")->row;

		if ($row) {
			$row['social'] = \Ocs\Blog::decodeSocial($row['social']);
		}

		return $row;
	}

	public function getDescriptions($author_id) {
		$out = array();

		foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_blog_author_description` WHERE author_id = '" . (int)$author_id . "'")->rows as $row) {
			$out[(int)$row['language_id']] = $row;
		}

		return $out;
	}

	/** List with the name in the admin language. */
	public function getAuthors() {
		return $this->db->query("SELECT a.*, ad.name, ad.position FROM `" . DB_PREFIX . "ocs_blog_author` a LEFT JOIN `" . DB_PREFIX . "ocs_blog_author_description` ad ON (ad.author_id = a.author_id AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "') ORDER BY a.sort_order ASC, ad.name ASC")->rows;
	}

	public function getArticleExtra($article_id) {
		$row = $this->db->query("SELECT author_id, likes, dislikes FROM `" . DB_PREFIX . "ocs_blog_article` WHERE article_id = '" . (int)$article_id . "'")->row;

		return $row ? $row : array('author_id' => 0, 'likes' => 0, 'dislikes' => 0);
	}

	public function saveArticleAuthor($article_id, $author_id) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_blog_article` SET article_id = '" . (int)$article_id . "', author_id = '" . (int)$author_id . "' ON DUPLICATE KEY UPDATE author_id = '" . (int)$author_id . "'");
	}

	/** The core admin model never writes date_available (REQ-BLG-010). */
	public function saveArticleDate($article_id, $date) {
		$this->db->query("UPDATE `" . DB_PREFIX . "article` SET date_available = '" . $this->db->escape($date) . "' WHERE article_id = '" . (int)$article_id . "'");
	}

	public function getArticleDate($article_id) {
		$row = $this->db->query("SELECT date_available FROM `" . DB_PREFIX . "article` WHERE article_id = '" . (int)$article_id . "'")->row;

		return $row ? $row['date_available'] : '';
	}

	public function deleteArticleData($article_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_blog_article` WHERE article_id = '" . (int)$article_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_blog_vote` WHERE target_type = 'article' AND target_id = '" . (int)$article_id . "'");
	}

	public function deleteCommentData($review_article_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_blog_comment` WHERE review_article_id = '" . (int)$review_article_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_blog_vote` WHERE target_type = 'comment' AND target_id = '" . (int)$review_article_id . "'");
	}
}
