@props([
'path' => 'sample-photo.jpg',
'title' => 'Judul Default',
'photoId' => 'ss',
'date' => now()->format('d M Y'),
'isLoved' => false,
])

<div class="bg-white group cardFoto cursor-pointer shadow-md rounded-lg overflow-hidden relative" @click="if (leave) $event.stopImmediatePropagation()" x-data="{ leave : false, control : false, loved: {{ $isLoved ? 'true' : 'false' }}, isArsip: window.location.pathname.startsWith('/arsip') }" @mouseleave="if (!leave) control = false">
    <div class="aspect-square rounded-sm overflow-hidden" @mouseenter="control = true">
        <template x-if="loved">
            <div class="p-4 absolute">
                <div class="p-2 flex w-fit rounded-md bg-transparent">
                    <span class="icon-filled material-symbols-outlined !text-red-500">favorite</span>
                </div>
            </div>
        </template>
        <div x-show="control" x-transition class="flex justify-between flex-col p-4 absolute w-full h-full bg-gradient-to-t from-black/50 via-black/0 to-black/50 from-10% via-40% to-90%">

            <div class="flex items-center justify-between">
                <div>
                    <button type="button" x-show=" !isArsip && !leave " class="p-2 flex rounded-md bg-gray-200"
                        @click.stop="
                        const el = $event.currentTarget.closest('.cardFoto');

                        loved = !loved;
                        fetch('{{ route('foto.togglefavorite') }}', {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ id_foto: {{ $photoId }} })
                        })
                        .then(res => res.json())
                        .then(data => {
                            loved = data.is_favorite;
                            if (window.location.pathname.startsWith('/favorit')) {
                                if (el) el.remove();
                                window.location.reload();
                            }
                        })
                        .catch(err => console.error(err));
                    ">
                        <span class="icon-filled material-symbols-outlined" :class="{'!text-red-500': loved }">favorite</span>
                    </button>
                </div>
                <div class="p-2 flex">
                    <label class="cursor-pointer label" @click.stop>
                        <input type="checkbox" class="foto-multiple-selector p-3 rounded-md cursor-pointer bg-transparent border-white border-[2px]" />
                        <input type="hidden" class="id_carrier" value="{{ $photoId }}">
                    </label>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex flex-col">
                    <div class="text-xl font-semibold text-white">{{ $title }}</div>
                    <div class="text-sm mt-1 font-light text-white opacity-80">{{ $date }}</div>
                </div>
                <div x-show=" !leave " @click.stop>
                    {{ $slot }}
                </div>
            </div>
        </div>

        <img src="{{ route('foto.access', ['path' => $path ]) }}" alt="{{ $title }}" class="w-full h-full object-cover object-center" loading="lazy">
    </div>
</div>