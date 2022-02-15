<?php

use App\Models\User;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sms($message = "", $num = "")
{
    $fields = array(
        "sender_id" => env('F2SMS_SENDER_ID'),
        "message" => $message,
        "route" => "v3",
        "numbers" => $num,
    );

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($fields),
        CURLOPT_HTTPHEADER => array(
            "authorization: " . env('F2SMS_AUTH_KEY'),
            "accept: */*",
            "cache-control: no-cache",
            "content-type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return false;
    } else {
        $response = json_decode($response);
        if ($response->return) {
            return $response->request_id;
        } else {
            return false;
        }
    }
}


if (!function_exists('email_otp_template')) {
    function email_otp_template()
    {
        $html = '<!DOCTYPE html><html lang="en"> <head> <meta charset="UTF-8"/> <meta http-equiv="Content-Type" content="text/html charset=UTF-8"/> <meta name="viewport" content="width=device-width, initial-scale=1.0"/> <title>Rent A Roof Email Verification</title> <link rel="preconnect" href="https://fonts.googleapis.com"/> <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/> <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300&display=swap" rel="stylesheet"/> </head> <body style="font-family: \'Merriweather\', serif;"> <table style="display: flex; flex-direction: column; justify-content: center; align-items: flex-start; max-width: 450px; width: 100%; margin: 0px auto;"> <tr> <td style=" width: 100%; display: flex; align-items: center; justify-content: center; "> <img style="width: 180px; height: 180px; object-fit: cover;" src="https://testspacefile.fra1.cdn.digitaloceanspaces.com/uploads/logo/FV8I1QXIeHXXFjeRMsuEsuIxtCcIcleDU08PKicn.png" alt="rentaroof"/> </td></tr><tr> <td style="position: relative"> <h2>Rent A Roof Email Verification</h2> <span style=" height: 5px; width: 50px; position: absolute; bottom: 15px; left: 0; background-color: #fbb031; border-radius: 10px; " ></span> </td></tr><tr> <td > <p style="line-height: 25px;"> Dear {{user}}, <br/> Please verify your email using OTP mentioned below. OTP is valid for 10 minutes only. </p></td></tr><tr> <td style=" border-bottom: 5px solid rgb(5 79 138); border-bottom-left-radius: 10px; "> <p style="font-size: 1rem;">Your OTP is</p><p style="font-size: 3rem;">{{otp}}</p></td></tr><tr> <td style=" font-size: 0.9rem; font-family: \'Courier New\', Courier, monospace; line-height: 0px; "> <p>&copy;Copyright 2022 Rent A Roof</p></td></tr></table> </body></html>';
        return $html;
    }
}

if (!function_exists('send_email_otp')) {
    function send_email_otp($data)
    {
        if ($data) {
            $mail = phpmailer();

            try {
                //Recipients
                $mail->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
                $mail->addAddress($data['email'], $data['user']);     //Add a recipient
                //Content
                $mail->isHTML(true);                                  //Set email format to HTML
                $mail->Subject = 'Email Verification - Rent a Roof';
                $html = email_otp_template();
                $html = str_replace('{{user}}', $data['user'], $html);
                $html = str_replace('{{otp}}', $data['otp'], $html);

                $mail->Body    = $html;
                $mail->send();
                return 1;
            } catch (Exception $e) {
                return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        }
    }
}


if (!function_exists('send_email')) {
    function send_email($to, $data)
    {
        if ($to && $data) {
            $mail = phpmailer();

            try {
                //Recipients
                $mail->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
                $mail->addAddress($to);     //Add a recipient
                //Content
                $mail->isHTML(true);                                  //Set email format to HTML
                $mail->Subject = 'Email Verification - Rent a Roof';
                $mail->Body    = $data;
                $mail->send();
                return 1;
            } catch (Exception $e) {
                return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        }
    }
}

if (!function_exists('phpmailer')) {
    function phpmailer()
    {
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->isSMTP();                                            //Send using SMTP
            $mail->Host       = env('MAIL_HOST');                     //Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $mail->Username   = env('MAIL_USERNAME');                     //SMTP username
            $mail->Password   = env('MAIL_PASSWORD');                               //SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
            $mail->Port       = env('MAIL_PORT');

            return $mail;
        } catch (Exception $e) {
            return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}

if (!function_exists('push_notification')) {
    function push_notification($notification)
    {
        $user_id = $notification->landlord_id ? $notification->landlord_id : ($notification->ibo_id ? $notification->ibo_id : ($notification->tenant_id ? $notification->tenant_id : 0));
        $firebaseToken = User::whereNotNull('device_token')->where("id", $user_id)->first();

        $data = [
            "to" => $firebaseToken->device_token ?? '',
            "notification" => [
                "title" => $notification->title,
                "body" => $notification->content,
            ]
        ];
        $dataString = json_encode($data);

        $headers = [
            'Authorization: key=' . env('FIREBASE_SERVER_KEY'),
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        $response = curl_exec($ch);
        return $response;
    }
}
