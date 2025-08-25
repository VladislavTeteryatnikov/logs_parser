<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
     * @param Request $request
     */
    private function applyFilters($query, Request $request)
    {
        return $query
            ->when($request->filled('from'), fn($q) => $q->whereDate('request_time', '>=', $request->from))
            ->when($request->filled('to'), fn($q) => $q->whereDate('request_time', '<=', $request->to))
            ->when($request->filled('os'), fn($q) => $q->where('os', $request->os))
            ->when($request->filled('architecture'), fn($q) => $q->where('architecture', $request->architecture));
    }

    /**
     * Возвращает топ-3 самых используемых браузеров за период с учётом фильтров
     *
     * @param Request $request
     */
    public function getTop3Browsers(Request $request)
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
     * @param Request $request
     * @param array $top3Browsers Массив топ 3 браузеров
     * @param array $totalByDay Массив: дата => общее кол-во запросов
     */
    public function getBrowserData(Request $request, $top3Browsers, $totalByDay)
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
     * Данные для таблицы
     *
     * @param Request $request
     */
    public function getTableData(Request $request)
    {
        // Количество запросов по дням
        $countRequestsByDate = $this->getCountRequestsByDate($request);

        // Самый популярный браузер по дням
        $topBrowserByDate = $this->getTopBrowserByDate($request);

        // Самый популярный URL по дням
        $topUrlByDate = $this->getTopUrlByDate($request);

        $result = $countRequestsByDate->map(function ($item) use ($topBrowserByDate, $topUrlByDate) {
            $date = $item['date'];
            return [
                'date' => $date,
                'countRequests' => $item['total_requests'],
                'browser' => $topBrowserByDate->get($date),
                'url' => $topUrlByDate->get($date),
            ];
        });

        return $result;
    }

    /**
     * Общее количество запросов по дням (с учетом фильтров)
     *
     * @param Request $request
     */
    private function getCountRequestsByDate(Request $request)
    {
        $query = Log::selectRaw('
            DATE(request_time) as date,
            COUNT(*) as total_requests
        ');

        // Применяем фильтры
        $this->applyFilters($query, $request);

        return $query->groupBy('date')->orderBy('date')->get();
    }

    /**
     * Самый популярный браузер по дням (с учетом фильтров)
     *
     * @param Request $request
     */
    private function getTopBrowserByDate(Request $request)
    {
        $subQuery = Log::selectRaw('
            DATE(request_time) as date,
            browser,
            COUNT(*) as count_requests,
            ROW_NUMBER() OVER (PARTITION BY DATE(request_time) ORDER BY COUNT(*) DESC) as rn
        ')
            ->whereNotNull('browser')
            ->groupBy('date', 'browser');

        // Применяем филтры
        $this->applyFilters($subQuery, $request);

        return DB::query()
            ->from($subQuery, 'top_browsers')
            ->where('rn', 1)
            ->orderBy('date')
            ->pluck('browser', 'date');
    }

    /**
     * Самый популярный url по дням (с учетом фильтров)
     *
     * @param Request $request
     */
    private function getTopUrlByDate(Request $request)
    {
        $subQuery = Log::selectRaw('
            DATE(request_time) as date,
            url,
            COUNT(*) as count_requests,
            ROW_NUMBER() OVER (PARTITION BY DATE(request_time) ORDER BY COUNT(*) DESC) as rn
        ')
            ->groupBy('date', 'url');

        // Применяем фильтры
        $this->applyFilters($subQuery, $request);

        return DB::query()
            ->from($subQuery, 'top_urls')
            ->where('rn', 1)
            ->pluck('url', 'date');
    }

    /**
     * Сортирует таблицу по указанному столбцу
     *
     * @param $tableData
     * @param $sort
     * @param string $direction
     * @param array $allowedSorts
     */
    public function sortTableData($tableData, $sort = null, string $direction = 'asc', array $allowedSorts = [])
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
