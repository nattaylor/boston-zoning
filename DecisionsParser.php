<?php
/**
 * Parse the Zoning Decisions HTML into structured data
 *
 * @see https://www.boston.gov/departments/inspectional-services/zoning-board-appeal-decisions
 *
 * As of 2019-09-24 many typos existed such as:
 *  - variations of APPROVED DECISIONS like Approved decisions
 *  - variations of BOA-0123456 like BOA_ and BOA#
 *
 * There are a million better ways to do this, but... it works
 * 
 * @return array The results of the form ["data"=>(array),"meta"=>(array),"headers"=>(array)]
 */
class DecisionParser {
	public function main() {

		$file = file_get_contents("zoning-board-appeal-decisions");
		
		$lines = explode("\n", $file);

		// Eliminate the extraneous lines
		$filtered = array_filter($lines, function($item) {
			$date = preg_match('/^.*[A-Za-z]+ [0-9]{1,2}, [0-9]{4}.*$/', $item);
			$label = preg_match('/<address>(?:A|D).*<\/address>/', $item);
			$case = preg_match('/BOA/', $item);
			return $date || $label || $case;
		});

		// Clean up each line to remove <tags> etc
		$cleansed = array_map(array($this, 'cleanLine'), $filtered);
		
		// Since cleanLine returns mixed, we must flatten it
		$flattened = [];
		array_walk_recursive($cleansed, function($item) use (&$flattened) { $flattened[] = $item; });
		
		// 
		$structured = array_map(array($this, 'parseAppeal'), $flattened);

		// Split up by date (MM/DD/YY) and the set of decisions that follow
		$splits = preg_split('/([0-9\/]{8})/', implode("\n", $structured), 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
		$dated = [];
		for($i = 0; $i<count($splits); $i+=2) {
			$dated[$splits[$i]] = $splits[$i+1];
		}

		$data = [];
		foreach($dated as $date=>$set) {
			// Split the set into a label for approved/denied and the decisions
			$categoryRegex = '/(APPROVED DECISIONS\n|Approved decisions\n|DENIED DECISIONS\n|Denied Decisions\n|Denied decisions\n|Denied Decision\n|DENIED DECISION\n|DENIED\n|Denied\n)/';
			$parts = preg_split($categoryRegex,trim($set), 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
			for($i=0; $i<count($parts); $i+=2) {
				foreach(explode("\n", trim($parts[$i+1])) as $part) {
					list($appeal, $address, $ward) = explode("\t", $part);
					$status = strpos(strtolower(trim($parts[$i])), "approved") !== False ? "APPROVED" : "DENIED";
					array_push($data, [$date,$status,$appeal,$address,$ward]);
				}
			}
		}

		$headers = ["date","status","appeal", "address", "ward"];
		$meta = ["input"=>substr_count($file, "BOA-")];
		
		$results = ["headers"=>$headers, "data"=>$data, "meta"=>$meta];

		return $results;
	}

	/**
	 * Clean each line into either date, category or a decision
	 * @param  string $line a line from the decisions
	 * @return string       the cleansed line
	 */
	private function cleanLine($line) {
		if(	preg_match('/^.*?([A-Za-z]+ [0-9]{1,2}, [0-9]{4}).*$/', $line, $match) ) {
			return strftime("%D",strtotime($match[1]));
		} else if ( preg_match('/<address>(.*?)<\/address>/', $line, $match) ) {
			return $match[1];
		} else if ( preg_match('/(BOA.*<br \/>)/', $line, $match) ) {
			$parsed = array_map(function($line) {
				return preg_replace('/^[0-9]+\.(\ | )+BOA/', 'BOA', $line);
			}, explode("<br />", $match[1]));
			return $parsed;
		} else if ( preg_match('/(BOA[^<]+)/', $line, $match) ) {
			return $match[1];
		} else {
			return $line;
		}
	}

	/**
	 * Parse an appeal into a [case, address, ward]
	 * @param  string $appeal a line that contains appeal information
	 * @return mixed          should be an array
	 */
	private function parseAppeal($appeal) {
		$appeal = trim($appeal, " ");
		$appeal = preg_replace('/(BOA#|BOA_|BOA-#)/', 'BOA-', $appeal);
		$appeal = preg_replace('/BOA([0-9])/', 'BOA-$1', $appeal);
		$appeal = preg_replace('/ Wad /', ' Ward ', $appeal);
		$appeal = preg_replace('/, /', ',', $appeal);
		$special = $this->specialCases($appeal);
		if($special) {
			return implode("\t", $special);
		} else if (preg_match('/^(?<case>BOA-[0-9]+)(?:\ | )+ ?(?<address>.*?), (?<ward>(?:Ward|WARD) [0-9]+)$/',$appeal,$match)) {
			return implode("\t", [$match['case'], $match['address'], $match['ward']]);
		} else {
			return $appeal;
		}
	}

	private function specialCases($appeal) {
		$cases = [
			"BOA-885363             38 Englewood Avenue 1" => ["BOA-885363", "38 Englewood Avenue", "Ward 1"],
			"BOA-892286             38R Minot Street, 16" => ["BOA-892286", "38R Minot Street", "Ward 16"],
			"BOA-871895             215 West Street, Ward" => ["BOA-871895", "215 West Street", "Ward 18"],
			"BOA-779503             1156-1160 Washington Street, War 17" => ["BOA-779503", "1156-1160 Washington Street", "Ward 17"],
			"BOA-785812             86 Princeton Street, Ward" => ["BOA-785812", "86 Princeton Street", "Ward 1"],
			"BOA-700855 1432-1440 Commonwealth Ave, Wd 21" => ["BOA-700855", "1432-1440 Commonwealth Ave", "Ward 21"],
			"BOA-694839 89-95 Brighton Avenue" => ["BOA-694839", "89-95 Brighton Avenue", "Ward 21"],
			"BOA-640507 522 East Seventh St, Wd 7-Granted In Part &amp;amp; Denied In Part" => ["BOA-640507", "522 East Seventh St*", "Ward 7"],
			"BOA-640509 8 Salerno Pl, Ward 7-Granted In Part &amp;amp; Denied In Part" => ["BOA-640509", "8 Salerno Pl*", "Ward 7"],
			"BOA-640513 4 Salerno Pl, Ward 7-Granted In Part &amp;amp; Denied In Part" => ["BOA-640513", "4 Salerno Pl*", "Ward 7"],
			"BOA-634526 3193-3201 Washington Street, 11" => ["BOA-634526", "3193-3201 Washington Street", "Ward 11"],
			"BOA-639862 38A-38 South Russell Street" => ["BOA-639862", "38A-38 South Russell Street", "Ward 5"],
			"BOA-830941             28 Dix Street, Ward, Ward 16" => ["BOA-830941", "28 Dix Street", "Ward 16"]
		];
		if(array_key_exists($appeal, $cases)) {
			return $cases[$appeal];
		} else {
			return False;
		}
	}
}
?>

