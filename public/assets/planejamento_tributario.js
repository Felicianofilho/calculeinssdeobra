/**
 * JavaScript Planejamento Tributário
 * Gerencia interatividade e comunicação com API
 * 
 * @author Sistema INSS
 * @version 2.0
 */

// Variáveis globais
let simulacaoId = null;
let areaObra = 0;
let rmtBase = 0;
let inssOriginal = 0;
let calculatedData = [];
let calculatedSummary = {};
let planningId = null;

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    initializeVariables();
    setupEventListeners();
});

/**
 * Inicializar variáveis globais
 */
function initializeVariables() {
    simulacaoId = window.simulacaoId;
    areaObra = window.areaObra;
    rmtBase = window.rmtBase;
    inssOriginal = window.inssOriginal;
    
    console.log('Variáveis inicializadas:', {
        simulacaoId,
        areaObra,
        rmtBase,
        inssOriginal
    });
}

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    const formPlanning = document.getElementById('formPlanning');
    
    if (formPlanning) {
        formPlanning.addEventListener('submit', function(e) {
            if (window.userType === 'lead') {
                e.preventDefault();
                showLeadModal();
                return;
            }
            e.preventDefault();
            calculatePlanning();
        });
    }
}

/**
 * Mostrar mensagem de alerta
 */
