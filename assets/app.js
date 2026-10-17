"use strict";
const LAYANAN = ["", "Jantung dan Pembuluh Darah", "Paru dan Pernafasan", "Uro Nefro", "Neonatus", "Neoplasma", "Ibu dan Ginekologi", "Muskuloskeletal dan Jaringan Lunak", "THT", "Mata", "Kulit dan Penyakit Kelamin", "Saraf", "Infeksi dan Parasit", "Pencernaan dan Hepatobilier", "Hematologi", "Alergi Imunologi dan Rheumatologi", "Rekonstruksi dan Estetika", "Keracunan", "Endokrin, Nutrisi dan Metabolik", "Luka Bakar", "Trauma", "Jiwa", "Gigi dan Mulut", "Forensik", "Rehabilitasi"];
const STRATA = ["Dasar", "Madya", "Utama", "Paripurna"];
const SORD = { Dasar: 1, Madya: 2, Utama: 3, Paripurna: 4 };
// Data bawaan: rekap dari PDF RS Online 17 Okt 2026, diringkas satu baris per group
// [no, layanan, strata, kode, nama, kelompok, status, jumlah, group]
const SEED = window.SEED_DATA || [];
const STORE_KEY = "kompetensi-rsud-kilisuci-v1";

// ---------- state ----------
// sources: key "no|SDM" or "no|Alkes" -> {file, at, rows:[item]}
let sources = new Map();
let model = null;
const ui = { pick: { no: null, strata: null }, sortK: "no", sortDir: 1 };

const $ = (id) => document.getElementById(id);
const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };
const fmtPct = (x) => (Math.round(x * 1000) / 10).toLocaleString("id-ID") + "%";
const fmtInt = (n) => n.toLocaleString("id-ID");
const toast = (msg) => { const t = $("toast"); if (!t) return; t.textContent = msg; t.hidden = false; clearTimeout(toast._t); toast._t = setTimeout(() => { t.hidden = true; }, 3200); };
const lead = $("lead");

// ---------- normalising input ----------
const normKey = (s) => String(s || "").toLowerCase().replace(/[^a-z0-9]/g, "");
const ALIAS = {
  no_layanan: ["nolayanan", "nomorlayanan", "kodelayanan", "layananno", "nolay"],
  layanan: ["layanan", "namalayanan", "kompetensi", "pelayanan", "namakompetensi"],
  strata: ["strata", "tingkat", "level"],
  kode: ["kode", "kodealat", "kodeitem", "kodealkes"],
  nama: ["nama", "namaitem", "item", "namasdm", "namaalat", "namaalkes", "uraian"],
  kelompok: ["kelompok", "jenis", "kategori", "tipe"],
  status: ["status", "sifat"],
  jumlah: ["jumlah", "jml", "qty", "total", "jumlahdimiliki"],
  group: ["group", "grup", "kodegroup", "kodegrup", "kelompokgroup"],
  komponen: ["komponen"],
};
function mapHeaders(fields) {
  const map = {};
  const used = new Set();
  for (const [k, al] of Object.entries(ALIAS)) {
    const f = fields.find((h) => !used.has(h) && al.includes(normKey(h)));
    if (f) { map[k] = f; used.add(f); }
  }
  return map;
}
function canonLayanan(name) {
  const n = normKey(name);
  if (!n) return 0;
  let best = 0;
  LAYANAN.forEach((l, i) => { if (i && (normKey(l) === n)) best = i; });
  if (best) return best;
  LAYANAN.forEach((l, i) => { if (!best && i && (normKey(l).startsWith(n) || n.startsWith(normKey(l)))) best = i; });
  return best;
}
function fromFilename(fn) {
  const base = fn.replace(/\.[^.]+$/, "");
  const out = { no: 0, komp: null };
  const m = base.match(/^\s*(\d{1,2})\s*[.\-_)]?\s*(.*)$/);
  let rest = base;
  if (m) { out.no = Number(m[1]); rest = m[2]; }
  if (/\b(sdm|nakes|tenaga)\b/i.test(rest)) out.komp = "SDM";
  else if (/\b(alkes|alat|sarana|prasarana)\b/i.test(rest)) out.komp = "Alkes";
  if (!out.no) out.no = canonLayanan(rest.replace(/[-–_].*$/, ""));
  return out;
}
function jenisOf(kelompok, group) {
  const k = String(kelompok || "").toLowerCase(), g = String(group || "").toLowerCase();
  if (k.includes("sdm") || k.includes("nakes") || /^ksdm/.test(g)) return "SDM";
  if (k.includes("sarana") || /sarana/.test(g)) return "Sarana";
  if (k.includes("alat") || k.includes("alkes") || /^kalkes/.test(g)) return "Alat";
  return "";
}
function strataOf(s) {
  const n = normKey(s);
  return STRATA.find((x) => normKey(x) === n) || (n ? s.trim().replace(/^./, (c) => c.toUpperCase()) : "");
}
function num(v) {
  if (typeof v === "number") return v;
  const s = String(v ?? "").trim().replace(/\s/g, "");
  if (!s) return 0;
  const n = Number(s.replace(/\.(?=\d{3}\b)/g, "").replace(",", "."));
  return Number.isFinite(n) ? n : 0;
}

function parseFile(file) {
  return new Promise((resolve) => {
    Papa.parse(file, {
      header: true, skipEmptyLines: "greedy", transformHeader: (h) => h.replace(/^﻿/, "").trim(),
      complete: (res) => resolve(res),
      error: (err) => resolve({ data: [], meta: { fields: [] }, errors: [err] }),
    });
  });
}

async function ingest(files) {
  const msgs = [];
  let added = 0;
  const incoming = new Map();
  for (const file of files) {
    if (!/\.(csv|txt|tsv)$/i.test(file.name)) { msgs.push(["err", `${file.name}: bukan file CSV, dilewati.`]); continue; }
    const res = await parseFile(file);
    const fields = res.meta.fields || [];
    const hm = mapHeaders(fields);
    const fn = fromFilename(file.name);
    const missing = ["strata", "nama", "status", "jumlah", "group"].filter((k) => !hm[k]);
    if (missing.length) { msgs.push(["err", `${file.name}: kolom ${missing.join(", ")} tidak ditemukan. Kolom terbaca: ${fields.join(", ") || "(kosong)"}.`]); continue; }
    let skipped = 0, noLay = 0;
    const perKey = new Map();
    for (const r of res.data) {
      const nama = String(r[hm.nama] ?? "").trim();
      const group = String(r[hm.group] ?? "").trim();
      const strata = strataOf(String(r[hm.strata] ?? ""));
      if (!nama || !group || !strata) { skipped++; continue; }
      let no = hm.no_layanan ? Number(String(r[hm.no_layanan]).replace(/\D/g, "")) : 0;
      if (!no && hm.layanan) no = canonLayanan(r[hm.layanan]);
      if (!no) no = fn.no;
      if (!no || no < 1 || no > 24) { noLay++; continue; }
      const kelompok = hm.kelompok ? String(r[hm.kelompok] ?? "").trim() : "";
      let jenis = jenisOf(kelompok, group);
      let komp = hm.komponen ? (/sdm/i.test(r[hm.komponen]) ? "SDM" : "Alkes") : null;
      if (!komp) komp = jenis === "SDM" ? "SDM" : jenis ? "Alkes" : fn.komp;
      if (!komp) { skipped++; continue; }
      if (!jenis) jenis = komp === "SDM" ? "SDM" : "Alat";
      const item = {
        no, layanan: LAYANAN[no], strata, kode: hm.kode ? String(r[hm.kode] ?? "").trim() : "",
        nama, kelompok, jenis, komp, status: /wajib/i.test(String(r[hm.status] ?? "")) ? "Wajib" : "Substitusi",
        jumlah: num(r[hm.jumlah]), group,
      };
      const key = no + "|" + komp;
      if (!perKey.has(key)) perKey.set(key, []);
      perKey.get(key).push(item);
    }
    for (const [key, rows] of perKey) {
      const prev = incoming.get(key);
      incoming.set(key, { file: prev ? prev.file + ", " + file.name : file.name, at: new Date().toISOString(), rows: prev ? prev.rows.concat(rows) : rows, user: true });
      added += rows.length;
    }
    const keys = [...perKey.keys()].map((k) => { const [n, c] = k.split("|"); return `${n}. ${LAYANAN[n]} (${c})`; });
    if (keys.length) msgs.push(["ok", `${file.name}: ${fmtInt([...perKey.values()].reduce((a, r) => a + r.length, 0))} baris → ${keys.join("; ")}.`]);
    if (noLay) msgs.push(["warn", `${file.name}: ${noLay} baris tanpa layanan yang dikenali. Isi kolom No Layanan/Layanan atau beri nama file seperti "4. Neonatus - Alkes.csv".`]);
    if (skipped) msgs.push(["warn", `${file.name}: ${skipped} baris kosong/tidak lengkap dilewati.`]);
  }
  for (const [k, v] of incoming) sources.set(k, v);
  renderMsgs(msgs);
  if (added) { persist(); rebuild(); toast(`${fmtInt(added)} baris dimuat.`); }
}

