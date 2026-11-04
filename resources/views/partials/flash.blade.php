@foreach (['success' => 'success', 'error' => 'danger', 'info' => 'info'] as $key => $color)
    @if (session($key))
        <div class="alert alert-{{ $color }} alert-dismissible fade show" role="alert">
            {{ session($key) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
@endforeach

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Destino de los avisos que los componentes Livewire emiten con $this->dispatch('flash', ...) --}}
<div id="livewire-flash"></div>

@once
    @push('scripts')
        <script>
            document.addEventListener('livewire:init', () => {
                const colors = { success: 'success', error: 'danger', info: 'info' };

                Livewire.on('flash', ({ type, message }) => {
                    const target = document.getElementById('livewire-flash');
                    if (!target) return;

                    const alert = document.createElement('div');
                    alert.className = `alert alert-${colors[type] ?? 'info'} alert-dismissible fade show`;
                    alert.setAttribute('role', 'alert');
                    alert.append(document.createTextNode(message));

                    const close = document.createElement('button');
                    close.type = 'button';
                    close.className = 'btn-close';
                    close.setAttribute('data-bs-dismiss', 'alert');
                    alert.append(close);

                    target.append(alert);
                });
            });
        </script>
    @endpush
@endonce
