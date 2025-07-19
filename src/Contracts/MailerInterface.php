<?php

namespace EVS\Contracts;

/**
 * Interface for email sending services
 */
interface MailerInterface
{
    /**
     * Send an email
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool;

    /**
     * Send email using template
     */
    public function sendTemplate(string $template, string $to, array $data = []): bool;

    /**
     * Queue email for later sending
     */
    public function queue(string $to, string $subject, string $body, array $options = []): bool;
}
