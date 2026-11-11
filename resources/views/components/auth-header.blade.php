@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="text-2xl font-semibold">{{ $title }}</h1>
    <flux:subheading>{{ $description }}</flux:subheading>
</div>
