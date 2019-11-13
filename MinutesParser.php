<?php
/**
 * Minutes are PDFs made up of sections delimeted by certain patterns
 * HTML is easier to work with than PDF, so convert them.
 *
 *
 */
namespace Zoning;

class MinutesParser {
	private $filename;
	public function main() {
		//$minutes = file_get_contents("https://www.boston.gov/departments/inspectional-services/zoning-board-appeal");
		$minutesPage = file_get_contents("cache/zoning-board-appeal");
		if (!preg_match_all('/href="(\/sites[^>]+\.pdf)"/sm', $minutesPage, $matches)) {
		}
		$parsedCases = [];
		
		foreach ($matches[1] as $path) {
			$base = "/Users/ntaylor/src/nattaylor@gmail.com/zoning/cache/minutes/";

			$this->filename = pathinfo($path)["basename"];
			if (!preg_match('/[a-z]+_[0-9]{1,2}_[0-9]{4}/', $this->filename, $dateMatch)) {
				continue;
			}
			$date = strftime("%Y-%m-%d", strtotime(str_replace('_', ' ', $dateMatch[0])));

			if (!file_exists("$base{$this->filename}")) {
				file_put_contents("$base{$this->filename}", file_get_contents("https://boston.gov$path"));
			}
			if (!file_exists("$base{$this->filename}.html")) {
				file_put_contents("$base{$this->filename}.html", shell_exec("pdftohtml -i -noframes -stdout $base{$this->filename}"));
			}
			$minutes = file_get_contents("$base{$this->filename}.html");

			$minutes = preg_replace('/(\n[0-9]+<br>|\n<hr>|<!DOCTYPE.*?<BODY.*?>|STEPHANIE HAYNES.*)/sm', '', $minutes);

			// Sections are denoted by when they start
			$sections = preg_split('/\n([A-Za-z\-\ \/]+: [0-9]+:[0-9\.amp\ ]+)<br>/sm', $minutes, 0, PREG_SPLIT_DELIM_CAPTURE);
			
			// Shift off the intro section of approving minutes, etc
			$intro = array_shift($sections);

			for ($i=0; $i<count($sections); $i+=2) {
				if (!preg_match('/(hearing|extension|gcod|discussion|code|final|recommendation|irish|interpretation|reconsideration)/', strtolower($sections[$i]))) {
					//throw new Exception("Problem splitting: $filename ".json_encode($sections[$i]));
					echo "Problem splitting: {$this->filename} ".json_encode($sections[$i]);
				}

				if (!preg_match('/(.*?):/', $sections[$i], $sectionLabel)) {
				}

				$cases = preg_split('/(?:^|\n)Case: /sm', trim(strip_tags($sections[$i+1])));
				array_shift($cases);

				
				foreach ($cases as $case) {
					/*
					preg_match_all('/\n([A-Z][^:\ ]+:)/sm', $case, $matches);
					foreach($matches[1] as $match) {
						echo utf8_encode($match).PHP_EOL;
					}
					*/
					try {
						$parsedCase = $this->parseCase($case);
						$parsedCase["date"] = $date;
						$parsedCase["type"] = $this->normalizeType($sectionLabel[1]);
						//$parsedCase["casestr"] = $case;
						//echo json_encode( $parsedCase ).PHP_EOL;
					} catch (Exception $e) {
						echo $e->getMessage().PHP_EOL;
						continue;
					}
					array_push($parsedCases, $parsedCase);
				}
			}
		}
		$parsedCases = array_filter($parsedCases, function ($case) {
			return strlen(json_encode($case)) > 0;
		});
		$arrayCases = [];
		foreach($parsedCases as $key=>$case) {
			array_push($arrayCases, $case);
		}
		return Json_encode($arrayCases, JSON_PRETTY_PRINT);
		/*
		return "[".implode(",".PHP_EOL, array_map(function ($case) {
			return json_encode(array_map(function ($case1) {
				return rtrim(str_replace(["`", "\n", "\"", " ", "\\"], " ", $case1), "\\");
			}, $case));
		}, $parsedCases))."]";
		*/
	}

