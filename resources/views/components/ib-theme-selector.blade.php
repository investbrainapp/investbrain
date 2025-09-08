@props([
    'id' => Str::uuid()->toString(),
    'darkTheme' => 'dark',
    'lightTheme' => 'light',
    'hidden' => false,
])

<div class="{{ $hidden ? 'hidden' : '' }}">
    <label
        for="{{ $id }}"
        x-data="{
            theme: $persist(window.matchMedia('(prefers-color-scheme: dark)').matches ? '{{ $darkTheme }}' : '{{ $lightTheme }}').as('theme'),
            init() {
                if (this.theme == '{{ $darkTheme }}') {
                    this.$refs.sun.classList.add('swap-off');
                    this.$refs.moon.classList.add('swap-on');
                } else {
                    this.$refs.sun.classList.add('swap-on');
                    this.$refs.moon.classList.add('swap-off');
                }
                
                this.setToggle()
            },
            setToggle() {
                document.documentElement.setAttribute('data-theme', this.theme)
                this.$dispatch('theme-changed', this.theme)
            },
            toggle() {
                this.theme = this.theme == '{{ $lightTheme }}' ? '{{ $darkTheme }}' : '{{ $lightTheme }}'
                this.setToggle()
            }
        }"
        {{ $attributes->class(["swap swap-rotate"]) }}
    >
        <input id="{{ $id }}" type="checkbox" class="theme-controller opacity-0" @click="toggle()" :value="theme" />
        <x-ib-icon x-ref="sun" name="o-sun" x-cloak />
        <x-ib-icon x-ref="moon" name="o-moon" x-cloak />
    </label>
</div>
<script>
    document.documentElement.setAttribute("data-theme", localStorage.getItem("theme")?.replaceAll("\"", ""))
</script>