<?php 

App::uses("EmailMessagesAppModel","EmailMessages.Model");
App::uses("IEmailMessage","EmailMessage.Model/Interface");
App::uses('CakeEmail', 'Network/Email');

class EmailMessage extends EmailMessagesAppModel implements IEmailMessage {

    private $email_configs = array();

    #STATUSES
    const PENDING       = 'pending';
    const SENT          = 'sent';
    const PROCESSING    = 'processing';
    const ERROR         = 'error';


    public static function getStatuses() {

        return array(
            EmailMessage::PENDING => strtouppet(EmailMessage::PENDING),
            EmailMessage::SENT => strtouppet(EmailMessage::SENT),
            EmailMessage::PROCESSING => strtouppet(EmailMessage::PROCESSING),
            EmailMessage::ERROR => strtouppet(EmailMessage::ERROR)
        );

    }

    public static function formatRecipients($data,$appendTo = false) {

        if(!isset($data['to']) || !isset($data['cc']) || !isset($data['bcc'])) {
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
            
            foreach($t['to'] as $email=>$name) {
                $emailObj->to($email,$name);    
            }
            
        }

        if(isset($r['cc']) && count($r['cc'])>0) {
            
            foreach($t['cc'] as $email=>$name) {
                $emailObj->cc($email,$name);    
            }
            
        }


        if(isset($r['bcc']) && count($r['bcc'])>0) {
            
            foreach($t['bcc'] as $email=>$name) {
                $emailObj->bcc($email,$name);    
            }
            
        }

    }


    public function addEmail($data,$send = false) {

        if(isset($data['EmailMessage'])) {
            $data = $data['EmailMessage'];
        }

        $data['email_status'] = EmailMessage::PENDING;

        if(!isset($data['email_config']) || empty($data['email_config'])) {
            $data['email_config'] = 'default';
        }

        if(!isset($data['layout']) || empty($data['layout'])) {
            $data['layout'] = 'default';
        }

        if(!empty($data['to_email']) || !empty($data['recipients'])) {
            throw new FatalErrorException("EmailMessage::addEmail - NO TO_EMAIL OR RECIPIENTS SPECIFIED");
        }

        if(!isset($data['domain']) || empty($data['domain'])) {
            $data['domain'] = (isset(env("HTTP_HOST"))) ? env("HTTP_HOST"):"";
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

        if(!isset($EmailMessage['EmailMessage'])) {
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
            throw new FatalErrorException("EmailMesasge::send - {$e['model']} MUST IMPLEMENT IEMAILMESSAGE INTERFACE ( App::uses('IEmailMessage','EmailMessage.Model/Interface') )");
        }

        if($model) {
            $e = $model->emailMessage_beforeSend($e);
        }

        $email = $this->getConfig($e['email_config']);

        $email->reset();

        if(!empty($e['to_email'])) {
            $name = (!empty($e['to_name'])) ? $e['to_name']:"";
            $email->to($e['to_email'],$name);
        }

        if(!empty($e['recipients'])) {
            $this->setRecipients($e['recipients'],$email);
        }

        $email->template($e['view'],$e['layout']);

        $email->viewVars(array("EmailMessage"=>$e));

        try {
            $send_status = EmailMessage::SENT;
            $send_result = $email->send();
        }
        catch (Exception $e) {
            $send_status = EmailMessage::ERROR;
        }

        finally {

            $this->update_status($e['id'],$send_status,array("send_result"=>$send_result));

            if($model) {
                $e = $model->emailMessage_afterSend($e);
            }

            unset($model,$e);

        }
       



    }

    public function getConfig($config = 'default') {

        if(!$this->email_configs[$config]) {

            $this->email_configs[$config] = new CakeEmail($config);

        }

        return $this->email_configs[$config];

    }

    public function send_batch($batch = 10) {

        $msgs = $this->find('all',array(
            'conditions'=>array(
                "EmailMessage.email_status"=>EmailMessage::PENDING
            ),
            'contain'=>false,
            'limit'=>$batch,
            'order'=>array("EmailMessage.created"=>"ASC");
        ));

        foreach($msgs as $msg) {
            $this->send($msg);
        }

    }

    public function emailMessage_beforeSend($EmailMessage) {
        return $EmailMessage;
    }

    public function emailMessage_afterSend($EmailMessage) {
        return $EmailMessage;
    }

}