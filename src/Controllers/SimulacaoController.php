<?php
require_once __DIR__ . '/../Models/Simulacao.php';
require_once __DIR__ . '/../Models/Vau.php';
require_once __DIR__ . '/../Utils/CalculoINSS.php';

class SimulacaoController
{
    public static function criarSimulacao($data)
    {
        // Validar dados
        $erros = CalculoINSS::validarDados($data);
        if (!empty($erros)) {
            throw new Exception('Dados inválidos: ' . implode(', ', $erros));
        }

        // Definir data da simulação
        $data['data_simulacao'] = $data['criado_em'] ?? date('Y-m-d H:i:s');

        // Calcular INSS
        $resultado = CalculoINSS::calcular($data);

        $data['valor_inss'] = $resultado['inss'];
        $data['vau_id'] = null;

        return Simulacao::create($data);
    }

    public static function listarSimulacoes($user_id)
    {
        return Simulacao::allByUser($user_id);
    }

    public static function verSimulacao($id)
    {
        return Simulacao::find($id);
    }

    public static function atualizarSimulacao($id, $data)
    {
        // Validar dados
        $erros = CalculoINSS::validarDados($data);
        if (!empty($erros)) {
            throw new Exception('Dados inválidos: ' . implode(', ', $erros));
        }

        // Buscar simulação original para obter data, se necessário
        $simulacao_original = Simulacao::find($id);
        if (isset($data['manter_data_original']) && $data['manter_data_original'] && $simulacao_original && isset($simulacao_original['criado_em'])) {
            $data['data_simulacao'] = $simulacao_original['criado_em'];
        } else {
            $data['data_simulacao'] = date('Y-m-d H:i:s');
        }

        // Recalcular INSS
        $resultado = CalculoINSS::calcular($data);

        $data['valor_inss'] = $resultado['inss'];
        $data['vau_id'] = null;

        return Simulacao::update($id, $data);
    }

    public static function excluirSimulacao($id)
    {
        return Simulacao::delete($id);
    }

    public static function duplicarSimulacao($id)
    {
        $simulacao = Simulacao::find($id);
        if (!$simulacao) {
            throw new Exception('Simulação não encontrada');
        }

        // Remover campos que não devem ser duplicados
        unset($simulacao['id']);
        unset($simulacao['criado_em']);
        unset($simulacao['atualizado_em']);

        // Adicionar sufixo ao nome
        $simulacao['nome'] = $simulacao['nome'] . ' (Cópia)';

        return Simulacao::create($simulacao);
    }

    public static function buscarSimulacoes($user_id, $filters = [])
    {
        return Simulacao::search($user_id, $filters);
    }

    public static function obterEstatisticas($user_id)
    {
        $simulacoes = Simulacao::allByUser($user_id);
        
        $stats = [
            'total' => count($simulacoes),
            'total_inss' => 0,
            'por_destinacao' => [],
            'por_tipo_obra' => [],
            'por_responsavel' => []
        ];

        foreach ($simulacoes as $sim) {
            $stats['total_inss'] += $sim['valor_inss'];

            // Por destinação
            $destinacao = $sim['destinacao'];
            if (!isset($stats['por_destinacao'][$destinacao])) {
                $stats['por_destinacao'][$destinacao] = 0;
            }
            $stats['por_destinacao'][$destinacao]++;

            // Por tipo de obra
            $tipo = $sim['tipo_obra'];
            if (!isset($stats['por_tipo_obra'][$tipo])) {
                $stats['por_tipo_obra'][$tipo] = 0;
            }
            $stats['por_tipo_obra'][$tipo]++;

            // Por responsável
            $responsavel = $sim['responsavel'] ?? 'Pessoa Física';
            if (!isset($stats['por_responsavel'][$responsavel])) {
                $stats['por_responsavel'][$responsavel] = 0;
            }
            $stats['por_responsavel'][$responsavel]++;
        }

        return $stats;
    }
}