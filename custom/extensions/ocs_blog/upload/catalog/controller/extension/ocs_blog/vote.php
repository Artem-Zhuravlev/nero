<?php
// Extension: ocs_blog. Storefront AJAX: like / dislike of an article or a comment. Owner: SPEC-084 (REQ-BLG-033, REQ-BLG-040, REQ-BLG-061).

class ControllerExtensionOcsBlogVote extends Controller {
	public function index() {
		$this->load->language('extension/ocs_blog/blog');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] != 'POST' || !$this->sameHost()) {
			$json['error'] = $this->language->get('error_vote');
		} else {
			$type = isset($this->request->post['target_type']) && $this->request->post['target_type'] === 'comment' ? 'comment' : 'article';
			$id = isset($this->request->post['target_id']) ? (int)$this->request->post['target_id'] : 0;
			$value = isset($this->request->post['value']) && (int)$this->request->post['value'] > 0 ? 1 : -1;

			$this->load->model('extension/ocs_blog/blog');

			$model = $this->model_extension_ocs_blog_blog;
			$key = $model->voterKey();

			try {
				if ($id < 1 || !$model->targetExists($type, $id)) {
					$json['error'] = $this->language->get('error_vote');
				} elseif ($model->recentVotes($key) >= \Ocs\Blog::VOTES_PER_HOUR) {
					$json['error'] = $this->language->get('error_vote_limit');
				} else {
					$json = $model->vote($type, $id, $value, $key);
				}
			} catch (\Exception $e) {
				$this->log->write('[ocs_blog] ' . $e->getMessage());

				$json = array('error' => $this->language->get('error_vote'));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/** Cross-site POSTs are refused: Origin or Referer, when sent, must be this host. */
	private function sameHost() {
		$server = $this->request->server;
		$source = isset($server['HTTP_ORIGIN']) ? $server['HTTP_ORIGIN'] : (isset($server['HTTP_REFERER']) ? $server['HTTP_REFERER'] : '');

		if ($source === '') {
			return true;
		}

		$host = parse_url($source, PHP_URL_HOST);

		return $host && isset($server['HTTP_HOST']) && strcasecmp($host, preg_replace('/:\d+$/', '', $server['HTTP_HOST'])) === 0;
	}
}
