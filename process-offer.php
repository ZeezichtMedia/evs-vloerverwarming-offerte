<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/Config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Initialize configuration
$config = \EVS\Config\Config::getInstance();

// Initialize Supabase client
$client = new \Supabase\Client(
    $config->get('SUPABASE_URL'),
    $config->get('SUPABASE_KEY')
);

// Initialize email client
$transport = (new \Swift_SmtpTransport(
    $config->get('SMTP_HOST'),
    $config->get('SMTP_PORT')
))
->setUsername($config->get('SMTP_USER'))
->setPassword($config->get('SMTP_PASS'));

$mailer = new \Swift_Mailer($transport);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate request
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            throw new \Exception('Invalid request');
        }

        // Get and validate form data
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !is_array($data)) {
            throw new \Exception('Invalid form data');
        }

        // Combine names and map to expected legacy fields for the model
        $data['customer_name'] = trim(($data['firstname'] ?? '') . ' ' . ($data['lastname'] ?? ''));
        $data['customer_email'] = $data['email'] ?? null;

        // Create offer object
        $offer = new \EVS\Models\Offer($data);
        $offer->calculatePrices();

        // Validate required fields from the form
        $requiredFields = [
            'floor_type', 'area', 'floor_level', 'heat_source',
            'distributor', 'sealing', 'firstname', 'lastname', 'email', 'phone', 'address', 'zipcode', 'city'
        ];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Het veld '{$field}' is verplicht.");
            }
        }

        // Save to Supabase
        $result = $client->from('offers')->insert([$offer->toArray()])->execute();

        if (!$result || (isset($result->error) && $result->error)) {
            $errorMsg = isset($result->error) ? $result->error->message : 'Failed to save offer to database';
            throw new \Exception($errorMsg);
        }

        // Send a simple confirmation email
        $message = (new \Swift_Message('Bevestiging van uw offerteaanvraag'))
            ->setFrom(['no-reply@evs-vloerverwarmingen.nl' => 'EVS Vloerverwarmingen'])
            ->setTo($data['email'])
            ->setBody(
                "Beste {$data['customer_name']},\n\n" .
                "Bedankt voor uw offerteaanvraag bij EVS Vloerverwarmingen.\n\n" .
                "We hebben uw aanvraag in goede orde ontvangen en zullen deze zo spoedig mogelijk verwerken. U ontvangt de definitieve offerte per e-mail nadat wij uw gegevens hebben bekeken.\n\n" .
                "Met vriendelijke groet,\nHet team van EVS Vloerverwarmingen",
                'text/plain'
            );

        if (!$mailer->send($message)) {
            throw new \Exception('Failed to send email');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Offerte aangemaakt en verzonden',
            'offer_id' => $result->data[0]['id']
        ]);

    } catch (\Exception $e) {
        http_response_code(500);
        error_log($e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Er is een fout opgetreden. Probeer het later opnieuw.'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
