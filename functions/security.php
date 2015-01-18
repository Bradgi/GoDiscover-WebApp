<?php
	function escape($string) {
		return htmlentities(trim($string), ENT_QUOTES, 'ISO-8859-1');
	}