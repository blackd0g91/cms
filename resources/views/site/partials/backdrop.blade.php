{{--
    Behind every page of the site: an aurora in the templates' colors at the
    top, and contour lines that slowly shift (drawn by resources/js/backdrop.ts).
    Expects $colors, four CSS colors.
--}}
<div
    class="backdrop-aurora"
    style="--c1: {{ $colors[0] }}; --c2: {{ $colors[1] }}; --c3: {{ $colors[2] }}; --c4: {{ $colors[3] }}"
    aria-hidden="true"
>
    <span></span>
    <span></span>
</div>
<canvas class="backdrop-contours" data-contours data-colors="{{ json_encode($colors) }}" aria-hidden="true"></canvas>
