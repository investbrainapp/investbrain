<x-layouts.guest>
    <div class="my-22">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
            <div class="w-24 mb-10">
                <x-ui.logo />
            </div>

            <div class="w-full sm:max-w-2xl mt-6 p-6 overflow-hidden sm:rounded-lg prose dark:prose-invert">
                {!! $terms !!}
            </div>
        </div>
    </div>
</x-layouts.guest>
