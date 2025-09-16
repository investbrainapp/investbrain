<span
    class="" 
    style="width:90em;overflow: hidden; white-space: nowrap;"
    title="{{ Number::currency($marketData->fifty_two_week_low ?? 0, $marketData->currency) }} - {{ Number::currency($marketData->fifty_two_week_high ?? 0, $marketData->currency) }}"
>

    @php
        // 52-week low must be a non-zero
        if (empty($marketData->fifty_two_week_low)) {
            $marketData->fifty_two_week_low = 1;
        }
    @endphp
    
    @for ($x = 0; $x < 10; $x++)
        @if ((($marketData->market_value - $marketData->fifty_two_week_low) * 100) / ($marketData->fifty_two_week_high - $marketData->fifty_two_week_low) > ($x * 10)) 
        
            &#9679;
            
        @else

            &#9675;
        @endif
    @endfor
</span>