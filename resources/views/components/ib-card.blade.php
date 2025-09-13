@props([
    'title' => '',
    'subTitle' => '',
    'dense' => false,
    'expanded' => false
])

<div 
    {{ $attributes->merge()->class(['p-5', 'shadow-sm', 'rounded-lg bg-base-200']) }} 
>
    @if($title)
        <h3 @class(['pb-2' => !$subTitle && !$dense, 'text-xl font-bold leading-none tracking-tight flex items-center truncate'])> {{ $title }} </h3>
    @endif
    
    @if($subTitle) 
        <h5 @class(['pb-2' => !$dense, 'text-sm text-gray-400 flex items-center truncate'])> {{ $subTitle }} </h5>
    @endif

    <div @class(['mt-2' => !$dense && !$expanded, 'mt-0' => $dense, 'mt-5' => $expanded])>
        {{ $slot }}
    </div>
</div>