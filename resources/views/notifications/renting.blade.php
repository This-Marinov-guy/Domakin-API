<!DOCTYPE html>
<html>

<head>
    <title>Renting Data</title>
</head>

<body>
    <h1>New Renting from {{ $data['name'] }} for {{ $data['property'] }}</h1>
    <p>Property Address: {{ $data['address'] }}</p>
    <p>Name: {{ $data['name'] }}</p>
    <p>Surname: {{ $data['surname'] }}</p>
    <p>Phone: {{ $data['phone'] }}</p>
    <p>Email: {{ $data['email'] }}</p>
    <p>Motivational Letter: {{ $data['letter'] }}</p>
    <p>Note: {{ $data['note'] }}</p>
</body>

</html>