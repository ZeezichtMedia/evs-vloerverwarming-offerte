<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/Config.php';

session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$config = EVS\Config\Config::getInstance();
$client = new \Supabase\Client($config->get('SUPABASE_URL'), $config->get('SUPABASE_KEY'));

$offer_id = $_GET['id'] ?? null;
if (!$offer_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch the offer data
$response = $client->from('offers')->select('*')->eq('id', $offer_id)->execute();
if (empty($response->data)) {
    die('Offer not found.');
}
$offerData = $response->data[0];

// Initialize email client
$transport = (new \Swift_SmtpTransport($config->get('SMTP_HOST'), $config->get('SMTP_PORT')))
    ->setUsername($config->get('SMTP_USER'))
    ->setPassword($config->get('SMTP_PASS'));
$mailer = new \Swift_Mailer($transport);

// Construct the email body
$drillingPrice = number_format($offerData['drilling_price'], 2, ',', '.');
$sealingPrice = number_format($offerData['sealing_price'], 2, ',', '.');

$emailBody = "Beste " . htmlspecialchars($offerData['customer_name']) . ",\n\n";
$emailBody .= "In navolging van uw aanvraag, sturen wij u hierbij de definitieve offerte voor de vloerverwarming.\n\n";
$emailBody .= "Offerte 1: Infrezen van de vloerverwarming\n";
$emailBody .= "Totaalbedrag: €{$drillingPrice}\n\n";

if ($offerData['sealing_price'] > 0) {
    $emailBody .= "Offerte 2: Dichtsmeren van de sleuven\n";
    $emailBody .= "Totaalbedrag: €{$sealingPrice}\n\n";
}

$emailBody .= "Indien u akkoord gaat met deze offerte(s), kunt u reageren op deze e-mail.\n\n";
$emailBody .= "Met vriendelijke groet,\nHet team van EVS Vloerverwarmingen";

// Send the email
$message = (new \Swift_Message('Uw definitieve offerte van EVS Vloerverwarmingen'))
    ->setFrom(['no-reply@evs-vloerverwarmingen.nl' => 'EVS Vloerverwarmingen'])
    ->setTo([$offerData['customer_email'] => $offerData['customer_name']])
    ->setBody($emailBody, 'text/plain');

$mailer->send($message);

// Optional: Update the status of the offer to 'sent'
$client->from('offers')->update(['status' => 'sent'])->eq('id', $offer_id)->execute();

// Redirect back to the dashboard with a success message
header('Location: dashboard.php?status=sent');
exit();
