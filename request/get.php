<?php
ini_set('display_startup_errors',1);
ini_set('display_errors',1);
error_reporting(-1);

// core functions 
include_once "../core.php";
$actions=$_POST["actions"];
if(isset($_POST["params"]))
$params=$_POST["params"];

function allowTechOnly(){
	global $onlineUser;
	if(!$onlineUser->isTechie())
	exit;
}

# code...
if(ONLINE)
{	
	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
	{
		$i=0;
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
				
				case "singleTicket": //load single ticket informations
					$jsn=array();
					$jsn["ticket"]=$onlineUser->getSingleTicket($ticketid)->getData();
					$jsn["status"]="success";
				break;
				
				case "userTickets": //load assigned to, owned by and created by tickets
					$jsn=array();
					if($onlineUser->isTechie()){
						if(isset($userid) && $userid!="" && $userid!="all"){ // when a user is specified
							$user=new User($db,$userid);
							$user->setRole($type);
							if(!$onlineUser->hasRight(SEE_ALL_TICKETS)){
								// richard said: make the created by etc, display all tickets regardless of what groups people are in
								// $user->tickets->setGroups($onlineUser->getGroups()->getIds()); 
								// $user->tickets->setRelated($onlineUser);// only tickets that are related to the online user 
							}
							$user->tickets->statusOpen();
							$tickets=$user->getTickets();
							$jsn["tickets"]=$tickets->getData();
						}
						else{ 
							$onlineUser->tickets->statusOpen();
							$onlineUser->tickets->setRelated($onlineUser);
							$jsn["tickets"]=$onlineUser->getTickets()->getData();
						}
						$jsn["title"]=$title;
						$jsn["status"]="success";
					}else{
						$jsn=array();
						$jsn["status"]="not allowed";
						$error=true;
					}
				break;
				
				case "allTickets": //load assigned to, owned by and created by tickets
					$jsn=array();
					if($onlineUser->isTechie() && $onlineUser->hasRight(SHOW_ALL_TICKETS)){
						if($onlineUser->hasRight(FILTER_ALL_TICKETS)){
						if($filter_assignedTo!="false" && !empty($filter_assignedTo)){
							$assignee=new User($db,$filter_assignedTo);
							$currentCompany->tickets->assignedTo($assignee);
						}
						if($filter_createdBy!="false" && !empty($filter_createdBy)){
							$creator=new User($db,$filter_createdBy);
							$currentCompany->tickets->createdBy($creator);
						}
						if($filter_ownedBy!="false" && !empty($filter_ownedBy)){
							$owner=new User($db,$filter_ownedBy);
							$currentCompany->tickets->ownedBy($owner);
						}
						if($filter_none=="false" ){
							$currentCompany->tickets->notNone($owner);
						}
						if($filter_minor=="false" ){
							$currentCompany->tickets->notMinor($owner);
						}
						if($filter_major=="false" ){
							$currentCompany->tickets->notMajor($owner);
						}
						if($filter_emergency=="false" ){
							$currentCompany->tickets->notEmergency($owner);
						}
						if($filter_topic!="false" && !empty($filter_topic)){
							$topic=new Group($db,$filter_topic);
							$currentCompany->tickets->topic($topic);
						}
						if($filter_waitingOnUser=="false" ){
							$currentCompany->tickets->notWaitingOnUser();
						}
						if($filter_waitingOnTech=="false" ){
							$currentCompany->tickets->notWaitingOnTech();
						}
						if($filter_closed=="false" ){
							$currentCompany->tickets->notClosed();
						}
						if(!empty($duration)){
							$currentCompany->tickets->setDuration($duration);
						}
					}
							$jsn["tickets"]=$currentCompany->getTickets()->getData();
						$jsn["title"]=$title;
						$jsn["status"]="success";
					}else{
						$jsn=array();
						$jsn["status"]="not allowed";
						$error=true;
					}
				break;
				
				case "dashboard": //load assigned to me tickets + all the topics tickets the online user belongs to
					$jsn=array();
					if($onlineUser->isTechie()){				
						$onlineUser->setRole("assignee");
						// if(isset($waitingOnMeOnly)&&$waitingOnMeOnly=='true'){
							$onlineUser->tickets->waitingOnTechOnly(); 
						// }
						$waitingOnMeOnly= strtolower($waitingOnMeOnly)=="true"? true: false; 
						$jsn[0]["tickets"]=$onlineUser->getTickets()->getData();
						$jsn[0]["title"]="Assigned to me";
						$jsn[0]["status"]="success";
						
						if($onlineUser->has_right(SEE_ALL_TICKETS))
						$groups=$onlineUser->getCompany()->getGroups()->groups; // a user can see all the company groups if he got the right to
						else
						$groups=$onlineUser->getGroups()->groups;
						$j=1;
						foreach ($groups as $g) { //loading the user topics and their tickets
							
						if($onlineUser->has_right(SEE_ALL_TICKETS)){
							if($waitingOnMeOnly)
								$g->tickets->groupWhereClause($onlineUser,$waitingOnMeOnly);
							// else
							// 	$g->tickets->statusOpen();
						}
						else
							$g->tickets->groupWhereClause($onlineUser,$waitingOnMeOnly);
							$g->tickets->notClosed();
							//exclude tickets that are assigned to the online user, this line has been modified for the 3rd time, it makes sure that there's no ticket duplicate in the dashboard, don't edit it if you don't know what you're doing
							$groupTickets=$g->getTickets($onlineUser); 
							$tickets=$groupTickets->getData(); 
							if(count($tickets)>0){
								$jsn[$j]["tickets"]=$tickets;
								$jsn[$j]["title"]=$g->information["name"];
								$jsn[$j]["status"]="success";
								$j++;
							}
						}
					}else{ 
						//because this is a simple user, we simply load tickets created by him
						$onlineUser->setRole("owner");
						$tickets=$onlineUser->getTickets();
						$tickets->orderBy("status","DESC");
						$jsn["tickets"]=$tickets->getData();
						$jsn["title"]="My tickets";
						$jsn["status"]="success";
					}
				break;
				
				case "assignedToMe": //load assigned to me tickets
					$jsn=array();
					if($onlineUser->isTechie()){				
						$onlineUser->setRole("assignee");
						if(isset($waitingOnMeOnly)&&$waitingOnMeOnly=='true'){
							$onlineUser->tickets->waitingOnTechOnly(); 
						}
						$tickets=$groups->getTickets();
						if($tickets!=false){
							$jsn["tickets"]=$tickets->getData();
							$jsn["title"]="Assigned to me";
							$jsn["status"]="success";
						}else{
							$jsn=array();
							$jsn["status"]=l("An error has occurred. Please contact support with Ref: tofindlater"); 
							$error=true;
						}
					}else{
						$jsn=array();
						$jsn["status"]="not allowed";
						$error=true;
					}
				break;
				
				case "unassigned": //load tickets that are assigned to no one
					$jsn=array();
					if($onlineUser->isTechie()){				
						if($onlineUser->has_right(SEE_ALL_TICKETS)){
							$groups=$currentCompany->getGroups();
						}
						else{
							$groups=$onlineUser->getGroups();
						}
						$groups->tickets->setAssignee("0"); //assigned to no one
						// $groups->tickets->statusOpen();
						$groups->tickets->notClosed();
						$tickets=$groups->getTickets();
							if($tickets!=false){
								$jsn["tickets"]=$tickets->getData();
								$jsn["title"]="Unassigned";
								$jsn["status"]="success";
							}else{
								$jsn=array();
								$jsn["status"]=l("An error has occurred. Please contact support with Ref: tofindlater"); 
								$error=true;
							}
					}else{
						$jsn=array();
						$jsn["status"]="not allowed";
						$error=true;
					}
				break;
				
				case "waitingOnUser": //load waiting on user tickets
					$jsn=array();
					if($onlineUser->isTechie()){				
						// if($onlineUser->has_right(SEE_ALL_TICKETS))
						$groups=$currentCompany->getGroups();
						// else{
							// $groups=$onlineUser->getGroups();
							// $groups->tickets->setRelated($onlineUser); // only tickets that are related to the online user
							// }
							$groups->tickets->waitingOnUserOnly(); 
							$tickets=$groups->getTickets();
							if($tickets!=false){
								$jsn["tickets"]=$tickets->getData();
								$jsn["title"]="Waiting on user"; 
								$jsn["status"]="success";
							}else{
								$jsn=array();
								$jsn["status"]=l("An error has occurred. Please contact support with Ref: tofindlater"); 
								$error=true;
							}
								
						}else{
							$jsn=array();
							$jsn["status"]="not allowed";
							$error=true;
						}
					break;
					
					case "recentlyClosed": //load waiting on user tickets
						$jsn=array();
						if($onlineUser->isTechie()){
							$userid=is_null($userid)?"":$userid; 
							if(isset($userid) && $userid!="" && $userid!="all"){
								$user=new User($db,$userid);
								$user->setRole("owner");
								if(!$onlineUser->hasRight(SEE_ALL_TICKETS)){
									$user->tickets->setGroups($user->getGroups()->getIds());
									$user->tickets->setRelated($onlineUser);
								}
								$user->tickets->closedOnly();
								$user->tickets->setDuration($duration);
								$tickets=$user->getTickets();
								$jsn["tickets"]=$tickets->getData();
							}
							else{
								if($onlineUser->has_right(SEE_ALL_TICKETS)){
									$groups=$currentCompany->getGroups();
								}
								else{
									$groups=$onlineUser->getGroups();
									$groups->tickets->setRelated($onlineUser); 
								}
								$groups->tickets->closedOnly(); 
								$groups->tickets->setDuration($duration); 
								$jsn["tickets"]=$groups->getTickets()->getData();
							}
							$jsn["title"]="Recently closed";
							$jsn["status"]="success";
						}else{
							$jsn=array();
							$jsn["status"]="not allowed";
							$error=true;
						}
					break;
					
					case "myTickets": //load assigned to me tickets
						$onlineUser->setRole("owner");
						if(isset($showClosed)&&$showClosed=='true'){
							$onlineUser->tickets->closedOnly(); 
						}
						$jsn["tickets"]=$onlineUser->getTickets()->getData();
						$jsn["title"]="My tickets";
						$jsn["status"]="success";
					break;
					
					case "groupTickets": //load a group's tickets
						
						if($onlineUser->isTechie()){				
							$group=new Group($db,$groupid);
							$jsn[$i]["tickets"]=$group->getTickets($onlineUser->id)->getData(); //exclude online user
							$jsn[$i]["title"]=$name;
							$jsn["status"]="success";
						}else{
							$jsn["status"]="not allowed";
							$error=true;
						}
					break;
					
					case "userGroups": //load groups a user is involved in
						
						$jsn=array();
						if($onlineUser->isTechie()){				
							if($onlineUser->has_right(SEE_ALL_TICKETS))
							$jsn=$onlineUser->getCompany()->getGroups()->getData(); // a user can see all the company groups if he got the right to
							else
							$jsn=$onlineUser->getGroups()->getData();
							$jsn["status"]="success";
						}else{
							$jsn["status"]="not allowed";
							$error=true;
						}
					break;
					
					case "companyGroups": //load all the company groups
						$jsn=array();
						$jsn=$onlineUser->getCompany()->getGroups()->getData();
						$jsn[$i]["status"]="success";
					break;
					
					case "companyUsers": //load all the company users
						$jsn=array();
						if($onlineUser->isTechie()){	
							$jsn=$onlineUser->getCompany()->getUsers();
							$jsn[$i]["status"]="success";
						}else{
							$jsn["status"]="not allowed";
							$error=true;
						}
					break;
					
					case "companyTechies": //load all the company techniciens
						$jsn=array();
						if($onlineUser->isTechie()){	 
							$jsn=$onlineUser->getCompany()->getUsers(1);
							$jsn[$i]["status"]="success";
						}else{
							$jsn["status"]="not allowed";
							$error=true;
						}
					break;
					
					case "ticket": //load ticket
						$jsn=array();
						$ticket= new Ticket($db,$ticketid);
						$data= $ticket->getData();
						if($data){
							if($onlineUser->hasRight(SEE_ALL_TICKETS) || $ticket->isRelatedToUser($onlineUser) || $ticket->hasGroupAccessRightForUser($onlineUser)){
								if($ticket->belongsToCompany($currentCompany)){
									
									$jsn["ticket"] =$data;
									$jsn["title"] = "Ticket #".$ticket->id;
									
									if($onlineUser->isTechie())
									$jsn["ticket_comments"] = $ticket->getComments();
									else{
										$jsn["ticket_comments"] = $ticket->getComments(1); //don't get comments that only contains staff notes
									}
									$jsn["status"]="success";
								}
								else{
									$jsn["status"]="different company";
									$jsn["code"]=1;
									$jsn["company"]=$ticket->data["companyID"];
									$error=true;
								}
							}
							else{
								$error=true;
								$jsn["status"]=l("you're not allowed to access this ticket");
							}
						}
						else{
							$error=true;
							$jsn["code"]=2;
							$jsn["status"]="ticket not found";
						}
					break;

					
					case "full_ticket": //load ticket, this isn't being used for now
						$jsn=array();
						$ticket= new Ticket($db,$ticketid);
						$jsn["tickets"] = $ticket->getData();
						$jsn["title"] = "Ticket #".$ticket->id;
						if($onlineUser->isTechie())
						$jsn["tickets"]["ticket_comments"] = $ticket->getComments();
						else{
							$jsn["tickets"]["ticket_comments"] = $ticket->getComments(1); //don't get comments that only contains staff notes
						}
						$jsn["status"]="success";
					break;
					
					case "users_autocomplete":
						if($onlineUser->has_right(OPEN_FOR_OTHERS)){
							
							if(!isset($ownedby)){
								$ownedby="a";
							}
							$jsn=$onlineUser->getCompany()->getUsersByEmail($ownedby);
							$jsn["status"]="success";
						}
						else{
							$error=true;
							$jsn["status"]="not allowed";
						}
					break;
					
					case "allowed_users":
						$allowedUsers=$onlineUser->getAllowedUsers();
						$jsn["allowedUsers"]=$allowedUsers;
						$jsn["status"]="success";
					break;
					case "embedded_forms":
						$embeddedForms=$currentCompany->getEmbeddedForms();
						$result=[];
						foreach ($embeddedForms as $form) { 
							$form['link']=EMBED_LINK.$form['token'];
							$iframeCode=EMBED_LINK_IFRAME;
							$iframeCode=str_replace("[link]",EMBED_LINK.$form['token'],$iframeCode);
							$form['iframe']=$iframeCode;
							$result[]=$form;
						}
						$jsn["embeddedForms"]=$result;
						$jsn["status"]="success";
					break;
					
					case "accessible_users":
						$allowedUsers=$onlineUser->getAccessibleUsers();
						$jsn["accessibleUsers"]=$allowedUsers;
						$jsn["status"]="success";
					break;
					
					case "company_notifications":
						$notifications=$onlineUser->getCompany()->getNotifications();
						$jsn["notifications"]=$notifications;
						$jsn["status"]="success";
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
			//if it's not an ajax request, redirect to homepage
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
					case "fastLogin":  
						$jsn=array();
						$loggedIn=false;
						if(isset($username)&&isset($password)){
							$servicedesk=new ServiceDesk($db);
							$username=$servicedesk->filter($username);
							$password=$servicedesk->filter($password);
							if(empty($username) || empty($password))
							{
								$jsn["msg"] = l("Required fields are empty");
								$jsn["status"]="failed";
							}
							else
							{
								$users=$servicedesk->getUsers($username,$password);
								
								 foreach ($users as $user) {
									if($user["CompanyID"]==$currentCompany->id){
										$_SESSION["User"] = $user;
										$onlineUser=new User($db,$user["userid"]);
										$onlineUser->loadInformation();
										$loggedIn=true;
									}
								}
								if(count($users)==0){
									$jsn["msg"] = l("Wrong username or password");
									$jsn["status"]="failed";
								}else{
									if(is_null($onlineUser)){
										$user=$users[0];
										$_SESSION["User"] = $user;
										$onlineUser=new User($db,$user["userid"]);
										$onlineUser->loadInformation();
										$loggedIn=true;
									}
									if(count($users)==1){
										if($onlineUser->CompanyID!=$currentCompany->id){
											$companyId=$onlineUser->information["CompanyID"];
											$chosenCompany=new Company($db,$companyId); 
											$originalLink=REQUESTED_URL;
											$attaignableCompanies=$servicedesk->getAttaignableCompanies($onlineUser);
											if(isset($_SESSION["attaignableCompanies"]) && $_SESSION["attaignableCompanies"]!=""){ 
												$attaignableCompanies=array_merge($attaignableCompanies,$_SESSION["attaignableCompanies"]);
											}
											$jsn["token"]=createSessionToken(IP,$onlineUser,$originalLink,$companyId,$attaignableCompanies);
											$jsn["redirect"]="//".$user['Hostname'];
										}else{
											$company=$onlineUser->getCompany();
											$jsn["redirect"]="/";
										}
									}else{
										$jsn["redirect"]="fastdashboard";
									}
									$jsn["data"]=$users;
									$jsn["status"]="success";
								}
							}
						}else{
							$jsn["msg"] = l("Required fields are empty");
							$jsn["status"]="failed";
						}

					break;
					case "ifHostExists":
						$tmpCompany=new Company($db);
						$jsn["response"]=$tmpCompany->hostExists($hostname);
						$jsn["status"]="success";
					break;
				}
				$jsn_array[$action]=$jsn;
				$i++;
				if($error)
				$jsn_array["ajaxStatus"]="failed";
				else
				$jsn_array["ajaxStatus"]="success";
				die(json_encode($jsn_array));
			}
		}
		else
	{
		// if not an ajax request, redirect to homepage
		header("Location: ".SITE_URL);
		exit;
	}
	}
return 
?>