function renderMsgs(msgs) {
  const ul = $("msgs");
  ul.replaceChildren(...msgs.map(([t, m]) => el("li", t === "ok" ? "ok" : t === "err" ? "err" : "", m)));
}

function loadSeed() {
  sources = new Map();
  for (const [no, layanan, strata, kode, nama, kelompok, status, jumlah, group] of SEED) {
    const jenis = jenisOf(kelompok, group);
    const komp = jenis === "SDM" ? "SDM" : "Alkes";
    const key = no + "|" + komp;
    if (!sources.has(key)) sources.set(key, { file: "rekap 17 Okt 2026", at: "2026-10-17", rows: [], user: false });
    sources.get(key).rows.push({ no, layanan, strata, kode, nama, kelompok, jenis, komp, status: status === "wajib" ? "Wajib" : "Substitusi", jumlah, group });
  }
}

function loadCustomSnapshot(rawItems, label = "Snapshot") {
  if (!rawItems || !rawItems.length) return;
  sources = new Map();
  state.seedOff = true;

  // Hitung counter group unik per (no, komp) agar tiap item mendapat kode berbeda
  const groupCounters = {};

  for (const row of rawItems) {
    const no       = Array.isArray(row) ? row[0] : (row.no || 1);
    const layanan  = Array.isArray(row) ? row[1] : (row.layanan || LAYANAN[no] || "");
    const strata   = Array.isArray(row) ? row[2] : (row.strata || "Dasar");
    const kode     = Array.isArray(row) ? row[3] : (row.kode || "");
    const nama     = Array.isArray(row) ? row[4] : (row.nama || "");
    const kelompok = Array.isArray(row) ? row[5] : (row.kelompok || "");
    const status   = Array.isArray(row) ? row[6] : (row.status || "wajib");
    const jumlah   = Array.isArray(row) ? row[7] : (num(row.jumlah));
    const group    = Array.isArray(row) ? row[8] : (row.group || "");

    if (!nama && !kelompok) continue; // skip baris kosong

    const jenis = jenisOf(kelompok, group);
    const komp = /sdm/i.test(kelompok) ? "SDM" : jenis === "SDM" ? "SDM" : "Alkes";
    const key = no + "|" + komp;
    if (!sources.has(key)) sources.set(key, { file: label, at: new Date().toISOString(), rows: [], user: true });

    // Gunakan group asli jika ada (misal ksdm01, kalkes01, ksarana01 dari SIRS).
    // Hanya buat group fallback jika kolom group benar-benar kosong.
    let safeGroup = (group && String(group).trim()) ? String(group).trim().toLowerCase() : "";
    if (!safeGroup) {
      const cKey = no + "|" + komp;
      if (!groupCounters[cKey]) groupCounters[cKey] = 0;
      groupCounters[cKey]++;
      const idx = groupCounters[cKey];
      const prefix = komp === "SDM" ? "ksdm" : (jenis === "Sarana" ? "ksarana" : "kalkes");
      safeGroup = prefix + String(idx).padStart(2, "0");
    }

    sources.get(key).rows.push({
      no, layanan: LAYANAN[no] || layanan, strata: strata || "Dasar", kode, nama, kelompok,
      jenis: jenis || "Alat", komp,
      status: /wajib/i.test(status) ? "Wajib" : "Substitusi",
      jumlah: Number(jumlah) || 0, group: safeGroup
    });
  }
  rebuild();
  renderMsgs([["ok", `Snapshot aktif: ${label} (${fmtInt(rawItems.length)} baris).`]]);
  toast(`Snapshot '${label}' aktif.`);
}
window.loadCustomSnapshot = loadCustomSnapshot;

function persist() {
  try {
    const user = [...sources].filter(([, v]) => v.user);
    localStorage.setItem(STORE_KEY, JSON.stringify({ v: 1, seedOff: [...sources.keys()].length === 0 || state.seedOff, user }));
  } catch (e) { /* storage optional */ }
}
const state = { seedOff: false };
function restore() {
  loadSeed();
  try {
    const raw = localStorage.getItem(STORE_KEY);
    if (!raw) return;
    const s = JSON.parse(raw);
    if (s.seedOff) { sources = new Map(); state.seedOff = true; }
    for (const [k, v] of s.user || []) sources.set(k, v);
  } catch (e) { /* ignore */ }
}

// ---------- rekap ----------
const SHORT_RULES = [
  [/ct-?scan|msct|^ct$/i, "CT-Scan"], [/mri/i, "MRI"], [/histopat/i, "Set histopatologi"], [/sitopat/i, "Set sitopatologi"],
  [/patologi anatomi|^r\.?\s*pa$/i, "Ruang Patologi Anatomi"], [/ruang.*(hemodialisa|cuci darah)|^r\.?hemodialisa/i, "Ruang hemodialisa"],
  [/hemodialisa/i, "Mesin hemodialisa"], [/slit ?lamp/i, "Slit lamp"], [/bronch|bronko/i, "Bronkoskopi"], [/flow cytometer/i, "Flow cytometer"],
  [/dermatoscope/i, "Dermatoscope"], [/bedah plastik|plastic surgery/i, "Set bedah plastik"], [/c-?arm/i, "C-Arm"],
  [/ophthalmoscope|opthalmoscope|oftalmoskop|funduscopy|funduskopi/i, "Oftalmoskop/funduskopi"], [/spirometer|peak-?flow/i, "Spirometer"],
  [/lup binokuler|binocular loupe/i, "Lup binokuler"], [/maxil|maksilo/i, "Set bedah maksilofasial"], [/tht unit|ent diagnostik|ent unit/i, "THT/ENT unit"],
  [/endoscop|endoskopi|gastroscopy|colonoscopy/i, "Endoskopi"], [/mammogra/i, "Mammografi"], [/\bbor\b|boor|cranial drill/i, "Bor/cranial drill"],
  [/echo/i, "Echo/USG Echo"], [/otoacoustic|oae/i, "OAE"], [/kraniotomi|craniotomy/i, "Set kraniotomi"],
];
const SDM_RULES = [
  [/neurolog|saraf \(sp\.s\)|^sp\.?n\b|^spn\b/i, "Sp.N (Saraf)"], [/spesialis mata|^sp\.?m\b|^spm\b/i, "Sp.M (Mata)"],
  [/kulit|dve|^sp\.?kk|^spkk/i, "Sp.DVE (Kulit & Kelamin)"], [/tht/i, "Sp.THT-KL"], [/psikiatri|jiwa|^sp\.?kj|^spkj/i, "Sp.KJ (Jiwa)"],
  [/urologi|^sp\.?u\b|^spu\b/i, "Sp.U (Urologi)"], [/jantung dan pembuluh|^sp\.?jp|^spjp/i, "Sp.JP (Jantung)"],
  [/fisik dan rehabilitasi|kfr/i, "Sp.KFR (Rehabilitasi Medik)"], [/patologi anatomi|^sp\.?pa\b|^sppa/i, "Sp.PA (Patologi Anatomi)"],
  [/gizi klinik|^sp\.?gk|^spgk/i, "Sp.GK (Gizi Klinik)"], [/mikrobiologi|^sp\.?mk|^spmk/i, "Sp.MK (Mikrobiologi)"],
  [/thoraks dan kardiovaskuler|btkv/i, "Sp.BTKV"], [/dokter gigi spesialis|drg ?sp/i, "drg spesialis"], [/kedaruratan|sp\.?em/i, "Sp.EM"],
];
function shortName(g) {
  const text = g.label;
  const rules = g.komp === "SDM" ? SDM_RULES : SHORT_RULES;
  for (const [re, lab] of rules) if (re.test(text)) return lab;
  return text.length > 48 ? text.slice(0, 46) + "…" : text;
}
function mainItem(items, komp) {
  if (komp === "SDM") {
    const spes = items.find((i) => /^dokter (gigi )?spesialis/i.test(i.nama) && !/fellowship/i.test(i.nama));
    if (spes) return spes.nama;
  }
  return items[0].nama;
}
function listNames(items, max = 3) {
  const names = [...new Set(items.map((i) => i.nama))];
  return names.slice(0, max).join(" / ") + (names.length > max ? ` / +${names.length - max} lainnya` : "");
}

