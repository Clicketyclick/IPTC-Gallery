<?php
declare(strict_types=1);

/**
 * iptc_api.php (browse/editor API)
 * - JSON-only endpoints for directory tree browsing + IPTC/GPS editing via ExifTool.
 *
 * Endpoints (GET):
 *   ?action=diag
 *   ?action=tree&root=.              (root for tree is start directory; typically '.')
 *   ?action=list&root=REL_DIR        (lists media in selected dir; overlays metadata.json if present)
 *   ?action=fields                   (template IPTC fields)
 *   ?action=read&root=REL_DIR&file=F (reads IPTC/GPS)
 *
 * Endpoint (POST JSON):
 *   ?action=save&root=REL_DIR        body: {file, iptc:{...}, geo:{lat,lng,bearing,zoom}}
 *
 * Config: iptc_edit.json in same directory.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

ob_start();

function jexit(array $payload, int $code=200): void {
  if (ob_get_length() !== false) { @ob_clean(); }

  // Clear any accidental output
  $out = ob_get_clean();
  if ($out !== '' && !isset($payload['_php_output'])) {
    // Keep a snippet for debugging if requested
    $payload['_php_output'] = substr($out, 0, 400);
  }
  http_response_code($code);
    $flags = JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT;
  if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
  elseif (defined('JSON_INVALID_UTF8_IGNORE')) $flags |= JSON_INVALID_UTF8_IGNORE;
  echo json_encode($payload, $flags);
  exit;
}

set_error_handler(function(int $severity, string $message, string $file, int $line): bool {
  throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function(): void {
  $err = error_get_last();
  if (!$err) return;
  $fatal = in_array($err['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true);
  if (!$fatal) return;
  if (headers_sent()) return;
  while (ob_get_level() > 0) { ob_end_clean(); }
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Fatal error: '.$err['message'].' @ '.$err['file'].':'.$err['line']], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
});

function loadCfg(): array {
  $cfgFile = __DIR__ . '/iptc_edit.json';
  if (!is_file($cfgFile)) return [];
  $j = json_decode((string)file_get_contents($cfgFile), true);
  return is_array($j) ? $j : [];
}
$CFG = loadCfg();

function cfg(string $key, $default=null) {
  global $CFG;
  return array_key_exists($key, $CFG) ? $CFG[$key] : $default;
}

function normPath(string $p): string {
  $p = str_replace('\\', '/', $p);
  $p = preg_replace('~^\./+~', '', $p);
  $p = preg_replace('~//+~', '/', $p);
  return $p;
}

function sanitizeRoot(string $root): string {
  $root = normPath(trim($root));
  $root = trim($root, '/');
  if ($root === '') return '.';
  if (str_contains($root, '..')) throw new RuntimeException('Invalid root');
  return $root;
}

function mediaRootAbs(?string $override=null): string {
  $root = '.';
  if ($override !== null && $override !== '') $root = sanitizeRoot($override);
  $abs = realpath(__DIR__ . '/' . $root);
  if ($abs === false) $abs = realpath(__DIR__) ?: __DIR__;
  return $abs;
}

function safeResolve(string $file, ?string $rootOverride=null): string {
  $file = normPath($file);
  $file = ltrim($file, '/');
  if ($file === '' || str_contains($file, '..')) throw new RuntimeException('Invalid file');
  $root = mediaRootAbs($rootOverride);
  $abs = $root . '/' . $file;
  $real = realpath($abs);
  if ($real === false) throw new RuntimeException('File not found');
  $realN = normPath($real);
  $rootN = rtrim(normPath($root), '/');
  if (strpos($realN, $rootN) !== 0) throw new RuntimeException('Path outside root');
  return $real;
}

function shellAvailable(): bool {
  if (!function_exists('shell_exec')) return false;
  $disabled = (string)ini_get('disable_functions');
  if ($disabled !== '') {
    $list = array_map('trim', explode(',', $disabled));
    if (in_array('shell_exec', $list, true)) return false;
  }
  return true;
}

function resolveExiftool(): ?string {
  $p = (string)cfg('EXIFTOOL_PATH', '');
  if ($p !== '' && is_file($p)) return $p;
  if (!shellAvailable()) return null;
  $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  $cmd = $isWin ? 'where exiftool 2>&1' : 'command -v exiftool 2>&1';
  $out = trim((string)@shell_exec($cmd));
  if ($out !== '') {
    $line = preg_split('~\R~', $out)[0];
    return $line !== '' ? $line : null;
  }
  return null;
}

function exiftoolVersion(): ?string {
  $bin = resolveExiftool();
  if ($bin === null || !shellAvailable()) return null;
  $v = trim((string)@shell_exec(escapeshellarg($bin) . ' -ver 2>&1'));
  return $v !== '' ? $v : null;
}

function exiftoolRequired(): void {
  if (!shellAvailable()) throw new RuntimeException('shell_exec disabled');
  $bin = resolveExiftool();
  if ($bin === null) throw new RuntimeException('ExifTool not found. Set EXIFTOOL_PATH in iptc_edit.json');
  $ver = exiftoolVersion();
  if ($ver === null) throw new RuntimeException('ExifTool not runnable from PHP');
}

function allowedExt(string $file): bool {
  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  $allowed = cfg('ALLOWED_EXT', ['jpg','jpeg','mp4']);
  return is_array($allowed) ? in_array($ext, $allowed, true) : true;
}

function safeMetaFilename(): string {
  $name = (string)cfg('METADATA_JSON', 'metadata.json');
  $name = basename(normPath($name));
  return $name !== '' ? $name : 'metadata.json';
}

function loadTemplateFields(): array {
  $tname = (string)cfg('TEMPLATE_JSON', 'template_metadata.json');
  $file = __DIR__ . '/' . basename($tname);
  if (is_file($file)) {
    $j = json_decode((string)@file_get_contents($file), true);
    $rec = is_array($j) && isset($j[0]) ? $j[0] : $j;
    if (is_array($rec)) {
      $out = [];
      foreach ($rec as $k => $_) {
        if (!is_string($k)) continue;
        $tag = str_starts_with($k, 'IPTC:') ? $k : ('IPTC:' . $k);
        $out[$tag] = true;
      }
      // Ensure core fields exist even if template doesn't include them
      foreach ([
        'IPTC:ObjectName','IPTC:Headline','IPTC:Caption-Abstract','IPTC:Keywords',
        'IPTC:By-line','IPTC:DateCreated','IPTC:SpecialInstructions',
        'IPTC:Country-PrimaryLocationName','IPTC:Country-PrimaryLocationCode',
        'IPTC:Province-State','IPTC:City','IPTC:Sub-location'
      ] as $tag) $out[$tag] = true;
      return array_keys($out);
    }
  }
  return [
    'IPTC:ObjectName','IPTC:Headline','IPTC:Caption-Abstract','IPTC:Keywords',
    'IPTC:By-line','IPTC:DateCreated','IPTC:SpecialInstructions',
    'IPTC:Country-PrimaryLocationName','IPTC:Country-PrimaryLocationCode',
    'IPTC:Province-State','IPTC:City','IPTC:Sub-location'
  ];
}

function exiftoolReadArgs(): array {
  $args = cfg('EXIFTOOL_READ_ARGS', []);
  return is_array($args) ? $args : [];
}
function exiftoolWriteArgs(): array {
  $args = cfg('EXIFTOOL_WRITE_ARGS', []);
  return is_array($args) ? $args : [];
}

function runExiftoolJson(string $abs): array {
  $bin = resolveExiftool();
  if ($bin === null) throw new RuntimeException('ExifTool binary not found');
  $args = exiftoolReadArgs();
  if (!$args) $args = ['-json','-G1','-a','-s','-n','-charset','UTF8','-charset','IPTC=UTF8','-charset','EXIF=UTF8'];
  $cmd = escapeshellarg($bin) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' ' . escapeshellarg($abs) . ' 2>&1';
  $out = (string)@shell_exec($cmd);
  $j = json_decode($out, true);
  if (!is_array($j) || !isset($j[0]) || !is_array($j[0])) {
    throw new RuntimeException('ExifTool JSON parse failed. Output: ' . substr($out, 0, 400));
  }
  return $j[0];
}

function pickFirst(array $raw, array $keys) {
  foreach ($keys as $k) {
    if (isset($raw[$k]) && $raw[$k] !== '' && $raw[$k] !== null) return $raw[$k];
  }
  return null;
}

function gpsVersionToFloat($v): ?float {
  if ($v === null) return null;
  $s = trim((string)$v);
  if ($s === '') return null;
  if (preg_match('~^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:\.(\d+))?$~', $s, $m)) {
    $a = (int)$m[1];
    $b = isset($m[2]) ? (int)$m[2] : 0;
    $c = isset($m[3]) ? (int)$m[3] : 0;
    $d = isset($m[4]) ? (int)$m[4] : 0;
    return $a + ($b/100.0) + ($c/10000.0) + ($d/1000000.0);
  }
  if (is_numeric($s)) return (float)$s;
  return null;
}

function metadataIndexByFile(string $metaFile): array {
  if (!is_file($metaFile)) return [];
  $txt = (string)@file_get_contents($metaFile);
  if ($txt === '') return [];
  $j = json_decode($txt, true);
  if (!is_array($j)) return [];
  $idx = [];
  foreach ($j as $rec) {
    if (!is_array($rec)) continue;
    $f = $rec['System:FileName'] ?? $rec['FileName'] ?? null;
    if (!is_string($f) || $f==='') {
      $sf = $rec['SourceFile'] ?? null;
      if (is_string($sf) && $sf!=='') $f = basename(normPath($sf));
    }
    if (is_string($f) && $f!=='') $idx[$f] = $rec;
  }
  return $idx;
}

function inferFlagsFromRecord(array $rec): array {
  $lat = $rec['GPS:GPSLatitude'] ?? $rec['GPSLatitude'] ?? ($rec['EXIF:GPSLatitude'] ?? null);
  $lng = $rec['GPS:GPSLongitude'] ?? $rec['GPSLongitude'] ?? ($rec['EXIF:GPSLongitude'] ?? null);
  $bear = $rec['GPS:GPSImgDirection'] ?? $rec['GPSImgDirection'] ?? ($rec['EXIF:GPSImgDirection'] ?? null);
  if (isset($rec['gps']) && is_array($rec['gps'])) {
    $lat = $lat ?? ($rec['gps']['lat'] ?? null);
    $lng = $lng ?? ($rec['gps']['lng'] ?? null);
    $bear = $bear ?? ($rec['gps']['bearing'] ?? null);
  }

  $hasGps = ($lat !== null && $lat !== '' && $lng !== null && $lng !== '');
  $hasBear = ($bear !== null && $bear !== '');

  $locKeys = [
    'IPTC:Country-PrimaryLocationName','IPTC:Country','IPTC:City',
    'IPTC:Province-State','IPTC:Sub-location','IPTC:Country-PrimaryLocationCode'
  ];
  $hasLoc = false;
  foreach ($locKeys as $k) {
    if (isset($rec[$k]) && trim((string)$rec[$k]) !== '') { $hasLoc = true; break; }
  }

  return ['has_gps'=>$hasGps, 'has_bearing'=>$hasBear, 'has_location'=>$hasLoc];
}


function scanRootFiles(string $rootAbs): array {
  $out = [];
  $names = @scandir($rootAbs);
  if (!is_array($names)) return $out;
  foreach ($names as $nm) {
    if ($nm === '.' || $nm === '..') continue;
    $abs = $rootAbs . '/' . $nm;
    if (!is_file($abs)) continue;
    if (!allowedExt($nm)) continue;
    $out[] = $nm;
  }
  sort($out, SORT_NATURAL | SORT_FLAG_CASE);
  return $out;
}

function buildMetadataForRoot(string $rootRel): array {
  exiftoolRequired();
  $rootAbs = mediaRootAbs($rootRel);
  $files = scanRootFiles($rootAbs);
  $items = [];
  $errors = [];
  $t0 = microtime(true);

  foreach ($files as $f) {
    try {
      $raw = runExiftoolJson($rootAbs . '/' . $f);
      // Normalize SourceFile to filename only (relative to root)
      $raw['SourceFile'] = $f;
      $items[] = $raw;
    } catch (Throwable $e) {
      $errors[] = ['file'=>$f, 'error'=>$e->getMessage()];
    }
  }

  $metaName = safeMetaFilename();
  $outFile = $rootAbs . '/' . $metaName;
  $flags = JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT;
  if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
  elseif (defined('JSON_INVALID_UTF8_IGNORE')) $flags |= JSON_INVALID_UTF8_IGNORE;
  $json = json_encode($items, $flags);
  if ($json === false) throw new RuntimeException('json_encode failed');
  if (@file_put_contents($outFile, $json) === false) throw new RuntimeException('Could not write metadata: ' . $outFile);

  return [
    'ok'=>true,
    'root'=>$rootRel,
    'metadata_file'=>$metaName,
    'count'=>count($items),
    'errors'=>$errors,
    'seconds'=>round(microtime(true)-$t0, 3),
  ];
}

function listMediaFiles(string $rootRel): array {
  $rootAbs = mediaRootAbs($rootRel);
  $metaIdx = metadataIndexByFile($rootAbs . '/' . safeMetaFilename());

  $items = [];
  $names = @scandir($rootAbs);
  if (!is_array($names)) return [];

  foreach ($names as $nm) {
    if ($nm === '.' || $nm === '..') continue;
    $abs = $rootAbs . '/' . $nm;
    if (!is_file($abs)) continue;
    if (!allowedExt($nm)) continue;

    $ext = strtolower(pathinfo($nm, PATHINFO_EXTENSION));
    $title = pathinfo($nm, PATHINFO_FILENAME);
    $flags = ['has_gps'=>false,'has_bearing'=>false,'has_location'=>false];

    if (isset($metaIdx[$nm]) && is_array($metaIdx[$nm])) {
      $rec = $metaIdx[$nm];

      // Keep specific fields for UI captions
      $objectname = trim((string)($rec['IPTC:ObjectName'] ?? $rec['XMP-dc:Title'] ?? $rec['XMP:Title'] ?? ''));
      $headline   = trim((string)($rec['IPTC:Headline'] ?? $rec['XMP-photoshop:Headline'] ?? ''));

      foreach (['IPTC:ObjectName','IPTC:Headline','XMP-dc:Title','XMP:Title','Title'] as $k) {
        if (isset($rec[$k]) && trim((string)$rec[$k]) !== '') { $title = trim((string)$rec[$k]); break; }
      }
      $flags = inferFlagsFromRecord($rec);
    } else {
      $objectname = '';
      $headline = '';
    }

    $items[] = ['file'=>$nm, 'ext'=>$ext, 'title'=>$title, 'objectname'=>$objectname, 'headline'=>$headline] + $flags;
  }

  usort($items, fn($a,$b)=>strnatcasecmp($a['file'],$b['file']));
  return $items;
}

function coerceDateTimeLocalToExiftool(string $dt): string {
  $dt = trim($dt);
  if ($dt === '') return '';
  if (preg_match('~^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$~', $dt, $m)) {
    $sec = $m[6] ?? '00';
    return "{$m[1]}:{$m[2]}:{$m[3]} {$m[4]}:{$m[5]}:$sec";
  }
  return $dt;
}

function iptcAliasMap(): array {
  return [
    'IPTC:ObjectName' => ['XMP-dc:Title', 'XMP:Title'],
    'IPTC:Headline' => ['XMP-photoshop:Headline'],
    'IPTC:Caption-Abstract' => ['XMP-dc:Description', 'XMP:Description', 'XMP-photoshop:Caption'],
    'IPTC:Keywords' => ['XMP-dc:Subject', 'XMP:Subject'],
    'IPTC:By-line' => ['XMP-dc:Creator', 'XMP:Creator'],
    'IPTC:DateCreated' => ['XMP-photoshop:DateCreated', 'XMP:CreateDate'],
    'IPTC:SpecialInstructions' => ['XMP-photoshop:Instructions'],
    'IPTC:Country-PrimaryLocationName' => ['XMP-photoshop:Country'],
    'IPTC:Country-PrimaryLocationCode' => ['XMP-iptcCore:CountryCode'],
    'IPTC:Province-State' => ['XMP-photoshop:State'],
    'IPTC:City' => ['XMP-photoshop:City'],
    'IPTC:Sub-location' => ['XMP-iptcCore:Location'],
  ];
}


function xmpDateFromAny(string $v): string {
  $v = trim($v);
  if ($v === '') return '';
  if (preg_match('~^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?)?$~', $v)) return $v;
  if (preg_match('~^(\d{4}):(\d{2}):(\d{2})\s+(\d{2}):(\d{2}):(\d{2})$~', $v, $m)) {
    return $m[1] . '-' . $m[2] . '-' . $m[3] . 'T' . $m[4] . ':' . $m[5] . ':' . $m[6];
  }
  if (preg_match('~^(\d{4}):(\d{2}):(\d{2})$~', $v, $m)) {
    return $m[1] . '-' . $m[2] . '-' . $m[3];
  }
  return $v;
}

function appendAliasArgs(array &$args, array $iptc): void {
  $map = iptcAliasMap();
  foreach ($map as $iptcTag => $aliases) {
    $short = preg_replace('~^IPTC:~', '', (string)$iptcTag);
    $val = $iptc[$iptcTag] ?? $iptc[$short] ?? null;

    if (is_array($val)) {
      foreach ($aliases as $a) {
        $args[] = "-$a=";
        foreach ($val as $vv) {
          $vv = trim((string)$vv);
          if ($vv !== '') $args[] = "-$a=$vv";
        }
      }
      continue;
    }

    $s = $val === null ? '' : (string)$val;

    foreach ($aliases as $a) {
      $outVal = $s;
      if ($iptcTag === 'IPTC:DateCreated' && str_starts_with((string)$a,'XMP:')) {
        $outVal = xmpDateFromAny($s);
      }
      $args[] = "-$a=$outVal";
    }
  }
}

function buildWriteArgs(array $templateFields, array $fields, array $geo): array {
  $args = exiftoolWriteArgs();
  if (!$args) $args = ['-overwrite_original','-charset','UTF8','-charset','IPTC=UTF8','-charset','EXIF=UTF8'];

  foreach ($templateFields as $tag) {
    $short = preg_replace('~^IPTC:~', '', $tag);
    $val = $fields[$tag] ?? $fields[$short] ?? '';
    if (is_array($val)) {
      $args[] = "-$tag=";
      foreach ($val as $vv) {
        $vv = trim((string)$vv);
        if ($vv !== '') $args[] = "-$tag=$vv";
      }
      continue;
    }
    $val = (string)$val;

    if (preg_match('~\b(Date|Time)\b~i', $short) || in_array($short, ['DateCreated','ReleaseDate','ReleaseTime','TimeCreated'], true)) {
      $val = coerceDateTimeLocalToExiftool($val);
    }

    if ($short === 'Keywords') {
      $parts = array_values(array_filter(array_map('trim', preg_split('~\s*,\s*~', $val)), fn($x)=>$x!==''));
      $args[] = "-$tag=";
      foreach ($parts as $p) $args[] = "-$tag=$p";
      continue;
    }

    $args[] = "-$tag=$val";
  }

  // Write XMP title/description too (no XMP charset options)
  $xmpCfg = cfg('XMP_WRITE', []);
  $writeTitle = is_array($xmpCfg) ? (bool)($xmpCfg['write_xmp_title'] ?? true) : true;
  $writeDesc  = is_array($xmpCfg) ? (bool)($xmpCfg['write_xmp_description'] ?? true) : true;

  if ($writeTitle) {
    $sources = is_array($xmpCfg['title_sources'] ?? null) ? $xmpCfg['title_sources'] : ['XMP-dc:Title','XMP:Title','IPTC:ObjectName','IPTC:Headline'];
    $t = '';
    foreach ($sources as $k) { if (isset($fields[$k]) && trim((string)$fields[$k]) !== '') { $t = trim((string)$fields[$k]); break; } }
    if ($t !== '') { $args[] = "-XMP-dc:Title=$t"; $args[] = "-XMP:Title=$t"; }
  }
  if ($writeDesc) {
    $sources = is_array($xmpCfg['desc_sources'] ?? null) ? $xmpCfg['desc_sources'] : ['XMP-dc:Description','XMP:Description','IPTC:Caption-Abstract'];
    $d = '';
    foreach ($sources as $k) { if (isset($fields[$k]) && trim((string)$fields[$k]) !== '') { $d = trim((string)$fields[$k]); break; } }
    if ($d !== '') { $args[] = "-XMP-dc:Description=$d"; $args[] = "-XMP:Description=$d"; }
  }

  // GPS + bearing + zoom
  if (isset($geo['lat']) && isset($geo['lng']) && $geo['lat'] !== '' && $geo['lng'] !== '' && $geo['lat'] !== null && $geo['lng'] !== null) {
    $lat = (float)$geo['lat'];
    $lng = (float)$geo['lng'];
    $args[] = "-EXIF:GPSLatitude=$lat";
    $args[] = "-EXIF:GPSLongitude=$lng";
    $args[] = "-EXIF:GPSLatitudeRef=" . ($lat < 0 ? 'S' : 'N');
    $args[] = "-EXIF:GPSLongitudeRef=" . ($lng < 0 ? 'W' : 'E');
    $args[] = "-XMP:GPSLatitude=$lat";
    $args[] = "-XMP:GPSLongitude=$lng";
  }
  if (isset($geo['bearing']) && $geo['bearing'] !== '' && $geo['bearing'] !== null) {
    $b = (float)$geo['bearing'];
    $args[] = "-EXIF:GPSImgDirection=$b";
    $args[] = "-EXIF:GPSImgDirectionRef=T";
    $args[] = "-XMP:GPSImgDirection=$b";
  }
  if (isset($geo['zoom']) && $geo['zoom'] !== '' && $geo['zoom'] !== null) {
    $z = (int)$geo['zoom'];
    $args[] = "-XMP:MapZoom=$z";
  }

  return $args;
}

function buildMapCfg(): array {
  $m = cfg('MAP', []);
  if (!is_array($m)) $m = [];
  // Default layers if not provided
  if (!isset($m['tile_layers']) || !is_array($m['tile_layers']) || !$m['tile_layers']) {
    $m['tile_layers'] = [
      [
        'name' => 'OpenStreetMap',
        'url' => $m['tile_url'] ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'attribution' => $m['attribution'] ?? '&copy; OpenStreetMap contributors',
        'maxZoom' => 22,
      ],
      [
        'name' => 'Satellite (Esri)',
        'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        'attribution' => 'Tiles &copy; Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        'maxZoom' => 19,
      ],
    ];
    $m['default_layer'] = $m['default_layer'] ?? 'OpenStreetMap';
  }
  return $m;
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$root = (string)($_GET['root'] ?? $_POST['root'] ?? '.');
try { $root = sanitizeRoot($root); } catch(Throwable $e) { $root = '.'; }

try {
  if ($action === 'aliases') {
    jexit(['ok'=>true,'aliases'=>iptcAliasMap()]);
  }

  if ($action === 'diag') {
    jexit([
      'ok'=>true,
      'php_sapi'=>php_sapi_name(),
      'os'=>PHP_OS,
      'shell_exec_available'=>shellAvailable(),
      'disable_functions'=>(string)ini_get('disable_functions'),
      'exiftool_path_config'=>(string)cfg('EXIFTOOL_PATH',''),
      'exiftool_resolved'=>resolveExiftool(),
      'exiftool_ver'=>exiftoolVersion(),
      'selected_root'=>$root,
      'cfg'=>[
        'MAP'=>buildMapCfg(),
        'ALLOWED_EXT'=>cfg('ALLOWED_EXT', ['jpg','jpeg','mp4']),
        'METADATA_JSON'=>safeMetaFilename(),
        'TEMPLATE_JSON'=>cfg('TEMPLATE_JSON','template_metadata.json'),
      ],
    ]);
  }

  if ($action === 'fields') {
    $fields = loadTemplateFields();
    jexit(['ok'=>true,'fields'=>$fields,'count'=>count($fields)]);
  }

  if ($action === 'tree') {
    $startRel = (string)($_GET['root'] ?? '.'); // for tree start, allow '.' (default)
    $startRel = sanitizeRoot($startRel);
    $startAbs = mediaRootAbs($startRel);

    $maxDepth = (int)cfg('TREE_MAX_DEPTH', 12);
    if ($maxDepth < 1) $maxDepth = 1;
    if ($maxDepth > 32) $maxDepth = 32;
    $skipHidden = (bool)cfg('TREE_SKIP_HIDDEN', true);

    $walk = function(string $abs, string $rel, int $depth) use (&$walk, $maxDepth, $skipHidden): array {
      if ($depth > $maxDepth) return [];
      $names = @scandir($abs);
      if (!is_array($names)) return [];
      $out = [];
      foreach ($names as $nm) {
        if ($nm === '.' || $nm === '..') continue;
        if ($skipHidden && $nm !== '' && $nm[0] === '.') continue;
        $childAbs = $abs . '/' . $nm;
        if (!is_dir($childAbs)) continue;
        $childRel = ($rel === '' ? $nm : ($rel . '/' . $nm));
        $out[] = ['name'=>$nm, 'path'=>$childRel, 'children'=>$walk($childAbs, $childRel, $depth+1)];
      }
      usort($out, fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
      return $out;
    };

    $tree = [
      'name' => $startRel,
      'path' => ($startRel === '.' ? '' : $startRel),
      'children' => $walk($startAbs, ($startRel === '.' ? '' : $startRel), 1),
    ];
    jexit(['ok'=>true,'root'=>$startRel,'tree'=>$tree,'maxDepth'=>$maxDepth]);
  }

  if ($action === 'build_metadata') {
    $res = buildMetadataForRoot($root);
    jexit($res);
  }

  if ($action === 'list') {
    $items = listMediaFiles($root);
    jexit(['ok'=>true,'root'=>$root,'items'=>$items,'count'=>count($items)]);
  }

  if ($action === 'read') {
    exiftoolRequired();
    $file = (string)($_GET['file'] ?? '');
    $abs = safeResolve($file, $root);
    $raw = runExiftoolJson($abs);

    $templateFields = loadTemplateFields();
    $iptc = [];
    foreach ($templateFields as $tag) {
      $short = preg_replace('~^IPTC:~','', $tag);
      $iptc[$tag] = $raw[$tag] ?? $raw["IPTC:$short"] ?? $raw[$short] ?? '';
    }

    $gpsKeys = cfg('GPS_READ_KEYS', []);
    if (!is_array($gpsKeys)) $gpsKeys = [];
    $lat = pickFirst($raw, $gpsKeys['lat'] ?? ['GPS:GPSLatitude','GPSLatitude']);
    $lng = pickFirst($raw, $gpsKeys['lng'] ?? ['GPS:GPSLongitude','GPSLongitude']);
    $bearing = pickFirst($raw, $gpsKeys['bearing'] ?? ['GPS:GPSImgDirection','GPSImgDirection']);
    $zoom = pickFirst($raw, $gpsKeys['zoom'] ?? ['XMP:MapZoom','MapZoom']);

    $geo = [
      'lat'=>$lat, 'lng'=>$lng, 'bearing'=>$bearing,
      'zoom'=>($zoom !== null && $zoom !== '' ? $zoom : (buildMapCfg()['default_zoom'] ?? 16)),
      'country'=>$raw['IPTC:Country-PrimaryLocationName'] ?? ($raw['IPTC:Country'] ?? ''),
      'country_code'=>$raw['IPTC:Country-PrimaryLocationCode'] ?? '',
      'city'=>$raw['IPTC:City'] ?? '',
      'state'=>$raw['IPTC:Province-State'] ?? ($raw['IPTC:State'] ?? ''),
      'sublocation'=>$raw['IPTC:Sub-location'] ?? '',
    ];

    // Also include gps version float if present
    $verRaw = $raw['GPS:GPSVersionID'] ?? $raw['EXIF:GPSVersionID'] ?? $raw['GPSVersionID'] ?? null;
    if ($verRaw !== null) {
      $geo['gps_version_raw'] = $verRaw;
      $geo['gps_version_float'] = gpsVersionToFloat($verRaw);
    }

    jexit(['ok'=>true,'root'=>$root,'file'=>basename($file),'iptc'=>$iptc,'geo'=>$geo,'raw'=>$raw]);
  }

  if ($action === 'save') {
    exiftoolRequired();
    $body = (string)file_get_contents('php://input');
    $j = json_decode($body, true);
    if (!is_array($j)) throw new RuntimeException('Invalid JSON body');
    $file = (string)($j['file'] ?? '');
    $mode = (string)($j['mode'] ?? 'all');
    $iptc = is_array($j['iptc'] ?? null) ? $j['iptc'] : [];
    $geo = is_array($j['geo'] ?? null) ? $j['geo'] : [];

    $abs = safeResolve($file, $root);
    $templateFields = loadTemplateFields();
    $args = buildWriteArgs($templateFields, $iptc, $geo);
    if ($mode === 'core') {
      appendAliasArgs($args, $iptc);
    }


    $bin = resolveExiftool();
    if ($bin === null) throw new RuntimeException('ExifTool binary not found');
    $cmd = escapeshellarg($bin) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' ' . escapeshellarg($abs) . ' 2>&1';
    $out = (string)@shell_exec($cmd);

    jexit(['ok'=>true,'mode'=>$mode,'message'=>'Saved','exiftool_output'=>trim($out)]);
  }

  jexit(['ok'=>false,'error'=>'Unknown action'], 400);

} catch (Throwable $e) {
  jexit(['ok'=>false,'error'=>$e->getMessage()], 500);
}
