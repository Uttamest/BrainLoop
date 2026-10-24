
/*
 * BrainLoop — browser-based flashcard learning studio.
 * Local-first MVP: no external services, accounts, or cloud storage.
 */
'use strict';

/* -------------------------------------------------------------------------- */
/* Configuration                                                              */
/* -------------------------------------------------------------------------- */

const STORAGE_KEY = 'brainloop-v1';
const DAY = 24 * 60 * 60 * 1000;
const INTERVALS = [0, 1, 3, 7, 14, 30, 60, 120];
const COLORS = ['violet', 'blue', 'green', 'rose'];

const SYMBOLS = {
  violet: '✦',
  blue: '⌘',
  green: '✳',
  rose: '✿'
};

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const escapeHTML = (value) =>
  String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  })[character]);

const uid = () =>
  globalThis.crypto?.randomUUID?.() ||
  `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;

const nowISO = () => new Date().toISOString();

function due(card, now = Date.now()) {
  if (!card?.dueAt) return true;

  const dueTime = Date.parse(card.dueAt);
  return !Number.isFinite(dueTime) || dueTime <= now;
}

const cardsFor = (deckId) =>
  state.cards.filter((card) => card.deckId === deckId);

const getDeck = (deckId) =>
  state.decks.find((deck) => deck.id === deckId);

const getCard = (cardId) =>
  state.cards.find((card) => card.id === cardId);

function focusElement(element) {
  if (!element) return;

  try {
    element.focus({ preventScroll: true });
  } catch {
    element.focus?.();
  }
}

/* -------------------------------------------------------------------------- */
/* Data and storage                                                            */
/* -------------------------------------------------------------------------- */

let initialStorageMissing = false;
let initialStorageWarning = '';

function defaultData() {
  const decks = [
    {
      id: 'demo-web',
      name: 'Web Development Basics',
      description: 'A sample collection of foundational web concepts.',
      color: 'violet',
      createdAt: nowISO()
    },
    {
      id: 'demo-cs',
      name: 'Computer Science',
      description: 'Sample questions on common programming concepts.',
      color: 'blue',
      createdAt: nowISO()
    }
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

  return {
    version: 1,
    decks,
    cards: samples.map(([deckId, question, answer]) => ({
      id: uid(),
      deckId,
      question,
      answer,
      level: 0,
      dueAt: nowISO(),
      lastReviewedAt: null,
      reviews: 0
    }))
  };
}

function isPlainObject(value) {
  return value !== null &&
    typeof value === 'object' &&
    !Array.isArray(value);
}

/**
 * Validate and normalize a BrainLoop backup.
 * Both imported backups and stored data use the same schema.
 */
function validateImport(data) {
  if (
    !isPlainObject(data) ||
    data.version !== 1 ||
    !Array.isArray(data.decks) ||
    !Array.isArray(data.cards)
  ) {
    throw new Error('This is not a valid BrainLoop v1 backup.');
  }

  if (data.decks.length > 2000 || data.cards.length > 100000) {
    throw new Error('This backup contains too much data to import.');
  }

  const deckIds = new Set();

  const decks = data.decks.map((deck) => {
    if (
      !isPlainObject(deck) ||
      typeof deck.id !== 'string' ||
      !deck.id.trim() ||
      deck.id.length > 200 ||
      deckIds.has(deck.id) ||
      typeof deck.name !== 'string' ||
      !deck.name.trim() ||
      deck.name.length > 90 ||
      (deck.description != null &&
        typeof deck.description !== 'string')
    ) {
      throw new Error('The backup contains invalid deck data.');
    }

    deckIds.add(deck.id);

    const createdAt =
      typeof deck.createdAt === 'string' &&
      Number.isFinite(Date.parse(deck.createdAt))
        ? deck.createdAt
        : nowISO();

    return {
      id: deck.id,
      name: deck.name.trim(),
      description: String(deck.description ?? '').slice(0, 250),
      color: COLORS.includes(deck.color) ? deck.color : 'violet',
      createdAt
    };
  });

  const cardIds = new Set();

  const cards = data.cards.map((card) => {
    if (
      !isPlainObject(card) ||
      typeof card.id !== 'string' ||
      !card.id.trim() ||
      card.id.length > 200 ||
      cardIds.has(card.id) ||
      typeof card.deckId !== 'string' ||
      !deckIds.has(card.deckId) ||
      typeof card.question !== 'string' ||
      typeof card.answer !== 'string' ||
      !card.question.trim() ||
      !card.answer.trim() ||
      card.question.length > 700 ||
      card.answer.length > 1500
    ) {
      throw new Error('The backup contains invalid flashcard data.');
    }

    cardIds.add(card.id);

    const parsedLevel = Number(card.level);
    const parsedReviews = Number(card.reviews);

    const validLevel = Number.isFinite(parsedLevel)
      ? Math.floor(parsedLevel)
      : 0;

    const validReviews = Number.isFinite(parsedReviews)
      ? Math.floor(parsedReviews)
      : 0;

    const dueAt =
      typeof card.dueAt === 'string' &&
      Number.isFinite(Date.parse(card.dueAt))
        ? card.dueAt
        : nowISO();

    const lastReviewedAt =
      typeof card.lastReviewedAt === 'string' &&
      Number.isFinite(Date.parse(card.lastReviewedAt))
        ? card.lastReviewedAt
        : null;

    return {
      id: card.id,
      deckId: card.deckId,
      question: card.question,
      answer: card.answer,
      level: Math.min(7, Math.max(0, validLevel)),
      dueAt,
      lastReviewedAt,
      reviews: Math.min(
        1000000000,
        Math.max(0, validReviews)
      )
    };
  });

  return { version: 1, decks, cards };
}

function readState() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);

    if (stored === null) {
      initialStorageMissing = true;
      return defaultData();
    }

    try {
      return validateImport(JSON.parse(stored));
    } catch (error) {
      console.warn('Unable to read saved BrainLoop data:', error);

      initialStorageWarning =
        'Saved data could not be read. Restore a backup if you have one.';

      return defaultData();
    }
  } catch (error) {
    console.warn('Browser storage is unavailable:', error);

    initialStorageWarning =
      'Browser storage is unavailable. Your changes may not persist after you close this page.';

    return defaultData();
  }
}

let state = readState();
let deckSearch = '';
let session = null;
let lastFocus = null;
let toastTimerIds = new Set();

function updateStorageStatus(saved, message = '') {
  const status = $('.data-status');
  if (!status) return;

  const label = status.lastElementChild;
  const dot = $('.status-dot', status);

  if (label) {
    label.textContent = saved
      ? 'Saved locally'
      : (message || 'Unsaved changes');
  }

  status.title = saved
    ? 'Your study data is saved in this browser.'
    : 'Some changes may not have been saved in this browser.';

  status.setAttribute(
    'aria-label',
    saved
      ? 'Storage status: saved locally in this browser'
      : `Storage status: ${message || 'unsaved changes'}`
  );

  if (dot) {
    dot.style.backgroundColor = saved ? '' : 'var(--red)';
    dot.style.boxShadow = saved ? '' : 'none';
  }
}

function save({ notifyOnError = true } = {}) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    updateStorageStatus(true);
    return true;
  } catch (error) {
    console.warn('BrainLoop data could not be saved:', error);
    updateStorageStatus(false);

    if (notifyOnError) {
      toast(
        'Unable to save changes in this browser. Export a backup before leaving.'
      );
    }

    return false;
  }
}

if (
  initialStorageMissing &&
  !save({ notifyOnError: false })
) {
  initialStorageWarning =
    'Browser storage is unavailable. Your changes may not persist after you close this page.';
}

/* -------------------------------------------------------------------------- */
/* Shared interface helpers                                                    */
/* -------------------------------------------------------------------------- */

function toast(message) {
  const container = $('#toastContainer');
  if (!container) return;

  const element = document.createElement('div');

  element.className = 'toast';
  element.setAttribute('role', 'status');
  element.textContent = String(message);

  container.append(element);

  const timerId = window.setTimeout(() => {
    element.remove();
    toastTimerIds.delete(timerId);
  }, 3500);

  toastTimerIds.add(timerId);
}

function openMobile(
  open,
  { restoreFocus = false, focusNavigation = false } = {}
) {
  const sidebar = $('#sidebar');
  const overlay = $('#mobileOverlay');
  const toggle = $('#menuToggle');

  if (!sidebar || !overlay || !toggle) return;

  sidebar.classList.toggle('is-open', open);
  overlay.hidden = !open;
  toggle.setAttribute('aria-expanded', String(open));

  if (open && focusNavigation) {
    focusElement($('.nav-link', sidebar));
  } else if (!open && restoreFocus) {
    focusElement(toggle);
  }
}

function route() {
  let hash = location.hash.replace(/^#/, '');

  try {
    hash = decodeURIComponent(hash);
  } catch {
    return { page: 'dashboard' };
  }

  if (hash.startsWith('deck/')) {
    return {
      page: 'deck',
      id: hash.slice('deck/'.length)
    };
  }

  const validPages = [
    'dashboard',
    'decks',
    'review',
    'settings'
  ];

  return {
    page: validPages.includes(hash) ? hash : 'dashboard'
  };
}

function navigate(hash) {
  if (location.hash === hash) {
    render();
    focusElement($('#appContent'));
    return;
  }

  location.hash = hash;
}

function setContent(html, name) {
  const content = $('#appContent');
  if (content) content.innerHTML = html;

  const breadcrumb = $('#currentCrumb');
  if (breadcrumb) breadcrumb.textContent = name;

  const deckCount = $('#sidebarDeckCount');
  if (deckCount) {
    deckCount.textContent = String(state.decks.length);
  }

  const currentPage = route().page;
  const activeRoute = currentPage === 'deck'
    ? 'decks'
    : currentPage;

  $$('.nav-link').forEach((link) => {
    const isActive = link.dataset.route === activeRoute;

    link.classList.toggle('active', isActive);

    if (isActive) {
      link.setAttribute('aria-current', 'page');
    } else {
      link.removeAttribute('aria-current');
    }
  });

  openMobile(false);
}

function deckCard(deck) {
  const cards = cardsFor(deck.id);

  const mastered = cards.filter(
    (card) => card.level >= 3
  ).length;

  const progress = cards.length
    ? Math.round((mastered / cards.length) * 100)
    : 0;

  const dueCount = cards.filter(due).length;

  const color = COLORS.includes(deck.color)
    ? deck.color
    : 'violet';

  const safeId = escapeHTML(deck.id);

  return `
    <article class="deck-card">
      <div class="deck-top">
        <span class="deck-art ${color}" aria-hidden="true">
          ${SYMBOLS[color]}
        </span>
        <span class="tag">${dueCount} due now</span>
      </div>

      <div>
        <h3>${escapeHTML(deck.name)}</h3>
        <p class="deck-description">
          ${escapeHTML(deck.description || 'Your custom learning deck.')}
        </p>
      </div>

      <div>
        <div class="deck-meta">
          <span>${cards.length} card${cards.length === 1 ? '' : 's'}</span>
          <span>${progress}% mastered</span>
        </div>

        <div
          class="progress-track"
          role="progressbar"
          aria-label="Mastered flashcards"
          aria-valuemin="0"
          aria-valuemax="100"
          aria-valuenow="${progress}"
        >
          <div class="progress-fill" style="width:${progress}%"></div>
        </div>
      </div>

      <div class="deck-actions">
        <button
          class="button button-muted"
          type="button"
          data-action="open-deck"
          data-id="${safeId}"
        >
          View cards <span aria-hidden="true">→</span>
        </button>

        <button
          class="button button-outline"
          type="button"
          data-action="start-deck"
          data-id="${safeId}"
          ${dueCount ? '' : 'disabled'}
        >
          Review due (${dueCount})
        </button>
      </div>
    </article>`;
}

/* -------------------------------------------------------------------------- */
/* Dashboard and deck views                                                    */
/* -------------------------------------------------------------------------- */

function dashboard() {
  const total = state.cards.length;
  const ready = state.cards.filter(due).length;

  const mastered = state.cards.filter(
    (card) => card.level >= 3
  ).length;

  const empty = state.decks.length === 0;
  const featured = state.decks.slice(0, 4);

  setContent(`
    <div class="welcome-title">
      <p class="eyebrow">WELCOME TO YOUR LEARNING SPACE</p>
      <h1 class="page-heading">
        Make learning stick<span style="color:#a99dff">.</span>
      </h1>
      <p class="page-lead">
        Build your knowledge, one flashcard at a time.
      </p>
    </div>

    <section class="hero" aria-labelledby="heroHeading">
      <div>
        <div class="eyebrow">✦ YOUR DAILY STUDY ROUTINE</div>

        <h2 id="heroHeading">
          Small sessions.<br>Big breakthroughs.
        </h2>

        <p>
          Practice active recall and revisit cards at increasing
          intervals as you remember them.
        </p>

        <button
          class="button button-primary"
          type="button"
          data-action="go-review"
        >
          Start reviewing <span aria-hidden="true">↗</span>
        </button>
      </div>

      <div class="hero-symbol" aria-hidden="true">✳</div>
    </section>

    <section class="stats" aria-label="Study statistics">
      <div class="stat">
        <div class="stat-label">
          Total flashcards
          <span class="stat-icon" aria-hidden="true">▤</span>
        </div>
        <div class="stat-value">${total}</div>
        <div class="stat-sub">
          Across ${state.decks.length}
          deck${state.decks.length === 1 ? '' : 's'}
        </div>
      </div>

      <div class="stat">
        <div class="stat-label">
          Due for review
          <span class="stat-icon" aria-hidden="true">◷</span>
        </div>
        <div class="stat-value">${ready}</div>
        <div class="stat-sub">Ready when you are</div>
      </div>

      <div class="stat">
        <div class="stat-label">
          Mastered cards
          <span class="stat-icon" aria-hidden="true">✦</span>
        </div>
        <div class="stat-value">${mastered}</div>
        <div class="stat-sub">Reached review level 3+</div>
      </div>
    </section>

    <section aria-labelledby="decksHeading">
      <div class="section-heading">
        <h2 id="decksHeading">Your study decks</h2>
        <button
          class="button-link"
          type="button"
          data-action="go-decks"
        >
          View all decks →
        </button>
      </div>

      ${
        empty
          ? emptyDeck()
          : `<div class="deck-grid">${featured.map(deckCard).join('')}</div>`
      }
    </section>

    <div class="tip-row">
      <strong>Study tip · </strong>
      Try recalling the answer before flipping a card. Retrieval practice
      works best when you challenge your memory first.
    </div>
  `, 'Dashboard');
}

function emptyDeck() {
  return `
    <div class="empty">
      <div class="empty-icon" aria-hidden="true">✦</div>
      <h3>No decks yet</h3>
      <p>
        Create a deck to start collecting things you want to remember.
      </p>
      <button
        class="button button-primary"
        type="button"
        data-action="new-deck"
      >
        Create your first deck
      </button>
    </div>`;
}

function decksPage() {
  setContent(`
    <div class="between">
      <div>
        <p class="eyebrow">YOUR KNOWLEDGE LIBRARY</p>
        <h1 class="page-heading">My decks</h1>
        <p class="page-lead">
          Keep your ideas organized in one place.
        </p>
      </div>

      <button
        class="button button-primary"
        type="button"
        data-action="new-deck"
      >
        ＋ Create deck
      </button>
    </div>

    <div class="controls-row">
      <input
        class="search-input"
        id="deckSearch"
        type="search"
        placeholder="Search decks..."
        aria-label="Search decks"
        autocomplete="off"
        value="${escapeHTML(deckSearch)}"
      >

      <span
        class="muted"
        id="deckListCount"
        style="font-size:12px"
        aria-live="polite"
      ></span>
    </div>

    <div class="deck-grid" id="deckList"></div>

    <div class="tip-row">
      <strong>New here? · </strong>
      The sample decks are editable. Delete them whenever you're ready
      to make BrainLoop your own.
    </div>
  `, 'My decks');

  updateDeckSearch();
}

function updateDeckSearch() {
  const list = $('#deckList');
  const count = $('#deckListCount');

  if (!list || !count) return;

  const query = deckSearch.trim().toLocaleLowerCase();

  const filtered = state.decks.filter((deck) =>
    `${deck.name} ${deck.description || ''}`
      .toLocaleLowerCase()
      .includes(query)
  );

  list.innerHTML = filtered.length
    ? filtered.map(deckCard).join('')
    : state.decks.length
      ? `
        <div class="empty" style="grid-column:1/-1">
          <h3>No matching decks</h3>
          <p>Try a different search.</p>
        </div>
      `
      : emptyDeck();

  count.textContent = `${filtered.length} of ${state.decks.length} decks`;
}

function deckPage(deckId) {
  const deck = getDeck(deckId);

  if (!deck) {
    navigate('#decks');
    return;
  }

  const cards = cardsFor(deckId);

  const color = COLORS.includes(deck.color)
    ? deck.color
    : 'violet';

  const safeDeckId = escapeHTML(deckId);

  setContent(`
    <a class="back-link" href="#decks">← Back to all decks</a>

    <div class="details-head">
      <div class="deck-art ${color}" aria-hidden="true">
        ${SYMBOLS[color]}
      </div>

      <div>
        <h1>${escapeHTML(deck.name)}</h1>
        <p>
          ${escapeHTML(deck.description || 'Personal study collection')}
          · ${cards.length} flashcards
        </p>
      </div>
    </div>

    <div class="detail-toolbar">
      <div class="section-heading" style="margin:0">
        <h2>Flashcards</h2>
        <span class="tag">${cards.filter(due).length} due for review</span>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button
          class="button button-outline"
          type="button"
          data-action="edit-deck"
          data-id="${safeDeckId}"
        >
          Edit deck
        </button>

        <button
          class="button button-muted"
          type="button"
          data-action="start-deck"
          data-id="${safeDeckId}"
          ${cards.some(due) ? '' : 'disabled'}
        >
          Study due cards
        </button>

        <button
          class="button button-primary"
          type="button"
          data-action="new-card"
          data-id="${safeDeckId}"
        >
          ＋ Add card
        </button>
      </div>
    </div>

    <div class="card-list">
      ${
        cards.length
          ? cards.map((card, index) => `
            <article class="flashcard-item">
              <span
                class="flashcard-number"
                aria-label="Flashcard ${index + 1}"
              >
                ${index + 1}
              </span>

              <div>
                <div class="flashcard-question">
                  ${escapeHTML(card.question)}
                </div>

                <div class="flashcard-answer">
                  ${escapeHTML(card.answer)}
                </div>

                <div style="margin-top:8px">
                  <span class="tag">
                    ${
                      card.level >= 3
                        ? 'Mastered'
                        : card.level > 0
                          ? 'Learning'
                          : 'New'
                    }
                  </span>
                </div>
              </div>

              <div class="card-item-actions">
                <button
                  class="tiny-btn"
                  type="button"
                  aria-label="Edit flashcard ${index + 1}"
                  data-action="edit-card"
                  data-id="${escapeHTML(card.id)}"
                >
                  Edit
                </button>

                <button
                  class="tiny-btn"
                  type="button"
                  aria-label="Delete flashcard ${index + 1}"
                  data-action="delete-card"
                  data-id="${escapeHTML(card.id)}"
                >
                  Delete
                </button>
              </div>
            </article>
          `).join('')
          : `
            <div class="empty">
              <div class="empty-icon" aria-hidden="true">▤</div>
              <h3>This deck is empty</h3>
              <p>Add your first question and answer.</p>

              <button
                class="button button-primary"
                type="button"
                data-action="new-card"
                data-id="${safeDeckId}"
              >
                Add a flashcard
              </button>
            </div>
          `
      }
    </div>

    <div style="margin-top:30px">
      <button
        class="button button-danger"
        type="button"
        data-action="delete-deck"
        data-id="${safeDeckId}"
      >
        Delete this deck
      </button>
    </div>
  `, deck.name);
}

/* -------------------------------------------------------------------------- */
/* Review sessions                                                             */
/* -------------------------------------------------------------------------- */

function reviewPage() {
  if (session) {
    // Ignore cards deleted after this session began.
    while (
      session.queue.length &&
      !getCard(session.queue[0])
    ) {
      session.queue.shift();
    }
  }

  const ready = state.cards.filter(due).length;

  if (!session) {
    setContent(`
      <div class="review-wrap">
        <p class="eyebrow">ACTIVE RECALL MODE</p>
        <h1 class="page-heading">Review your flashcards</h1>
        <p class="page-lead">
          Think of the answer first, then reveal it and grade your recall.
        </p>

        <div class="stats" style="grid-template-columns:repeat(2,minmax(0,1fr))">
          <div class="stat">
            <div class="stat-label">Due now</div>
            <div class="stat-value">${ready}</div>
            <div class="stat-sub">Cards ready to practice</div>
          </div>

          <div class="stat">
            <div class="stat-label">Total decks</div>
            <div class="stat-value">${state.decks.length}</div>
            <div class="stat-sub">Your learning collections</div>
          </div>
        </div>

        <label class="field-label" for="reviewDeck">
          Select a deck
        </label>

        <select class="field-select review-select" id="reviewDeck">
          <option value="all">All decks (${ready} due)</option>
          ${
            state.decks.map((deck) => `
              <option value="${escapeHTML(deck.id)}">
                ${escapeHTML(deck.name)}
                (${cardsFor(deck.id).filter(due).length} due)
              </option>
            `).join('')
          }
        </select>

        <div style="margin-top:22px">
          <button
            class="button button-primary"
            type="button"
            data-action="begin-review"
            ${ready ? '' : 'disabled'}
          >
            Start study session →
          </button>
        </div>

        ${
          ready
            ? `
              <div class="tip-row">
                <strong>How it works · </strong>
                Again repeats a card in the session. Good schedules the next
                review later. Easy moves it further ahead.
              </div>
            `
            : `
              <div class="tip-row">
                You're all caught up. Add new cards or come back when
                reviews are due.
              </div>
            `
        }
      </div>
    `, 'Review');

    return;
  }

  const cardId = session.queue[0];
  const card = cardId ? getCard(cardId) : null;

  if (!card) {
    setContent(`
      <div class="review-wrap">
        <div class="review-completed">
          <div class="big-emoji" aria-hidden="true">✦</div>
          <h2>Session complete!</h2>

          <p>
            You finished your review round.<br>
            ${session.correct} Good/Easy answers ·
            ${session.again} Again ratings
          </p>

          <button
            class="button button-primary"
            type="button"
            data-action="finish-review"
          >
            Back to dashboard →
          </button>
        </div>
      </div>
    `, 'Review');

    return;
  }

  const deck = getDeck(card.deckId);

  const completedRatings = session.correct + session.again;
  const denominator = Math.max(
    session.total,
    completedRatings + session.queue.length
  );

  const progress = Math.min(
    100,
    Math.round(
      completedRatings / Math.max(1, denominator) * 100
    )
  );

  setContent(`
    <div class="review-wrap">
      <div class="between">
        <div>
          <p class="eyebrow">FOCUS SESSION</p>
          <h1 class="page-heading">Keep the loop going.</h1>
        </div>

        <button
          class="button button-outline"
          type="button"
          data-action="end-review"
        >
          End session
        </button>
      </div>

      <div class="study-meta">
        <span>${escapeHTML(deck?.name || 'Review')}</span>
        <span>${session.queue.length} remaining</span>
      </div>

      <div
        class="progress-track"
        role="progressbar"
        aria-label="Study session progress"
        aria-valuemin="0"
        aria-valuemax="100"
        aria-valuenow="${progress}"
      >
        <div class="progress-fill" style="width:${progress}%"></div>
      </div>

      <button
        class="study-card ${session.flipped ? 'is-flipped' : ''}"
        type="button"
        data-action="flip"
        aria-label="${session.flipped ? 'Show question' : 'Reveal answer'}"
        aria-pressed="${session.flipped ? 'true' : 'false'}"
      >
        <div class="study-overline">
          ${session.flipped ? 'THE ANSWER' : 'THE QUESTION'}
        </div>

        <div class="study-main">
          ${escapeHTML(session.flipped ? card.answer : card.question)}
        </div>

        <div class="study-tap">
          ${
            session.flipped
              ? 'Tap to see the question again'
              : 'Tap anywhere to reveal the answer'
          }
        </div>
      </button>

      <div class="study-controls">
        ${
          session.flipped
            ? `
              <button class="button button-danger" type="button"
                data-action="grade" data-grade="again">
                ↻ Again
              </button>

              <button class="button button-muted" type="button"
                data-action="grade" data-grade="good">
                ✓ Good
              </button>

              <button class="button button-primary" type="button"
                data-action="grade" data-grade="easy">
                ✦ Easy
              </button>
            `
            : `
              <button
                class="button button-primary"
                type="button"
                data-action="flip"
              >
                Reveal answer
              </button>
            `
        }
      </div>

      <p class="key-help">
        Keyboard shortcuts: Space = flip · 1 = Again · 2 = Good · 3 = Easy
      </p>
    </div>
  `, 'Review');
}

function beginReview(deckId = 'all') {
  const queue = state.cards
    .filter((card) =>
      due(card) &&
      (deckId === 'all' || card.deckId === deckId)
    )
    .map((card) => card.id);

  if (!queue.length) {
    toast('No cards are due in that selection right now.');
    return;
  }

  session = {
    queue,
    flipped: false,
    correct: 0,
    again: 0,
    total: queue.length,
    repeated: Object.create(null)
  };

  navigate('#review');
}

function toggleStudyCard() {
  if (!session || !session.queue.length) return;

  const activeElement = document.activeElement;

  const wasStudyCard = activeElement?.matches?.('.study-card');

  const wasRevealButton =
    activeElement?.matches?.('[data-action="flip"]') &&
    !wasStudyCard;

  const wasNonInteractive = !activeElement?.matches?.(
    'button, a[href], input, textarea, select, [contenteditable="true"]'
  );

  session.flipped = !session.flipped;
  reviewPage();

  if (wasStudyCard || wasNonInteractive) {
    focusElement($('.study-card'));
  } else if (wasRevealButton) {
    focusElement($('[data-action="grade"]'));
  }
}

function gradeCard(grade) {
  if (!session?.flipped || !session.queue.length) return;

  if (!['again', 'good', 'easy'].includes(grade)) return;

  const cardId = session.queue.shift();
  const card = getCard(cardId);

  if (!card) {
    reviewPage();
    return;
  }

  if (grade === 'again') {
    card.level = 0;
    card.dueAt = nowISO();
    session.again += 1;

    // Retry a missed card once to avoid endless loops.
    if (!session.repeated[cardId]) {
      session.queue.push(cardId);
      session.repeated[cardId] = 1;
    }
  } else {
    const increment = grade === 'easy' ? 2 : 1;

    card.level = Math.min(
      7,
      Math.max(0, Number(card.level) || 0) + increment
    );

    card.dueAt = new Date(
      Date.now() + INTERVALS[card.level] * DAY
    ).toISOString();

    session.correct += 1;
  }

  card.reviews = Math.min(
    1000000000,
    (Number(card.reviews) || 0) + 1
  );

  card.lastReviewedAt = nowISO();
  session.flipped = false;

  save();
  reviewPage();

  focusElement(
    $('.study-card') || $('[data-action="finish-review"]')
  );
}

/* -------------------------------------------------------------------------- */
/* Settings                                                                    */
/* -------------------------------------------------------------------------- */

function settingsPage() {
  setContent(`
    <p class="eyebrow">OWN YOUR LEARNING DATA</p>
    <h1 class="page-heading">Settings &amp; data</h1>
    <p class="page-lead">Stay in control of your study library.</p>

    <section class="settings-card">
      <h2>Export a backup</h2>
      <p>
        Download all your decks, cards, and review progress as a JSON file.
        Keep it somewhere safe, especially before clearing browser data.
      </p>
      <button class="button button-primary" type="button" data-action="export">
        ↓ Export JSON
      </button>
    </section>

    <section class="settings-card">
      <h2>Import a backup</h2>
      <p>
        Restore a previously exported BrainLoop JSON backup. Importing
        replaces the data currently stored in this browser.
      </p>

      <label class="button button-muted" for="backupInput">
        ↑ Choose JSON file
      </label>

      <input
        class="hidden"
        type="file"
        id="backupInput"
        accept=".json,application/json"
      >

      <span class="field-hint">
        Supported: BrainLoop v1 JSON backups only.
      </span>
    </section>

    <section class="settings-card">
      <h2>Start fresh</h2>
      <p>
        Delete your locally saved decks and cards. This cannot be undone
        unless you've exported a backup.
      </p>
      <button class="button button-danger" type="button" data-action="clear-all">
        Delete all local data
      </button>
    </section>

    <p class="privacy-note">
      <strong>Privacy:</strong> This version stores information in your browser
      using localStorage. It does not create accounts, upload study content,
      or sync across devices. Private/incognito sessions or cleared site data
      may erase your progress. No AI API is connected yet.
    </p>
  `, 'Settings');
}

function render() {
  const currentRoute = route();

  if (currentRoute.page !== 'review') {
    session = null;
  }

  switch (currentRoute.page) {
    case 'decks':
      decksPage();
      break;

    case 'deck':
      deckPage(currentRoute.id);
      break;

    case 'review':
      reviewPage();
      break;

    case 'settings':
      settingsPage();
      break;

    case 'dashboard':
    default:
      dashboard();
      break;
  }
}

/* -------------------------------------------------------------------------- */
/* Accessible modal forms                                                      */
/* -------------------------------------------------------------------------- */

function modal(title, fields, onSave, label = 'Save') {
  lastFocus = document.activeElement;

  const root = $('#modalRoot');
  if (!root) return;

  root.innerHTML = `
    <div class="modal-screen" id="modalScreen">
      <div
        class="modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalTitle"
        tabindex="-1"
      >
        <div class="modal-head">
          <h2 id="modalTitle">${escapeHTML(title)}</h2>

          <button
            class="modal-close"
            type="button"
            data-action="close-modal"
            aria-label="Close dialog"
          >
            ×
          </button>
        </div>

        <form id="modalForm" novalidate>
          ${fields}

          <div class="modal-buttons">
            <button
              class="button button-quiet"
              type="button"
              data-action="close-modal"
            >
              Cancel
            </button>

            <button class="button button-primary" type="submit">
              ${escapeHTML(label)}
            </button>
          </div>
        </form>
      </div>
    </div>
  `;

  const form = $('#modalForm', root);
  const dialog = $('.modal', root);

  const firstField = $(
    'input:not([type="hidden"]), textarea, select',
    form
  );

  form?.addEventListener('submit', (event) => {
    event.preventDefault();

    if (!form.reportValidity()) return;

    const data = new FormData(form);
    onSave(data);
  });

  focusElement(firstField || dialog);
}

function closeModal() {
  const root = $('#modalRoot');
  if (root) root.innerHTML = '';

  if (lastFocus?.isConnected) {
    focusElement(lastFocus);
  }

  lastFocus = null;
}

function trapModalFocus(event) {
  const dialog = $('#modalScreen .modal');
  if (!dialog || event.key !== 'Tab') return;

  const focusable = $$(
    'button:not(:disabled), [href], input:not(:disabled):not([type="hidden"]), textarea:not(:disabled), select:not(:disabled), [tabindex]:not([tabindex="-1"])',
    dialog
  ).filter((element) => element.getClientRects().length > 0);

  if (!focusable.length) {
    event.preventDefault();
    focusElement(dialog);
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (
    event.shiftKey &&
    (
      document.activeElement === first ||
      !dialog.contains(document.activeElement)
    )
  ) {
    event.preventDefault();
    focusElement(last);
  } else if (
    !event.shiftKey &&
    (
      document.activeElement === last ||
      !dialog.contains(document.activeElement)
    )
  ) {
    event.preventDefault();
    focusElement(first);
  }
}

function deckForm(existingId) {
  const existing = existingId ? getDeck(existingId) : null;
  if (existingId && !existing) return;

  modal(existing ? 'Edit deck' : 'Create a deck', `
    <div class="field-group">
      <label class="field-label" for="deckName">Deck name</label>
      <input
        class="field-input"
        name="name"
        id="deckName"
        maxlength="90"
        required
        placeholder="e.g. Korean Vocabulary"
        value="${escapeHTML(existing?.name || '')}"
      >
    </div>

    <div class="field-group">
      <label class="field-label" for="deckDescription">Description</label>
      <textarea
        class="field-textarea"
        name="description"
        id="deckDescription"
        maxlength="250"
        placeholder="What will you learn?"
      >${escapeHTML(existing?.description || '')}</textarea>
    </div>

    <div class="field-group">
      <label class="field-label" for="deckColor">Theme</label>
      <select class="field-select" name="color" id="deckColor">
        ${
          COLORS.map((color) => `
            <option
              value="${color}"
              ${existing?.color === color ? 'selected' : ''}
            >
              ${color[0].toUpperCase() + color.slice(1)}
            </option>
          `).join('')
        }
      </select>
    </div>
  `, (formData) => {
    const name = String(formData.get('name') || '').trim();

    const description = String(
      formData.get('description') || ''
    ).trim();

    const selectedColor = String(
      formData.get('color') || 'violet'
    );

    const color = COLORS.includes(selectedColor)
      ? selectedColor
      : 'violet';

    if (!name) {
      toast('Enter a name for this deck.');
      $('#deckName')?.focus();
      return;
    }

    if (existing) {
      Object.assign(existing, { name, description, color });
    } else {
      state.decks.unshift({
        id: uid(),
        name,
        description,
        color,
        createdAt: nowISO()
      });
    }

    const saved = save();

    closeModal();
    render();

    if (saved) {
      toast(existing ? 'Deck updated.' : 'Deck created.');
    }
  }, existing ? 'Save changes' : 'Create deck');
}

function cardForm(deckId, cardId) {
  const deck = getDeck(deckId);
  const existing = cardId ? getCard(cardId) : null;

  if (!deck || (cardId && !existing)) return;

  modal(existing ? 'Edit flashcard' : 'Add flashcard', `
    <div class="field-group">
      <label class="field-label" for="cardQuestion">
        Question / front
      </label>

      <textarea
        class="field-textarea"
        name="question"
        id="cardQuestion"
        maxlength="700"
        required
        placeholder="What do you want to remember?"
      >${escapeHTML(existing?.question || '')}</textarea>
    </div>

    <div class="field-group">
      <label class="field-label" for="cardAnswer">
        Answer / back
      </label>

      <textarea
        class="field-textarea"
        name="answer"
        id="cardAnswer"
        maxlength="1500"
        required
        placeholder="Write a clear, concise answer."
      >${escapeHTML(existing?.answer || '')}</textarea>
    </div>
  `, (formData) => {
    const question = String(
      formData.get('question') || ''
    ).trim();

    const answer = String(
      formData.get('answer') || ''
    ).trim();

    if (!question || !answer) {
      toast('Enter both a question and an answer.');
      return;
    }

    if (existing) {
      existing.question = question;
      existing.answer = answer;
    } else {
      state.cards.push({
        id: uid(),
        deckId,
        question,
        answer,
        level: 0,
        dueAt: nowISO(),
        lastReviewedAt: null,
        reviews: 0
      });
    }

    const saved = save();

    closeModal();
    render();

    if (saved) {
      toast(existing ? 'Flashcard updated.' : 'Flashcard added.');
    }
  }, existing ? 'Save changes' : 'Add flashcard');
}

/* -------------------------------------------------------------------------- */
/* Deck and card actions                                                       */
/* -------------------------------------------------------------------------- */

function deleteCard(cardId) {
  const card = getCard(cardId);
  if (!card) return;

  if (!confirm('Delete this flashcard? This action cannot be undone.')) {
    return;
  }

  state.cards = state.cards.filter(
    (item) => item.id !== cardId
  );

  if (session) {
    session.queue = session.queue.filter((id) => id !== cardId);
  }

  const saved = save();
  render();

  if (saved) toast('Flashcard deleted.');
}

function deleteDeck(deckId) {
  const deck = getDeck(deckId);
  if (!deck) return;

  const deckCardCount = cardsFor(deckId).length;

  if (
    !confirm(
      `Delete “${deck.name}” and all ${deckCardCount} cards inside it? This cannot be undone.`
    )
  ) {
    return;
  }

  state.decks = state.decks.filter(
    (item) => item.id !== deckId
  );

  state.cards = state.cards.filter(
    (card) => card.deckId !== deckId
  );

  if (session) {
    session.queue = session.queue.filter(
      (id) => getCard(id)
    );
  }

  const saved = save();

  navigate('#decks');

  if (saved) toast('Deck deleted.');
}

function exportBackup() {
  try {
    const blob = new Blob(
      [JSON.stringify(state, null, 2)],
      { type: 'application/json' }
    );

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download =
      `brainloop-backup-${new Date().toISOString().slice(0, 10)}.json`;

    link.style.display = 'none';

    document.body.append(link);
    link.click();
    link.remove();

    window.setTimeout(() => URL.revokeObjectURL(url), 1500);

    toast('Backup downloaded.');
  } catch (error) {
    console.error('Unable to export BrainLoop backup:', error);
    toast('The backup could not be downloaded in this browser.');
  }
}

/* -------------------------------------------------------------------------- */
/* Backup import                                                               */
/* -------------------------------------------------------------------------- */

async function importBackup(input) {
  const file = input.files?.[0];
  if (!file) return;

  if (file.size > 12 * 1024 * 1024) {
    toast('Backup file is too large. Choose a file under 12 MB.');
    input.value = '';
    return;
  }

  try {
    const parsed = JSON.parse(await file.text());
    const imported = validateImport(parsed);

    const confirmed = confirm(
      `Replace your current decks and cards with ${imported.decks.length} decks and ${imported.cards.length} cards from this backup?`
    );

    if (!confirmed) return;

    state = imported;
    session = null;
    deckSearch = '';

    const saved = save({ notifyOnError: false });

    navigate('#dashboard');

    if (saved) {
      toast('Backup restored.');
    } else {
      toast(
        'Backup loaded for this session, but it could not be saved locally. Export a copy before leaving.'
      );
    }
  } catch (error) {
    console.warn('Unable to import BrainLoop backup:', error);

    const message = error instanceof SyntaxError
      ? 'This file is not valid JSON.'
      : (error?.message || 'Could not import the backup.');

    toast(message);
  } finally {
    input.value = '';
  }
}

function clearAllData() {
  if (
    !confirm(
      'Delete ALL locally saved BrainLoop decks, cards and progress? Export a backup first if you want to keep them.'
    )
  ) {
    return;
  }

  state = { version: 1, decks: [], cards: [] };
  session = null;
  deckSearch = '';

  const saved = save();

  navigate('#dashboard');

  if (saved) toast('Local data cleared.');
}

/* -------------------------------------------------------------------------- */
/* Event delegation                                                            */
/* -------------------------------------------------------------------------- */

document.addEventListener('click', (event) => {
  const eventTarget = event.target instanceof Element
    ? event.target
    : null;

  if (!eventTarget) return;

  const target = eventTarget.closest('[data-action]');

  if (!target) {
    if (eventTarget.id === 'modalScreen') {
      closeModal();
    }

    return;
  }

  const { action, id } = target.dataset;

  switch (action) {
    case 'new-deck':
      deckForm();
      break;

    case 'edit-deck':
      deckForm(id);
      break;

    case 'new-card':
      cardForm(id);
      break;

    case 'edit-card': {
      const card = getCard(id);
      if (card) cardForm(card.deckId, id);
      break;
    }

    case 'delete-card':
      deleteCard(id);
      break;

    case 'delete-deck':
      deleteDeck(id);
      break;

    case 'open-deck':
      navigate(`#deck/${encodeURIComponent(id)}`);
      break;

    case 'go-decks':
      navigate('#decks');
      break;

    case 'go-review':
      navigate('#review');
      break;

    case 'start-deck':
      session = null;
      beginReview(id);
      break;

    case 'begin-review': {
      const selector = $('#reviewDeck');
      if (selector) beginReview(selector.value);
      break;
    }

    case 'flip':
      toggleStudyCard();
      break;

    case 'grade':
      gradeCard(target.dataset.grade);
      break;

    case 'end-review':
    case 'finish-review':
      session = null;
      navigate('#dashboard');
      break;

    case 'close-modal':
      closeModal();
      break;

    case 'export':
      exportBackup();
      break;

    case 'clear-all':
      clearAllData();
      break;

    default:
      break;
  }
});

