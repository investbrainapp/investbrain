
<div {{ $attributes->class(['my-6' => !$attributes->has('class'), 'h-4 sm:h-auto' => $attributes->has('hide-on-mobile')]) }}>

    <hr class="{{ $attributes->has('hide-on-mobile') ? 'hidden sm:block' : '' }} border-t border-gray-200 dark:border-gray-700" />
</div>