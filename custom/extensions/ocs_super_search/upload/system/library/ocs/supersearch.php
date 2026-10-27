<?php
// Extension: ocs_super_search. Pure text helpers of the super search: normalisation, tokens, wrong-layout conversion, phonetic key,
// one-edit comparison, synonym parsing. Owner: SPEC-079 (REQ-SSR-002..005, 007).
// No registry or database access: unit-testable (SPEC-021).

namespace Ocs;

class SuperSearch {
	const MAX_QUERY = 100;
	const MAX_WORDS = 8;

	// physical keys of the QWERTY / ЙЦУКЕН (ru) / ЙЦУКЕН (uk) layouts, same positions in the three strings
	private static $en = "qwertyuiop[]asdfghjkl;'zxcvbnm,./`";
	private static $ru = 'йцукенгшщзхъфывапролджэячсмитьбю.ё';
	private static $uk = "йцукенгшщзхїфівапролджєячсмитьбю.'";

	private static $translit = array(
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ie', 'ж' => 'zh', 'з' => 'z',
		'и' => 'i', 'і' => 'i', 'ї' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
		'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh',
		'ы' => 'i', 'э' => 'e', 'ю' => 'iu', 'я' => 'ia', 'ь' => '', 'ъ' => ''
	);

	/**
	 * Lower case, "ё" -> "е", apostrophes removed ("м'ясо" -> "мясо").
	 */
	public static function normalize($text) {
		$text = mb_strtolower((string)$text, 'UTF-8');

		return str_replace(array('ё', "'", '’', 'ʼ', '`'), array('е', '', '', '', ''), $text);
	}

	/**
	 * Words of a text: letters and digits, at least 2 characters, in order, duplicates kept.
	 */
	public static function tokens($text) {
		preg_match_all('/[\p{L}\p{N}]+/u', self::normalize($text), $m);

		$out = array();

		foreach ($m[0] as $word) {
			if (mb_strlen($word, 'UTF-8') >= 2) {
				$out[] = $word;
			}
		}

		return $out;
	}

	/**
	 * The text as it would read if the visitor typed it with the wrong layout (en -> ru, en -> uk, ru -> en, uk -> en).
	 * Only whole-script texts are converted. Result: unique strings that differ from the input.
	 */
	public static function layoutVariants($text) {
		$text = mb_strtolower(trim((string)$text), 'UTF-8');
		$out = array();

		if ($text === '') {
			return $out;
		}

		$chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
		$en = preg_split('//u', self::$en, -1, PREG_SPLIT_NO_EMPTY);

		if (self::onlyFrom($chars, $en)) {
			foreach (array(self::$ru, self::$uk) as $to) {
				$out[] = self::convert($chars, $en, preg_split('//u', $to, -1, PREG_SPLIT_NO_EMPTY));
			}
		} else {
			foreach (array(self::$ru, self::$uk) as $from) {
				$from = preg_split('//u', $from, -1, PREG_SPLIT_NO_EMPTY);

				if (self::onlyFrom($chars, $from)) {
					$out[] = self::convert($chars, $from, $en);
				}
			}
		}

		return array_values(array_unique(array_diff($out, array($text))));
	}

	private static function onlyFrom($chars, $alphabet) {
		$letters = 0;

		foreach ($chars as $c) {
			if ($c === ' ' || ctype_digit($c)) {
				continue;
			}

			if (!in_array($c, $alphabet, true)) {
				return false;
			}

			$letters++;
		}

		return $letters > 0;
	}

	private static function convert($chars, $from, $to) {
		$out = '';

		foreach ($chars as $c) {
			$i = array_search($c, $from, true);
			$out .= $i === false ? $c : $to[$i];
		}

		return $out;
	}

