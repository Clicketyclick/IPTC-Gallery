# IPTC Browse + Gallery (PHP)

This repo is a small, self-contained PHP application for:

- Browsing folders recursively (tree navigation)
- Browsing media in the current folder (image/video thumbnails)
- Editing **Core IPTC** + **Location (GPS + bearing)** via ExifTool
- Editing **ALL IPTC fields** (template-driven)
- Building per-folder `metadata.json` caches for fast listing + thumbnail badges
- Viewing a simple gallery from `metadata.json`


> [!NOTE]  
> Please be ware that this app was build using ChatGBT!
>
> It's ment as a test of the capabilities of AI anno February 2026. Proceed with care.
>

---

## Repository layout (current)

```
.
├── gallery.php
├── iptc_api.php
├── iptc_browse_editor.css
├── iptc_browse_editor.php
├── iptc_edit.json
├── template_metadata.json 
├── testbench.php 
└── (per-folder) metadata.json
```

### What each file does

- **`gallery.php`**  
  Simple gallery page driven by `metadata.json`.

- **`iptc_api.php`**  
  JSON API used by the editors. Handles: tree/list/read/save/build-metadata + alias mapping.

- **`iptc_browse_editor.css`**  
  Styling for `iptc_browse_editor.php` (extracted from inline styles).

- **`iptc_browse_editor.php`**  
  Main UI: left tree + thumbnails, right preview + collapsible panels (Core IPTC / Location map / Advanced).

- **`iptc_edit.json`**  
  Shared configuration (ExifTool path, default roots, map settings, icons, metadata options).

- **`template_metadata.json`**  
  Template defining the full “ALL IPTC fields” set shown in Advanced.

- **`metadata.json`** (generated, per folder)  
  Cache of extracted metadata per directory (used for fast list + thumb overlays).

---

## Requirements

- PHP 8.x (works with PHP built-in server)
- ExifTool installed and accessible:
  - Configure explicit path in `iptc_edit.json` (`EXIFTOOL_PATH`) **or** ensure `exiftool` is on PATH.
- `shell_exec()` enabled in PHP
- Internet access (for Leaflet tile layers unless you self-host tiles)

---

## Quick start (PHP built-in server)

From this directory:

```bash
php -S localhost:8080
```

Open:

- Browse+Edit UI:  
  `http://localhost:8080/iptc_browse_editor.php?root=.&debug=1`

- Gallery:  
  `http://localhost:8080/gallery.php`

---

## Configuration (`iptc_edit.json`)

Common keys:

- `EXIFTOOL_PATH` (Windows example):  
  `"C:/Program Files/XnViewMP/AddOn/exiftool.exe"`

- `MEDIA_ROOT` (default base root):  
  `"."`

- `METADATA_JSON` (filename used within each folder):  
  `"metadata.json"`

- Metadata options:
  - `METADATA_INCLUDE_GPS_DECIMAL` (bool)
  - `METADATA_GPS_STORE_KEY` (string, default `"gps"`)

- Tree scan options:
  - `TREE_MAX_DEPTH` (default 8)
  - `TREE_SKIP_HIDDEN` (default true)

- Map layers (Leaflet base layers):
  - `MAP.tile_layers` (optional array)
  - `MAP.default_layer`

- Marker icons:
  - `MAP.ICONS.location_html` / `bearing_html`
  - `MAP.ICONS.location_size` / `location_anchor`
  - `MAP.ICONS.bearing_size` / `bearing_anchor`

---

## Browse editor URL parameters (`iptc_browse_editor.php`)

- `root=` (string) start directory relative to repo root
  - `.` = current directory  
- `debug=` `0|1` (optional)
- `file=` (optional) auto-load file in that folder

Examples:

- Start in current folder:
  - `iptc_browse_editor.php?root=.`
- Start in a subfolder:
  - `iptc_browse_editor.php?root=test3/a/2`
- Start and auto-load file:
  - `iptc_browse_editor.php?root=test3/a/2&file=IMG20260215155425.jpg`
- Debug mode:
  - `iptc_browse_editor.php?debug=1&root=.`

---

## API (`iptc_api.php`) endpoints + arguments

All API calls are JSON and support:

- `root=` (relative folder, no `..`)

### `action=diag`
Returns environment info and resolved ExifTool.

