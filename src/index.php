<?php
declare(strict_types=1);

function sanitize_root(string $root): string {
    $root = str_replace('\\', '/', trim($root));
    // PHP already URL-decodes query params, but accept stray %2F if present
    $root = rawurldecode($root);
    $root = preg_replace('~^\.?/+\s*~', '', $root); // remove leading ./ or /
    $root = preg_replace('~/+~', '/', $root);
    $root = rtrim($root, '/');
    if ($root === '' || $root === '.') return '.';
    if (strpos($root, "\0") !== false) return '.';
    if (preg_match('~^[A-Za-z]:~', $root)) return '.';
    if (strpos($root, '..') !== false) return '.';
    return $root;
}

$rootParam = isset($_GET['root']) ? (string)$_GET['root'] : '.';
$root = sanitize_root($rootParam);

// Read config (optional) to determine metadata filename
$metaName = 'metadata.json';
$cfgPath = __DIR__ . '/iptc_edit.json';
if (is_file($cfgPath)) {
    $cfgJson = @file_get_contents($cfgPath);
    if ($cfgJson !== false) {
        $cfg = json_decode($cfgJson, true);
        if (is_array($cfg) && isset($cfg['METADATA_JSON']) && is_string($cfg['METADATA_JSON']) && $cfg['METADATA_JSON'] !== '') {
            $metaName = basename(str_replace('\\', '/', $cfg['METADATA_JSON']));
        }
    }
}

// Build metadata path within selected root
$metaPath = __DIR__ . '/' . ($root === '.' ? '' : ($root . '/')) . $metaName;

$items = [];
$loadError = null;

if (!is_file($metaPath)) {
    $loadError = "Missing metadata file: " . ($root === '.' ? "./$metaName" : "./$root/$metaName");
} else {
    $json = @file_get_contents($metaPath);
    if ($json === false) {
        $loadError = "Could not read metadata file: " . ($root === '.' ? "./$metaName" : "./$root/$metaName");
    } else {
        $items = json_decode($json, true);
        if (!is_array($items)) {
            $loadError = "Invalid JSON in metadata file: " . ($root === '.' ? "./$metaName" : "./$root/$metaName");
            $items = [];
        }
    }
}

function build_web_src(array $img, string $root): string {
    $fileName = (string)($img['FileName'] ?? basename((string)($img['SourceFile'] ?? '')));
    $srcRaw = (string)($img['SourceFile'] ?? $fileName);
    $srcRaw = str_replace('\\', '/', $srcRaw);
    $srcRaw = ltrim($srcRaw, '/');

    // If SourceFile looks like an absolute filesystem path, fall back to basename
    if ($srcRaw === '' || preg_match('~^[A-Za-z]:/~', $srcRaw) || strpos($srcRaw, '..') !== false) {
        $srcRaw = $fileName;
    }

    if ($root !== '.') {
        $rootPrefix = $root . '/';
        if (strpos($srcRaw, $rootPrefix) !== 0) {
            $srcRaw = $rootPrefix . $srcRaw;
        }
    }
    return $srcRaw;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Image Index – Masonry with IPTC/EXIF</title>

<style>
body {
    font-family: sans-serif;
    margin: 0;
    padding: 16px;
    background: #f5f5f5;
}

/* Masonry container – more columns = smaller thumbs */
.masonry {
    column-count: 6;
    column-gap: 6px;
}

/* Responsive columns */
@media (max-width: 1400px) {
    .masonry { column-count: 5; }
}
@media (max-width: 1200px) {
    .masonry { column-count: 4; }
}
@media (max-width: 900px) {
    .masonry { column-count: 3; }
}
@media (max-width: 700px) {
    .masonry { column-count: 2; }
}
@media (max-width: 500px) {
    .masonry { column-count: 1; }
}

/* Each item must avoid being split between columns */
.masonry-item {
    break-inside: avoid;
    margin-bottom: 6px;
    cursor: pointer;
    position: relative;
}

/* Thumbnail image fills column width */
.masonry-item img,
.masonry-item video {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 12px;
}

.masonry-item img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 3px;
    border: 1px solid #ddd;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.thumb-video{width:100%;height:auto;display:block;border-radius:12px}


/* Title under each thumbnail */
.video-badge{
  position:absolute;top:10px;right:10px;
  background:rgba(0,0,0,0.65);color:#fff;
  padding:4px 8px;border-radius:999px;
  font-size:12px;line-height:1;pointer-events:none;
}

.thumb-caption {
    font-size: 0.8em;
    color: #222;
    margin-top: 3px;
    word-wrap: break-word;
}

/* Lightbox overlay */
#lightboxOverlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 3000;
}

