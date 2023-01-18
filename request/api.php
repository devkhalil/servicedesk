<?php
// core functions
include_once "../core.php";
$action=$_POST["action"];
if(isset($_POST["params"]))
$params=$_POST["params"];
if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
	{
		$i=0;
		$jsn=array();
			if(isset($params)){
				$param=$params;
				
				foreach ($param as $key => $value) {
					$$key=$value;
				}
			}
			$error=false;
			
			
			
			switch($action)
			{
					//dynamic embedded form (iframe form)
					case "saveOrphantAttachment":
						//params must be grabbed one by one because this post case hasn't been initiated by neoAjax
						$token=$_POST["token"];
                        $attachment=$_FILES["attachment"];
						$serviceDesk=new ServiceDesk($db);
						$formData=$serviceDesk->getEmbeddedFormData($token);
						$userId=$formData["user_id"];
						$defaultUser=new User($db,$userId);
						$status=$defaultUser->commentTicket(0,"attachment","",2,$attachment);
						$commentId=$defaultUser->lastCommentedTicket->lastCommentId;
						if($status){
							
						$jsn["status"]="success";
						$jsn["commentId"]=$commentId;
					}
					else{
							$jsn["status"]="fail";
							$jsn["message"]=l("error");  
						}
					break;
					case "removeOrphantAttachment":
                        $ticket= new Ticket($db,0);
                        if(isset($commentId)){
                            $ticket->removeComment($commentId);
                        }
						$jsn["status"]="success";
						$jsn["commentId"]=$commentId;
					break;
					case "saveEnquire":
						$serviceDesk=new ServiceDesk($db);
						$formData=$serviceDesk->getEmbeddedFormData($token);
						$companyId=$formData["company_id"];
						$userId=((int)$formData["user_id"]==0)?$formData["assignee_id"]:$formData["user_id"];
						// $userId=$formData["user_id"];
						$defaultTopicIds=explode(",",$formData["default_topics"]);
						$topic=$topic_id;
						$user_data=(object)[];
						if(isset($name))
							$user_data->name=$name;
						if(isset($email))
							$user_data->email=$email;
						if(isset($phone))
							$user_data->phone=$phone;
						if(isset($location))
							$user_data->location=$location; 
						if(isset($message))
							$user_data->message=$message;
						if(isset($topic_id))
							$user_data->topic_id=$topic_id;
						if(isset($attachementCommentsIds))
							$user_data->attachementCommentsIds=$attachementCommentsIds;

						if(!$formData["create_accounts"]){
							if(in_array($user_data->topic_id,$defaultTopicIds )){
								$user=new User($db,$userId);
								$serviceDesk->createEmbedFormTicket($user,$user_data,$formData,"UNAUTHENTICATED");
								$jsn["status"]="success";
							}
							else{
								$jsn["status"]="fail"; 
								$jsn["message"]=l("wrong query");
							}
						}else{ 
							$company=new Company($db,$companyId);
							$companyUserID=$company->loadUserByEmail($email);
							if(!is_null($companyUserID)){
								$user=new User($db,$companyUserID);
								$serviceDesk->createEmbedFormTicket($user,$user_data,$formData,"REGISTRED");
							}else{
								$newUser=new User($db);
								$password=$serviceDesk->password_generate(10);
								$newUser->register($name, md5($password), $email, $phone, $companyId, 0,''); 

								if(in_array($user_data->topic_id,$defaultTopicIds )){
									$newUser->setGroup($user_data->topic_id);
								}
								$response=$serviceDesk->createEmbedFormTicket($newUser,$user_data,$formData,"REGISTRED");
								$newUser->loadInformation();
								// $validationCode=$newUser->generateValidationCode();
								// $customData=(object)array("validationCode"=>$validationCode);
								// $registrationEmail=new DeskEmail($db,$currentCompany,"EmbedFormRegistration");
								// $registrationEmail->loadTemplate();
								// $registrationEmail->applyScheme("recipient",$newUser->information);
								// $registrationEmail->applyScheme("company",$currentCompany->information);
								// $registrationEmail->applyScheme("login",$customData); 
								// $registrationEmail->addSubject("Confirm your email");
								// $registrationEmail->addRecipient($newUser);
								// $registrationEmail->send();


									// $validationCode=$newUser->generateValidationCode();
									$validationCode=generate_random_password();
									passReset($newUser,$validationCode);
									$newUser->forceVerifyAccount();
									$customData=(object)array("token"=>$validationCode);
									$registrationEmail=new DeskEmail($db,$currentCompany,"EmbedFormRegistration");
									$registrationEmail->loadTemplate();
									$registrationEmail->applyScheme("recipient",$newUser->information);
									$registrationEmail->applyScheme("company",$currentCompany->information);
									// $registrationEmail->applyScheme("login",$customData);
									$registrationEmail->applyScheme("newpassword",$customData); 
									$registrationEmail->addSubject("Confirm your email");
									$registrationEmail->addRecipient($newUser);
									$response=$registrationEmail->send(); 
								$jsn["status"]="success";
							}
						}
					break;
				
			}
			$response["ajaxStatus"]="success";
			$response[$action]=$jsn;
		die(json_encode($response));
	}
?>