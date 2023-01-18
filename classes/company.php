<?php 
Class Company {
	
	public $id;
	public $host;
	public $information;
	public $groups;
	public $has_loaded;
	public $tickets;
	public $notifications;
	public $db;
	function __construct($db,$id=""){
		$this->db=$db;
		$this->Db=new Db($this->db);
		if($id!=""){
			$this->id=$id;
		}
		$this->tickets=new Tickets($this->db);
	}
	function add($companyData){
		foreach ($companyData as $key => $value) {
			$$key=htmlspecialchars($value);
		}
		$this->Db->query("INSERT INTO Companies (CompanyName,CompanyAddress,CompanyPostCode,CompanyCounty,CompanyCountry,CompanyTel,CompanyEmail,CompanyReportEmail,website_from_email) VALUES ('$CompanyName','$CompanyAddress','$CompanyPostCode','$CompanyCounty','$CompanyCountry','$CompanyTel','$CompanyEmail','$CompanyReportEmail','$website_from_email')");
		return $this->Db->lastInsertId();
	}
	function hostExists($hostName){
		$count=$this->Db->get_var("SELECT COUNT(*) FROM Hosts where Hostname='$hostName' LIMIT 1");
		$count=(int)$count;
		return ($count>0)?true:false;   
	}
	function addHost($hostName,$isHttps){
		$this->Db->query("INSERT INTO Hosts (CompanyID,Hostname,https)VALUES (:CompanyID,:Hostname,:https) ",array($this->id,$hostName,$isHttps));
		return $this->Db->lastInsertId();
	}
	function setHost($host){
		$this->host=$host;
		$data = $this->Db->get_var('SELECT CompanyID FROM Hosts WHERE Hostname = :Hostname LIMIT 1',array($this->host));
		$this->id=$data;
	}
	function loadHost(){
			$host=$this->Db->get_row("SELECT * FROM Hosts WHERE CompanyID={$this->id} ");
			if($host==false){
				return false;
			}
			else{
				$this->information["host"]=$host;
			}
	}
	function getUrl(){
		if(!isset($this->information["host"]) || empty($this->information["host"])){
			$this->loadHost();
		}
		$url=$this->information["host"]["Hostname"];
		if(!str_contains("http",$url))
		$url="https://$url";
		return $url;
	}
	function loadInformation($information=0){
		if($information==0){ // if no data has been passed then load from database
			$data = $this->Db->get_row('SELECT * FROM Companies WHERE CompanyID = :id LIMIT 1',array($this->id));
		}
		else{
			$data=$information;
		}
		$this->id=$data["CompanyID"];
		$this->information=$data;
		$this->has_loaded=true;
	}
	function getUploadDir(){
		return UPLOAD_COMPANY_FOLDER.$this->id."/";
	}
	function getLogoLink(){
		$logoLink=SITE_URL."uploads/company/".$this->id."/".$this->information["logo"];
		return $logoLink;
	}
	function reload(){
		$this->loadInformation();
	}
	function loadGroups(){ 
		$data=$this->Db->get_results("SELECT g.groupid,g.name FROM groups as g  WHERE g.company = :id ",array($this->id));
		$this->groups=new Groups($this->db,$data);
	}

	function loadUsers($tech=""){
		if($tech!="")
		$techQuery= "AND techie=1 "; 
		else {
			$techQuery="";
		}
		$data=$this->Db->get_results("SELECT CompanyID,DisplayName,Domain,Telephone,UserRights,avatar,address,email,country,userid,rights,enabled,seen,techie FROM Users WHERE CompanyId=:id AND enabled=1 $techQuery ",array($this->id));
		$this->users=$data; 
	}
	
	function loadUsersByEmail($email){ 
		if(strlen($email)==0){
			$email="a";
		}
		// htmlspecialchars(mysql_real_escape_string($email));
		$search="%$email%";
		$usersAutocomplete["items"] = $this->Db->get_results("SELECT userid as id,email as text,DisplayName as name FROM Users WHERE email LIKE '$search' AND CompanyId = :id AND enabled=1 LIMIT 4",array($this->id));
		return $usersAutocomplete;
	}
	function loadUserByEmail($email){ 
		$data= $this->Db->get_row("SELECT CompanyID,DisplayName,Domain,Telephone,UserRights,avatar,address,email,country,userid,rights,enabled,seen,techie FROM Users WHERE email=:email AND CompanyId=:id AND enabled=1 $techQuery ",array($email,$this->id));
		// $this->users=$data; 
		return $data["userid"];
	}
	function get_information(){
		if(!$this->has_loaded)
		$this->loadInformation();
		return $this->information;
	}
	function getGroups(){
		if(empty($this->groups))
		$this->loadGroups();
		return $this->groups;
	}
	function getTickets($condition=""){
		$this->tickets->setCompany($this);
		$this->tickets->loadTickets();
		return $this->tickets;
	}
	function getUsers($onlyTech=""){ //if onlyTech==1 then load technciens only
		if(empty($this->users))
		$this->loadUsers($onlyTech);
		return $this->users;
	}
	
	function getUsersByEmail($email){
		if(empty($this->users))
		return $this->loadUsersByEmail($email);
	}
	function hasUserEmail($email){
		$ifExists=$this->Db->get_var("SELECT count(userid) FROM Users WHERE email=:email  AND CompanyID=:CompanyID LIMIT 1",array($email,$this->id) );
		return $ifExists;
	}
	function createUser($user){
		$name=$user->information["name"];
		if(isset($user->information["CompanyID"]))
		$CompanyID=$user->information["CompanyID"];
		else
		$CompanyID=$this->id;
		$password=$user->information["password"];
		$email=$user->information["email"]; 
		$telephone=isset($user->information["Telephone"])?preg_replace('/[^0-9\-]/', '', $user->information["Telephone"]):"";   
		$avatar=$user->information["avatar"];
		$rights=$user->information["rights"];
		$enabled=$user->information["enabled"];
		$mobile=isset($user->information["enabled"])?preg_replace('/[^0-9\-]/', '', $user->information["enabled"]):"";     
		$address=isset($user->information["address"])?$user->information["address"]:""; 
		$postcode=isset($user->information["postcode"])?$user->information["postcode"]:"";
		$country=isset($user->information["country"])?$user->information["country"]:""; 
		$query = $this->Db->query('INSERT INTO Users (DisplayName, CompanyID, password, email, Telephone, avatar, rights,enabled,mobile,address,postcode,country) 
		VALUES (:name, :CompanyID, :password, :email, :telephone, :avatar, :rights, :enabled,:mobile,:address,:postcode,:country)',
		array( $name, $CompanyID, $password, $email, $telephone, $avatar, $rights,$enabled,$mobile,$address,$postcode,$country)); 
		return $this->Db->lastInsertId();
	}
	function getNotifications(){
		if(empty($this->notifications))
		$this->loadNotifications();
		return $this->notifications;
	}  
	function loadNotifications(){
		$data=$this->Db->get_results("SELECT n.name,n.id,n.title,n.defaultEmailTemplate,n.defaultSmsTemplate,n.enabled, cn.Type,cn.smsTemplate,cn.emailTemplate FROM Notifications as n 
		LEFT JOIN Companies_notifications cn ON cn.CompanyID=:id AND cn.NotificationID=n.id",array($this->id));
		$this->notifications=$data; 
	}

	function loadEmbeddedForms(){ 
		$data=$this->Db->get_results("SELECT * FROM embedded_forms WHERE company_id = :id order by id desc",array($this->id));
		$this->embeddedForms=$data;
	}
	function getEmbeddedForms(){
		if(empty($this->embeddedForms))
		$this->loadEmbeddedForms();
		return $this->embeddedForms;
	}
	function saveNotification($notificationType,$notificationName,$notificationId,$emailTemplate,$smsTemplate){
		$query = $this->Db->query('REPLACE INTO Companies_notifications ( CompanyID, NotificationID, type, smsTemplate, emailTemplate) 
		VALUES (:CompanyID, :NotificationID, :type, :smsTemplate, :emailTemplate) ',
		array( $this->id, $notificationId, $notificationType, $smsTemplate, $emailTemplate));
		return $this->Db->lastInsertId();
	}
}
?>
