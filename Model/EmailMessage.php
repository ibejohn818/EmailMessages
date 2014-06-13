<?php 

App::uses("EmailMessagesAppModel","EmailMessages.Model");
App::uses("IEmailMessage","EmailMessages.Model/Interface");
App::uses('CakeEmail', 'Network/Email');

class EmailMessage extends EmailMessagesAppModel implements IEmailMessage {


    /**
     * Parent Constructor
     * I don't like doing this but have no choice to allow better flexibility in configuration
     * @param boolean $id    [description]
     * @param [type]  $table [description]
     * @param [type]  $ds    [description]
     */
    public function __construct($id = false, $table = null, $ds = null) {

        $this->tablePrefix = Configure::read("EmailMessages.email_messages_table_prefix");

        parent::__construct($id,$table,$ds);

    }

    private $email_configs = array();

    #STATUSES
    const PENDING       = 'pending';
    const SENT          = 'sent';
    const PROCESSING    = 'processing';
    const ERROR         = 'error';


    public static function getStatuses() {

        return array(
            EmailMessage::PENDING => strtoupper(EmailMessage::PENDING),
            EmailMessage::SENT => strtoupper(EmailMessage::SENT),
            EmailMessage::PROCESSING => strtoupper(EmailMessage::PROCESSING),
            EmailMessage::ERROR => strtoupper(EmailMessage::ERROR)
        );

    }

    public static function formatRecipients($data,$appendTo = false) {

        if(!isset($data['to']) && !isset($data['cc']) && !isset($data['bcc'])) {
            throw new FatalErrorException("EmailMessage::formatRecipients - INCOMING DATA MUST HAVE KEY OF 'to','cc' OR 'bcc'");
        }

        if($appendTo) {
            
            $appendTo = self::parseRecipients($appendTo);

            $data = array_merge($data,$appendTo);

        }

        return serialize($data);

    }   

    public static function parseRecipients($data = array()) {
        return unserialize($data);
    }

    public function setRecipients($recipients,$emailObj) {

        $r = self::parseRecipients($recipients);

        if(isset($r['to']) && count($r['to'])>0) {
            $to = array();
            
            foreach($r['to'] as $email=>$name) {
                if(!self::validateEmailAddress($email)) {
                    $email = $name;
                }
                $to[$email]=$name;    
            }
            $emailObj->to($to);
        }

        if(isset($r['cc']) && count($r['cc'])>0) {
            $cc = array();
            
            foreach($r['cc'] as $email=>$name) {
                 if(!self::validateEmailAddress($email)) {
                    $email = $name;
                }
                $cc[$email]=$name;    
            }
            $emailObj->cc($cc);
        }
        
        if(isset($r['bcc']) && count($r['bcc'])>0) {
            $bcc = array();
           
            foreach($r['bcc'] as $email=>$name) {
                 if(!self::validateEmailAddress($email)) {
                    $email = $name;
                }
                $bcc[$email]=$name;    
            }
            $emailObj->bcc($bcc);
        }

    }

    public function setAttachments($EmailMessage,$emailObj) {
        
        if(!empty($EmailMessage['attachments'])) {
            
            $filePaths = explode(",", $EmailMessage['attachments']);
            $attachments = array();    

            foreach($filePaths as $v) {
                
                $test = explode(":",$v);

                if(count($test)==2) {
                    $attachments[$test[0]] = $test[1];
                } else {
                    $attachments[] = $v;
                }

            }

            $emailObj->attachments($attachments);

        }

    }


    /**
     * Adds an PENDING email to the database with the option of processing/sending in the same execution.
     * This is not the default behavior. You may wish for the email to be sent in the background to 
     * avoid having user have to wait for a possibly long process.
     * 
     *
     * @param [type]  $data [description]
     * @param boolean $send [description]
     */
    public function addEmail($data,$send = false) {

        if(isset($data['EmailMessage'])) {
            $data = $data['EmailMessage'];
        }

        $data['email_status'] = EmailMessage::PENDING;

        if(!isset($data['email_config']) || empty($data['email_config'])) {
            $data['email_config'] = 'default';
        }

        if(!isset($data['layout_file']) || empty($data['layout_file'])) {
            $data['layout_file'] = 'EmailMessages.default';
        }

        if(empty($data['to_email']) && empty($data['recipients'])) {
            throw new FatalErrorException("EmailMessage::addEmail - NO TO_EMAIL OR RECIPIENTS SPECIFIED");
        }

        if(!isset($data['domain']) || empty($data['domain'])) {
            $data['domain'] = (isset($_SERVER["HTTP_HOST"])) ? env("HTTP_HOST"):"";
        }

        if(!isset($data['priority']) || empty($data['priority'])) {
            $data['priority'] = 0;
        }

        if(!$this->save($data)) {
            throw new FatalErrorException("EmailMessage::addEmail - AN ERROR OCCURED WHILE SAVING EMAIL MESSAGE TO DATABASE",500);
        }

        if($send) {
            $this->send($this->read());
        }

        return $this->id;

    }



