@extends('paper-card.layout')

@section('content')
    <h1>{{ __('Photograph the paper card') }}</h1>
    <p class="muted">
        @if ($firstName)
            {{ __('For :name.', ['name' => $firstName]) }}
        @endif
        {{ __('Link valid until :time.', ['time' => $expiresAt->timezone(config('app.timezone'))->format('H:i')]) }}
    </p>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('paper-card.store', ['token' => $token], absolute: false) }}" enctype="multipart/form-data" id="scan-form">
        @csrf
        <label class="pick">
            📷 {{ __('Take photos') }}
            <input type="file" name="photos[]" accept="image/*" capture="environment" multiple id="photos">
        </label>
        <p class="muted">{{ __('Up to :max photos: front, back, extra pages.', ['max' => $max]) }}</p>
        <div class="thumbs" id="thumbs"></div>
        <button type="submit" class="btn" id="send" disabled>{{ __('Save to patient file') }}</button>
    </form>

    <script>
        (function () {
            var input = document.getElementById('photos');
            var thumbs = document.getElementById('thumbs');
            var send = document.getElementById('send');
            var max = {{ (int) $max }};
            input.addEventListener('change', function () {
                thumbs.innerHTML = '';
                var files = Array.prototype.slice.call(input.files || [], 0, max);
                files.forEach(function (f) {
                    var img = document.createElement('img');
                    img.alt = '';
                    img.src = URL.createObjectURL(f);
                    thumbs.appendChild(img);
                });
                send.disabled = files.length === 0;
            });
            document.getElementById('scan-form').addEventListener('submit', function () {
                send.disabled = true;
                send.textContent = @json(__('Sending…'));
            });
        })();
    </script>
@endsection
