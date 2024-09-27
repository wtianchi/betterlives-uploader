<?php
require '../../vendor/autoload.php';
use Aws\Resource\Aws;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
//echo $_POST["token"];
$json_string = file_get_contents('../../config.json');
$conf = json_decode($json_string, true);
header("Access-Control-Allow-Origin: *");
$host = $conf["host"];
$port = $conf["port"];
$dbname = $conf["dbname"];
$credentials = $conf["credentials"];
//echo "$host $port $dbname $credentials";
$db = pg_connect( "$host $port $dbname $credentials"  );
   if(!$db) {
     $return = json_encode(array("status" => "0", "message" => "database error"));
     pg_close($db);
   } else {
     $ret = false;
     try{
       $sql = "SELECT * from users WHERE token = '" . $_POST["token"] . "'";
       $ret = pg_query($db, $sql);
       //echo $sql;
     }
     catch(Exception $e){
       $return = json_encode(array("status" => "0", "message" => "param invalid"));
     }
     if(!$ret || pg_num_rows($ret) <= 0) {
       $return = json_encode(array("status" => "0", "message" => "user invalid"));
     }
     else {
       $row = pg_fetch_array($ret);
       $fName = $row["email"] . floor(microtime(true) * 1000) . $_FILES["file"]["name"];
       $fName = str_replace(" ", "", $fName);
       try{
         $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => 'ca-central-1',
            'credentials' => [
              'key'    => $conf["key"],
              'secret' => $conf["secret"],
          ],
        ]);
        $result = $s3->putObject([
			       'Bucket' => 'betterlives-media',
			       'Key'    => $fName,
			       'SourceFile' => $_FILES["file"]["tmp_name"],
             'ACL' => 'public-read'
		    ]);
        $return = json_encode(array("status" => "1", "file" => $result["ObjectURL"]));
       } catch (Exception $e) {
         // Catch an S3 specific exception.
         echo $e->getMessage();
         echo $e->getCode();
         echo $e->getFile();
         echo $e->getLine();
         $return = json_encode(array("status" => "0", "message" => "Uploader Error"));
       }

     }
     pg_close($db);
   }
   echo $return;
   WriteLog('Uploader --------- ' . $_POST["token"] . " " . $fName);


   function WriteLog($msg){
       $fp = fopen("../../Log.txt", "a");
       if($fp)
       {
           $time=date("Y-m-d h:i:s");
           $flag=fwrite($fp, $time ."   ".$msg . "   " .  $_SERVER["REMOTE_ADDR"] . "  \r\n");
           fclose($fp);
       }
   }
?>