function showMessage(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show alert-custom`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container');
    if (container) {
    container.insertBefore(alertDiv, container.firstChild);
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
}

/**
 * Calcular planejamento tributário
 */
async function calculatePlanning() {
    const area = areaObra; // Usar a área fixa da simulação
    const mesInicio = document.getElementById('mesInicio')?.value;
    const mesFim = document.getElementById('mesFim')?.value;
    const honorariosValor = parseFloat(document.getElementById('honorariosValor')?.value || 0);
    const tipoHonorario = document.getElementById('toggleTipoHonorario')?.dataset.tipo || 'real';
    const concretoUsinado = document.getElementById('concretoUsinado')?.checked || false;
    
    // Debug: verificar valores dos honorários
    console.log('calculatePlanning - honorariosValor:', honorariosValor);
    console.log('calculatePlanning - tipoHonorario:', tipoHonorario);
    console.log('calculatePlanning - toggle element:', document.getElementById('toggleTipoHonorario'));
    console.log('calculatePlanning - toggle dataset:', document.getElementById('toggleTipoHonorario')?.dataset);
    
    // Validações
    if (!area || !mesInicio || !mesFim) {
        showMessage('Por favor, preencha todos os campos obrigatórios.', 'error');
        return;
    }
    
    if (mesInicio > mesFim) {
        showMessage('A data de início deve ser anterior à data de fim.', 'error');
        return;
    }
    
    // Mostrar loading
    const btnCalculate = document.querySelector('.btn-calculate');
    const loading = document.getElementById('loadingMessage');
    
    if (btnCalculate) {
        btnCalculate.disabled = true;
        const loadingSpinner = btnCalculate.querySelector('.loading');
        if (loadingSpinner) {
            loadingSpinner.style.display = 'inline-block';
        }
    }
    
    if (loading) {
        loading.style.display = 'block';
    }
    
    try {
        const response = await fetch('api_planejamento_tributario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'calcular',
                simulacao_id: simulacaoId,
                periodo_inicio: mesInicio + '-01',
                periodo_fim: mesFim + '-01',
                honorarios_valor: honorariosValor,
                honorarios_tipo: tipoHonorario,
                concreto_usinado: concretoUsinado
            })
        });
        
        const result = await response.json();
        
        if (!result || result.error) {
            throw new Error(result?.error || 'Erro desconhecido');
        }
        
        if (!result.dados || !Array.isArray(result.dados)) {
            throw new Error('Resposta inesperada do servidor');
        }
        
        // Armazenar dados
        calculatedData = result.dados;
        calculatedSummary = result.resumo;
        planningId = result.planejamento_id;
        
        // Armazenar resultado completo da API para uso posterior
        window.lastApiResult = result;
        
        // Armazenar dados da dedução do concreto usinado se aplicável
        // TODO: Implementar quando backend estiver estável
        
        // Debug: verificar se os honorários estão chegando
        console.log('Dados do backend:', {
            resumo: result.resumo,
            honorarios_valor: result.resumo?.honorarios_valor,
            honorarios_tipo: result.resumo?.honorarios_tipo,
            honorarios_calculados: result.resumo?.honorarios_calculados
        });
        
        // Passar honorários para o buildPlanningTable e generateComparisonReport
        buildPlanningTable(result.dados, honorariosValor, tipoHonorario);
        updateSummary(result.resumo);
        showRMTDiscount(area, result);
        generateComparisonReport(honorariosValor, tipoHonorario);
        // Mostrar seções
        showSection('rmtDiscountSection');
        showSection('planningSection');
        showSection('comparisonSection');
        
        // Adicionar botão de exportação PDF
        console.log('Chamando addExportPdfButton()...');
        addExportPdfButton();
        
        // Scroll para resultados
        document.getElementById('rmtDiscountSection')?.scrollIntoView({ behavior: 'smooth' });
        
        // Mensagem de sucesso
        let successMessage = 'Planejamento calculado com sucesso!';
        if (result.updated) {
            successMessage = 'Planejamento atualizado com sucesso!';
        } else if (result.created) {
            successMessage = 'Novo planejamento criado com sucesso!';
        }
        
        showMessage(successMessage, 'success');
        
    } catch (error) {
        console.error('Erro ao calcular planejamento:', error);
        let errorMessage = 'Erro ao calcular planejamento: ' + error.message;
        if (error.message.includes('Sistema ocupado')) {
            errorMessage = 'O sistema está ocupado. Por favor, tente novamente em alguns instantes.';
        }
        showMessage(errorMessage, 'error');
    } finally {
        // Restaurar botão
        if (btnCalculate) {
            btnCalculate.disabled = false;
            const loadingSpinner = btnCalculate.querySelector('.loading');
            if (loadingSpinner) {
                loadingSpinner.style.display = 'none';
            }
        }
        
        // Ocultar loading
        if (loading) {
            loading.style.display = 'none';
        }
    }
}

/**
 * Construir tabela de planejamento
 */
function buildPlanningTable(data) {
    const tbody = document.getElementById('tableData');
    if (!tbody) return;
    tbody.innerHTML = '';
    
    let totalRemAtual = 0;
    let totalRemOrig = 0;
    let totalCpp = 0;
    let totalMulta = 0;
    let totalSelic = 0;
    let totalMora = 0;
    let totalMaed = 0;
    let totalFinal = 0;

    const mesesPt = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    const mesesEn = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    data.forEach((month, index) => {
        const row = tbody.insertRow();
        // Funções auxiliares para formatação
        const formatCurrency = (value) => {
            return typeof value === 'number' && !isNaN(value)
                ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
                : 'R$ 0,00';
        };
        // Corrigir parseNumber para não remover pontos decimais
        const parseNumber = (val) => {
            if (typeof val === 'string') {
                // Só troca vírgula por ponto
                let v = val.replace(/\s/g, '').replace(',', '.');
                return Number(v) || 0;
            }
            if (typeof val === 'number' && !isNaN(val)) {
                return val;
            }
            return 0;
        };
        // Usar os valores já calculados vindos do backend
        const remAtual = parseNumber(month.remuneracaoAjustada);
        const remOrig = parseNumber(month.remuneracaoOriginal);
        const cpp = parseNumber(month.cpp);
        const multa = parseNumber(month.multa);
        // SELIC: usar valor real, não multiplicar por 100
        const selic = parseNumber(month.selic);
        const mora = parseNumber(month.mora);
        const maed = parseNumber(month.maed);
        const total = parseNumber(month.totalMes);
        // Acumular totais
        totalRemAtual += remAtual;
        totalRemOrig += remOrig;
        totalCpp += cpp;
        totalMulta += multa;
        totalSelic += selic;
        totalMora += mora;
        totalMaed += maed;
        totalFinal += total;
        // Forçar mês em português abreviado
        let mesNome = month.mesNome;
        let mesNum = null, anoNum = null;
        // Tenta extrair mês/ano de mesNome (ex: 'Feb/2024' ou '02/2024')
        if (month.mes && month.ano) {
            mesNum = parseInt(month.mes, 10);
            anoNum = month.ano;
        } else if (typeof mesNome === 'string') {
            let match = mesNome.match(/([A-Za-z]{3}|\d{2})\/?(\d{4})/);
            if (match) {
                // Se for nome em inglês, converte para número
                let idx = mesesEn.findIndex(m => m.toLowerCase() === match[1].toLowerCase());
                if (idx > 0) mesNum = idx;
                else if (!isNaN(match[1])) mesNum = parseInt(match[1], 10);
                anoNum = match[2];
            }
        }
        if (mesNum && anoNum) {
            mesNome = mesesPt[mesNum] + '/' + anoNum;
        }
        // Montar linha da tabela
        row.innerHTML = `
            <td>${mesNome}</td>
            <td class="currency-value">${formatCurrency(remAtual)}</td>
            <td class="currency-value">${formatCurrency(remOrig)}</td>
            <td class="currency-value">${formatCurrency(cpp)}</td>
            <td class="currency-value">${formatCurrency(multa)}</td>
            <td>${selic.toFixed(2)}%</td>
            <td class="currency-value">${formatCurrency(mora)}</td>
            <td class="currency-value">${formatCurrency(maed)}</td>
            <td class="currency-value">${formatCurrency(total)}</td>
        `;
    });
    // Atualizar totais no rodapé
    updateElement('totalRemAtual', formatCurrency(totalRemAtual));
    updateElement('totalRemOrig', formatCurrency(totalRemOrig));
    updateElement('totalCpp', formatCurrency(totalCpp));
    updateElement('totalMulta', formatCurrency(totalMulta));
    // SELIC: mostrar média dos percentuais reais
    const mediaSelic = data.length > 0 ? totalSelic / data.length : 0;
    updateElement('totalSelic', mediaSelic.toFixed(2) + '%');
    updateElement('totalMora', formatCurrency(totalMora));
    updateElement('totalMaed', formatCurrency(totalMaed));
    updateElement('totalFinal', formatCurrency(totalFinal));
}

/**
 * Atualizar resumo
 */
function updateSummary(summary) {
    if (!summary) return;
    // Função auxiliar para atualizar elemento com segurança
    const updateElement = (id, value) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    };
    // Atualizar totais das colunas exibidas
    updateElement('totalRemAtual', formatCurrency(summary.total_remuneracao_ajustada || 0));
    updateElement('totalRemOrig', formatCurrency(summary.total_remuneracao_original || 0));
    updateElement('totalCpp', formatCurrency(summary.total_cpp || 0));
    updateElement('totalMulta', formatCurrency(summary.total_multa || 0));
    // Corrigir exibição do total da SELIC para mostrar média percentual
    updateElement('totalSelic', (summary.total_selic || 0).toFixed(2) + '%');
    updateElement('totalMora', formatCurrency(summary.total_mora || 0));
    updateElement('totalMaed', formatCurrency(summary.total_maed || 0));
    updateElement('totalFinal', formatCurrency(summary.total_geral || 0));
}

/**
 * Mostrar RMT com desconto
 */
function showRMTDiscount(area, response = null) {
    const rmtReal = rmtBase;
    const concretoUsinado = document.getElementById('concretoUsinado')?.checked;
    
    // CORREÇÃO: Determinar qual RMT usar para calcular o desconto
    // Se houver concreto usinado, usar RMT Final (após dedução)
    // Se não houver, usar RMT Base
    let rmtParaCalculoDesconto = rmtReal;
    
    if (concretoUsinado) {
        // Obter dados da simulação
        const form = document.getElementById('formPlanning');
        const areaForm = parseFloat(form?.dataset.area || area.toString());
        const uf = form?.dataset.uf || 'CE';
        const rmtBaseForm = parseFloat(form?.dataset.rmtBase || rmtReal.toString());
        
        // Usar RMT Final da resposta da API se disponível (mais preciso)
        // Caso contrário, calcular localmente
        if (response && response.rmt_final !== undefined) {
            rmtParaCalculoDesconto = response.rmt_final;
        } else {
            // Calcular RMT Final localmente como fallback
            const codReal = (response && response.cod) || (rmtBaseForm * 25);
            const percentualUfReal = response?.percentual_concreto_uf || 5.72;
            const deducaoEstimada = codReal * (percentualUfReal / 100) * 0.05;
            rmtParaCalculoDesconto = rmtBaseForm - deducaoEstimada;
        }
    }
    
    // Calcular desconto baseado na área
    let rmtComDesconto, textoDesconto, percentualDesconto;
    if (area <= 350) {
        // Obra até 350m² - desconto de 50%
        rmtComDesconto = rmtParaCalculoDesconto * 0.5;
        percentualDesconto = 50;
        textoDesconto = `RMT (50% obra até 350 m²): ${formatCurrency(rmtComDesconto)}`;
    } else {
        // Obra acima de 350m² - desconto de 30% (paga 70%)
        rmtComDesconto = rmtParaCalculoDesconto * 0.7;
        percentualDesconto = 30;
        textoDesconto = `RMT (70% acima de 350 m²): ${formatCurrency(rmtComDesconto)}`;
    }
    
    // Atualizar display
    const rmtDiscountDisplay = document.getElementById('rmtDiscountDisplay');
    if (rmtDiscountDisplay) {
        let html = '';
        
        if (concretoUsinado) {
            // Obter dados da simulação
            const form = document.getElementById('formPlanning');
            const areaForm = parseFloat(form?.dataset.area || area.toString());
            const uf = form?.dataset.uf || 'CE';
            const rmtBaseForm = parseFloat(form?.dataset.rmtBase || rmtReal.toString());
            
            // Usar dados reais do backend (COD = Área Equivalente × VAU)
            const codReal = (response && response.cod) || (rmtBaseForm * 25);
            const percentualUfReal = response?.percentual_concreto_uf || 5.72;
            const deducaoEstimada = codReal * (percentualUfReal / 100) * 0.05;
            // Usar RMT Final da resposta da API se disponível
            const rmtComConcreto = (response && response.rmt_final !== undefined) 
                ? response.rmt_final 
                : (rmtBaseForm - deducaoEstimada);
            
            // Determinar texto do desconto baseado na área
            const textoDescontoArea = areaForm <= 350 
                ? `RMT (50% obra até 350 m²):` 
                : `RMT (70% acima de 350 m²):`;
            
            html = `
                <!-- Card azul: RMT Base (igual ao PDF) -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div style="background:#eaf4ff; border:2px solid #007bff; color:#007bff; text-align:center; font-size:13px; font-weight:bold; border-radius:10px; padding:10px 0 6px 0;">
                            <span><b>RMT Base:</b> ${formatCurrency(rmtBaseForm)}</span>&nbsp;&nbsp;&nbsp;
                             <span><b>INSS Previsto:</b> ${formatCurrency(rmtBaseForm * 0.368)}</span>&nbsp;&nbsp;&nbsp;
                            <span><b>Área:</b> ${areaForm.toLocaleString('pt-BR')} m²</span>
                        </div>
                    </div>
                </div>
                
                <!-- Card verde: Dedução Concreto Usinado (igual ao PDF) -->
                <div class="row">
                    <div class="col-md-12">
                        <div style="background:#e8f5e8; border:2px solid #28a745; color:#28a745; text-align:center; font-size:12px; font-weight:bold; border-radius:10px; margin-bottom:12px; padding:8px 0 4px 0;">
                            <div><b>DEDUÇÃO CONCRETO USINADO APLICADA</b></div>
                            <div style="font-size:11px; margin-top:4px;">
                                <span><b>COD:</b> ${formatCurrency(codReal)}</span>&nbsp;&nbsp;&nbsp;
                                <span><b>% ${uf}:</b> ${percentualUfReal.toFixed(2)}%</span>&nbsp;&nbsp;&nbsp;
                                <span><b>Dedução:</b> ${formatCurrency(deducaoEstimada)}</span>&nbsp;&nbsp;&nbsp;
                                <span><b>RMT Final:</b> ${formatCurrency(rmtComConcreto)}</span>
                            </div>
                            <div style="font-size:10px; margin-top:2px; font-style:italic;">Conforme IN RFB 2.021/2021 - Redução da base de cálculo do INSS</div>
                        </div>
                    </div>
                </div>
                
                <!-- Card azul: RMT com Desconto (CORRIGIDO: usando RMT Final) -->
                <div class="row">
                    <div class="col-md-12">
                        <div style="background:#eaf4ff; border:2px solid #007bff; color:#007bff; text-align:center; font-size:13px; font-weight:bold; border-radius:10px; padding:10px 0 6px 0;">
                            <span><b>${textoDescontoArea}</b> ${formatCurrency(rmtComDesconto)}</span>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // Layout original quando não há concreto usinado
            const textoDescontoArea = area <= 350 
                ? `RMT (50% obra até 350 m²):` 
                : `RMT (70% acima de 350 m²):`;
            
            html = `
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div style="background:#eaf4ff; border:2px solid #007bff; color:#007bff; text-align:center; font-size:13px; font-weight:bold; border-radius:10px; padding:10px 0 6px 0;">
                            <span><b>${textoDescontoArea}</b> ${formatCurrency(rmtComDesconto)}</span>&nbsp;&nbsp;&nbsp;
                            <span><b>Área:</b> ${area.toLocaleString('pt-BR')} m²</span>
                        </div>
                        <p class="mb-0 text-muted mt-2">
                            <i class="fas fa-info-circle me-2"></i>
                            RMT original: ${formatCurrency(rmtReal)} | 
                            Desconto: ${percentualDesconto}%
                        </p>
                    </div>
                </div>
            `;
        }
        
        rmtDiscountDisplay.innerHTML = html;
    }
}

