<?PHP
	class System extends Api{
		private $data_source;
		private $version = "1.0";
		
		private $cookiecode;
		private $cookieck;
		
		# base128  static property :
		private static $ascii128='!#$%()*,.0123456789:;=@ABCDEFGHIJKLMNOPQRSTUVWXYZ[]^_abcdefghijklmnopqrstuvwxyz{|}~¡¢£¤¥¦§¨©ª«¬®¯°±²³´µ¶·¸¹º»¼½¾¿ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎ';
		private static $ascii128custom='!#$%()*,.0123456789:;=@ABCDEFGHIJKLMNOPQRSTUVWXYZ[]^_abcdefghijklmnopqrstuvwxyz{|}~¡¢£¤¥¦§¨©ª«¬®¯°±²³´µ¶·¸¹º»¼½¾¿ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎ';
		
		# base64 static property :
		private static $asccii64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
		private static $asccii64custom  = "/ABCDEFHIJKLMNOPQRUVWXYZGSTabcdefhijkl+mnopqruvwxyzgst0234678915";

		private $arr_clientparams = array("netconfig","clientvars","voice");
		
		public function __construct($data_source=null){
			# initiate request data from client :
			$this->data_source = $data_source;
		}
		
		public function phpinfo(){
			phpinfo();
			die;
		}
		
		# version method -> set on declaration variable or get from database :
		public function version(){
			return array("version"=>$this->version);
		}
		
		# connect :
		public function connect(){
			if($this->checkSystemCode($this->data_source->cid)){
				
				setCookie('code',$this->cookiecode,null,"/");
				setCookie('hcode','1',null,"/");  //hcode (for last heartbeat code)
				
				$return = array(
					"code"=>$this->cookiecode,
					"return"=>"S0000",
					"returnval"=>"0",
				);
				
				if(is_array(error_get_last())){
					new Logger($_SERVER['DOCUMENT_ROOT']."/log/CONNECT_ERROR_LAST.log", $_SERVER['PHP_SELF'].":".__LINE__."  #System::connect():ERROR_LAST:". print_r(error_get_last(),true)."#END ERROR_LAST");
				}
				
				return $return;
			}
			else{
				return array("logout"=>"1","return"=>"E0000","message"=>"Your CID device is not valid on system!");
			}
		}
		
		# login with hiq.user table :
		public function login(){
			if(isset($this->data_source->account_source) && !empty($this->data_source->account_source)){
				switch(trim($this->data_source->account_source)){
					
					case "google_account":
					break;
					
					case "facebook_account":
					break;
					
					case "csdm_account":
						return $this->login_csdm_account();
					break;
					
					case "local_account":
					
					break;
					
					default :
						return array("logout"=>1,"return"=>"E0000","returnmessage"=>"User Source not not found on our application!");
					break;
					
				}
			}
			else{
				return $this->login_old_version();
			}
		}
		
		public function login_csdm_account(){
			
			if(!isset($this->data_source->code) || empty($this->data_source->code)){
				return array("logout"=>1,"return"=>"E0000");
			}
			
			$code_decode = $this->base64urldecode($this->data_source->code); 
			$code_object = json_decode($code_decode);
			
			$db = $this->dbConnect();
			$this->data_source->user = isset($this->data_source->user) && !empty($this->data_source->user) ? $this->data_source->user : $this->data_source->userid;
			$clause = array("FID"=>$this->data_source->user,"FPassword"=>strtoupper($this->data_source->pwd),"FIDCloudConnection"=>$code_object->_fidcc,"FLevel"=>'U');
			$data_user = $db->getSingleRow('users',$clause);
			
			if($data_user){
				$rows = $data_user[0]; // get singgel data_user array 
				
				$code_object->_fldtime = date('YmdHis');
				
				$scode_stringify = json_encode($code_object);
				$scode_return = $this->base64encode($scode_stringify);
				
				setCookie('scode',$scode_return,null,"/");
				
				setCookie('did',$code_object->_fnamecd);
				
				setCookie('userid',$rows['FID']);
				
				setCookie('userfullname',$rows['FName']);
				
				$object_return = array(
					"logout"	=> 0,
					"return"	=> "S0000",
					"user"		=> $this->data_source->user,
					"scode"		=> $scode_return ,
					"name"		=> $rows['FName'],
					"key1"		=> "34ZzLy6UCns0vgAc9RpTMGuDYfk2KxQB157XqeObm+rlJj8NS/IhowdEHFiPtaWV",
					"params"	=> []
				);
				
				if(isset($code_object->_fidcd)){  // get the subscription of Device :
					$sql1 = "SELECT 
								CDSS.FIDCloudDevice,
								CDSS.FIDCloudServiceSubscription, 
								CDSS.FPriority,
								CSS.FName AS FNameCloudServiceSubscription,
								CSS.FDescription AS FDescCloudServiceSubscription,
								CSST.FName AS FNameCSST
							FROM clouddeviceservicesubscription CDSS
							INNER JOIN cloudservicesubscription CSS
							ON CDSS.FIDCloudServiceSubscription = CSS.FID
							LEFT JOIN cloudservicesubscriptiontype CSST
							ON CSST.FID=CDSS.FIDCloudServiceSubscriptionType
							WHERE FIDCloudDevice = '".$code_object->_fidcd."' ORDER BY CDSS.FPriority DESC";
					
					// get main service from device
					$sql2 = "SELECT 
								DISTINCT
								CDSS.FIDCloudServiceSubscription, 
								CSS.FName AS FNameCloudServiceSubscription,
								CSS.FDescription AS FDescCloudServiceSubscription
							FROM clouddeviceservicesubscription CDSS
							INNER JOIN cloudservicesubscription CSS
							ON CDSS.FIDCloudServiceSubscription = CSS.FID
							LEFT JOIN cloudservicesubscriptiontype CSST
							ON CSST.FID=CDSS.FIDCloudServiceSubscriptionType
							WHERE FIDCloudDevice = '".$code_object->_fidcd."'";
					
					//digunakan penentuan subsctype saat agent generate ticket:
					$sql3 = "SELECT 
								B.FID as FIDCC,
								B.FExtID1 as FExtID1CC,
								B.FBranch as FBranchCC,
								B.FDescription as FDescCC,
								CSS.FID as FIDCSS,
								CSS.FName as FNameCSS,
								CSS.FDescription FDescCSS,
								CSST.FID as FIDCSST,
								CSST.FName as FNameCSST,
								CSST.FDescription as FDescCSST
							FROM cloudservicesubscription CSS
							INNER JOIN cloudservicesubscriptiontype CSST on  CSST.FIDCloudServiceSubscription = CSS.FID
							INNER JOIN branch B on CSS.FIDCloudConnection = B.FID
							WHERE CSS.FIDCloudConnection = '".$code_object->_fidcc."'";
					
					$data_device_service 	= $db->execute($sql1);
					$data_device_main_subsc = $db->execute($sql2);
					$data_all_subsc 		= $db->execute($sql3);
					
					$subsc 		= array();
					$mainsubsc 	= array();
					$allsubsc 	= array();
					
					if(is_array($data_device_service)){
						foreach($data_device_service as $device_service){
							array_push($subsc , array(
								'group'		=> $device_service['FNameCloudServiceSubscription'],
								'grouplable'=> $device_service['FDescCloudServiceSubscription'],
								'subsctype'	=> empty($device_service['FNameCSST'])?'':$device_service['FNameCSST'],
								'priority'	=> $device_service['FPriority']
							));
						}
						$this->setSubsc($subsc);
						$object_return['params']['subsc']=$subsc;
					}
					
					if(is_array($data_device_main_subsc)){
						foreach($data_device_main_subsc as $device_main_subsc){
							array_push($mainsubsc , array(
								'group'		=> $device_main_subsc['FNameCloudServiceSubscription'],
								'grouplable'=> $device_main_subsc['FDescCloudServiceSubscription']
							));
						}
						$this->setMainSubsc($mainsubsc);
						$object_return['params']['mainsubsc']=$mainsubsc;
					}
					
					// session or cookie 'allsubsc' is for transfer on hiqclient:
					if(is_array($data_all_subsc)){
						foreach($data_all_subsc as $row_all_subsc){
							array_push($allsubsc , array(
								'subsc'		=> $row_all_subsc['FNameCSS'],
								'subsctype'	=> $row_all_subsc['FNameCSST']
							));
						}
						$this->setAllSubsc($allsubsc);
						$object_return['params']['allsubsc']=$allsubsc;
					}
				}
				return $object_return;
			}
			else{
				return array("logout"=>1,"return"=>"E0000");
			}
		}
		public function login_old_version(){
			if(isset($this->data_source->code)&&!is_array($this->data_source->code)&&!empty($this->data_source->code)){
				$client_decode = json_decode($this->base64urldecode($this->data_source->code));
				if(!isset($client_decode->_fidcc)){
					$client_decode = explode("#",$this->base64decode($this->data_source->code)); // to get the FIDCloudConnection, example: 'TSELSMGI'
				}
			}
			else{
				$client_decode = explode("#","undefined#undefined#undefined#undefined"); // to get the FIDCloudConnection, example: 'TSELSMGI'
			}
			
			$db = $this->dbConnect();
			
			$this->data_source->user = isset($this->data_source->user) && !empty($this->data_source->user) ? $this->data_source->user : $this->data_source->userid;
			$clause = [
				"FID"=>$this->data_source->user,
				"FPassword"=>strtoupper($this->data_source->pwd),
				"FIDCloudConnection"=>isset($client_decode->_fidcc) ? $client_decode->_fidcc : $client_decode[1] //if new serversetting
			];
			$data_user = $db->getSingleRow('users',$clause,NULL);
			
			if(count($data_user)>0){
				$rows = $data_user[0]; // get singgel data_user array 
				
				$client_decode->_fldtime = date('YmdHis');
				$scode_stringify = json_encode($client_decode);
				$scode_return = $this->base64encode($scode_stringify);
				
				// when not new version login : 
				if(!isset($client_decode->_fidcc)){
					$scode_return = $this->base64encode($this->base64encode($this->data_source->code)); //multiple or double  base64encode 
				}
				
				setCookie('scode',$scode_return,null,"/");
				
				$did = isset($client_decode->_fnamecd) ? $client_decode->_fnamecd : $client_decode[0];
				setCookie('did',$did);
				
				setCookie('userid',$rows['FID']);
				
				setCookie('userfullname',$rows['FName']);
				
				$object_return = [
					"logout"=>0,
					"return"=>"S0000",
					"returnval"=>"0",
					"user"=>$this->data_source->user,
					"scode"=>$scode_return ,
					"name"=>$rows['FName']
				];
				
				$fidcd = isset($client_decode->_fidcd) ? $client_decode->_fidcd : $client_decode[3];
				$fidcc = isset($client_decode->_fidcc) ? $client_decode->_fidcc : $client_decode[1];
				if(isset($fidcd)){  // get the subscription of Device :
					$sql1 = "SELECT 
								CDSS.FIDCloudDevice,
								CDSS.FIDCloudServiceSubscription, 
								CDSS.FPriority,
								CSS.FName as FNameCloudServiceSubscription,
								CSS.FDescription as FDescCloudServiceSubscription,
								CSST.FName as FNameCSST
							FROM clouddeviceservicesubscription CDSS
							INNER JOIN cloudservicesubscription CSS
							ON CDSS.FIDCloudServiceSubscription = CSS.FID
							LEFT JOIN cloudservicesubscriptiontype CSST
							ON CSST.FID=CDSS.FIDCloudServiceSubscriptionType
							WHERE FIDCloudDevice = '$fidcd' ORDER BY CDSS.FPriority DESC";

					//query to get main service from device
					$sql2 = "SELECT 
								DISTINCT
								CDSS.FIDCloudServiceSubscription, 
								CSS.FName as FNameCloudServiceSubscription,
								CSS.FDescription as FDescCloudServiceSubscription
							FROM clouddeviceservicesubscription CDSS
							INNER JOIN cloudservicesubscription CSS
							ON CDSS.FIDCloudServiceSubscription = CSS.FID
							LEFT JOIN cloudservicesubscriptiontype CSST
							ON CSST.FID=CDSS.FIDCloudServiceSubscriptionType
							WHERE FIDCloudDevice = '$fidcd'";

					//digunakan penentuan subsctype saat agent generate ticket:
					$sql3 = "SELECT 
								B.FID as FIDCC,
								B.FExtID1 as FExtID1CC,
								B.FBranch as FBranchCC,
								B.FDescription as FDescCC,
								CSS.FID as FIDCSS,
								CSS.FName as FNameCSS,
								CSS.FDescription FDescCSS,
								CSST.FID as FIDCSST,
								CSST.FName as FNameCSST,
								CSST.FDescription as FDescCSST
							FROM cloudservicesubscription CSS
							INNER JOIN cloudservicesubscriptiontype CSST ON  CSST.FIDCloudServiceSubscription = CSS.FID
							INNER JOIN branch B ON CSS.FIDCloudConnection = B.FID
							WHERE CSS.FIDCloudConnection = '$fidcc'";
							
					$data_device_service 	= $db->execute($sql1);
					$data_device_main_subsc = $db->execute($sql2);
					$data_all_subsc 		= $db->execute($sql3);
					
					$subsc 		= array();
					$mainsubsc 	= array();
					$allsubsc 	= array();
					
					if(is_array($data_device_service)){
						foreach($data_device_service as $device_service){
							array_push($subsc , array(
								'group'		=> $device_service['FNameCloudServiceSubscription'],
								'grouplable'=> $device_service['FDescCloudServiceSubscription'],
								'subsctype'	=> empty($device_service['FNameCSST'])?'':$device_service['FNameCSST'],
								'priority'	=> $device_service['FPriority']
							));
						}
						$this->setSubsc($subsc);
					}
					if(is_array($data_device_main_subsc)){
						foreach($data_device_main_subsc as $device_main_subsc){
							array_push($mainsubsc , array(
								'group'		=>$device_main_subsc['FNameCloudServiceSubscription'],
								'grouplable'=>$device_main_subsc['FDescCloudServiceSubscription']
							));
						}
						$this->setMainSubsc($mainsubsc);
					}
					
					if(is_array($data_all_subsc)){
						foreach($data_all_subsc as $row_all_subsc){
							array_push($allsubsc , array(
								'subsc'		=>$row_all_subsc['FNameCSS'],
								'subsctype'	=>$row_all_subsc['FNameCSST']
							));
						}
						$this->setAllSubsc($allsubsc);
					}
				}
				
				return $object_return;
			}
			else{
				return array("logout"=>1,"return"=>"E0000");
			}
		}
		
		# logout method :
		public function logout(){
			$response['kind']	= "logout#system";
			$response['scode']	= "";
			$response['logout']	= 1;
			$response['ck']		= "";
			$this->removeCookSess("scode");
			$this->removeCookSess("userid");
			$this->removeCookSess("userfullname");
			$this->removeCookSess("did");
			return $response;
		}
		# heartbeat every 10 seconds : 
		public function heartbeat(){
			//new Logger($_SERVER['DOCUMENT_ROOT']."/log/API.log", $_SERVER['PHP_SELF'].":".__LINE__."  #HEARTBEAT LOADED:". print_r("",true)."#END HEARTBEAT");
			if(!isset($this->data_source->scode)){
				#  base64  [code] = Counter2#FIDCloudConnection#YYYYMMDDHHiiss#FIDCloudDevice
				$code = explode("#",$this->base64decode($this->data_source->code));
				$db = $this->dbConnect();
				$clause = array("FID"=>$code[3],"FName"=>$code[0],"FIDCloudConnection"=>$code[1]);
				$data_device = $db->getSingleRow('clouddevices',$clause,null);
				if(count($data_device)>0){
					$d1=new DateTime();
					$d1->modify("10 seconds");
					$db->update('clouddevices',array("FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "1"),$clause);
					return array("status"=>"Connect OK");
				}
			}
			
			else if(isset($this->data_source->scode)){
				$db = $this->dbConnect();
				$clause = array("FID"=>$this->getAuthFIDCDFromScode(),"FName"=>$this->getAuthFNameCDFromScode(),"FIDCloudConnection"=>$this->getAuthFIDCCFromScode());
				$data_device = $db->getSingleRow('clouddevices',$clause,null);
				if(count($data_device)>0){
					$d1=new DateTime();
					$d1->modify("10 seconds");
					$status = $db->update('clouddevices',array("FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "2"),$clause);
					return array("status"=>"Login OK");
				}
			}
			
			else{
				return array("status"=>"Connect Failed");
			}
		}
		
		public function heartbeatv2(){
			if(isset($this->data_source->hcode)){
				switch($this->data_source->hcode){
					case '0' : //OFFLINE
						
					break;
					
					case '1' : //CONNECTED
						if(!isset($this->data_source->scode)){
							//note : base64  [code] = Counter2#FIDCloudConnection#YYYYMMDDHHiiss#FIDCloudDevice
							$code = explode("#",$this->base64decode($this->data_source->code));
							$clause = array("FID"=>$code[3],"FIDCloudConnection"=>$code[1]);
							$d1 = new DateTime();
							$d1->modify("10 seconds");
							$db = $this->dbConnect();
							$result_update = $db->update('clouddevices',array("FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "1"),$clause);
							if($result_update){
								return array("status"=>"OK");
							}
							else{
								return array("status"=>"Failed");
							}
						}
						else{
							return array("status"=>"Failed");
						}
					break;
					
					case '2' : //LOGIN(IDLE)
						if(isset($this->data_source->scode)){
							$deviceclause = array("FID"=>$this->getAuthFIDCDFromScode(),"FIDCloudConnection"=>$this->getAuthFIDCCFromScode());
							$userclause = array("FID"=>$this->data_source->userid,"FIDCloudConnection"=>$this->getAuthFIDCCFromScode());
							$d1 = new DateTime();
							$d1->modify("10 seconds");
							
							$db = $this->dbConnect();
							$result_cd_update = $db->update('clouddevices',array("FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "2","FIDCloudUsers"=>$this->data_source->userid),$deviceclause);
							$result_user_update = $db->update('users',array("FLastAccess"=>$d1->format('Y-m-d H:i:s'),"FConnSession" => "2"),$userclause);
							if($result_cd_update){
								if($result_user_update){
									return array("status"=>"OK","msg"=>"OK, for All");
								}
								else{
									return array("status"=>"OK","msg"=>"OK, for CD");
								}
							}
							else{
								if($result_user_update){
									return array("status"=>"OK","msg"=>"OK, for User");
								}
								else{
									return array("status"=>"Failed");
								}
							}
						}
						else{
							return array("status"=>"Failed");
						}
					break;
					
					case '3' : //LOGIN(SERVING)
						if(isset($this->data_source->scode)){
							$deviceclause = array("FID"=>$this->getAuthFIDCDFromScode(),"FIDCloudConnection"=>$this->getAuthFIDCCFromScode());
							$userclause = array("FID"=>$this->data_source->userid,"FIDCloudConnection"=>$this->getAuthFIDCCFromScode());
							$d1 = new DateTime();
							$d1->modify("10 seconds");
							
							$db = $this->dbConnect();
							$result_cd_update = $db->update('clouddevices',array("FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "3","FIDCloudUsers"=>$this->data_source->userid),$deviceclause);
							$result_user_update = $db->update('users',array("FLastAccess"=>$d1->format('Y-m-d H:i:s'),"FConnSession" => "3"),$userclause);
							if($result_cd_update){
								if($result_user_update){
									return array("status"=>"OK","msg"=>"OK, for All");
								}
								else{
									return array("status"=>"OK","msg"=>"OK, for CD");
								}
							}
							else{
								if($result_user_update){
									return array("status"=>"OK","msg"=>"OK, for User");
								}
								else{
									return array("status"=>"Failed");
								}
							}
						}
						else{
							return array("status"=>"Failed");
						}
					break;
					
					case '7' : //CONNECTED(hiQCloudAgent) :
						if(isset($this->data_source->scode)){
							$deviceclause = array("FID"=>$this->getAuthFIDCDFromScode(),"FIDCloudConnection"=>$this->getAuthFIDCCFromScode());
							$d1 = new DateTime();
							$d1->modify("25 seconds");
							
							$db = $this->dbConnect();
							$result_update = $db->update("clouddevices",array("FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "1"),$deviceclause);
							if($result_update){
								return array("status"=>"OK");
							}
							else{
								return array("status"=>"Failed");
							}
						}
						else{
							return array("status"=>"Heartbeat Failed", "returnmessage"=>"Failed");
						}
					break;
					
					default:
						return array("error"=>"Heartbeat access dennied!", "code"=>"0", "returnmessage"=>"hcode undefined!");
					break;
				}
			}
		}
		public function heartbeatv3(){
			$mc = $this->mcConnect();
			if(isset($this->data_source->hcode)){
				switch($this->data_source->hcode){
					case '0' : //OFFLINE
						// do nothing
					break;
					
					case '1' : //CONNECTED
						if(!isset($this->data_source->scode)){
							$code_decode = $this->base64urldecode($this->data_source->code);
							$code_object = json_decode($code_decode);
							
							$clause = array("FID"=>$code_object->_fidcd,"FIDCloudConnection"=>$code_object->_fidcc);
							$d1 = new DateTime();
							$d1->modify("10 seconds");
							
							//$db = $this->dbConnect();
							//$result_update = $db->update('clouddevices',array("FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "1"),$clause);
							$result_update = $mc->set($clause['FID'],["FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "1","FName"=>$code_object->_fnamecd,"FID"=>$code_object->_fidcd,"FAutocall"=>$code_object->p_autocall,"FIDCloudConnection"=>$code_object->_fidcc,"FIPs"=>$this->getIPs(),"mainsubsc"=>$this->getMainSubsc()]);
							if($result_update){
								
								return array("status"=>"OK");
							}
							else{
								return array("status"=>"Failed");
							}
						}
						else{
							return array("status"=>"Failed");
						}
					break;
					
					case '2' : //LOGIN(IDLE)
						if(isset($this->data_source->scode)){
							$scode_decode = $this->base64decode($this->data_source->scode);
							$scode_object = json_decode($scode_decode);
							
							$deviceclause = array("FID"=>$scode_object->_fidcd,"FIDCloudConnection"=>$scode_object->_fidcc);
							$userclause = array("FID"=>$this->data_source->userid,"FIDCloudConnection"=>$scode_object->_fidcc);
							$d1 = new DateTime();
							$d1->modify("10 seconds");
							
							//$db = $this->dbConnect();
							//$result_cd_update = $db->update('clouddevices',array("FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "2","FIDCloudUsers"=>$this->data_source->userid),$deviceclause);
							$result_cd_update = $mc->set($deviceclause['FID'],["FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "2","FName"=>$scode_object->_fnamecd,"FID"=>$scode_object->_fidcd,"FAutocall"=>$scode_object->p_autocall,"FIDCloudUsers"=>$this->data_source->userid,"FIDCloudConnection"=>$scode_object->_fidcc,"FNameCloudUser"=>$_COOKIE['userfullname'],"FIPs"=>$this->getIPs(),"mainsubsc"=>$this->getMainSubsc()]);
							
							//$result_user_update = $db->update('users',array("FLastAccess"=>$d1->format('Y-m-d H:i:s'),"FConnSession" => "2"),$userclause);
							$result_user_update = $mc->set($userclause['FID'],["FLastAccess"=>$d1->format('Y-m-d H:i:s'),"FConnSession" => "2","FIDCloudConnection"=>$scode_object->_fidcc,"FIPs"=>$this->getIPs(),"mainsubsc"=>$this->getMainSubsc()]);
							
							if($result_cd_update){
								if($result_user_update){
									return array("status"=>"OK","msg"=>"OK, for All");
								}
								else{
									return array("status"=>"OK","msg"=>"OK, for CD");
								}
							}
							else{
								if($result_user_update){
									return array("status"=>"OK","msg"=>"OK, for User");
								}
								else{
									return array("status"=>"Failed");
								}
							}
						}
						else{
							return array("status"=>"Failed");
						}
					break;
					
					case '3' : //LOGIN(SERVING)
						if(isset($this->data_source->scode)){
							$scode_decode = $this->base64decode($this->data_source->scode);
							$scode_object = json_decode($scode_decode);
							
							$deviceclause = array("FID"=>$scode_object->_fidcd,"FIDCloudConnection"=>$scode_object->_fidcc);
							$userclause = array("FID"=>$this->data_source->userid,"FIDCloudConnection"=>$scode_object->_fidcc);
							
							$d1 = new DateTime();
							$d1->modify("10 seconds");
							
							//$db = $this->dbConnect();
							//$result_cd_update = $db->update('clouddevices',array("FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "3","FIDCloudUsers"=>$this->data_source->userid),$deviceclause);
							$result_cd_update = $mc->set($deviceclause['FID'],["FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "3","FName"=>$scode_object->_fnamecd,"FID"=>$scode_object->_fidcd,"FAutocall"=>$scode_object->p_autocall,"FIDCloudUsers"=>$this->data_source->userid,"FIDCloudConnection"=>$scode_object->_fidcc,"FNameCloudUser"=>$_COOKIE['userfullname'],"FIPs"=>$this->getIPs(),"mainsubsc"=>$this->getMainSubsc()]);
							
							//$result_user_update = $db->update('users',array("FLastAccess"=>$d1->format('Y-m-d H:i:s'),"FConnSession" => "3"),$userclause);
							$result_user_update = $mc->set($userclause['FID'],["FLastAccess"=>$d1->format('Y-m-d H:i:s'),"FConnSession" => "3","FIDCloudConnection"=>$scode_object->_fidcc,"FIPs"=>$this->getIPs(),"mainsubsc"=>$this->getMainSubsc()]);
							
							if($result_cd_update){
								if($result_user_update){
									return array("status"=>"OK","msg"=>"OK, for All");
								}
								else{
									return array("status"=>"OK","msg"=>"OK, for CD");
								}
							}
							else{
								if($result_user_update){
									return array("status"=>"OK","msg"=>"OK, for User");
								}
								else{
									return array("status"=>"Failed");
								}
							}
						}
						else{
							return array("status"=>"Failed");
						}
					break;
					
					case '7' : //CONNECTED(hiQCloudAgent, hiQWelcomer) :  
						if(isset($this->data_source->scode)){
							$deviceclause = array("FID"=>$this->getAuthFIDCDFromScode(),"FIDCloudConnection"=>$this->getAuthFIDCCFromScode());
							/*
							if($deviceclause["FID"]=="1501A0100020F"){
								new Logger($_SERVER['DOCUMENT_ROOT']."/log/HEARTBEAT_CILANDAK.log", $_SERVER['PHP_SELF'].":" . __LINE__ . "  " . "#System::heartbeatv3()".$deviceclause["FID"]."#END");
							}
							*/
							$d1 = new DateTime();
							$d1->modify("35 seconds");
							
							//$db = $this->dbConnect();
							//$result_update = $db->update("clouddevices",array("FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "1"),$deviceclause);
							$result_update = $mc->set($deviceclause['FID'],["FLastUpdate"=>$d1->format('Y-m-d H:i:s'),"FConnStatus" => "1","FIDCloudConnection"=>$deviceclause['FIDCloudConnection'],"FIPs"=>$this->getIPs()]);
							
							if($result_update){
								return array("returnval"=>true,"time"=>$this->convertDateTime2toJS(date('Y-m-d H:i:s')));
							}
							else{
								return array("returnval"=>false,"returnmsg"=>"Device not found!");
							}
						}
						else{
							return array("returnval"=>false, "returnmsg"=>"Access retristict!");
						}
					break;
					
					default:
						return array("error"=>"Heartbeat access dennied!", "code"=>"0", "returnmessage"=>"hcode undefined!");
					break;
				}
			}
		}
		public function clientparams(){
			if(@$this->getAuthorized()){
				
				if($this->getConnectionName()=='1506122159503ECDE344ESI4'){
					new Logger($_SERVER['DOCUMENT_ROOT'].'/log/System_clientparams.log', $_SERVER['PHP_SELF'].':'.__LINE__.''.print_r($this->data_source,true).'#END');
				}
				
				//setting heartbeat for device :
				$this->data_source->hcode = '7';
				$this->heartbeatv3();
				
				if(isset($this->data_source->clientengine) && trim($this->data_source->clientengine)=="1"){  //IF agent = checkin system on loop yogyakarta
					$return = array(
								"valid"=>"1",
								"returnval"=>true,
								"class"=>array(
									"name"=>'heartbeat',
									"values" => array(
										"servertime"=>$this->convertDateTime2toJS()
									)
								)
							);
					return $return;
				}
				
				if(isset($this->data_source->classname)){
					$clientparams_clause = array(
						'FIDCloudConnection'=>$this->getConnectionName()/*'TSELKLNM'*/,
						'FExecuted'=>'0',
						'FClassName'=>$this->data_source->classname
					);
				}
				else{
					$clientparams_clause = array(
						'FIDCloudConnection'=>$this->getConnectionName()/*'TSELKLNM'*/,
						'FExecuted'=>'0',
						'FClassName'=>'voice'
					);
				}
				
				$this->db = $this->dbConnect();
				$waitingcount = $this->getWaitingCount();
				
				// server resource params :
				$scode_decode = $this->parseAuthBase64($this->data_source->scode);
				
				switch($this->data_source->properties->context){
					case 'multiple'	:
						$data = $this->getallclientparams($clientparams_clause);
						$return = [
							"valid"=>"1",
							"returnval"=>true,
							"result"=>[]
						];
					break;
					default:
						$data = $this->getclientparams($clientparams_clause);
					break;
				}
				
				if($data){
					$result_count = count($data);
					foreach($data as $top_row){
						switch(trim($top_row['FClassName'])){
							case 'voice' :
								if(isset($scode_decode->p_vc) && $scode_decode->p_vc==false){
									// dont delete clientparams
								}
								else{
									$this->deleteclientparams([
										"FID"=>$top_row['FID'],
										"FClassName"=>$top_row['FClassName'],
										"FIDCloudConnection"=>$top_row['FIDCloudConnection']
									]);
								}
								if($this->data_source->properties->context=='multiple'){
									array_push($return['result'],[
											"name"=>trim($top_row['FClassName']),
											"values" => [
												"counter" => $this->getDataExplode("=",$top_row['FValue1'],1),
												"qnumber" => $this->getDataExplode("=",$top_row['FValue2'],1),
												"qnumberext" => $this->getDataExplode("=",$top_row['FValue2'],1),
												"group" => $this->getDataExplode("=",$top_row['FValue3'],1),
												"userremark1" => $this->getDataExplode("=",$top_row['FValue4'],1),
												"ticketremark1"	=> $this->getDataExplode("=",$top_row['FValue5'],1),
												"waitingcount" => $waitingcount
											]
										]
									);
								}
								else{
									$return = [
										"valid"=>"1",
										"returnval"=>true,
										"class"=>[
											"name"=>trim($top_row['FClassName']),
											"values" => [
												"counter" => $this->getDataExplode("=",$top_row['FValue1'],1),
												"qnumber" => $this->getDataExplode("=",$top_row['FValue2'],1),
												"group" => $this->getDataExplode("=",$top_row['FValue3'],1),
												"userremark1" => $this->getDataExplode("=",$top_row['FValue4'],1),
												"ticketremark1"	=> $this->getDataExplode("=",$top_row['FValue5'],1),
												"waitingcount" => $waitingcount
											]
										],
										"waitingcount"=>$waitingcount
									];
									//add waitingsummary if requested by properties->waitingcount==true :
									if($this->data_source->properties->waitingcount || $this->data_source->properties->waitingsummary){
										$return["waitingsummary"] = $this->getWaitingSubsc();
										//add estcall :
										//$hiqobj = new Hiq();
										//$estcall = $hiqobj->getQLastInfo($top_row['FIDCloudConnection'],'%'); 
										//$return["waitingduration"] = $estcall;
									}
									return $return;
								}
							break;
							
							case 'mmd' :
								if(isset($scode_decode->p_mmd) && $scode_decode->p_mmd==false){
									// dont delete clientparams
								}
								else{
									$this->deleteclientparams([
										"FID"=>$top_row['FID'],
										"FClassName"=>$top_row['FClassName'],
										"FIDCloudConnection"=>$top_row['FIDCloudConnection']
									]);	
								}
								
								$user_rows = $this->db->getSingleRow('users',[
									'FIDCloudConnection'=>$top_row['FIDCloudConnection'],
									'FID'=>$this->getDataExplode("=",$top_row['FValue6'],1)
								]);
								
								if($user_rows){
									$user_rows = $user_rows[0];
									
									// get FLastUpdate user profile :
									$date = new DateTime($user_rows['FLastUpdate']);
									$t = $date->getTimestamp();
									if(isset($user_rows['FImgURL']) && !empty($user_rows['FImgURL'])){
										$imgurl = $user_rows['FImgURL']."/SW350/jpg/t=".$t;
									}
									else{
										$imgurl = '/img/cs-default.png';
									}
									$userfullname = $user_rows['FName'];
								}
								else{
									$imgurl = '/img/cs-default.png';
									$userfullname = 'UNKNOWN';
								}
								
								if($this->data_source->properties->context==true){
									array_push($return['result'],[
											"name"=>trim($top_row['FClassName']),
											"values" => array(
												"counter" => $this->getDataExplode("=",$top_row['FValue1'],1),
												"qnumber" => $this->getDataExplode("=",$top_row['FValue2'],1),
												"qnumberext" => $this->getDataExplode("=",$top_row['FValue2'],1),
												"group" => $this->getDataExplode("=",$top_row['FValue3'],1),
												"userremark1" => $this->getDataExplode("=",$top_row['FValue4'],1),
												"ticketremark1"	=> $this->getDataExplode("=",$top_row['FValue5'],1),
												"user" => $this->getDataExplode("=",$top_row['FValue6'],1),
												"method" => $this->getDataExplode("=",$top_row['FValue7'],1),
												"remark4" => $this->getDataExplode("=",$top_row['FValue8'],1),
												"remark5" => $this->getDataExplode("=",$top_row['FValue9'],1),
												"userfullname" => $userfullname,
												"userimg" =>$imgurl,
												"waitingcount" => $waitingcount
											)
										]
									);
								}
								else{
									$return = array(
										"valid"=>"1",
										"returnval"=>true,
										"class"=>array(
											"name"=>trim($top_row['FClassName']),
											"values" => array(
												"counter" => $this->getDataExplode("=",$top_row['FValue1'],1),
												"qnumber" => $this->getDataExplode("=",$top_row['FValue2'],1),
												"qnumberext" => $this->getDataExplode("=",$top_row['FValue2'],1),
												"group" => $this->getDataExplode("=",$top_row['FValue3'],1),
												"userremark1" => $this->getDataExplode("=",$top_row['FValue4'],1),
												"ticketremark1"	=> $this->getDataExplode("=",$top_row['FValue5'],1),
												"user" => $this->getDataExplode("=",$top_row['FValue6'],1),
												"method" => $this->getDataExplode("=",$top_row['FValue7'],1),
												"remark4" => $this->getDataExplode("=",$top_row['FValue8'],1),
												"remark5" => $this->getDataExplode("=",$top_row['FValue9'],1),
												"userfullname" => $userfullname,
												"userimg" =>$imgurl,
												"waitingcount" => $waitingcount
											)
										),
										"waitingcount"=>$waitingcount
									);
									/*
									if(isset($this->data_source->params->flnum)){
										$return["class"]["values"]["flnum"] = $this->getDataExplode("=",$top_row['FValue9'],1);
									}
									*/
									
									//add waitingsummary if requested by properties->waitingcount==true :
									if($this->data_source->properties->waitingsummary){
										$return["waitingsummary"] = $this->getWaitingSubsc();
										//add estcall :
										//$hiqobj = new Hiq();
										//$estcall = $hiqobj->getQLastInfo($top_row['FIDCloudConnection'],'%'); 
										//$return["waitingduration"] = $estcall;
									}
									return $return;
									
								}
							break;
							
							case 'mmdkiosk' :
								$this->deleteclientparams(["FID"=>$top_row['FID'],"FClassName"=>$top_row['FClassName'],"FIDCloudConnection"=>$top_row['FIDCloudConnection']]);
								$user_rows = $this->db->getSingleRow('users',array('FIDCloudConnection'=>$top_row['FIDCloudConnection'],'fid'=>$this->getDataExplode("=",$top_row['FValue6'],1)));
								if($user_rows){
									$user_rows = $user_rows[0];
									
									//get last update user profile
									$date = new DateTime($user_rows['FLastUpdate']);
									$t = $date->getTimestamp();
									if(isset($user_rows['FImgURL']) && !empty($user_rows['FImgURL'])){
										$imgurl = $user_rows['FImgURL']."/SW350/jpg/t=".$t;
									}
									else{
										$imgurl = '/img/cs-default.png';
									}
									$userfullname = $user_rows['FName'];
								}
								else{
									$imgurl = '/img/cs-default.png';
									$userfullname = 'UNKNOWN';
								}
			
								$return = array(
									"valid"=>"1",
									"returnval"=>true,
									"class"=>array(
										"name"=>trim($top_row['FClassName']),
										"values" => array(
											"counter" => $this->getDataExplode("=",$top_row['FValue1'],1),
											"qnumber" => $this->getDataExplode("=",$top_row['FValue2'],1),
											"qnumberext" => $this->getDataExplode("=",$top_row['FValue2'],1),
											"group" => $this->getDataExplode("=",$top_row['FValue3'],1),
											"userremark1" => $this->getDataExplode("=",$top_row['FValue4'],1),
											"ticketremark1"	=> $this->getDataExplode("=",$top_row['FValue5'],1),
											"user" => $this->getDataExplode("=",$top_row['FValue6'],1),
											"method" => $this->getDataExplode("=",$top_row['FValue7'],1),
											"remark4" => $this->getDataExplode("=",$top_row['FValue8'],1),
											"userfullname" => $userfullname,
											"userimg" =>$imgurl,
											"waitingcount" => $waitingcount
										)
									),
									"waitingcount"=>$waitingcount
								);
								/*
								if(isset($this->data_source->params->flnum)){
									$return["class"]["values"]["flnum"] = $this->getDataExplode("=",$top_row['FValue9'],1);
								}
								*/
								
								//add waitingsummary if requested by properties->waitingcount==true :
								if($this->data_source->properties->waitingcount){
									$return["waitingsummary"] = $this->getWaitingSubsc();
									//add estcall :
									//$hiqobj = new Hiq();
									//$estcall = $hiqobj->getQLastInfo($top_row['FIDCloudConnection'],'%'); 
									//$return["waitingduration"] = $estcall;
								}
								return $return;
								
							break;
							
							case 'netconfig' :
								
								$this->updateclientparams(["FExecuted"=>'1'],["FID"=>$top_row['FID'],"FClassName"=>$top_row['FClassName'],"FIDCloudConnection"=>$top_row['FIDCloudConnection']]);
								$return = array(
									"valid"=>"1",
									"returnval"=>true,
									"class"=>array(
										"name"=>trim($top_row['FClassName']),
										"values" => array(
											"ipaddress" => $this->getDataExplode("=",$top_row['FValue1'],1),
											"subnetmask" => $this->getDataExplode("=",$top_row['FValue2'],1),
											"defaultgateway" => $this->getDataExplode("=",$top_row['FValue3'],1),
											"dns1" => $this->getDataExplode("=",$top_row['FValue4'],1),
											"dns2" => $this->getDataExplode("=",$top_row['FValue5'],1)
										)
									)
								);
								return $return;
							break;
							
							case 'clientvars' :
								
								$this->updateclientparams(["FExecuted"=>'1'],["FID"=>$top_row['FID'],"FClassName"=>$top_row['FClassName'],"FIDCloudConnection"=>$top_row['FIDCloudConnection']]);
								$return = array(
									"valid"=>"1",
									"returnval"=>true,
									"class"=>array(
										"name"=>trim($top_row['FClassName']),
										"values" => array(
											"serveraddress"=>$this->getDataExplode("=",$top_row['FValue1'],1),
											"serverport"=>$this->getDataExplode("=",$top_row['FValue2'],1),
											"id" =>$this->getDataExplode("=",$top_row['FValue3'],1),
											"userid"=>$this->getDataExplode("=",$top_row['FValue4'],1),
											"password"=>$this->getDataExplode("=",$top_row['FValue5'],1),
											"secspollinterval"=>$this->getDataExplode("=",$top_row['FValue6'],1),
											"localsound"=>$this->getDataExplode("=",$top_row['FValue7'],1),
											"textinputhandler"=>$this->getDataExplode("=",$top_row['FValue8'],1)
										)
									)
								);
								return $return;
							break;
							
							case 'syscommand' :
								
								$this->updateclientparams(["FExecuted"=>'1'],["FID"=>$top_row['FID'],"FClassName"=>$top_row['FClassName'],"FIDCloudConnection"=>$top_row['FIDCloudConnection']]);
								$return = array(
									"valid"=>"1",
									"class"=>array(
										"name"=>trim($top_row['FClassName']),
										"values" => array(
											"path"=>$this->getDataExplode("=",$top_row['FValue1'],1),
											"exefile"=>$this->getDataExplode("=",$top_row['FValue2'],1),
											"params" =>$this->getDataExplode("=",$top_row['FValue3'],1)
										)
									)
								);
								return $return;
							break;
							
							default:
								$return  = ["valid"=>"0","message"=>"Params is not found!","waitingcount"=>$waitingcount];
								//add waitingsummary if requested by properties->waitingcount==true :
								if($this->data_source->properties->waitingcount || $this->data_source->properties->waitingsummary){
									$return["waitingsummary"] = $this->getWaitingSubsc();
									//add estcall :
									//$hiqobj = new Hiq();
									//$estcall = $hiqobj->getQLastInfo($this->getConnectionName(),'%'); 
									//$return["waitingduration"] = $estcall;
								}
								return $return;
							break;
						}
					}
					return $return;
				}
				else{
					$return = ["valid"=>"0","returnval"=>true,"message"=>"No Params!","waitingcount"=>$waitingcount];
					//add waitingsummary if requested by properties->waitingcount==true :
					if($this->data_source->properties->waitingcount || $this->data_source->properties->waitingsummary){
						$return["waitingsummary"] = $this->getWaitingSubsc();
						//add estcall :
						$hiqobj = new Hiq();
						$estcall = $hiqobj->getQLastInfo($this->getConnectionName(),'%'); 
						$return["waitingduration"] = $estcall;
					}
					return $return;
				}
			}
			else{
				return array("logout"=>1,"return"=>"E0000","returnmessage"=>"Authorization clientparams failed!");
			}
		}
		
		public function kioskparams(){
			if(@$this->getAuthorized()){
			
				#setting heartbeat for device :
				$this->data_source->hcode = '7';
				$this->heartbeatv3();
				$this->db = $this->dbConnect();
				$clientparams_clause = array(
					'FIDCloudConnection'=>$this->getConnectionName()/*'TSELKLNM'*/,
					'FExecuted'=>'0'
				);
				
				$data = $this->getclientparams($clientparams_clause);
				
				$waiting_count = $this->getWaitingCount();
				$return_waiting_subsc = $this->getWaitingSubsc();
				
				if($data){
					$top_row = $data[0];
					switch(trim($top_row['FClassName'])){
						case 'voice' :
							
							$this->deleteclientparams(["FID"=>$top_row['FID'],"FClassName"=>$top_row['FClassName'],"FIDCloudConnection"=>$top_row['FIDCloudConnection']]);
							
							$return = array(
								"valid"=>"1",
								"class"=>array(
									"name"=>trim($top_row['FClassName']),
									"values" => array(
										"counter" => $this->getDataExplode("=",$top_row['FValue1'],1),
										"qnumber" => $this->getDataExplode("=",$top_row['FValue2'],1),
										"group" => $this->getDataExplode("=",$top_row['FValue3'],1),
										"userremark1" => $this->getDataExplode("=",$top_row['FValue4'],1),
										"ticketremark1"	=> $this->getDataExplode("=",$top_row['FValue5'],1),
										"waitingcount" => $waiting_count
									)
								),
								"waitingcount"=>$waiting_count,
								"waitingdetails" => $return_waiting_subsc
							);
							return $return;
						break;
						
						case 'netconfig' :
							
							$this->updateclientparams(["FExecuted"=>'1'],["FID"=>$top_row['FID'],"FClassName"=>$top_row['FClassName'],"FIDCloudConnection"=>$top_row['FIDCloudConnection']]);
							$return = array(
								"valid"=>"1",
								"class"=>array(
									"name"=>trim($top_row['FClassName']),
									"values" => array(
										"ipaddress" => $this->getDataExplode("=",$top_row['FValue1'],1),
										"subnetmask" => $this->getDataExplode("=",$top_row['FValue2'],1),
										"defaultgateway" => $this->getDataExplode("=",$top_row['FValue3'],1),
										"dns1" => $this->getDataExplode("=",$top_row['FValue4'],1),
										"dns2" => $this->getDataExplode("=",$top_row['FValue5'],1)
									)
								)
							);
							return $return;
						break;
						
						case 'clientvars' :
							
							$this->updateclientparams(["FExecuted"=>'1'],["FID"=>$top_row['FID'],"FClassName"=>$top_row['FClassName'],"FIDCloudConnection"=>$top_row['FIDCloudConnection']]);
							$return = array(
								"valid"=>"1",
								"class"=>array(
									"name"=>trim($top_row['FClassName']),
									"values" => array(
										"serveraddress"=>$this->getDataExplode("=",$top_row['FValue1'],1),
										"serverport"=>$this->getDataExplode("=",$top_row['FValue2'],1),
										"id" =>$this->getDataExplode("=",$top_row['FValue3'],1),
										"userid"=>$this->getDataExplode("=",$top_row['FValue4'],1),
										"password"=>$this->getDataExplode("=",$top_row['FValue5'],1),
										"secspollinterval"=>$this->getDataExplode("=",$top_row['FValue6'],1),
										"localsound"=>$this->getDataExplode("=",$top_row['FValue7'],1),
										"textinputhandler"=>$this->getDataExplode("=",$top_row['FValue8'],1)
									)
								)
							);
							return $return;
						break;
						
						case 'syscommand' :
							
							$this->updateclientparams(["FExecuted"=>'1'],["FID"=>$top_row['FID'],"FClassName"=>$top_row['FClassName'],"FIDCloudConnection"=>$top_row['FIDCloudConnection']]);
							$return = array(
								"valid"=>"1",
								"class"=>array(
									"name"=>trim($top_row['FClassName']),
									"values" => array(
										"path"=>$this->getDataExplode("=",$top_row['FValue1'],1),
										"exefile"=>$this->getDataExplode("=",$top_row['FValue2'],1),
										"params" =>$this->getDataExplode("=",$top_row['FValue3'],1)
									)
								)
							);
							return $return;
						break;
						
						default:
							return array("valid."=>"0","message"=>"Params is not found!","waitingcount"=>$waiting_count,"waitingdetails"=>$return_waiting_subsc);
						break;
					}
				}
				else{
					return array("valid"=>"0","message"=>"No Params!","waitingcount"=>$waiting_count,"waitingdetails"=>$return_waiting_subsc);
				}
			}
			else{
				return array("logout"=>1,"return"=>"E0000","returnmessage"=>"Authorization clientparams failed!");
			}
		}
		
		public function getWaitingSubsc(){
			$this->db = $this->dbConnect();
			$sql = "SELECT FGroup, COUNT(*) AS Waiting FROM qserver WHERE FConnection='".$this->getConnectionName()."' AND FState='0'GROUP BY FGroup";
			$data = $this->db->selectCustom($sql);
			
			return $data;
		}
		
		# auto logout if scode not match :
		public function autologout(){
			$response['kind'] 	= "logout#system";
			$response['scode'] 	= "";
			$response['logout']	= 1;
			$response['ck']		= "";
			return $response;
		}
		
		public function getWaitingCount(){
			$clause = ["FIDCloudConnection"=>$this->getConnectionName(),"FState"=>0];
			if($clause["FIDCloudConnection"]!='' && $clause["FIDCloudConnection"]!=NULL){
				$result = $this->db->getRowCount('qserver',$clause);
				return $result;
			}
			else{
				return "0";
			}
		}
		
		# check code_app from client first request :
		public function checkSystemCode($cid=NULL){
			
			switch($this->getclientversion()){
				CASE "0" :
					$db = $this->dbConnect();
					$cid_decode = explode('#',$this->base64urldecode($this->data_source->cid)); // decode 'cid' from first connection,for standart format source is: 'CD.FName#CD.FCloudConnection#YmdHis#CD.FID' on 'Rest.php: _getCounter()'
					$data = $db->getSingleRow('clouddevices',array('FID'=>$cid_decode[3]),NULL,TRUE);
					
					if(count($data[0])>0){
						$device_rows = $data[0];
						$this->cookiecode = $this->base64encode($device_rows["FName"]."#".$device_rows["FIDCloudConnection"]."#".date('Y-m-dTH:i:s')."#".$device_rows["FID"]);
						return true;
					}
					else{
						return false;
					}
				BREAK;
				
				CASE "1.0" :
					// not release.
				BREAK;
				
				CASE "1.1" :   // For supported hiQ Client and hiQMMD
					$db = $this->dbConnect();
					$cid_decode = json_decode($this->base64urldecode($this->data_source->cid));
					$data = $db->getSingleRow('clouddevices',array('FID'=>$cid_decode->FIDCloudDevice),NULL);
					if($data){
						$device_rows = $data[0];
						
						$fparamsetting = json_decode($device_rows["FParamSetting"]);
						//migrate to serversetting enabled :
						if(isset($fparamsetting->serversetting)){
							$cookiecode = array(
								"_fnamecd"=>$device_rows["FName"],
								"_fidcc"=>$device_rows["FIDCloudConnection"],
								"_fdtime"=>date('Y-m-d\TH:i:s'),
								"_fidcd"=>$device_rows["FID"]
							);
							$serversetting = (array)$fparamsetting->serversetting;
							$cookiecode = array_merge($cookiecode,$serversetting);
							$this->cookiecode = $this->base64urlencode(json_encode($cookiecode));
							
							return true;
						}
						else{
							$this->cookiecode = $this->base64encode($device_rows["FName"]."#".$device_rows["FIDCloudConnection"]."#".date('Y-m-dTH:i:s')."#".$device_rows["FID"]);
							
							return true;
						}
					}
					else{
						return false;
					}
				BREAK;
				
				CASE "1.2" :
					$db = $this->dbConnect();
					$cid_decode = explode('#',$this->base64urldecode($this->data_source->cid));
					$data = $db->getSingleRow('clouddevices',array('FID'=>$cid_decode[0]),NULL);
					if(count($data[0])>0){
						$device_rows = $data[0];
						$cookiecode = array(
							"FName" => $device_rows["FName"],
							"FIDCloudConnection" => $device_rows["FIDCloudConnection"],
							"FID" => $device_rows["FID"],
							"FLastUpdate" => date('Y-m-d\TH:i:s')
						);
						$this->cookiecode = $this->base64encode(json_encode($cookiecode));
						return true;
					}
					else{
						return false;
					}
				BREAK;
				
				CASE "2.0" :  // with array to json_stringify encryption
					$db = $this->dbConnect();
					$cid_base64decode = $this->base64urldecode($this->data_source->cid);
					$cid_array_object = json_decode($cid_base64decode);
					$data = $db->getSingleRow('clouddevices',array('FID'=>$cid_array_object->_fidcd));
					if($data){
						$device_rows = $data[0];
						$cookiecode = array(
							"_fnamecd"=>$device_rows["FName"],
							"_fidcc"=>$device_rows["FIDCloudConnection"],
							"_fdtime"=>date('Y-m-d\TH:i:s'),
							"_fidcd"=>$device_rows["FID"]
						);
						$fparamsetting = json_decode($device_rows["FParamSetting"]);
						
						//parse the paramssetting for client setting :
						if(isset($fparamsetting->clientsetting)){
							switch($device_rows['FConnType']){
								case "3" :
									foreach($fparamsetting->clientsetting as $row){
										switch($row->name){
											case "cdisplay" :
												setCookie('cdisplay',json_encode($row->properties),null,"/");
											break;
											default:
											break;
										}
									}
								break;
								default :
								break;
							}
							unset($fparamsetting->clientsetting);
						}
						
						//migrate to serversetting enabled :
						$serversetting = isset($fparamsetting->serversetting) ? (array)$fparamsetting->serversetting : (array)$fparamsetting;
						$cookiecode = array_merge($cookiecode,$serversetting);
						$this->cookiecode = $this->base64urlencode(json_encode($cookiecode));
						return true;
					}
					else{
						return false;
					}
				BREAK;
				
				CASE "2.1" :   // For supported hiQ Client and hiQMMD
					$db = $this->dbConnect();
					$cid_decode = json_decode($this->base64urldecode($this->data_source->cid));
					$data = $db->getSingleRow('clouddevices',array('FID'=>$cid_decode->FIDCloudDevice),NULL);
					if($data){
						$device_rows = $data[0];
						
						$fparamsetting = json_decode($device_rows["FParamSetting"]);
						
						//parse the paramssetting for client setting :
						if(isset($fparamsetting->clientsetting)){
							switch($device_rows['FConnType']){
								case "3" :
									foreach($fparamsetting->clientsetting as $row){
										switch($row["name"]){
											case "cdisplay" :
												setCookie('cdisplay',json_encode($row),null,"/");
											break;
											default:
											break;
										}
									}
								break;
								default :
								break;
							}
						}
						
						//migrate to serversetting enabled :
						if(isset($fparamsetting->serversetting)){
							$cookiecode = array(
								"_fnamecd"=>$device_rows["FName"],
								"_fidcc"=>$device_rows["FIDCloudConnection"],
								"_fdtime"=>date('Y-m-d\TH:i:s'),
								"_fidcd"=>$device_rows["FID"]
							);
							$serversetting = (array)$fparamsetting->serversetting;
							$cookiecode = array_merge($cookiecode,$serversetting);
							$this->cookiecode = $this->base64urlencode(json_encode($cookiecode));
							
							return true;
						}
						else{
							$this->cookiecode = $this->base64encode($device_rows["FName"]."#".$device_rows["FIDCloudConnection"]."#".date('Y-m-dTH:i:s')."#".$device_rows["FID"]);
							
							return true;
						}
					}
					else{
						return false;
					}
				BREAK;
				
				DEFAULT :
					$db = $this->dbConnect();
					$cid_decode = explode('#',$this->base64urldecode($this->data_source->cid)); // decode 'cid' from first connection,for standart format source is: 'CD.FName#CD.FCloudConnection#YmdHis#CD.FID' on 'Rest.php: _getCounter()'
					$data = $db->getSingleRow('clouddevices',array('FID'=>$cid_decode[3]),NULL);
					
					if(count($data[0])>0){
						$device_rows = $data[0];
						$this->cookiecode = $this->base64encode($device_rows["FName"]."#".$device_rows["FIDCloudConnection"]."#".date('Y-m-dTH:i:s')."#".$device_rows["FID"]);
						return true;
					}
					else{
						return false;
					}
				BREAK;
			}
		}
		
		public function getCookieCode(){
			return $this->cookiecode;
		}
		
		public function getCookieCK(){
			return $this->cookieck; 
		}
		
		public function getConnectionName(){
			if(isset($this->data_source->scode)){
				return $this->getAuthFIDCCFromScode();
			}
			else{
				return "";
			}
		}
		
		//new version:
		public function getFIDCloudConnectionFromScode(){
			if(isset($this->data_source->scode)){
				$json_str = $this->base64decode($this->data_source->scode);
				$auth_object = json_decode($json_str); 
				return $auth_object->_fidcc;
			}
			else{
				return false;
			}
		}
		
		#function to return time-server with scode acepted :
		public function gtime(){
			if(isset($this->data_source->scode) && !empty($this->data_source->scode)){
				return array("time"=>$this->getDateTimeZ());
			}
			else{
				return array("logout"=>"1");
			}
		}
		# RETURN CLIENT DATETIME WITH PARAM TZO REQUEST
		public function ctime(){
			if(isset($this->data_source->scode) && !empty($this->data_source->scode)){
				return array("time"=>$this->getDateTimeClientZ($this->data_source->TZO));
			}
			else{
				return array("logout"=>"1");
			}
		}
		
		# return JS formata datetime "2015-06-12T23:12:54.123456+07:00"
		public function cstime(){
			if(isset($this->data_source->scode) && $this->data_source->scode == $_COOKIE['scode']){ 
				return array("time"=>$this->convertDateTime2toJS());
			}
			else{
				return array("logout"=>"1");
			}
			
		}
		# RETURN CLIENT TIMESTAMP WITH PARAM TZO REQUEST
		public function cstime2(){
			if(isset($this->data_source->scode) && !empty($this->data_source->scode)){
				return array("time"=>$this->getTimeStampServer($this->data_source->TZO),"other"=>$this->getTimeStampServerToUTC());
			}
			else{
				return array("logout"=>"1");
			}
		}
		
		# START METHOD DATETIME #
		/* yyyy-mm-dd HH:ii:ss */
		public function getDateTime(){
			return date('Y-m-d H:i:s');
		}
		/* yyyy-mm-dd HH:ii:ss.ms~7 */
		public function getDateTime2(){
			$t = microtime(true);
			$micro = sprintf("%07d",($t - floor($t)) * 10000000);
			$milisec = substr($micro,0,7);
			return date('Y-m-d H:i:s.').$milisec;
		}
		
		public function getstimestamp(){
			return date('U');
		}
		
		public function getDateTime2ServerToClient($datetime2=NULL, $TZO=NULL){  // param1 = '2015-02-01 12:22:40.123456'  param2='240'
			
			$offset = ($TZO==NULL) ? date('Z')/3600 : $TZO/60;
			
			$zone = $offset*100;
			
			$zone_str_min  = str_split(sprintf("%04d",(-1*$zone)),2)[0] .":". str_split(sprintf("%04d",(-1*$zone)),2)[1];
			$zone_str_plus = str_split(sprintf("%04d",$zone),2)[0] .":". str_split(sprintf("%04d",$zone),2)[1];
			
			$zone = ($zone < 0 ? "-".$zone_str_min : "+".$zone_str_plus);
			
			if($TZO==NULL){
				if($datetime2==NULL){
					return $this->getDateTime2().$zone;
				}
				else{
					return $datetime2.$zone;
				}
			}
			
			
			$arr_timestamp =  explode(" ",microtime());
			$arr_ms = explode(".",$arr_timestamp[0]);
			$ms = substr($arr_ms[1],0,3); 
		
			$diffTZO = ((date('Z')/60 * -1) - ($TZO * -1))/60;
			if($datetime2==NULL){
				$d1=new DateTime($this->getDateTime2());
				$d1->modify($diffTZO." hours");

				return $d1->format('Y-m-d H:i:s.u').$zone;
				//return $d1->format('U');
			}
			else{
				$d1=new DateTime($datetime2);
				$d1->modify($diffTZO." hours");
				
				return $d1->format('Y-m-d H:i:s.u').$zone;
				//return $d1->format('U');
			}
		}
		
		public function getDateTimeServerToClient($datetime=NULL, $TZO=NULL){  // param1 = '2015-02-01 12:22:40.123456'  param2='240'
			
			$datetime = (new DateTime($datetime))->format('Y-m-d H:i:s');
			
			$offset = ($TZO==NULL) ? date('Z')/3600 : $TZO/60;
			
			$zone = $offset*100;
			
			$zone_str_min  = str_split(sprintf("%04d",(-1*$zone)),2)[0] .":". str_split(sprintf("%04d",(-1*$zone)),2)[1];
			$zone_str_plus = str_split(sprintf("%04d",$zone),2)[0] .":". str_split(sprintf("%04d",$zone),2)[1];
			
			$zone = ($zone < 0 ? "-".$zone_str_min : "+".$zone_str_plus);
			
			if($TZO==NULL){
				if($datetime==NULL){
					return $this->getDateTime().$zone;
				}
				else{
					return $datetime.$zone;
				}
			}
			
			
			$arr_timestamp =  explode(" ",microtime());
			$arr_ms = explode(".",$arr_timestamp[0]);
			$ms = substr($arr_ms[1],0,3); 
		
			$diffTZO = ((date('Z')/60 * -1) - ($TZO * -1))/60;
			if($datetime==NULL){
				$d1=new DateTime($this->getDateTime2());
				$d1->modify($diffTZO." hours");

				return $d1->format('Y-m-d H:i:s').$zone;
				//return $d1->format('U');
			}
			else{
				$d1=new DateTime($datetime);
				$d1->modify($diffTZO." hours");
				
				return $d1->format('Y-m-d H:i:s').$zone;
				//return $d1->format('U');
			}
		}
		public function getDateTimeServerToClientF($datetime=NULL, $TZO=NULL){  // param1 = '2015-02-01 12:22:40.123456'  param2='240'
			
			$datetime = (new DateTime($datetime))->format('Y-m-d H:i:s');
			
			$offset = ($TZO==NULL) ? date('Z')/3600 : $TZO/60;
			
			$zone = $offset*100;
			
			$zone_str_min  = str_split(sprintf("%04d",(-1*$zone)),2)[0] .":". str_split(sprintf("%04d",(-1*$zone)),2)[1];
			$zone_str_plus = str_split(sprintf("%04d",$zone),2)[0] .":". str_split(sprintf("%04d",$zone),2)[1];
			
			$zone = ($zone < 0 ? "-".$zone_str_min : "+".$zone_str_plus);
			
			if($TZO==NULL){
				if($datetime==NULL){
					return $this->getDateTime().$zone;
				}
				else{
					return $datetime.$zone;
				}
			}
			
			
			$arr_timestamp =  explode(" ",microtime());
			$arr_ms = explode(".",$arr_timestamp[0]);
			$ms = substr($arr_ms[1],0,3); 
		
			$diffTZO = ((date('Z')/60 * -1) - ($TZO * -1))/60;
			if($datetime==NULL){
				$d1=new DateTime($this->getDateTime2());
				$d1->modify($diffTZO." hours");

				return $d1->format('F dS Y H:i').' GMT'.$zone;
				//return $d1->format('U');
			}
			else{
				$d1=new DateTime($datetime);
				$d1->modify($diffTZO." hours");
				
				return $d1->format('j F Y \a\t H:i').' GMT'.$zone;
				//return $d1->format('F dS Y H:i').' GMT'.$zone;
				//return $d1->format('U');
			}
		}
		
		public function ordinal_suffix($num){
			$num = $num % 100; // protect against large numbers
			if($num < 11 || $num > 13){
				 switch($num % 10){
					case 1: return 'st';
					case 2: return 'nd';
					case 3: return 'rd';
				}
			}
			return 'th';
		}
		
		public function getDateTime2ClientToServer($datetime2client=NULL){  // param1 = '2015-02-01 12:22:40.123456+08:00'
			
			$d1 = new DateTime($datetime2client);
			$d1->setTimezone(new DateTimeZone(date_default_timezone_get()));
			
			return $d1->format('Y-m-d H:i:s.u');
		}
		
		
		//return "2015-12-09T23:34:01.1234567+07:00"
		public function convertDateTime2toJS($datetime2=NULL){
			$server_offset = date('Z') / 3600;
			$zone = $server_offset*100;
			
			$zone_str_min  = str_split(sprintf("%04d",(-1*$zone)),2)[0] .":". str_split(sprintf("%04d",(-1*$zone)),2)[1];
			$zone_str_plus = str_split(sprintf("%04d",$zone),2)[0] .":". str_split(sprintf("%04d",$zone),2)[1];
			
			$zone = ($zone < 0 ? "-".$zone_str_min : "+".$zone_str_plus);
			
			if($datetime2==NULL){
				$d1=new DateTime($this->getDateTime2());
				return $d1->format('Y-m-d\TH:i:s.u').$zone;
			}
			else{
				$d1=new DateTime($datetime2);
				return $d1->format('Y-m-d\TH:i:s.u').$zone;
			}
		}
		
		/* yyyy-mm-ddThh:ii:ss.ms~3Z */
		public function getDateTimeZ($t=null){
			date_default_timezone_set('UTC');
			$time = $t ? $t : time();
			$datetime = date('Y-m-d\TH:i:s\Z',$time);
			//$datetime = date(DATE_W3C);
			return $datetime;
		}
		
		/* yyyy-mm-ddThh:ii:ss.ms~3Z */
		public function getUTCDateTimeZ(){
			date_default_timezone_set('UTC');
			$t = microtime(true);
			$micro = sprintf("%06d",($t - floor($t)) * 1000000);
			$datetime = date('Y-m-d\TH:i:s.',time()).substr($micro,0,3)."Z";
			//$this->datetime = date(DATE_W3C)'
			return $datetime;
		}
		
		/* yyyy-mm-ddThh:ii:ss.ms[~3]Z for Client-DateTime with $TZO (TimeZoneOffset) request */
		public function getDateTimeClientZ($TZO){
			$server_timezone = date('Z')/ 3600;
			$client_timezone = $TZO * 60 / 3600;
			$diffTZO =  $client_timezone-$server_timezone ;
			$d1=new DateTime($this->getDateTime2());
			$d1->modify($diffTZO." hours");
			return $d1->format('Y-m-d\TH:i:s.u\Z');
		}
		
		/* time_stamp[~13] for Server to Client-DateTime with $TZO (TimeZoneOffset) request*/
		public function getTimeStampServer(){
			$arr_timestamp =  explode(" ",microtime());
			$arr_ms = explode(".",$arr_timestamp[0]);
			$time_stamp = substr($arr_timestamp[1].$arr_ms[1],0,13);
			
			return $time_stamp;
		}
		
		// RETURN TimeStamp UTC  Timezone
		public function getTimeStampServerToUTC(){  
			$arr_timestamp =  explode(" ",microtime());
			$arr_ms = explode(".",$arr_timestamp[0]);
			$ms = substr($arr_ms[1],0,3); 

			$diffUTC = date('Z') / 60 / 60 * -1;
			$d1=new DateTime($this->getDateTime2());
			$d1->modify($diffUTC." hours");
			
			return $d1->format('U').$ms; 
			//return $d1->format('Y-m-d H:i:s.').$ms; 
			
		}
		
		
		// param FName 'TSELSMGI'  retur Timezone Offset from UTC:00
		public function getCloudConnectionTZO($FCloudConnection=NULL){
			if($FCloudConnection==NULL){
				$db = $this->dbConnect();
				$FCloudConnection = $this->getConnectionName();
				$data = $db->getSingleRow('branch',array('FName'=>$FCloudConnection));
				$top_row = $data[0];
				if(count($top_row)>0){
					return $top_row['FTimezoneOffset'];
				}
				else {
					return 0;
				}
			}
			else{
				$db = $this->dbConnect();
				$data = $db->getSingleRow('branch',array('FName'=>$FCloudConnection));
				$top_row = $data[0];
				if(count($top_row)>0){
					return $top_row['FTimezoneOffset'];
				}
				else {
					return 0;
				}
			}
		}
		
		
		// getDatetimeAddTime('2016-06-23 12:23:21','00:20:00');
		public function getDatetimeAddTime($datetime,$timeduration){
			list($hour, $minute, $second)=explode(':', $timeduration);
			$d1 = new DateTime($datetime);
			$d1->modify($hour." hours");
			$d1->modify($minute." minutes");
			$d1->modify($second." seconds");
			
			return $d1->format('Y-m-d H:i:s');
		}
		
		// getTimeDiv('01:04:30','4');
		// @return string : 'hh:mm:ss'
		public function getTimeDiv($time,$divby){
			$time_array = explode(':', $time);
			$hours = (int)$time_array[0];
			$minutes = (int)$time_array[1];
			$seconds = (int)$time_array[2];

			$total_seconds = ($hours * 3600) + ($minutes * 60) + $seconds;

			$timedivsec = floor($total_seconds / $divby);
			
			$timediv = gmdate("H:i:s", $timedivsec);
			
			return $timediv;
		}
		
		// getDateTimeDiff('2017-01-01 23:00:00','2017-02-03 12:00:00','s');
		public function getDateTimeDiff($min,$max,$format){
			$datetime1 = new DateTime($min);
			$datetime2 = new DateTime($max);
			$interval = $datetime1->diff($datetime2);
			
			return $interval->format($format);
		}
		
		public function getDateTimeDiffSec($datetime1,$datetime2){
			$datetime1 = new DateTime($datetime1);
			$datetime2 = new DateTime($datetime2);
			$interval = $datetime1->diff($datetime2);
			$inference = ( $datetime1 >= $datetime2 ) ? 1 : -1;
			return $inference * (($interval->format('%y') * 365 * 24 * 60 * 60) + 
				($interval->format('%m') * 30 * 24 * 60 * 60) + 
				($interval->format('%d') * 24 * 60 * 60) + 
				($interval->format('%h') * 60 * 60) + 
				($interval->format('%i') * 60) + 
				$interval->format('%s'));
		}
		
		// getTime2Sec('01:04:30')
		public function getTime2Sec($time){
			$time_array = explode(':', $time);
			$hours = (int)$time_array[0];
			$minutes = (int)$time_array[1];
			$seconds = isset($time_array[2]) ? (int)$time_array[2] : 0;

			$total_seconds = ($hours * 3600) + ($minutes * 60) + $seconds;
			
			return $total_seconds;
		}
		
		public function getSec2Time($sec){
		
			return gmdate("H:i:s", $sec);
		}
		
		public function getDateTimeFromString($format,$string){
			$time = strtotime($string);
			$newformat = date($format,$time);
			
			return $newformat;
		}
		
		public function getbranchTZO($branchid){
			//
		}
		# END METHOD DATETIME #
		
		public function getAuthorized(){
			if(isset($this->data_source->scode) || isset($this->data_source->extref)){
				return true;
			}
			else {
				return false;
			}
		}
		
		public function hasAuthorized(){
			
		}
		
		public function getclientversion(){
			if(isset($this->data_source->ver) && !empty($this->data_source->ver)){
				return trim($this->data_source->ver);
			}
			else {
				return "0";
			}
		}
		
		public function getclientengine(){
			if(isset($this->data_source->clientengine) && !empty($this->data_source->clientengine)){
				return trim($this->data_source->clientengine);
			}
			else {
				return "0";
			}
		}
		
		// addLeadingNumber(3,34);  will return 034
		// addLeadingNumber(4,34);  will return 0034
		// addLeadingNumber(3,234); will return 234
		public function addLeadingNumber($len,$num){
			$num_padded = sprintf("%0".$len."d", $num);
			return $num_padded;
		}
		
		public function addLeadingString($len,$string,$lead="0"){
			/*
				$input = "Alien";
				echo str_pad($input, 10);                      // produces "Alien     "
				echo str_pad($input, 10, "-=", STR_PAD_LEFT);  // produces "-=-=-Alien"
				echo str_pad($input, 10, "_", STR_PAD_BOTH);   // produces "__Alien___"
				echo str_pad($input, 6 , "___");               // produces "Alien_"
			*/
			return str_pad($string, $len, $lead, STR_PAD_LEFT); 
		}
		
		public function _setCookie(){
			#bool setcookie ( string $cookie_name [, string $cookie_value [, int $expire = 0 [, string $path [, string $domain [, bool $secure = false [, bool $httponly = false ]]]]]] )
			/*
				$cookie_name	= 'nama'
				$cookie_value	= 'DWI BUDI UTOMO'
				$expire 		= time()+3600  -> for 1 hours later
				$path			= '/foo/'
				$domain			= 'example.com'
				$secure			= if 'TRUE' cookie only set when connection is https://
				$httponly		= if 'TRUE' the cookie will be made accessible only through the HTTP protocol, JavaScript cannot access.
			*/
		}
		public function getSession($name=NULL){
			session_start();
			return ($name!=null && isset($_SESSION[$name])) ? $_SESSION[$name] : NULL;
		}
		
		public function setSession($name=NULL,$val=NULL){
			
			if($name!=NULL && $val!= NULL){
				session_start();
				$_SESSION[$name]= $val;
				
				return true;
			}
			
			return false;
		}
	
		/* allsubsc */
		public function getAllSubsc(){
			$result = $this->getSession('allsubsc');
			if($result){
				return $result;
			}
			return null;
		}
		
		public function setAllSubsc($allsubsc=NULL){
		
			return $this->setSession('allsubsc',$allsubsc);
		}
		
		/* mainsubsc */
		public function getMAinSubsc(){
			$result = $this->getSession('mainsubsc');
			if($result){
				return $result;
			}
			return null;
		}
		
		public function setMAinSubsc($mainsubsc=NULL){
		
			return $this->setSession('mainsubsc',$mainsubsc);
		}
		
		/* subsc */
		public function getSubsc(){
			$result = $this->getSession('subsc');
			if($result){
				return $result;
			}
			return null;
		}
		
		public function setSubsc($subsc=NULL){
		
			return $this->setSession('subsc',$subsc);
		}
		
		# CUSTOM BASE64 ENCODE-DECODE :
		public function base64encode($string=NULL){
			if($string!=NULL && !empty($string)){
				$encoded = strtr(base64_encode(trim($string)), self::$asccii64, self::$asccii64custom);
				return $encoded;
			}
			else{
				return "";
			}
		}
		public function base64decode($string=NULL){
			if($string==NULL && isset($this->data_source->encodestring)){
				$decoded = base64_decode(strtr(trim($this->data_source->encodestring), self::$asccii64custom, self::$asccii64));
				return array("decoded"=>$decoded);
			}
			if($string!=NULL){
				$decoded = base64_decode(strtr(trim($string), self::$asccii64custom, self::$asccii64));
				return $decoded;
			}
			else{
				$decoded = "";
				return $decoded;
			}
		}
		
		# CUSTOM BASE64 ENCODE-DECODE FOR URL:
		public function base64urlencode($string=NULL) {
			if($string!=NULL && !empty($string)){
				$encoded = rtrim(strtr($this->base64encode(trim($string)), '+/', '-_'), '=');
				return $encoded; 
			}
			else{
				return "";
			}
		}
		public function base64urldecode($string=NULL){
			if($string==NULL){
				$decoded = $this->base64decode(str_pad(strtr(trim($this->data_source->encodestring), '-_', '+/'), strlen(trim($this->data_source->encodestring)) % 4, '=', STR_PAD_RIGHT));
				return array("decoded"=>$decoded);
			}
			$decoded = $this->base64decode(str_pad(strtr(trim($string), '-_', '+/'), strlen(trim($string)) % 4, '=', STR_PAD_RIGHT));
			return $decoded;
		} 
		
		
		# START ENCODE-DECODE BASE128 METHOD #
		
		# ENCODE 128 :
		public function base128encode($buffer){
			return self::encode128_custom($buffer,self::$ascii128);
		}
		
		# DECODE 128 :
		public function base128decode($buffer){
			return self::decode128_custom($buffer,self::$ascii128);
		}
		
		public function encode128_custom($buffer,$ascii128){
			$size=strlen($buffer);
			$size++;                // add an empty byte to the end
			$ls=0;
			$rs=7;
			$r=0;
			$encoded="";
			
			for($inx=0;$inx<$size;$inx++){
				if($ls>7){
					$inx--;
					$ls=0;
					$rs=7;
				}
				$nc=ord(substr($buffer,$inx,1));
				$r1=$nc;                 // save $nc
				$nc=($nc<<$ls);          // shift left for $rs
				$nc=($nc & 0x7f)|$r;     // OR carry bits
				$r=($r1>>$rs) & 0x7F;    // shift right and save carry bits
				$ls++;
				$rs--;
				$encoded.=substr($ascii128,$nc,1);
			}
			return $encoded;
		}
		
		public function decode128_custom($buffer,$ascii128){
			$size=strlen($buffer);
			$rs=8;
			$ls=7;
			$r=0;
			$decoded="";
			
			for($inx=0;$inx<$size;$inx++){
				$nc=strpos($ascii128,substr($buffer,$inx,1));
				if($rs>7){
					$rs=1;
					$ls=7;
					$r=$nc;
					continue;
				}
				$r1=$nc;
				$nc=($nc<<$ls) & 0xFF;
				$nc=$nc|$r;
				$r=$r1>>$rs;
				$rs++;
				$ls--;
				$decoded.=chr($nc);
			}
			return $decoded;
		}
		# END ENCODE-DECODE BASE128 #
		
		public function getDevices(){
			$data = $this->db->getSingleRow('CloudDevice',array('FID'=>$this->data_source->code,"FCloudConnection"=>$this->data_source->code));
		}
		
		public function getCloudConnection(){
			if(isset($this->data_source->cid)){
				$id = base64_encode($this->data_source->cid);
			}
			else{
				if(isset($_COOKIE['did'])){
					
				}
			}
		}

		public function removeCookie($name,$path = '/'){
			if(isset($_COOKIE["$name"])){
				unset($_COOKIE["$name"]);
				setcookie("$name", null, -1, $path);
				return true;
			}
			else{
				return false;
			}
		}
		
		public function removeSession($name){
			if(isset($_SESSION["$name"])){
				unset($_SESSION["$name"]);
				return true;
			}
			else {
				return false;
			}
			
		}
		
		public function removeCookSess($name, $path='/'){
			if(isset($_SESSION["$name"])){
				unset($_SESSION["$name"]);
				unset($_COOKIE["$name"]);
				setcookie("$name", null, -1, $path);
				return true;
			}
			else {
				return false;
			}
		}
		// $this->getDataExplode("=",$array, $index);
		function getDataExplode($ch,$str,$index){
			$arr_explode = explode($ch,$str);
			if(isset($arr_explode[$index])){
				return $arr_explode[$index];
			}
			else {
				return "";
			}
		}
		
		public function getSimpleIDTIcket(){
			$t = microtime(true);
			$micro = sprintf("%06d",($t - floor($t)) * 1000000);
			$id = date('ymdHis').strtoupper($this->addLeadingString(3,dechex(substr($micro,0,3))));
			return $id;
		}
		
		// RETRUN 24 digit of UNIX ID :
		public function getComplexIDTicket(){  // duplicate method on Rest.php
			$arrAZ1 = range('A','Z');
			$arrAZ2 = range('A','Z');
			$arrAZ3 = range('A','Z');
			
			$arrs1 = range('A','Z');
			$arrs2 = range('A','Z');
			$arrs3 = range('A','Z');
			
			$a1 = $arrAZ1[rand(0,25)];
			$a2 = $arrAZ2[rand(0,25)];
			$a3 = $arrAZ3[rand(0,25)];
			
			$s1 = $arrs1[rand(0,25)];
			$s2 = $arrs2[rand(0,25)];
			$s3 = $arrs3[rand(0,25)];
			
			$s = $s1.$s2.$s3;
			
			$t = microtime(true);
			$micro = sprintf("%07d",($t - floor($t)) * 10000000);
			$id = date('ymdHis').strtoupper(dechex(substr($micro,0,7)));
			$id = str_pad($id, 24, $a3.$a2.$a1.$s, STR_PAD_RIGHT);
			// 151106214010 3DDBF0 L D C SM4
			return $id;
		}
		
		public function getToken($length=null){
			if(!$length){
				$length = 6;
			}
			$token = "";
			//$codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
			//$codeAlphabet = "0123456789ABCDEFGHIJKLMNOP0123456789ABCDEFGHIJKLMNOP";
			//$codeAlphabet = "0123456789ABCDEFGHIJKLMNP0123456789ABCDEFGHIJKLMNP";
			$codeAlphabet = "0123456789ABCDEFGH0123456789ABCDEFGH";
			$max = strlen($codeAlphabet); // edited

			for ($i=0; $i < $length; $i++) {
				$token .= $codeAlphabet[random_int(0, $max-1)];
			}

			return $token;
		}
		
		#AUTHENTICATION METHOD :

		# base64[code] = Counter2#FIDCloudConnection#YYYYMMDDHHiiss#FIDCloudDevice	
		# base64[scode] = base64[code]#base64[strrev[sessid]]
		public function getAuthCodeFromCookie(){
			return isset($_COOKIE['code']) ? $_COOKIE['code'] : false;
		}
		
		public function getAuthScode(){
			return isset($_COOKIE['scode']) ? $this->base64decode($_COOKIE['scode']) : (isset($this->data_source->scode) ? $this->base64decode($this->data_source->scode) : false);
		}
		
		public function getAuthCodeFromScode(){
			$scode = $this->getAuthScode();
			if($scode!=false){
				$scode_decode = json_decode($scode);
				if(!isset($scode_decode->_fidcc)){
					$scode_decode = $this->base64decode($scode);
					$scode_explode = explode("#",$scode_decode);
					return isset($scode_explode[0]) ? $scode_explode[0] : false;
				}
				return $scode_decode;
			}
			else{
				return false;
			}
		}

		public function getAuthFIDCCFromScode(){
			$code = $this->getAuthCodeFromScode();
			if($code!=false){
				if(!isset($code->_fidcc)){
					$code_decode = $this->base64decode($code);
					$code_explode = explode('#',$code_decode);
					return isset($code_explode[1]) ? $code_explode[1] : false;
				}
				
				return $code->_fidcc;
			}
			else{
				return false;
			}
		}
		public function getAuthFIDCDFromScode(){
			$code = $this->getAuthCodeFromScode();
			if($code!=false){
				if(!isset($code->_fidcd)){
					$code_decode = $this->base64decode($code);
					$code_explode = explode('#',$code_decode);
					return isset($code_explode[3])?$code_explode[3] : "";
				}
				
				return $code->_fidcd;
			}
			else{
				return "";
			}
		}
		public function getAuthFNameCDFromScode(){
			$code = $this->getAuthCodeFromScode();
			if($code!=false){
				if(!isset($code->_fnamecd)){
					$code_decode = $this->base64decode($code);
					$code_explode = explode('#',$code_decode);
					return isset($code_explode[0])?$code_explode[0] : false;
				}
				
				return $code->_fnamecd;
			}
			else{
				return false;
			}
		}
		
		public function base64id(){
			$id = $this->base64urlencode($this->getComplexIDTicket());
			$arr_params = array(
				"id"=>$id,
				"type"=>"base64urlencode",
				"length"=>strlen($id)
			);
			$arr_response = array(
				"return"=> "CX001",
				"returnmessage"=> "Suucess generated!",
				"params"=> $arr_params
			);
			return $arr_response;
		}
		#END AUTHENTICATION METHOD :
		
		
		function redirectURL($url=NULL){
			
			if(isset($url) && !empty($url) && $url!=NULL){
				$url_decode = urldecode($url);
				header("Location: ".$url_decode);
				exit();
			}
			else{
				return false;
			}
			
		}
		
		function isRedirect(){
			if(isset($this->data_source->redirect_url) && !empty($this->data_source->redirect_url)){
				$this->redirectURL($this->data_source->redirect_url);
			}
		}
		
		function getIDKey(){
			$base64encode =  $this->base64urlencode("hiQ-Agent#". $this->data_source->FIDCloudConnection ."#".date('YmdHis')."#".$this->data_source->FIDCloudDevice);
			return array("ID"=>$base64encode,"BID"=>$this->data_source->FIDCloudConnection);
		}
		
		function getSN_v1(){
			return  array("SN"=>$this->generateSN_v1());
		}
		
		function generateSN_v1(){
			$fid = $this->getComplexIDTicket();// eg : 1511061553562C672E67DBBM  be: 1511061553562C672E67DBBM
			$checksum 		= 0;
			$checksum_temp 	= 0;
			for($i=0; $i<strlen($fid);$i++){
				$checksum = $checksum + (ord($fid[$i]) * 7);
			}
			$checksum = $checksum."";
			while(strlen($checksum)>1){
				for($i=0;$i<strlen($checksum);$i++){
					$checksum_temp = $checksum_temp + $checksum[$i];
				}
				$checksum = $checksum_temp."";
				$checksum_temp = 0;
			}
			$SN = $fid . $checksum;
			$SN = chunk_split($SN, 5, "-");
			return trim($SN,"-");
		}
		
		function getSNChecksum(){
			
			if(isset($this->data_source->SN)){
				$checksum = $this->getChecksumFromString($this->data_source->SN,2);
				$checksum = $this->addLeadingString(2,strtoupper(dechex($checksum)),"0");
				
				$SN = $this->data_source->SN . $checksum;
				$SN_detail = array(
					"FIDCloudDevice"=> $SN,
					"FlastUpdate" => date('Y-m-d\TH:i:s')
				);
				$SNBase64encode = $this->base64urlencode(json_encode($SN_detail));
				
				$array_return = array(
					"SN"=> $SN,
					"SNBase64encode" => $SNBase64encode,
					"SNBase64decode" => json_decode($this->base64urldecode($SNBase64encode))
				);
				return $array_return;
			}
			else{
				return array("return"=>"E000","returnmessage"=>"Error request");
			}
		}
		
		function loopgetsnSNChecksum(){
			$i = 10001;
			for($i;$i<=10106;$i++){
				$source_sn = "1803B1".$i."";
				$checksum = $this->getChecksumFromString($source_sn,2);
				$checksum = $this->addLeadingString(2,strtoupper(dechex($checksum)),"0");
				
				$SN = $source_sn . $checksum;
				$SN_detail = array(
					"FIDCloudDevice"=> $SN,
					"FlastUpdate" => date('Y-m-d\TH:i:s')
				);
				$SNBase64encode = $this->base64urlencode(json_encode($SN_detail));
				echo $SN . "\t" . $SNBase64encode."\n";
				$checksum  = "";
			}
			die;
			return array();
		}
		
		public function checksum(){
			if(isset($_COOKIE['ck'])){
				parent::validateCookieChecksum();
			}
			else{
				parent::addChecksumCookie();
			}
			return array("cokie"=>$_COOKIE);
		}
		
		public function remWSchar($str=NULL){
			if($str!=NULL && !is_array($str)){
				$string = trim(preg_replace('/\s+/', ' ', $str));
				return $string;
			}
			else{
				return "";
			}
		}

		// @param array ['hh:mm:ss','hh:mm:ss']
		public function sumTime($times) {
			// loop throught all the times
			foreach ($times as $time) {
				list($hour, $minute, $second) = explode(':', $time);
				$minutes += $hour * 60;
				$minutes += $minute;
			}
			$hours = floor($minutes / 60);
			$minutes -= $hours * 60;
			// returns the time already formatted
			return sprintf('%02d:%02d:00', $hours, $minutes);
		}
		
		public function parseAuthBase64($encode_json){
			$decode_json = $this->base64decode($encode_json);
			$object = json_decode($decode_json);
			return $object;
		}
		public function parseAuthBase64url($encode_json){
			$decode_json = $this->base64urldecode($encode_json);
			$object = json_decode($decode_json);
			return $object;
		}
		
		public function getLooperCard(){
			for($i=1;$i<=1000;$i++){
				echo "L".substr($this->getComplexIDTicket(),1)."\n";
			}
			die;
		}
		
		/* CONFIG ARRANGEMENT */
		public function gethttpserverconfig($name=NULL,$uri=false){
			if($name!=NULL){
				if(isset(HTTP_SERVER[$name])){
					if($uri){
						return HTTP_SERVER[$name]["protocol"]."://".HTTP_SERVER[$name]["host"]. (HTTP_SERVER[$name]["port"]=='443' && HTTP_SERVER[$name]["protocol"]=='https' ? '' : ":".HTTP_SERVER[$name]["port"]) ."/".HTTP_SERVER[$name]["uri"];
					}
					
					return HTTP_SERVER[$name]["protocol"]."://".HTTP_SERVER[$name]["host"] . (HTTP_SERVER[$name]["port"]=='443' && HTTP_SERVER[$name]["protocol"]=='https' ? '' : ":".HTTP_SERVER[$name]["port"]);
				}
			}
			
			return false;
		}
		
		
		/* clientparams method */
		public function getallclientparams($clause,$dbobject=NULL){
		
			$dbobject = new DBManager(DB_CONFIG["clientparams"]);
			$result = $dbobject->getAllRow("cloudclientparams",$clause,['FLastUpdate'=>'ASC']);
			
			return $result;
		}
		public function getclientparams($clause,$dbobject=NULL){
		
			$dbobject = new DBManager(DB_CONFIG["clientparams"]);
			$result = $dbobject->getSingleRow("cloudclientparams",$clause,['FLastUpdate'=>'ASC']);
			
			return $result;
		}
		
		public function insertclientparams($data,$dbobject=NULL){
		
			$dbobject = new DBManager(DB_CONFIG["clientparams"]);
			$result = $dbobject->insert("cloudclientparams",$data);
			
			return $result;
		}
		
		public function updateclientparams($data,$clause=NULL,$dbobject=NULL){
		
			$dbobject = new DBManager(DB_CONFIG["clientparams"]);
			$result = $dbobject->update("cloudclientparams",$data,$clause);
			
			return $result;
		}
		
		public function deleteclientparams($clause,$dbobject=NULL){
			$dbobject = new DBManager(DB_CONFIG["clientparams"]);
			$result = $dbobject->deleteRow("cloudclientparams",$clause);
			
			return $result;
		}
		/* end clientparams method */
		
		
		public function test(){
			header('Content-Type: application/json');
			return ['data'=>$this->data_source->contact_detail];
			//
			//echo '"d":"p"}';
			//die;
			$x = ['remark3'=>12];
			
			echo is_null($x['remark3']) ? "a" : "b";die;
			
			//print_r($this->getDbObj()->getAllRow('branch'));die;
			
			$mc = $this->mcConnect();
			$stat = $mc->get("x",["key"=>"val7"]);
			return ["returnval"=>$stat,"returnmsg"=>"Test OK!"];
		}
		
		// CONVERT TO A VALID MSISDN FORMAT IF 081***  THEN 6281***
		public function getMSISDNFormat($msisdnsource){
			if(isset($msisdnsource) && ! empty($msisdnsource)){
				if(substr($msisdnsource,0,1)=='0'){
					return "62" . substr($msisdnsource,1,strlen($msisdnsource)-1);
				}
				else if(substr($msisdnsource,0,1)=='62'){
					return $msisdnsource;
				}
				else{
					return $msisdnsource;
				}
			}
		}
		
		public function getIPs(){
			//ips must base64 encoded!
			if(isset($_COOKIE['_ips'])){
				$ips = json_decode(base64_decode($_COOKIE['_ips']));
				return $ips;
			}
			else{
				return null;
			}
		}
		
		public static function view($dir, $data=null){
			$smarty = new Smarty();
			if (isset($data)) {
			  if (is_array($data)) {
				foreach ($data as $key => $value) {
				  $smarty->assign($key,$value);
				}
			  }else {
				$smarty->assign( 'response', $data);
			  }
			  $smarty->display('../view/'.$dir);
			}else {
			  $smarty->display('../view/'.$dir);
			}
			exit;
		}
	}
?>