<?php 

//import Vendor class for global static access to queue and send emails
App::import("EmailMessages.Vendor","EmailMsg");


//general configuration 

//table prefix for email messages table
Configure::write("EmailMessages.email_messages_table_prefix","");

//batch sending limit
Configure::write("EmailMessages.batch_limit",10);