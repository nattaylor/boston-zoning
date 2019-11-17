<?php

/**
 * Join case variance codes to the descriptions of the articles
 *
 * Usage: php adhoc-variances.php | sort -n | uniq -c | sort -nr | head -n50
 */
$cases = json_decode(file_get_contents('dist/cases_20191112.json'));
$lookupVariances = json_decode(file_get_contents('lookup-variances.json'));
$lookupArticles = json_decode(file_get_contents('lookup-articles.json'));

foreach ($cases as $case) {
	//if (!isset($case->articles) || ($case->type == 'HEARINGS' && $case->type == 'HEARING') || $case->ward != "1") continue;
	if (!isset($case->articles) || ($case->type == 'HEARINGS' && $case->type == 'HEARING')) continue;
	foreach ($case->articles as $article => $variances) {
		if (count($variances) == 0) {
			//echo $article.": ".($lookupArticles->{$article}->sectionTitle ?? '').PHP_EOL;
			echo ($lookupArticles->{$article}->sectionTitle ?? '').PHP_EOL;
		} else if (count($variances) > 0) {
			foreach ($variances as $variance) {
				//echo $article.": ".($lookupArticles->{$article}->sectionTitle ?? '')." ".($lookupVariances->{$variance} ?? $variance).PHP_EOL;
				echo ($lookupArticles->{$article}->sectionTitle ?? '')." ".($lookupVariances->{$variance} ?? $variance).PHP_EOL;
			}
		}
	}
}
