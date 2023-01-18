<?php
Class Tickets {
    private $db;
	private $has_loaded=false;
	private $Ids=array();
	private $data=array();
    private $groupsFilter=[];
    private $ownerFilter=0;
    private $assigneeFilter=0;
    private $creatorFilter=0;
    private $filter=[];
    private  $sqlFilter="";
    private  $sqlBruteFilter="";
    private  $sqlCompanyOnClause="";
    private $creator=0;
    private $owner=0;
    private $company=0;
    private $assignee=0;
    private $excludeAssignee=0;
    private $groups=0;
    private $related=false;
    private $relatedUser;
    private $status=0;
    private $user_condition="";
    private $status_condition="t.status!=3";
    private $sqlOrder="t.updated DESC";
    public $tickets=array();
    public $seen=array();
    private $condition;
    private $role=0;
    private $firstWhere=true;
    private $filterLabels=array('statusOpen' => 't.status','status' => 't.status','assignee' => 't.assignedto',"excludeAssignee"=>"t.assignedto","creator"=>"t.uid","owner"=>"t.ownedby","groups"=>"t.groupid","duration"=>"t.updated");
	function __construct($db,$data=0){
        global $onlineUser;
        $this->onlineUser=& $onlineUser;
        $this->db=$db;
        $this->Db=new Db($this->db);
        if($data!==0)
        $this->createTickets($data);
        $this->lastInsertId=0;
    }
    function setCreator($creator){
        $this->addToFilter("creator");
        $this->removeFromFilter("owner");
        $this->removeFromFilter("assignee");
        $this->creator=$creator;
        $this->assignee=0;
        $this->owner=0;
    }
    function setOwner($owner){
        $this->addToFilter("owner");
        $this->removeFromFilter("creator");
        $this->removeFromFilter("assignee");
        $this->owner=$owner;
        $this->creator=0;
        $this->assignee=0;
    }
    function setRelated($user){ //this condition function isn't following the same logic as the other filters
        $this->related=true;
        $this->relatedUser=$user->id;
    }
    function setRole($role){ //this condition function isn't following the same logic as the other filters
        $this->role=$role;
    }
    function insert($subject, $description, $severity,$assignedto,$ownedby,$status,$groupid,$userId=0){
        if($userId==0) 
        $uid = isset($this->onlineUser->id)?$this->onlineUser->id:0;
        else
        $uid =$userId;
        $ownedby=($ownedby==0)?$uid:$ownedby;
        $opened = date('Y-m-d H:i:s');
        $test=$this->Db->query('INSERT INTO Tickets (uid, subject, description, status, opened, updated, severity, assignedto,ownedby,groupid,lastComment,userComment,timeComment) 
        VALUES (:uid, :subject, :description, :status, :opened, :updated, :severity,:assignedto,:ownedby,:groupid,:lastComment,:userComment,:timeComment ) ',
        array($uid,$subject,$description,$status,$opened,$opened,$severity,$assignedto,$ownedby,$groupid,$description,$uid,$opened)); 
        $this->lastInsertId=$this->Db->lastInsertId();
    }
    function getLastInsertId(){
        return $this->lastInsertId;
    }
    function setAssignee($assignee){
        $this->addToFilter("assignee");
        $this->removeFromFilter("owner");
        $this->removeFromFilter("creator");
        $this->assignee=$assignee;
        $this->owner=0;
        $this->creator=0;
    }
    function setCompany($company){
        $this->sqlCompanyOnClause=" AND u.CompanyId=$company->id";
    }
    function orderBy($field,$order){
        $this->sqlOrder=" t.$field $order, t.updated $order";
    }
    
    function waitingOnUserOnly(){
        $this->addToFilter("status");
        $this->status=1;
    }
    function waitingOnTechOnly(){
        $this->status=2;
        $this->addToFilter("status");
    }
    function closedOnly(){
        $this->addToFilter("status");
        $this->status=3;
    }

    //these 3 functions are alternatives to the ones above, they can be combinaed with each other
    function notWaitingOnUser(){
        $status=1;
        $this->sqlBruteFilter.=" AND t.status!=$status";
    }
    function notWaitingOnTech(){
        $status=2;
        $this->sqlBruteFilter.=" AND t.status!=$status";
    }
    function notClosed(){
        $status=3;
        $this->sqlBruteFilter.=" AND t.status!=$status";
    }

    //Severity filters
    function notNone(){
        $severity=1;
        $this->sqlBruteFilter.=" AND t.severity!=$severity";
    }
    function notMinor(){
        $severity=2;
        $this->sqlBruteFilter.=" AND t.severity!=$severity";
    }
    function notMajor(){
        $severity=3;
        $this->sqlBruteFilter.=" AND t.severity!=$severity";
    }
    function notEmergency(){
        $severity=4;
        $this->sqlBruteFilter.=" AND t.severity!=$severity";
    }

    // These 3 functions are alternatives to setAssignee, setOwner, setCreator. They can be combined to form an AND where clause 
    function assignedTo($user){
        $this->sqlBruteFilter.=" AND t.assignedto=$user->id ";
    }
    function ownedBy($user){
        $this->sqlBruteFilter.=" AND t.ownedby=$user->id ";
    }
    function createdBy($user){
        $this->sqlBruteFilter.=" AND t.uid=$user->id ";
    }
    function topic($group){
        $this->sqlBruteFilter.=" AND t.groupid=$group->id ";
    }
    function setDuration($days){
        $this->addToFilter("duration");
        $today=date('Y-m-d h:i:s', time());
        $this->duration[1]= date('Y-m-d 00:00:00',strtotime($today . "-$days days"));
        $this->duration[0]=date('Y-m-d h:i:s',strtotime($today . "+1days"));;
    }
    function setStatus($status){
        
        
        switch ($status) {
            case 'open':
            $this->status_condition="t.status !=3";
            break;
            case 'closed':
            $this->status_condition="t.status =3";
            break;
            case 'waitingOnMeOnly':
            $this->status_condition="t.status =2";
            break;
            default:
            $this->status_condition="t.status !=3";
            break;
        }
    }
    function excludeAssignee($assignee){
        $this->addToFilter("excludeAssignee");
        $this->excludeAssignee=$assignee;
    }
    function setSeen($seen){
        $seen=array_map('intval', explode(',', $seen));
        $seen = implode("','",$seen);
        $this->seen=$seen;
    }
    function setGroups($groups){
        $this->addToFilter("groups");
        $this->groups=$groups;
    }
    function addToFilter($field){
        if(!isset($this->filter[$field]))
        $this->filter[]=$field;
    }
    function removeFromFilter($field){
        $this->filter=array_diff($this->filter,array($field));
    }
    function prepareFilterSql(){
        $this->clearFilter();
        foreach($this->filter as $f){ 
            if( $this->{$f}!=null){
                    $this->sqlFilter.="AND ";
                switch ($f) {
                    case 'groups':
                    $this->addInCondition($f);
                    break;
                    case 'excludeAssignee':
                    $this->addDifferentCondition($f);
                    break;
                    case 'duration':
                    $this->addBetweenCondition($f);
                    break;
                    case 'statusOpen':
                    $this->addDifferentCondition($f); 
                    break;
                    
                    default:
                    $this->addEqualCondition($f);
                    break;
                } 
                $this->firstWhere=false;
                $this->sqlFilter.=" ";
            }
        }
        if($this->related){
            $this->sqlFilter.=" AND ";
            $this->sqlFilter.="({$this->filterLabels['assignee']}=$this->relatedUser OR {$this->filterLabels['creator']}=$this->relatedUser OR {$this->filterLabels['owner']}=$this->relatedUser )";
        }
        if($this->status==0){ //if there's no specific status and the search role is not creator , then simply show tickets that are not closed
            
            
            if($this->onlineUser->isTechie()&&$this->role!="creator")
            $this->addNotClosedCondition();
        }
    }
    function addEqualCondition($filterElement){
        $sqlFilterElement=$this->filterLabels[$filterElement];
        $this->sqlFilter.="$sqlFilterElement = ".$this->{$filterElement};
    }
    function addDifferentCondition($filterElement){
        $sqlFilterElement=$this->filterLabels[$filterElement];
        $this->sqlFilter.="$sqlFilterElement != ".$this->{$filterElement};
    }
    function addInCondition($filterElement){
        $sqlFilterElement=$this->filterLabels[$filterElement];
        $joinedFilterElement=implode(",",$this->{$filterElement});
        $this->sqlFilter.="$sqlFilterElement IN (".$joinedFilterElement.")";
    }
    function addBetweenCondition($filterElement){
        $sqlFilterElement=$this->filterLabels[$filterElement];
        $filterElementMin=$this->{$filterElement}[0];
        $filterElementMax=$this->{$filterElement}[1];
        // $this->sqlFilter.="$sqlFilterElement BETWEEN  ('$filterElementMax'  AND '$filterElementMin' ) ";
        $this->sqlFilter.="$sqlFilterElement BETWEEN  CAST('$filterElementMax' AS DATETIME) AND CAST('$filterElementMin' AS DATETIME) ";
    }
    function addNotClosedCondition(){
        $this->sqlFilter.="AND t.status != 3 ";
    }
    
	function createTickets($data){
		foreach ($data as $d) {
            
			if(!isset($d["id"]))
			$d["id"]=$d["ticketid"];
			$ticket=new Ticket($this->db,$d["id"]);
			$ticket->loadData($d);
			array_push($this->tickets,$ticket);
			array_push($this->data,$d);
		}
		$this->updateIds();
    }
    function countTicketsHist($userid){
      return $this->Db->get_var("SELECT count(histid) FROM TicketHist WHERE poster=$userid");
    }
	function loadTickets($tickets=0){ 
        if($tickets==0){ 
            $onlineUserId=$this->onlineUser->id;
            $this->prepareFilterSql(); 
            $seen=$this->onlineUser->get_seen();
                   
            $first_image_subquery="(SELECT a.storefilename FROM attachments as a WHERE a.ticketid=t.ticketid
             AND a.mimetype IN ('image/gif','image/png' ,'image/jpg','image/jpeg','image/ico','image/ps','image/psd','image/svg','image/tif','image/tiff','image/bmp') LIMIT 1) as attachments";
            $count_comment_subquery="(SELECT COUNT(th.ticketid) FROM TicketHist as th WHERE th.ticketid=t.ticketid) as count";
            $seen=($seen=="")?"0":$seen;
            $requestsql="SELECT t.*, 
            DATE_FORMAT(updated,'".SQL_DATE_FORMAT."') as updated,
            DATE_FORMAT(timeComment,'".SQL_DATE_FORMAT."') as timeComment,
            $count_comment_subquery,
            u.userid,u.DisplayName,u.avatar,u.Telephone,
            g.name as group_name,
            CASE WHEN t.status !=3 THEN 'true' ELSE '' END as openticket,
            CASE WHEN t.status =1 THEN 'waiting on user' WHEN t.status =2 THEN 'waiting on staff' ELSE 'closed'  END as actualstatus,
            CASE WHEN t.assignedto !=$onlineUserId THEN ''  ELSE 'true' END as canClaim,
            CASE WHEN t.ticketid IN (".$seen.") THEN '' ELSE 'true' END as seen,
            createdby.userid as createdbyid,createdby.DisplayName as createdbyName,createdby.avatar as createdbyAvatar,createdby.email as createdbyemail,createdby.Telephone as createdbyphone,
            ownedby.userid as ownedbyid,ownedby.DisplayName as ownedbyName,ownedby.avatar as ownedbyAvatar,ownedby.email as ownedbyemail,ownedby.Telephone as ownedbyphone,
            uassignedTo.userid as assignedToid,uassignedTo.DisplayName as assignedToName,uassignedTo.avatar as assignedToAvatar,uassignedTo.email as assignedToemail,uassignedTo.Telephone as assignedTophone,
            $first_image_subquery 
            FROM Tickets t 
            LEFT JOIN groups g ON g.groupid=t.groupid 
            LEFT JOIN Users createdby ON createdby.userid=t.uid 
            LEFT JOIN Users ownedby ON ownedby.userid=t.ownedby 
            LEFT JOIN Users uassignedTo ON uassignedTo.userid=t.assignedto 
            INNER JOIN Users u ON u.userid=t.uid $this->sqlCompanyOnClause
            WHERE 1=1
            $this->sqlFilter $this->sqlBruteFilter ORDER BY $this->sqlOrder";
            $ticketsData = $this->Db->get_results($requestsql); 
            // exit;
		}
		else{
            $ticketsData=$tickets;
        }
        
        $this->createTickets($ticketsData);
        $this->has_loaded=true;
    }
    function groupWhereClause($user,$waitingOnMe){
        //query made by Richard himself, insert without asking questions, do not edit
        $or=$waitingOnMe? "":"or t.assignedto = 0";
        $this->sqlBruteFilter.=" AND ((t.status = 2 and (t.assignedto = $user->id $or)) OR (t.status=1 AND t.ownedby = $user->id)) ";
    }
    
	function getTickets(){
		if(empty($this->tickets) && !$this->has_loaded)
        $this->loadTickets();
		return $this->tickets;
    }
    function addGroupsToFilter($groups){
        foreach ($groups as $key => $value) {
            $this->group_ids[]=$value["group_id"];
        }
        $this->group_ids = join("','",$this->group_ids); 
        $this->groups_condition="t.groupid IN ('".$this->group_ids."')";
    }
    
    function clearFilter(){
        $this->sqlFilter="";
    }
    
    
	function updateIds(){
		foreach($this->tickets as $t){
			array_push($this->Ids,$t->id);
		}
	}
	function getIds(){
		if(count($this->Ids)<1)
		$this->updateIds();
		return $this->Ids;
	}
	function getData(){
		if(empty($this->tickets) && !$this->has_loaded)
        $this->loadTickets();
		return $this->data;
    }
}
?>