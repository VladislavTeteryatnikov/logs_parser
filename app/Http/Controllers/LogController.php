<?php

namespace App\Http\Controllers;

use App\Services\LogService;
use Illuminate\Http\Request;
use App\Models\Log;
use Illuminate\Validation\ValidationException;

class LogController extends Controller
{
    /**
     * Метод для показа таблицы и графиков
     *
     * @param Request $request Объект запроса
     * @param LogService $logService Сервис, куда вынесена основная логика
     */
    public function index(Request $request, LogService $logService)
    {
        // Проверяем диапазон дат
        try {
            $logService->validateDate($request->from, $request->to);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        // Данные для таблицы
        $tableData = $logService->getTableData($request);

        // Даты и кол-во запросов для графика 1
        $dates = $tableData->pluck('date');
        $countRequests = $tableData->pluck('countRequests');

        // ДАННЫЕ ДЛЯ ГРАФИКА 2

        // Топ-3 браузера за период c учетом фильтров
        $top3Browsers = $logService->getTop3Browsers($request);

        // Общее кол-во запросов по датам
        $totalByDay = $tableData->pluck('countRequests', 'date')->toArray();

        // Данные для графика 2
        $browserData = $logService->getBrowserData($request, $top3Browsers, $totalByDay);

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

    /**
     * Метод для обновления таблицы при сортировке (используется в ajax)
     *
     * @param Request $request Объект запроса
     * @param LogService $logService Сервис, куда вынесена основная логика
     */
    public function getTable(Request $request, LogService $logService)
    {
        // Данные для таблицы
        $tableData = $logService->getTableData($request);

        // Сортировка таблицы
        $sort = $request->get('sort');
        $direction = $request->get('direction', 'asc');
        $allowedSorts = ['date', 'countRequests', 'url', 'browser'];
        $tableData = $logService->sortTableData($tableData, $sort, $direction, $allowedSorts);

        return view('logs.parts.table', compact('tableData'))->render();
    }
}
