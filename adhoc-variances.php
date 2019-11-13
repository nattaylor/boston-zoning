<?php

//php adhoc-variances.php | sort -n | uniq -c | sort -nr | head -n50
/**
 * TODO: finishe articles.js https://library.municode.com/ma/boston/codes/redevelopment_authority?nodeId=ART65DONEDI
 * TODO: map variances into heirachy
 */

$cases = json_decode(file_get_contents('dist/cases_20191112.json'));
$lookupVariances = json_decode(file_get_contents('lookup-variances.json'));
$lookupArticles = json_decode(file_get_contents('lookup-articles.json'));

foreach ($cases as $case) {
	if (!isset($case->articles) || ($case->type == 'HEARINGS' && $case->type == 'HEARING') || $case->ward != "1") continue;
	foreach ($case->articles as $article => $variances) {
		if (count($variances) == 0) {
			echo $article.": ".($lookupArticles->{$article}->section ?? '').PHP_EOL;
		} else if (count($variances) > 0) {
			foreach ($variances as $variance) {
				echo $article.": ".($lookupArticles->{$article}->section ?? '')." ".($lookupVariances->{$variance} ?? $variance).PHP_EOL;
			}
		}
	}
}
