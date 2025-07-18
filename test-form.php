<?php
// Simple test page for EVS form
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVS Vloerverwarming Test Form</title>
    <link rel="stylesheet" href="assets/css/evs-form.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .test-container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-header {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1>🔧 EVS Vloerverwarming Plugin Test</h1>
            <p>Testing the redesigned offerte form with all 7 required questions</p>
        </div>
        
        <div class="evs-form-container">
            <?php include 'templates/form-template.php'; ?>
        </div>
    </div>
    
    <script src="assets/js/evs-form.js"></script>
    <script>
        // Add test-specific JavaScript if needed
        console.log('EVS Form Test Page Loaded');
    </script>
</body>
</html>
