@props(['points', 'color' => '#1B4B8F'])

@php $peak = max(1, ...array_map(fn ($point) => (float) $point['value'], $points)); @endphp

<div style="display:flex;align-items:flex-end;gap:6px;height:200px">
    @foreach ($points as $point)
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end">
            <span class="sfp-mono" style="font-size:10px;color:#16201D">{{ $point['display'] }}</span>
            <div title="{{ $point['title'] ?? $point['display'] }}" style="width:100%;background:{{ $color }};border-radius:6px 6px 0 0;height:{{ max(2, ((float) $point['value'] / $peak) * 78) }}%"></div>
            <span class="sfp-mono" style="font-size:9.5px;color:#AEBAB7;white-space:nowrap">{{ count($points) > 15 ? substr($point['label'], 0, 2) : $point['label'] }}</span>
        </div>
    @endforeach
</div>
