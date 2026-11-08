<x-layouts.site :title="__('site.checkout.title')">
    <livewire:site.checkout :room-id="$roomId" :check-in="$checkIn" :check-out="$checkOut" :guests="$guests" />
</x-layouts.site>
