@props(['sides' => 6])
<svg {{ $attributes->class(['dice-icon']) }} viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
    @switch((int) $sides)
        @case(2)
            <circle cx="20" cy="20" r="15" /><circle cx="20" cy="20" r="11" /><path d="M20 12v16m-4-13h6a3 3 0 0 1 0 6h-4a3 3 0 0 0 0 6h6" />
            @break
        @case(4)
            <path d="M20 4 37 34H3L20 4Z M20 4v20L3 34m17-10 17 10" />
            @break
        @case(6)
            <rect x="5" y="5" width="30" height="30" rx="6" />
            @foreach([[13, 12], [27, 12], [13, 20], [27, 20], [13, 28], [27, 28]] as [$x, $y])<circle cx="{{ $x }}" cy="{{ $y }}" r="1.6" fill="currentColor" stroke="none" />@endforeach
            @break
        @case(8)
            <path d="m20 3 16 17-16 17L4 20 20 3Z M4 20h32M20 3l-6 17 6 17 6-17-6-17Z" />
            @break
        @case(10)
            <path d="m20 3 16 12-5 18-11 4L9 33 4 15 20 3Z M20 3v17m-16-5 16 5 16-5M9 33l11-13 11 13m-11-13v17" />
            @break
        @case(12)
            <path d="m20 3 14 8 3 16-10 10H13L3 27l3-16 14-8Z m0 8 9 7-3 11H14l-3-11 9-7Z M20 3v8M6 11l5 7m23-7-5 7m8 9-11 2m1 8-1-8m-13 8 1-8M3 27l11 2" />
            @break
        @default
            <path d="m20 3 16 9v16l-16 9-16-9V12l16-9Z M20 3 12 16h16L20 3ZM4 12l8 4-8 12m32-16-8 4 8 12M12 16l8 17 8-17M4 28l16 5 16-5M20 37v-4" />
    @endswitch
</svg>
