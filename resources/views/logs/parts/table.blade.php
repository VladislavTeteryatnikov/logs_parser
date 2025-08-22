<table class="table table-hover table-bordered">
    <thead class="table-primary">
    <tr>
        <th style="width: 10%;">
            <a href="#" class="sort-link text-dark text-decoration-none d-flex align-items-center"
               data-sort="date">
                Дата
                @if(request('sort') === 'date')
                    @if(request('direction') === 'asc')
                        <i class="fas fa-arrow-up ms-1 small"></i>
                    @else
                        <i class="fas fa-arrow-down ms-1 small"></i>
                    @endif
                @else
                    <i class="fas fa-sort ms-1 text-muted small"></i>
                @endif
            </a>
        </th>
        <th style="width: 15%;">
            <a href="#" class="sort-link text-dark text-decoration-none d-flex align-items-center"
               data-sort="countRequests">
                Число запросов
                @if(request('sort') === 'countRequests')
                    @if(request('direction') === 'asc')
                        <i class="fas fa-arrow-up ms-1 small"></i>
                    @else
                        <i class="fas fa-arrow-down ms-1 small"></i>
                    @endif
                @else
                    <i class="fas fa-sort ms-1 text-muted small"></i>
                @endif
            </a>
        </th>
        <th style="width: 50%;">
            <a href="#" class="sort-link text-dark text-decoration-none d-flex align-items-center"
               data-sort="url">
                Самый популярный URL
                @if(request('sort') === 'url')
                    @if(request('direction') === 'asc')
                        <i class="fas fa-arrow-up ms-1 small"></i>
                    @else
                        <i class="fas fa-arrow-down ms-1 small"></i>
                    @endif
                @else
                    <i class="fas fa-sort ms-1 text-muted small"></i>
                @endif
            </a>
        </th>
        <th style="width: 25%;">
            <a href="#" class="sort-link text-dark text-decoration-none d-flex align-items-center"
               data-sort="browser">
                Самый популярный браузер
                @if(request('sort') === 'browser')
                    @if(request('direction') === 'asc')
                        <i class="fas fa-arrow-up ms-1 small"></i>
                    @else
                        <i class="fas fa-arrow-down ms-1 small"></i>
                    @endif
                @else
                    <i class="fas fa-sort ms-1 text-muted small"></i>
                @endif
            </a>
        </th>
    </tr>
    </thead>
    <tbody>
    @foreach ($tableData as $data)
        <tr>
            <td>{{ $data['date'] }}</td>
            <td>{{ number_format($data['countRequests'], 0, '', ' ') }}</td>
            <td><code>{{ $data['url'] }}</code></td>
            <td>{{ $data['browser'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<script>
    $(document).ready(function () {
        $('.sort-link').on('click', function () {

            $('#logs-table table tbody').html(
                '<tr><td colspan="4" class="text-center"><span class="spinner-border spinner-border-sm" role="status"></span> Загрузка... </td></tr>'
            );

            const sort = $(this).data('sort');
            const direction = $(this).find('.fa-arrow-up').length ? 'desc' : 'asc';

            const urlParams = new URLSearchParams(window.location.search);
            const from = urlParams.get('from');
            const to = urlParams.get('to');
            const os = urlParams.get('os');
            const architecture = urlParams.get('architecture');

            $.get("{{ route('logs.table') }}", {
                sort: sort,
                direction: direction,
                from: from,
                to: to,
                os: os,
                architecture: architecture
            }, function (html) {
                $('#logs-table').html(html);

                const url = new URL(window.location);
                url.searchParams.set('sort', sort);
                url.searchParams.set('direction', direction);
                window.history.pushState({}, '', url);
            }).fail(function () {
                $('#logs-table').html(
                    '<div class="text-center"> Ошибка загрузки </div>'
                );
            });
        });
    });
</script>
