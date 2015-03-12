<?php

session_start();

$basedir = explode ( '/var/', dirname(__DIR__));
require_once $basedir[0] . '/etc/config.vici_sync.php';

// LDAP connect settings:
$LDAP = array(
    'server' => '192.168.0.23',
    'port' => '389',
    'bindDN' => NULL,
    'bindPW' => NULL,
    'baseDN' => 'dc=company,dc=com',
    );

$ds = ldap_connect($LDAP['server'], $LDAP['port'])
    or die("Could not connect to LDAP server.");

//setting up LDAP protocol version
ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

function print_form($header){
?>
<div style="background-image: url('img/company.jpg'); background-repeat: no-repeat; height: 140px"></div>
<?php
echo $header;
?>
<form role="form" method="post">
  <div class="form-group">
    <label for="InputUsename">Username</label>
    <input type="text" name="username" class="form-control" id="InputUsename" placeholder="Enter your username">
  </div>
  <div class="form-group">
    <label for="InputPassword1">Password</label>
    <input type="password" name="passwd" class="form-control" id="InputPassword1" placeholder="Password">
  </div>
  <button name="submit_btn" type="submit" class="btn btn-default">Submit</button>
</form>
<?php
}
echo "<!DOCTYPE html>\n<html lang=\"en\">";
echo "<head>";
echo "<title>Vicidial password request</title>";
echo "<link href=\"./css/bootstrap.css\" rel=\"stylesheet\">";
echo "</head>";
echo "<div class=\"container\">";
echo "<div class=\"row\">";
echo "<div class=\"col-md-3 col-md-offset-4\">";

if(!isset($_POST['submit_btn'])){

$header = "<div style=\"wight: 200px\"><h2 class=\"text-center\">Get your vicidial password</h2></div>";

print_form($header);
}
else {
    //print_r($_GET);
    if(empty($_POST['username']) || empty($_POST['passwd']) ){
        $header = "<div style=\"wight: 200px\"><font color=\"red\"><h2 class=\"text-center\">ERROR!::</h2><p class=\"text-center\">" .
                "Username and password can't be empty</p></font></div>";
        print_form($header);
    }
    else{
        $ldapuser = trim(htmlspecialchars($_POST['username']));
        $ldaprdn  = 'uid=' . $ldapuser . ',' . $LDAP['usersDN'];
        $ldappass = trim(htmlspecialchars($_POST['passwd']));
        $attr = "Password";
        $filter = "(uid=".$ldapuser.")";

        $sr = ldap_search($ds,$LDAP['baseDN'],$filter,array('uid','mailRoutingAddress','cn'));

        if (! $sr) {
            ldap_close($ds);
            $header = "<div style=\"wight: 200px\"><font color=\"red\"><h2 class=\"text-center\">ERROR!::</h2><p class=\"text-center\">" .
                "Wrong username/password!</p></font></div>";
            print_form($header);
            echo "</div>";
            echo "</div>";
            echo "</div>";
            echo "</html>";
            exit();
        }

   //---------------------------------------------------------------
   // was one entry echoed?
   //---------------------------------------------------------------

        $n = ldap_count_entries($ds,$sr);

        if ($n < 1){
            ldap_close($ds);
            $header = "<div style=\"wight: 200px\"><font color=\"red\"><h2 class=\"text-center\">ERROR!::</h2><p class=\"text-center\">" .
                "Wrong username/password!</p></font></div>";
            print_form($header);
            echo "</div>";
            echo "</div>";
            echo "</div>";
            echo "</html>";
            exit();
        }

        if ($n > 1){
                ldap_close($ds);
                echo "0|Too many entries match user name";
                exit();
        }

   //---------------------------------------------------------------
   // one entry echoed, get its DN
   //---------------------------------------------------------------

        $info = ldap_get_entries($ds,$sr);

        $userdn = $info[0]['dn'];

   //---------------------------------------------------------------
   // bind to server - using DN and password
   //---------------------------------------------------------------

        $r = @ldap_bind($ds,$userdn,$ldappass);

        if (!$r){
            $header = "<div style=\"wight: 200px\"><font color=\"red\"><h2 class=\"text-center\">ERROR!::</h2><p class=\"text-center\">"
                . ldap_errno($ds).":".ldap_error($ds)."</p></font></div>";
            print_form($header);
            echo "</div>";
            echo "</div>";
            echo "</div>";
            echo "</html>";
            ldap_close($ds);
            exit();
        }
        else{
            //echo "1|OK \n";
            //print_r($info);
            $email = $info[0]['mailroutingaddress'][0];
            $fullname = $info[0]['cn'][0];
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Email is found and valid;
                $venom_mysqli = new mysqli($db1_host, $db1_user, $db1_pass, $db1_db);

                if (mysqli_connect_errno()) {
                    printf("Mysql connect error!: %s\n", mysqli_connect_error());
                    exit();
                }

                $query = "SELECT password from $venom_db.master_list where username='" . $ldapuser . "' LIMIT 1;";
                if ($result = $venom_mysqli->query($query)) {
                    while($row = mysqli_fetch_array($result)){
                        //echo $row['password'];
                        $vicipasswd = $row['password'];
                    }
                    $result->free();
                    if(isset($vicipasswd)){
                        $message = "Hi, $fullname\r\n\r\n";
                        $message .= "Opon your request, your vicidial login/password are\r\n\r\n"
                               . $ldapuser ." : ". $vicipasswd ."\r\n\r\n";
                        $message .= "Regards\r\n";
                        if( mail($email, "ViCIDial password requested", $message,
                            "From: vladimir.mitrofanov@company.com \r\n"
                           ."X-Mailer: PHP/" . phpversion())){
                            echo "<div style=\"background-image: url('img/company.jpg');
                            background-repeat: no-repeat; height: 140px\"></div>";
                            echo "<p class=\"text-center bg-success\">"
                            . "Your vicidial password <br />has been sent to your email";
                            if ( preg_match('/@company.com$/', $email)){
                                echo "</br><a href=\"https://192.168.0.20/roundcubemail/\">$email</a><br /></p>";
                            }
                            else { echo "</br><a href=\"mailto:#\">$email</a><br /></p>"; }

                            shell_exec('/usr/bin/php '. $basedir[0] .'/sbin/vici_sync.php > /dev/null 2>/dev/null &');
                        }
                    }
                    else{
                        echo "<div style=\"background-image: url('img/company.jpg');
                            background-repeat: no-repeat; height: 140px\"></div>";
                            echo "<p class=\"text-center bg-warning\">";
                        echo "ERROR: no vici password received <br />"
                            . "Please try to login to CRM first, to see if your agent's account is still active</p>";
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                        echo "</html>";
                        ldap_close($ds);
                        exit(1);
                    }
                }
                else{
                    echo "<p style=\"color:red\">Mysql error! Sorry sir/maam :{ </p>\n";
                    ldap_close($ds);
                    exit(1);
                }

            }
            else{
                echo "<div style=\"background-image: url('img/company.jpg');
                            background-repeat: no-repeat; height: 140px\"></div>";
                            echo "<p class=\"text-center bg-warning\">";
                        echo "ERROR:: wrong email retrieved!<br />Please visit IT Department to fix this error";
                echo "</div>";
                echo "</div>";
                echo "</div>";
                echo "</html>";
                ldap_close($ds);
                exit(1);
            }
        }
    }
}

echo "</div>";
echo "</div>";
echo "</div>";
echo "</html>";
ldap_close($ds);
