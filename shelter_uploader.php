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
     //echo var_dump($_POST);

     try{
       $sql = "SELECT * FROM shelter_managers WHERE ((manager_id = '" . $_POST["manager_id"] . "' AND password = '" . md5($_POST["password"]) . "') AND locked = 0)";
       $ret = pg_query($db, $sql);
       //echo $sql;
     }
     catch(Exception $e){
       $return = json_encode(array("status" => "0", "message" => "param invalid"));
     }
     if(!$ret || pg_num_rows($ret) <= 0) {
       //echo var_dump($_POST);
       $return = json_encode(array("status" => "0", "message" => "user invalid"));
     }
     else {
       $row = pg_fetch_array($ret);
       $fUrl = "";
       $pUrl = "";
       if($_FILES["file"]["size"] > 0){
         $fName = floor($row["id"] . microtime(true) * 1000) . $_FILES["file"]["name"];
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
			         'Bucket' => 'betterlives-shelter-img',
			          'Key'    => $fName,
			          'SourceFile' => $_FILES["file"]["tmp_name"],
                'ACL' => 'public-read'
		         ]);
         } catch (Exception $e) {
          // Catch an S3 specific exception.
          echo $e->getMessage();
          echo $e->getCode();
          echo $e->getFile();
          echo $e->getLine();
        }
        $fUrl = $result["ObjectURL"];
        $sql = "UPDATE shelters SET pic = '" . $fUrl . "' WHERE id = " . $row["shelter_id"];
        //echo $sql;
        $ret = pg_query($db, $sql);

      }
      if($_FILES["photo"]["size"][0] > 0){
        $total = count($_FILES['photo']['name']);
        //echo $total;
        try{
          $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => 'ca-central-1',
            'credentials' => [
            'key'    => $conf["key"],
            'secret' => $conf["secret"],
          ],
        ]);
        $sql = "INSERT INTO shelter_img (shelter_id, manager_id, url) VALUES";
        $f = false;
        // Loop through each file
        for( $i=0 ; $i < $total ; $i++ ) {
          //Get the temp file path
          $pName = floor($row["id"] . microtime(true) * 1000) . $i . $_FILES["photo"]["name"][$i];
          $pName = str_replace(" ", "", $pName);
          $result = $s3->putObject([
 			         'Bucket' => 'betterlives-shelter-img',
 			          'Key'    => $pName,
 			          'SourceFile' => $_FILES["photo"]["tmp_name"][$i],
                 'ACL' => 'public-read'
 		         ]);
          $pUrl = $result["ObjectURL"];
          if($f){
            $sql = $sql . ",";
          }
          else {
            $f = true;
          }
          $sql = $sql . " (" . $row["shelter_id"] . "," . $row["id"] . ",'" . $pUrl . "')";
        }
        //echo $sql;
        $ret = pg_query($db, $sql);
      } catch (Exception $e) {
       // Catch an S3 specific exception.
       echo $e->getMessage();
       echo $e->getCode();
       echo $e->getFile();
       echo $e->getLine();
     }
   }

      $return = json_encode(array("status" => "1", "file" => $fUrl));

     }
     pg_close($db);
   }
   echo '<script>window.location.href="' . $_POST["url"] . '"</script>';
   //echo '<a href="' . $_POST["url"] . '">Back</a>';
   //echo $return;
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
