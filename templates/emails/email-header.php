<?php
/**
 * Email Header
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($email_heading); ?></title>
    <style type="text/css">
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .email-wrapper {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .email-header {
            background-color: #E53E3E;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .email-body {
            padding: 30px;
            line-height: 1.6;
        }
        .email-footer {
            background-color: #f4f4f4;
            color: #888;
            padding: 20px;
            text-align: center;
            font-size: 12px;
        }
        .quote-details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .quote-details-table th, .quote-details-table td {
            border: 1px solid #e0e0e0;
            padding: 12px;
            text-align: left;
        }
        .quote-details-table th {
            background-color: #f9f9f9;
            font-weight: 600;
            width: 40%;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <h1><?php echo esc_html($email_heading); ?></h1>
        </div>
        <div class="email-body">