/**
 * Gerar relatório comparativo
 */
function generateComparisonReport(honorariosValor = 0, tipoHonorario = 'real') {
    // CORREÇÃO: Usar RMT Final se concreto usinado foi aplicado
    let rmtParaCalculo = rmtBase;
    const concretoUsinado = document.getElementById('concretoUsinado')?.checked;
    
    if (concretoUsinado) {
        // Se concreto usinado foi aplicado, usar RMT Final para cálculo
        // Usar diretamente os dados da API se disponível
        if (window.lastApiResult && window.lastApiResult.rmt_original) {
            // Se temos o RMT original da API, calcular RMT Final
            const rmtOriginal = window.lastApiResult.rmt_original;
            const deducao = window.lastApiResult.deducao_concreto || 0;
            rmtParaCalculo = rmtOriginal - deducao;
            
            console.log('🔧 Usando RMT Final da API:', {
                rmt_original: rmtOriginal,
                deducao_concreto: deducao,
                rmt_final: rmtParaCalculo
            });
        } else {
            // Fallback: usar RMT Base (não ideal, mas funcional)
            console.log('⚠️ Usando RMT Base como fallback');
            rmtParaCalculo = rmtBase;
        }
    }
    
    const valorSemFator = rmtParaCalculo * 0.368; // RMT Final × 36,8%
    const valorComFator = calculatedSummary.total_geral;
    const reducaoObtida = valorSemFator - valorComFator;
    
    // Debug: log dos valores (simplificado)
    console.log('🔍 CORREÇÃO RMT FINAL:', {
        concretoUsinado,
        rmtParaCalculo_final: rmtParaCalculo,
        valorSemFator: valorSemFator,
        valorComFator: valorComFator
    });
    
    // Usar honorários do backend se disponível, senão calcular localmente
    let honorarios = 0;
    if (calculatedSummary.honorarios_valor !== undefined) {
        // Usar valor do backend
        honorarios = calculatedSummary.honorarios_valor;
        console.log('generateComparisonReport - Usando valor do backend:', honorarios);
    } else {
        // Calcular localmente (fallback)
        honorarios = honorariosValor;
        if (tipoHonorario === 'percent') {
            honorarios = reducaoObtida * (honorariosValor / 100);
            console.log('generateComparisonReport - Calculando localmente (percent):', honorarios);
        }
        console.log('generateComparisonReport - Usando valor local:', honorarios);
    }
    
    console.log('generateComparisonReport - honorarios final:', honorarios);
    
    // CORREÇÃO: Se for percentual, recalcular baseado na redução da tela
    if (calculatedSummary.honorarios_tipo === 'percent' && calculatedSummary.honorarios_calculados?.valor_original) {
        const percentual = calculatedSummary.honorarios_calculados.valor_original;
        honorarios = reducaoObtida * (percentual / 100);
        console.log('generateComparisonReport - Recalculando honorários:', percentual + '% de', reducaoObtida, '=', honorarios);
    }
    
    // Para exibição: mostrar sempre o valor monetário
    let honorariosFormatado = formatCurrency(honorarios);
    
    const economiaLiquida = reducaoObtida - honorarios;
    
    // Debug: verificar valores do cálculo
    console.log('generateComparisonReport - reducaoObtida:', reducaoObtida);
    console.log('generateComparisonReport - honorariosParaCalculo:', honorarios);
    console.log('generateComparisonReport - economiaLiquida:', economiaLiquida);
    console.log('generateComparisonReport - honorariosFormatado:', honorariosFormatado);
    
    // Atualizar valores
    updateElement('valorSemFator', formatCurrency(valorSemFator));
    updateElement('valorComFator', formatCurrency(valorComFator));
    updateElement('valorReducao', formatCurrency(reducaoObtida));
    updateElement('valorHonorarios', honorariosFormatado);
    updateElement('valorLiquida', formatCurrency(economiaLiquida));
    updateElement('percentualEconomia', (valorSemFator > 0 ? (economiaLiquida / valorSemFator) * 100 : 0).toFixed(1) + '%');
    
    // Montar os 5 cards em linha, responsivos
    const row = document.querySelector('#comparisonSection .row');
    // Determinar texto do card baseado no concreto usinado
    const cardTextoSemFator = concretoUsinado ? 'RMT - Conc. Usin.' : 'Valor Original';
    
    if (row) {
        row.innerHTML = `
        <div class="comparison-cards-responsive">
            <div class="comparison-card-custom">
                <div class="card-header-custom bg-danger text-white">
                    <i class="fas fa-times-circle me-2"></i><span class="card-title-text">Sem Fator de Ajuste</span>
                </div>
                <div class="card-body-custom">
                    <div class="main-value text-danger">${formatCurrency(valorSemFator)}</div>
                    <div class="card-desc">${cardTextoSemFator}</div>
                </div>
            </div>
            <div class="comparison-card-custom">
                <div class="card-header-custom bg-success text-white">
                    <i class="fas fa-check-circle me-2"></i><span class="card-title-text">Com Fator de Ajuste</span>
                </div>
                <div class="card-body-custom">
                    <div class="main-value text-success">${formatCurrency(valorComFator)}</div>
                    <div class="card-desc">Valor Otimizado</div>
                </div>
            </div>
            <div class="comparison-card-custom">
                <div class="card-header-custom bg-warning text-dark">
                    <i class="fas fa-piggy-bank me-2"></i><span class="card-title-text">Redução Obtida</span>
                </div>
                <div class="card-body-custom">
                    <div class="main-value text-warning">${formatCurrency(reducaoObtida)}</div>
                    <div class="card-desc">Redução Total</div>
                </div>
            </div>
            <div class="comparison-card-custom">
                <div class="card-header-custom bg-info text-white">
                    <i class="fas fa-user-tie me-2"></i><span class="card-title-text">Honorários</span>
                </div>
                <div class="card-body-custom">
                    <div class="main-value text-info">${honorariosFormatado}</div>
                    <div class="card-desc">Honorários do contador</div>
                </div>
            </div>
            <div class="comparison-card-custom">
                <div class="card-header-custom bg-primary text-white">
                    <i class="fas fa-coins me-2"></i><span class="card-title-text">Economia Líquida</span>
                </div>
                <div class="card-body-custom">
                    <div class="main-value text-primary">${formatCurrency(economiaLiquida)}</div>
                    <div class="card-desc">Você economizou</div>
                    <div class="badge bg-primary text-white fs-6 mb-2" id="percentualEconomiaLiquida">${valorSemFator > 0 ? ((economiaLiquida / valorSemFator) * 100).toFixed(1) : '0.0'}%</div>
                </div>
            </div>
        </div>
        `;
    }
    
    // Resumo Executivo em duas colunas equilibradas, conclusão embaixo
    const executiveSummary = document.getElementById('executiveSummary');
    if (executiveSummary) {
        // Calcular RMT Final quando houver concreto usinado
        let rmtFinal = null;
        if (concretoUsinado) {
            // RMT Final é o rmtParaCalculo (já calculado acima)
            rmtFinal = rmtParaCalculo;
        }
        
        executiveSummary.innerHTML = `
            <div class="row">
                <div class="col-md-6 offset-md-1">
                    <ul class="list-unstyled mb-2">
                        <li><strong>Área da obra:</strong> ${areaObra.toLocaleString('pt-BR')} m²</li>
                        <li><strong>RMT base:</strong> ${formatCurrency(rmtBase)}</li>
                        ${concretoUsinado && rmtFinal ? `<li><strong>RMT Final:</strong> ${formatCurrency(rmtFinal)}</li>` : ''}
                        <li><strong>${concretoUsinado ? 'RMT - Conc. Usin.:' : 'Valor original:'}</strong> ${formatCurrency(valorSemFator)}</li>
                        <li><strong>Valor otimizado:</strong> ${formatCurrency(valorComFator)}</li>
                    </ul>
                </div>
                <div class="col-md-5">
                    <ul class="list-unstyled mb-2 mt-4 mt-md-0">
                        <li><strong>Redução obtida:</strong> ${formatCurrency(reducaoObtida)}</li>
                        <li><strong>Honorários:</strong> ${formatCurrency(honorarios)}</li>
                        <li><strong>Economia líquida:</strong> ${formatCurrency(economiaLiquida)}</li>
                        <li><strong>Percentual de economia:</strong> ${(valorSemFator > 0 ? (economiaLiquida / valorSemFator) * 100 : 0).toFixed(1)}%</li>
                    </ul>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <div class="alert alert-success mt-2">
                        <h6><i class="fas fa-lightbulb me-2"></i>Conclusão</h6>
                        <p class="mb-0">
                            Com o <strong>Fator de Ajuste</strong>, esta obra de ${areaObra.toLocaleString('pt-BR')}m² obtém uma economia líquida de <strong>${formatCurrency(economiaLiquida)}</strong> após considerar os honorários do contador.
                        </p>
                    </div>
                </div>
            </div>
        `;
    }
}

