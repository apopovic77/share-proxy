<?php
require_once __DIR__ . '/config.php';
$config = get_app_config();

// Server-side Open Graph/Twitter Card for rich previews (Telegram, etc.)
$collectionId = isset($_GET['collection_id']) ? $_GET['collection_id'] : '';
$apiBase = $config['api_storage_base_url'];
$apiKey = 'Inetpass1';
$ogTitle = $collectionId ? ("Collection: " . $collectionId) : 'Images';
$ogDescription = 'Browse shared images and HLS videos.';
$ogImage = null;
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ogUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

if ($collectionId) {
    $url = $apiBase . '/storage/list?collection_id=' . urlencode($collectionId) . '&limit=50';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: ' . $apiKey]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $resp = curl_exec($ch);
    if ($resp !== false) {
        $data = json_decode($resp, true);
        if (isset($data['items']) && is_array($data['items'])) {
            $count = count($data['items']);
            if ($count > 0) {
                $ogDescription = $count . ' items in this collection.';
            }
            foreach ($data['items'] as $it) {
                $thumb = isset($it['thumbnail_url']) ? $it['thumbnail_url'] : null;
                $fileUrl = isset($it['file_url']) ? $it['file_url'] : null;
                $mime = isset($it['mime_type']) ? $it['mime_type'] : '';
                $isImage = $mime && strpos($mime, 'image/') === 0;
                if (!$thumb && $fileUrl && preg_match('/\.(png|jpe?g|webp|gif|bmp|tiff|heic|heif)(\?.*)?$/i', $fileUrl)) {
                    $thumb = $fileUrl;
                }
                if ($isImage || $thumb) { $ogImage = $thumb ?: $fileUrl; break; }
            }
        }
    }
    curl_close($ch);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Arkturian Images</title>
    <link rel="canonical" href="<?= htmlspecialchars($ogUrl) ?>"/>
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>"/>
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>"/>
    <meta property="og:type" content="website"/>
    <meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>"/>
    <?php if ($ogImage) { ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>"/>
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>"/>
    <?php } else { ?>
    <meta name="twitter:card" content="summary"/>
    <?php } ?>
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle) ?>"/>
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDescription) ?>"/>
    <style>
        :root{
            --text:#1e293b; --muted:#475569; --brand:#1f2937; --brand-2:#8B9DC3;
            --surface:#0b1220; --card:#0f172a; --ring:rgba(148,163,184,.3);
            --radius-lg:16px; --radius-md:12px; --radius-sm:8px;
            --gutter:12px;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Inter,Roboto,system-ui,sans-serif;background:linear-gradient(135deg,#0b1220,#000);color:#e5e7eb}
        header{position:sticky;top:0;background:rgba(15,23,42,.8);backdrop-filter:blur(8px);padding:16px 20px;border-bottom:1px solid rgba(148,163,184,.15);z-index:10}
        h1{margin:0;font-size:22px;font-weight:700;letter-spacing:.3px}
        main{padding:var(--gutter)}
        .masonry{column-count:6;column-gap:var(--gutter)}
        .item{position:relative;break-inside:avoid;background:var(--card);border-radius:10px;overflow:hidden;margin:0 0 var(--gutter);border:1px solid rgba(148,163,184,.15);opacity:0;transition:opacity .25s ease}
        .item.visible{opacity:1}
        .thumb{width:100%;display:block;background:#111;opacity:0;transition:opacity .3s ease;will-change:opacity}
        .thumb.loaded{opacity:1}
        
        .meta{padding:8px 10px}
        .imglink, .thumb { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
        .meta { border-top: 1px solid rgba(148,163,184,.12); }
        .title{margin:0 0 4px 0;font-size:13px;color:#e2e8f0;font-weight:600}
        .caption{margin:0;font-size:11px;color:#94a3b8}
        .toolbar{display:flex;gap:8px;align-items:center;margin-top:8px}
        .tag{font-size:11px;padding:3px 7px;border-radius:10px;background:rgba(139,157,195,.15);color:#cbd5e1}
        a.imglink{display:block}
        .imglink{position:relative;overflow:hidden;border-top-left-radius:10px;border-top-right-radius:10px;line-height:0}
        /* Small video badge for discovery */
        .badge-video{position:absolute;bottom:10px;left:10px;padding:4px 8px;border-radius:12px;border:1px solid rgba(148,163,184,.35);background:rgba(15,23,42,.55);backdrop-filter:blur(6px);color:#fff;font-size:12px;line-height:1;z-index:2}
        .del-btn{position:absolute;top:8px;right:8px;width:30px;height:30px;border-radius:15px;border:1px solid rgba(148,163,184,.35);background:rgba(220,53,69,.9);color:#fff;font-weight:700;cursor:pointer;display:none;align-items:center;justify-content:center;z-index:3}
        .item.editable .del-btn{display:flex}
        @media (max-width:1400px){.masonry{column-count:5}}
        @media (max-width:1100px){.masonry{column-count:4}}
        @media (max-width:900px){.masonry{column-count:3}}
        @media (max-width:600px){.masonry{column-count:2}}
        /* Keep two columns even on very small screens */
        /* @media (max-width:420px){.masonry{column-count:1}} */

        /* Lightbox overlay */
        #lightbox{position:fixed;top:0;left:0;width:100vw;height:100dvh;height:calc(var(--vh, 1vh) * 100);background:rgba(15,23,42,.45);backdrop-filter:blur(14px) saturate(1.1);-webkit-backdrop-filter:blur(14px) saturate(1.1);display:none;align-items:center;justify-content:center;z-index:9999}
        #lightbox img{width:100vw;height:inherit;object-fit:contain;display:block}
        #lightbox .stage{position:relative;width:100vw;height:100dvh;height:calc(var(--vh, 1vh) * 100);overflow:hidden;z-index:1}
        #lightbox .slide{position:absolute;top:0;left:0;width:100vw;height:100dvh;height:calc(var(--vh, 1vh) * 100);object-fit:contain;will-change:transform;transition:transform .25s ease}
        #lightbox .no-transition{transition:none !important}
        #lightbox .close-btn{z-index:2}
        #lightbox .info{z-index:2}
        #lightbox .close-btn{position:absolute;top:12px;right:12px;width:40px;height:40px;border-radius:20px;border:1px solid rgba(148,163,184,.35);background:rgba(15,23,42,.5);backdrop-filter:blur(6px);color:#fff;font-size:22px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center}
        #lightbox .close-btn:hover{background:rgba(15,23,42,.65)}
        #lightbox .info{position:absolute;top:12px;left:12px;padding:6px 10px;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:rgba(15,23,42,.5);backdrop-filter:blur(6px);color:#cbd5e1;font-size:12px;line-height:1}
        #lightbox .nav-btn{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:22px;border:1px solid rgba(148,163,184,.35);background:rgba(15,23,42,.5);backdrop-filter:blur(6px);color:#fff;font-size:22px;line-height:1;cursor:pointer;display:none;align-items:center;justify-content:center;z-index:2}
        #lightbox .nav-btn:hover{background:rgba(15,23,42,.65)}
        #lightbox .nav-prev{left:12px}
        #lightbox .nav-next{right:12px}
        @media (hover:hover) and (pointer:fine){
            #lightbox .nav-btn{display:flex}
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            // iOS Safari viewport height fix
            const setVh = () => {
                const vh = window.innerHeight * 0.01;
                document.documentElement.style.setProperty('--vh', `${vh}px`);
            };
            setVh();
            window.addEventListener('resize', setVh);
            window.addEventListener('orientationchange', setVh);
            const API_BASE_URL = '<?= js_config('api_storage_base_url'); ?>';
            const API_KEY = 'Inetpass1';
            const params = new URLSearchParams(window.location.search);
            const collectionId = params.get('collection_id');
            const pageTitle = document.getElementById('page-title');
            const grid = document.getElementById('grid');
            const status = document.getElementById('status');
            const rawAuto = (params.get('autoplay') || '').toLowerCase();
            const autoplayAll = rawAuto === 'all';
            const autoplayAny = ['1','true','yes','on','all'].includes(rawAuto);
            let didAutoplay = false;
            const editable = (params.get('editable') || '').toLowerCase() === 'true';

            // Lightbox setup + swipe navigation
            const lightboxEl = document.createElement('div');
            lightboxEl.id = 'lightbox';
            const stage = document.createElement('div');
            stage.className = 'stage';
            const slideCurrent = document.createElement('img');
            slideCurrent.className = 'slide';
            const slideNext = document.createElement('img');
            slideNext.className = 'slide';
            const info = document.createElement('div');
            info.className = 'info';
            info.textContent = '';
            const closeBtn = document.createElement('button');
            closeBtn.className = 'close-btn';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.textContent = '✕';
            const prevBtn = document.createElement('button');
            prevBtn.className = 'nav-btn nav-prev';
            prevBtn.setAttribute('aria-label', 'Previous');
            prevBtn.textContent = '‹';
            const nextBtn = document.createElement('button');
            nextBtn.className = 'nav-btn nav-next';
            nextBtn.setAttribute('aria-label', 'Next');
            nextBtn.textContent = '›';
            stage.appendChild(slideCurrent);
            stage.appendChild(slideNext);
            lightboxEl.appendChild(stage);
            lightboxEl.appendChild(info);
            lightboxEl.appendChild(closeBtn);
            lightboxEl.appendChild(prevBtn);
            lightboxEl.appendChild(nextBtn);
            document.body.appendChild(lightboxEl);
            let galleryUrls = [];
            let galleryIndex = -1;
            let transitionTimerId = null;
            let itemsForInfo = [];
            const setInfo = () => {
                const item = itemsForInfo[galleryIndex];
                const idText = item && item.id ? `id: ${item.id}` : 'id: -';
                info.textContent = `${idText} • ${galleryIndex+1}/${galleryUrls.length}`;
            };
            const showIndex = (idx, direction = 0) => {
                if(idx < 0 || idx >= galleryUrls.length) return;
                galleryIndex = idx;
                const url = galleryUrls[galleryIndex];
                lightboxEl.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                // initial show only (no animation here)
                slideCurrent.src = url;
                slideCurrent.style.transform = 'translateX(0)';
                slideNext.src = '';
                slideNext.style.transform = 'translateX(100vw)';
                setInfo();
            };
            let lightboxOpen = false;
            let reopenBlockUntil = 0;
            const openLightbox = (urlOrIndex) => {
                if(lightboxOpen) return; // prevent duplicate opens
                if(Date.now() < reopenBlockUntil) return; // cooldown after close
                lightboxOpen = true;
                if(typeof urlOrIndex === 'number'){
                    showIndex(urlOrIndex);
                } else if(urlOrIndex) {
                    const i = galleryUrls.indexOf(urlOrIndex);
                    showIndex(i >= 0 ? i : 0);
                }
            };
            const closeLightbox = () => {
                if(transitionTimerId){ clearTimeout(transitionTimerId); transitionTimerId = null; }
                lightboxEl.style.display = 'none';
                // Reset slides and state
                slideCurrent.classList.add('no-transition');
                slideNext.classList.add('no-transition');
                slideCurrent.src = '';
                slideNext.src = '';
                slideCurrent.style.transform = 'translateX(0)';
                slideNext.style.transform = 'translateX(100vw)';
                document.body.style.overflow = '';
                lightboxOpen = false;
                reopenBlockUntil = Date.now() + 450; // block immediate re-open from same tap
            };
            lightboxEl.addEventListener('click', (e) => {
                // Only close on backdrop clicks
                if(e.target === lightboxEl){ e.stopPropagation(); closeLightbox(); }
            });
            closeBtn.addEventListener('click', (e) => { e.stopPropagation(); closeLightbox(); });
            document.addEventListener('keydown', (e) => { if(e.key === 'Escape') closeLightbox(); });
            const goPrev = () => {
                if(galleryIndex > 0){
                    // simulate a right swipe commit
                    slideCurrent.classList.remove('no-transition');
                    slideNext.classList.remove('no-transition');
                    slideNext.classList.add('no-transition');
                    slideNext.src = galleryUrls[galleryIndex - 1];
                    slideNext.style.transform = `translateX(${-window.innerWidth}px)`;
                    void slideNext.offsetWidth; slideNext.classList.remove('no-transition');
                    galleryIndex = galleryIndex - 1;
                    slideCurrent.style.transform = `translateX(${window.innerWidth}px)`;
                    slideNext.style.transform = 'translateX(0)';
                    transitionTimerId = setTimeout(() => { swapSlides(); setInfo(); transitionTimerId = null; }, 260);
                }
            };
            const goNext = () => {
                if(galleryIndex < galleryUrls.length - 1){
                    // simulate a left swipe commit
                    slideCurrent.classList.remove('no-transition');
                    slideNext.classList.remove('no-transition');
                    slideNext.classList.add('no-transition');
                    slideNext.src = galleryUrls[galleryIndex + 1];
                    slideNext.style.transform = `${'translateX(' + window.innerWidth + 'px)'}`;
                    void slideNext.offsetWidth; slideNext.classList.remove('no-transition');
                    galleryIndex = galleryIndex + 1;
                    slideCurrent.style.transform = `translateX(${-window.innerWidth}px)`;
                    slideNext.style.transform = 'translateX(0)';
                    transitionTimerId = setTimeout(() => { swapSlides(); setInfo(); transitionTimerId = null; }, 260);
                }
            };
            prevBtn.addEventListener('click', (e) => { e.stopPropagation(); goPrev(); });
            nextBtn.addEventListener('click', (e) => { e.stopPropagation(); goNext(); });
            document.addEventListener('keydown', (e) => {
                if(!lightboxOpen) return;
                if(e.key === 'ArrowLeft') goPrev();
                if(e.key === 'ArrowRight') goNext();
            });

            // Swipe handling
            let touchStartX = 0, touchStartY = 0, touchActive = false, lastDx = 0, previewIndex = null;
            const setPreviewForDir = (dir) => {
                const target = galleryIndex + (dir > 0 ? 1 : -1);
                if(target < 0 || target >= galleryUrls.length) return false;
                if(previewIndex !== target){
                    slideNext.classList.add('no-transition');
                    slideNext.src = galleryUrls[target];
                    slideNext.style.transform = `translateX(${dir > 0 ? window.innerWidth : -window.innerWidth}px)`;
                    previewIndex = target;
                }
                return true;
            };
            stage.addEventListener('touchstart', (e) => {
                if(!e.touches || e.touches.length !== 1) return;
                e.preventDefault();
                e.stopPropagation();
                touchActive = true;
                previewIndex = null;
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                lastDx = 0;
                slideCurrent.classList.add('no-transition');
                slideNext.classList.add('no-transition');
            }, { passive: false });
            stage.addEventListener('touchmove', (e) => {
                if(!touchActive || !e.touches || e.touches.length !== 1) return;
                e.preventDefault();
                e.stopPropagation();
                const x = e.touches[0].clientX;
                const dx = x - touchStartX;
                lastDx = dx;
                // translate current and prep next slide according to direction
                slideCurrent.style.transform = `translateX(${dx}px)`;
                const dir = dx < 0 ? 1 : -1; // 1 means moving to next (left swipe)
                if(setPreviewForDir(dir)){
                    slideNext.style.transform = `translateX(${dir > 0 ? (window.innerWidth + dx) : (-window.innerWidth + dx)}px)`;
                }
            }, { passive: false });
            stage.addEventListener('touchend', (e) => {
                if(!touchActive) return;
                e.preventDefault();
                e.stopPropagation();
                touchActive = false;
                const touch = e.changedTouches && e.changedTouches[0];
                if(!touch) return;
                const dx = lastDx;
                const dy = touch.clientY - touchStartY;
                slideCurrent.classList.remove('no-transition');
                slideNext.classList.remove('no-transition');
                if(Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)){
                    const dir = dx < 0 ? 1 : -1;
                    const target = galleryIndex + (dir > 0 ? 1 : -1);
                    if(target >= 0 && target < galleryUrls.length){
                        // Commit to the target that is currently previewed, keep one lightbox instance
                        galleryIndex = target;
                        // Animate current out and next in from their current positions
                        slideCurrent.style.transform = `translateX(${dir > 0 ? -window.innerWidth : window.innerWidth}px)`;
                        slideNext.style.transform = 'translateX(0)';
                        // After transition, hard set sources/positions once
                        transitionTimerId = setTimeout(() => { swapSlides(); setInfo(); transitionTimerId = null; }, 260);
                        return;
                    }
                }
                // revert to center if not enough swipe
                slideCurrent.style.transform = 'translateX(0)';
                // keep slideNext offscreen according to last direction used
                const dir = lastDx < 0 ? 1 : -1;
                slideNext.style.transform = `translateX(${dir > 0 ? window.innerWidth : -window.innerWidth}px)`;
            }, { passive: false });

            if(!collectionId){
                status.textContent = 'No collection_id provided.';
                return;
            }

            pageTitle.textContent = `Collection: ${collectionId}`;

            try{
                status.textContent = 'Loading…';
                const res = await fetch(`${API_BASE_URL}/storage/list?collection_id=${encodeURIComponent(collectionId)}&limit=500`, {
                    headers: { 'X-API-KEY': API_KEY }
                });
                if(!res.ok){
                    status.textContent = 'Failed to load images.';
                    return;
                }
                const data = await res.json();
                const looksLikeImageUrl = (url) => {
                    if(!url || typeof url !== 'string') return false;
                    return /\.(png|jpe?g|webp|gif|bmp|tiff|heic|heif)(\?.*)?$/i.test(url);
                };
                const looksLikeAudioUrl = (url) => {
                    if (!url || typeof url !== 'string') return false;
                    return /\.(mp3|wav|m4a|ogg|aac)(\?.*)?$/i.test(url);
                };
                const isImageItem = (it) => {
                    if(!it) return false;
                    if (it.mime_type && typeof it.mime_type === 'string' && it.mime_type.startsWith('image/') && it.file_url) return true;
                    if (it.thumbnail_url && looksLikeImageUrl(it.thumbnail_url)) return true;
                    if (it.file_url && looksLikeImageUrl(it.file_url)) return true;
                    return false;
                };
                const isHlsVideoItem = (it) => !!(it && it.hls_url);
                const isAudioItem = (it) => {
                    if (!it) return false;
                    if (it.mime_type && typeof it.mime_type === 'string' && it.mime_type.startsWith('audio/')) return true;
                    if (it.file_url && looksLikeAudioUrl(it.file_url)) return true;
                    return false;
                };
                const items = (data.items || []).filter(it => isImageItem(it) || isHlsVideoItem(it) || isAudioItem(it));

                if(items.length === 0){
                    status.textContent = 'No items found in this collection.';
                    return;
                }

                status.textContent = '';
                // Build list of full-res image URLs and keep parallel item refs for info
                const imageItems = items.filter(it => isImageItem(it) && !isHlsVideoItem(it));
                galleryUrls = imageItems.map(x => x.file_url || x.thumbnail_url);
                itemsForInfo = imageItems;

                const useFullResImages = items.length < 50;

                for(let idx = 0; idx < items.length; idx++){
                    const it = items[idx];
                    const title = it.ai_title || it.title || null; // no filename fallback
                    const desc = it.ai_subtitle || it.description || '';

                    const isVideo = isHlsVideoItem(it);
                    const isAudio = !isVideo && isAudioItem(it);
                    const isImage = !isVideo && !isAudio;

                    const tags = (() => {
                        try{
                            if(!it.ai_tags) return [];
                            return Array.isArray(it.ai_tags) ? it.ai_tags : JSON.parse(it.ai_tags);
                        }catch(_){ return []; }
                    })();

                    const card = document.createElement('article');
                    card.className = 'item' + (editable ? ' editable' : '');

                    const mediaContainer = document.createElement('div');
                    mediaContainer.className = 'imglink';

                    if (isAudio) {
                        mediaContainer.style.lineHeight = '1.4';
                        mediaContainer.style.padding = '10px';
                        const audioIcon = `
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 48px; height: 48px; margin: 20px auto 10px; display: block; color: #94a3b8;">
                                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                            </svg>`;
                        mediaContainer.innerHTML = audioIcon;

                        const audio = document.createElement('audio');
                        audio.src = it.file_url;
                        audio.controls = true;
                        audio.preload = 'metadata';
                        audio.style.width = '100%';
                        mediaContainer.appendChild(audio);
                        card.classList.add('visible');
                    } else { // For Image or Video
                        let imgSrc;
                        if (isImage) {
                            imgSrc = useFullResImages
                                ? (it.file_url || it.thumbnail_url)
                                : (it.thumbnail_url || it.file_url);
                        } else { // isVideo
                            imgSrc = it.thumbnail_url;
                        }

                        const img = document.createElement('img');
                        img.loading = 'lazy';
                        img.decoding = 'async';
                        img.alt = title || 'Item';
                        img.className = 'thumb';

                        if (imgSrc && looksLikeImageUrl(imgSrc)) {
                            img.src = imgSrc;
                        } else {
                            img.style.display = 'none';
                        }
                        mediaContainer.appendChild(img);

                        if (isVideo) {
                            const vBadge = document.createElement('div');
                            vBadge.className = 'badge-video';
                            vBadge.textContent = '▶ Video';
                            mediaContainer.appendChild(vBadge);

                            const initPlayer = async (auto = false) => {
                                if (card.dataset.initialized === '1') return;
                                card.dataset.initialized = '1';

                                const video = document.createElement('video');
                                video.controls = true;
                                video.playsInline = true;
                                video.preload = 'metadata';
                                if (imgSrc && looksLikeImageUrl(imgSrc)) video.poster = imgSrc;
                                video.style.width = '100%';
                                video.style.display = 'block';
                                if (auto) { video.muted = true; }

                                if (img.parentNode === mediaContainer) {
                                    mediaContainer.replaceChild(video, img);
                                } else {
                                    mediaContainer.prepend(video);
                                }
                                vBadge.style.display = 'none';

                                try {
                                    if (window.Hls && window.Hls.isSupported()) {
                                        const hls = new window.Hls();

                                        // When the manifest is parsed, find the highest quality level and set it as the starting level.
                                        hls.on(window.Hls.Events.MANIFEST_PARSED, function (event, data) {
                                            if (data.levels && data.levels.length > 0) {
                                                hls.startLevel = data.levels.length - 1;
                                            }
                                        });

                                        hls.loadSource(it.hls_url);
                                        hls.attachMedia(video);
                                        if (auto) { video.addEventListener('loadedmetadata', () => video.play()); }
                                    } else {
                                        // For native HLS playback, quality selection is typically handled by the browser.
                                        video.src = it.hls_url;
                                        if (auto) { video.addEventListener('loadedmetadata', () => video.play()); }
                                    }
                                } catch (_e) {
                                    window.open(it.file_url, '_blank', 'noopener');
                                }
                            };

                            mediaContainer.addEventListener('click', (ev) => { ev.preventDefault(); if (Date.now() < reopenBlockUntil) return; initPlayer(); });

                            if (autoplayAny && (!didAutoplay || autoplayAll)) {
                                didAutoplay = true;
                                setTimeout(() => initPlayer(true), 0);
                            }
                        } else { // isImage
                            const imageIndex = galleryUrls.indexOf(it.file_url || it.thumbnail_url);
                            mediaContainer.addEventListener('click', (ev) => {
                                ev.preventDefault?.();
                                if (Date.now() < reopenBlockUntil) return;
                                if (!lightboxOpen) { openLightbox(imageIndex >= 0 ? imageIndex : 0); }
                            });
                        }

                        const markLoaded = () => {
                            img.classList.add('loaded');
                            card.classList.add('visible');
                        };
                        img.addEventListener('load', markLoaded, { once: true });
                        img.addEventListener('error', () => { card.classList.add('visible'); });
                        if (img.complete) { markLoaded(); }
                        if (!imgSrc || !looksLikeImageUrl(imgSrc)) {
                            card.classList.add('visible');
                        }
                    }

                    const meta = document.createElement('div');
                    meta.className = 'meta';

                    if(title){
                        const h3 = document.createElement('h3');
                        h3.className = 'title';
                        h3.textContent = title;
                        meta.appendChild(h3);
                    }

                    if(desc){
                        const p = document.createElement('p');
                        p.className = 'caption';
                        p.textContent = desc;
                        meta.appendChild(p);
                    }

                    if(tags.length){
                        const tb = document.createElement('div');
                        tb.className = 'toolbar';
                        for(const t of tags){
                            const span = document.createElement('span');
                            span.className = 'tag';
                            span.textContent = `#${t}`;
                            tb.appendChild(span);
                        }
                        meta.appendChild(tb);
                    }

                    if(editable){
                        const del = document.createElement('button');
                        del.className = 'del-btn';
                        del.textContent = '×';
                        del.title = 'Delete item';
                        del.addEventListener('click', async (e) => {
                            e.stopPropagation();
                            e.preventDefault();
                            const name = title || it.original_filename || 'Untitled';
                            if(!confirm(`Are you sure you want to delete "${name}"?\n\nThis action cannot be undone.`)) return;
                            try{
                                const r = await fetch(`${API_BASE_URL}/storage/${it.id}`, { method:'DELETE', headers:{ 'X-API-KEY': API_KEY }});
                                if(!r.ok){ throw new Error(await r.text() || 'delete failed'); }
                                alert('Item deleted successfully!');
                                location.reload();
                            }catch(err){ alert('Error deleting item: ' + (err?.message || err)); }
                        });
                        mediaContainer.appendChild(del);
                    }

                    card.appendChild(mediaContainer);
                    if (meta.childNodes.length > 0) {
                        card.appendChild(meta);
                    }
                    grid.appendChild(card);
                }
            }catch(err){
                console.error(err);
                status.textContent = 'Error loading collection.';
            }
        });
    </script>
    
    <!-- Simple lightbox using native dialog -->
    <script>
    // Optional future: enhance with a lightbox; using direct links for now
    </script>
</head>
<body>
    <header>
        <h1 id="page-title">Images</h1>
    </header>
    <main>
        <p id="status" class="caption">Loading…</p>
        <section class="masonry" id="grid" aria-label="Image Masonry Grid"></section>
    </main>
</body>
</html>
