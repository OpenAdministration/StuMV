@props(['realm', 'username', 'name'])

<div class="max-w-[calc(100%_+_4rem)]! w-[calc(100%_+_3rem)]! sm:w-[calc(100%_+_4rem)]! flex flex-col -m-6! sm:-mx-8!">
    <div class="pt-6 px-6 sm:px-8 pb-3">
        <flux:heading size="xl" class="max-w-7xl mx-auto">{{ $name }}</flux:heading>
    </div>

    <x-navbar-profile :realm="$realm" :username="$username" />

    <div class="relative flex-1 p-6 sm:p-8 overflow-y-auto">
        {{ $slot }}
    </div>
</div>
