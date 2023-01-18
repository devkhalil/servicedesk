<?php
Class Ticket {
	public $id;
	public $has_loaded;
	public $db;
	public $data;
	public $status;
	public $lastCommentId;
	public $companyUrl;
	public $owner;
	public $assignee;
	public $creator;
	public $group;
	function __construct($db,$id=0){
		global $onlineUser;
		global $currentCompany;
		$this->onlineUser=& $onlineUser;
		$this->currentCompany=& $currentCompany;
		$this->db=$db;
		$this->Db=new Db($this->db);
		$this->id=$id;
	}
	function updateAssign($assignee){
		$updated = date('Y-m-d H:i:s');
		$id=$this->id;
		$this->Db->query("UPDATE Tickets SET updated=:updated,assignedto=:assignee WHERE ticketid=:ticketid",array($updated,$assignee,$this->id));
	}
	function updateTopic($topic){
		$updated = date('Y-m-d H:i:s');
		$id=$this->id;
		$this->Db->query("UPDATE Tickets SET updated=:updated,groupid=:groupid WHERE ticketid=:ticketid",array($updated,$topic,$this->id));
	}
	function updateStatus($status){
		$updated = date('Y-m-d H:i:s');
		$this->Db->query("UPDATE Tickets SET status=:status,updated=:updated WHERE ticketid=:ticketid",array($status,$updated,$this->id));
	}
	function getOwner(){
		if(empty($this->data))
		$this->loadData();
		return $this->owner;
	}
	function getGroup(){
		if(empty($this->group)){
			$groupid=$this->data["groupid"];
			$this->group=new Group($this->db,$groupid);
		}
		return $this->group;
	}

	function getAssignee(){
		if(empty($this->data))
		$this->loadData();
		return $this->assignee;
	}
	function getCreator(){
		if(empty($this->data))
		$this->loadData();
		return $this->creator;
	}
	function loadData($data=0){
		if($data==0){ // if no data has been passed then load from database
			$onlineUserIsTechie=$this->onlineUser->isTechie()?"true":"false";
			$ticketData = $this->Db->get_row("SELECT t.*,
			g.name as group_name,
			createdby.userid as createdbyid,
			createdby.DisplayName as createdbyName,
			createdby.avatar as createdbyAvatar,
			createdby.email as creatorOriginalEmail,
			createdby.companyID as companyID,
			CASE WHEN t.assignedto !=:uid THEN ''  ELSE 'true' END as canClaim,
			CASE WHEN '$onlineUserIsTechie'='true' THEN createdby.email WHEN createdby.techie=0 THEN createdby.email ELSE createdby.techemail END as createdbyemail,
			CASE WHEN '$onlineUserIsTechie'='true' THEN createdby.Telephone WHEN createdby.techie=0 THEN createdby.Telephone ELSE createdby.techphone END as createdbyphone,
			ownedby.userid as ownedbyid,
			ownedby.DisplayName as ownedbyName,
			ownedby.avatar as ownedbyAvatar,
			CASE WHEN '$onlineUserIsTechie'='true' THEN ownedby.email 
			WHEN ownedby.techie=0 THEN ownedby.email 
			ELSE ownedby.techemail END as ownedbyemail,
			CASE WHEN '$onlineUserIsTechie'='true' THEN ownedby.Telephone WHEN ownedby.techie=0 THEN ownedby.Telephone ELSE ownedby.techphone END as ownedbyphone,
			uassignedTo.userid as assignedToid,
			uassignedTo.DisplayName as assignedToName,
			uassignedTo.avatar as assignedToAvatar,

			CASE WHEN uassignedTo.techie=0 THEN uassignedTo.email 
			WHEN '$onlineUserIsTechie'='true' THEN uassignedTo.email 
			ELSE uassignedTo.techemail END as assignedToemail,
			
			CASE WHEN '$onlineUserIsTechie'='true' THEN uassignedTo.Telephone 
			WHEN uassignedTo.techie=0 THEN uassignedTo.Telephone ELSE uassignedTo.techphone END as assignedToTelephone,
			CASE WHEN t.ownedby !=:uid THEN 'true' ELSE '' END as ownedbyuser,
			CASE WHEN t.status =3 THEN 'true' ELSE '' END as closedticket,
			CASE WHEN t.status =1 THEN 'waiting on user' WHEN t.status =2 THEN 'waiting on staff' ELSE 'closed'  END as actualstatus
			FROM Tickets as t 
			CROSS JOIN Users createdby ON createdby.userid=t.uid -- AND createdby.companyID=:companyID  
			LEFT JOIN Users ownedby ON ownedby.userid=t.ownedby 
			LEFT JOIN Users uassignedTo ON uassignedTo.userid=t.assignedto 
			LEFT JOIN groups g ON g.groupid=t.groupid 
			WHERE t.ticketid=:ticketid 
			GROUP BY t.ticketid ORDER BY t.opened DESC LIMIT 1",array($this->onlineUser->id,$this->currentCompany->id,$this->id));
		}
		else{
			$ticketData=$data;
		}
		$this->id=$ticketData["ticketid"];
		$this->data=$ticketData;
		
		$this->owner=new User($this->db,$this->data["ownedby"]);
		if(is_null($this->data["assignedToid"]) || $this->data["assignedToid"]=="")
		$this->assignee=false; //this ticket isn't assigned to anyone
		else{}
		$this->assignee=new User($this->db,$this->data["assignedToid"]);
		$this->creator=new User($this->db,$this->data["createdbyid"]);
		$this->has_loaded=true;
	}
	/**
	 * checks if this ticket is related to a user, this is mostly used to identify if a user has the right to see this ticket or not
	 */
	function isRelatedToUser($user){
		return $this->data["assignedToemail"]==$user->information["email"] || $this->data["ownedbyemail"]==$user->information["email"] || $this->data["creatorOriginalEmail"]==$user->information["email"];
	}
	function hasGroupAccessRightForUser($user){
		$userGroups=$user->getGroups()->getData();
		if($this->data["assignedto"]=="" || $this->data["assignedto"]=="0") // a user has group access right on a ticket only if the ticket isn't assigned to someone else already
		foreach($userGroups as $ug){
			if($ug['group_id']==$this->data["groupid"] )
			return true;
		}
		return false;
	}
	function insertIntoDb(){ 
		$data=$this->Db->query("SELECT u.userid,u.name,u.avatar FROM users_groups ug INNER JOIN Users u ON u.userid=ug.userid WHERE ug.group_id = :group_id ",array($this->id));
		$this->groups=$data;
	}
	function isClosed(){
		if(empty($this->data))
		$this->loadData();
		return $this->data["status"]=="3";
	}
	function getData(){
		if(empty($this->data))
		$this->loadData();
		return $this->data;
	}
	function setCompanyUrl($url){
		$this->companyUrl=$url;
	}
	function belongsToCompany($company){
		return $this->data["companyID"]==$company->id;
	}
	function getComments($empty=0){
		 $comments=$this->Db->get_results("
		 SELECT th.histid,th.*,u.userid,u.DisplayName,u.avatar,u.techie,
		 a.DisplayName as assignedtouser,
		 ts,
		 g.name as topicName, g.groupid as topicId,
		 DATE_FORMAT(ts,'".SQL_DATE_FORMAT."') as originalTime,
		  att.storefilename,att.attid,att.opened 
		  FROM TicketHist as th 
		  LEFT JOIN Users u ON u.userid=th.poster 
		  LEFT JOIN Users a ON th.assignedto!=0 AND th.assignedto=a.userid 
		  LEFT JOIN groups g ON th.topic!=0 AND th.topic=g.groupid
		  LEFT JOIN attachments att ON att.histid=th.histid 
		  WHERE th.ticketid=:ticketid  GROUP BY th.histid,att.storefilename,att.attid ORDER BY th.histid DESC",array($this->id));
		  $newComments=array();

		  foreach ($comments as $c) { 
			$c["ts"]=time_elapsed($c["ts"]);
			if($empty==1){ 
				if($c["comments"]=="" && $c["status"]==1){
					
				}
				else{
					unset($c["technotes"]);
					array_push($newComments,$c);
				}
			}
			else {
				array_push($newComments,$c);
			}
		}
		return $newComments;
	}
	function removeComment($commentId){
		$this->Db->query("DELETE FROM TicketHist WHERE histid=$commentId LIMIT 1");
		$attachments=$this->Db->get_results("SELECT * FROM attachments WHERE histid=$commentId");
		if(count($attachments)>0){
			//delete any attachment that's linked to this comment
			foreach ($attachments as $a) {
				$_storeFilename=$a['storefilename'];
				$_ticketId=$a['ticketid'];
				$sourceImage=UPLOAD_PATH."/$_ticketId/$_storeFilename";
				$isWritable=is_writable($sourceImage);
				if($isWritable){
					unlink($sourceImage);
				}
			}
		}
		$this->Db->query("DELETE FROM attachments WHERE histid=$commentId ");
	}
	function addComment($comment,$technote,$commentStatus,$charges,$assignee=0,$topic=0,$poster=0){
		if($poster===0){
			$posterId=$this->onlineUser->id;
			$posterName=$this->onlineUser->DisplayName;
		}
		else{
			$posterId=$poster->id;
			$posterName=$poster->DisplayName;
		}
		$ticketId=$this->id;
		$ts=date('Y-m-d H:i:s');
		$assignee=($assignee=="")?0:$assignee;
		$charges=($charges=="")?0:$charges;
		$comment=($comment=="")?"":$comment;
		$test=$this->Db->query("INSERT INTO  TicketHist (poster, ticketid, comments, status, ts, technotes, charges,assignedto,topic) VALUES (:poster, :ticketid, :comments, :status, :ts, :technotes, :charges,:assignee,:topic )",array($posterId,$ticketId,$comment,$commentStatus,$ts,$technote,$charges,$assignee,$topic));

		$this->lastCommentId=$this->Db->lastInsertId();
		// if($commentStatus==1){ //condition before I allowed all comments types to be considered for last comments
		if($comment!=""){ // $comment can be empty on close,reassign,change topic... comments
			$this->Db->query("UPDATE Tickets SET lastComment=:lastComment,userComment=:userComment,timeComment=:timeComment WHERE ticketid=:ticketid",array($comment,$posterName,$ts,$ticketId));
		}
	} 
	// function linkComment($id){
	// 	$histId=$this->lastCommentId;
	// 	if(is_array($id)){
	// 		$ids=implode(",",$id);
	// 		$where="histid IN ($ids)";
	// 	}
	// 	else $where="histid = $id";
	// 	$attachments=$this->Db->get_results("SELECT * FROM attachments WHERE $where AND ticketid=0 ");
	// 	if(count($attachments)>0){
	// 		$destinationDir = UPLOAD_PATH."/{$this->id}";
	// 		if (!file_exists($destinationDir)){
	// 			mkdir ($destinationDir, 0755,true);
	// 		}
	// 		//move attchment files from temp folder to the new ticket's folder
	// 		foreach ($attachments as $a) {
	// 			$_storeFilename=$a->storefilename;
	// 			$sourceImage=UPLOAD_PATH."/0/$_storeFilename";
	// 			$destinationImage=$destinationDir.'/'.$_storeFilename;
	// 			rename($sourceImage, $destinationImage);
	// 		}
	// 	}
	// 	$this->Db->query("UPDATE attachments SET ticketid=$this->id WHERE $where ");
	// 	return $this->Db->query("UPDATE TicketHist SET ticketid=$this->id WHERE $where ");
	// }
		function linkComment($id,$user){
		$histId=$this->lastCommentId;
		if(is_array($id)){
			$ids=implode(",",$id);
			$where="histid IN ($ids)";
		}
		else $where="histid = $id";
		$attachments=$this->Db->get_results("SELECT * FROM attachments WHERE $where AND ticketid=0 ");
		$destinationDir = UPLOAD_PATH."/{$this->id}";
		//zip files if more than one
		if(count($attachments)>0&&count($attachments)>1){
			$zip = new ZipArchive();
			$zipName="attachement_".strtotime("now").".zip";
			$zipDirectory=$destinationDir."/".$zipName;
			if (!file_exists($destinationDir)){
				mkdir ($destinationDir, 0777,true);
			}
			$res=$zip->open($zipDirectory, ZipArchive::CREATE)===TRUE;

			//move attchment files from temp folder to the new ticket's folder
			foreach ($attachments as $a) {
				$sourceImage=UPLOAD_PATH."/0/".$a['storefilename'];
				$content = file_get_contents($sourceImage);
				$zip->addFromString($a['storefilename'],$content);
			}
			$zip->close();
			$this->Db->query("DELETE FROM attachments WHERE $where "); 
			$user->commentTicket($this->id,"attachment","",2,0);
			$this->addEmbededFormAttachement($zipName,$user); 
		}else{
			// save file as image attachement if only one attachement
			if (!file_exists($destinationDir)){
				mkdir ($destinationDir, 0755,true);
			}
			//move attchment files from temp folder to the new ticket's folder
			foreach ($attachments as $a) {
				$_storeFilename=$a->storefilename;
				$sourceImage=UPLOAD_PATH."/0/$_storeFilename";
				$destinationImage=$destinationDir.'/'.$_storeFilename;
				rename($sourceImage, $destinationImage);
			}
			$this->Db->query("UPDATE attachments SET ticketid=$this->id WHERE $where ");
			return $this->Db->query("UPDATE TicketHist SET ticketid=$this->id WHERE $where ");
		}

		// $this->Db->query("UPDATE attachments SET ticketid=$this->id WHERE $where ");
		// return $this->Db->query("UPDATE TicketHist SET ticketid=$this->id WHERE $where ");
	}
	function addEmbededFormAttachement($zip,$user){
		$file=$zip;
		$uid=$user->id;
		$ticketid=$this->id;
		$lastCommentId=$user->lastCommentedTicket->lastCommentId;
		$mimetype="application/x-zip-compressed";
		$size=filesize( UPLOAD_PATH."/{$this->id}/$file") ;
		$now= date('Y-m-d H:i:s');
		$query = $this->db->prepare('INSERT INTO attachments (by_uid, ticketid,histid, mimetype, origfilename, storefilename, origsize, opened) VALUES (:by_uid, :ticketid,:histid, :mimetype, :origfilename, :storefilename, :origsize, :opened)');
		$query->bindParam(':by_uid', $uid);    
		$query->bindParam(':ticketid', $ticketid);    
		$query->bindParam(':histid', $lastCommentId);    
		$query->bindParam(':mimetype', $mimetype);    
		$query->bindParam(':origfilename', $file);    
		$query->bindParam(':storefilename', $file);    
		$query->bindParam(':origsize', $size);    
		$query->bindParam(':opened',$now);
		$quer_result=$query->execute();
	}
	function addAttachement($attachment,$orphant=false){
		if($attachment['name']!=""){
			$name = $attachment['name'];
			$formattedName=preg_replace("/ /i", '_',$name);
			$formattedName=preg_replace("/[^a-z0-9\_\-\.]/i", '',$formattedName);
			$ext = pathinfo($formattedName, PATHINFO_EXTENSION);
			$formattedNameWithoutExt=str_replace(".$ext","",$formattedName);
			$type = $attachment['type'];
			$tmp_name = $attachment['tmp_name'];
			$size = $attachment['size'];
			$new_name = uniqid($this->lastCommentId.'-');
			$onlineUserId=($orphant)? 0 : $this->onlineUser->id;
			$ticketId=($orphant)? 0 : $this->id;
			$histId=($orphant)? 0 : $this->lastCommentId;
			$dir = $_SERVER['DOCUMENT_ROOT'].'/uploads/'.$this->id;
			$opened = date('Y-m-d H:i:s');
			$maxSize=ATTACHMENT_MAX_SIZE*1024*1024; // 1 mo = 1 * 1024 * 1024 octet
			if (!file_exists($dir)){
				mkdir ($dir, 0755,true);
			}
			$randomNumber=rand(100000,999999);
			if($size>=($maxSize)){
				return l('File size must be lower than {{$1}} mo',ATTACHMENT_MAX_SIZE);
			}
			
			if(!in_array(strtolower($ext),ATTACHMENT_ALLOWED_EXTENSIONS)){
				$allowedExtensionsString=implode(", ",ATTACHMENT_ALLOWED_EXTENSIONS);
				return l('File type not allowed',$allowedExtensionsString);
			}
			// $image=$dir.'/'.$new_name.'.'.$ext;
			// $storeName=$new_name.'.'.$ext;
			$image=$dir.'/'.$formattedNameWithoutExt."_".$randomNumber.".$ext";
			$storeName=$formattedNameWithoutExt."_".$randomNumber.".$ext";
			// if(getimagesize($tmp_name)!=false){
				if(move_uploaded_file($tmp_name, $image))
				{	
					$query = $this->db->prepare('INSERT INTO attachments (by_uid, ticketid,histid, mimetype, origfilename, storefilename, origsize, opened) VALUES (:by_uid, :ticketid,:histid, :mimetype, :origfilename, :storefilename, :origsize, :opened)');
					$query->bindParam(':by_uid', $onlineUserId);    
					$query->bindParam(':ticketid', $ticketId);    
					$query->bindParam(':histid', $histId);    
					$query->bindParam(':mimetype', $type);    
					$query->bindParam(':origfilename', $name);    
					$query->bindParam(':storefilename', $storeName);    
					$query->bindParam(':origsize', $size);    
					$query->bindParam(':opened', $opened);
					$query->execute();
					return true;
				}
				else{
					return l("File couldn't be saved");
				}
			// }
		}
	}
}
?>