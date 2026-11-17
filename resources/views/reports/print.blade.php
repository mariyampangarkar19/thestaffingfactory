<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $report->title() }} · {{ $agency }}</title>
    <style>
        @font-face { font-family: Roboto; font-weight: 400; src: url('/fonts/roboto/roboto-latin-400-normal.woff2') format('woff2'); }
        @font-face { font-family: Roboto; font-weight: 500; src: url('/fonts/roboto/roboto-latin-500-normal.woff2') format('woff2'); }
        body { font-family: Roboto, Arial, sans-serif; color: #171d1e; margin: 2rem; font-size: 12px; }
        header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #1c97a6; padding-bottom: 0.75rem; margin-bottom: 1rem; }
        h1 { font-size: 20px; font-weight: 500; margin: 0; color: #0b2545; }
        .meta { color: #3f484a; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-weight: 500; background: #eef2f4; color: #0b2545; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #c4cdd0; vertical-align: top; }
        .hint { margin: 0 0 1rem; padding: 0.75rem 1rem; background: #d3eef2; color: #00363e; border-radius: 8px; }
        .empty { padding: 2rem; text-align: center; color: #3f484a; }
        @media print { .hint { display: none; } body { margin: 0; } thead { display: table-header-group; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
    <p class="hint">{{ __('To save as PDF, print this page (Ctrl + P) and choose "Save as PDF".') }}</p>
    <header>
        <div>
            <h1>{{ $report->title() }}</h1>
            <div class="meta">{{ $agency }}</div>
        </div>
        <div class="meta">{{ $from->translatedFormat('j M Y') }} – {{ $to->translatedFormat('j M Y') }}</div>
    </header>
    <table>
        <thead>
            <tr>@foreach ($report->headings() as $heading)<th>{{ $heading }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @php $count = 0; @endphp
            @foreach ($rows as $row)
                @php $count++; @endphp
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
    @if ($count === 0)
        <p class="empty">{{ __('Nothing to report for this period.') }}</p>
    @endif
</body>
</html>
