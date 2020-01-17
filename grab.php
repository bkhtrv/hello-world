<?
	 require $_SERVER['DOCUMENT_ROOT'].'/mysql.php';
	 
	 // определяем тип юзера; john = обычный пользователь, admin = администратор
	 if (isset($_COOKIE['sid']) && $_COOKIE['sid'] == '66282323-1404-F24E-253A-9ADB17459A28') {
	 
	     $user['class'] = 'admin';
		 error_reporting(E_ALL);
	 
	 } else {
		 
		 $user['class'] = 'john';
		 error_reporting(0);
	 }

     if (preg_match('~-ru$~', $_GET['game']) == 1) {
		 
		 $postfix = '-ru';
		 $_GET['game'] = str_replace('-ru', '', $_GET['game']);
	 
	 } else $postfix = '';
	 
	 $game = mysqli_fetch_assoc(mysqli_query($db['casual'], "SELECT SQL_NO_CACHE g.url, g.remote_file, g.updated FROM `game` AS g USE INDEX (url_remote_file_added) WHERE g.`url` = '$_GET[game]' AND DATEDIFF(NOW(), g.updated) < 7"));
     
	 $isProxyUS = false;
	 
     if (isset($game['url'])) {
		 
		 // если ранее этот файл возвращал 404, то возвращаем 404
		 if (mysqli_num_rows(mysqli_query($db['casual'], "SELECT SQL_NO_CACHE * FROM `grab` WHERE `file_path` = '".'/c/f/g/'.$game['url'].$postfix.'/'.$_GET['file']."'")) > 0) {
			 
			 header("HTTP/1.0 404 Not Found");
			 die();
		 };

	     stream_context_set_default(array('http' => array('timeout' => '4', 'follow_location' => '1', 'request_fulluri' => true)));
		 
		 $file_to_download = implode(explode('/', $game['remote_file'], -1), '/').'/'.implode('/', array_map('rawurlencode', explode('/', $_GET['file'])));

        // возвращаем 404, если файл представляет собой php-скрипт
		// с текущей конфигурацией NGINX сервер и без этого отдаст 404
		if (preg_match('~\.php$~', parse_url($file_to_download, PHP_URL_PATH)) == 1) {
			
			header("HTTP/1.0 404 Not Found");
			die();
		}

		 $file_headers = @get_headers($file_to_download, 1);
		 
		 if (strpos($file_headers[0], '301') == TRUE) {

			 if (parse_url($file_headers['Location'], PHP_URL_PATH) == parse_url($file_to_download, PHP_URL_PATH)) {

			 	 $file_to_download = $file_headers['Location'];
			 	 $file_headers = @get_headers($file_to_download, 1);
			 
			 } else {

			     $proxy = explode('@', implode(mysqli_fetch_row(mysqli_query($db['casual'], "SELECT `value` FROM `vars` WHERE `name` = 'us_proxy'"))));
				 
				 stream_context_set_default(array(
			     	 	 'http' => array(
			     	 	     'proxy' => $proxy[1],
							 'header' => 'Proxy-Authorization: Basic '.base64_encode($proxy[0]),
			     	 	 ))
			     );
                 
				 $isProxyUS = true;
				 $file_headers = @get_headers($file_to_download, 1);
			 }
		 }

		 if (strpos($file_headers[0], '200') == FALSE)
		 	 if (strpos($file_headers[0], '302') == FALSE) {
		         $file_to_download = parse_url($game['remote_file'], PHP_URL_SCHEME).'://'.parse_url($game['remote_file'], PHP_URL_HOST).'/'.implode('/', array_map('rawurlencode', explode('/', $_GET['file'])));
				 $file_headers = @get_headers($file_to_download, 1);
			 }
			 
		 if (strpos($file_headers[0], '404') == TRUE) {
			 
			 header("HTTP/1.0 404 Not Found");
			 mysqli_query($db['casual'], "INSERT INTO `grab`(file_path, created_at) VALUES('".'/c/f/g/'.$game['url'].$postfix.'/'.$_GET['file']."', NOW())");
		 
		 } else {
			 
			 $proxy = explode('@', implode(mysqli_fetch_row(mysqli_query($db['casual'], "SELECT `value` FROM `vars` WHERE `name` = 'us_proxy'"))));
			 
			 stream_context_set_default(array('http' => array('timeout' => '5', 'proxy' => ($isProxyUS ? $proxy[1] : ''), 'header' => ($isProxyUS ? 'Proxy-Authorization: Basic '.base64_encode($proxy[0]) : ''))));

	        @mkdir($_SERVER['DOCUMENT_ROOT'].'/c/f/g/'.$game['url'].$postfix.'/'.implode(explode('/', $_GET['file'], -1), '/'), 0777, true);
	        @chmod($_SERVER['DOCUMENT_ROOT'].'/c/f/g/'.$game['url'].$postfix.'/'.implode(explode('/', $_GET['file'], -1), '/'), 0777);

			if (!copy($file_to_download, $_SERVER['DOCUMENT_ROOT'].'/c/f/g/'.$game['url'].$postfix.'/'.$_GET['file'])) {

				header("HTTP/1.0 404 Not Found");
				mysqli_query($db['casual'], "INSERT INTO `grab`(file_path, created_at) VALUES('".'/c/f/g/'.$game['url'].$postfix.'/'.$_GET['file']."', NOW())");
			 
			} else {

				//file_put_contents('grab.log', date('Y-m-d H:i:s', time()).' || '.$_SERVER['DOCUMENT_ROOT'].'/c/f/g/'.$game['url'].$postfix.'/'.$_GET['file'].PHP_EOL, FILE_APPEND);
				header('Content-type: '.mime_content_type($_SERVER['DOCUMENT_ROOT'].'/c/f/g/'.$game['url'].$postfix.'/'.$_GET['file']));
				echo file_get_contents($_SERVER['DOCUMENT_ROOT'].'/c/f/g/'.$game['url'].$postfix.'/'.$_GET['file']);
				chmod($_SERVER['DOCUMENT_ROOT'].'/c/f/g/'.$game['url'].$postfix.'/'.$_GET['file'], 0777);
			}
		}
	 
	} else header("HTTP/1.0 404 Not Found");
?>