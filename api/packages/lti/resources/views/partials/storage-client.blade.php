{{-- LTI Client Side postMessage Storage, tool side. Messages go to the platform's storage frame and
     only answers from that frame and the platform's origin are accepted. Nothing here is trusted by
     the server: the launch is checked against the server-side state either way. --}}
<script>
    (function () {
        var TIMEOUT = 2000;

        function storageFrame(name) {
            try {
                if (window.parent === window) { return null; }
                if (name === '_parent') { return window.parent; }
                return window.parent.frames[name] || null;
            } catch (e) {
                return null;
            }
        }

        // one request, one answer: resolves with the reply, rejects on error replies and timeouts
        function request(frame, origin, message) {
            return new Promise(function (resolve, reject) {
                var id = 'ulams-' + Math.random().toString(36).slice(2) + '-' + Date.now();
                message.message_id = id;
                var timer = setTimeout(function () { done(); reject(new Error('timeout')); }, TIMEOUT);
                function done() {
                    clearTimeout(timer);
                    window.removeEventListener('message', onMessage);
                }
                function onMessage(event) {
                    var data = event.data;
                    if (event.source !== frame || event.origin !== origin || !data || data.message_id !== id) { return; }
                    done();
                    if (data.error) { reject(new Error(data.error.code || 'error')); } else { resolve(data); }
                }
                window.addEventListener('message', onMessage);
                try { frame.postMessage(message, origin); } catch (e) { done(); reject(e); }
            });
        }

        // the frame that takes the message: the one named by lti_storage_target, or the frame the
        // platform names for the message in its lti.capabilities answer
        function frameFor(config, subject) {
            var base = storageFrame(config.target);
            if (!base) { return Promise.resolve(null); }
            return request(base, config.origin, { subject: 'lti.capabilities' }).then(function (reply) {
                var supported = reply.supported_messages || [];
                for (var i = 0; i < supported.length; i++) {
                    if (String(supported[i].subject || '').replace(/^org\.imsglobal\./, '') === subject) {
                        return supported[i].frame ? storageFrame(supported[i].frame) : base;
                    }
                }
                return null;
            }, function () {
                // no answer to capabilities: try the target anyway, the request below times out if it is deaf
                return base;
            });
        }

        window.ltiStorage = {
            put: function (config, key, value) {
                return frameFor(config, 'lti.put_data').then(function (frame) {
                    if (!frame) { return false; }
                    return request(frame, config.origin, { subject: 'lti.put_data', key: key, value: value }).then(function () { return true; });
                }).catch(function () { return false; });
            },
            get: function (config, key) {
                return frameFor(config, 'lti.get_data').then(function (frame) {
                    if (!frame) { return null; }
                    return request(frame, config.origin, { subject: 'lti.get_data', key: key }).then(function (reply) {
                        return typeof reply.value === 'string' ? reply.value : null;
                    });
                }).catch(function () { return null; });
            }
        };
    })();
</script>
