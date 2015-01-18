<?php
	function createZip($source, $destination, $uri) {
		if (!extension_loaded('zip') || !file_exists($source)) {
			die;
		}

		$zip = new ZipArchive();
		if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
			die;
		}

		if (is_dir($source) === true) {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

			foreach ($files as $file) {
				if (in_array(substr($file, strrpos($file, '/')+1), array('.', '..'))) {
					continue;
				}
				if (is_dir($file) === true) {
					$zip->addEmptyDir(str_replace($source.'/','',$file.'/'));
				} else if (is_file($file) === true) {
					$zip->addFromString(str_replace($source.'/','',$file), file_get_contents($file));
				}
			}
		} else if (is_file($source) === true) {
			$zip->addFromString(basename($source), file_get_contents($source));
		}
		$zip->close();
	}