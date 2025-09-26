@props([
    'id' => null,
    'label' => null,
    'hint' => null,
    'hintClass' => 'label-text-alt text-gray-400 py-1 pb-0',
    'multiple' => false,
    'clearable' => true,
    'hideProgress' => false,

    'errorField' => null,
    'errorClass' => 'text-red-500 label-text-alt p-1',
    'omitError' => false,
    'firstErrorOnly' => false,
])

@php
    $modelName = $attributes->whereStartsWith('wire:model')->first();
    $errorFieldName = $errorField ?? $modelName;
    $id = $id == $modelName ? $modelName : "{$id}{$modelName}";
@endphp

<div
    class="container"
    x-data="{
        files: @entangle($modelName),
        progress: 0,
        selectFiles(e) {
            this.files = e.target.files[0].name

            $wire.upload('{{ $modelName }}', e.target.files[0], (uploadedFilename) => {
                // Success callback...
                this.progress = 0;

            }, () => {
                // Error callback...
            }, (event) => {
                
                this.progress = event.detail.progress
  
            }, () => {
                // Cancelled callback...
            })
        },
        reset(){
            this.files = null
            this.$refs.fileInput.value = null
        }
    }">

    {{-- STANDARD LABEL --}}
    @if($label)
    <label for="{{ $id }}" class="pt-0 label label-text font-semibold">
        <span>
            {{ $label }}

            @if($attributes->get('required'))
                <span class="text-error">*</span>
            @endif
        </span>
    </label>
    @endif
    
    <div {{ $attributes->class(['relative']) }}>

        {{-- PROGRESS BAR  --}}
        @if(!$hideProgress)
            <progress
                x-cloak
                max="100"
                :value="progress"
                :class="{'hidden': !progress}"
                class="progress h-1 absolute -mt-2 w-56">
            </progress>
        @endif
        
        <input
            type="file"
            x-ref="fileInput"
            id="{{ $id }}"
            {{ $multiple ? 'multiple="true"' : '' }}
            @change="selectFiles"
            {{
                $attributes->whereDoesntStartWith(['wire:model', 'class'])->class([
                    "file-input w-full",
                    "!file-input-error" => $errorFieldName && $errors->has($errorFieldName) && !$omitError
                ])
            }}
        >

        @if($clearable)
            <span :class="{'hidden': !files}">
                <x-ui.button 
                    type="reset" 
                    @click="reset" 
                    class="absolute top-2 right-2 btn btn-sm btn-ghost btn-circle"
                    icon="o-x-mark"
                ></x-ui.button> 
            </span>
        @endif
    </div>

    {{-- ERROR --}}
    @if(!$omitError && $errors->has($errorFieldName))
    @foreach($errors->get($errorFieldName) as $message)
        @foreach(Arr::wrap($message) as $line)
            <div class="{{ $errorClass }}" x-classes="text-error">{{ $line }}</div>
            @break($firstErrorOnly)
        @endforeach
        @break($firstErrorOnly)
    @endforeach
    @endif

    {{-- MULTIPLE --}}
    @error($modelName.'.*')
    <div class="text-error" x-classes="text-error">{{ $message }}</div>
    @enderror

    {{-- HINT --}}
    @if($hint)
    <div class="{{ $hintClass }}" x-classes="fieldset-label">{{ $hint }}</div>
    @endif
</div>