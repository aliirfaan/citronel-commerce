<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Order Unfulfilled</title>
    <style>
        body {
            font-family: 'Aptos', sans-serif;
            background-color: #F1F1F1;
            color: #55575D;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header img {
            max-width: 150px;
        }
        .content {
            text-align: left;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #55575D;
        }
        .order-details {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .order-details th, .order-details td {
            border: 1px solid #55575D;
            padding: 8px;
            text-align: left;
        }
        .order-details th {
            background-color: #f1f1f1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/app-logo.png') }}" alt="{{ config('app.name') }}">
        </div>
        <div class="content">
            <p>{{ $mailVars['order']->order_number }},</p>
        </div>
   
    </div>
</body>
</html>
