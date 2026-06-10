<!DOCTYPE html>
<html>

<head>
    <title>Viewing Data</title>
</head>

<body>
    <span style="display:inline-block;background-color:#4f46e5;color:#ffffff;font-size:11px;font-weight:600;letter-spacing:.05em;padding:3px 10px;border-radius:12px;font-family:sans-serif;">Automation</span>
    <h1>New Viewing from {{ $data['name'] }} in {{ $data['city'] }}</h1>
    <p>Name: {{ $data['name'] }}</p>
    <p>Surname: {{ $data['surname'] }}</p>
    <p>Phone: {{ $data['phone'] }}</p>
    <p>Email: {{ $data['email'] }}</p>
    <p>City: {{ $data['city'] }}</p>
    <p>Address: {{ $data['address'] }}</p>
    <p>Date: {{ $data['date'] }}</p>
    <p>Time: {{ $data['time'] }}</p>
    <p>Note: {{ $data['note'] ?? '' }}</p>
</body>

</html>