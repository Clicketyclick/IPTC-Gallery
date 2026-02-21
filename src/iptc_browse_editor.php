<?php
// iptc_browse_editor.php
declare(strict_types=1);
$debug = isset($_GET['debug']) && $_GET['debug'] !== '0';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>IPTC Browse Editor</title>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<style>
  body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:16px;max-width:1400px}
  .wrap{max-width:1400px;margin:0 auto}
  #layout{display:grid;grid-template-columns:460px 1fr 460px;gap:14px}
  #advPane{border:1px solid #ddd;border-radius:18px;padding:12px;background:#fff}
  #leftPane,#rightPane{border:1px solid #ddd;border-radius:18px;padding:12px;background:#fff}
  h2{margin:0 0 10px}
  h3{margin:10px 0 8px}
  label{display:block;font-size:12px;color:#333;margin:8px 0 4px}
  input[type="text"],input[type="number"],input[type="datetime-local"],textarea,select{width:100%;padding:8px;border:1px solid #ccc;border-radius:10px}
  textarea{min-height:70px}
  button{padding:10px 14px;border:0;border-radius:12px;background:#111;color:#fff;cursor:pointer}
  button.secondary{background:#555}
  button:disabled{opacity:.5;cursor:not-allowed}
  .hint{font-size:12px;color:#555}
  .ok{color:#0a0}
  .err{color:#b00}
  .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
  details{margin-top:10px}
  summary{cursor:pointer}

  /* Tree */
  #dirTree{margin-top:8px;max-height:280px;overflow:auto;border:1px solid #ddd;border-radius:12px;padding:8px;background:#fafafa}
  .tree ul{list-style:none;margin:0;padding-left:0}
  .tree li{margin:2px 0;padding-left:0}
  .tree .prefix{font-family:ui-monospace,Consolas,monospace;white-space:pre}
  .tree .folder{font-family:ui-monospace,Consolas,monospace}
  .tree .node{display:flex;gap:6px;align-items:center;cursor:pointer;padding:2px 4px;border-radius:8px}
  .tree .node:hover{background:rgba(0,0,0,0.06)}
  .tree .twisty{width:16px;text-align:center;user-select:none;cursor:pointer;font-size:16px;line-height:16px}
  .tree .folder{font-weight:600}
  .tree .active{background:rgba(0,0,0,0.10)}

  /* Thumbs */
  #thumbGrid{margin-top:10px;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px}
  .thumb{border:1px solid #ddd;border-radius:12px;overflow:hidden;background:#fafafa;cursor:pointer;position:relative}
  .thumb:hover{outline:2px solid rgba(0,0,0,0.15)}
  .thumb.active{outline:3px solid rgba(0,0,0,0.30)}
  .thumb img,.thumb video{width:100%;height:94px;object-fit:cover;display:block}
  .thumb .cap{padding:6px 8px;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .thumb .badgeType{position:absolute;top:6px;left:6px;background:rgba(0,0,0,0.65);color:#fff;font-size:11px;padding:2px 6px;border-radius:10px}
  .thumb .badges{position:absolute;top:6px;right:6px;display:flex;gap:4px}
  .thumb .b{background:rgba(255,255,255,0.90);border:1px solid rgba(0,0,0,0.25);font-size:12px;padding:1px 6px;border-radius:10px}
  /* Preview */
  #mediaPreview img,#mediaPreview video{max-width:100%;max-height:520px;border-radius:12px}
  #map{height:360px;border-radius:14px;border:1px solid #ddd}
  /* Marker icons */
  .loc-icon{display:flex;align-items:center;justify-content:center;text-shadow:0 1px 2px rgba(0,0,0,.25)}
  .eye-icon{display:flex;align-items:center;justify-content:center;text-shadow:0 1px 2px rgba(0,0,0,.25)}
  .eye-inner,.loc-inner{display:inline-block;transform-origin:50% 50%}
  table{width:100%;border-collapse:collapse}
  td,th{border-bottom:1px solid #eee;padding:6px;font-size:13px;vertical-align:top}
  th{font-size:12px;color:#555;text-align:left}
  .tag{font-family:ui-monospace,Consolas,monospace;font-size:12px}
</style>
</head>
<body>
<div class="wrap">
  <h2>IPTC Browse Editor</h2>
  <div id="layout">
    <div id="leftPane">
      <div class="row">
        <input id="treeFilter" type="text" placeholder="Filter folders…" style="flex:1;min-width:240px">
        <button id="reloadTree" class="secondary" type="button">Reload</button>
      </div>
      <div id="dirTree" aria-label="Directory tree"></div>

      <h3>Files</h3>
      <div class="hint" id="thumbHint"></div>
      <div id="thumbGrid"></div>
    </div>

    <div id="rightPane">
      <div class="row" style="margin-bottom:8px">
        <div id="status" class="hint" style="flex:1">Idle.</div>
        <button id="saveBtn" type="button">Save</button>
      </div>

      <div id="mediaPreview" style="border:1px solid #ddd;border-radius:14px;padding:8px;background:#fafafa;display:flex;justify-content:center;align-items:center;min-height:120px">
        <div class="hint">Select a file…</div>
      </div>

      <details open>
        <summary><b>Core IPTC</b></summary>
        <div class="grid2" style="margin-top:10px">
          <div><label>ObjectName (Title)</label><input id="objname" type="text"></div>
          <div><label>Headline</label><input id="headline" type="text"></div>
          <div style="grid-column:1/-1"><label>Caption-Abstract</label><textarea id="caption"></textarea></div>
          <div style="grid-column:1/-1"><label>Keywords (comma separated)</label><input id="keywords" type="text"></div>
          <div><label>By-line</label><input id="byline" type="text"></div>
          <div><label>DateCreated</label><input id="datecreated" type="datetime-local" step="1"></div>
          <div style="grid-column:1/-1"><label>SpecialInstructions</label><input id="special" type="text"></div>
        </div>
      </details>

      <div id="log" class="hint" style="margin-top:10px"></div>
    </div>

    <div id="advPane">
      <div class="row" style="margin-bottom:8px">
        <div class="hint" style="flex:1">Advanced fields</div>
        

<details open>
        <summary><b>Location</b></summary>
        <div style="margin-top:10px" class="grid2">
          <div><label>Latitude (decimal)</label><input id="lat" type="number" step="0.0000001"></div>
          <div><label>Longitude (decimal)</label><input id="lng" type="number" step="0.0000001"></div>
          <div><label>Latitude (DMS)</label><input id="lat_dms" type="text" disabled></div>
          <div><label>Longitude (DMS)</label><input id="lng_dms" type="text" disabled></div>
        </div>

        <div class="grid2" style="margin-top:10px">
          <div><label>Bearing (degrees)</label><input id="bearing" type="number" step="0.1" min="0" max="360"></div>
          <div><label>Bearing (compass)</label><input id="bearing_compass" type="text" disabled></div>
        </div>

        <div class="grid3" style="margin-top:10px">
          <div><label>Country code</label><select id="country_code"></select></div>
          <div style="grid-column:span 2"><label>Country</label><input id="country" type="text"></div>
        </div>
        <div class="grid2" style="margin-top:10px">
          <div><label>State/Province</label><input id="state" type="text"></div>
          <div><label>City</label><input id="city" type="text"></div>
        </div>
        <div style="margin-top:10px"><label>Sub-location</label><input id="sublocation" type="text"></div>

        <div style="margin-top:12px">
          <div class="row">
            <div class="hint" style="flex:1">Map (drag 📍 for position, drag 👁 to set bearing)</div>
            <div class="hint">Zoom: <span id="zoomLabel">–</span></div>
          </div>
          <div id="map"></div>
        </div>
      </details>


<button id="saveAllBtn" class="secondary" type="button">Save all IPTC fields</button>
</div>

      <details open>
        <summary><b>Advanced</b></summary>
        <div style="margin-top:10px;max-height:520px;overflow:auto;border:1px solid #eee;border-radius:12px;padding:8px;background:#fafafa">
          <table style="width:100%;border-collapse:collapse">
            <thead>
              <tr>
                <th style="width:40%;text-align:left;font-size:12px;color:#555;border-bottom:1px solid #eee;padding:6px">Tag</th>
                <th style="text-align:left;font-size:12px;color:#555;border-bottom:1px solid #eee;padding:6px">Value</th>
              </tr>
            </thead>
            <tbody id="allFieldsBody"></tbody>
          </table>
        </div>
      </details>

      <details id="rawDetails" style="margin-top:10px">
        <summary><b>All metadata (read-only)</b></summary>
        <div style="margin-top:10px">
          <input id="rawFilter" type="text" placeholder="Filter tags…" style="width:100%;padding:8px;border-radius:10px;border:1px solid #ddd">
        </div>
        <div style="margin-top:10px;max-height:520px;overflow:auto;border:1px solid #eee;border-radius:12px;padding:8px;background:#fafafa">
          <table style="width:100%;border-collapse:collapse">
            <thead>
              <tr>
                <th style="width:40%;text-align:left;font-size:12px;color:#555;border-bottom:1px solid #eee;padding:6px">Tag</th>
                <th style="text-align:left;font-size:12px;color:#555;border-bottom:1px solid #eee;padding:6px">Value</th>
              </tr>
            </thead>
            <tbody id="rawFieldsBody"></tbody>
          </table>
        </div>
      </details>

    </div>

  </div>
</div>

<script>
const DEBUG = <?php echo $debug ? 'true' : 'false'; ?>;

const $ = (id)=>document.getElementById(id);
function escapeHtml(s){ return String(s??'').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
async function safeJson(resp){
  const text = await resp.text();
  try { return {ok:true, data: JSON.parse(text), text}; }
  catch(e){ return {ok:false, error:'Invalid JSON', text, parseError:String(e)}; }
}
function setStatus(msg, ok=null){
  const el=$('status'); if(!el) return;
  el.textContent = msg||'';
  el.className = 'hint ' + (ok===true?'ok':ok===false?'err':'');
}

let MAPCFG = { tile_layers: null, default_layer: 'OpenStreetMap', default_lat:55.4439, default_lng:10.4310, default_zoom:16, default_bearing:0, min_dir_dist_m:18, dir_handle_dist_m:50, ICONS:null };
let TEMPLATE_FIELDS = [];
let CURRENT_ROOT = (new URLSearchParams(location.search).get('root')||'.');
let CURRENT_FILE = '';
let CURRENT_IPTC_ALL = {};
let CURRENT_RAW = {};
let FILE_ITEMS = [];
const TREE_OPEN = new Set();

function normRoot(r){
  r = (r||'').toString().trim().replace(/\\/g,'/').replace(/^\.?\//,'').replace(/\/+$/,'');
  return r === '' ? '.' : r;
}
CURRENT_ROOT = normRoot(CURRENT_ROOT);

function apiUrl(params){
  const usp = new URLSearchParams(params||{});
  usp.set('root', CURRENT_ROOT);
  return 'iptc_api.php?' + usp.toString();
}
function mediaUrl(rel){
  const f = (rel||'').toString().trim().replace(/^\.?\//,'');
  let r = normRoot(CURRENT_ROOT);
  if (r === '.') r = '';
  return encodeURI(r ? (r + '/' + f) : f);
}
function updateUrlRoot(){
  try{
    const usp = new URLSearchParams(location.search);
    usp.set('root', CURRENT_ROOT);
    history.replaceState(null,'', location.pathname + '?' + usp.toString());
  }catch(e){}
}

function toDMS(value, isLat){
  const v = Number(value);
  if(!isFinite(v)) return '';
  const abs = Math.abs(v);
  const deg = Math.floor(abs);
  const minFloat = (abs - deg) * 60;
  const min = Math.floor(minFloat);
  const sec = (minFloat - min) * 60;
  const hemi = isLat ? (v >= 0 ? 'N' : 'S') : (v >= 0 ? 'E' : 'W');
  return `${deg}°${String(min).padStart(2,'0')}'${sec.toFixed(1)}"${hemi}`;
}
function bearingToCompass(deg){
  const d = Number(deg);
  if(!isFinite(d)) return '';
  const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
  const x = ((d%360)+360)%360;
  return dirs[Math.round(x/22.5)%16];
}
function updateDerived(){
  $('lat_dms').value = toDMS($('lat').value, true);
  $('lng_dms').value = toDMS($('lng').value, false);
  $('bearing_compass').value = bearingToCompass($('bearing').value);
}

// Country list alpha-3
const COUNTRY_ALPHA3 = [
  ["AFG","Afghanistan"],["ALB","Albania"],["DZA","Algeria"],["ASM","American Samoa"],["AND","Andorra"],["AGO","Angola"],
    ["AIA","Anguilla"],["ATA","Antarctica"],["ATG","Antigua and Barbuda"],["ARG","Argentina"],["ARM","Armenia"],["ABW","Aruba"],
    ["AUS","Australia"],["AUT","Austria"],["AZE","Azerbaijan"],["BHS","Bahamas"],["BHR","Bahrain"],["BGD","Bangladesh"],
    ["BRB","Barbados"],["BLR","Belarus"],["BEL","Belgium"],["BLZ","Belize"],["BEN","Benin"],["BMU","Bermuda"],
    ["BTN","Bhutan"],["BOL","Bolivia"],["BES","Bonaire, Sint Eustatius and Saba"],["BIH","Bosnia and Herzegovina"],["BWA","Botswana"],
    ["BVT","Bouvet Island"],["BRA","Brazil"],["IOT","British Indian Ocean Territory"],["BRN","Brunei"],["BGR","Bulgaria"],["BFA","Burkina Faso"],
    ["BDI","Burundi"],["CPV","Cabo Verde"],["KHM","Cambodia"],["CMR","Cameroon"],["CAN","Canada"],["CYM","Cayman Islands"],
    ["CAF","Central African Republic"],["TCD","Chad"],["CHL","Chile"],["CHN","China"],["CXR","Christmas Island"],["CCK","Cocos (Keeling) Islands"],
    ["COL","Colombia"],["COM","Comoros"],["COG","Congo"],["COD","Congo (Democratic Republic)"],["COK","Cook Islands"],["CRI","Costa Rica"],
    ["HRV","Croatia"],["CUB","Cuba"],["CUW","Curaçao"],["CYP","Cyprus"],["CZE","Czechia"],["CIV","Côte d’Ivoire"],
    ["DNK","Denmark"],["DJI","Djibouti"],["DMA","Dominica"],["DOM","Dominican Republic"],["ECU","Ecuador"],["EGY","Egypt"],
    ["SLV","El Salvador"],["GNQ","Equatorial Guinea"],["ERI","Eritrea"],["EST","Estonia"],["SWZ","Eswatini"],["ETH","Ethiopia"],
    ["FLK","Falkland Islands"],["FRO","Faroe Islands"],["FJI","Fiji"],["FIN","Finland"],["FRA","France"],["GUF","French Guiana"],
    ["PYF","French Polynesia"],["ATF","French Southern Territories"],["GAB","Gabon"],["GMB","Gambia"],["GEO","Georgia"],["DEU","Germany"],
    ["GHA","Ghana"],["GIB","Gibraltar"],["GRC","Greece"],["GRL","Greenland"],["GRD","Grenada"],["GLP","Guadeloupe"],
    ["GUM","Guam"],["GTM","Guatemala"],["GGY","Guernsey"],["GIN","Guinea"],["GNB","Guinea-Bissau"],["GUY","Guyana"],
    ["HTI","Haiti"],["HMD","Heard Island and McDonald Islands"],["VAT","Holy See"],["HND","Honduras"],["HKG","Hong Kong"],["HUN","Hungary"],
    ["ISL","Iceland"],["IND","India"],["IDN","Indonesia"],["IRN","Iran"],["IRQ","Iraq"],["IRL","Ireland"],["IMN","Isle of Man"],
    ["ISR","Israel"],["ITA","Italy"],["JAM","Jamaica"],["JPN","Japan"],["JEY","Jersey"],["JOR","Jordan"],["KAZ","Kazakhstan"],
    ["KEN","Kenya"],["KIR","Kiribati"],["PRK","Korea (North)"],["KOR","Korea (South)"],["KWT","Kuwait"],["KGZ","Kyrgyzstan"],
    ["LAO","Laos"],["LVA","Latvia"],["LBN","Lebanon"],["LSO","Lesotho"],["LBR","Liberia"],["LBY","Libya"],["LIE","Liechtenstein"],
    ["LTU","Lithuania"],["LUX","Luxembourg"],["MAC","Macao"],["MDG","Madagascar"],["MWI","Malawi"],["MYS","Malaysia"],["MDV","Maldives"],
    ["MLI","Mali"],["MLT","Malta"],["MHL","Marshall Islands"],["MTQ","Martinique"],["MRT","Mauritania"],["MUS","Mauritius"],["MYT","Mayotte"],
    ["MEX","Mexico"],["FSM","Micronesia"],["MDA","Moldova"],["MCO","Monaco"],["MNG","Mongolia"],["MNE","Montenegro"],["MSR","Montserrat"],
    ["MAR","Morocco"],["MOZ","Mozambique"],["MMR","Myanmar"],["NAM","Namibia"],["NRU","Nauru"],["NPL","Nepal"],["NLD","Netherlands"],
    ["NCL","New Caledonia"],["NZL","New Zealand"],["NIC","Nicaragua"],["NER","Niger"],["NGA","Nigeria"],["NIU","Niue"],["NFK","Norfolk Island"],
    ["MKD","North Macedonia"],["MNP","Northern Mariana Islands"],["NOR","Norway"],["OMN","Oman"],["PAK","Pakistan"],["PLW","Palau"],
    ["PSE","Palestine"],["PAN","Panama"],["PNG","Papua New Guinea"],["PRY","Paraguay"],["PER","Peru"],["PHL","Philippines"],["PCN","Pitcairn"],
    ["POL","Poland"],["PRT","Portugal"],["PRI","Puerto Rico"],["QAT","Qatar"],["ROU","Romania"],["RUS","Russia"],["RWA","Rwanda"],
    ["REU","Réunion"],["BLM","Saint Barthélemy"],["SHN","Saint Helena"],["KNA","Saint Kitts and Nevis"],["LCA","Saint Lucia"],
    ["MAF","Saint Martin"],["SPM","Saint Pierre and Miquelon"],["VCT","Saint Vincent and the Grenadines"],["WSM","Samoa"],
    ["SMR","San Marino"],["STP","Sao Tome and Principe"],["SAU","Saudi Arabia"],["SEN","Senegal"],["SRB","Serbia"],["SYC","Seychelles"],
    ["SLE","Sierra Leone"],["SGP","Singapore"],["SXM","Sint Maarten"],["SVK","Slovakia"],["SVN","Slovenia"],["SLB","Solomon Islands"],
    ["SOM","Somalia"],["ZAF","South Africa"],["SGS","South Georgia and the South Sandwich Islands"],["SSD","South Sudan"],["ESP","Spain"],
    ["LKA","Sri Lanka"],["SDN","Sudan"],["SUR","Suriname"],["SJM","Svalbard and Jan Mayen"],["SWE","Sweden"],["CHE","Switzerland"],
    ["SYR","Syria"],["TWN","Taiwan"],["TJK","Tajikistan"],["TZA","Tanzania"],["THA","Thailand"],["TLS","Timor-Leste"],["TGO","Togo"],
    ["TKL","Tokelau"],["TON","Tonga"],["TTO","Trinidad and Tobago"],["TUN","Tunisia"],["TUR","Turkey"],["TKM","Turkmenistan"],
    ["TCA","Turks and Caicos Islands"],["TUV","Tuvalu"],["UGA","Uganda"],["UKR","Ukraine"],["ARE","United Arab Emirates"],["GBR","United Kingdom"],
    ["USA","United States"],["UMI","United States Minor Outlying Islands"],["URY","Uruguay"],["UZB","Uzbekistan"],["VUT","Vanuatu"],
    ["VEN","Venezuela"],["VNM","Vietnam"],["VGB","Virgin Islands (British)"],["VIR","Virgin Islands (U.S.)"],["WLF","Wallis and Futuna"],
    ["ESH","Western Sahara"],["YEM","Yemen"],["ZMB","Zambia"],["ZWE","Zimbabwe"],["ALA","Åland Islands"]
];
const COUNTRY_BY_CODE = Object.fromEntries(COUNTRY_ALPHA3.map(([c,n])=>[c,n]));
const CODE_BY_COUNTRY = Object.fromEntries(COUNTRY_ALPHA3.map(([c,n])=>[n.toLowerCase(),c]));
function initCountryDropdown(){
  const sel = $('country_code');
  sel.innerHTML = '';
  const o0=document.createElement('option'); o0.value=''; o0.textContent='(none)'; sel.appendChild(o0);
  for(const [c,n] of COUNTRY_ALPHA3){
    const o=document.createElement('option'); o.value=c; o.textContent=c+' — '+n; sel.appendChild(o);
  }
  sel.addEventListener('change', ()=>{
    const c = sel.value;
    if(c && COUNTRY_BY_CODE[c]) $('country').value = COUNTRY_BY_CODE[c];
  });
  $('country').addEventListener('blur', ()=>{
    const nm = ($('country').value||'').trim().toLowerCase();
    if(nm && CODE_BY_COUNTRY[nm]) sel.value = CODE_BY_COUNTRY[nm];
  });
}

async function loadDiag(){
  const r = await fetch('iptc_api.php?action=diag&root=' + encodeURIComponent(CURRENT_ROOT));
  const sj = await safeJson(r);
  if(!sj.ok) throw new Error('diag JSON failed: ' + sj.text.slice(0,200));
  const d = sj.data;
  if(!d.ok) throw new Error(d.error || 'diag failed');
  if(d.cfg && d.cfg.MAP) MAPCFG = Object.assign(MAPCFG, d.cfg.MAP);
}

async function loadTemplate(){
  const r = await fetch('iptc_api.php?action=fields');
  const sj = await safeJson(r);
  if(!sj.ok) throw new Error('fields JSON failed: ' + sj.text.slice(0,200));
  const d = sj.data;
  if(!d.ok) throw new Error(d.error || 'fields failed');
  TEMPLATE_FIELDS = d.fields || [];
}

function pathIsPrefix(prefix, p){
  prefix = (prefix||'').replace(/\\/g,'/').replace(/^\.?\//,'').replace(/\/+$/,'');
  p = (p||'').replace(/\\/g,'/').replace(/^\.?\//,'').replace(/\/+$/,'');
  if(prefix==='') return true;
  return p===prefix || p.startsWith(prefix + '/');
}

function renderTreeNode(node, filter, pipes){
  pipes = Array.isArray(pipes) ? pipes : [];

  const name = node.name || '';
  const path = (node.path||'').replace(/\\/g,'/');
  const kids = Array.isArray(node.children) ? node.children : [];
  const f = (filter||'').toLowerCase();
  const match = !f || name.toLowerCase().includes(f) || path.toLowerCase().includes(f);

  const childHtml = [];
  let anyKid = false;

  for(let i=0;i<kids.length;i++){
    const ch = kids[i];
    const isLast = (i === kids.length-1);
    const h = renderTreeNode(ch, filter, pipes.concat([!isLast]));
    if(h){ anyKid = true; childHtml.push(h); }
  }
  if(!match && !anyKid) return '';

  const hasKids = kids.length>0;
  const cur = normRoot(CURRENT_ROOT);
  const curPath = (cur==='.'?'':cur);

  const expanded = (path==='' || f) ? true : (TREE_OPEN.has(path) || pathIsPrefix(curPath, path) || pathIsPrefix(path, curPath));
  const active = (cur==='.' ? (path==='') : (cur===path));

  // Build prefix with │   /     and branch with ├─── / └───
  let prefix = '';
  for(let i=0;i<pipes.length-1;i++){
    prefix += pipes[i] ? '│   ' : '    ';
  }
  const isLastHere = (pipes.length>0 ? !pipes[pipes.length-1] : false);
  const branch = (pipes.length===0) ? '' : (isLastHere ? '└───' : '├───');

  let html = `<li data-path="${encodeURIComponent(path)}">`;
  html += `<div class="node${active?' active':''}" data-path="${encodeURIComponent(path)}" data-has="${hasKids?'1':'0'}">`;
  html += `<span class="prefix" data-toggle="${hasKids?'1':'0'}">${escapeHtml(prefix + branch)}</span>`;
  html += `<span class="folder">${escapeHtml(name||'(root)')}</span>`;
  html += `</div>`;

  if(hasKids){
    html += `<ul style="${expanded?'':'display:none'}">${childHtml.join('')}</ul>`;
  }
  html += `</li>`;
  return html;
}

async function loadDirTree(){
  const box = $('dirTree');
  const filter = ($('treeFilter').value||'').trim();
  const r = await fetch('iptc_api.php?action=tree&root=.');
  const sj = await safeJson(r);
  if(!sj.ok) { box.innerHTML = '<div class="err">Tree JSON failed</div>'; return; }
  const d = sj.data;
  if(!d.ok) { box.innerHTML = '<div class="err">'+escapeHtml(d.error||'tree failed')+'</div>'; return; }
  box.innerHTML = `<div class="tree"><ul>${renderTreeNode(d.tree, filter)}</ul></div>`;
}

function bindTreeClicks(){
  $('dirTree').addEventListener('click', async (ev)=>{
    const nodeEl = ev.target.closest('.node');
    if(!nodeEl) return;
    const path = decodeURIComponent(nodeEl.getAttribute('data-path')||'');
    const hasKids = nodeEl.getAttribute('data-has')==='1';
    const clickedTwisty = ev.target.classList.contains('twisty') || ev.target.getAttribute('data-tw')==='1';
    if(hasKids && clickedTwisty){
      if(TREE_OPEN.has(path)) TREE_OPEN.delete(path); else TREE_OPEN.add(path);
      await loadDirTree();
      return;
    }
    CURRENT_ROOT = normRoot(path || '.');
    if(path) TREE_OPEN.add(path);
    updateUrlRoot();
    CURRENT_FILE = '';
    updatePreview('');
    await loadDirTree();
    await loadFileList();
    const qsFile = (new URLSearchParams(location.search).get('file')||'').trim();
    if(qsFile){
      const f = qsFile.includes('/') ? qsFile.split('/').pop() : qsFile;
      await loadFile(f);
    }
  });
}

async function loadFileList(){
  const r = await fetch(apiUrl({action:'list'}));
  const sj = await safeJson(r);
  if(!sj.ok) { setStatus('List JSON failed: ' + sj.text.slice(0,120), false); return; }
  const d = sj.data;
  if(!d.ok) { setStatus('List failed: ' + (d.error||''), false); return; }
  FILE_ITEMS = d.items || [];
  renderThumbs(FILE_ITEMS);
  $('thumbHint').textContent = `${FILE_ITEMS.length} file(s) in ${CURRENT_ROOT==='.'?'./':'./'+CURRENT_ROOT}`;
  setStatus(`Loaded ${FILE_ITEMS.length} file(s).`, true);
}

function renderThumbs(items){
  const box = $('thumbGrid');
  if(!box) return;
  const cur = (CURRENT_FILE||'').replace(/^\.\//,'');
  const parts = [];
  for(const it of (items||[])){
    // API returns {file,title,objectname,headline,has_gps,has_bearing,has_location}
    let f = (it.file||it.FileName||it.SourceFile||'').toString();
    if(!f) continue;
    // If metadata accidentally includes a path, keep basename for read/save
    if(f.includes('/')) f = f.split('/').pop();
    const ext = (f.split('.').pop()||'').toLowerCase();
    const isVid = (ext==='mp4' || ext==='webm' || ext==='mov');
    const url = mediaUrl(f) + '?v=' + Date.now();
    const active = (f===cur);
    parts.push(`<div class="thumb${active?' active':''}" data-file="${encodeURIComponent(f)}">`);

    // Media type badge (left)
    parts.push(`<div class="badge">${isVid?'🎞':'🖼'}</div>`);

    // Feature flags (right)
    const hasGps = !!(it.has_gps);
    const hasBear = !!(it.has_bearing);
    const hasLoc = !!(it.has_location);
    const flags = (hasGps?'📍':'') + (hasBear?'👁':'') + (hasLoc?'🗺️':'');
    if(flags){
      parts.push(`<div class="badge" style="left:auto;right:6px">${flags}</div>`);
    }

    if(isVid){
      parts.push(`<video muted playsinline preload="metadata" src="${url}"></video>`);
    } else {
      parts.push(`<img src="${url}" alt="">`);
    }

    // Caption rule:
    // "ObjectName :" if non-empty, then Headline or FileName. Tooltip is filename.
    const obj = (it.objectname||'').toString().trim();
    const head = (it.headline||'').toString().trim();
    const main = head || f;
    const capText = obj ? (obj + ' : ' + main) : main;

    parts.push(`<div class="cap" title="${escapeHtml(f)}">${escapeHtml(capText)}</div>`);
    parts.push(`</div>`);
  }
  box.innerHTML = parts.join('');

  // Make video thumbs show a frame
  box.querySelectorAll('video').forEach(v=>{
    v.addEventListener('loadedmetadata', ()=>{ try{ v.currentTime = Math.min(0.1, v.duration? Math.max(0, v.duration*0.01):0.1);}catch(e){} }, {once:true});
  });

  $('thumbHint').textContent = `${(items||[]).length} file(s) in ${CURRENT_ROOT==='.'?'./':'./'+CURRENT_ROOT}`;
}


$('thumbGrid').addEventListener('click', async (ev)=>{
  const t = ev.target.closest('.thumb');
  if(!t) return;
  const file = decodeURIComponent(t.getAttribute('data-file')||'');
  if(!file) return;
  await loadFile(file);
});

function updatePreview(file){
  const box = $('mediaPreview');
  if(!file){
    box.innerHTML = '<div class="hint">Select a file…</div>';
    return;
  }
  const ext = (file.split('.').pop()||'').toLowerCase();
  const isVid = (ext==='mp4'||ext==='webm'||ext==='mov');
  const url = mediaUrl(file) + '?v=' + Date.now();
  if(isVid){
    box.innerHTML = `<video controls autoplay muted playsinline preload="metadata"><source src="${url}"></video>`;
  } else {
    box.innerHTML = `<img src="${url}" alt="Preview">`;
  }
}

/* -------- Leaflet map (interactive) -------- */
let map=null, posMarker=null, dirMarker=null, dirLine=null, baseLayers=null;
function deg2rad(d){ return d*Math.PI/180; }
function rad2deg(r){ return r*180/Math.PI; }
function offsetLatLng(lat,lng,meters,bearingDeg){
  const R=6378137;
  const br=deg2rad(bearingDeg);
  const d=meters/R;
  const lat1=deg2rad(lat), lng1=deg2rad(lng);
  const lat2=Math.asin(Math.sin(lat1)*Math.cos(d)+Math.cos(lat1)*Math.sin(d)*Math.cos(br));
  const lng2=lng1+Math.atan2(Math.sin(br)*Math.sin(d)*Math.cos(lat1), Math.cos(d)-Math.sin(lat1)*Math.sin(lat2));
  return [rad2deg(lat2), rad2deg(lng2)];
}
function bearingBetween(lat1,lng1,lat2,lng2){
  const φ1=deg2rad(lat1), φ2=deg2rad(lat2);
  const Δλ=deg2rad(lng2-lng1);
  const y=Math.sin(Δλ)*Math.cos(φ2);
  const x=Math.cos(φ1)*Math.sin(φ2)-Math.sin(φ1)*Math.cos(φ2)*Math.cos(Δλ);
  let θ=rad2deg(Math.atan2(y,x));
  return (θ+360)%360;
}
function rotateEye(deg){
  if(!dirMarker) return;
  const el = dirMarker.getElement();
  if(!el) return;
  const inner = el.querySelector('.eye-inner');
  if(inner){ inner.style.transform = 'rotate(' + deg + 'deg)'; }
}

function makeDivIcon(kind, html, size, anchor){
  const w = Number(size?.[0] ?? 24), h = Number(size?.[1] ?? 24);
  let ax = Number(anchor?.[0]); let ay = Number(anchor?.[1]);
  if(!isFinite(ax) || !isFinite(ay) || ax>w || ay>h) { ax = Math.round(w/2); ay = Math.round(h/2); }
  const font = Math.max(12, Math.round(w*0.70));
  const cls = (kind==='loc') ? 'loc-icon' : 'eye-icon';
  const innerCls = (kind==='loc') ? 'loc-inner' : 'eye-inner';
  return L.divIcon({className: cls, html: `<span class="${innerCls}" style="font-size:${font}px;line-height:${font}px">${html}</span>`, iconSize:[w,h], iconAnchor:[ax,ay]});
}
function initMapIfNeeded(lat,lng,zoom,bearing){
  if(map) return;
  map = L.map('map', { zoomControl:true }).setView([lat,lng], zoom);
  baseLayers = {};
  const layers = Array.isArray(MAPCFG.tile_layers) ? MAPCFG.tile_layers : [];
  for(const lyr of layers){
    if(!lyr || !lyr.url) continue;
    baseLayers[lyr.name||lyr.url] = L.tileLayer(lyr.url, { maxZoom: lyr.maxZoom ?? 22, attribution: lyr.attribution||'' });
  }
  const def = MAPCFG.default_layer || Object.keys(baseLayers)[0];
  const active = baseLayers[def] || baseLayers[Object.keys(baseLayers)[0]];
  if(active) active.addTo(map);
  if(Object.keys(baseLayers).length > 1) L.control.layers(baseLayers, null, {collapsed:true}).addTo(map);

  const icons = MAPCFG.ICONS || {};
  const locIcon = makeDivIcon('loc', icons.location_html || '📍', icons.location_size || [24,24], icons.location_anchor || [12,24]);
  const eyeIcon = makeDivIcon('eye', icons.bearing_html || '👁', icons.bearing_size || [42,42], icons.bearing_anchor || [21,21]);

  posMarker = L.marker([lat,lng], {draggable:true, icon: locIcon, zIndexOffset:1000}).addTo(map);
  const handleDist = Number(MAPCFG.dir_handle_dist_m || 50);
  const [dl,dn] = offsetLatLng(lat,lng,handleDist,bearing);
  dirMarker = L.marker([dl,dn], {draggable:true, icon: eyeIcon, zIndexOffset:1100}).addTo(map);
  rotateEye(bearing);
  dirLine = L.polyline([[lat,lng],[dl,dn]]).addTo(map);

  posMarker.on('drag', ()=>syncInputsFromMap());
  dirMarker.on('drag', ()=>syncInputsFromMap());
  map.on('click', (e)=>{ posMarker.setLatLng(e.latlng); syncInputsFromMap(); });
  map.on('zoomend', ()=>{ $('zoomLabel').textContent = map.getZoom(); });
}
function ensureDirNotIdentical(){
  if(!map||!posMarker||!dirMarker) return;
  const p=posMarker.getLatLng(); const d=dirMarker.getLatLng();
  const dist=map.distance(p,d);
  const minDist=Number(MAPCFG.min_dir_dist_m||18);
  const b = Number($('bearing').value||0);
  const target = Math.max(minDist, Number(MAPCFG.dir_handle_dist_m||50));
  if(!isFinite(dist) || dist < minDist){
    const [lat2,lng2]=offsetLatLng(p.lat,p.lng,target,b);
    dirMarker.setLatLng([lat2,lng2]);
  }
}
function syncInputsFromMap(){
  if(!map||!posMarker||!dirMarker) return;
  const p=posMarker.getLatLng();
  $('lat').value = p.lat.toFixed(7);
  $('lng').value = p.lng.toFixed(7);
  const d=dirMarker.getLatLng();
  const b=bearingBetween(p.lat,p.lng,d.lat,d.lng);
  $('bearing').value = b.toFixed(1);
  rotateEye(b);
  updateDerived();
  ensureDirNotIdentical();
  dirLine.setLatLngs([posMarker.getLatLng(), dirMarker.getLatLng()]);
  $('zoomLabel').textContent = map.getZoom();
}
function syncMapFromInputs(){
  if(!map||!posMarker||!dirMarker) return;
  const lat = Number($('lat').value); const lng = Number($('lng').value);
  let b = Number($('bearing').value); if(!isFinite(b)) b=0;
  const z = map.getZoom();
  posMarker.setLatLng([lat,lng]);
  const handleDist = Number(MAPCFG.dir_handle_dist_m||50);
  const [dl,dn]=offsetLatLng(lat,lng,handleDist,b);
  dirMarker.setLatLng([dl,dn]);
  rotateEye(b);
  ensureDirNotIdentical();
  dirLine.setLatLngs([posMarker.getLatLng(), dirMarker.getLatLng()]);
  updateDerived();
  $('zoomLabel').textContent = z;
}

$('lat').addEventListener('change', ()=>{ updateDerived(); syncMapFromInputs(); });
$('lng').addEventListener('change', ()=>{ updateDerived(); syncMapFromInputs(); });
$('bearing').addEventListener('change', ()=>{ updateDerived(); syncMapFromInputs(); });

/* -------- Read / Save -------- */
function exifDateToLocalInput(v){
  if(v==null) return '';
  let s = String(v).trim();
  if(!s) return '';

  // Strip timezone suffix (e.g. +01:00, +0100, Z). datetime-local has no TZ.
  s = s.replace(/(Z|[+-]\d{2}:?\d{2})$/, '').trim();

  // YYYY:MM:DD HH:MM:SS
  let m = s.match(/^(\d{4}):(\d{2}):(\d{2})\s+(\d{2}):(\d{2}):(\d{2})$/);
  if(m) return `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}:${m[6]}`;

  // YYYY:MM:DD HH:MM
  m = s.match(/^(\d{4}):(\d{2}):(\d{2})\s+(\d{2}):(\d{2})$/);
  if(m) return `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}:00`;

  // YYYY:MM:DD
  m = s.match(/^(\d{4}):(\d{2}):(\d{2})$/);
  if(m) return `${m[1]}-${m[2]}-${m[3]}T00:00:00`;

  // YYYY-MM-DDTHH:MM:SS
  m = s.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})$/);
  if(m) return `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}:${m[6]}`;

  // YYYY-MM-DD HH:MM:SS
  m = s.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})$/);
  if(m) return `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}:${m[6]}`;

  // YYYY-MM-DDTHH:MM or YYYY-MM-DD HH:MM
  m = s.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})$/);
  if(m) return `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}:00`;

  // YYYY-MM-DD
  m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if(m) return `${m[1]}-${m[2]}-${m[3]}T00:00:00`;

  return '';
}

function fillCoreFromIptc(iptc){
  $('objname').value = iptc['IPTC:ObjectName'] || '';
  $('headline').value = iptc['IPTC:Headline'] || '';
  $('caption').value = iptc['IPTC:Caption-Abstract'] || '';
  const kw = iptc['IPTC:Keywords'];
  $('keywords').value = Array.isArray(kw) ? kw.join(', ') : (kw||'');
  $('byline').value = iptc['IPTC:By-line'] || '';
  $('datecreated').value = exifDateToLocalInput(iptc['IPTC:DateCreated']||'') || '';
  $('special').value = iptc['IPTC:SpecialInstructions'] || '';
}
function fillLocationFromGeo(geo){
  let lat = Number(geo.lat); if(!isFinite(lat)) lat = Number(MAPCFG.default_lat);
  let lng = Number(geo.lng); if(!isFinite(lng)) lng = Number(MAPCFG.default_lng);
  let bearing = Number(geo.bearing); if(!isFinite(bearing)) bearing = Number(MAPCFG.default_bearing||0);
  let zoom = parseInt(geo.zoom||MAPCFG.default_zoom||16,10); if(!isFinite(zoom)) zoom = 16;

  $('lat').value = lat.toFixed(7);
  $('lng').value = lng.toFixed(7);
  $('bearing').value = ((bearing%360)+360)%360;
  $('country').value = geo.country || '';
  $('country_code').value = geo.country_code || '';
  $('city').value = geo.city || '';
  $('state').value = geo.state || '';
  $('sublocation').value = geo.sublocation || '';
  updateDerived();

  initMapIfNeeded(lat,lng,zoom,bearing);
  map.setView([lat,lng], zoom);
  $('zoomLabel').textContent = zoom;
  syncMapFromInputs();
}


function renderRawFields(raw){
  const body = $('rawFieldsBody');
  if(!body) return;
  const filter = ( ($('rawFilter') && $('rawFilter').value) ? $('rawFilter').value.trim().toLowerCase() : '' );
  body.innerHTML = '';
  const keys = Object.keys(raw||{}).sort((a,b)=>a.localeCompare(b));
  for(const k of keys){
    const kk = String(k);
    if(filter && !kk.toLowerCase().includes(filter)) continue;
    let v = raw[k];
    if(Array.isArray(v)) v = v.join(', ');
    else if(v && typeof v === 'object') v = JSON.stringify(v);
    v = (v==null?'':String(v));

    const tr = document.createElement('tr');
    const td1 = document.createElement('td');
    td1.innerHTML = `<span class="tag">${escapeHtml(kk)}</span>`;
    td1.style.verticalAlign = 'top';

    const td2 = document.createElement('td');
    const isLong = v.length > 160;
    if(isLong){
      const ta = document.createElement('textarea');
      ta.readOnly = true;
      ta.rows = 3;
      ta.style.width = '100%';
      ta.value = v;
      td2.appendChild(ta);
    } else {
      const inp = document.createElement('input');
      inp.type = 'text';
      inp.readOnly = true;
      inp.style.width = '100%';
      inp.value = v;
      td2.appendChild(inp);
    }
    tr.appendChild(td1); tr.appendChild(td2);
    body.appendChild(tr);
  }
}

function normalizeRawValue(v){
  if(v == null) return '';
  if(Array.isArray(v)) return v.map(x=>String(x)).join(', ');
  if(typeof v === 'object') {
    try { return JSON.stringify(v); } catch(e){ return String(v); }
  }
  return String(v);
}

function rawLookupAny(keys){
  for(const k of keys){
    if(k in CURRENT_RAW) return CURRENT_RAW[k];
  }
  return undefined;
}

function lookupRawForIptc(tag){
  if(!CURRENT_RAW) return undefined;
  const short = tag.replace(/^IPTC:/,'');
  const direct = rawLookupAny([tag, 'IPTC:'+short, short]);
  if(direct !== undefined) return direct;

  // Try any key that ends with ':' + short (e.g. EXIF:Artist for By-line, etc.)
  const suffix = ':' + short;
  let found;
  for(const k in CURRENT_RAW){
    if(k.endsWith(suffix)){
      found = CURRENT_RAW[k];
      break;
    }
  }
  if(found !== undefined) return found;

  // Special mappings
  const map = {
    'IPTC:ObjectName': ['XMP-dc:Title','XMP:Title','Title','IPTC:ObjectName','IPTC:Headline','Headline'],
    'IPTC:Headline': ['IPTC:Headline','Headline','XMP-dc:Title','XMP:Title','Title'],
    'IPTC:Caption-Abstract': ['IPTC:Caption-Abstract','Caption-Abstract','EXIF:ImageDescription','ImageDescription','XMP-dc:Description','XMP:Description','Description'],
    'IPTC:Keywords': ['IPTC:Keywords','Keywords','XMP-dc:Subject','XMP:Subject','Subject'],
    'IPTC:By-line': ['IPTC:By-line','By-line','EXIF:Artist','Artist','XMP-dc:Creator','XMP:Creator','Creator'],
    'IPTC:DateCreated': ['IPTC:DateCreated','DateCreated','EXIF:DateTimeOriginal','DateTimeOriginal','EXIF:CreateDate','CreateDate','XMP:CreateDate','Composite:DateTimeOriginal'],
    'IPTC:Country-PrimaryLocationName': ['IPTC:Country-PrimaryLocationName','Country-PrimaryLocationName','XMP:Country','Country'],
    'IPTC:Country-PrimaryLocationCode': ['IPTC:Country-PrimaryLocationCode','Country-PrimaryLocationCode','CountryCode'],
    'IPTC:City': ['IPTC:City','City','XMP:City'],
    'IPTC:Province-State': ['IPTC:Province-State','Province-State','State','XMP:State'],
    'IPTC:Sub-location': ['IPTC:Sub-location','Sub-location','Sublocation','XMP:Location']
  };
  if(map[tag]){
    const v = rawLookupAny(map[tag]);
    if(v !== undefined) return v;
  }
  return undefined;
}

function mergeRawIntoIptcTable(overwrite=false){
  const body = $('allFieldsBody');
  if(!body) return;
  if(!CURRENT_RAW || Object.keys(CURRENT_RAW).length === 0){
    setStatus('No raw metadata available to merge.', false);
    return;
  }
  let changed = 0;
  body.querySelectorAll('input[data-tag]').forEach(inp=>{
    const tag = inp.dataset.tag;
    const cur = (inp.value||'').trim();
    if(!overwrite && cur !== '') return;

    let rawVal = lookupRawForIptc(tag);
    if(rawVal === undefined) return;
    let str = normalizeRawValue(rawVal).trim();
    if(!str) return;

    if(inp.type === 'datetime-local'){
      const dt = exifDateToLocalInput(str);
      if(dt) { inp.value = dt; changed++; }
      return;
    }

    inp.value = str;
    changed++;
  });

  setStatus(`Merged ${changed} field(s) from "All metadata" into "All IPTC fields".`, true);
}

function renderAdvancedFields(iptc){
  const body = $('allFieldsBody');
  body.innerHTML = '';
  for(const tag of TEMPLATE_FIELDS){
    const short = tag.replace(/^IPTC:/,'');
    const isDate = /Date|Time/i.test(short) || ['DateCreated','ReleaseDate','ReleaseTime','TimeCreated'].includes(short);
    let val = iptc[tag];
    if(Array.isArray(val)) val = val.join(', ');
    val = (val==null?'':String(val));

    const tr = document.createElement('tr');
    const td1 = document.createElement('td');
    td1.innerHTML = `<span class="tag">${escapeHtml(tag)}</span>`;
    const td2 = document.createElement('td');
    const inp = document.createElement('input');
    inp.dataset.tag = tag;
    inp.type = isDate ? 'datetime-local' : 'text';
    if(isDate) inp.value = exifDateToLocalInput(val) || '';
    else inp.value = val;
    td2.appendChild(inp);
    tr.appendChild(td1); tr.appendChild(td2);
    body.appendChild(tr);
  }
}

async function loadFile(file){
  CURRENT_FILE = file;
  updatePreview(file);
  renderThumbs(FILE_ITEMS);
  const r = await fetch(apiUrl({action:'read', file}));
  const sj = await safeJson(r);
  if(!sj.ok){ setStatus('Read JSON failed: '+sj.text.slice(0,120), false); return; }
  const d = sj.data;
  if(!d.ok){ setStatus('Read failed: '+(d.error||''), false); return; }

  CURRENT_IPTC_ALL = d.iptc || {};
  CURRENT_RAW = d.raw || {};
  fillCoreFromIptc(CURRENT_IPTC_ALL);
  fillLocationFromGeo(d.geo || {});
  renderAdvancedFields(CURRENT_IPTC_ALL);
  renderRawFields(CURRENT_RAW);
  // Auto-merge raw metadata into IPTC table (fill empty only)
  mergeRawIntoIptcTable(false);
  // Close All metadata by default
  const rd = $('rawDetails');
  if(rd) rd.open = false;

  setStatus('Loaded: '+file, true);
}

function collectCoreIptc(){
  return {
    'IPTC:ObjectName': $('objname').value || '',
    'IPTC:Headline': $('headline').value || '',
    'IPTC:Caption-Abstract': $('caption').value || '',
    'IPTC:Keywords': $('keywords').value || '',
    'IPTC:By-line': $('byline').value || '',
    'IPTC:DateCreated': $('datecreated').value || '',
    'IPTC:SpecialInstructions': $('special').value || '',
    'IPTC:Country-PrimaryLocationName': $('country').value || '',
    'IPTC:Country-PrimaryLocationCode': $('country_code').value || '',
    'IPTC:Province-State': $('state').value || '',
    'IPTC:City': $('city').value || '',
    'IPTC:Sub-location': $('sublocation').value || '',
  };
}
function collectGeo(){
  return {
    lat: $('lat').value,
    lng: $('lng').value,
    bearing: $('bearing').value,
    zoom: map ? map.getZoom() : (MAPCFG.default_zoom||16),
  };
}
function collectAdvancedIptc(){
  const iptc = {};
  document.querySelectorAll('#allFieldsBody input[data-tag]').forEach(inp=>{
    iptc[inp.dataset.tag] = inp.value;
  });
  return iptc;
}

async function save(mode){
  if(!CURRENT_FILE){ setStatus('Select a file first.', false); return; }
  setStatus('Saving…', null);
  try{
    let iptc = {};
    if(mode === 'core'){
      iptc = Object.assign({}, CURRENT_IPTC_ALL);
      Object.assign(iptc, collectCoreIptc());
    } else {
      iptc = collectAdvancedIptc();
    }
    const payload = { file: CURRENT_FILE, mode, iptc, geo: collectGeo() };
    const r = await fetch(apiUrl({action:'save'}), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const sj = await safeJson(r);
    if(!sj.ok) throw new Error('Save JSON failed: ' + sj.text.slice(0,200));
    const d = sj.data;
    if(!d.ok) throw new Error(d.error||'Save failed');
    setStatus('Saved.', true);
    // refresh current file read + list icons
    await loadFile(CURRENT_FILE);
    await loadFileList();
    const qsFile = (new URLSearchParams(location.search).get('file')||'').trim();
    if(qsFile){
      const f = qsFile.includes('/') ? qsFile.split('/').pop() : qsFile;
      await loadFile(f);
    }
  }catch(e){
    setStatus(String(e.message||e), false);
    if(DEBUG) console.error(e);
  }
}

$('saveBtn').addEventListener('click', ()=>save('core'));
$('saveAllBtn').addEventListener('click', ()=>save('all'));
const _mBtn = $('mergeRawBtn');
if(_mBtn){
  _mBtn.addEventListener('click', ()=>{
    const ov = !!($('mergeOverwrite') && $('mergeOverwrite').checked);
    mergeRawIntoIptcTable(ov);
  });
}

$('reloadTree').addEventListener('click', ()=>loadDirTree());
const rf = $('rawFilter');
if(rf){ rf.addEventListener('input', ()=>renderRawFields(CURRENT_RAW)); }

$('treeFilter').addEventListener('input', ()=>loadDirTree());

(async function init(){
  try{
    initCountryDropdown();
    await loadDiag();
    await loadTemplate();
    bindTreeClicks();
    await loadDirTree();
    await loadFileList();
    const qsFile = (new URLSearchParams(location.search).get('file')||'').trim();
    if(qsFile){
      const f = qsFile.includes('/') ? qsFile.split('/').pop() : qsFile;
      await loadFile(f);
    }
    setStatus('Ready.', true);
  }catch(e){
    setStatus('Init failed: ' + String(e.message||e), false);
    if(DEBUG) console.error(e);
  }
})();
</script>
</body>
</html>
