<?php
/**
 * Generate Cases JSON from OCRed Minutes
 */

function main() {
	$decisions = call_user_func(function () {
		$decisions = [];
		$handle = fopen("dist/decisions_20191109.csv", "r");
		fgets($handle);
		while (($row = fgetcsv($handle, 1000, ",")) !== false) {
				$decisions[$row[2]] = $row[1];
		}
		fclose($handle);
		return $decisions;
	});

	$cases = [];
	foreach (glob("cache/minutes/*.{txt,html}", GLOB_BRACE) as $file) {
		$minutes = processAppeals($file, $decisions);
		if (!$minutes) {
			continue;
		}
		$cases+=$minutes;
		$cases = array_merge($cases, $minutes);
		//printf("%s → added %s for %s total".PHP_EOL, $file, count($minutes), count($cases));
	}

	foreach ($cases as &$case) {
		if(!isset($case['articles'])) {
			continue;
		}
		$case['articles'] = lazyArticles($case['articles']);
	}
	$cases = array_values(array_filter($cases, function ($case) {
		return isset($case['case']);
	}));
	return json_encode($cases, JSON_PRETTY_PRINT);
}

function processAppeals($file, $decisions) {

	$type = "Ocr";
	$text = file_get_contents($file);
	if (!preg_match('/Page 2 of (?:2|3)/sm', $text)) {
		return false;
	}
	if (preg_match('/Board Of Appeals/sm', $text)) {
		$text = html_entity_decode(strip_tags($text));
		$type = "html";
	}
	$split = preg_split('/(?:Page ?2 o(?:f|r) ?2|Page 3 of 3)/sm', $text);
	if (end($split) == "\n") {
		array_pop($split);
	}
	$cases = [];
	$i = 1;
	foreach ($split as $appeal) {
		$case = parseAppeal($appeal, $type, $decisions);
		if (!$case) {
			continue;
		}
		//$case['page'] = $i;
		array_push($cases, $case);
		$i += 2;
	}
	return $cases;
}

