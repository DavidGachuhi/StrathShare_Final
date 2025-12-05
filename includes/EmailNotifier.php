<?php



// Include PHPMailer files
// If using Composer: require 'vendor/autoload.php';
// If manual installation:
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailNotifier
{


    // SMTP CONFIGURATION - UPDATE THESE WITH YOUR CREDENTIALS

    // Gmail SMTP settings
    private $smtp_host = 'smtp.gmail.com';
    private $smtp_port = 587;
    private $smtp_secure = 'tls'; // Use 'ssl' for port 465

    // Your Gmail credentials - REPLACE THESE!
    private $smtp_username = 'strathshare@gmail.com';      // Your Gmail address
    private $smtp_password = 'fwfmgsrewbhhasld';    // Gmail App Password (16 chars)

    // Sender info
    private $from_email = 'noreply@strathshare.com';
    private $from_name = 'StrathShare';

    // Enable/disable emails (set to false during development)
    private $emails_enabled = true;

    // ===================================================================

    private $mailer;
    private $last_error = '';

    /**
     * Constructor - Initialize PHPMailer
     */
    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->configureSMTP();
    }

    /**
     * Configure SMTP settings
     */
    private function configureSMTP()
    {
        try {
            // Server settings
            $this->mailer->isSMTP();
            
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            $this->mailer->Debugoutput = function ($str, $level) {
                error_log("PHPMailer [$level]: $str");
            };
            
            $this->mailer->Host = $this->smtp_host;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtp_username;
            $this->mailer->Password = $this->smtp_password;
            $this->mailer->SMTPSecure = $this->smtp_secure;
            $this->mailer->Port = $this->smtp_port;

            // Sender
            $this->mailer->setFrom($this->from_email, $this->from_name);

            // Email format
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("EmailNotifier SMTP Config Error: " . $e->getMessage());
        }
    }

    /**
     * Send email
     * @param string $to_email Recipient email
     * @param string $to_name Recipient name
     * @param string $subject Email subject
     * @param string $html_body HTML email body
     * @param string $plain_body Plain text alternative
     * @return bool Success status
     */
    public function sendEmail($to_email, $to_name, $subject, $html_body, $plain_body = '')
    {
        // Skip if emails are disabled
        if (!$this->emails_enabled) {
            error_log("Email skipped (disabled): To: $to_email, Subject: $subject");
            return true; // Return true so code continues
        }

        try {
            // Reset mailer for new email
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Add recipient
            $this->mailer->addAddress($to_email, $to_name);

            // Content
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $html_body;
            $this->mailer->AltBody = $plain_body ?: strip_tags($html_body);

            // Send
            $this->mailer->send();

            error_log("Email sent successfully to: $to_email");
            return true;
        } catch (Exception $e) {
            $this->last_error = $this->mailer->ErrorInfo;
            error_log("Email send error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }

    /**
     * Get last error message
     * @return string Error message
     */
    public function getLastError()
    {
        return $this->last_error;
    }
    
    // NOTIFICATION TEMPLATES

    /**
     * Send notification when request is accepted by provider
     */
    public function sendRequestAcceptedNotification($seeker_email, $seeker_name, $provider_name, $request_title)
    {
        $subject = "Your Request Has Been Accepted! - StrathShare";

        $html_body = $this->getEmailTemplate(
            "Request Accepted! 🎉",
            "Good news, $seeker_name!",
            "
            <p><strong>$provider_name</strong> has accepted your request:</p>
            <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                <strong>$request_title</strong>
            </div>
            <p>You can now chat with $provider_name to discuss the details and get started!</p>
            ",
            "Chat Now",
            "https://localhost/StrathShare/index.html"
        );

        return $this->sendEmail($seeker_email, $seeker_name, $subject, $html_body);
    }

    /**
     * Send notification when provider marks work as complete
     */
    public function sendWorkCompletedNotification($seeker_email, $seeker_name, $provider_name, $request_title, $amount)
    {
        $subject = "Work Completed - Please Confirm & Pay - StrathShare";

        $html_body = $this->getEmailTemplate(
            "Work Completed! ✅",
            "Hi $seeker_name,",
            "
            <p><strong>$provider_name</strong> has marked the following request as complete:</p>
            <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                <strong>$request_title</strong>
            </div>
            <p>Please review the work and confirm completion.</p>
            <p style='font-size: 18px; color: #ff2d55;'><strong>Amount Due: KES " . number_format($amount, 2) . "</strong></p>
            <p>Click below to confirm and make payment via M-Pesa.</p>
            ",
            "Confirm & Pay",
            "https://localhost/StrathShare/index.html"
        );

        return $this->sendEmail($seeker_email, $seeker_name, $subject, $html_body);
    }

    /**
     * Send notification when payment is received
     */
    public function sendPaymentReceivedNotification($provider_email, $provider_name, $seeker_name, $request_title, $amount, $mpesa_ref)
    {
        $subject = "Payment Received! 💰 - StrathShare";

        $html_body = $this->getEmailTemplate(
            "Payment Received! 💰",
            "Congratulations $provider_name!",
            "
            <p>You have received a payment from <strong>$seeker_name</strong>:</p>
            <div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                <p style='margin: 0;'><strong>Request:</strong> $request_title</p>
                <p style='margin: 10px 0 0;'><strong>Amount:</strong> KES " . number_format($amount, 2) . "</p>
                <p style='margin: 10px 0 0;'><strong>M-Pesa Reference:</strong> $mpesa_ref</p>
            </div>
            <p>Don't forget to leave a review for $seeker_name!</p>
            ",
            "Leave Review",
            "https://localhost/StrathShare/index.html"
        );

        return $this->sendEmail($provider_email, $provider_name, $subject, $html_body);
    }

    /**
     * Send notification when payment is made (to seeker)
     */
    public function sendPaymentConfirmationNotification($seeker_email, $seeker_name, $provider_name, $request_title, $amount, $mpesa_ref)
    {
        $subject = "Payment Successful! - StrathShare";

        $html_body = $this->getEmailTemplate(
            "Payment Successful! ✅",
            "Hi $seeker_name,",
            "
            <p>Your payment has been processed successfully:</p>
            <div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                <p style='margin: 0;'><strong>Provider:</strong> $provider_name</p>
                <p style='margin: 10px 0 0;'><strong>Request:</strong> $request_title</p>
                <p style='margin: 10px 0 0;'><strong>Amount:</strong> KES " . number_format($amount, 2) . "</p>
                <p style='margin: 10px 0 0;'><strong>M-Pesa Reference:</strong> $mpesa_ref</p>
            </div>
            <p>Please leave a review for $provider_name to help other students!</p>
            ",
            "Leave Review",
            "https://localhost/StrathShare/index.html"
        );

        return $this->sendEmail($seeker_email, $seeker_name, $subject, $html_body);
    }

    /**
     * Send new message notification
     */
    public function sendNewMessageNotification($receiver_email, $receiver_name, $sender_name)
    {
        $subject = "New Message from $sender_name - StrathShare";

        $html_body = $this->getEmailTemplate(
            "New Message 💬",
            "Hi $receiver_name,",
            "
            <p>You have a new message from <strong>$sender_name</strong> on StrathShare.</p>
            <p>Log in to read and reply to your message.</p>
            ",
            "View Messages",
            "https://localhost/StrathShare/index.html"
        );

        return $this->sendEmail($receiver_email, $receiver_name, $subject, $html_body);
    }

    /**
     * Send new review notification
     */
    public function sendNewReviewNotification($user_email, $user_name, $reviewer_name, $rating)
    {
        $stars = str_repeat('⭐', $rating);
        $subject = "New $rating-Star Review! - StrathShare";

        $html_body = $this->getEmailTemplate(
            "New Review! $stars",
            "Hi $user_name,",
            "
            <p><strong>$reviewer_name</strong> has left you a review:</p>
            <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: center;'>
                <span style='font-size: 32px;'>$stars</span>
                <p style='margin: 10px 0 0;'><strong>$rating out of 5 stars</strong></p>
            </div>
            <p>Great job! Keep up the excellent work.</p>
            ",
            "View Profile",
            "https://localhost/StrathShare/index.html"
        );

        return $this->sendEmail($user_email, $user_name, $subject, $html_body);
    }

    /**
     * Send welcome email after registration
     */
    public function sendWelcomeEmail($user_email, $user_name)
    {
        $subject = "Welcome to StrathShare! 🎓";

        $html_body = $this->getEmailTemplate(
            "Welcome to StrathShare! 🎓",
            "Hi $user_name,",
            "
            <p>Welcome to StrathShare - the peer-to-peer skill sharing platform exclusively for Strathmore University students!</p>
            <p>Here's what you can do:</p>
            <ul style='line-height: 1.8;'>
                <li><strong>Offer Services</strong> - Share your skills and earn money</li>
                <li><strong>Request Help</strong> - Get assistance from fellow students</li>
                <li><strong>Build Your Profile</strong> - Showcase your expertise</li>
                <li><strong>Connect</strong> - Network with other talented students</li>
            </ul>
            <p>Get started by browsing available services or posting your first offering!</p>
            ",
            "Get Started",
            "https://localhost/StrathShare/index.html"
        );

        return $this->sendEmail($user_email, $user_name, $subject, $html_body);
    }
    
    // ===================================================================
    // EMAIL TEMPLATE
    // ===================================================================

    /**
     * Get styled email template
     */
    private function getEmailTemplate($title, $greeting, $content, $button_text, $button_url)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #0a0a14;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #0a0a14; padding: 40px 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background: linear-gradient(145deg, #1a1a2e, #16162a); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.5);'>
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #ff2d55, #ff5b7c); padding: 30px; text-align: center;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;'>StrathShare</h1>
                                    <p style='margin: 5px 0 0; color: rgba(255,255,255,0.9); font-size: 14px;'>Strathmore University</p>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <h2 style='margin: 0 0 20px; color: #ffffff; font-size: 24px;'>$title</h2>
                                    <p style='color: #b3b3c0; font-size: 16px; margin: 0 0 20px;'>$greeting</p>
                                    <div style='color: #e0e0e8; font-size: 15px; line-height: 1.6;'>
                                        $content
                                    </div>
                                    
                                    <!-- Button -->
                                    <div style='text-align: center; margin: 30px 0;'>
                                        <a href='$button_url' style='display: inline-block; background: linear-gradient(135deg, #ff2d55, #ff5b7c); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 25px; font-weight: 600; font-size: 15px; box-shadow: 0 10px 30px rgba(255,45,85,0.4);'>$button_text</a>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style='background: #0f0f1a; padding: 25px 30px; text-align: center; border-top: 1px solid rgba(255,255,255,0.05);'>
                                    <p style='margin: 0; color: #6b6b80; font-size: 13px;'>
                                        This email was sent by StrathShare<br>
                                        Peer-to-Peer Skill Sharing Platform<br>
                                        Strathmore University, Nairobi, Kenya
                                    </p>
                                    <p style='margin: 15px 0 0; color: #4a4a5a; font-size: 12px;'>
                                        © 2025 StrathShare. All rights reserved.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
}

// ===================================================================
// HELPER FUNCTION - Easy access to email notifications
// ===================================================================

/**
 * Get EmailNotifier instance (singleton pattern)
 * @return EmailNotifier
 */
function getEmailNotifier()
{
    static $notifier = null;
    if ($notifier === null) {
        $notifier = new EmailNotifier();
    }
    return $notifier;
}
