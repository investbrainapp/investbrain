@props([
    'sidebar' => null,
    'content' => null,
    'footer' => null,
    'fullWidth' => false,
    'withNav' => false,
    'collapseText' => 'Collapse',
    'collapseIcon' => 'o-bars-3-bottom-right',
    'collapsible' => false,
    'url' => route('mary.toogle-sidebar', absolute: false),
])

<main class="{{ !$fullWidth ? 'max-w-screen-2xl' : '' }} w-full mx-auto">
    <div class="drawer {{ $sidebar?->attributes['right'] ? 'drawer-end' : '' }} lg:drawer-open">
        <input id="{{ $sidebar?->attributes['drawer'] }}" type="checkbox" class="drawer-toggle" />

        <div {{ $content->attributes->class(["drawer-content w-full mx-auto p-5 lg:px-10 lg:py-5"]) }}>
            {{-- MAIN CONTENT --}}
            {{ $content }}
        </div>

        {{-- SIDEBAR --}}
        @if($sidebar)
            <div
                x-data="{
                    collapsed: {{ session('mary-sidebar-collapsed', 'false') }},
                    collapseText: '{{ $collapseText }}',
                    toggle() {
                        this.collapsed = !this.collapsed;
                        fetch('{{ $url }}?collapsed=' + this.collapsed);
                        this.$dispatch('sidebar-toggled', this.collapsed);
                    }
                }"
                @menu-sub-clicked="if(collapsed) { toggle() }"
                @class(["drawer-side z-20 lg:z-auto", "top-0 lg:top-[73px] lg:h-[calc(100vh-73px)]" => $withNav])
            >
                <label for="{{ $sidebar?->attributes['drawer'] }}" aria-label="close sidebar" class="drawer-overlay"></label>
                {{-- SIDEBAR CONTENT --}}
                <div>
                    
                    {{ $sidebar }}
                    
                    {{-- SIDEBAR COLLAPSE --}}
                    @if($sidebar->attributes['collapsible'])
                        <x-mary-menu class="hidden !bg-inherit lg:block">
                            <x-mary-menu-item
                                @click="toggle"
                                icon="{{ $sidebar->attributes['collapse-icon'] ?? $collapseIcon }}"
                                title="{{ $sidebar->attributes['collapse-text'] ?? $collapseText }}" />
                        </x-mary-menu>
                    @endif
                </div>
            </div>
        @endif
        {{-- END SIDEBAR--}}
    </div>
</main>

{{-- FOOTER --}}
@if($footer)
    <footer {{ $footer?->attributes->class(["mx-auto w-full", "max-w-screen-2xl" => !$fullWidth ]) }}>
        {{ $footer }}
    </footer>
@endif