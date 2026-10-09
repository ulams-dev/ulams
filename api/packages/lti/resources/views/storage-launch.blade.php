<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Opening the activity…</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; color: #1f2933; margin: 0; padding: 24px; }
        button { font: inherit; padding: 8px 16px; }
    </style>
</head>
<body>
<form id="lti-verify" method="post" action="{{ $action }}">
    <input type="hidden" name="id_token" value="{{ $idToken }}">
    <input type="hidden" name="state" value="{{ $state }}">
    <input type="hidden" name="stored" id="lti-stored" value="">
    <p role="status">Opening the activity…</p>
    <noscript><button type="submit">Continue</button></noscript>
</form>
@include('lti::partials.storage-client')
<script id="lti-storage-config" type="application/json">@json($storage)</script>
<script>
    (function () {
        var config = JSON.parse(document.getElementById('lti-storage-config').textContent);
        // The value the login page put in the platform's storage. Unavailable storage sends an empty
        // value and the server falls back to its own state; a wrong value is refused there.
        window.ltiStorage.get(config, config.key).then(function (value) {
            document.getElementById('lti-stored').value = value || '';
            document.getElementById('lti-verify').submit();
        });
    })();
</script>
</body>
</html>
