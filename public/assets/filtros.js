// Gerenciamento de filtros AJAX para minhas_simulacoes.php
document.addEventListener("DOMContentLoaded", function() {
    // Variáveis para debounce
    let filterTimer;
    let isLoading = false;

    // Função para aplicar filtros via AJAX
    function aplicarFiltros() {
        if (isLoading) return;
        
        isLoading = true;
        
        // Mostrar indicador de carregamento
        const tableBody = document.querySelector('#dataTable tbody');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Carregando...</td></tr>';
        }

        // Coletar valores dos filtros
        const filtros = {
            nome: document.getElementById('filtro-nome').value,
            destinacao: document.getElementById('filtro-destinacao').value,
            responsavel: document.getElementById('filtro-responsavel').value,
            uf: document.getElementById('filtro-uf').value
        };

        // Fazer requisição AJAX
        const params = new URLSearchParams(filtros);
        fetch('filtrar_simulacoes_ajax.php?' + params.toString(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualizar apenas o conteúdo da tabela
                const tableContainer = document.querySelector('.table-responsive');
                if (tableContainer) {
                    tableContainer.innerHTML = data.html;
                }
                
                // Atualizar contador
                const headerTitle = document.querySelector('.card-header h6');
                if (headerTitle) {
                    headerTitle.innerHTML = '<i class="fas fa-table me-2"></i>Lista de Simulações (' + data.total_exibido + ')';
                }
                
                // Remover avisos anteriores
                const avisosAnteriores = document.querySelectorAll('.alert-info');
                avisosAnteriores.forEach(aviso => aviso.remove());
                
                // Mostrar aviso se há mais registros
                if (data.total_registros > data.total_exibido) {
                    const aviso = document.createElement('div');
                    aviso.className = 'alert alert-info alert-dismissible fade show mt-3';
                    aviso.innerHTML = `
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Exibindo ${data.total_exibido} de ${data.total_registros} registros.</strong> 
                        Use "Exportar CSV" para acessar todos os registros.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    const cardBody = document.querySelector('.card-body');
                    if (cardBody) {
                        cardBody.appendChild(aviso);
                    }
                }
            } else {
                console.error('Erro ao filtrar:', data.message);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            // Restaurar tabela original em caso de erro
            location.reload();
        })
        .finally(() => {
            isLoading = false;
        });
    }

    // Aplicar debounce nos filtros
    function debounceFilter(func, delay) {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(func, delay);
    }

    // Event listeners para filtros
    const filtroNome = document.getElementById('filtro-nome');
    const filtroDestinacao = document.getElementById('filtro-destinacao');
    const filtroResponsavel = document.getElementById('filtro-responsavel');
    const filtroUf = document.getElementById('filtro-uf');

    if (filtroNome) {
        filtroNome.addEventListener('input', () => {
            debounceFilter(aplicarFiltros, 500);
        });
    }

    if (filtroDestinacao) {
        filtroDestinacao.addEventListener('change', aplicarFiltros);
    }

    if (filtroResponsavel) {
        filtroResponsavel.addEventListener('change', aplicarFiltros);
    }

    if (filtroUf) {
        filtroUf.addEventListener('change', aplicarFiltros);
    }

    // Botão de exportação
    const btnExportar = document.getElementById('btn-exportar');
    if (btnExportar) {
        btnExportar.addEventListener('click', function() {
            // Coletar filtros atuais
            const filtros = {
                nome: filtroNome ? filtroNome.value : '',
                destinacao: filtroDestinacao ? filtroDestinacao.value : '',
                responsavel: filtroResponsavel ? filtroResponsavel.value : '',
                uf: filtroUf ? filtroUf.value : ''
            };

            // Criar URL para exportação
            const params = new URLSearchParams(filtros);
            const url = 'exportar_simulacoes_csv.php?' + params.toString();
            
            // Fazer download
            window.location.href = url;
        });
    }

    // --- Paginação AJAX Simulações (admin) ---
    let simulacoesOffset = 0;
    const simulacoesLimit = 6;
    const simulacoesTabelaContainer = document.getElementById('simulacoes-tabela-container');
    const btnSimulacoesProximo = document.getElementById('simulacoes-proximo');
    const btnSimulacoesAnterior = document.getElementById('simulacoes-anterior');
    function carregarSimulacoesAdmin(offset) {
        console.log('Chamando carregarSimulacoesAdmin com offset:', offset);
        const usuario = document.getElementById('filtroUsuario')?.value || '';
        const nome = document.getElementById('filtroNome')?.value || '';
        const data = document.getElementById('filtroData')?.value || '';
        fetch(`listar_simulacoes_admin.php?offset=${offset}&limit=${simulacoesLimit}&usuario=${encodeURIComponent(usuario)}&nome=${encodeURIComponent(nome)}&data=${encodeURIComponent(data)}`)
            .then(r => r.json())
            .then(resp => {
                const tbody = simulacoesTabelaContainer.querySelector('tbody');
                if (!tbody) {
                    console.error('Não encontrou o tbody dentro de simulacoesTabelaContainer!');
                    return;
                }
                tbody.innerHTML = resp.html;
                const total = resp.total || simulacoesLimit;
                btnSimulacoesAnterior.disabled = offset === 0;
                btnSimulacoesProximo.disabled = (offset + simulacoesLimit) >= total;
                console.log('Tabela de simulações atualizada. Offset:', offset, 'Total:', total);
            });
    }
    if (btnSimulacoesProximo && btnSimulacoesAnterior) {
        btnSimulacoesProximo.addEventListener('click', function() {
            simulacoesOffset += simulacoesLimit;
            carregarSimulacoesAdmin(simulacoesOffset);
        });
        btnSimulacoesAnterior.addEventListener('click', function() {
            simulacoesOffset = Math.max(0, simulacoesOffset - simulacoesLimit);
            carregarSimulacoesAdmin(simulacoesOffset);
        });
    }

    // --- Paginação AJAX Planejamentos (admin) ---
    window.planejamentosOffset = window.planejamentosOffset || 0;
    window.planejamentosLimit = window.planejamentosLimit || 6;
    const planejamentosTabelaContainer = document.getElementById('planejamentos-tabela-container');
    const btnPlanejamentosProximo = document.getElementById('planejamentos-proximo');
    const btnPlanejamentosAnterior = document.getElementById('planejamentos-anterior');
    window.carregarPlanejamentosAdmin = window.carregarPlanejamentosAdmin || function carregarPlanejamentosAdmin(offset) {
        const usuario = document.getElementById('filtroUsuarioPlanejamento')?.value || '';
        const nome = document.getElementById('filtroNomeSimulacao')?.value || '';
        const destinacao = document.getElementById('filtroDestinacao')?.value || '';
        const data = document.getElementById('filtroDataPlanejamento')?.value || '';
        fetch(`listar_planejamentos_admin.php?offset=${offset}&limit=${window.planejamentosLimit}&usuario=${encodeURIComponent(usuario)}&nome=${encodeURIComponent(nome)}&destinacao=${encodeURIComponent(destinacao)}&data=${encodeURIComponent(data)}`)
            .then(r => r.json())
            .then(resp => {
                document.querySelector('#planejamentos-tabela-container tbody').innerHTML = resp.html;
                const total = resp.total || window.planejamentosLimit;
                btnPlanejamentosAnterior.disabled = offset === 0;
                btnPlanejamentosProximo.disabled = (offset + window.planejamentosLimit) >= total;
            });
    };
    if (btnPlanejamentosProximo && btnPlanejamentosAnterior) {
        btnPlanejamentosProximo.addEventListener('click', function() {
            window.planejamentosOffset += window.planejamentosLimit;
            window.carregarPlanejamentosAdmin(window.planejamentosOffset);
        });
        btnPlanejamentosAnterior.addEventListener('click', function() {
            window.planejamentosOffset = Math.max(0, window.planejamentosOffset - window.planejamentosLimit);
            window.carregarPlanejamentosAdmin(window.planejamentosOffset);
        });
    }

    // Resetar paginação ao filtrar
    if (document.getElementById('filtrosPlanejamentosForm')) {
        document.getElementById('filtrosPlanejamentosForm').addEventListener('submit', function(e) {
            window.planejamentosOffset = 0;
            setTimeout(() => window.carregarPlanejamentosAdmin(0), 100);
        });
    }
    if (document.getElementById('filtroUsuario') || document.getElementById('filtroNome') || document.getElementById('filtroData')) {
        [document.getElementById('filtroUsuario'), document.getElementById('filtroNome'), document.getElementById('filtroData')].forEach(function(el) {
            if (el) el.addEventListener('change', function() {
                simulacoesOffset = 0;
                setTimeout(() => carregarSimulacoesAdmin(0), 100);
            });
        });
    }
});

// Funções globais
function duplicarSimulacao(id) {
    if (confirm('Deseja duplicar esta simulação?')) {
        window.location.href = 'duplicar_simulacao.php?id=' + id;
    }
} 

// Função para exclusão AJAX de planejamento na tela admin
function excluirPlanejamentoAdmin(id) {
    if (!confirm('Tem certeza que deseja excluir este planejamento?')) return;
    fetch('excluir_planejamento_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(resp => {
        window.location.reload();
    });
} 