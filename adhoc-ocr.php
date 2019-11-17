<?php

function processAppeal($file) {
	$type = "Ocr";
	$text = file_get_contents($file);
	if (preg_match('/^<!DOCTYPE/', $text)) {
		$text = html_entity_decode(strip_tags($text));
		$type = "html";
	}
	$split = preg_split('/Page 2 of 2/sm', $text);
	if (end($split) == "\n") {
		array_pop($split);
	}
	$cases = [];
	$i = 1;
	foreach ($split as $appeal) {
		// This seems eviiiil 😈
		$case = ("parse{$type}Appeal")($appeal, $type);
		if (!$case) {
			//
		}
		$case['page'] = $i;
		array_push($cases, $case);
		$i += 2;
	}
	return $cases;
}

function parseOcrAppeal($str, $type) {
	$case = [];
	$regexes = [
		'/\nHearings for (?<date>.*?)\n/sm',
		'/\n(?<appeal>BOA.*?)\n(?<seeking>.*?)Article\(s(?:\)|\})/sm',
		'/\nArticle\(s(?:\)|\}) +Description\n\n(?<articles>.*?)\nDescription:/sm',
		'/\nDescription: ?(?<description>.*?)DOCUMENTS CONSIDERED AT THE HEARING/sm',
		'/\nSUMMARY: ?(?<summary>.*?)\nA true copy of resolution adopted by the Board of Appeals/sm'
	];

	$case = [];
	foreach ($regexes as $regex) {
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
		$appealRegex = '/^(?<case>.*?) Address:(?<address>.*?)Ward - (?<ward>[0-9]+) Applicant: ?(?<applicant>.*)$/';
		if (!preg_match($appealRegex, $case['appeal'], $matches)) {
			//
		}
		if (isset($matches['case'])) {
			$matches['case'] = preg_replace('/^BOA/', 'BOA-', $matches['case']);
		}
		foreach ($matches as $key => $match) {
			if (preg_match('/[a-z]+/', $key)) {
				$case[$key] = trim($match, ", ");
			}
		}
	}

	if (isset($case['summary'])) {
		$case['summary'] = preg_replace('/\n/', ' ', $case['summary']);
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
					return [$article];
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
		$case['articles'] = $articles;
	}

	if (isset($case['date'])) {
		$case['date'] = strtotime($case['date']);
	}

	/*
	foreach ($case['articles'] as $article) {
		if (!isset($article['article'])) {
			//echo $article['full'].PHP_EOL;
		} else {
			echo "{$article['article']}({$article['article']}-{$article['section']}) {$article['description']}".PHP_EOL;
		}
	}
	*/

	

	unset($case['appeal']);
	//var_dump($case);
	return $case;
}

function genTxt() {
	foreach (glob('cache/minutes/*.html') as $file) {
		$str = file_get_contents($file);
		if (!preg_match('/(?:\n<META name="author"|Board of Appeals)/sm', $str)) {
			$pathinfo =  pathinfo($file);
			///Users/ntaylor/Downloads/VietOCR
			$pdf = implode("", ["/Users/ntaylor/src/nattaylor@gmail.com/zoning/", $pathinfo["dirname"], "/", $pathinfo['filename']]);
			$txt= implode("", ["/Users/ntaylor/src/nattaylor@gmail.com/zoning/", $pathinfo["dirname"], "/", $pathinfo['filename'], ".txt"]);
			echo $pdf.PHP_EOL;
			shell_exec("cp $pdf ocr");
			//var_dump(shell_exec('java -jar /Users/ntaylor/Downloads/VietOCR/VietOCR.jar $pdf $txt'));
		}
	}
}

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
	foreach (glob('cache/minutes/*.txt') as $file) {
		$minutes = processAppeal($file);
		if (!$minutes) {
			//
		}
		$cases+=$minutes;
	}
	foreach ($cases as &$case) {
		if (!isset($case['case'])) {
			continue;
		}
		$case['status'] = $decisions[$case['case']] ?? "UNKNOWN";
	}
	echo json_encode($cases, JSON_PRETTY_PRINT);
}

main();
