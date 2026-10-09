@props([
    'eyebrow',
    'title',
    'description' => null,
    'icon' => 'fa-layer-group',
])

<header {{ $attributes->class(['page-hero']) }}>
    <div class="page-hero-main">
        <span class="page-hero-icon" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
        <div class="page-hero-copy">
            <div class="page-hero-eyebrow">{{ $eyebrow }}</div>
            <h2 class="page-hero-title">{{ $title }}</h2>
            @if($description)
                <p class="page-hero-description">{{ $description }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="page-hero-actions">{{ $actions }}</div>
    @endisset
</header>
