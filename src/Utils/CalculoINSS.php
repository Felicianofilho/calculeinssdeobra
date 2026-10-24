<?php
require_once __DIR__ . '/Database.php';

class CalculoINSS
{
    /**
     * Calcula o INSS baseado na nova lógica da IN RFB Nº 2.021/2021
     * 
     * @param array $dados Dados da simulação
     * @return array Resultado do cálculo
     */
    public static function calcular($dados)
    {
        // Buscar VAU
        $data_referencia = $dados['data_simulacao'] ?? null;
        $vau = self::buscarVau($dados['uf'], $dados['destinacao'], $data_referencia);
        
        // Passo 1: Área Equivalente (AE)
        $ae = self::calcularAreaEquivalente($dados['area'], $dados['destinacao']);
        
        // Passo 2: COD (Custo das Obras por Destinação)
        $cod = $ae * $vau;
        
        // Verificar se é Pessoa Física ou Pessoa Jurídica
        $is_pessoa_juridica = ($dados['responsavel'] === 'Pessoa Jurídica');
        
        if ($is_pessoa_juridica) {
            // LÓGICA PARA PESSOA JURÍDICA
            // Não aplica Fator Social - usa apenas fator baseado no tipo de obra
            if ($dados['tipo_obra'] == 'Alvenaria') {
                $fator_rmt_fixo = 0.20; // 20%
            } else { // Madeira ou Mista
                $fator_rmt_fixo = 0.15; // 15%
            }
            $rmt = $cod * $fator_rmt_fixo;
            
            // Determinar alíquota baseada no regime tributário
            $aliquota = self::determinarAliquotaPJ($dados['regime_tributario']);
            
        } else {
            // LÓGICA PARA PESSOA FÍSICA
            // Passo 3: RMT (Remuneração da Mão de Obra Total) com base na área original
            $area_original = floatval($dados['area']);
            $percentual_fator_social = 0.0; // Padrão de segurança

            if ($area_original <= 100.0) {
                $percentual_fator_social = 0.20; // 20%
            } elseif ($area_original <= 200.0) {
                $percentual_fator_social = 0.40; // 40%
            } elseif ($area_original <= 300.0) {
                $percentual_fator_social = 0.55; // 55%
            } elseif ($area_original <= 400.0) {
                $percentual_fator_social = 0.70; // 70%
            } else { // > 400.0
                $percentual_fator_social = 0.90; // 90%
            }
            
            // Cálculo em duas etapas:
            // 1. Aplica o "Fator Social" variável sobre o COD
            $valor_intermediario = $cod * $percentual_fator_social;
            
            // 2. Aplica fator baseado no tipo de obra
            if ($dados['tipo_obra'] == 'Alvenaria') {
                $fator_rmt_fixo = 0.20; // 20%
            } else { // Madeira ou Mista
                $fator_rmt_fixo = 0.15; // 15%
            }
            $rmt = $valor_intermediario * $fator_rmt_fixo;
            
            // Alíquota fixa para Pessoa Física
            $aliquota = 0.368; // 36.8%
        }
        
        // Passo 4: Dedução do Concreto Usinado (se aplicável)
        $deducao_concreto = 0.0;
        $rmt_original = $rmt;
        
        if (isset($dados['concreto_usinado']) && $dados['concreto_usinado']) {
            $deducao_concreto = self::calcularDeducaoConcretoUsinado($cod, $dados['uf'], $dados['destinacao']);
            $rmt = $rmt - $deducao_concreto;
            // Garantir que RMT não seja negativo
            if ($rmt < 0) {
                $rmt = 0;
            }
        }
        
        // Passo 5: INSS
        $inss = $rmt * $aliquota;
        
        return [
            'vau' => $vau,
            'area_equivalente' => $ae,
            'cod' => $cod,
            'rmt' => $rmt,
            'rmt_original' => $rmt_original,
            'inss' => $inss,
            'percentual_rmt' => $is_pessoa_juridica ? ($fator_rmt_fixo * 100) : ($percentual_fator_social * 100),
            'percentual_equivalencia' => self::calcularPercentualEquivalencia($dados['area'], $dados['destinacao']),
            'responsavel' => $dados['responsavel'],
            'regime_tributario' => $dados['regime_tributario'] ?? null,
            'aliquota_aplicada' => $aliquota * 100,
            'is_pessoa_juridica' => $is_pessoa_juridica,
            'concreto_usinado' => isset($dados['concreto_usinado']) ? $dados['concreto_usinado'] : false,
            'deducao_concreto' => $deducao_concreto,
            'percentual_concreto_uf' => isset($dados['concreto_usinado']) && $dados['concreto_usinado'] ? self::obterPercentualConcretoUF($dados['uf'], $dados['destinacao']) : 0.0
        ];
    }
    
