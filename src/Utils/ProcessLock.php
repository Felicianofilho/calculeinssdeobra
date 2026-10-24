<?php
// Caminho: src/Utils/ProcessLock.php

require_once __DIR__ . '/Database.php';

class ProcessLock
{
    const LOCK_EXPIRATION_SECONDS = 10; // Tempo para considerar um lock expirado
    const ACQUIRE_TIMEOUT_SECONDS = 5;  // Tempo máximo de espera para adquirir o lock

    /**
     * Tenta adquirir um lock para um processo específico.
     *
     * @param string $lockName O nome do processo a ser bloqueado.
     * @return bool True se o lock foi adquirido, false caso contrário.
     */
    public static function acquireLock($lockName)
    {
        $startTime = time();
        $pdo = Database::getConnection();

        while (time() - $startTime < self::ACQUIRE_TIMEOUT_SECONDS) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM system_locks WHERE lock_name = ? FOR UPDATE");
                $stmt->execute([$lockName]);
                $lock = $stmt->fetch();

                if ($lock) {
                    $lockExpired = false;
                    if ($lock['locked_at'] !== null) {
                        $lockExpired = (time() - strtotime($lock['locked_at'])) > self::LOCK_EXPIRATION_SECONDS;
                    }

                    if (!$lock['is_locked'] || $lockExpired) {
                        $updateStmt = $pdo->prepare(
                            "UPDATE system_locks SET is_locked = 1, locked_at = NOW(), locked_by = ? WHERE lock_name = ?"
                        );
                        $updateStmt->execute([session_id(), $lockName]);
                        $pdo->commit();
                        return true; // Lock adquirido
                    }
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                // Log do erro, se necessário
                error_log("Erro ao tentar adquirir o lock: " . $e->getMessage());
                return false; // Falha ao tentar adquirir o lock
            }

            sleep(1); // Espera 1 segundo antes de tentar novamente
        }

        return false; // Timeout
    }

    /**
     * Libera um lock de processo.
     *
     * @param string $lockName O nome do processo a ser liberado.
     */
    public static function releaseLock($lockName)
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE system_locks SET is_locked = 0, locked_at = NULL, locked_by = NULL WHERE lock_name = ?");
            $stmt->execute([$lockName]);
        } catch (Exception $e) {
            // Log do erro, se necessário
            error_log("Erro ao liberar o lock: " . $e->getMessage());
        }
    }
}
