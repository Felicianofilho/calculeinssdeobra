<?php
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Vau.php';
require_once __DIR__ . '/../Models/Setting.php';
require_once __DIR__ . '/../Models/Simulacao.php';
require_once __DIR__ . '/../Utils/Auth.php';

class AdminController
{
    public static function listarUsuarios()
    {
        // Se não for SuperAdmin, não mostrar SuperAdmins
        if (!Auth::isSuperAdmin()) {
            return User::allExceptSuperAdmin();
        }
        return User::all();
    }

    public static function criarUsuario($data)
    {
        $user_type = $data['user_type'] ?? 'lead';
        if (!User::canCreateUser($user_type)) {
            if ($user_type === 'client') {
                throw new Exception("Erro: Limite de usuários para a categoria 'cliente' foi atingido. Procure o Suporte para ajustar seu Plano.");
            }
            throw new Exception("Limite de usuários para a categoria '{$user_type}' foi atingido.");
        }
        return User::create($data);
    }

    public static function editarUsuario($id, $data)
    {
        return User::update($id, $data);
    }

    public static function toggleUsuario($id, $status)
    {
        return User::toggleStatus($id, $status);
    }

    public static function promoverUsuario($id)
    {
        return User::promoteToAdmin($id);
    }

    public static function rebaixarUsuario($id)
    {
        return User::demoteFromAdmin($id);
    }

    public static function promoverParaCliente($id)
    {
        return User::promoteToClient($id);
    }

    public static function rebaixarParaLead($id)
    {
        return User::demoteToLead($id);
    }

    public static function alterarCategoria($id, $categoria)
    {
        $categoriasValidas = ['admin', 'lead', 'client', 'superadmin'];
        error_log('[alterarCategoria] id=' . $id . ' categoria=' . $categoria . ' user_id_logado=' . ($_SESSION['user_id'] ?? 'N/A'));
        
        if (!in_array($categoria, $categoriasValidas)) {
            error_log('[alterarCategoria] Categoria inválida');
            return false;
        }

        if (!User::canCreateUser($categoria)) {
            if ($categoria === 'client') {
                throw new Exception("Erro: Limite de usuários para a categoria 'cliente' foi atingido. Procure o Suporte para ajustar seu Plano.");
            }
            throw new Exception("Limite de usuários para a categoria '{$categoria}' foi atingido.");
        }
        
        // Verificar se está tentando promover para admin
        if ($categoria === 'admin' && !Auth::isSuperAdmin()) {
            error_log('[alterarCategoria] Tentativa de promover para admin sem ser superadmin');
            return false;
        }
        // Verificar se está tentando promover para superadmin
        if ($categoria === 'superadmin' && !Auth::isSuperAdmin()) {
            error_log('[alterarCategoria] Tentativa de promover para superadmin sem ser superadmin');
            return false;
        }
        // Verificar se está tentando alterar a si mesmo
        if ($id == $_SESSION['user_id']) {
            error_log('[alterarCategoria] Tentativa de alterar a si mesmo');
            return false;
        }
        
        $result = User::update($id, ['user_type' => $categoria]);
        error_log('[alterarCategoria] Resultado do update: ' . var_export($result, true));
        return $result;
    }

    public static function alterarSenha($id, $senha)
    {
        // Verificar se está tentando alterar a senha de um SuperAdmin
        $targetUser = User::find($id);
        if ($targetUser && $targetUser['user_type'] === 'superadmin' && !Auth::isSuperAdmin()) {
            return false;
        }
        
        return User::update($id, ['senha' => $senha]);
    }

    public static function excluirUsuario($id)
    {
        // Verificar se está tentando excluir um SuperAdmin
        $targetUser = User::find($id);
        if ($targetUser && $targetUser['user_type'] === 'superadmin' && !Auth::isSuperAdmin()) {
            return false;
        }
        
        return User::delete($id); // pode retornar true, false ou 'has_simulacoes'
    }

    public static function buscarUsuario($id)
    {
        return User::find($id);
    }

    public static function listarVau()
    {
        return Vau::all();
    }

    public static function adicionarVau($data)
    {
        return Vau::create($data);
    }

    public static function getSetting($chave)
    {
        return Setting::get($chave);
    }

    public static function setSetting($chave, $valor)
    {
        return Setting::set($chave, $valor);
    }

