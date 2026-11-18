import './bootstrap';

function stripDiacritics(text) {
    return (text || '')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();
}

window.oduMatch = function (query, text) {
    if (!query) return true;
    return stripDiacritics(text).includes(stripDiacritics(query));
};

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
