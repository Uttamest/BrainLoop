/* BrainLoop — static learning MVP. No external services, API keys, or cloud storage. */
'use strict';

const STORAGE_KEY = 'brainloop-v1';
const DAY = 24 * 60 * 60 * 1000;
const INTERVALS = [0, 1, 3, 7, 14, 30, 60, 120];
const COLORS = ['violet', 'blue', 'green', 'rose'];
const SYMBOLS = { violet: '✦', blue: '⌘', green: '✳', rose: '✿' };
const $ = (selector) => document.querySelector(selector);
const escapeHTML = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
const uid = () => (globalThis.crypto?.randomUUID?.() || `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`);
const nowISO = () => new Date().toISOString();
const due = (card) => !card.dueAt || new Date(card.dueAt).getTime() <= Date.now();
const cardsFor = (deckId) => state.cards.filter(c => c.deckId === deckId);
const getDeck = (deckId) => state.decks.find(d => d.id === deckId);
const getCard = (cardId) => state.cards.find(c => c.id === cardId);

function defaultData() {
  const decks = [
    {id:'demo-web', name:'Web Development Basics', description:'A sample collection of foundational web concepts.', color:'violet', createdAt:nowISO()},
    {id:'demo-cs', name:'Computer Science', description:'Sample questions on common programming concepts.', color:'blue', createdAt:nowISO()}
  ];
  const samples = [
    ['demo-web', 'What does HTML stand for?', 'HyperText Markup Language.'],
    ['demo-web', 'What is CSS used for?', 'Styling and laying out web pages.'],
    ['demo-web', 'What does the DOM represent?', 'The document as a tree of objects that scripts can read and update.'],
    ['demo-web', 'What is a semantic HTML element?', 'An element whose name communicates its meaning, such as <main> or <article>.'],
    ['demo-web', 'What does a JavaScript event listener do?', 'Runs a callback when a specified event occurs on a target.'],
    ['demo-cs', 'What is a data structure?', 'A way of organizing data for access and modification.'],
    ['demo-cs', 'What is a stack?', 'A last-in, first-out (LIFO) collection.'],
    ['demo-cs', 'What is a queue?', 'A first-in, first-out (FIFO) collection.'],
    ['demo-cs', 'What is an algorithm?', 'A finite sequence of well-defined steps for solving a problem.'],
    ['demo-cs', 'What is encapsulation in OOP?', 'Bundling data and behavior while controlling access to implementation details.']
  ];
  return {version:1, decks, cards:samples.map(([deckId,question,answer]) => ({id:uid(), deckId, question, answer, level:0, dueAt:nowISO(), lastReviewedAt:null, reviews:0}))};
}

function readState() {
  try {
    const text = localStorage.getItem(STORAGE_KEY);
    if (!text) return defaultData();
    const value = JSON.parse(text);
    if (value?.version === 1 && Array.isArray(value.decks) && Array.isArray(value.cards)) return value;
  } catch (err) { console.warn('Unable to read saved BrainLoop data:', err); }
  return defaultData();
}
let state = readState();
let currentDeckId = null;
let deckSearch = '';
let session = null;
let lastFocus = null;

function save() {
  try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); return true; }
  catch (err) { console.warn('BrainLoop data could not be saved:', err); toast('Unable to save in this browser. Export a backup.'); return false; }
}
try { if (!localStorage.getItem(STORAGE_KEY)) save(); } catch (err) { console.warn('Storage is unavailable in this browser:', err); }

