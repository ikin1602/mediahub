// Simple client-side booking store & conflict checker (per resource & date)
const $ = (s, ctx=document) => ctx.querySelector(s);

function getQuery() { return Object.fromEntries(new URLSearchParams(location.search)); }
function keyFor(type, id) { return `re_bookings::${type}::${id}`; }
function readBookings(type, id) {
  try { return JSON.parse(localStorage.getItem(keyFor(type, id)) || "[]"); }
  catch(e){ return []; }
}
function writeBookings(type, id, arr) {
  localStorage.setItem(keyFor(type, id), JSON.stringify(arr));
}
function timeToMinutes(t) {
  const [h, m] = t.split(":").map(Number);
  return h * 60 + m;
}
function intervalsOverlap(aStart, aEnd, bStart, bEnd) {
  return Math.max(aStart, bStart) < Math.min(aEnd, bEnd);
}
function formatRange(s, e){ return `${s}–${e}`; }
function uid(){ return Math.random().toString(36).slice(2,10); }

function renderTableRows(tbody, rows, opts={}){
  tbody.innerHTML = "";
  rows.forEach(r => {
    const tr = document.createElement("tr");
    tr.innerHTML = [
      opts.all ? `<td>${r.type}</td><td>${r.resourceName}</td>` : "",
      `<td>${r.date}</td>`,
      `<td>${formatRange(r.start, r.end)}</td>`,
      `<td>${r.name}</td>`,
      `<td>${r.qty}</td>`,
      `<td><button class="btn" data-cancel data-type="${opts.all ? r.type : r.type}" data-id="${opts.all ? r.idKey : r.idKey}" data-bookid="${r.bookId}">Cancel</button></td>`
    ].join("");
    tbody.appendChild(tr);
  });
}

document.addEventListener("click", (e)=>{
  const btn = e.target.closest("button[data-cancel]");
  if (!btn) return;
  const type = btn.getAttribute("data-type");
  const idKey = btn.getAttribute("data-id");
  const bookId = btn.getAttribute("data-bookid");
  const arr = readBookings(type, idKey).filter(b => b.bookId !== bookId);
  writeBookings(type, idKey, arr);
  // Re-render appropriate view(s)
  initBookPage();
  initMyBookingsPage();
});

function initBookPage(){
  const sect = document.querySelector('#booking-section[data-page="book"]');
  if (!sect) return;

  const q = getQuery();
  const type = q.type || "equipment";
  const idKey = q.id || "unknown";
  const name = decodeURIComponent((q.name||idKey).replace(/\+/g," "));
  $("#resourceTitle").textContent = `Book ${name}`;
  $("#type").value = type;
  $("#id").value = idKey;

  const now = new Date();
  $("#date").value = now.toISOString().slice(0,10);
  $("#start").value = "09:00";
  $("#end").value = "12:00";

  const tbody = document.querySelector("#bookingsTable tbody");
  const rows = readBookings(type, idKey);
  renderTableRows(tbody, rows.map(b=>({...b, idKey})), { all:false });

  const form = document.querySelector("#bookingForm");
  const msg = document.querySelector("#formMsg");
  form.onsubmit = (ev) => {
    ev.preventDefault();
    const date = document.querySelector("#date").value;
    const start = document.querySelector("#start").value;
    const end = document.querySelector("#end").value;
    const qty = parseInt(document.querySelector("#qty").value || "1", 10);
    const person = document.querySelector("#name").value.trim();
    const contact = document.querySelector("#contact").value.trim();

    const s = timeToMinutes(start), e = timeToMinutes(end);
    if (e <= s){ msg.style.display="block"; msg.textContent="End time must be after start time."; return; }

    const existingSameDay = readBookings(type, idKey).filter(b => b.date === date);
    const conflicts = existingSameDay.some(b => intervalsOverlap(s, e, timeToMinutes(b.start), timeToMinutes(b.end)));
    if (conflicts){
      msg.style.display="block"; msg.textContent="Conflict: overlaps an existing booking for this resource on the same date.";
      return;
    }

    const booking = { bookId: uid(), type, idKey, resourceName: name, date, start, end, qty, name: person, contact };
    const all = readBookings(type, idKey);
    all.push(booking);
    writeBookings(type, idKey, all);
    msg.style.display="block"; msg.textContent="Saved! Your booking is stored locally.";
    renderTableRows(tbody, readBookings(type, idKey).map(b=>({...b, idKey})), { all:false });
    form.reset();
    document.querySelector("#date").value = date;
    document.querySelector("#start").value = start;
    document.querySelector("#end").value = end;
  };
}

function initMyBookingsPage(){
  const sect = document.querySelector('#booking-section[data-page="my-bookings"]');
  if (!sect) return;
  const tbody = document.querySelector("#allBookingsTable tbody");
  const keys = Object.keys(localStorage).filter(k => k.startsWith("re_bookings::"));
  const rows = [];
  keys.forEach(k => {
    const [_root, type, idKey] = k.split("::");
    let arr = [];
    try { arr = JSON.parse(localStorage.getItem(k) || "[]"); } catch(e){}
    arr.forEach(b => rows.push({ ...b, type, idKey }));
  });
  rows.sort((a,b)=> (a.date+a.start).localeCompare(b.date+b.start));
  renderTableRows(tbody, rows, { all:true });
}

document.addEventListener("DOMContentLoaded", ()=>{
  initBookPage();
  initMyBookingsPage();
});
