@if ($error)
    <native:column ref="error" class="w-full px-4 py-3 bg-theme-surface">
        <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
    </native:column>
@endif
