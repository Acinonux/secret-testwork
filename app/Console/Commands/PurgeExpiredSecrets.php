<?php

namespace App\Console\Commands;

use App\Models\Secret;
use Illuminate\Console\Command;

class PurgeExpiredSecrets extends Command
{
    protected $signature = 'secrets:purge {--chunk=1000 : Сколько строк удалять за один запрос}';

    protected $description = 'Удалить секреты с истёкшим сроком жизни';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $total = 0;

        // Удаление порциями, чтобы не держать долгую блокировку таблицы.
        do {
            $deleted = Secret::expired()->limit($chunk)->delete();
            $total += $deleted;
        } while ($deleted === $chunk);

        $this->info("Удалено секретов: {$total}");

        return self::SUCCESS;
    }
}
