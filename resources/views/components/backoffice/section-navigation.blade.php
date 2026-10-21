@props(['label'])

<nav aria-label="{{ $label }}" {{ $attributes->class(['backoffice-section-navigation']) }}>
    <ul class="backoffice-section-navigation-list">
        {{ $slot }}
    </ul>
</nav>
