<?php

class Mailer
{
    private $host = 'localhost';
    private $port = 1025; // Standard Mailpit SMTP port
    private $senderName = "UI Info Stats";
    private $senderEmail = "noreply@ui.edu.ng";

    /**
     * Sends the OTP via SMTP to Mailpit.
     */
    public function sendOtp($toEmail, $otp)
    {
        $subject = "Password Reset Verification Code";

        // HTML Email Template
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
        </head>
        <body style='font-family: Helvetica, Arial, sans-serif; background-color: #f4f4f4; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <div style='background-color: #2c3e50; padding: 20px; text-align: center;'>
                    <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>$this->senderName</h1>
                </div>
                <div style='padding: 30px;'>
                    <p style='color: #333333; font-size: 16px; line-height: 1.5;'>Hello,</p>
                    <p style='color: #333333; font-size: 16px; line-height: 1.5;'>You have requested to reset your password. Please use the verification code below to complete the process. This code will expire in 5 minutes.</p>
                    
                    <div style='background-color: #f8f9fa; border: 1px dashed #cccccc; padding: 15px; text-align: center; margin: 20px 0;'>
                        <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #2563eb;'>$otp</span>
                    </div>

                    <p style='color: #666666; font-size: 14px; margin-top: 30px;'>If you did not request this password reset, please ignore this email.</p>
                </div>
                <div style='background-color: #eeeeee; padding: 15px; text-align: center; font-size: 12px; color: #888888;'>
                    &copy; " . date("Y") . " University of Ibadan Info Statistics. All rights reserved.
                </div>
            </div>
        </body>
        </html>";

        return $this->sendSmtp($toEmail, $subject, $message);
    }

    /**
     * Connects to Mailpit SMTP (no auth required usually)
     */
    private function sendSmtp($to, $subject, $htmlMessage)
    {
        // 1. Open Connection
        $socket = fsockopen($this->host, $this->port, $errno, $errstr, 10);
        if (!$socket) {
            error_log("Mailer Error: Could not connect to Mailpit at $this->host:$this->port. Is it running?");
            return false;
        }

        // 2. Read Greeting
        $this->readResponse($socket);

        // 3. Handshake (EHLO)
        fputs($socket, "EHLO " . $this->host . "\r\n");
        $this->readResponse($socket);

        // 4. Sender and Recipient
        fputs($socket, "MAIL FROM: <$this->senderEmail>\r\n");
        $this->readResponse($socket);

        fputs($socket, "RCPT TO: <$to>\r\n");
        $this->readResponse($socket);

        // 5. Data Transmission
        fputs($socket, "DATA\r\n");
        $this->readResponse($socket);

        // 6. Headers and Body
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $this->senderName <$this->senderEmail>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Date: " . date("r") . "\r\n";

        fputs($socket, "$headers\r\n$htmlMessage\r\n.\r\n");
        $this->readResponse($socket);

        // 7. Quit
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    private function readResponse($socket)
    {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            // SMTP response lines end with a space after the code (e.g., "250 OK")
            // Multi-line responses use a dash (e.g., "250-SIZE")
            if (substr($str, 3, 1) == " ") {
                break;
            }
        }
        return $response;
    }
}
