<script>
(function () {
    if (window.__imgZoomInit) return;
    window.__imgZoomInit = true;

    // Fullscreen overlay (built once, reused)
    var overlay = document.createElement('div');
    overlay.style.cssText =
        'position:fixed;inset:0;background:rgba(0,0,0,.92);display:none;' +
        'align-items:center;justify-content:center;z-index:999999;cursor:zoom-out;';
    var bigImg = document.createElement('img');
    bigImg.style.cssText = 'max-width:95vw;max-height:95vh;object-fit:contain;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.6);';
    overlay.appendChild(bigImg);

    function close() { overlay.style.display = 'none'; bigImg.removeAttribute('src'); }
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    function mount() { if (document.body && !overlay.parentNode) document.body.appendChild(overlay); }
    mount();
    document.addEventListener('DOMContentLoaded', mount);

    // Capture-phase delegation so it fires BEFORE any row/edit click handler.
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-zoom-src]');
        if (!el) return;
        e.preventDefault();
        e.stopPropagation();
        mount();
        bigImg.src = el.getAttribute('data-zoom-src');
        overlay.style.display = 'flex';
    }, true);
})();
</script>