	private function parseCase($caseStr) {
		$case = [];
		if (!preg_match('/((?:BOA|BZC)(?:-|#)\ ?[0-9]+),? ?Address: (.*?),? ?Ward:? ?([0-9]+) ?,? ?Applicant: ?(.*?)(\n.*)/sm', $caseStr, $matches)) {
			if (!preg_match('/((?:BOA|BZC)(?:-|#)\ ?[0-9]+),? ?Address: (.*?),? ?Ward:? ?([0-9]+) ?,? ?Applicant: ?(.*)/sm', $caseStr, $matches)) {
				$matches = $this->specialCases($caseStr);
				if (!$matches || !preg_match('/(BOA|BZC)/', $matches[1])) {
					//"first line bad: ".
					//var_dump("$filename $caseStr");
					throw new Exception(explode("\n", $caseStr)[0]);
					//throw new Exception("first line bad: ".$caseStr);
				}
			}
		}
		//echo explode("\n", $caseStr)[0]." --> ".implode(" | ", array_slice($matches, 1, -1)).PHP_EOL;

		if (count($matches) == 5) {
			array_push($matches, "");
		}	else if (count($matches) != 6) {
			throw new Exception("Problem parsing case: ".json_encode($matches));
		}

		list($_, $case['appeal'], $case['address'], $case['ward'], $case['applicant'], $remaining) = $matches;

		if ($remaining != "") {
			$caseParts = preg_split('/\n(Purpose|Article\(s\)|Discussion|Votes|Documents\/Exhibits|Testimony|Discussion|Vote):/sm', $remaining, 0, PREG_SPLIT_DELIM_CAPTURE);
			
			array_shift($caseParts);
			
			for ($i=0; $i<count($caseParts); $i+=2) {
				$labelMap = [
					"Purpose" => "purpose",
					"Article(s)" => "articles",
					"Discussion" => "discussion",
					"Votes" => "vote",
					"Documents/Exhibits" => "documents",
					"Testimony" => "testimony",
					"Vote" => "vote"
				];
				$case[$labelMap[$caseParts[$i]]] = trim(str_replace("\n", " ", $caseParts[$i+1]));
			}

			if (isset($case['vote'])) {
				$case['status'] = $this->inferStatus($case["vote"]);
			} else if (isset($case['discussion'])) {
				$case['status'] = $this->inferStatus($case["discussion"]);
			} else {
				$case['status'] = 'NOVOTE';
			}

			$case['appeal'] = preg_replace('/(BOA- ?,?|BOA# ?)/', 'BOA-', $case['appeal']);
			
			if (isset($case['articles'])) {
				$case['articles'] = $this->parseArticles($case['articles']);
			}

			if (isset($case['applicant'])) {
				$case['applicant'] = $this->normalizeApplicant($case['applicant']);
			}

			if (isset($case['address'])) {
				$case['address'] = trim($case['address']);
				$parcel = $this->lookupParcel($case['address']);
				if ($parcel) {
					$case['parcel']=$parcel;
				}
			}


		}
		return $case;
	}

	private function inferStatus($vote) {
		$vote = strtolower(preg_replace("/\n/", ' ', $vote));
		if (strpos($vote, "voted unanimously to approve") || strpos($vote, "voted to approve")) {
			return "APPROVED";
		} elseif (strpos($vote, "voted unanimously to deny") || strpos($vote, "appeal was denied") || strpos($vote, "relief was denied") || strpos($vote, "appeals were denied")|| strpos($vote, "denied")) {
			return "DENIED";
		} elseif (strpos($vote, "dismiss without prejudice") || strpos($vote, "dismissed without prejudice") || strpos($vote, "dismiss the appeal without prejudice")) {
			return "DISMISSED";
		} elseif (strpos($vote, "deferred") || strpos($vote, "defer")) {
			return "DEFERRED";
		}
		return false;
	}

	private function parseArticles($articles) {
		if (!preg_match_all('/([0-9]+\(.*?)(?:\) |\)\n|\)$)/', $articles, $matches)) {
			//
		}
		$cleanedMatches = [];
		foreach ($matches[0] as $article) {
			$key = trim($article);
			$variances = [];
			if (preg_match('/([0-9]+\([0-9\-\.]+): ?(.*?\))/', $article, $matches2)) {
				$key = $matches2[1].")";

				$variances = array_map(function ($variance) {
					if (substr_count($variance, ")") > substr_count($variance, "(")) {
						$variance = preg_replace('/\)$/', '', $variance);
					}
					return $variance;
				}, preg_split('/(?:, | &amp; )/', $matches2[2]));
			}
			$cleanedMatches[$key] = $variances;
		}
		return $cleanedMatches;
	}

	private function normalizeType($type) {
		$searches = [
			"/^(?:EXTENSIONS|Extensions|Extension|EXTENSION)$/",
			"/^(?:HEARINGS|HEARING)$/"
		];
		$replacements = [
			"EXTENSION",
			"HEARING"
		];
		return preg_replace($searches, $replacements, $type);
	}

	private function normalizeApplicant($applicant) {
		$applicant = preg_replace('/(?:, Esq\.?$| for$)/', '', $applicant);
		$lookupApplicants = json_decode(file_get_contents('lookup-applicants.json'));
		return $lookupApplicants->{$applicant} ?? $applicant;
	}

	private function lookupParcel($address) {
		$lookupParcels = json_decode(file_get_contents('lookup-parcels.json'));
		return $lookupParcels->$address ?? false;
	}

	private function specialCases($caseStr) {
		$specialCases = json_decode(file_get_contents('lookup-cases.json'));
		$split = explode("\n", $caseStr, 2);
		$first = $split[0];
		$remaining = $split[1] ?? "";
		if (isset($specialCases->$first)) {
			return array_merge([""], $specialCases->$first, [$remaining]);
		} else {
			return false;
		}
	}

	private function checkCases($cases) {
		foreach ($cases as $case) {
			if (!preg_match('/^(?:BOA|BZC)-[0-9]+/', $case['appeal'])) {
				echo $case["date"]." ".$case['appeal']." ".json_encode($case).PHP_EOL;
			}
			if (!isset($case['status']) || strlen($case['status'])==0) {
				//echo json_encode($case).PHP_EOL;
			}
		}
	}
}
