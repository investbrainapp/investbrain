@props(['key' => 'dialog'])

<x-ui.modal
    :key="$key"
    box-class="max-w-xl"
    persistent="true"
    no-card="true"
    {{ $attributes }}
>

    <div class="p-5">
        <div class="text-xl font-bold text-primary-content">
            {{ $title }}
        </div>

        <div class="mt-2 text-sm text-secondary-content">
            {{ $content }}
        </div>
    

        <div class="flex flex-row items-center justify-end mt-8 text-end">
            {{ $footer }}
        </div>
    </div>
</x-ui.modal>
