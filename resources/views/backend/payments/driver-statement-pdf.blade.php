<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $driverName }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { margin-bottom: 12px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; font-size: 9px; }
        .total { margin-top: 10px; font-size: 12px; }
    </style>
</head>
<body>
    <h1>{{ $driverName }} — {{ $title }}</h1>
    <div class="meta">Generated {{ $generatedAt }}</div>
    <table>
        <thead>
        <tr>
            @foreach($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                @foreach($row as $cell)
                    <td>{{ $cell !== '' ? $cell : '—' }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($headers) }}">No records.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
    <p class="total"><strong>{{ $totalLabel }}: {{ $totalValue }}</strong></p>
</body>
</html>
