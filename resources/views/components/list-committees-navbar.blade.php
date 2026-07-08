<div class="border-b border-zinc-200 dark:border-zinc-700 mb-4">
    <flux:navbar>
        <flux:navbar.item href="{{ route('committees.list', ['uid' => $realm, 'search' => $search]) }}" icon="list-tree">{{ __('committees.treeView') }}</flux:navbar.item>
        <flux:navbar.item href="{{ route('committees.list.list', ['uid' => $realm, 'search' => $search]) }}" icon="list">{{ __('committees.listView') }}</flux:navbar.item>
    </flux:navbar>
</div>