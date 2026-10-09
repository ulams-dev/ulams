<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; color: #1f2933; margin: 0; padding: 24px; max-width: 40rem; }
        h1 { font-size: 1.25rem; }
    </style>
</head>
<body>
<main>
    <h1>{{ $title }}</h1>
    <p role="{{ ($error ?? false) ? 'alert' : 'status' }}">{{ $message }}</p>
</main>
@if (!empty($postMessage))
<script>
    try { (window.opener || window.parent).postMessage(@json($postMessage), '*'); } catch (e) {}
</script>
@endif
</body>
</html>
