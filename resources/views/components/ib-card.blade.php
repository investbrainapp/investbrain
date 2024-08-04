@props(['title' => ''])

<x-card 
    {{ $attributes->merge(['class' => 'bg-slate-100 dark:bg-base-200 rounded-lg']) }} 
>

    <h2 class="text-xl mb-2"> {{ $title }} </h2>

    {{ $slot }}
</x-card>