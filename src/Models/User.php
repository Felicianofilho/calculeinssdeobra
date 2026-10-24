<?php
require_once __DIR__ . '/../Utils/Database.php';
require_once __DIR__ . '/../Utils/LocalConfig.php';

class User
{
    public static function canCreateUser($user_type)
    {
        $config = LocalConfig::getAll();
        $maxAdmins = $config['features']['max_admins'] ?? 1;
        $maxClients = $config['features']['max_clients'] ?? 2;

        if ($user_type === 'admin' && self::getAdminCount() >= $maxAdmins) {
            return false;
        }
        if ($user_type === 'client' && self::getClientCount() >= $maxClients) {
            return false;
        }
        return true;
    }

    public static function create($data)
    {
        $user_type = $data['user_type'] ?? 'lead';

        if (!self::canCreateUser($user_type)) {
            return false; // Ou lançar uma exceção
        }

        $pdo = Database::getConnection();
        $senha_hash = password_hash($data['senha'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (nome, email, telefone, senha_hash, tipo, user_type, ativo, email_confirm_token, email_confirmed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['nome'],
            $data['email'],
            $data['telefone'],
            $senha_hash,
            'usuario', // Mantém compatibilidade com o campo tipo
            $user_type,
            $data['ativo'] ?? 0,
            $data['email_confirm_token'] ?? null,
            $data['email_confirmed'] ?? 0
        ]);
    }

    public static function findByEmail($email)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public static function all()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM users ORDER BY criado_em DESC");
        return $stmt->fetchAll();
    }

    public static function allExceptSuperAdmin()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM users WHERE user_type != 'superadmin' ORDER BY criado_em DESC");
        return $stmt->fetchAll();
    }

    public static function find($id)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function update($id, $data)
    {
        $pdo = Database::getConnection();
        
        $fields = [];
        $values = [];
        
        if (isset($data['nome'])) {
            $fields[] = "nome = ?";
            $values[] = $data['nome'];
        }
        
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $values[] = $data['email'];
        }
        
        if (isset($data['telefone'])) {
            $fields[] = "telefone = ?";
            $values[] = $data['telefone'];
        }
        
        if (isset($data['senha']) && !empty($data['senha'])) {
            $fields[] = "senha_hash = ?";
            $values[] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }
        
        if (isset($data['tipo'])) {
            $fields[] = "tipo = ?";
            $values[] = $data['tipo'];
        }
        
        if (isset($data['user_type'])) {
            $fields[] = "user_type = ?";
            $values[] = $data['user_type'];
        }
        
        if (isset($data['ativo'])) {
            $fields[] = "ativo = ?";
            $values[] = $data['ativo'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public static function toggleStatus($id, $status)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET ativo = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public static function promoteToAdmin($id)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET user_type = 'admin' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function demoteFromAdmin($id)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET user_type = 'lead' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function promoteToClient($id)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET user_type = 'client' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function demoteToLead($id)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET user_type = 'lead' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function promoteToSuperAdmin($id)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET user_type = 'superadmin' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function delete($id)
    {
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();

            // 1. Verificar e excluir simulações associadas
            $stmtSimulacoes = $pdo->prepare("SELECT id FROM simulacoes WHERE user_id = ?");
            $stmtSimulacoes->execute([$id]);
            $simulacoes = $stmtSimulacoes->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($simulacoes)) {
                // Excluir planejamentos tributários associados a essas simulações
                $placeholders = implode(',', array_fill(0, count($simulacoes), '?'));
                $stmtDeletePlanejamentos = $pdo->prepare("DELETE FROM planejamento_tributario WHERE simulacao_id IN ($placeholders)");
                $stmtDeletePlanejamentos->execute($simulacoes);

                // Excluir as simulações
                $stmtDeleteSimulacoes = $pdo->prepare("DELETE FROM simulacoes WHERE user_id = ?");
                $stmtDeleteSimulacoes->execute([$id]);
            }

            // 2. Excluir o usuário
            $stmtUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmtUser->execute([$id]);

            $pdo->commit();
            return $result;
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Log the error for debugging
            error_log("Erro ao excluir usuário e suas simulações: " . $e->getMessage());
            // Return false or throw an exception based on desired error handling
            return false;
        }
    }

    public static function deactivate($id)
    {
        return self::toggleStatus($id, 0);
    }

    public static function activate($id)
    {
        return self::toggleStatus($id, 1);
    }

    public static function getActiveCount()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE ativo = 1");
        return $stmt->fetchColumn();
    }

    public static function getAdminCount()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'admin' AND ativo = 1");
        return $stmt->fetchColumn();
    }

    public static function getSuperAdminCount()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'superadmin' AND ativo = 1");
        return $stmt->fetchColumn();
    }

    public static function getClientCount()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'client' AND ativo = 1");
        return $stmt->fetchColumn();
    }

    public static function getLeadCount()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'lead' AND ativo = 1");
        return $stmt->fetchColumn();
    }
}