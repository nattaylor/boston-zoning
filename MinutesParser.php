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
				if($parcel) {
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
			if (preg_match('/([0-9]+\([0-9\-\.]+): (.*?\))/', $article, $matches2)) {
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
		$specialCases = [
			"BZC-33494 Address: 68 Willow Court Ward 7 Applicant: Willow Ct, LLC" =>
				["BZC-33494", "68 Willow Court", "7", "Willow Ct, LLC"],

			"BZC-33158 Address: 319-327 Chelsea Street Ward 1 Applicant: Richard Lynds, Esq" =>
				["BZC-33158", "319-327 Chelsea Street", "1", "Richard Lynds, Esq"],

			"BOA-948357Address: 303 Silver Street Ward 6 Applicant: George Morancy" =>
				["BOA-948357", "303 Silver Street", "6", "George Morancy"],

			"BOA-#929262 Address: 73-75 Maverick Square Ward 1 Applicant: OZ DBA" =>
				["BOA-929262", "73-75 Maverick Square", "1", "OZ DBA"],

			"BOA938099- Address: 105 West First Ward 6 Applicant: Eli Long" =>
				["BOA-938099", "105 West First", "6", "Eli Long"],

			"BOA-918720 Address: 95 Ellington Street Ward 14Applicant: Ronan Ryan" =>
				["BOA-918720", "95 Ellington Street", "14", "Ronan Ryan"],

			"BOA-881803 Address: 131 Condor Street Ward 1Applicant: Neighborhood of Affordable Housing," =>
				["BOA-881803", "131 Condor Street", "1", "Neighborhood of Affordable Housing"],

			"BOA-853295, Address: 31 Dell Avenue Ward:18 Applicant: Elida Sanchez" =>
				["BOA-853295", "31 Dell Avenue", "18", "Elida Sanchez"],

			"BOA-924595Address: 103-105 Newbury Street Ward 5 Applicant: Frazer 103 Holdings LP" =>
				["BOA-924595", "103-105 Newbury Street", "5", "Frazer 103 Holdings LP"],

			"BOA-524297 Address: 85 Linden Street, Ward 21Applicant: Jackson Solmiak" =>
				["BOA-524297", "85 Linden Street", "21", "Jackson Solmiak"],

			"BOA-613478, Address: 820 William T Morrissey BLVD, Ward: 16, Applicant: Outfront Media, LLC" =>
				["BOA-613478", "820 William T Morrissey BLVD", "16", "Outfront Media, LLC"],

			"BOA912336, Address: 101-103 Rosseter Street Ward: 14 Applicant: Kenneth Battle" =>
				["BOA-912336", "101-103 Rosseter Street", "14", "Kenneth Battle"],

			"BOA-524297 Address: 85 Linden Street, Ward 21Applicant: Jackson Solmiak" =>
				["BOA-524297", "85 Linden Street", "21", "Jackson Solmiak"],

			"BOA-613478, Address: 820 William T Morrissey BLVD, Ward: 16, Applicant: Outfront Media, LLC" =>
				["BOA-613478", "820 William T Morrissey BLVD", "16", "Outfront Media, LLC"],

			"BOA912336, Address: 101-103 Rosseter Street Ward: 14 Applicant: Kenneth Battle" =>
				["BOA-912336", "101-103 Rosseter Street", "14", "Kenneth Battle"],

			"BOA-907797 Address: 744-748 Dudley Street Ward 7" =>
				["BOA-907797", "744-748 Dudley Street", "7", ""],

			"BOA-824678, Address: 301-303 Corey Street Ward 20 Applicant: Michael Kelly" =>
				["BOA-824678", "301-303 Corey Street", "20", "Michael Kelly"],

			"BOA792891 Address: 111 West Street Ward 18 Applicant: Guimy Cesar" =>
				["BOA-792891", "111 West Street", "18", "Guimy Cesar"],

			"BOA879862 Address: 60-62 Mapleton Street Ward 22 Applicant: Matthew Murphy" =>
				["BOA-879862", "60-62 Mapleton Street", "22", "Matthew Murphy"],

			"BOA842247- Address: 75-77 Cedar Street , Ward 11 Applicant: Ulyen Coleman" =>
				["BOA-842247", "75-77 Cedar Street", "11", "Ulyen Coleman"],

			"BOA842247- Address: 75-77 Cedar Street , Ward 11 Applicant: Ulyen Coleman" =>
				["BOA-842247", "75-77 Cedar Street", "11", "Ulyen Coleman"],

			"BZC-30461 Address: 191 Talbot Avenue, Ward , Applicant: Derric Small, Esq" =>
				["BZC-30461", "191 Talbot Avenue", "15", "Derric Small, Esq"],

			"BOA-859199 Address:59 Blake Street , Ward 18, Applicant: Derric Small" =>
				["BOA-859199", "59 Blake Street", "18", "Derric Small"],

			"BOA-853982 Address:114 Bennington Street , Ward 1, Applicant: Michael Romano" =>
				["BOA-853982", "114 Bennington Street", "1", "Michael Romano"],

			"BOA-,810527 Address: 694 East Fifth Street Ward: 6 , Applicant: Lindsay Bennett" =>
				["BOA-,810527", "694 East Fifth Street", "6 , ", "Lindsay Bennett"],

			"BOA-808985 Address: 69-73 Almont Street, Ward 18" =>
				["BOA-808985", "69-73 Almont Street", "18", ""],

			"BOA-827186 Address: 46 Brooksdale Road, Ward 22" =>
				["BOA-827186", "46 Brooksdale Road", "22", ""],

			"BOA-812233 Address:15-17 Swallow Street Ward 6 Applicant: Brendon O'Heir" =>
				["BOA-812233", "15-17 Swallow Street", "6 ", "Brendon O'Heir"],

			"BOA-779357, Address:29-31 Ward Street , Ward 7 Applicant: 29-31 Ward Street LLC" =>
				["BOA-779357,", "29-31 Ward Street", "7", "29-31 Ward Street LLC"],

			"BOA-488299, Address:358-360 Athens Street , Ward 6 Applicant: Ann Marie Bayer," =>
				["BOA-488299", "358-360 Athens Street", "6", "Ann Marie Bayer,"],

			"BOA-812233 Address:15-17 Swallow Street Ward 6 Applicant: Brendon O'Heir" =>
				["BOA-812233", "15-17 Swallow Street", "6", "Brendon O'Heir"],

			"BOA-,803912 Address: 29 Minot Street Ward: 16 , Applicant: Linda Lombardi" =>
				["BOA-803912", "29 Minot Street", "16", "Linda Lombardi"],

			"BOA-818470 Address: 85-93 Glenville Avenue, Ward 21 Applicants: Daniel Toscano, Esq." =>
				["BOA-818470", "85-93 Glenville Avenu", "21", "Daniel Toscano, Esq."],

			"BOA-812908 Address:537A-537 Columbus Avenue , Ward 4 Applicant: Leo Papile" =>
				["BOA-812908", "537A-537 Columbus Avenue", "4", "Leo Papile"],

			"BOA-806243, Address:23-25 Bowdoin Avenue , Ward 14 Applicant: James Christopher" =>
				["BOA-806243", "23-25 Bowdoin Avenue", "14", "James Christopher"],

			"BOA-779357, Address:29-31 Ward Street , Ward 7 Applicant: 29-31 Ward Street LLC" =>
				["BOA-779357", "29-31 Ward Street", "7", "29-31 Ward Street LLC"],

			"BOA-,813658 Address: 76 White Street , Ward 1 Applicant: Smith &amp; Townsend LLC" =>
				["BOA-813658", "76 White Street", "1", "Smith &amp; Townsend LLC"],

			"BOA-805721, Address: 66 Edson Street, Ward 17" =>
				["BOA-805721", "66 Edson Street", "17", ""],

			"BOA-,787613 Address: 18 Marbury Terrace , Ward 11 Applicant: Marbury Terrace, Inc." =>
				["BOA-787613", "18 Marbury Terrace", "11", "Marbury Terrace, Inc."]
		];
		$split = explode("\n", $caseStr, 2);
		$first = $split[0];
		$remaining = $split[1] ?? "";

		if (array_key_exists($first, $specialCases)) {
			return array_merge([""], $specialCases[$first], [$remaining]);
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

	/**
	 * @todo east west
	 *
	 * @param  [type] $parsedCases [description]
	 * @return [type]              [description]
	 */
	private function findParcels($parsedCases) {
		$abbreviations = array_values([
			'ST' => 'street',
			'AV' => 'avenue',
			'RD' => 'road',
			'PL' => 'place',
			'TE' => 'terrace',
			'PK' => 'park',
			'SQ' => 'square',
			'CT' => 'court',
			'DR' => 'drive',
			'WY' => 'way',
			'HW' => 'highway',
			'PZ' => 'plaza',
			'LA' => 'lane'
			/*
			'RO' => '',
			'PW' => '',
			'BL' => '',
			'CI' => '',
			'WH' => '',
			'CC' => '',
			'CR' => '',
			'RD' => '',
			'FW' => ''
			*/
		]);
		$revAbbr = [];
		foreach ([
			'ST' => 'street',
			'AV' => 'avenue',
			'RD' => 'road',
			'PL' => 'place',
			'TE' => 'terrace',
			'PK' => 'park',
			'SQ' => 'square',
			'CT' => 'court',
			'DR' => 'drive',
			'WY' => 'way',
			'HW' => 'highway',
			'PZ' => 'plaza',
			'LA' => 'lane'
			/*
			'RO' => '',
			'PW' => '',
			'BL' => '',
			'CI' => '',
			'WH' => '',
			'CC' => '',
			'CR' => '',
			'RD' => '',
			'FW' => ''
			*/
		] as $k => $v) {
			$revAbbr[$v] = strtolower($k);
		}
		$parcels = Parcels::load('fy19fullpropassess.csv');
		foreach ($parsedCases as $case) {
			//echo "Searching for {$case['address']}".PHP_EOL;
			list($stNum, $remaining) = explode(" ", $case["address"], 2);
			$split = preg_split('/ (street|avenue|road|place|terrace|park|square|court|drive|way|highway|plaza|lane)$/', strtolower($remaining), 0, PREG_SPLIT_DELIM_CAPTURE);
			$stName = $split[0];
			$stSuf = $split[1] ?? '';
			$stNum = preg_replace('/([0-9]+).*/', '$1', $stNum);
			
			

			$parcelId = false;
			$key = implode(" ", [$stNum, $stName, $revAbbr[$stSuf] ?? '']);
			if (array_key_exists($key, $parcels)) {
				$parcelId = $parcels[$key];
			}
			/*
			foreach($parcels as $parcel) {
				//echo var_dump($parcel, [$stNum, $stName, $revAbbr[$stSuf]]);
				if ($parcel[1] == $stNum && $parcel[2] == $stName && $parcel[3] == $revAbbr[$stSuf]) {
					$parcelId = $parcel;
					//echo var_dump($parcel, [$stNum, $stName, $stSuf]);
				} else {

				}
			}
			*/
			if (!$parcelId) {
				echo "No match for {$case['address']} with key: $key".PHP_EOL;
			} else {
				//echo "Found $parcelId for {$case['address']}".PHP_EOL;
			}
		}
	}
}
