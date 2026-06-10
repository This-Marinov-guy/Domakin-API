<!DOCTYPE html>
<html>

<head>
    <title>Renting Data</title>
</head>

<body>
    <span style="display:inline-block;background-color:#4f46e5;color:#ffffff;font-size:11px;font-weight:600;letter-spacing:.05em;padding:3px 10px;border-radius:12px;font-family:sans-serif;">Automation</span>
    <h1>New Searching for Renting from {{ $data['name'] }} in {{ $data['city'] }}</h1>
    <p>Name: {{ $data['name'] }}</p>
    <p>Surname: {{ $data['surname'] }}</p>
    <p>Phone: {{ $data['phone'] }}</p>
    <p>Email: {{ $data['email'] }}</p>
    <p>City: {{ $data['city'] }}</p>
    <p>Budget: {{ $data['budget'] }}</p>
    <p>Move in Date: {{ $data['move_in'] }}</p>
    <p>People: {{ $data['people'] }}</p>
    <p>Registration need: {{ $data['registration'] }}</p>
    <p>Motivational Letter: {{ $data['letter'] }}</p>
    <p>Note: {{ $data['note'] }}</p>
</body>

</html>