document.addEventListener('input', (event) => {
  if (
    event.target instanceof HTMLInputElement &&
    event.target.id === 'deckSearch'
  ) {
    deckSearch = event.target.value;
    updateDeckSearch();
  }
});

document.addEventListener('change', (event) => {
  if (
    event.target instanceof HTMLInputElement &&
    event.target.id === 'backupInput'
  ) {
    importBackup(event.target);
  }
});

/* -------------------------------------------------------------------------- */
/* Keyboard interactions                                                       */
/* -------------------------------------------------------------------------- */

document.addEventListener('keydown', (event) => {
  const modalScreen = $('#modalScreen');

  if (modalScreen) {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
      return;
    }

    trapModalFocus(event);
    return;
  }

  if (
    event.key === 'Escape' &&
    $('#sidebar')?.classList.contains('is-open')
  ) {
    event.preventDefault();
    openMobile(false, { restoreFocus: true });
    return;
  }

  if (
    event.repeat ||
    event.altKey ||
    event.ctrlKey ||
    event.metaKey ||
    route().page !== 'review' ||
    !session ||
    !session.queue.length
  ) {
    return;
  }

  const activeElement = document.activeElement;

  const isTextEntry =
    activeElement?.matches?.(
      'input, textarea, select, [contenteditable="true"]'
    ) ||
    activeElement?.isContentEditable;

  if (isTextEntry) return;

  if (event.code === 'Space') {
    // Native buttons already handle Space with their normal click behavior.
    if (activeElement?.matches?.('button, a[href]')) return;

    event.preventDefault();
    toggleStudyCard();
    return;
  }

  if (session.flipped) {
    const grades = {
      Digit1: 'again',
      Numpad1: 'again',
      Digit2: 'good',
      Numpad2: 'good',
      Digit3: 'easy',
      Numpad3: 'easy'
    };

    const grade = grades[event.code];

    if (grade) {
      event.preventDefault();
      gradeCard(grade);
    }
  }
});

/* -------------------------------------------------------------------------- */
/* Startup and navigation                                                      */
/* -------------------------------------------------------------------------- */

window.addEventListener('hashchange', () => {
  render();
  window.scrollTo({ top: 0, behavior: 'auto' });
  focusElement($('#appContent'));
});

$('#menuToggle')?.addEventListener('click', () => {
  const isOpen = $('#sidebar')?.classList.contains('is-open');

  openMobile(!isOpen, {
    focusNavigation: !isOpen
  });
});

$('#mobileOverlay')?.addEventListener('click', () => {
  openMobile(false, { restoreFocus: true });
});

window.addEventListener('resize', () => {
  if (window.matchMedia('(min-width: 771px)').matches) {
    openMobile(false);
  }
});

const yearElement = $('#year');

if (yearElement) {
  yearElement.textContent = String(new Date().getFullYear());
}

render();

if (initialStorageWarning) {
  updateStorageStatus(false, 'Data needs attention');
  toast(initialStorageWarning);
}
