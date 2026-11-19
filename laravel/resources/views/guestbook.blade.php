<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buku tamu Laravel di Sakala</title>
    <meta name="description" content="Contoh aplikasi Laravel kecil yang di-deploy di Sakala.">
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f7f7f5;
            --fg: #1c1c1a;
            --muted: #55554f;
            --card: #ffffff;
            --border: #d6d6d0;
            --accent: #0b5cad;
            --ok: #1d6b35;
            --warn: #8a4b00;
            --error: #a32020;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #161615;
                --fg: #ededea;
                --muted: #b4b4ad;
                --card: #20201e;
                --border: #3a3a36;
                --accent: #7ab8f5;
                --ok: #7fd197;
                --warn: #f0b860;
                --error: #ff8a8a;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--fg);
            font: 1rem/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        main { max-width: 40rem; margin: 0 auto; padding: 2.5rem 1.25rem 4rem; }
        h1 { font-size: 1.75rem; line-height: 1.25; margin: 0 0 0.5rem; }
        h2 { font-size: 1.15rem; margin: 2rem 0 0.75rem; }
        p { margin: 0 0 1rem; }
        .lead { color: var(--muted); }
        a { color: var(--accent); }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.9em; }
        .notice {
            border: 1px solid var(--border);
            border-left: 4px solid var(--warn);
            background: var(--card);
            border-radius: 6px;
            padding: 1rem 1.25rem;
            margin: 1.5rem 0;
        }
        .notice.ok { border-left-color: var(--ok); }
        .notice.error { border-left-color: var(--error); }
        .notice ol { margin: 0.5rem 0 0; padding-left: 1.25rem; }
        form { display: grid; gap: 1rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
        input, textarea {
            width: 100%;
            font: inherit;
            color: inherit;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.6rem 0.75rem;
        }
        textarea { min-height: 6rem; resize: vertical; }
        input:focus-visible, textarea:focus-visible, button:focus-visible, a:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 2px;
        }
        .hint { color: var(--muted); font-size: 0.875rem; margin-top: 0.25rem; }
        .field-error { color: var(--error); font-size: 0.875rem; margin-top: 0.25rem; }
        button {
            justify-self: start;
            font: inherit;
            font-weight: 600;
            color: #fff;
            background: var(--accent);
            border: 0;
            border-radius: 6px;
            padding: 0.6rem 1.25rem;
            cursor: pointer;
        }
        @media (prefers-color-scheme: dark) { button { color: #0d1b2a; } }
        ul.entries { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.75rem; }
        ul.entries li { background: var(--card); border: 1px solid var(--border); border-radius: 6px; padding: 0.75rem 1rem; }
        .meta { color: var(--muted); font-size: 0.875rem; }
        footer { margin-top: 3rem; color: var(--muted); font-size: 0.875rem; }
    </style>
</head>
<body>
<main>
    <h1>Buku tamu Laravel di Sakala</h1>
    <p class="lead">Aplikasi Laravel kecil ini menulis dan membaca baris di PostgreSQL. Tabelnya dibuat oleh migration yang Sakala jalankan lewat perintah rilis <code>php artisan migrate --force</code>.</p>

    @if ($state === \App\Support\GuestbookState::NotConfigured)
        <section class="notice" aria-labelledby="no-db-title">
            <h2 id="no-db-title" style="margin-top: 0">Aplikasi sudah jalan, database belum dihubungkan</h2>
            <p>Halaman ini tetap tampil walau belum ada database. Untuk mengaktifkan buku tamu:</p>
            <ol>
                <li>Siapkan database PostgreSQL di layanan lain.</li>
                <li>Di tab <strong>Environment Variables</strong> project-mu, isi <code>DATABASE_URL</code> sebagai rahasia, misalnya <code>postgres://user:password@host:5432/nama_db?sslmode=require</code>.</li>
                <li>Pastikan <strong>Release command</strong> berisi <code>php artisan migrate --force</code>, lalu tekan <strong>Redeploy</strong>.</li>
            </ol>
        </section>
    @elseif ($state === \App\Support\GuestbookState::Unavailable)
        <section class="notice error" role="alert">
            <h2 style="margin-top: 0">Database belum bisa dibaca</h2>
            <p>Database sudah diatur, tetapi aplikasi gagal membacanya. Periksa variabel koneksi, pastikan <strong>Release command</strong> berisi <code>php artisan migrate --force</code>, lalu lihat detailnya di Logs.</p>
        </section>
    @else
        @if (session('saved'))
            <p class="notice ok" role="status">Pesanmu sudah tersimpan.</p>
        @endif

        <h2 id="form-title">Tinggalkan pesan</h2>
        <form method="post" action="{{ route('guestbook.store') }}" aria-labelledby="form-title" novalidate>
            @csrf
            <div>
                <label for="name">Nama</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="{{ $nameMaxLength }}" required autocomplete="name"
                       @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name')<p id="name-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="message">Pesan</label>
                <textarea id="message" name="message" maxlength="{{ $messageMaxLength }}" required
                          aria-describedby="message-hint @error('message') message-error @enderror"
                          @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                <p id="message-hint" class="hint">Paling panjang {{ $messageMaxLength }} karakter. Pesan tampil untuk semua pengunjung.</p>
                @error('message')<p id="message-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit">Kirim pesan</button>
        </form>

        <h2>Pesan terbaru</h2>
        @if (count($entries) === 0)
            <p>Belum ada pesan. Jadilah yang pertama.</p>
        @else
            <ul class="entries">
                @foreach ($entries as $entry)
                    <li>
                        <p style="margin: 0">{{ $entry->message }}</p>
                        <p class="meta" style="margin: 0.25rem 0 0">{{ $entry->name }} &middot; <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->format('d-m-Y H:i') }} UTC</time></p>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    <footer>
        <p>Ubah halaman ini di <code>resources/views/guestbook.blade.php</code>, push ke GitHub, lalu deploy ulang. Panduan lengkap ada di <a href="https://sakala.dev/docs/perintah-rilis">dokumentasi Sakala</a>.</p>
    </footer>
</main>
</body>
</html>
