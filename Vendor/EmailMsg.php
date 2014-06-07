<?php 

class EmailMsg {

    public static $EmailMessage = null;

    public static function EmailMessage() {

        if(!self::$EmailMessage) {
            self::$EmailMessage = ClassRegistry::init("EmailMessages.EmailMessage");
        }

        return self::$EmailMessage;

    }

    public static function add($data,$send = false) {

        return self::EmailMessage()->addEmail($data,$send);

    }

}


 ?>