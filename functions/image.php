<?php

	define('RESULT_IMAGE_MAX_WIDTH', 800);
	define('RESULT_IMAGE_MAX_HEIGHT', 600);

	function create_resized_image($source, $result) {
		list($source_width, $source_height, $source_type) = getimagesize($source);
		switch ($source_type) {
			case IMAGETYPE_JPEG:
				$source_gd = imagecreatefromjpeg($source);
				break;
			case IMAGETYPE_PNG:
				$source_gd = imagecreatefrompng($source);
				break;
			case IMAGETYPE_BMP:
				$source_gd = imagecreatefromwbmp($source);
				break;
		}
		if ($source_gd === false) {
			return false;
		}
		$source_aspect_ratio = $source_width / $source_height;
		$result_aspect_ratio = RESULT_IMAGE_MAX_WIDTH / RESULT_IMAGE_MAX_HEIGHT;
		if ($source_width <= RESULT_IMAGE_MAX_WIDTH && $source_height <= RESULT_IMAGE_MAX_HEIGHT) {
			$result_width = $source_width;
			$result_height = $source_height;
		} elseif ($result_aspect_ratio > $source_aspect_ratio) {
			$result_width = (int) (RESULT_IMAGE_MAX_HEIGHT * $source_aspect_ratio);
			$result_height = RESULT_IMAGE_MAX_HEIGHT;
		} else {
			$result_width = RESULT_IMAGE_MAX_WIDTH;
			$result_height = (int) (RESULT_IMAGE_MAX_WIDTH / $source_aspect_ratio);
		}
		$result_gd = imagecreatetruecolor($result_width, $result_height);
		imagecopyresampled($result_gd, $source_gd, 0, 0, 0, 0, $result_width, $result_height, $source_width, $source_height);
		imagejpeg($result_gd, $result, 90);
		imagedestroy($source_gd);
		imagedestroy($result_gd);
		return true;
	}

	function process_image_upload($temp_image_path, $temp_image_name, $uploaded_path, $resized_path) {
		$uploaded_path = $uploaded_path.'/';
		$resized_path = $resized_path.'/';
    	list(, , $temp_image_type) = getimagesize($temp_image_path);
    	if ($temp_image_type === NULL) {
        	return false;
    	}
    	switch ($temp_image_type) {
        	case IMAGETYPE_BMP:
            	break;
        	case IMAGETYPE_JPEG:
            	break;
        	case IMAGETYPE_PNG:
            	break;
        	default:
            	return false;
    	}
    	$uploaded_image_path = $uploaded_path . $temp_image_name;
    	move_uploaded_file($temp_image_path, $uploaded_image_path);
    	$thumbnail_image_path = $resized_path . preg_replace('{\\.[^\\.]+$}', '.jpg', $temp_image_name);
    	$result = create_resized_image($uploaded_image_path, $thumbnail_image_path);
    	unlink($uploaded_image_path);
    	return $result ? array($uploaded_image_path, $thumbnail_image_path) : false;
}