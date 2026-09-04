<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $podcast->title }} - {{ config('app.name', 'Laravel') }}</title>

    <!-- Meta Tags -->
    <meta name="description" content="{{ Str::limit($podcast->description, 160) }}">
    <meta property="og:title" content="{{ $podcast->title }}">
    <meta property="og:description" content="{{ Str::limit($podcast->description, 160) }}">
    @if($podcast->cover_image_url)
        <meta property="og:image" content="{{ $podcast->cover_image_url }}">
    @endif
    <meta property="og:url" content="{{ url('/' . $podcast->slug) }}">

    <!-- Fonts (Default Laravel UI Nunito) -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,600,700" rel="stylesheet">

    <!-- Bootstrap 5 & Bootstrap Icons (Default Laravel UI sample) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #212529;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar-brand {
            font-weight: 700;
            color: #0d6efd !important;
        }

        .podcast-cover {
            width: 140px;
            height: 140px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            background-color: #e9ecef;
        }

        .cover-fallback {
            width: 140px;
            height: 140px;
            border-radius: 8px;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 48px;
        }

        audio::-webkit-media-controls-panel {
            background-color: #f8f9fa;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1055;
        }
    </style>
</head>
<body>
    <div id="app">
        <!-- Default Laravel UI Navbar -->
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    <i class="bi bi-broadcast-pin"></i> {{ config('app.name', 'Laravel') }}
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link active" href="{{ url('/') }}">
                                <i class="bi bi-soundwave me-1"></i> Podcasts
                            </a>
                        </li>
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto gap-2">
                        <li class="nav-item">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyShareUrl()">
                                <i class="bi bi-share me-1"></i> Share
                            </button>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm" href="{{ $streamUrl }}" target="_blank">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Direct Stream
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="py-4">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 col-md-10">

                        <!-- Main Podcast Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <!-- Card Header -->
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    @if($podcast->playlist)
                                        <span class="badge bg-primary">{{ $podcast->playlist->name }}</span>
                                    @endif
                                    @if($podcast->season_number)
                                        <span class="badge bg-secondary">Season {{ $podcast->season_number }}</span>
                                    @endif
                                    @if($podcast->episode_number)
                                        <span class="badge bg-info text-dark">Episode {{ $podcast->episode_number }}</span>
                                    @endif
                                    @foreach($podcast->categories as $category)
                                        <span class="badge bg-light text-dark border">{{ $category->name }}</span>
                                    @endforeach
                                </div>

                                <span class="badge text-bg-light border">
                                    <i class="bi bi-eye me-1"></i> {{ number_format($podcast->views) }} views
                                </span>
                            </div>

                            <!-- Card Body -->
                            <div class="card-body p-4">
                                <div class="d-flex flex-column flex-sm-row gap-3 mb-4">
                                    @if(!empty($podcast->cover_image_url))
                                        <img src="{{ $podcast->cover_image_url }}" alt="{{ $podcast->title }}" class="podcast-cover flex-shrink-0 align-self-center align-self-sm-start">
                                    @else
                                        <div class="cover-fallback flex-shrink-0 align-self-center align-self-sm-start">
                                            <i class="bi bi-mic"></i>
                                        </div>
                                    @endif

                                    <div class="flex-grow-1">
                                        <h2 class="card-title fw-bold text-dark mb-2">{{ $podcast->title }}</h2>

                                        <p class="text-muted mb-2">
                                            <i class="bi bi-person-circle me-1"></i> By <span class="fw-semibold text-dark">{{ $podcast->user?->name ?? 'John Podcaster' }}</span>
                                            @if($podcast->user?->username)
                                                <small class="text-muted">({{'@' . $podcast->user->username}})</small>
                                            @endif
                                        </p>

                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-calendar3 me-1"></i> Released: {{ $podcast->created_at ? $podcast->created_at->format('M d, Y') : 'Recent' }}
                                            @if($podcast->duration)
                                                &bull; <i class="bi bi-clock me-1"></i> {{ gmdate($podcast->duration >= 3600 ? 'H:i:s' : 'i:s', $podcast->duration) }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <!-- Audio Player Box -->
                                <div class="bg-light p-3 rounded-3 border mb-4">
                                    <audio id="audio-player" class="w-100" controls preload="metadata" autoplay>
                                        <source src="{{ $streamUrl }}" type="{{ $podcast->mime_type ?: 'audio/mpeg' }}">
                                        Your browser does not support the audio element.
                                    </audio>

                                    <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                                        <small class="text-muted">
                                            <i class="bi bi-soundwave me-1"></i> Streaming from application
                                        </small>

                                        <div class="btn-group btn-group-sm" role="group" aria-label="Playback speed">
                                            <button type="button" class="btn btn-outline-secondary" onclick="setSpeed(0.75, this)">0.75x</button>
                                            <button type="button" class="btn btn-outline-secondary active" onclick="setSpeed(1, this)">1x</button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="setSpeed(1.25, this)">1.25x</button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="setSpeed(1.5, this)">1.5x</button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="setSpeed(2, this)">2x</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description -->
                                @if(!empty($podcast->description))
                                    <h5 class="fw-bold mb-2">About This Episode</h5>
                                    <p class="text-secondary mb-4" style="white-space: pre-line; line-height: 1.6;">{{ $podcast->description }}</p>
                                @endif

                                <!-- Actions Footer -->
                                <div class="border-top pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="copyShareUrl()">
                                        <i class="bi bi-link-45deg me-1"></i> Copy Share Link
                                    </button>

                                    <small class="text-muted">
                                        Slug: <code>{{ $podcast->slug }}</code>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Related Episodes (Default Laravel UI List Group) -->
                        @if(!empty($relatedPodcasts) && count($relatedPodcasts) > 0)
                            <div class="card shadow-sm border-0">
                                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold mb-0">
                                        <i class="bi bi-collection-play me-1"></i> More Episodes
                                    </h6>
                                    <span class="badge bg-secondary rounded-pill">{{ count($relatedPodcasts) }}</span>
                                </div>

                                <div class="list-group list-group-flush">
                                    @foreach($relatedPodcasts as $item)
                                        <a href="{{ url('/' . $item->slug) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 {{ $item->id === $podcast->id ? 'active' : '' }}">
                                            <div class="d-flex align-items-center gap-3">
                                                @if(!empty($item->cover_image_url))
                                                    <img src="{{ $item->cover_image_url }}" alt="{{ $item->title }}" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px;">
                                                @else
                                                    <div style="width: 48px; height: 48px; background-color: #e9ecef; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #6c757d;">
                                                        <i class="bi bi-mic"></i>
                                                    </div>
                                                @endif

                                                <div>
                                                    <h6 class="mb-1 fw-bold text-dark">{{ $item->title }}</h6>
                                                    <small class="text-muted">{{ $item->user?->name ?? 'Creator' }} &bull; {{ number_format($item->views) }} views</small>
                                                </div>
                                            </div>

                                            <span class="btn btn-outline-primary btn-sm rounded-pill">
                                                <i class="bi bi-play-fill me-1"></i> Listen
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap Toast -->
    <div class="toast-container toast-notification">
        <div id="copyToast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle-fill text-success me-2"></i> Link copied to clipboard!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const audio = document.getElementById('audio-player');

        function setSpeed(speed, btn) {
            if (audio) {
                audio.playbackRate = speed;
                document.querySelectorAll('.btn-group button').forEach(el => el.classList.remove('active'));
                if (btn) btn.classList.add('active');
            }
        }

        function copyShareUrl() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                const toastEl = document.getElementById('copyToast');
                if (toastEl) {
                    const toast = new bootstrap.Toast(toastEl, { delay: 2500 });
                    toast.show();
                }
            }).catch(() => {
                alert('Link: ' + window.location.href);
            });
        }
    </script>
</body>
</html>
