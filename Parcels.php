<?php

class Parcels {
	static public function load($filename) {
		$row = 0;
		$slim = [];
		if (($handle = fopen($filename, "r")) !== FALSE) {
				while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
					$row++;
					if($row == 0) continue;
					/*
					array_push($slim, [
						strtolower($data[0]), // Parcel
						preg_replace('/([0-9]+)./', '$1', $data[3]), // St Num (add *)
						strtolower($data[4]), // St Name
						strtolower($data[5]), // St Suf
						strtolower($data[7])  // Zip
					]);
					*/
					$key = implode(" ", [
						preg_replace('/([0-9]+).*/', '$1', $data[3]),
						strtolower($data[4]), // St Name
						strtolower($data[5]) // St Suf
					]);
					$slim[$key] = $data[0];
				}
				fclose($handle);
		}
		return $slim;
	}
}


