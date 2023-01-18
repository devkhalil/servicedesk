<?php
Class Apps {
	public $apps=array();
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
		$this->onlineUser=& $onlineUser;
	
	}
	function get_users(){
		if(empty($this->users))
		$this->load_users();
		return $this->users;
	}
	function get_user_apps(){
        
    }
	function getData(){
		return $this->data;
	}
}
?>