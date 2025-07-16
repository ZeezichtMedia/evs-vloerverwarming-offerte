<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/Config.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$config = EVS\Config\Config::getInstance();
$client = new \Supabase\Client($config->get('SUPABASE_URL'), $config->get('SUPABASE_KEY'));

// Handle invoice creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $offer_id = $_POST['offer_id'];
    $invoice_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];
    $drilling_price = (float)$_POST['drilling_price'];
    $sealing_price = (float)$_POST['sealing_price'];
    $notes = $_POST['notes'];

    // Fetch offer details to get customer info
    $offerResponse = $client->from('offers')->select('customer_name, customer_email')->eq('id', $offer_id)->execute();
    $offerData = $offerResponse->data[0];

    // Generate a new invoice number
    $lastInvoiceResponse = $client->from('invoices')->select('invoice_number')->order('created_at', 'desc')->limit(1)->execute();
    $lastNumber = 0;
    if (!empty($lastInvoiceResponse->data)) {
        $lastNumber = (int)substr($lastInvoiceResponse->data[0]['invoice_number'], -4);
    }
    $newInvoiceNumber = 'INV-' . date('Y') . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

    $invoiceData = [
        'offer_id' => $offer_id,
        'invoice_number' => $newInvoiceNumber,
        'customer_name' => $offerData['customer_name'],
        'customer_email' => $offerData['customer_email'],
        'drilling_price' => $drilling_price,
        'sealing_price' => $sealing_price,
        'total_price' => $drilling_price + $sealing_price,
        'invoice_date' => $invoice_date,
        'due_date' => $due_date,
        'notes' => $notes,
        'status' => 'unpaid'
    ];

    $client->from('invoices')->insert([$invoiceData])->execute();
    $client->from('offers')->update(['status' => 'invoiced'])->eq('id', $offer_id)->execute();

    header('Location: dashboard.php?status=invoiced');
    exit();
}

// Fetch completed or approved offers to populate the dropdown
$offersResponse = $client->from('offers')->select('*')->in('status', ['approved', 'completed'])->execute();
$offers = $offersResponse->data;

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice - EVS Vloerverwarmingen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5 mb-5">
    <h1>Create Invoice</h1>
    <p>Select an approved offer to generate an invoice. You can make final adjustments to the prices below.</p>

    <form method="POST" id="invoice-form">
        <div class="mb-3">
            <label for="offer_id" class="form-label">Select Offer</label>
            <select class="form-select" id="offer_id" name="offer_id" required>
                <option value="" selected disabled>-- Choose an offer --</option>
                <?php foreach ($offers as $offer): ?>
                    <option value="<?= $offer['id'] ?>" 
                            data-drilling="<?= $offer['drilling_price'] ?>" 
                            data-sealing="<?= $offer['sealing_price'] ?>">
                        <?= htmlspecialchars($offer['customer_name']) ?> - Offer #<?= substr($offer['id'], 0, 8) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="drilling_price" class="form-label">Drilling Price (€)</label>
                <input type="number" step="0.01" class="form-control" id="drilling_price" name="drilling_price" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="sealing_price" class="form-label">Sealing Price (€)</label>
                <input type="number" step="0.01" class="form-control" id="sealing_price" name="sealing_price" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="invoice_date" class="form-label">Invoice Date</label>
                <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="due_date" class="form-label">Due Date</label>
                <input type="date" class="form-control" id="due_date" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Notes (Optional)</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
        </div>

        <hr class="my-4">

        <div class="d-flex justify-content-end">
            <a href="dashboard.php" class="btn btn-secondary me-2">Cancel</a>
            <button type="submit" class="btn btn-primary">Create and Save Invoice</button>
        </div>
    </form>
</div>

<script>
    document.getElementById('offer_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        document.getElementById('drilling_price').value = selectedOption.getAttribute('data-drilling');
        document.getElementById('sealing_price').value = selectedOption.getAttribute('data-sealing');
    });
</script>

</body>
</html>
