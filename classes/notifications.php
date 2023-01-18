<?php
function renameProprety($data, $oldProprety, $newProprety, $delete = "yes")
{
    if (isset($data->{$oldProprety})) {
        $data->{$newProprety} = $data->{$oldProprety};
        if ($delete == "yes") {
            unset($data->{$oldProprety});
        }

    }
}

$messageSchemes = array(
    "normalize" => function ($data, $style) { //this scheme reformates the data proprety names in order to make them general (all lowercase) for example: data.CompanyName becomes data.companyname
        $newData = (object) array();
        foreach ($data as $key => $value) {
            $newData->{strtolower($key)} = $value;
            unset($data->{$key});
        }

        return $newData;
    },
    "company" => function ($data, $style) {
        renameProprety($data, "companyaddress", "companyaddressblock", "no");
        $data->headerblock = "<div style='" . $style['header_style'] . "'><img style='" . $style['header_img_style'] . "' src='" . SITE_URL . "/assets/images/servicedesk.png'></div>";
        return $data;
    },
    "group" => function ($data, $style) {
        renameProprety($data, "name", "groupname", "no");
        return $data;
    },
    "password" => function ($data, $style) {
        $data->passwordresetlink = SITE_URL . "reset_password/" . $data->token;
        return $data;
    },
    "newpassword"=>function($data,$style){
        $data->passwordresetlink=SITE_URL."setup_password/".$data->token;
        return $data;
    },
    "recipient" => function ($data, $style) {
        renameProprety($data, "displayname", "recipientname", "no");
        renameProprety($data, "displayname", "recipientusername");
        renameProprety($data, "email", "recipientEmail");
        return $data;
    },
    "login" => function ($data, $style) {
        $data->validationcode = SITE_URL . "login?token=" . $data->validationcode;
        renameProprety($data, "validationcode", "confirmationlink");
        return $data;
    },
    "changeEmail" => function ($data, $style) {
        return $data;
    },
    "ticket" => function ($data, $style) {
        $ticket = $data->ticket;
        unset($data->ticket);
        if (isset($ticket->companyUrl) && !empty($ticket->companyUrl)) { 
            // ticket urls should be loaded from the ticket itself when the ticket reminder bot sends the reminders
            $ticketLink = $ticket->companyUrl . "/tickets/ticket/" . $ticket->id;
        } else {
            $ticketLink = SITE_URL . "tickets/ticket/" . $ticket->id;
        }

        $data->ticketlink = $ticketLink;
        switch ($ticket->data['status']) {
            case 1:
                $data->ticketstatus = "waiting on user";
                break;
            case 2:
                $data->ticketstatus = "waiting on staff";
                break;
            case 3:
                $data->ticketstatus = "closed";
                break;
        }
        $comments = $ticket->getComments();
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
                $severity = "None";
                break;
        }
        $data->subjectblock = "<div><h3 style='{$style['title']}'>Subject</h3><span stylr='{{$style['subject']}}'>{$ticket->data['subject']}</span> </div> ";
        $threadblock = "<div style='" . $style['comment_container'] . "'><h3 style='{$style['title']}'>Discussion Thread</h3>";
        $threadblock .= "<div style='{$style['comments_history']}'>";
        foreach ($comments as $c) {
            $commentMessage = "";
            switch ($c["status"]) {
                case 1:
                    $commentMessage = "{$c['DisplayName']} said ";
                    break;
                case 2:
                    $commentMessage = "Ticket re-assigned to {$c['assignedtouser']} ";
                    break;
                case 3:
                    $commentMessage = "Ticket closed";
                    break;
                case 4:
                    $commentMessage = "{$c['DisplayName']} claimed this ticket";
                    break;
                case 5:
                    $commentMessage = "";
                    break;
                case 6:
                    $commentMessage = "{$c['DisplayName']} changed the ticket's topic to {$c['topicName']}";
                    break;
                case 7:
                    $commentMessage = "{$c['DisplayName']} re-opened this ticket";
                    break;
                case 8:
                    $commentMessage = "{$c['DisplayName']} unassigned this ticket";
                    break;
            }
            if (!($c['comments'] == "" && $c["status"] == 1)) {
                $commentOwnerStyle = ($c["techie"] == "1") ? $style['comment_tech'] : $style['comment_simple'];
                $threadblock .= "<table style='{$commentOwnerStyle}'>
                                    <tr><td  style='{$style['comment_title']}'>{$commentMessage}</td>
                                        <td style='{$style['comment_time']}'>{$c['originalTime']}</td></tr>
                                </table>";
                $threadblock .= "<div style='{$style['comment_text']}'>{$c['comments']}</div>";
            }
        }
        $threadblock .= "</div>";
        $referenceBlock = "<div><h3 style='{$style['title']}'>Ticket Reference #$ticket->id</h3><table>";
        $referenceBlock .= "<tbody>";
        $referenceBlock .= "<tr><td style='{$style['left_col']}'>Created By:</td><td>{$ticket->data['createdbyName']}</td></tr>";
        $referenceBlock .= "<tr><td style='{$style['left_col']}'>Created On:</td><td>{$ticket->data['opened']}</td></tr>";
        $referenceBlock .= "<tr><td style='{$style['left_col']}'>Owner:</td><td>{$ticket->data['ownedbyName']}</td></tr>";
        $referenceBlock .= "<tr><td style='{$style['left_col']}'>email:</td><td>{$ticket->data['ownedbyemail']}</td></tr>";
        $referenceBlock .= "<tr><td style='{$style['left_col']}'>Phone:</td><td>{$ticket->data['ownedbyphone']}</td></tr>";
        $referenceBlock .= "<tr><td style='{$style['left_col']}'>Severity:</td><td>$severity</td></tr>";
        $referenceBlock .= "</tbody>";
        $referenceBlock .= "</table></div>";
        $data->threadblock = $threadblock;
        $data->referenceblock = $referenceBlock;
        return $data;
    },
    "TicketOpen" => function ($data) {

    },
    "TicketUpdate" => function ($data) {

    },
    "TicketClose" => function ($data) {

    },
    "TicketReminder" => function ($data) {

    },
    "PasswordReminder" => function ($data) {

    },
);
$fontSize = "12px";
$style = array(
    "container" => "white-space: pre-line;overflow:hidden;font-size:$fontSize",
    "title" => "background:silver;margin:1px 0;padding:5px 0",
    "subject" => ";font-size:$fontSize",
    // "comment"=>" margin-top: 20px;white-space: initial;padding-left: 12px;padding-right: 12px;padding-bottom:5px;padding-top:5px;border-radius: 5px;position: relative;background-color: rgb(240, 245, 247);box-shadow: rgba(0, 0, 0, 0.15) 0px 1px 1px 0px;width: 90%;",
    "comments_history" => "padding-left:10px;font-size:$fontSize",
    "comment_tech" => "width:100%;background:#ccffcc;margin-bottom:3px;font-size:$fontSize",
    "comment_simple" => "width:100%;background:#ccf;margin-bottom:3px;font-size:$fontSize",
    "comment_title" => "padding-bottom:5px;font-weight:900;;font-size:$fontSize",
    "left_col" => "padding-right:10px;font-weight:900;text-align:right;font-size:$fontSize",
    "comment_container" => ";font-size:$fontSize",
    "comment_text" => "padding:5px;margin-bottom:5px;font-size:$fontSize",
    "comment_time" => "text-align:right;font-size:$fontSize",
    "header_style" => "background-color:#0d1b28;padding:10px;width:100%;margin-bottom:30px;font-size:$fontSize",
    "header_img_style" => "width:90%;max-width:250px;font-size:$fontSize",
    "signature" => "margin-bottom:10px;font-size:$fontSize",
    "link" => ";font-size:$fontSize",
);
class DeskEmail
{
    public $templateName;
    public $body;
    public $Db;
    public $company;
    public $data;
    public $initialSmsTemplate;
    public $initialEmailTemplate;
    public $smsTemplate;
    public $emailTemplate;
    public $mailer;
    public $subject;
    public $recipient;
    public $smsCollection;
    public $recieverPhone;
    public $forcedNotifType;
    public function __construct($db, $company, $templateName)
    {
        global $messageSchemes;
        global $style;
        $this->company = $company;
        $this->Db = new Db($db);
        $this->style = $style;
        $this->addresses = [];
        $this->templateName = $templateName;
        if ($templateName != "") {
            $this->loadTemplate();
            $this->data = (object) array();
            $this->messageSchemes = $messageSchemes;
        }
        // try {
        //     $this->mailer = initMailer();
        //      $this->mailer->From = $company->information["CompanyEmail"];
        //     $this->mailer->FromName = $company->information["CompanyName"];
        //     $this->setDatabase();
        // } catch (\Throwable $th) {
        //    return false;
        // }
       
    }
    public function setDatabase()
    {
        $this->MongoClient = new MongoDB\Client("mongodb://sms:uVvB#tpqoRG!c@192.168.128.11:27017");
        $smsdb = $this->MongoClient->selectDatabase('SMSSender');
        $this->smsCollection = $smsdb->selectCollection("Smsout");
    }
    public function setForcedNotifType($type)
    {
        $this->forcedNotifType = $type;
    }
    public function loadTemplate()
    {

        $templateData = $this->Db->get_row("SELECT cn.NotificationID,cn.emailTemplate,cn.smsTemplate,n.defaultEmailTemplate,n.defaultSmsTemplate  FROM Notifications n
                                                                    LEFT JOIN Companies_notifications cn ON cn.NotificationID=n.id AND cn.CompanyID=:CompanyID
                                                                    WHERE n.name=:templateName", array($this->company->id, $this->templateName));
        if ($templateData === false) {
            die("$this->type template not found");
        } else {
            if ($templateData["emailTemplate"] == null) {
                $emailTemplate = $templateData["defaultEmailTemplate"];
            } else {
                $emailTemplate = $templateData["emailTemplate"];
            }

            if ($templateData["smsTemplate"] == null) {
                $smsTemplate = $templateData["defaultSmsTemplate"];
            } else {
                $smsTemplate = $templateData["smsTemplate"];
            }

            $this->smsTemplate = $smsTemplate;
            $this->emailTemplate = $emailTemplate;
            $this->initialEmailTemplate = $emailTemplate;
            $this->initialSmsTemplate = $smsTemplate;
            $this->data = (object) array();
        }
    }
    public function resetTemplates()
    {
        $this->smsTemplate = $this->initialSmsTemplate;
        $this->emailTemplate = $this->initialEmailTemplate;
    }
    public function getTemplates()
    {
        return (object) array("sms" => $this->smsTemplate, "email" => $this->emailTemplate);
    }
    public function setTemplates($templatesObject)
    {
        $this->smsTemplate = $templatesObject->sms;
        $this->emailTemplate = $templatesObject->email;
    }
    public function replaceLabels($data)
    {
        $templates = array($this->smsTemplate, $this->emailTemplate); //temporary array on which we're going to loop
        // foreach($templates as $t){
        $out = array();
        preg_match_all("/{{([a-zA-z0-9_]*)}}/", $this->smsTemplate, $out, PREG_PATTERN_ORDER);
        $i = 0;

        foreach ($out[0] as $label) {
            if (isset($data->{strtolower($out[1][$i])})) {
                $dataValue = $data->{strtolower($out[1][$i])};

                $this->smsTemplate = str_ireplace($label, $dataValue, $this->smsTemplate);
            }
            $i++;
        }
        $outEmail = array();
        preg_match_all("/{{([a-zA-z0-9_]*)}}/", $this->emailTemplate, $outEmail, PREG_PATTERN_ORDER);
        $i = 0;
        foreach ($outEmail[0] as $label) {
            if (isset($data->{strtolower($outEmail[1][$i])})) {
                $dataValue = $data->{strtolower($outEmail[1][$i])};
                $this->emailTemplate = str_ireplace($label, $dataValue, $this->emailTemplate);
            }
            $i++;
        }
        // }
    }
    public function applyScheme($emailSchemeName, &$data = "")
    {
        if (is_object($data)) {
            $newData = clone $data;
        } else {
            $newData = $data;
        }
        if ($newData == "") {
            $newData = $this->data;
            $formattedData = $newData;
        } else {
            $formattedData = $newData;
            $formattedData = $this->messageSchemes["normalize"]($formattedData, $this->style);
        }
        $formattedData = $this->messageSchemes[$emailSchemeName]($formattedData, $this->style);

        $this->data = $formattedData;
        $this->replaceLabels($formattedData);
    }
    // public function addRecipient($user)
    // {
    //     $email = $user->information["email"];
    //     $name = $user->information["DisplayName"];
    //     $this->recipient = $user;
    //     $this->mailer->ClearAllRecipients();
    //     if (DEBUG_EMAIL_MODE) {
    //         $this->mailer->addAddress(DEBUG_EMAIL_ADDRESS, $name);
    //     } else {
    //         $this->mailer->addAddress($email, $name);
    //     }

    //     $this->recieverPhone = $user->information["Telephone"];
    // }
    public function addRecipient($user)
    {
        $email = $user->information["email"];
        $name = $user->information["DisplayName"];
        $this->recipient = $user;
        $this->addresses=[];
        if (DEBUG_EMAIL_MODE) {
            $this->addresses[]=[DEBUG_EMAIL_ADDRESS, $name];
        } else {
            $this->addresses[]=[$email, $name];
        }

        $this->recieverPhone = $user->information["Telephone"];
    }
    public function addsubject($subject)
    {

        $this->subject = $subject;

    }
    public function setTicketSubject($ticket, $action)
    {

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
                $severity = "None";
                break;
        }
        // $actionLetter = strtoupper($action[0]);
        // $subject="#$ticket->id:$severity:$actionLetter# {$ticket->data['subject']} ";
        $subject="#$ticket->id:$severity:$action# {$ticket->data['subject']} ";
        $this->addSubject($subject);
    }
    public function getUserTypePart($userType, $notificationType)
    {
        $userTypes = array("user", "tech");
        $removingTagName = ($userType == $userTypes[0]) ? $userTypes[1] : $userTypes[0];

        $cleanedBody = preg_replace("/<{$removingTagName}>[\w\W]*<\/{$removingTagName}>/", '', $this->{$notificationType . "Template"});
        return $cleanedBody;
    }
    // public function hasRecipient()
    // {
    //     return count($this->mailer->getAllRecipientAddresses()) > 0;
    // }
    public function hasRecipient()
    {
        return count($this->addresses) > 0;
    }
    public function sendSms($messageBody)
    {
        $phone = $this->recieverPhone;
        $now = date('Y-m-d H:i:s', strtotime('+0 hours'));
        $now = new MongoDB\BSON\UTCDateTime(strtotime($now) * 1000);

        $sms = array(
            "phone_number" => $phone,
            "message" => $messageBody,
            'status' => 1,
            'date_time' => $now,
            'Sendfrom' => '',
        );
        $this->smsCollection->insertOne($sms, []);
    }
    public function sendVerificationCode($code, $phone)
    {

        $now = date('Y-m-d H:i:s');
        $now = new MongoDB\BSON\UTCDateTime(strtotime($now) * 1000);
        $sms = array(
            "phone_number" => $phone,
            "message" => $code,
            'status' => 0,
            'date_time' => $now,
            'Sendfrom' => '',
        );
        $this->smsCollection->insertOne($sms, []);
    }
    public function send($debug = false)
    {
        if (SEND_EMAILS && $this->hasRecipient()) {
            $additional_text = "";
            $recipientType = ($this->recipient->isTechie() === true) ? "tech" : "user";
            if (is_null($this->forcedNotifType)) {
                $notificationType = $this->recipient->getNotificationType();
            } else {
                $notificationType = $this->forcedNotifType;
            }
            switch ($notificationType) {
                case 'email':
                    
                    $userSpecificTemplate = $this->getUserTypePart($recipientType, "email");
                    $body = "<pre style='{$this->style['container']}'>" . $userSpecificTemplate . "<div style='{$this->style['signature']}'>{$this->company->information['CompanyEmailSignature']}</div></pre>";
                    $body = str_replace("<a", "<a style='{$this->style['link']}' ", $body);
                    
                    
                    
                    
                    


                    
$config = SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', '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');
$config = SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('partner-key', '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');

$apiInstance = new SendinBlue\Client\Api\TransactionalEmailsApi(
    new GuzzleHttp\Client(),
    $config
);
$sendSmtpEmail = new \SendinBlue\Client\Model\SendSmtpEmail(); // \SendinBlue\Client\Model\SendSmtpEmail | Values to send a transactional email

// $sendSmtpEmail = new \SendinBlue\Client\Model\SendSmtpEmail(); 

$sendSmtpEmail['subject'] =  $this->subject;
// $sendSmtpEmail['htmlContent'] = $body;
$sendSmtpEmail['htmlContent'] = $body;

$sname=$this->company->information["CompanyName"];
$semail=$this->company->information["CompanyEmail"]; 
// $semail="no-reply@mail.me.ms";
// $semail="example@example.com";
// $semail="no-reply@theservicedesk.biz";
$sendSmtpEmail['sender'] = array('name' => $sname, 'email' => $semail);

// $sendSmtpEmail['to'] = array(
//     array('email' => 'richard@me.ms', 'name' => 'Richard')
// );
// $t=$this->mailer->getAllRecipientAddresses();
$i=0;
$errors=[];
// $sendSmtpEmail['to']=array();
$myaddresses=[];
foreach ($this->addresses as $address) {
    $myaddresses[]=array('email' => $address[0], 'name' => $address[1]);
}
// $sendSmtpEmail['to']= $myaddresses;
foreach ($myaddresses as $address) {
    $sendSmtpEmail['to']= array($address);
    try {
        $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
    } catch (Exception $e) {
        $errors[]=$e->getMessage();
    }
}
return (count($errors)==0)?true:$errors;

                    
                    // $this->mailer->isHTML(true);
                    // $this->mailer->Subject = $this->subject;
                    // $this->mailer->Body = $body;
                    // if ($debug) {
                    // } else
                    // if (!$this->mailer->send()) {
                    //     echo 'Mailer Error: ' . $this->mailer->ErrorInfo;
                    // } else {
                    //     return true;
                    // }
                    break;
                case "sms":
                    $userSpecificTemplate = $this->getUserTypePart($recipientType, "sms");
                    $body = strip_tags($userSpecificTemplate); //making sure there's no html in the sms body
                    $this->sendSms($body);
                    break;
            }

        }
    }
    public function debug()
    {
        $this->send(true);
    }
}
