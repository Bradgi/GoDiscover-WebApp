<?php
	$s3 = new S3('AccessKey', 'SecretKey');

	function createTrackXML($trackName, $trackDescription, $trackFile, $mapFile, $spotsXY, $spotsContent, $spotsLatLong, $spotsName, $spotsInformation, $xmlFileName) {
		$doc = new DOMDocument();
		$doc->formatOutput = true;

		$trackElement = $doc->createElement("track");
		$doc->appendChild($trackElement);

		$versionElement = $doc->createElement("version");
		$versionElement->appendChild($doc->createTextNode("1"));
		$trackElement->appendChild($versionElement);

		$nameElement = $doc->createElement("name");
		$nameElement->appendChild($doc->createTextNode($trackName));
		$trackElement->appendChild($nameElement);

		$picElement = $doc->createElement("picPath");
		$picElement->appendChild($doc->createTextNode(str_replace('./godiscover_tmp', '', $trackFile)));
		$trackElement->appendChild($picElement);

		$mapElement = $doc->createElement("mapPath");
		$mapElement->appendChild($doc->createTextNode(str_replace('./godiscover_tmp', '', $mapFile)));
		$trackElement->appendChild($mapElement);

		$tdescriptionElement = $doc->createElement("description");
		$tdescriptionElement->appendChild($doc->createTextNode($trackDescription));
		$trackElement->appendChild($tdescriptionElement);

		$spotsElement = $doc->createElement("spots");
		$trackElement->appendChild($spotsElement);

		for ($i = 0; $i < sizeof($spotsName); $i++) {
			$spotElement = $doc->createElement("spot");

			$spotNameElement = $doc->createElement("name");
			$spotNameElement->appendChild($doc->createTextNode($spotsName[$i]));
			$spotElement->appendChild($spotNameElement);

			$spotInfoElement = $doc->createElement("info");
			$spotInfoElement->appendChild($doc->createTextNode($spotsInformation[$i]));
			$spotElement->appendChild($spotInfoElement);

			$spotXElement = $doc->createElement("x");
			$spotYElement = $doc->createElement("y");
			$spotXElement->appendChild($doc->createTextNode($spotsXY[$i][0]));
			$spotYElement->appendChild($doc->createTextNode($spotsXY[$i][1]));
			$spotElement->appendChild($spotXElement);
			$spotElement->appendChild($spotYElement);

			$spotLatElement = $doc->createElement("lat");
			$spotLongElement = $doc->createElement("long");
			$spotLatElement->appendChild($doc->createTextNode($spotsLatLong[$i][0]));
			$spotLongElement->appendChild($doc->createTextNode($spotsLatLong[$i][1]));
			$spotElement->appendChild($spotLatElement);
			$spotElement->appendChild($spotLongElement);

			$contentElement = $doc->createElement("content");

			for ($j = 0; $j < sizeof($spotsContent[$i]); $j += 4) {
				$resourceElement = $doc->createElement("res");

				$typeElement = $doc->createElement("type");
				$typeElement->appendChild($doc->createTextNode($spotsContent[$i][$j+3]));
				$resourceElement->appendChild($typeElement);

				$contentNameElement = $doc->createElement("name");
				$contentNameElement->appendChild($doc->createTextNode($spotsContent[$i][$j+1]));
				$resourceElement->appendChild($contentNameElement);

				$storyElement = $doc->createElement("story");
				$storyElement->appendChild($doc->createTextNode($spotsContent[$i][$j+2]));
				$resourceElement->appendChild($storyElement);

				$contentPathElement = $doc->createElement("path");
				$contentPathElement->appendChild($doc->createTextNode(str_replace('./godiscover_tmp', '', $spotsContent[$i][$j])));
				$resourceElement->appendChild($contentPathElement);

				$contentElement->appendChild($resourceElement);
			}

			$spotElement->appendChild($contentElement);

			$spotsElement->appendChild($spotElement);
		}

		$doc->save($xmlFileName);
	}

	function createIndexXML($zipName) {
		$doc = new DOMDocument();

		if (S3::getObject('BucketName', 'index.xml')) {
			S3::getObject('BucketName', 'index.xml', './zips/index.xml');

			$doc->load('./zips/index.xml');

			$doc->formatOutput = true;

			$indexElement = $doc->getElementsByTagName('index')->item(0);
			
			$fileElement = $doc->createElement('file');

			$fileNameElement = $doc->createElement("fileName");
			$fileNameElement->appendChild($doc->createTextNode($zipName));
			$fileElement->appendChild($fileNameElement);

			$fileVersion = $doc->createElement("version");
			$fileVersion->appendChild($doc->createTextNode("1"));
			$fileElement->appendChild($fileVersion);

			$indexElement->appendChild($fileElement);

			$doc->appendChild($indexElement);

			$doc->save('./zips/index.xml');
		} else {
			$doc->formatOutput = true;

			$indexElement = $doc->createElement("index");
			$doc->appendChild($indexElement);

			$fileElement = $doc->createElement("file");

			$fileNameElement = $doc->createElement("fileName");
			$fileNameElement->appendChild($doc->createTextNode($zipName));
			$fileElement->appendChild($fileNameElement);

			$fileVersion = $doc->createElement("version");
			$fileVersion->appendChild($doc->createTextNode("1"));
			$fileElement->appendChild($fileVersion);

			$indexElement->appendChild($fileElement);

			$doc->save('./zips/index.xml');
		}
	}

	function updateIndexXML($trackName, $version) {
		//TODO: Update a XML with a new version of a track.
	}

	function parseXML($xmlType) {
		//TODO: Parse the info form a XML to display it on the browser.
	}