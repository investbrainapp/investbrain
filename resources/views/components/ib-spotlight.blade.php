@props([
    'id' => Str::uuid()->toString(),
    'shortcut' => "meta.g",
    'searchText' => "Search ...",
    'noResultsText' => "Nothing found.",
    'url' => null,
    'fallbackAvatar' => null,
]);

@php
    $url = $url ?? route('mary.spotlight', absolute: false);
@endphp


<div x-data="{
        value: '',
        results: [],
        open: false,
        maxDebounce: 250,
        debounceTimer: null,
        controller: new AbortController(),
        query: '',
        searchedWithNoResults: false,
        init(){
            if(this.open) {
                this.show()
            }            

            this.$watch('value', value => {
                this.$refs.progressBar.classList.remove('hidden');

                this.debounce(() => this.search(), this.maxDebounce)
            })
        },
        close() {
            this.open = false
            this.$refs.spotlightModal?.close()
        },
        show() {
            this.open = true;
            this.$refs.spotlightModal?.showModal();
        },
        focus() {
            setTimeout(() => {
                this.$refs.spotlightSearch.focus();
                this.$refs.spotlightSearch.select();
            }, 100)
        },
        debounce(fn, waitTime) {
            clearTimeout(this.debounceTimer)
            this.debounceTimer = setTimeout(() => fn(), waitTime)
        },
        async search() {
            $refs.spotlightSearch.focus()

            if (this.value == '') {
                this.results = []
                return
            }

            try {
                this.controller?.abort()
                this.controller = new AbortController();

                let response = await fetch(`{{$url}}?search=${this.value}&${this.query}`, { signal: this.controller.signal })
                this.results = await response.json()
            } catch(e) {
                console.log(e)
                return
            }

            this.$refs.progressBar.classList.add('hidden');

            Object.keys(this.results).length
                ? this.searchedWithNoResults = false
                : this.searchedWithNoResults = true
        }
    }"

    @keydown.window.prevent.{{ $shortcut }}="show(); focus();"
    @keydown.escape="close()"
    @keydown.up="$focus.previous()"
    @keydown.down="$focus.next()"
    @spotlight-open.window="show(); focus();"
>
    <x-ib-modal
        key="spotlight"
        x-ref="spotlightModal"
        class="backdrop-blur-sm rounded-box shadow-lg "
        box-class="absolute p-0 top-10 lg:top-24 h-auto w-full lg:max-w-3xl bg-transparent"
        no-padding="true"
    >
    
        <div class="relative">

            {{-- INPUT --}}
            <x-ib-input
                id="{{ $id }}"
                icon="o-magnifying-glass"
                x-model="value"
                x-ref="spotlightSearch"
                placeholder=" {{ $searchText }}"
                class="flex w-full input my-2 border-none outline-none shadow-none border-transparent focus:shadow-none focus:outline-none focus:border-transparent"
                @focus="$el.focus()"
                autofocus
                tabindex="1"
            />

            {{-- PROGRESS --}}
            <x-ib-progress 
                x-ref="progressBar" 
                class="absolute hidden left-0 bottom-0 w-full progress progress-secondary h-[2px]" 
                indeterminate="true"
            />
        </div>

        {{-- NO RESULTS --}}
        <template x-if="searchedWithNoResults && value != ''">
            <div class="text-base-content/50 p-3 spotlight-element">{{ $noResultsText }}</div>
        </template>

        {{-- RESULTS --}}
        <div class="-mx-1 mt-1" @click="close()" @keydown.enter="close()">
            <template x-for="(item, index) in results" :key="index">
                {{-- ITEM --}}
                <a x-bind:href="item.link" class="spotlight-element" wire:navigate tabindex="0">
                    <div class="p-3 hover:bg-base-100" >
                        <div class="flex gap-3 items-center">
                            {{-- ICON --}}
                            <template x-if="item.icon">
                                <div x-html="item.icon"></div>
                            </template>
                            {{-- AVATAR --}}
                            <template x-if="item.avatar && !item.icon">
                                <div>
                                    <img :src="item.avatar" class="rounded-full w-11 h-11" @if($fallbackAvatar) onerror="this.src='{{ $fallbackAvatar }}'" @endif />
                                </div>
                            </template>
                            <div class="flex-1 overflow-hidden whitespace-nowrap text-ellipsis truncate w-0">
                                {{-- NAME --}}
                                <div x-text="item.name" class="font-semibold truncate"></div>

                                {{-- DESCRIPTION --}}
                                <template x-if="item.description">
                                    <div x-text="item.description" class="text-base-content/50 text-sm truncate"></div>
                                </template>
                            </div>
                        </div>
                    </div>
                </a>
            </template>
            <div x-show="results.length" class="mb-3"></div>
        </div>

    </x-ib-modal>
</div>
