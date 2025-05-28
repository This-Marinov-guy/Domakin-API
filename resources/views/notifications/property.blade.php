<!DOCTYPE html>
<html>

<head>
    <title>Property Data</title>
</head>

<body>
    <h1>New Property Upload</h1>

    <h2>Client Information</h2>
    <p><strong>Name:</strong> {{ $data['personalData']['name'] ?? 'N/A' }}</p>
    <p><strong>Surname:</strong> {{ $data['personalData']['surname'] ?? 'N/A' }}</p>
    <p><strong>Email:</strong> {{ $data['personalData']['email'] ?? 'N/A' }}</p>
    <p><strong>Phone:</strong> {{ $data['personalData']['phone'] ?? 'N/A' }}</p>

    <br />

    <h2>Property Information</h2>
    <p><strong>City:</strong> {{ $data['propertyData']['city'] ?? 'N/A' }}</p>
    <p><strong>Address:</strong> {{ $data['propertyData']['address'] ?? 'N/A' }}</p>
</body>

</html>