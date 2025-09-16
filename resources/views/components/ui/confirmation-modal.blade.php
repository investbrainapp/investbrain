@props(['id' => null])

<x-ui.modal :id="$id" box-class="max-w-xl" persistent="true" no-card="true" {{ $attributes }} >

    <div class="p-5">
        <div class="sm:flex sm:items-start">
            <div class="mx-auto shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>

            <div class="mt-3 text-center sm:mt-0 sm:ms-4 sm:text-start">
                <h3 class="text-xl font-bold text-primary-content">
                    {{ $title }}
                </h3>

                <div class="mt-2 text-sm text-secondary-content">
                    {{ $content }}
                </div>
            </div>
        </div>

        <div class="flex flex-row items-center justify-center sm:justify-end mt-8 text-end">
            {{ $footer }}
        </div>
    </div>
</x-ui.modal>
