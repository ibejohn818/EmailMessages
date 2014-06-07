<?php 

interface IEmailMessage {

    public function emailMessage_beforeSend($EmailMessage);
    public function emailMessage_afterSend($EmailMessage);

}