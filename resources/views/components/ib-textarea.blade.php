@props([
    'uuid' => md5(rand()),
    'label' => '',
    'hint' => '',
    'errorField' => '',
    'rows' => 4
])

<div {{ $attributes->class([]) }}>
    {{-- STANDARD LABEL --}}
    @if($label)
        <label for="{{ $uuid }}" class="pt-0 label label-text font-semibold">
            <span>
                {{ $label }}

                @if($attributes->get('required'))
                    <span class="text-error">*</span>
                @endif
            </span>
        </label>
    @endif

    <textarea {{ $attributes
            ->merge([
                'id' => $uuid
            ])
            ->class([
                'textarea textarea-primary w-full peer',
                'border border-dashed' => $attributes->has('readonly') && $attributes->get('readonly') == true,
                'textarea-error' => $errors->has($errorField),
            ]) 
        }}
        x-data="{ 
            resize (rows) { 
                $el.style.height = '0px'; 
                $el.style.height = ($el.scrollHeight >= rows * 32 ? $el.scrollHeight : rows * 32) + 'px';
            } 
        }"
        x-init="resize({{$rows}})"
        @input="resize({{$rows}})"
        type="text" 
        placeholder = "{{ $attributes->whereStartsWith('placeholder')->first() }}"
    >{{ $slot }}</textarea>

    @if($errors->has($errorField))
        @foreach($errors->get($errorField) as $message)
            @foreach(Arr::wrap($message) as $line)
                <div class="{{ $errorClass }}" x-classes="text-red-500 label-text-alt p-1">{{ $line }}</div>
                @break($firstErrorOnly)
            @endforeach
            @break($firstErrorOnly)
        @endforeach
    @endif

    {{-- HINT --}}
    @if($hint)
        <div x-classes="label-text-alt text-gray-400 py-1 pb-0">{{ $hint }}</div>
    @endif
</div>

