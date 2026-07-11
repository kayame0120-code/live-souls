import React, { useState, useMemo } from "react";

// ─────────────────────────────────────────────────────────
// 現場手帖 / events 一括インポート デモ
// パーサは Python→JS 移植版（node で 45件一致を機械検証済み）。
// Laravel実装ではこの parse() をそのまま PHP のサービスクラスへ移植する想定。
// デモにDBは無いため「送信」＝確定表として画面表示（=登録先を画面に差し替え）。
// ─────────────────────────────────────────────────────────

const SAMPLE = `TOP
ARTIST
NEWS
公演情報
CONCERT/STAGE
CONCERT
なにわ男子 1st DOME LIVE 'VoyAGE'
なにわ男子
SCHEDULE
TICKET
GOODS
SCHEDULE
[大阪府] 京セラドーム大阪
公演日\t開演時間
2026.11.14（土）\t14:00
18:30
2026.11.15（日）\t14:00
アクセス情報
JR大正駅より徒歩7分
[東京都] 東京ドーム
公演日\t開演時間
2026.11.28（土）\t14:00
18:30
2026.11.29（日）\t14:00
アクセス情報
JR水道橋駅より徒歩5分

＜ご注意＞
※公演スケジュールは変更となる場合があります。
一覧へ戻る
SHARE
TOP`;

// ノイズ見出し（この直後の実質行がツアー名候補）
const NOISE = new Set(["TOP", "ARTIST", "NEWS", "SCHEDULE", "CONCERT/STAGE", "MOVIE",
  "RELEASE", "会社情報", "OFFICIAL ACCOUNT", "CONCERT", "TICKET", "GOODS", "公演情報",
  "SHARE", "EN", "JP", "サービスガイドはこちら", "一覧へ戻る"]);
const HEAD = /^(CONCERT|公演情報|CONCERT\/STAGE)$/;

// ── パイプライン：切出し→正規化→中間表 ──
function parse(text) {
  const lines = text.split("\n").map((l) => l.trim());
  const VENUE = /^\[(.+?)\]\s*(.+)$/;
  const DATE = /^(\d{4})\.(\d{2})\.(\d{2})（[^）]+）\s*(\d{1,2}:\d{2})?/;
  const TIME = /^(\d{1,2}:\d{2})$/;
  const END = /^(＜ご注意＞|※|一覧へ戻る|SHARE$)/;

  const startIdx = lines.findIndex((l) => VENUE.test(l));
  if (startIdx === -1) return { tour: null, rows: [], bodyRange: null };
  let endIdx = lines.findIndex((l, i) => i > startIdx && END.test(l));
  if (endIdx === -1) endIdx = lines.length;

  // ツアー名＝構造位置で特定（キーワード非依存）：
  // 「CONCERT / 公演情報」等の見出しの直後にある、ノイズでない実質行。
  // 命名が自由（LIVE TOUR / DOME LIVE 'VoyAGE' / PARTY 等）でも構造は共通なので拾える。
  let tour = null;
  for (let i = 0; i < startIdx && !tour; i++) {
    if (HEAD.test(lines[i])) {
      for (let j = i + 1; j < startIdx; j++) {
        const t = lines[j];
        if (!t || NOISE.has(t) || HEAD.test(t)) continue;
        if (t.length >= 5) { tour = t; break; }
      }
    }
  }

  const rows = [];
  let venue = null, date = null, mode = null;
  for (let i = startIdx; i < endIdx; i++) {
    let line = lines[i].replace(/[ \u3000\t]+/g, " ").trim();
    if (!line || line === "公演日 開演時間") continue;
    let m;
    if ((m = line.match(VENUE))) {
      venue = m[2]; mode = "schedule";
      rows.push({ kind: "venue", venue, date: "", time: "", raw: line });
      continue;
    }
    if (line === "アクセス情報") { mode = "access"; continue; }
    if ((m = line.match(DATE))) {
      date = `${m[1]}-${m[2]}-${m[3]}`; mode = "schedule";
      if (m[4]) rows.push({ kind: "show", venue, date, time: m[4], raw: line });
      continue;
    }
    if (mode === "schedule" && (m = line.match(TIME))) {
      rows.push({ kind: "show", venue, date, time: m[1], raw: line });
      continue;
    }
    if (mode === "access") {
      rows.push({ kind: "access", venue, date: "", time: "", raw: line });
      continue;
    }
    rows.push({ kind: "unknown", venue: "", date: "", time: "", raw: line });
  }
  return { tour, rows, bodyRange: [startIdx, endIdx, lines.length] };
}

const PAPER = "#FAFAF7", CARD = "#FFFFFF", INK = "#23252B", SUB = "#8A8C92";
const KEISEN = "#E9E8E2", KEISEN2 = "#D8D7D0", OSHI = "#C7414F", OK = "#3D9A6E";

