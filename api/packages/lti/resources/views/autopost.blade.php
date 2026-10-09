<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title ?? 'Continuing…' }}</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; color: #1f2933; margin: 0; padding: 24px; }
        button { font: inherit; padding: 8px 16px; }
    </style>
</head>
<body>
<form id="lti-autopost" method="post" action="{{ $action }}">
    @foreach ($fields as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <p role="status">{{ $message ?? 'Continuing to the activity…' }}</p>
    <noscript><button type="submit">Continue</button></noscript>
</form>
<script nonce="{{ $nonce ?? '' }}">document.getElementById('lti-autopost').submit();</script>
</body>
</html>
