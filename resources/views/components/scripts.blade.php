<script>
    if (typeof window.exportLinkRedirectWithUrlParams !== 'function') {
        window.exportLinkRedirectWithUrlParams = function(e, options) {
            if (e && e.preventDefault) {
                e.preventDefault();
            }
            const type = typeof options === 'string' ? options : (options.type || 'excel');
            const name = options.name || 'export';
            const targetUrl = options.url || window.location.href;
            
            const updatedUrl = new URL(targetUrl, window.location.origin);
            updatedUrl.searchParams.set(name, type);
            window.location.href = updatedUrl.toString();
        };
    }
</script>
