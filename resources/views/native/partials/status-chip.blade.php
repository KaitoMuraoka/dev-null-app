{{-- A commit's status when it isn't approved, as the web marks it: pending in amber, rejected in Rm Red. --}}
@php($small ??= false)
@php($rejected = $status === 'rejected')
<native:column class="{{ $small ? 'px-[6]' : 'ml-1 px-2 py-[2]' }} rounded-full {{ $rejected ? 'bg-theme-destructive/10' : 'bg-theme-pending-soft' }} flex-shrink-0">
    <native:text font="mono" class="{{ $small ? 'text-[11px]' : 'text-xs' }} {{ $rejected ? 'text-theme-destructive' : 'text-theme-pending' }}">{{ $status }}</native:text>
</native:column>