function buildModel() {
  const items = [];
  for (const v of sources.values()) items.push(...v.rows);
  const gmap = new Map();
  for (const it of items) {
    const k = [it.no, it.strata, it.komp, it.group].join("|");
    if (!gmap.has(k)) gmap.set(k, []);
    gmap.get(k).push(it);
  }
  const groups = [];
  for (const [k, its] of gmap) {
    const [no, strata, komp, group] = k.split("|");
    const w = its.filter((i) => i.status === "Wajib"), s = its.filter((i) => i.status === "Substitusi");
    const wOk = w.every((i) => i.jumlah > 0), sOk = s.length === 0 || s.some((i) => i.jumlah > 0);
    const met = wOk && sOk;
    const sifat = w.length && s.length ? "Campuran" : w.length ? "Wajib" : "Substitusi";
    const missing = [...w.filter((i) => !(i.jumlah > 0)), ...(sOk ? [] : s)];
    const have = its.filter((i) => i.jumlah > 0);
    const label = mainItem(its, komp);
    const g = {
      no: Number(no), layanan: LAYANAN[no], strata, komp, jenis: its[0].jenis, group, sifat, met,
      nItems: its.length, label,
      persyaratan: komp === "SDM" ? label : listNames(its),
      kurang: met ? "" : (komp === "SDM" && !w.length ? label : listNames(missing)),
      dipenuhi: met ? have.map((i) => `${i.nama} (${i.jumlah})`).slice(0, 3).join("; ") : "",
    };
    g.item = shortName({ label: met ? g.persyaratan : (g.kurang || g.persyaratan), komp });
    groups.push(g);
  }
  groups.sort((a, b) => a.no - b.no || SORD[a.strata] - SORD[b.strata] || (a.komp === "SDM" ? 0 : 1) - (b.komp === "SDM" ? 0 : 1) || a.group.localeCompare(b.group, "id", { numeric: true }));
  // per layanan × strata
  const rmap = new Map();
  for (const g of groups) {
    const k = g.no + "|" + g.strata;
    if (!rmap.has(k)) rmap.set(k, { no: g.no, layanan: g.layanan, strata: g.strata, sdm_ok: 0, sdm_n: 0, alk_ok: 0, alk_n: 0, gaps: [] });
    const r = rmap.get(k);
    if (g.komp === "SDM") { r.sdm_n++; if (g.met) r.sdm_ok++; } else { r.alk_n++; if (g.met) r.alk_ok++; }
    if (!g.met) r.gaps.push(g);
  }
  const ring = [...rmap.values()].sort((a, b) => a.no - b.no || SORD[a.strata] - SORD[b.strata]);
  for (const r of ring) {
    r.ok = r.sdm_ok + r.alk_ok; r.n = r.sdm_n + r.alk_n; r.pct = r.n ? r.ok / r.n : 0;
    r.status = r.alk_n === 0 ? "Data Alkes belum ada" : r.sdm_n === 0 ? "Data SDM belum ada" : r.ok === r.n ? "Terpenuhi" : "Belum terpenuhi";
  }
  const gaps = groups.filter((g) => !g.met);
  const ok = groups.filter((g) => g.met).length;
  return { items, groups, ring, gaps, ok, n: groups.length };
}

// ---------- render ----------
function rebuild() {
  model = buildModel();
  renderCoverage(); renderKpis(); renderMatrix(); renderBars(); renderTop(); renderTable(); renderAllTable();
}

function renderCoverage() {
  const cov = $("cov");
  const rows = [];
  for (let i = 1; i <= 24; i++) {
    const d = el("div");
    d.append(el("span", "n", String(i)), el("span", "nm", LAYANAN[i]));
    for (const komp of ["SDM", "Alkes"]) {
      const s = sources.get(i + "|" + komp);
      const t = el("span", "tag " + (!s ? "off" : s.user ? "on" : "seed"), komp === "SDM" ? "SDM" : "Alkes");
      t.title = s ? `${s.file} · ${fmtInt(s.rows.length)} baris` : "belum ada data";
      d.append(t);
    }
    rows.push(d);
  }
  cov.replaceChildren(...rows);
  const user = [...sources.values()].filter((v) => v.user);
  const srcName = user.length ? (user[0]?.file || "Snapshot Kustom") : "Rekap Bawaan SIRS";
  $("src-line").textContent = `${fmtInt(model.items.length)} baris instrumen aktif (${srcName}).`;
}

function renderKpis() {
  const m = model;
  const pct = m.n ? m.ok / m.n : 0;
  const elPct = $("k-pct"); if (elPct) elPct.textContent = m.n ? fmtPct(pct) : "–";
  const elBar = $("k-bar"); if (elBar) elBar.style.width = (pct * 100).toFixed(1) + "%";
  const elSub = $("k-pct-sub"); if (elSub) elSub.textContent = `${fmtInt(m.ok)} dari ${fmtInt(m.n)} group persyaratan`;
  const lengkap = m.ring.filter((r) => r.status === "Terpenuhi").length;
  const elStr = $("k-strata"); if (elStr) elStr.textContent = fmtInt(lengkap);
  const elStrSub = $("k-strata-sub"); if (elStrSub) elStrSub.textContent = `dari ${m.ring.length} layanan × strata`;
  const gs = m.gaps.filter((g) => g.komp === "SDM").length;
  const elGap = $("k-gap"); if (elGap) elGap.textContent = fmtInt(m.gaps.length);
  const elGapSub = $("k-gap-sub"); if (elGapSub) elGapSub.textContent = `${fmtInt(gs)} SDM · ${fmtInt(m.gaps.length - gs)} alkes & sarana`;
  const wajib = m.gaps.filter((g) => g.sifat !== "Substitusi").length;
  const elWajib = $("k-wajib"); if (elWajib) elWajib.textContent = fmtInt(wajib);
  if (!m.n) { if (lead) lead.textContent = "Belum ada data kompetensi. Silakan lakukan sinkronisasi atau pilih tanggal tarikan."; return; }
  const worst = m.ring.filter((r) => r.alk_n && r.sdm_n).sort((a, b) => a.pct - b.pct)[0];
  if (lead) lead.replaceChildren(
    "Dari ", el("b", null, fmtInt(m.n)), " group persyaratan di ", el("b", null, String(new Set(m.ring.map((r) => r.no)).size)), " layanan, ",
    el("b", null, fmtPct(pct)), " sudah terpenuhi. ",
    ...(worst ? ["Pemenuhan terendah: ", el("b", null, `${worst.layanan} ${worst.strata}`), ` (${fmtPct(worst.pct)}).`] : []));
}

const pick = (no, strata) => {
  if (ui.pick.no === no && ui.pick.strata === strata) ui.pick = { no: null, strata: null };
  else ui.pick = { no, strata };
  renderMatrix(); renderBars(); renderTable();
  if (ui.pick.no) $("daftar").scrollIntoView({ behavior: "smooth", block: "start" });
};

function tipFor(box) {
  let t = box.querySelector(":scope > .tip");
  if (!t) { t = el("div", "tip"); t.hidden = true; box.append(t); }
  return t;
}
function showTip(box, tip, ev, lines) {
  tip.replaceChildren(...lines);
  tip.hidden = false;
  const b = box.getBoundingClientRect();
  let x = ev.clientX - b.left + 12 + box.scrollLeft, y = ev.clientY - b.top + 12;
  if (x + tip.offsetWidth > b.width + box.scrollLeft) x = Math.max(0, ev.clientX - b.left - tip.offsetWidth - 12 + box.scrollLeft);
  tip.style.left = x + "px"; tip.style.top = y + "px";
}