    public function update_status($id,$status,$extra = array()) {

        $statuses = self::getStatuses();

        if(!array_key_exists($status,$statuses)) {
            throw new FatalErrorException("EmailMessage::update_status() - INVALID STATUS [{$status}]");
        }

        $updateData = array(
            "email_status"=>$status
        );

        $updateData = array_merge($updateData,$extra);

        $this->create();

        $this->id = $id;

        return $this->save($updateData);

    }

    public function send($EmailMessage) {

        if(isset($EmailMessage['EmailMessage'])) {
            $e = $EmailMessage['EmailMessage'];
        } else {
            $e = $EmailMessage;
        }

        $this->update_status($e['id'],EmailMessage::PROCESSING);

        if(!empty($e['model'])) {
            $model = ClassRegistry::init($e['model']);
        } else {
            $model = false;
        }

        if($model && !($model instanceof IEmailMessage)) {
            throw new FatalErrorException("EmailMesasge::send - {$e['model']} MUST IMPLEMENT IEMAILMESSAGE INTERFACE ( App::uses('IEmailMessage','EmailMessage.Model') )");
        }

        if($model) {
            $e = $model->emailMessage_beforeSend($e);
        }

        $email = new CakeEmail($e['email_config']);

        $email->subject($e['subject']);

        if(!empty($e['to_email'])) {
            $name = (!empty($e['to_name'])) ? $e['to_name']:"";
            $email->to($e['to_email'],$name);
        }

        if(!empty($e['recipients'])) {
            $this->setRecipients($e['recipients'],$email);
        }

        $email->template($e['view_file'],$e['layout_file']);

        $email->viewVars(array("EmailMessage"=>$e));

        if(!empty($e['domain'])) {
            $email->domain($e['domain']);
        }

        //check for attachments
        $this->setAttachments($e,$email);

        try {
            $send_status = EmailMessage::SENT;
            $send_result = $email->send();
            print_r($send_result);
        }
        catch (Exception $e) {
            $send_status = EmailMessage::ERROR;
            print_r($e);
        } 

        $this->update_status($e['id'],$send_status,array("send_result"=>var_dump($send_result),"sent_datetime"=>date("Y-m-d H:i:s")));

        if($model) {
            $e = $model->emailMessage_afterSend($e);
        }

        unset($model,$e);

        
       
        return $send_status;


    }

    public function getConfig($config = 'default') {

        if(!isset($this->email_configs[$config])) {

            $this->email_configs[$config] = new CakeEmail($config);

        }

        return $this->email_configs[$config];

    }

    public function send_batch($batch = false) {

        if(!$batch) {
            $batch = Configure::read("EmailMessages.batch_limit");
        }

        $msgs = $this->find('all',array(
            'conditions'=>array(
                "EmailMessage.email_status"=>EmailMessage::PENDING
            ),
            'contain'=>false,
            'limit'=>$batch,
            'order'=>array("EmailMessage.priority"=>"DESC","EmailMessage.created"=>"ASC")
        ));

        foreach($msgs as $msg) {
            $this->send($msg);
        }

    }

    public static function validateEmailAddress($email) {

        if(preg_match('/^[\p{L}0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[\p{L}0-9!#$%&\'*+\/=?^_`{|}~-]+)*@(?:[_\p{L}0-9][-_\p{L}0-9]*\.)*(?:[\p{L}0-9][-\p{L}0-9]{0,62})\.(?:(?:[a-z]{2}\.)?[a-z]{2,})$/ui',$email)) {
            return true;
        }

        return false;

    }

    public function emailMessage_beforeSend($EmailMessage) {
        return $EmailMessage;
    }

    public function emailMessage_afterSend($EmailMessage) {
        return $EmailMessage;
    }

}