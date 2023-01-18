<?php
Class Groups {
	public $groups=array();
	public $information;
	public $users;
	public $has_loaded;
	public $tickets;
	public $Ids=array();
	public $data=array();
	public $db;
	function __construct($db,$data=0){
		global $onlineUser;
		$this->db=$db;
		$this->Db=new Db($this->db);
		$this->tickets=new Tickets($this->db);
		$this->onlineUser=& $onlineUser;
		if($data!=0)
		$this->createGroups($data);
	}
	function loadAll(){
		$idsString=implode(",",$this->Ids);
		$groupsData=$this->Db->get_results("SELECT * FROM groups WHERE groupid IN ($idsString) ");
		$groupById=array();
		if($groupsData!==false){
			foreach($groupsData as $gd){
				$_groupid=$gd["groupid"];
				$groupById[$_groupid]=$gd;
			}
			foreach($this->groups as $g){
				$_groupid=$g->id;
				$g->loadInformation($groupById[$_groupid]);
			}
			return true;
		}
		else {
			return false;
		}
	}
	function createGroups($data){
		foreach ($data as $d) {
			if(!isset($d["groupid"]))
			$d["groupid"]=$d["group_id"];
			$group=new Group($this->db,$d["groupid"]);
			$group->loadInformation($d);
			array_push($this->groups,$group);
			array_push($this->data,$d);
		}
		$this->updateIds();
	}
	function updateIds(){
		foreach($this->groups as $g){
			array_push($this->Ids,$g->id);
		}
	}
	
	function get_users(){
		if(empty($this->users))
		$this->load_users();
		return $this->users;
	}
	function getTickets($condition=""){
		if(count($this->getIds())>0){
			$this->tickets->setGroups($this->getIds());
			$this->tickets->setSeen($this->onlineUser->get_seen());
			$this->tickets->loadTickets();
		}
		else{
			// when a company or user doesn't have any group just exit, this scenario shouldn't happen
			return false; 
		} 
		return $this->tickets;
	}
	
	function getIds(){
		if(count($this->Ids)<1)
		$this->updateIds();
		return $this->Ids;
	}
	function getData(){
		return $this->data;
	}
}
?>