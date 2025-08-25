<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Анализ логов Nginx</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        .chart-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <h1 class="text-center mb-5">Анализ логов Nginx</h1>

    <!-- Фильтры -->
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <form method="GET" class="mb-4">
                <div class="row align-items-end">
                    <!-- Дата до -->
                    <div class="col-md-2">
                        <label for="from" class="form-label">От</label>
                        <input type="date" id="from"
                               name="from" class="form-control"
                               value="{{ request('from') }}"
                        >
                    </div>

                    <!-- Дата после -->
                    <div class="col-md-2">
                        <label for="to" class="form-label">До</label>
                        <input type="date" id="to"
                               name="to" class="form-control"
                               value="{{ request('to') }}"
                        >
                    </div>

                    <!-- ОС -->
                    <div class="col-md-3">
                        <label for="os" class="form-label">ОС</label>
                        <select id="os" name="os" class="form-select">
                            <option value="">Все</option>
                            @foreach($oses as $os)
                                <option value="{{ $os }}" {{ request('os') == $os ? 'selected' : '' }}>
                                    {{ $os }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Архитектура -->
                    <div class="col-md-3">
                        <label for="architecture" class="form-label">Архитектура</label>
                        <select id="architecture" name="architecture" class="form-select">
                            <option value="">Все</option>
                            @foreach($architectures as $arch)
                                <option value="{{ $arch }}" {{ request('architecture') == $arch ? 'selected' : '' }}>
                                    {{ $arch }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Кнопка -->
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Фильтровать</button>
                    </div>
                </div>

                <!-- Ошибки валидации -->
                @if($errors->any())
                    <div class="alert alert-danger mt-3 mb-0">
                        {{ $errors->first() }}
                    </div>
                @endif
            </form>
        </div>
    </div>

    @if(count($dates) > 0)

        <!-- Таблица -->
        <div class="row">
            <div id="logs-table" class="col-md-10 offset-md-1 mb-5">
                @include('logs.parts.table')
            </div>
        </div>

        <!-- График 1: Количество запросов -->
        <div class="row">
            <div class="col-md-10 offset-md-1 mb-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Общее количество запросов по дням</h5>
                        <div class="chart-container">
                            <canvas id="requestsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- График 2: Доля браузеров -->
        <div class="row">
            <div class="col-md-10 offset-md-1 mb-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Доля запросов для 3-х самых популярных браузеров по дням</h5>
                        <div class="chart-container">
                            <canvas id="browsersChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="text-muted text-center fs-4 mt-5">
            Нет данных для отображения
        </div>
    @endif

</div>


<script>
    /* Данные из Laravel */
    const dates = @json($dates);
    const countRequests = @json($countRequests);

    /* График 1 */
    if (dates.length > 0 && countRequests.length > 0) {
        new Chart(document.getElementById('requestsChart'), {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Число запросов',
                    data: countRequests,
                    borderColor: '#1a73e8',
                    backgroundColor: '#1a73e8',
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Дата'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Кол-во запросов, шт'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }



    /* Данные из Laravel */
    const browserData = @json($browserData);
    const top3Browsers = Object.keys(browserData);

    /* Цвета для браузеров */
    const colors = [
        '#4285F4',
        '#FF6D01',
        '#FBBC05',
    ];

    if (top3Browsers.length > 0) {
        /* Формируем datasets */
        const datasets = top3Browsers.map((browser, index) => {
            return {
                label: browser,
                data: browserData[browser] || Array(dates.length).fill(0),
                borderColor: colors[index],
                backgroundColor: colors[index],
                fill: false,
            };
        });

        /* График 2 */
        new Chart(document.getElementById('browsersChart'), {
            type: 'line',
            data: {
                labels: dates,
                datasets
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Дата'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Кол-во запросов, %'
                        },
                        min: 0,
                        max: 100
                    }
                }
            }
        });
    }

</script>
</body>
</html>