/**
 * Gerar resumo executivo
 */
function generateExecutiveSummary() {
    const area = areaObra; // Usar a área fixa da simulação
    const valorSemFator = inssOriginal;
    const valorComFator = calculatedSummary.total_geral;
    const economia = valorSemFator - valorComFator;
    const percentualEconomia = valorSemFator > 0 ? ((economia / valorSemFator) * 100) : 0;
    
    const criterioDesconto = area <= 350 ? '50%' : '30%';
    const tipoObra = area <= 350 ? 'pequena (até 350m²)' : 'grande (acima de 350m²)';
    
    const executiveSummary = document.getElementById('executiveSummary');
    if (executiveSummary) {
        executiveSummary.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-info-circle me-2"></i>Análise da Obra</h6>
                <ul class="list-unstyled">
                        <li><strong>Área:</strong> ${area.toLocaleString('pt-BR')} m²</li>
                    <li><strong>Classificação:</strong> Obra ${tipoObra}</li>
                    <li><strong>Desconto aplicado:</strong> ${criterioDesconto}</li>
                        <li><strong>RMT base:</strong> ${formatCurrency(rmtBase)}</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-calculator me-2"></i>Resultado Financeiro</h6>
                <ul class="list-unstyled">
                        <li><strong>INSS original:</strong> ${formatCurrency(valorSemFator)}</li>
                        <li><strong>INSS otimizado:</strong> ${formatCurrency(valorComFator)}</li>
                        <li><strong>Economia total:</strong> ${formatCurrency(economia)}</li>
                    <li><strong>Redução percentual:</strong> ${percentualEconomia.toFixed(1)}%</li>
                </ul>
            </div>
        </div>
        <div class="alert alert-success mt-3">
            <h6><i class="fas fa-lightbulb me-2"></i>Conclusão</h6>
            <p class="mb-0">
                    Com o <strong>Fator de Ajuste</strong>, esta obra de ${area.toLocaleString('pt-BR')}m² 
                    obtém uma economia de <strong>${formatCurrency(economia)}</strong> 
                (${percentualEconomia.toFixed(1)}% de redução) em relação ao INSS original calculado pelo sistema. 
                O planejamento tributário otimizado resulta em significativa economia de recursos.
            </p>
        </div>
    `;
    }
}

/**
 * Adicionar botão de exportação PDF após o cálculo
 */
async function addExportPdfButton() {
    console.log('Adicionando botão de exportação PDF...');
    
    // Remover botão antigo se existir
    let existingBtn = document.getElementById('btnExportPdf');
    if (existingBtn) existingBtn.remove();

    // Obter valores de período
    const mesInicio = document.getElementById('mesInicio')?.value;
    const mesFim = document.getElementById('mesFim')?.value;
    const honorariosValor = parseFloat(document.getElementById('honorariosValor')?.value || 0);
    const tipoHonorario = document.getElementById('toggleTipoHonorario')?.dataset.tipo || 'real';
    const concretoUsinado = document.getElementById('concretoUsinado')?.checked || false;
    
    console.log('Períodos:', { mesInicio, mesFim });
    console.log('Honorários:', { honorariosValor, tipoHonorario });
    console.log('Concreto Usinado:', { concretoUsinado });
    
    if (!mesInicio || !mesFim) {
        console.warn('Períodos não encontrados, não criando botão');
        return;
    }

    const periodo_inicio = mesInicio + '-01';
    const periodo_fim = mesFim + '-01';

    // Criar botão
    const btn = document.createElement('button');
    btn.id = 'btnExportPdf';
    btn.className = 'btn btn-danger mb-3';
    btn.innerHTML = '<i class="fas fa-file-pdf me-2"></i>Gerar PDF do Planejamento <span class="loading-pdf" style="display:none;"><i class="fas fa-spinner fa-spin"></i></span>';

    // Inserir botão acima da tabela de planejamento
    const planningSection = document.getElementById('planningSection');
    if (planningSection) {
        planningSection.parentNode.insertBefore(btn, planningSection);
        console.log('Botão de PDF adicionado com sucesso');
    } else {
        console.warn('Seção planningSection não encontrada');
    }

    // Adicionar event listener para a chamada AJAX
    btn.addEventListener('click', async function() {
        if (window.userType === 'lead') {
            showLeadModal();
            return;
        }

        btn.disabled = true;
        const loadingSpinner = btn.querySelector('.loading-pdf');
        if (loadingSpinner) {
            loadingSpinner.style.display = 'inline-block';
        }

        try {
            // Abrir uma nova janela imediatamente
            const newWindow = window.open('', '_blank');
            if (!newWindow) {
                showMessage('O navegador bloqueou a abertura do PDF. Por favor, desative o bloqueador de pop-ups para este site.', 'error');
                return;
            }

            const response = await fetch('relatorio_planejamento_pdf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    simulacao_id: simulacaoId,
                    periodo_inicio: periodo_inicio,
                    periodo_fim: periodo_fim,
                    honorarios_valor: honorariosValor,
                    honorarios_tipo: tipoHonorario,
                    concreto_usinado: concretoUsinado
                })
            });

            const result = await response.json();

            if (result.success) {
                console.log('PDF URL:', result.pdf_url);
                newWindow.location.href = result.pdf_url;
                showMessage('PDF gerado com sucesso!', 'success');
            } else {
                newWindow.close(); // Fechar a janela em caso de erro
                showMessage('Erro ao gerar PDF: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Erro ao gerar PDF:', error);
            showMessage('Erro ao gerar PDF: ' + error.message, 'error');
        } finally {
            btn.disabled = false;
            if (loadingSpinner) {
                loadingSpinner.style.display = 'none';
            }
        }
    });
}

/**
 * Mostrar seção
 */
function showSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.style.display = 'block';
    }
}

/**
 * Atualizar elemento com segurança
 */
function updateElement(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
        // Debug: log para garantir atualização
        // console.log('Atualizando', id, 'para', value);
    } else {
        console.warn(`Elemento com ID '${id}' não encontrado`);
    }
}

/**
 * Formatar moeda
 */
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value || 0);
}

function formatPercentage(value) {
    return typeof value === 'number' && !isNaN(value)
        ? (value * 100).toFixed(2) + '%'
        : '0,00%';
} 

function showLeadModal() {
    // Remover modal antigo se existir
    let existing = document.getElementById('leadModal');
    if (existing) existing.remove();
    // Criar modal
    const modal = document.createElement('div');
    modal.id = 'leadModal';
    modal.innerHTML = `
    <div class="modal fade show" tabindex="-1" style="display:block; background:rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-warning-subtle">
            <h5 class="modal-title text-primary"><i class="fas fa-lock me-2"></i>Funcionalidade exclusiva para clientes</h5>
            <button type="button" class="btn-close" onclick="document.getElementById('leadModal').remove()"></button>
          </div>
          <div class="modal-body text-center">
            <p class="mb-3 fs-5"><strong>O Planejamento Tributário é uma ferramenta avançada que pode gerar grande economia no INSS da sua obra.</strong></p>
            <p class="mb-3">Esta funcionalidade é exclusiva para clientes. Torne-se cliente agora mesmo e tenha acesso a este recurso premium, com suporte especializado e potencial de economia real.</p>
            <button id="ctaContratar" class="btn btn-success btn-lg w-100 mb-2"><i class="fas fa-star me-2"></i>Quero ser Cliente e Economizar!</button>
            <div class="text-muted mt-2" style="font-size:0.95em;">Fale com nossa equipe e descubra como economizar de verdade no INSS da sua obra.</div>
            </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    // CTA: redirecionar para página de contato/contratação/WhatsApp
    document.getElementById('ctaContratar').onclick = function() {
        window.open(window.whatsappUrl, '_blank');
    };
} 