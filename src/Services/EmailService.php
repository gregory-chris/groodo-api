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
        // In a real application, this would be the frontend URL
        $baseUrl = $_ENV['FRONTEND_URL'] ?? 'https://groodo.greq.me';
        return $baseUrl . '/confirm-email?token=' . urlencode($token);
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
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Confirm Your GrooDo Account</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
                .button { display: inline-block; background-color: #4CAF50; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Welcome to GrooDo!</h1>
            </div>
            <div class='content'>
                <h2>Hello {$fullName},</h2>
                <p>Thank you for registering with GrooDo, your calendar-based todo app!</p>
                <p>To complete your registration and start organizing your tasks, please confirm your email address by clicking the button below:</p>
                <p style='text-align: center;'>
                    <a href='{$confirmationUrl}' class='button'>Confirm Email Address</a>
                </p>
                <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background-color: #eee; padding: 10px; border-radius: 3px;'>{$confirmationUrl}</p>
                <p><strong>Important:</strong> This confirmation link will expire in 1 hour for security reasons.</p>
                <p>If you didn't create a GrooDo account, please ignore this email.</p>
                <p>Best regards,<br>The GrooDo Team</p>
            </div>
            <div class='footer'>
                <p>This email was sent from GrooDo API. Please do not reply to this email.</p>
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
