<?php
/**
 * Generate a JSON lookup keyed on Case Address pointing to the GIS Parcel ID
 *
 * Uses the assessing data and then a series of increasingly general joins
 */
ini_set('memory_limit', '256M');
/**
 * Write an indexed version of the assessing data
 *
 * The goal is to speed up lookups in `parcelJoin()`
 *
 * Generate slim.csv `cut -d$',' -f1-9 cache/fy19fullpropassess.csv`
 *
 * @return void
 */
function writeSlimParcels() {
	$parcels = [];
	$handle = fopen("cache/slim.csv", "r");
	$n = 0;
	while (($data = fgetcsv($handle)) !== false) {
		$n++;
		if (!isset($parcels[strtolower($data[4])])) {
			$parcels[strtolower($data[4])] = [];
		}
		array_push($parcels[strtolower($data[4])], $data);
	}

	fclose($handle);
	asort($parcels);
	
	file_put_contents("cache/slim.txt", serialize($parcels));
}

function parcelJoin() {
	$results = [];
	$parcels = unserialize(file_get_contents("cache/slim.txt"));
	$cases = json_decode(file_get_contents("dist/cases_20191112.json"));
	//$cases = json_decode(file_get_contents("/tmp/foo.json"));
	$n = 0;
	foreach ($cases as $case) {
		//$n++; if($n>100) break;
		//TODO Combine these to regexs
		if (!preg_match('/^(?<st_num>.*?)\ (?<st_name>.*?)\ (?<st_name_suf>[a-z]+(?: ?,)?)$/', trim(strtolower($case->address)), $address)) {
			if (!preg_match('/^(?<st_num>.*?)\ (?<st_name>.*?)$/', trim(strtolower($case->address)), $address)) {
			} else {
				$address['st_name_suf'] = '';
			}
		}

		//TODO Make this into a `preg_replace` with arrays
		$street = str_replace("west ", "w ", $address['st_name']);
		$street = str_replace("east ", "e ", $street);
		if (!isset($parcels[$street])) {
			//echo "Missed {$address['st_name']} for key \"$street\" ".str_replace("west ", "w ", $address['st_name']).PHP_EOL;
			continue;
		}
		$findMatchingParcels = function ($parcels, $address) {
			$matchingParcels = ["exact"=>[], "levenshteinScore"=>100000, "levenshtein"=>[]];
			foreach ($parcels as $parcel) {
				/**
				 * - Exact match
				 * - Condo match (e.g. multiple matches)
				 * - Approx match after splitting address
				 * - levenshtein distance match
				 */
				if ($address['st_num'] == $parcel[3]) {
					array_push($matchingParcels["exact"], $parcel);
				} else {
					$stNumRegex = '/^(?<st_num>[0-9]+)/';
					if (!preg_match_all($stNumRegex, $address['st_num'], $addressCase)) {}
					
					if (!preg_match_all($stNumRegex, $parcel[3], $addressParcel)) {}

					if ($addressCase['st_num'] == $addressParcel['st_num']) {
						array_push($matchingParcels["exact"], $parcel);
					} else {
						$cur = levenshtein($address['st_num']." ".$address['st_name'], $parcel[3]." ".$parcel[4]);
						if ($cur < $matchingParcels["levenshteinScore"]) {
							$matchingParcels["levenshteinScore"] = $cur;
							$matchingParcels["levenshtein"] = $parcel;
						}
					}

				}
			}

			if (count($matchingParcels["exact"]) == 1) {
				return 	array_merge($matchingParcels["exact"][0], ["exact"]);
			} else if (count($matchingParcels["exact"]) > 1) {
				$condos = array_filter($matchingParcels["exact"], function ($parcel) {
					return '' == $parcel[6];
				});
				if (count($condos) == 1) {
					return @array_merge(array_pop(array_reverse($condos)), ["exact"]);
				} else {
					return array_merge($matchingParcels["exact"][0], ["exact"]);
				}
			} else {
				return array_merge($matchingParcels["levenshtein"], ["levenshtein"]);
			}
		};
		
		$matchingParcel = $findMatchingParcels($parcels[$street], $address);
		/*if($matchingParcel[8] == 'levenshtein') {
			printf("levenshtein match %s to %s\n", $case->address, implode(" ", [$matchingParcel[3],$matchingParcel[4]]));
		}*/
		if($matchingParcel[8] == 'exact') {
			$results[$case->address] = $matchingParcel[0];
		}
	}
	echo json_encode($results, JSON_PRETTY_PRINT);
}

parcelJoin();
