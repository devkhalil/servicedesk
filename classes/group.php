<?php
Class Group {
	public $id;
	public $information;
	public $users;
	public $has_loaded;
	public $db;
	public $tickets;
	function __construct($db,$id=0){
		$this->db=$db;
		$this->Db=new Db($this->db);
		$this->id=$id;
		$this->tickets=new Tickets($this->db);
		$this->users= array();
	}
	function loadInformation($information=0){
		if($information==0){ // if no data has been passed then load from database
			$data = $this->Db->get_row('SELECT * FROM groups WHERE groupid = :groupid LIMIT 1',array($this->id));
		}
		else{
			$data=$information;
		}
		$this->id=$data["groupid"];
		$this->information=$data;
		$this->has_loaded=true;
	}
	function loadUsers(){ 
		$this->users= array();
		$data=$this->Db->get_results("SELECT u.userid,u.* FROM users_groups ug INNER JOIN Users u ON u.userid=ug.user_id WHERE ug.group_id = :group_id ",array($this->id));
		foreach ($data as $u) {
			$newUser=new User($this->db,$u["userid"]);
			$newUser->loadInformation($u);
			$this->users[]=$newUser;
		}
	}
	
	function loadTickets($condition=""){
		global $db;
		global $onlineUser;

		$status="t.status!=3"; 

		$first_image_subquery="(SELECT a.storefilename FROM attachments as a WHERE a.ticketid=t.ticketid AND a.mimetype IN ('image/gif','image/png' ,'image/jpg','image/jpeg','image/ico','image/ps','image/psd','image/svg','image/tif','image/tiff','image/bmp') LIMIT 1) as attachments";
		$count_comment_subquery="(SELECT COUNT(th.ticketid) FROM TicketHist as th WHERE th.ticketid=t.ticketid) as count";
		$condition_sql="";
		if($condition!="")
			$condition_sql="AND $condition";
		$tickets["tickets"]  = $this->Db->get_results("SELECT 
		t.*,u.userid,u.DisplayName,u.avatar,$count_comment_subquery  
		FROM Tickets as t LEFT JOIN Users u ON u.userid=t.uid  WHERE groupid=:groupid $condition_sql",
		array($this->id));
		for ($i=0; $i <count($tickets["tickets"]) ; $i++) { 
			$tickets["tickets"][$i]["opened"]=time_elapsed($tickets["tickets"][$i]["opened"]);
		}
		$tickets["assignment"]=get_users();
		$tickets["topics"]=get_groups_page();
		$tickets["allowed_user"]=$onlineUser->isTechie();
		$this->tickets=$tickets;
	}
	function get_information(){
		if(empty($this->information))
			$this->loadInformation();
		return $this->information;
	}
	function getUsers(){
		if(empty($this->users))
			$this->loadUsers();
		return $this->users;
	}
	function get_users_by_email($ownedby){
	if(strlen($ownedby)==0){
		$ownedby="a";
	}
	$search="%$ownedby%";
	$query = $db->prepare("SELECT u.userid as id,u.email as text,u.DisplayName as name FROM users_groups ug INNER JOIN Users u ON u.userid=ug.userid WHERE u.email LIKE ? AND ug.group_id = $this->id LIMIT 4");
	$query->execute(array($search));
	$temps["items"] = $query->fetchAll(PDO::FETCH_ASSOC);
	return $temps;
	}
	function getTickets($excludedAssignee=0){
		global $onlineUser;
		$this->tickets->setSeen($onlineUser->get_seen());
		$this->tickets->setGroups(array($this->id));
		if($excludedAssignee!==0){
			$this->tickets->excludeAssignee($excludedAssignee->id);
		}
		$this->tickets->loadTickets();
		return $this->tickets;
	}
}
?>