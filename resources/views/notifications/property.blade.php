<!DOCTYPE html>
<html>

<head>
    <title>Property Data</title>
</head>

<body>
    <h1>New Property Upload</h1>

    <h2>Client Information</h2>
    <p><strong>Name:</strong> {{ $data['personalData']['name'] }}</p>
    <p><strong>Surname:</strong> {{ $data['personalData']['surname'] }}</p>
    <p><strong>Email:</strong> {{ $data['personalData']['email'] }}</p>
    <p><strong>Phone:</strong> {{ $data['personalData']['phone'] }}</p>

    <br />

    <h2>Property Information</h2>
    <p><strong>City:</strong> {{ $data['propertyData']['city'] }}</p>
    <p><strong>Address:</strong> {{ $data['propertyData']['address'] }}</p>
    <!-- <p><strong>Size:</strong> {{ $data['propertyData']['size'] }}</p>

    <p><strong>Rent:</strong> {{ $data['propertyData']['rent'] }}</p>
    <p><strong>Registration:</strong> {{ $data['propertyData']['registration'] }}</p>
    <p><strong>Period:</strong> {{ json_decode($data['propertyData']['period'], true)['en'] ?? 'N/A' }}</p>
    <p><strong>Bills:</strong> {{ json_decode($data['propertyData']['bills'], true)['en'] ?? 'N/A' }}</p>
    <p><strong>Flatmates:</strong> {{ json_decode($data['propertyData']['flatmates'], true)['en'] ?? 'N/A' }}</p>
    <p><strong>Description:</strong> {{ json_decode($data['propertyData']['description'], true)['en'] ?? 'N/A' }}</p> -->
<!-- 
    <br />

    <h2>Images</h2>
    <div>
        @foreach(explode(', ', $data['images']) as $image)
        <img src="{{ $image }}" alt="Property Image" style="max-width: 300px; margin: 20px;">
        @endforeach
    </div> -->
</body>

</html>