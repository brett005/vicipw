<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);
$DEBUG=1;

require_once dirname(__DIR__) . '/config.vici_sync.php';


// we'll connect to main database to get master_list table
$venom_mysqli = new mysqli($db1_host, $db1_user, $db1_pass, $db1_db);

if (mysqli_connect_errno()) {
    printf("Mysql connect error!: %s\n", mysqli_connect_error());
    exit();
}

$query = "SELECT name,username,password,email_address,vici_group from $venom_db.master_list where enabled='1'";

$userlist = array();

if ($result = $venom_mysqli->query($query)) {

    while ($data = $result->fetch_assoc())
    {
        $userlist[] = $data;
    }
    $result->free();
}
else{
    echo "Mysql error \n";
    exit();
}

print_r($userlist);

// We are done with this database
// Now its time to close it:

$venom_mysqli->close();


// Connect to vicidial db:

$vici_mysqli = new mysqli($vici_host, $vici_user, $vici_pass, $vici_db);

if (mysqli_connect_errno()) {
    printf("Mysql connect error!: %s\n", mysqli_connect_error());
    exit();
}

$query = "SELECT user from $vici_db.$vici_user_table";

if ($result = $vici_mysqli->query($query)) {

    while ($row = $result->fetch_assoc())
    {
        if (in_array($row['user'], $vici_users)) { continue; }
        if ($DEBUG == 1) { echo $row['user']."\n"; }
        $found = false;
        foreach ($userlist as $user_row){
            if ($user_row['username'] == $row['user']){
                $found = true;
                break;
            }
        }
        if (!$found){
            //delete this user!
            $del_query = "DELETE FROM $vici_db.$vici_user_table WHERE user='" . $row['user'] . "'";
            if (!$vici_mysqli->query($del_query)){
                echo "error on deleting";
                exit();
            }
            else{
                if ($DEBUG == 1) { echo "Deleting ".$row['user']."\n"; }
            }
        }
    }
    $result->free();
}
else{
    echo "Mysql error \n";
    exit();
}

// Preparation are done!
// we'll insert our users into vicidial_users table:

foreach ( $userlist as $user_row ){
    if( empty($user_row['username'])) { continue; }
    if (in_array($user_row['username'], $vici_users)) { continue; }

    $query = "INSERT INTO $vici_db.$vici_user_table (user,pass,full_name,user_level,user_group,email) "
            . "VALUES ( '".$user_row['username']."',"
            . "'" .$user_row['password'] ."',"
            . "'" .  $user_row['name'] ."',"
            . "'" . $default_user_level ."',"
            . "'" . $user_row['vici_group'] ."',"
            . "'" . $user_row['email_address'] ."') "
            . "ON DUPLICATE KEY UPDATE pass='". $user_row['password'] ."',"
            //. "user_group='". $user_row['vici_group'] ."',"
            . "email='". $user_row['email_address'] ."'";
    if ($DEBUG == 1) { echo $query ."\n"; }
    if (!$vici_mysqli->query($query)){
        echo "error on inserting";
        exit();
    }
    else{
        if ($DEBUG == 1) { echo "Inserting ".$user_row['username']."\n"; }
    }
}

$vici_mysqli->close();
