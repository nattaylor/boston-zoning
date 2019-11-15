<?php
/**
https://api.municode.com/codesToc?jobId=351833&productId=15398
https://api.municode.com/CodesContent?jobId=351833&nodeId=PRONZOCOBOMA&productId=15398
https://api.municode.com/codesToc/children?jobId=351833&nodeId=ART53EABONEDI&productId=15398

.Children[].Id
.Docs[].Title //Section 53-1. - Statement of Purpose, Goals, and Objectives.
*/
function retrieve() {
	$articlesToMatch = "/ARTICLE (?:3|5|6|7|8|9|10|11|12|13|14|15|16|17|18|19|20|21|23|24|25|29|32|33|39|41|43|44|45|46|48|49|50|51|52|53|54|55|56|57|59|60|61|62|63|64|65|66|67|68|69|80|86|1065|651|659) /";
	//$articlesToMatch = "/ARTICLE (?:3) /";

	$toc = json_decode(file_get_contents('https://api.municode.com/codesToc?jobId=351833&productId=15398'));

	$articles = [];
	foreach ($toc->Children as $child) {
		if (!preg_match($articlesToMatch, $child->Heading))	{
			continue;
		}
		$articles[$child->Heading] = [];
		$url = "https://api.municode.com/CodesContent?jobId=351833&nodeId=%s&productId=15398";
		$content = json_decode(file_get_contents(sprintf($url, $child->Id)));
		foreach ($content->Docs as $doc) {
			array_push($articles[$child->Heading], $doc->Title);
		}
	}

	return $articles;
}
/**
 * 	"65(65-9)": {
 * 		"article": "ARTICLE 65 - DORCHESTER NEIGHBORHOOD DISTRICT",
 * 		"section": "Section 65-9. - Dimensional Regulations Applicable in Residential Subdistricts.",
 * 		"link": ""
 * 	},
 * @return [type] [description]
 */
function build() {
	$articlesJson = json_decode(file_get_contents("cache/articles.json"));
	$articles = [];
	foreach ($articlesJson as $key => $article) {
		$articleTitle = array_shift($article);
		preg_match('/^ARTICLE (?<id>[0-9]+) - (?<title>.*)$/', $articleTitle, $articleParts);
		foreach ($article as $section) {
			//Section 45-10. - Planned Development Area: Use and Dimensional Regulations."
			if (!preg_match('/^Section (?<id>[0-9]+-[0-9]+)\. - (?<title>.*)\.$/', $section, $sectionParts)) {
				continue;
			}
			$articles["{$articleParts['id']}({$sectionParts['id']})"] = [
				"article" => $articleTitle,
				"section" => $section,
				"sectionTitle" => $sectionParts['title'],
				"sectionNum" => $sectionParts['id'],
				"articleTitle" => $articleParts['title'],
				"articleId" => $articleParts['id']
			];
		}
	}
	return $articles;
}

echo json_encode(build(), JSON_PRETTY_PRINT);
