@foreach ($documents as $document)
    <tr class="hover:bg-slate-50/70">
        <td class="px-5 py-4">
            <div class="font-semibold text-slate-800">{{ $document['number'] }}</div>
            @if ($document['number_badge_label'] ?? null)
                <div class="mt-2">
                    <x-ui.status-badge :label="$document['number_badge_label']" :tone="$document['number_badge_tone'] ?? 'red'" />
                </div>
            @endif
        </td>
        <td class="px-5 py-4 text-slate-700">{{ $document['name'] }}</td>
        <td class="px-5 py-4">
            @if (($document['type_tone'] ?? null) === 'red')
                <x-ui.status-badge :label="$document['type']" tone="red" />
            @else
                <span class="text-slate-600">{{ $document['type'] }}</span>
            @endif
        </td>
        <td class="px-5 py-4 text-slate-600">{{ $document['stage'] }}</td>
        <td class="px-5 py-4 text-slate-600">{{ $document['owner'] }}</td>
        <td class="px-5 py-4 text-slate-600">{{ $document['department'] }}</td>
        <td class="px-5 py-4 text-slate-600">{{ $document['date'] }}</td>
        <td class="px-5 py-4">
            <x-ui.status-badge :label="$document['status']" :tone="$document['tone']" />
        </td>
        <td class="px-5 py-4">
            <span class="rounded-md bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
                {{ $document['action'] }}
            </span>
        </td>
        <td class="px-5 py-4">
            <a href="{{ $document['detail_url'] }}" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                Detail
            </a>
        </td>
    </tr>
@endforeach
