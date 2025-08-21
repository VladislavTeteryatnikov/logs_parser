<?php

namespace App\Http\Controllers;

use App\Services\LogService;
use Illuminate\Http\Request;
use App\Models\Log;
use Illuminate\Validation\ValidationException;

class LogController extends Controller
{
    public function index(Request $request, LogService $logService)
    {
        // Получаем фильтры по датам для валидации
        $from = $request->from;
        $to = $request->to;

        // Проверяем диапазон дат
        try {
            $logService->validateDate($from, $to);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        // Агрегируем данные по дате
        $query = Log::selectRaw('
            DATE(request_time) as date,
            COUNT(*) as total_requests,
            GROUP_CONCAT(url) as urls,
            GROUP_CONCAT(browser) as browsers
        ');

        // Применяем фильтры
        $logService->applyFilters($query, $request);

        $dailyStats = $query->groupBy('date')->orderBy('date')->get();

        // Формируем данные для таблицы
        $tableData = $logService->getTableData($dailyStats);

        // Получаем отдельно даты и кол-во запросов для графика 1
        $dates = $tableData->pluck('date');
        $countRequests = $tableData->pluck('countRequests');


        // ДАННЫЕ ДЛЯ ГРАФИКА 2

        // Топ-3 браузера за период c учетом фильтров
        $top3Browsers = $logService->getTop3Browsers($request);

        // Получаю общее кол-во запросов по датам
        $totalByDay = $dailyStats->pluck('total_requests', 'date')->toArray();

        // Получаем данные для графика 2
        $browserData = $logService->getBrowserData($top3Browsers, $request, $totalByDay);
        //dd($browserData);
        // Данные для фильтров select
        $oses = Log::query()->distinct()->pluck('os')->filter();
        $architectures = Log::query()->distinct()->pluck('architecture')->filter();

        return view('logs.index', compact(
            'tableData',
            'dates',
            'countRequests',
            'browserData',
            'oses',
            'architectures'
        ));
    }
}
