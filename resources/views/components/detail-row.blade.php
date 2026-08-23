@props(['label', 'value' => null])

<div class="grid grid-cols-3 gap-4 py-2">
    <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
    <dd class="col-span-2 text-gray-800 dark:text-gray-100">
        {{ filled($value) ? $value : '—' }}
    </dd>
</div>
