<?php
/**
 * Modal "Comunicado Importante" - migração de plataforma.
 * Exibido uma vez por sessão, após o login, até a data limite.
 * Requer Bootstrap 5 (JS bundle) carregado antes do include.
 */
$comunicadoLimite = new DateTime('2026-11-10 23:59:59', new DateTimeZone('America/Sao_Paulo'));
$agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

if ($agora <= $comunicadoLimite && empty($_SESSION['comunicado_migracao_visto'])):
    $_SESSION['comunicado_migracao_visto'] = true;

    if (!class_exists('LocalConfig')) {
        require_once __DIR__ . '/../../src/Utils/LocalConfig.php';
    }
    $cfgCom = LocalConfig::getAll();
    $whatsUrl = $cfgCom['company']['social_media']['whatsapp'] ?? 'https://wa.me/558534940140';
    $whatsUrl .= (strpos($whatsUrl, '?') === false ? '?' : '&')
        . 'text=' . rawurlencode('Olá! Já utilizo a plataforma de Cálculo de INSS de Obras e gostaria de orientações sobre a migração para o novo sistema.');
?>
<style>
    #comunicadoMigracao .modal-content { border: 0; border-radius: 18px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,.35); }
    #comunicadoMigracao .com-header { background: linear-gradient(135deg, #0f2c59 0%, #1f5fa8 100%); color: #fff; padding: 1.6rem 1.5rem 1.3rem; text-align: center; position: relative; }
    #comunicadoMigracao .com-icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(255,255,255,.15); display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: .6rem; animation: comPulse 2s infinite; }
    #comunicadoMigracao .com-header h2 { font-size: 1.25rem; font-weight: 800; letter-spacing: .08em; margin: 0; }
    #comunicadoMigracao .btn-close { position: absolute; top: 1rem; right: 1rem; }
    #comunicadoMigracao .modal-body { padding: 1.6rem 1.75rem .5rem; font-size: 1.02rem; line-height: 1.6; color: #333; }
    #comunicadoMigracao .com-destaque { font-weight: 600; color: #0f2c59; }
    #comunicadoMigracao .com-assinatura { margin-top: 1.1rem; font-weight: 700; color: #0f2c59; }
    #comunicadoMigracao .com-prazo { display: inline-block; margin-top: .4rem; background: #fff4e5; color: #b45309; border: 1px solid #fcd9a8; border-radius: 999px; padding: .3rem .9rem; font-size: .9rem; font-weight: 600; }
    #comunicadoMigracao .modal-footer { border: 0; padding: 1rem 1.75rem 1.6rem; gap: .5rem; }
    #comunicadoMigracao .btn-whats { background: #25d366; border: 0; color: #fff; font-weight: 700; border-radius: 10px; padding: .7rem 1.2rem; transition: transform .15s, box-shadow .15s; }
    #comunicadoMigracao .btn-whats:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37,211,102,.4); background: #1ebe5b; color: #fff; }
    @keyframes comPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(255,255,255,.35); } 50% { box-shadow: 0 0 0 12px rgba(255,255,255,0); } }
</style>

<div class="modal fade" id="comunicadoMigracao" tabindex="-1" aria-labelledby="comunicadoMigracaoTitulo" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="com-header">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                <div class="com-icon"><i class="fas fa-bullhorn"></i></div>
                <h2 id="comunicadoMigracaoTitulo">COMUNICADO IMPORTANTE</h2>
            </div>
            <div class="modal-body">
                <p class="com-destaque">Nossa plataforma de Cálculo de INSS de Obras está migrando para um novo sistema.</p>
                <p>Se você já utilizava nossos serviços, entre em contato pelo WhatsApp para receber orientações e continuar utilizando a plataforma.</p>
                <p class="mb-0">Agradecemos pela confiança!</p>
                <div class="com-assinatura">Equipe Hevcon Assessoria Contábil</div>
                <span class="com-prazo"><i class="far fa-calendar-alt me-1"></i>Data limite: 10/11/2026</span>
            </div>
            <div class="modal-footer justify-content-center">
                <a id="comunicadoWhatsBtn" href="<?php echo htmlspecialchars($whatsUrl); ?>" target="_blank" rel="noopener" class="btn btn-whats">
                    <i class="fab fa-whatsapp me-2"></i>Falar no WhatsApp
                </a>
                <button id="comunicadoFecharBtn" type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Entendi</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('comunicadoMigracao');
    if (el && window.bootstrap) { new bootstrap.Modal(el).show(); }
});
</script>
<?php endif; ?>
