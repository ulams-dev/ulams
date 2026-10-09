<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Signing in…</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; color: #1f2933; margin: 0; padding: 24px; }
    </style>
</head>
<body>
<main>
    <p role="status">Continuing to the activity…</p>
    <noscript><p><a href="{{ $url }}">Continue</a></p></noscript>
</main>
@include('lti::partials.storage-client')
<script id="lti-storage-config" type="application/json">@json($storage)</script>
<script>
    (function () {
        var config = JSON.parse(document.getElementById('lti-storage-config').textContent);
        var next = @json($url);
        // The platform keeps the nonce under the OIDC state; the launch page reads it back. Whatever
        // happens here the login continues: the server-side state decides.
        window.ltiStorage.put(config, config.key, config.value).then(function () {
            window.location.replace(next);
        });
    })();
</script>
</body>
</html>
