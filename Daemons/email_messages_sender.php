#!/usr/bin/php
<?php

$shell = "/home/sites/johnchardy.com/cakeshell";

$pid = pcntl_fork();

if($pid === -1) {

    return 1;

} elseif($pid) {

    return 0;

} else {

    while(true) {

        `nohup {$shell} email_messages.run_sender >& /dev/null &`;

        sleep(10);

    }

}