function toast(message) {
  const el = document.createElement('div');
  el.className = 'toast';
  el.textContent = message;
  $('#toastContainer').append(el);
  setTimeout(() => el.remove(), 3000);
}
function openMobile(open) {
  $('#sidebar').classList.toggle('is-open', open);
  $('#mobileOverlay').hidden = !open;
  $('#menuToggle').setAttribute('aria-expanded', String(open));
}
function route() {
  const hash = decodeURIComponent(location.hash.replace(/^#/, ''));
  if (hash.startsWith('deck/')) return {page:'deck', id:hash.slice(5)};
  return {page:['dashboard','decks','review','settings'].includes(hash) ? hash : 'dashboard'};
}
function navigate(hash) { if (location.hash === hash) render(); else location.hash = hash; }
function setContent(html, name) {
  $('#appContent').innerHTML = html;
  $('#currentCrumb').textContent = name;
  $('#sidebarDeckCount').textContent = state.decks.length;
  document.querySelectorAll('.nav-link').forEach(el => el.classList.toggle('active', el.dataset.route === (route().page === 'deck' ? 'decks' : route().page)));
  openMobile(false);
}
function deckCard(deck) {
  const cards = cardsFor(deck.id);
  const mastered = cards.filter(c => c.level >= 3).length;
  const progress = cards.length ? Math.round(mastered / cards.length * 100) : 0;
  const dueCount = cards.filter(due).length;
  const color = COLORS.includes(deck.color) ? deck.color : 'violet';
  return `<article class="deck-card">
    <div class="deck-top"><span class="deck-art ${color}" aria-hidden="true">${SYMBOLS[color]}</span><span class="tag">${dueCount} due now</span></div>
    <div><h3>${escapeHTML(deck.name)}</h3><p class="deck-description">${escapeHTML(deck.description || 'Your custom learning deck.')}</p></div>
    <div><div class="deck-meta"><span>${cards.length} card${cards.length===1?'':'s'}</span><span>${progress}% mastered</span></div><div class="progress-track" aria-label="${progress}% mastered"><div class="progress-fill" style="width:${progress}%"></div></div></div>
    <div class="deck-actions"><button class="button button-muted" data-action="open-deck" data-id="${escapeHTML(deck.id)}">View cards →</button><button class="button button-outline" data-action="start-deck" data-id="${escapeHTML(deck.id)}" ${!dueCount?'disabled':''}>Review due (${dueCount})</button></div>
  </article>`;
}
function dashboard() {
  const total = state.cards.length;
  const ready = state.cards.filter(due).length;
  const mastered = state.cards.filter(c => c.level >= 3).length;
  const empty = !state.decks.length;
  const featured = state.decks.slice(0, 4);
  setContent(`<div class="welcome-title"><p class="eyebrow">WELCOME TO YOUR LEARNING SPACE</p><h1 class="page-heading">Make learning stick<span style="color:#a99dff">.</span></h1><p class="page-lead">Build your knowledge, one flashcard at a time.</p></div>
    <section class="hero"><div><div class="eyebrow">✦ YOUR DAILY STUDY ROUTINE</div><h2>Small sessions.<br>Big breakthroughs.</h2><p>Practice active recall and revisit cards at increasing intervals as you remember them.</p><button class="button button-primary" data-action="go-review">Start reviewing <span aria-hidden="true">↗</span></button></div><div class="hero-symbol" aria-hidden="true">✳</div></section>
    <section class="stats" aria-label="Study statistics">
      <div class="stat"><div class="stat-label">Total flashcards <span class="stat-icon">▤</span></div><div class="stat-value">${total}</div><div class="stat-sub">Across ${state.decks.length} deck${state.decks.length===1?'':'s'}</div></div>
      <div class="stat"><div class="stat-label">Due for review <span class="stat-icon">◷</span></div><div class="stat-value">${ready}</div><div class="stat-sub">Ready when you are</div></div>
      <div class="stat"><div class="stat-label">Mastered cards <span class="stat-icon">✦</span></div><div class="stat-value">${mastered}</div><div class="stat-sub">Reached review level 3+</div></div>
    </section>
    <section><div class="section-heading"><h2>Your study decks</h2><button class="button-link" data-action="go-decks">View all decks →</button></div>
    ${empty ? emptyDeck() : `<div class="deck-grid">${featured.map(deckCard).join('')}</div>`}</section>
    <div class="tip-row"><strong>Study tip · </strong>Try recalling the answer before flipping a card. Retrieval practice works best when you challenge your memory first.</div>`, 'Dashboard');
}
function emptyDeck() { return `<div class="empty"><div class="empty-icon">✦</div><h3>No decks yet</h3><p>Create a deck to start collecting things you want to remember.</p><button class="button button-primary" data-action="new-deck">Create your first deck</button></div>`; }
function decksPage() {
  setContent(`<div class="between"><div><p class="eyebrow">YOUR KNOWLEDGE LIBRARY</p><h1 class="page-heading">My decks</h1><p class="page-lead">Keep your ideas organized in one place.</p></div><button class="button button-primary" data-action="new-deck">＋ Create deck</button></div>
    <div class="controls-row"><input class="search-input" id="deckSearch" type="search" placeholder="Search decks..." aria-label="Search decks" value="${escapeHTML(deckSearch)}"><span class="muted" id="deckListCount" style="font-size:12px"></span></div>
    <div class="deck-grid" id="deckList"></div>
    <div class="tip-row"><strong>New here? · </strong>The first two decks are editable sample content. Delete them whenever you're ready to make BrainLoop your own.</div>`, 'My decks');
  updateDeckSearch();
}
function updateDeckSearch() {
  const filtered = state.decks.filter(d => `${d.name} ${d.description||''}`.toLowerCase().includes(deckSearch.toLowerCase()));
  $('#deckList').innerHTML = filtered.length ? filtered.map(deckCard).join('') : (state.decks.length ? `<div class="empty" style="grid-column:1/-1"><h3>No matching decks</h3><p>Try a different search.</p></div>` : emptyDeck());
  $('#deckListCount').textContent = `${filtered.length} of ${state.decks.length} decks`;
}
function deckPage(deckId) {
  const deck = getDeck(deckId);
  if (!deck) { navigate('#decks'); return; }
  currentDeckId = deckId;
  const cards = cardsFor(deckId);
  const color = COLORS.includes(deck.color) ? deck.color : 'violet';
  setContent(`<a class="back-link" href="#decks">← Back to all decks</a>
    <div class="details-head"><div class="deck-art ${color}" aria-hidden="true">${SYMBOLS[color]}</div><div><h1>${escapeHTML(deck.name)}</h1><p>${escapeHTML(deck.description || 'Personal study collection')} · ${cards.length} flashcards</p></div></div>
    <div class="detail-toolbar"><div class="section-heading" style="margin:0"><h2>Flashcards</h2><span class="tag">${cards.filter(due).length} due for review</span></div><div style="display:flex;gap:8px;flex-wrap:wrap"><button class="button button-outline" data-action="edit-deck" data-id="${escapeHTML(deckId)}">Edit deck</button><button class="button button-muted" data-action="start-deck" data-id="${escapeHTML(deckId)}" ${cards.some(due)?'':'disabled'}>Study due cards</button><button class="button button-primary" data-action="new-card" data-id="${escapeHTML(deckId)}">＋ Add card</button></div></div>
    <div class="card-list">${cards.length ? cards.map((card,i) => `<article class="flashcard-item"><span class="flashcard-number">${i+1}</span><div><div class="flashcard-question">${escapeHTML(card.question)}</div><div class="flashcard-answer">${escapeHTML(card.answer)}</div><div style="margin-top:8px"><span class="tag">${card.level >= 3 ? 'Mastered' : card.level > 0 ? 'Learning' : 'New'}</span></div></div><div class="card-item-actions"><button class="tiny-btn" aria-label="Edit flashcard" data-action="edit-card" data-id="${escapeHTML(card.id)}">Edit</button><button class="tiny-btn" aria-label="Delete flashcard" data-action="delete-card" data-id="${escapeHTML(card.id)}">Delete</button></div></article>`).join('') : `<div class="empty"><div class="empty-icon">▤</div><h3>This deck is empty</h3><p>Add your first question and answer.</p><button class="button button-primary" data-action="new-card" data-id="${escapeHTML(deckId)}">Add a flashcard</button></div>`}</div>
    <div style="margin-top:30px"><button class="button button-danger" data-action="delete-deck" data-id="${escapeHTML(deckId)}">Delete this deck</button></div>`, deck.name);
}
function reviewPage() {
  const ready = state.cards.filter(due).length;
  if (!session) {
    setContent(`<div class="review-wrap"><p class="eyebrow">ACTIVE RECALL MODE</p><h1 class="page-heading">Review your flashcards</h1><p class="page-lead">Think of the answer first, then reveal it and grade your recall.</p>
      <div class="stats" style="grid-template-columns:repeat(2,1fr)"><div class="stat"><div class="stat-label">Due now</div><div class="stat-value">${ready}</div><div class="stat-sub">Cards ready to practice</div></div><div class="stat"><div class="stat-label">Total decks</div><div class="stat-value">${state.decks.length}</div><div class="stat-sub">Your learning collections</div></div></div>
      <label class="field-label" for="reviewDeck">Select a deck</label><select class="field-select review-select" id="reviewDeck"><option value="all">All decks (${ready} due)</option>${state.decks.map(d => `<option value="${escapeHTML(d.id)}">${escapeHTML(d.name)} (${cardsFor(d.id).filter(due).length} due)</option>`).join('')}</select>
      <div style="margin-top:22px"><button class="button button-primary" data-action="begin-review" ${!ready?'disabled':''}>Start study session →</button></div>
      ${ready ? `<div class="tip-row"><strong>How it works · </strong>Again repeats a card in the session. Good schedules the next review later. Easy moves it further ahead.</div>` : `<div class="tip-row">You're all caught up. Add new cards or come back when reviews are due.</div>`}</div>`, 'Review');
    return;
  }
  const cardId = session.queue[0];
  const card = cardId && getCard(cardId);
  if (!card) {
    const total = session.correct + session.again;
    setContent(`<div class="review-wrap"><div class="review-completed"><div class="big-emoji">✦</div><h2>Session complete!</h2><p>You finished your review round.<br>${session.correct} Good/Easy answers · ${session.again} Again ratings</p><button class="button button-primary" data-action="finish-review">Back to dashboard →</button></div></div>`, 'Review');
    return;
  }
  const deck = getDeck(card.deckId);
  const denom = Math.max(session.total,session.correct + session.again + session.queue.length);
  const progress = Math.round((session.correct + session.again) / Math.max(1,denom) * 100);
  setContent(`<div class="review-wrap"><div class="between"><div><p class="eyebrow">FOCUS SESSION</p><h1 class="page-heading">Keep the loop going.</h1></div><button class="button button-outline" data-action="end-review">End session</button></div>
    <div class="study-meta"><span>${escapeHTML(deck?.name || 'Review')}</span><span>${session.queue.length} remaining</span></div>
    <div class="progress-track" aria-label="Study session progress"><div class="progress-fill" style="width:${progress}%"></div></div>
    <button class="study-card ${session.flipped ? 'is-flipped' : ''}" data-action="flip" aria-label="${session.flipped?'Show question':'Reveal answer'}"><div class="study-overline">${session.flipped?'THE ANSWER':'THE QUESTION'}</div><div class="study-main">${escapeHTML(session.flipped?card.answer:card.question)}</div><div class="study-tap">${session.flipped?'Tap to see the question again':'Tap anywhere to reveal the answer'}</div></button>
    <div class="study-controls">${session.flipped ? `<button class="button button-danger" data-action="grade" data-grade="again">↻ Again</button><button class="button button-muted" data-action="grade" data-grade="good">✓ Good</button><button class="button button-primary" data-action="grade" data-grade="easy">✦ Easy</button>` : `<button class="button button-primary" data-action="flip">Reveal answer</button>`}</div>
    <p class="key-help">Keyboard shortcuts: Space = flip · 1 = Again · 2 = Good · 3 = Easy</p></div>`, 'Review');
}
function beginReview(deckId = 'all') {
  const queue = state.cards.filter(c => due(c) && (deckId==='all' || c.deckId===deckId)).map(c => c.id);
  if (!queue.length) { toast('No cards due in that deck right now.'); return; }
  session = {queue, flipped:false, correct:0, again:0, total:queue.length, repeated:{}};
  navigate('#review');
}
function gradeCard(grade) {
  if (!session?.flipped || !session.queue.length) return;
  const cardId = session.queue.shift();
  const card = getCard(cardId);
  if (!card) return;
  if (grade === 'again') {
    card.level = 0;
    card.dueAt = nowISO();
    session.again++;
    if (!session.repeated[cardId]) { session.queue.push(cardId); session.repeated[cardId] = 1; }
  } else {
    card.level = Math.min(7, Math.max(0,Number(card.level)||0) + (grade==='easy'?2:1));
    card.dueAt = new Date(Date.now() + INTERVALS[card.level] * DAY).toISOString();
    session.correct++;
  }
  card.reviews = (Number(card.reviews)||0) + 1;
  card.lastReviewedAt = nowISO();
  session.flipped = false;
  save();
  reviewPage();
}
function settingsPage() {
  setContent(`<p class="eyebrow">OWN YOUR LEARNING DATA</p><h1 class="page-heading">Settings & data</h1><p class="page-lead">Stay in control of your study library.</p>
    <section class="settings-card"><h2>Export a backup</h2><p>Download all your decks, cards, and review progress as a JSON file. Keep it somewhere safe, especially before clearing browser data.</p><button class="button button-primary" data-action="export">↓ Export JSON</button></section>
    <section class="settings-card"><h2>Import a backup</h2><p>Restore a previously exported BrainLoop JSON backup. Importing replaces the data currently stored in this browser.</p><label class="button button-muted" for="backupInput">↑ Choose JSON file</label><input class="hidden" type="file" id="backupInput" accept=".json,application/json"/><span class="field-hint">Supported: BrainLoop v1 JSON backups only.</span></section>
    <section class="settings-card"><h2>Start fresh</h2><p>Delete your locally saved decks and cards. This cannot be undone unless you've exported a backup.</p><button class="button button-danger" data-action="clear-all">Delete all local data</button></section>
    <p class="privacy-note"><strong>Privacy:</strong> This version stores information in your browser using localStorage. It does not create accounts, upload study content, or sync across devices. Private/incognito sessions or cleared site data may erase your progress. No AI API is connected yet.</p>`, 'Settings');
}
function render() {
  const r = route();
  if (r.page !== 'review') session = null;
  if (r.page === 'dashboard') dashboard();
  if (r.page === 'decks') decksPage();
  if (r.page === 'deck') deckPage(r.id);
  if (r.page === 'review') reviewPage();
  if (r.page === 'settings') settingsPage();
}
function modal(title, fields, onSave, label = 'Save') {
  lastFocus = document.activeElement;
  $('#modalRoot').innerHTML = `<div class="modal-screen" id="modalScreen"><div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle"><div class="modal-head"><h2 id="modalTitle">${escapeHTML(title)}</h2><button class="modal-close" data-action="close-modal" aria-label="Close dialog">×</button></div><form id="modalForm">${fields}<div class="modal-buttons"><button class="button button-quiet" type="button" data-action="close-modal">Cancel</button><button class="button button-primary" type="submit">${escapeHTML(label)}</button></div></form></div></div>`;
  const form = $('#modalForm');
  form.addEventListener('submit', (e) => { e.preventDefault(); const data = new FormData(form); onSave(data); });
  form.querySelector('input,textarea,select')?.focus();
}
function closeModal() { $('#modalRoot').innerHTML = ''; lastFocus?.focus?.(); }
function deckForm(existingId) {
  const existing = existingId ? getDeck(existingId) : null;
  if (existingId && !existing) return;
  modal(existing?'Edit deck':'Create a deck', `
    <div class="field-group"><label class="field-label" for="deckName">Deck name</label><input class="field-input" name="name" id="deckName" maxlength="90" required placeholder="e.g. Korean Vocabulary" value="${escapeHTML(existing?.name||'')}"></div>
    <div class="field-group"><label class="field-label" for="deckDescription">Description</label><textarea class="field-textarea" name="description" id="deckDescription" maxlength="250" placeholder="What will you learn?">${escapeHTML(existing?.description||'')}</textarea></div>
    <div class="field-group"><label class="field-label" for="deckColor">Theme</label><select class="field-select" name="color" id="deckColor">${COLORS.map(c => `<option value="${c}" ${existing?.color === c?'selected':''}>${c[0].toUpperCase()+c.slice(1)}</option>`).join('')}</select></div>`, (form) => {
      const name = String(form.get('name')||'').trim();
      const description = String(form.get('description')||'').trim();
      const color = String(form.get('color'));
      if (!name) return;
      if (existing) Object.assign(existing, {name,description,color});
      else state.decks.unshift({id:uid(),name,description,color,createdAt:nowISO()});
      save(); closeModal(); render(); toast(existing?'Deck updated.':'Deck created.');
    }, existing?'Save changes':'Create deck');
}
function cardForm(deckId, cardId) {
  const deck = getDeck(deckId);
  const existing = cardId ? getCard(cardId) : null;
  if (!deck || (cardId && !existing)) return;
  modal(existing?'Edit flashcard':'Add flashcard', `
    <div class="field-group"><label class="field-label" for="cardQuestion">Question / front</label><textarea class="field-textarea" name="question" id="cardQuestion" maxlength="700" required placeholder="What do you want to remember?">${escapeHTML(existing?.question||'')}</textarea></div>
    <div class="field-group"><label class="field-label" for="cardAnswer">Answer / back</label><textarea class="field-textarea" name="answer" id="cardAnswer" maxlength="1500" required placeholder="Write a clear, concise answer.">${escapeHTML(existing?.answer||'')}</textarea></div>`, (form) => {
      const question = String(form.get('question')||'').trim();
      const answer = String(form.get('answer')||'').trim();
      if (!question || !answer) return;
      if (existing) { existing.question = question; existing.answer = answer; }
      else state.cards.push({id:uid(),deckId,question,answer,level:0,dueAt:nowISO(),lastReviewedAt:null,reviews:0});
      save(); closeModal(); render(); toast(existing?'Flashcard updated.':'Flashcard added.');
    }, existing?'Save changes':'Add flashcard');
}
function deleteCard(cardId) {
  const card = getCard(cardId); if (!card) return;
  if (!confirm('Delete this flashcard? This action cannot be undone.')) return;
  state.cards = state.cards.filter(c => c.id!==cardId);
  save();render();toast('Flashcard deleted.');
}
function deleteDeck(deckId) {
  const deck = getDeck(deckId); if (!deck) return;
  if (!confirm(`Delete “${deck.name}” and all ${cardsFor(deckId).length} cards inside it? This cannot be undone.`)) return;
  state.decks = state.decks.filter(d => d.id!==deckId);
  state.cards = state.cards.filter(c => c.deckId!==deckId);
  save();navigate('#decks');toast('Deck deleted.');
}
function exportBackup() {
  const blob = new Blob([JSON.stringify(state, null, 2)],{type:'application/json'});
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a'); link.href=url;link.download=`brainloop-backup-${new Date().toISOString().slice(0,10)}.json`;
  document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);
  toast('Backup downloaded.');
}
function validateImport(data) {
  if (!data || data.version!==1 || !Array.isArray(data.decks) || !Array.isArray(data.cards)) throw Error('Not a BrainLoop v1 backup.');
  if (data.decks.length > 2000 || data.cards.length > 100000) throw Error('Backup is too large.');
  const ids = new Set();
  const decks = data.decks.map(d => {
    if (!d || typeof d.id !== 'string' || !d.id || typeof d.name !== 'string' || !d.name.trim() || d.name.length>90 || typeof (d.description||'')!=='string') throw Error('Invalid deck data.');
    if (ids.has(d.id)) throw Error('Duplicate deck IDs.'); ids.add(d.id);
    return {id:d.id, name:d.name, description:String(d.description||'').slice(0,250),color:COLORS.includes(d.color)?d.color:'violet',createdAt:typeof d.createdAt==='string'?d.createdAt:nowISO()};
  });
  const cardIds=new Set();
  const cards=data.cards.map(c => {
    if (!c || typeof c.id !== 'string' || !c.id || cardIds.has(c.id) || !ids.has(c.deckId) || typeof c.question!=='string' || typeof c.answer!=='string' || !c.question.trim() || !c.answer.trim() || c.question.length>700 || c.answer.length>1500) throw Error('Invalid flashcard data.');
    cardIds.add(c.id);
    return {id:c.id,deckId:c.deckId,question:c.question,answer:c.answer,level:Math.min(7,Math.max(0,Math.floor(Number(c.level)||0))),dueAt:typeof c.dueAt==='string' && !Number.isNaN(Date.parse(c.dueAt))?c.dueAt:nowISO(),lastReviewedAt:typeof c.lastReviewedAt==='string'?c.lastReviewedAt:null,reviews:Math.max(0,Math.floor(Number(c.reviews)||0))};
  });
  return {version:1,decks,cards};
}
async function importBackup(input) {
  const file=input.files?.[0]; if (!file) return;
  if (file.size > 12*1024*1024) { toast('Backup file is too large.');return; }
  try {
    const imported=validateImport(JSON.parse(await file.text()));
    if (!confirm(`Replace your current decks and cards with ${imported.decks.length} decks and ${imported.cards.length} cards from this backup?`)) return;
    state=imported;save();navigate('#dashboard');render();toast('Backup restored.');
  } catch(err) { toast(err.message || 'Could not import the backup.'); }
  finally { input.value=''; }
}

document.addEventListener('click', (event) => {
  const target=event.target.closest('[data-action]');
  if (!target) {
    if (event.target.id==='modalScreen') closeModal();
    return;
  }
  const action=target.dataset.action, id=target.dataset.id;
  if (action==='new-deck') deckForm();
  if (action==='edit-deck') deckForm(id);
  if (action==='new-card') cardForm(id);
  if (action==='edit-card') { const card=getCard(id); if (card) cardForm(card.deckId,id); }
  if (action==='delete-card') deleteCard(id);
  if (action==='delete-deck') deleteDeck(id);
  if (action==='open-deck') navigate(`#deck/${encodeURIComponent(id)}`);
  if (action==='go-decks') navigate('#decks');
  if (action==='go-review') navigate('#review');
  if (action==='start-deck') {session=null;beginReview(id);}
  if (action==='begin-review') beginReview($('#reviewDeck').value);
  if (action==='flip' && session) {session.flipped=!session.flipped;reviewPage();}
  if (action==='grade') gradeCard(target.dataset.grade);
  if (action==='end-review' || action==='finish-review') {session=null;navigate('#dashboard');}
  if (action==='close-modal') closeModal();
  if (action==='export') exportBackup();
  if (action==='clear-all' && confirm('Delete ALL locally saved BrainLoop decks, cards and progress? Export a backup first if you want to keep them.')) {state={version:1,decks:[],cards:[]};save();navigate('#dashboard');render();toast('Local data cleared.');}
});
document.addEventListener('input',(event) => {if(event.target.id==='deckSearch') {deckSearch=event.target.value;updateDeckSearch();}});
document.addEventListener('change',(event) => {if(event.target.id==='backupInput') importBackup(event.target);});
document.addEventListener('keydown',(event) => {
  if (event.key==='Escape' && $('#modalScreen')) {closeModal();return;}
  if ($('#modalScreen') || route().page!=='review' || !session || !session.queue.length || /INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName||'')) return;
  if (event.code==='Space') {event.preventDefault();session.flipped=!session.flipped;reviewPage();}
  if (session?.flipped && ['Digit1','Digit2','Digit3'].includes(event.code)) {event.preventDefault();gradeCard({Digit1:'again',Digit2:'good',Digit3:'easy'}[event.code]);}
});
window.addEventListener('hashchange', () => {render();window.scrollTo({top:0,behavior:'instant'});});
$('#menuToggle').addEventListener('click', () => openMobile(!$('#sidebar').classList.contains('is-open')));
$('#mobileOverlay').addEventListener('click', () => openMobile(false));
$('#year').textContent=new Date().getFullYear();
render();