    public static function getEstatisticasGerais()
    {
        $usuarios = self::listarUsuarios();
        $simulacoes = Simulacao::all();
        
        return [
            'total_usuarios' => count($usuarios),
            'usuarios_ativos' => count(array_filter($usuarios, function($u) { return $u['ativo']; })),
            'total_admins' => count(array_filter($usuarios, function($u) { return $u['user_type'] === 'admin'; })),
            'total_superadmins' => count(array_filter($usuarios, function($u) { return $u['user_type'] === 'superadmin'; })),
            'total_clients' => count(array_filter($usuarios, function($u) { return $u['user_type'] === 'client'; })),
            'total_leads' => count(array_filter($usuarios, function($u) { return $u['user_type'] === 'lead'; })),
            'total_simulacoes' => count($simulacoes),
            'total_inss' => array_sum(array_column($simulacoes, 'valor_inss')),
            'simulacoes_hoje' => count(array_filter($simulacoes, function($s) {
                return date('Y-m-d', strtotime($s['criado_em'])) === date('Y-m-d');
            }))
        ];
    }

    public static function exportarDados($tipo = 'usuarios')
    {
        switch ($tipo) {
            case 'usuarios':
                return self::exportarUsuarios();
            case 'simulacoes':
                return self::exportarSimulacoes();
            case 'completo':
                return self::exportarCompleto();
            default:
                return false;
        }
    }

    private static function exportarUsuarios()
    {
        $usuarios = self::listarUsuarios();
        $csv = "ID,Nome,Email,Categoria,Status,Criado em\n";
        
        foreach ($usuarios as $u) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s\n",
                $u['id'],
                $u['nome'],
                $u['email'],
                $u['user_type'],
                $u['ativo'] ? 'Ativo' : 'Inativo',
                $u['criado_em']
            );
        }
        
        return $csv;
    }

    private static function exportarSimulacoes()
    {
        $simulacoes = Simulacao::all();
        $csv = "ID,Nome,Tipo Obra,UF,Area,Valor INSS,Usuario ID,Criado em\n";
        
        foreach ($simulacoes as $s) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%.2f,%.2f,%d,%s\n",
                $s['id'],
                $s['nome'],
                $s['tipo_obra'],
                $s['uf'],
                $s['area'],
                $s['valor_inss'],
                $s['user_id'],
                $s['criado_em']
            );
        }
        
        return $csv;
    }

    private static function exportarCompleto()
    {
        // Implementar exportação completa se necessário
        return "Exportação completa não implementada ainda.";
    }

    public static function limparLogsAntigos($dias = 30)
    {
        // Implementar limpeza de logs se houver sistema de logs
        return true;
    }

    public static function getAtividadeRecente($limite = 10)
    {
        $simulacoes = Simulacao::getRecent($limite);
        $usuarios = self::listarUsuarios();
        
        $atividades = [];
        foreach ($simulacoes as $sim) {
            $usuario = array_filter($usuarios, function($u) use ($sim) {
                return $u['id'] == $sim['user_id'];
            });
            $nomeUsuario = !empty($usuario) ? reset($usuario)['nome'] : 'Usuário não encontrado';
            
            $atividades[] = [
                'tipo' => 'simulacao',
                'descricao' => "Nova simulação: {$sim['nome']}",
                'usuario' => $nomeUsuario,
                'data' => $sim['criado_em']
            ];
        }
        
        return $atividades;
    }

    public static function excluirSimulacao($simulacao_id) {
        $pdo = Database::getConnection();

        // 1. Obter informações do usuário logado
        $logged_user_id = $_SESSION['user_id'] ?? null;
        $logged_user_type = $_SESSION['user_type'] ?? 'lead';

        // 2. Obter informações da simulação e do seu proprietário
        $stmt = $pdo->prepare("SELECT s.user_id as owner_id, u.user_type as owner_type 
                               FROM simulacoes s 
                               JOIN users u ON s.user_id = u.id 
                               WHERE s.id = ?");
        $stmt->execute([$simulacao_id]);
        $target = $stmt->fetch();

        if (!$target) {
            return false; // Simulação não encontrada
        }

        $owner_id = $target['owner_id'];
        $owner_type = $target['owner_type'];

        // 3. Aplicar regras de permissão
        $can_delete = false;
        if ($logged_user_type === 'superadmin') {
            $can_delete = true; // SuperAdmin pode tudo
        } elseif ($logged_user_id == $owner_id) {
            $can_delete = true; // Dono pode excluir o seu
        } elseif ($logged_user_type === 'admin' && ($owner_type === 'lead' || $owner_type === 'client')) {
            $can_delete = true; // Admin pode excluir de leads e clientes
        }

        if (!$can_delete) {
            return false; // Permissão negada
        }

        // 4. Executar exclusão (transação para garantir atomicidade)
        try {
            $pdo->beginTransaction();

            // Excluir planejamento associado primeiro
            $stmt = $pdo->prepare("DELETE FROM planejamento_tributario WHERE simulacao_id = ?");
            $stmt->execute([$simulacao_id]);

            // Excluir a simulação
            $stmt = $pdo->prepare("DELETE FROM simulacoes WHERE id = ?");
            $stmt->execute([$simulacao_id]);

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao excluir simulação e planejamento: " . $e->getMessage());
            return false;
        }
    }


}

