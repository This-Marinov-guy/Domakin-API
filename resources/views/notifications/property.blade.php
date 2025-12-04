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
    <p><strong>Postcode:</strong> {{ $data['propertyData']['postcode'] ?? 'N/A' }}</p>
    <p><strong>Pets Allowed:</strong> {{ isset($data['propertyData']['petsAllowed']) ? ($data['propertyData']['petsAllowed'] ? 'Yes' : 'No') : (isset($data['propertyData']['pets_allowed']) ? ($data['propertyData']['pets_allowed'] ? 'Yes' : 'No') : 'N/A') }}</p>
    <p><strong>Smoking Allowed:</strong> {{ isset($data['propertyData']['smokingAllowed']) ? ($data['propertyData']['smokingAllowed'] ? 'Yes' : 'No') : (isset($data['propertyData']['smoking_allowed']) ? ($data['propertyData']['smoking_allowed'] ? 'Yes' : 'No') : 'N/A') }}</p>
</body>

</html>