<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/src/Models/Offer.php';

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

// Handle form submission for updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = $_POST;
    
    // Create a new Offer model and populate it from the POST data
    $offer = new EVS\Models\Offer($postData);
    $offer->calculatePrices(); // Recalculate prices based on new data
    
    // Update the record in Supabase
    $client->from('offers')->update($offer->toArray())->eq('id', $offer_id)->execute();
    
    // Redirect back to the dashboard
    header('Location: dashboard.php?status=updated');
    exit();
}

// Fetch the existing offer data
$response = $client->from('offers')->select('*')->eq('id', $offer_id)->execute();
if (empty($response->data)) {
    die('Offer not found.');
}
$offerData = $response->data[0];

?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Offer - EVS Vloerverwarmingen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5 mb-5">
        <h1>Edit Offer #<?= htmlspecialchars(substr($offer_id, 0, 8)) ?></h1>
        <p>Modify the details below and save to recalculate the prices and update the offer.</p>

        <form method="POST">
            <div class="row">
                <!-- Customer Details -->
                <div class="col-md-6 mb-3">
                    <label for="customer_name" class="form-label">Customer Name</label>
                    <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?= htmlspecialchars($offerData['customer_name']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="customer_email" class="form-label">Customer Email</label>
                    <input type="email" class="form-control" id="customer_email" name="customer_email" value="<?= htmlspecialchars($offerData['customer_email']) ?>" required>
                </div>

                <!-- Project Details -->
                <div class="col-md-4 mb-3">
                    <label for="area" class="form-label">Area (m²)</label>
                    <input type="number" step="0.01" class="form-control" id="area" name="area" value="<?= htmlspecialchars($offerData['area']) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="floor_level" class="form-label">Floor Level</label>
                    <select class="form-select" id="floor_level" name="floor_level">
                        <option value="begane_grond" <?= $offerData['floor_level'] == 'begane_grond' ? 'selected' : '' ?>>Begaande grond</option>
                        <option value="eerste_verdieping" <?= $offerData['floor_level'] == 'eerste_verdieping' ? 'selected' : '' ?>>Eerste verdieping</option>
                        <option value="zolder" <?= $offerData['floor_level'] == 'zolder' ? 'selected' : '' ?>>Zolder</option>
                        <option value="anders" <?= $offerData['floor_level'] == 'anders' ? 'selected' : '' ?>>Anders</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="floor_type" class="form-label">Floor Type</label>
                    <select class="form-select" id="floor_type" name="floor_type">
                        <option value="cement" <?= $offerData['floor_type'] == 'cement' ? 'selected' : '' ?>>Cement dekvloer</option>
                        <option value="tile" <?= $offerData['floor_type'] == 'tile' ? 'selected' : '' ?>>Tegelvloer</option>
                        <option value="concrete" <?= $offerData['floor_type'] == 'concrete' ? 'selected' : '' ?>>Betonvloer</option>
                        <option value="fermacel" <?= $offerData['floor_type'] == 'fermacel' ? 'selected' : '' ?>>Fermacelvloer</option>
                    </select>
                </div>

                <!-- Technical Details -->
                <div class="col-md-4 mb-3">
                    <label for="heat_source" class="form-label">Heat Source</label>
                    <select class="form-select" id="heat_source" name="heat_source">
                        <option value="cv_ketel" <?= $offerData['heat_source'] == 'cv_ketel' ? 'selected' : '' ?>>CV ketel</option>
                        <option value="hybride_warmtepomp" <?= $offerData['heat_source'] == 'hybride_warmtepomp' ? 'selected' : '' ?>>Hybride warmtepomp</option>
                        <option value="volledige_warmtepomp" <?= $offerData['heat_source'] == 'volledige_warmtepomp' ? 'selected' : '' ?>>Volledige warmtepomp</option>
                        <option value="stadsverwarming" <?= $offerData['heat_source'] == 'stadsverwarming' ? 'selected' : '' ?>>Stadsverwarming</option>
                        <option value="toekomstige_warmtepomp" <?= $offerData['heat_source'] == 'toekomstige_warmtepomp' ? 'selected' : '' ?>>Toekomstige warmtepomp</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Connect Distributor?</label>
                    <select class="form-select" name="distributor">
                        <option value="yes" <?= $offerData['distributor'] == 'yes' ? 'selected' : '' ?>>Yes</option>
                        <option value="no" <?= $offerData['distributor'] == 'no' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Seal Floor?</label>
                    <select class="form-select" name="sealing">
                        <option value="yes" <?= $offerData['sealing'] == 'yes' ? 'selected' : '' ?>>Yes</option>
                        <option value="no" <?= $offerData['sealing'] == 'no' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between">
                <a href="send-quote.php?id=<?= $offer_id ?>" class="btn btn-success">Send Final Quote</a>
                <div>
                    <a href="dashboard.php" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</body>
</html>
