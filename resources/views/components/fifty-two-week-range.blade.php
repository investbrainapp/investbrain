<span
    class="" 
    style="width:90em;overflow: hidden; white-space: nowrap;"
    title="{{ currency($low ?? 0) }} - {{ currency($high ?? 0) }}"
>

    @php
        // 52-week low must be a non-zero
        if (empty($low)) {
            $low = 1;
        }
    @endphp
    
    @for ($x = 0; $x < 10; $x++)
        @if ((($current - $low) * 100) / ($high - $low) > ($x * 10)) 
        
            &#9679;
            
        @else

            &#9675;
        @endif
    @endfor
</span>