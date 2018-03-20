<?PHP
	namespace Api;
	/* File : Rest.php
		* Author : GST
	*/
	class Rest {
		
		protected $_allow = array();
		protected $_content_type = "application/json";
		protected $_request = array();
		protected $_input = array();
		protected $_data = array(); // used on system.php
		protected $_data_input = array(); // used on system.php
		protected $_data_post = array(); // used on system.php
		protected $_data_get = array(); // used on system.php
		protected $_data_put = array(); // used on system.php
		protected $_data_del = array(); // used on system.php
		protected $_data_image = array(); // used on system.php

		
		protected $_method = "";		
		protected $_code = 200;
		
		private $request = array();
		
		public function __construct(){
			$this->inputs();
		}
		
		public function get_referer(){
			return $_SERVER['HTTP_REFERER'];
		}
		
		public function response($data,$status=NULL){
			$this->_code = ($status)? $status : 200;
			$this->set_headers();
			echo $data;
			exit;
		}
		
		private function get_status_message(){
			$status = array(
				100 => 'Continue',  
				101 => 'Switching Protocols',  
				200 => 'OK',
				201 => 'Created',  
				202 => 'Accepted',  
				203 => 'Non-Authoritative Information',  
				204 => 'No Content',  
				205 => 'Reset Content',  
				206 => 'Partial Content',  
				300 => 'Multiple Choices',  
				301 => 'Moved Permanently',  
				302 => 'Found',  
				303 => 'See Other',  
				304 => 'Not Modified',  
				305 => 'Use Proxy',  
				306 => '(Unused)',  
				307 => 'Temporary Redirect',  
				400 => 'Bad Request',  
				401 => 'Unauthorized',  
				402 => 'Payment Required',  
				403 => 'Forbidden',  
				404 => 'Not Found',  
				405 => 'Method Not Allowed',  
				406 => 'Not Acceptable',  
				407 => 'Proxy Authentication Required',  
				408 => 'Request Timeout',  
				409 => 'Conflict',  
				410 => 'Gone',  
				411 => 'Length Required',  
				412 => 'Precondition Failed',  
				413 => 'Request Entity Too Large',  
				414 => 'Request-URI Too Long',  
				415 => 'Unsupported Media Type',  
				416 => 'Requested Range Not Satisfiable',  
				417 => 'Expectation Failed',  
				500 => 'Internal Server Error',  
				501 => 'Not Implemented',  
				502 => 'Bad Gateway',  
				503 => 'Service Unavailable',  
				504 => 'Gateway Timeout',  
				505 => 'HTTP Version Not Supported'
			);
			return ($status[$this->_code]) ? $status[$this->_code] : $status[500];
		}
		
		public function get_request_method(){
			return $_SERVER['REQUEST_METHOD'];
		}
		
		public function getInputMethod($files,$objname){
			switch($objname){
				case "JSON" :
					if(!empty($files[$objname]['tmp_name']) 
						 && file_exists($files[$objname]['tmp_name'])) {
						$json_parse = file_get_contents($files[$objname]['tmp_name']);
						return $json_parse;
					}
					else{
						return NULL;
					}
					
				break;
				case "FILE" :
					if(!empty($files[$objname]['tmp_name']) 
						 && file_exists($files[$objname]['tmp_name'])) {
						$json_parse = file_get_contents($files[$objname]['tmp_name']);
						return $json_parse;
					}
					else{
						return NULL;
					}
				break;
				default:
					return NULL;
				break;
			}
		}
		
		public function getInput(){
			switch($this->getContentType()){
				case "multipart/form-data" :
					$this->_data_post = $this->getInputMethod($_FILES,"JSON");
					$this->_data_image = $this->getInputMethod($_FILES,"FILE");
					return $this->_data_post;
				break;
				
				case "multipart/mixed" :
					$this->_data_post->kind = "upload2#files";
				break;
				default :
					$this->_input = file_get_contents("php://input");
					return $this->_input;
				break;
			}
		}
		
		public function getContentType(){
			
			$contenttypeset = isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : $_SERVER['CONTENT_TYPE'];
			if($contenttypeset && !empty($contenttypeset)){
				$this->request['HTTP_CONTENT_TYPE'] = explode(';',$contenttypeset)[0];
				
				return $this->request['HTTP_CONTENT_TYPE'];
			}
			else{
			
				return FALSE;
			}
		}
		
		private function inputs(){
			switch($this->get_request_method()){
				case "POST":
					$this->_data_post = (object)$this->cleanInputs($_POST);
					$this->_data_input = json_decode($this->getInput());
					if($this->_data_input){
						$this->_data_input->kind = isset($_GET['kind']) ? $_GET['kind'] : $this->_data_input->kind;
					}
				break;
				case "GET":
					$this->_data_get= (object)$this->cleanInputs($_GET);
					$this->_data_input = json_decode($this->getInput());
				break;
				case "DELETE":
					$this->_data_del = $this->cleanInputs($_GET);
					$this->_data_input = json_decode($this->getInput());
				break;
				case "PUT":
					parse_str(file_get_contents("php://input"),$this->_request);
					$this->_data_put = $this->cleanInputs($this->_request);
					$this->_data_input = json_decode($this->getInput());
				break;
				default:
					$this->response('',406);
				break;
			}
		}
		private function inputs_orig(){
			switch($this->get_request_method()){
				case "POST":
					$this->_request = $this->cleanInputs($_POST);
				break;
				case "GET":
				break;
				case "DELETE":
					$this->_request = $this->cleanInputs($_GET);
				break;
				case "PUT":
					parse_str(file_get_contents("php://input"),$this->_request);
					$this->_request = $this->cleanInputs($this->_request);
				break;
				default:
					$this->response('',406);
				break;
			}
		}
		
		private function cleanInputs($data){
			$clean_input = array();
			if(is_array($data)){
				foreach($data as $k => $v){
					$clean_input[$k] = $this->cleanInputs($v);
				}
			}
			else{
				if(get_magic_quotes_gpc()){
					$data = trim(stripslashes($data));
				}
				$data = strip_tags($data);
				$clean_input = trim($data);
			}
			return $clean_input;
		}		
		
		private function set_headers(){
			header("HTTP/1.1 ".$this->_code." ".$this->get_status_message());
			header("Content-Type: ".$this->_content_type);
			header("Access-Control-Allow-Origin: *");
			//header("Authorization: Basic XYZ");
			
		}
		
		
		
		/* CONTENT_TYPE PARSE */
		private function httpParseMultipart($stream=NULL, $boundary=NULL, array &$variables=NULL, array &$files=NULL) {
			if($stream == null) {
				$stream = fopen('php://input');
			}
			
			$partInfo = null;
			
			$lineN = fgets($stream);
			while(($lineN = fgets($stream)) !== false) {
				if(strpos($lineN, '--') === 0) {
					if(!isset($boundary)) {
						$boundary = rtrim($lineN);
					}
					continue;
				}
				
				$line = rtrim($lineN);
				
				if($line == '') {
					if(!empty($partInfo['Content-Disposition']['filename'])) {
						$this->httpParseMultipartFile($stream, $boundary, $partInfo, $files);
						} else {
						$this->httpParseMultipartVariable($stream, $boundary, $partInfo['Content-Disposition']['name'], $variables);
					}
					$partInfo = null;
					continue;
				}
				
				$delim = strpos($line, ':');
				
				$headerKey = substr($line, 0, $delim);
				$headerVal = ltrim(substr($line, $delim + 1));
				$partInfo[$headerKey] = $this->HttpParseHeaderValue($headerVal);
			}
			fclose($stream);
		}
		
		private function httpParseMultipartVariable($stream, $boundary, $name, &$array) {
			$fullValue = '';
			$lastLine = null;
			while(($lineN = fgets($stream)) !== false && strpos($lineN, $boundary) !== 0) {
				if($lastLine != null) {
					$fullValue .= $lastLine;
				}
				$lastLine = $lineN;
			}
			
			if($lastLine != null) {
				$fullValue .= rtrim($lastLine, "\r\n");
			}
			
			$array[$name] = $fullValue;
		}
		
		private function httpParseMultipartFile($stream, $boundary, $info, &$array) {
			$tempdir = sys_get_temp_dir();
			// we should technically 'clean' name - replace '.' with _, etc
			// http://stackoverflow.com/questions/68651/get-php-to-stop-replacing-characters-in-get-or-post-arrays
			$name = $info['Content-Disposition']['name'];
			$fileStruct['name'] = $info['Content-Disposition']['filename'];
			$fileStruct['type'] = $info['Content-Type']['value'];
			
			$array[$name] = &$fileStruct;
			
			if(empty($tempdir)) {
				$fileStruct['error'] = UPLOAD_ERR_NO_TMP_DIR;
				return;
			}
			
			$tempname = tempnam($tempdir, 'php');
			$outFP = fopen($tempname, 'wb');
			
			$fileStruct['tmp_name'] = $tempname;
			if($outFP === false) {
				$fileStruct['error'] = UPLOAD_ERR_CANT_WRITE;
				return;
			}
			
			$lastLine = null;
			while(($lineN = fgets($stream, 8096)) !== false && strpos($lineN, $boundary) !== 0) {
				if($lastLine != null) {
					if(fwrite($outFP, $lastLine) === false) {
						$fileStruct['error'] = UPLOAD_ERR_CANT_WRITE;
						return;
					}
				}
				$lastLine = $lineN;
			}
			
			if($lastLine != null) {
				if(fwrite($outFP, rtrim($lastLine, "\r\n")) === false) {
					$fileStruct['error'] = UPLOAD_ERR_CANT_WRITE;
					return;
				}
			}
			$fileStruct['error'] = UPLOAD_ERR_OK;
			$fileStruct['size'] = filesize($tempname);
		}
		/* END CONTENT_TYPE PARSE */
		
		
	}	
?>