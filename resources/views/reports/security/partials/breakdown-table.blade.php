<div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
        <thead>
            <tr>
                <th>Label</th>
                <th class="text-end">Incidents</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr data-report-breakdown="{{ $row['key'] }}">
                    <td>{{ $row['label'] }}</td>
                    <td class="text-end">{{ $row['total'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-secondary">
                        {{ $emptyMessage }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
