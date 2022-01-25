<?php
//RUN url/backup.php?mysql=1 to get only a fresh SQL backup. run url/backup.php for full all files+sql file in a zip.




//  CONFIGURATION

//Your password in MD5 for security reasons.

 $md5Password = '827ccb0eea8a706c4c34a16891f84e7b'; //DEFAULT password: 12345 run url/backup.php?newpass=PASSWORD to get your md5

 $dir_path = './';  //Dir to zip, backup files. ./ root dir.

 //MySQL server and database
$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'phpmyadmin';
$SQL_SystemMethod = false; //SQL Backup method: system (mysqldump) or fopen
 

//  END CONFIGURATION

 
if (isset($_GET['newpass'])) {
    echo 'This is your MD5 password, copy paste it to $md5Password variable: <p style="background-color:powderblue;"> '. md5($_GET['newpass']);
   exit;
   }
   
   
 
if (isset($_POST['password'])) {

 if (md5($_POST['password']) != $md5Password) {
   
    echo 'Unauthorized';
 
 } else {
    doBackUp();
 }
} else {
    ?>
    <form method="post">
Password: <input type="password" name="password"> <input type="submit">
</form>


<?php
}
 

function doBackUp() {
    global $dbhost, $dbname, $dbpass, $dbuser, $dir_path,$SQL_SystemMethod;
 

ini_set('max_execution_time', 600);
ini_set('memory_limit','1024M');
//back up SQL

/**
* Updated: Mohammad M. AlBanna
* Website: MBanna.info
*/
 
$backup_file = $dbname . date("Y-m-d-H-i-s") . '.sql';

if ($SQL_SystemMethod) {
 
$command = "mysqldump --opt --host=$dbhost --user=$dbuser --password='$dbpass' " ." $dbname > $backup_file 2>&1";

 
system($command);

} else {
    backDb($dbhost, $dbuser, $dbpass, $dbname, $backup_file);
}

 
//Core function
if (isset($_GET['mysql'])) {

    header("Content-type: application/zip"); 
    header("Content-Disposition: attachment; filename=$backup_file");
    header("Content-length: " . filesize($backup_file));
    header("Pragma: no-cache"); 
    header("Expires: 0"); 
    readfile($backup_file);
    unlink($backup_file);
exit;
}

// Get real path for our folder
$rootPath = realpath($dir_path);




$archive_file_name = "backup-".time().".zip";

// Initialize archive object
$zip = new ZipArchive();
$zip->open($archive_file_name, ZipArchive::CREATE | ZipArchive::OVERWRITE);

// Create recursive directory iterator
/** @var SplFileInfo[] $files */
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file)
{
    // Skip directories (they would be added automatically)
    if (!$file->isDir())
    {
        // Get real and relative path for current file
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($rootPath) + 1);

        // Add current file to archive
        $zip->addFile($filePath, $relativePath);
    }
}
$zip->close();
header("Content-type: application/zip"); 
header("Content-Disposition: attachment; filename=$archive_file_name");
header("Content-length: " . filesize($archive_file_name));
header("Pragma: no-cache"); 
header("Expires: 0"); 
readfile($archive_file_name);
unlink($backup_file);
unlink($archive_file_name);

exit;
}
 
 

 
function backDb($host, $user, $pass, $dbname,   $backup_file_name, $tables = '*'){
 
	$conn = new mysqli($host, $user, $pass, $dbname);
	if ($conn->connect_error) {
	    die("Connection failed: " . $conn->connect_error);
	}
 
 
	if($tables == '*'){
		$tables = array();
		$sql = "SHOW TABLES";
		$query = $conn->query($sql);
		while($row = $query->fetch_row()){
			$tables[] = $row[0];
		}
	}
	else{
		$tables = is_array($tables) ? $tables : explode(',',$tables);
	}
 
 
	$outsql = '';
	foreach ($tables as $table) {
 
 
	    $sql = "SHOW CREATE TABLE $table";
	    $query = $conn->query($sql);
	    $row = $query->fetch_row();
 
	    $outsql .= "\n\n" . $row[1] . ";\n\n";
 
	    $sql = "SELECT * FROM $table";
	    $query = $conn->query($sql);
 
	    $columnCount = $query->field_count;
 
 
	    for ($i = 0; $i < $columnCount; $i ++) {
	        while ($row = $query->fetch_row()) {
	            $outsql .= "INSERT INTO $table VALUES(";
	            for ($j = 0; $j < $columnCount; $j ++) {
	                $row[$j] = $row[$j];
 
	                if (isset($row[$j])) {
	                    $outsql .= '"' . $row[$j] . '"';
	                } else {
	                    $outsql .= '""';
	                }
	                if ($j < ($columnCount - 1)) {
	                    $outsql .= ',';
	                }
	            }
	            $outsql .= ");\n";
	        }
	    }
 
	    $outsql .= "\n"; 
	}
 
 
    $fileHandler = fopen($backup_file_name, 'w+');
    fwrite($fileHandler, $outsql);
    fclose($fileHandler);
 
 
}


?>