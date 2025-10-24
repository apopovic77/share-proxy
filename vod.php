<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Arkturian VOD Player</title>
    <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
    <style>
        :root { 
            --plyr-color-main: #8B9DC3;
            --text: #1e293b; --muted: #475569; --brand: #1f2937; --brand-2: #8B9DC3;
            --ring: rgba(148, 163, 184, .3); --surface: rgba(255, 255, 255, 0.98);
            --radius-lg: 16px; --radius-md: 12px; --radius-sm: 8px;
            --shadow-primary: 0 10px 30px rgba(0, 0, 0, .07);
            --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, Roboto, system-ui, sans-serif;
        }
        body { 
            background: linear-gradient(135deg, var(--text) 0%, #000000 100%); 
            margin: 0; font-family: var(--font-family); overflow: hidden; 
        }
        #player-container {
            position: relative; width: 100vw; height: 100vh;
            display: flex; justify-content: center; align-items: center;
        }
        .plyr { width: 100%; height: 100%; }
        
        /* Scaling Modes */
        #video.fit-mode { object-fit: contain; } /* Default */
        #video.fill-mode { object-fit: cover; }  /* Zoomed in */

        #player-container.hide-cursor { cursor: none; }
        .plyr__controls { display: none !important; }

        /* Custom UI Elements */
        #custom-ui-overlay, .nav-arrow, #info-overlay {
            opacity: 0; transition: opacity 0.3s;
        }
        #player-container.show-ui #custom-ui-overlay,
        #player-container.show-ui .nav-arrow,
        #player-container.show-ui #info-overlay {
            opacity: 1;
        }
        #custom-ui-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 25; pointer-events: none;
        }
        #play-pause-button {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            font-size: 80px; color: white; background: rgba(139, 157, 195, 0.6); border: none;
            border-radius: 50%; width: 120px; height: 120px;
            cursor: pointer; pointer-events: auto; backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        #player-container:not(.show-ui) #play-pause-button { opacity: 0; }
        #player-container.paused.show-ui #play-pause-button { opacity: 1; }
        #mute-button {
            position: absolute; top: 20px; right: 20px; font-size: 30px;
            color: white; background: none; border: none; cursor: pointer;
            pointer-events: auto;
        }
        #scale-mode-button {
            position: absolute; top: 20px; right: 70px; font-size: 28px;
            color: white; background: none; border: none; cursor: pointer;
            pointer-events: auto;
        }
        #fullscreen-button {
            position: absolute; top: 20px; right: 120px; font-size: 28px;
            color: white; background: none; border: none; cursor: pointer;
            pointer-events: auto;
        }
        #progress-bar-container {
            position: absolute; bottom: 20px; left: 2%; width: 96%; height: 5px;
            background-color: rgba(255,255,255,0.3); cursor: pointer;
            pointer-events: auto; border-radius: var(--radius-sm);
        }
        #progress-bar {
            width: 0%; height: 100%; background-color: var(--plyr-color-main);
        }
        .nav-arrow {
            position: absolute; top: 50%; transform: translateY(-50%); width: 50px; height: 50px;
            background-color: rgba(139, 157, 195, 0.6); color: white; border: none; border-radius: 50%;
            font-size: 24px; cursor: pointer; z-index: 20; display: flex; justify-content: center;
            align-items: center; pointer-events: auto; backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        #prev-video { left: 20px; }
        #next-video { right: 20px; }
                #info-overlay {
            position: absolute; bottom: 80px; left: 20px; right: 20px; padding: 15px;
            color: white;
            z-index: 20; opacity: 0; transition: opacity 0.3s; pointer-events: none;
        }
        #video-title { font-size: 1.5em; font-weight: bold; margin: 0 0 5px 0; }
        #video-description { font-size: 1.em; margin: 0; max-width: 70%; }
        #like-section {
            position: absolute; bottom: 15px; right: 15px; display: flex;
            align-items: center; cursor: pointer; pointer-events: auto;
        }
        #like-button { font-size: 24px; background: none; border: none; color: white; padding: 5px; }
        #like-count { font-size: 1.2em; margin-left: 5px; }
        
        /* Mobile Responsive Design for Video Player */
        @media (max-width: 768px) {
            #play-pause-button {
                width: 80px; 
                height: 80px; 
                font-size: 50px;
            }
            
            .nav-arrow {
                width: 40px; 
                height: 40px; 
                font-size: 18px;
            }
            
            #prev-video { left: 10px; }
            #next-video { right: 10px; }
            
            #mute-button, #scale-mode-button, #fullscreen-button {
                font-size: 20px;
                padding: 8px;
                min-width: 40px;
                min-height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            #mute-button { top: 10px; right: 10px; }
            #scale-mode-button { top: 10px; right: 55px; }
            #fullscreen-button { top: 10px; right: 100px; }
            
            #info-overlay {
                bottom: 60px; 
                left: 10px; 
                right: 10px; 
                padding: 12px;
            }
            
            #video-title { 
                font-size: 1.2em; 
                margin-bottom: 8px; 
            }
            
            #video-description { 
                font-size: 0.9em; 
                max-width: 90%; 
            }
            
            #like-section {
                bottom: 10px; 
                right: 10px;
            }
            
            #like-button { 
                font-size: 20px; 
                min-width: 40px; 
                min-height: 40px; 
            }
            
            #like-count { 
                font-size: 1em; 
            }
            
            #progress-bar-container {
                bottom: 10px; 
                left: 1%; 
                width: 98%; 
                height: 6px;
            }
        }
        
        @media (max-width: 480px) {
            #play-pause-button {
                width: 60px; 
                height: 60px; 
                font-size: 40px;
            }
            
            .nav-arrow {
                width: 35px; 
                height: 35px; 
                font-size: 16px;
            }
            
            #mute-button, #scale-mode-button, #fullscreen-button {
                font-size: 16px;
                min-width: 35px;
                min-height: 35px;
            }
            
            #scale-mode-button { right: 45px; }
            #fullscreen-button { right: 85px; }
            
            #video-title { 
                font-size: 1em; 
            }
            
            #video-description { 
                font-size: 0.8em; 
            }
            
            #like-button { 
                font-size: 18px; 
                min-width: 35px; 
                min-height: 35px; 
            }
            
            #like-count { 
                font-size: 0.9em; 
            }
        }
        
        /* Touch-friendly improvements for video player */
        @media (hover: none) and (pointer: coarse) {
            #play-pause-button {
                min-width: 80px; 
                min-height: 80px;
            }
            
            .nav-arrow {
                min-width: 44px; 
                min-height: 44px;
            }
            
            #mute-button, #scale-mode-button, #fullscreen-button {
                min-width: 44px; 
                min-height: 44px;
            }
            
            #like-button {
                min-width: 44px; 
                min-height: 44px;
            }
        }
        
        /* Landscape orientation on mobile */
        @media (max-width: 768px) and (orientation: landscape) {
            #info-overlay {
                bottom: 40px;
            }
            
            #progress-bar-container {
                bottom: 5px;
            }
            
            #like-section {
                bottom: 5px;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script src="https://cdn.plyr.io/3.7.8/plyr.js"></script>