function renderMatrix() {
  const box = $("mx");
  const strataUsed = STRATA.filter((s) => model.ring.some((r) => r.strata === s));
  const tbl = el("table", "mx");
  const hr = tbl.createTHead().insertRow();
  ["Layanan", ...strataUsed].forEach((h) => hr.append(el("th", null, h)));
  const tb = tbl.createTBody();
  const tip = tipFor(box);
  const byNo = new Map();
  for (const r of model.ring) { if (!byNo.has(r.no)) byNo.set(r.no, []); byNo.get(r.no).push(r); }
  for (const no of [...byNo.keys()].sort((a, b) => a - b)) {
    const rs = byNo.get(no);
    const tr = tb.insertRow();
    const nm = tr.insertCell(); nm.className = "nm"; nm.tabIndex = 0; nm.title = LAYANAN[no];
    nm.append(el("span", "n", String(no)), LAYANAN[no]);
    if (ui.pick.no === no && !ui.pick.strata) nm.classList.add("sel");
    nm.addEventListener("click", () => pick(no, null));
    nm.addEventListener("keydown", (e) => { if (e.key === "Enter") pick(no, null); });
    for (const st of strataUsed) {
      const r = rs.find((x) => x.strata === st);
      const td = tr.insertCell(); td.className = "cell";
      if (!r) { td.classList.add("na"); td.textContent = "–"; continue; }
      td.tabIndex = 0;
      td.textContent = fmtPct(r.pct);
      td.classList.add(r.pct >= 1 ? "ok" : r.pct >= 0.7 ? "mid" : "low");
      if (ui.pick.no === no && ui.pick.strata === st) td.classList.add("sel");
      td.addEventListener("click", () => pick(no, st));
      td.addEventListener("keydown", (e) => { if (e.key === "Enter") pick(no, st); });
      td.addEventListener("mousemove", (ev) => showTip(box, tip, ev, [
        el("div", "t", `${r.layanan} · ${st}`),
        el("div", null, `SDM: ${r.sdm_ok} / ${r.sdm_n}`),
        el("div", null, `Alkes & sarana: ${r.alk_ok} / ${r.alk_n}`),
        ...(r.status.startsWith("Data") ? [el("div", "t", r.status)] : []),
      ]));
      td.addEventListener("mouseleave", () => { tip.hidden = true; });
    }
  }
  box.replaceChildren(tbl, tip);
}

const SVGNS = "http://www.w3.org/2000/svg";
const svgEl = (tag, attrs = {}, text) => { const e = document.createElementNS(SVGNS, tag); for (const [k, v] of Object.entries(attrs)) e.setAttribute(k, v); if (text != null) e.textContent = text; return e; };
const measure = (() => { let s, t; return (str) => { if (!s) { s = svgEl("svg", { width: 0, height: 0, style: "position:absolute;visibility:hidden" }); t = svgEl("text", { style: "font: 11px " + getComputedStyle(document.body).fontFamily }); s.append(t); document.body.append(s); } t.textContent = str; return t.getComputedTextLength(); }; })();
const niceMax = (v) => { if (v <= 5) return Math.max(1, Math.ceil(v)); const p = Math.pow(10, Math.floor(Math.log10(v))); for (const m of [1, 2, 2.5, 5, 10]) if (m * p >= v) return m * p; return v; };

function hbar(box, data, { label, parts, onClick, tipLines, dim }) {
  const W = Math.max(300, box.clientWidth || 600), rowH = 24, top = 4, axisH = 20;
  const H = top + data.length * rowH + axisH;
  const lw = Math.min(W * 0.46, Math.max(60, ...data.map((d) => measure(label(d)))) + 10);
  const max = niceMax(Math.max(1, ...data.map((d) => parts(d).reduce((a, p) => a + p.v, 0))));
  const x = (v) => lw + (v / max) * (W - lw - 30);
  const svg = svgEl("svg", { viewBox: `0 0 ${W} ${H}`, role: "img" });
  const ticks = max <= 5 ? Array.from({ length: max + 1 }, (_, i) => i) : [0, max / 4, max / 2, (3 * max) / 4, max];
  for (const t of ticks) {
    svg.append(svgEl("line", { x1: x(t), x2: x(t), y1: top, y2: top + data.length * rowH, class: "grid" }));
    svg.append(svgEl("text", { x: x(t), y: H - 6, "text-anchor": "middle", class: "mut" }, String(t)));
  }
  const tip = tipFor(box);
  data.forEach((d, i) => {
    const y = top + i * rowH;
    const g = svgEl("g", { class: onClick ? "row" : "", opacity: dim && dim(d) ? 0.35 : 1 });
    g.append(svgEl("rect", { x: 0, y, width: W, height: rowH, fill: "transparent", class: "hit", rx: 4 }));
    g.append(svgEl("text", { x: lw - 8, y: y + rowH / 2, dy: "0.35em", "text-anchor": "end" }, label(d)));
    let acc = 0;
    const ps = parts(d);
    ps.forEach((p, j) => {
      if (p.v > 0) {
        const last = ps.slice(j + 1).every((q) => !(q.v > 0));
        g.append(svgEl("rect", { x: x(acc), y: y + 5, width: Math.max(0, x(acc + p.v) - x(acc)), height: rowH - 10, fill: p.c, rx: last ? 3 : 0 }));
      }
      acc += p.v;
    });
    g.append(svgEl("text", { x: x(acc) + 5, y: y + rowH / 2, dy: "0.35em", class: "mut" }, String(acc)));
    if (onClick) g.addEventListener("click", () => onClick(d));
    g.addEventListener("mousemove", (ev) => showTip(box, tip, ev, tipLines(d)));
    g.addEventListener("mouseleave", () => { tip.hidden = true; });
    svg.append(g);
  });
  box.replaceChildren(svg, tip);
}
const cssVar = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();

function renderBars() {
  const box = $("bars");
  const byNo = new Map();
  for (const r of model.ring) {
    const o = byNo.get(r.no) || { no: r.no, layanan: r.layanan, sdm: 0, alk: 0 };
    o.sdm += r.sdm_n - r.sdm_ok; o.alk += r.alk_n - r.alk_ok; byNo.set(r.no, o);
  }
  const data = [...byNo.values()].sort((a, b) => (b.sdm + b.alk) - (a.sdm + a.alk) || a.no - b.no);
  if (!data.length) { box.replaceChildren(el("p", "note", "Belum ada data.")); return; }
  const cS = cssVar("--c-sdm"), cA = cssVar("--c-alk");
  hbar(box, data, {
    label: (d) => (d.layanan.length > 30 ? d.layanan.slice(0, 29) + "…" : d.layanan),
    parts: (d) => [{ v: d.sdm, c: cS }, { v: d.alk, c: cA }],
    onClick: (d) => pick(d.no, null),
    dim: (d) => ui.pick.no && ui.pick.no !== d.no,
    tipLines: (d) => {
      const a = el("div"); const ia = el("i"); ia.style.background = cS; a.append(ia, `SDM: ${d.sdm}`);
      const b = el("div"); const ib = el("i"); ib.style.background = cA; b.append(ib, `Alkes & sarana: ${d.alk}`);
      return [el("div", "t", d.layanan), a, b];
    },
  });
}

