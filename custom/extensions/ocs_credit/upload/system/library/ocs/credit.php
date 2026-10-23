<?php
// Extension: ocs_credit. Instalment formula and plan settings (pure, tested without a database). Owner: SPEC-036.
// The numbers are display values: final terms are set by the bank (REQ-CRD-002).

namespace Ocs;

class Credit {
	/**
	 * Monthly payment of an annuity loan.
	 *
	 * @param float $price     price in the default currency
	 * @param int   $months    number of payments (>= 1)
	 * @param float $annualPct annual rate in percent; 0 = zero-rate plan (price / months)
	 * @param float $feePct    one-off commission in percent of the price, added to the financed amount
	 */
	public static function payment($price, $months, $annualPct, $feePct = 0.0) {
		$months = max(1, (int)$months);
		$amount = max(0.0, (float)$price) * (1 + max(0.0, (float)$feePct) / 100);
		$r = max(0.0, (float)$annualPct) / 1200;

		if ($r <= 0) {
			return $amount / $months;
		}

		return $amount * $r / (1 - pow(1 + $r, -$months));
	}

	/**
	 * Plans that apply to the price (min <= price <= max; max 0 = no upper limit), with their payment.
	 *
	 * @param array[] $plans bank, months, rate, fee, min, max
	 * @return array[] the applicable plans, each with 'payment' (unrounded) and its original 'index'
	 */
	public static function plansFor($plans, $price) {
		$out = array();

		foreach (array_values((array)$plans) as $i => $plan) {
			$min = (float)(isset($plan['min']) ? $plan['min'] : 0);
			$max = (float)(isset($plan['max']) ? $plan['max'] : 0);

			if ($price < $min || ($max > 0 && $price > $max) || $price <= 0) {
				continue;
			}

			$plan['index'] = $i;
			$plan['payment'] = self::payment($price, $plan['months'], isset($plan['rate']) ? $plan['rate'] : 0, isset($plan['fee']) ? $plan['fee'] : 0);

			$out[] = $plan;
		}

		return $out;
	}

	/**
	 * Admin rows -> clean plans. Fully empty rows are skipped.
	 *
	 * @return array array('errors' => array of codes, 'plans' => array)
	 */
	public static function cleanPlans($rows, $max = 12) {
		$errors = array();
		$plans = array();

		foreach (array_values((array)$rows) as $row) {
			if (!is_array($row)) {
				continue;
			}

			$bank = trim(preg_replace('/\s+/u', ' ', (string)(isset($row['bank']) ? $row['bank'] : '')));
			$filled = $bank !== '' || trim((string)(isset($row['months']) ? $row['months'] : '')) !== '';

			if (!$filled) {
				continue;
			}

			$months = isset($row['months']) && ctype_digit(trim((string)$row['months'])) ? (int)$row['months'] : 0;

			if ($bank === '' || mb_strlen($bank, 'UTF-8') > 64) {
				$errors['bank'] = 'bank';
			}

			if ($months < 1 || $months > 120) {
				$errors['months'] = 'months';
			}

			$numbers = array();

			foreach (array('rate' => 100, 'fee' => 50, 'min' => 100000000, 'max' => 100000000) as $field => $limit) {
				$raw = isset($row[$field]) ? trim((string)$row[$field]) : '';

				if ($raw === '') {
					$numbers[$field] = 0.0;
				} elseif (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > $limit) {
					$errors[$field] = $field;
					$numbers[$field] = 0.0;
				} else {
					$numbers[$field] = round((float)$raw, 4);
				}
			}

			if ($numbers['max'] > 0 && $numbers['max'] < $numbers['min']) {
				$errors['range'] = 'range';
			}

			$plans[] = array('bank' => $bank, 'months' => $months) + $numbers;
		}

		if (count($plans) > $max) {
			$errors['count'] = 'count';
		}

		return array('errors' => $errors, 'plans' => $plans);
	}

	/**
	 * Text that goes into the quick-order comment when the visitor picks a plan.
	 */
	public static function note($template, $bank, $months, $payment) {
		return str_replace(array('%bank%', '%months%', '%pay%'), array($bank, (int)$months, $payment), (string)$template);
	}
}
