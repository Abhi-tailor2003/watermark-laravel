<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marked files</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="shell">
        <header class="masthead">
            <div>
                <p class="eyebrow">Private file desk</p>
                <h1>Mark it before it moves.</h1>
                <p class="lede">Every download is a processed copy with a visible record of the user IDs that handled it.</p>
            </div>
            <div class="status"><span></span> Processing online</div>
        </header>
        @if (session('success')) <div class="notice success">{{ session('success') }}</div> @endif
        @if ($errors->any()) <div class="notice error">{{ $errors->first() }}</div> @endif

        <section class="workspace">
            <form action="{{ route('uploads.store') }}" method="POST" enctype="multipart/form-data" class="upload-panel">
                @csrf
                <div class="panel-heading"><div><span class="step">01</span><h2>Upload a file</h2></div><span class="badge">Always watermarked</span></div>
                <label class="field-label" for="user_id">User ID</label>
                <input id="user_id" name="user_id" value="{{ old('user_id') }}" placeholder="e.g. reviewer-204" required>
                <p class="hint">This ID is printed into the file and retained when the same filename is uploaded again.</p>
                <label class="dropzone" for="file">
                    <input id="file" type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.xlsx,.xls,.ods,.csv" required>
                    <span class="upload-icon">+</span><strong>Choose a file</strong>
                    <span id="file-name">Images, PDF, Excel and OpenDocument sheets up to 50 MB</span>
                </label>
                <button type="submit">Add watermark <span>→</span></button>
            </form>

            <aside class="history-panel">
                <div class="panel-heading"><div><span class="step">02</span><h2>Recent files</h2></div><span class="count">{{ $uploads->count() }}</span></div>
                @forelse ($uploads as $upload)
                    <article class="file-row">
                        <div class="file-type">{{ strtoupper($upload->extension) }}</div>
                        <div class="file-info">
                            <strong title="{{ $upload->original_name }}">{{ $upload->original_name }}</strong>
                            <span>{{ count($upload->watermark_ids) }} watermark{{ count($upload->watermark_ids) === 1 ? '' : 's' }} · {{ $upload->created_at->diffForHumans() }}</span>
                            <small>{{ implode('  ·  ', $upload->watermark_ids) }}</small>
                        </div>
                        <a class="download" href="{{ route('uploads.download', $upload) }}" title="Download watermarked file">↓</a>
                    </article>
                @empty
                    <div class="empty"><span>◌</span><p>Your processed files will appear here.</p></div>
                @endforelse
            </aside>
        </section>
        <footer>Original uploads stay private. Only files that pass through the watermark service can be downloaded.</footer>
    </main>
    <script>
        document.querySelector('#file')?.addEventListener('change', (event) => {
            document.querySelector('#file-name').textContent = event.target.files[0]?.name || 'Images, PDF, Excel and OpenDocument sheets up to 50 MB';
        });
    </script>
</body>
</html>