Example:
- `iptc_api.php?action=diag&root=.`

### `action=aliases`
Returns the alias mapping applied on **Core Save**.

Example:
- `iptc_api.php?action=aliases`

### `action=tree`
Returns recursive directory tree (depth-limited).

Example:
- `iptc_api.php?action=tree&root=.`

### `action=list`
Returns list of media files in the selected root.  
If `metadata.json` exists in that folder, list may include flags like `has_gps/has_bearing/has_location`.

Example:
- `iptc_api.php?action=list&root=test3/a/2`

### `action=read`
Reads all ExifTool JSON for a file and returns:
- `iptc` (template-driven IPTC dictionary)
- `geo` (lat/lng/bearing/country fields)
- `raw` (full ExifTool JSON object)

Args:
- `file=` filename inside `root`

Example:
- `iptc_api.php?action=read&root=test3/a/2&file=IMG20260215155425.jpg`

### `action=save` (POST)
Writes metadata to the file via ExifTool.

- URL: `iptc_api.php?action=save&root=<folder>`
- JSON body:
  - `file` (string)
  - `mode` = `"core"` or `"all"`
  - `iptc` (object)
  - `geo` (object)

Example (core save):

```bash
curl -X POST "http://localhost:8080/iptc_api.php?action=save&root=test3/a/2" ^
  -H "Content-Type: application/json" ^
  -d "{"file":"IMG20260215155425.jpg","mode":"core","iptc":{"IPTC:ObjectName":"My title","IPTC:Headline":"My headline"},"geo":{"lat":55.47,"lng":10.45,"bearing":180.0,"zoom":16}}"
```

### `action=build_metadata`
Builds `metadata.json` for the selected folder.

Example:
- `iptc_api.php?action=build_metadata&root=test3/a/2`

---

## Alias mappings (Core Save)

When saving with `mode=core`, the API also writes these alias fields:

- `IPTC:ObjectName` → `XMP-dc:Title`, `XMP:Title`
- `IPTC:Headline` → `XMP-photoshop:Headline`
- `IPTC:Caption-Abstract` → `XMP-dc:Description`, `XMP:Description`, `XMP-photoshop:Caption`
- `IPTC:Keywords` → `XMP-dc:Subject`, `XMP:Subject`
- `IPTC:By-line` → `XMP-dc:Creator`, `XMP:Creator`
- `IPTC:DateCreated` → `XMP-photoshop:DateCreated`, `XMP:CreateDate`
- `IPTC:SpecialInstructions` → `XMP-photoshop:Instructions`
- `IPTC:Country-PrimaryLocationName` → `XMP-photoshop:Country`
- `IPTC:Country-PrimaryLocationCode` → `XMP-iptcCore:CountryCode`
- `IPTC:Province-State` → `XMP-photoshop:State`
- `IPTC:City` → `XMP-photoshop:City`
- `IPTC:Sub-location` → `XMP-iptcCore:Location`

Retrieve as JSON:
- `iptc_api.php?action=aliases`

---

## Common workflows

### 1) Browse and edit a folder
1. Open: `iptc_browse_editor.php?root=some/folder`
2. Click a thumbnail
3. Edit Core IPTC and/or Location
4. Click **Save**
5. (Optional) Build metadata cache:
   - `iptc_api.php?action=build_metadata&root=some/folder`

### 2) Ensure thumbnail GPS/location badges work
Run build metadata for that folder:
- `iptc_api.php?action=build_metadata&root=some/folder`

### 3) Use the gallery
Ensure `metadata.json` exists in the folder you want to view, then open `gallery.php`.

---

## Troubleshooting

### “Unexpected token '<' … not valid JSON”
`iptc_api.php` returned HTML (PHP error).  
Open directly:
- `iptc_api.php?action=diag&root=.`  
and fix the reported error (ExifTool path, permissions, etc.).

### “Malformed UTF-8 characters …”
The API is configured to use UTF-8 tolerant JSON encoding (invalid sequences substituted).  
If you need strict diagnostics, add a validator pass to identify the offending tags.

### ExifTool not found / not runnable
- Confirm `EXIFTOOL_PATH` in `iptc_edit.json` is correct
- Or ensure `exiftool` is on PATH
- Confirm `shell_exec()` is enabled

---

## License
Add your preferred license.
