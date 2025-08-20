<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Facades\Agent;

class ParseLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:parse-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсит логи из файла и загружает в БД';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Путь к файлу
        $filePath = storage_path('logs/nginx/modimio.access.log');

        // Размер массива данных, которые вставим за один запрос
        $batchSize = 1000;

        // Буфер для данных
        $parsedData = [];

        // Количество вставленных строк
        $countData = 0;

        // Количество строк, которые не удалось вставить
        $countError = 0;

        if (!file_exists($filePath)) {
            $this->error("Файл не найден: $filePath");
            return 1;
        }

        $this->info("Начинаем парсинг: $filePath");

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Не удалось открыть файл");
            return 1;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                // Парсим строку
                $parsed = $this->parseLine($line);

                if ($parsed) {
                    $parsedData[] = $parsed;
                }

                // Вставляем данные пачками
                if (count($parsedData) >= $batchSize) {
                    try {
                        DB::table('logs')->insert($parsedData);
                        $countData += count($parsedData);
                    } catch (\Exception $e) {
                        $countError += count($parsedData);
                        $this->error("Ошибка при вставке данных: " . $e->getMessage());
                    }

                    // Очищаем буфер
                    $parsedData = [];
                }
            }

            // Вставляем остаток данных, если есть
            if (!empty($parsedData)) {
                try {
                    DB::table('logs')->insert($parsedData);
                    $countData += count($parsedData);
                } catch (\Exception $e) {
                    $countError += count($parsedData);
                    $this->error("Ошибка при вставке данных: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            $this->error("Фатальная ошибка: " . $e->getMessage());
            fclose($handle);
            return 1;
        } finally {
            fclose($handle);
        }

        $this->info("Всего обработано записей: $countData");
        $this->info("Количество записей, которые не удалось обработать: $countError");

        return 0;
    }

    private function parseLine($line)
    {
        $pattern = '/^(\S+) \S+ \S+ \[([^]]+)\] "(\S+) ([^"]*) HTTP\/[\d.]+" \d+ \d+ "[^"]*" "([^"]*)"$/';

        if (!preg_match($pattern, $line, $matches)) {
            return null;
        }

        // ip, время, url и User-Agent
        $ip = $matches[1];
        $timestamp = Carbon::createFromFormat('d/M/Y:H:i:s O', $matches[2])->toDateTimeString();
        $url = $matches[4];
        $userAgent = empty($matches[5]) ? null : $matches[5];

        // Определяем браузер, ОС
        $browser = $os = $architecture = null;
        if ($userAgent) {
            Agent::setUserAgent($userAgent);

            // Если вернется false
            $browser = Agent::browser() ?: null;
            $os = Agent::platform() ?: null;

            // Определяем архитектуру
            if (preg_match('/\b(x86_64|Win64|x64)\b/i', $userAgent)) {
                $architecture = 'x64';
            } elseif (preg_match('/\b(i386|i686|Win32)\b/i', $userAgent)) {
                $architecture = 'x86';
            }  elseif (preg_match('/\b(arm64|aarch64|ARM|Apple\s+Silicon)\b/i', $userAgent)) {
                $architecture = 'arm64';
            }

        }

        return [
            'ip' => $ip,
            'request_time' => $timestamp,
            'url' => $url,
            'user_agent' => $userAgent,
            'browser' => $browser,
            'os' => $os,
            'architecture' => $architecture,
        ];
    }

}
