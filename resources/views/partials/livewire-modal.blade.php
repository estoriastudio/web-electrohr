{{-- Listener del evento "modal" de los componentes Livewire (docs/livewire-conventions.md §8). --}}
@once
    @push('scripts')
        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.on('modal', ({ id, action }) => {
                    const element = document.getElementById(id);
                    if (element) {
                        bootstrap.Modal.getOrCreateInstance(element)[action]();
                    }
                });
            });
        </script>
    @endpush
@endonce