	/**
	 * Phonetic key shared by Latin, Russian and Ukrainian spellings: "apple", "аппле", "эпл", "aple" -> "apl";
	 * "одяг", "одиг" -> "adag". Lossy on purpose (vowels are one class, doubles and the final vowel are dropped).
	 */
	public static function phonetic($word) {
		$t = strtr(self::normalize($word), self::$translit);
		$t = preg_replace('/[^a-z0-9]/', '', $t);

		$t = strtr($t, array('sh' => 'S', 'ch' => 'C', 'zh' => 'Z', 'kh' => 'k', 'ph' => 'f', 'ck' => 'k', 'th' => 't'));
		$t = preg_replace('/c(?=[eiy])/', 's', $t);
		$t = strtr($t, array('c' => 'k', 'q' => 'k', 'x' => 'ks', 'w' => 'v', 'j' => 'Z', 'y' => 'i', 'h' => ''));
		$t = preg_replace('/[aeiou]+/', 'a', $t);
		$t = preg_replace('/(.)\1+/', '$1', $t);

		return preg_replace('/a$/', '', $t);
	}

	/**
	 * True when the words are equal or differ by exactly one insertion, deletion, replacement or swap of neighbours.
	 */
	public static function withinOneEdit($a, $b) {
		$a = preg_split('//u', (string)$a, -1, PREG_SPLIT_NO_EMPTY);
		$b = preg_split('//u', (string)$b, -1, PREG_SPLIT_NO_EMPTY);
		$la = count($a);
		$lb = count($b);

		if (abs($la - $lb) > 1) {
			return false;
		}

		if ($la === $lb) {
			$diff = array();

			for ($i = 0; $i < $la; $i++) {
				if ($a[$i] !== $b[$i]) {
					$diff[] = $i;

					if (count($diff) > 2) {
						return false;
					}
				}
			}

			if (count($diff) < 2) {
				return true;
			}

			return $diff[1] === $diff[0] + 1 && $a[$diff[0]] === $b[$diff[1]] && $a[$diff[1]] === $b[$diff[0]];
		}

		$short = $la < $lb ? $a : $b;
		$long = $la < $lb ? $b : $a;
		$i = 0;

		while ($i < count($short) && $short[$i] === $long[$i]) {
			$i++;
		}

		return array_slice($short, $i) === array_slice($long, $i + 1);
	}

	/**
	 * Synonym textarea -> groups of normalised phrases. Groups are separated by a new line, comma or semicolon,
	 * members by "=" ("одежда=шмотки=одяг, носки=гольфы"). Pieces without "=" are returned as errors.
	 *
	 * @return array array('groups' => array(array('одежда', 'шмотки'), ...), 'errors' => array('raw piece', ...))
	 */
	public static function parseSynonyms($text) {
		$groups = array();
		$errors = array();

		foreach (preg_split('/[\r\n,;]+/u', (string)$text) as $piece) {
			$piece = trim($piece);

			if ($piece === '') {
				continue;
			}

			$members = array();

			foreach (explode('=', $piece) as $member) {
				$member = implode(' ', self::tokens($member));

				if ($member !== '' && !in_array($member, $members, true)) {
					$members[] = $member;
				}
			}

			if (count($members) < 2) {
				$errors[] = $piece;
			} else {
				$groups[] = $members;
			}
		}

		return array('groups' => $groups, 'errors' => $errors);
	}

	/**
	 * Query words -> slots. A slot is one word, or up to 4 consecutive words that form a member of a synonym group;
	 * such a slot carries the words of every member of its group(s) as alternatives.
	 *
	 * @return array list of array('words' => array(...), 'syn' => array(words of the other members))
	 */
	public static function slots($words, $groups) {
		$index = array();

		foreach ($groups as $gid => $members) {
			foreach ($members as $member) {
				$index[$member][] = $gid;
			}
		}

		$slots = array();
		$i = 0;
		$count = count($words);

		while ($i < $count) {
			$taken = 1;
			$syn = array();

			for ($n = min(4, $count - $i); $n >= 1; $n--) {
				$phrase = implode(' ', array_slice($words, $i, $n));

				if (isset($index[$phrase])) {
					foreach (array_unique($index[$phrase]) as $gid) {
						foreach ($groups[$gid] as $member) {
							foreach (explode(' ', $member) as $w) {
								$syn[$w] = true;
							}
						}
					}

					$taken = $n;

					break;
				}
			}

			$slot_words = array_slice($words, $i, $taken);

			$slots[] = array('words' => $slot_words, 'syn' => array_values(array_diff(array_keys($syn), $slot_words)));

			$i += $taken;
		}

		return $slots;
	}
}
