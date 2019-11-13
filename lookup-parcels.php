<?php
ini_set('memory_limit','256M');
function writeParcels() {
	$parcels = [];
	$handle = fopen("cache/slim.csv", "r");
	$n = 0;
	while (($data = fgetcsv($handle)) !== false) {
		//echo "Reading line ".implode(",", $data).PHP_EOL;
		$n++;
		if (strtolower($data[4]) == 'back') {
			//var_dump($data);
		}
		if (!isset($parcels[strtolower($data[4])])) {
			//echo "adding key ".strtolower($data[4]).PHP_EOL;
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
	$n = 0;
	foreach ($cases as $case) {
		//$n++; if($n>100) break;
		if (!preg_match('/^(?<st_num>.*?)\ (?<st_name>.*?)\ (?<st_name_suf>[a-z]+(?: ?,)?)$/', trim(strtolower($case->address)), $address)) {
			if (!preg_match('/^(?<st_num>.*?)\ (?<st_name>.*?)$/', trim(strtolower($case->address)), $address)) {
			} else {
				$address['st_name_suf'] = '';
			}
		}
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
		/*
		$matchingParcels = array_filter($parcels[$street], function ($parcel) use ($address) {
			return $address['st_num'] == $parcel[3];
		});
		if (count($matchingParcels)>1) {
			$matchingParcels = array_filter($matchingParcels, function ($parcel) use ($address) {
				return '' == $parcel[6];
			});
		} else if (count($matchingParcels) == 0) {

			$matchingParcels = array_filter($parcels[$street], function ($parcel) use ($address) {
				// Split the case address
				var_dump(preg_split('/[0-9]+/', $address['st_num'], -1, PREG_SPLIT_DELIM_CAPTURE));
				$caseAddress = preg_split('/[0-9]+/', $address['st_num'], -1, PREG_SPLIT_DELIM_CAPTURE)[0];
				// Split the parcel address
				$parcelAddress = preg_split('/[0-9]+/', $parcel[3], -1, PREG_SPLIT_DELIM_CAPTURE)[0];
				echo $caseAddress . " " . $parcelAddress.PHP_EOL;
				if ($address['st_name'] == 'brimmer') {
					//
				}
				return $caseAddress == $parcelAddress;
			});
		}
		//printf("Found %s matching parcels".PHP_EOL, count($matchingParcels));
		if (count($matchingParcels) == 1) {
			//echo $case->address." ".implode(",", array_pop(array_reverse($matchingParcels))).PHP_EOL;
		} else if (count($matchingParcels) > 1) {
			/*
			echo $case->address.PHP_EOL;
			foreach ($matchingParcels as $match) {
				echo "     ".implode(", ", $match).PHP_EOL;
			}
		} else if (count($matchingParcels) == 0) {
			//echo $case->address.PHP_EOL;
		}
		*/
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
