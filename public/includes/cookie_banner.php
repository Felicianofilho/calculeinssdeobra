<?php
/**
 * Banner de aviso de cookies - LGPD
 * Exibe na primeira visita e armazena consentimento em localStorage
 * Incluir antes do fechamento do </body>
 */
?>
<div id="cookie-banner" class="cookie-banner" role="dialog" aria-labelledby="cookie-banner-title" aria-describedby="cookie-banner-text" style="display: none;">
    <div class="cookie-banner-content">
        <div class="cookie-banner-text">
            <strong id="cookie-banner-title"><i class="fas fa-cookie-bite me-2"></i>Uso de cookies</strong>
            <p id="cookie-banner-text" class="mb-0 mt-1">
                Utilizamos cookies essenciais para o funcionamento do site (sessão, segurança e autenticação).
                Ao continuar navegando, você concorda com nosso uso. Consulte nossa
                <a href="cookies.php" target="_blank" rel="noopener">Política de Cookies</a> e
                <a href="privacidade.php" target="_blank" rel="noopener">Política de Privacidade</a>.
            </p>
        </div>
        <div class="cookie-banner-actions">
            <button type="button" id="cookie-banner-aceitar" class="btn btn-primary btn-sm">
                <i class="fas fa-check me-1"></i>Entendi
            </button>
        </div>
    </div>
</div>

<style>
.cookie-banner {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #ffffff;
    color: #1f2937;
    padding: 1rem 1.5rem;
    box-shadow: 0 -4px 24px rgba(0,0,0,0.12);
    border-top: 3px solid #2c5aa0;
    z-index: 9999;
    animation: slideUp 0.4s ease-out;
}
.cookie-banner-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.cookie-banner-text {
    flex: 1;
    min-width: 250px;
    font-size: 0.9rem;
}
.cookie-banner-text strong { color: #111827; }
.cookie-banner-text a {
    color: #2c5aa0;
    font-weight: 600;
    text-decoration: underline;
}
.cookie-banner-text a:hover {
    color: #1e3d72;
}
.cookie-banner-actions {
    flex-shrink: 0;
}
@keyframes slideUp {
    from { transform: translateY(100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
@media (max-width: 576px) {
    .cookie-banner-content { flex-direction: column; text-align: center; }
    .cookie-banner-actions { width: 100%; justify-content: center; }
}
</style>

<script>
(function() {
    var STORAGE_KEY = 'cookie_consent_accept';
    var banner = document.getElementById('cookie-banner');
    var btnAceitar = document.getElementById('cookie-banner-aceitar');

    function showBanner() {
        if (banner && !localStorage.getItem(STORAGE_KEY)) {
            banner.style.display = 'block';
        }
    }

    function hideBanner() {
        if (banner) {
            localStorage.setItem(STORAGE_KEY, 'true');
            banner.style.display = 'none';
        }
    }

    if (btnAceitar) {
        btnAceitar.addEventListener('click', hideBanner);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showBanner);
    } else {
        showBanner();
    }
})();
</script>
