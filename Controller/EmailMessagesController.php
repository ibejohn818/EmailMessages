<?php 

App::uses("EmailMessagesAppController","EmailMessages.Controller");


class EmailMessagesController extends EmailMessagesAppController {


    public $uses = array(
        "EmailMessages.EmailMessage"
    );

    public function beforeFilter() {
        parent::beforeFilter();
    }
    

    public function admin_index() {
        
    }
    

}