<?php
// Extension: ocs_product_extras. Admin events: video field on the Data tab of the product form, pros / cons / reply of the review form, saving and cleanup. Owner: SPEC-035, SPEC-115, SPEC-116.
// Handlers never throw to the admin user (REQ-EXT-062).

class ControllerExtensionOcsProductExtrasEvents extends Controller {
	/**
	 * admin/view/catalog/review_form/before: editable pros, cons and admin reply (SPEC-116 REQ-REV-030). The OCMOD prints {{ ocs_review_extra }}.
	 * Without the migrated table nothing is rendered (REQ-REV-034).
	 */
	public function beforeReviewForm(&$route, &$data) {
		if (!is_array($data)) {
			return;
		}

		try {
			$strings = $this->load->language('extension/ocs_product_extras/tab');
			$strings = is_array($strings) ? $strings : array();

			// throws when the table or the reply column is missing
			$this->db->query("SELECT reply FROM `" . DB_PREFIX . "ocs_review_extra` LIMIT 0");

			$row = array('pros' => '', 'cons' => '', 'reply' => '');

			if (!empty($this->request->get['review_id'])) {
				$found = $this->db->query("SELECT pros, cons, reply FROM `" . DB_PREFIX . "ocs_review_extra` WHERE review_id = '" . (int)$this->request->get['review_id'] . "'")->row;

				if ($found) {
					$row = array_merge($row, array_map('strval', $found));
				}
			}

			// a form re-shown after a validation error keeps what was typed
			foreach (array('pros', 'cons', 'reply') as $key) {
				if (isset($this->request->post['ocs_' . $key]) && is_scalar($this->request->post['ocs_' . $key])) {
					$row[$key] = html_entity_decode((string)$this->request->post['ocs_' . $key], ENT_QUOTES, 'UTF-8');
				}
			}

			$e = function ($v) {
				return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
			};
			$t = function ($key) use ($strings, $e) {
				return $e(isset($strings[$key]) ? $strings[$key] : $key);
			};

			$html = '';

			foreach (array('pros' => 'ocs_entry_pros', 'cons' => 'ocs_entry_cons') as $key => $label) {
				$html .= '<div class="form-group"><label class="col-sm-2 control-label" for="input-ocs-' . $key . '">' . $t($label) . '</label>'
					. '<div class="col-sm-10"><input type="text" name="ocs_' . $key . '" value="' . $e($row[$key]) . '" maxlength="255" id="input-ocs-' . $key . '" class="form-control" /></div></div>';
			}

			$html .= '<div class="form-group"><label class="col-sm-2 control-label" for="input-ocs-reply">' . $t('ocs_entry_reply') . '</label>'
				. '<div class="col-sm-10"><textarea name="ocs_reply" rows="5" maxlength="2000" id="input-ocs-reply" class="form-control">' . $e($row['reply']) . '</textarea>'
				. '<div class="help-block">' . $t('ocs_help_reply') . '</div></div></div>';

			$data['ocs_review_extra'] = $html;
		} catch (\Exception $e) {
			$this->log->write('[ocs_product_extras] ' . $e->getMessage());
		}
	}

	/**
	 * admin/model/catalog/review/addReview/after: $output is the new review id (SPEC-116 REQ-REV-031).
	 */
	public function afterAddReview(&$route, &$args, &$output) {
		$this->saveReviewExtra((int)$output);
	}

	/**
	 * admin/model/catalog/review/editReview/after: $args[0] is the review id.
	 */
	public function afterEditReview(&$route, &$args, &$output) {
		$this->saveReviewExtra(is_array($args) && isset($args[0]) ? (int)$args[0] : 0);
	}

	private function saveReviewExtra($review_id) {
		// the fields are absent from posts that do not come from the review form (for example the bulk status toggle): nothing changes then
		if ($review_id < 1 || (!isset($this->request->post['ocs_reply']) && !isset($this->request->post['ocs_pros']) && !isset($this->request->post['ocs_cons']))) {
			return;
		}

		try {
			$post = $this->request->post;
			$pros = \Ocs\ReviewExtra::clean(isset($post['ocs_pros']) ? $post['ocs_pros'] : '');
			$cons = \Ocs\ReviewExtra::clean(isset($post['ocs_cons']) ? $post['ocs_cons'] : '');
			$reply = \Ocs\ReviewExtra::cleanReply(isset($post['ocs_reply']) ? $post['ocs_reply'] : '');

			if ($pros === '' && $cons === '' && $reply === '') {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_review_extra` WHERE review_id = '" . $review_id . "'");
			} else {
				$this->db->query("REPLACE INTO `" . DB_PREFIX . "ocs_review_extra` SET review_id = '" . $review_id . "', pros = '" . $this->db->escape($pros) . "', cons = '" . $this->db->escape($cons) . "', reply = '" . $this->db->escape($reply) . "'");
			}
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
