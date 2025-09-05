@props([
    'label' => '',
])

<button 
    {{ $attributes->whereDoesntStartWith('class') }} 
    type="button" 
    {{ $attributes->class(['px-4 py-2 text-sm font-medium tracking-wide transition-colors duration-100 rounded-md']) }} 
>
    
    
    {{ $label }}
    
</button>