@props(['id' => null, 'maxWidth' => null])

<x-ib-livewire-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }} :showClose="false">
    <div class="p-2">
        <div class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ $title }}
        </div>

        <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
            {{ $content }}
        </div>
    </div>

    <div class="flex flex-row items-center justify-end mt-3 p-2 text-end">
        {{ $footer }}
    </div>
</x-ib-livewire-modal>