</head>
<body>
    <div id="player-container" class="paused show-ui">
        <video id="video" class="fit-mode" playsinline autoplay></video>
        <div id="custom-ui-overlay">
            <button id="play-pause-button">▶</button>
            <button id="mute-button">🔊</button>
            <button id="scale-mode-button">⛶</button>
            <button id="fullscreen-button">⤡</button>
            <div id="progress-bar-container">
                <div id="progress-bar"></div>
            </div>
        </div>
        <div id="info-overlay">
            <h2 id="video-title">Loading...</h2>
            <p id="video-description"></p>
            <div id="like-section">
                <button id="like-button">♡</button>
                <span id="like-count">0</span>
            </div>
        </div>
        <button id="prev-video" class="nav-arrow">‹</button>
        <button id="next-video" class="nav-arrow">›</button>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const video = document.getElementById('video');
        const playerContainer = document.getElementById('player-container');
        const playPauseBtn = document.getElementById('play-pause-button');
        const muteBtn = document.getElementById('mute-button');
        const progressBarContainer = document.getElementById('progress-bar-container');
        const progressBar = document.getElementById('progress-bar');
        const scaleModeBtn = document.getElementById('scale-mode-button');
        const fullscreenBtn = document.getElementById('fullscreen-button');
        const urlParams = new URLSearchParams(window.location.search);
        const collectionId = urlParams.get('collection_id');
        const currentId = parseInt(urlParams.get('current_id'), 10);
        const singleSrc = urlParams.get('src');
        const titleEl = document.getElementById('video-title');
        const descriptionEl = document.getElementById('video-description');
        const likeButton = document.getElementById('like-button');
        const likeCountEl = document.getElementById('like-count');
        const prevButton = document.getElementById('prev-video');
        const nextButton = document.getElementById('next-video');

        let playlist = [];
        let currentIndex = -1;
        let player;
        let hls;
        const API_BASE_URL = 'https://api-storage.arkturian.com';
        const API_KEY = 'Inetpass1';

        player = new Plyr(video, { controls: [], clickToPlay: true, fullscreen: { enabled: true } });

        function updateUI(videoData) {
            // Use AI-generated title if available, fallback to title, then filename
            const displayTitle = videoData.ai_title || videoData.title || videoData.original_filename;
            titleEl.textContent = displayTitle;
            
            // Use AI-generated subtitle if available, fallback to description
            const displayDescription = videoData.ai_subtitle || videoData.description || '';
            descriptionEl.textContent = displayDescription;
            
            // Parse and display AI tags if available
            let aiTags = [];
            if (videoData.ai_tags) {
                try {
                    // Handle both JSON string and array formats
                    aiTags = typeof videoData.ai_tags === 'string' 
                        ? JSON.parse(videoData.ai_tags) 
                        : videoData.ai_tags;
                } catch (e) {
                    console.error('Error parsing ai_tags:', e);
                    aiTags = [];
                }
            }
            
            if (aiTags && aiTags.length > 0) {
                const tagsElement = document.getElementById('video-tags') || createTagsElement();
                displayTags(tagsElement, aiTags);
            } else {
                const existingTags = document.getElementById('video-tags');
                if (existingTags) existingTags.style.display = 'none';
            }
            
            likeCountEl.textContent = videoData.likes;
            likeButton.textContent = '♡';
        }
        
        function createTagsElement() {
            const tagsDiv = document.createElement('div');
            tagsDiv.id = 'video-tags';
            tagsDiv.style.marginTop = '8px';
            descriptionEl.parentNode.insertBefore(tagsDiv, descriptionEl.nextSibling);
            return tagsDiv;
        }
        
        function displayTags(tagsElement, tags) {
            tagsElement.innerHTML = '';
            tagsElement.style.display = 'block';
            
            tags.forEach(tag => {
                const tagSpan = document.createElement('span');
                tagSpan.className = 'tag';
                tagSpan.textContent = `#${tag}`;
                tagSpan.style.cssText = `
                    background: rgba(139, 157, 195, 0.3);
                    color: white;
                    padding: 4px 8px;
                    margin: 2px 4px 2px 0;
                    border-radius: 12px;
                    font-size: 0.85em;
                    display: inline-block;
                    backdrop-filter: blur(5px);
                `;
                tagsElement.appendChild(tagSpan);
            });
        }

        function loadSource(sourceUrl) {
            if (Hls.isSupported()) {
                if (!hls) hls = new Hls();
                hls.loadSource(sourceUrl);
                hls.attachMedia(video);
                // Prefer highest quality by default for VOD
                hls.on(Hls.Events.MANIFEST_PARSED, () => {
                    try {
                        const max = (hls.levels?.length || 1) - 1;
                        if (max >= 0) {
                            hls.currentLevel = max;
                        }
                    } catch (e) {}
                });
            } else { video.src = sourceUrl; }
        }

        function loadVideoByIndex(index) {
            if (index < 0 || index >= playlist.length) return;
            currentIndex = index;
            const videoData = playlist[currentIndex];
            if (collectionId) {
                const newUrl = `vod.php?collection_id=${collectionId}&current_id=${videoData.id}`;
                history.pushState({path: newUrl}, '', newUrl);
            }
            updateUI(videoData);
            loadSource(videoData.hls_url);
        }

        async function likeCurrentVideo() {
            if (currentIndex === -1) return;
            const videoData = playlist[currentIndex];
            try {
                const response = await fetch(`${API_BASE_URL}/storage/objects/${videoData.id}/like`, {
                    method: 'POST', headers: { 'X-API-KEY': API_KEY }
                });
                if (!response.ok) throw new Error('Failed to like video');
                const updatedVideo = await response.json();
                likeCountEl.textContent = updatedVideo.likes;
                playlist[currentIndex].likes = updatedVideo.likes;
                likeButton.textContent = '♥';
            } catch (error) { console.error("Error liking video:", error); }
        }

        if (collectionId) {
            const response = await fetch(`${API_BASE_URL}/storage/list?collection_id=${collectionId}`, { headers: { 'X-API-KEY': API_KEY } });
            if (response.ok) {
                const data = await response.json();
                playlist = data.items.filter(item => item.hls_url);
                if (playlist.length > 0) {
                    const initialIndex = playlist.findIndex(v => v.id === currentId);
                    loadVideoByIndex(initialIndex !== -1 ? initialIndex : 0);
                } else { titleEl.textContent = "No playable videos in this collection."; }
            } else { titleEl.textContent = "Error loading collection."; }
        } else if (currentId) {
            prevButton.style.display = 'none';
            nextButton.style.display = 'none';
            const response = await fetch(`${API_BASE_URL}/storage/objects/${currentId}`, { headers: { 'X-API-KEY': API_KEY } });
            if (response.ok) {
                const videoData = await response.json();
                if (videoData.hls_url) {
                    playlist = [videoData];
                    loadVideoByIndex(0);
                } else { titleEl.textContent = "Video is not ready for streaming."; }
            } else { titleEl.textContent = "Could not load video data."; }
        } else if (singleSrc) {
            titleEl.textContent = "Single Video";
            descriptionEl.style.display = 'none';
            likeButton.style.display = 'none';
            likeCountEl.style.display = 'none';
            prevButton.style.display = 'none';
            nextButton.style.display = 'none';
            loadSource(singleSrc);
        } else {
            titleEl.textContent = "No video source specified.";
        }

        playPauseBtn.addEventListener('click', () => player.togglePlay());
        muteBtn.addEventListener('click', () => player.muted = !player.muted);
        player.on('play', () => { playPauseBtn.style.opacity = 0; playerContainer.classList.remove('paused'); });
        player.on('pause', () => { playPauseBtn.style.opacity = 1; playerContainer.classList.add('paused'); });
        player.on('volumechange', () => { muteBtn.textContent = player.muted ? '🔇' : '🔊'; });
        player.on('timeupdate', () => {
            if (player.duration) {
                progressBar.style.width = `${(player.currentTime / player.duration) * 100}%`;
            }
        });
        progressBarContainer.addEventListener('click', (e) => {
            const rect = progressBarContainer.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            player.currentTime = percent * player.duration;
        });
        
        let uiTimeout;
        function hideUi() { playerContainer.classList.remove('show-ui'); }
        function showUi() {
            playerContainer.classList.add('show-ui');
            clearTimeout(uiTimeout);
            if (player.playing) { uiTimeout = setTimeout(hideUi, 2500); }
        }
        player.on('play', showUi);
        player.on('pause', showUi);
        playerContainer.addEventListener('mousemove', showUi);
        playerContainer.addEventListener('mouseleave', hideUi);
        nextButton.addEventListener('click', () => loadVideoByIndex((currentIndex + 1) % playlist.length));
        prevButton.addEventListener('click', () => loadVideoByIndex((currentIndex - 1 + playlist.length) % playlist.length));
        likeButton.addEventListener('click', likeCurrentVideo);

        let currentScaleMode = 'fit';
        scaleModeBtn.addEventListener('click', () => {
            if (currentScaleMode === 'fit') {
                video.classList.remove('fit-mode');
                video.classList.add('fill-mode');
                scaleModeBtn.innerHTML = '&#x26F6;'; // Fill icon
                currentScaleMode = 'fill';
            } else {
                video.classList.remove('fill-mode');
                video.classList.add('fit-mode');
                scaleModeBtn.innerHTML = '&#x26F6;'; // Fit icon
                currentScaleMode = 'fit';
            }
        });
        fullscreenBtn.addEventListener('click', () => {
            player.fullscreen.toggle();
        });
    });
</script>

</body>
</html>
