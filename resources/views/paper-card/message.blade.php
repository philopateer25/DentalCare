@extends('paper-card.layout')

@section('content')
    <div class="big" aria-hidden="true">{{ $ok ? '✅' : '⚠️' }}</div>
    <p style="text-align:center" class="{{ $ok ? '' : 'error' }}">{{ $message }}</p>
    @if ($ok)
        <p class="muted" style="text-align:center">{{ __('You can close this page. The photos are on the computer now.') }}</p>
    @endif
@endsection
