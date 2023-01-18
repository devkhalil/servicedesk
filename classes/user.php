<?php 
Class User {
	public $id;
	public $information;
	public $groups;
	public $email;
	public $hasLoaded;
	public $db;
	public $company;
	public $tickets;
	public $role="";
	public $lastCommentedTicket;
	function __construct($db,$id=0,$copyingUser=0){
		global $currentCompany;
		$this->currentCompany=& $currentCompany;
		$this->db=$db;
		$this->Db=new Db($this->db);
		if($copyingUser==0){
			$this->id=$id;
			$this->email="";
			$this->information=array();
			$this->tickets=new Tickets($this->db);
		}
		else{
			$this->id=$copyingUser->id;
			$this->email=$copyingUser->email;
			$this->information=$copyingUser->information;
			$this->tickets=$copyingUser->tickets;
		}
	}
	function register($name, $password, $email, $phone, $CompanyID,$techie=0,$rights='') 
	{
	$jsn["OK"] = false;
	
	
	// check if email exists
	// if(false) //uncomment this after finishing debug
		$UserRights = $rights==''?1:$rights;
		$avatar = 'default.jpg';
		
		// try to register a new user on database
		$query = $this->Db->query('INSERT INTO Users (DisplayName, CompanyID, password, email, Telephone, avatar, UserRights,enabled,techie) 
		VALUES (:name, :CompanyID, :password, :email, :phone, :avatar, :UserRights,-1,:techie)',
		array( $name, $CompanyID, $password, $email, $phone, $avatar, $UserRights,$techie));
		if($query===true)
		{	
			$userId=$this->Db->lastInsertId();
			$this->id=$userId;
			return $userId;
		}
		else
		{
			// database error message
			return l("Database error");
		}
	return $jsn;
	}
	function isEmpty(){
		return $this->id==null;
	}
	function setEmail($email=""){
		if($email=="")
		$email=$this->information["email"];
		$this->information["email"]=$email;
		$this->email=$email;
	}
	function checkPassword($password){
		if($password!=""){
			$passwordmd5=md5($password);
			$pass=$this->Db->get_var("SELECT userid FROM Users Where userid=:userid AND password=:password",array($this->id,$passwordmd5));
			if(is_null($pass)){
				$pass=$this->Db->get_var("SELECT userid FROM Users Where userid=:userid AND password=ENCRYPT(:password, CONCAT('$6$', SUBSTRING(SHA(RAND()),NOW())))",array($this->id,$password));
				return (is_null($pass))?false:$pass; 
			}else{
				return true;
			}
			
		}
		return $status;
	}
	function updatePassword($password){
		if($password!=""){
			$passwordmd5=md5($password);
			$pass=$this->Db->query("UPDATE Users SET password=:password WHERE userid=:userid",array($passwordmd5,$this->id));
		}
		return $pass;
	}
	function updateEmailVerification($code){
		$pass=$this->Db->query("UPDATE Users SET email_validation_code=:email_validation_code WHERE userid=:userid",array($code,$this->id));
		return $pass;
	}
	function is($user){ //compare to another user
		
		$thisId=(!isset($this->information["userid"]))?$this->id:$this->information["userid"];
		$userId=(!isset($user->information["userid"]))?$user->id:$user->information["userid"];
		return ($thisId==$userId);
	}
	function isAllowedUser($user){
		function userids($n){
			return $n["userid"];
		}
		$allowedUsers=$this->getAllowedUsers();
		return in_array($user->id,array_map("userids",$allowedUsers));
	}
	function getNotificationType(){
		return $this->information["notification_type"]==0?"email":"sms";
	}
	function getAllowedUsers(){
		$users=$this->Db->get_results("SELECT userid,DisplayName,avatar,email FROM Users WHERE userid IN (SELECT parent FROM Users_access WHERE child=:child ) AND companyID=:CompanyID",array($this->id,$this->currentCompany->id));
		$users=array_reverse($users); //sort users by last added
		return $users;
	}
	function getAccessibleUsers(){
		$users=$this->Db->get_results("SELECT userid,DisplayName,avatar,email FROM Users WHERE userid IN (SELECT child FROM Users_access WHERE parent=:parent ) AND companyID=:CompanyID",array($this->id,$this->currentCompany->id));
		$users=array_reverse($users); //sort users by last added
		return $users;
	}
	function removeParent($user){
		$query=$this->Db->query("DELETE FROM Users_access WHERE parent=:parent AND child=:child",array($user->id,$this->id));
		return $query;
	}
	function removeChild($user){
		$query=$this->Db->query("DELETE FROM Users_access WHERE parent=:parent AND child=:child",array($this->id,$user->id));
		return $query;
	}
	function deleteUser($user){
		$query=$this->Db->query("DELETE FROM Users WHERE userid=:userid",array($user->id));
		$this->Db->query("DELETE FROM attachments WHERE by_uid=:userid",array($user->id));
		$this->Db->query("DELETE FROM password_resets WHERE user=:userid",array($user->id));
		$this->Db->query("DELETE TicketHist, Tickets FROM TicketHist  INNER JOIN Tickets ON TicketHist.ticketid=Tickets.ticketid AND Tickets.uid=:userid WHERE TicketHist.poster=:userid ",array($user->id,$user->id));
		$this->Db->query("DELETE FROM sessionTokens WHERE user=:userid",array($user->id));
		$this->Db->query("DELETE FROM users_groups WHERE user_id=:userid",array($user->id));
		$this->Db->query("DELETE FROM Users_apps WHERE user_id=:userid",array($user->id));
		$this->Db->query("DELETE FROM Users_external WHERE userid=:userid",array($user->id)); 
		// $this->Db->query("DELETE FROM Tickets WHERE uid=:userid",array($user->id));
		return $query;
	}
	function printInfo(){ //show user info for debug
		highlight_string("<?php\n\user information =\n" . var_export($this->information, true) . ";\n?>");
	}
	function loadIdFromEmail(){
		if($this->email=="")
		$this->setEmail();
		$this->id = $this->Db->get_var('SELECT userid FROM Users WHERE email = :email AND CompanyID=:CompanyID LIMIT 1',array($this->email,$this->currentCompany->id));
	}
	function allowAccessTo($user){
		 
		
		$status=$this->Db->query("INSERT INTO Users_access(parent,child) VALUES(:parent,:child )",array($this->id,$user->id));
		return $status;
	}
	function setGroup($group_id){
		$status=$this->Db->query("INSERT INTO users_groups(user_id,group_id) VALUES(:user_id,:group_id )",array($this->id,$group_id));
		return $status;
	}
	function loadFromEmail(){
		if($this->email=="")
			$this->setEmail();
		$data = $this->Db->get_row('SELECT CompanyID,DisplayName,Domain,Telephone,UserRights,avatar,address,email,country,userid,rights,enabled,seen,techie,notification_type FROM Users WHERE email = :email AND CompanyID=:CompanyID LIMIT 1',array($this->email,$this->currentCompany->id));
		$this->id=$data["userid"];
		$this->DisplayName=$data["DisplayName"];
		$this->information=$data;
		$this->hasLoaded=true; 
	}
	function exists(){
		if($this->email=="")
		$this->setEmail();
		$ifExists=$this->Db->get_var("SELECT count(userid) FROM Users WHERE email=:email  AND CompanyID=:CompanyID LIMIT 1",array($this->email,$this->currentCompany->id) );
		return $ifExists>0;
	}
	function checkEmailExistance($email){
		if($this->email=="")
		$this->setEmail();
		$ifExists=$this->Db->get_var("SELECT count(userid) FROM Users WHERE email=:email  AND CompanyID=:CompanyID LIMIT 1",array($email,$this->currentCompany->id) );
		return $ifExists>0;
	}
	function linkExternalId($app,$externalId){
	  $test=$this->Db->query("INSERT INTO Users_external(userid,externalid,app) VALUES(:userid,:externalid,:app)",array($this->id,$externalId,2));
	}
	function setApp($app){
		$test=$this->Db->query("INSERT INTO Users_apps(user_id,app_id) VALUES(:userid,:app)",array($this->id,$app));
	}
	function haveAppRight(){
		$CompanyID = CompanyID;
		$right = $this->Db->get_var('SELECT COUNT(apps.ApplicationID) 
		FROM Applications as apps 
		INNER JOIN companies_apps ca ON ca.company_id=:company_id AND ca.app_id=:app_id',array($CompanyID,APP_ID)); 
		if($right == 0){
		  return false;
		}else{
		  return true;
		}
	  }
	function loadInformation($information=0){
		if($information==0){ // if no data has been passed then load from database
			$data = $this->Db->get_row('SELECT CompanyID,DisplayName,Domain,Telephone,UserRights,avatar,address,email,country,userid,rights,enabled,seen,techie,notification_type FROM Users WHERE userid = :userid LIMIT 1',array($this->id));
		}
		else{
			$data=$information;
		}
		$this->id=$data["userid"];
		$this->DisplayName=$data["DisplayName"];
		$this->information=$data;
		$this->hasLoaded=true;
	}
	
	function loadGroups(){ 
		$data=$this->Db->get_results("SELECT ug.group_id,g.name FROM users_groups as ug LEFT JOIN groups g ON ug.group_id=g.groupid WHERE ug.user_id = :userid ",array($this->id));
		$this->groups=new Groups($this->db,$data);
	}
	function loadApps(){ 
		$data=$this->Db->get_results("SELECT up.* FROM Users_apps as up INNER JOIN Applications apps ON apps.ApplicationID=up.app_id INNER JOIN Users u ON u.userid=up.user_id WHERE u.userid = :userid ",array($this->id));
		// if(count($data==0)){
		// 	$data=$this->Db->get_results("SELECT up.* FROM Users_external as up INNER JOIN Applications apps ON apps.ApplicationID=up.app INNER JOIN Users u ON u.userid=up.userid WHERE u.userid = :userid ",array($this->id));
		// }
		$this->apps=$data;
		
		return $data;
	}
	
	function generateValidationCode(){ 
		$code=substr(md5(uniqid(mt_rand(), true)) , 0, 15);
		$this->Db->query("INSERT INTO Accounts_verifications(userid,code) VALUES($this->id,'$code')  ");
		return $code;
	}
	
	function verifyAccount($token){ 
		$unverifiedUser=(int)$this->Db->get_var("SELECT userid FROM Accounts_verifications WHERE code=:code ",array($token));
		if($unverifiedUser){
			$this->Db->query("UPDATE Users SET enabled=1 WHERE userid=:userid",array($unverifiedUser));
			$this->Db->query("DELETE FROM Accounts_verifications WHERE userid=:userid",array($unverifiedUser));
			return l("Email confirmed");
		}
		else{
			return l("Email confirmation link expired");
		}
	}
	function forceVerifyAccount(){ 
			$this->Db->query("UPDATE Users SET enabled=1 WHERE userid=:userid",array($this->id));
			$this->Db->query("DELETE FROM Accounts_verifications WHERE userid=:userid",array($this->id));
			return l("Email confirmed");
	}
	function get_information(){
		if(!$this->hasLoaded)
		$this->loadInformation();
		return $this->information;
	}
	function getGroups(){
		if(empty($this->groups))
		$this->loadGroups();
		return $this->groups;
	}
	function isAllowedApp($appID){
		$allowedApps=$this->getApps();
		
		
		foreach($allowedApps as $allowed){
			if(isset($allowed["app_id"])){
				if($allowed["app_id"]==$appID){
					return true;
				}
			}else{
				if($allowed["app"]==$appID){
					return true;
				}
			}
		}
		return false;
	}
	function getApps(){
		if(empty($this->apps))
		$this->loadApps();
		return $this->apps;
	}
	function areTechContactEmpty(){
		if($this->isTechie()){
			if(!$this->hasLoaded)
			$this->loadInformation();
			return $this->information["techemail"]=="" || $this->information["techphone"]=="" ;
		}
		else{
			false; //if user isn't a techie then we don't need to check if his staff email and phone are empty
		}
		
	}
	function getCompany(){
		if(empty($this->groups)){
			if(!$this->hasLoaded){
				$this->loadInformation();
			}
			
			$this->company=new Company($this->db,$this->information["CompanyID"]);
		}
		return $this->company;
	}
	function getCompanyID(){
		return $this->information["CompanyID"];
	}
	function isTechie(){
		if(!$this->hasLoaded)
		$this->loadInformation();
		if((int)$this->information["techie"]==1)
		return true;
		else
		return false;
	}
	function isUnverified(){
		if(!$this->hasLoaded)
		$this->loadInformation();
		if($this->information["enabled"]==-1)
		return true;
		else
		return false;
	}
	function isActive(){
		if(!$this->hasLoaded)
		$this->loadInformation();
		if($this->information["enabled"]==1)
		return true;
		else
		return false;
	}
	function hasRight($feature){
		return $this->has_right($feature);
	}
	function has_right($feature){
		if($this->isTechie() && $this->isActive()){
			if(strpos($this->information["rights"],$feature."")>-1)
			return true;
			else
			return false;
		}
		else {
			return false;
		}
	}
	function set_seen($seen){
		$data=$this->Db->query("UPDATE Users SET seen=:seen WHERE userid=:userid",array($seen,$this->id));
		$this->information["seen"]=$seen;
	}
	function get_seen(){
		$data=$this->Db->get_var("SELECT seen FROM Users WHERE userid=:userid AND enabled=1 LIMIT 1",array($this->id));
		$data=trim($data,",");
		$this->information["seen"]=$data;
		return $data;
	}
	function setRole($role){
		$this->role=$role;
		$this->tickets->setRole($role);
	}
	function getUsers(){
		$groups=new Groups($this->db,$this->groups);
		$users=$groups->get_users();
		return $users;
	}
	
	function setTicketsStatus($status){
		$this->tickets->setStatus($status);
	}
	function hasTicketHist(){
		$count= (int)$this->tickets->countTicketsHist($this->id);
				
		return $count>0?true:false;
	}
	function getTickets(){
		$this->tickets->setSeen($this->get_seen());
		switch ($this->role) {
			case 'owner':
			$this->tickets->setOwner($this->id);
			break;
			case 'assignee':
			$this->tickets->setAssignee($this->id);
			break;
			case 'creator':
			$this->tickets->setCreator($this->id);
			break;
			
		}
		$this->tickets->loadTickets();
		return $this->tickets;
	}
	function seeTicket($ticketid){ //this switches the "new" indicator to off
		$this->get_seen();
		$seenTickets=explode(",",$this->information["seen"]);
		if(!in_array($ticketid,$seenTickets)){
			$seenTickets[]=$ticketid;
			$this->information["seen"]=implode(",",$seenTickets);
			$seen=$this->information["seen"];
			$this->Db->query("UPDATE Users SET seen =:seen WHERE userid=:userid",array($seen,$this->id));
		}
	}
	function claimTicket($ticketid){
		$ticket=new ticket($this->db,$ticketid);
		$ticket->updateStatus(2);
		$ticket->updateAssign($this->id);
		$ticket->addComment("","",4,0);
	}
	function closeTicket($ticket,$comment,$technote,$attachement=0){
		$ticket->updateStatus(3);
		$ticket->addComment($comment,$technote,3,0);
		if($attachement!=0)
			$ticket->addAttachement($attachement);
	}
	function reopenTicket($ticket){
		if($this->isTechie())
		$ticket->updateStatus(1);
		else
		$ticket->updateStatus(2);
		$ticket->addComment("","",7,0);
	}
	function unassignTicket($ticket,$comment,$technote,$status,$attachement=0){
		$ticket->updateStatus($status);
		$ticket->updateAssign("0");
		$ticket->addComment($comment,$technote,8,0); 
		if($attachement!=0)
			$ticket->addAttachement($attachement);
	}
	function reassignTicket($ticket,$comment,$technote,$assignee,$status,$attachement=0){
		$ticket->updateStatus($status);
		$ticket->updateAssign($assignee);
		$ticket->addComment($comment,$technote,2,0,$assignee); 
		if($attachement!=0)
			$ticket->addAttachement($attachement);
	}
	
	function changeTicketTopic($ticketid,$comment,$technote,$topic,$status,$attachement=0){
		$ticket=new ticket($this->db,$ticketid);
		$ticket->updateStatus($status);
		$ticket->updateTopic($topic);
		$ticket->addComment($comment,$technote,6,0,"",$topic); 
		if($attachement!=0)
			$ticket->addAttachement($attachement);
	}
	function commentTicket($ticketid,$comment,$technote,$status,$attachement=0){
		$ticket=new ticket($this->db,$ticketid);
		$ticket->updateStatus($status);
		if($technote=="undefined")
			$technote="";
		$ticket->addComment($comment,$technote,1,0,0,0,$this);
		if($attachement!=0){
			$status=$ticket->addAttachement($attachement);
			if($status!==true)
			return $status;
		}
		$this->lastCommentedTicket=$ticket;
		return true;
	}
	function insertTicket($subject, $description, $severity,$assignedto,$ownedby,$status,$topic,$attachement=0){
		$this->tickets->insert($subject, $description, $severity,$assignedto,$ownedby,$status,$topic,$this->id);
		$ticketid=$this->tickets->getLastInsertId();
		$ticket=new ticket($this->db,$ticketid);
		$ticket->addComment($description,"",1,0,0,0,$this);
		if($attachement!=0)
			$ticket->addAttachement($attachement);
		$this->seeTicket($ticketid);
		$insertedTicket=new Ticket($this->db,$ticketid);
		return $insertedTicket;
	}
	function getSingleTicket($ticketid){
		$ticket=new ticket($this->db,$ticketid);
		return $ticket;
	}
	function getAllCompanies(){ //get all the companies that this user's email may be associated to
		$companies=$this->Db->get_results("SELECT  c.CompanyID, h.*,c.* from Users u
		LEFT JOIN Companies c ON u.CompanyID=c.CompanyID
		INNER JOIN Hosts h ON c.CompanyID=h.CompanyID
		WHERE u.email=:email
		GROUP BY c.CompanyID
		ORDER BY c.CompanyName
		",array($this->information["email"]));
		return $companies;
	}
	// function getAllCompanies(){ //get all the companies that this user's email may be associated to
	// 	$companies=$this->Db->get_results("SELECT  c.*, h.* from Companies as c
	// 	INNER JOIN Users u ON u.CompanyID=c.CompanyID 
	// 	INNER JOIN Hosts h ON c.CompanyID=h.CompanyID
	// 	WHERE u.email=:email
	// 	",array($this->id,$this->information["email"]));
	// 	return $companies;
	// }
	function changePassword($password)
	{
		$query = $this->Db->query('UPDATE Users SET password = :password WHERE userid=:userid',array(passEnc($password),$this->id));
		$query = $this->Db->query('DELETE FROM password_resets WHERE user=:userid',array($this->id));
		return true;
	}
	function updatePhoneVerification($phone,$phone_digit)
	{
		$query = $this->Db->query('UPDATE Users SET Telephone=:phone, phone_digit = :phone_digit,phone_validation_date=NOW() WHERE userid=:userid',array($phone,(int)$phone_digit,$this->id));
		$this->information["Telephone"]=$phone;
		return true;
	}
	function verifyCode($code)
	{
		
		$data=$this->Db->get_var("SELECT userid FROM Users WHERE userid=:userid AND phone_digit=:code LIMIT 1",array($this->id,(int)$code));
		return (!is_null($data));
	}
	function updateCode()
	{
			$query = $this->Db->query('UPDATE Users SET phone_validation = 1 WHERE userid=:userid',array($this->id)); 
			return $query;			
	}
	function disable()
	{
			$query = $this->Db->query('UPDATE Users SET enabled = 0 WHERE userid=:userid',array($this->id)); 
			return $query;			
	}
	function unverify()
	{
			$query = $this->Db->query('UPDATE Users SET enabled = -1 WHERE userid=:userid',array($this->id)); 
			return $query;			
	}
	function verifyEmailCode($code)
	{
		
		$data=$this->Db->get_var("SELECT userid FROM Users WHERE userid=:userid AND email_validation_code=:code LIMIT 1",array($this->id,(int)$code));
		return (!is_null($data));
	}
	function updateEmail($email)
	{
			$query = $this->Db->query('UPDATE Users SET email = :email WHERE userid=:userid',array($email,$this->id)); 
			
			return $query;			
	}
	function copyUser($user){

	}
	function getLastValidation(){
		$data=$this->Db->get_var("SELECT phone_validation_date FROM Users WHERE userid=:userid AND enabled=1 LIMIT 1",array($this->id));
		$this->information["phone_validation_date"]=$data;
		return $data;
	}
	function getLastEmailValidation(){
		$data=$this->Db->get_var("SELECT email_validation_date FROM Users WHERE userid=:userid AND enabled=1 LIMIT 1",array($this->id));
		$this->information["email_validation_date"]=$data;
		return $data;
	}
	function getUserID(){
		return $this->id;
	}
	function update_profile($DisplayName, $Telephone, $mobile, $fax, $address, $postcode, $country, $company, $Notify,$notificationType, $PlainTextEmail,$techemail=null,$techphone=null) {
	$phone_validation_update="";
	$old_phone=$this->information["Telephone"];
	$old_techphone=$this->information["techphone"];
	if($old_phone!=$Telephone){
		$phone_validation_update.=", phone_validation=0, phone_validation_date=NULL";
	}
	if($this->isTechie()){
		if($old_techphone!=$techphone){
			$phone_validation_update.=", tech_phone_validation=0, tech_phone_validation_date=NULL";
		}
	}
		if($this->isTechie()){
			$query = $this->db->prepare('UPDATE Users SET DisplayName=:DisplayName,  Telephone=:Telephone, mobile=:mobile, fax=:fax, address=:address, postcode=:postcode, country=:country, company=:company, Notify=:Notify,notification_type=:notificationType,PlainTextEmail=:PlainTextEmail,techemail=:techemail,techphone=:techphone '.$phone_validation_update.' WHERE userid=:uid');
		}
		else{
			$query = $this->db->prepare('UPDATE Users SET DisplayName=:DisplayName, Telephone=:Telephone, mobile=:mobile, fax=:fax, address=:address, postcode=:postcode, country=:country, company=:company, Notify=:Notify,notification_type=:notificationType,PlainTextEmail=:PlainTextEmail '.$phone_validation_update.' WHERE userid=:uid');
		}
		$query->bindParam(':uid', $this->id);
		$query->bindParam(':DisplayName', $DisplayName);      
		$query->bindParam(':Telephone', $Telephone);
		$query->bindParam(':mobile', $mobile);
		$query->bindParam(':fax', $fax);
		$query->bindParam(':address', $address); 
		$query->bindParam(':postcode', $postcode);
		$query->bindParam(':country', $country);
		$query->bindParam(':company', $company);
		$query->bindParam(':Notify', $Notify);
		$query->bindParam(':notificationType', $notificationType);
		$query->bindParam(':PlainTextEmail', $PlainTextEmail);
		if($this->isTechie()){
			$query->bindParam(':techemail', $techemail);
			$query->bindParam(':techphone', $techphone);
		}
		if($query->execute()){
			return $query->rowCount();
		}else{
			return l("An error happened, please contact an administrator");
		}
	}
}
?>