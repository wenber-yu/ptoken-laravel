<?php

declare(strict_types=1);

namespace Wenbo\PToken\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class KeyCommand extends Command
{
    protected $signature = 'ptoken:key {--env : 同时更新 .env 文件中的 PTOKEN_ENCRYPT_KEY}';

    protected $description = '生成一个 32 字节的随机加密密钥，用于 PToken AES-256-CBC 加密';

    public function handle(): int
    {
        $key = bin2hex(random_bytes(32));

        $this->info("生成的密钥（32 字节）：");
        $this->line("  <comment>{$key}</comment>");

        if ($this->option('env')) {
            $envPath = $this->laravel->basePath('.env');

            if (!File::exists($envPath)) {
                $this->error('.env 文件不存在');
                return self::FAILURE;
            }

            $envContent = File::get($envPath);

            if (str_contains($envContent, 'PTOKEN_ENCRYPT_KEY=')) {
                $envContent = preg_replace(
                    '/^PTOKEN_ENCRYPT_KEY=.*/m',
                    "PTOKEN_ENCRYPT_KEY={$key}",
                    $envContent
                );
                $this->info('.env 中的 PTOKEN_ENCRYPT_KEY 已更新');
            } else {
                $envContent .= "\nPTOKEN_ENCRYPT_KEY={$key}\n";
                $this->info('.env 中已追加 PTOKEN_ENCRYPT_KEY');
            }

            File::put($envPath, $envContent);
        } else {
            $this->warn('提示：添加 --env 参数可自动更新 .env 文件');
        }

        return self::SUCCESS;
    }
}
