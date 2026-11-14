<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Progressive web app --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#1d4ed8">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<script>
    // Register the service worker (browsers only allow this on https or localhost) and keep the
    // install prompt so an "Install app" button can trigger it.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
    }
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.__installPrompt = event;
        window.dispatchEvent(new CustomEvent('pwa-installable'));
    });
    window.addEventListener('appinstalled', () => {
        window.__installPrompt = null;
        window.dispatchEvent(new CustomEvent('pwa-installed'));
    });
    window.installPwa = async () => {
        if (!window.__installPrompt) return;
        window.__installPrompt.prompt();
        await window.__installPrompt.userChoice;
        window.__installPrompt = null;
        window.dispatchEvent(new CustomEvent('pwa-installed'));
    };
</script>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
