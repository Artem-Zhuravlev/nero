<?php
// Extension: ocs_product_extras. Admin events: video field on the Data tab of the product form, saving and cleanup. Owner: SPEC-035, SPEC-115.
// Handlers never throw to the admin user (REQ-EXT-062).

class ControllerExtensionOcsProductExtrasEvents extends Controller {
	/**
	 * admin/view/catalog/review_form/before: pros and cons of the review, read-only (SPEC-049 REQ-REV-005). The OCMOD prints {{ ocs_review_extra }}.
	 */
	public function beforeReviewForm(&$route, &$data) {
		if (!is_array($data) || empty($this->request->get['review_id'])) {
			return;
		}

		try {
			$strings = $this->load->language('extension/ocs_product_extras/tab');
			$strings = is_array($strings) ? $strings : array();

			$row = $this->db->query("SELECT pros, cons FROM `" . DB_PREFIX . "ocs_review_extra` WHERE review_id = '" . (int)$this->request->get['review_id'] . "'")->row;

			if (!$row) {
				return;
			}

			$e = function ($v) {
				return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
			};

			$html = '';

			foreach (array('pros' => 'ocs_entry_pros', 'cons' => 'ocs_entry_cons') as $key => $label) {
				if ($row[$key] !== '') {
					$html .= '<p class="help-block"><strong>' . $e(isset($strings[$label]) ? $strings[$label] : $key) . ':</strong> ' . $e($row[$key]) . '</p>';
				}
			}

			$data['ocs_review_extra'] = $html;
		} catch (\Exception $e) {
			$this->log->write('[ocs_product_extras] ' . $e->getMessage());
		}
	}

	/**
	 * admin/model/catalog/review/deleteReview/before: $args[0] is the review id.
	 */
	public function beforeDeleteReview(&$route, &$args) {
		if (!is_array($args) || empty($args[0])) {
			return;
		}

		try {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_review_extra` WHERE review_id = '" . (int)$args[0] . "'");
		} catch (\Exception $e) {
			$this->log->write('[ocs_product_extras] ' . $e->getMessage());
		}
	}

	public function beforeProductForm(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_product_extras_status')) {
			return;
		}

		try {
			$strings = $this->load->language('extension/ocs_product_extras/tab');
			$strings = is_array($strings) ? $strings : array();

			$this->load->model('extension/ocs_product_extras/extras');

			$model = $this->model_extension_ocs_product_extras_extras;
			$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;

			$video = $product_id ? $model->getVideoId($product_id) : '';

			if (isset($this->request->post['ocs_video'])) {
				$video = (string)$this->request->post['ocs_video'];
			}

			$video_error = '';

			if (isset($this->session->data['ocs_video_error'])) {
				$video_error = isset($strings['ocs_error_video']) ? $strings['ocs_error_video'] : '';
				unset($this->session->data['ocs_video_error']);
			}

			$data['ocs_video_field'] = $this->load->view('extension/ocs_product_extras/tab', array(
				'video'          => $video,
				'video_error'    => $video_error,
				'ocs_entry_video' => isset($strings['ocs_entry_video']) ? $strings['ocs_entry_video'] : '',
				'ocs_help_video' => isset($strings['ocs_help_video']) ? $strings['ocs_help_video'] : ''
			));
		} catch (\Exception $e) {
			$this->log->write('[ocs_product_extras] ' . $e->getMessage());
		}
	}

	public function afterAddProduct(&$route, &$args, &$output) {
		$this->save((int)$output);
	}

	public function afterEditProduct(&$route, &$args, &$output) {
		$this->save(is_array($args) && isset($args[0]) ? (int)$args[0] : 0);
	}

	/**
	 * admin/model/catalog/product/deleteProduct/before: $args[0] is the product id.
	 */
	public function beforeDeleteProduct(&$route, &$args) {
		if (!is_array($args) || empty($args[0])) {
			return;
		}

		try {
			$this->load->model('extension/ocs_product_extras/extras');

			$this->model_extension_ocs_product_extras_extras->deleteProduct((int)$args[0]);
		} catch (\Exception $e) {
			$this->log->write('[ocs_product_extras] ' . $e->getMessage());
		}
	}

	private function save($product_id) {
		// only the product form sends the marker; other callers of the model (imports) leave the video alone
		if ($product_id < 1 || !$this->config->get('module_ocs_product_extras_status') || empty($this->request->post['ocs_extras_present']) || !$this->user->hasPermission('modify', 'extension/module/ocs_product_extras')) {
			return;
		}

		try {
			$this->load->model('extension/ocs_product_extras/extras');

			// SPEC-048 REQ-VID-001: a YouTube URL or id; anything else is not stored and the form reports it on the next load
			$raw = isset($this->request->post['ocs_video']) ? trim((string)$this->request->post['ocs_video']) : '';
			$video = \Ocs\Video::parse($raw);

			if ($raw === '' || $video !== '') {
				$this->model_extension_ocs_product_extras_extras->setVideo($product_id, $video);
			} else {
				$this->session->data['ocs_video_error'] = 1;
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs_product_extras] ' . $e->getMessage());
		}
	}
}
