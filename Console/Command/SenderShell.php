<?php 

App::uses("AppShell","Console/Command");

class SenderShell extends AppShell {

    public $uses = array(
        "EmailMessages.EmailMessage"
    );

    public function run_sender() {

        $this->out("EmailMessages Sender Intializing Batch Send....");
 
        $this->hr();

        $this->out("Checking for unsent messages");

        $emails = $this->EmailMessage->find('all',array(
            'conditions'=>array(
                "EmailMessage.email_status"=>EmailMessage::PENDING
            ),
            'order'=>array("EmailMessage.created"=>"ASC","EmailMessage.priority"=>"DESC")
        ));

        $total = count($emails);

        $total_sent = 0;

        $total_errors = 0;

        $this->out("{$total} Emails found in PENDING status");

        if(count($emails)<=0) {
            $this->out("Exiting ....");
            return;
        }

        foreach($emails as $k=>$email) {

            $num = $k+1;
            $this->hr();
            $this->out("Sending {$num} of {$total} | ID: {$email['EmailMessage']['id']} | Priority: {$email['EmailMessage']['priority']}");

            $status = $this->EmailMessage->send($email);

            if($status === EmailMessage::SENT) {
                $total_sent++;
                $this->out("SUCCESS!");
            } else {
                $total_errors++;
                $this->out("ERROR :-(");
            }

        }

        $this->hr();
        
        $this->out("Finished Sending Batch");
        
        $this->out("Success: {$total_sent} | Errors: {$total_errors}");


    }

}