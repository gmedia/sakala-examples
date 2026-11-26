<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($waiting)
        <meta http-equiv="refresh" content="3">
    @endif
    <title>Antrean dan jadwal Laravel di Sakala</title>
    <meta name="description" content="Contoh Laravel dengan worker antrean dan jadwal di Sakala.">
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
        input {
            width: 100%;
            font: inherit;
            color: inherit;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.6rem 0.75rem;
        }
        input:focus-visible, button:focus-visible, a:focus-visible {
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
        dl.status { display: grid; grid-template-columns: max-content 1fr; gap: 0.35rem 1rem; margin: 0 0 1.5rem; background: var(--card); border: 1px solid var(--border); border-radius: 6px; padding: 1rem 1.25rem; }
        dl.status dt { color: var(--muted); }
        dl.status dd { margin: 0; }
        ul.reports { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
        ul.reports li { background: var(--card); border: 1px solid var(--border); border-radius: 6px; padding: 0.6rem 1rem; display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .done { color: var(--ok); }
        .queued { color: var(--warn); }
        .meta { color: var(--muted); font-size: 0.875rem; }
        footer { margin-top: 3rem; color: var(--muted); font-size: 0.875rem; }
    </style>
</head>
<body>
@php($local = fn ($time) => $time->copy()->setTimezone($timezone)->format('d-m-Y H.i T'))
<main>
    <h1>Antrean dan jadwal Laravel di Sakala</h1>
    <p class="lead">Formulir memasukkan job ke antrean, worker menjalankannya, dan jadwal menghapus laporan yang lebih tua dari {{ $keepDays }} hari.</p>

    <dl class="status" aria-label="Status">
        <dt>Antrean</dt>
        <dd id="queue-connection">
            <code>{{ $queueConnection }}</code>
            @if ($queueConnection === \App\Support\Backends::REDIS)
                (Valkey)
            @elseif ($queueConnection === \App\Support\Backends::QUEUE_NONE)
                (belum ada database atau Valkey)
            @endif
        </dd>
        <dt>Job terakhir</dt>
        <dd id="last-job">
            @if ($lastFinished)
                selesai {{ $local($lastFinished->finished_at) }} lewat <code>{{ $lastFinished->queue_connection }}</code>, {{ $lastFinished->waitSeconds() }} detik setelah dikirim
            @else
                belum ada
            @endif
        </dd>
        <dt>Jadwal terakhir</dt>
        <dd id="last-cleanup">
            @if ($lastCleanup)
                {{ $local($lastCleanup->created_at) }}, {{ $lastCleanup->deleted_reports }} laporan lama dihapus
            @else
                belum pernah berjalan
            @endif
        </dd>
    </dl>

    @if ($state === \App\Support\DatabaseState::NotConfigured)
        <section class="notice" aria-labelledby="no-db-title">
            <h2 id="no-db-title" style="margin-top: 0">Aplikasi sudah jalan, database belum dihubungkan</h2>
            <ol>
                <li>Di Console Sakala, buka halaman <strong>Database</strong>, buat database, lalu <strong>Pasang ke proyek</strong> ini.</li>
                <li>Pastikan <strong>Release command</strong> berisi <code>php artisan migrate --force</code>, lalu tekan <strong>Redeploy</strong>.</li>
            </ol>
            <p class="meta">PostgreSQL di layanan lain juga bisa: isi <code>DATABASE_URL</code> sebagai secret di tab <strong>Variabel</strong>.</p>
        </section>
    @elseif ($state === \App\Support\DatabaseState::Unavailable)
        <section class="notice error" role="alert">
            <h2 style="margin-top: 0">Database belum bisa dibaca</h2>
            <p>Pastikan <strong>Release command</strong> berisi <code>php artisan migrate --force</code>, lalu lihat detailnya di Logs.</p>
        </section>
    @else
        @if (session('queued'))
            <p class="notice ok" role="status">Laporan masuk antrean. Halaman memuat ulang sendiri sampai worker selesai.</p>
        @endif

        <h2 id="form-title">Buat laporan</h2>
        <form method="post" action="{{ route('reports.store') }}" aria-labelledby="form-title" novalidate>
            @csrf
            <div>
                <label for="title">Judul</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" maxlength="{{ $titleMaxLength }}" required
                       @error('title') aria-invalid="true" aria-describedby="title-error" @enderror>
                @error('title')<p id="title-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit">Masukkan ke antrean</button>
        </form>

        <h2>Laporan terbaru</h2>
        @if (count($reports) === 0)
            <p>Belum ada laporan.</p>
        @else
            <ul class="reports">
                @foreach ($reports as $report)
                    <li>
                        <span>{{ $report->title }}</span>
                        @if ($report->status === \App\Enums\ReportStatus::Done)
                            <span class="meta done">selesai lewat <code>{{ $report->queue_connection }}</code></span>
                        @else
                            <span class="meta queued">menunggu worker</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    <footer>
        <p>Cara memasang worker, jadwal, dan Valkey ada di README contoh ini dan di <a href="https://sakala.dev/docs/">dokumentasi Sakala</a>.</p>
    </footer>
</main>
</body>
</html>
