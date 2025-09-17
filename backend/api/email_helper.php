<?php
// backend/api/email_helper.php

/**
 * Sends a confirmation email.
 *
 * @param string $to The recipient's email address.
 * @param string $subject The subject of the email.
 * @param string $body The body of the email.
 * @return bool True on success, false on failure.
 */
function send_confirmation_email(string $to, string $subject, string $body): bool
{
    // For this to work, the server must have a configured `sendmail` service.
    // This is a common setup for many PHP hosting environments.

    // In a real-world, high-volume application, using a dedicated email service
    // like SendGrid or Mailgun via their API would be more robust.

    $headers = "From: no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";
    $headers .= "Reply-To: no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // The mail() function returns false if it failed to be accepted for delivery.
    return mail($to, $subject, $body, $headers);
}
?>
