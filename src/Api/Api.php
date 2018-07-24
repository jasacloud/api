<?PHP
	@session_start();
	class Api extends Rest {
	
		protected static $dbobj = NULL;
		
		protected $data_response = [];
		protected $request_agent = [
			10011 => 'Browser',  
			10012 => 'AndroidBrowser',  
			10033 => 'AndroidApp',
			10044 => 'DesktopProgam',  
			10055 => 'Other' 
		];
		
		public function __construct(){
			parent::__construct(); // Init parent contructor
		}

		/*
			*  Database connection 
		*/
		public function dbConnect($connname=NULL){
			static::$dbobj = new DBManager();
			if($connname!=NULL){
				static::$dbobj->setConnectionName($connname);
			}
			else{
				static::$dbobj->setConnectionName('default');
			}
			return static::$dbobj;
		}
		
		public function mcConnect(){
			$mc = new MCManager();
			
			return $mc;
		}

		/*
			* Public method for access api.
			* This method dynmically call the method based on the query string
			*
		*/
		public function processApi(){
			$class = $this->getGroupFunction();
			if(class_exists($class)){
				$object = new $class($this->_data_input);
				$method = $this->getVerbFunction();
				
				if(method_exists($object,$method)){
					$data = $object->$method();
					$this->addResponse('kind',$this->getKind());
					if(isset($this->_data_input->cseq)){
						$this->addResponse('cseq',$this->_data_input->cseq);
					}
					$this->addResponse('object',$data);
					//$this->addChecksumResponse();
					
					//add log :
					$this->logTR();
					
					echo $this->response($this->json($this->data_response), 200);
				}
				else{
					$return = array("return"=>"error","returnmessage"=>"Method not found!");
					
					return $this->response($this->json($return),404);
				}
			}
			else if(!empty($this->_data_post) && class_exists($this->getGroupFunction($this->_data_post->kind))){
				$class = $this->getGroupFunction($this->_data_post->kind);
				$object = new $class($this->_data_post);
				$method = $this->getVerbFunction($this->_data_post->kind);
				
				if(method_exists($object,$method)){
					$data = $object->$method();
					$this->addResponse('kind',$method.'#'.$class);
					if(isset($this->_data_post->cseq)){
						$this->addResponse('cseq',$this->_data_post->cseq);
					}
					$this->addResponse('object',$data);
					//$this->addChecksumResponse();
					
					//add log :
					$this->logTR();
					
					echo $this->response($this->json($this->data_response), 200);
				}
				else{
					$return = array("return"=>"error","returnmessage"=>"Method not found!");
					
					return $this->response($this->json($return),404);
				}
			}
			
			else if(!empty($this->_data_get) && class_exists($this->getGroupFunction($this->_data_get->kind))){
				$class = $this->getGroupFunction($this->_data_get->kind);
				$object = new $class($this->_data_get);
				$method = $this->getVerbFunction($this->_data_get->kind);
				
				if(method_exists($object,$method)){ 
					$data = $object->$method();
					$this->addResponse('kind',$method.'#'.$class);
					
					if(isset($this->_data_get->cseq)){
						$this->addResponse('cseq',$this->_data_get->cseq);
					}
					
					$this->addResponse('object',$data);
					//$this->addChecksumResponse();
					
					//add log :
					$this->logTR();
					
					echo $this->response($this->json($this->data_response), 200);
				}
				else{
					$return = array("return"=>"error","returnmessage"=>"Method not found!");
					
					return $this->response($this->json($return),404);
				}
			}
			else{
				// CREATE LOGGER FOR ERROR HANDLE :
				$this->create_log_Server();
				$return = array(
					'return'=>'E0001',
					'returnmessage'=>'You not have authorized to access this page directly, Please read the API documentation!'
				);
				$this->response($this->json($return),404); // If the method not exist with in this class, response would be 'Page not found'.
			}
		}
		
		// get the Group/Class of kind, example 'newticket#hiq'  return value 'hiq'
		public function getGroupFunction($kind=NULL){
			if(isset($this->_data_input->kind)&&$kind==NULL){
				$groupFunction = explode('#',$this->_data_input->kind);
				
				return ucfirst($groupFunction[1]);
			}
			else if(!isset($this->_data_input->kind)&&$kind!=NULL){
				$groupFunction = explode('#',$kind);
				
				return ucfirst($groupFunction[1]);
			}
			else{
				return 'input class not valid!';
			}
		}
		
		// get the Group/Class of kind, example 'newticket#hiq'  return value 'newticket'
		public function getVerbFunction($kind=NULL){
			if(isset($this->_data_input->kind) && $kind==NULL){
				$verbFunction = explode('#',$this->_data_input->kind);

				return $verbFunction[0];
			}
			else if(!isset($this->_data_input->kind) && $kind!=NULL){
				$verbFunction = explode('#',$kind);

				return $verbFunction[0];
			}
			else{
				return 'input method not valid!';
			}
		}
		
		public function getKind(){
			return $this->getVerbFunction().'#'.$this->getGroupFunction();
		}
		
		// convert array to json :
		private function json($data){
			if(is_array($data)){
				//return json_encode($data, JSON_PRETTY_PRINT);
				if(isset($this->_data_input->callback) && !empty($this->_data_input->callback)){
					$this->_content_type = "application/javascript";
					return $this->_data_input->callback . '('.json_encode($data).')';
				}
				return json_encode($data);
			}
			else{
				new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_MERGE.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  '.' #Api::json()#ERROR DATA IS NON_ARRAY:' . print_r($data,true).'#END ERROR');
				return null;
			}
		}
		
		private function addResponse($index,$data){
			if(isset($index) && $index == 'object'){
				if(is_array($data)){
					$this->data_response=array_merge($this->data_response,$data);
				}
				else {
					new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_MERGE.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  '.' #Api::addResponse()#ERROR MERGER_ARRAY NON_ARRAY ON  : ' . print_r($data,true).'#END ERROR');
					$this->data_response=array_merge($this->data_response,array('logout'=>1,'message'=>'The response is retristic from server.','data'=>$data));
				}
			}
			else{
				$this->data_response=array_merge($this->data_response,array($index=>$data));
			}
		}
		
		/* ***** SATRT LOGGER METHOD ***** */
		private function create_log_Server(){
			new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_SERVER.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  #Api::processApi()#SERVER_LOG START:');
			new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_SERVER.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  #Api::processApi()#SERVER_LOG \$GROUPFUNCTION/CLASS :'.$this->getGroupFunction());
			new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_SERVER.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  #Api::processApi()#SERVER_LOG \$VERBFUNCTION/METHOD :'.$this->getVerbFunction());
			new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_SERVER.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  #Api::processApi()#SERVER_LOG \$_SERVER :'.print_r($_SERVER,true));
			new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_SERVER.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  #Api::processApi()#SERVER_LOG \$_POST :'.print_r($_POST,true));
			new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_SERVER.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  #Api::processApi()#SERVER_LOG \$_GET :'.print_r($_GET,true));
			new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_SERVER.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  #Api::processApi()#SERVER_LOG php://input :'.print_r($this->getInput(),true));
			new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_SERVER.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  #Api::processApi()#SERVER_LOG \$_COOKIE :'.print_r($_COOKIE,true));
			new Logger($_SERVER['DOCUMENT_ROOT'].'/log/ERROR_SERVER.log', $_SERVER['PHP_SELF'].':'.__LINE__.'  #Api::processApi()#SERVER_LOG END');
		}
		
		protected function logTR(){
			if(isset($_REQUEST['XDEBUG_SESSION_START']) && $_REQUEST['XDEBUG_SESSION_START']=='xdebug'){
				new Logger($_SERVER['DOCUMENT_ROOT'].'/log/API_REQUEST_RESPONSE.log', '[REQ]:'. json_encode($this->_data_input).'#END_REQ');
				new Logger($_SERVER['DOCUMENT_ROOT'].'/log/API_REQUEST_RESPONSE.log', '[RES]:'. json_encode($this->data_response).'#END_RES');
			}
			else{
				//do nothing...
			}
		}
		/****** END LOGGER METHOD ******/
		
		private function addChecksumResponse(){
			$str=  '';
			$value_response = new RecursiveIteratorIterator(new RecursiveArrayIterator($this->data_response)); 
			foreach($value_response as $value){
				$str = $str.$value;
			}
			
			$ck = $this->getChecksumFromString($str,4);
			$this->addResponse('ck',$ck);
		}
		
		private function validateChecksumRequest(){
			//required from client ! Algorithm
		}
		
		// ISO 7064 Mod 97,10
		// @return concat number + checksum -- to check (return%$mod==1)
		public function addChecksumNum($num, $mod){
			$ck = (($mod+1)-(($numb*100)%$mod))%$mod;
			return $num.$ck;
		}
		
		function getChecksumFromString($str=NULL,$len=NULL){
			$string = ($str!=NULL) ? $str.'' : '';
			$length = ($len!=NULL) ? $len : 4;
			$checksum 		= 0;
			$checksum_temp 	= 0;
			for($i=0; $i<strlen($string);$i++){
				$checksum = $checksum + (ord($string[$i]) * 13);
			}
			$checksum = $checksum.'';
			while(strlen($checksum)>$length){
				for($i=0;$i<strlen($checksum);$i++){
					$checksum_temp = $checksum_temp + $checksum[$i];
				}
				$checksum = $checksum_temp.'';
				$checksum_temp = 0;
			}
			
			return $checksum;
		}
		
		protected function addChecksumCookie(){
			$old_ck = isset($_COOKIE['ck']) ? $_COOKIE['ck'] : NULL;
			if($old_ck!=NULL){
				unset($_COOKIE['ck']);
				setcookie('ck', NULL, -1, '/');
			}
			$str = '';
			$cookie_value = (array)$_COOKIE; 
			foreach($cookie_value as $key=>$value){
				$str = $str.$cookie_value[$key];
			}
			$ck = $this->getChecksumFromString($str,4);
			setCookie('ck',$ck,NULL,'/');
		}
		
		protected function validateCookieChecksum(){
			$old_ck = isset($_COOKIE['ck']) ? $_COOKIE['ck'] : NULL;
			if($old_ck!=NULL){
				unset($_COOKIE['ck']);
				setcookie('ck', NULL, -1, '/');
			}
			$str = '';
			$cookie_value = (array)$_COOKIE; 
			foreach($cookie_value as $key=>$value){
				$str = $str.$cookie_value[$key];
			}
			$ck = $this->getChecksumFromString($str,4);
			
			if($old_ck==$ck){
				$this->addChecksumCookie();
				
				return true;
			}
			else{
				$this->addChecksumCookie();
				
				return false;
			}
		}
		
		public static function getClientIp() {
			$ipaddress = '';
			if (getenv('HTTP_CLIENT_IP'))
				$ipaddress = getenv('HTTP_CLIENT_IP');
			else if(getenv('HTTP_X_FORWARDED_FOR'))
				$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
			else if(getenv('HTTP_X_FORWARDED'))
				$ipaddress = getenv('HTTP_X_FORWARDED');
			else if(getenv('HTTP_FORWARDED_FOR'))
				$ipaddress = getenv('HTTP_FORWARDED_FOR');
			else if(getenv('HTTP_FORWARDED'))
			   $ipaddress = getenv('HTTP_FORWARDED');
			else if(getenv('REMOTE_ADDR'))
				$ipaddress = getenv('REMOTE_ADDR');
			else
				$ipaddress = 'UNKNOWN';
			return $ipaddress;
		}
		
		public static function getClientBrowser() {
			return $_SERVER['HTTP_USER_AGENT'];
		}
		
		public static function redirect($location){
			$location = trim(preg_replace('/\s\s+/', '', $location));
			header("Location: ".$location);
			exit;
		}
		
		public function __destruct(){
			if($this->dbobj != NULL){
				$this->dbobj->close();
				$this->dbobj = null;
			}
		}
	}
?>