<?php

//Config 

//########################################

$registerNew = true;  //Turn this to false if you dont want to register a new password
$dir_path = "./";  //This is for the directory the backup.php lies in.
$correct_password = "827ccb0eea8a706c4c34a16891f84e7b"; // pass=12345, Change this to your actual password as an MD5 hash

//Database conf, if you want them permanent

$set_dbhost = '127.0.0.1';
$set_dbname = "db_name";
$set_dbuser = "db_user";
$set_dbpass = "db_pass";

//########################################


if (!empty($_POST['newpassword']) && $registerNew == true) {

    $newpass = md5($_POST['newpassword']);
    $backupFile = file_get_contents("." . $_SERVER['PHP_SELF']);
    $backupFile = str_replace($correct_password, $newpass, $backupFile);
    $backupFile = preg_replace('/\$registerNew = true;/', '$registerNew = false;', $backupFile, 1);
    file_put_contents("." . $_SERVER['PHP_SELF'] . ".tmp", $backupFile);
    if (unlink("." . $_SERVER['PHP_SELF']) && rename("." . $_SERVER['PHP_SELF'] . ".tmp", "." . $_SERVER['PHP_SELF'])) {
        $registerNew = false;
    } else {
        echo '<div class="alert alert-success" role="alert">Failed to register, check file permissions</div>';
    }



}


session_start();

function logout()
{
    $_SESSION = array();

    // Destroy the session
    session_destroy();
}

function preventUnAuthorized()
{
    if (!authorizeLogggedIn()) {
        die("Unauthorized action");
    }
}
function authorizeLogggedIn()
{
    global $correct_password, $set_dbhost, $set_dbname, $set_dbuser, $set_dbpass;
    if (isset($_SESSION['backup_logged_in']) && $_SESSION['backup_logged_in'] === $correct_password) {
        if (isset($_SESSION['set_dbhost'])) {
            $set_dbhost = $_SESSION['set_dbhost'];
        }
        if (isset($_SESSION['set_dbuser'])) {
            $set_dbuser = $_SESSION['set_dbuser'];
        }
        if (isset($_SESSION['set_dbname'])) {
            $set_dbname = $_SESSION['set_dbname'];
        }
        if (isset($_SESSION['set_dbpass'])) {
            $set_dbpass = $_SESSION['set_dbpass'];
        }
        return true;
    } else {
        return false;
    }
}

if (isset($_GET["logout"]) && $_GET["logout"] == 1) {
    logout();
    header("Location: " . $_SERVER['PHP_SELF']);
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if the password is correct (you need to define your own password)


    if (isset($_POST["password"]) && !$_SESSION['backup_logged_in'] && md5($_POST["password"]) == $correct_password) {
        // Password is correct, set backup_logged_in session variable
        $_SESSION['backup_logged_in'] = $correct_password;
    } else {
        if (isset($_POST["password"])) {
            echo '<div class="alert alert-danger" role="alert">Wrong password.</div>';
        }
    }
}
function unzipFile($zipFile)
{
    preventUnAuthorized();
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo('./'); // Extract to the current directory
        $zip->close();
        echo '<div class="alert alert-success" role="alert">File unzipped successfully!</div>';
    } else {
        echo '<div class="alert alert-danger" role="alert">Failed to unzip the file.</div>';
    }
}

// Handle sql import action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_file'])) {
    preventUnAuthorized();
    $disable_foreign = false;
    if (isset($_POST['disableforeginkey'])) {
        $disable_foreign = true;
    }
    $selectedSQLFile = $_POST['sql_file'];
    doSqlImport($selectedSQLFile, $disable_foreign);
}

