<!DOCTYPE html>
<html>

<head>
    <title>Career Application</title>
</head>

<body>
    <h1>New Career Application from {{ $data['name'] }}</h1>
    <p><strong>Name:</strong> {{ $data['name'] }}</p>
    <p><strong>Email:</strong> {{ $data['email'] }}</p>
    <p><strong>Phone:</strong> {{ $data['phone'] }}</p>
    <p><strong>Position:</strong> {{ $data['position'] }}</p>
    <p><strong>Location:</strong> {{ $data['location'] }}</p>
    
    @if(isset($data['experience']) && $data['experience'])
    <p><strong>Experience:</strong></p>
    <p>{{ $data['experience'] }}</p>
    @endif
    
    @if(isset($data['message']) && $data['message'])
    <p><strong>Message:</strong></p>
    <p>{{ $data['message'] }}</p>
    @endif
    
    @if(isset($data['resume']) && $data['resume'])
    <p><strong>Resume:</strong> <a href="{{ $data['resume'] }}">Download Resume</a></p>
    @endif
</body>

</html>

