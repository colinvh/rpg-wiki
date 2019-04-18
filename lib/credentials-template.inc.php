<?

// see the mysqli documentation (https://www.php.net/manual/en/book.mysqli.php) for configuration details
function db_conf() {
    $conn = new mysqli(
        'example.com', // host
        'rpg_wiki', // username
        '************', // password
        'rpg_wiki' // database
    );
    if ($conn->connect_errno) {
        trigger_error("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// see the PHPMailer documentation (https://github.com/PHPMailer/PHPMailer/wiki) for configuration deatils
function mail_conf($mail) {
    $mail->isSMTP();
    $mail->Host = 'smtp.example.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'user@example.com';
    $mail->Password = '************';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
}

?>
