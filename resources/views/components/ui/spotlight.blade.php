@props([
    'id' => null,
    'searchText' => "Search ...",
    'noResultsText' => "Nothing found.",
    'url' => null,
    'fallbackAvatar' => null,
])

@php
    $url = $url ?? route('spotlight', absolute: false);
@endphp

<div x-data="{
    loading: false,
    value: '',
    results: [],
    maxDebounce: 250,
    debounceTimer: null,
    controller: new AbortController(),
    query: '',
    searchedWithNoResults: false,
    openFirstResultWhenLoaded: false,
    init(){
        this.$watch('value', value => {
            this.loading = true
            this.openFirstResultWhenLoaded = false

            this.debounce(() => this.search(), this.maxDebounce)
        })
    },
    openSpotlight() {
        this.$dispatch('open-spotlight')
    },
    isTypingInField(element) {
        return element.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName)
    },
    handleShortcut(event) {
        if ((event.metaKey || event.ctrlKey) && !event.altKey && event.key?.toLowerCase() === 'k') {
            event.preventDefault()
            this.openSpotlight()

            return
        }

        if (event.key === '/' && !event.metaKey && !event.ctrlKey && !event.altKey && !this.isTypingInField(event.target)) {
            event.preventDefault()
            this.openSpotlight()
        }
    },
    openFirstResult() {
        if (this.value == '') {
            return
        }

        if (this.loading) {
            this.openFirstResultWhenLoaded = true

            return
        }

        if (!this.results.length) {
            return
        }

        this.$dispatch('close-spotlight')
        Livewire.navigate(this.results[0].link)
    },
    debounce(fn, waitTime) {
        clearTimeout(this.debounceTimer)
        this.debounceTimer = setTimeout(() => fn(), waitTime)
    },
    async search() {

        if (this.value == '') {
            this.results = [];
            this.loading = false
            return
        }

        try {
            this.controller?.abort()
            this.controller = new AbortController();

            let params = new URLSearchParams({ search: this.value })
            let response = await fetch(`{{ $url }}?${params}${this.query ? '&' + this.query : ''}`, { signal: this.controller.signal })
            this.results = await response.json()
        } catch(e) {
            console.log(e)
            return
        }

        this.loading = false

        Object.keys(this.results).length
            ? this.searchedWithNoResults = false
            : this.searchedWithNoResults = true

        if (this.openFirstResultWhenLoaded) {
            this.openFirstResultWhenLoaded = false
            this.openFirstResult()
        }
    }
}"
    @keydown.window="handleShortcut($event)"
>
    <x-ui.modal
        key="spotlight"
        class="backdrop-blur-sm shadow-xl"
        box-class="absolute top-10 lg:top-24 w-full lg:max-w-3xl "
        no-card="true"
        @keydown.up="$focus.wrap().previous()"
        @keydown.down="$focus.wrap().next()"
    >
        <div class="relative">

            {{-- CLOSE --}}
            <x-ui.button 
                title="{{ __('Close') }} (esc)"
                class="absolute top-1/2 -translate-y-1/2 right-4 btn btn-ghost hover:bg-transparent border-none shadow-none btn-xs select-none z-50" 
                @click="close()" 
                @focus="$focus.lastFocused().focus()"
            >
                <kbd class="kbd kbd-sm">ESC</kbd>
            </x-ui.button>

            {{-- INPUT --}}
            <x-ui.input
                id="{{ $id }}"
                icon="o-magnifying-glass"
                x-model="value"
                @keydown.enter.prevent="openFirstResult()"
                placeholder=" {{ $searchText }}"
                class="text-xl flex w-full input my-2 py-6 border-none outline-none shadow-none border-transparent focus:shadow-none focus:outline-none focus:border-transparent"
                @focus="$el.focus()"
                autofocus
                tabindex="1"
            />

            {{-- PROGRESS --}}
            <x-ui.progress 
                x-show="loading"
                class="z-60 absolute left-0 bottom-0 w-full progress progress-secondary h-[2px]" 
                indeterminate="true"
            />
        </div>

        {{-- NO RESULTS --}}
        <template x-if="searchedWithNoResults && value != ''">
            <div class="bg-base-100 text-base-content/50 p-4 spotlight-element">{{ $noResultsText }}</div>
        </template>

        {{-- RESULTS --}}
        <div 
            @click="close()"
            @keydown.enter="close()"
        >
            <template x-for="(item, index) in results" :key="index">

                {{-- ITEM --}}
                <a x-bind:href="item.link" class="spotlight-element" wire:navigate tabindex="0">
                    <div class="p-4 bg-base-100 hover:bg-base-200 rounded-md">
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

    </x-ui.modal>
</div>
