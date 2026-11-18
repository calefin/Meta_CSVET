<style>
    #app-upload-progress-bar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        z-index: 9999;
        background: rgba(13, 148, 136, 0.15);
        display: none;
    }
    #app-upload-progress-bar .bar {
        height: 100%;
        background: #0d9488;
        width: 0%;
        transition: width 0.15s ease;
    }
    #app-upload-toast {
        position: fixed;
        bottom: 1rem;
        left: 50%;
        transform: translateX(-50%);
        background: #0d9488;
        color: #fff;
        padding: 0.55rem 1.1rem;
        border-radius: 0.6rem;
        font-size: 0.8125rem;
        font-weight: 600;
        z-index: 9999;
        display: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        transition: background-color 0.2s ease;
    }

    /* Overlay exibido ao SUBMETER o formulário (clicar em Enviar/Salvar), cobrindo
       a espera do processamento no backend (Google Drive, auditoria IA, e-mail),
       que é onde o usuário fica sem feedback e clica várias vezes no botão. */
    #app-submit-overlay {
        position: fixed;
        inset: 0;
        z-index: 10000;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(2px);
        display: none;
        align-items: center;
        justify-content: center;
    }
    #app-submit-overlay .card {
        background: #fff;
        border-radius: 1rem;
        padding: 1.75rem 2.25rem;
        box-shadow: 0 20px 40px -8px rgba(0, 0, 0, 0.35);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.85rem;
        max-width: 90vw;
        text-align: center;
    }
    .dark #app-submit-overlay .card {
        background: #1e293b;
        color: #f1f5f9;
    }
    #app-submit-overlay .spinner {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 9999px;
        border: 3px solid rgba(13, 148, 136, 0.2);
        border-top-color: #0d9488;
        animation: app-submit-spin 0.8s linear infinite;
    }
    #app-submit-overlay .label {
        font-size: 0.9375rem;
        font-weight: 600;
    }
    #app-submit-overlay .hint {
        font-size: 0.8125rem;
        opacity: 0.75;
    }
    @keyframes app-submit-spin {
        to { transform: rotate(360deg); }
    }
</style>
<div id="app-upload-progress-bar"><div class="bar"></div></div>
<div id="app-upload-toast">Enviando arquivo...</div>
<div id="app-submit-overlay">
    <div class="card">
        <div class="spinner"></div>
        <div class="label">Enviando, aguarde...</div>
        <div class="hint">Isso pode levar alguns segundos. Não feche esta página nem clique novamente.</div>
    </div>
</div>
<script>
    document.addEventListener('livewire:init', () => {
        const bar = document.getElementById('app-upload-progress-bar');
        const barInner = bar?.querySelector('.bar');
        const toast = document.getElementById('app-upload-toast');
        if (!bar || !barInner || !toast) {
            return;
        }

        let hideTimeout = null;

        window.addEventListener('livewire:upload-start', () => {
            clearTimeout(hideTimeout);
            toast.style.background = '#0d9488';
            barInner.style.width = '0%';
            bar.style.display = 'block';
            toast.style.display = 'block';
            toast.textContent = 'Enviando arquivo... aguarde, isso pode levar alguns segundos.';
        });

        window.addEventListener('livewire:upload-progress', (event) => {
            const progress = event.detail?.progress ?? 0;
            barInner.style.width = progress + '%';
            toast.textContent = 'Enviando arquivo... ' + progress + '%';
        });

        window.addEventListener('livewire:upload-finish', () => {
            barInner.style.width = '100%';
            toast.textContent = 'Arquivo enviado com sucesso!';
            hideTimeout = setTimeout(() => {
                bar.style.display = 'none';
                toast.style.display = 'none';
                barInner.style.width = '0%';
            }, 900);
        });

        window.addEventListener('livewire:upload-error', () => {
            toast.style.background = '#dc2626';
            toast.textContent = 'Erro ao enviar o arquivo. Tente novamente.';
            hideTimeout = setTimeout(() => {
                bar.style.display = 'none';
                toast.style.display = 'none';
                barInner.style.width = '0%';
            }, 2500);
        });

        // Overlay de "processando envio": cobre o intervalo entre o clique no botão
        // (Enviar/Salvar/Criar) e a resposta do servidor, evitando que o usuário
        // clique várias vezes por achar que não funcionou.
        const overlay = document.getElementById('app-submit-overlay');
        if (!overlay) {
            return;
        }

        let awaitingSubmitResponse = false;

        const showOverlay = () => {
            awaitingSubmitResponse = true;
            overlay.style.display = 'flex';
        };
        const hideOverlay = () => {
            awaitingSubmitResponse = false;
            overlay.style.display = 'none';
        };

        // Captura o submit de qualquer formulário Filament (wire:submit) assim que
        // o usuário clica, antes mesmo da requisição sair — feedback imediato.
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (form instanceof HTMLFormElement && form.closest('.fi-form')) {
                showOverlay();
            }
        }, true);

        Livewire.hook('request', ({ succeed, fail }) => {
            if (!awaitingSubmitResponse) {
                return;
            }
            succeed(() => hideOverlay());
            fail(() => hideOverlay());
        });

        // Se a navegação mudar de página (redirecionamento pós-criação), garante
        // que o overlay não fique "preso" na próxima página.
        document.addEventListener('livewire:navigated', hideOverlay);
    });
</script>
