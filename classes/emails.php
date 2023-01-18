<?php 
function renameProprety($data,$oldProprety,$newProprety,$delete="yes"){
    if(isset($data->{$oldProprety})){
        $data->{$newProprety}=$data->{$oldProprety};
        if($delete=="yes")
        unset($data->{$oldProprety});
    }
}

$emailSchemes=array(
    "normalize"=>function($data,$style){ //this scheme reformates the data proprety names in order to make them general (all lowercase) for example: data.CompanyName becomes data.companyname
        $newData=(object)array();
        foreach ($data as $key => $value) {
            $newData->{strtolower($key)}=$value;
            unset($data->{$key});
        }
        
        return $newData;
    },
    "company"=>function($data,$style){
        renameProprety($data,"companyaddress","companyaddressblock","no");
        $data->headerblock="<div style='".$style['header_style']."'><img style='".$style['header_img_style']."' src='".SITE_URL."/assets/images/servicedesk.png'></div>";
        return $data;
    },
    "group"=>function($data,$style){
        renameProprety($data,"name","groupname","no");
        return $data;
    },
    "password"=>function($data,$style){
        $data->passwordresetlink=SITE_URL."reset_password/".$data->token;
        return $data;
    },
    "newpassword"=>function($data,$style){
        highlight_string("<?php\n\$data->token =\n" . var_export($data->token, true) . ";\n?>");
        $data->passwordresetlink=SITE_URL."setup_password/".$data->token;
        return $data;
    },
    "recipient"=>function($data,$style){
        renameProprety($data,"displayname","recipientname","no");
        renameProprety($data,"displayname","recipientusername");
        return $data;
    },
    "login"=>function ($data,$style){
        $data->validationcode=SITE_URL."login?token=".$data->validationcode;
        renameProprety($data,"validationcode","confirmationlink");
        return $data;
    },
    "ticket"=>function ($data,$style){
        $ticket=$data->ticket;
        unset($data->ticket);
        $ticketLink=SITE_URL."tickets/ticket/".$ticket->id;
        $data->ticketlink=$ticketLink;
        switch ($ticket->data['status']) {
            case 1:
            $data->ticketstatus="waiting on user";
            break;
            case 2:
            $data->ticketstatus="waiting on staff";
            break;
            case 3:
            $data->ticketstatus="closed";
            break;
            default:
            # code...
            break;
        }
        $comments=$ticket->getComments();
        switch ($ticket->data['severity']) {
            case '1':
            $severity = 'None';
            break;
            case '2': 
            $severity = 'Minor - 8 Hours';
            break;
            case '3':
            $severity = 'Major - 4 Hours';
            break;
            case '4':
            $severity = 'Emergency - 1 Hour';
            break;
            default:
            $severity="None";
            break;
        }
        $data->subjectblock="<div><h3 style='{$style['title']}'>Subject</h3><span stylr='{{$style['subject']}}'>{$ticket->data['subject']}</span> </div> ";
        $threadblock="<div style='".$style['comment_container']."'><h3 style='{$style['title']}'>Discussion Thread</h3>";
        $threadblock.= "<div style='{$style['comments_history']}'>";
        foreach ($comments  as $c) {
            $commentMessage="";
            switch ($c["status"]) {
                case 1:
                $commentMessage="{$c['DisplayName']} said ";
                break;
                case 2:
                $commentMessage="Ticket re-assigned to {$c['assignedtouser']} ";
                break;
                case 3:
                $commentMessage="Ticket closed";
                break;
                case 4:
                $commentMessage="{$c['DisplayName']} claimed this ticket";
                break;
                case 5:
                $commentMessage="";
                break;
                case 6:
                $commentMessage="{$c['DisplayName']} changed the ticket's topic to {$c['topicName']}";
                break;
                case 7:
                $commentMessage="{$c['DisplayName']} re-opened this ticket";
                break;
                case 8:
                $commentMessage="{$c['DisplayName']} unassigned this ticket";
                break;
            }
            if(!($c['comments']=="" && $c["status"]==1)){
                $commentOwnerStyle=($c["techie"]=="1")?$style['comment_tech']:$style['comment_simple'];
                $threadblock.="<table style='{$commentOwnerStyle}'>
                <tr><td  style='{$style['comment_title']}'>{$commentMessage}</td>
                <td style='{$style['comment_time']}'>{$c['originalTime']}</td></tr>
                </table>";
                $threadblock.="<div style='{$style['comment_text']}'>{$c['comments']}</div>";
            }
        }
        $threadblock.= "</div>";
        $referenceBlock="<div><h3 style='{$style['title']}'>Ticket Reference #$ticket->id</h3><table>";
        $referenceBlock.="<tbody>";
        $referenceBlock.="<tr><td style='{$style['left_col']}'>Created By:</td><td>{$ticket->data['createdbyName']}</td></tr>";
        $referenceBlock.="<tr><td style='{$style['left_col']}'>Created On:</td><td>{$ticket->data['opened']}</td></tr>";
        $referenceBlock.="<tr><td style='{$style['left_col']}'>Owner:</td><td>{$ticket->data['ownedbyName']}</td></tr>";
        $referenceBlock.="<tr><td style='{$style['left_col']}'>email:</td><td>{$ticket->data['ownedbyemail']}</td></tr>";
        $referenceBlock.="<tr><td style='{$style['left_col']}'>Phone:</td><td>{$ticket->data['ownedbyphone']}</td></tr>";
        $referenceBlock.="<tr><td style='{$style['left_col']}'>Severity:</td><td>$severity</td></tr>";
        $referenceBlock.="</tbody>";
        $referenceBlock.="</table></div>";
        $data->threadblock=$threadblock;
        $data->referenceblock=$referenceBlock;
        return $data;
    },
    "TicketOpen"=>function($data){
        
    },
    "TicketUpdate"=>function($data){
        
    },
    "TicketClose"=>function($data){
        
    },
    "TicketReminder"=>function($data){
        
    },
    "PasswordReminder"=>function($data){
        
    }
);
$fontSize="12px";
$style=array(
    "container"=>"white-space: pre-line;overflow:hidden;font-size:$fontSize",
    "title"=>"background:silver;margin:1px 0;padding:5px 0",
    "subject"=>";font-size:$fontSize",
    // "comment"=>" margin-top: 20px;white-space: initial;padding-left: 12px;padding-right: 12px;padding-bottom:5px;padding-top:5px;border-radius: 5px;position: relative;background-color: rgb(240, 245, 247);box-shadow: rgba(0, 0, 0, 0.15) 0px 1px 1px 0px;width: 90%;",
    "comments_history"=>"padding-left:10px;font-size:$fontSize",
    "comment_tech"=>"width:100%;background:#ccffcc;margin-bottom:3px;font-size:$fontSize",
    "comment_simple"=>"width:100%;background:#ccf;margin-bottom:3px;font-size:$fontSize",
    "comment_title"=>"padding-bottom:5px;font-weight:900;;font-size:$fontSize",
    "left_col"=>"padding-right:10px;font-weight:900;text-align:right;font-size:$fontSize",
    "comment_container"=>";font-size:$fontSize",
    "comment_text"=>"padding:5px;margin-bottom:5px;font-size:$fontSize",
    "comment_time"=>"text-align:right;font-size:$fontSize",
    "header_style"=>"background-color:#0d1b28;padding:10px;width:100%;margin-bottom:30px;font-size:$fontSize",
    "header_img_style"=>"width:90%;max-width:250px;font-size:$fontSize",
    "signature"=>"margin-bottom:10px;font-size:$fontSize",
    "link"=>";font-size:$fontSize"
);
Class DeskEmail{
    public $name;
    public $body;
    public $Db;
    public $company;
    public $data;
    public $initialEmailTemplate;
    public $emailTemplate;
    public $mailer;
    public $subject;
    public $recipient;
    function __construct($db,$company,$name){
        global $emailSchemes;
        global $style;
        $this->company=$company;
        $this->Db=new Db($db);
        $this->style=$style;
        $this->name=$name;
        $this->loadTemplate();
        $this->data=(object) array();
        $this->emailSchemes=$emailSchemes;
        $this->mailer=initMailer();
        $this->mailer->From=$company->information["CompanyEmail"];
        $this->mailer->FromName=$company->information["CompanyName"];
    }
    function loadTemplate(){
        $this->emailTemplate=$this->Db->get_var("SELECT emt".$this->name." FROM Companies WHERE CompanyID=:CompanyID",array($this->company->id));
        if($this->emailTemplate===false){
            die("email template not found");
        }
        else {
            $this->initialEmailTemplate=$this->emailTemplate;
            $this->data=(object) array();
        }
    }
    function resetTemplate(){
        $this->emailTemplate=$this->initialEmailTemplate;
    }
    function getTemplate(){
        return $this->emailTemplate;
    }
    function setTemplate($newTemplate){
        $this->emailTemplate=$newTemplate;
    }
    function replaceLabels($data){
        preg_match_all("/{{([a-zA-z0-9_]*)}}/",$this->emailTemplate , $out, PREG_PATTERN_ORDER);
        $i=0;
        foreach ($out[0] as $label) {
            if(isset($data->{strtolower($out[1][$i])})){
                $dataValue=$data->{strtolower($out[1][$i])};
                $this->emailTemplate=str_ireplace($label,$dataValue,$this->emailTemplate);
            }
            $i++;
        }
    }
    function applyScheme($emailSchemeName,&$data=""){
        if(is_object($data))
        $newData=clone $data;
        else {
            $newData=$data;
        }
        if($newData==""){
            $newData=$this->data;
            $formattedData=$newData;
        }
        else{
            $formattedData=$newData;
            $formattedData=$this->emailSchemes["normalize"]($formattedData,$this->style);
        }
        $formattedData=$this->emailSchemes[$emailSchemeName]($formattedData,$this->style);
        $this->data=$formattedData;
        $this->replaceLabels($formattedData);
    }
    function addRecipient($user){
        $email=$user->information["email"];
        $name=$user->information["DisplayName"];
        $this->recipient=$user;
        $this->mailer->ClearAllRecipients( ); 
        if(DEBUG_EMAIL_MODE)
        $this->mailer->addAddress(DEBUG_EMAIL_ADDRESS,$name);
        else
        $this->mailer->addAddress($email,$name);
    }
    function addsubject($subject){
        $this->subject=$subject;
        
    }
    function setTicketSubject($ticket,$action){
        
        switch ($ticket->data['severity']) {
            case '1':
            $severity = 'None';
            break;
            case '2': 
            $severity = 'Minor';
            break;
            case '3':
            $severity = 'Major';
            break;
            case '4':
            $severity = 'Emergency';
            break;
            default:
            $severity="None";
            break;
        }
        $actionLetter=strtoupper($action[0]);
        $this->addSubject("#$ticket->id:$severity:$actionLetter# {$ticket->data['subject']} ");
    }
    function getUserTypePart($userType){
        $userTypes=array("user","tech");
        $removingTagName=($userType==$userTypes[0])?$userTypes[1]:$userTypes[0];
        $cleanedBody=preg_replace("/<{$removingTagName}>[\w\W]*<\/{$removingTagName}>/",'',$this->emailTemplate);
        return $cleanedBody;
    }
    function hasRecipient(){
        return count($this->mailer->getAllRecipientAddresses())>0;
    }
    function send(){
        if(SEND_EMAILS && $this->hasRecipient()){
            $this->mailer->isHTML(true);
            $this->mailer->Subject= $this->subject;
            $additional_text="";
            $recipientType=($this->recipient->isTechie()===true)?"tech":"user";
            $userSpecificTemplate=$this->getUserTypePart($recipientType);
            $body="<pre style='{$this->style['container']}'>".$userSpecificTemplate."<div style='{$this->style['signature']}'>{$this->company->information['CompanyEmailSignature']}</div></pre>";
            $body=str_replace("<a","<a style='{$this->style['link']}' ",$body);
            $this->mailer->Body=$body;
            // echo $this->mailer->Body;
            // echo "<br>Email supposed to be sent to ".$this->recipient->information['email'] ;
            if(!$this->mailer->send()){
                echo 'Mailer Error: ' . $this->mailer->ErrorInfo;
            } 
            else{
                return true;
            }
            
        }
    }
}
?>