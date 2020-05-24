<?php
require_once 'vendor/autoload.php';

spl_autoload_register(
	function ($class) {
		$file = str_replace("\\", "/", $class . '.php');

		if (file_exists(PATH_ROOT . $file)) {
			include_once(PATH_ROOT . $file);
		}
	}
);