/* Lightbox content */
#lightbox {
    background: #ffffff;
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: row;
    box-shadow: 0 8px 24px rgba(0,0,0,0.5);
    border-radius: 4px;
    overflow: hidden;
}

/* Large image panel */
.lightbox-image {
    flex: 2;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    cursor: grab;
    position: relative;
}
.lightbox-image.dragging {
    cursor: grabbing;
}
.lightbox-image img {
    max-width: 100%;
    max-height: 90vh;
    transform-origin: center center;
    transition: transform 0.05s linear;
}

/* IPTC/EXIF info panel */
.lightbox-info {
    flex: 1;
    padding: 16px;
    font-size: 0.9em;
    overflow-y: auto;
    box-sizing: border-box;
}
.lightbox-info h2 {
    margin-top: 0;
    font-size: 1.1em;
}

/* Missing IPTC highlight */
.missing {
    color: #c00;
    font-style: italic;
}

/* Close button */
#lightboxClose {
    position: fixed;
    top: 12px;
    right: 20px;
    font-size: 2rem;
    color: #ffffff;
    cursor: pointer;
    z-index: 3100;
    user-select: none;
}

/* Navigation arrows */
.lightbox-arrow {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    font-size: 2.5rem;
    color: #ffffff;
    cursor: pointer;
    padding: 0 10px;
    user-select: none;
    z-index: 3100;
}
#lightboxPrev { left: 10px; }
#lightboxNext { right: 10px; }

/* Counter in the popup */
#lightboxCounter {
    position: fixed;
    top: 12px;
    left: 20px;
    font-size: 0.95rem;
    color: #ffffff;
    z-index: 3100;
    background: rgba(0, 0, 0, 0.5);
    padding: 4px 8px;
    border-radius: 3px;
}

/* Zoom buttons */
#zoomControls {
    position: fixed;
    bottom: 18px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 3200;
    display: flex;
    gap: 8px;
}

#zoomControls button {
    background: rgba(255,255,255,0.9);
    border: 1px solid #444;
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 1rem;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

#zoomControls button:hover {
    background: #ffffff;
}
</style>

</head>
<body>

