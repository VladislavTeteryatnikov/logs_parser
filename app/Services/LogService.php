<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LogService
{
    /**
     * Проверяет корректность диапазона дат
     *
     * @param string|null $from Дата от
     * @param string|null $to Дата до
     */
    public function validateDate($from, $to)
    {
        // не проверяем, если обе даты пустые
        if (!$from && !$to) {
            return;
        }

        // Валидируем формат
        $validator = Validator::make(['from' => $from, 'to' => $to], [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['date' => 'Некорректный формат даты.']);
        }

        // Парсим только если есть
        $startDate = $from ? Carbon::parse($from) : null;
        $endDate = $to ? Carbon::parse($to) : null;

        // Проверяем, что to >= from
        if ($startDate && $endDate && $endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'date' => 'Дата "до" не может быть раньше даты "от"'
            ]);
        }

        // Проверяем, что диапазон <= 365 дней
        if ($startDate && $endDate && $startDate->diffInDays($endDate) > 365) {
            throw ValidationException::withMessages([
                'date' => 'Диапазон дат не может превышать 1 год'
            ]);
        }

    }

    /**
     * Применить фильтры к запросу
     *
     * @param $query
     * @param \Illuminate\Http\Request $request
     */
    private function applyFilters($query, $request)
    {
        return $query
            ->when($request->filled('from'), fn($q) => $q->whereDate('request_time', '>=', $request->from))
            ->when($request->filled('to'), fn($q) => $q->whereDate('request_time', '<=', $request->to))
            ->when($request->filled('os'), fn($q) => $q->where('os', $request->os))
            ->when($request->filled('architecture'), fn($q) => $q->where('architecture', $request->architecture));
    }

    /**
     * Форматирует данные для таблицы
     *
     * @param $dailyStats
     */
    public function getTableData($dailyStats)
    {
        if ($dailyStats->isEmpty()) {
            return collect();
        }

        return $dailyStats->map(function ($day) {
            // Считаем и сортируем URL
            $urls = array_count_values(explode(',', $day->urls));
            arsort($urls);

            // Считаем и сортируем браузеры
            $browsers = array_count_values(explode(',', $day->browsers));
            arsort($browsers);

            return [
                'date' => $day->date,
                'countRequests' => $day->total_requests,
                'url' => array_key_first($urls),
                'browser' =>array_key_first($browsers),
            ];
        });
    }

    /**
     * Возвращает топ-3 самых используемых браузеров за период с учётом фильтров
     *
     * @param \Illuminate\Http\Request $request
     */
    public function getTop3Browsers($request)
    {
        $query = Log::selectRaw('browser, COUNT(*) as count')
            ->whereNotNull('browser');

        // Применяем фильтры
        $this->applyFilters($query, $request);

        return $query
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(3)
            ->pluck('browser');
    }

    /**
     * Формирует данные для графика доли топ 3 браузеров по дням
     *
     * @param array $top3Browsers Массив топ 3 браузеров
     * @param Request $request
     * @param array $totalByDay Массив: дата => общее кол-во запросов
     */
    public function getBrowserData($top3Browsers, $request, $totalByDay)
    {
        $browserData = [];

        foreach ($top3Browsers as $browser) {
            // кол-во запросов в день с этих 3-х браузеров с учетом фильров
            $browserCountRequests = Log::selectRaw('DATE(request_time) as date, COUNT(*) as count')
                ->where('browser', $browser);

            $this->applyFilters($browserCountRequests, $request);

            $browserCountRequests = $browserCountRequests
                ->groupBy('date')
                ->pluck('count', 'date')
                ->toArray();

            // Рассчитываем проценты
            $percentages = [];
            foreach ($totalByDay as $date => $totalRequests) {
                $count = $browserCountRequests[$date] ?? 0;
                $percentages[$date] = $totalRequests > 0
                    ? round(($count / $totalRequests) * 100, 1)
                    : 0;
            }

            $browserData[$browser] = $percentages;
        }

        return $browserData;
    }

    /**
     *
     *
     * @param Request $request
     */
    public function getDailyStats(Request $request)
    {
        // Агрегируем данные по дате
        $query = Log::selectRaw('
            DATE(request_time) as date,
            COUNT(*) as total_requests,
            GROUP_CONCAT(url) as urls,
            GROUP_CONCAT(browser) as browsers
        ');

        // Применяем фильтры
        $this->applyFilters($query, $request);

        return $query->groupBy('date')->orderBy('date')->get();
    }

    /**
     * Сортирует таблицу по указанному столбцу
     *
     * @param $tableData
     * @param $sort
     * @param $direction
     * @param $allowedSorts
     */
    public function sortTableData($tableData, $sort = null, $direction = 'asc', $allowedSorts = [])
    {
        if (!$sort || !in_array($sort, $allowedSorts)) {
            return $tableData;
        }

        if ($direction == 'asc') {
            $tableData = $tableData->sortBy($sort);
        } elseif ($direction == 'desc') {
            $tableData = $tableData->sortByDesc($sort);
        }

        return $tableData;
    }

}
