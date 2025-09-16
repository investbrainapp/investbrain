@props([
    'id' => Str::uuid()->toString(),
    'label' => null,
    'icon' => null,
    'hint' => null,
    'hintClass' => 'label-text-alt text-gray-400 py-1 pb-0',
    'hideProgress' => false,
    'cropAfterChange' => false,
    'changeText' => 'Change',
    'cropTitleText' => 'Crop image',
    'cropCancelText' => 'Cancel',
    'cropSaveText' => 'Crop',
    'cropConfig' => [],
    'cropMimeType' => 'image/png',

    'errorField' => null,
    'errorClass' => 'text-red-500 label-text-alt p-1',
    'omitError' => false,
    'firstErrorOnly' => false,
])

@php
    $modelName = $attributes->whereStartsWith('wire:model')->first();
    $errorFieldName = $errorField ?? $modelName;
    $id = $id == $modelName ? $modelName : "{$id}{$modelName}";

    $cropSetup = fn () => json_encode(array_merge([
            'autoCropArea' => 1,
            'viewMode' => 1,
            'dragMode' => 'move'
        ], $cropConfig));
@endphp

<div
    x-data="{
        progress: 0,
        cropper: null,
        justCropped: false,
        fileChanged: false,
        imagePreview: null,
        imageCrop: null,
        originalImageUrl: null,
        cropAfterChange: {{ json_encode($cropAfterChange) }},
        file: @entangle($attributes->wire('model')),
        init () {
            this.imagePreview = this.$refs.preview?.querySelector('img')
            this.imageCrop = this.$refs.crop?.querySelector('img')
            this.originalImageUrl = this.imagePreview?.src

            this.$watch('progress', value => {
                if (value == 100 && this.cropAfterChange && !this.justCropped) {
                    this.crop()
                }
            })
        },
        get processing () {
            return this.progress > 0 && this.progress < 100
        },
        close() {
            $refs.cropDialog.close()
            this.cropper?.destroy()
        },
        change() {
            if (this.processing) {
                return
            }

            this.$refs.file.click()
        },
        refreshImage() {
            this.progress = 1
            this.justCropped = false

            if (this.imagePreview?.src) {
                this.imagePreview.src = URL.createObjectURL(this.$refs.file.files[0])
                this.imageCrop.src = this.imagePreview.src
            }
        },
        crop() {
            $refs.cropDialog.showModal()
            this.cropper?.destroy()

            this.cropper = new Cropper(this.imageCrop, {{ $cropSetup() }});
        },
        revert() {
                $wire.$removeUpload('{{ $attributes->wire('model')->value }}', this.file.split('livewire-file:').pop(), () => {
                this.imagePreview.src = this.originalImageUrl
                })
        },
        async save() {
            $refs.cropDialog.close();

            this.progress = 1
            this.justCropped = true

            this.imagePreview.src = this.cropper.getCroppedCanvas().toDataURL()
            this.imageCrop.src = this.imagePreview.src

            this.cropper.getCroppedCanvas().toBlob((blob) => {
                blob.name = $refs.file.files[0].name
                @this.upload('{{ $attributes->wire('model')->value }}', blob,
                    (uploadedFilename) => {  },
                    (error) => {  },
                    (event) => { this.progress = event.detail.progress }
                )
            }, '{{ $cropMimeType }}')
        }
        }"

    x-on:livewire-upload-progress="progress = $event.detail.progress;"

    {{ $attributes->whereStartsWith('class') }}
>
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

    {{-- PROGRESS BAR --}}
    @if(! $hideProgress && $slot->isEmpty())
        <div class="h-1 -mt-5 mb-5">
            <progress
                x-cloak
                :class="!processing && 'hidden'"
                :value="progress"
                max="100"
                class="progress progress-success h-1 w-56"></progress>
        </div>
    @endif

    {{-- FILE INPUT --}}
    <input
        id="{{ $id }}"
        type="file"
        x-ref="file"
        @change="refreshImage()"

        {{
            $attributes->whereDoesntStartWith('class')->class([
                "file-input file-input-bordered file-input-primary",
                "hidden" => $slot->isNotEmpty()
            ])
        }}
    />

    @if ($slot->isNotEmpty())
        {{-- PREVIEW AREA --}}
        <div x-ref="preview" class="relative flex">
            <div
                wire:ignore
                @click="change()"
                :class="processing && 'opacity-50 pointer-events-none'"
                class="cursor-pointer hover:scale-105 transition-all tooltip"
                data-tip="{{ $changeText }}"
            >
                {{ $slot }}
            </div>
            {{-- PROGRESS --}}
            <div
                x-cloak
                :style="`--value:${progress}; --size:1.5rem; --thickness: 4px;`"
                :class="!processing && 'hidden'"
                class="radial-progress text-success absolute top-5 start-5 bg-neutral"
                role="progressbar"
            ></div>
        </div>

        {{-- CROP MODAL --}}
        <div @click.prevent="" x-ref="crop" wire:ignore>
            <x-ui.modal id="cropDialog{{ $id }}" x-ref="cropDialog" :title="$cropTitleText" separator class="backdrop-blur-sm" persistent @keydown.window.esc.prevent="" without-trap-focus>
                <img src="" />
                <x-slot:actions>
                    <x-ui.button :label="$cropCancelText" @click="close()" />
                    <x-ui.button :label="$cropSaveText" class="btn-primary" @click="save()" ::disabled="processing" />
                </x-slot:actions>
            </x-ui.modal>
        </div>
    @endif

    {{-- ERROR --}}
    @if(!$omitError && $errors->has($errorFieldName))
        @foreach($errors->get($errorFieldName) as $message)
            @foreach(Arr::wrap($message) as $line)
                <div class="{{ $errorClass }}" x-classes="text-red-500 label-text-alt p-1">{{ $line }}</div>
                @break($firstErrorOnly)
            @endforeach
            @break($firstErrorOnly)
        @endforeach
    @endif

    {{-- MULTIPLE --}}
    @error($modelName.'.*')
        <div class="text-red-500 label-text-alt p-1 pt-2">{{ $message }}</div>
    @enderror

    {{-- HINT --}}
    @if($hint)
        <div class="{{ $hintClass }}" x-classes="label-text-alt text-gray-400 py-1 pb-0">{{ $hint }}</div>
    @endif
</div>