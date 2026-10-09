@if ($photoUrl)
    <figure>
        <img src="{{ $photoUrl }}" alt="Foto jenis sampah tersimpan" loading="lazy" class="h-40 w-56 rounded-lg object-cover">
        <figcaption class="mt-2 text-sm">Foto saat ini. Unggah foto baru di bawah untuk menggantinya.</figcaption>
    </figure>
@endif
