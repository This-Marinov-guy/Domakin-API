<!DOCTYPE html>
<html>

<head>
    <title>Agents Sync</title>
</head>

<body style="font-family: Arial, sans-serif; color: #111827;">
    <span style="display:inline-block;background-color:#4f46e5;color:#ffffff;font-size:11px;font-weight:600;letter-spacing:.05em;padding:3px 10px;border-radius:12px;font-family:sans-serif;">Automation</span>

    <h1>Agents sheet sync completed</h1>

    <p><strong>Sheet:</strong> {{ $data['sheet_title'] ?? 'N/A' }}</p>
    <p><strong>Rows seen:</strong> {{ $data['rows_seen'] ?? 0 }}</p>
    <p><strong>Added:</strong> {{ $data['created'] ?? 0 }}</p>
    <p><strong>Updated:</strong> {{ $data['updated'] ?? 0 }}</p>
    <p><strong>Skipped:</strong> {{ $data['skipped'] ?? 0 }}</p>

    @if(!empty($data['added_agents']))
        <h2>New agents added</h2>

        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #e5e7eb; font-size: 14px;">
            <thead>
                <tr style="background-color: #f9fafb;">
                    <th align="left">Row</th>
                    <th align="left">Name</th>
                    <th align="left">Email</th>
                    <th align="left">Phone</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['added_agents'] as $agent)
                    <tr>
                        <td>{{ $agent['sheet_row_number'] ?? 'N/A' }}</td>
                        <td>{{ $agent['name'] ?? 'N/A' }}</td>
                        <td>{{ $agent['email'] ?? 'N/A' }}</td>
                        <td>{{ $agent['phone'] ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <h2>No new agents added</h2>
        <p>The sync ran successfully, but no new agents were inserted into the database.</p>
    @endif
</body>

</html>
