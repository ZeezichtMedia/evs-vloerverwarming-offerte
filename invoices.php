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

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $invoice_id = $_POST['invoice_id'];
    $status = $_POST['status'];
    $client->from('invoices')->update(['status' => $status])->eq('id', $invoice_id)->execute();
    header('Location: invoices.php');
    exit();
}

// Fetch all invoices
$invoicesResponse = $client->from('invoices')->select('*')->order('invoice_date', 'desc')->execute();
$invoices = $invoicesResponse->data;

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - EVS Vloerverwarmingen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">EVS Admin</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Offers</a></li>
                    <li class="nav-item"><a class="nav-link active" href="invoices.php">Invoices</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a href="logout.php" class="btn btn-danger">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>Invoices</h1>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr><td colspan="7" class="text-center">No invoices found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                                    <td><?= date('d-m-Y', strtotime($invoice['invoice_date'])) ?></td>
                                    <td><?= date('d-m-Y', strtotime($invoice['due_date'])) ?></td>
                                    <td>€<?= number_format($invoice['total_price'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'overdue' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($invoice['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="unpaid" <?= $invoice['status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                                <option value="paid" <?= $invoice['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                                <option value="overdue" <?= $invoice['status'] === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
