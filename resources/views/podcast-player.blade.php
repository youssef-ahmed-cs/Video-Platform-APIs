<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $podcast->title }} - LaraStream Podcast</title>
    <meta name="description" content="{{ Str::limit($podcast->description, 160) }}">
    <meta property="og:title" content="{{ $podcast->title }}">
    <meta property="og:description" content="{{ Str::limit($podcast->description, 160) }}">
    @if($podcast->cover_image_url)
        <meta property="og:image" content="{{ $podcast->cover_image_url }}">
    @endif
    <meta property="og:type" content="music.song">
    <meta property="og:audio" content="{{ $streamUrl }}">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            max-width: 540px;
            width: 100%;
            padding: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .cover-wrap {
            width: 220px;
            height: 220px;
            margin: 0 auto 24px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            background: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cover-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cover-wrap svg {
            width: 80px;
            height: 80px;
            fill: #94a3b8;
        }

        .badges {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .badge {
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.3;
            color: #ffffff;
        }

        .author {
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .description {
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
            text-align: left;
            background: rgba(15, 23, 42, 0.5);
            padding: 14px 18px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            max-height: 120px;
            overflow-y: auto;
        }

        .player-box {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 16px;
            padding: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 16px;
        }

        audio {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            outline: none;
        }

        .speed-control {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
        }

        .speed-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #cbd5e1;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .speed-btn:hover, .speed-btn.active {
            background: #6366f1;
            color: #ffffff;
            border-color: #6366f1;
        }

        .meta-stats {
            display: flex;
            justify-content: space-around;
            color: #94a3b8;
            font-size: 13px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .meta-stats span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meta-stats svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="cover-wrap">
            @if(!empty($podcast->cover_image_url))
                <img src="{{ $podcast->cover_image_url }}" alt="{{ $podcast->title }}">
            @else
                <svg viewBox="0 0 24 24">
                    <path d="M12 2a4 4 0 0 0-4 4v6a4 4 0 0 0 8 0V6a4 4 0 0 0-4-4zm6 10a6 6 0 0 1-12 0H4a8 8 0 0 0 7 7.93V22h2v-2.07A8 8 0 0 0 20 12h-2z"/>
                </svg>
            @endif
        </div>

        <div class="badges">
            @if($podcast->season_number)
                <span class="badge">Season {{ $podcast->season_number }}</span>
            @endif
            @if($podcast->episode_number)
                <span class="badge">Episode {{ $podcast->episode_number }}</span>
            @endif
            @if($podcast->playlist)
                <span class="badge">{{ $podcast->playlist->name }}</span>
            @endif
        </div>

        <h1>{{ $podcast->title }}</h1>

        @if($podcast->user)
            <div class="author">By {{ $podcast->user->name }}</div>
        @endif

        @if(!empty($podcast->description))
            <div class="description">{{ $podcast->description }}</div>
        @endif

        <div class="player-box">
            <audio id="audio-player" controls preload="metadata" autoplay>
                <source src="{{ $streamUrl }}" type="{{ $podcast->mime_type ?: 'audio/mpeg' }}">
                Your browser does not support the audio element.
            </audio>

            <div class="speed-control">
                <span style="font-size: 12px; color: #94a3b8; align-self: center; margin-right: 4px;">Speed:</span>
                <button type="button" class="speed-btn active" onclick="setSpeed(1, this)">1x</button>
                <button type="button" class="speed-btn" onclick="setSpeed(1.25, this)">1.25x</button>
                <button type="button" class="speed-btn" onclick="setSpeed(1.5, this)">1.5x</button>
                <button type="button" class="speed-btn" onclick="setSpeed(2, this)">2x</button>
            </div>
        </div>

        <div class="meta-stats">
            <span>
                <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                {{ number_format($podcast->views) }} plays
            </span>
            @if($podcast->duration)
                <span>
                    <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                    {{ gmdate($podcast->duration >= 3600 ? 'H:i:s' : 'i:s', $podcast->duration) }}
                </span>
            @endif
        </div>
    </div>

    <script>
        const audio = document.getElementById('audio-player');
        function setSpeed(speed, btn) {
            if (audio) {
                audio.playbackRate = speed;
                document.querySelectorAll('.speed-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            }
        }
    </script>
</body>
</html>
