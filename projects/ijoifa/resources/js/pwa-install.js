const DISMISS_KEY = 'ijoifa-pwa-install-dismissed';

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function isIos() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}

function init(bannerEl) {
    if (isStandalone() || localStorage.getItem(DISMISS_KEY)) {
        return;
    }

    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        showBanner('android');
    });

    if (isIos()) {
        showBanner('ios');
    }

    function showBanner(platform) {
        bannerEl.classList.remove('hidden');
        bannerEl.dataset.platform = platform;
        bannerEl.querySelectorAll('[data-platform-content]').forEach((el) => {
            el.classList.toggle('hidden', el.dataset.platformContent !== platform);
        });
        bannerEl.querySelectorAll('[data-platform-show]').forEach((el) => {
            el.classList.toggle('hidden', el.dataset.platformShow !== platform);
        });
    }

    bannerEl.querySelector('[data-action="install"]')?.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
        }
        bannerEl.classList.add('hidden');
    });

    bannerEl.querySelector('[data-action="dismiss"]')?.addEventListener('click', () => {
        localStorage.setItem(DISMISS_KEY, '1');
        bannerEl.classList.add('hidden');
    });
}

window.IjoifaPwaInstall = { init };
