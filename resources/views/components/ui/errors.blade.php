@props([
    'id' => null,
    'title' => null,
    'description' => null,
    'icon' => 'o-x-circle',
    'only' => []
])

<div>
    @if ($errors->any())
        <div {{ $attributes->class(["alert alert-error rounded rounded-sm"]) }} >
            <div class="grid gap-3">
                <div class="flex gap-2">
                    @if($title)
                        <x-icon :name="$icon" class="w-6 h-6 mt-0.5" />
                    @endif
                    <div>
                        @if($title)
                            <div class="font-bold text-lg">{{ $title }}</div>
                        @endif

                        @if($description)
                            <div class="font-semibold">{{ $description }}</div>
                        @endif
                    </div>
                </div>
                <div>
                    <ul class="list-disc ms-5 space-y-2 sm:ms-12 pb-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif
</div>
