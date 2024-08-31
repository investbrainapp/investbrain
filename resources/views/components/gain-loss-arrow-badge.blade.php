    
    @php
        if (isset($percent)) {

            $isUp = $percent > 0;

        } else {

            $isUp = $costBasis <= $marketValue;
            $percent = $costBasis ? (($marketValue - $costBasis) / $costBasis) : 0;
        }
        
    @endphp

    @if(!empty($percent))

        <x-badge class="badge-sm {{ $isUp ? 'badge-success' : 'badge-error' }} badge-outline ml-2">
            <x-slot:value>
                {!! $isUp ?  '&#9650;' :'&#9660;' !!}
                {{ Number::percentage(
                    $percent,
                    $percent < 1 ? 2 : 0
                ) }}
            </x-slot:value>
        </x-badge>
        
    @endif