function parseAppeal($str, $type, $decisions) {
	$case = [];
	$regexes = [
		'date' => '/\nHearings for (?<date>.*?)\n/sm',
		'appeal' => '/\n(?<appeal>BOA.*?)\n(?<seeking>.*?)Article\(s(?:\)|\})/sm',
		'articles' => '/\nArticle\(s(?:\)|\}) +Description\n\n(?<articles>.*?)\nDescription:/sm',
		'description' => '/\nDescription: ?(?<description>.*?)DOCUMENTS CONSIDERED AT THE HEARING/sm',
		'summary' => '/\nSUMMARY: ?(?<summary>.*?)\nA true copy of resolution adopted by the Board of ?Appeals/sm'
	];
	$appealRegex = '/^(?<case>.*?) Address:(?<address>.*?)Ward - (?<ward>[0-9]+) Applicant: ?(?<applicant>.*)$/';

	if ($type == 'html') {
		$regexes['appeal'] = '/\n(?<appeal>BOA.*?Applicant: .*?\n)(?<seeking>.*?)Article\(s(?:\)|\})/sm';
		$regexes['articles'] = '/\nArticle\(s(?:\)|\})\nDescription\n(?<articles>.*?)\nDescription:/sm';
		if (!preg_match('//usm', $str)) {
			$str = iconv("UTF-8", "ISO-8859-1//IGNORE", $str);
		}
		$str = preg_replace('/_{2,}/', '', $str);
	}

	foreach ($regexes as $key => $regex) {
		unset($matches);
		if (!preg_match($regex, $str, $matches)) {
			//
		}
		foreach ($matches as $key => $match) {
			if (preg_match('/[a-z]+/', $key)) {
				$case[$key] = trim($match);
			}
		}
	}

	if (isset($case['appeal'])) {
		if (!preg_match($appealRegex, $case['appeal'], $matches)) {
			$appealRegex = '/^(?<case>.*?)\n+Address:(?<address>.*?)Ward(?: ?- ?(?<ward>[0-9]+))?\n+Applicant: ?(?<applicant>.*)$/';
			if (!preg_match($appealRegex, $case['appeal'], $matches)) {
				if (!preg_match('/^(?<case>.*?)\n+Address:/', $case['appeal'], $matches)) {
					//
				}
			}
		}
		if (isset($matches['case'])) {
			$matches['case'] = preg_replace('/^BOA/', 'BOA-', $matches['case']);
		}
		foreach ($matches as $key => $match) {
			if (preg_match('/[a-z]+/', $key)) {
				$case[$key] = trim($match, ", ");
			}
		}
	} else {
		return false;
	}

	if (isset($case['summary'])) {
		$case['summary'] = preg_replace('/\n/', ' ', $case['summary']);

	}

	if (isset($case['description'])) {
		$case['description'] = preg_replace('/\n/', ' ', $case['description']);
	}

	if (isset($case['articles'])) {
		$articles = array_filter(
			explode("\n", $case['articles']),
			function ($line) {
				return preg_match('/^Art/', $line);
			}
		);
		$articles = array_map(
			function ($article) {
				if (!preg_match('/^Art(?:icle)?\.? (?<article>[0-9]+),? Se(?:c|e)(?:tion)?\.? ?(?<section>[0-9\-]+)(?<description>.*)/', $article, $matches)) {
					return ["full" => $article];
				}
				return [
					"article" => $matches['article'] ?? -1,
					"section" => $matches['section'] ?? -1,
					"description" => trim($matches['description'], ' *') ?? -1,
					"full" => $matches[0]
				];
			},
			$articles
		);
		$case['articles'] = array_values($articles);
	}

	if (isset($case['date'])) {
		$case['date'] = strftime("%Y-%m-%d", strtotime($case['date']));
	}

	if (isset($case['case'])) {
		if (isset($decisions[$case['case']])) {
			$case['status'] = $decisions[$case['case']];
		} else if (isset($case['summary'])) {
			if (preg_match('/approve.*(?:motion carried|voted to approve)/i', $case['summary'])) {
				$case['status'] = 'APPROVED';
			} else if (preg_match('/deny.*motion carried/i', $case['summary'])) {
				$case['status'] = 'DENIED';
			} else if (preg_match('/(?:case was postponed|defer(?:red)?|re-advertised)/i', $case['summary'])) {
				$case['status'] = 'DEFERRED';
			} else {
				$special = [
					"BOA-s19100" => "APPROVED",
					"BOA-657082" => "DEFERRED",
					"BOA-775979" => "APPROVED",
					"BOA-777089" => "APPROVED",
					"BOA-692071" => "DEFERRED",
					"BOA-730634" => "APPROVED",
					"BOA-738153" => "APPROVED",
					"BOA-865552" => "DENIED",
					"BOA-804787" => "DENIED",
					"BOA-695061" => "DENIED",
					"BOA-695062" => "DENIED"
				];
				if (isset($special[$case['case']])) {
					$case['status'] = $special[$case['case']];
				}
			}
		} else {
			$case['status'] = 'UNKNOWN';
		}
	}
	if (isset($case['summary'])) {
		$case['discussion'] = $case['summary'];
		unset($case['summary']);
	}

	if (isset($case['description'])) {
		$case['purpose'] = $case['description'];
		unset($case['description']);
	}

	if (isset($case['applicant'])) {
		$case['applicant'] = preg_replace('/(?:, Esq\.?$| for$)/', '', $case['applicant']);
	}

	if (isset($case['ward'])) {
		$case['ward'] = strval(intval($case['ward']));
	}

	if (isset($case['address'])) {
		// FIXME
		$lookupParcels = json_decode(file_get_contents('/tmp/blah.json'));
		$case['parcel'] = $lookupParcels->{$case['address']} ?? false;
	}

	unset($case['appeal']);
	unset($case['seeking']);
	$case['type'] = 'HEARING';
	return $case;
}

function lazyArticles($articles) {
	$specArticles = [];
	foreach ($articles as $article) {
		if(!isset($article['article']) || !isset($article['section'])) {
			continue;
		}
		$key = sprintf("%s(%s-%s)", $article['article'], $article['article'], $article['section']);
		$specArticles[$key] = [];
		if (!isset($article['description']) || $article['description'] == "") {
			continue;
		}
		array_push($specArticles[$key], $article['description']);
	}
	return $specArticles;
}

echo main();