    /**
     * Determina a alíquota para Pessoa Jurídica baseada no regime tributário
     * 
     * @param string $regime_tributario Regime tributário da empresa
     * @return float Alíquota a ser aplicada
     */
    private static function determinarAliquotaPJ($regime_tributario)
    {
        switch ($regime_tributario) {
            case 'Simples Nacional':
                return 0.31; // 31%
            case 'Lucro Presumido':
            case 'Lucro Real':
                return 0.368; // 36.8%
            default:
                return 0.368; // Padrão 36.8%
        }
    }
    
    /**
     * Calcula a Área Equivalente conforme Art. 26, §6º, I, "a" da IN 2.021/2021
     * 
     * @param float $area Área construída
     * @param string $destinacao Destinação da obra
     * @return float Área equivalente
     */
    private static function calcularAreaEquivalente($area, $destinacao)
    {
        // Res. Unifamiliar
        if ($destinacao == "Res. Unifamiliar") {
            if ($area <= 1000) {
                return $area * 0.89; // 89%
            } else {
                return $area * 0.85; // 85%
            }
        }
        
        // Res. Multifamiliar
        if ($destinacao == "Res. Multifamiliar") {
            if ($area <= 1000) {
                return $area * 0.90; // 90%
            } else {
                return $area * 0.86; // 86%
            }
        }
        
        // Comercial Salas/Lojas
        if ($destinacao == "Comercial Salas/Lojas") {
            if ($area <= 3000) {
                return $area * 0.86; // 86%
            } else {
                return $area * 0.83; // 83%
            }
        }
        
        // Outras destinações específicas
        if ($destinacao == "Galpão Ind.") {
            return $area * 0.95; // 95%
        }
        
        if ($destinacao == "Casa Popular") {
            return $area * 0.98; // 98%
        }
        
        if ($destinacao == "Conj. Hab. Popular") {
            return $area * 0.98; // 98%
        }
        
        // Outras destinações: 100%
        return $area;
    }
    
    /**
     * Calcula o percentual de equivalência para exibição
     * 
     * @param float $area Área construída
     * @param string $destinacao Destinação da obra
     * @return float Percentual de equivalência
     */
    private static function calcularPercentualEquivalencia($area, $destinacao)
    {
        // Res. Unifamiliar
        if ($destinacao == "Res. Unifamiliar") {
            if ($area <= 1000) {
                return 89.0; // 89%
            } else {
                return 85.0; // 85%
            }
        }
        
        // Res. Multifamiliar
        if ($destinacao == "Res. Multifamiliar") {
            if ($area <= 1000) {
                return 90.0; // 90%
            } else {
                return 86.0; // 86%
            }
        }
        
        // Comercial Salas/Lojas
        if ($destinacao == "Comercial Salas/Lojas") {
            if ($area <= 3000) {
                return 86.0; // 86%
            } else {
                return 83.0; // 83%
            }
        }
        
        // Outras destinações específicas
        if ($destinacao == "Galpão Ind.") {
            return 95.0; // 95%
        }
        
        if ($destinacao == "Casa Popular") {
            return 98.0; // 98%
        }
        
        if ($destinacao == "Conj. Hab. Popular") {
            return 98.0; // 98%
        }
        
        // Outras destinações: 100%
        return 100.0;
    }
    
