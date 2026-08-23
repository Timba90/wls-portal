@props(['label', 'value' => null])

<div class="grid grid-cols-3 gap-4 py-2 text-[12.5px]">
    <dt class="text-ink-muted">{{ $label }}</dt>
    <dd class="col-span-2 text-ink-base">
        {{ filled($value) ? $value : '—' }}
    </dd>
</div>
