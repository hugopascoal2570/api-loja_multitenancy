<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup
        {--compress : Compactar o backup com gzip}
        {--keep=7 : Numero de dias para manter backups antigos}';

    protected $description = 'Realiza backup do banco de dados MySQL';

    public function handle(): int
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        // Verifica conectividade com o banco antes de tentar o dump
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->error("Nao foi possivel conectar ao banco: {$e->getMessage()}");
            return self::FAILURE;
        }

        $backupDir = base_path('backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "backup_{$database}_{$timestamp}.sql";
        $filepath = "{$backupDir}/{$filename}";

        $this->info("Iniciando backup do banco '{$database}'...");

        $dumpCmd = $this->buildDumpCommand($host, $port, $username, $password, $database);

        if (!$dumpCmd) {
            $this->error("Nao foi possivel montar o comando de backup.");
            return self::FAILURE;
        }

        $compress = $this->option('compress');

        if ($compress) {
            $filepath .= '.gz';
            $filename .= '.gz';
            $cmd = "{$dumpCmd} | gzip > " . escapeshellarg($filepath);
        } else {
            $cmd = "{$dumpCmd} > " . escapeshellarg($filepath);
        }

        $startTime = microtime(true);
        exec($cmd, $output, $exitCode);
        $duration = round(microtime(true) - $startTime, 2);

        if (!file_exists($filepath) || filesize($filepath) < 100) {
            $this->error("Falha no backup: arquivo vazio ou nao criado.");

            // Tenta capturar o erro
            exec($dumpCmd . ' 2>&1 | head -5', $errorOutput);
            if (!empty($errorOutput)) {
                $this->error(implode("\n", $errorOutput));
            }

            Log::error("Falha no backup do banco", [
                'database' => $database,
                'exit_code' => $exitCode,
            ]);

            if (file_exists($filepath)) {
                unlink($filepath);
            }

            return self::FAILURE;
        }

        $fileSize = $this->formatFileSize(filesize($filepath));

        $this->info("Backup concluido com sucesso!");
        $this->info("Arquivo: {$filename}");
        $this->info("Tamanho: {$fileSize}");
        $this->info("Duracao: {$duration}s");

        Log::info("Backup do banco realizado", [
            'database' => $database,
            'file' => $filename,
            'size' => $fileSize,
            'duration' => "{$duration}s",
        ]);

        $keepDays = (int) $this->option('keep');
        $this->cleanOldBackups($backupDir, $keepDays);

        return self::SUCCESS;
    }

    private function buildDumpCommand(string $host, string $port, string $username, string $password, string $database): ?string
    {
        $dumpFlags = '--single-transaction --routines --triggers --no-tablespaces --skip-ssl';

        // Usa MYSQL_PWD como env var para evitar problemas de escape com caracteres especiais
        $envPrefix = 'MYSQL_PWD=' . escapeshellarg($password);

        // Estrategia 1: mysqldump disponivel localmente
        if ($this->commandExists('mysqldump')) {
            $this->info("Usando mysqldump local");
            return sprintf(
                '%s mysqldump -h%s -P%s -u%s %s %s 2>/dev/null',
                $envPrefix,
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                $dumpFlags,
                escapeshellarg($database)
            );
        }

        // Estrategia 2: executar mysqldump no container MySQL via docker exec
        $container = $this->getDockerMysqlContainer();

        if ($container) {
            $this->info("Usando docker exec no container: {$container}");
            return sprintf(
                'docker exec -e MYSQL_PWD=%s %s mysqldump -u%s %s %s 2>/dev/null',
                escapeshellarg($password),
                escapeshellarg($container),
                escapeshellarg($username),
                $dumpFlags,
                escapeshellarg($database)
            );
        }

        $this->error("mysqldump nao encontrado e nenhum container MySQL disponivel.");
        return null;
    }

    private function commandExists(string $command): bool
    {
        exec("which {$command} 2>/dev/null", $output, $exitCode);
        return $exitCode === 0;
    }

    private function getDockerMysqlContainer(): ?string
    {
        exec('docker ps --format "{{.Names}}" 2>/dev/null', $containers);

        foreach ($containers as $container) {
            if (stripos($container, 'db') !== false || stripos($container, 'mysql') !== false) {
                return $container;
            }
        }

        return null;
    }

    private function cleanOldBackups(string $dir, int $keepDays): void
    {
        $cutoff = now()->subDays($keepDays)->timestamp;
        $files = glob("{$dir}/backup_*.sql*");
        $removed = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->info("Removidos {$removed} backup(s) com mais de {$keepDays} dias.");
        }
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
