@props(['title' => ''])

<div {{ $attributes->merge(['class' => 'flex mb-6']) }} class="">
    <h1 class="text-2xl font-medium mr-3"> {{ $title }} </h1>
    
    {{ $slot }}
</div>