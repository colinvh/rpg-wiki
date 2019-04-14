<?

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/PHPMailer/src/Exception.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/PHPMailer/src/PHPMailer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/PHPMailer/src/SMTP.php';

function send_mail($subject, $html_body, $text_body, ...$users) {
    $mail = new PHPMailer(true);
    // try {
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'error_log';
        mail_conf($mail);
        $mail->setFrom('noreply@rpg-wiki.divitu.com', 'RPG Wiki');
        foreach ($users as $user) {
            $mail->addAddress($user->email, $user->nickname);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $text_body;
        $mail->send();
    // } catch (Exception $e) {
    //     throw new Exception($mail->ErrorInfo);
    // }
}

?>
