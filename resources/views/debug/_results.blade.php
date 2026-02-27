{{-- Reusable debug results table — include with @include('debug._results', ['results' => $results]) --}}
@if(!empty($results))
    <div class="border border-gray-200 rounded-lg overflow-hidden mt-4">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-gray-600 w-16">Status</th>
                    <th class="px-4 py-2 text-left text-gray-600">Test</th>
                    <th class="px-4 py-2 text-left text-gray-600">Detail</th>
                </tr>
            </thead>
            <tbody>
                @foreach($results as $r)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-2">
                            @if($r['pass'])
                                <span class="text-green-600 font-bold">✅</span>
                            @else
                                <span class="text-red-600 font-bold">❌</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $r['test'] }}</td>
                        <td class="px-4 py-2 text-gray-600 break-all">{{ $r['detail'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
