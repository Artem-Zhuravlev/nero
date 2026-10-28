<?php
// Extension: ocs_novaposhta. Nova Poshta JSON API v2.0 client: timeouts, retries, the key never leaves the request body or reaches errors. Owner: SPEC-085.
// The transport is injectable (callable), so the sync is tested with fixtures and no network.

namespace Ocs\Novaposhta;

class Client {
	const URL = 'https://api.novaposhta.ua/v2.0/json/';

	private $key;
	private $transport;
	private $retries;
	private $sleep;

	/**
	 * @param string        $key       API key
	 * @param callable|null $transport fn(string $url, string $body, int $timeout): array [int status, string body]; null = curl
	 * @param int           $retries   extra attempts on network errors, 5xx and 429
	 * @param callable|null $sleep     fn(int $microseconds) between attempts (tests pass a no-op)
	 */
	public function __construct($key, $transport = null, $retries = 2, $sleep = null) {
		$this->key = (string)$key;
		$this->transport = $transport ? $transport : array($this, 'curl');
		$this->retries = (int)$retries;
		$this->sleep = $sleep ? $sleep : 'usleep';
	}

	/**
	 * One list page of a directory.
	 *
	 * @param string $entity areas|cities|warehouses
	 * @return array list of raw API records (empty when the page is past the end)
	 * @throws \RuntimeException on transport, HTTP or API errors (message never contains the key)
	 */
	public function page($entity, $page, $limit) {
		$methods = array('areas' => 'getAreas', 'cities' => 'getCities', 'warehouses' => 'getWarehouses');

		if (!isset($methods[$entity])) {
			throw new \RuntimeException('Unknown entity ' . $entity);
		}

		$properties = $entity == 'areas' ? array() : array('Page' => (int)$page, 'Limit' => (int)$limit);

		if ($entity == 'areas' && $page > 1) {
			return array();
		}

		$data = $this->call('Address', $methods[$entity], $properties);

		return $data;
	}

	public function call($model, $method, array $properties) {
		if ($this->key === '') {
			throw new \RuntimeException('Nova Poshta API key is not set');
		}

		$body = json_encode(array('apiKey' => $this->key, 'modelName' => $model, 'calledMethod' => $method, 'methodProperties' => (object)$properties));

		$attempt = 0;

		while (true) {
			try {
				list($status, $response) = call_user_func($this->transport, self::URL, $body, 20);
			} catch (\Exception $e) {
				$status = 0;
				$response = '';
				$error = $e->getMessage();
			}

			if ($status == 200) {
				$json = json_decode($response, true);

				if (!is_array($json) || !array_key_exists('success', $json)) {
					throw new \RuntimeException('Nova Poshta: unreadable response');
				}

				if (!$json['success']) {
					$errors = !empty($json['errors']) && is_array($json['errors']) ? implode('; ', array_map('strval', $json['errors'])) : 'unknown error';

					throw new \RuntimeException('Nova Poshta: ' . str_replace($this->key, '***', $errors));
				}

				return isset($json['data']) && is_array($json['data']) ? $json['data'] : array();
			}

			$retryable = ($status == 0 || $status == 429 || $status >= 500);

			if (!$retryable || $attempt >= $this->retries) {
				throw new \RuntimeException('Nova Poshta: HTTP ' . $status . (isset($error) ? ' ' . str_replace($this->key, '***', $error) : ''));
			}

			$attempt++;
			call_user_func($this->sleep, 400000 * $attempt + mt_rand(0, 200000));
		}
	}

	private function curl($url, $body, $timeout) {
		$ch = curl_init($url);

		curl_setopt_array($ch, array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_TIMEOUT        => $timeout
		));

		$response = curl_exec($ch);

		if ($response === false) {
			$error = curl_error($ch);
			curl_close($ch);

			throw new \RuntimeException($error);
		}

		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return array($status, $response);
	}
}
