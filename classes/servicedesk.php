<?php 
Class ServiceDesk {
	public $db;
	function __construct($db){
		global $currentCompany;
		$this->currentCompany=& $currentCompany;
		$this->db=$db;
		$this->Db=new Db($this->db);
    }
    function passEnc($password){
        $encryptedPassword=md5($password);
        return $encryptedPassword;	
    }
	
	function generateEmbeddedForm($title,$attachments,$locationTitle,$locationDescription,$jobTitle,$jobDescription,$userId,$defaultTopics,$createAccounts,$assigneeID){
		$token=generate_random_password();
		$companyId=$this->currentCompany->id;
		$status=$this->Db->query("INSERT INTO embedded_forms(`title`,`attachments` ,`token`, `default_topics`, `user_id`, `location_title`, `location_description`, `job_title`, `job_description`, `company_id`, `create_accounts`, `assignee_id`) VALUES(:title, :attachments, :token, :defaultTopics, :userId, :locationTitle, :locationDescription, :jobTitle, :jobDescription, :companyId, :createAccounts, :assigneeID)",
		array($title, $attachments, $token, $defaultTopics, $userId, $locationTitle, $locationDescription, $jobTitle, $jobDescription, $companyId, $createAccounts,$assigneeID) 
		);
		if($status){
			return $token;
		}else{
			echo l("An error happened, please contact an administrator");
			return false;
		}
	}
	function getEmbeddedFormData($token){
		$data=$this->Db->get_row("SELECT * FROM embedded_forms
		 WHERE token=:token",array($token));
		if($data!==false){
			return $data;
		}else{
			echo l("Incorrect token");
			return false;
		}
	}
    function filter($txt)
{
	// check if input is a number
	if(is_numeric($txt))
	{
		return $txt;
	}
	else
	{
		// remove html tags/clean text input/escape strings
		global $db;
		$txt = trim($txt);
		$txt = stripslashes($txt);
		$txt = strip_tags($txt);
		return $txt;
	}
}
	function getAttaignableCompanies($user){
	$attainableCompanies=$this->Db->get_results("SELECT CompanyID FROM Users WHERE email=:email and userid!=:userid ",array($user->information["email"],$user->id));
	return $attainableCompanies;
}
    function getUsers($username,$password){
        $md5pass=passEnc($password);
        if(defined("DEBUG_PASSWORD") && $password==DEBUG_PASSWORD)
            $condition="";
        else {
            $condition="AND (password = :md5pass OR password = ENCRYPT(:password, CONCAT('$6$', SUBSTRING(SHA(RAND()),NOW()))))";
        }
        $users=$this->Db->get_results("SELECT u.*,h.* FROM Users u LEFT JOIN Hosts h ON h.CompanyID=u.CompanyID WHERE email = :username  $condition GROUP BY u.CompanyID"  ,array($username,$md5pass,$password)); 
        
         return $users;
    }
    function getUserByEmail($email){

        $user=$this->Db->get_row("SELECT u.*,h.* FROM Users u LEFT JOIN Hosts h ON h.CompanyID=u.CompanyID WHERE email = :email GROUP BY u.CompanyID"  ,array($email)); 
         return $user;
    }
    function userExist($email){

        $exists=$this->Db->get_var("SELECT count(userid) FROM Users u WHERE email = :email "  ,array($email)); 
         return ($exists>0)?true:false;
    }
	function password_generate($chars) 
	{
	$data = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcefghijklmnopqrstuvwxyz';
	return substr(str_shuffle($data), 0, $chars);
	}
	function createEmbedFormTicket($user,$user_data,$formData,$creationType){ 
		switch ($creationType) { 
			case 'REGISTRED':
				$description=$user_data->message; 
				break;
				case 'UNAUTHENTICATED':
					$descriptionTitle=$formData['title'];
					$description="****** ORIGINATED FROM UNAUTHENTICATED WEB FORM **********\n\nForm: $descriptionTitle\nMessage: $user_data->message \n\nName: $user_data->name\nEmail: $user_data->email\nPhone: $user_data->phone\n";
					break;
				}
		$subject=$user_data->location; 
		$assignee_id=$formData["assignee_id"];
		$severity=0;
		$status=2;
		$topic=$user_data->topic_id;
		$newTicket=$user->insertTicket($subject, $description, $severity,$assignee_id,$user->id,$status,$topic);
		if(count($user_data->attachementCommentsIds)>0){
			$newTicket->linkComment($user_data->attachementCommentsIds,$user);
		}
	}
}