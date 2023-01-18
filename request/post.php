<?php
// core functions
include_once "../core.php";
$actions=$_POST["actions"];
if(isset($_POST["params"]))
$params=$_POST["params"];
$noLogin=isset($_POST["noLogin"]) ? true:false;

# code...
if(ONLINE && !$noLogin)
{	
	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
	{
		$i=0;
		$jsn=array();
		foreach ($actions as $action) {
			if(isset($params[$i])){
				$param=$params[$i];
				
				foreach ($param as $key => $value) {
					$$key=$value;
				}
			}
			$error=false;

			switch($action)
			{
				case "ticketSeen":
					$onlineUser->seeTicket($id);
					$jsn["status"]="success";
				break;
				case "switchCompany":
					$chosenCompany=new Company($db,$companyId);
					$originalLink=REQUESTED_URL;
					$attaignableCompanies=getAttaignableCompanies($onlineUser);
					if(isset($_SESSION["attaignableCompanies"]) && $_SESSION["attaignableCompanies"]!=""){
						$attaignableCompanies=array_merge($attaignableCompanies,$_SESSION["attaignableCompanies"]);
					}
					$jsn["token"]=createSessionToken(IP,$onlineUser,$originalLink,$companyId,$attaignableCompanies);
					$jsn["status"]="success";
				break;
				case "reopenTicket":
					$attachement=(isset($_FILES["attachement"]))?$_FILES["attachement"]:0;
					$closedTicket= new Ticket($db,$id);
					if($closedTicket->isClosed()){
						if(!$onlineUser->isTechie())
						$technote="";
						$onlineUser->reopenTicket($closedTicket);
						
						$commentedTicket=$closedTicket;
						$owner=$commentedTicket->getOwner();
						$owner->loadInformation();
						$assignee=$commentedTicket->getAssignee();
						$commentedTicket->loadData();
						$customData=(object)array("ticket"=>$commentedTicket);
						$subject=$commentedTicket->data["subject"];
						
						$email=new DeskEmail($db,$currentCompany,"TicketOpen");
						$email->loadTemplate();
						$email->applyScheme("company",$currentCompany->information);
						$email->applyScheme("ticket",$customData);
						$notificationTemplates=$email->getTemplates(); //grabbing what has been filled so far before we fill the next recipient information
						if(!$owner->is($onlineUser)){ // if the user who updated the ticket is himself the owner then don't send him an email
							$email->applyScheme("recipient",$owner->information);
							$email->setTicketSubject($commentedTicket,"Opened"); 
							$email->addRecipient($owner);
							$response=$email->send();
						}
						
						if(!$assignee->isEmpty() && !$assignee->is($onlineUser)){ //if an assignee exists and he's not the user who made the comment then send him an email
							$email->resetTemplates();
							$email->setTemplates($notificationTemplates);
							$assignee->loadInformation();
							$email->applyScheme("recipient",$assignee->information);
							$email->addRecipient($assignee);
							$response=$email->send();
						}elseif( $assignee->isEmpty() ){
							$commentedTicketGroup=$commentedTicket->getGroup();
							$commentedTicketGroup->loadInformation();
							$commentedTicketGroupUsers=$commentedTicketGroup->getUsers();
							$topicEmail=new DeskEmail($db,$currentCompany,"TopicUpdate");
							$topicEmail->loadTemplate();
							$topicEmail->applyScheme("company",$currentCompany->information);
							$topicEmail->applyScheme("ticket",$customData);
							$topicNotificationTemplates=$topicEmail->getTemplates(); 
							
							foreach ($commentedTicketGroupUsers as $gu) {
								if(!$gu->is($onlineUser)){
									
									$topicEmail->resetTemplates(); 
									$topicEmail->setTemplates($topicNotificationTemplates);
									$topicEmail->applyScheme("recipient",$gu->information);
									$topicEmail->applyScheme("group",$commentedTicketGroup->information);
									$topicEmail->setTicketSubject($commentedTicket,"Opened");
									$topicEmail->addRecipient($gu);
									$response=$topicEmail->send();
								}
							}
						}
						$jsn["status"]="success";
					}
					else{
						$error=true;
						$jsn["status"]="ticket already open";
					}
				break;
				
				case "claimTicket":
					$onlineUser->claimTicket($id);
					$commentedTicket=new Ticket($db,$id);
					$owner=$commentedTicket->getOwner();
					$assignee=$commentedTicket->getAssignee();
					$owner->loadInformation();
					$commentedTicket->loadData();
					$subject=$commentedTicket->data["subject"];
					$customData=(object)array("ticket"=>$commentedTicket);
					
					$email=new DeskEmail($db,$currentCompany,"TicketUpdate");
					$email->loadTemplate();
					$email->applyScheme("company",$currentCompany->information);
					$email->applyScheme("ticket",$customData);
					$notificationTemplates=$email->getTemplates(); //grabbing what has been filled so far before we fill the next recipient information
					if(!$owner->is($onlineUser)){ // if the user who updated the ticket is himself the owner then don't send him an email
						$email->applyScheme("recipient",$assignee->information);
						$email->setTicketSubject($commentedTicket,"Updated");
						$email->addRecipient($assignee);
						$response=$email->send();
					}
					
					$jsn["status"]="success";
				break;
				
				case "reassignTicket":
					$status=($status==true)?2:1;
					$attachement=(isset($_FILES["attachement"]))?$_FILES["attachement"]:0;
					$commentedTicket=new Ticket($db,$ticketid);
					$commentedTicket->loadData();
					if($commentedTicket->data["assignedto"]==='0' && $assignee==""){
						$error=true;
						$jsn["status"]=l("You must choose a user");
						$jsn["code"]=1;
					}
					else{
						if($assignee==""){
							$onlineUser->unassignTicket($commentedTicket,$comment,$technote,$status,$attachement); 
						}
						else
							$onlineUser->reassignTicket($commentedTicket,$comment,$technote,$assignee,$status,$attachement); 
						$owner=$commentedTicket->getOwner();
						$assignee=$commentedTicket->getAssignee();
						$owner->loadInformation();
						$assignee->loadInformation();
						$customData=(object)array("ticket"=>$commentedTicket);
						$subject=$commentedTicket->data["subject"];
						$comment=$commentedTicket->data["comment"];
						if((!$assignee->isEmpty() && !$assignee->is($onlineUser) ) || $assignee->isTechie()){ //if an assignee exists and he's not the user who made the comment then send the email
							$email=new DeskEmail($db,$currentCompany,"TicketUpdate");
							$notificationTemplates=$email->getTemplates(); //grabbing what has been filled so far before we fill the next recipient information
							$email->loadTemplate();
							$email->applyScheme("company",$currentCompany->information);
							$email->applyScheme("ticket",$customData);
							$email->applyScheme("recipient",$assignee->information);
							$email->setTicketSubject($commentedTicket,"Updated");
							$email->addRecipient($assignee); 
							$email->send();
						}
						if($comment!=""){
							$commentEmail=new DeskEmail($db,$currentCompany,"TicketUpdate");
							$ownerTemplate=$commentEmail->getTemplates(); 
							$commentEmail->loadTemplate(); 
							$commentEmail->addRecipient($owner);   
							$commentEmail->setTemplates($ownerTemplate);
							$commentEmail->applyScheme("company",$currentCompany->information);
							$commentEmail->applyScheme("ticket",$customData);
							$commentEmail->applyScheme("recipient",$owner->information);
							$commentEmail->setTicketSubject($commentedTicket,"Updated");
							$response=$commentEmail->send();
						}

						if($assignee->isEmpty()){
							$commentedTicketGroup=$commentedTicket->getGroup();
							$commentedTicketGroup->loadInformation();
							$commentedTicketGroupUsers=$commentedTicketGroup->getUsers();
							$topicEmail=new DeskEmail($db,$currentCompany,"TopicUpdate");
							$topicEmail->loadTemplate();
							$topicEmail->applyScheme("company",$currentCompany->information);
							$topicEmail->applyScheme("ticket",$customData);
							$topicNotificationTemplates=$topicEmail->getTemplates(); 
							
							foreach ($commentedTicketGroupUsers as $gu) {
								if(!$gu->is($onlineUser)){
									
									$topicEmail->resetTemplates(); 
									$topicEmail->setTemplates($topicNotificationTemplates);
									$topicEmail->applyScheme("recipient",$gu->information);
									$topicEmail->applyScheme("group",$commentedTicketGroup->information);
									$topicEmail->setTicketSubject($commentedTicket,"Updated");
									$topicEmail->addRecipient($gu);
									$response=$topicEmail->send();
								}
							}
						}

						$jsn["status"]="success";
					}
				break;
				
				case "changeTopic":
					if($onlineUser->has_right(CHANGE_TOPIC)){
						
						$status=($status==true)?2:1;
						$attachement=(isset($_FILES["attachement"]))?$_FILES["attachement"]:0;
						$onlineUser->changeTicketTopic($ticketid,$comment,$technote,$topic,$status,$attachement); 
						
						$commentedTicket=new Ticket($db,$ticketid);
						$owner=$commentedTicket->getOwner();
						$assignee=$commentedTicket->getAssignee();
						$owner->loadInformation();
						$commentedTicket->loadData();
						$customData=(object)array("ticket"=>$commentedTicket);
						$subject=$commentedTicket->data["subject"];
						
						$email=new DeskEmail($db,$currentCompany,"TicketUpdate");
						$email->loadTemplate();
						$email->applyScheme("company",$currentCompany->information);
						$email->applyScheme("ticket",$customData);
						
						$email->setTicketSubject($commentedTicket,"Updated");
						$notificationTemplates=$email->getTemplates(); //grabbing what has been filled so far before we fill the next recipient information
						if(!$owner->is($onlineUser)){ // if the user who updated the ticket is himself the owner then don't send him an email
							$email->applyScheme("recipient",$owner->information);
							$email->addRecipient($owner);
							$response=$email->send();
						}
						if(!$assignee->isEmpty() && !$assignee->is($onlineUser) ){
							$email->resetTemplates();
							$email->setTemplates($notificationTemplates);
							$assignee->loadInformation();
							$email->applyScheme("recipient",$assignee->information);
							$email->addRecipient($assignee);
							$response=$email->send();
						}
						$jsn["status"]="success";
					}
					else{
						$error=true;
						$jsn["status"]="not allowed";
					}
				break;
				
				case "commentTicket":
					$attachement=(isset($_FILES["attachement"]))?$_FILES["attachement"]:0;
					if(!$onlineUser->isTechie()){ //if the user is a simple, then the status is automaticaly waiting on user 
						$status=2;
					}
					if(!in_array($status,array(1,2,3))){ // if status is incorrect set it to waiting on user
						$status=2;
					}
					$commentedTicket= new Ticket($db,$ticketid);
					$owner=$commentedTicket->getOwner();
					$owner->loadInformation();
					$assignee=$commentedTicket->getAssignee();
					$commentedTicket->loadData();
					$customData=(object)array("ticket"=>$commentedTicket);
					$subject=$commentedTicket->data["subject"];
					if(!$commentedTicket->isClosed()){ //closed tickets cannot be updated
						if($status==3 && !$commentedTicket->isClosed()){
							$onlineUser->closeTicket($commentedTicket,$comment,$technote,$attachement);
							if(!$owner->is($onlineUser)){ // if the user who updated the ticket is himself the owner then don't send him an email
								$email=new DeskEmail($db,$currentCompany,"TicketClose");
								$email->loadTemplate();
								$email->applyScheme("company",$currentCompany->information);
								$email->applyScheme("ticket",$customData);
								$notificationTemplates=$email->getTemplates(); //grabbing what has been filled so far before we fill the next recipient information
								$email->applyScheme("recipient",$owner->information);
								$email->setTicketSubject($commentedTicket,"Closed");
								$email->addRecipient($owner);
								$response=$email->send();
							}
						}
						else{
							if($commentedTicket->isClosed())
							$status=3;
							$status=$onlineUser->commentTicket($ticketid,$comment,$technote,$status,$attachement);
							if($status!==true){
								$error=true;
								$jsn["status"]=$status;
								break;
							}
							if($comment!=""){
								$email=new DeskEmail($db,$currentCompany,"TicketUpdate");
								if($email!=false){
									$email->loadTemplate();
									$email->applyScheme("company",$currentCompany->information);
									$email->applyScheme("ticket",$customData);
									$notificationTemplates=$email->getTemplates(); //grabbing what has been filled so far before we fill the next recipient information
								if(!$owner->is($onlineUser)){ // if the user who updated the ticket is himself the owner then don't send him an email
									
									$email->applyScheme("recipient",$owner->information);
									
									$email->setTicketSubject($commentedTicket,"Updated");
									$email->addRecipient($owner);
									$response=$email->send();
								}
								if(!$assignee->isEmpty() && !$assignee->is($onlineUser)){ //if an assignee exists and he's not the user who made the comment then send the email
									
									$email->resetTemplates();
									$email->setTemplates($notificationTemplates);
									$assignee->loadInformation();
									$email->applyScheme("recipient",$assignee->information);
									$email->addRecipient($assignee);
									$response=$email->send();
								}else{
									$commentedTicketGroup=$commentedTicket->getGroup();
									$commentedTicketGroup->loadInformation();
									$commentedTicketGroupUsers=$commentedTicketGroup->getUsers();
									$topicEmail=new DeskEmail($db,$currentCompany,"TopicUpdate");
									$topicEmail->loadTemplate();
									$topicEmail->applyScheme("company",$currentCompany->information);
									$topicEmail->applyScheme("ticket",$customData);
									$topicNotificationTemplates=$topicEmail->getTemplates(); 
									
									foreach ($commentedTicketGroupUsers as $gu) {
										if(!$gu->is($onlineUser)){
											
											$topicEmail->resetTemplates();
											$topicEmail->setTemplates($topicNotificationTemplates);
											$topicEmail->applyScheme("recipient",$gu->information);
											$topicEmail->applyScheme("group",$commentedTicketGroup->information);
											$topicEmail->setTicketSubject($commentedTicket,"Updated");
											$topicEmail->addRecipient($gu);
											$response=$topicEmail->send();
										}
									}
								}
							}
						}
						$jsn["status"]="success";
					}
					}
					else{
						$error=true;
						$jsn["status"]=l("You can't update a closed ticket");
					}
				break;
				case "closeTicket":
					$attachement=(isset($_FILES["attachement"]))?$_FILES["attachement"]:0;
					$closingTicket= new Ticket($db,$ticketid);
					if(!$closingTicket->isClosed()){
						if(!$onlineUser->isTechie())
						$technote="";
						$onlineUser->closeTicket($closingTicket,$comment,$technote,$attachement);
						
						$commentedTicket=$closingTicket;
						$owner=$commentedTicket->getOwner();
						$owner->loadInformation();
						$assignee=$commentedTicket->getAssignee();
						$commentedTicket->loadData();
						$customData=(object)array("ticket"=>$commentedTicket);
						$subject=$commentedTicket->data["subject"];
						
						$email=new DeskEmail($db,$currentCompany,"TicketClose");
						$email->loadTemplate();
						$email->applyScheme("company",$currentCompany->information);
						$email->applyScheme("ticket",$customData);
						$notificationTemplates=$email->getTemplates(); //grabbing what has been filled so far before we fill the next recipient information
						$email->applyScheme("recipient",$owner->information);
						if(!$owner->is($onlineUser)){ // if the user who updated the ticket is himself the owner then don't send him an email
							$email->setTicketSubject($commentedTicket,"Closed");
							$email->addRecipient($owner);
							$response=$email->send();
						}
						
						// if(!$assignee->isEmpty() && !$assignee->is($onlineUser)){
						// 	$email->resetTemplates();
						// 	$email->setTemplates($notificationTemplates);
						// 	$assignee->loadInformation();
						// 	$email->applyScheme("recipient",$assignee->information);
						// 	$email->addRecipient($assignee);
						// 	$response=$email->send();
						// }
						$jsn["status"]="success";
					}
					else{
						$error=true;
						$jsn["status"]=l("Ticket already closed");
					}
				break;
				
				
				
				
				case "grantAccess":
					$targetUser=new User($db);
					$targetUser->setEmail($email);
					if($targetUser->exists()){
						$targetUser->loadFromEmail();
						if($onlineUser->is($targetUser)){
							$error=true;
							$jsn["status"]="failed";
							$jsn["code"]=2;
							$jsn["message"]=l("You can't add your own email");
						}
						else{
							if($onlineUser->isAllowedUser($targetUser)){
								$error=true;
								$jsn["status"]="failed";
								$jsn["code"]=3;
								$jsn["message"]=l("Access has already be granted to this email");
								
							}
							else{
								
								$targetUser->allowAccessTo($onlineUser);
								$email=new DeskEmail($db,$currentCompany,"GrantAccess");
								$email->loadTemplate();
								$email->applyScheme("company",$currentCompany->information);
								$email->applyScheme("recipient",$targetUser->information);
								$email->addsubject(l("You can now access someone else's account"));
								$email->addRecipient($targetUser);
								$response=$email->send();
								
								$jsn["status"]="success";
								$jsn["message"]=l("Access granted");
							}
						}
					}
					else{
						$error=true;
						$jsn["status"]="failed";
						$jsn["code"]=1;
						$jsn["message"]=l("User email doesn't exist");
						
					}
					
				break;
				case "saveNotification":
					if ($onlineUser->isTechie()){ //must test user right here
						if(isset($notificationType)&&isset($notificationName)&&isset($notificationId)&&isset($emailTemplate)&&isset($smsTemplate)){
							$currentCompany->saveNotification($notificationType,$notificationName,$notificationId,$emailTemplate,$smsTemplate);
							$jsn["status"]="success";
							// $template=$_POST["template"];
							// $template_type=$_POST["template_type"];
							// $jsn["OK"]=update_template($template_type,$template);
						}
						else{
							$jsn["message"]=l("There are empty fields.");
						}
					}else{
						$jsn["status"]="failed";
						$jsn["code"]=1;
						$jsn["message"]=l("You don't have the rights to access this part.");
					}
					
				break;
				
				case "saveTicket":
					if(!isset($onlineUser->isTechie)){
						$onlineUser->isTechie=1;
					}
					if(isset($assignedto) ){
						if($assignedto=="")
						$assignedto="0";
					}else{
						$assignedto="0";
					}
					$status=($onlineUser->isTechie)?2:1;
					
					// check empty fields
					if(empty($subject) || empty($description))
					{
						$jsn["msg"] = "Subject or description is empty.";
					}
					else
					{	
						$attachement=(isset($_FILES["attachement"]))? $_FILES["attachement"]:0;
						if(!isset($ownedbyemail) || $ownedbyemail==""){
							$owner=$onlineUser;
						}
						else{
							$owner=new User($db);
							$owner->setEmail($ownedbyemail);
							$owner->loadIdFromEmail();
						}
						if(!$owner->exists()){
							$jsn["OK"]=false;
							$jsn["code"]=1;
							$jsn["msg"] = l("User email doesn't exist");
						}
						else{
							$newTicket=$onlineUser->insertTicket($subject, $description, $severity,$assignedto,$owner->id,$status,$topic,$attachement);
							$ticketGroup=new Group($db,$topic);
							$ticketGroup->loadInformation();
							$groupUsers=$ticketGroup->getUsers();
							$customData=(object)array("ticket"=>$newTicket);
							$newTicket->loadData();
							$owner->loadInformation();
							$email=new DeskEmail($db,$currentCompany,"TicketOpen");
							$email->loadTemplate();
							$email->applyScheme("company",$currentCompany->information); 
							$email->applyScheme("ticket",$customData);
							$notificationTemplates=$email->getTemplates(); //grabbing what has been filled so far before we fill the next recipient information
							// richard asked to remove this condition: 02/02/2021 14:10
							// if(!$owner->is($onlineUser)){ // if the user who created the ticket is himself the owner then don't send him an email
								$email->applyScheme("recipient",$owner->information);
								$email->setTicketSubject($newTicket,"Opened");
								$email->addRecipient($owner);
								$response=$email->send();
							// }
							if(isset($assignedto) && $assignedto!==0 && $assignedto!="" && $assignedto ){
								$assignee=new User($db,$assignedto);
								if(!$assignee->is($onlineUser)){
									
									$email->resetTemplates();
									$email->setTemplates($notificationTemplates);
									$assignee->loadInformation();
									$email->applyScheme("recipient",$assignee->information);
									$email->addRecipient($assignee);
									$response=$email->send();
								}
							}
							else{
								//an email is sent to the new ticket's topic members only if the new ticket hasn't been assigned to someone
								
								$topicEmail=new DeskEmail($db,$currentCompany,"TopicUpdate");
								$topicEmail->loadTemplate();
								$topicEmail->applyScheme("company",$currentCompany->information);
								$topicEmail->applyScheme("ticket",$customData);
								$topicNotificationTemplates=$topicEmail->getTemplates(); 
								
								foreach ($groupUsers as $gu) {
									if(!$gu->is($onlineUser)){
										
										$topicEmail->resetTemplates();
										$topicEmail->setTemplates($topicNotificationTemplates);
										$topicEmail->applyScheme("recipient",$gu->information);
										$topicEmail->applyScheme("group",$ticketGroup->information);
										$topicEmail->setTicketSubject($newTicket,"Updated");
										$topicEmail->addRecipient($gu);
										$response=$topicEmail->send();
									}
								}
							}
							$jsn["code"]=0;
							$jsn["OK"] = true;
							$jsn["id"] = $newTicket->id;
							$jsn["message"] = l("Ticket added");
						}
					}
				break;
				
				
				case "removeParent":
					$parent=new User($db,$userid);
					$onlineUser->removeParent($parent);
					$jsn["status"]="success";
				break;
				
				
				
				case "removeChild":
					$parent=new User($db,$userid);
					$onlineUser->removeChild($parent);
					$jsn["status"]="success";
				break;
				
				case "deleteUser":
					$user=new User($db,$userid);
					$hasTickets=$user->hasTicketHist();
					
					
					// if($hasTickets==false){
						$onlineUser->deleteUser($user);
						$jsn["code"]=1;
						$jsn["message"]=l("User deleted successfully");
						$jsn["status"]="success";
					// }else{
					// 	$jsn["code"]=2;
					// 	$jsn["message"]=l("This account has tickets");
					// 	$jsn["status"]="fail";
					// }
					
				break;
				case "sendEmailVerification":
					$newUser=new User($db,$userid);
					$newUser->loadInformation();
					$newUser->unverify();
					$validationCode=$newUser->generateValidationCode();
					$customData=(object)array("validationCode"=>$validationCode);
					$registrationEmail=new DeskEmail($db,$currentCompany,"Registration");
					$registrationEmail->loadTemplate();
					$registrationEmail->applyScheme("recipient",$newUser->information);
					$registrationEmail->applyScheme("company",$currentCompany->information);
					$registrationEmail->applyScheme("login",$customData);
					$registrationEmail->addSubject("Confirm your email");
					$registrationEmail->addRecipient($newUser);
					$response=$registrationEmail->send();
					if($response){
						$jsn["code"]=1;
						$jsn["message"]=l("The account verification email has been sent");
						$jsn["status"]="success";
					}else{
						$jsn["code"]=2;
						$jsn["message"]=l("An unexpected error happened, try again.");
						$jsn["status"]="fail";
					}
				break;
				
				case "accessAccount":
					$user=new User($db,$userid);
					$user->loadInformation();
					
					if($user->isAllowedUser($onlineUser)){
						
						if($user->isActive()){
							$_SESSION["User"]=$user->information;
							refresh_sessions();
							$jsn["status"]="success";
						}
						else{
							$error=true;
							$jsn["status"]="fail";
							$jsn["target"]="#accessible_accounts tr[data-id=$userid] span";
							$jsn["code"]=1;
							$jsn["message"]=l("This account is disabled");
						}
					}
					else{
						$error=true;
						$jsn["status"]="fail";
						$jsn["code"]=2;
						$jsn["message"]=l("You're not allowed to access this account");
						
					}
				break;
				
				case "sendPhoneVerifyCode":
					if(isset($phone)&&$phone!=""){
						$real_number=$phone;
						$phone=str_replace("(0)","",$phone);
						$lastValidation=$onlineUser->getLastValidation();
						$lastValidationTime=strtotime($lastValidation);
						$hoursToAdd = 24;
						$secondsToAdd = $hoursToAdd * (60 * 60);
						$whenAllowedTime= $lastValidationTime + $secondsToAdd;
						$whenAllowed=date('Y-m-d H:i:s', $whenAllowedTime);
						$now=date('Y-m-d H:i:s');
						
						
						$isAllowed=($now>$whenAllowed)?true:false;
						if($isAllowed||is_null($lastValidation) ){
							$randomCode=randomChars("number",6);
							$notification=new DeskEmail($db,$currentCompany,"");
							$notification->sendVerificationCode($randomCode,$phone);
							$onlineUser->updatePhoneVerification($real_number,$randomCode);
							refresh_sessions();
							$jsn["status"]="success";
						}else{
							$date1 = new DateTime($now);
							$date2 = new DateTime($whenAllowed);
							$diff = $date1->diff($date2);
							$month    = $diff->format('%m');
							$day      = $diff->format('%d');
							$hour     = $diff->format('%h');
							$min      = $diff->format('%i');
							$sec      = $diff->format('%s');
							$timeLeft=$day." days, ".$hour." hours and ".$min." minutes";
							$jsn["status"]="error";
							$jsn["message"]=l("You're not allowed to verify your phone number, try again in ".$timeLeft);  
						}		
					}
				break;
				case "verifyCode":
					//this part need changes
					if(isset($code)){
						if($onlineUser->verifyCode($code)){
							if($onlineUser->updateCode()){
								refresh_sessions();
								$jsn["status"]="success";
							}else{
								$jsn["message"]=l("An error happened, Please retry in a few minutes");  
								$jsn["status"]="false";
								$jsn["code"]=3;
							}
							
						}else{
							$jsn["message"]=l("The code is incorrect");  
							$jsn["status"]="false";
							$jsn["code"]=1;
						}
					}else{
						$jsn["message"]=l("The code field is required");  
						$jsn["code"]=2;
						$jsn["status"]="false";
					}
				break;
				case "updatePassword":
					//this part need changes
					
					if(isset($password)&&isset($confirm_password)&&isset($actual_password)){
						if($password==$confirm_password){
							if($onlineUser->checkPassword($actual_password)){
								$onlineUser->updatePassword($password);
								$jsn["message"]=l("Password updated successfully");  
								$jsn["status"]="true";
								$jsn["code"]=4;
							}else{
								$jsn["message"]=l("Incorrect password.");  
								$jsn["status"]="false";
								$jsn["code"]=3;
							}
						}else{
							$jsn["message"]=l("passwords doesn't match.");  
							$jsn["status"]="false";
							$jsn["code"]=1;
						}
					}else{
						$jsn["message"]=l("All fields are required");  
						$jsn["code"]=2;
						$jsn["status"]="false";
					}
				break;  
				case "sendEmailVerifyCode":
					
					if(isset($email)){
						if($email!=$onlineUser->information['email']){
							if($onlineUser->information['phone_validation']==1){
								if(!$onlineUser->checkEmailExistance($email)){
									$phone=$onlineUser->information['phone'];
									$phone=str_replace("(0)","",$phone);
									$_SESSION["tmpemail"]=$email;
									$lastValidation=$onlineUser->getLastEmailValidation();
									$lastValidationTime=strtotime($lastValidation);
									$hoursToAdd = 24;
									$secondsToAdd = $hoursToAdd * (60 * 60);
									$whenAllowedTime= $lastValidationTime + $secondsToAdd;
									$whenAllowed=date('Y-m-d H:i:s', $whenAllowedTime);
									$now=date('Y-m-d H:i:s');
									$isAllowed=($now>$whenAllowed)?true:false;
									if($isAllowed||is_null($lastValidation) ){
										
										
										$randomCode=randomChars("number",6);
										// $notification=new DeskEmail($db,$currentCompany,"");
										// $notification->sendVerificationCode($randomCode,$phone);
										// refresh_sessions();
										// $jsn["status"]="success";
										// $jsn["code"]=1;
										
										
										$onlineUser->updateEmailVerification($randomCode);
										$sendEmail=new DeskEmail($db,$currentCompany,"ChangeEmail");
										$sendEmail->loadTemplate();
										$sendEmail->setForcedNotifType("email");
										$sendEmail->applyScheme("company",$currentCompany->information);
										
										$customData=(object)array("validationCode"=>$randomCode);
										$sendEmail->applyScheme("changeEmail",$customData);
										$sendEmail->applyScheme("recipient",$onlineUser->information);
										$sendEmail->addSubject("Confirm your new email");
										$tempUser=$onlineUser;
										$tempUser->information["email"]=$email;
										$sendEmail->addRecipient($tempUser);
										$response=$sendEmail->send();
										
										refresh_sessions(); 
										$jsn["status"]="success";
										$jsn["code"]=1;
									}else{
										$date1 = new DateTime($now);
										$date2 = new DateTime($whenAllowed);
										$diff = $date1->diff($date2);
										$month    = $diff->format('%m');
										$day      = $diff->format('%d');
										$hour     = $diff->format('%h');
										$min      = $diff->format('%i');
										$sec      = $diff->format('%s');
										$timeLeft=$day." days, ".$hour." hours and ".$min." minutes";
										$jsn["status"]="error";
										$jsn["code"]=2;
										$jsn["message"]=l("You're not allowed to change your email, try again in ".$timeLeft);  
									}	
								}else{
									$jsn["message"]=l("The email you entred already exists");  
									$jsn["code"]=3;
									$jsn["status"]="false";
								}
							}else{
								$jsn["message"]=l("You have to verify your phone number before changing your email");  
								$jsn["code"]=3;
								$jsn["status"]="false";
							}
						}else{
							$jsn["message"]=l("Nothing to process");  
							$jsn["code"]=3;
							$jsn["status"]="false";
						}
					}else{
						$jsn["message"]=l("All fields are required");  
						$jsn["code"]=4;
						$jsn["status"]="false";
					} 
				break;
				case "VerifyEmailCode":
					if(isset($smscode)){
						if($onlineUser->verifyEmailCode($smscode)){
							if($onlineUser->updateEmail($_SESSION["tmpemail"])){
								refresh_sessions();
								$jsn["status"]="success";
								$jsn["message"]=l("Your email have been updated successfully");  
								$jsn["code"]=1;
							}else{
								$jsn["message"]=l("An error happened, Please retry in a few minutes");  
								$jsn["status"]="false";
								$jsn["code"]=2;
							}
							
						}else{
							$jsn["message"]=l("The code is incorrect");  
							$jsn["status"]="false";
							$jsn["code"]=3;
						}
					}
				break;
				case "rememberWaitingOnMeAjax":
					$waitingOnMeOnly=$waitingOnMeOnly=="true";
					$_SESSION["waiting_on_me_only"]=$waitingOnMeOnly; // waiting on me only checkbox
				break;
				case "remembershowClosedAjax": 
					$showClosed=$showClosed=="true";
					$_SESSION["show_closed"]=$showClosed; // show closed checkbox
				break;


				case "generateEmbeddedForm": 
					$servicedesk=new ServiceDesk($db);
					$assigneeID=(isset($assigneeID)&&$assigneeID!='')?$assigneeID:0;
					$userId=(isset($userId)&&$userId!='')?$userId:0;
					$token=$servicedesk->generateEmbeddedForm($title,$attachments,$locationTitle,$locationDescription,$jobTitle,$jobDescription,$userId,$defaultTopics,$createAccounts,$assigneeID);

					if($token!==false){
						$jsn["status"]="success";
						$jsn["link"]=EMBED_LINK.$token;
						$iframeCode=EMBED_LINK_IFRAME;
						$iframeCode=str_replace("[link]",EMBED_LINK.$token,$iframeCode);
						$jsn["iframe"]=$iframeCode;
					}
					else{
						$jsn["code"]=2;
						$jsn["message"]=l("An unexpected error happened, try again.");
						$jsn["status"]="fail";
					}
				break;
			}
			
			
			$jsn_array[$action]=$jsn;
			$i++;
		}
		// send response asin json format
		if($error)
		$jsn_array["ajaxStatus"]="failed";
		else
		$jsn_array["ajaxStatus"]="success";
		die(json_encode($jsn_array));
	}
	else
	{
		// if not an ajax request, redirect to homepage
		header("Location: ".SITE_URL); 
		exit;
	}
}
else
{
	//post request that doesn't requires login
	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
	{
		$i=0;
		$jsn=array();
		foreach ($actions as $action) {
			if(isset($params[$i])){
				$param=$params[$i];
				
				foreach ($param as $key => $value) {
					$$key=$value;
				}
			}
			$error=false;
			
			
			
			switch($action)
			{
				case "registerCompany":
					
					$newCompany=new Company($db);
					$hostName=$HostName2.$HostName3;
					if($newCompany->hostExists($hostName,$db)){
						$jsn["status"]="fail";
						$jsn["message"]=l("host already exist");  
						$error=true;
					}
					else{

						$CompanyTel=preg_replace('/[^0-9.]+/', '', $CompanyTel);
						$isHttps=$HostName1;
						$companyData=array(
							'CompanyName' => $CompanyName,
							'CompanyAddress' => $CompanyAddress,
							'CompanyPostCode' => $CompanyPostCode,
							'CompanyCounty' => $CompanyCounty,
							'CompanyCountry' => $CompanyCountry,
							'CompanyTel' => $CompanyTel,
							'CompanyEmail' => $CompanyEmail,
							'CompanyReportEmail' => $CompanyEmail, 
							'website_from_email' =>"no-reply@theservicedesk.com"
						);
						$companyId=$newCompany->add($companyData);
						$storedCompany=new Company($db,$companyId);
						$storedCompany->addHost($hostName,$isHttps);
						$jsn["status"]="success";
					}
					break;
					case "registerAccount":
						if(!$error){
							$newUser=new User($db);
							$newUser->register($DisplayName, md5($password), $email_address, $Telephone, $companyId,1,"123456789abcdefghij"); 
							add_group("General");
							$newUser->loadInformation();
							$validationCode=$newUser->generateValidationCode();
							$customData=(object)array("validationCode"=>$validationCode);
							$registrationEmail=new DeskEmail($db,$currentCompany,"Registration");
							$registrationEmail->loadTemplate();
							$registrationEmail->applyScheme("recipient",$newUser->information);
							$registrationEmail->applyScheme("company",$currentCompany->information);
							$registrationEmail->applyScheme("login",$customData);
							$registrationEmail->addSubject("Confirm your email");
							$registrationEmail->addRecipient($newUser);
							$response=$registrationEmail->send();
							$jsn["response"]=$response;
							$jsn["status"]="success";
						}
						else{
							$jsn["status"]="fail";
							$jsn["message"]=l("error");  
						}
					break;
					case "newSignupTicket":
						if(!$error){
						$test="User Idx: {$newUser->id}";
						$subject="new Servicedesk sign up";
						$description="company name:$CompanyName,
						\ncompany Id:$companyId,
						\nHost: $hostName,
						\nUsername: $DisplayName,
						\nUser Id: {$newUser->id},
						\nEmail: $email_address";
						$severity=1;
						$assignedto=1;
						$ownerId=-1;
						$status=2;
						$topic=SD_SIGNUPS_TOPIC_ID;
						$tmpTickets=new Tickets($db);
						$defaultTechie=new User($db,DEFAULT_TECHIE_ID);
						$defaultTechie->insertTicket($subject, $description, $severity,$assignedto,$ownerId,$status,$topic,0);
						$jsn["status"]="success";}
						else{
							$jsn["status"]="fail";
							$jsn["message"]=l("error");  
						}
					break;
					//dynamic enquire form (iframe form)
					case "saveOrphantAttachment":
						$userId=MONDERSKY_ID;
						$user=new User($db,$userId);
						$status=$user->commentTicket(0,"attachment","",2,0);
						$commentId=$user->lastCommentedTicket->lastCommentId;
						if($status){
							
						$jsn["status"]="success";
						$jsn["commentId"]=$commentId;
					}
					else{
							$jsn["status"]="fail";
							$jsn["message"]=l("error");  
						}
					break;
					case "saveEnquire":
						
						$userId=MONDERSKY_ID;
						$user=new User($db,$userId);
						$subject=EMBED_SUBJECT;
						$topic=TEST_TOPIC_ID;
						$description="Message: $message \n\n
						Name: $name\n
						Email: $email\n
						Phone: $phone\n
						Location: $location\n
						";
						$severity=0;
						$assignedto=0;
						$status=2;
						$newTicket=$user->insertTicket($subject, $description, $severity,$assignedto,$user->id,$status,$topic);

						$jsn["status"]="success";
						$jsn["commentId"]=$commentId;
					break;
			}
			$jsn_array[$action]=$jsn;
			$i++;
		}
		if($error)
		$jsn_array["ajaxStatus"]="failed";
		else
		$jsn_array["ajaxStatus"]="success";
		die(json_encode($jsn_array));
	}
	else
	{
		// if not an ajax request, redirect to homepage
		header("Location: ".SITE_URL);
		exit;
	}
	
}
?>