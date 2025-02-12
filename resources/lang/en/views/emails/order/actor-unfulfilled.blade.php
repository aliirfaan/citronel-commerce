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
            <p>Dear {{ $mailVars['actor']['full_name'] }},</p>
            <p>Your order is being processed. You&apos;ll be notified by mail once your order is complete.</p>
            <p>Order details:</p>
            @foreach($mailVars['items'] as $index => $item)
            <table class="order-details">
                <tr>
                    <th>Type</th>
                    <td>{{ $item->order_item->product->title }}</td>
                </tr>
                <tr>
                    <th>Payment reference</th>
                    <td>{{ $mailVars['payment']->gateway_merchant_transaction_no }}</td>
                </tr>
                <tr>
                    <th>Paid at</th>
                    <td>{{ $mailVars['payment']->paid_at }} UTC+00:00</td>
                </tr>
                <tr>
                    <th>Paid by</th>
                    <td>{{ $mailVars['payment']->title }}</td>
                </tr>
            </table>
            @break
            @endforeach
            <p>For further assistance, feel free to contact us.</p>
        </div>
        <div class="footer">
            <p>If you didn't request this order, you don't have to do anything. So that's easy.</p>
        </div>
    </div>
</body>
</html>
