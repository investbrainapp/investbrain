
<div {{ $attributes->class(['h-4 sm:h-auto' => $attributes->has('hide-on-mobile')]) }}>
    <div {{ $attributes->class(['py-6' => !$attributes->has('class')]) }}>

        <hr class="hidden sm:block border-t border-gray-200 dark:border-gray-700" />
    </div>
</div>