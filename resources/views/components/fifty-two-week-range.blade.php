<span
    class="" 
    style="width:90em;overflow: hidden; white-space: nowrap;"
    title="{{ Number::currency($low) }} - {{ Number::currency($high) }}"
>
    
    @for ($x = 0; $x < 10; $x++)
        @if ((($current - $low) * 100) / ($high - $low) > ($x * 10)) 
        
            &#9679;
            
        @else

            &#9675;
        @endif
    @endfor
</span>