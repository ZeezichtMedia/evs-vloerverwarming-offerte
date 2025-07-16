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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $offerId = $_POST['offer_id'];
    $status = $_POST['status'];
    $client->from('offers')->update(['status' => $status])->eq('id', $offerId)->execute();
    header('Location: dashboard.php');
    exit();
}

// Fetch all offers
$offersResponse = $client->from('offers')->select('*')->order('created_at', 'desc')->execute();
$offers = $offersResponse->data;

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offers Dashboard - EVS Vloerverwarmingen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">EVS Admin</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Offers</a></li>
                    <li class="nav-item"><a class="nav-link" href="invoices.php">Invoices</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a href="logout.php" class="btn btn-danger">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>Offers</h1>
            <a href="create-invoice.php" class="btn btn-success">Create Invoice</a>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Floor Type</th>
                            <th>Area</th>
                            <th>Drilling Price</th>
                            <th>Sealing Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($offers)): ?>
                            <tr><td colspan="7" class="text-center">No offers found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($offers as $offer): ?>
                                <tr>
                                    <td><?= htmlspecialchars($offer['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($offer['floor_type']) ?></td>
                                    <td><?= $offer['area'] ?>m²</td>
                                    <td>€<?= number_format($offer['drilling_price'], 2, ',', '.') ?></td>
                                    <td>€<?= number_format($offer['sealing_price'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= ucfirst($offer['status']) ?></span>
                                    </td>
                                    <td>
                                        <a href="edit-offer.php?id=<?= $offer['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="post" class="d-inline-block ms-1">
                                            <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                            <input type="hidden" name="action" value="update">
                                            <select name="status" onchange="this.form.submit()" class="form-select form-select-sm d-inline-block w-auto">
                                                <option value="new" <?= $offer['status'] === 'new' ? 'selected' : '' ?>>New</option>
                                                <option value="sent" <?= $offer['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
                                                <option value="approved" <?= $offer['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                <option value="completed" <?= $offer['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="invoiced" <?= $offer['status'] === 'invoiced' ? 'selected' : '' ?>>Invoiced</option>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