function renderTop() {
  const box = $("top");
  const m = new Map();
  for (const g of model.gaps) {
    const o = m.get(g.item) || { item: g.item, komp: g.komp, n: 0, where: [] };
    o.n++; o.where.push(`${g.layanan} (${g.strata})`); m.set(g.item, o);
  }
  const data = [...m.values()].filter((o) => o.n >= 2).sort((a, b) => b.n - a.n || a.item.localeCompare(b.item)).slice(0, 20);
  if (!data.length) { box.replaceChildren(el("p", "note", "Tidak ada kekurangan yang muncul di lebih dari satu layanan.")); return; }
  const cS = cssVar("--c-sdm"), cA = cssVar("--c-alk");
  hbar(box, data, {
    label: (d) => d.item,
    parts: (d) => [{ v: d.n, c: d.komp === "SDM" ? cS : cA }],
    onClick: (d) => { $("q").value = d.item.replace(/\s*\(.*$/, ""); ui.pick = { no: null, strata: null }; renderMatrix(); renderBars(); renderTable(); $("daftar").scrollIntoView({ behavior: "smooth" }); },
    tipLines: (d) => [el("div", "t", `${d.item} · ${d.n} layanan-strata`), ...d.where.slice(0, 10).map((w) => el("div", null, w)), ...(d.where.length > 10 ? [el("div", "t", `+${d.where.length - 10} lainnya`)] : [])],
  });
}

function renderTable() {
  const term = $("q").value.trim().toLowerCase();
  const fk = $("f-komp").value, fs = $("f-strata").value, ff = $("f-sifat").value;
  const p = ui.pick;
  let rows = model.gaps.filter((g) =>
    (!p.no || g.no === p.no) && (!p.strata || g.strata === p.strata) && (!fk || g.komp === fk) && (!fs || g.strata === fs) &&
    (!ff || (ff === "Wajib" ? g.sifat !== "Substitusi" : g.sifat === "Substitusi")) &&
    (!term || [g.layanan, g.strata, g.group, g.kurang, g.persyaratan, g.item, g.komp].join(" ").toLowerCase().includes(term)));
  const k = ui.sortK, dir = ui.sortDir;
  const val = (g) => k === "no" ? g.no * 10 + SORD[g.strata] : k === "strata" ? SORD[g.strata] : String(g[k] || "");
  rows = rows.slice().sort((a, b) => { const A = val(a), B = val(b); return (A < B ? -1 : A > B ? 1 : 0) * dir; });
  document.querySelectorAll("table.gap th").forEach((th) => th.setAttribute("aria-sort", th.dataset.k === k ? (dir > 0 ? "ascending" : "descending") : "none"));
  const chip = $("picked");
  if (chip) {
    chip.hidden = !p.no;
    if (p.no) chip.textContent = `${LAYANAN[p.no]}${p.strata ? " · " + p.strata : ""}  ×`;
  }
  const tb = $("tb");
  if (tb) {
    tb.replaceChildren(...rows.map((g) => {
      const tr = el("tr");
      tr.append(el("td", null, `${g.no}. ${g.layanan}`), el("td", null, g.strata));
      const tk = el("td"); tk.append(el("span", "pill " + (g.komp === "SDM" ? "k-sdm" : "k-alk"), g.komp === "SDM" ? "SDM" : g.jenis === "Sarana" ? "Sarana" : "Alkes")); tr.append(tk);
      tr.append(el("td", "code hide-sm", g.group));
      tr.append(el("td", null, g.kurang || g.persyaratan));
      const ts = el("td"); ts.append(el("span", "pill " + (g.sifat === "Substitusi" ? "s" : "w"), g.sifat)); tr.append(ts);
      return tr;
    }));
  }
  $("count") && ($("count").textContent = `${fmtInt(rows.length)} group`);
  $("badge-gap-count") && ($("badge-gap-count").textContent = fmtInt(rows.length));
}

// ─── SELURUH DETIL KOMPONEN RS ONLINE ─────────────────────────────────────────
const allUi = {
  sortK: "no",
  sortDir: 1,
  page: 1,
  perPage: 100,
  filteredItems: []
};

function initAllLayananDropdown() {
  const sel = $("all-f-layanan");
  if (!sel || sel.children.length > 1) return;
  for (let i = 1; i <= 24; i++) {
    const opt = document.createElement("option");
    opt.value = i;
    opt.textContent = `${i}. ${LAYANAN[i]}`;
    sel.appendChild(opt);
  }
}

function renderAllTable() {
  initAllLayananDropdown();
  if (!model || !model.items) return;

  const term = ($("all-q")?.value || "").trim().toLowerCase();
  const flay = $("all-f-layanan")?.value ? Number($("all-f-layanan").value) : 0;
  const fkomp = $("all-f-komp")?.value || "";
  const fstrata = $("all-f-strata")?.value || "";
  const fstatus = $("all-f-status")?.value || "";
  const fkondisi = $("all-f-kondisi")?.value || "";

  let filtered = model.items.filter((it) => {
    if (flay && it.no !== flay) return false;
    if (fkomp && it.komp !== fkomp) return false;
    if (fstrata && it.strata !== fstrata) return false;
    if (fstatus && it.status !== fstatus) return false;
    if (fkondisi === "ada" && !(it.jumlah > 0)) return false;
    if (fkondisi === "kosong" && it.jumlah > 0) return false;
    if (term) {
      const haystack = [it.no, it.layanan, it.strata, it.komp, it.nama, it.kelompok, it.kode, it.group, it.status].join(" ").toLowerCase();
      if (!haystack.includes(term)) return false;
    }
    return true;
  });

  // Sorting
  const k = allUi.sortK, dir = allUi.sortDir;
  const val = (it) => {
    if (k === "no") return it.no * 10000 + (SORD[it.strata] || 0) * 100 + (it.komp === "SDM" ? 1 : 2);
    if (k === "strata") return SORD[it.strata] || 0;
    if (k === "jumlah") return it.jumlah || 0;
    return String(it[k] || "");
  };
  filtered.sort((a, b) => {
    const A = val(a), B = val(b);
    return (A < B ? -1 : A > B ? 1 : 0) * dir;
  });

  allUi.filteredItems = filtered;

  // Counters
  const totalCount = filtered.length;
  const okCount = filtered.filter((i) => i.jumlah > 0).length;
  const emptyCount = totalCount - okCount;
  const wajibCount = filtered.filter((i) => i.status === "Wajib").length;
  const subCount = filtered.filter((i) => i.status === "Substitusi").length;

  $("stat-all-total") && ($("stat-all-total").textContent = fmtInt(totalCount));
  $("stat-all-ok") && ($("stat-all-ok").textContent = fmtInt(okCount));
  $("stat-all-empty") && ($("stat-all-empty").textContent = fmtInt(emptyCount));
  $("stat-all-wajib") && ($("stat-all-wajib").textContent = fmtInt(wajibCount));
  $("stat-all-sub") && ($("stat-all-sub").textContent = fmtInt(subCount));
  $("badge-all-count") && ($("badge-all-count").textContent = fmtInt(model.items.length));
  $("all-total-filtered") && ($("all-total-filtered").textContent = fmtInt(totalCount));

  // Pagination calculation
  const perPageVal = $("all-per-page")?.value || "100";
  const perPage = perPageVal === "all" ? Math.max(1, totalCount) : Number(perPageVal);
  const totalPages = Math.max(1, Math.ceil(totalCount / perPage));
  if (allUi.page > totalPages) allUi.page = totalPages;
  if (allUi.page < 1) allUi.page = 1;

  const startIdx = (allUi.page - 1) * perPage;
  const endIdx = Math.min(startIdx + perPage, totalCount);
  const pageRows = filtered.slice(startIdx, endIdx);

  $("all-page-range") && ($("all-page-range").textContent = totalCount === 0 ? "0" : `${startIdx + 1}–${endIdx}`);
  $("all-page-indicator") && ($("all-page-indicator").textContent = `Halaman ${allUi.page} / ${totalPages}`);
  const btnPrev = $("all-btn-prev");
  const btnNext = $("all-btn-next");
  if (btnPrev) btnPrev.disabled = allUi.page <= 1;
  if (btnNext) btnNext.disabled = allUi.page >= totalPages;

  // Sorting header indicator
  document.querySelectorAll("table.tbl-rsonline th").forEach((th) => {
    th.setAttribute("aria-sort", th.dataset.allK === k ? (dir > 0 ? "ascending" : "descending") : "none");
  });

  const tbody = $("all-tb");
  if (!tbody) return;

  if (pageRows.length === 0) {
    const tr = el("tr");
    const td = el("td", null, "Tidak ada data komponen yang cocok dengan kriteria filter.");
    td.colSpan = 9;
    td.style.textAlign = "center";
    td.style.padding = "28px 12px";
    td.style.color = "var(--muted)";
    tr.appendChild(td);
    tbody.replaceChildren(tr);
    return;
  }

  tbody.replaceChildren(...pageRows.map((it, idx) => {
    const tr = el("tr");

    // 1. No urut
    tr.append(el("td", "code", String(startIdx + idx + 1)));

    // 2. Layanan
    const tdLay = el("td");
    tdLay.innerHTML = `<b>${it.no}.</b> ${it.layanan}`;
    tr.append(tdLay);

    // 3. Strata (RS Online styled badge)
    const tdStrata = el("td");
    const strClass = "badge-strata badge-strata-" + (it.strata || "dasar").toLowerCase();
    tdStrata.append(el("span", strClass, it.strata));
    tr.append(tdStrata);

    // 4. Komponen (SDM / Alkes / Sarana)
    const tdKomp = el("td");
    tdKomp.append(el("span", "pill " + (it.komp === "SDM" ? "k-sdm" : "k-alk"), it.komp === "SDM" ? "SDM" : (it.jenis || "Alkes")));
    tr.append(tdKomp);

    // 5. Nama Komponen / Uraian
    const tdNama = el("td");
    tdNama.style.fontWeight = "500";
    tdNama.textContent = it.nama;
    if (it.kode) {
      const codeSpan = el("span", "code hide-sm", ` (${it.kode})`);
      codeSpan.style.opacity = "0.7";
      tdNama.appendChild(codeSpan);
    }
    tr.append(tdNama);

    // 6. Kelompok
    const tdKel = el("td", "hide-sm", it.kelompok || "-");
    tdKel.style.color = "var(--muted)";
    tdKel.style.fontSize = "12px";
    tr.append(tdKel);

    // 7. Status (Wajib / Substitusi)
    const tdStatus = el("td");
    const isWajib = /wajib/i.test(it.status);
    tdStatus.append(el("span", isWajib ? "badge-status-wajib" : "badge-status-sub", isWajib ? "Wajib" : "Substitusi"));
    tr.append(tdStatus);

    // 8. Jumlah (Ada: Hijau, Kosong: Lembut)
    const tdJml = el("td");
    tdJml.style.textAlign = "center";
    const hasQty = it.jumlah > 0;
    const qtySpan = el("span", hasQty ? "badge-qty-ada" : "badge-qty-nol", hasQty ? `✓ ${it.jumlah}` : "0");
    tdJml.append(qtySpan);
    tr.append(tdJml);

    // 9. Group (ksdm01, kalkes01)
    tr.append(el("td", "code hide-sm", it.group || "-"));

    return tr;
  }));
}

// ---------- Excel ----------
async function buildXlsx() {
  const m = model;
  const wb = new ExcelJS.Workbook();
  wb.creator = "Kompetensi Layanan RSUD Kilisuci";
  wb.calcProperties.fullCalcOnLoad = true;
  const F = { name: "Arial", size: 10 };
  const HF = { name: "Arial", size: 10, bold: true, color: { argb: "FFFFFFFF" } };
  const HFILL = { type: "pattern", pattern: "solid", fgColor: { argb: "FF1F4E78" } };
  const B = { style: "thin", color: { argb: "FFBFBFBF" } };
  const BORDER = { top: B, left: B, bottom: B, right: B };
  const RED = { type: "pattern", pattern: "solid", bgColor: { argb: "FFF8CBAD" } };
  const GREEN = { type: "pattern", pattern: "solid", bgColor: { argb: "FFC6EFCE" } };
  const YEL = { type: "pattern", pattern: "solid", bgColor: { argb: "FFFFEB9C" } };
  const header = (ws, row) => row.eachCell((c) => { c.font = HF; c.fill = HFILL; c.border = BORDER; c.alignment = { wrapText: true, vertical: "middle", horizontal: "center" }; });
  const body = (ws, from) => { for (let r = from; r <= ws.rowCount; r++) ws.getRow(r).eachCell({ includeEmpty: true }, (c) => { c.font = c.font && c.font.bold ? c.font : F; c.border = BORDER; c.alignment = { wrapText: true, vertical: "top" }; }); };

  // Ringkasan (first), Kekurangan, Detail, Data mentah, Catatan
  const wsR = wb.addWorksheet("Ringkasan", { views: [{ state: "frozen", xSplit: 3, ySplit: 4 }] });
  const wsK = wb.addWorksheet("Kekurangan", { views: [{ state: "frozen", ySplit: 1 }] });
  const wsD = wb.addWorksheet("Detail", { views: [{ state: "frozen", ySplit: 1 }] });
  const wsM = wb.addWorksheet("Data mentah", { views: [{ state: "frozen", ySplit: 1 }] });
  const wsC = wb.addWorksheet("Catatan");

  // Detail (group level)
  wsD.columns = [
    { header: "No", key: "no", width: 5 }, { header: "Layanan", key: "layanan", width: 30 }, { header: "Strata", key: "strata", width: 9 },
    { header: "Komponen", key: "komp", width: 10 }, { header: "Jenis", key: "jenis", width: 9 }, { header: "Kode Group", key: "group", width: 13 },
    { header: "Persyaratan (item dalam group)", key: "persyaratan", width: 50 }, { header: "Sifat", key: "sifat", width: 11 },
    { header: "Jml Group", key: "jml", width: 9 }, { header: "Terpenuhi", key: "ok", width: 10 }, { header: "Belum ada / dipenuhi oleh", key: "cat", width: 50 },
  ];
  for (const g of m.groups) wsD.addRow({ no: g.no, layanan: g.layanan, strata: g.strata, komp: g.komp, jenis: g.jenis, group: g.group, persyaratan: g.persyaratan, sifat: g.sifat, jml: 1, ok: g.met ? "Ya" : "Tidak", cat: g.met ? g.dipenuhi : g.kurang });
  header(wsD, wsD.getRow(1)); body(wsD, 2);
  const lastD = wsD.rowCount;
  wsD.autoFilter = { from: "A1", to: `K${lastD}` };
  wsD.addConditionalFormatting({ ref: `J2:J${lastD}`, rules: [
    { type: "cellIs", operator: "equal", formulae: ['"Tidak"'], style: { fill: RED }, priority: 1 },
    { type: "cellIs", operator: "equal", formulae: ['"Ya"'], style: { fill: GREEN }, priority: 2 } ] });

  // Ringkasan
  wsR.getCell("A1").value = "Rekap Pemenuhan Komponen Kompetensi per Layanan – RSUD Kilisuci";
  wsR.getCell("A1").font = { name: "Arial", size: 14, bold: true };
  wsR.mergeCells("A2:M2");
  wsR.getCell("A2").value = `Dibuat ${new Date().toLocaleString("id-ID")} dari ${fmtInt(m.items.length)} baris data RS Online. Group substitusi terpenuhi bila minimal 1 item berjumlah > 0; item wajib harus berjumlah > 0. Angka dihitung dengan rumus dari sheet Detail.`;
  wsR.getCell("A2").font = { name: "Arial", size: 9, italic: true };
  wsR.getCell("A2").alignment = { wrapText: true, vertical: "top" };
  wsR.getRow(2).height = 30;
  const rh = ["No", "Layanan", "Strata", "SDM terpenuhi", "SDM total", "Alkes & Sarana terpenuhi", "Alkes & Sarana total", "Total terpenuhi", "Total group", "% Pemenuhan", "Status", "Kekurangan SDM (per group)", "Kekurangan Alkes & Sarana (per group)"];
  wsR.getRow(4).values = rh; header(wsR, wsR.getRow(4));
  const rng = (c) => `Detail!$${c}$2:$${c}$${lastD}`;
  let r = 5;
  const fmtGap = (g) => `• ${g.group} – ${g.kurang || g.persyaratan}${g.sifat !== "Substitusi" ? " [wajib]" : ""}`;
  for (const x of m.ring) {
    const base = `${rng("A")},$A${r},${rng("C")},$C${r}`;
    const row = wsR.getRow(r);
    row.values = [x.no, x.layanan, x.strata,
      { formula: `SUMIFS(${rng("I")},${base},${rng("D")},"SDM",${rng("J")},"Ya")`, result: x.sdm_ok },
      { formula: `SUMIFS(${rng("I")},${base},${rng("D")},"SDM")`, result: x.sdm_n },
      { formula: `SUMIFS(${rng("I")},${base},${rng("D")},"Alkes",${rng("J")},"Ya")`, result: x.alk_ok },
      { formula: `SUMIFS(${rng("I")},${base},${rng("D")},"Alkes")`, result: x.alk_n },
      { formula: `D${r}+F${r}`, result: x.ok }, { formula: `E${r}+G${r}`, result: x.n },
      { formula: `IF(I${r}=0,0,H${r}/I${r})`, result: x.pct },
      { formula: `IF(G${r}=0,"Data Alkes belum ada",IF(E${r}=0,"Data SDM belum ada",IF(H${r}=I${r},"Terpenuhi","Belum terpenuhi")))`, result: x.status },
      x.gaps.filter((g) => g.komp === "SDM").map(fmtGap).join("\n") || "-",
      x.gaps.filter((g) => g.komp !== "SDM").map(fmtGap).join("\n") || "-"];
    row.getCell(10).numFmt = "0%";
    r++;
  }
  const first = 5, last = r - 1;
  body(wsR, first);
  const tr = wsR.getRow(r);
  tr.getCell(2).value = "TOTAL";
  "DEFGHI".split("").forEach((c, i) => { tr.getCell(4 + i).value = { formula: `SUM(${c}${first}:${c}${last})` }; });
  tr.getCell(10).value = { formula: `IF(I${r}=0,0,H${r}/I${r})` }; tr.getCell(10).numFmt = "0%";
  tr.getCell(11).value = { formula: `COUNTIF(K${first}:K${last},"Terpenuhi")&" dari "&COUNTA(K${first}:K${last})&" strata lengkap"` };
  for (let c = 1; c <= 13; c++) { const cell = tr.getCell(c); cell.font = { name: "Arial", size: 10, bold: true }; cell.border = BORDER; }
  wsR.addConditionalFormatting({ ref: `K${first}:K${last}`, rules: [
    { type: "cellIs", operator: "equal", formulae: ['"Belum terpenuhi"'], style: { fill: RED }, priority: 1 },
    { type: "cellIs", operator: "equal", formulae: ['"Terpenuhi"'], style: { fill: GREEN }, priority: 2 },
    { type: "containsText", operator: "containsText", text: "Data", style: { fill: YEL }, priority: 3 } ] });
  [5, 26, 8, 10, 8, 12, 10, 10, 9, 11, 15, 45, 55].forEach((w, i) => { wsR.getColumn(i + 1).width = w; });

  // Kekurangan
  wsK.columns = [
    { header: "No", key: "no", width: 5 }, { header: "Layanan", key: "layanan", width: 30 }, { header: "Strata", key: "strata", width: 9 },
    { header: "Komponen", key: "komp", width: 10 }, { header: "Jenis", key: "jenis", width: 9 }, { header: "Kode Group", key: "group", width: 13 },
    { header: "Belum terpenuhi", key: "kurang", width: 55 }, { header: "Nama diseragamkan", key: "item", width: 26 }, { header: "Sifat", key: "sifat", width: 11 },
  ];
  for (const g of m.gaps) wsK.addRow({ no: g.no, layanan: g.layanan, strata: g.strata, komp: g.komp, jenis: g.jenis, group: g.group, kurang: g.kurang || g.persyaratan, item: g.item, sifat: g.sifat });
  header(wsK, wsK.getRow(1)); body(wsK, 2);
  wsK.autoFilter = { from: "A1", to: `I${Math.max(2, wsK.rowCount)}` };

  // Data mentah
  wsM.columns = [
    { header: "No Layanan", key: "no", width: 9 }, { header: "Layanan", key: "layanan", width: 28 }, { header: "Strata", key: "strata", width: 9 },
    { header: "Kode", key: "kode", width: 14 }, { header: "Nama", key: "nama", width: 50 }, { header: "Kelompok", key: "kelompok", width: 16 },
    { header: "Status", key: "status", width: 11 }, { header: "Jumlah", key: "jumlah", width: 8 }, { header: "Group", key: "group", width: 12 },
  ];
  for (const it of m.items.slice().sort((a, b) => a.no - b.no || SORD[a.strata] - SORD[b.strata])) wsM.addRow(it);
  header(wsM, wsM.getRow(1));
  wsM.autoFilter = { from: "A1", to: `I${Math.max(2, wsM.rowCount)}` };
  for (let i = 2; i <= wsM.rowCount; i++) wsM.getRow(i).font = F;

  // Catatan
  const notes = ["Catatan", "Sumber data per layanan:"];
  for (let i = 1; i <= 24; i++) for (const k of ["SDM", "Alkes"]) {
    const s = sources.get(i + "|" + k);
    notes.push(`  ${i}. ${LAYANAN[i]} – ${k}: ${s ? `${s.file} (${s.rows.length} baris)` : "BELUM ADA DATA"}`);
  }
  notes.push("", "Aturan: satu group terpenuhi bila semua item wajib di group itu berjumlah > 0 dan, kalau ada item substitusi, minimal satu berjumlah > 0.",
    "Sheet Ringkasan memakai rumus SUMIFS ke sheet Detail; mengubah kolom Terpenuhi di Detail ikut mengubah Ringkasan.",
    "Kolom 'Nama diseragamkan' di Kekurangan menyatukan varian nama (mis. CT-Scan 32/64/128 slice) agar kebutuhan lintas layanan bisa dihitung.");
  notes.forEach((t, i) => { const c = wsC.getCell(i + 1, 1); c.value = t; c.font = { name: "Arial", size: i === 0 ? 12 : 10, bold: i === 0 }; });
  wsC.getColumn(1).width = 120;

  return wb.xlsx.writeBuffer();
}

// ---------- template ----------
function templateCsv() {
  const rows = [
    ["No Layanan", "Layanan", "No", "Strata", "Kode", "Nama", "Kelompok", "Status", "Jumlah", "Group", "Kategori Nakes"],
    [4, "Neonatus", 1, "Dasar", "21102025", "USG 2D", "Alat Kesehatan", "substitusi", 0, "kalkes01", ""],
    [4, "Neonatus", 2, "Dasar", "21102019", "USG 3D", "Alat Kesehatan", "substitusi", 1, "kalkes01", ""],
    [4, "Neonatus", 3, "Dasar", "20902034", "Phototherapy unit", "Alat Kesehatan", "wajib", 4, "kalkes03", ""],
    [4, "Neonatus", 4, "Dasar", "739", "Ruangan Perawatan Perinatologi", "Sarana", "wajib", 1, "ksarana04", ""],
    [4, "Neonatus", 1, "Dasar", "", "Dokter Spesialis Anak (Sp.A)", "SDM Medis", "substitusi", 1, "ksdm02", "Sp. A/ Sp. A Fellow/ Sp. A Sub"],
  ];
  return "﻿" + rows.map((r) => r.map((v) => { const s = String(v); return /[",;\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; }).join(",")).join("\r\n");
}

// ---------- wiring ----------
function save(filename, data) {
  try {
    const isXlsx = /\.xlsx$/i.test(filename);
    const type = isXlsx ? "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" : "text/csv;charset=utf-8";
    const blob = new Blob([data], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url; 
    link.download = filename;
    link.target = "_blank";
    (document.body || document.documentElement).appendChild(link); 
    link.click();
    setTimeout(() => {
      link.remove();
      URL.revokeObjectURL(url);
    }, 6000);
    toast(`${filename} berhasil diunduh.`);
  } catch (err) {
    console.error("Download error:", err);
    toast(`Gagal mengunduh: ${err.message || filename}`);
  }
}

function exportAllCsv() {
  const items = model && model.items && model.items.length ? model.items : (window.SEED_DATA || []).map(r => ({
    no: r[0], layanan: r[1], strata: r[2], kode: r[3], nama: r[4], kelompok: r[5], status: r[6], jumlah: r[7], group: r[8]
  }));
  if (!items || !items.length) {
    toast("Tidak ada data untuk diunduh.");
    return;
  }
  const header = ["No Layanan", "Layanan", "Strata", "Kode", "Nama", "Kelompok", "Status", "Jumlah", "Group"];
  const rows = [header];
  items.forEach(it => {
    rows.push([
      it.no ?? "",
      it.layanan ?? "",
      it.strata ?? "",
      it.kode ?? "",
      it.nama ?? "",
      it.kelompok ?? "",
      it.status ?? "",
      it.jumlah ?? 0,
      it.group ?? ""
    ]);
  });
  const csvContent = "\uFEFF" + rows.map(r => r.map(v => {
    const s = String(v ?? "");
    return /[",;\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  }).join(",")).join("\r\n");
  save(`Semua_Kompetensi_24_Layanan_RSUD_Kilisuci_${new Date().toISOString().slice(0, 10)}.csv`, csvContent);
}

$("btn-all-csv")?.addEventListener("click", exportAllCsv);

$("btn-xlsx")?.addEventListener("click", async () => {
  if (!model || !model.n) { toast("Belum ada data untuk direkap."); return; }
  const b = $("btn-xlsx"); b.disabled = true; b.textContent = "Menyusun Excel…";
  try { 
    const buf = await buildXlsx(); 
    await save(`Rekap_Kompetensi_per_Layanan_${new Date().toISOString().slice(0, 10)}.xlsx`, new Uint8Array(buf)); 
  }
  catch (e) { 
    console.error("Excel build error:", e);
    toast("Gagal menyusun Excel."); 
  }
  finally { b.disabled = false; b.textContent = "Unduh rekap Excel"; }
});
$("btn-tpl")?.addEventListener("click", () => save("Template_Kompetensi_RS_Online.csv", templateCsv()));
const fileIn = $("file");
$("btn-upload")?.addEventListener("click", () => fileIn.click());
$("btn-pick")?.addEventListener("click", () => fileIn.click());
fileIn?.addEventListener("change", () => { if (fileIn.files.length) ingest([...fileIn.files]); fileIn.value = ""; });
const drop = $("drop");
["dragenter", "dragover"].forEach((t) => drop?.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add("over"); }));
["dragleave", "drop"].forEach((t) => drop?.addEventListener(t, (e) => { e.preventDefault(); drop.classList.remove("over"); }));
drop?.addEventListener("drop", (e) => { const fs = [...(e.dataTransfer?.files || [])]; if (fs.length) ingest(fs); });
$("btn-reset")?.addEventListener("click", () => { state.seedOff = false; loadSeed(); try { localStorage.removeItem(STORE_KEY); } catch (e) {} renderMsgs([["ok", "Kembali ke data rekap 17 Okt 2026."]]); rebuild(); });
$("btn-clear")?.addEventListener("click", () => { $("confirm-clear").hidden = false; });
$("btn-clear-no")?.addEventListener("click", () => { $("confirm-clear").hidden = true; });
$("btn-clear-yes")?.addEventListener("click", () => { $("confirm-clear").hidden = true; sources = new Map(); state.seedOff = true; persist(); renderMsgs([["ok", "Data dikosongkan. Unggah CSV 24 layanan."]]); rebuild(); });
["q", "f-komp", "f-strata", "f-sifat"].forEach((id) => $(id)?.addEventListener("input", renderTable));
$("picked")?.addEventListener("click", () => pick(ui.pick.no, ui.pick.strata));
document.querySelectorAll("table.gap th").forEach((th) => th.querySelector("button")?.addEventListener("click", () => {
  const k = th.dataset.k; if (ui.sortK === k) ui.sortDir = -ui.sortDir; else { ui.sortK = k; ui.sortDir = 1; } renderTable();
}));

// Tab Switching
$("tab-btn-gap")?.addEventListener("click", () => {
  $("tab-btn-gap")?.classList.add("active");
  $("tab-btn-all")?.classList.remove("active");
  const pGap = $("panel-gap"); if (pGap) pGap.hidden = false;
  const pAll = $("panel-all"); if (pAll) pAll.hidden = true;
});
$("tab-btn-all")?.addEventListener("click", () => {
  $("tab-btn-all")?.classList.add("active");
  $("tab-btn-gap")?.classList.remove("active");
  const pGap = $("panel-gap"); if (pGap) pGap.hidden = true;
  const pAll = $("panel-all"); if (pAll) pAll.hidden = false;
  renderAllTable();
});

// Full Components Table Listeners
["all-q", "all-f-layanan", "all-f-komp", "all-f-strata", "all-f-status", "all-f-kondisi"].forEach((id) => {
  $(id)?.addEventListener("input", () => { allUi.page = 1; renderAllTable(); });
  $(id)?.addEventListener("change", () => { allUi.page = 1; renderAllTable(); });
});
$("all-per-page")?.addEventListener("change", () => { allUi.page = 1; renderAllTable(); });
$("all-btn-prev")?.addEventListener("click", () => { if (allUi.page > 1) { allUi.page--; renderAllTable(); } });
$("all-btn-next")?.addEventListener("click", () => { allUi.page++; renderAllTable(); });

document.querySelectorAll("table.tbl-rsonline th").forEach((th) => {
  th.querySelector("button")?.addEventListener("click", () => {
    const k = th.dataset.allK;
    if (allUi.sortK === k) allUi.sortDir = -allUi.sortDir;
    else { allUi.sortK = k; allUi.sortDir = 1; }
    renderAllTable();
  });
});

$("btn-export-all-filter")?.addEventListener("click", () => {
  if (!allUi.filteredItems || !allUi.filteredItems.length) {
    toast("Tidak ada data untuk diunduh.");
    return;
  }
  const headers = ["No Layanan", "Layanan", "Strata", "Komponen", "Kode", "Nama Komponen", "Kelompok", "Status", "Jumlah", "Group"];
  const rows = allUi.filteredItems.map((it) => [
    it.no,
    `"${(it.layanan || "").replace(/"/g, '""')}"`,
    it.strata,
    it.komp,
    it.kode,
    `"${(it.nama || "").replace(/"/g, '""')}"`,
    `"${(it.kelompok || "").replace(/"/g, '""')}"`,
    it.status,
    it.jumlah,
    it.group
  ]);
  const csv = [headers.join(","), ...rows.map((r) => r.join(","))].join("\r\n");
  save(`Detil_Komponen_RS_Online_${new Date().toISOString().slice(0, 10)}.csv`, csv);
});
let rw;
window.addEventListener("resize", () => { clearTimeout(rw); rw = setTimeout(() => { if (model) { renderBars(); renderTop(); } }, 150); });
window.matchMedia("(prefers-color-scheme: dark)").addEventListener?.("change", () => { if (model) { renderBars(); renderTop(); } });
new MutationObserver(() => { if (model) { renderBars(); renderTop(); } }).observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });

// Sync with main app theme
const syncThemeFromStorage = () => {
  try {
    const parentTheme = localStorage.getItem("theme") || localStorage.getItem("kompetensi-theme") || "light";
    document.documentElement.setAttribute("data-theme", parentTheme);
  } catch (e) {}
};
syncThemeFromStorage();
window.addEventListener("storage", syncThemeFromStorage);
window.addEventListener("message", (e) => {
  if (e.data && e.data.type === "THEME_CHANGE") {
    document.documentElement.setAttribute("data-theme", e.data.theme);
  }
});

const themeBtn = $("btn-theme");
if (themeBtn) {
  const updateThemeIcon = () => {
    const cur = document.documentElement.getAttribute("data-theme") || "light";
    themeBtn.textContent = cur === "dark" ? "☀️ Terang" : "🌙 Gelap";
  };
  updateThemeIcon();
  themeBtn.addEventListener("click", () => {
    const cur = document.documentElement.getAttribute("data-theme") || "light";
    const next = cur === "dark" ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", next);
    try { 
      localStorage.setItem("theme", next); 
      localStorage.setItem("kompetensi-theme", next);
    } catch (e) {}
    updateThemeIcon();
    // Notify parent window if in iframe
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: "THEME_CHANGE", theme: next }, "*");
      }
    } catch (e) {}
  });
}

// Global hooks for parent window
window.downloadAllCsv = exportAllCsv;
window.downloadXlsx = () => $("btn-xlsx")?.click();
window.resetAllData = () => { sources = new Map(); loadSeed(); rebuild(); };

restore();
rebuild();

// In-iframe detection & Public Standalone Mode
if (window.self !== window.top) {
  document.body.classList.add('in-iframe');
} else {
  document.body.classList.remove('in-iframe');
  // Standalone public view: auto fetch latest snapshot from database
  fetch('/api/snapshot-data')
    .then((r) => r.json())
    .then((data) => {
      if (data.status === 'success' && data.items && data.items.length > 0) {
        loadCustomSnapshot(data.items, data.name || 'Tarikan SIRS Terkini');
        const lead = $('lead');
        if (lead) {
          lead.innerHTML = 'Data Terkini SIRS Online <b>(' + (data.periode || 'Hari ini') + ')</b> &bull; ' + data.total.toLocaleString('id-ID') + ' baris instrumen terintegrasi.';
        }
      }
    })
    .catch(() => {});
}

