@props(['id' => null, 'maxWidth' => null])

<x-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="px-6 py-4">
        <div class="text-lg font-medium text-gray-900">
            {{ $title }}
        </div>

        <div class="mt-4 text-sm text-gray-600 break-words">
            {{ $content }}
        </div>
    </div>

    <div class="flex flex-row flex-wrap items-center justify-end gap-2 px-4 py-4 sm:px-6 bg-gray-100 text-end">
        {{ $footer }}
    </div>
</x-modal>