// Handle unzip action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zip_file'])) {
    preventUnAuthorized();
    $selectedZipFile = $_POST['zip_file'];
    unzipFile($selectedZipFile);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mysql'])) {
    preventUnAuthorized();
    // Backup SQL
    $db_host = $_POST['db_host'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $db_name = $_POST['db_name'];
    $_SESSION['set_dbhost'] = $db_host;
    $_SESSION['set_dbuser'] = $db_user;
    $_SESSION['set_dbpass'] = $db_pass;
    $_SESSION['set_dbname'] = $db_name;

    $filename = doSqlBackup($db_host, $db_user, $db_pass, $db_name);

    header("Content-type: application/zip");
    header("Content-Disposition: attachment; filename=$filename");
    header("Content-length: " . filesize($filename));
    header("Pragma: no-cache");
    header("Expires: 0");
    readfile($filename);
    unlink($filename);
    exit;



} elseif (isset($_POST['full_backup'])) {
    preventUnAuthorized();
    $db_host = $_POST['db_host'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $db_name = $_POST['db_name'];
    $_SESSION['set_dbhost'] = $db_host;
    $_SESSION['set_dbuser'] = $db_user;
    $_SESSION['set_dbpass'] = $db_pass;
    $_SESSION['set_dbname'] = $db_name;

    $rootPath = realpath($dir_path);

    $sqlfile = doSqlBackup($db_host, $db_user, $db_pass, $db_name);
    $archive_file_name = "backup-" . time() . ".zip";

    // Initialize archive object
    $zip = new ZipArchive();
    $zip->open($archive_file_name, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // Create recursive directory iterator
    /** @var SplFileInfo[] $files */
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        // Skip directories (they would be added automatically)
        if (!$file->isDir()) {
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
    unlink($sqlfile);

    unlink($archive_file_name);

    exit;
} elseif (isset($_POST['connect'])) {
    preventUnAuthorized();
    try {
        $db_host = $_POST['db_host'];
        $db_user = $_POST['db_user'];
        $db_pass = $_POST['db_pass'];
        $db_name = $_POST['db_name'];
        $dbConnection = new mysqli($db_host, $db_user, $db_pass, $db_name);
    } catch (Exception $e) {

        header("Location: " . $_SERVER['PHP_SELF'] . "?error=Connection failed: " . $e->getMessage());
        exit;
    }

    $_SESSION['set_dbhost'] = $db_host;
    $_SESSION['set_dbuser'] = $db_user;
    $_SESSION['set_dbpass'] = $db_pass;
    $_SESSION['set_dbname'] = $db_name;
    $_SESSION['db_connection'] = true;
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=Connected to Database ");

}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file"])) {
    preventUnAuthorized();

    $uploadFile = $dir_path . basename($_FILES["file"]["name"]);

    // Check if file already exists
    if (file_exists($uploadFile)) {
        echo '<div class="alert alert-danger" role="alert">File already exists.</div>';
    } else {
        // Try to upload file
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $uploadFile)) {
            echo '<div class="alert alert-success" role="alert">File uploaded successfully!</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">Error uploading file.</div>';
        }
    }
}

function doSqlImport($sqlFile, $disable_foreign)
{
    preventUnAuthorized();
    global $set_dbhost, $set_dbname, $set_dbuser, $set_dbpass;
    try {
        $dbConnection = new mysqli($set_dbhost, $set_dbuser, $set_dbpass, $set_dbname);
    } catch (Exception $e) {

        header("Location: " . $_SERVER['PHP_SELF'] . "?error=Connection failed: " . urlencode($e->getMessage()));
        exit;
    }
    if ($disable_foreign) {
        $dbConnection->query('SET FOREIGN_KEY_CHECKS=0');
    }
    $sql = file($sqlFile);
    $query = '';
    foreach ($sql as $line) {
        $startWith = substr(trim($line), 0, 2);
        $endWith = substr(trim($line), -1, 1);

        if (empty($line) || $startWith == '--' || $startWith == '/*' || $startWith == '//') {
            continue;
        }
        $query = $query . $line;
        if ($endWith == ';') {
            try {
                $result = mysqli_query($dbConnection, $query);
                if (!$result) {
                    header("Location: " . $_SERVER['PHP_SELF'] . "?error='Error  : " . mysqli_error($dbConnection));
                    exit;
                }
            } catch (Exception $e) {

                header("Location: " . $_SERVER['PHP_SELF'] . "?error=Connection failed: " . urlencode($e->getMessage()));
                exit;
            }
            $query = '';
        }
    }
    mysqli_close($dbConnection);
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=Database imported");
    exit;

}