<h1>Image Index</h1>
<div style="margin:0 0 10px;color:#444;font-size:0.9em">Root: <b><?= htmlspecialchars($root === '.' ? './' : ('./'.$root.'/'), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></b><?php if ($loadError): ?> <span style="color:#c00">(<?= htmlspecialchars($loadError, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>)</span><?php endif; ?></div>

<div class="masonry">
<?php foreach ($items as $index => $img): ?>
    <?php
        // Main title: IPTC:Headline → IPTC:ObjectName → XMP-dc:Title → filename
        $fileName      = $img['FileName'] ?? basename($img['SourceFile'] ?? '');
        $baseNameNoExt = preg_replace('/\.[^.]+$/', '', $fileName);

        $headlineRaw   = $img['IPTC:Headline'] ?? '';
        $objectNameRaw = $img['IPTC:ObjectName'] ?? '';
        $xmpTitleRaw   = $img['XMP-dc:Title'] ?? '';

        if ($headlineRaw !== '') {
            $rawTitle = $headlineRaw;
        } elseif ($objectNameRaw !== '') {
            $rawTitle = $objectNameRaw;
        } elseif ($xmpTitleRaw !== '') {
            $rawTitle = $xmpTitleRaw;
        } else {
            $rawTitle = $baseNameNoExt;
        }

        $mainTitle = htmlspecialchars($rawTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // IPTC fields (escaped)
        $headline = htmlspecialchars($headlineRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $caption  = htmlspecialchars($img['IPTC:Caption-Abstract'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $byline   = htmlspecialchars($img['IPTC:By-line']           ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $dateIptc = htmlspecialchars($img['IPTC:DateCreated']       ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $special  = htmlspecialchars($img['IPTC:SpecialInstructions'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $kw = $img['IPTC:Keywords'] ?? '';
        if (is_array($kw)) {
            $kw = implode(', ', $kw);
        }
        $keywords = htmlspecialchars($kw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Build mouse-over tooltip string
        $tooltipParts = [];

        if ($headline !== '') $tooltipParts[] = "Headline: $headline";
        if ($caption  !== '') $tooltipParts[] = "Caption: $caption";
        if ($keywords !== '') $tooltipParts[] = "Keywords: $keywords";
        if ($byline   !== '') $tooltipParts[] = "By-line: $byline";
        if ($dateIptc !== '') $tooltipParts[] = "Date: $dateIptc";
        if ($special  !== '') $tooltipParts[] = "Special: $special";

        $tooltip = htmlspecialchars(implode("\n", $tooltipParts), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // EXIF
        $camera   = htmlspecialchars($img['EXIF:Model']             ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $exifDate = htmlspecialchars($img['EXIF:DateTimeOriginal']  ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $src = htmlspecialchars(build_web_src($img, $root), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ext = strtolower(pathinfo($fileName ?? '', PATHINFO_EXTENSION));
        $isVideo = in_array($ext, ['mp4','webm','mov'], true);


        // Missing IPTC info (from builder)
        $missing = isset($img['_missingIPTC']) && is_array($img['_missingIPTC'])
            ? array_flip($img['_missingIPTC'])
            : [];

        $isMissingHeadline = isset($missing['IPTC:Headline'])            || $headline === '';
        $isMissingCaption  = isset($missing['IPTC:Caption-Abstract'])    || $caption === '';
        $isMissingKeywords = isset($missing['IPTC:Keywords'])            || $keywords === '';
        $isMissingByline   = isset($missing['IPTC:By-line'])             || $byline === '';
        $isMissingDate     = isset($missing['IPTC:DateCreated'])         || $dateIptc === '';
        $isMissingSpecial  = isset($missing['IPTC:SpecialInstructions']) || $special === '';
    ?>

    <div class="masonry-item"
         data-index="<?= (int)$index ?>"
         data-src="<?= $src ?>"
         data-type="<?= $isVideo ? 'video' : 'image' ?>"
         data-title="<?= $mainTitle ?>"
         data-headline="<?= $headline ?>"
         data-caption="<?= $caption ?>"
         data-keywords="<?= $keywords ?>"
         data-byline="<?= $byline ?>"
         data-dateiptc="<?= $dateIptc ?>"
         data-special="<?= $special ?>"
         data-camera="<?= $camera ?>"
         data-exifdate="<?= $exifDate ?>"
         data-miss-headline="<?= $isMissingHeadline ? '1' : '0' ?>"
         data-miss-caption="<?= $isMissingCaption ? '1' : '0' ?>"
         data-miss-keywords="<?= $isMissingKeywords ? '1' : '0' ?>"
         data-miss-byline="<?= $isMissingByline ? '1' : '0' ?>"
         data-miss-date="<?= $isMissingDate ? '1' : '0' ?>"
         data-miss-special="<?= $isMissingSpecial ? '1' : '0' ?>"
    >
        <?php if ($isVideo): ?>
        <video class="thumb-video" src="<?= $src ?>" muted playsinline preload="metadata"></video>
        <div class="video-badge">▶</div>
        <?php else: ?>
        <img src="<?= $src ?>" alt="<?= $mainTitle ?>" title="<?= $tooltip ?>">
        <?php endif; ?>
        <div class="thumb-caption"><?= $isVideo ? "🎞 " : "🖼 " ?><?= $mainTitle ?></div>
    </div>

<?php endforeach; ?>
</div>

<!-- Lightbox overlay -->
<div id="lightboxOverlay">
    <div id="lightbox">
        <div class="lightbox-image" id="lightboxImageContainer">
            <img id="lightboxImg" src="" alt="">
            <video id="lightboxVideo" src="" controls playsinline style="display:none;max-width:100%;max-height:80vh;border-radius:12px"></video>
        </div>
        <div class="lightbox-info">
            <h2 id="infoTitle"></h2>
            <p id="infoHeadlineWrapper"><em id="infoHeadline"></em></p>
            <p id="infoCaptionWrapper"><strong>Caption:</strong><br><span id="infoCaption"></span></p>
            <p id="infoKeywordsWrapper"><strong>Keywords:</strong> <span id="infoKeywords"></span></p>
            <p id="infoBylineWrapper"><strong>By-line:</strong> <span id="infoByline"></span></p>
            <p id="infoDateWrapper"><strong>Date (IPTC):</strong> <span id="infoDateIptc"></span></p>
            <p id="infoSpecialWrapper"><strong>Special instructions:</strong><br><span id="infoSpecial"></span></p>
            <hr>
            <p><strong>Camera:</strong> <span id="infoCamera"></span></p>
            <p><strong>Date (EXIF):</strong> <span id="infoExifDate"></span></p>
        </div>
    </div>
</div>

<!-- Controls -->
<div id="lightboxClose">&times;</div>
<div id="lightboxPrev" class="lightbox-arrow">&#10094;</div>
<div id="lightboxNext" class="lightbox-arrow">&#10095;</div>
<div id="lightboxCounter"></div>

<!-- Zoom buttons -->
<div id="zoomControls">
    <button id="zoomInBtn">+</button>
    <button id="zoomOutBtn">−</button>
    <button id="zoomResetBtn">1:1</button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var items = Array.prototype.slice.call(document.querySelectorAll('.masonry-item'));
    var overlay = document.getElementById('lightboxOverlay');
    var imgEl   = document.getElementById('lightboxImg');
    var videoEl = document.getElementById('lightboxVideo');
    var imgContainer = document.getElementById('lightboxImageContainer');

    var infoTitle    = document.getElementById('infoTitle');
    var infoHeadline = document.getElementById('infoHeadline');
    var infoCaption  = document.getElementById('infoCaption');
    var infoKeywords = document.getElementById('infoKeywords');
    var infoByline   = document.getElementById('infoByline');
    var infoDateIptc = document.getElementById('infoDateIptc');
    var infoSpecial  = document.getElementById('infoSpecial');
    var infoCamera   = document.getElementById('infoCamera');
    var infoExifDate = document.getElementById('infoExifDate');

    var headlineWrapper = document.getElementById('infoHeadlineWrapper');
    var captionWrapper  = document.getElementById('infoCaptionWrapper');
    var keywordsWrapper = document.getElementById('infoKeywordsWrapper');
    var bylineWrapper   = document.getElementById('infoBylineWrapper');
    var dateWrapper     = document.getElementById('infoDateWrapper');
    var specialWrapper  = document.getElementById('infoSpecialWrapper');

    var btnClose   = document.getElementById('lightboxClose');
    var btnPrev    = document.getElementById('lightboxPrev');
    var btnNext    = document.getElementById('lightboxNext');
    var counterEl  = document.getElementById('lightboxCounter');

    var zoomInBtn    = document.getElementById('zoomInBtn');
    var zoomOutBtn   = document.getElementById('zoomOutBtn');
    var zoomResetBtn = document.getElementById('zoomResetBtn');

    var currentIndex = -1;

    // Zoom & pan state
    var zoom = 1;
    var minZoom = 1;
    var maxZoom = 4;
    var posX = 0;
    var posY = 0;
    var isPanning = false;
    var startX = 0;
    var startY = 0;

    function resetZoomAndPan() {
        zoom = 1;
        posX = 0;
        posY = 0;
        updateTransform();
    }

    function updateTransform() {
        imgEl.style.transform = 'translate(' + posX + 'px,' + posY + 'px) scale(' + zoom + ')';
    }

    function setMissingClass(wrapper, isMissing, missingText, originalTextNode) {
        wrapper.classList.toggle('missing', isMissing);
        if (isMissing && missingText) {
            originalTextNode.textContent = missingText;
        }
    }

    function openLightbox(index) {
        if (index < 0 || index >= items.length) return;
        currentIndex = index;

        var item = items[index];

        var src      = item.getAttribute('data-src') || '';
        var type = item.getAttribute('data-type') || '';
        if (!type) {
            type = (/\.(mp4|webm|mov)(\?|$)/i.test(src) ? 'video' : 'image');
        }

        var title    = item.getAttribute('data-title') || '';
        var headline = item.getAttribute('data-headline') || '';
        var caption  = item.getAttribute('data-caption') || '';
        var keywords = item.getAttribute('data-keywords') || '';
        var byline   = item.getAttribute('data-byline') || '';
        var dateIptc = item.getAttribute('data-dateiptc') || '';
        var special  = item.getAttribute('data-special') || '';
        var camera   = item.getAttribute('data-camera') || '';
        var exifDate = item.getAttribute('data-exifdate') || '';

        var missHeadline = item.getAttribute('data-miss-headline') === '1';
        var missCaption  = item.getAttribute('data-miss-caption') === '1';
        var missKeywords = item.getAttribute('data-miss-keywords') === '1';
        var missByline   = item.getAttribute('data-miss-byline') === '1';
        var missDate     = item.getAttribute('data-miss-date') === '1';
        var missSpecial  = item.getAttribute('data-miss-special') === '1';

                // Toggle media
        if (type === 'video') {
            imgEl.style.display = 'none';
            imgEl.src = '';
        if (videoEl) {
            try { videoEl.pause(); } catch (e) {}
            videoEl.src = '';
            videoEl.style.display = 'none';
        }
            if (videoEl) {
                videoEl.style.display = '';
                videoEl.src = src;
                videoEl.currentTime = 0;
                try { videoEl.play(); } catch (e) {}
            }
        } else {
            if (videoEl) {
                try { videoEl.pause(); } catch (e) {}
                videoEl.src = '';
                videoEl.style.display = 'none';
            }
            imgEl.style.display = '';
            imgEl.src = src;
            imgEl.alt = title;
        }

        infoTitle.textContent    = title || '(No title)';
        infoHeadline.textContent = headline;
        infoCaption.textContent  = caption;
        infoKeywords.textContent = keywords;
        infoByline.textContent   = byline;
        infoDateIptc.textContent = dateIptc;
        infoSpecial.innerHTML    = special.replace(/\n/g, '<br>');
        infoCamera.textContent   = camera;
        infoExifDate.textContent = exifDate;

        // Missing IPTC highlighting
        setMissingClass(headlineWrapper, missHeadline, '[Missing Headline]', infoHeadline);
        setMissingClass(captionWrapper,  missCaption,  '[Missing Caption]', infoCaption);
        setMissingClass(keywordsWrapper, missKeywords, '[Missing Keywords]', infoKeywords);
        setMissingClass(bylineWrapper,   missByline,   '[Missing By-line]', infoByline);
        setMissingClass(dateWrapper,     missDate,     '[Missing Date]', infoDateIptc);
        setMissingClass(specialWrapper,  missSpecial,  '[Missing Special Instructions]', infoSpecial);

        // Counter: "current / total"
        counterEl.textContent = (currentIndex + 1) + ' / ' + items.length;

        resetZoomAndPan();
        overlay.style.display = 'flex';
    }

    function closeLightbox() {
        overlay.style.display = 'none';
        currentIndex = -1;
        imgEl.src = '';
        if (videoEl) {
            try { videoEl.pause(); } catch (e) {}
            videoEl.src = '';
            videoEl.style.display = 'none';
        }
    }

    function showNext() {
        if (items.length === 0) return;
        var nextIndex = (currentIndex + 1) % items.length;
        openLightbox(nextIndex);
    }

    function showPrev() {
        if (items.length === 0) return;
        var prevIndex = (currentIndex - 1 + items.length) % items.length;
        openLightbox(prevIndex);
    }

    // Click on thumbnails
    items.forEach(function (item, index) {
        item.addEventListener('click', function () {
            openLightbox(index);
        });
    });

    // Close button
    btnClose.addEventListener('click', closeLightbox);

    // Arrows
    btnNext.addEventListener('click', function (e) {
        e.stopPropagation();
        showNext();
    });
    btnPrev.addEventListener('click', function (e) {
        e.stopPropagation();
        showPrev();
    });

    // Click outside content closes
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeLightbox();
        }
    });

    // Keyboard shortcuts: Esc, left/right
    document.addEventListener('keydown', function (e) {
        if (overlay.style.display !== 'flex') return;

        if (e.key === 'Escape') {
            closeLightbox();
        } else if (e.key === 'ArrowRight') {
            showNext();
        } else if (e.key === 'ArrowLeft') {
            showPrev();
        }
    });

    // Zoom with mouse wheel over the image container
    imgContainer.addEventListener('wheel', function (e) {
        if (overlay.style.display !== 'flex') return;
        e.preventDefault();

        var delta = e.deltaY < 0 ? 0.1 : -0.1;
        var newZoom = zoom + delta;
        if (newZoom < minZoom) newZoom = minZoom;
        if (newZoom > maxZoom) newZoom = maxZoom;

        zoom = newZoom;

        // When zooming out back to 1, reset pan
        if (zoom === 1) {
            posX = 0;
            posY = 0;
        }

        updateTransform();
    }, { passive: false });

    // Pan when zoomed in: mouse events on container
    imgContainer.addEventListener('mousedown', function (e) {
        if (zoom <= 1) return;
        isPanning = true;
        imgContainer.classList.add('dragging');
        startX = e.clientX - posX;
        startY = e.clientY - posY;
    });

    document.addEventListener('mousemove', function (e) {
        if (!isPanning) return;
        posX = e.clientX - startX;
        posY = e.clientY - startY;
        updateTransform();
    });

    document.addEventListener('mouseup', function () {
        if (!isPanning) return;
        isPanning = false;
        imgContainer.classList.remove('dragging');
    });

    // Zoom buttons
    zoomInBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        zoom += 0.2;
        if (zoom > maxZoom) zoom = maxZoom;
        updateTransform();
    });

    zoomOutBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        zoom -= 0.2;
        if (zoom < minZoom) zoom = minZoom;
        if (zoom === 1) {
            posX = 0;
            posY = 0;
        }
        updateTransform();
    });

    zoomResetBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        zoom = 1;
        posX = 0;
        posY = 0;
        updateTransform();
    });
});
</script>

</body>
</html>
