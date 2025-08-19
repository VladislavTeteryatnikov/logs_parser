<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверяет существование базы данных и создаёт её при необходимости';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $databaseName = config('database.connections.mysql.database');

        // Это позволяет подключиться к серверу MySQL без выбора конкретной БД
        config(['database.connections.mysql.database' => null]);

        try {
            // Подключаеся к mysql
            DB::connection('mysql')->getPdo();

            // Создаем БД, если ее нет
            DB::statement("CREATE DATABASE IF NOT EXISTS `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->info("База данных '$databaseName' готова к использованию");

            // Возвращаем конфиг
            config(['database.connections.mysql.database' => $databaseName]);

        } catch (\Exception $e) {
            config(['database.connections.mysql.database' => $databaseName]);
            $this->error("Ошибка подключения к mysql: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