function doSqlBackup($host, $user, $pass, $dbname)
{

    preventUnAuthorized();

    try {
        $conn = new mysqli($host, $user, $pass, $dbname);

        if ($conn->connect_error) {

            header("Location: " . $_SERVER['PHP_SELF'] . "?error=Connection failed: " . $conn->connect_error);
            exit;
        }

        // Continue with your code here
    } catch (Exception $e) {

        header("Location: " . $_SERVER['PHP_SELF'] . "?error=Connection failed: " . urlencode($e->getMessage()));
        exit;
    }

    $outsql = '';

    $tables = array();
    $sql = "SHOW TABLES";
    $query = $conn->query($sql);
    while ($row = $query->fetch_row()) {
        $tables[] = $row[0];
    }


    foreach ($tables as $table) {
        $outsql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql = "SHOW CREATE TABLE $table";
        $query = $conn->query($sql);
        $createTable = $query->fetch_assoc()['Create Table'];
        $outsql .= $createTable . ";\n";

        $sql = "SELECT * FROM $table";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $keys = array_keys($row);
            $values = array_map(function ($value) use ($conn) {
                return $conn->real_escape_string($value);
            }, $row);
            $outsql .= "INSERT INTO `$table` (`" . implode('`,`', $keys) . "`) VALUES ('" . implode("','", $values) . "');\n";
        }
        $outsql .= "\n";
    }
    $backup_file_name = $dbname . '_' . date('Ymd_His') . '.sql';
    $fileHandler = fopen($backup_file_name, 'w+');
    fwrite($fileHandler, $outsql);
    fclose($fileHandler);
    return $backup_file_name;

}
if (!isset($_POST['full_backup']) && !isset($_POST['mysql'])) {
    if (!empty($_GET['error'])) {

        echo '<div class="alert alert-danger" role="alert">' . $_GET['error'] . '</div>';
    }
    if (!empty($_GET['success'])) {

        echo '<div class="alert alert-success" role="alert">' . $_GET['success'] . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Helper</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        html,
        body {
            height: 100%;
        }
        .content-wrap {
            min-height: 100%;
            /* Equal to the footer height */
            margin-bottom: -100px;
        }
        .footer {
            height: 100px;
        }
        .alert {
            position: absolute;
            top: 30px;
            left: 50%;
            transform: translate(-50%, 0);
            z-index: 1000;
        }
    </style>
</head>

<body>
    <header>
        <nav class="navbar navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="#"><i class="fas fa-cloud-upload-alt"></i> Backup Helper</a>
                <?php if (authorizeLogggedIn()): ?>
                    <a href="?logout=1" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div class="container mt-5">
        <?php if (!authorizeLogggedIn()): ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <?php if (!$registerNew): ?>
                        <!-- Login form -->
                        <div class="card">
                            <div class="card-header"><i class="fas fa-sign-in-alt"></i> Login</div>
                            <div class="card-body">
                                <form action="<?= $_SERVER['PHP_SELF']?>" method="post">
                                    <div class="form-group">
                                        <label for="password">Password:</label>
                                        <input type="password" name="password" id="password" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i>
                                        Login</button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Register form -->

                        <div class="card">
                            <div class="card-header"><i class="fas fa-user-plus"></i> Register</div>
                            <div class="card-body">
                                <form action="<?= $_SERVER['PHP_SELF']?>" method="post">
                                    <div class="form-group">
                                        <label for="password">New Password:</label>
                                        <input type="password" name="newpassword" id="password" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i>
                                        Register</button>
                                </form>
                            </div>
                        </div>


                    <?php endif; ?>

                </div>
            </div>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card mt-3">
                        <div class="card-header"><i class="fas fa-cloud-upload-alt"></i> Backup</div>
                        <div class="card-body">
                            <form action="<?= $_SERVER['PHP_SELF']?>" method="post">
                                <!-- Database Credentials -->
                                <div class="form-group">
                                    <label for="db_host">Database Host:</label>
                                    <input type="text" name="db_host" value="<?= $set_dbhost ?>" id="db_host"
                                        class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="db_user">Database Username:</label>
                                    <input type="text" name="db_user" id="db_user" value="<?= $set_dbuser ?>"
                                        class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="db_pass">Database Password:</label>
                                    <input type="password" name="db_pass" value="<?= $set_dbpass ?>" id="db_pass"
                                        class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="db_name">Database Name:</label>
                                    <input type="text" name="db_name" value="<?= $set_dbname ?>" id="db_name"
                                        class="form-control" required>
                                </div>
                                <button type="submit" name="connect" class="btn mb-1 btn-primary"><i
                                        class="fas fa-database"></i>
                                    Connect</button>
                                <hr>
                                <!-- Backup Options -->
                                <button type="submit" name="mysql" class="btn mb-1 btn-success"><i
                                        class="fas fa-database"></i>
                                    Backup SQL file</button>
                                <button type="submit" name="full_backup"
                                    onclick="javascript:alert('Wait, It may take some time')"
                                    class="btn  mb-1 btn-success"><i class="fas fa-cloud-upload-alt"></i> Backup all
                                    files+SQL</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mt-3">
                        <div class="card-header"><i class="fas fa-file-archive"></i> Unzip</div>
                        <div class="card-body">
                            <form  action="<?= $_SERVER['PHP_SELF']?>"  method="post">
                                <?php

                                $zip_files = glob('*.zip');
                                if (count($zip_files) > 0): ?>
                                    <div class="form-group">

                                        <label for="zip_file">Select ZIP file to Unzip:</label>

                                        <select name="zip_file" id="zip_file" class="form-control">
                                            <?php

                                            foreach ($zip_files as $file) {
                                                echo "<option value=\"$file\">$file</option>";
                                            }
                                            ?>
                                        </select>



                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-file-archive"></i>
                                        Unzip</button>
                                <?php else: ?>
                                    <p>No zip files found in the root directory</p>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>


                    <div class="card mt-3">
                        <div class="card-header"><i class="fas fa-database"></i> SQL Import</div>
                        <div class="card-body">
                            <form action="<?= $_SERVER['PHP_SELF']?>" method="post">
                                <?php

                                $sql_files = glob('*.sql');
                                if (count($sql_files) > 0): ?>
                                    <div class="form-group">

                                        <label for="zip_file">Select SQL file to Import:</label>

                                        <select name="sql_file" id="sql_file" class="form-control">
                                            <?php

                                            foreach ($sql_files as $file) {
                                                echo "<option value=\"$file\">$file</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" checked name="disableforeginkey"
                                            id="foreginkey">
                                        <label class="form-check-label mb-2" for="foreginkey">
                                            Disable Foreign Key Constraints
                                        </label>
                                    </div>

                                    <button type="submit" class="btn btn-primary"><i class="fas fa-database"></i>
                                        Import</button>
                                <?php else: ?>
                                    <p>No SQL files found in the root directory</p>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="card mt-3">

                        <div class="card-header"><i class="fas fa-upload"></i> Upload</div>
                        <div class="card-body">
                            <form action="<?= $_SERVER['PHP_SELF']?>" method="post" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="file">Choose File:</label>
                                    <input type="file" name="file" id="file" class="form-control-file">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
                            </form>
                        </div>


                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    </br></br>
    <footer class="footer mt-5 bg-dark py-3">
        <div class="container">
            <span class="text-muted">PHP BackUp Helper v1 <a href="https://github.com/captainerd"
                    target="_blank">Captainerd</a> - <a
                    href="https://github.com/captainerd/Backup-SQL-database-or-files"
                    target="_blank">Backup-SQL-database-or-files repository</a></span>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>

        setTimeout(() => {
            $(".alert").hide();
        }, 4000);

    </script>
</body>


</html>
