<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Psr\Log\LoggerInterface;

class EmailService
{
    private array $config;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->config = require __DIR__ . '/../../config/email.php';
    }

    public function sendEmailConfirmation(string $email, string $fullName, string $token): bool
    {
        $this->logger->info('Sending email confirmation', [
            'to' => $email,
            'name' => $fullName
        ]);

        $subject = 'Confirm Your GrooDo Account';
        $confirmationUrl = $this->buildConfirmationUrl($token);
        
        $htmlBody = $this->getEmailConfirmationTemplate($fullName, $confirmationUrl);
        $textBody = $this->getEmailConfirmationTextTemplate($fullName, $confirmationUrl);

        return $this->sendEmail($email, $fullName, $subject, $htmlBody, $textBody);
    }

    public function sendPasswordReset(string $email, string $fullName, string $token): bool
    {
        $this->logger->info('Sending password reset email', [
            'to' => $email,
            'name' => $fullName
        ]);

        $subject = 'Reset Your GrooDo Password';
        $resetUrl = $this->buildPasswordResetUrl($token);
        
        $htmlBody = $this->getPasswordResetTemplate($fullName, $resetUrl);
        $textBody = $this->getPasswordResetTextTemplate($fullName, $resetUrl);

        return $this->sendEmail($email, $fullName, $subject, $htmlBody, $textBody);
    }

    private function sendEmail(string $email, string $name, string $subject, string $htmlBody, string $textBody): bool
    {
        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['encryption'];
            $mail->Port = $this->config['smtp']['port'];

            // Recipients
            $mail->setFrom($this->config['from']['email'], $this->config['from']['name']);
            $mail->addAddress($email, $name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            // Send email
            $mail->send();

            $this->logger->info('Email sent successfully', [
                'to' => $email,
                'subject' => $subject
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send email', [
                'to' => $email,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    private function buildConfirmationUrl(string $token): string
    {
        // Point to API endpoint that will handle confirmation and redirect
        $baseUrl = $_ENV['API_URL'] ?? 'https://groodo-api.greq.me';
        return $baseUrl . '/api/users/confirm-email?token=' . urlencode($token);
    }

    private function buildPasswordResetUrl(string $token): string
    {
        // In a real application, this would be the frontend URL
        $baseUrl = $_ENV['FRONTEND_URL'] ?? 'https://groodo.greq.me';
        return $baseUrl . '/reset-password?token=' . urlencode($token);
    }

    private function getEmailConfirmationTemplate(string $fullName, string $confirmationUrl): string
    {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Confirm Your GrooDo Account</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    line-height: 1.6;
                    color: #1a202c;
                    background-color: #f7fafc;
                    padding: 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                }
                .header h1 {
                    font-size: 28px;
                    font-weight: 600;
                    margin-bottom: 8px;
                }
                .header p {
                    font-size: 16px;
                    opacity: 0.9;
                }
                .content {
                    padding: 40px 30px;
                }
                .greeting {
                    font-size: 20px;
                    font-weight: 600;
                    color: #2d3748;
                    margin-bottom: 20px;
                }
                .message {
                    font-size: 16px;
                    color: #4a5568;
                    margin-bottom: 16px;
                }
                .button-container {
                    text-align: center;
                    margin: 35px 0;
                }
                .button {
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 16px 40px;
                    text-decoration: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    transition: transform 0.2s;
                }
                .link-box {
                    background-color: #f7fafc;
                    border: 1px solid #e2e8f0;
                    padding: 16px;
                    border-radius: 8px;
                    word-break: break-all;
                    font-size: 13px;
                    color: #718096;
                    margin: 20px 0;
                }
                .info-box {
                    background-color: #fef3c7;
                    border-left: 4px solid #f59e0b;
                    padding: 16px;
                    border-radius: 4px;
                    margin: 25px 0;
                }
                .info-box strong {
                    color: #92400e;
                    font-weight: 600;
                }
                .footer {
                    background-color: #f7fafc;
                    padding: 30px;
                    text-align: center;
                    font-size: 13px;
                    color: #718096;
                    border-top: 1px solid #e2e8f0;
                }
                .footer p {
                    margin: 8px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✓ Welcome to GrooDo</h1>
                    <p>Your calendar-based task manager</p>
                </div>
                <div class='content'>
                    <div class='greeting'>Hello {$fullName}! 👋</div>
                    <p class='message'>Thank you for signing up with GrooDo. We're excited to help you organize your tasks and boost your productivity!</p>
                    <p class='message'>To get started and access all features, please confirm your email address:</p>
                    
                    <div class='button-container'>
                        <a href='{$confirmationUrl}' class='button'>Confirm Your Email</a>
                    </div>
                    
                    <p class='message' style='font-size: 14px;'>Or copy and paste this link into your browser:</p>
                    <div class='link-box'>{$confirmationUrl}</div>
                    
                    <div class='info-box'>
                        <strong>⏱ Important:</strong> This confirmation link will expire in 1 hour for security reasons.
                    </div>
                    
                    <p class='message' style='font-size: 14px; color: #718096;'>If you didn't create a GrooDo account, please ignore this email. Your email will not be added to our system.</p>
                    
                    <p class='message' style='margin-top: 30px; color: #2d3748;'>
                        Best regards,<br>
                        <strong>The GrooDo Team</strong>
                    </p>
                </div>
                <div class='footer'>
                    <p><strong>GrooDo</strong> - Organize your tasks, maximize your productivity</p>
                    <p>This is an automated email. Please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getEmailConfirmationTextTemplate(string $fullName, string $confirmationUrl): string
    {
        return "
        Welcome to GrooDo!

        Hello {$fullName},

        Thank you for registering with GrooDo, your calendar-based todo app!

        To complete your registration and start organizing your tasks, please confirm your email address by visiting this link:

        {$confirmationUrl}

        Important: This confirmation link will expire in 1 hour for security reasons.

        If you didn't create a GrooDo account, please ignore this email.

        Best regards,
        The GrooDo Team

        ---
        This email was sent from GrooDo API. Please do not reply to this email.
        ";
    }

    private function getPasswordResetTemplate(string $fullName, string $resetUrl): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Reset Your GrooDo Password</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #FF6B6B; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
                .button { display: inline-block; background-color: #FF6B6B; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
                .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Password Reset Request</h1>
            </div>
            <div class='content'>
                <h2>Hello {$fullName},</h2>
                <p>We received a request to reset the password for your GrooDo account.</p>
                <p>If you requested this password reset, click the button below to create a new password:</p>
                <p style='text-align: center;'>
                    <a href='{$resetUrl}' class='button'>Reset Password</a>
                </p>
                <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background-color: #eee; padding: 10px; border-radius: 3px;'>{$resetUrl}</p>
                <div class='warning'>
                    <strong>Security Notice:</strong>
                    <ul>
                        <li>This password reset link will expire in 1 hour</li>
                        <li>If you didn't request this reset, please ignore this email</li>
                        <li>Your password will remain unchanged until you create a new one</li>
                    </ul>
                </div>
                <p>If you continue to have problems, please contact our support team.</p>
                <p>Best regards,<br>The GrooDo Team</p>
            </div>
            <div class='footer'>
                <p>This email was sent from GrooDo API. Please do not reply to this email.</p>
            </div>
        </body>
        </html>
        ";
    }

    private function getPasswordResetTextTemplate(string $fullName, string $resetUrl): string
    {
        return "
        Password Reset Request

        Hello {$fullName},

        We received a request to reset the password for your GrooDo account.

        If you requested this password reset, visit this link to create a new password:

        {$resetUrl}

        Security Notice:
        - This password reset link will expire in 1 hour
        - If you didn't request this reset, please ignore this email
        - Your password will remain unchanged until you create a new one

        If you continue to have problems, please contact our support team.

        Best regards,
        The GrooDo Team

        ---
        This email was sent from GrooDo API. Please do not reply to this email.
        ";
    }

    public function testConnection(): bool
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['encryption'];
            $mail->Port = $this->config['smtp']['port'];

            // Test connection without sending
            $mail->smtpConnect();
            $mail->smtpClose();

            $this->logger->info('Email service connection test successful');
            return true;

        } catch (Exception $e) {
            $this->logger->error('Email service connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
