<?php
/**
 * Controller para Planejamento Tributário
 * Gerencia cálculos e lógica de negócio
 */
require_once __DIR__ . '/../Utils/Database.php';
require_once __DIR__ . '/../Models/PlanejamentoTributario.php';
require_once __DIR__ . '/../Models/Simulacao.php';
require_once __DIR__ . '/../Models/Selic.php';
require_once __DIR__ . '/../Utils/CalculoINSS.php';

class PlanejamentoTributarioController
{
    // Constantes para cálculos
    const FATOR_AJUSTE = 0.20; // 20% de desconto
    const TAXA_CPP = 0.20; // 20% sobre remuneração ajustada
    const TAXA_MULTA = 0.20; // 20% sobre CPP em atraso
    const MULTA_MAID = 200.00; // Multa por atraso na entrega
    const SELIC_ANUAL = 0.1075; // 10.75% a.a.
    const SELIC_MENSAL = 0.008958; // Taxa mensal da SELIC
    
    /**
     * Calcular planejamento tributário
     * 
     * @param int $simulacao_id ID da simulação
     * @param int $user_id ID do usuário
     * @param string $periodo_inicio Data de início (Y-m-d)
     * @param string $periodo_fim Data de fim (Y-m-d)
     * @param bool $force_update Forçar atualização se já existir
     * @param int $planejamento_id ID específico do planejamento (opcional)
     * @param float $honorarios_valor Valor dos honorários
     * @param string $honorarios_tipo Tipo dos honorários (real/percent)
     * @return array Resultado do cálculo
     */
    public static function calcular($simulacao_id, $user_id, $periodo_inicio, $periodo_fim, $force_update = false, $planejamento_id = null, $honorarios_valor = 0, $honorarios_tipo = 'real', $concreto_usinado = false) 
    {
        try {
            // Log de início
            error_log("[PlanejamentoTributarioController] Iniciando cálculo - Simulação: $simulacao_id, Usuário: $user_id");
            
            // 1. Validar e buscar simulação
            $simulacao = self::validarSimulacao($simulacao_id, $user_id);
            if (!$simulacao) {
                return ['error' => 'Simulação não encontrada ou acesso negado'];
            }
            
            // 2. Validações rigorosas de imutabilidade e tipo
            if (!$simulacao['area'] || $simulacao['area'] <= 0) {
                return ['error' => 'Área da obra é obrigatória e deve ser maior que zero'];
            }
            
            if (($simulacao['responsavel'] ?? '') !== 'Pessoa Física') {
                return ['error' => 'Planejamento tributário é exclusivo para Pessoa Física'];
            }
            
            // 3. Validar período
            if (!self::validarPeriodo($periodo_inicio, $periodo_fim)) {
                return ['error' => 'Período inválido'];
            }
            
            // 4. Calcular dados base da simulação
            $dados_base = self::calcularDadosBase($simulacao, $concreto_usinado);
            
            // 5. Calcular dados mensais
            // CORREÇÃO: Usar RMT Final (após dedução do concreto usinado) para calcular o desconto de 50% ou 70%
            // Quando há concreto usinado, o desconto deve ser aplicado sobre o RMT Final, não sobre o RMT Base
            $rmt_para_calculo = $dados_base['rmt_final']; // RMT Final quando há concreto usinado, RMT Base quando não há
            $dados_mensais = self::calcularDadosMensais($periodo_inicio, $periodo_fim, $rmt_para_calculo, $dados_base['area_obra']);
            
            // 6. Calcular honorários
            $honorarios_calculados = self::calcularHonorarios($dados_mensais, $honorarios_valor, $honorarios_tipo);
            
            // 7. Preparar dados para salvamento
            $dados_planejamento = [
                'area_obra' => $dados_base['area_obra'],
                'rmt_base' => $dados_base['rmt_base'],
                'inss_original' => $dados_base['inss_original'],
                'periodo_inicio' => $periodo_inicio,
                'periodo_fim' => $periodo_fim,
                'dados_calculados' => $dados_mensais,
                'honorarios_valor' => $honorarios_valor,
                'honorarios_tipo' => $honorarios_tipo,
                'honorarios_calculados' => $honorarios_calculados
            ];
            
            // 8. Determinar propriedade e origem do planejamento
            require_once __DIR__ . '/../Utils/Auth.php';
            $simulacao_owner_id = $simulacao['user_id']; // Dono original da simulação
            $current_user = Auth::user();
            $created_by_admin = ($current_user['id'] != $simulacao_owner_id && Auth::isAdmin());
            
            // Adicionar flag de criação por admin nos dados
            $dados_planejamento['created_by_admin'] = $created_by_admin;
            $dados_planejamento['created_by_admin_id'] = $created_by_admin ? $current_user['id'] : null;
            
            // 9. Salvar ou atualizar planejamento (sempre um por simulação)
            $planejamento_id = PlanejamentoTributario::create($simulacao_id, $simulacao_owner_id, $dados_planejamento);
            
            if ($planejamento_id) {
                // Verificar se foi criado ou atualizado
                $planejamento_existente = PlanejamentoTributario::findExistingBySimulacao($simulacao_id, $simulacao_owner_id);
                $was_updated = $planejamento_existente && $planejamento_existente['id'] == $planejamento_id;
                
                // Calcular resumo incluindo honorários
                $resumo = self::calcularResumo($dados_mensais, $honorarios_calculados);
                
                // Debug: verificar se os honorários estão sendo retornados
                error_log("[PlanejamentoTributarioController] Honorários calculados: " . json_encode($honorarios_calculados));
                error_log("[PlanejamentoTributarioController] Resumo com honorários: " . json_encode($resumo));
                
                return [
                    'success' => true,
                    'planejamento_id' => $planejamento_id,
                    'dados' => $dados_mensais,
                    'resumo' => $resumo,
                    'updated' => $was_updated,
                    'created' => !$was_updated,
                    'message' => $was_updated ? 'Planejamento atualizado com sucesso' : 'Novo planejamento criado com sucesso',
                    'percentual_concreto_uf' => $dados_base['percentual_concreto_uf'],
                    'deducao_concreto' => $dados_base['deducao_concreto'],
                    'rmt_original' => $dados_base['rmt_original'],
                    'rmt_base' => $dados_base['rmt_base'],
                    'rmt_final' => $dados_base['rmt_final'], // RMT após dedução do concreto usinado (se aplicável)
                    'cod' => $dados_base['cod'],
                    'area_equivalente' => $dados_base['area_equivalente'],
                    'vau' => $dados_base['vau']
                ];
            }
            
            return ['error' => 'Erro ao salvar planejamento'];
            
        } catch (Exception $e) {
            error_log("[PlanejamentoTributarioController] Erro: " . $e->getMessage());
            return ['error' => 'Erro interno no cálculo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validar simulação e acesso
     */
    public static function validarSimulacao($simulacao_id, $user_id) 
    {
        require_once __DIR__ . '/../Utils/Auth.php';
        
        $simulacao = Simulacao::find($simulacao_id);
        
        // Verificar se simulação existe e se usuário tem acesso
        if (!$simulacao || ($simulacao['user_id'] != $user_id && !Auth::isAdmin())) {
            return false;
        }
        
        // Validações rigorosas de imutabilidade
        if (empty($simulacao['area']) || !is_numeric($simulacao['area']) || $simulacao['area'] <= 0) {
            error_log("[PlanejamentoTributarioController] Área inválida na simulação: " . ($simulacao['area'] ?? 'null'));
            return false;
        }
        
        // Verificar se é Pessoa Física (exclusivo para planejamento tributário)
        if (($simulacao['responsavel'] ?? '') !== 'Pessoa Física') {
            error_log("[PlanejamentoTributarioController] Responsável inválido: " . ($simulacao['responsavel'] ?? 'null'));
            return false;
        }
        
        // Validar campos obrigatórios
        if (empty($simulacao['destinacao']) || empty($simulacao['tipo_obra']) || empty($simulacao['uf'])) {
            error_log("[PlanejamentoTributarioController] Campos obrigatórios ausentes na simulação");
            return false;
        }
        
        // Validar se a simulação não foi alterada durante o processo
        if (empty($simulacao['valor_inss']) || $simulacao['valor_inss'] <= 0) {
            error_log("[PlanejamentoTributarioController] Valor INSS inválido na simulação");
            return false;
        }
        
        return $simulacao;
    }
    
    /**
     * Validar período
     */
    public static function validarPeriodo($periodo_inicio, $periodo_fim) 
    {
        $data_inicio = DateTime::createFromFormat('Y-m-d', $periodo_inicio);
        $data_fim = DateTime::createFromFormat('Y-m-d', $periodo_fim);
        
        if (!$data_inicio || !$data_fim) {
            return false;
        }
        
        return $data_inicio <= $data_fim;
    }
    
    /**
     * Calcular dados base da simulação
     */
    public static function calcularDadosBase($simulacao, $concreto_usinado = false) 
    {
        // Preparar dados para cálculo
        $dados_simulacao = $simulacao;
        if (isset($simulacao['criado_em'])) {
            $dados_simulacao['data_simulacao'] = $simulacao['criado_em'];
        }
        
        // Adicionar concreto usinado aos dados
        $dados_simulacao['concreto_usinado'] = $concreto_usinado;
        
        // Calcular usando CalculoINSS
        $dados_calculo = CalculoINSS::calcular($dados_simulacao);
        
        // RMT Base (antes da dedução do concreto usinado)
        $rmt_base = $dados_calculo['rmt_original'] ?? $dados_calculo['rmt'];
        
        // RMT Final (após dedução do concreto usinado, se aplicável)
        // Quando há concreto usinado, o CalculoINSS já retorna o RMT com a dedução aplicada em 'rmt'
        $rmt_final = $concreto_usinado ? ($dados_calculo['rmt'] ?? $rmt_base) : $rmt_base;
        
        return [
            'area_obra' => $simulacao['area'],
            'rmt_base' => $rmt_base,
            'rmt_final' => $rmt_final, // RMT após dedução do concreto usinado (se aplicável)
            'rmt_original' => $rmt_base, // Mantido para compatibilidade
            'inss_original' => $dados_calculo['inss'],
            'percentual_concreto_uf' => $dados_calculo['percentual_concreto_uf'] ?? 0,
            'deducao_concreto' => $dados_calculo['deducao_concreto'] ?? 0,
            'cod' => $dados_calculo['cod'] ?? 0,
            'area_equivalente' => $dados_calculo['area_equivalente'] ?? 0,
            'vau' => $dados_calculo['vau'] ?? 0
        ];
    }
    
    /**
     * Calcular dados mensais do planejamento
     */
    private static function calcularDadosMensais($periodo_inicio, $periodo_fim, $rmt_base, $area_obra = null) 
    {
        require_once __DIR__ . '/../Models/Selic.php';
        $dados_mensais = [];
        $tz = new DateTimeZone('America/Sao_Paulo');
        
        $data_inicio = new DateTime($periodo_inicio, $tz);
        $data_fim = new DateTime($periodo_fim, $tz);
        
        // Ajustar para primeiro dia do mês
        $data_inicio->setDate($data_inicio->format('Y'), $data_inicio->format('m'), 1);
        $data_fim->setDate($data_fim->format('Y'), $data_fim->format('m'), 1);
        
        $mes_atual = clone $data_inicio;
        $mes_numero = 1;
        $max_loops = 50; // Proteção contra loop infinito
        
        // Calcular número de meses do período
        $interval = $data_inicio->diff($data_fim);
        $num_meses = ($interval->y * 12) + $interval->m + 1;
        if ($num_meses < 1) $num_meses = 1;
        
        // Calcular RMT com desconto aplicado
        if ($area_obra === null) {
            $rmt_com_desconto = $rmt_base;
        } else if ($area_obra <= 350) {
            $rmt_com_desconto = $rmt_base * 0.5;
        } else {
            $rmt_com_desconto = $rmt_base * 0.7;
        }
        
        $remuneracao_ajustada_mensal = $rmt_com_desconto / $num_meses;
        $remuneracao_original_mensal = $rmt_base / $num_meses;
        
        while ($mes_atual <= $data_fim && $mes_numero <= $max_loops) {
            $mes_ref = $mes_atual->format('Y-m');
            $mes_seguinte = (clone $mes_atual)->add(new DateInterval('P1M'));
            $mes = (int)$mes_seguinte->format('m');
            $ano = (int)$mes_seguinte->format('Y');
            $selic_row = Selic::buscar($mes, $ano);
            $selic = $selic_row && isset($selic_row['selic_acumulada']) ? $selic_row['selic_acumulada'] : 0;
            // Cálculos
            $selic_percentual = is_numeric($selic) ? floatval(str_replace(',', '.', $selic)) : 0; // ex: 14.77
            $remuneracao_atual = $remuneracao_ajustada_mensal;
            $remuneracao_original = ($remuneracao_atual * 100) / ($selic_percentual + 100);
            $cpp = $remuneracao_original * 0.2;

            // NOVA LÓGICA: só aplica encargos se HOJE > 15 do mês seguinte à competência
            $data_limite = (clone $mes_atual)->add(new DateInterval('P1M'))->setDate($ano, $mes, 15);
            $hoje = new DateTime('now', $tz);
            if ($hoje > $data_limite) {
                $multa = $cpp * 0.2;
                $selicAcumulada = $selic_percentual / 100; // decimal para cálculo da mora
                $mora = $cpp * $selicAcumulada;
                $maed = 100;
            } else {
                $multa = 0;
                $mora = 0;
                $maed = 0;
            }
            $totalMes = $cpp + $multa + $mora + $maed;
            $dados_mensais[] = [
                'mes' => $mes_ref,
                'mesNome' => $mes_atual->format('M/Y'),
                'mesNumero' => $mes_numero,
                'remuneracaoOriginal' => $remuneracao_original,
                'remuneracaoAjustada' => $remuneracao_atual,
                'selic' => $selic, // valor exato da tabela, sem conversão
                'cpp' => $cpp,
                'multa' => $multa,
                'selicAcumulada' => $selic_percentual / 100,
                'mora' => $mora,
                'maed' => $maed,
                'percentualAdicional' => 0,
                'totalMes' => $totalMes,
                'isAtraso' => false,
                'isMesAtual' => false,
                'isMesFuturo' => false,
                'fatorAjuste' => self::FATOR_AJUSTE
            ];
            $mes_atual->add(new DateInterval('P1M'));
            $mes_numero++;
        }
        return $dados_mensais;
    }
    
    /**
     * Calcular CPP (Contribuição Previdenciária Patronal)
     */
    private static function calcularCPP($remuneracao_mensal) 
    {
        return $remuneracao_mensal * self::TAXA_CPP;
    }
    
    /**
     * Calcular multa por atraso
     */
    private static function calcularMulta($cpp) 
    {
        return $cpp * self::TAXA_MULTA;
    }
    
    /**
     * Obter meses entre duas datas
     */
    private static function obterMesesEntre($data_inicio, $data_fim) 
    {
        $interval = $data_inicio->diff($data_fim);
        return ($interval->y * 12) + $interval->m;
    }
    
    /**
     * Calcular SELIC acumulada em ordem cronológica crescente
     * 
     * @param DateTime $mes_vencimento Mês de vencimento
     * @param DateTime $data_atual Data atual
     * @return float Fator acumulado da SELIC
     */
    private static function calcularSelicAcumulada($mes_vencimento, $data_atual) 
    {
        // Validação: não calcular SELIC para datas futuras
        $data_hoje = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        if ($data_atual > $data_hoje) {
            error_log("[PlanejamentoTributarioController] Tentativa de calcular SELIC para data futura");
            return 0.0;
        }
        
        // SELIC inicia no mês seguinte ao vencimento
        $data_selic = (clone $mes_vencimento)->add(new DateInterval('P1M'));
        $fator_acumulado = 1.0;
        
        // Calcular em ordem cronológica crescente, mês a mês
        while ($data_selic <= $data_atual) {
            $mes = (int)$data_selic->format('m');
            $ano = (int)$data_selic->format('Y');
            
            // Buscar SELIC Over mensal (já incorpora correção monetária e juros de mora)
            $selic_row = Selic::buscar($mes, $ano);
            $selic_mensal = $selic_row && isset($selic_row['selic_acumulada']) ? 
                           floatval($selic_row['selic_acumulada']) : 0.0;
            
            // Acumular fator (juros compostos)
            $fator_acumulado *= (1 + $selic_mensal);
            
            // Avançar para o próximo mês
            $data_selic->add(new DateInterval('P1M'));
        }
        
        return $fator_acumulado - 1;
    }
    
    /**
     * Calcular resumo dos dados mensais
     */
    private static function calcularResumo($dados_mensais, $honorarios_calculados = []) 
    {
        $total_cpp = 0;
        $total_multa = 0;
        $total_mora = 0;
        $total_maed = 0;
        $total_geral = 0;
        $meses_atraso = 0;
        $meses_futuro = 0;
        $meses_atual = 0;
        $total_remuneracao_ajustada = 0;
        $total_remuneracao_original = 0;
        $total_selic = 0;
        $count = count($dados_mensais);
        
        foreach ($dados_mensais as $mes) {
            $total_cpp += $mes['cpp'];
            $total_multa += $mes['multa'];
            $total_mora += $mes['mora'];
            $total_maed += $mes['maed'];
            $total_geral += $mes['totalMes'];
            $total_remuneracao_ajustada += $mes['remuneracaoAjustada'];
            $total_remuneracao_original += $mes['remuneracaoOriginal'];
            $total_selic += isset($mes['selic']) ? floatval(str_replace(',', '.', $mes['selic'])) : 0;
            if ($mes['isAtraso']) $meses_atraso++;
            if ($mes['isMesFuturo']) $meses_futuro++;
            if ($mes['isMesAtual']) $meses_atual++;
        }
        // Agora total_selic é a soma, não a média
        return [
            'total_cpp' => $total_cpp,
            'total_multa' => $total_multa,
            'total_mora' => $total_mora,
            'total_maed' => $total_maed,
            'total_geral' => $total_geral,
            'meses_atraso' => $meses_atraso,
            'meses_futuro' => $meses_futuro,
            'meses_atual' => $meses_atual,
            'total_meses' => $count,
            'total_remuneracao_ajustada' => $total_remuneracao_ajustada,
            'total_remuneracao_original' => $total_remuneracao_original,
            'total_selic' => $total_selic,
            'honorarios_valor' => isset($honorarios_calculados['valor_total']) ? $honorarios_calculados['valor_total'] : 0,
            'honorarios_tipo' => isset($honorarios_calculados['tipo']) ? $honorarios_calculados['tipo'] : 'real',
            'honorarios_calculados' => $honorarios_calculados
        ];
    }
    
    
    /**
     * Calcular honorários baseado no tipo e valor
     * 
     * @param array $dados_mensais Dados mensais do planejamento
     * @param float $honorarios_valor Valor dos honorários
     * @param string $honorarios_tipo Tipo dos honorários (real/percent)
     * @return array Dados dos honorários calculados
     */
    public static function calcularHonorarios($dados_mensais, $honorarios_valor, $honorarios_tipo)
    {
        $valor_total = 0;
        $valor_original = 0;
        
        // Calcular valor total do planejamento
        foreach ($dados_mensais as $mes) {
            $valor_total += $mes['totalMes'];
            $valor_original += $mes['remuneracaoOriginal'];
        }
        
        // Debug: log dos valores
        error_log("[calcularHonorarios] honorarios_valor: $honorarios_valor");
        error_log("[calcularHonorarios] honorarios_tipo: $honorarios_tipo");
        error_log("[calcularHonorarios] valor_total: $valor_total");
        error_log("[calcularHonorarios] valor_original: $valor_original");
        
        // Calcular honorários baseado no tipo
        $honorarios_calculados = 0;
        
        if ($honorarios_tipo === 'percent') {
            // Honorários como percentual da redução obtida
            $reducao_obtida = $valor_original - $valor_total;
            $honorarios_calculados = $reducao_obtida * ($honorarios_valor / 100);
            error_log("[calcularHonorarios] reducao_obtida: $reducao_obtida");
            error_log("[calcularHonorarios] honorarios_calculados: $honorarios_calculados");
        } else {
            // Honorários como valor fixo
            $honorarios_calculados = $honorarios_valor;
        }
        
        return [
            'valor_total' => $honorarios_calculados,
            'tipo' => $honorarios_tipo,
            'valor_original' => $honorarios_valor,
            'valor_planejamento' => $valor_total,
            'valor_original_total' => $valor_original,
            'reducao_obtida' => $valor_original - $valor_total,
            'economia_liquida' => ($valor_original - $valor_total) - $honorarios_calculados
        ];
    }
    
    /**
     * Buscar planejamento por ID
     */
    public static function buscar($id, $user_id) 
    {
        $planejamento = PlanejamentoTributario::find($id, $user_id);
        
        if (!$planejamento) {
            return ['error' => 'Planejamento não encontrado'];
        }
        
        $resumo = self::calcularResumo($planejamento['dados_calculados']);
        
        return [
            'success' => true,
            'planejamento' => $planejamento,
            'dados' => $planejamento['dados_calculados'],
            'resumo' => $resumo
        ];
    }
    
    /**
     * Listar planejamentos do usuário
     */
    public static function listar($user_id) 
    {
        $planejamentos = PlanejamentoTributario::findByUser($user_id);
        
        return [
            'success' => true,
            'planejamentos' => $planejamentos
        ];
    }
    
    /**
     * Listar planejamentos do usuário com filtros opcionais
     */
    public static function listarPlanejamentosUsuario($user_id, $filters = [], $limit = 100) 
    {
        require_once __DIR__ . '/../Models/PlanejamentoTributario.php';
        $planejamentos = PlanejamentoTributario::findByUserComFiltros($user_id, $filters, $limit);
        // Montar dados resumidos para exibição
        $result = [];
        foreach ($planejamentos as $p) {
            $result[] = [
                'id' => $p['id'],
                'nome' => $p['simulacao_nome'] ?? 'Planejamento #' . $p['id'],
                'periodo' => date('m/Y', strtotime($p['periodo_inicio'])) . ' - ' . date('m/Y', strtotime($p['periodo_fim'])),
                'valor_total' => isset($p['dados_calculados']) ? array_sum(array_column($p['dados_calculados'], 'totalMes')) : 0,
                'criado_em' => $p['criado_em'],
                'simulacao_id' => $p['simulacao_id'],
                'created_by_admin' => $p['created_by_admin'] ?? false,
            ];
        }
        return $result;
    }
    
    /**
     * Excluir planejamento
     */
    public static function excluir($id, $user_id) 
    {
        if (PlanejamentoTributario::hasAccess($id, $user_id)) {
            $resultado = PlanejamentoTributario::delete($id, $user_id);
            return ['success' => $resultado];
        }
        
        return ['error' => 'Acesso negado'];
    }
} 