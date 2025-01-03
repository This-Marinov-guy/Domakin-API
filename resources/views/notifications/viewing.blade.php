<!DOCTYPE html>
<html>

<head>
    <title>Viewing Data</title>
</head>

<body>
    <h1>New Viewing from {{ $data['name'] }} in {{ $data['city'] }}</h1>
    <p>Name: {{ $data['name'] }}</p>
    <p>Surname: {{ $data['surname'] }}</p>
    <p>Phone: {{ $data['phone'] }}</p>
    <p>Email: {{ $data['email'] }}</p>
    <p>City: {{ $data['city'] }}</p>
    <p>Address: {{ $data['address'] }}</p>
    <p>Date: {{ \Carbon\Carbon::parse($data['date'])->format('Y-m-d') }}</p>
    <p>Time: {{ \Carbon\Carbon::parse($data['date'])->format('H:i:s') }}</p>
    <p>Note: {{ $data['note'] }}</p>
</body>

</html>