export default function EventImportDemo() {
  const [text, setText] = useState(SAMPLE);
  const [confirmed, setConfirmed] = useState(false);
  const [rows, setRows] = useState([]);
  const [tour, setTour] = useState("");
  const [committed, setCommitted] = useState(null);

  const stats = useMemo(() => {
    const { tour, rows, bodyRange } = parse(text);
    const shows = rows.filter((r) => r.kind === "show");
    const events = shows.map((s, i) => ({
      id: i,
      event_name: tour || "(ツアー名未検出)",
      event_date: s.date,
      start_time: s.time, // Q1: 昼夜識別のため保持
      venue: s.venue,
      dropped: false,
    }));
    const unknown = rows.filter((r) => r.kind === "unknown");
    const noise = bodyRange ? bodyRange[2] - (bodyRange[1] - bodyRange[0]) : 0;
    return { tour, events, unknown, noise, accessCount: rows.filter(r=>r.kind==="access").length };
  }, [text]);

  function handleExtract() {
    setRows(stats.events);
    setTour(stats.tour || "");
    setConfirmed(true);
    setCommitted(null);
  }

  function editCell(id, field, val) {
    setRows((rs) => rs.map((r) => (r.id === id ? { ...r, [field]: val } : r)));
  }
  function toggleDrop(id) {
    setRows((rs) => rs.map((r) => (r.id === id ? { ...r, dropped: !r.dropped } : r)));
  }

  function handleCommit() {
    const keep = rows.filter((r) => !r.dropped);
    // venue名寄せ（同名→1会場）
    const venues = [...new Set(keep.map((r) => r.venue))];
    // 同一 venue×date×time の重複検出（警告・非ブロック）
    const seen = {}, dupes = [];
    keep.forEach((r) => {
      const k = `${r.venue}|${r.event_date}|${r.start_time}`;
      if (seen[k]) dupes.push(r); else seen[k] = true;
    });
    setCommitted({ events: keep, venues, dupes });
  }

  const wrap = { fontFamily: "'Zen Kaku Gothic New', sans-serif", background: "#EBEAE5", color: INK, padding: 24, minHeight: "100%", lineHeight: 1.7 };
  const doc = { maxWidth: 760, margin: "0 auto", background: PAPER, borderRadius: 14, boxShadow: "0 0 40px rgba(0,0,0,.08)", padding: "32px 30px 40px" };
  const h1 = { fontSize: 20, fontWeight: 700, letterSpacing: ".1em", borderBottom: `2px solid ${INK}`, paddingBottom: 12, marginBottom: 6 };
  const h1s = { fontSize: 11, fontWeight: 500, color: SUB, letterSpacing: ".18em", marginLeft: 8 };
  const step = { fontSize: 12, fontWeight: 700, color: OSHI, letterSpacing: ".1em", margin: "22px 0 6px" };
  const ta = { width: "100%", minHeight: 160, fontFamily: "ui-monospace,Menlo,monospace", fontSize: 11.5, padding: 12, border: `1px solid ${KEISEN2}`, borderRadius: 8, background: CARD, color: INK, resize: "vertical", lineHeight: 1.6 };
  const btn = (bg) => ({ background: bg, color: "#fff", border: "none", padding: "9px 20px", borderRadius: 8, fontFamily: "inherit", fontSize: 13, fontWeight: 700, letterSpacing: ".04em", cursor: "pointer" });
  const th = { textAlign: "left", padding: "8px 10px", fontSize: 10.5, fontWeight: 700, color: SUB, background: "#F2F1EC", letterSpacing: ".04em", borderBottom: `1px solid ${KEISEN}` };
  const td = { padding: "6px 10px", fontSize: 12, borderBottom: `1px solid ${KEISEN}`, verticalAlign: "middle" };
  const inp = { width: "100%", fontFamily: "inherit", fontSize: 12, padding: "4px 6px", border: `1px solid ${KEISEN}`, borderRadius: 4, background: CARD, color: INK };

  return (
    <div style={wrap}>
      <div style={doc}>
        <div style={h1}>現場手帖<span style={h1s}>events 一括インポート / デモ</span></div>
        <p style={{ fontSize: 12, color: SUB, margin: "10px 0 4px", padding: "10px 14px", background: CARD, borderLeft: `3px solid ${OSHI}`, borderRadius: "0 6px 6px 0" }}>
          公式サイトの公演ページを丸ごと貼り付け → 抽出 → 確認テーブルで補正 → 送信で共有マスタへ登録。
          抽出は正規表現＋状態機械（AI不使用）。ノイズ（ナビ・フッター）は本文境界で自動遮断。
        </p>

        <div style={step}>STEP 1 ── 貼り付け（ノイズ込みでOK）</div>
        <textarea style={ta} value={text} onChange={(e) => { setText(e.target.value); setConfirmed(false); }} />
        <div style={{ display: "flex", gap: 14, alignItems: "center", marginTop: 8, flexWrap: "wrap" }}>
          <button style={btn(INK)} onClick={handleExtract}>抽出する</button>
          <span style={{ fontSize: 11.5, color: SUB }}>
            プレビュー: <b style={{ color: INK }}>{stats.events.length}</b> 公演を検出
            {stats.noise > 0 && <> ／ ノイズ <b style={{ color: OSHI }}>{stats.noise}</b> 行を遮断</>}
            {stats.unknown.length > 0 && <> ／ 未解析 <b style={{ color: OSHI }}>{stats.unknown.length}</b> 行</>}
          </span>
        </div>

        {confirmed && (
          <>
            <div style={step}>STEP 2 ── 確認テーブル（編集・除外できる）</div>
            <div style={{ fontSize: 11.5, color: SUB, marginBottom: 6 }}>
              ツアー名（全行に適用）:&nbsp;
              <input style={{ ...inp, width: 320, display: "inline-block" }} value={tour}
                onChange={(e) => { setTour(e.target.value); setRows(rs => rs.map(r => ({ ...r, event_name: e.target.value }))); }} />
            </div>
            <div style={{ overflowX: "auto", border: `1px solid ${KEISEN}`, borderRadius: 8 }}>
              <table style={{ width: "100%", borderCollapse: "collapse", background: CARD }}>
                <thead><tr>
                  <th style={th}>公演日</th><th style={th}>開演</th><th style={th}>会場</th><th style={{ ...th, width: 56 }}>除外</th>
                </tr></thead>
                <tbody>
                  {rows.map((r) => (
                    <tr key={r.id} style={{ opacity: r.dropped ? 0.35 : 1, background: r.dropped ? "#FBF2F3" : "transparent" }}>
                      <td style={td}><input style={inp} value={r.event_date} onChange={(e) => editCell(r.id, "event_date", e.target.value)} /></td>
                      <td style={td}><input style={{ ...inp, width: 64 }} value={r.start_time} onChange={(e) => editCell(r.id, "start_time", e.target.value)} /></td>
                      <td style={td}><input style={inp} value={r.venue} onChange={(e) => editCell(r.id, "venue", e.target.value)} /></td>
                      <td style={{ ...td, textAlign: "center" }}>
                        <button onClick={() => toggleDrop(r.id)} style={{ border: `1px solid ${KEISEN2}`, background: r.dropped ? OSHI : CARD, color: r.dropped ? "#fff" : SUB, borderRadius: 4, fontSize: 11, padding: "2px 8px", cursor: "pointer", fontFamily: "inherit" }}>
                          {r.dropped ? "戻す" : "除外"}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {stats.unknown.length > 0 && (
              <div style={{ fontSize: 11, color: SUB, marginTop: 8, padding: "8px 12px", background: "#FBF3F2", border: "1px solid #EBD3D3", borderRadius: 6 }}>
                未解析として保持された行（捨てずに確認可能）:
                {stats.unknown.map((u, i) => <div key={i} style={{ fontFamily: "monospace", fontSize: 10.5 }}>・{u.raw}</div>)}
              </div>
            )}
            <div style={{ marginTop: 12 }}>
              <button style={btn(OSHI)} onClick={handleCommit}>共有マスタへ送信</button>
            </div>
          </>
        )}

        {committed && (
          <>
            <div style={step}>STEP 3 ── 登録結果（Laravelでは events テーブルへ INSERT）</div>
            <div style={{ display: "flex", gap: 10, flexWrap: "wrap", marginBottom: 8 }}>
              <Badge c={OK}>events {committed.events.length} 件</Badge>
              <Badge c={INK}>venues 名寄せ {committed.venues.length} 会場</Badge>
              {committed.dupes.length > 0 && <Badge c={OSHI}>同一会場・同日時 {committed.dupes.length} 件（警告・登録は許可）</Badge>}
            </div>
            <div style={{ overflowX: "auto", border: `1px solid ${KEISEN}`, borderRadius: 8 }}>
              <table style={{ width: "100%", borderCollapse: "collapse", background: CARD }}>
                <thead><tr>
                  <th style={th}>event_date</th><th style={th}>start_time</th><th style={th}>venue_id →</th><th style={th}>event_name</th>
                </tr></thead>
                <tbody>
                  {committed.events.map((e, i) => (
                    <tr key={i}>
                      <td style={td}>{e.event_date}</td>
                      <td style={td}>{e.start_time}</td>
                      <td style={td}>{committed.venues.indexOf(e.venue) + 1}：{e.venue}</td>
                      <td style={{ ...td, color: SUB }}>{e.event_name}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p style={{ fontSize: 11, color: SUB, marginTop: 10 }}>
              ↑ この表がDBに入る行。start_time 列があるため同日昼夜（例 大阪城 7/29 の 13:30 と 18:00）が別行として区別されている。
            </p>
          </>
        )}
      </div>
    </div>
  );
}

function Badge({ c, children }) {
  return <span style={{ fontSize: 11, fontWeight: 700, color: "#fff", background: c, padding: "3px 10px", borderRadius: 999, letterSpacing: ".03em" }}>{children}</span>;
}