    /**
     * Busca o VAU na tabela
     * 
     * @param string $uf UF da obra
     * @param string $destinacao Destinação da obra
     * @param string|null $data_referencia Data da simulação (Y-m-d H:i:s ou timestamp)
     * @return float Valor do VAU
     */
    private static function buscarVau($uf, $destinacao, $data_referencia = null)
    {
        $pdo = Database::getConnection();
        if ($data_referencia) {
            // Extrair mês e ano da data
            $dt = is_numeric($data_referencia) ? date('Y-m-d', $data_referencia) : $data_referencia;
            $mes = (int)date('n', strtotime($dt));
            $ano = (int)date('Y', strtotime($dt));
            // Buscar VAU vigente na data (menor igual à data)
            $stmt = $pdo->prepare("SELECT valor FROM vau WHERE uf = ? AND destinacao = ? AND (ano < ? OR (ano = ? AND mes <= ?)) ORDER BY ano DESC, mes DESC LIMIT 1");
            $stmt->execute([$uf, $destinacao, $ano, $ano, $mes]);
            $vau_row = $stmt->fetch();
            if ($vau_row) {
                return floatval($vau_row['valor']);
            }
        }
        // Se não houver data ou não encontrar, buscar o mais recente
        $stmt = $pdo->prepare("SELECT valor FROM vau WHERE uf = ? AND destinacao = ? ORDER BY ano DESC, mes DESC LIMIT 1");
        $stmt->execute([$uf, $destinacao]);
        $vau_row = $stmt->fetch();
        if ($vau_row) {
            return floatval($vau_row['valor']);
        }
        // Valor padrão caso não encontre
        return 2000.00;
    }
    
    /**
     * Calcula a dedução do concreto usinado conforme IN RFB Nº 2.021/2021
     * 
     * @param float $cod Custo das Obras por Destinação
     * @param string $uf UF da obra
     * @param string $destinacao Destinação da obra
     * @return float Valor da dedução
     */
    private static function calcularDeducaoConcretoUsinado($cod, $uf, $destinacao)
    {
        // Buscar percentual da tabela de concreto usinado
        $percentual_uf = self::obterPercentualConcretoUF($uf, $destinacao);
        
        if ($percentual_uf <= 0) {
            return 0.0;
        }
        
        // Fórmula: COD × Percentual_UF × 5%
        $deducao_base = $cod * ($percentual_uf / 100);
        $deducao_final = $deducao_base * 0.05; // 5% da Receita Federal
        
        return $deducao_final;
    }
    
    /**
     * Obtém o percentual de concreto usinado para UF e destinação
     * 
     * @param string $uf UF da obra
     * @param string $destinacao Destinação da obra
     * @return float Percentual da tabela
     */
    private static function obterPercentualConcretoUF($uf, $destinacao)
    {
        require_once __DIR__ . '/../Models/ConcretoUsinado.php';
        
        try {
            return ConcretoUsinado::buscarPercentual($uf, $destinacao);
        } catch (Exception $e) {
            // Em caso de erro, retorna 0 (sem dedução)
            return 0.0;
        }
    }
    
    /**
     * Valida os dados de entrada
     * 
     * @param array $dados Dados da simulação
     * @return array Array com erros (vazio se válido)
     */
    public static function validarDados($dados)
    {
        $erros = [];
        
        // Validar responsável
        if (empty($dados['responsavel']) || !in_array($dados['responsavel'], ['Pessoa Física', 'Pessoa Jurídica'])) {
            $erros[] = 'Responsável deve ser "Pessoa Física" ou "Pessoa Jurídica"';
        }
        
        // Validar regime tributário para Pessoa Jurídica
        if ($dados['responsavel'] === 'Pessoa Jurídica') {
            if (empty($dados['regime_tributario']) || !in_array($dados['regime_tributario'], ['Simples Nacional', 'Lucro Presumido', 'Lucro Real'])) {
                $erros[] = 'Regime Tributário é obrigatório para Pessoa Jurídica e deve ser "Simples Nacional", "Lucro Presumido" ou "Lucro Real"';
            }
        }
        
        // Validar tipo de obra
        if (empty($dados['tipo_obra']) || !in_array($dados['tipo_obra'], ['Alvenaria', 'Madeira', 'Mista'])) {
            $erros[] = 'Tipo de obra deve ser "Alvenaria", "Madeira" ou "Mista"';
        }
        
        // Validar UF
        if (empty($dados['uf']) || strlen($dados['uf']) != 2) {
            $erros[] = 'UF deve ter 2 caracteres';
        }
        
        // Validar área construída
        if (empty($dados['area']) || !is_numeric($dados['area']) || $dados['area'] <= 0) {
            $erros[] = 'Área construída deve ser um número maior que zero';
        }
        
        // Validar destinação
        if (empty($dados['destinacao'])) {
            $erros[] = 'Destinação é obrigatória';
        }
        
        // Validar concreto usinado (opcional, boolean)
        if (isset($dados['concreto_usinado']) && !is_bool($dados['concreto_usinado']) && !in_array($dados['concreto_usinado'], [0, 1, '0', '1', 'true', 'false'])) {
            $erros[] = 'Concreto usinado deve ser verdadeiro ou falso';
        }
        
        return $erros;
    }
} 